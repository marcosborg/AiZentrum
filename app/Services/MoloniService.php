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

    public function getCountries(): array
    {
        $token = $this->refreshAccessToken();

        $response = Http::asForm()->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post("{$this->baseUrl}/countries/getAll/?access_token={$token}", [
            'company_id' => config('services.moloni.company_id'),
        ]);

        if (!$response->ok()) {
            throw new \Exception('Erro ao buscar países: ' . $response->body());
        }

        return $response->json();
    }

    public function createSupplier(array $data): array
    {
        $token = $this->refreshAccessToken();

        $payload = [
            'company_id' => config('services.moloni.company_id'),
            'vat' => $data['vat'] ?? '',
            'number' => $data['number'] ?? '',
            'name' => $data['name'],
            'language_id' => 1,
            'address' => $data['address'] ?? '',
            'zip_code' => $data['zip_code'] ?? '',
            'city' => $data['city'] ?? '',
            'country_id' => $data['country_id'],
            'maturity_date_id' => 70679,
            'qty_copies_document' => 3,
            'payment_method_id' => 69611,
            'discount' => 0,
            'credit_limit' => 0,
            'delivery_method_id' => 1,
        ];

        $response = Http::asForm()->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post("{$this->baseUrl}/suppliers/insert/?access_token={$token}", $payload);

        $data = $response->json();

        if (!$response->ok() || !isset($data['valid']) || empty($data['supplier_id'])) {
            throw new \Exception('Fornecedor criado, mas não foi possível confirmar os dados: ' . json_encode($data));
        }

        return [
            'supplier_id' => $data['supplier_id'],
            'name' => $payload['name'], // devolvemos manualmente o nome que enviámos
        ];
    }
}
