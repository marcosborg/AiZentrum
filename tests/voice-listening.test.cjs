const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

async function session(submit = async () => ({sent:true})) {
    const elements = new Map();
    const element = id => {
        if (!elements.has(id)) elements.set(id, { value: '', dataset: {}, addEventListener() {}, replaceChildren() {} });
        return elements.get(id);
    };
    const timers = new Map();
    let timerId = 0;
    const track = { enabled: true, stop() { this.stopped = true; } };
    const stream = { getTracks: () => [track], getAudioTracks: () => [track] };
    const sent = [];
    const dc = { readyState:'open', send(value) { sent.push(JSON.parse(value)); }, close() {} };
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
        RTCPeerConnection: Peer, AbortController, AbortSignal,
        VoiceTranscript: class { clear() {} event() {} visible() { return []; } },
        fetch: async (url, options) => JSON.parse(options.body).sdp
            ? {ok:true,text:async()=>'answer',headers:{get:()=> 'session-1'}}
            : {ok:true,json:submit},
        setTimeout: (fn, ms) => { timers.set(++timerId, { fn, ms }); return timerId; },
        clearTimeout: id => timers.delete(id), setInterval() {}, clearInterval() {},
    });
    await element('voice-start').onclick();
    dc.onopen();
    return {
        track, elements, sent, send: event => dc.onmessage({ data: JSON.stringify(event) }),
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

test('background tool resumes listening silently', async () => {
    const s = await session();
    await s.send({type:'response.created',response:{id:'r1'}});
    await s.send({type:'response.done',response:{id:'r1',status:'completed',output:[{type:'function_call',call_id:'n1',name:'ignore_background_audio',arguments:'{}'}]}});
    s.tick();
    assert.equal(s.track.enabled,true);
    assert.equal(s.sent.filter(e=>e.type==='response.create').length,1);
});

test('email tool holds the microphone until completion and returns failure honestly', async () => {
    let finish;
    const s = await session(() => new Promise(resolve => { finish=resolve; }));
    await s.send({type:'response.created',response:{id:'r1'}});
    const completed = s.send({type:'response.done',response:{id:'r1',status:'completed',output:[{type:'function_call',call_id:'e1',name:'submit_complaint',arguments:'{}'}]}});
    await Promise.resolve();
    await s.send({type:'output_audio_buffer.stopped',response_id:'r1'});
    s.tick(); assert.equal(s.track.enabled,false);
    finish({sent:false,status:'needs_review'});
    await completed;
    assert.equal(JSON.parse(s.sent.find(e=>e.type==='conversation.item.create').item.output).sent,false);
    assert.equal(s.sent.filter(e=>e.type==='response.create').length,2);
});
