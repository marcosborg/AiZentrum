@extends('layouts.admin')
@section('content')
<div id="voice-test" data-session-url="{{ route('admin.voice-test.session') }}" style="max-width:1000px;margin:auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1 class="h3 mb-1">Testar atendimento</h1><span class="text-muted">Techniczentrum · Electriczentrum</span></div>
        <span class="badge badge-info p-2">Teste de voz</span>
    </div>
    <div class="card"><div class="card-body">
        <details>
            <summary class="h5" style="cursor:pointer">Instruções do atendimento · Reclamações</summary>
            <p class="text-muted mt-3">Edite o comportamento, as perguntas e as regras do assistente. Em produção, as alterações guardadas aplicam-se às novas chamadas telefónicas e aos testes deste painel. As chamadas em curso mantêm as instruções anteriores.</p>
            <form id="voice-instructions-form" action="{{ route('admin.voice-test.instructions') }}">
                <label for="voice-instructions">Instruções específicas</label>
                <textarea id="voice-instructions" class="form-control" rows="16" maxlength="20000" required aria-describedby="voice-instructions-help">{{ $instructions }}</textarea>
                <p id="voice-instructions-help" class="small text-muted mt-2">Configuração inicial preparada para peças e reparações. Este teste continua sem guardar reclamações nem efetuar transferências.</p>
                <button id="voice-instructions-save" type="submit" class="btn btn-primary">Guardar instruções</button>
                <span id="voice-instructions-status" role="status" aria-live="polite" class="ml-2"></span>
            </form>
        </details>
    </div></div>
    <div class="card"><div class="card-body">
        <h2 class="h5">Converse com o assistente</h2>
        <p>Teste uma reclamação sobre uma peça ou reparação. Use auscultadores para evitar eco.</p>
        <p class="small text-muted">Ao iniciar, o microfone envia áudio à OpenAI para gerar as respostas. A transcrição fica apenas nesta página; nenhuma reclamação é guardada ou enviada.</p>
        <div class="d-flex flex-wrap align-items-center" style="gap:12px">
            <button id="voice-start" class="btn btn-primary"><i class="fas fa-microphone mr-2"></i>Iniciar conversa</button>
            <button id="voice-stop" class="btn btn-outline-danger" disabled>Terminar</button>
            <span id="voice-status" role="status" aria-live="polite">Pronto para começar</span>
            <span id="voice-timer" class="text-muted ml-auto">00:00</span>
        </div>
        <audio id="voice-audio" autoplay controls class="mt-3" style="height:36px;width:100%"></audio>
        <div id="voice-error" class="alert alert-danger mt-3 mb-0" role="alert" hidden></div>
    </div></div>
    <div class="card"><div class="card-body">
        <h2 class="h5">Transcrição da conversa</h2>
        <p id="voice-empty" class="text-muted">As suas palavras e as respostas do assistente aparecem aqui.</p>
        <div id="voice-transcript" role="log" aria-live="polite" style="max-height:480px;overflow:auto"></div>
    </div></div>
</div>
@endsection
@section('scripts')
@parent
<script src="{{ asset('js/voice-transcript.js') }}?v={{ filemtime(public_path('js/voice-transcript.js')) }}" defer></script>
<script src="{{ asset('js/voice-test.js') }}?v={{ filemtime(public_path('js/voice-test.js')) }}" defer></script>
@endsection
