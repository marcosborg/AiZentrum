@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.show') }} {{ trans('cruds.technicalAssistanteSession.title') }}
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.technical-assistante-sessions.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
            <table class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteSession.fields.id') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteSession->id }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteSession.fields.client') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteSession->client }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteSession.fields.client_name') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteSession->client_name }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteSession.fields.nif') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteSession->nif }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteSession.fields.email') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteSession->email }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteSession.fields.invoice_number') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteSession->invoice_number }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteSession.fields.product') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteSession->product }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteSession.fields.car') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteSession->car }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.technicalAssistanteSession.fields.comercial') }}
                        </th>
                        <td>
                            {{ $technicalAssistanteSession->comercial }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.technical-assistante-sessions.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
        </div>
    </div>
</div>



@endsection