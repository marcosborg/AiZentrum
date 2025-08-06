@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.edit') }} {{ trans('cruds.technicalAssistanteMessage.title_singular') }}
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route("admin.technical-assistante-messages.update", [$technicalAssistanteMessage->id]) }}" enctype="multipart/form-data">
            @method('PUT')
            @csrf
            <div class="form-group">
                <label for="technical_assistante_session_id">{{ trans('cruds.technicalAssistanteMessage.fields.technical_assistante_session') }}</label>
                <select class="form-control select2 {{ $errors->has('technical_assistante_session') ? 'is-invalid' : '' }}" name="technical_assistante_session_id" id="technical_assistante_session_id">
                    @foreach($technical_assistante_sessions as $id => $entry)
                        <option value="{{ $id }}" {{ (old('technical_assistante_session_id') ? old('technical_assistante_session_id') : $technicalAssistanteMessage->technical_assistante_session->id ?? '') == $id ? 'selected' : '' }}>{{ $entry }}</option>
                    @endforeach
                </select>
                @if($errors->has('technical_assistante_session'))
                    <div class="invalid-feedback">
                        {{ $errors->first('technical_assistante_session') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.technicalAssistanteMessage.fields.technical_assistante_session_helper') }}</span>
            </div>
            <div class="form-group">
                <label>{{ trans('cruds.technicalAssistanteMessage.fields.role') }}</label>
                @foreach(App\Models\TechnicalAssistanteMessage::ROLE_RADIO as $key => $label)
                    <div class="form-check {{ $errors->has('role') ? 'is-invalid' : '' }}">
                        <input class="form-check-input" type="radio" id="role_{{ $key }}" name="role" value="{{ $key }}" {{ old('role', $technicalAssistanteMessage->role) === (string) $key ? 'checked' : '' }}>
                        <label class="form-check-label" for="role_{{ $key }}">{{ $label }}</label>
                    </div>
                @endforeach
                @if($errors->has('role'))
                    <div class="invalid-feedback">
                        {{ $errors->first('role') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.technicalAssistanteMessage.fields.role_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="content">{{ trans('cruds.technicalAssistanteMessage.fields.content') }}</label>
                <textarea class="form-control {{ $errors->has('content') ? 'is-invalid' : '' }}" name="content" id="content">{{ old('content', $technicalAssistanteMessage->content) }}</textarea>
                @if($errors->has('content'))
                    <div class="invalid-feedback">
                        {{ $errors->first('content') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.technicalAssistanteMessage.fields.content_helper') }}</span>
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