@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.show') }} {{ trans('cruds.aiMessage.title') }}
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.ai-messages.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
            <table class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>
                            {{ trans('cruds.aiMessage.fields.id') }}
                        </th>
                        <td>
                            {{ $aiMessage->id }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiMessage.fields.client') }}
                        </th>
                        <td>
                            {{ $aiMessage->client_name }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            ZCM client ID
                        </th>
                        <td>
                            {{ $aiMessage->client }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiMessage.fields.parent') }}
                        </th>
                        <td>
                            {{ $aiMessage->parent->client ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiMessage.fields.email') }}
                        </th>
                        <td>
                            {{ $aiMessage->email }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiMessage.fields.nif') }}
                        </th>
                        <td>
                            {{ $aiMessage->nif }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiMessage.fields.user') }}
                        </th>
                        <td>
                            {{ $aiMessage->user->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiMessage.fields.context') }}
                        </th>
                        <td>
                            {{ $aiMessage->context }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiMessage.fields.ai_response') }}
                        </th>
                        <td>
                            {{ $aiMessage->ai_response }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiMessage.fields.conflict_type') }}
                        </th>
                        <td>
                            {{ App\Models\AiMessage::CONFLICT_TYPE_RADIO[$aiMessage->conflict_type] ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiMessage.fields.urgency') }}
                        </th>
                        <td>
                            {{ App\Models\AiMessage::URGENCY_RADIO[$aiMessage->urgency] ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiMessage.fields.resolved') }}
                        </th>
                        <td>
                            <input type="checkbox" disabled="disabled" {{ $aiMessage->resolved ? 'checked' : '' }}>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.ai-messages.create', ['ai_message_id' => $aiMessage->id]) }}">
                    Criar nova resposta
                </a>
            </div>
        </div>
    </div>
</div>



@endsection