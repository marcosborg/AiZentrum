@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.edit') }} {{ trans('cruds.moloniSuplierInvoice.title_singular') }}
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route("admin.moloni-suplier-invoices.update", [$moloniSuplierInvoice->id]) }}" enctype="multipart/form-data">
            @method('PUT')
            @csrf
            <div class="form-group">
                <label class="required" for="user_id">{{ trans('cruds.moloniSuplierInvoice.fields.user') }}</label>
                <select class="form-control select2 {{ $errors->has('user') ? 'is-invalid' : '' }}" name="user_id" id="user_id" required>
                    @foreach($users as $id => $entry)
                        <option value="{{ $id }}" {{ (old('user_id') ? old('user_id') : $moloniSuplierInvoice->user->id ?? '') == $id ? 'selected' : '' }}>{{ $entry }}</option>
                    @endforeach
                </select>
                @if($errors->has('user'))
                    <div class="invalid-feedback">
                        {{ $errors->first('user') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.moloniSuplierInvoice.fields.user_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required">{{ trans('cruds.moloniSuplierInvoice.fields.category') }}</label>
                @foreach(App\Models\MoloniSuplierInvoice::CATEGORY_RADIO as $key => $label)
                    <div class="form-check {{ $errors->has('category') ? 'is-invalid' : '' }}">
                        <input class="form-check-input" type="radio" id="category_{{ $key }}" name="category" value="{{ $key }}" {{ old('category', $moloniSuplierInvoice->category) === (string) $key ? 'checked' : '' }} required>
                        <label class="form-check-label" for="category_{{ $key }}">{{ $label }}</label>
                    </div>
                @endforeach
                @if($errors->has('category'))
                    <div class="invalid-feedback">
                        {{ $errors->first('category') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.moloniSuplierInvoice.fields.category_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="photo">{{ trans('cruds.moloniSuplierInvoice.fields.photo') }}</label>
                <div class="needsclick dropzone {{ $errors->has('photo') ? 'is-invalid' : '' }}" id="photo-dropzone">
                </div>
                @if($errors->has('photo'))
                    <div class="invalid-feedback">
                        {{ $errors->first('photo') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.moloniSuplierInvoice.fields.photo_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="data">{{ trans('cruds.moloniSuplierInvoice.fields.data') }}</label>
                <textarea class="form-control {{ $errors->has('data') ? 'is-invalid' : '' }}" name="data" id="data">{{ old('data', $moloniSuplierInvoice->data) }}</textarea>
                @if($errors->has('data'))
                    <div class="invalid-feedback">
                        {{ $errors->first('data') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.moloniSuplierInvoice.fields.data_helper') }}</span>
            </div>
            <div class="form-group">
                <div class="form-check {{ $errors->has('handled') ? 'is-invalid' : '' }}">
                    <input type="hidden" name="handled" value="0">
                    <input class="form-check-input" type="checkbox" name="handled" id="handled" value="1" {{ $moloniSuplierInvoice->handled || old('handled', 0) === 1 ? 'checked' : '' }}>
                    <label class="form-check-label" for="handled">{{ trans('cruds.moloniSuplierInvoice.fields.handled') }}</label>
                </div>
                @if($errors->has('handled'))
                    <div class="invalid-feedback">
                        {{ $errors->first('handled') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.moloniSuplierInvoice.fields.handled_helper') }}</span>
            </div>
            <div class="form-group">
                <button class="btn btn-danger" type="submit">
                    {{ trans('global.save') }}
                </button>
            </div>
        </form>
        <form action="{{ route('admin.moloni-suplier-invoices.launch', $moloniSuplierInvoice) }}" method="POST" onsubmit="return confirm('Tens a certeza que queres lançar esta fatura no Moloni?');">
                @csrf
                <button class="btn btn-success" type="submit">
                    <i class="fas fa-paper-plane"></i> Lançar no Moloni
                </button>
            </form>
    </div>
</div>



@endsection

@section('scripts')
<script>
    Dropzone.options.photoDropzone = {
    url: '{{ route('admin.moloni-suplier-invoices.storeMedia') }}',
    maxFilesize: 5, // MB
    acceptedFiles: '.jpeg,.jpg,.png,.gif',
    maxFiles: 1,
    addRemoveLinks: true,
    headers: {
      'X-CSRF-TOKEN': "{{ csrf_token() }}"
    },
    params: {
      size: 5,
      width: 4096,
      height: 4096
    },
    success: function (file, response) {
      $('form').find('input[name="photo"]').remove()
      $('form').append('<input type="hidden" name="photo" value="' + response.name + '">')
    },
    removedfile: function (file) {
      file.previewElement.remove()
      if (file.status !== 'error') {
        $('form').find('input[name="photo"]').remove()
        this.options.maxFiles = this.options.maxFiles + 1
      }
    },
    init: function () {
@if(isset($moloniSuplierInvoice) && $moloniSuplierInvoice->photo)
      var file = {!! json_encode($moloniSuplierInvoice->photo) !!}
          this.options.addedfile.call(this, file)
      this.options.thumbnail.call(this, file, file.preview ?? file.preview_url)
      file.previewElement.classList.add('dz-complete')
      $('form').append('<input type="hidden" name="photo" value="' + file.file_name + '">')
      this.options.maxFiles = this.options.maxFiles - 1
@endif
    },
    error: function (file, response) {
        if ($.type(response) === 'string') {
            var message = response //dropzone sends it's own error messages in string
        } else {
            var message = response.errors.file
        }
        file.previewElement.classList.add('dz-error')
        _ref = file.previewElement.querySelectorAll('[data-dz-errormessage]')
        _results = []
        for (_i = 0, _len = _ref.length; _i < _len; _i++) {
            node = _ref[_i]
            _results.push(node.textContent = message)
        }

        return _results
    }
}

</script>
@endsection