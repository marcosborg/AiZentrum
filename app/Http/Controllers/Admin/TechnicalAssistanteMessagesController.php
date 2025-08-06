<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyTechnicalAssistanteMessageRequest;
use App\Http\Requests\StoreTechnicalAssistanteMessageRequest;
use App\Http\Requests\UpdateTechnicalAssistanteMessageRequest;
use App\Models\TechnicalAssistanteMessage;
use App\Models\TechnicalAssistanteSession;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class TechnicalAssistanteMessagesController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('technical_assistante_message_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = TechnicalAssistanteMessage::with(['technical_assistante_session'])->select(sprintf('%s.*', (new TechnicalAssistanteMessage)->table));
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate      = 'technical_assistante_message_show';
                $editGate      = 'technical_assistante_message_edit';
                $deleteGate    = 'technical_assistante_message_delete';
                $crudRoutePart = 'technical-assistante-messages';

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
            $table->addColumn('technical_assistante_session_client', function ($row) {
                return $row->technical_assistante_session ? $row->technical_assistante_session->client : '';
            });

            $table->editColumn('role', function ($row) {
                return $row->role ? TechnicalAssistanteMessage::ROLE_RADIO[$row->role] : '';
            });
            $table->editColumn('content', function ($row) {
                return $row->content ? $row->content : '';
            });

            $table->rawColumns(['actions', 'placeholder', 'technical_assistante_session']);

            return $table->make(true);
        }

        $technical_assistante_sessions = TechnicalAssistanteSession::get();

        return view('admin.technicalAssistanteMessages.index', compact('technical_assistante_sessions'));
    }

    public function create()
    {
        abort_if(Gate::denies('technical_assistante_message_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $technical_assistante_sessions = TechnicalAssistanteSession::pluck('client', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.technicalAssistanteMessages.create', compact('technical_assistante_sessions'));
    }

    public function store(StoreTechnicalAssistanteMessageRequest $request)
    {
        $technicalAssistanteMessage = TechnicalAssistanteMessage::create($request->all());

        return redirect()->route('admin.technical-assistante-messages.index');
    }

    public function edit(TechnicalAssistanteMessage $technicalAssistanteMessage)
    {
        abort_if(Gate::denies('technical_assistante_message_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $technical_assistante_sessions = TechnicalAssistanteSession::pluck('client', 'id')->prepend(trans('global.pleaseSelect'), '');

        $technicalAssistanteMessage->load('technical_assistante_session');

        return view('admin.technicalAssistanteMessages.edit', compact('technicalAssistanteMessage', 'technical_assistante_sessions'));
    }

    public function update(UpdateTechnicalAssistanteMessageRequest $request, TechnicalAssistanteMessage $technicalAssistanteMessage)
    {
        $technicalAssistanteMessage->update($request->all());

        return redirect()->route('admin.technical-assistante-messages.index');
    }

    public function show(TechnicalAssistanteMessage $technicalAssistanteMessage)
    {
        abort_if(Gate::denies('technical_assistante_message_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $technicalAssistanteMessage->load('technical_assistante_session');

        return view('admin.technicalAssistanteMessages.show', compact('technicalAssistanteMessage'));
    }

    public function destroy(TechnicalAssistanteMessage $technicalAssistanteMessage)
    {
        abort_if(Gate::denies('technical_assistante_message_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $technicalAssistanteMessage->delete();

        return back();
    }

    public function massDestroy(MassDestroyTechnicalAssistanteMessageRequest $request)
    {
        $technicalAssistanteMessages = TechnicalAssistanteMessage::find(request('ids'));

        foreach ($technicalAssistanteMessages as $technicalAssistanteMessage) {
            $technicalAssistanteMessage->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
