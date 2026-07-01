<?php

namespace App\Services;

use App\Models\ZcmPendingAd;

class ZcmAdPublishingService
{
    public function prepare(ZcmPendingAd $ad): array
    {
        return [
            'status' => 'not_exported',
            'destination' => null,
            'message' => 'Destino de exportacao ainda nao configurado. Nenhuma publicacao foi executada.',
            'payload_preview' => [
                'reference' => $ad->reference,
                'title' => data_get($ad->enrichment?->seo, 'title', $ad->title),
                'description' => data_get($ad->enrichment?->seo, 'long_description', $ad->description),
                'price' => $ad->price,
                'images' => data_get($ad->enrichment?->images, 'final_images', $ad->images),
            ],
        ];
    }
}
