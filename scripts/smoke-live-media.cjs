// Real Agora media smoke test with synthetic camera/microphone on a CI browser.
// Never logs tokens and never uses a real person's camera or microphone.
const { spawn } = require('node:child_process');
const { mkdtemp, readFile, rm } = require('node:fs/promises');
const { join } = require('node:path');
const { tmpdir } = require('node:os');
const { randomBytes } = require('node:crypto');
const assert = require('node:assert/strict');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const apiBase = 'https://livecommerce-api.onrender.com/api/v1';
async function api(path, method = 'GET', body, token) {
  const response = await fetch(apiBase + path, { method,
    signal: AbortSignal.timeout(120000),
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  if (!response.ok) throw Error(`${method} ${path}: HTTP ${response.status}`);
  return response.status === 204 ? null : (await response.json()).data;
}
async function connect(url) {
  const socket = new WebSocket(url);
  await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
  let sequence = 0;
  const pending = new Map();
  socket.addEventListener('message', event => {
    const message = JSON.parse(event.data), item = pending.get(message.id);
    if (!item) return;
    clearTimeout(item.timer); pending.delete(message.id);
    message.error ? item.reject(Error(message.error.message)) : item.resolve(message.result);
  });
  const rpc = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++sequence;
    const timer = setTimeout(() => { pending.delete(id); reject(Error(`Browser timeout: ${method}`)); }, 55000);
    pending.set(id, { resolve, reject, timer }); socket.send(JSON.stringify({ id, method, params }));
  });
  const evaluate = async (expression, userGesture = false) => {
    const result = await rpc('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true, userGesture });
    if (result.exceptionDetails) throw Error('Browser script evaluation failed');
    return result.result.value;
  };
  return { rpc, evaluate, close: () => socket.close() };
}
async function waitFor(page, expression, description) {
  for (let attempt = 0; attempt < 50; attempt++) {
    if (await page.evaluate(expression)) return;
    await delay(1000);
  }
  const state = await page.evaluate(`({connected:window.__rtc?.connectionState, remoteUsers:window.__rtc?.remoteUsers.length, notice:document.getElementById('notice').textContent})`);
  throw Error(`${description}: ${JSON.stringify(state)}`);
}
(async () => {
  let auth, live, browser;
  const pages = [];
  const profile = await mkdtemp(join(tmpdir(), 'livecommerce-media-'));
  try {
    const name = 'livemediacheck_' + randomBytes(5).toString('hex');
    const password = randomBytes(24).toString('base64url');
    auth = await api('/auth/register', 'POST', { username: name, email: `${name}@example.invalid`, password, password_confirmation: password });
    const token = auth.access_token;
    await api('/seller/apply', 'POST', { store_name: `Temporary media verification ${randomBytes(4).toString('hex')}` }, token);
    live = await api('/live/start', 'POST', { title: 'Temporary synthetic media verification' }, token);
    assert.ok(live.publisher_token && live.subscriber_token && live.app_id);
    const source = await readFile(join(__dirname, '../mobile/web/media_player.js'), 'utf8');
    browser = spawn(process.env.CHROME_PATH || '/usr/bin/google-chrome', [
      '--headless=new', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--no-first-run',
      '--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream',
      '--disable-background-timer-throttling', '--disable-renderer-backgrounding',
      '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank',
    ], { stdio: 'ignore', windowsHide: true });
    let port;
    for (let i = 0; i < 60; i++) { try { port = (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; } catch { await delay(500); } }
    if (!port) throw Error('Browser did not start');
    async function page(isHost) {
      const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json();
      const p = await connect(target.webSocketDebuggerUrl); pages.push(p);
      await p.rpc('Network.enable');
      await p.rpc('Network.setBlockedURLs', { urls: ['*media_player.js'] });
      await p.rpc('Page.enable');
      await p.rpc('Page.navigate', { url: 'https://livecommerce-web.onrender.com/media_player.html' });
      for (let i = 0; i < 30 && !await p.evaluate(`!!document.getElementById('media')`); i++) await delay(500);
      await p.evaluate(`(async()=>{
        await new Promise((resolve,reject)=>{const s=document.createElement('script');s.src='https://cdn.jsdelivr.net/npm/agora-rtc-sdk-ng@4.23.4/AgoraRTC_N-production.js';s.onload=resolve;s.onerror=reject;document.head.append(s);});
        const create=AgoraRTC.createClient.bind(AgoraRTC);
        AgoraRTC.createClient=cfg=>(window.__rtc=create(${isHost ? "{...cfg,codec:'h264'}" : 'cfg'}));
      })()`);
      await p.evaluate(source);
      const config = { type: 'init', mode: 'agora', appId: live.app_id, channel: live.channel_id, token: isHost ? live.publisher_token : live.subscriber_token, isHost };
      await p.evaluate(`window.postMessage(${JSON.stringify(config)},location.origin)`);
      return p;
    }
    const host = await page(true);
    await delay(300);
    await host.evaluate(`document.querySelector('#actions button').click()`, true);
    await waitFor(host, `Array.from(document.querySelectorAll('button')).some(b=>b.textContent==='Включить микрофон и начать эфир')`, 'Host microphone action not shown');
    await host.evaluate(`Array.from(document.querySelectorAll('button')).find(b=>b.textContent==='Включить микрофон и начать эфир').click()`, true);
    await waitFor(host, `window.__rtc?.localTracks.length===2`, 'Host did not publish camera and microphone');
    const viewer = await page(false);
    await waitFor(viewer, `window.__rtc?.remoteUsers.some(u=>u.audioTrack&&u.videoTrack)`, 'Viewer did not subscribe automatically');
    await viewer.evaluate(`Array.from(document.querySelectorAll('button')).find(b=>b.textContent==='Включить звук')?.click()`, true);
    await waitFor(viewer, `Array.from(document.querySelectorAll('video')).some(v=>v.videoWidth>0&&v.currentTime>0)`, 'Viewer video did not decode');
    await waitFor(viewer, `Object.values(window.__rtc.getRemoteAudioStats()).some(s=>s.receiveBitrate>0)`, 'Viewer did not receive audio packets');
    console.log(JSON.stringify({ passed: true, hostCodec: 'h264', viewerAutoJoined: true, videoDecoded: true, audioPacketsReceived: true }));
  } finally {
    for (const page of pages) { try { await page.evaluate(`window.postMessage({type:'dispose'},location.origin)`); } catch {} page.close(); }
    browser?.kill();
    if (live && auth) await api(`/live/${live.id}/end`, 'POST', {}, auth.access_token);
    if (auth) await api('/me', 'DELETE', undefined, auth.access_token);
    await rm(profile, { recursive: true, force: true, maxRetries: 3 });
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
