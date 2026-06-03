<!doctype html>
<html lang="{{ $locale }}">

@php
    $languages = trans('public_forms.languages');
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zentrum Group | {{ trans('public_forms.ui.forms') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
</head>

<body>
    @if ($forms->count() > 0)
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card mt-5">
                    <div class="card-header text-center">
                        <div class="d-flex justify-content-end mb-2">
                            <div class="btn-group btn-group-sm" role="group" aria-label="{{ trans('public_forms.ui.language') }}">
                                @foreach ($languages as $code => $name)
                                    <a href="{{ url('/form/all/' . $forms->first()->project->id) }}?lang={{ $code }}"
                                        class="btn {{ $locale === $code ? 'btn-primary' : 'btn-outline-primary' }}">
                                        {{ strtoupper($code) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                        <img src="{{ asset('images/logo-black.png') }}" class="img-thumbnail" width="100">
                        <h1>{{ $forms->first()->project->name }}</h1>
                        <h4>{{ trans('public_forms.ui.customer_service_center') }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach ($forms as $form)
                            <div class="col-md-4">
                                <div class="d-grid gap-2">
                                    <a href="{{ url('/form/' . $form->id) }}?lang={{ $locale }}" class="btn btn-outline-primary btn-lg m-2">
                                        {{ data_get(trans('public_forms.forms'), $form->id, $form->name) }}
                                    </a>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="alert alert-primary" role="alert">
        {{ trans('public_forms.ui.no_forms') }}
    </div>
    @endif

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous">
    </script>
</body>

</html>
