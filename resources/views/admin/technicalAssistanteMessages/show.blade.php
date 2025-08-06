@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.show') }} {{ trans('cruds.technicalAssistanteMessage.title') }}
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.technical-assistante-messages.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
            <table class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteMessage.fields.id') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteMessage->id }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteMessage.fields.technical_assistante_session') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteMessage->technical_assistante_session->client ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteMessage.fields.role') }}
                        </th>
                        <td>
                            {{ App\Models\TechnicalAssistanteMessage::ROLE_RADIO[$technicalAssistanteMessage->role] ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteMessage.fields.content') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteMessage->content }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.technical-assistante-messages.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
        </div>
    </div>
</div>



@endsection