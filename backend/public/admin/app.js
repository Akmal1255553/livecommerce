(() => {
  const API_BASE = `${window.location.origin}/api/v1`;
  const STORAGE_KEY = 'lc_admin_session';
  const ADMIN_ONLY_TABS = new Set(['overview', 'stores', 'users', 'categories', 'audit']);

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
    activeTab = isAdmin() ? 'overview' : 'reports';
    setActiveTab(activeTab);
  }

  function setActiveTab(tab) {
    if (ADMIN_ONLY_TABS.has(tab) && !isAdmin()) {
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
      if (activeTab === 'overview') await renderOverview();
      else if (activeTab === 'reports') await renderReports();
      else if (activeTab === 'videos') await renderVideos();
      else if (activeTab === 'stores') await renderStores();
      else if (activeTab === 'users') await renderUsers();
      else if (activeTab === 'categories') await renderCategories();
      else if (activeTab === 'audit') await renderAudit();
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

  function formatMoney(minor, currency) {
    const major = (Number(minor) || 0) / 100;
    return `${major.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ${currency || 'UZS'}`;
  }

  async function renderOverview() {
    const payload = await api('/admin/overview');
    const data = payload.data || {};
    const cards = [
      ['Users total', data.users_total],
      ['Users active', data.users_active],
      ['Suspended', data.users_suspended],
      ['Banned', data.users_banned],
      ['Stores pending', data.stores_pending],
      ['Stores active', data.stores_active],
      ['Videos pending', data.videos_pending],
      ['Open reports', data.reports_open],
      ['Orders total', data.orders_total],
      ['Orders paid+', data.orders_paid_or_later],
      ['Revenue (paid)', formatMoney(data.revenue_paid_minor, data.currency)],
    ];

    content.innerHTML = `
      <div class="stats-grid">
        ${cards
          .map(
            ([label, value]) => `
          <article class="stat-card">
            <span class="meta">${escapeHtml(label)}</span>
            <strong>${escapeHtml(String(value ?? 0))}</strong>
          </article>`,
          )
          .join('')}
      </div>`;
  }

  async function renderReports() {
    const payload = await api('/admin/reports?status=pending&page=1');
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

  async function renderCategories() {
    const payload = await api('/admin/categories');
    const items = payload.data || [];

    content.innerHTML = `
      <form id="category-form" class="card create-form">
        <div class="card-head"><strong>Create category</strong></div>
        <label>
          Name
          <input name="name" required maxlength="100" />
        </label>
        <label>
          Sort order
          <input name="sort_order" type="number" min="0" value="0" />
        </label>
        <div class="actions">
          <button type="submit" class="ok">Create</button>
        </div>
      </form>
      <div id="category-list"></div>`;

    const listEl = document.getElementById('category-list');
    if (!items.length) {
      listEl.innerHTML = '<div class="empty">No categories yet.</div>';
    } else {
      listEl.innerHTML = items
        .map(
          (cat) => `
        <article class="card" data-id="${escapeHtml(String(cat.id))}">
          <div class="card-head">
            <strong>${escapeHtml(cat.name)}</strong>
            <span class="meta">${cat.is_active ? 'active' : 'inactive'} · #${escapeHtml(String(cat.sort_order ?? 0))}</span>
          </div>
          <p class="meta">${escapeHtml(cat.slug || '')}</p>
          <div class="actions">
            ${
              cat.is_active
                ? '<button type="button" class="warn" data-action="deactivate">Deactivate</button>'
                : '<button type="button" class="ok" data-action="reactivate">Reactivate</button>'
            }
          </div>
        </article>`,
        )
        .join('');
    }

    document.getElementById('category-form').addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const submit = form.querySelector('button[type="submit"]');
      submit.disabled = true;
      try {
        const name = form.name.value.trim();
        const sortOrder = Number(form.sort_order.value || 0);
        await api('/admin/categories', {
          method: 'POST',
          body: JSON.stringify({ name, sort_order: sortOrder }),
        });
        flash('Category created');
        await renderCategories();
      } catch (error) {
        flash(error.message, true);
        submit.disabled = false;
      }
    });

    listEl.querySelectorAll('[data-action]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = btn.closest('.card').dataset.id;
        const action = btn.dataset.action;
        btn.disabled = true;
        try {
          if (action === 'deactivate') {
            await api(`/admin/categories/${id}`, { method: 'DELETE' });
            flash('Category deactivated');
          } else {
            await api(`/admin/categories/${id}`, {
              method: 'PUT',
              body: JSON.stringify({ is_active: true }),
            });
            flash('Category reactivated');
          }
          await renderCategories();
        } catch (error) {
          flash(error.message, true);
          btn.disabled = false;
        }
      });
    });
  }

  async function renderAudit() {
    const payload = await api('/admin/audit-logs?page=1');
    const items = payload.data || [];
    if (!items.length) {
      content.innerHTML = '<div class="empty">No audit log entries.</div>';
      return;
    }

    content.innerHTML = items
      .map(
        (entry) => `
      <article class="card">
        <div class="card-head">
          <strong>${escapeHtml(entry.action || '')}</strong>
          <span class="meta">${escapeHtml(entry.created_at || '')}</span>
        </div>
        <p class="meta">
          @${escapeHtml(entry.actor?.username || 'system')}
          · ${escapeHtml(entry.entity_type || '—')}
          · ${escapeHtml(String(entry.entity_id || '—'))}
        </p>
      </article>`,
      )
      .join('');
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
