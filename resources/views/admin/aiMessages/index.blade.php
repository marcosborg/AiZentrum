@extends('layouts.admin')
@section('content')
@can('ai_message_create')
    <div style="margin-bottom: 10px;" class="row">
        <div class="col-lg-12">
            <a class="btn btn-success" href="{{ route('admin.ai-messages.create') }}">
                {{ trans('global.add') }} {{ trans('cruds.aiMessage.title_singular') }}
            </a>
        </div>
    </div>
@endcan
<div class="card">
    <div class="card-header">
        {{ trans('cruds.aiMessage.title_singular') }} {{ trans('global.list') }}
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class=" table table-bordered table-striped table-hover datatable datatable-AiMessage">
                <thead>
                    <tr>
                        <th width="10">

                        </th>
                        <th>
                            {{ trans('cruds.aiMessage.fields.id') }}
                        </th>
                        <th>
                            {{ trans('cruds.aiMessage.fields.client') }}
                        </th>
                        <th>
                            ZCM client ID
                        </th>
                        <th>
                            {{ trans('cruds.aiMessage.fields.email') }}
                        </th>
                        <th>
                            {{ trans('cruds.aiMessage.fields.nif') }}
                        </th>
                        <th>
                            {{ trans('cruds.aiMessage.fields.user') }}
                        </th>
                        <th>
                            {{ trans('cruds.aiMessage.fields.conflict_type') }}
                        </th>
                        <th>
                            {{ trans('cruds.aiMessage.fields.urgency') }}
                        </th>
                        <th>
                            {{ trans('cruds.aiMessage.fields.resolved') }}
                        </th>
                        <th>
                            &nbsp;
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($aiMessages as $key => $aiMessage)
                        <tr data-entry-id="{{ $aiMessage->id }}">
                            <td>

                            </td>
                            <td>
                                {{ $aiMessage->id ?? '' }}
                            </td>
                            <td>
                                {{ $aiMessage->client_name ?? '' }}
                            </td>
                            <td>
                                {{ $aiMessage->client ?? '' }}
                            </td>
                            <td>
                                {{ $aiMessage->email ?? '' }}
                            </td>
                            <td>
                                {{ $aiMessage->nif ?? '' }}
                            </td>
                            <td>
                                {{ $aiMessage->user->name ?? '' }}
                            </td>
                            <td>
                                {{ App\Models\AiMessage::CONFLICT_TYPE_RADIO[$aiMessage->conflict_type] ?? '' }}
                            </td>
                            <td>
                                {{ App\Models\AiMessage::URGENCY_RADIO[$aiMessage->urgency] ?? '' }}
                            </td>
                            <td>
                                <span style="display:none">{{ $aiMessage->resolved ?? '' }}</span>
                                <input type="checkbox" disabled="disabled" {{ $aiMessage->resolved ? 'checked' : '' }}>
                            </td>
                            <td>
                                @can('ai_message_show')
                                    <a class="btn btn-xs btn-primary" href="{{ route('admin.ai-messages.show', $aiMessage->id) }}">
                                        {{ trans('global.view') }}
                                    </a>
                                @endcan

                                @can('ai_message_edit')
                                    <a class="btn btn-xs btn-info" href="{{ route('admin.ai-messages.edit', $aiMessage->id) }}">
                                        {{ trans('global.edit') }}
                                    </a>
                                @endcan

                                @can('ai_message_delete')
                                    <form action="{{ route('admin.ai-messages.destroy', $aiMessage->id) }}" method="POST" onsubmit="return confirm('{{ trans('global.areYouSure') }}');" style="display: inline-block;">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <input type="submit" class="btn btn-xs btn-danger" value="{{ trans('global.delete') }}">
                                    </form>
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
@section('scripts')
@parent
<script>
    $(function () {
  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)
@can('ai_message_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}'
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.ai-messages.massDestroy') }}",
    className: 'btn-danger',
    action: function (e, dt, node, config) {
      var ids = $.map(dt.rows({ selected: true }).nodes(), function (entry) {
          return $(entry).data('entry-id')
      });

      if (ids.length === 0) {
        alert('{{ trans('global.datatables.zero_selected') }}')

        return
      }

      if (confirm('{{ trans('global.areYouSure') }}')) {
        $.ajax({
          headers: {'x-csrf-token': _token},
          method: 'POST',
          url: config.url,
          data: { ids: ids, _method: 'DELETE' }})
          .done(function () { location.reload() })
      }
    }
  }
  dtButtons.push(deleteButton)
@endcan

  $.extend(true, $.fn.dataTable.defaults, {
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,
  });
  let table = $('.datatable-AiMessage:not(.ajaxTable)').DataTable({ buttons: dtButtons })
  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(e){
      $($.fn.dataTable.tables(true)).DataTable()
          .columns.adjust();
  });
  
})

</script>
@endsection