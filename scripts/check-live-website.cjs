// Exercise the deployed Flutter UI, not just the media frame in isolation.
// Uses temporary credentials and Chrome's synthetic camera and microphone.
const { chromium } = require('playwright');
const { randomBytes, createHash } = require('node:crypto');
const { mkdir, writeFile, readFile } = require('node:fs/promises');
const { join } = require('node:path');
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
    const hash = value => createHash('sha256').update(value.replace(/\r\n/g, '\n')).digest('hex');
    const expected = hash(await readFile(join(__dirname, '../mobile/web/media_player.js'), 'utf8'));
    console.log('Waiting for website player deployment');
    let deployed = false;
    for (let attempt = 0; attempt < 42; attempt++) {
      const response = await fetch(`${site}/media_player.js?check=${expected}`, { signal: AbortSignal.timeout(15000) });
      if (response.ok && hash(await response.text()) === expected) { deployed = true; break; }
      await new Promise(resolve => setTimeout(resolve, 5000));
    }
    if (!deployed) throw Error('Expected website player has not deployed yet');
    const name = 'livemediacheck_' + randomBytes(5).toString('hex');
    const password = randomBytes(24).toString('base64url');
    const email = `${name}@example.invalid`;
    auth = await api('/auth/register', 'POST', { username: name, email, password, password_confirmation: password });
    await api('/seller/apply', 'POST', { store_name: 'Temporary website media verification' }, auth.access_token);
    live = await api('/live/start', 'POST', { title: 'Temporary synthetic media verification' }, auth.access_token);
    browser = await chromium.launch({ executablePath: '/usr/bin/google-chrome',
      args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream', '--disable-dev-shm-usage'] });
    const context = await browser.newContext({ viewport: { width: 412, height: 915 },
      isMobile: true, hasTouch: true,
      userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36' });
    page = await context.newPage();
    page.on('pageerror', error => diagnostics.errors.push(error.name));
    await page.goto(site, { waitUntil: 'domcontentloaded' });
    await page.locator('flt-glass-pane').waitFor({ state: 'attached', timeout: 90000 });
    console.log('Flutter application attached');
    await page.waitForTimeout(2000);
    // Flutter creates editable DOM inputs only after the canvas field receives
    // a real tap. Use the fixed mobile viewport's login fields for this fixture.
    await page.touchscreen.tap(120, 108);
    await page.locator('textarea,input').first().fill(email, { timeout: 30000 });
    await page.touchscreen.tap(120, 180);
    await page.locator('input[type="password"]').fill(password);
    await page.touchscreen.tap(206, 258);
    await page.waitForURL(/home/, { timeout: 60000 });
    loggedIn = true;
    console.log('Temporary host logged in');
    // Hash navigation preserves the authenticated Flutter session.
    await page.evaluate(id => { location.hash = `/live/${id}?host=1`; }, live.id);
    const frame = page.frameLocator('iframe');
    const start = frame.getByRole('button', { name: 'Включить камеру', exact: true });
    await start.waitFor({ timeout: 60000 });
    console.log('Host camera start control visible');
    await start.click();
    await frame.locator('#media video').first().waitFor({ timeout: 60000 });
    await frame.locator('#media video').first().evaluate(async video => {
      const deadline = Date.now() + 20000;
      while (!(video.videoWidth > 0 && video.currentTime > 0)) {
        if (Date.now() > deadline) throw Error('Host preview has no decoded video');
        await new Promise(resolve => setTimeout(resolve, 250));
      }
    });
    await frame.getByRole('button', { name: 'Включить микрофон и начать эфир', exact: true }).click();
    await frame.getByRole('button', { name: 'Выключить микрофон' }).waitFor({ timeout: 30000 });
    diagnostics.passed = true;
    console.log('Actual Flutter host UI: camera preview and microphone publishing passed');
  } finally {
    if (page) {
      for (const frame of page.frames()) {
        diagnostics.frames.push(await frame.evaluate(() => ({
          mediaFrame: !!document.getElementById('media'),
          notice: document.getElementById('notice')?.textContent,
          actions: document.getElementById('actions')?.textContent,
          videos: Array.from(document.querySelectorAll('video')).map(v => ({ width: v.videoWidth, playing: !v.paused })),
        })).catch(() => ({ inaccessible: true })));
      }
      await page.screenshot({ path: 'live-website-diagnostics/host.png', mask: loggedIn ? [] : [page.locator('input,textarea')] }).catch(() => {});
    }
    await writeFile('live-website-diagnostics/result.json', JSON.stringify(diagnostics, null, 2));
    await browser?.close();
    if (live && auth) await api(`/live/${live.id}/end`, 'POST', {}, auth.access_token);
    if (auth) await api('/me', 'DELETE', undefined, auth.access_token);
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
