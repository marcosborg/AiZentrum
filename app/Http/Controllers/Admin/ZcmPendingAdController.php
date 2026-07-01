<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZcmPendingAd;
use App\Models\ZcmPendingAdSyncLog;
use App\Services\ZcmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ZcmPendingAdController extends Controller
{
    public function index(ZcmService $zcm)
    {
        $ads = ZcmPendingAd::query()
            ->latest()
            ->paginate(25);

        $syncLogs = ZcmPendingAdSyncLog::query()
            ->latest('ran_at')
            ->limit(10)
            ->get();

        $adsConfigured = $zcm->adsConfigured();

        return view('admin.zcm.pending-ads', compact('ads', 'syncLogs', 'adsConfigured'));
    }

    public function sync(Request $request, ZcmService $zcm): RedirectResponse
    {
        $filters = $request->only(['reference', 'user_id', 'from', 'per_page']);
        $received = [];
        $importedIds = [];
        $importedCount = 0;
        $failed = [];
        $errors = [];
        $markResponse = null;
        $markSuccess = false;

        try {
            $payload = $zcm->pendingAds($filters);
            $received = $this->extractAds($payload);

            foreach ($received as $item) {
                try {
                    $ad = DB::transaction(function () use ($item) {
                        return $this->storeAd($item);
                    });

                    $importedCount++;

                    if ($ad->zcmanager_ad_id) {
                        $importedIds[] = (int) $ad->zcmanager_ad_id;
                    }
                } catch (\Throwable $e) {
                    $failed[] = [
                        'id' => Arr::get($item, 'id'),
                        'reference' => Arr::get($item, 'reference'),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if ($importedIds !== []) {
                try {
                    $markResponse = $zcm->markAdsAsSent($importedIds);
                    $markSuccess = true;

                    ZcmPendingAd::whereIn('zcmanager_ad_id', $importedIds)->update([
                        'sync_status' => 'sent',
                        'synced_to_zcmanager_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    $errors[] = 'Imported locally, but failed to mark as sent in ZCManager: ' . $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        ZcmPendingAdSyncLog::create([
            'ran_at' => now(),
            'total_received' => count($received),
            'total_imported' => $importedCount,
            'total_failed' => count($failed),
            'imported_ids' => array_values(array_unique($importedIds)),
            'failed_items' => $failed,
            'errors' => $errors,
            'mark_as_sent_success' => $markSuccess,
            'mark_as_sent_response' => $markResponse,
        ]);

        if ($errors !== []) {
            return redirect()
                ->route('admin.zcm.pending-ads.index')
                ->with('error', implode(' ', $errors));
        }

        return redirect()
            ->route('admin.zcm.pending-ads.index')
            ->with('message', $importedCount . ' anuncios pendentes sincronizados.');
    }

    private function extractAds(array $payload): array
    {
        $items = Arr::get($payload, 'data', $payload);

        if (isset($items['data']) && is_array($items['data'])) {
            $items = $items['data'];
        }

        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    private function storeAd(array $item): ZcmPendingAd
    {
        $zcmanagerId = Arr::get($item, 'id');
        $reference = Arr::get($item, 'reference');

        $query = ZcmPendingAd::withTrashed();

        if (!$zcmanagerId && !$reference) {
            $ad = new ZcmPendingAd();
        } elseif ($zcmanagerId) {
            $query->where('zcmanager_ad_id', $zcmanagerId);

            if ($reference) {
                $query->orWhere('reference', $reference);
            }

            $ad = $query->first() ?: new ZcmPendingAd();
        } else {
            $ad = $query->where('reference', $reference)->first() ?: new ZcmPendingAd();
        }

        if ($ad->trashed()) {
            $ad->restore();
        }

        $ad->fill([
            'zcmanager_ad_id' => $zcmanagerId,
            'reference' => $reference,
            'title' => Arr::get($item, 'title'),
            'description' => Arr::get($item, 'description'),
            'price' => $this->normalizePrice(Arr::get($item, 'price')),
            'category' => Arr::get($item, 'category'),
            'brand_model' => $this->stringifyValue(Arr::get($item, 'brand_model')),
            'images' => $this->normalizeImages(Arr::get($item, 'images')),
            'requested_by' => $this->stringifyValue(Arr::get($item, 'requested_by')),
            'status' => Arr::get($item, 'status'),
            'zcmanager_created_at' => $this->parseDate(Arr::get($item, 'created_at')),
            'zcmanager_updated_at' => $this->parseDate(Arr::get($item, 'updated_at')),
            'raw_payload' => $item,
            'sync_status' => 'imported',
        ]);

        $ad->save();

        return $ad;
    }

    private function stringifyValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private function normalizePrice($price): ?float
    {
        if ($price === null || $price === '') {
            return null;
        }

        if (is_string($price)) {
            $price = str_replace([' ', ','], ['', '.'], $price);
        }

        return is_numeric($price) ? (float) $price : null;
    }

    private function normalizeImages($images): array
    {
        if (is_array($images)) {
            return $images;
        }

        if (is_string($images) && $images !== '') {
            $decoded = json_decode($images, true);

            return is_array($decoded) ? $decoded : [$images];
        }

        return [];
    }

    private function parseDate($date): ?Carbon
    {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
