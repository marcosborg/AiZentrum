@extends('layouts.admin')

@section('content')
<div class="container">
  <h1 class="mb-4">Dashboard de Performance (ZCManager)</h1>

  {{-- Filtros simples (opcional) --}}
  <form method="get" class="row g-2 mb-3">
    <div class="col-auto">
      <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm" />
    </div>
    <div class="col-auto">
      <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm" />
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-outline-primary">Aplicar</button>
    </div>
  </form>

  {{-- KPIs --}}
  <div class="row g-3 mb-4">
    @php
      $cards = [
        'Vendas' => $kpis['venda'] ?? 0,
        'Orçamentado (c/ peça cliente)' => $kpis['orcamentado_com_peca_cliente'] ?? 0,
        'CallBack (sem material cliente)' => $kpis['callback_sem_material_cliente'] ?? 0,
        'Reparado/Vendido (aguarda pagamento)' => $kpis['reparado_ou_vendido_aguarda_pagamento'] ?? 0,
      ];
    @endphp
    @foreach($cards as $title => $value)
      <div class="col-md-3">
        <div class="card h-100">
          <div class="card-body">
            <div class="h6 text-muted">{{ $title }}</div>
            <div class="display-6">{{ $value }}</div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Gráficos --}}
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title mb-3">Comparativo por Vendedor — Contagem por Categoria</h5>
          <canvas id="chartCounts" aria-label="Gráfico comparativo de contagem por categoria" role="img"></canvas>
          <div class="text-muted small mt-2">Barras empilhadas: quantos pedidos cada vendedor tem em cada etapa.</div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title mb-3">Comparativo por Vendedor — Valor (€) por Categoria</h5>
          <canvas id="chartValues" aria-label="Gráfico comparativo de valor por categoria" role="img"></canvas>
          <div class="text-muted small mt-2">Barras agrupadas: soma de orçamentos/valores por vendedor.</div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title mb-3">Perfil por Vendedor (Radar)</h5>
          <canvas id="chartRadar" aria-label="Radar de desempenho por vendedor" role="img"></canvas>
          <div class="text-muted small mt-2">Índice 0–100 resultante de contagem (40%) + valor (60%), normalizados por categoria.</div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title mb-3">Idade Média dos Tickets (dias)</h5>
          <canvas id="chartAges" aria-label="Idade média dos tickets por vendedor" role="img"></canvas>
          <div class="text-muted small mt-2">Quanto menor melhor. Útil para perceber retenções no funil.</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Tabela detalhada --}}
  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>Vendedor</th>
        <th>Vendas</th>
        <th>Orçamentado (c/ peça cliente)</th>
        <th>CallBack (sem material cliente)</th>
        <th>Reparado/Vendido (aguarda pagamento)</th>
        <th>Detalhes</th>
      </tr>
    </thead>
    <tbody>
      @foreach($zcmrequests as $nome => $info)
        @php
          $s = $info['summary'] ?? [];
          $slug = \Illuminate\Support\Str::slug($nome, '-');
        @endphp
        <tr>
          <td class="fw-semibold text-capitalize">{{ $nome }}</td>
          <td>{{ $s['venda'] ?? 0 }}</td>
          <td>{{ $s['orcamentado_com_peca_cliente'] ?? 0 }}</td>
          <td>{{ $s['callback_sem_material_cliente'] ?? 0 }}</td>
          <td>{{ $s['reparado_ou_vendido_aguarda_pagamento'] ?? 0 }}</td>
          <td>
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#det-{{ $slug }}">Ver</button>
          </td>
        </tr>
        <tr class="collapse" id="det-{{ $slug }}">
          <td colspan="6">
            <div class="row">
              @foreach(($info['data'] ?? []) as $categoria => $items)
                <div class="col-md-6 mb-3">
                  <h6 class="mb-2">{{ str_replace('_',' ', ucfirst($categoria)) }}</h6>
                  @if(empty($items))
                    <div class="text-muted small">Sem registos.</div>
                  @else
                    <div class="table-responsive">
                      <table class="table table-sm">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Criado em</th>
                          </tr>
                        </thead>
                        <tbody>
                          @foreach($items as $r)
                            @php
                              $statusName = data_get($r, 'histories.0.status.name', '-');
                              if (!$statusName && !empty($r['histories'])) {
                                  $last = last($r['histories']);
                                  $statusName = data_get($last, 'status.name', '-');
                              }
                              $valor = (float)($r['total'] ?? $r['budget'] ?? 0);
                            @endphp
                            <tr>
                              <td>{{ $r['id'] }}</td>
                              <td>{{ data_get($r, 'client.name', '-') }}</td>
                              <td>{{ $statusName }}</td>
                              <td>{{ number_format($valor, 2, ',', ' ') }} €</td>
                              <td>{{ $r['created_at'] }}</td>
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection

@section('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
    window.addEventListener('load', function () {
      const sellers     = @json($sellers);
      const categoriesK = @json(array_keys($categories));
      const categoriesL = @json(array_values($categories));
      const counts      = @json($countsBySeller);
      const values      = @json($valuesBySeller);
      const radarIndex  = @json($radarIndex);
      const avgAgeDays  = @json($avgAgeDays);

      const palette = [
        'rgba(75, 192, 192, 0.7)',
        'rgba(255, 205, 86, 0.7)',
        'rgba(54, 162, 235, 0.7)',
        'rgba(255, 99, 132, 0.7)',
        'rgba(153, 102, 255, 0.7)',
        'rgba(201, 203, 207, 0.7)'
      ];

      const dsCountsStacked = categoriesK.map((catKey, idx) => ({
        label: categoriesL[idx],
        data: sellers.map(s => counts[s]?.[catKey] ?? 0),
        backgroundColor: palette[idx % palette.length],
        borderWidth: 0
      }));

      const dsValuesGrouped = categoriesK.map((catKey, idx) => ({
        label: categoriesL[idx],
        data: sellers.map(s => values[s]?.[catKey] ?? 0),
        backgroundColor: palette[idx % palette.length],
        borderWidth: 0
      }));

      const dsRadar = sellers.map((s, idx) => ({
        label: s,
        data: categoriesK.map(cat => radarIndex[s]?.[cat] ?? 0),
        backgroundColor: palette[idx % palette.length].replace('0.7','0.25'),
        borderColor: palette[idx % palette.length].replace('0.7','1'),
        borderWidth: 1,
        pointRadius: 2
      }));

      const dsAges = [{
        label: 'Idade média (dias)',
        data: sellers.map(s => avgAgeDays[s] ?? 0),
        backgroundColor: palette[3],
        borderWidth: 0
      }];

      new Chart(document.getElementById('chartCounts'), {
        type: 'bar',
        data: { labels: sellers.map(s => s.charAt(0).toUpperCase()+s.slice(1)), datasets: dsCountsStacked },
        options: {
          responsive: true,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { footer: items => 'Total: ' + items.reduce((a,i)=>a+i.parsed.y,0) } }
          },
          scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } }
        }
      });

      new Chart(document.getElementById('chartValues'), {
        type: 'bar',
        data: { labels: sellers.map(s => s.charAt(0).toUpperCase()+s.slice(1)), datasets: dsValuesGrouped },
        options: {
          responsive: true,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString('pt-PT',{minimumFractionDigits:2})} €` } }
          },
          scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('pt-PT') + ' €' } } }
        }
      });

      new Chart(document.getElementById('chartRadar'), {
        type: 'radar',
        data: { labels: categoriesL, datasets: dsRadar },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { r: { suggestedMin: 0, suggestedMax: 100, ticks: { stepSize: 20 } } } }
      });

      new Chart(document.getElementById('chartAges'), {
        type: 'bar',
        data: { labels: sellers.map(s => s.charAt(0).toUpperCase()+s.slice(1)), datasets: dsAges },
        options: {
          indexAxis: 'y',
          responsive: true,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => `${ctx.parsed.x.toLocaleString('pt-PT')} dias` } } },
          scales: { x: { beginAtZero: true, ticks: { callback: v => v + ' d' } } }
        }
      });
    });
  </script>
@endsection
