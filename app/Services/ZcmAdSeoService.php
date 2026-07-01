<?php

namespace App\Services;

use App\Models\ZcmPendingAd;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ZcmAdSeoService
{
    public function generate(ZcmPendingAd $ad): array
    {
        $fallback = $this->fallbackSeo($ad);
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            return $fallback + ['ai_used' => false, 'note' => 'OPENAI_API_KEY em falta.'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(45)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.3,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Gerador SEO para ecommerce automovel. Responde apenas com JSON valido em Portugues de Portugal.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'expected_keys' => ['title', 'short_description', 'long_description', 'meta_title', 'meta_description', 'keywords', 'slug'],
                                'ad' => [
                                    'reference' => $ad->reference,
                                    'title' => $ad->title,
                                    'description' => $ad->description,
                                    'category' => $ad->category,
                                    'brand_model' => $ad->brand_model_data,
                                    'analysis' => $ad->enrichment?->ai_analysis,
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            if (!$response->ok()) {
                return $fallback + ['ai_used' => false, 'error' => 'OpenAI HTTP ' . $response->status()];
            }

            $json = json_decode(data_get($response->json(), 'choices.0.message.content', '{}'), true);

            if (!is_array($json)) {
                return $fallback + ['ai_used' => false, 'error' => 'Resposta AI nao e JSON valido.'];
            }

            return array_merge($fallback, $json, ['ai_used' => true]);
        } catch (\Throwable $e) {
            Log::error('ZCM ad SEO exception: ' . $e->getMessage(), ['ad_id' => $ad->id]);

            return $fallback + ['ai_used' => false, 'error' => $e->getMessage()];
        }
    }

    private function fallbackSeo(ZcmPendingAd $ad): array
    {
        $name = trim(implode(' ', array_filter([
            $ad->category,
            $ad->title ?: $ad->reference,
        ])));

        if ($name === '') {
            $name = 'Peca automovel ' . $ad->id;
        }

        return [
            'title' => $name,
            'short_description' => $ad->description ?: 'Peca automovel usada com referencia ' . $ad->reference . '.',
            'long_description' => $ad->description ?: 'Peca automovel usada. Confirme compatibilidade pela referencia antes da compra.',
            'meta_title' => Str::limit($name, 60, ''),
            'meta_description' => Str::limit($ad->description ?: 'Peca automovel usada disponivel para venda.', 155, ''),
            'keywords' => array_values(array_filter([$ad->reference, $ad->category, data_get($ad->brand_model_data, 'manufacturer')])),
            'slug' => Str::slug($name . ' ' . $ad->reference),
        ];
    }
}
