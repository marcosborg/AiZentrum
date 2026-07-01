<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PrestashopCatalogService
{
    public function languages(): array
    {
        $languages = data_get($this->get('languages', ['display' => 'full']), 'languages', []);

        return collect($languages)
            ->map(function ($language) {
                return [
                    'id' => (int) data_get($language, 'id'),
                    'name' => (string) data_get($language, 'name'),
                    'iso_code' => (string) data_get($language, 'iso_code'),
                    'language_code' => (string) data_get($language, 'language_code'),
                    'active' => (int) data_get($language, 'active', 1) === 1,
                ];
            })
            ->filter(fn ($language) => $language['id'] > 0 && $language['active'])
            ->values()
            ->all();
    }

    public function categories(): array
    {
        $categories = data_get($this->get('categories', ['display' => 'full']), 'categories', []);

        return collect($categories)
            ->map(function ($category) {
                $names = $this->localizedValues(data_get($category, 'name', []));

                return [
                    'id' => (int) data_get($category, 'id'),
                    'id_parent' => (int) data_get($category, 'id_parent'),
                    'level_depth' => (int) data_get($category, 'level_depth'),
                    'active' => (int) data_get($category, 'active', 1) === 1,
                    'name' => $this->bestName($names),
                    'localized_names' => $names,
                ];
            })
            ->filter(fn ($category) => $category['id'] > 0 && $category['active'] && $category['name'] !== '')
            ->values()
            ->all();
    }

    public function bestCategory(array $categories, array $signals): ?array
    {
        $haystack = $this->normalize(implode(' ', array_filter([
            data_get($signals, 'name'),
            data_get($signals, 'category'),
            data_get($signals, 'part_type'),
            data_get($signals, 'manufacturer'),
            data_get($signals, 'model'),
            implode(' ', (array) data_get($signals, 'keywords', [])),
        ])));

        return collect($categories)
            ->map(function ($category) use ($haystack) {
                $categoryTerms = $this->normalize($category['name'] . ' ' . implode(' ', $category['localized_names']));
                $score = 0;

                foreach (array_unique(explode(' ', $categoryTerms)) as $term) {
                    if (strlen($term) >= 4 && !$this->isGenericTerm($term) && str_contains($haystack, $term)) {
                        $score += 12;
                    }
                }

                foreach ($this->categoryAliases() as $needle => $aliases) {
                    if (str_contains($haystack, $needle)) {
                        foreach ($aliases as $alias) {
                            if (str_contains($categoryTerms, $alias)) {
                                $score += 60;
                            }
                        }
                    }
                }

                if (($category['level_depth'] ?? 0) >= 3) {
                    $score += 5;
                }

                return $category + ['match_score' => $score];
            })
            ->filter(fn ($category) => $category['match_score'] > 0)
            ->sortByDesc('match_score')
            ->first();
    }

    private function get(string $resource, array $query = []): array
    {
        $url = rtrim((string) config('services.ps.url'), '/') . '/' . ltrim($resource, '/');
        $key = (string) config('services.ps.key');

        $response = Http::withBasicAuth($key, '')
            ->acceptJson()
            ->timeout(30)
            ->get($url, $query + [
                'output_format' => 'JSON',
            ]);

        if (!$response->ok()) {
            throw new \RuntimeException('Prestashop API HTTP ' . $response->status() . ' em ' . $resource);
        }

        return $response->json() ?: [];
    }

    private function localizedValues($values): array
    {
        if (is_string($values)) {
            return ['default' => $values];
        }

        if (!is_array($values)) {
            return [];
        }

        return collect($values)
            ->mapWithKeys(function ($item, $key) {
                if (is_array($item) && array_key_exists('id', $item)) {
                    return [(string) $item['id'] => (string) data_get($item, 'value', '')];
                }

                return [(string) $key => is_scalar($item) ? (string) $item : (string) data_get($item, 'value', '')];
            })
            ->filter()
            ->all();
    }

    private function bestName(array $names): string
    {
        return (string) ($names['1'] ?? reset($names) ?: '');
    }

    private function normalize(string $value): string
    {
        return Str::ascii(Str::lower(preg_replace('/[^[:alnum:]\s]+/u', ' ', $value)));
    }

    private function categoryAliases(): array
    {
        return [
            'abs' => ['abs', 'travagem', 'freio', 'brake', 'brems'],
            'modulo' => ['modulo', 'module', 'unidad', 'steuergerat'],
            'inversor' => ['inversor', 'inverter', 'conversor', 'converter', 'modulo controlo', 'modulo de controlo', 'control module'],
            'conversor' => ['inversor', 'inverter', 'conversor', 'converter', 'modulo controlo', 'modulo de controlo', 'control module'],
            'airbag' => ['airbag', 'srs'],
            'cinto' => ['cinto', 'seatbelt', 'pretensor'],
            'centralina' => ['centralina', 'modulo', 'module', 'ecu', 'steuergerat'],
            'display' => ['display', 'monitor', 'ecra', 'screen'],
            'radio' => ['radio', 'multimedia', 'navegacao', 'navigation'],
            'farol' => ['farol', 'headlight', 'scheinwerfer'],
            'compressor' => ['compressor'],
        ];
    }

    private function isGenericTerm(string $term): bool
    {
        return in_array($term, [
            'auto',
            'peca',
            'pecas',
            'automovel',
            'automoveis',
            'usado',
            'usada',
            'novo',
            'nova',
            'mercedes',
            'benz',
            'classe',
            'class',
        ], true);
    }
}
