@extends('layouts.admin')

@section('content')
    @php
        $conflict = \App\Models\AiMessage::CONFLICT_TYPE_RADIO[$aiMessage->conflict_type] ?? ($aiMessage->conflict_type ?? '—');
        $urgency  = \App\Models\AiMessage::URGENCY_RADIO[$aiMessage->urgency] ?? ($aiMessage->urgency ?? '—');
    @endphp

    <div class="card">
        <div class="card-header clearfix">
            <div class="pull-left">
                {{ trans('global.show') }} {{ trans('cruds.aiMessage.title') }}
            </div>
            <div class="pull-right">
                <a class="btn btn-default btn-sm" href="{{ route('admin.ai-messages.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
                <a class="btn btn-info btn-sm" href="{{ route('admin.ai-messages.edit', $aiMessage->id) }}">
                    Editar esta entrada
                </a>
                <a class="btn btn-primary btn-sm"
                   href="{{ route('admin.ai-messages.create', ['client' => $aiMessage->client, 'ai_message_id' => $aiMessage->id]) }}">
                    Criar nova entrada
                </a>
            </div>
        </div>

        <div class="card-body">
            <div class="row">

                {{-- Coluna esquerda: Resumo + contador --}}
                <div class="col-md-4">
                    <div class="card" style="margin-bottom:15px;">
                        <div class="card-header"><strong>Resumo do cliente</strong></div>
                        <div class="card-body" style="padding:0;">
                            <table class="table table-condensed table-striped" style="margin:0;">
                                <tr>
                                    <th class="w-40">Cliente</th>
                                    <td>{{ $aiMessage->client_name ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th>ZCM client ID</th>
                                    <td><span class="label label-primary">{{ $aiMessage->client }}</span></td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td>{{ $aiMessage->email ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th>NIF</th>
                                    <td>{{ $aiMessage->nif ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Atendido por</th>
                                    <td>{{ $aiMessage->user->name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Tipo de conflito</th>
                                    <td>
                                        @if($conflict && $conflict !== '—')
                                            <span class="label label-default">{{ $conflict }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Urgência</th>
                                    <td>
                                        @php
                                            $urgMap = [
                                                'Crítica' => 'label label-danger',
                                                'Alta'    => 'label label-warning',
                                                'Média'   => 'label label-info',
                                                'Baixa'   => 'label label-success',
                                            ];
                                            $urgClass = $urgMap[$urgency] ?? 'label label-default';
                                        @endphp
                                        <span class="{{ $urgClass }}">{{ $urgency }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Resolvido</th>
                                    <td>
                                        @if($aiMessage->resolved)
                                            <span class="label label-success">Sim</span>
                                        @else
                                            <span class="label label-default">Não</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Criado em</th>
                                    <td>{{ $aiMessage->created_at }}</td>
                                </tr>
                                <tr>
                                    <th>Atualizado em</th>
                                    <td>{{ $aiMessage->updated_at }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body" style="padding:15px;">
                            <div class="clearfix">
                                <div class="pull-left">
                                    <div style="font-weight:bold;">Mensagens na thread</div>
                                    <div class="text-muted small">Cliente #{{ $aiMessage->client }}</div>
                                </div>
                                <div class="pull-right">
                                    <span class="label label-primary">{{ $history->count() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Coluna direita: Histórico completo (timeline) --}}
                <div class="col-md-8">
                    <div class="card" style="margin-bottom:15px;">
                        <div class="card-header"><strong>Histórico completo</strong></div>
                        <div class="card-body" style="max-height:650px; overflow:auto;">

                            @if($history->isEmpty())
                                <em>Sem histórico para este cliente.</em>
                            @else
                                <ul class="list-unstyled timeline">
                                    @foreach($history as $m)
                                        {{-- Turno do Cliente --}}
                                        @if(!empty($m->context))
                                            <li class="timeline-item">
                                                <div class="timeline-meta">
                                                    <span class="label label-default mr-8">Cliente</span>
                                                    <small class="text-muted">{{ $m->created_at }}</small>
                                                </div>
                                                <div class="timeline-bubble">
                                                    {{ $m->context }}
                                                </div>
                                            </li>
                                        @endif

                                        {{-- Turno do Assistente --}}
                                        @if(!empty($m->ai_response))
                                            <li class="timeline-item">
                                                <div class="timeline-meta">
                                                    <span class="label label-primary mr-8">Assistente</span>
                                                    <small class="text-muted">{{ $m->created_at }}</small>
                                                </div>
                                                <div class="timeline-bubble assistant">
                                                    {{ $m->ai_response }}
                                                </div>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="card-footer" style="padding:10px 15px;">
                            <div class="text-right">
                                <a class="btn btn-primary"
                                   href="{{ route('admin.ai-messages.create', ['client' => $aiMessage->client, 'ai_message_id' => $aiMessage->id]) }}">
                                    Criar nova entrada
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Entrada destacada (a atual) --}}
                    <div class="card">
                        <div class="card-header">
                            <strong>Entrada selecionada (#{{ $aiMessage->id }})</strong>
                        </div>
                        <div class="card-body">
                            <div class="mb-8">
                                <div class="text-muted small" style="margin-bottom:6px;">Contexto</div>
                                <div class="well well-sm" style="margin-bottom:0;">{{ $aiMessage->context ?: '—' }}</div>
                            </div>
                            <div>
                                <div class="text-muted small" style="margin-bottom:6px;">Resposta da AI</div>
                                <div class="well well-sm" style="background:#f7f7f7; margin-bottom:0;">
                                    {{ $aiMessage->ai_response ?: '—' }}
                                </div>
                            </div>
                        </div>
                        <div class="card-footer" style="padding:10px 15px;">
                            <div class="clearfix">
                                <div class="pull-left">
                                    <a class="btn btn-default" href="{{ route('admin.ai-messages.index') }}">
                                        {{ trans('global.back_to_list') }}
                                    </a>
                                </div>
                                <div class="pull-right">
                                    <a class="btn btn-info" href="{{ route('admin.ai-messages.edit', $aiMessage->id) }}">
                                        Editar esta entrada
                                    </a>
                                    <a class="btn btn-primary"
                                       href="{{ route('admin.ai-messages.create', ['client' => $aiMessage->client, 'ai_message_id' => $aiMessage->id]) }}">
                                        Criar nova entrada
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div> {{-- /col-md-8 --}}
            </div> {{-- /row --}}
        </div> {{-- /card-body --}}
    </div> {{-- /card --}}
@endsection

@section('styles')
@parent
<style>
    /* Pequenas utilidades para BS3 */
    .mr-8 { margin-right: 8px; }
    .mb-8 { margin-bottom: 8px; }

    /* Timeline simples */
    .timeline { margin:0; padding:0; }
    .timeline-item { margin-bottom: 12px; }
    .timeline-meta { margin-bottom: 6px; }
    .timeline-bubble {
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 10px 12px;
        background: #fff;
    }
    .timeline-bubble.assistant {
        background: #f5f7fb; /* leve azul */
        border-color: #dfe7ff;
    }

    /* Cartões do tema (compatibilidade) */
    .card { background:#fff; border:1px solid #ddd; border-radius:4px; }
    .card-header { padding:10px 15px; border-bottom:1px solid #ddd; background:#f5f5f5; }
    .card-body { padding:15px; }
    .card-footer { border-top:1px solid #ddd; background:#fafafa; }
</style>
@endsection
