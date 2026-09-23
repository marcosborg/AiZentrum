import test from 'node:test';
import assert from 'node:assert/strict';
import {executeVoiceTool} from '../tools.mjs';
test('background noise never triggers an email request', async () => {
  assert.deepEqual(await executeVoiceTool({name:'ignore_background_audio'}, 'id', {fetch:()=>{throw Error('must not run');}}), {ignored:true});
});
test('submission includes conversation identity and returns the verified backend result', async () => {
  const result = await executeVoiceTool({name:'submit_complaint',arguments:'{"part":"ABS"}'}, 'session-1', {
    endpoint:'https://example.test',token:'secret',fetch:async (url, options) => {
      assert.equal(options.headers.Authorization, 'Bearer secret');
      assert.equal(JSON.parse(options.body).session_id, 'session-1');
      return {ok:true,json:async()=>({sent:true,reference:'session-1'})};
    },
  });
  assert.equal(result.sent,true);
});
test('timeout or rejected submission never claims successful delivery', async () => {
  for (const fetch of [async()=>{throw Error('timeout');},async()=>({ok:false})]) {
    assert.equal((await executeVoiceTool({name:'submit_complaint',arguments:'{}'}, 'id', {endpoint:'https://example.test',token:'secret',fetch})).sent,false);
  }
});
