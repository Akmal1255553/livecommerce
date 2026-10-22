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
  let cancelCapture;

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
    cancelCapture?.(); cancelCapture = null;
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
      if (!window.AgoraRTC) await loadScript('https://cdn.jsdelivr.net/npm/agora-rtc-sdk-ng@4.23.4/AgoraRTC_N-production.js');
      if (currentGeneration !== generation) return;
      const sdk = window.AgoraRTC;
      sdk.setLogLevel(4);
      joiningClient = sdk.createClient({ mode: 'live', codec: 'vp8' });
      client = joiningClient;
      await joiningClient.setClientRole(current.isHost ? 'host' : 'audience');
      if (currentGeneration !== generation) return;
      const subscriptions = new Set();
      let soundButton;
      function enableSound() {
        if (currentGeneration !== generation || soundButton || current.isHost) return;
        soundButton = button('Включить звук', async node => {
          // Replay inside the click handler, before awaiting, to retain the
          // browser's user activation even when RTC joining took several seconds.
          for (const remote of joiningClient.remoteUsers || []) {
            remote.audioTrack?.play();
            remote.videoTrack?.play(media, { fit: 'contain' });
          }
          await sdk.resumeAudioContext?.();
          node.remove(); soundButton = null;
        });
      }
      sdk.onAutoplayFailed = enableSound;
      async function subscribe(user, kind) {
        if (kind !== 'audio' && kind !== 'video') return;
        const key = `${user.uid}:${kind}`;
        if (subscriptions.has(key)) return;
        subscriptions.add(key);
        try {
          await joiningClient.subscribe(user, kind);
          if (currentGeneration !== generation) return;
          if (kind === 'video') { user.videoTrack.play(media, { fit: 'contain' }); notice.textContent = ''; }
          if (kind === 'audio') user.audioTrack.play();
        } catch (_) {
          subscriptions.delete(key);
          if (currentGeneration === generation) notice.textContent = 'Не удалось получить поток. Переподключитесь.';
        }
      }
      joiningClient.on('user-published', subscribe);
      joiningClient.on('user-unpublished', (user, kind) => {
        subscriptions.delete(`${user.uid}:${kind}`);
        if (kind === 'video') notice.textContent = 'Продавец приостановил видео.';
      });
      joiningClient.on('user-left', user => {
        subscriptions.delete(`${user.uid}:audio`);
        subscriptions.delete(`${user.uid}:video`);
      });
      joiningClient.on('token-privilege-did-expire', () => {
        notice.textContent = 'Сессия истекла. Откройте эфир заново.';
      });
      // Open capture before waiting for RTC signaling. A slow connection must
      // not leave the host staring at a blank camera preview.
      if (current.isHost) {
        notice.textContent = 'Разрешите камеру в запросе браузера…';
        const camera = await sdk.createCameraVideoTrack({ encoderConfig: '480p_1' });
        localTracks = [camera];
        if (currentGeneration !== generation) {
          localTracks.forEach(track => { track.stop(); track.close(); });
          await joiningClient.leave(); return;
        }
        tracks = localTracks;
        camera.play(media, { fit: 'contain' });
        connectButton.remove();
        notice.textContent = 'Камера включена. Нажмите «Включить микрофон и начать эфир» и разрешите микрофон.';
        // Android permission dialogs need separate, explicit capture actions.
        // Keep the working camera preview if microphone access is denied.
        const microphone = await new Promise(resolve => {
          cancelCapture = () => resolve(null);
          button('Включить микрофон и начать эфир', async node => {
            notice.textContent = 'Разрешите микрофон в запросе браузера…';
            try {
              const audio = await sdk.createMicrophoneAudioTrack();
              if (currentGeneration !== generation) {
                audio.stop(); audio.close(); resolve(null); return;
              }
              cancelCapture = null;
              node.remove(); resolve(audio);
            } catch (error) {
              if (currentGeneration !== generation) return;
              const code = String(error?.code || error?.name || '');
              notice.textContent = /PERMISSION|NotAllowed|DENIED/i.test(code)
                ? 'Chrome запретил микрофон. Разрешите его в настройках сайта. Если запроса нет: настройки Android → Приложения → Chrome → Разрешения → Микрофон. Затем нажмите кнопку ещё раз.'
                : /NOT_READABLE|NotReadable|TrackStart/i.test(code)
                  ? 'Микрофон занят другим приложением. Закройте его и повторите включение микрофона.'
                  : 'Не удалось включить микрофон. Проверьте его доступность и нажмите кнопку ещё раз.';
            }
          });
        });
        if (!microphone || currentGeneration !== generation) return;
        localTracks = [microphone, camera];
        tracks = localTracks;
        notice.textContent = 'Камера и микрофон включены. Подключение к эфиру…';
      }
      await joiningClient.join(current.appId, current.channel, current.token, null);
      if (currentGeneration !== generation) { await joiningClient.leave(); return; }
      // A broadcaster may already be publishing before this viewer joins.
      // Reconcile the snapshot as well as events, without double subscriptions.
      for (const remote of joiningClient.remoteUsers || []) {
        if (remote.hasVideo) void subscribe(remote, 'video');
        if (remote.hasAudio) void subscribe(remote, 'audio');
      }
      if (current.isHost) {
        await joiningClient.publish(localTracks);
      }
      if (currentGeneration !== generation) return;
      connectButton.remove();
      notice.textContent = current.isHost || (joiningClient.remoteUsers || []).some(u => u.hasVideo)
        ? '' : 'Ожидаем видео продавца…';
      if (!current.isHost) enableSound();
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
        if (config === current) setupAgora(current, false);
      });
    } catch (error) {
      localTracks.forEach(track => { track.stop(); track.close(); });
      if (joiningClient) { joiningClient.removeAllListeners(); try { await joiningClient.leave(); } catch (_) {} }
      if (currentGeneration === generation) {
        tracks = []; client = null;
        actions.replaceChildren();
        button(current.isHost ? 'Включить камеру' : 'Повторить подключение', node => joinAgora(current, node));
        // Never show raw SDK errors: they may contain connection credentials.
        const code = String(error?.code || error?.name || '');
        if (/PERMISSION|NotAllowed|DENIED/i.test(code)) {
          notice.textContent = 'Нет доступа к камере или микрофону. В настройках сайта Chrome разрешите ОБА устройства и повторите подключение.';
        } else if (/NOT_READABLE|NotReadable|TrackStart/i.test(code)) {
          notice.textContent = 'Камера или микрофон заняты. Закройте другие приложения с камерой, затем повторите подключение.';
        } else if (/DEVICE_NOT_FOUND|NotFound|DevicesNotFound/i.test(code)) {
          notice.textContent = 'Камера или микрофон не найдены. Проверьте, доступны ли оба устройства браузеру.';
        } else if (/NOT_SUPPORTED|NotSupported|CONSTRAINT|Overconstrained/i.test(code)) {
          notice.textContent = 'Браузер не смог включить камеру и микрофон. Обновите Chrome и повторите подключение.';
        } else {
          notice.textContent = 'Не удалось подключиться к эфиру. Проверьте интернет и повторите подключение.';
        }
      }
    } finally { if (currentGeneration === generation) connecting = false; }
  }

  function setupAgora(current, autoJoin = true) {
    if (!current.appId || !current.channel || !current.token) {
      notice.textContent = 'Эфир не настроен на сервере. Свяжитесь с администратором.';
      return;
    }
    notice.textContent = current.isHost ? 'Разрешите камеру и микрофон для трансляции.' : 'Подключитесь к эфиру со звуком.';
    const connectButton = button(current.isHost ? 'Включить камеру' : 'Повторить подключение', node => joinAgora(current, node));
    // Viewing needs no microphone/camera permission or initial button press.
    // Only audible playback may require a separate browser user gesture.
    if (!current.isHost && autoJoin) void joinAgora(current, connectButton);
  }

  async function initialize(current) {
    // Status polling refreshes RTC tokens. Keep the established camera/connection.
    if (config?.mode === 'agora' && current.mode === 'agora' &&
        config.appId === current.appId && config.channel === current.channel &&
        config.isHost === current.isHost) {
      const previousToken = config.token;
      Object.assign(config, current);
      if (client && !connecting && previousToken !== current.token) {
        try { await client.renewToken(current.token); }
        catch (_) { notice.textContent = 'Не удалось продлить подключение. Переподключитесь к эфиру.'; }
      }
      return;
    }
    config = current;
    media.dataset.live = String(current.mode === 'agora' || current.live === true);
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
