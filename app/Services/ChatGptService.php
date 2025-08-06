<?php

namespace App\Services;

use App\Models\AiMessage;
use Illuminate\Support\Facades\Http;

class ChatGptService
{
    public function generateAiResponse(AiMessage $aiMessage): string
    {
        $user = $aiMessage->user;

        // Prepara o prompt com base nos dados do próprio AiMessage
        $prompt = <<<EOT
O seguinte cliente apresentou uma reclamação. Gera uma resposta profissional, empática e orientada para a resolução do problema. Evita linguagem técnica desnecessária e mantém o tom cordial.

--- Cliente ---
Nome: {$aiMessage->client_name}
ID: {$aiMessage->client}
Email: {$aiMessage->email}
NIF: {$aiMessage->nif}


--- Contactado por ---
Utilizador: {$user?->name}

--- Contexto ---
{$aiMessage->context}

Responde diretamente à reclamação como se fosses um gestor da empresa, com objetivo de ajudar o cliente e evitar escalonamento.
EOT;

        $apiKey = config('services.openai.key');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'És um assistente de atendimento ao cliente.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
        ]);

        dd($response->json());

        if ($response->successful()) {
            return $response->json('choices.0.message.content');
        }

        return 'Não foi possível gerar uma resposta automática neste momento.';
    }
}
