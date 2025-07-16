<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class MoloniService
{
    private string $clientId;
    private string $clientSecret;
    private string $username;
    private string $password;
    private string $baseUrl = 'https://api.moloni.pt/v1';

    public function __construct()
    {
        $this->clientId = config('services.moloni.client_id');
        $this->clientSecret = config('services.moloni.client_secret');
        $this->username = config('services.moloni.username');
        $this->password = config('services.moloni.password');
    }

    private function refreshAccessToken(): string
    {
        $token = Cache::get('moloni_access_token');

        if ($token) {
            return $token;
        }

        $response = Http::get("{$this->baseUrl}/grant/", [
            'grant_type' => 'password',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if (!$response->ok()) {
            throw new \Exception('Falha ao obter token de acesso: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['access_token'])) {
            throw new \Exception('Resposta inválida ao obter token de acesso.');
        }

        Cache::put('moloni_access_token', $data['access_token'], now()->addMinutes(50));

        return $data['access_token'];
    }

    public function getSuppliersByName(string $name): array
    {
        $token = $this->refreshAccessToken();

        $response = Http::asForm()->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post("{$this->baseUrl}/suppliers/getByName/?access_token={$token}", [
            'company_id' => config('services.moloni.company_id'),
            'name' => $name,
        ]);

        if (!$response->ok()) {
            throw new \Exception('Erro ao buscar fornecedores: ' . $response->body());
        }

        return $response->json();
    }
}
