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
use App\Models\AiAssistantCategory;
use App\Models\AiAssistantIntruction; // atenção ao nome do model/tabela, como no teu código
use Illuminate\Support\Facades\Auth;

class AiMessageController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('ai_message_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aiMessages = AiMessage::with(['user'])->get();

        return view('admin.aiMessages.index', compact('aiMessages'));
    }

    public function create(Request $request)
    {
        abort_if(Gate::denies('ai_message_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $ai_message_id = (int) $request->query('ai_message_id', 0);
        $ai_message    = $ai_message_id ? AiMessage::find($ai_message_id) : null;

        // Thread = ID do cliente ZCM (vem em ?client=)
        $threadId = $request->query('client')
            ?? old('client')
            ?? ($ai_message ? $ai_message->client : null);

        $history = collect();
        if (!empty($threadId)) {
            $history = AiMessage::with('user')
                ->where('client', $threadId)
                ->orderBy('created_at', 'asc')
                ->get(['id', 'context', 'ai_response', 'user_id', 'created_at']);
        }

        return view('admin.aiMessages.create', compact('users', 'ai_message_id', 'ai_message', 'history', 'threadId'));
    }


    public function store(StoreAiMessageRequest $request)
    {
        abort_if(Gate::denies('ai_message_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->all();

        // NÃO forçar parent_id = client (parent_id é FK para ai_messages.id)
        if (empty($data['user_id']) && auth()->check()) {
            $data['user_id'] = auth()->id();
        }

        $aiMessage = AiMessage::create($data);

        $response = app(\App\Services\ChatGptService::class)->generateAiResponse($aiMessage);

        $aiMessage->update(['ai_response' => $response]);

        return redirect()->route('admin.ai-messages.edit', $aiMessage->id);
    }

    public function edit(AiMessage $aiMessage)
    {
        abort_if(Gate::denies('ai_message_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $threadId = $aiMessage->client;
        $history = AiMessage::with('user')
            ->where('client', $threadId)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'context', 'ai_response', 'user_id', 'created_at']);

        return view('admin.aiMessages.edit', compact('aiMessage', 'users', 'history', 'threadId'));
    }

    public function update(UpdateAiMessageRequest $request, AiMessage $aiMessage)
    {
        abort_if(Gate::denies('ai_message_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $willResolveThread = $request->boolean('resolved');

        // 🌟 Se for para resolver, não mexemos em mais nada:
        if ($willResolveThread && $aiMessage->client) {
            AiMessage::where('client', $aiMessage->client)->update(['resolved' => true]);

            return redirect()
                ->route('admin.ai-messages.index')
                ->with('status', 'Thread marcada como resolvida. Não foi gerada nova resposta da AI.');
        }

        // Caso contrário, segue o fluxo normal de update + regeneração
        $aiMessage->update($request->all());
        $aiMessage->refresh()->load('user');

        $response = app(\App\Services\ChatGptService::class)->generateAiResponse($aiMessage);
        $aiMessage->update(['ai_response' => $response]);

        return redirect()
            ->route('admin.ai-messages.edit', $aiMessage->id)
            ->with('status', 'Registo atualizado e resposta da AI regenerada.');
    }

    public function show(AiMessage $aiMessage)
    {
        abort_if(Gate::denies('ai_message_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        // Thread = id cliente ZCM
        $threadId = $aiMessage->client;

        // Histórico completo desta thread (cliente), do mais antigo para o mais recente
        $history = AiMessage::with('user')
            ->where('client', $threadId)
            ->orderBy('created_at', 'asc')
            ->get([
                'id',
                'context',
                'ai_response',
                'user_id',
                'created_at'
            ]);

        return view('admin.aiMessages.show', compact('aiMessage', 'history', 'threadId'));
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

    // App\Http\Controllers\Admin\AiMessageController.php
    public function history(Request $request)
    {
        $threadId = $request->input('client');
        if (!$threadId) {
            return response()->json([]);
        }

        $history = AiMessage::where('client', $threadId)
            ->orderBy('created_at', 'asc')
            ->get(['context', 'ai_response', 'created_at']);

        // devolvemos JSON simples; o front trata de renderizar
        return response()->json($history);
    }

    public function assistantCategories(Request $request)
    {
        $userId = $request->query('user_id') ?: (Auth::check() ? Auth::id() : null);

        // categorias do utilizador + (opcional) categorias globais (user_id = null)
        $cats = AiAssistantCategory::query()
            ->when($userId, function ($q) use ($userId) {
                $q->where(function ($q2) use ($userId) {
                    $q2->where('user_id', $userId)->orWhereNull('user_id');
                });
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($cats);
    }

    public function assistantInstructions(Request $request)
    {
        $userId     = $request->query('user_id') ?: (Auth::check() ? Auth::id() : null);
        $categoryId = $request->query('category_id');

        if (!$categoryId) {
            return response()->json([]);
        }

        $list = AiAssistantIntruction::query()
            ->where('ai_assistant_category_id', $categoryId)
            ->when($userId, function ($q) use ($userId) {
                $q->where(function ($q2) use ($userId) {
                    $q2->where('user_id', $userId)->orWhereNull('user_id');
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'instructions']);

        return response()->json($list);
    }

    private function resolveThread(Request $request)
    {

        $threadId = $request->input('client');
        if (!$threadId) {
            return back()->with('error', 'Thread inválida.');
        }

        AiMessage::where('client', $threadId)->update(['resolved' => true]);

        return back()->with('status', 'Toda a thread foi marcada como resolvida.');
    }
}
