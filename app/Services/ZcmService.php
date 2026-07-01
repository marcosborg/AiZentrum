<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ZcmService
{
    private string $base;
    private string $token;
    private string $managerBase;
    private string $managerToken;

    public function __construct()
    {
        $this->base  = rtrim((string) config('services.zcm.base', env('ZCM_API_BASE')), '/');
        $this->token = (string) config('services.zcm.token', env('ZCM_API_TOKEN'));
        $this->managerBase = rtrim((string) config('services.zcm.manager_base', env('ZCMANAGER_API_URL', 'https://zcmanager.com')), '/');
        $this->managerToken = (string) config('services.zcm.manager_token', env('ZCMANAGER_API_TOKEN'));
    }

    public function dashboard(?string $from = null, ?string $to = null): array
    {
        $url = "{$this->base}/dashboard";

        $query = [];
        if ($from) {
            $query['from'] = $from;
        }
        if ($to) {
            $query['to']   = $to;
        }

        $resp = Http::timeout(15)
            ->withHeaders(['New-Api-Token' => $this->token])
            ->get($url, $query);

        if (!$resp->ok()) {
            throw new \RuntimeException("ZCM dashboard HTTP {$resp->status()}: " . $resp->body());
        }

        return $resp->json() ?? [];
    }

    public function pendingAds(array $filters = []): array
    {
        $this->ensureAdsConfigured();

        $query = array_filter($filters, static function ($value) {
            return $value !== null && $value !== '';
        });

        $resp = Http::timeout(30)
            ->acceptJson()
            ->withToken($this->managerToken)
            ->get("{$this->managerBase}/api/ai/ads/pending", $query);

        if (!$resp->ok()) {
            throw new \RuntimeException("ZCM pending ads HTTP {$resp->status()}: " . $resp->body());
        }

        return $resp->json() ?? [];
    }

    public function markAdsAsSent(array $ids): array
    {
        $this->ensureAdsConfigured();

        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return ['skipped' => true, 'message' => 'No imported ad ids to mark as sent.'];
        }

        $resp = Http::timeout(30)
            ->acceptJson()
            ->withToken($this->managerToken)
            ->post("{$this->managerBase}/api/ai/ads/mark-as-sent", [
                'ids' => $ids,
            ]);

        if (!$resp->ok()) {
            throw new \RuntimeException("ZCM mark-as-sent HTTP {$resp->status()}: " . $resp->body());
        }

        return $resp->json() ?? ['success' => true];
    }

    public function adsConfigured(): bool
    {
        return $this->managerBase !== '' && $this->managerToken !== '';
    }

    private function ensureAdsConfigured(): void
    {
        if (!$this->adsConfigured()) {
            throw new \RuntimeException('Configura ZCMANAGER_API_TOKEN no ficheiro .env para sincronizar anuncios pendentes.');
        }
    }
}
