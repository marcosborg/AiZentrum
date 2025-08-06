<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyAiMessageRequest;
use App\Http\Requests\StoreAiMessageRequest;
use App\Http\Requests\UpdateAiMessageRequest;
use App\Models\AiMessage;
use App\Services\ChatGptService;
use App\Models\User;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Http;

class AiMessageController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('ai_message_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aiMessages = AiMessage::with(['parent', 'user'])->get();

        return view('admin.aiMessages.index', compact('aiMessages'));
    }

    public function create()
    {
        abort_if(Gate::denies('ai_message_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $parents = AiMessage::pluck('client', 'id')->prepend(trans('global.pleaseSelect'), '');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.aiMessages.create', compact('parents', 'users'));
    }

    public function store(StoreAiMessageRequest $request)
    {
        // Cria o registo
        $aiMessage = AiMessage::create($request->all());

        // Gera resposta da AI
        $response = app(ChatGptService::class)->generateAiResponse($aiMessage);

        // Atualiza com a resposta da AI
        $aiMessage->update([
            'ai_response' => $response
        ]);

        // Redireciona para edição já com resposta preenchida
        return redirect()->route('admin.ai-messages.edit', $aiMessage->id);
    }

    public function edit(AiMessage $aiMessage)
    {
        abort_if(Gate::denies('ai_message_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $parents = AiMessage::pluck('client', 'id')->prepend(trans('global.pleaseSelect'), '');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $aiMessage->load('parent', 'user');

        return view('admin.aiMessages.edit', compact('aiMessage', 'parents', 'users'));
    }

    public function update(UpdateAiMessageRequest $request, AiMessage $aiMessage)
    {
        $aiMessage->update($request->all());

        return redirect()->route('admin.ai-messages.index');
    }

    public function show(AiMessage $aiMessage)
    {
        abort_if(Gate::denies('ai_message_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aiMessage->load('parent', 'user');

        return view('admin.aiMessages.show', compact('aiMessage'));
    }

    public function destroy(AiMessage $aiMessage)
    {
        abort_if(Gate::denies('ai_message_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aiMessage->delete();

        return back();
    }

    public function massDestroy(MassDestroyAiMessageRequest $request)
    {
        $aiMessages = AiMessage::find(request('ids'));

        foreach ($aiMessages as $aiMessage) {
            $aiMessage->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function search(Request $request)
    {
        $term = $request->get('term');

        // Verifica se é e-mail ou NIF
        $data = [];

        if (filter_var($term, FILTER_VALIDATE_EMAIL)) {
            $data['mail'] = $term;
        } elseif (preg_match('/^\d{9}$/', $term)) {
            $data['nif'] = $term;
        } else {
            return response()->json([]); // Termo inválido
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://zcmanager.com/api/ai/get-client-by-mail-or-nif', $data);

        if ($response->successful()) {
            $clients = $response->json(); // agora é array

            $result = collect($clients)->map(function ($client) {
                return [
                    'id'      => $client['id'] ?? null,
                    'name'    => $client['name'] ?? 'Desconhecido',
                    'email'   => $client['mail'] ?? '',
                    'nif'     => $client['nif'] ?? '',
                    'context' => $client['context'] ?? '',
                ];
            });

            return response()->json($result);
        }

        return response()->json([]);
    }
}
