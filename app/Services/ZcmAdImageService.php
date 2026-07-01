<?php

namespace App\Services;

use App\Models\ZcmPendingAd;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ZcmAdImageService
{
    public function prepare(ZcmPendingAd $ad): array
    {
        $ad->loadMissing('enrichment');
        $existingImages = $ad->enrichment?->images ?? [];
        $existingAiImages = $this->existingAiGeneratedImages($ad, $existingImages);

        $sourceImages = collect($ad->images ?? [])
            ->map(function ($image) {
                return [
                    'source' => 'zcmanager',
                    'url' => is_string($image) ? $image : data_get($image, 'url'),
                    'raw' => $image,
                    'selected' => true,
                ];
            })
            ->values()
            ->all();

        $webImages = $this->webImageCandidates($ad);
        $candidates = array_values(array_merge($sourceImages, $webImages));
        $baseFinalImages = !empty($sourceImages) ? $sourceImages : collect($webImages)
            ->take(3)
            ->map(fn($image) => array_merge($image, ['selected' => true]))
            ->values()
            ->all();
        $finalImages = array_values(array_merge($existingAiImages, $baseFinalImages));

        return [
            'source_images' => $sourceImages,
            'web_candidates' => $webImages,
            'candidate_images' => $candidates,
            'ai_generated_images' => $existingAiImages,
            'final_images' => $finalImages,
            'needs_ai_generation' => count($finalImages) === 0,
            'ai_generation_status' => !empty($existingAiImages) ? 'generated' : (count($finalImages) === 0 ? 'pending' : 'not_required'),
            'extraction_note' => count($finalImages) === 0
                ? 'Nao foram encontradas imagens validas da peca nas paginas pesquisadas. Fontes bloqueadas ou imagens encontradas eram de outras pecas/modelos.'
                : null,
        ];
    }

    public function recreateWithAi(ZcmPendingAd $ad, string $imageUrl): array
    {
        @set_time_limit(240);
        $ad->loadMissing('enrichment');

        if (!$ad->enrichment) {
            throw new \RuntimeException('Ainda nao existem dados de enriquecimento para este anuncio.');
        }

        $image = $this->findImageByUrl($ad, $imageUrl);

        if (!$image) {
            throw new \InvalidArgumentException('Imagem selecionada nao encontrada no anuncio.');
        }

        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY em falta.');
        }

        $prompt = $this->imageEditPrompt($ad);

        $response = Http::withToken($apiKey)
            ->timeout(240)
            ->post('https://api.openai.com/v1/images/edits', [
                'model' => config('services.openai.image_edit_model', 'gpt-image-1.5'),
                'images' => [
                    ['image_url' => $imageUrl],
                ],
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'output_format' => 'png',
            ]);

        if (!$response->ok()) {
            Log::warning('ZCM ad AI image recreation failed', [
                'ad_id' => $ad->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('OpenAI Images HTTP ' . $response->status() . ': ' . $response->body());
        }

        $base64 = data_get($response->json(), 'data.0.b64_json');

        if (!$base64) {
            throw new \RuntimeException('A OpenAI nao devolveu imagem em b64_json.');
        }

        $binary = base64_decode($base64, true);

        if ($binary === false) {
            throw new \RuntimeException('Imagem gerada invalida.');
        }

        $path = 'zcm-ai-images/' . $ad->id . '/' . Str::uuid() . '.png';
        Storage::disk('public')->put($path, $binary);

        $generated = [
            'source' => 'ai_generated',
            'url' => '/admin/zcm/pending-ads/' . $ad->id . '/generated-image/' . basename($path),
            'storage_disk' => 'public',
            'storage_path' => $path,
            'original_url' => $imageUrl,
            'prompt' => $prompt,
            'model' => config('services.openai.image_edit_model', 'gpt-image-1.5'),
            'selected' => true,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];

        $images = $ad->enrichment->images ?? [];
        $images['ai_generated_images'] = array_values(array_merge(data_get($images, 'ai_generated_images', []), [$generated]));
        $images['final_images'] = $this->replaceFinalImage($images['final_images'] ?? [], $imageUrl, $generated);
        $images['ai_generation_status'] = 'generated';
        $images['needs_ai_generation'] = false;

        $ad->enrichment->update(['images' => $images]);

        return $generated;
    }

    public function deleteAiGenerated(ZcmPendingAd $ad, string $imageUrl): array
    {
        $ad->loadMissing('enrichment');

        if (!$ad->enrichment) {
            throw new \RuntimeException('Ainda nao existem dados de enriquecimento para este anuncio.');
        }

        $images = $ad->enrichment->images ?? [];
        $deleted = null;

        foreach (['final_images', 'ai_generated_images'] as $key) {
            $images[$key] = collect(data_get($images, $key, []))
                ->reject(function ($image) use ($imageUrl, &$deleted) {
                    if (data_get($image, 'source') !== 'ai_generated') {
                        return false;
                    }

                    $matches = data_get($image, 'url') === $imageUrl
                        || data_get($image, 'storage_path') === $imageUrl
                        || basename((string) data_get($image, 'storage_path')) === basename($imageUrl);

                    if ($matches) {
                        $deleted = $image;
                    }

                    return $matches;
                })
                ->values()
                ->all();
        }

        if (!$deleted) {
            throw new \InvalidArgumentException('Imagem IA nao encontrada no anuncio.');
        }

        $storagePath = data_get($deleted, 'storage_path');

        if ($storagePath && Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->delete($storagePath);
        }

        $images['ai_generation_status'] = !empty($images['ai_generated_images']) ? 'generated' : 'not_required';

        $ad->enrichment->update(['images' => $images]);

        return $deleted;
    }

    private function webImageCandidates(ZcmPendingAd $ad): array
    {
        $pages = collect(data_get($ad->enrichment?->research, 'web.items', []))
            ->merge(data_get($ad->enrichment?->research, 'web.sources', []))
            ->map(function ($source) {
                return [
                    'title' => data_get($source, 'title'),
                    'url' => data_get($source, 'url'),
                    'confidence_score' => data_get($source, 'confidence_score'),
                ];
            })
            ->filter(fn($source) => !empty($source['url']))
            ->unique('url')
            ->take(12)
            ->values();

        if ($pages->isEmpty()) {
            return [];
        }

        return $pages
            ->flatMap(fn($page) => $this->extractImagesFromPage($page, $ad))
            ->unique('url')
            ->take(8)
            ->values()
            ->all();
    }

    private function findImageByUrl(ZcmPendingAd $ad, string $imageUrl): ?array
    {
        $images = $ad->enrichment?->images ?? [];

        foreach (['final_images', 'ai_generated_images', 'candidate_images', 'web_candidates', 'source_images'] as $key) {
            foreach (data_get($images, $key, []) as $image) {
                if (data_get($image, 'url') === $imageUrl) {
                    return $image;
                }
            }
        }

        return null;
    }

    private function existingAiGeneratedImages(ZcmPendingAd $ad, array $images): array
    {
        $known = collect()
            ->merge(data_get($images, 'ai_generated_images', []))
            ->merge(collect(data_get($images, 'final_images', []))->where('source', 'ai_generated')->all())
            ->filter(fn($image) => data_get($image, 'source') === 'ai_generated')
            ->values();

        $knownPaths = $known
            ->map(fn($image) => data_get($image, 'storage_path'))
            ->filter()
            ->all();

        $storageDir = 'zcm-ai-images/' . $ad->id;

        if (Storage::disk('public')->exists($storageDir)) {
            foreach (Storage::disk('public')->files($storageDir) as $path) {
                if (!in_array($path, $knownPaths, true)) {
                    $known->push([
                        'source' => 'ai_generated',
                        'url' => '/admin/zcm/pending-ads/' . $ad->id . '/generated-image/' . basename($path),
                        'storage_disk' => 'public',
                        'storage_path' => $path,
                        'original_url' => null,
                        'model' => config('services.openai.image_edit_model', 'gpt-image-1.5'),
                        'selected' => true,
                        'created_at' => null,
                        'recovered_from_storage' => true,
                    ]);
                }
            }
        }

        return $known
            ->unique(fn($image) => data_get($image, 'storage_path') ?: data_get($image, 'url'))
            ->values()
            ->all();
    }

    private function replaceFinalImage(array $finalImages, string $originalUrl, array $generated): array
    {
        $replaced = false;
        $finalImages = collect($finalImages)
            ->map(function ($image) use ($originalUrl, $generated, &$replaced) {
                if (!$replaced && data_get($image, 'url') === $originalUrl) {
                    $replaced = true;

                    return array_merge($image, ['selected' => false, 'replaced_by_ai' => true]);
                }

                return $image;
            })
            ->values()
            ->all();

        array_unshift($finalImages, $generated);

        return $finalImages;
    }

    private function imageEditPrompt(ZcmPendingAd $ad): string
    {
        $analysis = $ad->enrichment?->ai_analysis ?? [];
        $partType = data_get($analysis, 'part_type', 'peca automovel usada');
        $brand = data_get($analysis, 'brand');
        $model = data_get($analysis, 'model');

        return trim(implode("\n", [
            'Edita esta fotografia mantendo a mesma pose da peca, a mesma face visivel, a mesma orientacao, o mesmo angulo de camera, o mesmo enquadramento e a mesma posicao no canvas.',
            'Nao rodes a peca, nao mudes o lado visivel, nao alteres a perspectiva, nao mudes o formato geral e nao reposiciones a peca.',
            'Nao inventes nem desenhes componentes que nao estejam claramente visiveis na imagem original. Se uma zona nao estiver visivel, mantem-na fora da imagem ou indistinta; nao completes a peca por imaginacao.',
            'Mantem apenas os contornos, furos, conectores, relevos, parafusos e detalhes fisicos claramente observaveis na fotografia original.',
            'A peca deve parecer nova ou recondicionada: limpa, sem sujidade, sem desgaste visivel, sem riscos fortes e com acabamento realista de produto automovel, mas sem alterar a sua forma observavel.',
            'Remove todas as referencias visiveis, codigos, numeros de serie, etiquetas, marcas de agua, texto sobreposto e identificadores comerciais visiveis na imagem.',
            'Nao alteres o tipo de peca, nao acrescentes componentes novos e nao removas componentes estruturais da peca.',
            'Coloca a peca sobre um fundo cinza realista, como uma bancada de oficina ou mesa de trabalho limpa, com iluminacao natural de fotografia de ecommerce.',
            'Nao incluas texto novo na imagem.',
            'Peca: ' . $partType . ($brand ? ' ' . $brand : '') . ($model ? ' ' . $model : '') . '.',
        ]));
    }

    private function extractImagesFromPage(array $page, ZcmPendingAd $ad): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'pt-PT,pt;q=0.9,en;q=0.8',
                'Cache-Control' => 'no-cache',
            ])
                ->timeout(15)
                ->get($page['url']);

            if (!$response->ok()) {
                return [];
            }

            return $this->extractImagesFromHtml($response->body(), $page, $ad);
        } catch (\Throwable $e) {
            Log::warning('ZCM ad image page fetch failed', [
                'ad_id' => $ad->id,
                'url' => $page['url'],
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function extractImagesFromHtml(string $html, array $page, ZcmPendingAd $ad): array
    {
        $dom = new \DOMDocument();

        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html);
        libxml_clear_errors();

        if (!$loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $urls = [];

        foreach ([
            '//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="og:image"]/@content',
            '//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="twitter:image"]/@content',
            '//meta[translate(@itemprop, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="image"]/@content',
        ] as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $urls[] = [
                    'url' => $node->nodeValue,
                    'reason' => 'Imagem principal declarada na pagina.',
                    'score' => $this->imageRelevanceScore($node->nodeValue, $ad),
                ];
            }
        }

        foreach ($xpath->query('//img') ?: [] as $img) {
            $src = $this->imageSource($img);
            $alt = $img->getAttribute('alt');
            $score = $this->imageRelevanceScore($src . ' ' . $alt, $ad);

            if ($score >= 40) {
                $urls[] = [
                    'url' => $src,
                    'reason' => trim($alt) ?: 'Imagem encontrada no conteudo da pagina.',
                    'score' => $score,
                ];
            }
        }

        return collect($urls)
            ->map(function ($image) use ($page) {
                $url = $this->absoluteUrl((string) $image['url'], (string) $page['url']);

                return [
                    'source' => 'web_openai',
                    'url' => $url,
                    'page_url' => $page['url'],
                    'page_title' => $page['title'],
                    'match_reason' => $image['reason'],
                    'confidence_score' => $image['score'] ?? null,
                    'selected' => false,
                ];
            })
            ->filter(fn($image) => $this->isLikelyProductImageUrl($image['url']))
            ->sortByDesc('confidence_score')
            ->values()
            ->all();
    }

    private function imageSource(\DOMElement $img): string
    {
        foreach (['data-src', 'data-original', 'data-lazy-src', 'data-zoom-image', 'src'] as $attribute) {
            $value = trim($img->getAttribute($attribute));

            if ($value !== '') {
                return $value;
            }
        }

        $srcset = trim($img->getAttribute('srcset'));

        if ($srcset === '') {
            return '';
        }

        $first = trim(explode(',', $srcset)[0] ?? '');

        return trim(explode(' ', $first)[0] ?? '');
    }

    private function imageRelevanceScore(string $text, ZcmPendingAd $ad): int
    {
        $haystack = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $text));
        $reference = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $ad->reference));

        if ($reference !== '' && str_contains($haystack, $reference)) {
            return 100;
        }

        if ($this->requiresStrictImageMatch($ad)) {
            foreach ($this->strictImageNeedles($ad) as $needle) {
                if (str_contains($haystack, $needle)) {
                    return 90;
                }
            }

            return 0;
        }

        foreach ($this->imageRelevanceNeedles($ad) as $needle) {
            if (str_contains($haystack, $needle)) {
                return 60;
            }
        }

        return 0;
    }

    private function requiresStrictImageMatch(ZcmPendingAd $ad): bool
    {
        $text = strtoupper((string) $ad->reference . ' ' . (string) $ad->title . ' ' . (string) $ad->category);

        return str_contains($text, 'INVERSOR')
            || str_contains($text, 'INVERTER')
            || str_contains($text, 'CONVERSOR')
            || str_contains($text, 'CONVERTER');
    }

    private function strictImageNeedles(ZcmPendingAd $ad): array
    {
        $text = strtoupper((string) $ad->reference . ' ' . (string) $ad->title . ' ' . (string) $ad->category);
        $needles = ['INVERTER', 'INVERTOR', 'INVERSOR', 'CONVERTER', 'CONVERSOR', 'VOLTAGE'];

        preg_match_all('/A\d{6,}/i', $text, $matches);

        foreach ($matches[0] ?? [] as $match) {
            $needles[] = strtoupper($match);
        }

        return array_values(array_unique($needles));
    }

    private function imageRelevanceNeedles(ZcmPendingAd $ad): array
    {
        $text = strtoupper((string) $ad->reference . ' ' . (string) $ad->title . ' ' . (string) $ad->category);
        $needles = ['MERCEDES'];

        if (str_contains($text, 'ABS')) {
            $needles = array_merge($needles, ['ABS', 'W204', '204']);
        }

        if (str_contains($text, 'W205')) {
            $needles = array_merge($needles, ['W205', '205', 'INVERTER', 'INVERTOR', 'INVERSOR', 'CONVERTER', 'CONVERSOR', 'VOLTAGE']);
        }

        preg_match_all('/A\d{6,}/i', $text, $matches);

        foreach ($matches[0] ?? [] as $match) {
            $needles[] = strtoupper($match);
        }

        return array_values(array_unique($needles));
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        if ($url === '' || str_starts_with($url, 'data:')) {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $base = parse_url($baseUrl);

        if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return $base['scheme'] . ':' . $url;
        }

        if (str_starts_with($url, '/')) {
            return $base['scheme'] . '://' . $base['host'] . $url;
        }

        $path = isset($base['path']) ? rtrim(dirname($base['path']), '/\\') : '';

        return $base['scheme'] . '://' . $base['host'] . $path . '/' . $url;
    }

    private function isLikelyProductImageUrl(string $url): bool
    {
        if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
            return false;
        }

        if (!preg_match('/\.(jpg|jpeg|png|webp)(\?|$)/i', $url)) {
            return false;
        }

        $decorativePatterns = [
            'logo',
            'icon',
            'sprite',
            'flag',
            'flg-',
            'wishlist',
            'questao',
            'pagamento',
            'payment',
            'euro',
            'placeholder',
            'avatar',
            'loader',
            'spinner',
        ];

        $normalized = mb_strtolower($url);

        foreach ($decorativePatterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return false;
            }
        }

        return true;
    }
}
