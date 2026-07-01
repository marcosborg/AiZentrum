<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZcmPendingAd;
use App\Models\ZcmPendingAdSyncLog;
use App\Services\ZcmAdImageService;
use App\Services\ZcmAdPipelineService;
use App\Services\ZcmAdPrestashopDraftService;
use App\Services\ZcmPendingAdSyncService;
use App\Services\ZcmService;
use Gate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ZcmPendingAdController extends Controller
{
    public function index(Request $request, ZcmService $zcm)
    {
        $this->authorizeZcmPendingAds();

        $perPage = (int) $request->input('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $ads = $this->pendingAdsQuery($request)
            ->latest()
            ->paginate($perPage)
            ->appends($request->query());

        $syncLogs = ZcmPendingAdSyncLog::query()
            ->latest('ran_at')
            ->limit(10)
            ->get();

        $adsConfigured = $zcm->adsConfigured();

        return view('admin.zcm.pending-ads', compact('ads', 'syncLogs', 'adsConfigured'));
    }

    public function export(Request $request): RedirectResponse
    {
        $this->authorizeZcmPendingAds();

        $filename = 'zcm-pending-ads-' . now()->format('Ymd-His') . '.xlsx';
        $directory = public_path('exports');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        $rows = [[
            'ID local',
            'ID ZCM',
            'Reference',
            'Title',
            'Description',
            'Price',
            'Category',
            'Brand model',
            'Requested by',
            'Status ZCM',
            'Sync status',
            'Pipeline status',
            'Review status',
            'Prestashop draft name',
            'Prestashop category',
            'Prestashop price',
            'Created ZCM',
            'Updated ZCM',
            'Created local',
            'Updated local',
        ]];

        $hasEnrichments = $this->hasZcmPendingAdEnrichmentsTable();

        $this->pendingAdsQuery($request)
            ->when($hasEnrichments, fn ($query) => $query->with('enrichment'))
            ->orderBy('id')
            ->chunkById(200, function ($ads) use (&$rows, $hasEnrichments) {
                foreach ($ads as $ad) {
                    $draft = $hasEnrichments
                        ? data_get($ad->enrichment?->technical_data, 'prestashop_draft', [])
                        : [];

                    $rows[] = [
                        $ad->id,
                        $ad->zcmanager_ad_id,
                        $ad->reference,
                        $ad->title,
                        $ad->description,
                        $ad->price,
                        $ad->category,
                        data_get($ad->brand_model_data, 'manufacturer') ?: $ad->brand_model,
                        data_get($ad->requested_by_data, 'name') ?: $ad->requested_by,
                        $ad->status,
                        $ad->sync_status,
                        $ad->pipeline_status_label,
                        $ad->review_status_label,
                        data_get($draft, 'name'),
                        trim((string) data_get($draft, 'prestashop_category_id') . ' ' . (string) data_get($draft, 'prestashop_category_name')),
                        data_get($draft, 'price'),
                        optional($ad->zcmanager_created_at)->format('Y-m-d H:i:s'),
                        optional($ad->zcmanager_updated_at)->format('Y-m-d H:i:s'),
                        optional($ad->created_at)->format('Y-m-d H:i:s'),
                        optional($ad->updated_at)->format('Y-m-d H:i:s'),
                    ];
                }
            });

        $this->writeXlsx($path, $rows);

        return redirect()
            ->route('admin.zcm.pending-ads.index', $request->query())
            ->with('message', 'Excel gerado com sucesso.')
            ->with('export_url', asset('exports/' . $filename))
            ->with('export_filename', $filename);
    }

    public function show(ZcmPendingAd $pendingAd)
    {
        $this->authorizeZcmPendingAds();

        $relations = [];

        if ($this->hasZcmPendingAdEnrichmentsTable()) {
            $relations[] = 'enrichment';
        } else {
            $pendingAd->setRelation('enrichment', null);
        }

        if ($this->hasZcmPendingAdPipelineEventsTable()) {
            $relations[] = 'pipelineEvents.creator';
        } else {
            $pendingAd->setRelation('pipelineEvents', collect());
        }

        if ($relations !== []) {
            $pendingAd->load($relations);
        }

        return view('admin.zcm.pending-ad-show', compact('pendingAd'));
    }

    public function sync(Request $request, ZcmPendingAdSyncService $syncService): RedirectResponse
    {
        $this->authorizeZcmPendingAds();

        $result = $syncService->sync($request->only(['reference', 'user_id', 'from', 'per_page']), auth()->id());

        if ($result['errors'] !== []) {
            return redirect()
                ->route('admin.zcm.pending-ads.index')
                ->with('error', implode(' ', $result['errors']));
        }

        return redirect()
            ->route('admin.zcm.pending-ads.index')
            ->with('message', $result['imported'] . ' anuncios pendentes sincronizados.');
    }

    public function runStage(Request $request, ZcmPendingAd $pendingAd, ZcmAdPipelineService $pipeline): RedirectResponse
    {
        $this->authorizeZcmPendingAds();

        $validated = $request->validate([
            'stage' => 'required|in:research,analysis,images,seo,publishing',
        ]);

        if ($validated['stage'] === 'publishing' && Gate::denies('zcm_pending_ad_export') && Gate::denies('zcm_access')) {
            abort(Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        $event = $pipeline->runStage($pendingAd, $validated['stage'], auth()->id());

        if ($event->status === 'failed') {
            return redirect()
                ->route('admin.zcm.pending-ads.show', $pendingAd)
                ->with('error', $event->error);
        }

        return redirect()
            ->route('admin.zcm.pending-ads.show', $pendingAd)
            ->with('message', 'Etapa executada com sucesso.');
    }

    public function recreateImage(Request $request, ZcmPendingAd $pendingAd, ZcmAdImageService $images, ZcmAdPipelineService $pipeline)
    {
        $this->authorizeZcmPendingAdReview();
        @set_time_limit(240);

        $validated = $request->validate([
            'image_url' => 'required|url|max:2048',
        ]);

        try {
            $generated = $images->recreateWithAi($pendingAd, $validated['image_url']);
            $pipeline->recordEvent($pendingAd, 'images', 'ai_generated', [
                'original_url' => $validated['image_url'],
            ], [
                'generated_url' => $generated['url'] ?? null,
                'model' => $generated['model'] ?? null,
            ], null, auth()->id());
        } catch (\Throwable $e) {
            $pipeline->recordEvent($pendingAd, 'images', 'ai_generation_failed', [
                'original_url' => $validated['image_url'],
            ], null, $e->getMessage(), auth()->id());

            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return redirect()
                ->route('admin.zcm.pending-ads.show', $pendingAd)
                ->with('error', $e->getMessage());
        }

        if ($this->wantsJson($request)) {
            return response()->json([
                'message' => 'Imagem recriada com IA.',
                'image' => $this->imagePayload($generated),
            ]);
        }

        return redirect()
            ->route('admin.zcm.pending-ads.show', $pendingAd)
            ->with('message', 'Imagem recriada com IA.');
    }

    public function deleteGeneratedImage(Request $request, ZcmPendingAd $pendingAd, ZcmAdImageService $images, ZcmAdPipelineService $pipeline)
    {
        $this->authorizeZcmPendingAdReview();

        $validated = $request->validate([
            'image_url' => 'required|string|max:2048',
        ]);

        try {
            $deleted = $images->deleteAiGenerated($pendingAd, $validated['image_url']);
            $pipeline->recordEvent($pendingAd, 'images', 'ai_deleted', [
                'image_url' => $validated['image_url'],
            ], [
                'deleted_url' => $deleted['url'] ?? null,
                'storage_path' => $deleted['storage_path'] ?? null,
            ], null, auth()->id());
        } catch (\Throwable $e) {
            $pipeline->recordEvent($pendingAd, 'images', 'ai_delete_failed', [
                'image_url' => $validated['image_url'],
            ], null, $e->getMessage(), auth()->id());

            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return redirect()
                ->route('admin.zcm.pending-ads.show', $pendingAd)
                ->with('error', $e->getMessage());
        }

        if ($this->wantsJson($request)) {
            return response()->json([
                'message' => 'Imagem IA eliminada.',
                'image' => $this->imagePayload($deleted),
            ]);
        }

        return redirect()
            ->route('admin.zcm.pending-ads.show', $pendingAd)
            ->with('message', 'Imagem IA eliminada.');
    }

    public function generatePrestashopDraft(
        ZcmPendingAd $pendingAd,
        ZcmAdPrestashopDraftService $drafts,
        ZcmAdPipelineService $pipeline
    ): RedirectResponse {
        $this->authorizeZcmPendingAdReview();

        $draft = $drafts->generate($pendingAd);
        $drafts->store($pendingAd, $draft);

        $pipeline->recordEvent($pendingAd, 'prestashop_draft', 'generated', [
            'ad_id' => $pendingAd->id,
            'reference' => $pendingAd->reference,
        ], [
            'name' => $draft['name'] ?? null,
            'price' => $draft['price'] ?? null,
            'currency' => $draft['currency'] ?? null,
            'images_count' => count($draft['images'] ?? []),
        ], null, auth()->id());

        return redirect()
            ->route('admin.zcm.pending-ads.show', $pendingAd)
            ->with('message', 'Rascunho Prestashop gerado para edicao.');
    }

    public function savePrestashopDraft(
        Request $request,
        ZcmPendingAd $pendingAd,
        ZcmAdPrestashopDraftService $drafts,
        ZcmAdPipelineService $pipeline
    ): RedirectResponse {
        $this->authorizeZcmPendingAdReview();

        $validated = $request->validate([
            'name' => 'required|string|max:128',
            'reference' => 'required|string|max:191',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:8',
            'quantity' => 'nullable|integer|min:0|max:999999',
            'condition' => 'nullable|string|max:32',
            'category' => 'nullable|string|max:191',
            'prestashop_category_id' => 'nullable|integer|min:1',
            'manufacturer' => 'nullable|string|max:191',
            'model' => 'nullable|string|max:191',
            'short_description' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:20000',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:170',
            'link_rewrite' => 'nullable|string|max:191',
            'approval_notes' => 'nullable|string|max:5000',
            'translations' => 'nullable|array',
            'translations.*.name' => 'nullable|string|max:128',
            'translations.*.short_description' => 'nullable|string|max:2000',
            'translations.*.description' => 'nullable|string|max:20000',
            'translations.*.meta_title' => 'nullable|string|max:70',
            'translations.*.meta_description' => 'nullable|string|max:170',
            'translations.*.link_rewrite' => 'nullable|string|max:191',
        ]);

        $draft = $drafts->save($pendingAd, $validated);

        $pipeline->recordEvent($pendingAd, 'prestashop_draft', 'saved', [
            'ad_id' => $pendingAd->id,
            'reference' => $pendingAd->reference,
        ], [
            'name' => $draft['name'] ?? null,
            'price' => $draft['price'] ?? null,
            'currency' => $draft['currency'] ?? null,
        ], null, auth()->id());

        return redirect()
            ->route('admin.zcm.pending-ads.show', $pendingAd)
            ->with('message', 'Rascunho Prestashop guardado.');
    }

    public function generatedImage(ZcmPendingAd $pendingAd, string $filename)
    {
        $this->authorizeZcmPendingAds();

        $filename = basename($filename);
        $path = 'zcm-ai-images/' . $pendingAd->id . '/' . $filename;

        if (!Storage::disk('public')->exists($path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    public function approve(ZcmPendingAd $pendingAd, ZcmAdPipelineService $pipeline): RedirectResponse
    {
        $this->authorizeZcmPendingAdReview();

        $pipeline->approve($pendingAd, auth()->id());

        return redirect()
            ->route('admin.zcm.pending-ads.show', $pendingAd)
            ->with('message', 'Anuncio aprovado para exportacao.');
    }

    public function reject(Request $request, ZcmPendingAd $pendingAd, ZcmAdPipelineService $pipeline): RedirectResponse
    {
        $this->authorizeZcmPendingAdReview();

        $pipeline->reject($pendingAd, (string) $request->input('reason', ''), auth()->id());

        return redirect()
            ->route('admin.zcm.pending-ads.show', $pendingAd)
            ->with('message', 'Anuncio rejeitado.');
    }

    public function requestChanges(Request $request, ZcmPendingAd $pendingAd, ZcmAdPipelineService $pipeline): RedirectResponse
    {
        $this->authorizeZcmPendingAdReview();

        $pipeline->requestChanges($pendingAd, (string) $request->input('reason', ''), auth()->id());

        return redirect()
            ->route('admin.zcm.pending-ads.show', $pendingAd)
            ->with('message', 'Anuncio marcado para alteracoes.');
    }

    private function authorizeZcmPendingAds(): void
    {
        if (Gate::denies('zcm_pending_ad_access') && Gate::denies('zcm_access')) {
            abort(Response::HTTP_FORBIDDEN, '403 Forbidden');
        }
    }

    private function authorizeZcmPendingAdReview(): void
    {
        if (Gate::denies('zcm_pending_ad_review') && Gate::denies('zcm_access')) {
            abort(Response::HTTP_FORBIDDEN, '403 Forbidden');
        }
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax();
    }

    private function hasZcmPendingAdEnrichmentsTable(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = Schema::hasTable('zcm_pending_ad_enrichments');
        }

        return $exists;
    }

    private function hasZcmPendingAdPipelineEventsTable(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = Schema::hasTable('zcm_pending_ad_pipeline_events');
        }

        return $exists;
    }

    private function pendingAdsQuery(Request $request)
    {
        return ZcmPendingAd::query()
            ->when($request->filled('reference'), function ($query) use ($request) {
                $reference = trim((string) $request->input('reference'));

                $query->where(function ($query) use ($reference) {
                    $query->where('reference', 'like', '%' . $reference . '%')
                        ->orWhere('title', 'like', '%' . $reference . '%')
                        ->orWhere('description', 'like', '%' . $reference . '%')
                        ->orWhere('zcmanager_ad_id', $reference);
                });
            })
            ->when($request->filled('user_id'), function ($query) use ($request) {
                $userId = trim((string) $request->input('user_id'));

                $query->where(function ($query) use ($userId) {
                    $query->where('requested_by', 'like', '%' . $userId . '%')
                        ->orWhere('raw_payload->requested_by->id', $userId);
                });
            })
            ->when($request->filled('from'), function ($query) use ($request) {
                $query->whereDate('zcmanager_created_at', '>=', $request->input('from'));
            });
    }

    private function writeXlsx(string $path, array $rows): void
    {
        $sheetRows = '';

        foreach ($rows as $rowIndex => $values) {
            $sheetRows .= '<row r="' . ($rowIndex + 1) . '">';

            foreach (array_values($values) as $columnIndex => $value) {
                $cell = $this->xlsxColumnName($columnIndex + 1) . ($rowIndex + 1);
                $sheetRows .= '<c r="' . $cell . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $this->xmlValue($value)
                    . '</t></is></c>';
            }

            $sheetRows .= '</row>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '</worksheet>';

        $createdAt = now()->toIso8601String();
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
                . '</Relationships>',
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
                . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
                . '<Application>Zentrum AI</Application></Properties>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
                . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
                . 'xmlns:dcterms="http://purl.org/dc/terms/" '
                . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
                . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                . '<dc:title>Anuncios pendentes</dc:title>'
                . '<dc:creator>Zentrum AI</dc:creator>'
                . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $createdAt . '</dcterms:created>'
                . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $createdAt . '</dcterms:modified>'
                . '</cp:coreProperties>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="Anuncios pendentes" sheetId="1" r:id="rId1"/></sheets>'
                . '</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
                . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
                . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
                . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                . '</styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ];

        $this->writeZipStore($path, $files);
    }

    private function writeZipStore(string $path, array $files): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Nao foi possivel criar o ficheiro Excel.');
        }

        $centralDirectory = '';

        foreach ($files as $name => $contents) {
            $offset = ftell($handle);
            $size = strlen($contents);
            $crc = crc32($contents);

            fwrite($handle, pack('V', 0x04034b50));
            fwrite($handle, pack('v', 20));
            fwrite($handle, pack('v', 0));
            fwrite($handle, pack('v', 0));
            fwrite($handle, pack('v', 0));
            fwrite($handle, pack('v', 0));
            fwrite($handle, pack('V', $crc));
            fwrite($handle, pack('V', $size));
            fwrite($handle, pack('V', $size));
            fwrite($handle, pack('v', strlen($name)));
            fwrite($handle, pack('v', 0));
            fwrite($handle, $name);
            fwrite($handle, $contents);

            $centralDirectory .= pack('V', 0x02014b50);
            $centralDirectory .= pack('v', 20);
            $centralDirectory .= pack('v', 20);
            $centralDirectory .= pack('v', 0);
            $centralDirectory .= pack('v', 0);
            $centralDirectory .= pack('v', 0);
            $centralDirectory .= pack('v', 0);
            $centralDirectory .= pack('V', $crc);
            $centralDirectory .= pack('V', $size);
            $centralDirectory .= pack('V', $size);
            $centralDirectory .= pack('v', strlen($name));
            $centralDirectory .= pack('v', 0);
            $centralDirectory .= pack('v', 0);
            $centralDirectory .= pack('v', 0);
            $centralDirectory .= pack('v', 0);
            $centralDirectory .= pack('V', 0);
            $centralDirectory .= pack('V', $offset);
            $centralDirectory .= $name;
        }

        $centralOffset = ftell($handle);
        fwrite($handle, $centralDirectory);
        $centralSize = strlen($centralDirectory);

        fwrite($handle, pack('V', 0x06054b50));
        fwrite($handle, pack('v', 0));
        fwrite($handle, pack('v', 0));
        fwrite($handle, pack('v', count($files)));
        fwrite($handle, pack('v', count($files)));
        fwrite($handle, pack('V', $centralSize));
        fwrite($handle, pack('V', $centralOffset));
        fwrite($handle, pack('v', 0));

        fclose($handle);
    }

    private function xlsxColumnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xmlValue($value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $value = preg_replace('/[^\P{C}\t\n\r]/u', '', (string) $value);
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function imagePayload(array $image): array
    {
        return [
            'source' => $image['source'] ?? null,
            'url' => $image['url'] ?? null,
            'original_url' => $image['original_url'] ?? null,
            'model' => $image['model'] ?? null,
            'created_at' => $image['created_at'] ?? null,
        ];
    }
}
