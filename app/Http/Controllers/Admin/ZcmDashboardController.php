<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ZcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class ZcmDashboardController extends Controller
{
    public function __invoke(Request $request, ZcmService $zcm)
    {
        // Datas opcionais no formato YYYY-MM-DD
        $from = $request->query('from');
        $to   = $request->query('to');

        // Cache por intervalo
        $cacheKey = 'zcm.dashboard.' . ($from ?: 'null') . '.' . ($to ?: 'null');

        try {
            $data = Cache::remember($cacheKey, 120, function () use ($zcm, $from, $to) {
                return $zcm->dashboard($from, $to);
            });
        } catch (\Throwable $e) {
            $data = Cache::get($cacheKey, ['zcmrequests' => []]);
        }

        $zcmrequests = $data['zcmrequests'] ?? [];

        // KPIs agregados
        $kpis = [
            'venda' => 0,
            'orcamentado_com_peca_cliente' => 0,
            'callback_sem_material_cliente' => 0,
            'reparado_ou_vendido_aguarda_pagamento' => 0,
        ];

        foreach ($zcmrequests as $user) {
            $s = $user['summary'] ?? [];
            $kpis['venda'] += (int) Arr::get($s, 'venda', 0);
            $kpis['orcamentado_com_peca_cliente'] += (int) Arr::get($s, 'orcamentado_com_peca_cliente', 0);
            $kpis['callback_sem_material_cliente'] += (int) Arr::get($s, 'callback_sem_material_cliente', 0);
            $kpis['reparado_ou_vendido_aguarda_pagamento'] += (int) Arr::get($s, 'reparado_ou_vendido_aguarda_pagamento', 0);
        }

        // ---- Preparação para gráficos
        $categories = [
            'venda'                                 => 'Vendas',
            'orcamentado_com_peca_cliente'          => 'Orçamentado (c/ peça cliente)',
            'callback_sem_material_cliente'         => 'CallBack (sem material cliente)',
            'reparado_ou_vendido_aguarda_pagamento' => 'Aguarda pagamento',
        ];

        $sellers = array_keys($zcmrequests); // ['jorge','carina','adriano','catia']

        // 1) Contagem por categoria e vendedor
        $countsBySeller = [];
        foreach ($sellers as $seller) {
            $countsBySeller[$seller] = [];
            foreach ($categories as $key => $label) {
                $countsBySeller[$seller][$key] = (int) Arr::get($zcmrequests, "$seller.summary.$key", 0);
            }
        }

        // 2) Valor € por categoria e vendedor (soma total/budget)
        $valuesBySeller = [];
        foreach ($sellers as $seller) {
            $valuesBySeller[$seller] = [];
            $dataBuckets = Arr::get($zcmrequests, "$seller.data", []);
            foreach ($categories as $key => $label) {
                $sum = 0.0;
                $items = Arr::get($dataBuckets, $key, []);
                foreach ($items as $r) {
                    $sum += (float) ($r['total'] ?? $r['budget'] ?? 0);
                }
                $valuesBySeller[$seller][$key] = round($sum, 2);
            }
        }

        // 3) Radar index — normalização simples (0..100) combinando contagem e valor €
        //    pesos: 60% valor, 40% contagem (ajusta como quiseres)
        $radarIndex = [];
        foreach ($categories as $key => $label) {
            $maxCount = 0;
            $maxValue = 0.0;
            foreach ($sellers as $seller) {
                $maxCount = max($maxCount, $countsBySeller[$seller][$key] ?? 0);
                $maxValue = max($maxValue, $valuesBySeller[$seller][$key] ?? 0.0);
            }
            foreach ($sellers as $seller) {
                $c = $countsBySeller[$seller][$key] ?? 0;
                $v = $valuesBySeller[$seller][$key] ?? 0.0;
                $countScore = $maxCount > 0 ? ($c / $maxCount) : 0;
                $valueScore = $maxValue > 0 ? ($v / $maxValue) : 0;
                $score = (0.4 * $countScore + 0.6 * $valueScore) * 100;
                $radarIndex[$seller][$key] = round($score, 1);
            }
        }

        // 4) Idade média (dias) por vendedor (para leitura de urgência / SLA)
        $avgAgeDays = [];
        foreach ($sellers as $seller) {
            $ages = [];
            $dataBuckets = Arr::get($zcmrequests, "$seller.data", []);
            foreach ($dataBuckets as $items) {
                foreach ($items as $r) {
                    if (!empty($r['created_at'])) {
                        $created = Carbon::parse($r['created_at']);
                        $ages[] = now()->diffInDays($created);
                    }
                }
            }
            $avgAgeDays[$seller] = count($ages) ? round(array_sum($ages)/count($ages), 1) : 0;
        }

        return view('admin.zcm.dashboard', [
            'zcmrequests'    => $zcmrequests,
            'kpis'           => $kpis,
            'from'           => $from,
            'to'             => $to,
            // gráficos
            'categories'     => $categories,
            'sellers'        => $sellers,
            'countsBySeller' => $countsBySeller,
            'valuesBySeller' => $valuesBySeller,
            'radarIndex'     => $radarIndex,
            'avgAgeDays'     => $avgAgeDays,
        ]);
    }
}
