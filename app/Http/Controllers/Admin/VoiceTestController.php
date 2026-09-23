<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoiceTestController extends Controller
{
    public function index()
    {
        \Illuminate\Support\Facades\Gate::authorize('user_management_access');
        return view('admin.voice-test', ['instructions' => app(\App\Services\VoiceInstructions::class)->get()]);
    }

    public function saveInstructions(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('user_management_access');
        $data = $request->validate(['instructions' => ['required', 'string', 'max:20000']]);
        app(\App\Services\VoiceInstructions::class)->save($data['instructions']);
        return response()->json(['message' => 'Instruções guardadas. Serão usadas nas próximas chamadas e testes.']);
    }

    public function session(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('user_management_access');
        $request->validate(['sdp' => ['required', 'string', 'max:65536']]);
        // TrimStrings removes the final CRLF required by the SDP parser.
        $sdp = str_replace(["\r\n", "\r"], "\n", trim($request->input('sdp')));
        $sdp = str_replace("\n", "\r\n", $sdp)."\r\n";
        if (!config('openai.api_key')) {
            return response()->json(['message' => 'A chave OpenAI não está configurada.'], 503);
        }
        try {
            $runtime = json_decode(file_get_contents(resource_path('voice/runtime.json')), true, 512, JSON_THROW_ON_ERROR);
            $response = Http::withToken(config('openai.api_key'))->timeout(25)
                ->attach('sdp', $sdp)
                ->attach('session', json_encode([
                    'type' => 'realtime',
                    'model' => config('openai.realtime_model', 'gpt-realtime'),
                    'output_modalities' => ['audio'],
                    'instructions' => app(\App\Services\VoiceInstructions::class)->forSession(),
                    'tools' => app()->environment('production') ? $runtime['tools'] : [$runtime['tools'][0]],
                    'audio' => [
                        'input' => [
                            'transcription' => ['model' => 'gpt-4o-mini-transcribe', 'language' => 'pt'],
                            ...$runtime['audio']['input'],
                        ],
                        'output' => $runtime['audio']['output'],
                    ],
                ]))->post('https://api.openai.com/v1/realtime/calls');
            if (!$response->successful()) {
                $code = $response->json('error.code');
                Log::warning('Voice session rejected', [
                    'status' => $response->status(),
                    'code' => $code,
                    'param' => $response->json('error.param'),
                    'request_id' => $response->header('x-request-id'),
                ]);
                $message = match (true) {
                    $code === 'invalid_offer' => 'O browser enviou uma proposta de áudio inválida. Atualiza a página e tenta novamente.',
                    $response->status() === 401 => 'A chave OpenAI não foi aceite. Verifica a configuração da API.',
                    $response->status() === 429 => 'A API atingiu um limite de utilização. Verifica os limites e o saldo antes de tentar novamente.',
                    default => 'Não foi possível iniciar a sessão de voz. O erro técnico ficou registado para diagnóstico.',
                };
                return response()->json(['message' => $message], 502);
            }
            $id = (string) \Illuminate\Support\Str::uuid();
            if ($request->hasSession()) $request->session()->put('voice_sessions.'.$id, time());
            return response($response->body(), 200, ['Content-Type' => 'application/sdp', 'Cache-Control' => 'no-store', 'X-Voice-Session' => $id]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['message' => 'A ligação à OpenAI demorou demasiado. Tenta novamente.'], 504);
        }
    }
}
