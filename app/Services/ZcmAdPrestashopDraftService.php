<?php

namespace App\Services;

use App\Models\ZcmPendingAd;
use App\Models\ZcmPendingAdEnrichment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ZcmAdPrestashopDraftService
{
    public function __construct(private readonly PrestashopCatalogService $prestashop)
    {
    }

    public function generate(ZcmPendingAd $ad): array
    {
        $ad->loadMissing('enrichment');

        $seo = $ad->enrichment?->seo ?? [];
        $analysis = $ad->enrichment?->ai_analysis ?? [];
        $research = $ad->enrichment?->research ?? [];
        $pricing = data_get($research, 'pricing_summary', []);
        $technicalData = $ad->enrichment?->technical_data ?? [];

        $name = $this->cleanText(data_get($seo, 'title') ?: $this->fallbackName($ad, $analysis));
        $shortDescription = $this->cleanText(data_get($seo, 'short_description') ?: $this->fallbackShortDescription($ad, $analysis));
        $description = $this->cleanText(data_get($seo, 'long_description') ?: $this->fallbackDescription($ad, $analysis));
        $price = $this->recommendedPrice($ad, $pricing);
        $images = $this->draftImages($ad);
        $languages = $this->prestashopLanguages();
        $categories = $this->prestashopCategories();
        $categorySuggestion = $this->prestashop->bestCategory($categories, [
            'name' => $name,
            'category' => $ad->category,
            'part_type' => data_get($analysis, 'part_type'),
            'manufacturer' => data_get($analysis, 'brand') ?: data_get($ad->brand_model_data, 'manufacturer'),
            'model' => data_get($analysis, 'model') ?: data_get($ad->brand_model_data, 'car_model'),
            'keywords' => (array) data_get($seo, 'keywords', []),
        ]);
        $translations = $this->translations($languages, [
            'name' => Str::limit($name, 128, ''),
            'short_description' => $shortDescription,
            'description' => $description,
            'meta_title' => Str::limit(data_get($seo, 'meta_title') ?: $name, 70, ''),
            'meta_description' => Str::limit(data_get($seo, 'meta_description') ?: $shortDescription, 160, ''),
        ], $ad, $analysis);

        return [
            'status' => 'draft',
            'destination' => 'prestashop',
            'name' => Str::limit($name, 128, ''),
            'reference' => $ad->reference ?: (string) $ad->zcmanager_ad_id,
            'price' => $price['price'],
            'price_source' => $price['source'],
            'currency' => $price['currency'],
            'quantity' => 1,
            'condition' => 'reconditioned',
            'category' => $ad->category ?: data_get($analysis, 'part_type') ?: 'Pecas auto',
            'prestashop_category_id' => data_get($categorySuggestion, 'id'),
            'prestashop_category_name' => data_get($categorySuggestion, 'name'),
            'prestashop_category_score' => data_get($categorySuggestion, 'match_score'),
            'prestashop_categories' => $categories,
            'prestashop_languages' => $languages,
            'manufacturer' => data_get($analysis, 'brand') ?: data_get($ad->brand_model_data, 'manufacturer'),
            'model' => data_get($analysis, 'model') ?: data_get($ad->brand_model_data, 'car_model'),
            'compatibilities' => array_values((array) data_get($analysis, 'compatibilities', [])),
            'technical_references' => array_values(array_unique(array_filter([
                $ad->reference,
                data_get($ad->brand_model_data, 'brand_reference'),
                data_get($ad->brand_model_data, 'manufacturer_reference'),
            ]))),
            'short_description' => $shortDescription,
            'description' => $description,
            'meta_title' => Str::limit(data_get($seo, 'meta_title') ?: $name, 70, ''),
            'meta_description' => Str::limit(data_get($seo, 'meta_description') ?: $shortDescription, 160, ''),
            'link_rewrite' => data_get($seo, 'slug') ?: Str::slug($name . ' ' . $ad->reference),
            'keywords' => array_values((array) data_get($seo, 'keywords', [])),
            'translations' => $translations,
            'images' => $images,
            'approval_notes' => $this->approvalNotes($pricing, $images, $technicalData),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    public function save(ZcmPendingAd $ad, array $data): array
    {
        $ad->loadMissing('enrichment');

        $existing = data_get($ad->enrichment?->technical_data, 'prestashop_draft', []);

        $draft = array_merge($existing, [
            'status' => 'draft',
            'destination' => 'prestashop',
            'name' => $this->cleanText($data['name']),
            'reference' => $this->cleanText($data['reference']),
            'price' => $this->normalizePrice($data['price']),
            'currency' => $this->cleanText($data['currency'] ?? 'EUR'),
            'quantity' => (int) ($data['quantity'] ?? 1),
            'condition' => $this->cleanText($data['condition'] ?? 'reconditioned'),
            'category' => $this->cleanText($data['category'] ?? ''),
            'prestashop_category_id' => $data['prestashop_category_id'] ?? null,
            'prestashop_category_name' => $this->categoryNameFromExisting($existing, $data['prestashop_category_id'] ?? null),
            'manufacturer' => $this->cleanText($data['manufacturer'] ?? ''),
            'model' => $this->cleanText($data['model'] ?? ''),
            'short_description' => $this->cleanText($data['short_description'] ?? ''),
            'description' => $this->cleanText($data['description'] ?? ''),
            'meta_title' => $this->cleanText($data['meta_title'] ?? ''),
            'meta_description' => $this->cleanText($data['meta_description'] ?? ''),
            'link_rewrite' => Str::slug($data['link_rewrite'] ?: $data['name']),
            'approval_notes' => $this->cleanText($data['approval_notes'] ?? ''),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $draft['quantity'] = max(0, $draft['quantity']);
        $draft['images'] = $existing['images'] ?? $this->draftImages($ad);
        $draft['keywords'] = $existing['keywords'] ?? [];
        $draft['compatibilities'] = $existing['compatibilities'] ?? [];
        $draft['technical_references'] = $existing['technical_references'] ?? [];
        $draft['prestashop_categories'] = $existing['prestashop_categories'] ?? [];
        $draft['prestashop_languages'] = $existing['prestashop_languages'] ?? [];
        $draft['translations'] = $this->normalizeSubmittedTranslations($data['translations'] ?? [], $existing['translations'] ?? []);

        $this->store($ad, $draft);

        return $draft;
    }

    public function store(ZcmPendingAd $ad, array $draft): ZcmPendingAdEnrichment
    {
        $enrichment = ZcmPendingAdEnrichment::firstOrCreate([
            'zcm_pending_ad_id' => $ad->id,
        ]);

        $technicalData = $enrichment->technical_data ?? [];
        Arr::set($technicalData, 'prestashop_draft', $draft);

        $enrichment->update([
            'technical_data' => $technicalData,
        ]);

        return $enrichment;
    }

    private function fallbackName(ZcmPendingAd $ad, array $analysis): string
    {
        return trim(implode(' ', array_filter([
            data_get($analysis, 'part_type'),
            data_get($analysis, 'brand'),
            data_get($analysis, 'model'),
            $ad->reference ?: $ad->title,
        ]))) ?: ($ad->title ?: 'Peca automovel ' . $ad->id);
    }

    private function fallbackShortDescription(ZcmPendingAd $ad, array $analysis): string
    {
        $part = data_get($analysis, 'part_type') ?: $ad->category ?: 'Peca automovel';
        $brand = data_get($analysis, 'brand') ?: data_get($ad->brand_model_data, 'manufacturer');

        return trim($part . ' ' . $brand . ' com referencia ' . $ad->reference . '. Confirmar compatibilidade antes da compra.');
    }

    private function fallbackDescription(ZcmPendingAd $ad, array $analysis): string
    {
        $lines = [
            $this->fallbackShortDescription($ad, $analysis),
            '',
            'Produto preparado para venda apos validacao humana.',
            'Referencia: ' . ($ad->reference ?: '-'),
        ];

        $compatibilities = array_values((array) data_get($analysis, 'compatibilities', []));
        if ($compatibilities !== []) {
            $lines[] = 'Compatibilidades indicadas: ' . implode(', ', $compatibilities) . '.';
        }

        if ($ad->description) {
            $lines[] = 'Descricao original: ' . $ad->description;
        }

        return implode("\n", $lines);
    }

    private function recommendedPrice(ZcmPendingAd $ad, array $pricing): array
    {
        if ((float) $ad->price > 0) {
            return [
                'price' => $this->normalizePrice($ad->price),
                'currency' => 'EUR',
                'source' => 'Preco original ZCManager',
            ];
        }

        if (data_get($pricing, 'has_prices')) {
            $confirmedSources = collect((array) data_get($pricing, 'sources', []))
                ->filter(fn ($source) => data_get($source, 'is_confirmed') && (float) data_get($source, 'price') > 0);

            if ($confirmedSources->isNotEmpty()) {
                return [
                    'price' => round($confirmedSources->avg(fn ($source) => (float) data_get($source, 'price')), 2),
                    'currency' => data_get($pricing, 'currency', 'EUR'),
                    'source' => 'Media de fontes confirmadas da pesquisa',
                ];
            }

            return [
                'price' => $this->normalizePrice(data_get($pricing, 'average')),
                'currency' => data_get($pricing, 'currency', 'EUR'),
                'source' => 'Media indicativa da pesquisa',
            ];
        }

        return [
            'price' => null,
            'currency' => 'EUR',
            'source' => 'Sem preco definido',
        ];
    }

    private function draftImages(ZcmPendingAd $ad): array
    {
        $images = data_get($ad->enrichment?->images, 'final_images', []);
        if ($images === []) {
            $images = data_get($ad->enrichment?->images, 'candidate_images', []);
        }

        return collect((array) $images)
            ->map(function ($image) {
                return [
                    'source' => data_get($image, 'source'),
                    'url' => data_get($image, 'url'),
                    'storage_path' => data_get($image, 'storage_path'),
                    'original_url' => data_get($image, 'original_url'),
                    'page_url' => data_get($image, 'page_url'),
                    'confidence_score' => data_get($image, 'confidence_score'),
                ];
            })
            ->filter(fn ($image) => $image['url'] || $image['storage_path'])
            ->values()
            ->all();
    }

    private function approvalNotes(array $pricing, array $images, array $technicalData): string
    {
        $notes = [];

        if (!data_get($pricing, 'has_prices')) {
            $notes[] = 'Preco deve ser validado manualmente: nao ha fontes de preco suficientes.';
        } elseif (!data_get($pricing, 'is_market_range')) {
            $notes[] = 'Preco baseado em dados indicativos; confirmar antes de exportar.';
        }

        if ($images === []) {
            $notes[] = 'Sem imagens finais associadas ao rascunho.';
        }

        if (data_get($technicalData, 'missing_data')) {
            $notes[] = 'Existem dados tecnicos em falta na analise IA.';
        }

        return implode("\n", $notes);
    }

    private function cleanText(?string $value): string
    {
        return trim((string) $value);
    }

    private function normalizePrice($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([' ', ','], ['', '.'], $value);
        }

        return round((float) $value, 2);
    }

    private function prestashopLanguages(): array
    {
        try {
            return $this->prestashop->languages();
        } catch (\Throwable $e) {
            Log::warning('Prestashop languages unavailable: ' . $e->getMessage());

            return [
                ['id' => 1, 'name' => 'Portugues', 'iso_code' => 'pt', 'language_code' => 'pt-pt', 'active' => true],
            ];
        }
    }

    private function prestashopCategories(): array
    {
        try {
            return $this->prestashop->categories();
        } catch (\Throwable $e) {
            Log::warning('Prestashop categories unavailable: ' . $e->getMessage());

            return [];
        }
    }

    private function translations(array $languages, array $base, ZcmPendingAd $ad, array $analysis): array
    {
        $fallback = $this->fallbackTranslations($languages, $base);
        $apiKey = config('services.openai.key');

        if (!$apiKey || count($languages) <= 1) {
            return $fallback;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tradutor ecommerce Prestashop de pecas automoveis. Mantem referencias, codigos, marcas e medidas sem traducao. Responde apenas JSON valido.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'expected_shape' => [
                                    'translations' => [
                                        'language_id' => [
                                            'name' => 'max 128 chars',
                                            'short_description' => 'commercial short text',
                                            'description' => 'full product description',
                                            'meta_title' => 'max 70 chars',
                                            'meta_description' => 'max 160 chars',
                                            'link_rewrite' => 'slug',
                                        ],
                                    ],
                                ],
                                'languages' => $languages,
                                'base_language' => 'pt',
                                'base_text' => $base,
                                'product_context' => [
                                    'reference' => $ad->reference,
                                    'part_type' => data_get($analysis, 'part_type'),
                                    'brand' => data_get($analysis, 'brand'),
                                    'model' => data_get($analysis, 'model'),
                                    'compatibilities' => data_get($analysis, 'compatibilities', []),
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            if (!$response->ok()) {
                return $fallback;
            }

            $json = json_decode(data_get($response->json(), 'choices.0.message.content', '{}'), true);
            $translations = data_get($json, 'translations', []);

            if (!is_array($translations)) {
                return $fallback;
            }

            return $this->normalizeGeneratedTranslations($languages, $translations, $fallback);
        } catch (\Throwable $e) {
            Log::warning('Prestashop draft translations failed: ' . $e->getMessage(), ['ad_id' => $ad->id]);

            return $fallback;
        }
    }

    private function fallbackTranslations(array $languages, array $base): array
    {
        return collect($languages)
            ->mapWithKeys(function ($language) use ($base) {
                $entry = $base;
                $entry['link_rewrite'] = Str::slug($base['name'] ?? '');
                $entry['language_id'] = $language['id'];
                $entry['language_name'] = $language['name'];
                $entry['iso_code'] = $language['iso_code'];

                return [(string) $language['id'] => $entry];
            })
            ->all();
    }

    private function normalizeGeneratedTranslations(array $languages, array $translations, array $fallback): array
    {
        foreach ($languages as $language) {
            $id = (string) $language['id'];
            $entry = $translations[$id] ?? $translations[$language['iso_code']] ?? [];

            if (!is_array($entry)) {
                continue;
            }

            $fallback[$id] = array_merge($fallback[$id], [
                'name' => Str::limit($this->cleanText($entry['name'] ?? $fallback[$id]['name']), 128, ''),
                'short_description' => $this->cleanText($entry['short_description'] ?? $fallback[$id]['short_description']),
                'description' => $this->cleanText($entry['description'] ?? $fallback[$id]['description']),
                'meta_title' => Str::limit($this->cleanText($entry['meta_title'] ?? $fallback[$id]['meta_title']), 70, ''),
                'meta_description' => Str::limit($this->cleanText($entry['meta_description'] ?? $fallback[$id]['meta_description']), 160, ''),
                'link_rewrite' => Str::slug($entry['link_rewrite'] ?? $entry['name'] ?? $fallback[$id]['name']),
            ]);
        }

        return $fallback;
    }

    private function normalizeSubmittedTranslations(array $submitted, array $existing): array
    {
        foreach ($existing as $languageId => $entry) {
            $posted = $submitted[$languageId] ?? [];

            if (!is_array($posted)) {
                continue;
            }

            $existing[$languageId] = array_merge($entry, [
                'name' => Str::limit($this->cleanText($posted['name'] ?? data_get($entry, 'name')), 128, ''),
                'short_description' => $this->cleanText($posted['short_description'] ?? data_get($entry, 'short_description')),
                'description' => $this->cleanText($posted['description'] ?? data_get($entry, 'description')),
                'meta_title' => Str::limit($this->cleanText($posted['meta_title'] ?? data_get($entry, 'meta_title')), 70, ''),
                'meta_description' => Str::limit($this->cleanText($posted['meta_description'] ?? data_get($entry, 'meta_description')), 160, ''),
                'link_rewrite' => Str::slug($posted['link_rewrite'] ?? data_get($entry, 'link_rewrite')),
            ]);
        }

        return $existing;
    }

    private function categoryNameFromExisting(array $existing, $categoryId): ?string
    {
        if (!$categoryId) {
            return null;
        }

        foreach ((array) data_get($existing, 'prestashop_categories', []) as $category) {
            if ((string) data_get($category, 'id') === (string) $categoryId) {
                return data_get($category, 'name');
            }
        }

        return data_get($existing, 'prestashop_category_name');
    }
}
