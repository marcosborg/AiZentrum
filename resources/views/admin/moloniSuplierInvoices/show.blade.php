@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.show') }} {{ trans('cruds.moloniSuplierInvoice.title') }}
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.moloni-suplier-invoices.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
            <table class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>
                            {{ trans('cruds.moloniSuplierInvoice.fields.id') }}
                        </th>
                        <td>
                            {{ $moloniSuplierInvoice->id }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.moloniSuplierInvoice.fields.user') }}
                        </th>
                        <td>
                            {{ $moloniSuplierInvoice->user->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.moloniSuplierInvoice.fields.photo') }}
                        </th>
                        <td>
                            @foreach($moloniSuplierInvoice->photos as $photo)
                                <a href="{{ $photo->getUrl() }}" target="_blank" style="display: inline-block; margin-right: 6px;">
                                    <img src="{{ $photo->getUrl('thumb') }}">
                                </a>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.moloniSuplierInvoice.fields.data') }}
                        </th>
                        <td>
                            {{ $moloniSuplierInvoice->data }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.moloniSuplierInvoice.fields.handled') }}
                        </th>
                        <td>
                            <input type="checkbox" disabled="disabled" {{ $moloniSuplierInvoice->handled ? 'checked' : '' }}>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.moloni-suplier-invoices.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
        </div>
    </div>
</div>



@endsection
