import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import net from 'node:net';
import test from 'node:test';

const audioPort = 19019;
const healthPort = 19020;

const waitFor = async (check, timeout = 5000) => {
  const deadline = Date.now() + timeout;
  while (Date.now() < deadline) {
    try {
      const value = await check();
      if (value) return value;
    } catch {}
    await new Promise(resolve => setTimeout(resolve, 50));
  }
  throw new Error('Timed out waiting for condition');
};

const connect = () => new Promise((resolve, reject) => {
  const socket = net.createConnection({ host: '127.0.0.1', port: audioPort });
  socket.once('connect', () => resolve(socket));
  socket.once('error', reject);
});

const health = async () => {
  const response = await fetch(`http://127.0.0.1:${healthPort}/health`);
  assert.equal(response.status, 200);
  return response.json();
};

test('a busy third connection cannot crash active calls', async t => {
  const service = spawn(process.execPath, ['server.mjs'], {
    cwd: new URL('..', import.meta.url),
    env: {
      ...process.env,
      VOICE_MODE: 'echo',
      VOICE_AUDIO_PORT: String(audioPort),
      VOICE_HEALTH_PORT: String(healthPort),
    },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  t.after(() => service.kill());
  await waitFor(async () => (await health()).active === 0);

  const first = await connect();
  const second = await connect();
  t.after(() => first.destroy());
  t.after(() => second.destroy());
  await waitFor(async () => (await health()).active === 2);

  for (let index = 0; index < 20; index++) {
    const rejected = await connect();
    rejected.on('error', () => {});
    rejected.destroy();
  }

  const state = await waitFor(async () => {
    const current = await health();
    return current.rejectedBusy >= 20 ? current : false;
  });
  assert.equal(state.active, 2);
  assert.equal(state.lastError, null);
  assert.equal(service.exitCode, null);
});
