<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Bot;

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

        // Obtem instruções do bot (ID 2)
        $bot = Bot::find(2);
        $instrucoes = $bot?->instructions ?? 'És um assistente técnico amigável.';

        // Mensagem de contexto (se existir)
        if ($contexto) {
            $mensagemContexto = "Contexto Técnico:\n"
                . "- Número da Fatura: {$contexto['invoice_number']}\n"
                . "- Produto: {$contexto['product']}\n"
                . "- Veículo: {$contexto['car']}\n"
                . "- Comercial: {$contexto['comercial']}";
        } else {
            $mensagemContexto = "Contexto técnico não fornecido.";
        }

        // Construção da conversa com GPT
        $messages = [
            ['role' => 'system', 'content' => $instrucoes],
            ['role' => 'system', 'content' => $mensagemContexto],
        ];

        // Junta o histórico
        $historico = session()->get('chat_history', []);
        foreach ($historico as $mensagem) {
            $messages[] = $mensagem;
        }

        // Adiciona a pergunta atual
        $messages[] = ['role' => 'user', 'content' => $mensagemUser];

        try {
            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o', // ou gpt-4 se preferires
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

            // Guarda histórico
            $historico[] = ['role' => 'user', 'content' => $mensagemUser];
            $historico[] = ['role' => 'assistant', 'content' => $respostaTexto];
            session()->put('chat_history', $historico);

            return response()->json(['resposta' => $respostaTexto]);
        } catch (\Exception $e) {
            Log::error('Exceção na chamada ao GPT: ' . $e->getMessage());
            return response()->json([
                'resposta' => 'Desculpa, ocorreu um erro ao tentar responder. Por favor tenta novamente mais tarde.'
            ], 500);
        }
    }

    public function resetChat()
    {
        session()->forget('chat_history');
        return response()->json(['success' => true]);
    }
}
