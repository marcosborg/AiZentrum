<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyMoloniSuplierInvoiceRequest;
use App\Http\Requests\StoreMoloniSuplierInvoiceRequest;
use App\Http\Requests\UpdateMoloniSuplierInvoiceRequest;
use App\Models\MoloniSuplierInvoice;
use App\Models\User;
use App\Services\MoloniService;
use Gate;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class MoloniSuplierInvoiceController extends Controller
{
    use MediaUploadingTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('moloni_suplier_invoice_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = MoloniSuplierInvoice::with(['user'])->select(sprintf('%s.*', (new MoloniSuplierInvoice)->table));
            $table = DataTables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate      = 'moloni_suplier_invoice_show';
                $editGate      = 'moloni_suplier_invoice_edit';
                $deleteGate    = 'moloni_suplier_invoice_delete';
                $crudRoutePart = 'moloni-suplier-invoices';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', function ($row) {
                return $row->id ?: '';
            });

            $table->addColumn('user_name', function ($row) {
                return $row->user ? $row->user->name : '';
            });

            $table->editColumn('photo', function ($row) {
                $photos = $row->getMedia('photo');
                if ($photos->isEmpty()) {
                    return '';
                }

                $first = $photos->first();
                $count = $photos->count();
                $badge = $count > 1 ? '<br><small>' . $count . ' pages</small>' : '';

                return sprintf(
                    '<a href="%s" target="_blank"><img src="%s" width="50px" height="50px"></a>%s',
                    $first->getUrl(),
                    $first->getUrl('thumb'),
                    $badge
                );
            });

            $table->editColumn('data', function ($row) {
                return $row->data ?: '';
            });

            $table->editColumn('handled', function ($row) {
                return '<input type="checkbox" disabled ' . ($row->handled ? 'checked' : null) . '>';
            });

            $table->rawColumns(['actions', 'placeholder', 'user', 'photo', 'handled']);

            return $table->make(true);
        }

        return view('admin.moloniSuplierInvoices.index');
    }

    public function create()
    {
        abort_if(Gate::denies('moloni_suplier_invoice_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.moloniSuplierInvoices.create', compact('users'));
    }

    public function store(StoreMoloniSuplierInvoiceRequest $request)
    {
        $moloniSuplierInvoice = MoloniSuplierInvoice::create($request->all());

        $photoNames = $this->extractPhotoNamesFromRequest($request);
        $this->attachPhotosFromTmp($moloniSuplierInvoice, $photoNames);

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $moloniSuplierInvoice->id]);
        }

        $imageUrls = $this->getInvoiceImageUrls($moloniSuplierInvoice);
        if (!empty($imageUrls)) {
            $json = $this->analyzeInvoiceImage($imageUrls);
            $normalizedJson = $this->normalizeInvoiceJson($json);
            $moloniSuplierInvoice->update(['data' => json_encode($normalizedJson)]);
        }

        return redirect()->route('admin.moloni-suplier-invoices.edit', [$moloniSuplierInvoice->id]);
    }

    public function edit(MoloniSuplierInvoice $moloniSuplierInvoice)
    {
        abort_if(Gate::denies('moloni_suplier_invoice_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $moloniSuplierInvoice->load('user');

        return view('admin.moloniSuplierInvoices.edit', compact('moloniSuplierInvoice', 'users'));
    }

    public function update(UpdateMoloniSuplierInvoiceRequest $request, MoloniSuplierInvoice $moloniSuplierInvoice)
    {
        $moloniSuplierInvoice->update($request->all());

        $photoNames = $this->extractPhotoNamesFromRequest($request);
        $this->syncPhotos($moloniSuplierInvoice, $photoNames);

        $imageUrls = $this->getInvoiceImageUrls($moloniSuplierInvoice);
        if (!empty($imageUrls)) {
            $json = $this->analyzeInvoiceImage($imageUrls);
            $normalizedJson = $this->normalizeInvoiceJson($json);
            $moloniSuplierInvoice->update(['data' => json_encode($normalizedJson)]);
        }

        return redirect()->route('admin.moloni-suplier-invoices.edit', [$moloniSuplierInvoice->id]);
    }

    public function show(MoloniSuplierInvoice $moloniSuplierInvoice)
    {
        abort_if(Gate::denies('moloni_suplier_invoice_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $moloniSuplierInvoice->load('user');

        return view('admin.moloniSuplierInvoices.show', compact('moloniSuplierInvoice'));
    }

    public function destroy(MoloniSuplierInvoice $moloniSuplierInvoice)
    {
        abort_if(Gate::denies('moloni_suplier_invoice_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $moloniSuplierInvoice->delete();

        return back();
    }

    public function massDestroy(MassDestroyMoloniSuplierInvoiceRequest $request)
    {
        $moloniSuplierInvoices = MoloniSuplierInvoice::find(request('ids'));

        foreach ($moloniSuplierInvoices as $moloniSuplierInvoice) {
            $moloniSuplierInvoice->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('moloni_suplier_invoice_create') && Gate::denies('moloni_suplier_invoice_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new MoloniSuplierInvoice();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }

    private function normalizeInvoiceJson(array $raw): array
    {
        $supplierName = 'DESCONHECIDO';
        $supplierNif = '';

        if (isset($raw['supplier'])) {
            $supplier = $raw['supplier'];

            if (is_string($supplier)) {
                $supplierName = $supplier;
            } elseif (is_array($supplier)) {
                if (isset($supplier['name'])) {
                    if (is_string($supplier['name'])) {
                        $supplierName = $supplier['name'];
                    } elseif (is_array($supplier['name'])) {
                        $supplierName = $supplier['name']['name'] ?? $supplierName;
                        $supplierNif = $supplier['name']['NIF'] ?? $supplierNif;
                    }
                }

                if (isset($supplier['NIF'])) {
                    $supplierNif = $supplier['NIF'];
                }
                if (isset($supplier['nif'])) {
                    $supplierNif = $supplier['nif'];
                }
            }
        }

        return [
            'invoice_date' => $raw['invoice_date'] ?? now()->toDateString(),
            'invoice_number' => $raw['invoice_number'] ?? 'SEM-NUMERO',
            'supplier' => [
                'name' => $supplierName,
                'NIF' => $supplierNif,
            ],
            'buyer' => [
                'name' => 'AIRBAGS-ZENTRUM, LDA',
                'NIF' => '508263069',
            ],
            'items' => collect($raw['items'] ?? [])->map(function ($item) {
                return [
                    'description' => $item['description'] ?? '',
                    'brand' => $item['brand'] ?? '',
                    'reference' => $item['reference'] ?? '',
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'vat' => (float) ($item['vat'] ?? 23),
                    'total' => (float) ($item['total'] ?? 0),
                ];
            })->toArray(),
            'totals' => [
                'subtotal' => (float) ($raw['totals']['subtotal'] ?? 0),
                'tax' => (float) ($raw['totals']['tax'] ?? 0),
                'total' => (float) ($raw['totals']['total'] ?? 0),
            ],
            'payment' => [
                'terms' => $raw['payment']['terms'] ?? '30 dias',
            ],
        ];
    }

    function parseEuroNumber(string $number): float
    {
        return (float) str_replace(',', '.', str_replace(' ', '', $number));
    }

    private function analyzeInvoiceImage($imageUrls): array
    {
        $imageUrls = is_array($imageUrls) ? $imageUrls : [$imageUrls];
        $imageUrls = array_values(array_filter($imageUrls));

        $content = [[
            'type' => 'text',
            'text' => 'Analisa estas imagens (podem ser varias paginas da mesma fatura) e responde exclusivamente com um objeto JSON (sem qualquer texto explicativo). Junta toda a informacao numa unica fatura. O JSON deve conter os seguintes campos: invoice_date, invoice_number, supplier (com name e nif), buyer (com name e nif), items, totals, taxes (opcional) e payment (opcional). O fornecedor esta geralmente a esquerda e o comprador a direita, mas em muitos casos o NIF a esquerda pertence ao comprador (neste caso, 508263069). Se encontrares este NIF (508263069), considera que e o comprador (buyer). Se nao conseguires identificar com clareza o NIF do fornecedor, define supplier.nif como 999999990. Extrai os produtos com os campos: reference, description, brand, quantity, unit_price, vat e total. Se nao conseguires extrair um item completo, omite-o. O campo totals deve conter subtotal, tax, discount (se aplicavel) e total.'
        ]];

        foreach ($imageUrls as $imageUrl) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $imageUrl],
            ];
        }

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
        ]);

        $content = $response->choices[0]->message->content ?? '{}';

        \Log::debug('GPT response content: ' . $content);

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $content = $matches[0];
        }

        $json = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('Falha ao fazer json_decode.', [
                'error' => json_last_error_msg(),
                'content' => $content,
            ]);

            throw new \RuntimeException('A resposta do GPT nao e um JSON valido.');
        }

        return $json;
    }

    private function extractPhotoNamesFromRequest(Request $request): array
    {
        $photos = $request->input('photos', []);

        if (!is_array($photos)) {
            $photos = [$photos];
        }

        if ($request->filled('photo')) {
            $photos[] = $request->input('photo');
        }

        $photos = array_map(static fn($name) => basename((string) $name), $photos);
        $photos = array_filter($photos);

        return array_values(array_unique($photos));
    }

    private function attachPhotosFromTmp(MoloniSuplierInvoice $invoice, array $photoNames): void
    {
        foreach ($photoNames as $photoName) {
            $path = storage_path('tmp/uploads/' . $photoName);
            if (file_exists($path)) {
                $invoice->addMedia($path)->toMediaCollection('photo');
            }
        }
    }

    private function syncPhotos(MoloniSuplierInvoice $invoice, array $photoNames): void
    {
        $currentMedia = $invoice->getMedia('photo');
        $currentNames = $currentMedia->pluck('file_name')->all();

        foreach ($currentMedia as $media) {
            if (!in_array($media->file_name, $photoNames, true)) {
                $media->delete();
            }
        }

        foreach ($photoNames as $photoName) {
            if (!in_array($photoName, $currentNames, true)) {
                $path = storage_path('tmp/uploads/' . $photoName);
                if (file_exists($path)) {
                    $invoice->addMedia($path)->toMediaCollection('photo');
                }
            }
        }
    }

    private function getInvoiceImageUrls(MoloniSuplierInvoice $invoice): array
    {
        return $invoice->getMedia('photo')
            ->map(static fn($media) => $media->getUrl())
            ->filter()
            ->values()
            ->all();
    }

    public function launchToMoloni($moloni_suplier_invoice_id)
    {
        $moloni_suplier_invoice = MoloniSuplierInvoice::findOrFail($moloni_suplier_invoice_id);
        $data = json_decode($moloni_suplier_invoice->data);
        $supplierName = $data->supplier->name ?? null;

        $moloni = new MoloniService();

        $supplier = null;
        if ($supplierName) {
            $results = $moloni->getSuppliersByName($supplierName);

            if (count($results) > 0) {
                $supplier = [
                    'supplier_id' => $results[0]['supplier_id'],
                    'name' => $results[0]['name']
                ];
            }
        }

        $countries = app(MoloniService::class)->getCountries();

        return view('admin.moloniSuplierInvoices.launch_to_moloni', compact('moloni_suplier_invoice', 'data', 'supplier', 'countries'));
    }

    public function getSuppliersByName(Request $request, MoloniService $moloni)
    {
        $name = $request->get('term');

        if (!$name) {
            return response()->json([]);
        }

        $results = $moloni->getSuppliersByName($name);

        $formatted = collect($results)
            ->filter(fn($supplier) => isset($supplier['supplier_id'], $supplier['name']))
            ->map(fn($supplier) => [
                'id' => $supplier['supplier_id'],
                'text' => $supplier['name'],
            ])
            ->values();

        return response()->json($formatted);
    }

    public function createSupplier(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'vat' => 'nullable|string',
            'address' => 'nullable|string',
            'zip_code' => 'nullable|string',
            'city' => 'nullable|string',
            'country_id' => 'required|integer',
        ]);

        if ((int) $validated['country_id'] === 1) {
            if (!empty($validated['zip_code']) && !preg_match('/^\d{4}-\d{3}$/', $validated['zip_code'])) {
                return response()->json(['error' => 'Codigo postal invalido. Deve ser no formato NNNN-NNN.'], 422);
            }
        }

        $validated['number'] = rand(100000000, 999999999);

        try {
            $moloni = new MoloniService();
            $supplier = $moloni->createSupplier($validated);

            return response()->json([
                'supplier_id' => $supplier['supplier_id'],
                'name' => $supplier['name'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function syncInvoice(Request $request)
    {
        $data = $request->input('data');

        $validated = validator($data, [
            'invoice_date'   => 'required|date',
            'invoice_number' => 'required|string',
            'supplier_id'    => 'required|string',
            'items'          => 'required|array|min:1',
            'items.*.reference'   => 'required|string',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric',
            'items.*.unit_price'  => 'required|numeric',
            'items.*.vat'         => 'required|numeric',
            'items.*.total'       => 'required|numeric',
        ])->validate();

        $invoice = MoloniSuplierInvoice::findOrFail($request->input('moloni_suplier_invoice_id'));

        $moloni = new MoloniService();

        foreach ($validated['items'] as &$item) {
            $existing = $moloni->searchProductByReference($item['reference']);

            if (empty($existing)) {
                $created = $moloni->insertProduct([
                    'reference'   => $item['reference'],
                    'description' => $item['description'] ?? $item['reference'],
                    'unit_price'  => $item['unit_price'],
                    'unit_id'     => $item['unit_id'] ?? 86267,
                    'vat'         => $item['vat'],
                ]);
                $item['product_id'] = $created['product_id'];
            } else {
                $item['product_id'] = $existing[0]['product_id'];
            }
        }

        $moloni->insertSupplierInvoice([
            'invoice_date'   => $validated['invoice_date'],
            'invoice_number' => $validated['invoice_number'],
            'supplier_id'    => $validated['supplier_id'],
            'items'          => $validated['items'],
        ]);

        $invoice->update([
            'handled' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fatura sincronizada e marcada como tratada com sucesso.'
        ]);
    }
}
