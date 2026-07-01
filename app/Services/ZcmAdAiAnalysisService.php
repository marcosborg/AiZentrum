<?php

namespace App\Services;

use App\Models\ZcmPendingAd;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZcmAdAiAnalysisService
{
    public function analyze(ZcmPendingAd $ad): array
    {
        $ad->loadMissing('enrichment');

        $fallback = $this->fallbackAnalysis($ad);
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            return $fallback + ['ai_used' => false, 'note' => 'OPENAI_API_KEY em falta.'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(45)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Analisa anuncios de pecas automoveis usadas para ecommerce. Responde apenas com JSON valido.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'task' => 'Produz analise tecnica e comercial para o anuncio.',
                                'expected_keys' => [
                                    'part_type',
                                    'brand',
                                    'model',
                                    'compatibilities',
                                    'missing_data',
                                    'risks',
                                    'suggestions',
                                    'technical_data',
                                    'evidence_sources',
                                    'confidence_score',
                                ],
                                'ad' => $this->adPayload($ad),
                                'research' => $ad->enrichment?->research,
                                'rules' => [
                                    'Usa a pesquisa interna como fonte prioritaria quando existir best_match.',
                                    'Se a referencia do anuncio aparecer no nome do produto encontrado, trata o match como evidencia relevante.',
                                    'Nao devolvas unknown para tipo de peca, marca ou modelo quando esses dados forem inferiveis pelo nome do produto encontrado.',
                                    'Inclui evidence_sources separando claramente fontes internal_prestashop e web_openai.',
                                    'Cada evidence_source deve ter origin, title, url, reference, match_reason e confidence_score quando disponivel.',
                                    'confidence_score deve ser inteiro entre 0 e 100.',
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            if (!$response->ok()) {
                Log::warning('ZCM ad AI analysis failed', [
                    'ad_id' => $ad->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $fallback + ['ai_used' => false, 'error' => 'OpenAI HTTP ' . $response->status()];
            }

            $content = data_get($response->json(), 'choices.0.message.content', '{}');
            $json = json_decode($content, true);

            if (!is_array($json)) {
                return $fallback + ['ai_used' => false, 'error' => 'Resposta AI nao e JSON valido.'];
            }

            $analysis = array_merge($fallback, $json, ['ai_used' => true]);

            return $this->normalizeAnalysis($this->preferKnownFallback($analysis, $fallback));
        } catch (\Throwable $e) {
            Log::error('ZCM ad AI analysis exception: ' . $e->getMessage(), ['ad_id' => $ad->id]);

            return $fallback + ['ai_used' => false, 'error' => $e->getMessage()];
        }
    }

    private function fallbackAnalysis(ZcmPendingAd $ad): array
    {
        $brandModel = $ad->brand_model_data;
        $bestMatch = $this->researchBestMatch($ad);
        $webText = collect(data_get($ad->enrichment?->research, 'web.items', []))
            ->map(fn($item) => trim((string) data_get($item, 'title') . ' ' . (string) data_get($item, 'snippet')))
            ->implode(' ');
        $bestMatchName = (string) data_get($bestMatch, 'name', '');
        $inferred = $this->inferFromText($bestMatchName . ' ' . $webText . ' ' . $ad->title . ' ' . $ad->description);
        $price = $ad->price ?: data_get($bestMatch, 'price');
        $partType = $ad->category ?: ($inferred['part_type'] ?? null);
        $brand = $brandModel['manufacturer'] ?? ($inferred['brand'] ?? null);
        $model = $brandModel['car_model'] ?? ($inferred['model'] ?? null);

        return [
            'part_type' => $partType,
            'brand' => $brand,
            'model' => $model,
            'compatibilities' => [],
            'missing_data' => array_values(array_filter([
                !$ad->description ? 'description' : null,
                !$price ? 'price' : null,
                !$partType ? 'category' : null,
                !$brand ? 'manufacturer' : null,
                !$model ? 'car_model' : null,
                empty($ad->images) ? 'images' : null,
            ])),
            'risks' => array_values(array_filter([
                !$bestMatch ? 'Sem correspondencia interna forte encontrada.' : null,
                empty($ad->images) ? 'Anuncio sem imagens de origem.' : null,
            ])),
            'suggestions' => [
                'Validar compatibilidade por referencia antes de exportar.',
                'Confirmar preco e imagens finais.',
            ],
            'technical_data' => [
                'reference' => $ad->reference,
                'brand_reference' => $brandModel['brand_reference'] ?? null,
                'manufacturer_reference' => $brandModel['manufacturer_reference'] ?? null,
                'prestashop_product_id' => data_get($bestMatch, 'id'),
                'prestashop_reference' => data_get($bestMatch, 'reference'),
                'prestashop_name' => data_get($bestMatch, 'name'),
                'prestashop_price' => data_get($bestMatch, 'price'),
                'prestashop_match_quality' => data_get($bestMatch, 'match_quality'),
                'prestashop_match_score' => data_get($bestMatch, 'match_score'),
                'web_sources' => data_get($ad->enrichment?->research, 'web.sources', []),
            ],
            'evidence_sources' => $this->evidenceSources($ad, $bestMatch),
            'confidence_score' => $this->fallbackConfidence($ad),
        ];
    }

    private function fallbackConfidence(ZcmPendingAd $ad): int
    {
        $score = 30;
        $score += $ad->reference ? 20 : 0;
        $score += $ad->description ? 15 : 0;
        $score += $ad->price ? 10 : 0;
        $score += $ad->category ? 10 : 0;
        $score += !empty($ad->images) ? 15 : 0;
        $score += (int) data_get($this->researchBestMatch($ad), 'match_score', 0) >= 70 ? 25 : 0;
        $score += data_get($ad->enrichment?->research, 'web.found') ? 10 : 0;

        return min(100, $score);
    }

    private function adPayload(ZcmPendingAd $ad): array
    {
        return [
            'id' => $ad->id,
            'zcmanager_ad_id' => $ad->zcmanager_ad_id,
            'reference' => $ad->reference,
            'title' => $ad->title,
            'description' => $ad->description,
            'price' => $ad->price,
            'category' => $ad->category,
            'brand_model' => $ad->brand_model_data,
            'requested_by' => $ad->requested_by_data,
            'raw_payload' => $ad->raw_payload,
        ];
    }

    private function inferFromText(string $text): array
    {
        $normalized = mb_strtolower($text);
        $result = [];

        if (str_contains($normalized, 'abs')) {
            $result['part_type'] = 'Modulo ABS';
        }

        $brands = [
            'Mercedes' => ['mercedes', 'classe-c', 'classe c', 'w204'],
            'BMW' => ['bmw'],
            'Audi' => ['audi'],
            'Volkswagen' => ['volkswagen', 'vw'],
            'Renault' => ['renault'],
            'Peugeot' => ['peugeot'],
            'Citroen' => ['citroen', 'citroën'],
            'Ford' => ['ford'],
            'Opel' => ['opel'],
            'Toyota' => ['toyota'],
        ];

        foreach ($brands as $brand => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($normalized, $needle)) {
                    $result['brand'] = $brand;
                    break 2;
                }
            }
        }

        if (preg_match('/classe[\s-]*c\s+w204/i', $text, $matches)) {
            $result['model'] = 'Classe-C W204';
        } elseif (preg_match('/\b([a-z]{1,3}\d{2,4})\b/i', $text, $matches)) {
            $result['model'] = strtoupper($matches[1]);
        }

        return $result;
    }

    private function researchBestMatch(ZcmPendingAd $ad): ?array
    {
        $research = $ad->enrichment?->research ?? [];
        $bestMatch = data_get($research, 'prestashop.best_match');

        if (is_array($bestMatch) && !empty($bestMatch)) {
            return $bestMatch;
        }

        $reference = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $ad->reference));
        $items = data_get($research, 'prestashop.items', []);

        if ($reference === '' || !is_array($items)) {
            return null;
        }

        foreach ($items as $item) {
            $itemReference = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) data_get($item, 'reference')));
            $name = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) data_get($item, 'name')));

            if ($itemReference === $reference || str_contains($name, $reference)) {
                return $item + [
                    'match_quality' => $itemReference === $reference ? 'exact_reference' : 'name_contains_reference',
                    'match_score' => $itemReference === $reference ? 100 : 82,
                ];
            }
        }

        return null;
    }

    private function normalizeAnalysis(array $analysis): array
    {
        $score = $analysis['confidence_score'] ?? null;

        if (is_numeric($score)) {
            $score = (float) $score;
            $analysis['confidence_score'] = (int) round($score <= 1 ? $score * 100 : $score);
            $analysis['confidence_score'] = max(0, min(100, $analysis['confidence_score']));
        }

        foreach (['compatibilities', 'missing_data', 'risks', 'suggestions'] as $key) {
            if (!isset($analysis[$key]) || !is_array($analysis[$key])) {
                $analysis[$key] = [];
            }

            $analysis[$key] = array_values(array_filter($analysis[$key], fn($value) => !$this->isUnknown($value)));
        }

        $analysis['missing_data'] = array_values(array_filter($analysis['missing_data'], fn($value) => is_string($value)));

        if (!isset($analysis['technical_data']) || !is_array($analysis['technical_data'])) {
            $analysis['technical_data'] = [];
        }

        if (!isset($analysis['evidence_sources']) || !is_array($analysis['evidence_sources'])) {
            $analysis['evidence_sources'] = [];
        }

        return $analysis;
    }

    private function preferKnownFallback(array $analysis, array $fallback): array
    {
        foreach (['part_type', 'brand', 'model'] as $key) {
            if ($this->isUnknown($analysis[$key] ?? null) && !$this->isUnknown($fallback[$key] ?? null)) {
                $analysis[$key] = $fallback[$key];
            }
        }

        $analysis['technical_data'] = array_merge(
            $fallback['technical_data'] ?? [],
            is_array($analysis['technical_data'] ?? null) ? $analysis['technical_data'] : []
        );
        $analysis['evidence_sources'] = $this->mergeEvidenceSources(
            $fallback['evidence_sources'] ?? [],
            is_array($analysis['evidence_sources'] ?? null) ? $analysis['evidence_sources'] : []
        );

        $missing = $analysis['missing_data'] ?? [];
        if (is_array($missing)) {
            $filled = [
                'category' => $analysis['part_type'] ?? null,
                'manufacturer' => $analysis['brand'] ?? null,
                'car_model' => $analysis['model'] ?? null,
                'price' => data_get($analysis, 'technical_data.prestashop_price'),
            ];

            $analysis['missing_data'] = array_values(array_filter($missing, function ($field) use ($filled) {
                return empty($filled[$field] ?? null);
            }));
        }

        if (!empty(data_get($analysis, 'technical_data.prestashop_price'))) {
            $analysis['risks'] = array_values(array_filter($analysis['risks'] ?? [], function ($risk) {
                return !str_contains(mb_strtolower((string) $risk), 'preço')
                    && !str_contains(mb_strtolower((string) $risk), 'preco')
                    && !str_contains(mb_strtolower((string) $risk), 'price');
            }));

            $analysis['suggestions'] = array_values(array_filter($analysis['suggestions'] ?? [], function ($suggestion) {
                return !str_contains(mb_strtolower((string) $suggestion), 'preço')
                    && !str_contains(mb_strtolower((string) $suggestion), 'preco')
                    && !str_contains(mb_strtolower((string) $suggestion), 'price');
            }));
        }

        return $analysis;
    }

    private function isUnknown($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array(mb_strtolower(trim((string) $value)), ['unknown', 'desconhecido', 'n/a', 'na', '-', 'null'], true);
    }

    private function evidenceSources(ZcmPendingAd $ad, ?array $bestMatch): array
    {
        $sources = [];

        if ($bestMatch) {
            $sources[] = [
                'origin' => 'internal_prestashop',
                'title' => data_get($bestMatch, 'name'),
                'url' => null,
                'reference' => data_get($bestMatch, 'reference'),
                'match_reason' => data_get($bestMatch, 'match_reason'),
                'confidence_score' => data_get($bestMatch, 'match_score'),
            ];
        }

        foreach (data_get($ad->enrichment?->research, 'web.items', []) as $item) {
            $sources[] = [
                'origin' => 'web_openai',
                'title' => data_get($item, 'title'),
                'url' => data_get($item, 'url'),
                'reference' => $ad->reference,
                'match_reason' => data_get($item, 'match_reason'),
                'confidence_score' => data_get($item, 'confidence_score'),
            ];
        }

        return $this->mergeEvidenceSources($sources, []);
    }

    private function mergeEvidenceSources(array $primary, array $secondary): array
    {
        return collect(array_merge($primary, $secondary))
            ->map(function ($source) {
                $url = data_get($source, 'url');
                $origin = data_get($source, 'origin');

                if (!$origin && $url) {
                    $origin = 'web_openai';
                }

                return [
                    'origin' => $origin,
                    'title' => data_get($source, 'title'),
                    'url' => $url,
                    'reference' => data_get($source, 'reference'),
                    'match_reason' => data_get($source, 'match_reason'),
                    'confidence_score' => is_numeric(data_get($source, 'confidence_score'))
                        ? max(0, min(100, (int) round((float) data_get($source, 'confidence_score'))))
                        : null,
                ];
            })
            ->filter(fn($source) => in_array($source['origin'], ['internal_prestashop', 'web_openai'], true))
            ->filter(fn($source) => !empty($source['title']) || !empty($source['url']) || !empty($source['reference']))
            ->unique(fn($source) => $source['url'] ?: (($source['origin'] ?? '') . '|' . ($source['title'] ?? '')))
            ->values()
            ->all();
    }
}
