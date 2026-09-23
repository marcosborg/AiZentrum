<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class VoiceInstructions
{
    public const PATH = 'voice/instructions.txt';

    public function get(): string
    {
        return Storage::disk('local')->exists(self::PATH)
            ? Storage::disk('local')->get(self::PATH)
            : file_get_contents(resource_path('voice/complaints.txt'));
    }

    public function save(string $instructions): void
    {
        // Do not reset the shared directory's setgid/read permissions on every save.
        if (!Storage::disk('local')->directoryExists('voice')) {
            Storage::disk('local')->makeDirectory('voice');
        }
        // New calls must never read a partially written prompt.
        \Illuminate\Support\Facades\File::replace(Storage::disk('local')->path(self::PATH), $instructions, 0640);
    }

    public function forSession(): string
    {
        return $this->get()."\n\nCONTEXTO DESTE TESTE (prevalece sobre instruções incompatíveis): Esta sessão é uma simulação no painel. Identifica-te como IA em teste na abertura. Não existem ferramentas de registo, consulta ou transferência. Nunca afirmes que guardaste ou enviaste uma reclamação. No fecho, usa a frase definida nas instruções: «Obrigado pelas informações. Será contactado brevemente pelo Departamento de Engenharia e Suporte.» No teste, esta frase ensaia o atendimento e não executa qualquer encaminhamento.";
    }
}
