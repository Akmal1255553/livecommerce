(() => {
  const API_BASE = `${window.location.origin}/api/v1`;
  const STORAGE_KEY = 'lc_admin_session';

  const loginView = document.getElementById('login-view');
  const shellView = document.getElementById('shell-view');
  const loginForm = document.getElementById('login-form');
  const loginError = document.getElementById('login-error');
  const whoami = document.getElementById('whoami');
  const content = document.getElementById('content');
  const flashEl = document.getElementById('flash');
  const tabs = document.getElementById('tabs');

  let session = loadSession();
  let activeTab = 'reports';

  function loadSession() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  }

  function saveSession(next) {
    session = next;
    if (next) {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    } else {
      sessionStorage.removeItem(STORAGE_KEY);
    }
  }

  function isStaff(role) {
    return role === 'admin' || role === 'moderator';
  }

  function isAdmin() {
    return session?.user?.role === 'admin';
  }

  function flash(message, isError = false) {
    flashEl.textContent = message;
    flashEl.classList.remove('hidden');
    flashEl.style.color = isError ? '#fb7185' : '';
    window.setTimeout(() => flashEl.classList.add('hidden'), 4000);
  }

  async function api(path, options = {}) {
    const headers = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    };
    if (session?.accessToken) {
      headers.Authorization = `Bearer ${session.accessToken}`;
    }

    const response = await fetch(`${API_BASE}${path}`, {
      ...options,
      headers,
    });

    let body = null;
    const text = await response.text();
    if (text) {
      try {
        body = JSON.parse(text);
      } catch {
        body = { message: text };
      }
    }

    if (!response.ok) {
      const message =
        body?.message ||
        body?.error ||
        `Request failed (${response.status})`;
      const error = new Error(message);
      error.status = response.status;
      error.body = body;
      throw error;
    }

    return body;
  }

  function showLogin() {
    loginView.classList.remove('hidden');
    shellView.classList.add('hidden');
  }

  function showShell() {
    loginView.classList.add('hidden');
    shellView.classList.remove('hidden');
    whoami.textContent = ` · @${session.user.username} (${session.user.role})`;
    tabs.querySelectorAll('[data-admin-only]').forEach((el) => {
      el.classList.toggle('hidden', !isAdmin());
    });
    setActiveTab(activeTab);
  }

  function setActiveTab(tab) {
    if ((tab === 'users' || tab === 'stores') && !isAdmin()) {
      tab = 'reports';
    }
    activeTab = tab;
    tabs.querySelectorAll('button[data-tab]').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    renderTab();
  }

  async function renderTab() {
    content.innerHTML = '<p class="muted">Loading…</p>';
    try {
      if (activeTab === 'reports') await renderReports();
      else if (activeTab === 'videos') await renderVideos();
      else if (activeTab === 'stores') await renderStores();
      else if (activeTab === 'users') await renderUsers();
    } catch (error) {
      if (error.status === 401 || error.status === 403) {
        saveSession(null);
        showLogin();
        loginError.textContent = error.message;
        return;
      }
      content.innerHTML = `<p class="error">${escapeHtml(error.message)}</p>`;
    }
  }

  async function renderReports() {
    const payload = await api('/admin/reports?status=open&page=1');
    const items = payload.data || [];
    if (!items.length) {
      content.innerHTML = '<div class="empty">No open reports.</div>';
      return;
    }

    content.innerHTML = items
      .map(
        (report) => `
      <article class="card" data-id="${escapeHtml(report.id)}">
        <div class="card-head">
          <strong>${escapeHtml(report.target_type)} · ${escapeHtml(String(report.target_id))}</strong>
          <span class="meta">${escapeHtml(report.status)} · ${escapeHtml(report.created_at || '')}</span>
        </div>
        <p>${escapeHtml(report.reason || '')}</p>
        <p class="meta">Reporter: @${escapeHtml(report.reporter?.username || '—')}</p>
        <div class="actions">
          <button type="button" class="ok" data-action="resolve">Resolve</button>
          <button type="button" class="secondary" data-action="dismiss">Dismiss</button>
        </div>
      </article>`,
      )
      .join('');

    content.querySelectorAll('[data-action]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const card = btn.closest('.card');
        const id = card.dataset.id;
        const action = btn.dataset.action;
        btn.disabled = true;
        try {
          await api(`/admin/reports/${id}/${action}`, {
            method: 'PUT',
            body: JSON.stringify({
              resolution_note: action === 'resolve' ? 'Resolved via web admin' : 'Dismissed via web admin',
            }),
          });
          flash(`Report ${action}d`);
          await renderReports();
        } catch (error) {
          flash(error.message, true);
          btn.disabled = false;
        }
      });
    });
  }

  async function renderVideos() {
    const payload = await api('/admin/videos/pending?page=1');
    const items = payload.data || [];
    if (!items.length) {
      content.innerHTML = '<div class="empty">No pending videos.</div>';
      return;
    }

    content.innerHTML = items
      .map((video) => {
        const title = video.title || video.description || video.id;
        return `
      <article class="card" data-id="${escapeHtml(video.id)}">
        <div class="card-head">
          <strong>${escapeHtml(title)}</strong>
          <span class="meta">${escapeHtml(video.status || '')}</span>
        </div>
        <p class="meta">@${escapeHtml(video.user?.username || '—')} · ${escapeHtml(video.created_at || '')}</p>
        <div class="actions">
          <button type="button" class="ok" data-action="approve">Approve</button>
          <button type="button" class="warn" data-action="reject">Reject</button>
          <button type="button" class="secondary" data-action="hide">Hide</button>
        </div>
      </article>`;
      })
      .join('');

    content.querySelectorAll('[data-action]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = btn.closest('.card').dataset.id;
        const action = btn.dataset.action;
        btn.disabled = true;
        try {
          const body =
            action === 'reject'
              ? JSON.stringify({ reason: 'Rejected via web admin' })
              : undefined;
          await api(`/admin/videos/${id}/${action}`, {
            method: 'PUT',
            body,
          });
          flash(`Video ${action}d`);
          await renderVideos();
        } catch (error) {
          flash(error.message, true);
          btn.disabled = false;
        }
      });
    });
  }

  async function renderStores() {
    const payload = await api('/admin/stores/pending?page=1');
    const items = payload.data || [];
    if (!items.length) {
      content.innerHTML = '<div class="empty">No pending stores.</div>';
      return;
    }

    content.innerHTML = items
      .map(
        (store) => `
      <article class="card" data-id="${escapeHtml(store.id)}">
        <div class="card-head">
          <strong>${escapeHtml(store.name || store.slug || store.id)}</strong>
          <span class="meta">${escapeHtml(store.status || '')}</span>
        </div>
        <p class="meta">Owner: @${escapeHtml(store.user?.username || store.owner?.username || '—')}</p>
        <div class="actions">
          <button type="button" class="ok" data-action="approve">Approve</button>
          <button type="button" class="warn" data-action="reject">Reject</button>
        </div>
      </article>`,
      )
      .join('');

    content.querySelectorAll('[data-action]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = btn.closest('.card').dataset.id;
        const action = btn.dataset.action;
        btn.disabled = true;
        try {
          await api(`/admin/stores/${id}/${action}`, { method: 'PUT' });
          flash(`Store ${action}d`);
          await renderStores();
        } catch (error) {
          flash(error.message, true);
          btn.disabled = false;
        }
      });
    });
  }

  async function renderUsers() {
    const payload = await api('/admin/users?page=1&per_page=30');
    const items = payload.data || [];
    if (!items.length) {
      content.innerHTML = '<div class="empty">No users found.</div>';
      return;
    }

    content.innerHTML = items
      .map(
        (user) => `
      <article class="card" data-id="${escapeHtml(user.id)}">
        <div class="card-head">
          <strong>@${escapeHtml(user.username)}</strong>
          <span class="meta">${escapeHtml(user.role)} · ${escapeHtml(user.status)}</span>
        </div>
        <p class="meta">${escapeHtml(user.email || '')}</p>
        <div class="actions">
          <button type="button" class="warn" data-action="suspend">Suspend</button>
          <button type="button" class="secondary" data-action="ban">Ban</button>
          <button type="button" class="ok" data-action="activate">Activate</button>
        </div>
      </article>`,
      )
      .join('');

    content.querySelectorAll('[data-action]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = btn.closest('.card').dataset.id;
        const action = btn.dataset.action;
        btn.disabled = true;
        try {
          await api(`/admin/users/${id}/${action}`, { method: 'PUT' });
          flash(`User ${action}d`);
          await renderUsers();
        } catch (error) {
          flash(error.message, true);
          btn.disabled = false;
        }
      });
    });
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }

  loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    loginError.textContent = '';
    const submit = loginForm.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
      const payload = await api('/auth/login', {
        method: 'POST',
        body: JSON.stringify({
          login: document.getElementById('login').value.trim(),
          password: document.getElementById('password').value,
        }),
      });

      const data = payload.data || payload;
      const user = data.user;
      const accessToken = data.access_token;
      if (!user || !accessToken) {
        throw new Error('Unexpected login response');
      }
      if (!isStaff(user.role)) {
        throw new Error('This account is not admin/moderator');
      }

      saveSession({ user, accessToken });
      showShell();
    } catch (error) {
      loginError.textContent = error.message;
    } finally {
      submit.disabled = false;
    }
  });

  document.getElementById('logout-btn').addEventListener('click', () => {
    saveSession(null);
    showLogin();
  });

  tabs.addEventListener('click', (event) => {
    const btn = event.target.closest('button[data-tab]');
    if (!btn) return;
    setActiveTab(btn.dataset.tab);
  });

  if (session?.accessToken && isStaff(session.user?.role)) {
    showShell();
  } else {
    showLogin();
  }
})();
