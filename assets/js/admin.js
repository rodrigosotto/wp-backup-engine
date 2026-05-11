/**
 * WP Snapshot Engine — Admin UI
 * Vanilla JS, no jQuery dependency, communicates with the REST API.
 */
(function () {
  'use strict';

  // ── Config ────────────────────────────────────────────────────────────────
  const API   = window.WPSE.apiBase;
  const NONCE = window.WPSE.nonce;
  const i18n  = window.WPSE.i18n;

  const TYPE_META = {
    elementor: { icon: '🎨', label: 'Elementor',   cls: 'elementor' },
    post:      { icon: '📝', label: 'Post update',  cls: 'post'      },
    option:    { icon: '⚙️', label: 'Option change', cls: 'option'   },
    plugin:    { icon: '🔌', label: 'Plugin/Theme',  cls: 'plugin'   },
    system:    { icon: '🚀', label: 'System',        cls: 'system'   },
  };

  // ── State ─────────────────────────────────────────────────────────────────
  let state = {
    items:          [],
    total:          0,
    page:           1,
    perPage:        20,
    filterType:     '',
    filterDateFrom: '',
    filterDateTo:   '',
    activeId:       null,
    loading:        false,
  };

  // ── DOM refs ──────────────────────────────────────────────────────────────
  const $ = (id) => document.getElementById(id);
  const timeline   = $('wpse-timeline');
  const loadingEl  = $('wpse-loading');
  const detailsEl  = $('wpse-details');
  const paginEl    = $('wpse-pagination');
  const toastEl    = $('wpse-toast');
  const filterType = $('wpse-filter-type');
  const filterFrom = $('wpse-filter-date-from');
  const filterTo   = $('wpse-filter-date-to');
  const layoutEl   = document.querySelector('.wpse-layout');

  // ── Bootstrap ─────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    loadSnapshots();

    $('wpse-filter-apply').addEventListener('click', () => {
      state.page           = 1;
      state.filterType     = filterType.value;
      state.filterDateFrom = filterFrom.value;
      state.filterDateTo   = filterTo.value;
      loadSnapshots();
    });

    $('wpse-filter-reset').addEventListener('click', () => {
      filterType.value = filterFrom.value = filterTo.value = '';
      state.page = 1; state.filterType = ''; state.filterDateFrom = ''; state.filterDateTo = '';
      loadSnapshots();
    });

    $('wpse-details-close').addEventListener('click', closeDetails);

    // Tab switching
    document.querySelectorAll('.wpse-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.wpse-tab').forEach(t => t.classList.remove('wpse-tab--active'));
        tab.classList.add('wpse-tab--active');
        const target = tab.dataset.tab;
        document.querySelectorAll('.wpse-tab-panel').forEach(p => {
          p.hidden = p.id !== `wpse-tab-${target}`;
        });
      });
    });
  });

  // ── API helpers ───────────────────────────────────────────────────────────
  async function apiFetch(path, options = {}) {
    const res = await fetch(API + path, {
      headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json', ...(options.headers || {}) },
      ...options,
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || `HTTP ${res.status}`);
    }
    return res.json();
  }

  // ── Load & Render ─────────────────────────────────────────────────────────
  async function loadSnapshots() {
    if (state.loading) return;
    state.loading = true;
    showLoading(true);

    const params = new URLSearchParams({
      page:     state.page,
      per_page: state.perPage,
    });
    if (state.filterType)     params.set('snapshot_type', state.filterType);
    if (state.filterDateFrom) params.set('date_from', state.filterDateFrom);
    if (state.filterDateTo)   params.set('date_to',   state.filterDateTo);

    try {
      const data = await apiFetch(`/snapshots?${params}`);
      state.items = data.data;
      state.total = data.meta.total;
      renderTimeline();
      renderPagination();
    } catch (e) {
      showError(e.message);
    } finally {
      state.loading = false;
      showLoading(false);
    }
  }

  function showLoading(show) {
    if (loadingEl) loadingEl.hidden = !show;
  }

  function renderTimeline() {
    // Clear everything except the loading indicator
    Array.from(timeline.children).forEach(c => {
      if (c !== loadingEl) c.remove();
    });

    if (!state.items.length) {
      timeline.insertAdjacentHTML('beforeend', `
        <div class="wpse-empty">
          <div class="wpse-empty__icon">📭</div>
          <p class="wpse-empty__title">No snapshots yet</p>
          <p class="wpse-empty__sub">Save a post or change an option to create your first snapshot.</p>
        </div>`);
      return;
    }

    // Group by date (calendar day)
    const groups = groupByDay(state.items);

    for (const [day, items] of Object.entries(groups)) {
      const groupEl = document.createElement('div');
      groupEl.className = 'wpse-group';
      groupEl.innerHTML = `<div class="wpse-group-label">${day}</div>`;
      items.forEach(item => groupEl.appendChild(buildItem(item)));
      timeline.appendChild(groupEl);
    }
  }

  function buildItem(item) {
    const meta  = TYPE_META[item.snapshot_type] || TYPE_META.system;
    const title = buildTitle(item);
    const date  = formatDate(item.created_at);
    const isActive = state.activeId === item.id;

    const wrap = document.createElement('div');
    wrap.className = 'wpse-item';
    wrap.dataset.id = item.id;
    wrap.innerHTML = `
      <div class="wpse-item__dot wpse-item__dot--${meta.cls}"></div>
      <div class="wpse-item__card${isActive ? ' wpse-item__card--active' : ''}">
        <div class="wpse-item__top">
          <span class="wpse-item__icon">${meta.icon}</span>
          <span class="wpse-item__title">${escHtml(title)}</span>
          <span class="wpse-item__badge wpse-item__badge--${meta.cls}">${meta.label}</span>
        </div>
        <div class="wpse-item__meta">
          <span>🕐 ${date}</span>
          <span>#${item.id}</span>
          ${item.entity_id ? `<span>Post ${item.entity_id}</span>` : ''}
        </div>
        <div class="wpse-item__actions">
          <button class="wpse-btn wpse-btn--outline wpse-btn--sm" data-action="view">Details</button>
          <button class="wpse-btn wpse-btn--primary wpse-btn--sm" data-action="restore">Restore</button>
          ${item.snapshot_type === 'elementor' || item.snapshot_type === 'post'
            ? `<button class="wpse-btn wpse-btn--ghost wpse-btn--sm" data-action="restore-elementor">Elementor only</button>`
            : ''}
          <button class="wpse-btn wpse-btn--danger wpse-btn--sm" data-action="delete">Delete</button>
        </div>
      </div>`;

    wrap.querySelector('[data-action="view"]').addEventListener('click', (e) => {
      e.stopPropagation(); openDetails(item.id);
    });
    wrap.querySelector('[data-action="restore"]').addEventListener('click', (e) => {
      e.stopPropagation(); restoreSnapshot(item.id, 'full');
    });
    const elBtn = wrap.querySelector('[data-action="restore-elementor"]');
    if (elBtn) elBtn.addEventListener('click', (e) => {
      e.stopPropagation(); restoreSnapshot(item.id, 'elementor');
    });
    wrap.querySelector('[data-action="delete"]').addEventListener('click', (e) => {
      e.stopPropagation(); deleteSnapshot(item.id, wrap);
    });
    wrap.querySelector('.wpse-item__card').addEventListener('click', () => openDetails(item.id));

    return wrap;
  }

  // ── Details Panel ─────────────────────────────────────────────────────────
  async function openDetails(id) {
    state.activeId = id;
    // Update active state on cards
    document.querySelectorAll('.wpse-item__card').forEach(c => c.classList.remove('wpse-item__card--active'));
    const activeWrap = document.querySelector(`.wpse-item[data-id="${id}"] .wpse-item__card`);
    if (activeWrap) activeWrap.classList.add('wpse-item__card--active');

    // Show panel
    detailsEl.hidden = false;
    layoutEl.classList.add('wpse-layout--split');

    $('wpse-details-title').textContent = 'Loading…';
    $('wpse-details-meta').innerHTML = '';
    $('wpse-details-json').textContent = '';
    $('wpse-diff-view').innerHTML = '';

    try {
      const res = await apiFetch(`/snapshots/${id}`);
      const snap = res.data;
      const meta = TYPE_META[snap.snapshot_type] || TYPE_META.system;

      $('wpse-details-title').textContent = `${meta.icon} Snapshot #${snap.id}`;
      $('wpse-details-meta').innerHTML = `
        <span><strong>Type:</strong> ${meta.label}</span>
        <span><strong>Date:</strong> ${formatDate(snap.created_at)}</span>
        ${snap.entity_id ? `<span><strong>Post ID:</strong> ${snap.entity_id}</span>` : ''}
        <span><strong>Hash:</strong> <code>${snap.hash}</code></span>`;

      const payload = snap.payload || {};
      $('wpse-details-json').textContent = JSON.stringify(payload, null, 2);

      // Build simple diff if it's a post
      buildDiff(payload);
    } catch (e) {
      $('wpse-details-title').textContent = 'Error loading snapshot';
      showToast(e.message, 'error');
    }
  }

  function closeDetails() {
    detailsEl.hidden = true;
    layoutEl.classList.remove('wpse-layout--split');
    state.activeId = null;
    document.querySelectorAll('.wpse-item__card').forEach(c => c.classList.remove('wpse-item__card--active'));
  }

  function buildDiff(payload) {
    const diffView = $('wpse-diff-view');
    diffView.innerHTML = '';

    if (!payload.elementor || !payload.elementor['_elementor_data']) {
      diffView.innerHTML = '<p style="color:var(--wpse-muted);padding:20px;font-size:13px;">Diff is available for Elementor snapshots only in this view.</p>';
      return;
    }

    const snapshotStr = JSON.stringify(payload.elementor['_elementor_data'], null, 2);
    const label = document.createElement('div');
    label.className = 'wpse-diff-label';
    label.textContent = 'Elementor Data (snapshot)';
    diffView.appendChild(label);

    snapshotStr.split('\n').forEach(line => {
      const div = document.createElement('div');
      div.className = 'wpse-diff-line';
      div.textContent = line;
      diffView.appendChild(div);
    });
  }

  // ── Actions ───────────────────────────────────────────────────────────────
  async function restoreSnapshot(id, mode) {
    const confirmMsg = mode === 'elementor'
      ? 'Restore Elementor data from this snapshot? Only Elementor meta will be replaced.'
      : i18n.confirmRestore;
    if (!confirm(confirmMsg)) return;

    const endpoint = mode === 'elementor' ? `/restore/${id}/elementor` : `/restore/${id}`;
    const btn = document.querySelector(`.wpse-item[data-id="${id}"] [data-action="${mode === 'elementor' ? 'restore-elementor' : 'restore'}"]`);
    if (btn) { btn.textContent = i18n.restoring; btn.disabled = true; }

    try {
      await apiFetch(endpoint, { method: 'POST' });
      showToast(i18n.restored, 'success');
    } catch (e) {
      showToast(e.message, 'error');
    } finally {
      if (btn) { btn.textContent = mode === 'elementor' ? 'Elementor only' : 'Restore'; btn.disabled = false; }
    }
  }

  async function deleteSnapshot(id, wrapEl) {
    if (!confirm(i18n.confirmDelete)) return;
    try {
      await apiFetch(`/snapshots/${id}`, { method: 'DELETE' });
      wrapEl.remove();
      if (state.activeId === id) closeDetails();
      state.total = Math.max(0, state.total - 1);
      renderPagination();
      showToast(i18n.deleted, 'success');
    } catch (e) {
      showToast(e.message, 'error');
    }
  }

  // ── Pagination ─────────────────────────────────────────────────────────────
  function renderPagination() {
    paginEl.innerHTML = '';
    const totalPages = Math.ceil(state.total / state.perPage);
    if (totalPages <= 1) return;

    const makeBtn = (label, page, active = false, disabled = false) => {
      const b = document.createElement('button');
      b.className = 'wpse-pagination__btn' + (active ? ' wpse-pagination__btn--active' : '');
      b.textContent = label;
      b.disabled = disabled;
      if (!disabled && !active) b.addEventListener('click', () => { state.page = page; loadSnapshots(); });
      return b;
    };

    paginEl.appendChild(makeBtn('←', state.page - 1, false, state.page === 1));

    const range = pageRange(state.page, totalPages);
    let prev = null;
    range.forEach(p => {
      if (prev !== null && p - prev > 1) {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'wpse-pagination__info';
        ellipsis.textContent = '…';
        paginEl.appendChild(ellipsis);
      }
      paginEl.appendChild(makeBtn(p, p, p === state.page));
      prev = p;
    });

    paginEl.appendChild(makeBtn('→', state.page + 1, false, state.page === totalPages));

    const info = document.createElement('span');
    info.className = 'wpse-pagination__info';
    info.textContent = `${state.total} total`;
    paginEl.appendChild(info);
  }

  function pageRange(current, total) {
    const delta = 2;
    const range = [];
    for (let i = Math.max(1, current - delta); i <= Math.min(total, current + delta); i++) {
      range.push(i);
    }
    if (range[0] > 1)     range.unshift(1);
    if (range[range.length - 1] < total) range.push(total);
    return range;
  }

  // ── Helpers ───────────────────────────────────────────────────────────────
  function buildTitle(item) {
    if (item.snapshot_type === 'elementor') return `Elementor saved — Post ${item.entity_id || ''}`;
    if (item.snapshot_type === 'post')      return `Post updated — #${item.entity_id || ''}`;
    if (item.snapshot_type === 'option') {
      const preview = item.payload_preview || '';
      const match   = preview.match(/"option_name":"([^"]+)"/);
      return match ? `Option changed: ${match[1]}` : 'Option changed';
    }
    return `Snapshot #${item.id}`;
  }

  function groupByDay(items) {
    const groups = {};
    items.forEach(item => {
      const day = new Date(item.created_at).toLocaleDateString(undefined, {
        year: 'numeric', month: 'long', day: 'numeric',
      });
      if (!groups[day]) groups[day] = [];
      groups[day].push(item);
    });
    return groups;
  }

  function formatDate(dt) {
    return new Date(dt).toLocaleString(undefined, {
      year: 'numeric', month: 'short', day: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function showToast(msg, type = 'info') {
    toastEl.textContent = msg;
    toastEl.className = `wpse-toast wpse-toast--${type}`;
    toastEl.hidden = false;
    clearTimeout(toastEl._timer);
    toastEl._timer = setTimeout(() => { toastEl.hidden = true; }, 3500);
  }

  function showError(msg) {
    showToast(msg, 'error');
  }

})();
