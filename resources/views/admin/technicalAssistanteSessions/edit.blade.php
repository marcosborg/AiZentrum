@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.edit') }} {{ trans('cruds.technicalAssistanteSession.title_singular') }}
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route("admin.technical-assistante-sessions.update", [$technicalAssistanteSession->id]) }}" enctype="multipart/form-data">
            @method('PUT')
            @csrf
            <div class="form-group">
                <label for="client">{{ trans('cruds.technicalAssistanteSession.fields.client') }}</label>
                <input class="form-control {{ $errors->has('client') ? 'is-invalid' : '' }}" type="number" name="client" id="client" value="{{ old('client', $technicalAssistanteSession->client) }}" step="1">
                @if($errors->has('client'))
                    <div class="invalid-feedback">
                        {{ $errors->first('client') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.technicalAssistanteSession.fields.client_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="client_name">{{ trans('cruds.technicalAssistanteSession.fields.client_name') }}</label>
                <input class="form-control {{ $errors->has('client_name') ? 'is-invalid' : '' }}" type="text" name="client_name" id="client_name" value="{{ old('client_name', $technicalAssistanteSession->client_name) }}">
                @if($errors->has('client_name'))
                    <div class="invalid-feedback">
                        {{ $errors->first('client_name') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.technicalAssistanteSession.fields.client_name_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="nif">{{ trans('cruds.technicalAssistanteSession.fields.nif') }}</label>
                <input class="form-control {{ $errors->has('nif') ? 'is-invalid' : '' }}" type="text" name="nif" id="nif" value="{{ old('nif', $technicalAssistanteSession->nif) }}">
                @if($errors->has('nif'))
                    <div class="invalid-feedback">
                        {{ $errors->first('nif') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.technicalAssistanteSession.fields.nif_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="email">{{ trans('cruds.technicalAssistanteSession.fields.email') }}</label>
                <input class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}" type="text" name="email" id="email" value="{{ old('email', $technicalAssistanteSession->email) }}">
                @if($errors->has('email'))
                    <div class="invalid-feedback">
                        {{ $errors->first('email') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.technicalAssistanteSession.fields.email_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="invoice_number">{{ trans('cruds.technicalAssistanteSession.fields.invoice_number') }}</label>
                <input class="form-control {{ $errors->has('invoice_number') ? 'is-invalid' : '' }}" type="text" name="invoice_number" id="invoice_number" value="{{ old('invoice_number', $technicalAssistanteSession->invoice_number) }}">
                @if($errors->has('invoice_number'))
                    <div class="invalid-feedback">
                        {{ $errors->first('invoice_number') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.technicalAssistanteSession.fields.invoice_number_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="product">{{ trans('cruds.technicalAssistanteSession.fields.product') }}</label>
                <input class="form-control {{ $errors->has('product') ? 'is-invalid' : '' }}" type="text" name="product" id="product" value="{{ old('product', $technicalAssistanteSession->product) }}">
                @if($errors->has('product'))
                    <div class="invalid-feedback">
                        {{ $errors->first('product') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.technicalAssistanteSession.fields.product_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="car">{{ trans('cruds.technicalAssistanteSession.fields.car') }}</label>
                <input class="form-control {{ $errors->has('car') ? 'is-invalid' : '' }}" type="text" name="car" id="car" value="{{ old('car', $technicalAssistanteSession->car) }}">
                @if($errors->has('car'))
                    <div class="invalid-feedback">
                        {{ $errors->first('car') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.technicalAssistanteSession.fields.car_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="comercial">{{ trans('cruds.technicalAssistanteSession.fields.comercial') }}</label>
                <input class="form-control {{ $errors->has('comercial') ? 'is-invalid' : '' }}" type="text" name="comercial" id="comercial" value="{{ old('comercial', $technicalAssistanteSession->comercial) }}">
                @if($errors->has('comercial'))
                    <div class="invalid-feedback">
                        {{ $errors->first('comercial') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.technicalAssistanteSession.fields.comercial_helper') }}</span>
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