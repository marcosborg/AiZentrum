@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('cruds.formsInbox.title') }}
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.forms-inboxes.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
            <table class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>
                            Form ID
                        </th>
                        <td>
                            {{ $formData->form_id }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.formData.fields.form') }}
                        </th>
                        <td>
                            {{ $formData->form->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.formData.fields.data') }}
                        </th>
                        <td>
                            <ul class="list-group">
                                @foreach ($formData->data as $field)
                                <li class="list-group-item">
                                    <label><span>[{{ $field['name'] }}]</span>{{ $field['label'] }}</label><br>
                                    @if (is_array($field['value']))
                                        <ul class="mb-0">
                                            @foreach ($field['value'] as $value)
                                                <li>{{ $value }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        {{ $field['value'] }}
                                    @endif
                                </li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.forms-inboxes.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
@section('styles')
<style>
    span {
        font-family: monospace;
        color: #ffffff;
        background: #000000;
        margin-right: 10px;
    }

    label {
        font-size: 12px;
    }
</style>
@endsection
<script>
    console.log({!! $formData !!})
</script>
