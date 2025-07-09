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

        // Obtém token válido do serviço de tokens
        $this->accessToken = app(MoloniTokenService::class)->getValidAccessToken();
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

    public function createSupplier(string $name, string $nif = '999999990'): array
    {
        return $this->request('entities/insert/', [
            'company_id' => config('services.moloni.company_id'),
            'name' => $name,
            'vat' => $nif,
            'language_id' => 1, // Português
            'country_id' => 1   // Portugal
        ]);
    }



    public function findSupplier(string $name): ?array
    {
        if (!empty($name)) {
            try {
                $suppliers = $this->request('entities/getAll/', [
                    'company_id' => config('services.moloni.company_id'),
                    'options' => [
                        'search' => $name,
                        'search_field' => 'name',
                        'qty' => 10,
                    ],
                ]);

                $normalize = fn($string) => strtolower(
                    preg_replace('/[-\s]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $string))
                );

                $normalizedSearchedName = $normalize($name);

                foreach ($suppliers as $s) {
                    if (isset($s['name']) && $normalize($s['name']) === $normalizedSearchedName) {
                        return $s;
                    }
                }

                if (!empty($suppliers)) {
                    return $suppliers[0];
                }
            } catch (\Exception $e) {
                \Log::warning("Fornecedor não encontrado por nome ({$name}): " . $e->getMessage());
            }
        }

        return null;
    }


    public function findProductByReference(string $reference): ?array
    {
        return $this->request('products/getByReference/', [
            'company_id' => config('services.moloni.company_id'),
            'reference' => $reference,
        ]);
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
