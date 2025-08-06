<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyTechnicalAssistanteSessionRequest;
use App\Http\Requests\StoreTechnicalAssistanteSessionRequest;
use App\Http\Requests\UpdateTechnicalAssistanteSessionRequest;
use App\Models\TechnicalAssistanteSession;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class TechnicalAssistanteSessionController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('technical_assistante_session_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = TechnicalAssistanteSession::query()->select(sprintf('%s.*', (new TechnicalAssistanteSession)->table));
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate      = 'technical_assistante_session_show';
                $editGate      = 'technical_assistante_session_edit';
                $deleteGate    = 'technical_assistante_session_delete';
                $crudRoutePart = 'technical-assistante-sessions';

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
            $table->editColumn('client', function ($row) {
                return $row->client ? $row->client : '';
            });
            $table->editColumn('client_name', function ($row) {
                return $row->client_name ? $row->client_name : '';
            });
            $table->editColumn('nif', function ($row) {
                return $row->nif ? $row->nif : '';
            });
            $table->editColumn('email', function ($row) {
                return $row->email ? $row->email : '';
            });
            $table->editColumn('invoice_number', function ($row) {
                return $row->invoice_number ? $row->invoice_number : '';
            });
            $table->editColumn('product', function ($row) {
                return $row->product ? $row->product : '';
            });
            $table->editColumn('car', function ($row) {
                return $row->car ? $row->car : '';
            });
            $table->editColumn('comercial', function ($row) {
                return $row->comercial ? $row->comercial : '';
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        return view('admin.technicalAssistanteSessions.index');
    }

    public function create()
    {
        abort_if(Gate::denies('technical_assistante_session_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.technicalAssistanteSessions.create');
    }

    public function store(StoreTechnicalAssistanteSessionRequest $request)
    {
        $technicalAssistanteSession = TechnicalAssistanteSession::create($request->all());

        return redirect()->route('admin.technical-assistante-sessions.index');
    }

    public function edit(TechnicalAssistanteSession $technicalAssistanteSession)
    {
        abort_if(Gate::denies('technical_assistante_session_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.technicalAssistanteSessions.edit', compact('technicalAssistanteSession'));
    }

    public function update(UpdateTechnicalAssistanteSessionRequest $request, TechnicalAssistanteSession $technicalAssistanteSession)
    {
        $technicalAssistanteSession->update($request->all());

        return redirect()->route('admin.technical-assistante-sessions.index');
    }

    public function show(TechnicalAssistanteSession $technicalAssistanteSession)
    {
        abort_if(Gate::denies('technical_assistante_session_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.technicalAssistanteSessions.show', compact('technicalAssistanteSession'));
    }

    public function destroy(TechnicalAssistanteSession $technicalAssistanteSession)
    {
        abort_if(Gate::denies('technical_assistante_session_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $technicalAssistanteSession->delete();

        return back();
    }

    public function massDestroy(MassDestroyTechnicalAssistanteSessionRequest $request)
    {
        $technicalAssistanteSessions = TechnicalAssistanteSession::find(request('ids'));

        foreach ($technicalAssistanteSessions as $technicalAssistanteSession) {
            $technicalAssistanteSession->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
