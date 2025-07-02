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

    public function findSupplierByVat(string $vat): ?array
    {
        return $this->request('entities/getByVat/', [
            'company_id' => config('services.moloni.company_id'),
            'vat' => $vat,
        ]);
    }

    public function findSupplier(string $vat = null, string $name = null): ?array
    {
        if (!empty($vat)) {
            try {
                $supplier = $this->findSupplierByVat($vat);
                if ($supplier) {
                    return $supplier;
                }
            } catch (\Exception $e) {
                \Log::warning("Fornecedor não encontrado por VAT ({$vat}): " . $e->getMessage());
            }
        }

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

                // Função para normalizar nomes: remove espaços, hífens, acentos e converte para minúsculas
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
                    // Se não encontrar match "quase exato", devolve o primeiro
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
