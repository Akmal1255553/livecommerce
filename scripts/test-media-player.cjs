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
      remove() { for (const parent of created) parent.children = parent.children.filter(child => child !== this); },
      removeAttribute() {}, load() {},
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
  h.send({ type: 'init', mode: 'agora', appId: 'app', channel: 'room', token: 'token', isHost: true });
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
  h.send({ ...config, token: 'new' });
  await tick();
  assert.equal(joins, 1);
  assert.equal(leaves, 0);
  assert.deepEqual(renewed, ['new']);
  h.send({ ...config, channel: 'another' });
  await tick();
  assert.equal(leaves, 1);
});

test('a viewer automatically receives an existing host video and audio without capture permission', async () => {
  const h = harness();
  const handlers = {}, subscribed = [];
  let joined = 0, audioPlayed = 0, videoPlayed = 0;
  const remote = { uid: 42, hasVideo: true, hasAudio: true,
    audioTrack: { play() { audioPlayed++; } },
    videoTrack: { play(container) { assert.equal(container, h.nodes.media); videoPlayed++; } },
  };
  h.window.isSecureContext = true;
  h.window.AgoraRTC = {
    setLogLevel() {},
    createMicrophoneAndCameraTracks() { assert.fail('Viewers must not capture camera/microphone'); },
    createClient: () => ({
      remoteUsers: [remote],
      async setClientRole(role) { assert.equal(role, 'audience'); },
      on(name, fn) { handlers[name] = fn; }, removeAllListeners() {}, async leave() {},
      async join() {
        joined++;
        // Agora may emit both events during join and include them in remoteUsers.
        void handlers['user-published'](remote, 'video');
        void handlers['user-published'](remote, 'audio');
      },
      async subscribe(user, kind) { assert.equal(user, remote); subscribed.push(kind); },
    }),
  };
  h.send({ type: 'init', mode: 'agora', appId: 'app', channel: 'room', token: 'token', isHost: false });
  await tick();
  assert.equal(joined, 1);
  assert.deepEqual(subscribed.sort(), ['audio', 'video']);
  assert.equal(audioPlayed, 1);
  assert.equal(videoPlayed, 1);
  assert.equal(h.nodes.notice.textContent, '');
  h.window.AgoraRTC.onAutoplayFailed();
  h.window.AgoraRTC.onAutoplayFailed();
  const sound = h.nodes.actions.children.filter(node => node.textContent === 'Включить звук');
  assert.equal(sound.length, 1);
  await sound[0].onclick();
  assert.equal(audioPlayed, 2);
  assert.equal(videoPlayed, 2);
  await handlers['user-unpublished'](remote, 'video');
  await handlers['user-published'](remote, 'video');
  assert.equal(videoPlayed, 3);
  h.send({ type: 'dispose' });
  await tick();
  h.window.AgoraRTC.onAutoplayFailed();
  assert.equal(h.nodes.actions.children.length, 0);
});

test('host previews capture while signaling is pending and leaving releases both devices', async () => {
  const h = harness();
  let finishJoin, published = 0, previewed = 0, closed = 0;
  const joining = new Promise(resolve => { finishJoin = resolve; });
  const localTracks = [
    { stop() {}, close() { closed++; } },
    { play() { previewed++; }, stop() {}, close() { closed++; } },
  ];
  h.window.isSecureContext = true;
  h.window.AgoraRTC = {
    setLogLevel() {},
    async createCameraVideoTrack() { return localTracks[1]; },
    async createMicrophoneAudioTrack() { return localTracks[0]; },
    createClient: () => ({
      async setClientRole() {}, on() {}, removeAllListeners() {},
      join: () => joining, async leave() {},
      async publish() { published++; },
    }),
  };
  h.send({ type: 'init', mode: 'agora', appId: 'app', channel: 'room', token: 'token', isHost: true });
  await tick();
  const connecting = h.nodes.actions.children[0].onclick();
  await tick();
  await h.nodes.actions.children.find(node => node.textContent === 'Включить микрофон и начать эфир').onclick();
  await tick();
  assert.equal(previewed, 1);
  assert.equal(published, 0);
  assert.match(h.nodes.notice.textContent, /Камера и микрофон включены/);
  h.send({ type: 'dispose' });
  await tick();
  assert.equal(closed, 2);
  finishJoin();
  await connecting;
  assert.equal(published, 0);
});

test('denied or occupied camera shows actionable errors and allows retry', async () => {
  for (const [code, message] of [['PERMISSION_DENIED', /ОБА устройства/], ['NOT_READABLE', /заняты/]]) {
    const h = harness();
    let attempts = 0;
    h.window.isSecureContext = true;
    h.window.AgoraRTC = {
      setLogLevel() {},
      async createCameraVideoTrack() { attempts++; throw { code }; },
      createClient: () => ({
        async setClientRole() {}, on() {}, removeAllListeners() {}, async leave() {},
        async join() { assert.fail('Do not join without capture'); },
      }),
    };
    h.send({ type: 'init', mode: 'agora', appId: 'app', channel: 'room', token: 'token', isHost: true });
    await tick();
    await h.nodes.actions.children[0].onclick();
    assert.match(h.nodes.notice.textContent, message);
    assert.equal(h.nodes.actions.children[0].disabled, undefined);
    await h.nodes.actions.children[0].onclick();
    assert.equal(attempts, 2);
  }
});

test('microphone gets its own user action; denial preserves camera and can be retried', async () => {
  const h = harness();
  let audioAttempts = 0, cameraClosed = 0, joined = 0;
  const published = [];
  const camera = { play() {}, stop() {}, close() { cameraClosed++; } };
  const audio = { setEnabled() {}, stop() {}, close() {} };
  h.window.isSecureContext = true;
  h.window.AgoraRTC = {
    setLogLevel() {},
    async createCameraVideoTrack() { return camera; },
    async createMicrophoneAudioTrack() {
      if (++audioAttempts === 1) throw { code: 'PERMISSION_DENIED' };
      return audio;
    },
    createClient: () => ({
      async setClientRole() {}, on() {}, removeAllListeners() {}, async leave() {},
      async join() { joined++; }, async publish(tracks) { published.push(...tracks); },
    }),
  };
  h.send({ type: 'init', mode: 'agora', appId: 'app', channel: 'room', token: 'token', isHost: true });
  await tick();
  const starting = h.nodes.actions.children[0].onclick();
  await tick();
  assert.equal(audioAttempts, 0, 'Microphone capture requires a separate click');
  const micButton = h.nodes.actions.children[0];
  assert.equal(micButton.textContent, 'Включить микрофон и начать эфир');
  await micButton.onclick();
  assert.match(h.nodes.notice.textContent, /Chrome запретил микрофон/);
  assert.equal(cameraClosed, 0);
  assert.equal(joined, 0);
  assert.equal(micButton.disabled, false);
  await micButton.onclick();
  await starting;
  assert.deepEqual(published, [audio, camera]);
  assert.equal(joined, 1);
  h.send({ type: 'dispose' });
  await tick();
  assert.equal(cameraClosed, 1);
});

test('leaving while waiting for microphone closes preview without joining', async () => {
  const h = harness();
  let closed = 0;
  h.window.isSecureContext = true;
  h.window.AgoraRTC = {
    setLogLevel() {},
    async createCameraVideoTrack() { return { play() {}, stop() {}, close() { closed++; } }; },
    createClient: () => ({
      async setClientRole() {}, on() {}, removeAllListeners() {}, async leave() {},
      async join() { assert.fail('Must not join after leaving'); },
    }),
  };
  h.send({ type: 'init', mode: 'agora', appId: 'app', channel: 'room', token: 'token', isHost: true });
  await tick();
  const starting = h.nodes.actions.children[0].onclick();
  await tick();
  h.send({ type: 'dispose' });
  await starting;
  assert.equal(closed, 1);
  assert.equal(h.nodes.actions.children.length, 0);
});
