<?php

namespace App\Services;

use App\Http\Controllers\Traits\PrestashopApi;
use App\Models\ZcmPendingAd;

class ZcmAdResearchService
{
    use PrestashopApi;

    public function __construct(private readonly ZcmAdWebResearchService $webResearch)
    {
    }

    public function research(ZcmPendingAd $ad): array
    {
        $reference = trim((string) $ad->reference);
        $brandModel = $ad->brand_model_data;

        $result = [
            'reference' => $reference,
            'title' => $ad->title,
            'brand_model' => $brandModel,
            'requested_by' => $ad->requested_by_data,
            'source_images_count' => count($ad->images ?? []),
            'prestashop' => [
                'searched' => false,
                'found' => false,
                'items' => [],
            ],
            'web' => [
                'searched' => false,
                'found' => false,
                'items' => [],
                'sources' => [],
            ],
        ];

        if ($reference !== '') {
            try {
                $response = $this->zentrumSearch('https://techniczentrum.com', $reference);
                $items = data_get($response, 'products', []);

                $normalizedItems = array_slice($this->normalizeItems($items, $reference), 0, 5);
                $bestMatch = $this->bestMatch($normalizedItems);

                $result['prestashop'] = [
                    'searched' => true,
                    'found' => !empty($items),
                    'relevant_found' => $bestMatch !== null,
                    'best_match' => $bestMatch,
                    'confidence_score' => $bestMatch['match_score'] ?? 0,
                    'items' => $normalizedItems,
                ];
            } catch (\Throwable $e) {
                $result['prestashop'] = [
                    'searched' => true,
                    'found' => false,
                    'relevant_found' => false,
                    'best_match' => null,
                    'confidence_score' => 0,
                    'items' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }

        $result['web'] = $this->webResearch->search($ad, $result);
        $result['pricing_summary'] = $this->pricingSummary($result);

        return $result;
    }

    private function normalizeItems($items, string $reference): array
    {
        if (is_object($items)) {
            $items = [$items];
        }

        if (!is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function ($item) use ($reference) {
                $normalized = [
                    'id' => data_get($item, 'id'),
                    'reference' => data_get($item, 'reference'),
                    'name' => data_get($item, 'name.0.value', data_get($item, 'name')),
                    'price' => data_get($item, 'price'),
                    'active' => data_get($item, 'active'),
                ];

                return $normalized + $this->matchMetadata($normalized, $reference);
            })
            ->filter(fn($item) => array_filter($item) !== [])
            ->sortByDesc('match_score')
            ->values()
            ->all();
    }

    private function matchMetadata(array $item, string $reference): array
    {
        $normalizedReference = $this->normalizeReference($reference);
        $itemReference = $this->normalizeReference((string) ($item['reference'] ?? ''));
        $name = $this->normalizeReference((string) ($item['name'] ?? ''));

        if ($normalizedReference !== '' && $itemReference === $normalizedReference) {
            return [
                'match_quality' => 'exact_reference',
                'match_reason' => 'A referencia do produto Prestashop coincide com a referencia do anuncio.',
                'match_score' => 100,
            ];
        }

        if ($normalizedReference !== '' && str_contains($name, $normalizedReference)) {
            return [
                'match_quality' => 'name_contains_reference',
                'match_reason' => 'A referencia do anuncio aparece no nome do produto Prestashop.',
                'match_score' => 82,
            ];
        }

        return [
            'match_quality' => 'weak_match',
            'match_reason' => 'Resultado devolvido pela pesquisa, mas sem confirmacao direta da referencia.',
            'match_score' => 35,
        ];
    }

    private function bestMatch(array $items): ?array
    {
        $match = collect($items)
            ->first(fn($item) => ($item['match_score'] ?? 0) >= 70);

        return $match ?: null;
    }

    private function normalizeReference(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $value));
    }

    private function pricingSummary(array $research): array
    {
        $prices = collect();

        foreach (data_get($research, 'prestashop.items', []) as $item) {
            $price = $this->priceValue(data_get($item, 'price'));

            if ($price !== null) {
                $prices->push([
                    'origin' => 'internal_prestashop',
                    'provenance' => 'Prestashop interno',
                    'title' => data_get($item, 'name'),
                    'url' => null,
                    'reference' => data_get($item, 'reference'),
                    'price' => $price,
                    'currency' => 'EUR',
                    'confidence_score' => data_get($item, 'match_score'),
                    'is_confirmed' => true,
                    'confirmation_level' => 'internal_confirmed',
                    'match_quality' => data_get($item, 'match_quality'),
                    'match_reason' => data_get($item, 'match_reason'),
                    'price_source' => 'Campo price do produto interno encontrado pela pesquisa Prestashop.',
                ]);
            }
        }

        foreach (data_get($research, 'web.items', []) as $item) {
            $price = $this->priceValue(data_get($item, 'price'));
            $url = (string) data_get($item, 'url');

            if ($price !== null && $this->isAllowedPriceSourceUrl($url) && !$this->isInternalGroupUrl($url)) {
                $prices->push([
                    'origin' => 'web_openai',
                    'provenance' => 'Pesquisa web via OpenAI',
                    'title' => data_get($item, 'title'),
                    'url' => $url,
                    'reference' => data_get($research, 'reference'),
                    'price' => $price,
                    'currency' => data_get($item, 'currency') ?: 'EUR',
                    'confidence_score' => data_get($item, 'confidence_score'),
                    'is_confirmed' => false,
                    'confirmation_level' => 'ai_reported_unverified',
                    'match_quality' => 'web_result',
                    'match_reason' => data_get($item, 'match_reason'),
                    'price_source' => 'Preco reportado pela resposta estruturada da pesquisa OpenAI; ainda nao confirmado por extracao direta da pagina.',
                ]);
            }
        }

        foreach (data_get($research, 'web.price_candidates', []) as $item) {
            $price = $this->priceValue(data_get($item, 'price'));
            $url = (string) data_get($item, 'url');

            if ($price !== null && $this->isAllowedPriceSourceUrl($url) && !$this->isInternalGroupUrl($url)) {
                $prices->push([
                    'origin' => 'web_page',
                    'provenance' => 'Pagina web externa',
                    'title' => data_get($item, 'title') ?: parse_url($url, PHP_URL_HOST),
                    'url' => $url,
                    'reference' => data_get($research, 'reference'),
                    'price' => $price,
                    'currency' => data_get($item, 'currency') ?: 'EUR',
                    'confidence_score' => 85,
                    'is_confirmed' => true,
                    'confirmation_level' => 'page_extracted',
                    'match_quality' => 'page_price_extracted',
                    'match_reason' => 'Preco extraido diretamente da pagina encontrada pela pesquisa web.',
                    'price_source' => 'Preco extraido de ' . (data_get($item, 'price_source') ?: 'HTML da pagina') . '.',
                ]);
            }
        }

        $prices = $prices
            ->filter(fn($item) => $item['price'] > 0)
            ->unique(fn($item) => ($item['url'] ?: $item['origin']) . '|' . $item['price'] . '|' . $item['currency'])
            ->values();

        if ($prices->isEmpty()) {
            return [
                'has_prices' => false,
                'currency' => null,
                'min' => null,
                'average' => null,
                'max' => null,
                'count' => 0,
                'confidence_level' => 'none',
                'is_market_range' => false,
                'note' => 'Nenhum preco confirmado em fontes validas.',
                'sources' => [],
            ];
        }

        $currency = $prices
            ->groupBy('currency')
            ->sortByDesc(fn($group) => $group->count())
            ->keys()
            ->first();

        $sameCurrencyPrices = $prices
            ->where('currency', $currency)
            ->values();
        $confirmedCount = $sameCurrencyPrices->where('is_confirmed', true)->count();
        $unverifiedCount = $sameCurrencyPrices->where('is_confirmed', false)->count();
        $hasMarketRange = $confirmedCount >= 2;

        return [
            'has_prices' => true,
            'currency' => $currency,
            'min' => round((float) $sameCurrencyPrices->min('price'), 2),
            'average' => round((float) $sameCurrencyPrices->avg('price'), 2),
            'max' => round((float) $sameCurrencyPrices->max('price'), 2),
            'count' => $sameCurrencyPrices->count(),
            'confirmed_count' => $confirmedCount,
            'unverified_count' => $unverifiedCount,
            'confidence_level' => $confirmedCount >= 3 ? 'good' : ($confirmedCount >= 2 ? 'limited' : ($confirmedCount === 1 ? 'single_source' : 'unverified')),
            'is_market_range' => $hasMarketRange,
            'note' => $this->pricingNote($sameCurrencyPrices->count(), $confirmedCount, $unverifiedCount),
            'sources' => $sameCurrencyPrices->all(),
        ];
    }

    private function pricingNote(int $totalCount, int $confirmedCount, int $unverifiedCount): string
    {
        if ($confirmedCount >= 2) {
            return 'Intervalo calculado com fontes de preco confirmadas. Precos indicativos adicionais podem aparecer na tabela quando existirem.';
        }

        if ($confirmedCount === 1 && $unverifiedCount > 0) {
            return 'Existe apenas uma fonte de preco confirmada; os restantes valores sao indicativos reportados pela pesquisa e devem ser validados antes da aprovacao.';
        }

        if ($confirmedCount === 1) {
            return 'Amostra insuficiente: existe apenas uma fonte de preco confirmada, por isso minimo, medio e maximo ficam iguais e nao representam intervalo de mercado.';
        }

        if ($totalCount > 0) {
            return 'Precos indicativos encontrados pela pesquisa, mas nenhum foi confirmado por fonte interna ou extracao direta da pagina. Validar manualmente antes da aprovacao.';
        }

        return 'Nenhum preco confirmado em fontes validas.';
    }

    private function priceValue($price): ?float
    {
        if ($price === null || $price === '') {
            return null;
        }

        if (is_numeric($price)) {
            return round((float) $price, 2);
        }

        $value = preg_replace('/[^\d,.\-]/', '', (string) $price);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function isAllowedPriceSourceUrl(string $url): bool
    {
        if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            return false;
        }

        $host = mb_strtolower((string) $host);

        return !str_contains($host, 'example.')
            && !str_contains($host, 'exemplo.')
            && !str_contains($host, 'localhost')
            && !str_contains($host, '127.0.0.1');
    }

    private function isInternalGroupUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            return false;
        }

        $host = mb_strtolower((string) $host);
        $internalDomains = [
            'techniczentrum.com',
            'www.techniczentrum.com',
            'zcmanager.com',
            'www.zcmanager.com',
            'airbagszentrum.com',
            'www.airbagszentrum.com',
        ];

        return in_array($host, $internalDomains, true);
    }
}
