@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.create') }} {{ trans('cruds.aiAssistantIntruction.title_singular') }}
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route("admin.ai-assistant-intructions.store") }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="required" for="user_id">{{ trans('cruds.aiAssistantIntruction.fields.user') }}</label>
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
                <span class="help-block">{{ trans('cruds.aiAssistantIntruction.fields.user_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="ai_assistant_category_id">{{ trans('cruds.aiAssistantIntruction.fields.ai_assistant_category') }}</label>
                <select class="form-control select2 {{ $errors->has('ai_assistant_category') ? 'is-invalid' : '' }}" name="ai_assistant_category_id" id="ai_assistant_category_id" required>
                    @foreach($ai_assistant_categories as $id => $entry)
                        <option value="{{ $id }}" {{ old('ai_assistant_category_id') == $id ? 'selected' : '' }}>{{ $entry }}</option>
                    @endforeach
                </select>
                @if($errors->has('ai_assistant_category'))
                    <div class="invalid-feedback">
                        {{ $errors->first('ai_assistant_category') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiAssistantIntruction.fields.ai_assistant_category_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="name">{{ trans('cruds.aiAssistantIntruction.fields.name') }}</label>
                <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" type="text" name="name" id="name" value="{{ old('name', '') }}" required>
                @if($errors->has('name'))
                    <div class="invalid-feedback">
                        {{ $errors->first('name') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiAssistantIntruction.fields.name_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="instructions">{{ trans('cruds.aiAssistantIntruction.fields.instructions') }}</label>
                <textarea class="form-control ckeditor {{ $errors->has('instructions') ? 'is-invalid' : '' }}" name="instructions" id="instructions">{!! old('instructions') !!}</textarea>
                @if($errors->has('instructions'))
                    <div class="invalid-feedback">
                        {{ $errors->first('instructions') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.aiAssistantIntruction.fields.instructions_helper') }}</span>
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

@section('scripts')
<script>
    $(document).ready(function () {
  function SimpleUploadAdapter(editor) {
    editor.plugins.get('FileRepository').createUploadAdapter = function(loader) {
      return {
        upload: function() {
          return loader.file
            .then(function (file) {
              return new Promise(function(resolve, reject) {
                // Init request
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '{{ route('admin.ai-assistant-intructions.storeCKEditorImages') }}', true);
                xhr.setRequestHeader('x-csrf-token', window._token);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.responseType = 'json';

                // Init listeners
                var genericErrorText = `Couldn't upload file: ${ file.name }.`;
                xhr.addEventListener('error', function() { reject(genericErrorText) });
                xhr.addEventListener('abort', function() { reject() });
                xhr.addEventListener('load', function() {
                  var response = xhr.response;

                  if (!response || xhr.status !== 201) {
                    return reject(response && response.message ? `${genericErrorText}\n${xhr.status} ${response.message}` : `${genericErrorText}\n ${xhr.status} ${xhr.statusText}`);
                  }

                  $('form').append('<input type="hidden" name="ck-media[]" value="' + response.id + '">');

                  resolve({ default: response.url });
                });

                if (xhr.upload) {
                  xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                      loader.uploadTotal = e.total;
                      loader.uploaded = e.loaded;
                    }
                  });
                }

                // Send request
                var data = new FormData();
                data.append('upload', file);
                data.append('crud_id', '{{ $aiAssistantIntruction->id ?? 0 }}');
                xhr.send(data);
              });
            })
        }
      };
    }
  }

  var allEditors = document.querySelectorAll('.ckeditor');
  for (var i = 0; i < allEditors.length; ++i) {
    ClassicEditor.create(
      allEditors[i], {
        extraPlugins: [SimpleUploadAdapter]
      }
    );
  }
});
</script>

@endsection