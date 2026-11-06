/* Purrfect Pet Care - helpers shared by the website and the dashboard */
'use strict';

const API_URL = 'api/index.php';
const Session = { csrf: null, user: null, clinic: {}, unread: 0, perms: [] };

// Read-only actions are sent as GET (with data as query string); everything else is POST.
const GET_ACTIONS = new Set(['activity_list', 'record_history', 'pet_weights', 'enquiries_list', 'enquiry_get', 'messaging_get',
  'me', 'public_info', 'pets_list', 'pet_get', 'appointments_list', 'appointment_slots', 'records_list',
  'vaccinations_list', 'payments_list', 'payment_options', 'payment_status', 'forum_list', 'forum_get',
  'adoption_list', 'adoption_requests', 'lostfound_list', 'dashboard', 'vets_list', 'users_list',
  'services_list', 'settings_get', 'reports', 'notifications',
  'attachments', 'schedule_get', 'invoice_get', 'products_list', 'stock_movements',
]);

/** Call the API and return its data, or throw an Error with the server's message. */
async function api(action, data = null, method = null) {
  method = method || (GET_ACTIONS.has(action) ? 'GET' : 'POST');
  let url = `${API_URL}?action=${encodeURIComponent(action)}`;
  const opts = { method, credentials: 'same-origin', headers: {} };
  if (method === 'GET' && data) {
    const qs = new URLSearchParams();
    Object.entries(data).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '') qs.append(k, v); });
    url += '&' + qs.toString();
  } else if (method === 'POST') {
    opts.headers['Content-Type'] = 'application/json';
    opts.headers['X-CSRF-Token'] = Session.csrf || '';
    opts.body = JSON.stringify(data || {});
  }
  let res, json;
  try {
    res = await fetch(url, opts);
    json = await res.json();
  } catch (e) {
    throw new Error('Could not reach the server. Check your internet connection and try again.');
  }
  if (!json.ok) {
    const err = new Error(json.error || 'Request failed');
    err.status = res.status;
    if (res.status === 419) await loadSession(); // refresh CSRF token
    throw err;
  }
  if (json.data && json.data.csrf) Session.csrf = json.data.csrf;
  return json.data;
}

async function loadSession() {
  const d = await api('me');
  Session.csrf = d.csrf;
  Session.user = d.user;
  Session.clinic = d.clinic || {};
  Session.unread = d.unread || 0;
  Session.perms = d.perms || [];
  return d;
}

/** Escape text before putting it into HTML. */
function esc(v) {
  if (v === null || v === undefined) return '';
  return String(v).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function money(v) {
  const n = Number(v || 0);
  return 'KSh ' + n.toLocaleString('en-KE', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function fmtDate(d, opts) {
  if (!d) return '';
  const dt = new Date(String(d).length <= 10 ? d + 'T00:00:00' : String(d).replace(' ', 'T'));
  if (isNaN(dt)) return esc(d);
  return dt.toLocaleDateString('en-GB', opts || { day: 'numeric', month: 'short', year: 'numeric' });
}

function fmtDateTime(d) {
  if (!d) return '';
  const dt = new Date(String(d).replace(' ', 'T'));
  return isNaN(dt) ? esc(d) : dt.toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function timeAgo(d) {
  const dt = new Date(String(d).replace(' ', 'T'));
  const s = Math.floor((Date.now() - dt.getTime()) / 1000);
  if (s < 60) return 'just now';
  if (s < 3600) return Math.floor(s / 60) + ' min ago';
  if (s < 86400) return Math.floor(s / 3600) + ' h ago';
  if (s < 604800) return Math.floor(s / 86400) + ' d ago';
  return fmtDate(d);
}

function todayISO(offsetDays = 0) {
  const d = new Date();
  d.setDate(d.getDate() + offsetDays);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function statusBadge(s, label) {
  const text = label || (s === 'checked_in' ? 'arrived' : String(s || '').replace(/_/g, ' '));
  return `<span class="status s-${esc(s)}">${esc(text)}</span>`;
}

function toast(message, type = 'success') {
  let box = document.getElementById('toast-box');
  if (!box) {
    box = document.createElement('div');
    box.id = 'toast-box';
    box.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(box);
  }
  const colors = { success: 'text-bg-success', error: 'text-bg-danger', warning: 'text-bg-warning', info: 'text-bg-dark' };
  const el = document.createElement('div');
  el.className = `toast align-items-center border-0 ${colors[type] || colors.info}`;
  el.setAttribute('role', 'alert');
  el.innerHTML = `<div class="d-flex"><div class="toast-body">${esc(message)}</div>
    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
  box.appendChild(el);
  const t = new bootstrap.Toast(el, { delay: type === 'error' ? 6000 : 3500 });
  el.addEventListener('hidden.bs.toast', () => el.remove());
  t.show();
}

/** Disable a button and show a spinner while an async function runs. */
async function withBusy(btn, fn) {
  if (!btn) return fn();
  const html = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Please wait';
  try { return await fn(); } finally { btn.disabled = false; btn.innerHTML = html; }
}

function formData(form) {
  const out = {};
  new FormData(form).forEach((v, k) => { out[k] = typeof v === 'string' ? v.trim() : v; });
  form.querySelectorAll('input[type=checkbox][name]').forEach(cb => { out[cb.name] = cb.checked ? '1' : '0'; });
  return out;
}

const SPECIES_ICON = { Dog: 'assets/img/dogicon.png', Cat: 'assets/img/caticon.png', Bird: 'assets/img/birdicon.webp' };
function petAvatar(species, photoId, size) {
  const style = size ? ` style="width:${size}px;height:${size}px"` : '';
  if (photoId) return `<div class="pet-avatar has-photo"${style}><img src="file.php?id=${encodeURIComponent(photoId)}" alt=""></div>`;
  const src = SPECIES_ICON[species];
  return `<div class="pet-avatar"${style}>${src ? `<img src="${src}" alt="">` : '<i class="fa-solid fa-paw"></i>'}</div>`;
}

/** Upload one file (FormData POST) and return the saved attachment. */
async function apiUpload(entityType, entityId, file, kind = 'document') {
  if (file.size > 5 * 1024 * 1024) throw new Error(`${file.name} is larger than 5 MB`);
  const fd = new FormData();
  fd.append('entity_type', entityType);
  fd.append('entity_id', entityId);
  fd.append('kind', kind);
  fd.append('file', file);
  let res, json;
  try {
    res = await fetch(`${API_URL}?action=upload`, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': Session.csrf || '' }, body: fd });
    json = await res.json();
  } catch (e) {
    throw new Error('Upload failed. Check your connection and that the file is under 5 MB.');
  }
  if (!json.ok) throw new Error(json.error || 'Upload failed');
  return json.data;
}

function fileSize(n) {
  n = Number(n || 0);
  return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
}

function fileLinkHTML(a) {
  const icon = a.mime === 'application/pdf' ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
  return `<a href="file.php?id=${a.id}" target="_blank" rel="noopener" class="text-decoration-none"><i class="fa-solid ${icon} me-1"></i>${esc(a.original_name)}</a> <small class="text-muted">${fileSize(a.size_bytes)}</small>`;
}

function petAge(dob) {
  if (!dob) return '';
  const b = new Date(dob + 'T00:00:00'), n = new Date();
  let months = (n.getFullYear() - b.getFullYear()) * 12 + (n.getMonth() - b.getMonth());
  if (n.getDate() < b.getDate()) months--;
  if (months < 1) return 'Under 1 month';
  if (months < 12) return `${months} month${months > 1 ? 's' : ''}`;
  const y = Math.floor(months / 12);
  return `${y} year${y > 1 ? 's' : ''}`;
}
