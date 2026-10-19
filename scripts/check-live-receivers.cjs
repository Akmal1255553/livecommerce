// Read-only receiver diagnostics: never captures devices or saves live media.
const { chromium } = require('playwright');
const site = 'https://livecommerce-web.onrender.com';
const api = 'https://livecommerce-api.onrender.com/api/v1';
(async () => {
  const response = await fetch(`${api}/live`, { signal: AbortSignal.timeout(90000) });
  if (!response.ok) throw Error(`Live listing: HTTP ${response.status}`);
  const sessions = (await response.json()).data.filter(s => s.title !== 'Temporary synthetic media verification');
  const browser = await chromium.launch({ executablePath: '/usr/bin/google-chrome', args: ['--disable-dev-shm-usage'] });
  try {
    for (const session of sessions.slice(0, 5)) {
      const page = await browser.newPage();
      try {
        await page.goto(`${site}/media_player.html`, { waitUntil: 'domcontentloaded' });
        await page.evaluate(async () => {
          await new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/agora-rtc-sdk-ng@4.23.4/AgoraRTC_N-production.js';
            script.onload = resolve; script.onerror = reject; document.head.append(script);
          });
          const create = AgoraRTC.createClient.bind(AgoraRTC);
          AgoraRTC.createClient = config => (window.receiver = create(config));
        });
        // Tokens stay inside the browser and are never returned to the log.
        await page.evaluate(async ({ api, id }) => {
          const response = await fetch(`${api}/live/${id}`, { headers: { Accept: 'application/json' } });
          if (!response.ok) throw Error('Cannot fetch viewer credentials');
          const { data } = await response.json();
          window.postMessage({ type: 'init', mode: 'agora', appId: data.app_id,
            channel: data.channel_id, token: data.subscriber_token, isHost: false }, location.origin);
        }, { api, id: session.id });
        await page.waitForTimeout(15000);
        const result = await page.evaluate(() => ({
          connection: window.receiver?.connectionState,
          senders: window.receiver?.remoteUsers.map(user => ({ video: user.hasVideo, audio: user.hasAudio })),
          video: Object.values(window.receiver?.getRemoteVideoStats() || {}).map(s => ({ receiving: s.receiveBitrate > 0, decoding: s.decodeFrameRate > 0 })),
          audio: Object.values(window.receiver?.getRemoteAudioStats() || {}).map(s => ({ receiving: s.receiveBitrate > 0 })),
          notice: document.getElementById('notice').textContent,
        }));
        console.log(JSON.stringify({ session: session.id, ...result }));
      } finally { await page.close(); }
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
