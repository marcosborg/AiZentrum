@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.create') }} {{ trans('cruds.aiMessage.title_singular') }}
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route("admin.ai-messages.store") }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="required" for="client">{{ trans('cruds.aiMessage.fields.client') }}</label>
                <input class="form-control {{ $errors->has('client') ? 'is-invalid' : '' }}" type="number" name="client" id="client" value="{{ old('client', '') }}" step="1" required>
                @if($errors->has('client'))
                    <div class="invalid-feedback">
                        {{ $errors->first('client') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiMessage.fields.client_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="parent_id">{{ trans('cruds.aiMessage.fields.parent') }}</label>
                <select class="form-control select2 {{ $errors->has('parent') ? 'is-invalid' : '' }}" name="parent_id" id="parent_id">
                    @foreach($parents as $id => $entry)
                        <option value="{{ $id }}" {{ old('parent_id') == $id ? 'selected' : '' }}>{{ $entry }}</option>
                    @endforeach
                </select>
                @if($errors->has('parent'))
                    <div class="invalid-feedback">
                        {{ $errors->first('parent') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiMessage.fields.parent_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="email">{{ trans('cruds.aiMessage.fields.email') }}</label>
                <input class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}" type="text" name="email" id="email" value="{{ old('email', '') }}">
                @if($errors->has('email'))
                    <div class="invalid-feedback">
                        {{ $errors->first('email') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiMessage.fields.email_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="nif">{{ trans('cruds.aiMessage.fields.nif') }}</label>
                <input class="form-control {{ $errors->has('nif') ? 'is-invalid' : '' }}" type="text" name="nif" id="nif" value="{{ old('nif', '') }}">
                @if($errors->has('nif'))
                    <div class="invalid-feedback">
                        {{ $errors->first('nif') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiMessage.fields.nif_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="user_id">{{ trans('cruds.aiMessage.fields.user') }}</label>
                <select class="form-control select2 {{ $errors->has('user') ? 'is-invalid' : '' }}" name="user_id" id="user_id" required>
                    @foreach($users as $id => $entry)
                        <option value="{{ $id }}" {{ old('user_id') == $id ? 'selected' : '' }}>{{ $entry }}</option>
                    @endforeach
                </select>
                @if($errors->has('user'))
                    <div class="invalid-feedback">
                        {{ $errors->first('user') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiMessage.fields.user_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="context">{{ trans('cruds.aiMessage.fields.context') }}</label>
                <textarea class="form-control {{ $errors->has('context') ? 'is-invalid' : '' }}" name="context" id="context">{{ old('context') }}</textarea>
                @if($errors->has('context'))
                    <div class="invalid-feedback">
                        {{ $errors->first('context') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiMessage.fields.context_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="ai_response">{{ trans('cruds.aiMessage.fields.ai_response') }}</label>
                <textarea class="form-control {{ $errors->has('ai_response') ? 'is-invalid' : '' }}" name="ai_response" id="ai_response">{{ old('ai_response') }}</textarea>
                @if($errors->has('ai_response'))
                    <div class="invalid-feedback">
                        {{ $errors->first('ai_response') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiMessage.fields.ai_response_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required">{{ trans('cruds.aiMessage.fields.conflict_type') }}</label>
                @foreach(App\Models\AiMessage::CONFLICT_TYPE_RADIO as $key => $label)
                    <div class="form-check {{ $errors->has('conflict_type') ? 'is-invalid' : '' }}">
                        <input class="form-check-input" type="radio" id="conflict_type_{{ $key }}" name="conflict_type" value="{{ $key }}" {{ old('conflict_type', '') === (string) $key ? 'checked' : '' }} required>
                        <label class="form-check-label" for="conflict_type_{{ $key }}">{{ $label }}</label>
                    </div>
                @endforeach
                @if($errors->has('conflict_type'))
                    <div class="invalid-feedback">
                        {{ $errors->first('conflict_type') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiMessage.fields.conflict_type_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required">{{ trans('cruds.aiMessage.fields.urgency') }}</label>
                @foreach(App\Models\AiMessage::URGENCY_RADIO as $key => $label)
                    <div class="form-check {{ $errors->has('urgency') ? 'is-invalid' : '' }}">
                        <input class="form-check-input" type="radio" id="urgency_{{ $key }}" name="urgency" value="{{ $key }}" {{ old('urgency', '') === (string) $key ? 'checked' : '' }} required>
                        <label class="form-check-label" for="urgency_{{ $key }}">{{ $label }}</label>
                    </div>
                @endforeach
                @if($errors->has('urgency'))
                    <div class="invalid-feedback">
                        {{ $errors->first('urgency') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiMessage.fields.urgency_helper') }}</span>
            </div>
            <div class="form-group">
                <div class="form-check {{ $errors->has('resolved') ? 'is-invalid' : '' }}">
                    <input type="hidden" name="resolved" value="0">
                    <input class="form-check-input" type="checkbox" name="resolved" id="resolved" value="1" {{ old('resolved', 0) == 1 ? 'checked' : '' }}>
                    <label class="form-check-label" for="resolved">{{ trans('cruds.aiMessage.fields.resolved') }}</label>
                </div>
                @if($errors->has('resolved'))
                    <div class="invalid-feedback">
                        {{ $errors->first('resolved') }}
                    </div>
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