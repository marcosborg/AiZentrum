<?php

namespace App\Services;

use App\Models\ZcmPendingAd;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZcmAdWebResearchService
{
    public function search(ZcmPendingAd $ad, array $localResearch = []): array
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            return [
                'searched' => false,
                'found' => false,
                'items' => [],
                'sources' => [],
                'error' => 'OPENAI_API_KEY em falta.',
            ];
        }

        $payload = $this->payload($ad, $localResearch);
        $result = $this->request($apiKey, $payload, config('services.openai.web_search_tool', 'web_search'));

        if (($result['retry_with_preview'] ?? false) === true) {
            $result = $this->request($apiKey, $payload, 'web_search_preview');
        }

        return $result;
    }

    private function request(string $apiKey, array $payload, string $toolType): array
    {
        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.web_search_model', config('services.openai.model', 'gpt-4o-mini')),
                    'tools' => [
                        ['type' => $toolType],
                    ],
                    'include' => [
                        'web_search_call.action.sources',
                    ],
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => 'Pesquisa referencias de pecas automoveis na web. Responde apenas com JSON valido, sem markdown.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            if (!$response->ok()) {
                $body = $response->body();

                Log::warning('ZCM ad web research failed', [
                    'status' => $response->status(),
                    'body' => $body,
                    'tool' => $toolType,
                ]);

                return [
                    'searched' => true,
                    'found' => false,
                    'items' => [],
                    'sources' => [],
                    'tool' => $toolType,
                    'retry_with_preview' => $toolType === 'web_search' && str_contains($body, 'web_search'),
                    'error' => 'OpenAI Responses HTTP ' . $response->status(),
                ];
            }

            $json = $response->json();
            $text = $this->outputText($json);
            $parsed = $this->parseJson($text);
            $items = $this->normalizeItems($parsed['items'] ?? []);
            $sources = $this->normalizeSources(array_merge($parsed['sources'] ?? [], $this->responseSources($json)));
            $priceCandidates = $this->priceCandidatesFromSources($sources);

            return [
                'searched' => true,
                'found' => !empty($items) || !empty($sources) || !empty($priceCandidates),
                'summary' => $parsed['summary'] ?? null,
                'items' => $items,
                'sources' => $sources,
                'price_candidates' => $priceCandidates,
                'tool' => $toolType,
                'model' => config('services.openai.web_search_model', config('services.openai.model', 'gpt-4o-mini')),
            ];
        } catch (\Throwable $e) {
            Log::error('ZCM ad web research exception: ' . $e->getMessage());

            return [
                'searched' => true,
                'found' => false,
                'items' => [],
                'sources' => [],
                'tool' => $toolType,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function payload(ZcmPendingAd $ad, array $localResearch): array
    {
        return [
            'task' => 'Encontrar resultados web relevantes para enriquecer um anuncio de peca automovel usada.',
            'expected_json' => [
                'summary' => 'Resumo curto dos resultados encontrados.',
                'items' => [
                    [
                        'title' => 'Titulo do resultado',
                        'url' => 'URL',
                        'snippet' => 'Resumo do conteudo',
                        'price' => 'Preco encontrado, se existir',
                        'currency' => 'Moeda do preco, se existir',
                        'match_reason' => 'Porque e relevante para a referencia/anuncio',
                        'confidence_score' => 'Inteiro 0-100',
                    ],
                ],
                'sources' => [
                    ['title' => 'Titulo', 'url' => 'URL'],
                ],
            ],
            'rules' => [
                'Prioriza paginas que mencionem a referencia exata do anuncio.',
                'Procura tambem por referencias relacionadas encontradas no Prestashop interno.',
                'Nao inventes compatibilidades, precos ou URLs.',
                'Se nao encontrares evidencia relevante, devolve items vazio.',
            ],
            'ad' => [
                'reference' => $ad->reference,
                'title' => $ad->title,
                'description' => $ad->description,
                'brand_model' => $ad->brand_model_data,
                'category' => $ad->category,
            ],
            'local_research' => [
                'prestashop_best_match' => data_get($localResearch, 'prestashop.best_match'),
                'prestashop_items' => data_get($localResearch, 'prestashop.items', []),
            ],
        ];
    }

    private function outputText(array $response): string
    {
        $text = data_get($response, 'output_text');

        if (is_string($text) && $text !== '') {
            return $text;
        }

        $chunks = [];

        foreach ($response['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                $value = $content['text'] ?? null;

                if (is_string($value)) {
                    $chunks[] = $value;
                }
            }
        }

        return trim(implode("\n", $chunks));
    }

    private function parseJson(string $text): array
    {
        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeItems($items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function ($item) {
                return [
                    'title' => data_get($item, 'title'),
                    'url' => $this->cleanUrl((string) data_get($item, 'url')),
                    'snippet' => data_get($item, 'snippet'),
                    'price' => $this->price(data_get($item, 'price')),
                    'currency' => data_get($item, 'currency'),
                    'match_reason' => data_get($item, 'match_reason'),
                    'confidence_score' => $this->score(data_get($item, 'confidence_score')),
                ];
            })
            ->filter(fn($item) => $this->isAllowedSourceUrl((string) $item['url']))
            ->filter(fn($item) => !empty($item['title']) || !empty($item['url']) || !empty($item['snippet']))
            ->take(8)
            ->values()
            ->all();
    }

    private function normalizeSources($sources): array
    {
        if (!is_array($sources)) {
            return [];
        }

        return collect($sources)
            ->map(function ($source) {
                return [
                    'title' => data_get($source, 'title'),
                    'url' => $this->cleanUrl((string) data_get($source, 'url')),
                ];
            })
            ->filter(fn($source) => $this->isAllowedSourceUrl((string) $source['url']))
            ->unique('url')
            ->take(10)
            ->values()
            ->all();
    }

    private function priceCandidatesFromSources(array $sources): array
    {
        return collect($sources)
            ->take(6)
            ->map(fn($source) => $this->priceCandidateFromSource($source))
            ->filter()
            ->values()
            ->all();
    }

    private function priceCandidateFromSource(array $source): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 ZentrumAI/1.0',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
                ->timeout(15)
                ->get($source['url']);

            if (!$response->ok()) {
                return null;
            }

            $candidate = $this->extractPriceFromHtml($response->body());

            if (!$candidate) {
                return null;
            }

            return [
                'title' => $source['title'],
                'url' => $source['url'],
                'price' => $candidate['price'],
                'currency' => $candidate['currency'] ?: 'EUR',
                'price_source' => $candidate['source'],
            ];
        } catch (\Throwable $e) {
            Log::warning('ZCM ad web price extraction failed', [
                'url' => $source['url'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function extractPriceFromHtml(string $html): ?array
    {
        foreach ($this->jsonLdBlocks($html) as $json) {
            $candidate = $this->extractPriceFromJsonLd($json);

            if ($candidate) {
                return $candidate;
            }
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html);
        libxml_clear_errors();

        if (!$loaded) {
            return null;
        }

        $xpath = new \DOMXPath($dom);
        $queries = [
            ['//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="product:price:amount"]/@content', 'meta product:price:amount'],
            ['//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="og:price:amount"]/@content', 'meta og:price:amount'],
            ['//*[@itemprop="price"]/@content', 'itemprop price content'],
            ['//*[@itemprop="price"]', 'itemprop price text'],
            ['//*[contains(@class, "price")]', 'class price'],
        ];

        foreach ($queries as [$query, $source]) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $value = $node instanceof \DOMAttr ? $node->nodeValue : $node->textContent;
                $price = $this->price($value);

                if ($price !== null) {
                    return [
                        'price' => $price,
                        'currency' => $this->currencyFromHtml($html),
                        'source' => $source,
                    ];
                }
            }
        }

        return null;
    }

    private function jsonLdBlocks(string $html): array
    {
        if (!preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            return [];
        }

        return collect($matches[1])
            ->map(fn($json) => json_decode(html_entity_decode(trim($json)), true))
            ->filter(fn($json) => is_array($json))
            ->values()
            ->all();
    }

    private function extractPriceFromJsonLd(array $json): ?array
    {
        foreach ($this->flattenJsonLd($json) as $node) {
            $price = data_get($node, 'offers.price')
                ?? data_get($node, 'offers.0.price')
                ?? data_get($node, 'price');
            $currency = data_get($node, 'offers.priceCurrency')
                ?? data_get($node, 'offers.0.priceCurrency')
                ?? data_get($node, 'priceCurrency');

            $price = $this->price($price);

            if ($price !== null) {
                return [
                    'price' => $price,
                    'currency' => $currency,
                    'source' => 'json_ld',
                ];
            }
        }

        return null;
    }

    private function flattenJsonLd(array $json): array
    {
        $nodes = [$json];

        foreach (['@graph', 'itemListElement'] as $key) {
            foreach ((array) data_get($json, $key, []) as $child) {
                if (is_array($child)) {
                    $nodes = array_merge($nodes, $this->flattenJsonLd($child));
                }
            }
        }

        return $nodes;
    }

    private function currencyFromHtml(string $html): ?string
    {
        if (preg_match('/\b(EUR|USD|GBP|CAD|AUD)\b/i', $html, $matches)) {
            return strtoupper($matches[1]);
        }

        if (str_contains($html, '€')) {
            return 'EUR';
        }

        return null;
    }

    private function responseSources(array $response): array
    {
        $sources = [];

        foreach ($response['output'] ?? [] as $output) {
            foreach (data_get($output, 'action.sources', []) as $source) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    private function score($score): ?int
    {
        if (!is_numeric($score)) {
            return null;
        }

        $score = (float) $score;

        return max(0, min(100, (int) round($score <= 1 ? $score * 100 : $score)));
    }

    private function price($price): ?float
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

    private function cleanUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $query = array_filter($query, fn($key) => !str_starts_with($key, 'utm_'), ARRAY_FILTER_USE_KEY);
        }

        $clean = $parts['scheme'] . '://' . $parts['host'];
        $clean .= $parts['path'] ?? '';

        if (!empty($query)) {
            $clean .= '?' . http_build_query($query);
        }

        if (!empty($parts['fragment'])) {
            $clean .= '#' . $parts['fragment'];
        }

        return $clean;
    }

    private function isAllowedSourceUrl(string $url): bool
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
}
