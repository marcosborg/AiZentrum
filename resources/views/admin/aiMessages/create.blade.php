@extends('layouts.admin')

@section('content')
    <div class="card">
        <div class="card-header">
            {{ trans('global.create') }} {{ trans('cruds.aiMessage.title_singular') }}
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.ai-messages.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="row">
                    {{-- Pesquisa (email ou NIF) e autocompletar --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="client_search">Pesquisar cliente (email ou NIF)</label>
                            <input
                                type="text"
                                id="client_search"
                                class="form-control"
                                placeholder="Digite o email ou NIF do cliente..."
                                {{ ($ai_message_id ?? 0) !== 0 ? 'disabled' : '' }}
                            >
                            <small class="form-text text-muted">
                                Seleciona um cliente para preencher automaticamente os campos abaixo e carregar o histórico.
                            </small>
                        </div>
                    </div>

                    {{-- Nome do cliente --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="client_name">Client name</label>
                            <input
                                type="text"
                                name="client_name"
                                id="client_name"
                                class="form-control"
                                value="{{ $ai_message ? $ai_message->client_name : '' }}"
                            >
                        </div>
                    </div>

                    {{-- ZCM client ID (a nossa "thread") --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="required" for="client">ZCM client ID</label>
                            <input
                                class="form-control {{ $errors->has('client') ? 'is-invalid' : '' }}"
                                type="number"
                                name="client"
                                id="client"
                                value="{{ old('client', isset($threadId) ? $threadId : ($ai_message ? $ai_message->client : '')) }}"
                                step="1"
                                required
                            >
                            @if ($errors->has('client'))
                                <div class="invalid-feedback">{{ $errors->first('client') }}</div>
                            @endif
                            <span class="help-block">{{ trans('cruds.aiMessage.fields.client_helper') }}</span>
                        </div>
                    </div>

                    {{-- Indicador de Thread --}}
                    <div class="col-md-3">
                        <div class="alert alert-info" role="alert" style="margin-top: 2rem;">
                            <strong>Thread:</strong>
                            <span class="ml-1">{{ $threadId ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Email / NIF / User --}}
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="email">{{ trans('cruds.aiMessage.fields.email') }}</label>
                            <input
                                class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                type="text"
                                name="email"
                                id="email"
                                value="{{ old('email', $ai_message ? $ai_message->email : '') }}"
                            >
                            @if ($errors->has('email'))
                                <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                            @endif
                            <span class="help-block">{{ trans('cruds.aiMessage.fields.email_helper') }}</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="nif">{{ trans('cruds.aiMessage.fields.nif') }}</label>
                            <input
                                class="form-control {{ $errors->has('nif') ? 'is-invalid' : '' }}"
                                type="text"
                                name="nif"
                                id="nif"
                                value="{{ old('nif', $ai_message ? $ai_message->nif : '') }}"
                            >
                            @if ($errors->has('nif'))
                                <div class="invalid-feedback">{{ $errors->first('nif') }}</div>
                            @endif
                            <span class="help-block">{{ trans('cruds.aiMessage.fields.nif_helper') }}</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required" for="user_id">{{ trans('cruds.aiMessage.fields.user') }}</label>
                            <select
                                class="form-control select2 {{ $errors->has('user') ? 'is-invalid' : '' }}"
                                name="user_id"
                                id="user_id"
                                required
                            >
                                @foreach ($users as $id => $entry)
                                    <option value="{{ $id }}" {{ old('user_id', auth()->id()) == $id ? 'selected' : '' }}>
                                        {{ $entry }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($errors->has('user'))
                                <div class="invalid-feedback">{{ $errors->first('user') }}</div>
                            @endif
                            <span class="help-block">{{ trans('cruds.aiMessage.fields.user_helper') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Histórico --}}
                @if (isset($history) && $history->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header">Histórico do cliente</div>
                        <div class="card-body" style="max-height: 400px; overflow:auto;">
                            @foreach ($history as $m)
                                @if ($m->context)
                                    <div class="mb-2">
                                        <strong>Cliente:</strong>
                                        <div class="border rounded p-2">{{ $m->context }}</div>
                                        <small class="text-muted">{{ $m->created_at }}</small>
                                    </div>
                                @endif
                                @if ($m->ai_response)
                                    <div class="mb-3">
                                        <strong>Assistente:</strong>
                                        <div class="border rounded bg-light p-2">{{ $m->ai_response }}</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Contexto / Resposta --}}
                <div class="form-group">
                    <label for="context">{{ trans('cruds.aiMessage.fields.context') }}</label>
                    <textarea
                        class="form-control {{ $errors->has('context') ? 'is-invalid' : '' }}"
                        name="context"
                        id="context"
                    >{{ old('context') }}</textarea>
                    @if ($errors->has('context'))
                        <div class="invalid-feedback">{{ $errors->first('context') }}</div>
                    @endif
                    <span class="help-block">{{ trans('cruds.aiMessage.fields.context_helper') }}</span>
                </div>

                <div class="form-group">
                    <label for="ai_response">{{ trans('cruds.aiMessage.fields.ai_response') }}</label>
                    <textarea
                        class="form-control {{ $errors->has('ai_response') ? 'is-invalid' : '' }}"
                        name="ai_response"
                        id="ai_response"
                    >{{ old('ai_response') }}</textarea>
                    @if ($errors->has('ai_response'))
                        <div class="invalid-feedback">{{ $errors->first('ai_response') }}</div>
                    @endif
                    <span class="help-block">{{ trans('cruds.aiMessage.fields.ai_response_helper') }}</span>
                </div>

                {{-- Radios / Checkbox --}}
                <div class="form-group">
                    <label class="required">{{ trans('cruds.aiMessage.fields.conflict_type') }}</label>
                    @foreach (App\Models\AiMessage::CONFLICT_TYPE_RADIO as $key => $label)
                        <div class="form-check {{ $errors->has('conflict_type') ? 'is-invalid' : '' }}">
                            <input
                                class="form-check-input"
                                type="radio"
                                id="conflict_type_{{ $key }}"
                                name="conflict_type"
                                value="{{ $key }}"
                                {{ old('conflict_type', $ai_message ? $ai_message->conflict_type : '') === (string) $key ? 'checked' : '' }}
                                required
                            >
                            <label class="form-check-label" for="conflict_type_{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    @if ($errors->has('conflict_type'))
                        <div class="invalid-feedback">{{ $errors->first('conflict_type') }}</div>
                    @endif
                    <span class="help-block">{{ trans('cruds.aiMessage.fields.conflict_type_helper') }}</span>
                </div>

                <div class="form-group">
                    <label class="required">{{ trans('cruds.aiMessage.fields.urgency') }}</label>
                    @foreach (App\Models\AiMessage::URGENCY_RADIO as $key => $label)
                        <div class="form-check {{ $errors->has('urgency') ? 'is-invalid' : '' }}">
                            <input
                                class="form-check-input"
                                type="radio"
                                id="urgency_{{ $key }}"
                                name="urgency"
                                value="{{ $key }}"
                                {{ old('urgency', $ai_message ? $ai_message->urgency : '') === (string) $key ? 'checked' : '' }}
                                required
                            >
                            <label class="form-check-label" for="urgency_{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    @if ($errors->has('urgency'))
                        <div class="invalid-feedback">{{ $errors->first('urgency') }}</div>
                    @endif
                    <span class="help-block">{{ trans('cruds.aiMessage.fields.urgency_helper') }}</span>
                </div>

                <div class="form-group">
                    <div class="form-check {{ $errors->has('resolved') ? 'is-invalid' : '' }}">
                        <input type="hidden" name="resolved" value="0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="resolved"
                            id="resolved"
                            value="1"
                            {{ old('resolved', $ai_message ? $ai_message->resolved : 0) == 1 ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="resolved">
                            {{ trans('cruds.aiMessage.fields.resolved') }}
                        </label>
                    </div>
                    @if ($errors->has('resolved'))
                        <div class="invalid-feedback">{{ $errors->first('resolved') }}</div>
                    @endif
                    <span class="help-block">{{ trans('cruds.aiMessage.fields.resolved_helper') }}</span>
                </div>

                <div class="form-group">
                    <button class="btn btn-danger" type="submit">
                        {{ trans('global.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('styles')
    @parent
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
@endsection

@section('scripts')
    @parent
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script>
        $(document).ready(function() {
            // CSRF para AJAX
            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });

            // Autocomplete (rota que criaste no controller: search)
            $('#client_search').autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: '{{ route('ai-messages.search') }}', // ajusta se a tua rota tiver prefixo admin
                        method: 'POST',
                        data: { term: request.term },
                        success: function(data) {
                            response($.map(data, function(item) {
                                return {
                                    label: (item.name || 'Sem nome') + (item.nif ? ' (' + item.nif + ')' : ''),
                                    value: item.name || '',
                                    id: item.id,
                                    nif: item.nif || '',
                                    email: item.email || '',
                                    context: item.context || ''
                                };
                            }));
                        }
                    });
                },
                minLength: 3,
                select: function(event, ui) {
                    // Preenche campos
                    $('#client').val(ui.item.id);
                    $('#client_name').val(ui.item.value);
                    $('#email').val(ui.item.email);
                    $('#nif').val(ui.item.nif);

                    // Opcional: pré-preencher o contexto
                    if (ui.item.context) {
                        $('#context').val(ui.item.context);
                    }

                    // Recarrega para carregar histórico no servidor
                    window.location = '{{ route('admin.ai-messages.create') }}?client=' + ui.item.id;
                }
            });
        });
    </script>
@endsection
