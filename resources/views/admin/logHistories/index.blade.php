@extends('layouts.admin')
@section('content')


<div class="card">
    <div class="card-header">
        {{ trans('cruds.logHistory.title') }}
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class=" table table-bordered table-striped table-hover datatable datatable-Log">
                <thead>
                    <tr>
                        <th width="10">

                        </th>
                        <th>
                            {{ trans('cruds.log.fields.id') }}
                        </th>
                        <th>
                            {{ trans('cruds.log.fields.project') }}
                        </th>
                        <th>
                            {{ trans('cruds.log.fields.created_at') }}
                        </th>
                        <th>
                            &nbsp;
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $key => $log)
                    <tr data-entry-id="{{ $log->id }}">
                        <td>

                        </td>
                        <td>
                            {{ $log->id ?? '' }}
                        </td>
                        <td>
                            {{ $log->project ?? '' }}
                        </td>
                        <td>
                            {{ $log->created_at ?? '' }}
                        </td>
                        <td>
                            @can('log_show')
                            <a class="btn btn-xs btn-primary" href="/admin/log-histories/history/{{ $log->id }}">
                                {{ trans('global.view') }}
                            </a>
                            @endcan

                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>



@endsection