@extends('layouts.admin')

@section('content')
<div class="content">
  @if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
  @endif
  @if(!$adsConfigured)
    <div class="alert alert-warning" role="alert">
      Define <strong>ZCMANAGER_API_TOKEN</strong> no ficheiro <strong>.env</strong> para ativar a sincronizacao dos anuncios pendentes.
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>An&uacute;ncios pendentes</strong>
      <form method="post" action="{{ route('admin.zcm.pending-ads.sync') }}" class="mb-0">
        @csrf
        <input type="hidden" name="reference" value="{{ request('reference') }}">
        <input type="hidden" name="user_id" value="{{ request('user_id') }}">
        <input type="hidden" name="from" value="{{ request('from') }}">
        <input type="hidden" name="per_page" value="{{ request('per_page') }}">
        <button type="submit" class="btn btn-primary btn-sm" {{ $adsConfigured ? '' : 'disabled' }}>
          <i class="fas fa-sync-alt"></i> Sync an&uacute;ncios pendentes
        </button>
      </form>
    </div>
    <div class="card-body">
      <form method="get" class="row mb-3">
        <div class="col-md-3 mb-2">
          <input type="text" name="reference" value="{{ request('reference') }}" class="form-control form-control-sm" placeholder="Reference">
        </div>
        <div class="col-md-2 mb-2">
          <input type="text" name="user_id" value="{{ request('user_id') }}" class="form-control form-control-sm" placeholder="User ID">
        </div>
        <div class="col-md-2 mb-2">
          <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 mb-2">
          <input type="number" name="per_page" value="{{ request('per_page') }}" class="form-control form-control-sm" placeholder="Per page" min="1">
        </div>
        <div class="col-md-3 mb-2">
          <button class="btn btn-outline-primary btn-sm">Aplicar filtros</button>
          <a href="{{ route('admin.zcm.pending-ads.index') }}" class="btn btn-outline-secondary btn-sm">Limpar</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
          <thead>
            <tr>
              <th>ID ZCM</th>
              <th>Reference</th>
              <th>Title</th>
              <th>Description</th>
              <th>Price</th>
              <th>Category</th>
              <th>Brand model</th>
              <th>Images</th>
              <th>Requested by</th>
              <th>Status</th>
              <th>Created at</th>
              <th>Updated at</th>
            </tr>
          </thead>
          <tbody>
            @forelse($ads as $ad)
              <tr>
                <td>{{ $ad->zcmanager_ad_id }}</td>
                <td>{{ $ad->reference }}</td>
                <td>{{ $ad->title }}</td>
                <td style="min-width: 260px;">{{ \Illuminate\Support\Str::limit($ad->description, 160) }}</td>
                <td>{{ $ad->price !== null ? number_format((float) $ad->price, 2, ',', ' ') . ' EUR' : '' }}</td>
                <td>{{ $ad->category }}</td>
                <td>{{ $ad->brand_model }}</td>
                <td>
                  @foreach(($ad->images ?? []) as $image)
                    @if(is_string($image))
                      <a href="{{ $image }}" target="_blank" rel="noopener" class="badge badge-light">Imagem</a>
                    @else
                      <span class="badge badge-light">Imagem</span>
                    @endif
                  @endforeach
                </td>
                <td>{{ $ad->requested_by }}</td>
                <td>
                  <span class="badge badge-{{ $ad->sync_status === 'sent' ? 'success' : 'secondary' }}">{{ $ad->status ?: $ad->sync_status }}</span>
                </td>
                <td>{{ optional($ad->zcmanager_created_at)->format('Y-m-d H:i') }}</td>
                <td>{{ optional($ad->zcmanager_updated_at)->format('Y-m-d H:i') }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="12" class="text-center text-muted">Sem an&uacute;ncios importados.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{ $ads->links('pagination::bootstrap-4') }}
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <strong>Logs de sincronizacao</strong>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm mb-0">
          <thead>
            <tr>
              <th>Data</th>
              <th>Recebidos</th>
              <th>Importados</th>
              <th>Falhados</th>
              <th>Mark as sent</th>
              <th>Erros</th>
            </tr>
          </thead>
          <tbody>
            @forelse($syncLogs as $log)
              <tr>
                <td>{{ optional($log->ran_at)->format('Y-m-d H:i:s') }}</td>
                <td>{{ $log->total_received }}</td>
                <td>{{ $log->total_imported }}</td>
                <td>{{ $log->total_failed }}</td>
                <td>
                  <span class="badge badge-{{ $log->mark_as_sent_success ? 'success' : 'secondary' }}">
                    {{ $log->mark_as_sent_success ? 'OK' : 'Nao executado' }}
                  </span>
                </td>
                <td>
                  @foreach(($log->errors ?? []) as $error)
                    <div class="text-danger small">{{ $error }}</div>
                  @endforeach
                  @foreach(($log->failed_items ?? []) as $failed)
                    <div class="text-danger small">
                      {{ $failed['id'] ?? '-' }} {{ $failed['reference'] ?? '' }}: {{ $failed['error'] ?? '' }}
                    </div>
                  @endforeach
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted">Sem logs de sincronizacao.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
