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
    private const REQUIRED_LANGUAGES = [
        'pt' => ['name' => 'Portugues', 'language_code' => 'pt-pt'],
        'fr' => ['name' => 'Francais', 'language_code' => 'fr-fr'],
        'es' => ['name' => 'Espanol', 'language_code' => 'es-es'],
        'en' => ['name' => 'English', 'language_code' => 'en-gb'],
    ];

    public function __construct(
        private readonly PrestashopCatalogService $prestashop,
        private readonly ZcmAdPrestashopRulesService $rules
    ) {
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
        $baseContent = [
            'name' => Str::limit($name, 128, ''),
            'short_description' => $shortDescription,
            'description' => $description,
            'meta_title' => Str::limit(data_get($seo, 'meta_title') ?: $name, 70, ''),
            'meta_description' => Str::limit(data_get($seo, 'meta_description') ?: $shortDescription, 160, ''),
        ];
        $generatedContent = $this->generateListingContent(
            $languages,
            $baseContent,
            $ad,
            $analysis,
            $research,
            $categories,
            $categorySuggestion
        );
        $translations = $generatedContent['translations'];
        $tags = $generatedContent['tags'];
        $productFacts = $generatedContent['product_facts'];
        $warnings = $this->generationWarnings($generatedContent['warnings'], $ad, $analysis, $categorySuggestion);
        $categoryReason = data_get($generatedContent, 'category.reason')
            ?: $this->categoryReason($categorySuggestion);
        $ptTranslation = $translations['pt'] ?? $baseContent;
        $name = $this->cleanText(data_get($ptTranslation, 'name') ?: $name);
        $shortDescription = $this->cleanText(data_get($ptTranslation, 'short_description') ?: $shortDescription);
        $description = $this->cleanText(data_get($ptTranslation, 'description') ?: $description);

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
            'category_reason' => $categoryReason,
            'prestashop_categories' => $categories,
            'prestashop_languages' => $languages,
            'manufacturer' => data_get($productFacts, 'brand') ?: data_get($analysis, 'brand') ?: data_get($ad->brand_model_data, 'manufacturer'),
            'model' => data_get($productFacts, 'model') ?: data_get($analysis, 'model') ?: data_get($ad->brand_model_data, 'car_model'),
            'brand_filter' => data_get($productFacts, 'brand') ?: data_get($analysis, 'brand') ?: data_get($ad->brand_model_data, 'manufacturer'),
            'model_filter' => data_get($productFacts, 'model') ?: data_get($analysis, 'model') ?: data_get($ad->brand_model_data, 'car_model'),
            'vehicle_year' => data_get($productFacts, 'vehicle_year'),
            'product_facts' => $productFacts,
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
            'keywords' => $tags['pt'] ?? $this->fallbackTags($ad, $analysis),
            'tags' => $tags,
            'translations' => $translations,
            'images' => $images,
            'approval_notes' => $this->approvalNotes($pricing, $images, $technicalData),
            'generation_warnings' => $warnings,
            'rules_version' => $this->rules->version(),
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
            'brand_filter' => $this->cleanText($data['brand_filter'] ?? data_get($existing, 'brand_filter', '')),
            'model_filter' => $this->cleanText($data['model_filter'] ?? data_get($existing, 'model_filter', '')),
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
        $draft['keywords'] = $this->normalizeKeywords($data['keywords'] ?? data_get($existing, 'keywords', []));
        $draft['tags'] = $this->normalizeSubmittedTags($data['tags'] ?? [], data_get($existing, 'tags', []), $draft['keywords']);
        $draft['compatibilities'] = $existing['compatibilities'] ?? [];
        $draft['technical_references'] = $existing['technical_references'] ?? [];
        $draft['product_facts'] = $existing['product_facts'] ?? [];
        $draft['category_reason'] = $existing['category_reason'] ?? null;
        $draft['generation_warnings'] = $existing['generation_warnings'] ?? [];
        $draft['rules_version'] = $existing['rules_version'] ?? $this->rules->version();
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
        $languages = [];

        try {
            $languages = $this->prestashop->languages();
        } catch (\Throwable $e) {
            Log::warning('Prestashop languages unavailable: ' . $e->getMessage());
        }

        return $this->requiredPrestashopLanguages($languages);
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

    private function generateListingContent(
        array $languages,
        array $base,
        ZcmPendingAd $ad,
        array $analysis,
        array $research,
        array $categories,
        ?array $categorySuggestion
    ): array
    {
        $fallback = [
            'product_facts' => $this->fallbackProductFacts($ad, $analysis),
            'category' => [
                'id' => data_get($categorySuggestion, 'id'),
                'name' => data_get($categorySuggestion, 'name'),
                'confidence_score' => data_get($categorySuggestion, 'match_score', 0),
                'reason' => $this->categoryReason($categorySuggestion),
            ],
            'translations' => $this->fallbackTranslations($languages, $base),
            'tags' => $this->fallbackTagsByLanguage($ad, $analysis),
            'warnings' => [],
        ];

        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            return $fallback;
        }

        try {
            $response = Http::withToken($apiKey)
                ->connectTimeout(10)
                ->timeout(50)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "Criador de anuncios PrestaShop TechnicZentrum. Responde apenas JSON valido, sem markdown.\n\n" . $this->rules->prompt(),
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'expected_shape' => [
                                    'product_facts' => [
                                        'component_type' => null,
                                        'brand' => null,
                                        'model' => null,
                                        'vehicle_year' => null,
                                        'main_reference' => null,
                                        'additional_references' => [],
                                    ],
                                    'category' => [
                                        'id' => null,
                                        'name' => null,
                                        'confidence_score' => 0,
                                        'reason' => null,
                                    ],
                                    'translations' => [
                                        'pt' => [
                                            'name' => 'max 128 chars',
                                            'short_description' => 'commercial short text',
                                            'description' => 'full product description',
                                            'meta_title' => 'max 70 chars',
                                            'meta_description' => 'max 160 chars',
                                            'link_rewrite' => 'slug',
                                        ],
                                        'fr' => [],
                                        'es' => [],
                                        'en' => [],
                                    ],
                                    'tags' => [
                                        'pt' => [],
                                        'fr' => [],
                                        'es' => [],
                                        'en' => [],
                                    ],
                                    'warnings' => [],
                                ],
                                'languages' => $languages,
                                'required_languages' => array_keys(self::REQUIRED_LANGUAGES),
                                'base_text' => $base,
                                'product_context' => [
                                    'reference' => $ad->reference,
                                    'title' => $ad->title,
                                    'description' => $ad->description,
                                    'category' => $ad->category,
                                    'brand_model' => $ad->brand_model_data,
                                    'part_type' => data_get($analysis, 'part_type'),
                                    'brand' => data_get($analysis, 'brand'),
                                    'model' => data_get($analysis, 'model'),
                                    'compatibilities' => data_get($analysis, 'compatibilities', []),
                                    'research' => [
                                        'best_internal_match' => data_get($research, 'prestashop.best_match'),
                                        'pricing_summary' => data_get($research, 'pricing_summary'),
                                        'web_sources' => data_get($research, 'web.items', []),
                                    ],
                                    'category_suggestion' => $categorySuggestion,
                                    'available_categories' => $this->categoryPayload($categories, $categorySuggestion),
                                ],
                                'hard_rules' => [
                                    'Nao inventar dados tecnicos, referencias, anos, compatibilidades, aplicacoes ou caracteristicas.',
                                    'Nao traduzir nem alterar referencias, codigos, marcas, modelos ou medidas.',
                                    'Se o ano nao existir nos dados fornecidos, vehicle_year deve ser null e o ano nao deve aparecer em textos.',
                                    'Gerar traducoes finais para pt, fr, es e en.',
                                    'Se houver incerteza, preencher warnings.',
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            if (!$response->ok()) {
                Log::warning('Prestashop draft content generation failed', [
                    'ad_id' => $ad->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $fallback;
            }

            $json = json_decode(data_get($response->json(), 'choices.0.message.content', '{}'), true);

            if (!is_array($json)) {
                return $fallback;
            }

            return $this->normalizeGeneratedContent($json, $fallback, $languages, $ad, $analysis, $categorySuggestion);
        } catch (\Throwable $e) {
            Log::warning('Prestashop draft content generation failed: ' . $e->getMessage(), ['ad_id' => $ad->id]);

            return $fallback;
        }
    }

    private function requiredPrestashopLanguages(array $languages): array
    {
        return collect(self::REQUIRED_LANGUAGES)
            ->map(function (array $defaults, string $iso) use ($languages) {
                $match = collect($languages)->first(function ($language) use ($iso) {
                    $languageIso = Str::lower((string) data_get($language, 'iso_code'));
                    $languageCode = Str::lower((string) data_get($language, 'language_code'));

                    return $languageIso === $iso || str_starts_with($languageCode, $iso . '-');
                });

                return [
                    'id' => data_get($match, 'id') ?: $iso,
                    'prestashop_id' => is_numeric(data_get($match, 'id')) ? (int) data_get($match, 'id') : null,
                    'name' => data_get($match, 'name') ?: $defaults['name'],
                    'iso_code' => $iso,
                    'language_code' => data_get($match, 'language_code') ?: $defaults['language_code'],
                    'active' => true,
                ];
            })
            ->values()
            ->all();
    }

    private function fallbackTranslations(array $languages, array $base): array
    {
        return collect($languages)
            ->mapWithKeys(function ($language) use ($base) {
                $entry = $base;
                $entry['link_rewrite'] = Str::slug($base['name'] ?? '');
                $entry['language_id'] = $language['id'];
                $entry['prestashop_id'] = $language['prestashop_id'] ?? null;
                $entry['language_name'] = $language['name'];
                $entry['iso_code'] = $language['iso_code'];

                return [(string) $language['iso_code'] => $entry];
            })
            ->all();
    }

    private function normalizeGeneratedContent(
        array $generated,
        array $fallback,
        array $languages,
        ZcmPendingAd $ad,
        array $analysis,
        ?array $categorySuggestion
    ): array
    {
        $fallback['product_facts'] = $this->normalizeProductFacts(
            data_get($generated, 'product_facts', []),
            $fallback['product_facts'],
            $ad,
            $analysis
        );
        $fallback['category'] = [
            'id' => data_get($categorySuggestion, 'id'),
            'name' => data_get($categorySuggestion, 'name'),
            'confidence_score' => data_get($categorySuggestion, 'match_score', 0),
            'reason' => data_get($generated, 'category.reason') ?: $fallback['category']['reason'],
        ];
        $fallback['translations'] = $this->normalizeGeneratedTranslations(
            $languages,
            data_get($generated, 'translations', []),
            $fallback['translations'],
            $ad
        );
        $fallback['tags'] = $this->normalizeGeneratedTags(
            data_get($generated, 'tags', []),
            $fallback['tags'],
            $ad,
            $analysis
        );
        $fallback['warnings'] = $this->normalizeWarnings(data_get($generated, 'warnings', []));

        return $fallback;
    }

    private function normalizeGeneratedTranslations(array $languages, array $translations, array $fallback, ZcmPendingAd $ad): array
    {
        foreach ($languages as $language) {
            $iso = (string) $language['iso_code'];
            $entry = $translations[$iso] ?? $translations[$language['id']] ?? [];

            if (!is_array($entry)) {
                continue;
            }

            $fallback[$iso] = array_merge($fallback[$iso], [
                'name' => $this->withReference(Str::limit($this->cleanText($entry['name'] ?? $fallback[$iso]['name']), 128, ''), $ad),
                'short_description' => $this->withReference($this->cleanText($entry['short_description'] ?? $fallback[$iso]['short_description']), $ad),
                'description' => $this->withReference($this->cleanText($entry['description'] ?? $fallback[$iso]['description']), $ad),
                'meta_title' => Str::limit($this->withReference($this->cleanText($entry['meta_title'] ?? $fallback[$iso]['meta_title']), $ad), 70, ''),
                'meta_description' => Str::limit($this->withReference($this->cleanText($entry['meta_description'] ?? $fallback[$iso]['meta_description']), $ad), 160, ''),
                'link_rewrite' => Str::slug($entry['link_rewrite'] ?? $entry['name'] ?? $fallback[$iso]['name']),
            ]);
        }

        return $fallback;
    }

    private function normalizeProductFacts(array $generated, array $fallback, ZcmPendingAd $ad, array $analysis): array
    {
        $facts = array_merge($fallback, array_filter([
            'component_type' => $this->cleanText(data_get($generated, 'component_type')),
            'brand' => $this->cleanText(data_get($generated, 'brand')),
            'model' => $this->cleanText(data_get($generated, 'model')),
            'main_reference' => $this->cleanText(data_get($generated, 'main_reference')),
        ]));

        $facts['component_type'] = $facts['component_type'] ?: data_get($analysis, 'part_type') ?: $ad->category;
        $facts['brand'] = $facts['brand'] ?: data_get($analysis, 'brand') ?: data_get($ad->brand_model_data, 'manufacturer');
        $facts['model'] = $facts['model'] ?: data_get($analysis, 'model') ?: data_get($ad->brand_model_data, 'car_model');
        $facts['main_reference'] = $ad->reference ?: $facts['main_reference'];
        $facts['vehicle_year'] = $this->knownVehicleYear($ad, $analysis, data_get($generated, 'vehicle_year'));
        $facts['additional_references'] = array_values(array_unique(array_filter(array_merge(
            (array) data_get($fallback, 'additional_references', []),
            (array) data_get($generated, 'additional_references', [])
        ), fn ($reference) => $this->cleanText($reference) !== '' && $this->cleanText($reference) !== $facts['main_reference'])));

        return $facts;
    }

    private function fallbackProductFacts(ZcmPendingAd $ad, array $analysis): array
    {
        return [
            'component_type' => data_get($analysis, 'part_type') ?: $ad->category,
            'brand' => data_get($analysis, 'brand') ?: data_get($ad->brand_model_data, 'manufacturer'),
            'model' => data_get($analysis, 'model') ?: data_get($ad->brand_model_data, 'car_model'),
            'vehicle_year' => $this->knownVehicleYear($ad, $analysis),
            'main_reference' => $ad->reference,
            'additional_references' => array_values(array_unique(array_filter([
                data_get($ad->brand_model_data, 'brand_reference'),
                data_get($ad->brand_model_data, 'manufacturer_reference'),
            ]))),
        ];
    }

    private function knownVehicleYear(ZcmPendingAd $ad, array $analysis, $candidate = null): ?string
    {
        $candidate = $this->cleanText($candidate);
        $source = $this->sourceText($ad, $analysis);

        if ($candidate !== '' && preg_match('/^(19|20)\d{2}$/', $candidate) && str_contains($source, $candidate)) {
            return $candidate;
        }

        if (preg_match('/\b(19|20)\d{2}\b/', $source, $matches)) {
            return $matches[0];
        }

        return null;
    }

    private function sourceText(ZcmPendingAd $ad, array $analysis): string
    {
        return implode(' ', array_filter([
            $ad->title,
            $ad->description,
            $ad->category,
            json_encode($ad->raw_payload, JSON_UNESCAPED_UNICODE),
            json_encode($analysis, JSON_UNESCAPED_UNICODE),
        ]));
    }

    private function categoryPayload(array $categories, ?array $categorySuggestion): array
    {
        $suggestedId = data_get($categorySuggestion, 'id');

        return collect($categories)
            ->sortByDesc(fn ($category) => (string) data_get($category, 'id') === (string) $suggestedId ? 9999 : (int) data_get($category, 'level_depth'))
            ->take(80)
            ->map(fn ($category) => [
                'id' => data_get($category, 'id'),
                'name' => data_get($category, 'name'),
                'level_depth' => data_get($category, 'level_depth'),
                'localized_names' => data_get($category, 'localized_names', []),
            ])
            ->values()
            ->all();
    }

    private function categoryReason(?array $categorySuggestion): ?string
    {
        if (!$categorySuggestion) {
            return 'Nao foi encontrada uma categoria PrestaShop suficientemente relacionada; validar manualmente.';
        }

        return 'Categoria sugerida por correspondencia entre tipo de peca, nome, marca/modelo e categorias PrestaShop disponiveis.';
    }

    private function generationWarnings(array $warnings, ZcmPendingAd $ad, array $analysis, ?array $categorySuggestion): array
    {
        $warnings = $this->normalizeWarnings($warnings);

        if (!data_get($analysis, 'part_type') && !$ad->category) {
            $warnings[] = 'Tipo de componente pouco claro; validar antes de exportar.';
        }

        if (!$categorySuggestion) {
            $warnings[] = 'Categoria PrestaShop nao encontrada automaticamente.';
        } elseif ((int) data_get($categorySuggestion, 'match_score', 0) < 40) {
            $warnings[] = 'Categoria PrestaShop sugerida com baixa confianca.';
        }

        if (!$ad->reference) {
            $warnings[] = 'Anuncio sem referencia principal.';
        }

        return array_values(array_unique(array_filter($warnings)));
    }

    private function fallbackTagsByLanguage(ZcmPendingAd $ad, array $analysis): array
    {
        $base = $this->fallbackTags($ad, $analysis);

        return collect(array_keys(self::REQUIRED_LANGUAGES))
            ->mapWithKeys(fn ($iso) => [$iso => array_values(array_unique(array_merge($base, [$this->genericTagForLanguage($iso)])))])
            ->all();
    }

    private function fallbackTags(ZcmPendingAd $ad, array $analysis): array
    {
        return $this->normalizeKeywords([
            $ad->reference,
            data_get($analysis, 'part_type') ?: $ad->category,
            data_get($analysis, 'brand') ?: data_get($ad->brand_model_data, 'manufacturer'),
            data_get($analysis, 'model') ?: data_get($ad->brand_model_data, 'car_model'),
        ]);
    }

    private function genericTagForLanguage(string $iso): string
    {
        return [
            'pt' => 'peca auto',
            'fr' => 'piece auto',
            'es' => 'pieza coche',
            'en' => 'car part',
        ][$iso] ?? 'auto part';
    }

    private function normalizeGeneratedTags($generated, array $fallback, ZcmPendingAd $ad, array $analysis): array
    {
        foreach (array_keys(self::REQUIRED_LANGUAGES) as $iso) {
            $fallback[$iso] = array_values(array_unique(array_merge(
                $this->fallbackTags($ad, $analysis),
                $this->normalizeKeywords(data_get($generated, $iso, [])),
                [$this->genericTagForLanguage($iso)]
            )));
        }

        return $fallback;
    }

    private function normalizeSubmittedTags(array $submitted, array $existing, array $defaultKeywords): array
    {
        foreach (array_keys(self::REQUIRED_LANGUAGES) as $iso) {
            $existing[$iso] = $this->normalizeKeywords($submitted[$iso] ?? data_get($existing, $iso, $defaultKeywords));
        }

        return $existing;
    }

    private function normalizeKeywords($keywords): array
    {
        if (is_string($keywords)) {
            $keywords = preg_split('/[,;\r\n]+/', $keywords) ?: [];
        }

        if (!is_array($keywords)) {
            return [];
        }

        return collect($keywords)
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique(fn ($keyword) => Str::lower($keyword))
            ->take(20)
            ->values()
            ->all();
    }

    private function normalizeWarnings($warnings): array
    {
        if (is_string($warnings)) {
            $warnings = [$warnings];
        }

        if (!is_array($warnings)) {
            return [];
        }

        return collect($warnings)
            ->map(fn ($warning) => trim((string) $warning))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function withReference(string $value, ZcmPendingAd $ad): string
    {
        $reference = trim((string) $ad->reference);

        if ($reference === '' || str_contains($value, $reference)) {
            return $value;
        }

        return trim($value . ' ' . $reference);
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
