<?php
namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\Log as ChatLog;
use App\Models\LogMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ChatContact;

class PublicChatController extends Controller
{
    public function history(Request $request, $assistant)
    {
        Assistant::findOrFail($assistant);
        return response()->json($request->session()->get("public_chat.$assistant.messages", []));
    }

    public function message(Request $request, $assistant)
    {
        $data = $request->validate(['message' => 'required|string|max:6000']);
        $bot = Assistant::with(['instructions', 'project.openai'])->findOrFail($assistant);
        $key = $bot->project->openai->openai_api_key ?: config('services.openai.key');
        $state = $request->session()->get("public_chat.$assistant", ['messages' => []]);
        $input = array_map(fn ($m) => ['role' => $m['role'] === 'chat' ? 'assistant' : 'user', 'content' => $m['message']], array_slice($state['messages'], -30));
        $input[] = ['role' => 'user', 'content' => $data['message']];
        $instructions = $bot->instructions->pluck('text')->implode("\n\n");
        $instructions .= "\nResponde em português de Portugal. Mantém o contexto da conversa. Usa get_products para consultar produtos e send_email apenas se o cliente pedir e confirmar o envio do pedido de contacto. Nunca afirmes que executaste ações sem resultado da ferramenta. Não inventes URLs de formulários; usa apenas os indicados nas instruções. Responde em texto simples ou Markdown, sem HTML.";
        $tools = [
            ['type' => 'function', 'name' => 'get_products', 'description' => 'Consultar produtos por nome ou referência.', 'parameters' => ['type' => 'object', 'properties' => ['symbol' => ['type' => 'string']], 'required' => ['symbol'], 'additionalProperties' => false], 'strict' => true],
            ['type' => 'function', 'name' => 'send_email', 'description' => 'Enviar pedido de contacto confirmado pelo cliente à equipa comercial.', 'parameters' => ['type' => 'object', 'properties' => ['data' => ['type' => 'string']], 'required' => ['data'], 'additionalProperties' => false], 'strict' => true],
        ];
        try {
            $text = '';
            $emailSent = false;
            for ($round = 0; $round < 4; $round++) {
                $response = Http::withToken($key)->timeout(40)->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'instructions' => $instructions, 'input' => $input, 'tools' => $tools,
                    'store' => false, 'max_output_tokens' => 1800,
                ]);
                if (!$response->successful()) {
                    Log::warning('Public chat API failed', ['status' => $response->status(), 'code' => $response->json('error.code')]);
                    throw new \RuntimeException('upstream_error');
                }
                $output = $response->json('output', []);
                $calls = [];
                foreach ($output as $item) {
                    if ($item['type'] === 'function_call') $calls[] = $item;
                    if ($item['type'] === 'message') foreach ($item['content'] ?? [] as $part) {
                        if (($part['type'] ?? '') === 'output_text') $text .= $part['text'];
                    }
                }
                if (!$calls) break;
                $input = array_merge($input, $output);
                foreach ($calls as $call) {
                    $args = json_decode($call['arguments'], true, 512, JSON_THROW_ON_ERROR);
                    $result = ['error' => 'Ferramenta indisponível'];
                    if ($call['name'] === 'get_products') {
                        $result = app(ChatController::class)->apiSearch($assistant, substr((string) ($args['symbol'] ?? ''), 0, 300));
                    } elseif ($call['name'] === 'send_email' && !$emailSent) {
                        Notification::route('mail', config('mail.commercial_address'))->notify(new ChatContact((string) ($args['data'] ?? '')));
                        $emailSent = true;
                        $result = ['sent' => true];
                    } elseif ($call['name'] === 'send_email') {
                        $result = ['sent' => true, 'already_sent' => true];
                    }
                    $input[] = ['type' => 'function_call_output', 'call_id' => $call['call_id'], 'output' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)];
                }
                $text = '';
            }
            if (trim($text) === '') throw new \RuntimeException('empty_response');
            $log = isset($state['log_id']) ? ChatLog::find($state['log_id']) : null;
            if (!$log) { $log = new ChatLog; $log->project = $bot->project->name; $log->save(); }
            foreach ([['role' => 'user', 'message' => $data['message']], ['role' => 'chat', 'message' => $text]] as $message) {
                LogMessage::create(['log_id' => $log->id] + $message);
                $state['messages'][] = $message;
            }
            $state['messages'] = array_slice($state['messages'], -60);
            $state['log_id'] = $log->id;
            $request->session()->put("public_chat.$assistant", $state);
            return response()->json(['message' => $text]);
        } catch (\Throwable $e) {
            Log::warning('Public chat unavailable', ['exception' => get_class($e)]);
            return response()->json(['message' => 'Não foi possível obter resposta neste momento. Tente novamente dentro de instantes.'], 502);
        }
    }
}
