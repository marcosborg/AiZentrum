@if ($form)
<div class="card mt-4">
    <div class="card-header">
        <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#add_field">Add
            field</button>
        <button class="btn btn-danger btn-sm pull-right" onclick="deleteForm({{ $form->id }})"><i
                class="fa-fw fas fa-trash"></i></button>
    </div>
    <div class="card-body" style="background: #eeeeee;">
        @if ($form && $form->logo)
        <img src="https://ai.airbagszentrum.com/public/images/logo-black.png" class="img-thumbnail mb-4" width="100">
        @endif
        @if ($form->form_fields->count() == 0)
        <div class="alert alert-primary" role="alert">
            Ainda não existem campos.
        </div>
        @else
        @if ($form)
        <div id="sortable" class="row">
            @foreach ($form->form_fields as $form_field)
            <div class="col-md-4">
                <div class="card" data-position={{ $form_field->position }} data-id="{{ $form_field->id }}">
                    <div class="card-body" style="padding: 10px">
                        <div class="delete" onclick="deleteField({{ $form_field->id }})"><i
                                class="fa-fw fas fa-trash"></i></div>
                        @switch($form_field->type)
                        @case('text')
                        <div class="form-group">
                            <label for="{{ $form_field->name }}">{{ $form_field->label }}{{ $form_field->required ? ' *'
                                : '' }}</label>
                            <input type="text" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                class="form-control form-field" data-label="{{ $form_field->label }}"
                                data-type="{{ $form_field->type }}"
                                data-required="{{ $form_field->required ? true : false }}">
                        </div>
                        @break
                        @case('date')
                        <div class="form-group">
                            <label for="{{ $form_field->name }}">{{ $form_field->label }}{{ $form_field->required ? ' *'
                                : '' }}</label>
                            <input type="date" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                class="form-control form-field" data-label="{{ $form_field->label }}"
                                data-type="{{ $form_field->type }}"
                                data-required="{{ $form_field->required ? true : false }}">
                        </div>
                        @break
                        @case('email')
                        <div class="form-group">
                            <label for="{{ $form_field->name }}">{{ $form_field->label }}{{ $form_field->required ? ' *'
                                : '' }}</label>
                            <input type="email" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                class="form-control form-field" data-label="{{ $form_field->label }}"
                                data-type="{{ $form_field->type }}"
                                data-required="{{ $form_field->required ? true : false }}">
                        </div>
                        @break
                        @case('textarea')
                        <div class="form-group">
                            <label for="{{ $form_field->name }}">{{ $form_field->label }}{{ $form_field->required ? ' *'
                                : '' }}</label>
                            <textarea name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                class="form-control form-field" data-label="{{ $form_field->label }}"
                                data-type="{{ $form_field->type }}"
                                data-required="{{ $form_field->required ? true : false }}"></textarea>
                        </div>
                        @break
                        @case('radio')
                        <label>{{ $form_field->label }}</label>
                        <div class="form-check">
                            <input class="form-check-input form-field" type="radio" name="{{ $form_field->name }}"
                                value="yes" checked id="check-{{ $form_field->id }}-1"
                                data-label="{{ $form_field->label }}" data-type="{{ $form_field->type }}" value="yes">
                            <label class="form-check-label" for="check-{{ $form_field->id }}-1">
                                Sim
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input form-field" type="radio" name="{{ $form_field->name }}"
                                value="no" id="check-{{ $form_field->id }}-2" data-label="{{ $form_field->label }}"
                                data-type="{{ $form_field->type }}" value="no">
                            <label class="form-check-label" for="check-{{ $form_field->id }}-2">
                                Não
                            </label>
                        </div>
                        @break
                        @case('file')
                        <div class="form-group">
                            <label for="{{ $form_field->name }}">{{ $form_field->label }}{{ $form_field->required ? ' *'
                                : '' }}</label>
                            <input type="file" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                class="form-control form-field" data-label="{{ $form_field->label }}"
                                data-type="{{ $form_field->type }}"
                                data-required="{{ $form_field->required ? true : false }}">
                        </div>
                        @break
                        @case('checkbox')
                        <div class="form-check">
                            <input class="form-check-input form-field" type="checkbox" name="{{ $form_field->name }}"
                                id="{{ $form_field->name }}" data-label="{{ $form_field->label }}"
                                data-type="{{ $form_field->type }}"
                                data-required="{{ $form_field->required ? true : false }}">
                            <label class="form-check-label" for="{{ $form_field->name }}">
                                {{ $form_field->label }}{{ $form_field->required ? ' *' : '' }}
                            </label>
                        </div>
                        @break
                        @default
                        <div class="form-group">
                            <label for="{{ $form_field->name }}">{{ $form_field->label }}{{ $form_field->required ? ' *'
                                : '' }}</label>
                            <input type="text" name="{{ $form_field->name }}" id="{{ $form_field->name }}"
                                class="form-control form-field" data-label="{{ $form_field->label }}"
                                data-type="{{ $form_field->type }}"
                                data-required="{{ $form_field->required ? true : false }}">
                        </div>
                        @endswitch
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
        @endif
        <div class="alert alert-info">
            <strong>Form url</strong><br>
            <a target="_new" href="{{ url('/') . '/form/' . $form->id }}">{{ url('/') . '/form/' . $form->id }}</a>
        </div>
    </div>
    <div class="card-footer">
        <button type="button" class="btn btn-success" onclick="submitForm({{ $form->id }})">Send</button>
        <div class="pull-right">* <small>Required</small></div>
    </div>
</div>
@endif