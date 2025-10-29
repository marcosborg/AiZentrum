<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyLogRequest;
use App\Http\Requests\StoreLogRequest;
use App\Http\Requests\UpdateLogRequest;
use App\Models\Log;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;


class LogController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('log_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = Log::query()->select(sprintf('%s.*', (new Log)->table));
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate      = 'log_show';
                $editGate      = 'log_edit';
                $deleteGate    = 'log_delete';
                $crudRoutePart = 'logs';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->editColumn('project', function ($row) {
                return $row->project ? $row->project : '';
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        return view('admin.logs.index');
    }

    public function create()
    {
        abort_if(Gate::denies('log_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.logs.create');
    }

    public function store(StoreLogRequest $request)
    {
        $log = Log::create($request->all());

        return redirect()->route('admin.logs.index');
    }

    public function edit(Log $log)
    {
        abort_if(Gate::denies('log_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.logs.edit', compact('log'));
    }

    public function update(UpdateLogRequest $request, Log $log)
    {
        $log->update($request->all());

        return redirect()->route('admin.logs.index');
    }

    public function show(Log $log)
    {
        abort_if(Gate::denies('log_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.logs.show', compact('log'));
    }

    public function destroy(Log $log)
    {
        abort_if(Gate::denies('log_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $log->delete();

        return back();
    }

    public function massDestroy(MassDestroyLogRequest $request)
    {
        $logs = Log::find(request('ids'));

        foreach ($logs as $log) {
            $log->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
