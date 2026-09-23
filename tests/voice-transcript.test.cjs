const { test } = require('node:test');
const assert = require('node:assert/strict');
const Transcript = require('../public/js/voice-transcript.js');
test('late user transcription stays before the reply', () => {
    const t = new Transcript();
    t.event({type:'response.output_audio_transcript.done', item_id:'reply', transcript:'Resposta'});
    t.event({type:'conversation.item.added', item:{id:'reply',role:'assistant'}, previous_item_id:'user'});
    t.event({type:'input_audio_buffer.committed', item_id:'user', previous_item_id:null});
    t.event({type:'conversation.item.input_audio_transcription.completed', item_id:'user', transcript:'Pergunta'});
    assert.deepEqual(t.visible().map(i => i.text), ['Pergunta', 'Resposta']);
});
test('empty speech segments are invisible and retain ordering links', () => {
    const t = new Transcript();
    t.event({type:'input_audio_buffer.speech_started', item_id:'noise'});
    t.event({type:'conversation.item.input_audio_transcription.completed', item_id:'noise', transcript:'   '});
    assert.deepEqual(t.visible(), []);
    assert.equal(t.items.size, 1);
});
test('final transcript replaces deltas without duplicate rows', () => {
    const t = new Transcript();
    t.event({type:'response.output_audio_transcript.delta', item_id:'a', delta:'Olá'});
    t.event({type:'response.output_audio_transcript.done', item_id:'a', transcript:'Olá!'});
    assert.equal(t.visible().length, 1);
    assert.equal(t.visible()[0].text, 'Olá!');
});
