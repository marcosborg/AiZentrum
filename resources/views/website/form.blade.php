<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zentrum Group | {{ $form->name }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
</head>

<body>
    <div class="container">
        <div class="mt-4">
            @if ($form->logo)
            <div class="logo text-center">
                <img src="https://ai.airbagszentrum.com/public/images/logo-black.png" class="img-thumbnail" style="max-width: 200px;">
            </div>
            @endif
            <div class="card mt-4 mb-5">
                <div class="card-header text-center">
                    <h3>{{ $form->name }}</h3>
                </div>
                <div class="card-body mb-4">
                    <div class="row">
                        @foreach ($form->form_fields as $form_field)
                        <div
                            class="col-md-{{ $form_field->type == 'textarea' || $form_field->type == 'checkbox' ? '12' : '6' }}">
                            @switch($form_field->type)
                            @case('text')
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $form_field->label }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <input type="text" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field" data-label="{{ $form_field->label }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? true : false }}">
                            </div>
                            @break
                            @case('date')
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $form_field->label }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <input type="date" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field" data-label="{{ $form_field->label }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? true : false }}">
                            </div>
                            @break
                            @case('email')
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $form_field->label }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <input type="email" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field" data-label="{{ $form_field->label }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? true : false }}">
                            </div>
                            @break
                            @case('textarea')
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $form_field->label }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <textarea name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field" data-label="{{ $form_field->label }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? true : false }}"></textarea>
                            </div>
                            @break
                            @case('radio')
                            <label class="mt-4">{{ $form_field->label }}</label>
                            <div class="form-check">
                                <input class="form-check-input form-field" type="radio" name="{{ $form_field->name }}"
                                    id="check-{{ $form_field->id }}-1" data-label="{{ $form_field->label }}"
                                    data-type="{{ $form_field->type }}" value="yes">
                                <label class="form-check-label" for="check-{{ $form_field->id }}-1">
                                    Sim
                                </label>
                            </div>
                            <div class="form-check mb-4">
                                <input class="form-check-input form-field" type="radio" name="{{ $form_field->name }}"
                                    id="check-{{ $form_field->id }}-2" data-label="{{ $form_field->label }}"
                                    data-type="{{ $form_field->type }}" value="no">
                                <label class="form-check-label" for="check-{{ $form_field->id }}-2">
                                    Não
                                </label>
                            </div>
                            @break
                            @case('file')
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $form_field->label }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <input type="file" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field" data-label="{{ $form_field->label }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? true : false }}">
                            </div>
                            @break
                            @case('checkbox')
                            <div class="form-check mt-4">
                                <input class="form-check-input form-field" type="checkbox"
                                    name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    data-label="{{ $form_field->label }}" data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? true : false }}">
                                <label class="form-check-label" for="{{ $form_field->name }}">
                                    {{ $form_field->label }}{{ $form_field->required ? ' *' : '' }}
                                </label>
                            </div>
                            @break
                            @default
                            <div class="form-group">
                                <label for="{{ $form_field->name }}">{{ $form_field->label }}{{
                                    $form_field->required ? ' *'
                                    : '' }}</label>
                                <input type="text" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                    class="form-control form-field" data-label="{{ $form_field->label }}"
                                    data-type="{{ $form_field->type }}"
                                    data-required="{{ $form_field->required ? true : false }}">
                            </div>
                            @endswitch
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-success btn-lg" onclick="submitForm()">Enviar</button>
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
    submitForm = () => {
        const form_id = {{ $form->id }};
        var fields = $('.form-field');
        let formData = new FormData();
        let validation = '';

        // 1) Marcar o último checkbox como "Termos" obrigatório
        const $allCheckboxes = $('.form-field[data-type="checkbox"]');
        if ($allCheckboxes.length > 0) {
            const $terms = $allCheckboxes.last();

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
                validation += '<p>Tem de aceitar os termos e condições.</p>';
            }
        }

        // 2) Recolha + validação genérica
        fields.each((i, v) => {
            let label = $(v).data('label');
            let value = $(v).val();
            let name = $(v).attr('name');
            let type = $(v).data('type');
            let required = $(v).data('required');

            if (required === true) {
                // checkboxes e radios não usam .val() para "vazio"
                if (type === 'checkbox' && !$(v).is(':checked')) {
                    validation += '<p>O campo "' + label + '" é obrigatório.</p>';
                } else if (type === 'radio') {
                    // garante que pelo menos um radio com o mesmo name foi escolhido
                    if ($('input[name="'+name+'"]:checked').length === 0) {
                        validation += '<p>O campo "' + label + '" é obrigatório.</p>';
                    }
                } else if (value === '' || value === null) {
                    validation += '<p>O campo "' + label + '" é obrigatório.</p>';
                }
            }

            // anexos/ficheiros
            if (type === 'file') {
                let fileInput = $(v)[0];
                if (fileInput.files.length > 0) {
                    formData.append(name, fileInput.files[0]);
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
                title: "Faltam dados!",
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
                    title: "Sucesso!",
                    text: "Formulário enviado!",
                    icon: "success"
                }).then(() => {
                    location.reload();
                });
            },
            error: (error) => {
                $.LoadingOverlay('hide');
                console.log(error);
                Swal.fire({
                    title: "Erro!",
                    text: "Não foi possível enviar o formulário.",
                    icon: "error"
                });
            }
        });
    }
</script>
  
</body>

</html>