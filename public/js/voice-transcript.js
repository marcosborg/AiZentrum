class VoiceTranscript {
    constructor() { this.items = new Map(); }
    clear() { this.items.clear(); }
    ensure(id) {
        if (!this.items.has(id)) this.items.set(id, { id, text: '', who: '', previous: undefined });
        return this.items.get(id);
    }
    event(e) {
        const id = e.item_id || e.item?.id;
        if (!id) return;
        const item = this.ensure(id);
        if (Object.hasOwn(e, 'previous_item_id')) item.previous = e.previous_item_id;
        if (e.item?.role) item.who = e.item.role === 'user' ? 'Você' : 'Assistente';
        if (e.type === 'input_audio_buffer.speech_started') item.who = 'Você';
        if (e.type === 'conversation.item.input_audio_transcription.completed') {
            item.who = 'Você'; item.text = e.transcript || '';
        }
        if (e.type === 'conversation.item.input_audio_transcription.failed') {
            item.who = 'Você'; item.text = '[Não foi possível transcrever este trecho.]';
        }
        if (e.type === 'response.output_audio_transcript.delta') {
            item.who = 'Assistente'; item.text += e.delta || '';
        }
        if (e.type === 'response.output_audio_transcript.done') {
            item.who = 'Assistente'; item.text = e.transcript || '';
        }
    }
    visible() {
        const ordered = [], visited = new Set();
        const visit = item => {
            if (visited.has(item.id)) return;
            visited.add(item.id);
            if (this.items.has(item.previous)) visit(this.items.get(item.previous));
            ordered.push(item);
        };
        this.items.forEach(visit);
        // Keep empty items in the chain for ordering, but never display empty bubbles.
        return ordered.filter(item => item.who && item.text.trim());
    }
}
if (typeof module !== 'undefined') module.exports = VoiceTranscript;
