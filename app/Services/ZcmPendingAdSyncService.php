<?php

namespace App\Services;

use App\Models\ZcmPendingAd;
use App\Models\ZcmPendingAdSyncLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ZcmPendingAdSyncService
{
    public function __construct(
        private readonly ZcmService $zcm,
        private readonly ZcmAdPipelineService $pipeline
    ) {
    }

    public function sync(array $filters = [], ?int $userId = null): array
    {
        $received = [];
        $importedIds = [];
        $importedCount = 0;
        $failed = [];
        $errors = [];
        $markResponse = null;
        $markSuccess = false;

        try {
            $payload = $this->zcm->pendingAds($filters);
            $received = $this->extractAds($payload);

            foreach ($received as $item) {
                try {
                    $ad = DB::transaction(function () use ($item, $userId) {
                        $ad = $this->storeAd($item);
                        $this->pipeline->recordEvent($ad, 'received', 'success', [
                            'zcmanager_ad_id' => Arr::get($item, 'id'),
                            'reference' => Arr::get($item, 'reference'),
                        ], [
                            'message' => 'Anuncio importado ou atualizado localmente.',
                        ], null, $userId);

                        return $ad;
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
                    $markResponse = $this->zcm->markAdsAsSent($importedIds);
                    $markSuccess = true;

                    ZcmPendingAd::whereIn('zcmanager_ad_id', $importedIds)->update([
                        'sync_status' => ZcmPendingAd::SYNC_STATUS_SENT,
                        'synced_to_zcmanager_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    ZcmPendingAd::whereIn('zcmanager_ad_id', $importedIds)->update([
                        'sync_status' => ZcmPendingAd::SYNC_STATUS_MARK_FAILED,
                        'updated_at' => now(),
                    ]);

                    $errors[] = 'Imported locally, but failed to mark as sent in ZCManager: ' . $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        $log = ZcmPendingAdSyncLog::create([
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

        return [
            'log' => $log,
            'received' => count($received),
            'imported' => $importedCount,
            'failed' => count($failed),
            'errors' => $errors,
        ];
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
            'sync_status' => ZcmPendingAd::SYNC_STATUS_IMPORTED,
            'pipeline_status' => $ad->pipeline_status ?: ZcmPendingAd::PIPELINE_STATUS_RECEIVED,
            'review_status' => $ad->review_status ?: ZcmPendingAd::REVIEW_STATUS_PENDING,
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
