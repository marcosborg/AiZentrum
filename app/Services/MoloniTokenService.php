<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MoloniTokenService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.moloni.api_url');
    }

    public function getValidAccessToken(): string
    {
        $tokens = $this->getTokens();

        if (!$tokens || Carbon::now()->greaterThan(Carbon::parse($tokens['expires_at']))) {
            return $this->refreshOrAuthenticate();
        }

        return $tokens['access_token'];
    }


    private function getTokens(): ?array
    {
        if (!Storage::exists('moloni_tokens.json')) {
            return null;
        }

        return json_decode(Storage::get('moloni_tokens.json'), true);
    }

    private function refreshOrAuthenticate(): string
    {
        $tokens = $this->getTokens();

        if ($tokens && isset($tokens['refresh_token'])) {
            $response = Http::get($this->baseUrl . '/grant/', [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.moloni.client_id'),
                'client_secret' => config('services.moloni.client_secret'),
                'refresh_token' => $tokens['refresh_token'],
            ]);

            if ($response->ok()) {
                return $this->saveTokens($response->json());
            }
        }

        return $this->authenticate();
    }

    private function authenticate(): string
    {
        \Log::debug('Moloni: iniciando autenticação completa');
        $response = Http::get($this->baseUrl . '/grant/', [
            'grant_type' => 'password',
            'client_id' => config('services.moloni.client_id'),
            'client_secret' => config('services.moloni.client_secret'),
            'username' => config('services.moloni.username'),
            'password' => config('services.moloni.password'),
        ]);

        if (!$response->ok() || empty($response['access_token'])) {
            \Log::error('Falha na autenticação Moloni', [
                'body' => $response->body(),
                'config' => config('services.moloni'),
            ]);
            throw new \Exception('Não foi possível autenticar na API Moloni.');
        }

        return $this->saveTokens($response->json());
    }

    private function saveTokens(array $data): string
    {
        $tokens = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => now()->addSeconds($data['expires_in'])->toDateTimeString(),
        ];

        Storage::put('moloni_tokens.json', json_encode($tokens));

        return $tokens['access_token'];
    }
}
