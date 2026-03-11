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
            <div class="row">
                <div class="col-md-9">
                    <div class="form-group">
                        <label for="photo" class="required">{{ trans('cruds.moloniSuplierInvoice.fields.photo') }}</label>
                        <div class="needsclick dropzone {{ $errors->has('photo') ? 'is-invalid' : '' }}" id="photo-dropzone">
                        </div>
                        @if($errors->has('photo'))
                            <div class="invalid-feedback">
                                {{ $errors->first('photo') }}
                            </div>
                        @endif
                        <span class="help-block">{{ trans('cruds.moloniSuplierInvoice.fields.photo_helper') }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    @if($moloniSuplierInvoice->photos->count())
                        <img src="{{ $moloniSuplierInvoice->photos->first()->getUrl() }}" class="img-thumbnail" style="cursor:pointer;" data-toggle="modal" data-target="#imageModal">
                    @endif
                </div>
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
        <a href="{{ route('admin.moloni-suplier-invoices.launch', [$moloniSuplierInvoice->id]) }}" class="btn btn-success">
            <i class="fas fa-paper-plane"></i> Lancar no Moloni
        </a>
    </div>
</div>

<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-body p-0">
        @foreach($moloniSuplierInvoice->photos as $photo)
            <img src="{{ $photo->getUrl() }}" class="img-fluid w-100 mb-2" alt="Fatura">
        @endforeach
      </div>
    </div>
  </div>
</div>

@endsection

@section('scripts')

<script>
    Dropzone.options.photoDropzone = {
    url: '{{ route('admin.moloni-suplier-invoices.storeMedia') }}',
    maxFilesize: 5,
    acceptedFiles: '.jpeg,.jpg,.png,.gif',
    maxFiles: 10,
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
      file.uploadedName = response.name
      $('form').append('<input type="hidden" name="photos[]" value="' + response.name + '">')
    },
    removedfile: function (file) {
      file.previewElement.remove()
      var name = file.uploadedName || file.file_name
      if (file.status !== 'error' && name) {
        $('form').find('input[name="photos[]"][value="' + name + '"]').remove()
      }
    },
    init: function () {
@if(isset($moloniSuplierInvoice) && $moloniSuplierInvoice->photos->count())
      var files = {!! json_encode($moloniSuplierInvoice->photos) !!}
      for (var i = 0; i < files.length; i++) {
        var file = files[i]
        this.options.addedfile.call(this, file)
        this.options.thumbnail.call(this, file, file.preview ?? file.preview_url)
        file.previewElement.classList.add('dz-complete')
        file.uploadedName = file.file_name
        $('form').append('<input type="hidden" name="photos[]" value="' + file.file_name + '">')
        this.options.maxFiles = this.options.maxFiles - 1
      }
@endif
    },
    error: function (file, response) {
        if ($.type(response) === 'string') {
            var message = response
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
