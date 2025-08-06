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
            'contexto' => 'required|array',
            'historico' => 'nullable|array',
        ]);

        $mensagemUser = $request->input('mensagem');
        $contexto = $request->input('contexto');
        $historico = $request->input('historico') ?? [];

        // LOG para depuração
        //Log::info('Dados recebidos no suporte técnico:', compact('mensagemUser', 'contexto', 'historico'));

        // Obter instruções do bot
        $bot = Bot::find(2);
        $instrucoes = $bot?->instructions ?? 'És um assistente técnico amigável.';

        // Criar sessão de suporte com base no cliente e contexto
        $client = $contexto['client'] ?? [];

        $session = TechnicalAssistanteSession::firstOrCreate([
            'client' => $client['id'] ?? null,
            'invoice_number' => $contexto['invoice_number'] ?? null,
        ], [
            'client_name'     => $client['name'] ?? null,
            'nif'             => $client['nif'] ?? null,
            'email'           => $client['mail'] ?? null,
            'product'         => $contexto['product'] ?? null,
            'car'             => $contexto['car'] ?? null,
            'comercial'       => $contexto['comercial'] ?? null,
        ]);

        // Adicionar instruções + contexto técnico
        $messages = [
            ['role' => 'system', 'content' => $instrucoes],
            ['role' => 'system', 'content' => "Contexto Técnico:\n- Nº Fatura: {$contexto['invoice_number']}\n- Produto: {$contexto['product']}\n- Veículo: {$contexto['car']}\n- Comercial: {$contexto['comercial']}"],
        ];

        // Guardar histórico anterior (se existir)
        $isNewSession = $session->wasRecentlyCreated;

        if ($isNewSession && is_array($historico)) {
            foreach ($historico as $msg) {
                TechnicalAssistanteMessage::create([
                    'technical_assistante_session_id' => $session->id,
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ]);
            }
        }

        // Adicionar nova mensagem do utilizador
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
                Log::error('Erro da OpenAI: ' . $response->body());
                return response()->json(['resposta' => 'Erro ao tentar obter resposta do assistente.'], 500);
            }

            $respostaTexto = $response->json()['choices'][0]['message']['content'];

            // Guardar a nova interação
            TechnicalAssistanteMessage::create([
                'technical_assistante_session_id' => $session->id,
                'role' => 'user',
                'content' => $mensagemUser,
            ]);

            TechnicalAssistanteMessage::create([
                'technical_assistante_session_id' => $session->id,
                'role' => 'assistant',
                'content' => $respostaTexto,
            ]);

            return response()->json(['resposta' => $respostaTexto]);
        } catch (\Exception $e) {
            Log::error('Exceção na resposta GPT: ' . $e->getMessage());
            return response()->json(['resposta' => 'Erro ao tentar responder.'], 500);
        }
    }


    public function resetChat(Request $request)
    {
        $contexto = $request->input('contexto', []);
        $client = is_array($contexto['client'] ?? null) ? ($contexto['client']['id'] ?? null) : ($contexto['client'] ?? null);
        $invoice = $contexto['invoice_number'] ?? null;

        $sessao = TechnicalAssistanteSession::where('client', $client)
            ->where('invoice_number', $invoice)
            ->first();

        if ($sessao) {
            $sessao->messages()->delete();
        }

        return response()->json(['success' => true]);
    }
}
