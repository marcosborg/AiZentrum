(() => {
    const root = document.getElementById('voice-test');
    if (!root) return;
    const el = name => document.getElementById('voice-' + name);
    let current = null;
    const instructionsForm = el('instructions-form');
    let savedInstructions = el('instructions').value;
    el('instructions').addEventListener('input', () => {
        el('instructions-status').textContent = el('instructions').value === savedInstructions ? '' : 'Alterações por guardar';
    });
    instructionsForm.onsubmit = async event => {
        event.preventDefault();
        const value = el('instructions').value;
        el('instructions-save').disabled = true;
        el('instructions-status').textContent = 'A guardar…';
        try {
            const response = await fetch(instructionsForm.action, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ instructions: value }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'Não foi possível guardar. Atualiza a página e verifica a sessão.');
            savedInstructions = value;
            el('instructions-status').textContent = el('instructions').value === value ? result.message : 'Instruções guardadas; existem novas alterações por guardar.';
        } catch (error) { el('instructions-status').textContent = error.message; }
        finally { el('instructions-save').disabled = false; }
    };
    const rows = new Map();
    const conversation = new VoiceTranscript();
    function transcript(id, who, text) {
        if (!rows.has(id)) {
            const row = document.createElement('div');
            row.className = 'border rounded p-3 mb-2';
            const label = document.createElement('strong'); label.textContent = who;
            const body = document.createElement('p'); body.className = 'mb-0 mt-1';
            row.append(label, body); el('transcript').append(row); rows.set(id, body);
        }
        const body = rows.get(id);
        body.textContent = text;
        el('transcript').append(body.parentNode);
        el('transcript').scrollTop = el('transcript').scrollHeight;
    }
    function renderTranscript(event) {
        conversation.event(event);
        const visible = conversation.visible();
        const ids = new Set(visible.map(item => item.id));
        for (const [id, body] of rows) {
            if (!ids.has(id)) { body.parentNode.remove(); rows.delete(id); }
        }
        visible.forEach(item => transcript(item.id, item.who, item.text));
        el('empty').hidden = visible.length > 0;
    }
    function stop(message = 'Conversa terminada') {
        const s = current; current = null;
        if (s) {
            s.abort.abort(); clearTimeout(s.timeout); clearTimeout(s.listenTimer); clearInterval(s.timer);
            s.stream?.getTracks().forEach(t => t.stop());
            s.dc?.close(); s.pc?.close();
        }
        el('audio').srcObject = null;
        el('start').disabled = false; el('stop').disabled = true;
        el('status').textContent = message;
    }
    function fail(message) { stop('Não foi possível continuar'); el('error').textContent = message; el('error').hidden = false; }
    function muteInput(s, message) {
        clearTimeout(s.listenTimer);
        s.stream.getAudioTracks().forEach(track => { track.enabled = false; });
        el('status').textContent = message;
    }
    function resumeInput(s) {
        clearTimeout(s.listenTimer);
        s.listenTimer = setTimeout(() => {
            if (current !== s) return;
            s.stream.getAudioTracks().forEach(track => { track.enabled = true; });
            el('status').textContent = 'À escuta · pode falar';
        }, 350);
    }
    el('stop').onclick = () => stop();
    window.addEventListener('pagehide', () => stop());
    el('start').onclick = async () => {
        if (current) return;
        const s = { abort: new AbortController() }; current = s;
        el('start').disabled = true; el('stop').disabled = false;
        el('error').hidden = true; el('status').textContent = 'A pedir acesso ao microfone…';
        rows.clear(); conversation.clear(); el('transcript').replaceChildren(); el('empty').hidden = false; el('timer').textContent = '00:00';
        try {
            if (!navigator.mediaDevices?.getUserMedia || !window.RTCPeerConnection) throw new Error('Este browser não suporta áudio em tempo real. Abre esta página no Chrome ou Edge.');
            const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true }, video: false });
            if (current !== s) { stream.getTracks().forEach(t => t.stop()); return; }
            s.stream = stream;
            muteInput(s, 'A ligar ao assistente…');
            s.timeout = setTimeout(() => { if (current === s) fail('A ligação demorou demasiado. Tenta novamente.'); }, 35000);
            el('status').textContent = 'A ligar ao assistente…';
            const pc = s.pc = new RTCPeerConnection();
            pc.ontrack = event => {
                if (current !== s) return;
                el('audio').srcObject = event.streams[0];
                el('audio').play().catch(() => { el('status').textContent = 'Carrega em reproduzir para ouvir o assistente'; });
            };
            pc.onconnectionstatechange = () => {
                if (current === s && ['failed', 'disconnected'].includes(pc.connectionState)) fail('A ligação foi interrompida. Podes iniciar uma nova conversa.');
            };
            stream.getTracks().forEach(track => { pc.addTrack(track, stream); track.onended = () => { if (current === s) stop('Microfone desligado'); }; });
            const dc = s.dc = pc.createDataChannel('oai-events');
            dc.onopen = () => {
                if (current !== s) return;
                clearTimeout(s.timeout); muteInput(s, 'A preparar resposta · microfone silenciado');
                const started = Date.now();
                s.timer = setInterval(() => {
                    const seconds = Math.floor((Date.now() - started) / 1000);
                    el('timer').textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
                    if (seconds >= 600) stop('Teste terminado após 10 minutos');
                }, 1000);
                dc.send(JSON.stringify({ type: 'response.create' }));
            };
            dc.onclose = () => { if (current === s) stop('Ligação terminada'); };
            dc.onmessage = event => {
                if (current !== s) return;
                let e; try { e = JSON.parse(event.data); } catch { return; }
                if (e.type === 'error') return fail('O serviço de voz devolveu um erro. Tenta iniciar novamente.');
                if (e.type === 'response.created') {
                    s.responseId = e.response.id;
                    muteInput(s, 'A preparar resposta · microfone silenciado');
                }
                if (e.type === 'output_audio_buffer.started') {
                    s.responseId = e.response_id;
                    muteInput(s, 'Atendedor a falar · microfone silenciado');
                }
                // response.done ends generation, not playback. Wait for the audio buffer to drain.
                if (['output_audio_buffer.stopped', 'output_audio_buffer.cleared'].includes(e.type)
                    && e.response_id === s.responseId) resumeInput(s);
                renderTranscript(e);
                if (e.type === 'response.done' && e.response?.status === 'failed') fail('Não foi possível gerar a resposta. Verifica o acesso à API.');
                else if (e.type === 'response.done' && e.response?.id === s.responseId
                    && !(e.response.output || []).some(item => (item.content || []).some(part => ['audio', 'output_audio'].includes(part.type)))) resumeInput(s);
            };
            const offer = await pc.createOffer(); await pc.setLocalDescription(offer);
            if (current !== s) return;
            const response = await fetch(root.dataset.sessionUrl, {
                method: 'POST', signal: s.abort.signal,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ sdp: offer.sdp }),
            });
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(response.status === 419 || response.status === 401 ? 'A sessão expirou. Atualiza a página e entra novamente.' : data.message || 'Não foi possível ligar. Tenta novamente dentro de um minuto.');
            }
            const sdp = await response.text();
            if (current !== s) return;
            await pc.setRemoteDescription({ type: 'answer', sdp });
        } catch (error) {
            if (current !== s) return;
            fail(error.name === 'NotAllowedError' ? 'Permite o acesso ao microfone para conversar.' : error.name === 'NotFoundError' ? 'Não foi encontrado um microfone ligado.' : error.message);
        }
    };
})();
