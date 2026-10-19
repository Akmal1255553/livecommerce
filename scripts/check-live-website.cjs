// Exercise the deployed Flutter UI, not just the media frame in isolation.
// Uses temporary credentials and Chrome's synthetic camera and microphone.
const { chromium } = require('playwright');
const { randomBytes } = require('node:crypto');
const { mkdir, writeFile } = require('node:fs/promises');
const apiBase = 'https://livecommerce-api.onrender.com/api/v1';
const site = 'https://livecommerce-web.onrender.com';
async function api(path, method = 'GET', body, token) {
  const response = await fetch(apiBase + path, {
    method, signal: AbortSignal.timeout(120000),
    headers: { Accept: 'application/json', 'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  if (!response.ok) throw Error(`${method} ${path}: HTTP ${response.status}`);
  return (await response.json()).data;
}
(async () => {
  let auth, live, browser, page;
  let loggedIn = false;
  const diagnostics = { errors: [], frames: [] };
  await mkdir('live-website-diagnostics', { recursive: true });
  try {
    const name = 'livemediacheck_' + randomBytes(5).toString('hex');
    const password = randomBytes(24).toString('base64url');
    const email = `${name}@example.invalid`;
    auth = await api('/auth/register', 'POST', { username: name, email, password, password_confirmation: password });
    await api('/seller/apply', 'POST', { store_name: 'Temporary website media verification' }, auth.access_token);
    live = await api('/live/start', 'POST', { title: 'Temporary synthetic media verification' }, auth.access_token);
    browser = await chromium.launch({ executablePath: '/usr/bin/google-chrome',
      args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream', '--disable-dev-shm-usage'] });
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    page = await context.newPage();
    page.on('pageerror', error => diagnostics.errors.push(error.name));
    await page.goto(site, { waitUntil: 'domcontentloaded' });
    await page.locator('flt-glass-pane').waitFor({ timeout: 90000 });
    await page.locator('flt-semantics-placeholder').evaluate(node => node.click());
    const inputs = page.locator('input');
    await inputs.first().fill(email, { timeout: 30000 });
    await inputs.nth(1).fill(password);
    await page.getByRole('button', { name: /^(Войти|Kirish)$/ }).click();
    await page.waitForURL(/home/, { timeout: 60000 });
    loggedIn = true;
    // Hash navigation preserves the authenticated Flutter session.
    await page.evaluate(id => { location.hash = `/live/${id}?host=1`; }, live.id);
    const frame = page.frameLocator('iframe');
    const start = frame.getByRole('button', { name: 'Включить камеру и начать' });
    await start.waitFor({ timeout: 60000 });
    await start.click();
    await frame.locator('video').waitFor({ timeout: 60000 });
    await frame.locator('video').evaluate(async video => {
      const deadline = Date.now() + 20000;
      while (!(video.videoWidth > 0 && video.currentTime > 0)) {
        if (Date.now() > deadline) throw Error('Host preview has no decoded video');
        await new Promise(resolve => setTimeout(resolve, 250));
      }
    });
    await frame.getByRole('button', { name: 'Выключить микрофон' }).waitFor({ timeout: 30000 });
    diagnostics.passed = true;
    console.log('Actual Flutter host UI: camera preview and microphone publishing passed');
  } finally {
    if (page && loggedIn) {
      for (const frame of page.frames()) {
        diagnostics.frames.push(await frame.evaluate(() => ({
          mediaFrame: !!document.getElementById('media'),
          notice: document.getElementById('notice')?.textContent,
          actions: document.getElementById('actions')?.textContent,
          videos: Array.from(document.querySelectorAll('video')).map(v => ({ width: v.videoWidth, playing: !v.paused })),
        })).catch(() => ({ inaccessible: true })));
      }
      await page.screenshot({ path: 'live-website-diagnostics/host.png' }).catch(() => {});
    }
    await writeFile('live-website-diagnostics/result.json', JSON.stringify(diagnostics, null, 2));
    await browser?.close();
    if (live && auth) await api(`/live/${live.id}/end`, 'POST', {}, auth.access_token);
    if (auth) await api('/me', 'DELETE', undefined, auth.access_token);
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
