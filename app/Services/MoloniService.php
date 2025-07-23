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

    public function searchProductByReference(string $reference): array
    {
        $token = $this->refreshAccessToken();

        $response = Http::asForm()->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post("{$this->baseUrl}/products/getBySearch/?access_token={$token}", [
            'company_id' => config('services.moloni.company_id'),
            'search' => $reference,
        ]);

        if (!$response->ok()) {
            throw new \Exception('Erro ao pesquisar produto: ' . $response->body());
        }

        return $response->json(); // pode devolver um array vazio se não encontrar nada
    }

    public function updateProductStockAndInfo(array $product, array $item): array
    {
        $token = $this->refreshAccessToken();

        // Obter o stock atual
        $currentStock = $product['stock'] ?? 0;
        $newStock = $currentStock + (float) $item['quantity'];

        $payload = [
            'company_id'   => config('services.moloni.company_id'),
            'product_id'   => $product['product_id'],
            'category_id'  => $product['category_id'] ?? 127495, // usa a categoria existente ou uma default
            'type'         => 1, // Produto
            'name'         => strtoupper($item['description']), // nome com descrição
            'reference'    => $item['reference'],
            'price'        => $item['unit_price'],
            'unit_id'      => $product['unit_id'] ?? 86267, // default: Unidade
            'has_stock'    => 1,
            'stock'        => $newStock,
        ];

        $response = Http::asForm()->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post("{$this->baseUrl}/products/update/?access_token={$token}", $payload);

        if (!$response->ok()) {
            throw new \Exception('Erro ao atualizar produto: ' . $response->body());
        }

        return $response->json();
    }
}
