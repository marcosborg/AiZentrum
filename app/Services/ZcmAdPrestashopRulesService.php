<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ZcmAdPrestashopRulesService
{
    private const RULES_FILE = 'regras-anuncios-prestashop-techniczentrum.md';

    private ?string $content = null;

    public function content(): string
    {
        if ($this->content !== null) {
            return $this->content;
        }

        $path = base_path(self::RULES_FILE);

        if (!File::exists($path)) {
            return $this->content = '';
        }

        return $this->content = trim((string) File::get($path));
    }

    public function version(): string
    {
        if (preg_match('/^Versao:\s*(.+)$/mi', $this->content(), $matches)) {
            return trim($matches[1]);
        }

        return 'unknown';
    }

    public function prompt(): string
    {
        $content = $this->content();

        if ($content === '') {
            return 'Segue as regras TechnicZentrum: nao inventar dados, manter referencias intactas e gerar conteudo tecnico para PrestaShop.';
        }

        return Str::limit($content, 12000, "\n\n[regras truncadas]");
    }
}
