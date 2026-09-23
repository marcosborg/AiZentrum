const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

async function session() {
    const elements = new Map();
    const element = id => {
        if (!elements.has(id)) elements.set(id, { value: '', dataset: {}, addEventListener() {}, replaceChildren() {} });
        return elements.get(id);
    };
    const timers = new Map();
    let timerId = 0;
    const track = { enabled: true, stop() { this.stopped = true; } };
    const stream = { getTracks: () => [track], getAudioTracks: () => [track] };
    const dc = { send() {}, close() {} };
    class Peer {
        addTrack() {}
        createDataChannel() { return dc; }
        async createOffer() { return { sdp: 'test' }; }
        async setLocalDescription() {}
        async setRemoteDescription() {}
        close() {}
    }
    vm.runInNewContext(fs.readFileSync(require.resolve('../public/js/voice-test.js'), 'utf8'), {
        document: { getElementById: element, querySelector: () => ({ content: 'csrf' }) },
        window: { addEventListener() {}, RTCPeerConnection: Peer },
        navigator: { mediaDevices: { getUserMedia: async () => stream } },
        RTCPeerConnection: Peer, AbortController,
        VoiceTranscript: class { clear() {} event() {} visible() { return []; } },
        fetch: async () => ({ ok: true, text: async () => 'answer' }),
        setTimeout: (fn, ms) => { timers.set(++timerId, { fn, ms }); return timerId; },
        clearTimeout: id => timers.delete(id), setInterval() {}, clearInterval() {},
    });
    await element('voice-start').onclick();
    dc.onopen();
    return {
        track, elements, send: event => dc.onmessage({ data: JSON.stringify(event) }),
        tick: () => { for (const [id, timer] of timers) if (timer.ms === 350) { timers.delete(id); timer.fn(); } },
        stop: () => element('voice-stop').onclick(),
    };
}

test('microphone stays muted through generation and playback, then opens after the guard delay', async () => {
    const s = await session();
    assert.equal(s.track.enabled, false);
    s.send({ type: 'response.created', response: { id: 'r1' } });
    s.send({ type: 'output_audio_buffer.started', response_id: 'r1' });
    s.send({ type: 'response.done', response: { id: 'r1', status: 'completed', output: [{ content: [{ type: 'audio' }] }] } });
    s.tick();
    assert.equal(s.track.enabled, false);
    s.send({ type: 'output_audio_buffer.stopped', response_id: 'r1' });
    assert.equal(s.track.enabled, false);
    s.tick();
    assert.equal(s.track.enabled, true);
    assert.equal(s.elements.get('voice-status').textContent, 'À escuta · pode falar');
});

test('a new response cancels pending listening and stale playback events cannot reopen it', async () => {
    const s = await session();
    s.send({ type: 'response.created', response: { id: 'r1' } });
    s.send({ type: 'output_audio_buffer.stopped', response_id: 'r1' });
    s.send({ type: 'response.created', response: { id: 'r2' } });
    s.tick();
    s.send({ type: 'output_audio_buffer.stopped', response_id: 'r1' });
    s.tick();
    assert.equal(s.track.enabled, false);
    s.send({ type: 'output_audio_buffer.stopped', response_id: 'r2' });
    s.stop();
    s.tick();
    assert.equal(s.track.enabled, false);
    assert.equal(s.track.stopped, true);
});

test('a response without audio does not leave the microphone permanently muted', async () => {
    const s = await session();
    s.send({ type: 'response.created', response: { id: 'r1' } });
    s.send({ type: 'response.done', response: { id: 'r1', status: 'completed', output: [] } });
    s.tick();
    assert.equal(s.track.enabled, true);
});
