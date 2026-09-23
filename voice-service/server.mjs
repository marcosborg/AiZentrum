import net from 'node:net';
import { performance } from 'node:perf_hooks';
import http from 'node:http';
import WebSocket from 'ws';
import codecs from 'alawmulaw';
import { readFileSync, existsSync } from 'node:fs';

function instructions() {
  const saved = process.env.VOICE_INSTRUCTIONS_PATH || new URL('../storage/app/voice/instructions.txt', import.meta.url);
  const fallback = new URL('../resources/voice/complaints.txt', import.meta.url);
  const context = process.env.VOICE_ENV === 'production'
    ? '\nCONTEXTO DE PRODUÇÃO: Identifica-te como assistente de inteligência artificial, sem dizer que é um teste. Não existem ferramentas para guardar ou enviar reclamações ou transferir chamadas; nunca afirmes ter executado essas ações.'
    : '\nCONTEXTO: Esta é uma chamada de teste. Identifica-te como IA em teste. Não existem ferramentas para guardar ou enviar reclamações ou transferir chamadas; nunca afirmes ter executado essas ações.';
  return readFileSync(existsSync(saved) ? saved : fallback, 'utf8') + context;
}

const mode = process.env.VOICE_MODE || 'realtime';
const model = process.env.OPENAI_REALTIME_MODEL || 'gpt-realtime';
const key = process.env.OPENAI_API_KEY;
const audioPort = Number(process.env.VOICE_AUDIO_PORT || 9019);
const healthPort = Number(process.env.VOICE_HEALTH_PORT || 9020);
if (!['echo', 'realtime'].includes(mode)) throw new Error('Invalid VOICE_MODE');
if (mode === 'realtime' && !key) throw new Error('OPENAI_API_KEY is required');
if (!Number.isInteger(audioPort) || audioPort < 1 || audioPort > 65535) throw new Error('Invalid VOICE_AUDIO_PORT');
if (!Number.isInteger(healthPort) || healthPort < 1 || healthPort > 65535) throw new Error('Invalid VOICE_HEALTH_PORT');
let active = 0;
let completed = 0;
let lastError = null;
const stats = { receivedBytes: 0, sentBytes: 0, mutedInputBytes: 0, sessionsReady: 0, speechStarts: 0, responsesCreated: 0, responsesCancelled: 0, rejectedBusy: 0 };
const packet = (type, data) => {
  const header = Buffer.alloc(3);
  header[0] = type;
  header.writeUInt16BE(data.length, 1);
  return Buffer.concat([header, data]);
};

net.createServer(socket => {
  const rejectedBusy = active >= 2;
  socket.on('error', () => {
    if (!rejectedBusy) lastError = 'socket_error';
  });
  if (rejectedBusy) {
    stats.rejectedBusy++;
    socket.end(packet(0, Buffer.alloc(0)));
    return;
  }
  active++;
  socket.setNoDelay(true);
  socket.setTimeout(30000, () => socket.destroy());
  let buffer = Buffer.alloc(0), output = Buffer.alloc(0), pending = [];
  let ws, ready = false, identified = false, itemId, played = 0;
  let closed = false;
  let assistantSpeaking = true, responseDone = false, listenAfter = 0;
  const send = event => { if (ws?.readyState === WebSocket.OPEN) ws.send(JSON.stringify(event)); };
  const fail = code => { lastError = code; console.error(code); socket.destroy(); };
  const lifetime = setTimeout(() => socket.destroy(), 600000);
  const startup = setTimeout(() => { if (!identified || (mode === 'realtime' && !ready)) fail('session_timeout'); }, 15000);
  // Pace by elapsed time: Windows timers can wake later than requested.
  let nextFrameAt = null;
  const playback = setInterval(() => {
    if (assistantSpeaking && responseDone && !output.length) {
      if (!listenAfter) listenAfter = performance.now() + 350;
      if (performance.now() >= listenAfter) {
        assistantSpeaking = false;
        responseDone = false;
        listenAfter = 0;
      }
    }
    if (!output.length || socket.destroyed) { nextFrameAt = null; return; }
    if (socket.writableLength > 32000) return fail('audio_backpressure');
    const now = performance.now();
    if (nextFrameAt === null) nextFrameAt = now;
    // Limit catch-up after a long scheduling stall.
    if (now - nextFrameAt > 100) nextFrameAt = now;
    while (output.length && now >= nextFrameAt) {
      const pcm = output.subarray(0, 320);
      output = output.subarray(pcm.length);
      socket.write(packet(0x10, pcm));
      stats.sentBytes += pcm.length;
      played += pcm.length / 16;
      nextFrameAt += pcm.length / 16;
    }
  }, 5);
  const append = pcm => {
    if (output.length + pcm.length > 960000) return fail('audio_queue_overflow');
    output = Buffer.concat([output, pcm]);
  };
  const connect = () => {
    ws = new WebSocket(`wss://api.openai.com/v1/realtime?model=${encodeURIComponent(model)}`, {
      headers: { Authorization: `Bearer ${key}` }, handshakeTimeout: 10000,
    });
    ws.on('open', () => send({ type: 'session.update', session: {
      type: 'realtime', output_modalities: ['audio'],
      instructions: instructions(),
      audio: {
        input: { format: { type: 'audio/pcmu' }, noise_reduction: { type: 'near_field' }, turn_detection: { type: 'server_vad', threshold: 0.75, prefix_padding_ms: 300, silence_duration_ms: 1200, create_response: true, interrupt_response: false } },
        output: { format: { type: 'audio/pcmu' }, voice: 'cedar' },
      },
    } }));
    ws.on('message', raw => {
      let event;
      try { event = JSON.parse(raw.toString()); } catch { return fail('invalid_api_event'); }
      if (event.type === 'error') return fail(`openai_${event.error?.code || 'error'}`);
      if (event.type === 'session.updated' && !ready) {
        ready = true; stats.sessionsReady++;
        clearTimeout(startup);
        // Listen after the opening, avoiding interruption by greeting echo.
        pending = [];
        send({ type: 'response.create' });
      }
      if (event.type === 'response.created') {
        stats.responsesCreated++;
        assistantSpeaking = true;
        responseDone = false;
        listenAfter = 0;
      }
      if (event.type === 'response.done') {
        responseDone = true;
        if (event.response?.status === 'cancelled') stats.responsesCancelled++;
      }
      if (event.type === 'response.output_audio.delta') {
        if (event.item_id !== itemId) { itemId = event.item_id; played = 0; }
        const samples = codecs.mulaw.decode(Buffer.from(event.delta, 'base64'));
        const pcm = Buffer.alloc(samples.length * 2);
        samples.forEach((sample, i) => pcm.writeInt16LE(sample, i * 2));
        append(pcm);
      }
      if (event.type === 'input_audio_buffer.speech_started') {
        stats.speechStarts++;
      }
      if (event.type === 'response.done' && event.response?.status === 'failed') fail('response_failed');
    });
    ws.on('error', () => fail('openai_connection_error'));
    ws.on('close', () => { if (!closed) socket.destroy(); });
  };
  socket.on('data', chunk => {
    buffer = Buffer.concat([buffer, chunk]);
    while (buffer.length >= 3) {
      const type = buffer[0], length = buffer.readUInt16BE(1);
      if (buffer.length < length + 3) break;
      const payload = buffer.subarray(3, length + 3);
      buffer = buffer.subarray(length + 3);
      if (type === 0) return socket.end();
      if (!identified) {
        if (type !== 1 || length !== 16) return fail('invalid_uuid');
        identified = true;
        if (mode === 'realtime') connect(); else clearTimeout(startup);
        continue;
      }
      if (type === 3) continue;
      if (type !== 0x10 || length % 2) return fail('invalid_audio');
      stats.receivedBytes += length;
      if (mode === 'echo') { append(payload); continue; }
      // Do not send caller audio while the assistant is producing or playing a response.
      if (assistantSpeaking) { stats.mutedInputBytes += length; continue; }
      const samples = new Int16Array(length / 2);
      for (let i = 0; i < samples.length; i++) samples[i] = payload.readInt16LE(i * 2);
      const audio = Buffer.from(codecs.mulaw.encode(samples)).toString('base64');
      if (ready) {
        if (ws.bufferedAmount > 64000) return fail('api_backpressure');
        send({ type: 'input_audio_buffer.append', audio });
      } else {
        if (pending.length >= 500) return fail('startup_audio_overflow');
        pending.push(audio);
      }
    }
  });
  socket.on('close', () => {
    closed = true; active--; completed++;
    clearInterval(playback); clearTimeout(lifetime); clearTimeout(startup);
    if (ws) ws.terminate();
  });
}).listen(audioPort, '127.0.0.1', () => console.log(`AudioSocket listening on 127.0.0.1:${audioPort} (${mode})`));

http.createServer((req, res) => {
  if (req.url !== '/health') { res.writeHead(404); return res.end(); }
  res.setHeader('Content-Type', 'application/json');
  res.end(JSON.stringify({ mode, model, active, completed, lastError, ...stats }));
}).listen(healthPort, '127.0.0.1');
