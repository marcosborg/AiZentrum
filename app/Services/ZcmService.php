<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ZcmService
{
    private string $base;
    private string $token;

    public function __construct()
    {
        $this->base  = rtrim(config('services.zcm.base', env('ZCM_API_BASE')), '/');
        $this->token = (string) env('ZCM_API_TOKEN');
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
}
