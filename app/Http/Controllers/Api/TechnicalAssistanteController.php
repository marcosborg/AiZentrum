<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Bot;
use App\Models\TechnicalAssistanteSession;
use App\Models\TechnicalAssistanteMessage;

class TechnicalAssistanteController extends Controller
{
    public function responder(Request $request)
    {
        $request->validate([
            'mensagem' => 'required|string|min:3',
            'contexto' => 'nullable|array',
        ]);

        $mensagemUser = $request->input('mensagem');
        $contexto = $request->input('contexto');

        // Obter instruções do bot (ID 2)
        $bot = Bot::find(2);
        $instrucoes = $bot?->instructions ?? 'És um assistente técnico amigável.';

        // Criar ou obter sessão associada
        $session = TechnicalAssistanteSession::firstOrCreate(
            [
                'client'          => $contexto['client'] ?? null,
                'invoice_number'  => $contexto['invoice_number'] ?? null,
            ],
            [
                'client_name'     => $contexto['client_name'] ?? '',
                'nif'             => $contexto['nif'] ?? '',
                'email'           => $contexto['email'] ?? '',
                'product'         => $contexto['product'] ?? '',
                'car'             => $contexto['car'] ?? '',
                'comercial'       => $contexto['comercial'] ?? '',
            ]
        );

        // Mensagem de contexto
        $mensagemContexto = $contexto
            ? "Contexto Técnico:\n"
            . "- Número da Fatura: {$contexto['invoice_number']}\n"
            . "- Produto: {$contexto['product']}\n"
            . "- Veículo: {$contexto['car']}\n"
            . "- Comercial: {$contexto['comercial']}"
            : "Contexto técnico não fornecido.";

        $messages = [
            ['role' => 'system', 'content' => $instrucoes],
            ['role' => 'system', 'content' => $mensagemContexto],
        ];

        // Conversa gravada em BD (últimas mensagens associadas)
        $historico = $session->technical_assistante_messages()->orderBy('created_at')->get();
        foreach ($historico as $mensagem) {
            $messages[] = [
                'role' => $mensagem->role,
                'content' => $mensagem->content,
            ];
        }

        // Adiciona mensagem atual do utilizador
        $messages[] = ['role' => 'user', 'content' => $mensagemUser];

        try {
            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o',
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                ]);

            if (!$response->successful()) {
                Log::error('Erro na API OpenAI: ' . $response->body());
                return response()->json([
                    'resposta' => 'Desculpa, ocorreu um erro ao tentar responder (API). Tenta novamente mais tarde.'
                ], 500);
            }

            $respostaTexto = $response->json()['choices'][0]['message']['content'];

            // Gravar mensagens
            $session->technical_assistante_messages()->createMany([
                [
                    'role' => 'user',
                    'content' => $mensagemUser,
                ],
                [
                    'role' => 'assistant',
                    'content' => $respostaTexto,
                ],
            ]);

            return response()->json(['resposta' => $respostaTexto]);
        } catch (\Exception $e) {
            Log::error('Exceção na chamada ao GPT: ' . $e->getMessage());
            return response()->json([
                'resposta' => 'Desculpa, ocorreu um erro ao tentar responder. Por favor tenta novamente mais tarde.'
            ], 500);
        }
    }


    public function resetChat(Request $request)
    {
        $sessionId = $request->input('session_id');

        if ($sessionId) {
            TechnicalAssistanteMessage::where('technical_assistante_session_id', $sessionId)->delete();
        }

        return response()->json(['success' => true]);
    }
}
