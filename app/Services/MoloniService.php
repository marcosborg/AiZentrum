<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MoloniService
{
    protected string $baseUrl;
    protected string $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.moloni.api_url');
        $this->authenticate();
    }

    private function authenticate(): void
    {
        $response = Http::asForm()->post($this->baseUrl . '/grant/', [
            'grant_type' => 'password',
            'client_id' => config('services.moloni.client_id'),
            'client_secret' => config('services.moloni.client_secret'),
            'username' => config('services.moloni.username'),
            'password' => config('services.moloni.password'),
        ]);

        if (!$response->ok() || empty($response['access_token'])) {
            throw new \Exception('Falha ao autenticar na Moloni: ' . $response->body());
        }

        $this->accessToken = $response['access_token'];
    }

    private function request(string $endpoint, array $data): array
    {
        $response = Http::withToken($this->accessToken)
            ->post($this->baseUrl . '/' . ltrim($endpoint, '/'), $data);

        if (!$response->ok()) {
            throw new \Exception("Erro na Moloni ({$endpoint}): " . $response->body());
        }

        return $response->json();
    }

    public function findSupplierByVat(string $vat): ?array
    {
        $response = $this->request('entities/getByVat/', [
            'company_id' => config('services.moloni.company_id'),
            'vat' => $vat,
        ]);

        return $response ?: null;
    }

    public function findProductByReference(string $reference): ?array
    {
        $response = $this->request('products/getByReference/', [
            'company_id' => config('services.moloni.company_id'),
            'reference' => $reference,
        ]);

        return $response ?: null;
    }

    public function updateProductStock(int $productId, float $newStock): array
    {
        return $this->request('products/updateStock/', [
            'company_id' => config('services.moloni.company_id'),
            'product_id' => $productId,
            'stock' => $newStock,
        ]);
    }

    public function createPurchase(array $data): array
    {
        return $this->request('purchases/insert/', $data);
    }
}
