<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;

use Illuminate\Http\Request;

class RefSearchController extends Controller
{
    public function index(Request $r)
    {
        $ref = trim($r->query('ref', ''));
        abort_if($ref === '', 400, 'ref obrigatório');

        $base = config('services.ps.url');        // https://techniczentrum.com/api
        $key  = config('services.ps.key');        // WS key

        $headers = ['Authorization' => 'Basic ' . base64_encode($key . ':')];
        $paramsP = [
            'filter[reference]' => "[$ref]",
            'display'           => '[id,reference,ean13,price,id_default_image,link_rewrite,name]',
            'language'          => 1,
            'output_format'     => 'JSON',
        ];

        // 1) products por referência
        $p = Http::withHeaders($headers)->get("$base/products", $paramsP)->json();

        // 2) combinations por referência
        $paramsC = [
            'filter[reference]' => "[$ref]",
            'display'           => '[id,id_product,reference,ean13,quantity,price]',
            'output_format'     => 'JSON',
        ];
        $c = Http::withHeaders($headers)->get("$base/combinations", $paramsC)->json();

        // 3) se nada, fallback para /api/search
        $s = [];
        if (empty($p['products']) && empty($c['combinations'])) {
            $s = Http::withHeaders($headers)->get("$base/search", [
                'query'         => $ref,
                'language'      => 1,
                'output_format' => 'JSON',
            ])->json();
        }

        // Normalizar (exemplo simples)
        $items = [];
        foreach (($p['products'] ?? []) as $prod) {
            $items[] = [
                'product_id'  => (int)$prod['id'],
                'combination_id' => null,
                'reference'   => $prod['reference'] ?? null,
                'ean13'       => $prod['ean13'] ?? null,
                'name'        => is_array($prod['name']) ? ($prod['name'][0]['value'] ?? '') : ($prod['name'] ?? ''),
                'qty'         => null, // podes puxar de stock_availables
                'price'       => (float)($prod['price'] ?? 0),
                'url'         => url("/pt/{$prod['id']}-{$prod['link_rewrite']}"),
                'image'       => url("/api/images/products/{$prod['id']}/{$prod['id_default_image']}"),
            ];
        }

        foreach (($c['combinations'] ?? []) as $comb) {
            // buscar info base do product
            $pid = (int)$comb['id_product'];
            $prod = Http::withHeaders($headers)->get("$base/products", [
                'filter[id]'       => "[$pid]",
                'display'          => '[id,name,link_rewrite,id_default_image]',
                'language'         => 1,
                'output_format'    => 'JSON',
            ])->json();
            $p1 = $prod['products'][0] ?? null;
            $items . push([
                'product_id'     => $pid,
                'combination_id' => (int)$comb['id'],
                'reference'      => $comb['reference'] ?? null,
                'ean13'          => $comb['ean13'] ?? null,
                'name'           => $p1 ? (is_array($p1['name']) ? ($p1['name'][0]['value'] ?? '') : $p1['name']) : '',
                'qty'            => $comb['quantity'] ?? null,
                'price'          => (float)($comb['price'] ?? 0),
                'url'            => $p1 ? url("/pt/{$p1['id']}-{$p1['link_rewrite']}?comb={$comb['id']}") : null,
                'image'          => $p1 ? url("/api/images/products/{$p1['id']}/{$p1['id_default_image']}") : null,
            ]);
        }

        // fallback results (simplifica conforme a resposta do /search)
        // ...

        return response()->json($items);
    }
}
