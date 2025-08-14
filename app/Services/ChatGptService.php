<?php

namespace App\Services;

use App\Models\AiMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatGptService
{
    /**
     * Gera a resposta da AI usando o histórico completo do cliente (thread = client).
     * Mantém compatibilidade com dados antigos (se algum usar parent_id como thread).
     */
    public function generateAiResponse(AiMessage $aiMessage): string
    {
        $apiKey = config('services.openai.key');
        $model  = config('services.openai.model', 'gpt-4o-mini'); // opcional em config/services.php

        if (empty($apiKey)) {
            Log::error('OpenAI API key em falta. Define OPENAI_API_KEY no .env e limpa a cache de config.');
            return 'Não foi possível gerar uma resposta automática neste momento (configuração da API em falta).';
        }

        // Thread = ID do cliente ZCM
        $threadId = $aiMessage->client ?: $aiMessage->parent_id;

        // Histórico anterior a esta mensagem
        $past = collect();
        if (!empty($threadId)) {
            $past = AiMessage::where(function ($q) use ($threadId) {
                    // principal: usar client
                    $q->where('client', $threadId)
                      // compat: caso exista dado antigo que tenha usado parent_id como thread
                      ->orWhere('parent_id', $threadId);
                })
                ->where('id', '<', $aiMessage->id)
                ->orderBy('created_at', 'asc')
                ->get(['context', 'ai_response', 'created_at']);
        }

        // Mapas legíveis para conflict_type e urgency
        $conflictLabel = $aiMessage->conflict_type
            ? (AiMessage::CONFLICT_TYPE_RADIO[$aiMessage->conflict_type] ?? (string) $aiMessage->conflict_type)
            : '—';

        $urgencyLabel = $aiMessage->urgency
            ? (AiMessage::URGENCY_RADIO[$aiMessage->urgency] ?? (string) $aiMessage->urgency)
            : '—';

        $user = $aiMessage->user;

        // Mensagens base
        $messages = [
            [
                'role'    => 'system',
                'content' => 'És um assistente de atendimento ao cliente: profissional, empático, claro e focado na resolução. Evita jargão técnico; propõe passos concretos e prazos realistas. Se precisares de dados em falta, pede-os de forma educada.'
            ],
            [
                'role'    => 'user',
                'content' =>
                    "Dados do cliente\n".
                    "- Nome: {$aiMessage->client_name}\n".
                    "- ID: {$aiMessage->client}\n".
                    "- Email: {$aiMessage->email}\n".
                    "- NIF: {$aiMessage->nif}\n".
                    "- Atendido por: ".($user?->name ?? '—')."\n\n".
                    "Metadados\n".
                    "- Tipo de conflito: {$conflictLabel}\n".
                    "- Urgência: {$urgencyLabel}\n"
            ],
        ];

        // Turnos anteriores (cliente -> assistente)
        $turns = [];
        foreach ($past as $m) {
            if (!empty($m->context)) {
                $turns[] = ['role' => 'user', 'content' => (string) $m->context];
            }
            if (!empty($m->ai_response)) {
                $turns[] = ['role' => 'assistant', 'content' => (string) $m->ai_response];
            }
        }

        // Limitar número de turnos para poupar tokens
        $MAX_TURNS = 10; // 10 interações (user+assistant)
        if (count($turns) > $MAX_TURNS * 2) {
            $turns = array_slice($turns, -$MAX_TURNS * 2);
        }

        // Junta histórico + mensagem atual
        $messages = array_merge($messages, $turns);

        // Mensagem atual do cliente (contexto)
        $messages[] = [
            'role'    => 'user',
            'content' => (string) ($aiMessage->context ?? '')
        ];

        // Pedido final orientado à ação
        $messages[] = [
            'role'    => 'user',
            'content' => "Responde como gestor de cliente, mantendo tom cordial. Indica solução clara, próximos passos com responsáveis e prazos. Se necessário, pede dados em falta. Portugues de Portugal."
        ];

        try {
            $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => $model,
                    'messages'    => $messages,
                    'temperature' => 0.3,
                    'max_tokens'  => 800,
                ]);

            if ($response->successful()) {
                $content = data_get($response->json(), 'choices.0.message.content');
                return is_string($content) && $content !== '' ? trim($content) : 'Não foi possível obter conteúdo da resposta.';
            }

            Log::warning('Falha na chamada OpenAI', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            switch ($response->status()) {
                case 401:
                    return 'Não foi possível gerar a resposta (falha de autenticação com a API).';
                case 429:
                    return 'O sistema de respostas está momentaneamente ocupado. Tenta novamente dentro de instantes.';
                default:
                    return 'Não foi possível gerar uma resposta automática neste momento.';
            }
        } catch (\Throwable $e) {
            Log::error('Erro ao contactar OpenAI: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return 'Ocorreu um erro ao gerar a resposta automática.';
        }
    }
}
