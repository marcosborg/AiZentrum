(() => {
    const box = document.getElementById('message-textarea');
    const container = document.getElementById('chat-container');
    const endpoint = '/chat/response/' + window.publicChatAssistant;
    const modal = new bootstrap.Modal(document.getElementById('termsModal'), {backdrop: 'static', keyboard: false});
    let busy = true;
    const accepted = () => localStorage.getItem('chat_terms_accepted') === 'true';
    function append(role, text) {
        const row = document.createElement('div');
        row.className = role === 'user' ? 'line-client' : 'line-chat';
        const bubble = document.createElement('div');
        bubble.className = role === 'user' ? 'client' : 'chat';
        const label = document.createElement('small');
        label.textContent = role === 'user' ? 'Eu' : 'Zentrum';
        bubble.append(label, document.createElement('br'));
        const content = document.createElement('div');
        content.className = 'message';
        content.style.whiteSpace = 'pre-wrap';
        // Render links without interpreting model or user HTML.
        const pattern = /https?:\/\/[^\s<>"\])]+/g;
        let offset = 0;
        for (const match of text.matchAll(pattern)) {
            content.append(document.createTextNode(text.slice(offset, match.index)));
            const link = document.createElement('a');
            link.href = match[0]; link.textContent = match[0];
            link.target = '_blank'; link.rel = 'noopener noreferrer';
            content.append(link); offset = match.index + match[0].length;
        }
        content.append(document.createTextNode(text.slice(offset)));
        bubble.append(content); row.append(bubble); container.append(row);
        container.scrollTop = container.scrollHeight;
        return row;
    }
    if (!accepted()) modal.show();
    document.getElementById('acceptTerms').addEventListener('click', () => {
        localStorage.setItem('chat_terms_accepted', 'true'); modal.hide(); box.focus();
    });
    box.disabled = true;
    fetch(endpoint, {headers: {Accept: 'application/json'}}).then(r => {
        if (!r.ok) throw new Error('history');
        return r.json();
    }).then(messages => messages.forEach(m => append(m.role, m.message)))
      .catch(() => append('chat', 'Não foi possível recuperar a conversa anterior.'))
      .finally(() => { busy = false; box.disabled = false; });
    box.addEventListener('keydown', async event => {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
        event.preventDefault();
        if (!accepted()) { modal.show(); return; }
        const message = box.value.trim();
        if (busy || !message) return;
        busy = true; box.disabled = true; box.value = '';
        const userRow = append('user', message);
        const waiting = append('chat', 'A preparar resposta…');
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 175000);
        try {
            const response = await fetch(endpoint, {method: 'POST', signal: controller.signal,
                headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({message})});
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Pedido falhou');
            waiting.remove(); append('chat', result.message);
        } catch (error) {
            waiting.remove(); userRow.remove(); box.value = message;
            append('chat', 'Não foi possível concluir o pedido. A sua mensagem ficou na caixa para tentar novamente. Se a sessão expirou, atualize a página.');
        } finally {
            clearTimeout(timer); busy = false; box.disabled = false; box.focus();
        }
    });
})();
