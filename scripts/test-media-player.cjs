// Dependency-free protocol tests. Real camera/playback still needs browser smoke tests.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');
const source = readFileSync(join(__dirname, '../mobile/web/media_player.js'), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));

function harness() {
  const listeners = {};
  const created = [];
  function element(tag) {
    const node = { tag, children: [], textContent: '', paused: true,
      append(child) { this.children.push(child); },
      replaceChildren() { this.children = []; },
      remove() {}, removeAttribute() {}, load() {},
      play() { this.paused = false; return Promise.resolve(); },
      pause() { this.paused = true; }, canPlayType() { return ''; },
      get childElementCount() { return this.children.length; },
    };
    created.push(node);
    return node;
  }
  const nodes = Object.fromEntries(['media', 'notice', 'actions'].map(id => [id, element(id)]));
  const parent = { messages: [], postMessage(data) { this.messages.push(data); } };
  const window = { addEventListener(name, fn) { listeners[name] = fn; } };
  const document = { hidden: false, getElementById(id) { return nodes[id]; },
    createElement: element, addEventListener() {},
    head: { append(script) { queueMicrotask(() => script.onload()); } },
  };
  const location = { origin: 'https://shop.example', href: 'https://shop.example/media_player.html' };
  vm.runInNewContext(source, { window, document, location, parent, URL });
  return { nodes, window, created, parent,
    send(data, origin = location.origin, sender = parent) {
      listeners.message({ data, origin, source: sender });
    },
  };
}

test('only the same-origin parent can configure the player', async () => {
  const h = harness();
  assert.equal(h.parent.messages[0].type, 'lc-media-ready');
  h.send({ type: 'init', mode: 'video', url: 'https://cdn.example/a.mp4' }, 'https://evil.example');
  h.send({ type: 'init', mode: 'video', url: 'https://cdn.example/a.mp4' }, undefined, {});
  await tick();
  assert.equal(h.nodes.media.children.length, 0);
});

test('video playback follows active state and dispose stops the player', async () => {
  const h = harness();
  h.send({ type: 'init', mode: 'video', url: 'https://cdn.example/a.mp4', loop: true });
  await tick();
  const video = h.nodes.media.children[0];
  assert.equal(video.src, 'https://cdn.example/a.mp4');
  assert.equal(video.paused, false);
  assert.equal(video.muted, true);
  h.send({ type: 'playback', active: false, muted: false });
  assert.equal(video.paused, true);
  assert.equal(video.muted, false);
  h.send({ type: 'dispose' });
  await tick();
  assert.equal(h.nodes.media.children.length, 0);
  assert.equal(video.paused, true);
});

test('HLS uses its actual URL and releases the SDK instance on disposal', async () => {
  const h = harness();
  let instance;
  h.window.Hls = class {
    static Events = { ERROR: 'error' };
    static ErrorTypes = { MEDIA_ERROR: 'media' };
    static isSupported() { return true; }
    constructor() { instance = this; }
    on() {} loadSource(url) { this.url = url; }
    attachMedia(video) { this.video = video; }
    destroy() { this.destroyed = true; }
  };
  h.send({ type: 'init', mode: 'video', url: 'https://cdn.example/master.m3u8' });
  await tick();
  assert.equal(instance.url, 'https://cdn.example/master.m3u8');
  assert.ok(instance.video);
  h.send({ type: 'dispose' });
  await tick();
  assert.equal(instance.destroyed, true);
});

test('missing live credentials show an error without pretending to connect', async () => {
  const h = harness();
  h.send({ type: 'init', mode: 'agora', isHost: true });
  await tick();
  assert.match(h.nodes.notice.textContent, /не настроен/);
  assert.equal(h.nodes.actions.children.length, 0);
  assert.equal(h.created.filter(node => node.tag === 'script').length, 0);
});

test('leaving while the live role is pending prevents a late channel join', async () => {
  const h = harness();
  let resolveRole;
  let joins = 0;
  const role = new Promise(resolve => { resolveRole = resolve; });
  h.window.isSecureContext = true;
  h.window.AgoraRTC = {
    setLogLevel() {},
    createClient() {
      return {
        setClientRole: () => role,
        on() {}, removeAllListeners() {},
        async leave() {},
        async join() { joins++; },
      };
    },
  };
  h.send({ type: 'init', mode: 'agora', appId: 'app', channel: 'room', token: 'token' });
  await tick();
  const connecting = h.nodes.actions.children[0].onclick();
  await tick();
  h.send({ type: 'dispose' });
  resolveRole();
  await connecting;
  assert.equal(joins, 0);
  assert.equal(h.nodes.media.children.length, 0);
  assert.equal(h.nodes.actions.children.length, 0);
  assert.equal(h.nodes.notice.textContent, '');
});

test('live status polling renews token without disconnecting or restarting camera', async () => {
  const h = harness();
  let joins = 0, leaves = 0;
  const renewed = [];
  h.window.isSecureContext = true;
  h.window.AgoraRTC = {
    setLogLevel() {},
    createClient: () => ({
      async setClientRole() {}, on() {}, removeAllListeners() {},
      async join() { joins++; }, async leave() { leaves++; },
      async renewToken(token) { renewed.push(token); },
    }),
  };
  const config = { type: 'init', mode: 'agora', appId: 'app', channel: 'room', token: 'old', isHost: false };
  h.send(config);
  await tick();
  await h.nodes.actions.children[0].onclick();
  h.send({ ...config, token: 'new' });
  await tick();
  assert.equal(joins, 1);
  assert.equal(leaves, 0);
  assert.deepEqual(renewed, ['new']);
  h.send({ ...config, channel: 'another' });
  await tick();
  assert.equal(leaves, 1);
});
