/* SDKs are loaded only when needed. No credentials are persisted or logged. */
(() => {
  'use strict';
  const media = document.getElementById('media');
  const notice = document.getElementById('notice');
  const actions = document.getElementById('actions');
  const scripts = new Map();
  let config, player, hls, client, tracks = [], generation = 0;
  let playback = { active: true, muted: true };
  let connecting = false;

  function loadScript(url) {
    if (!scripts.has(url)) {
      scripts.set(url, new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = url;
        script.onload = resolve;
        script.onerror = () => { scripts.delete(url); script.remove(); reject(new Error('sdk')); };
        document.head.append(script);
      }));
    }
    return scripts.get(url);
  }

  function button(label, action) {
    const node = document.createElement('button');
    node.textContent = label;
    node.onclick = async () => {
      node.disabled = true;
      try { await action(node); }
      catch (_) { notice.textContent = 'Не удалось выполнить действие. Попробуйте ещё раз.'; }
      finally { node.disabled = false; }
    };
    actions.append(node);
    return node;
  }

  async function cleanup() {
    generation++;
    connecting = false;
    hls?.destroy(); hls = null;
    if (player) { player.pause(); player.removeAttribute('src'); player.load(); player = null; }
    for (const track of tracks) { track.stop(); track.close(); }
    tracks = [];
    const previous = client; client = null;
    media.replaceChildren(); actions.replaceChildren(); notice.textContent = '';
    if (previous) {
      previous.removeAllListeners();
      try { await previous.leave(); } catch (_) { /* Already disconnected. */ }
    }
  }

  function syncPlayback() {
    if (!player) return;
    player.muted = playback.muted;
    if (playback.active && !document.hidden) {
      player.play().catch(() => {
        notice.textContent = 'Нажмите на видео, чтобы начать воспроизведение.';
        // Interactive replay/live frames can recover after autoplay is blocked.
        if (config.controls && !actions.childElementCount) {
          button('Воспроизвести', async () => { await player.play(); actions.replaceChildren(); });
        }
      });
    } else { player.pause(); }
  }

  async function startVideo(current) {
    const url = new URL(current.url, location.href);
    if (!['https:', 'http:', 'blob:'].includes(url.protocol)) throw new Error('url');
    const currentGeneration = generation;
    player = document.createElement('video');
    player.playsInline = true;
    player.controls = !!current.controls;
    player.loop = !!current.loop;
    player.muted = playback.muted;
    if (current.poster) player.poster = current.poster;
    player.onplaying = () => { notice.textContent = ''; };
    player.onerror = () => { notice.textContent = 'Видео недоступно. Обновите страницу или попробуйте позже.'; };
    media.append(player);
    notice.textContent = 'Загрузка видео…';
    if (/\.m3u8$/i.test(url.pathname) && !player.canPlayType('application/vnd.apple.mpegurl')) {
      await loadScript('https://cdn.jsdelivr.net/npm/hls.js@1.6.13/dist/hls.min.js');
      if (currentGeneration !== generation) return;
      if (!window.Hls.isSupported()) throw new Error('unsupported');
      hls = new window.Hls({ maxBufferLength: 15, maxMaxBufferLength: 30 });
      let recoveryAttempts = 0;
      hls.on(window.Hls.Events.ERROR, (_, data) => {
        if (!data.fatal || currentGeneration !== generation) return;
        if (recoveryAttempts++ < 2 && data.type === window.Hls.ErrorTypes.MEDIA_ERROR) {
          hls.recoverMediaError();
        } else {
          notice.textContent = current.live ? 'Эфир пока недоступен. Повторите подключение.' : 'Не удалось загрузить видео.';
          hls.destroy(); hls = null;
          if (current.controls) button('Повторить', () => initialize(current));
        }
      });
      hls.loadSource(url.href);
      hls.attachMedia(player);
    } else { player.src = url.href; }
    syncPlayback();
  }

  async function joinAgora(current, connectButton) {
    if (connecting || client) return;
    connecting = true;
    const currentGeneration = generation;
    let joiningClient, localTracks = [];
    notice.textContent = 'Подключение к эфиру…';
    try {
      if (!window.isSecureContext) throw new Error('secure');
      await loadScript('https://cdn.jsdelivr.net/npm/agora-rtc-sdk-ng@4.23.4/AgoraRTC_N-production.js');
      if (currentGeneration !== generation) return;
      const sdk = window.AgoraRTC;
      sdk.setLogLevel(4);
      joiningClient = sdk.createClient({ mode: 'live', codec: 'vp8' });
      client = joiningClient;
      await joiningClient.setClientRole(current.isHost ? 'host' : 'audience');
      if (currentGeneration !== generation) return;
      joiningClient.on('user-published', async (user, kind) => {
        try {
          await joiningClient.subscribe(user, kind);
          if (currentGeneration !== generation) return;
          if (kind === 'video') { user.videoTrack.play(media); notice.textContent = ''; }
          if (kind === 'audio') user.audioTrack.play();
        } catch (_) { if (currentGeneration === generation) notice.textContent = 'Не удалось получить поток. Переподключитесь.'; }
      });
      joiningClient.on('user-unpublished', (_, kind) => {
        if (kind === 'video') notice.textContent = 'Продавец приостановил видео.';
      });
      joiningClient.on('token-privilege-did-expire', () => {
        notice.textContent = 'Сессия истекла. Откройте эфир заново.';
      });
      await joiningClient.join(current.appId, current.channel, current.token, null);
      if (currentGeneration !== generation) { await joiningClient.leave(); return; }
      if (current.isHost) {
        localTracks = await sdk.createMicrophoneAndCameraTracks({}, { encoderConfig: '480p_1' });
        if (currentGeneration !== generation) {
          localTracks.forEach(track => { track.stop(); track.close(); });
          await joiningClient.leave(); return;
        }
        tracks = localTracks;
        localTracks[1].play(media);
        await joiningClient.publish(localTracks);
      }
      if (currentGeneration !== generation) return;
      connectButton.remove();
      notice.textContent = current.isHost ? '' : 'Ожидаем видео продавца…';
      if (current.isHost) {
        let muted = false;
        button('Выключить микрофон', async node => {
          await localTracks[0].setEnabled(muted);
          muted = !muted;
          node.textContent = muted ? 'Включить микрофон' : 'Выключить микрофон';
        });
      }
      button('Отключить видео', async () => {
        await cleanup();
        if (config === current) setupAgora(current);
      });
    } catch (error) {
      localTracks.forEach(track => { track.stop(); track.close(); });
      if (joiningClient) { joiningClient.removeAllListeners(); try { await joiningClient.leave(); } catch (_) {} }
      if (currentGeneration === generation) {
        tracks = []; client = null;
        const denied = /PERMISSION|NotAllowed|DENIED/i.test(String(error?.code || error?.name));
        notice.textContent = denied
          ? 'Разрешите доступ к камере и микрофону в браузере и повторите подключение.'
          : 'Не удалось подключиться. Проверьте интернет, камеру и настройки эфира.';
      }
    } finally { if (currentGeneration === generation) connecting = false; }
  }

  function setupAgora(current) {
    if (!current.appId || !current.channel || !current.token) {
      notice.textContent = 'Эфир не настроен на сервере. Свяжитесь с администратором.';
      return;
    }
    notice.textContent = current.isHost ? 'Разрешите камеру и микрофон для трансляции.' : 'Подключитесь к эфиру со звуком.';
    button(current.isHost ? 'Включить камеру и начать' : 'Смотреть эфир', node => joinAgora(current, node));
  }

  async function initialize(current) {
    config = current;
    await cleanup();
    if (config !== current) return;
    if (current.mode === 'agora') setupAgora(current);
    else if (current.mode === 'video') {
      try { await startVideo(current); }
      catch (_) { notice.textContent = 'Не удалось загрузить видеоплеер. Проверьте соединение.'; }
    }
  }

  window.addEventListener('message', event => {
    if (event.origin !== location.origin || event.source !== parent) return;
    const data = event.data;
    if (!data || typeof data !== 'object') return;
    if (data.type === 'init') void initialize(data);
    if (data.type === 'playback') { playback = { active: !!data.active, muted: !!data.muted }; syncPlayback(); }
    if (data.type === 'dispose') { config = null; void cleanup(); }
  });
  document.addEventListener('visibilitychange', syncPlayback);
  window.addEventListener('pagehide', () => { config = null; void cleanup(); });
  parent.postMessage({ type: 'lc-media-ready' }, location.origin);
})();
