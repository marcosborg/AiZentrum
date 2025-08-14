@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.show') }} {{ trans('cruds.aiAssistantIntruction.title') }}
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.ai-assistant-intructions.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
            <table class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>
                            {{ trans('cruds.aiAssistantIntruction.fields.id') }}
                        </th>
                        <td>
                            {{ $aiAssistantIntruction->id }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiAssistantIntruction.fields.user') }}
                        </th>
                        <td>
                            {{ $aiAssistantIntruction->user->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiAssistantIntruction.fields.ai_assistant_category') }}
                        </th>
                        <td>
                            {{ $aiAssistantIntruction->ai_assistant_category->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiAssistantIntruction.fields.name') }}
                        </th>
                        <td>
                            {{ $aiAssistantIntruction->name }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.aiAssistantIntruction.fields.instructions') }}
                        </th>
                        <td>
                            {!! $aiAssistantIntruction->instructions !!}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.ai-assistant-intructions.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
        </div>
    </div>
</div>



@endsection