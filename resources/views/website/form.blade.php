<!doctype html>
<html lang="{{ $locale }}">

@php
    $languages = trans('public_forms.languages');
    $formName = data_get(trans('public_forms.forms'), $form->id, $form->name);
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zentrum Group | {{ $formName }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <style>
        .upload-dropzone {
            border: 2px dashed #0d6efd;
            border-radius: 8px;
            cursor: pointer;
            min-height: 124px;
            transition: background-color .15s ease, border-color .15s ease;
        }

        .upload-dropzone.is-dragover {
            background-color: rgba(13, 110, 253, .08);
            border-color: #084298;
        }

        .upload-file-list {
            font-size: .875rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="mt-4">
            <div class="d-flex justify-content-end mb-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ trans('public_forms.ui.language') }}">
                    @foreach ($languages as $code => $name)
                        <a href="{{ url('/form/' . $form->id) }}?lang={{ $code }}"
                            class="btn {{ $locale === $code ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ strtoupper($code) }}
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="logo text-center">
                <img src="{{ asset('images/logo-black.png') }}" class="img-thumbnail" style="max-width: 200px;">
            </div>
            <div class="card mt-4 mb-5">
                <div class="card-header text-center">
                    <h3>{{ $formName }}</h3>
                </div>
                <div class="card-body mb-4">
                    <div class="row">
                        @foreach ($form->form_fields as $form_field)
                        @php
                            $fieldLabel = data_get(trans('public_forms.fields'), $form_field->id, $form_field->label);
                        @endphp
                        <div
                            class="col-md-{{ $form_field->type == 'textarea' || $form_field->type == 'checkbox' ? '12' : '6' }}">
                            @switch($form_field->type)
                            @case('text')
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $fieldLabel }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <input type="text" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field" data-label="{{ $fieldLabel }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? 'true' : 'false' }}">
                            </div>
                            @break
                            @case('date')
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $fieldLabel }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <input type="date" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field" data-label="{{ $fieldLabel }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? 'true' : 'false' }}">
                            </div>
                            @break
                            @case('email')
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $fieldLabel }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <input type="email" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field" data-label="{{ $fieldLabel }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? 'true' : 'false' }}">
                            </div>
                            @break
                            @case('textarea')
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $fieldLabel }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <textarea name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field" data-label="{{ $fieldLabel }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? 'true' : 'false' }}"></textarea>
                            </div>
                            @break
                            @case('radio')
                            <label class="mt-4">{{ $fieldLabel }}</label>
                            <div class="form-check">
                                <input class="form-check-input form-field" type="radio" name="{{ $form_field->name }}"
                                    id="check-{{ $form_field->id }}-1" data-label="{{ $fieldLabel }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? 'true' : 'false' }}" value="yes">
                                <label class="form-check-label" for="check-{{ $form_field->id }}-1">
                                    {{ trans('public_forms.ui.yes') }}
                                </label>
                            </div>
                            <div class="form-check mb-4">
                                <input class="form-check-input form-field" type="radio" name="{{ $form_field->name }}"
                                    id="check-{{ $form_field->id }}-2" data-label="{{ $fieldLabel }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? 'true' : 'false' }}" value="no">
                                <label class="form-check-label" for="check-{{ $form_field->id }}-2">
                                    {{ trans('public_forms.ui.no') }}
                                </label>
                            </div>
                            @break
                            @case('file')
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $fieldLabel }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <div class="upload-dropzone d-flex align-items-center justify-content-center p-3 text-center"
                                    data-target="{{ $form_field->name }}">
                                    <div>
                                        <strong>{{ trans('public_forms.ui.upload_drop') }}</strong>
                                        <div class="text-muted small">{{ trans('public_forms.ui.upload_browse') }}</div>
                                    </div>
                                </div>
                                <input type="file" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field d-none" data-label="{{ $fieldLabel }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? 'true' : 'false' }}" multiple>
                                <div class="upload-file-list text-muted mt-2" data-list="{{ $form_field->name }}">
                                    {{ trans('public_forms.ui.no_files_selected') }}
                                </div>
                            </div>
                            @break
                            @case('checkbox')
                            <div class="form-check mt-4">
                                <input class="form-check-input form-field" type="checkbox"
                                    name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    data-label="{{ $fieldLabel }}" data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? 'true' : 'false' }}">
                                <label class="form-check-label" for="{{ $form_field->name }}">
                                    {{ $fieldLabel }}{{ $form_field->required ? ' *' : '' }}
                                </label>
                            </div>
                            @break
                            @default
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $fieldLabel }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <input type="text" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field" data-label="{{ $fieldLabel }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? 'true' : 'false' }}">
                            </div>
                            @endswitch
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-success btn-lg" onclick="submitForm()">{{ trans('public_forms.ui.submit') }}</button>
                </div>
            </div>
        </div>


    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js">
    </script>
  <script>
    var publicFormTranslations = @json(trans('public_forms.js'));
    var uploadedFilesByInput = {};

    var escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    };

    window.refreshFileList = (input) => {
        const files = uploadedFilesByInput[input.id] || Array.from(input.files || []);
        const list = document.querySelector('[data-list="' + input.id + '"]');

        if (!list) {
            return;
        }

        if (files.length === 0) {
            list.textContent = publicFormTranslations.no_files_selected;
            return;
        }

        const fileNames = files.map((file) => escapeHtml(file.name));
        list.innerHTML = '<strong>' + publicFormTranslations.selected_files + '</strong><br>' + fileNames.join('<br>');
    };

    window.setSelectedFiles = (input, files, append = false) => {
        const inputId = input.id;
        const currentFiles = append ? uploadedFilesByInput[inputId] || Array.from(input.files || []) : [];
        uploadedFilesByInput[inputId] = currentFiles.concat(Array.from(files || []));
        window.refreshFileList(input);
    };

    document.querySelectorAll('.upload-dropzone').forEach((dropzone) => {
        dropzone.addEventListener('click', () => {
            document.getElementById(dropzone.dataset.target).click();
        });

        ['dragover', 'dragenter'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.remove('is-dragover');
            });
        });

        dropzone.addEventListener('drop', (event) => {
            const input = document.getElementById(dropzone.dataset.target);
            window.setSelectedFiles(input, event.dataTransfer.files, true);
        });
    });

    document.querySelectorAll('.form-field[data-type="file"]').forEach((input) => {
        input.addEventListener('change', () => {
            window.setSelectedFiles(input, input.files, false);
            window.refreshFileList(input);
        });
    });

    submitForm = () => {
        const form_id = {{ $form->id }};
        var fields = $('.form-field');
        let formData = new FormData();
        let validation = '';
        let termsName = null;
        let validatedRadios = new Set();

        // 1) Marcar o último checkbox como "Termos" obrigatório
        const $allCheckboxes = $('.form-field[data-type="checkbox"]');
        if ($allCheckboxes.length > 0) {
            const $terms = $allCheckboxes.last();
            termsName = $terms.attr('name');

            // marca como obrigatório
            $terms.data('required', true);

            // adicionar * ao label, se ainda não tiver
            const termsId = $terms.attr('id');
            const $termsLabel = $('label[for="'+termsId+'"]');
            if ($termsLabel.length && !$termsLabel.text().trim().endsWith('*')) {
                $termsLabel.text($termsLabel.text().trim() + ' *');
            }

            // validação explícita dos termos
            if (!$terms.is(':checked')) {
                validation += '<p>' + publicFormTranslations.terms_required + '</p>';
            }
        }

        // 2) Recolha + validação genérica
        fields.each((i, v) => {
            let label = $(v).data('label');
            let value = $(v).val();
            let name = $(v).attr('name');
            let type = $(v).data('type');
            let required = $(v).data('required');

            if (required === true && !(type === 'checkbox' && name === termsName)) {
                // checkboxes e radios não usam .val() para "vazio"
                if (type === 'checkbox' && !$(v).is(':checked')) {
                    validation += '<p>' + publicFormTranslations.required_field.replace(':field', label) + '</p>';
                } else if (type === 'file') {
                    let selectedFiles = uploadedFilesByInput[$(v).attr('id')] || Array.from($(v)[0].files || []);
                    if (selectedFiles.length === 0) {
                        validation += '<p>' + publicFormTranslations.required_field.replace(':field', label) + '</p>';
                    }
                } else if (type === 'radio') {
                    // garante que pelo menos um radio com o mesmo name foi escolhido
                    if ($('input[name="'+name+'"]:checked').length === 0 && !validatedRadios.has(name)) {
                        validation += '<p>' + publicFormTranslations.required_field.replace(':field', label) + '</p>';
                        validatedRadios.add(name);
                    }
                } else if (value === '' || value === null) {
                    validation += '<p>' + publicFormTranslations.required_field.replace(':field', label) + '</p>';
                }
            }

            // anexos/ficheiros
            if (type === 'file') {
                let fileInput = $(v)[0];
                let selectedFiles = uploadedFilesByInput[$(v).attr('id')] || Array.from(fileInput.files || []);
                if (selectedFiles.length > 0) {
                    selectedFiles.forEach((file) => {
                        formData.append(name + '[]', file);
                    });
                }
            } else if (type === 'checkbox') {
                formData.append(name, $(v).is(':checked'));
            } else if (type === 'radio') {
                if ($(v).is(':checked')) {
                    formData.append(name, value);
                }
            } else {
                formData.append(name, value);
            }
        });

        formData.append('form_id', form_id);

        if (validation !== '') {
            Swal.fire({
                title: publicFormTranslations.missing_data_title,
                html: validation,
                icon: "error"
            });
            return; // impede envio
        }

        $.LoadingOverlay('show');
        $.ajax({
            url: '/form/form-send',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: (resp) => {
                $.LoadingOverlay('hide');
                Swal.fire({
                    title: publicFormTranslations.success_title,
                    text: publicFormTranslations.success_text,
                    icon: "success"
                }).then(() => {
                    location.reload();
                });
            },
            error: (error) => {
                $.LoadingOverlay('hide');
                console.log(error);
                Swal.fire({
                    title: publicFormTranslations.error_title,
                    text: publicFormTranslations.error_text,
                    icon: "error"
                });
            }
        });
    }
</script>
  
</body>

</html>
