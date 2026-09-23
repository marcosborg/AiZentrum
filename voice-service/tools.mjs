export async function executeVoiceTool(call, sessionId, options = {}) {
  if (call.name === 'ignore_background_audio') return { ignored: true };
  if (call.name !== 'submit_complaint') return { sent: false, status: 'unknown_tool' };
  try {
    const endpoint = options.endpoint ?? process.env.VOICE_SUBMISSION_URL;
    const token = options.token ?? process.env.VOICE_SUBMISSION_TOKEN;
    if (!endpoint || !token) return { sent: false, status: 'unavailable' };
    const response = await (options.fetch ?? fetch)(endpoint, {
      method: 'POST', signal: AbortSignal.timeout(20000),
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}` },
      body: JSON.stringify({ session_id: sessionId, arguments: JSON.parse(call.arguments) }),
    });
    if (!response.ok) return { sent: false, status: 'rejected' };
    return await response.json();
  } catch {
    return { sent: false, status: 'unconfirmed' };
  }
}
