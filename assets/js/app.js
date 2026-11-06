/* Purrfect Pet Care - dashboard for pet parents, vets and administrators */
'use strict';

const $view = document.getElementById('view');
const SPECIES = ['Dog', 'Cat', 'Bird', 'Rabbit', 'Fish', 'Reptile', 'Rodent', 'Other'];
const RECORD_TYPES = ['checkup', 'vaccination', 'surgery', 'dental', 'illness', 'injury', 'lab', 'grooming', 'other'];
const TREATMENT_LABEL = { vaccine: 'Vaccine', deworming: 'Deworming', flea_tick: 'Flea & tick' };
const SERVICE_CATEGORY = { consultation: 'Consultation', vaccination: 'Vaccination', checkup: 'Check-up', dental: 'Dental', surgery: 'Surgery', grooming: 'Grooming', emergency: 'Emergency', other: 'Other' };
const ROLE_LABEL = { owner: 'Pet parent', vet: 'Veterinarian', reception: 'Receptionist', assistant: 'Vet assistant', admin: 'Administrator' };
const METHOD_LABEL = { mpesa: 'M-Pesa', card: 'Card', cash: 'Cash' };

const NAV = {
  owner: [
    ['overview', 'Overview', 'fa-gauge'], ['pets', 'My Pets', 'fa-paw'], ['appointments', 'Appointments', 'fa-calendar-check'],
    ['records', 'Health Records', 'fa-notes-medical'], ['vaccinations', 'Vaccines & Treatments', 'fa-syringe'], ['payments', 'Payments', 'fa-wallet'],
    '-Community', ['adoption', 'Adoption', 'fa-heart'], ['lostfound', 'Lost & Found', 'fa-magnifying-glass-location'], ['forum', 'Forum', 'fa-comments'],
    '-Account', ['profile', 'My Profile', 'fa-user'],
  ],
  vet: [
    ['overview', 'Overview', 'fa-gauge'], ['calendar', 'Calendar', 'fa-calendar-days'], ['appointments', 'Appointments', 'fa-calendar-check'], ['pets', 'Patients', 'fa-paw'],
    ['records', 'Health Records', 'fa-notes-medical'], ['vaccinations', 'Vaccines & Treatments', 'fa-syringe'], ['payments', 'Payments', 'fa-wallet'],
    ['inventory', 'Inventory', 'fa-boxes-stacked'], ['schedule', 'My Schedule', 'fa-clock'],
    '-Community', ['adoption', 'Adoption', 'fa-heart'], ['lostfound', 'Lost & Found', 'fa-magnifying-glass-location'], ['forum', 'Forum', 'fa-comments'],
    '-Account', ['profile', 'My Profile', 'fa-user'],
  ],
  reception: [
    ['overview', 'Overview', 'fa-gauge'], ['calendar', 'Calendar', 'fa-calendar-days'], ['appointments', 'Appointments', 'fa-calendar-check'],
    ['pets', 'Patients', 'fa-paw'], ['clients', 'Pet Parents', 'fa-address-book'], ['payments', 'Payments', 'fa-wallet'],
    ['vaccinations', 'Vaccines & Treatments', 'fa-syringe'], ['inventory', 'Inventory', 'fa-boxes-stacked'], ['schedule', 'Vet Schedules', 'fa-clock'],
    '-Community', ['adoption', 'Adoption', 'fa-heart'], ['lostfound', 'Lost & Found', 'fa-magnifying-glass-location'], ['forum', 'Forum', 'fa-comments'],
    '-Account', ['profile', 'My Profile', 'fa-user'],
  ],
  assistant: [
    ['overview', 'Overview', 'fa-gauge'], ['calendar', 'Calendar', 'fa-calendar-days'], ['appointments', 'Appointments', 'fa-calendar-check'],
    ['pets', 'Patients', 'fa-paw'], ['records', 'Health Records', 'fa-notes-medical'], ['vaccinations', 'Vaccines & Treatments', 'fa-syringe'],
    ['inventory', 'Inventory', 'fa-boxes-stacked'], ['schedule', 'Vet Schedules', 'fa-clock'],
    '-Community', ['adoption', 'Adoption', 'fa-heart'], ['lostfound', 'Lost & Found', 'fa-magnifying-glass-location'], ['forum', 'Forum', 'fa-comments'],
    '-Account', ['profile', 'My Profile', 'fa-user'],
  ],
  admin: [
    ['overview', 'Overview', 'fa-gauge'], ['calendar', 'Calendar', 'fa-calendar-days'], ['appointments', 'Appointments', 'fa-calendar-check'], ['pets', 'Patients', 'fa-paw'],
    ['users', 'Users', 'fa-users'], ['payments', 'Payments', 'fa-wallet'], ['records', 'Health Records', 'fa-notes-medical'],
    ['vaccinations', 'Vaccines & Treatments', 'fa-syringe'],
    '-Community', ['adoption', 'Adoption', 'fa-heart'], ['lostfound', 'Lost & Found', 'fa-magnifying-glass-location'], ['forum', 'Forum', 'fa-comments'],
    '-Management', ['services', 'Services & Prices', 'fa-tags'], ['inventory', 'Inventory', 'fa-boxes-stacked'], ['schedule', 'Vet Schedules', 'fa-clock'],
    ['reports', 'Reports', 'fa-chart-column'], ['activity', 'Activity Log', 'fa-list-check'], ['settings', 'Settings & Backups', 'fa-gear'],
    '-Account', ['profile', 'My Profile', 'fa-user'],
  ],
};

const demoTag = r => Number(r && r.is_demo) ? ' <span class="badge-demo">Demo</span>' : '';

let ACTIONS = {};          // data-act handlers for the current page
const GLOBAL_ACTIONS = {   // handlers that work on every page and in modals
  recFiles: id => showRecordFiles(id),
  recHistory: id => showRecordHistory(id),
  invoice: id => invoiceModal(id),
};
let route = { page: 'overview', params: new URLSearchParams() };
const cache = {};
const isStaff = () => Session.user && ['vet', 'admin', 'reception', 'assistant'].includes(Session.user.role);
const can = perm => !!Session.user && (Session.user.role === 'admin' || (Session.perms || []).includes(perm));
const realEmail = e => e && !/@walkin\.local$/i.test(e) ? e : '';
/** Red strip with allergies, conditions and behaviour warnings for a pet. */
function petAlerts(p, compact = false) {
  const a = (p && (p.allergies ?? p.pet_allergies)) || '', c = (p && (p.conditions ?? p.pet_conditions)) || '', b = (p && (p.behaviour ?? p.pet_behaviour)) || '';
  if (!a && !c && !b) return '';
  if (compact) return ` <span class="pet-alert-dot" title="${esc([a && 'Allergies: ' + a, c && 'Conditions: ' + c, b && 'Behaviour: ' + b].filter(Boolean).join(' · '))}"><i class="fa-solid fa-triangle-exclamation"></i></span>`;
  return `<div class="pet-alert">${a ? `<div><i class="fa-solid fa-triangle-exclamation me-1"></i><strong>Allergies:</strong> ${esc(a)}</div>` : ''}${c ? `<div><i class="fa-solid fa-heart-pulse me-1"></i><strong>Conditions:</strong> ${esc(c)}</div>` : ''}${b ? `<div><i class="fa-solid fa-hand me-1"></i><strong>Handling:</strong> ${esc(b)}</div>` : ''}</div>`;
}
const isAdmin = () => Session.user && Session.user.role === 'admin';
const isOwner = () => Session.user && Session.user.role === 'owner';

// ---------------------------------------------------------------------------
// Generic UI helpers
// ---------------------------------------------------------------------------
function emptyHTML(text, icon = 'fa-folder-open') {
  return `<div class="empty"><i class="fa-solid ${icon}"></i>${esc(text)}</div>`;
}

function tableHTML(cols, rows, emptyText) {
  if (!rows.length) return emptyHTML(emptyText || 'Nothing here yet');
  return `<div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr>${cols.map(c => `<th class="${c.cls || ''}">${c.label}</th>`).join('')}</tr></thead>
    <tbody>${rows.map(r => `<tr>${cols.map(c => `<td class="${c.cls || ''}">${c.render(r)}</td>`).join('')}</tr>`).join('')}</tbody>
  </table></div>`;
}

function btn(act, id, label, cls = 'btn-light', icon = '', extra = '') {
  return `<button class="btn btn-sm ${cls}" data-act="${act}" data-id="${esc(id)}" ${extra}>${icon ? `<i class="fa-solid ${icon}${label ? ' me-1' : ''}"></i>` : ''}${label}</button>`;
}

function statCard(icon, color, value, label, link) {
  const inner = `<div class="card-x stat"><div class="icon ${color}"><i class="fa-solid ${icon}"></i></div>
    <div><div class="value">${value}</div><div class="label">${esc(label)}</div></div></div>`;
  return `<div class="col-6 col-xl-3">${link ? `<a href="#${link}" class="text-decoration-none text-reset">${inner}</a>` : inner}</div>`;
}

function pageHead(title, buttons = '') {
  document.getElementById('topTitle').textContent = title;
  return `<div class="page-head"><h1>${esc(title)}</h1><div class="d-flex flex-wrap gap-2">${buttons}</div></div>`;
}

function tabsHTML(name, items, active) {
  return `<ul class="nav nav-pills mb-3 flex-wrap gap-1">${items.map(([v, l]) =>
    `<li class="nav-item"><button class="nav-link py-1 px-3 ${v === active ? 'active' : ''}" data-tab="${name}" data-value="${esc(v)}">${esc(l)}</button></li>`).join('')}</ul>`;
}

function showModal(el) {
  return new Promise(resolve => {
    const open = [...document.querySelectorAll('.modal.show')].filter(m => m !== el);
    const go = () => { bootstrap.Modal.getOrCreateInstance(el).show(); resolve(); };
    if (open.length) {
      open[0].addEventListener('hidden.bs.modal', go, { once: true });
      bootstrap.Modal.getInstance(open[0]).hide();
    } else {
      go();
    }
  });
}

function hideModals() {
  document.querySelectorAll('.modal.show').forEach(m => bootstrap.Modal.getInstance(m).hide());
}

function confirmBox(message, okText = 'Yes', danger = true) {
  const el = document.getElementById('confirmModal');
  el.querySelector('[data-message]').textContent = message;
  const ok = el.querySelector('[data-ok]');
  ok.textContent = okText;
  ok.className = 'btn ' + (danger ? 'btn-danger' : 'btn-brand');
  return new Promise(resolve => {
    let answered = false;
    ok.onclick = () => { answered = true; bootstrap.Modal.getInstance(el).hide(); resolve(true); };
    el.addEventListener('hidden.bs.modal', () => { if (!answered) resolve(false); }, { once: true });
    showModal(el);
  });
}

function fieldHTML(f, value) {
  const id = 'f_' + f.name;
  const v = value === undefined || value === null ? (f.value ?? '') : value;
  const req = f.required ? 'required' : '';
  const label = f.label ? `<label class="form-label" for="${id}">${esc(f.label)}${f.required ? ' <span class="text-danger">*</span>' : ''}</label>` : '';
  const help = f.help ? `<div class="form-text">${esc(f.help)}</div>` : '';
  let input;
  switch (f.type) {
    case 'html': return `<div class="${f.col || 'col-12'}">${f.html}</div>`;
    case 'hidden': return `<input type="hidden" name="${f.name}" value="${esc(v)}">`;
    case 'textarea':
      input = `<textarea class="form-control" id="${id}" name="${f.name}" rows="${f.rows || 3}" ${req} placeholder="${esc(f.placeholder || '')}">${esc(v)}</textarea>`;
      break;
    case 'select': {
      const opts = (f.options || []).map(o => Array.isArray(o) ? o : [o, o]);
      input = `<select class="form-select" id="${id}" name="${f.name}" ${req}>
        ${f.placeholder !== undefined ? `<option value="">${esc(f.placeholder)}</option>` : ''}
        ${opts.map(([ov, ol]) => `<option value="${esc(ov)}" ${String(ov) === String(v) ? 'selected' : ''}>${esc(ol)}</option>`).join('')}
      </select>`;
      break;
    }
    case 'checkbox':
      return `<div class="${f.col || 'col-12'}"><div class="form-check"><input class="form-check-input" type="checkbox" id="${id}" name="${f.name}" ${v === true || v === '1' || v === 1 ? 'checked' : ''}>
        <label class="form-check-label" for="${id}">${esc(f.label)}</label></div>${help}</div>`;
    case 'file':
      input = `<input class="form-control" type="file" id="${id}" name="${f.name}" ${f.accept ? `accept="${f.accept}"` : ''} ${f.multiple ? 'multiple' : ''}>`;
      break;
    default:
      input = `<input class="form-control" type="${f.type || 'text'}" id="${id}" name="${f.name}" value="${esc(v)}" ${req}
        ${f.min !== undefined ? `min="${f.min}"` : ''} ${f.max !== undefined ? `max="${f.max}"` : ''} ${f.step ? `step="${f.step}"` : ''}
        placeholder="${esc(f.placeholder || '')}" ${f.autocomplete ? `autocomplete="${f.autocomplete}"` : ''}>`;
  }
  return `<div class="${f.col || 'col-12'}">${label}${input}${help}</div>`;
}

/**
 * Open a form in a modal.
 * opts: {title, fields, values, submitText, intro, onRender(form), onSubmit(data) -> Promise}
 */
function openForm(opts) {
  const el = document.getElementById('formModal');
  const form = el.querySelector('form');
  el.querySelector('.modal-title').textContent = opts.title;
  el.querySelector('.modal-dialog').className = 'modal-dialog modal-dialog-centered modal-dialog-scrollable ' + (opts.size || 'modal-lg');
  const values = opts.values || {};
  el.querySelector('.modal-body').innerHTML = (opts.intro ? `<div class="mb-3">${opts.intro}</div>` : '') +
    `<div class="row g-3">${opts.fields.map(f => fieldHTML(f, values[f.name])).join('')}</div>`;
  const submit = el.querySelector('[data-submit]');
  submit.textContent = opts.submitText || 'Save';
  submit.className = 'btn ' + (opts.danger ? 'btn-danger' : 'btn-brand');
  const err = el.querySelector('[data-error]');
  err.textContent = '';
  form.onsubmit = async e => {
    e.preventDefault();
    err.textContent = '';
    const missing = opts.fields.filter(f => f.required && f.type !== 'checkbox' && !String(form.elements[f.name]?.value || '').trim());
    if (missing.length) {
      err.textContent = `Please fill in: ${missing.map(f => f.label).join(', ')}`;
      form.elements[missing[0].name]?.focus();
      return;
    }
    await withBusy(submit, async () => {
      try {
        const msg = await opts.onSubmit(formData(form), form);
        bootstrap.Modal.getInstance(el).hide();
        if (msg !== false) toast(typeof msg === 'string' ? msg : 'Saved');
      } catch (ex) {
        err.textContent = ex.message;
      }
    });
  };
  if (opts.onRender) opts.onRender(form);
  showModal(el);
}

function openView(title, html, size = 'modal-lg') {
  const el = document.getElementById('viewModal');
  el.querySelector('.modal-title').textContent = title;
  el.querySelector('.modal-dialog').className = 'modal-dialog modal-dialog-centered modal-dialog-scrollable ' + size;
  el.querySelector('.modal-body').innerHTML = html;
  showModal(el);
  return el.querySelector('.modal-body');
}

function pickFiles(accept, multiple = false) {
  return new Promise(resolve => {
    const inp = document.createElement('input');
    inp.type = 'file';
    inp.accept = accept || '';
    inp.multiple = multiple;
    inp.onchange = () => resolve([...inp.files]);
    inp.click();
  });
}

async function uploadAll(entityType, entityId, files, kind = 'document') {
  for (const f of files || []) await apiUpload(entityType, entityId, f, kind);
}

const PHOTO_ACCEPT = 'image/jpeg,image/png,image/webp,image/gif';
const DOC_ACCEPT = 'image/jpeg,image/png,image/webp,image/gif,application/pdf';

async function run(fn, success) {
  try {
    const r = await fn();
    if (success) toast(success);
    return r;
  } catch (e) {
    toast(e.message, 'error');
    throw e;
  }
}

async function getCached(key, loader) {
  if (!cache[key]) cache[key] = await loader();
  return cache[key];
}

const getServices = () => getCached('services', () => api('services_list'));
const getVets = () => getCached('vets', () => api('vets_list'));
const getMyPets = () => api('pets_list');
const getOwners = () => getCached('owners', () => api('users_list', isAdmin() ? { role: 'owner' } : null));

// ---------------------------------------------------------------------------
// Layout, routing and notifications
// ---------------------------------------------------------------------------
function buildNav() {
  const items = NAV[Session.user.role] || [];
  document.getElementById('sideNav').innerHTML = items.map(i => typeof i === 'string'
    ? `<div class="nav-section">${esc(i.slice(1))}</div>`
    : `<a class="nav-link" href="#${i[0]}" data-page="${i[0]}"><i class="fa-solid ${i[2]}"></i>${esc(i[1])}</a>`).join('');
  const u = Session.user;
  document.getElementById('userName').textContent = u.name;
  document.getElementById('userRole').textContent = ROLE_LABEL[u.role];
  document.getElementById('userInitial').textContent = (u.name || '?').trim().charAt(0).toUpperCase();
  document.getElementById('brandName').textContent = Session.clinic.name || 'Purrfect Pet Care';
  document.title = `Dashboard | ${Session.clinic.name || 'Purrfect Pet Care'}`;
}

function parseHash() {
  const h = location.hash.replace(/^#/, '');
  const [page, qs] = h.split('?');
  return { page: page || 'overview', params: new URLSearchParams(qs || '') };
}

function setHashQuiet(page, params = '') {
  history.replaceState(null, '', '#' + page + (params ? '?' + params : ''));
}

async function render() {
  route = parseHash();
  const allowed = (NAV[Session.user.role] || []).filter(i => typeof i !== 'string').map(i => i[0]);
  if (!allowed.includes(route.page)) route.page = 'overview';
  document.querySelectorAll('#sideNav .nav-link').forEach(a => a.classList.toggle('active', a.dataset.page === route.page));
  closeSidebar();
  if (!render.keepModals) hideModals(); // leaving a page closes its pop-ups
  render.keepModals = false;
  ACTIONS = {};
  $view.innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border"></span></div>';
  try {
    await PAGES[route.page]();
  } catch (e) {
    if (e.status === 401) return goLogin();
    $view.innerHTML = `<div class="alert alert-danger">${esc(e.message)} <button class="btn btn-sm btn-outline-danger ms-2" onclick="render()">Try again</button></div>`;
  }
  $view.focus({ preventScroll: true });
}

function rerender() {
  return render();
}

function goLogin() {
  location.href = 'index.html?login=1';
}

function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('backdrop').classList.remove('show');
}

function setUnread(n) {
  Session.unread = n;
  const b = document.getElementById('notifCount');
  b.textContent = n > 9 ? '9+' : n;
  b.classList.toggle('d-none', !n);
}

async function loadNotifications() {
  const list = await api('notifications');
  setUnread(list.filter(n => !Number(n.is_read)).length);
  document.getElementById('notifList').innerHTML = list.length ? list.map(n => `
    <a href="#${esc(n.link || 'overview')}" class="d-block px-3 py-2 border-bottom text-decoration-none text-reset notif-item ${Number(n.is_read) ? '' : 'unread'}" data-notif="${n.id}">
      <div class="fw-semibold small">${esc(n.title)}</div>
      ${n.message ? `<div class="small">${esc(n.message)}</div>` : ''}
      <small>${timeAgo(n.created_at)}</small>
    </a>`).join('') : '<div class="p-3 text-muted small">You have no notifications.</div>';
}

// ---------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------
async function pageOverview() {
  const d = await api('dashboard');
  const u = Session.user;
  const hello = `<p class="text-muted mb-4">Welcome back, ${esc(u.name.split(' ')[0])}! Today is ${fmtDate(todayISO(), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}.</p>`;
  if (u.role === 'owner') {
    $view.innerHTML = pageHead('Overview', `<button class="btn btn-brand" data-act="book"><i class="fa-solid fa-plus me-1"></i>Book appointment</button>`) + hello + `
      <div class="row g-3 mb-4">
        ${statCard('fa-paw', 'i-orange', d.pets, 'Active pets', 'pets')}
        ${statCard('fa-calendar-check', 'i-teal', d.upcoming, 'Upcoming visits', 'appointments')}
        ${statCard('fa-syringe', d.vaccines_due ? 'i-red' : 'i-blue', d.vaccines_due, 'Vaccines due (30 days)', 'vaccinations?tab=due')}
        ${statCard('fa-wallet', d.balance ? 'i-red' : 'i-purple', money(d.balance), 'Amount due', 'payments')}
      </div>
      <div class="row g-3">
        <div class="col-lg-8"><div class="card-x p-3 h-100">
          <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Upcoming appointments</h2><a href="#appointments" class="small">View all</a></div>
          ${d.next_appointments.length ? d.next_appointments.map(a => `
            <div class="d-flex align-items-center gap-3 py-2 border-bottom">
              ${petAvatar(a.species)}
              <div class="flex-grow-1"><div class="fw-semibold">${esc(a.service_name)} · ${esc(a.pet_name)}</div>
                <div class="small text-muted">${fmtDate(a.appt_date, { weekday: 'short', day: 'numeric', month: 'short' })} at ${esc(a.appt_time)}${a.vet_name ? ' · ' + esc(a.vet_name) : ''}</div></div>
              ${statusBadge(a.status)}
            </div>`).join('') : emptyHTML('No upcoming appointments', 'fa-calendar')}
        </div></div>
        <div class="col-lg-4"><div class="card-x p-3 h-100">
          <h2 class="h6">Quick actions</h2>
          <div class="d-grid gap-2">
            <a class="btn btn-light text-start" href="#pets?add=1"><i class="fa-solid fa-paw me-2 text-brand"></i>Add a pet</a>
            <button class="btn btn-light text-start" data-act="book"><i class="fa-solid fa-calendar-plus me-2 text-brand"></i>Book a visit</button>
            <a class="btn btn-light text-start" href="#payments"><i class="fa-solid fa-mobile-screen me-2 text-brand"></i>Pay with M-Pesa</a>
            <a class="btn btn-light text-start" href="#lostfound"><i class="fa-solid fa-bullhorn me-2 text-brand"></i>Report a lost pet</a>
          </div>
        </div></div>
      </div>`;
    ACTIONS.book = () => bookForm({});
    return;
  }

  const admin = u.role === 'admin';
  const alertBox = (icon, html, link, label) => `<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
      <span><i class="fa-solid ${icon} me-2"></i>${html}</span><a class="btn btn-sm btn-brand" href="#${link}">${label}</a></div>`;
  const alerts = [
    admin && d.demo_users ? alertBox('fa-broom', `Demo data is loaded (${d.demo_users} demo accounts). Remove it before real clients sign up.`, 'settings', 'Remove') : '',
    admin && (d.days_since_backup === null || d.days_since_backup >= 7) ? alertBox('fa-database', d.days_since_backup === null ? 'You have never downloaded a backup.' : `Your last backup was ${d.days_since_backup} days ago.`, 'settings', 'Back up now') : '',
    d.low_stock ? alertBox('fa-boxes-stacked', `${d.low_stock} inventory item${d.low_stock > 1 ? 's are' : ' is'} running low.`, 'inventory?tab=low', 'View') : '',
  ].join('');
  const quick = [
    can('appointments.manage') ? `<button class="btn btn-brand" data-act="walkin"><i class="fa-solid fa-person-walking-arrow-right me-1"></i>New walk-in</button>` : '',
    can('appointments.manage') ? `<button class="btn btn-outline-brand" data-act="book"><i class="fa-solid fa-calendar-plus me-1"></i>Book appointment</button>` : '',
    `<a class="btn btn-light" href="#calendar"><i class="fa-solid fa-calendar-days me-1"></i>Calendar</a>`,
    can('billing') ? `<a class="btn btn-light" href="#payments?tab=unpaid"><i class="fa-solid fa-file-invoice-dollar me-1"></i>Unpaid invoices</a>` : '',
    can('appointments.manage') ? `<a class="btn btn-light" href="#pets?add=1"><i class="fa-solid fa-paw me-1"></i>Add pet</a>` : '',
  ].filter(Boolean).join('');
  $view.innerHTML = pageHead('Overview') + hello + `<div class="quick-bar mb-3">${quick}</div>` + alerts +
    (admin && d.pending_users ? `<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span><i class="fa-solid fa-user-clock me-2"></i><strong>${d.pending_users}</strong> staff account${d.pending_users > 1 ? 's are' : ' is'} waiting for your approval.</span>
      <a class="btn btn-sm btn-brand" href="#users?status=pending">Review</a></div>` : '') + `
    <div class="row g-3 mb-4">
      ${statCard('fa-calendar-day', 'i-orange', d.today_appointments.length, "Today's appointments", 'calendar')}
      ${statCard('fa-couch', d.waiting ? 'i-red' : 'i-teal', d.waiting, 'In the waiting room', 'appointments?tab=waiting')}
      ${d.unpaid_count !== undefined ? statCard('fa-file-invoice-dollar', d.unpaid_count ? 'i-red' : 'i-blue', money(d.unpaid_total), `Unpaid (${d.unpaid_count} invoice${d.unpaid_count === 1 ? '' : 's'})`, 'payments?tab=unpaid')
              : statCard('fa-syringe', 'i-red', d.vaccines_due, 'Vaccines due / overdue', 'vaccinations?tab=due')}
      ${admin ? statCard('fa-sack-dollar', 'i-purple', money(d.revenue_month), 'Revenue this month', 'reports')
              : statCard('fa-hourglass-half', 'i-teal', d.pending_requests, 'Awaiting confirmation', 'appointments?tab=pending')}
    </div>
    ${admin ? `<div class="row g-3 mb-4">
      ${statCard('fa-users', 'i-blue', d.owners, 'Clients', 'users?role=owner')}
      ${statCard('fa-user-doctor', 'i-teal', d.vets, 'Active vets', 'users')}
      ${statCard('fa-comments', 'i-orange', d.pending_posts, 'Posts to moderate', 'forum?tab=pending')}
      ${statCard('fa-heart', 'i-red', d.adoption_requests, 'Adoption requests', 'adoption')}
    </div>` : ''}
    <div class="card-x mb-4"><div class="p-3 d-flex justify-content-between"><h2 class="h6 mb-0">Today's schedule</h2><a href="#appointments" class="small">All appointments</a></div>
      ${apptTable(d.today_appointments, 'No appointments today')}</div>
    ${admin ? `<div class="card-x"><div class="p-3 d-flex justify-content-between"><h2 class="h6 mb-0">Recent payments</h2><a href="#payments" class="small">All payments</a></div>
      ${paymentsTable(d.recent_payments)}</div>` : ''}`;
  ACTIONS.book = () => bookForm({});
  ACTIONS.walkin = () => walkinForm();
  registerApptActions(d.today_appointments);
  registerPaymentActions(d.recent_payments || []);
}

// ---------------------------------------------------------------------------
// Pets
// ---------------------------------------------------------------------------
async function pagePets() {
  const q = route.params.get('q') || '';
  const pets = await api('pets_list', isStaff() ? { q } : null);
  const title = isOwner() ? 'My Pets' : 'Patients';
  let body;
  if (isOwner()) {
    body = pets.length ? `<div class="row g-3">${pets.map(p => `
      <div class="col-sm-6 col-xl-4"><div class="card-x pet-card">
        <div class="d-flex gap-3 align-items-center mb-3">${petAvatar(p.species, p.photo_id)}
          <div><div class="h5 mb-0">${esc(p.name)}</div><div class="text-muted small">${esc(p.breed || p.species)}${p.sex ? ' · ' + esc(p.sex) : ''}</div></div>
          ${p.status !== 'active' ? `<span class="ms-auto">${statusBadge(p.status)}</span>` : ''}
        </div>
        ${petAlerts(p)}
        <div class="small text-muted mb-3 flex-grow-1">
          ${p.date_of_birth ? `<div><i class="fa-solid fa-cake-candles me-2"></i>${petAge(p.date_of_birth)} old</div>` : ''}
          ${p.weight_kg ? `<div><i class="fa-solid fa-weight-scale me-2"></i>${esc(p.weight_kg)} kg</div>` : ''}
          ${p.microchip ? `<div><i class="fa-solid fa-microchip me-2"></i>${esc(p.microchip)}</div>` : ''}
        </div>
        <div class="d-flex flex-wrap gap-2">
          ${btn('view', p.id, 'Details', 'btn-outline-brand', 'fa-file-medical')}
          ${p.status === 'active' ? btn('bookPet', p.id, 'Book', 'btn-light', 'fa-calendar-plus') : ''}
          ${btn('edit', p.id, '', 'btn-light', 'fa-pen', 'title="Edit"')}
          ${btn('del', p.id, '', 'btn-light text-danger', 'fa-trash', 'title="Delete"')}
        </div>
      </div></div>`).join('')}</div>`
      : `<div class="card-x">${emptyHTML('You have not added any pets yet. Add your first pet to start booking appointments.', 'fa-paw')}</div>`;
  } else {
    body = `<div class="card-x">
      <div class="p-3"><form class="d-flex gap-2" id="petSearch" style="max-width:420px">
        <input class="form-control" name="q" value="${esc(q)}" placeholder="Search by pet, owner, breed or microchip">
        <button class="btn btn-light"><i class="fa-solid fa-magnifying-glass"></i></button></form></div>
      ${tableHTML([
        { label: 'Pet', render: p => `<div class="d-flex align-items-center gap-2">${petAvatar(p.species, p.photo_id, 40)}<div><strong>${esc(p.name)}</strong>${petAlerts(p, true)}${demoTag(p)}<div class="small text-muted">${esc(p.species)}${p.breed ? ' · ' + esc(p.breed) : ''}</div></div></div>` },
        { label: 'Owner', render: p => `${esc(p.owner_name)}<div class="small text-muted">${esc(p.owner_phone || '')}</div>` },
        { label: 'Age', render: p => petAge(p.date_of_birth) },
        { label: 'Status', render: p => statusBadge(p.status) },
        { label: '', cls: 'text-end text-nowrap', render: p => btn('view', p.id, 'Open', 'btn-outline-brand') + (can('appointments.manage') || can('medical.view') ? ' ' + btn('edit', p.id, '', 'btn-light', 'fa-pen') : '') + (isAdmin() ? ' ' + btn('del', p.id, '', 'btn-light text-danger', 'fa-trash') : '') },
      ], pets, q ? 'No pets match your search' : 'No patients registered yet')}</div>`;
  }
  $view.innerHTML = pageHead(title, (isOwner() || can('appointments.manage') || can('medical.view') ? `<button class="btn btn-brand" data-act="add"><i class="fa-solid fa-plus me-1"></i>Add pet</button>` : '')) + body;
  const search = document.getElementById('petSearch');
  if (search) search.onsubmit = e => { e.preventDefault(); location.hash = 'pets?q=' + encodeURIComponent(search.q.value.trim()); };

  const byId = id => pets.find(p => String(p.id) === String(id));
  ACTIONS.add = () => petForm();
  ACTIONS.edit = id => petForm(byId(id));
  ACTIONS.view = id => viewPet(id);
  ACTIONS.bookPet = id => bookForm({ petId: id });
  ACTIONS.del = async id => {
    const p = byId(id);
    if (await confirmBox(`Delete ${p.name}? Their health records, vaccinations and appointments will also be deleted. This cannot be undone.`, 'Delete')) {
      await run(() => api('pet_delete', { id }), `${p.name} was deleted`);
      rerender();
    }
  };
  if (route.params.get('add')) { setHashQuiet('pets'); petForm(); }
}

async function petForm(pet) {
  const fields = [];
  if (isStaff() && !pet) {
    const owners = await getOwners();
    fields.push({ name: 'owner_id', label: 'Owner', type: 'select', required: true, placeholder: 'Choose the owner…',
      options: owners.map(o => [o.id, `${o.name} (${realEmail(o.email) || o.phone || 'no contact'})`]), help: isAdmin() ? 'New client? Create their account under Users first.' : 'New client? Add them under Pet Parents first, or use New walk-in.' });
  }
  fields.push(
    { name: 'name', label: 'Pet name', required: true, col: 'col-md-6' },
    { name: 'species', label: 'Species', type: 'select', options: SPECIES, required: true, col: 'col-md-6' },
    { name: 'breed', label: 'Breed', col: 'col-md-6' },
    { name: 'sex', label: 'Sex', type: 'select', options: ['Male', 'Female', 'Unknown'], placeholder: '—', col: 'col-md-6' },
    { name: 'date_of_birth', label: 'Date of birth (approx.)', type: 'date', max: todayISO(), col: 'col-md-4' },
    { name: 'weight_kg', label: 'Weight (kg)', type: 'number', step: '0.01', min: 0, col: 'col-md-4' },
    { name: 'color', label: 'Colour / markings', col: 'col-md-4' },
    { name: 'microchip', label: 'Microchip number', col: 'col-md-6' },
  );
  if (pet) fields.push({ name: 'status', label: 'Status', type: 'select', options: [['active', 'Active'], ['archived', 'Archived'], ['deceased', 'Deceased']], col: 'col-md-6' });
  fields.push(
    { type: 'html', name: '_h', html: '<div class="form-section-title"><i class="fa-solid fa-triangle-exclamation me-1"></i>Health alerts — shown to staff on every visit and invoice</div>' },
    { name: 'allergies', label: 'Allergies', placeholder: 'e.g. Penicillin, chicken', col: 'col-md-6' },
    { name: 'conditions', label: 'Existing conditions', placeholder: 'e.g. Diabetes, heart murmur', col: 'col-md-6' },
    { name: 'medications', label: 'Current medication', placeholder: 'e.g. Insulin 2 units twice daily', col: 'col-md-6' },
    { name: 'behaviour', label: 'Behaviour / handling', placeholder: 'e.g. Nervous, needs a muzzle', col: 'col-md-6' },
    { name: 'diet', label: 'Diet', placeholder: 'e.g. Grain-free dry food, twice a day', col: 'col-md-6' },
    { name: 'notes', label: 'Other notes', type: 'textarea', rows: 2, col: 'col-md-6' },
  );
  fields.push({ name: 'photo', label: pet && pet.photo_id ? 'Replace photo (optional)' : 'Photo (optional)', type: 'file', accept: PHOTO_ACCEPT, help: 'JPG, PNG or WEBP, up to 5 MB.' });
  openForm({
    title: pet ? `Edit ${pet.name}` : 'Add a pet',
    fields, values: pet || { species: 'Dog' },
    submitText: pet ? 'Save changes' : 'Add pet',
    onSubmit: async (d, form) => {
      if (pet) d.id = pet.id;
      delete d.photo;
      const saved = await api('pet_save', d);
      const photo = form.elements.photo.files[0];
      if (photo) await apiUpload('pet', saved.id, photo, 'photo');
      rerender();
      return pet ? 'Pet updated' : `${d.name} was added`;
    },
  });
}

async function viewPet(id, tab) {
  const p = await run(() => api('pet_get', { id }));
  const rec = p.records, vac = p.vaccinations, ap = p.appointments, docs = p.documents || [];
  const canEdit = isStaff() || Number(p.owner_id) === Session.user.id;
  const t = tab || (rec ? 'rec' : 'vac');
  const body = openView(p.name, `
    <div class="d-flex gap-3 align-items-center mb-3 flex-wrap">
      ${p.photo_id ? `<a href="file.php?id=${p.photo_id}" target="_blank"><img class="pet-hero-photo" src="file.php?id=${p.photo_id}" alt="${esc(p.name)}"></a>` : petAvatar(p.species)}
      <div class="flex-grow-1"><div class="h5 mb-0">${esc(p.name)} ${p.status !== 'active' ? statusBadge(p.status) : ''}</div>
      <div class="text-muted small">${esc(p.species)}${p.breed ? ' · ' + esc(p.breed) : ''}${p.sex ? ' · ' + esc(p.sex) : ''}${p.date_of_birth ? ' · ' + petAge(p.date_of_birth) : ''}${p.weight_kg ? ' · ' + esc(p.weight_kg) + ' kg' : ''}${p.microchip ? ' · Chip ' + esc(p.microchip) : ''}</div>
      ${isStaff() ? `<div class="small">Owner: <strong>${esc(p.owner.name)}</strong> · ${esc(p.owner.phone || '')}${realEmail(p.owner.email) ? ' · ' + esc(p.owner.email) : ''}${p.balance > 0 && can('billing') ? ` · <a class="text-danger fw-semibold" href="#payments?tab=unpaid&owner=${p.owner.id}">Owes ${money(p.balance)}</a>` : ''}</div>
        ${p.owner.emergency_name || p.owner.emergency_phone ? `<div class="small text-muted">Emergency contact: ${esc(p.owner.emergency_name || '')} ${esc(p.owner.emergency_phone || '')}</div>` : ''}` : ''}
      ${canEdit ? `<button class="btn btn-link btn-sm p-0" data-act="petPhoto" data-id="${p.id}"><i class="fa-solid fa-camera me-1"></i>${p.photo_id ? 'Change photo' : 'Add photo'}</button>` : ''}</div>
    </div>
    ${petAlerts(p)}
    ${p.medications || p.diet ? `<div class="small mb-2">${p.medications ? `<div><i class="fa-solid fa-pills me-1 text-brand"></i><strong>Medication:</strong> ${esc(p.medications)}</div>` : ''}${p.diet ? `<div><i class="fa-solid fa-bowl-food me-1 text-brand"></i><strong>Diet:</strong> ${esc(p.diet)}</div>` : ''}</div>` : ''}
    ${p.notes ? `<div class="alert alert-light border small py-2"><i class="fa-solid fa-circle-info me-1"></i>${esc(p.notes)}</div>` : ''}
    <div class="d-flex flex-wrap gap-2 mb-3">
      ${p.status === 'active' ? btn('bookPet', p.id, 'Book appointment', 'btn-brand', 'fa-calendar-plus') : ''}
      ${can('medical.write') ? btn('addRecord', p.id, 'Add record', 'btn-teal', 'fa-notes-medical') : ''}${can('treatment.write') ? btn('addVacc', p.id, 'Add vaccine / treatment', 'btn-teal', 'fa-syringe') : ''}
      <div class="btn-group"><button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="fa-solid fa-print me-1"></i>Print</button>
        <ul class="dropdown-menu"><li><a class="dropdown-item" target="_blank" href="certificate.php?pet=${p.id}">Vaccination certificate</a></li>
        ${rec ? `<li><a class="dropdown-item" target="_blank" href="certificate.php?pet=${p.id}&type=health">Full health summary</a></li>` : ''}</ul></div>
      ${canEdit && (!isStaff() || can('appointments.manage') || can('treatment.write')) ? btn('petCard', p.id, p.card_token ? 'Health card & QR' : 'Health card', 'btn-light', 'fa-qrcode') : ''}
    </div>
    <ul class="nav nav-tabs mb-3">
      ${rec ? `<li class="nav-item"><button class="nav-link ${t === 'rec' ? 'active' : ''}" data-bs-toggle="tab" data-bs-target="#pt-rec">Health records (${rec.length})</button></li>` : ''}
      <li class="nav-item"><button class="nav-link ${t === 'vac' ? 'active' : ''}" data-bs-toggle="tab" data-bs-target="#pt-vac">Vaccines & treatments (${vac.length})</button></li>
      <li class="nav-item"><button class="nav-link ${t === 'app' ? 'active' : ''}" data-bs-toggle="tab" data-bs-target="#pt-app">Visits (${ap.length})</button></li>
      <li class="nav-item"><button class="nav-link ${t === 'doc' ? 'active' : ''}" data-bs-toggle="tab" data-bs-target="#pt-doc">Documents (${docs.length})</button></li>
      <li class="nav-item"><button class="nav-link ${t === 'wt' ? 'active' : ''}" data-bs-toggle="tab" data-bs-target="#pt-wt">Weight</button></li>
    </ul>
    <div class="tab-content">
      ${rec ? `<div class="tab-pane fade ${t === 'rec' ? 'show active' : ''}" id="pt-rec">${rec.length ? rec.map(recordCard).join('') : emptyHTML('No health records yet', 'fa-notes-medical')}</div>` : ''}
      <div class="tab-pane fade ${t === 'vac' ? 'show active' : ''}" id="pt-vac">${tableHTML([
        { label: 'Vaccine / treatment', render: v => `<strong>${esc(v.vaccine)}</strong> <span class="badge text-bg-light">${esc(TREATMENT_LABEL[v.kind] || 'Vaccine')}</span>${v.batch_no ? `<div class="small text-muted">Batch ${esc(v.batch_no)}</div>` : ''}` },
        { label: 'Given', render: v => fmtDate(v.date_given) },
        { label: 'Next due', render: v => v.next_due ? fmtDate(v.next_due) : '—' },
        { label: 'Status', render: v => statusBadge(v.due_status) },
      ], vac, 'No vaccinations recorded')}</div>
      <div class="tab-pane fade ${t === 'app' ? 'show active' : ''}" id="pt-app">${tableHTML([
        { label: 'Date', render: a => `${fmtDate(a.appt_date)} ${esc(String(a.appt_time).slice(0, 5))}` },
        { label: 'Service', render: a => esc(a.service_name || '') },
        { label: 'Vet', render: a => esc(a.vet_name || '—') },
        { label: 'Status', render: a => statusBadge(a.status) },
        { label: '', cls: 'text-end', render: a => btn('invoice', a.id, '', 'btn-light', 'fa-file-invoice', 'title="Invoice"') },
      ], ap, 'No visits yet')}</div>
      <div class="tab-pane fade ${t === 'doc' ? 'show active' : ''}" id="pt-doc">
        <p class="small text-muted">Lab results, X-rays, referral letters, previous vet records… (PDF or photos, up to 5 MB each).</p>
        ${canEdit ? `<button class="btn btn-sm btn-outline-brand mb-3" data-act="petDocs" data-id="${p.id}"><i class="fa-solid fa-upload me-1"></i>Upload documents</button>` : ''}
        ${docs.length ? `<ul class="list-group">${docs.map(d => `<li class="list-group-item d-flex justify-content-between align-items-center">
          <span>${fileLinkHTML(d)} <small class="text-muted ms-1">${fmtDate(d.created_at)}</small></span>
          ${canEdit ? `<button class="btn btn-sm btn-light text-danger" data-act="delFile" data-id="${d.id}" title="Delete"><i class="fa-solid fa-trash"></i></button>` : ''}</li>`).join('')}</ul>` : emptyHTML('No documents uploaded', 'fa-file')}
      </div>
      <div class="tab-pane fade ${t === 'wt' ? 'show active' : ''}" id="pt-wt"><div class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm"></span></div></div>
    </div>`);
  if (window.afterViewPet) window.afterViewPet(p, body, canEdit);
  ACTIONS.bookPet = pid => bookForm({ petId: pid });
  ACTIONS.addRecord = pid => recordForm(null, pid);
  ACTIONS.addVacc = pid => vaccinationForm(null, pid);
  ACTIONS.invoice = aid => invoiceModal(aid);
  ACTIONS.petPhoto = async pid => {
    const [f] = await pickFiles(PHOTO_ACCEPT);
    if (!f) return;
    await run(() => apiUpload('pet', pid, f, 'photo'), 'Photo updated');
    viewPet(pid);
    if (route.page === 'pets') rerender();
  };
  ACTIONS.petDocs = async pid => {
    const files = await pickFiles(DOC_ACCEPT, true);
    if (!files.length) return;
    await run(() => uploadAll('pet', pid, files), files.length > 1 ? `${files.length} documents uploaded` : 'Document uploaded');
    viewPet(pid, 'doc');
  };
  ACTIONS.delFile = async fid => {
    if (!(await confirmBox('Delete this document?', 'Delete'))) return;
    await run(() => api('attachment_delete', { id: fid }), 'Document deleted');
    viewPet(id, 'doc');
  };
  return body;
}

function recordCard(r) {
  const tools = isStaff() ? `${r.locked ? '<span class="badge text-bg-light" title="Locked 24 hours after it was written. Only an administrator can correct it."><i class="fa-solid fa-lock me-1"></i>Locked</span>' : ''}
    ${Number(r.version_count) ? `<button class="btn btn-link btn-sm p-0" data-act="recHistory" data-id="${r.id}"><i class="fa-solid fa-clock-rotate-left me-1"></i>Edited ${r.version_count}×</button>` : ''}` : '';
  return `<div class="border rounded-3 p-3 mb-2">
    <div class="d-flex justify-content-between flex-wrap gap-2"><div><strong>${esc(r.title)}</strong> <span class="badge bg-light text-dark text-capitalize">${esc(r.record_type)}</span> ${tools}</div>
    <small class="text-muted">${fmtDate(r.record_date)}${r.vet_name ? ' · ' + esc(r.vet_name) : ''}</small></div>
    ${r.diagnosis ? `<div class="small mt-2"><strong>Findings:</strong> ${esc(r.diagnosis)}</div>` : ''}
    ${r.treatment ? `<div class="small"><strong>Treatment:</strong> ${esc(r.treatment)}</div>` : ''}
    ${r.prescription ? `<div class="small" style="white-space:pre-line"><strong>Prescription:</strong> ${esc(r.prescription)}</div>` : ''}
    ${r.weight_kg ? `<div class="small text-muted">Weight: ${esc(r.weight_kg)} kg</div>` : ''}
    ${Number(r.file_count) ? `<div class="mt-2" data-files-for="${r.id}"><button class="btn btn-sm btn-light" data-act="recFiles" data-id="${r.id}"><i class="fa-solid fa-paperclip me-1"></i>${r.file_count} attached file${Number(r.file_count) > 1 ? 's' : ''}</button></div>` : ''}
  </div>`;
}

/** Show every earlier version of a health record. */
async function showRecordHistory(id) {
  const h = await run(() => api('record_history', { id }));
  const field = (label, v) => v ? `<div class="small"><strong>${label}:</strong> ${esc(v)}</div>` : '';
  const block = (d, head) => `<div class="border rounded-3 p-3 mb-2"><div class="small text-muted mb-1">${head}</div>
    <div><strong>${esc(d.title)}</strong> <span class="badge bg-light text-dark text-capitalize">${esc(d.record_type)}</span> · ${fmtDate(d.record_date)}</div>
    ${field('Findings', d.diagnosis)}${field('Treatment', d.treatment)}${field('Prescription', d.prescription)}${d.weight_kg ? field('Weight', d.weight_kg + ' kg') : ''}</div>`;
  openView('Record history', `<p class="small text-muted">Every change to a health record is kept. Newest first.</p>` +
    block(h.record, '<span class="badge text-bg-success">Current version</span>') +
    h.versions.map(v => block(v.data, `Before the change made by <strong>${esc(v.changed_by_name || 'someone')}</strong> on ${fmtDateTime(v.changed_at)}${v.reason ? ` — “${esc(v.reason)}”` : ''}`)).join(''));
}

/** Expand a record's attachment list in place (works on any page). */
async function showRecordFiles(id) {
  const box = document.querySelector(`[data-files-for="${id}"]`);
  const files = await run(() => api('attachments', { entity_type: 'record', entity_id: id }));
  if (box) box.innerHTML = files.map(f => `<div class="small">${fileLinkHTML(f)}</div>`).join('') || '<span class="small text-muted">No files</span>';
}

// ---------------------------------------------------------------------------
// Calendar (day / week / month)
// ---------------------------------------------------------------------------
const isoOf = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const parseISO = iso => new Date(iso + 'T00:00:00');
const addDays = (iso, n) => { const d = parseISO(iso); d.setDate(d.getDate() + n); return isoOf(d); };

function calRange(view, anchor) {
  const d = parseISO(anchor);
  if (view === 'day') return [anchor, anchor];
  if (view === 'week') {
    const start = addDays(anchor, -((d.getDay() + 6) % 7)); // weeks start on Monday
    return [start, addDays(start, 6)];
  }
  const first = new Date(d.getFullYear(), d.getMonth(), 1);
  const gridStart = addDays(isoOf(first), -((first.getDay() + 6) % 7));
  return [gridStart, addDays(gridStart, 41)];
}

function calChip(a) {
  const cat = a.service_category || 'other';
  return `<button class="cal-chip cat-${esc(cat)} st-${esc(a.status)}" data-act="calOpen" data-id="${a.id}" title="${esc(`${a.appt_time} ${a.pet_name} – ${a.service_name || ''} (${a.owner_name})`)}">
    <span class="cal-time">${esc(a.appt_time)}</span> ${esc(a.pet_name)}${petAlerts(a, true)}<span class="cal-svc">${esc(a.service_name || '')}</span></button>`;
}

async function pageCalendar() {
  const view = ['day', 'week', 'month'].includes(route.params.get('view')) ? route.params.get('view') : 'week';
  const anchor = route.params.get('date') || todayISO();
  const vetFilter = route.params.get('vet') || '';
  const [from, to] = calRange(view, anchor);
  const [list, vets] = await Promise.all([api('appointments_list', { from, to, vet_id: vetFilter }), getVets()]);
  const appts = list.filter(a => a.status !== 'cancelled').sort((x, y) => (x.appt_date + x.appt_time).localeCompare(y.appt_date + y.appt_time));
  const byDay = {};
  appts.forEach(a => { (byDay[a.appt_date] = byDay[a.appt_date] || []).push(a); });
  const step = view === 'day' ? 1 : view === 'week' ? 7 : null;
  const prev = step ? addDays(anchor, -step) : isoOf(new Date(parseISO(anchor).getFullYear(), parseISO(anchor).getMonth() - 1, 1));
  const next = step ? addDays(anchor, step) : isoOf(new Date(parseISO(anchor).getFullYear(), parseISO(anchor).getMonth() + 1, 1));
  const title = view === 'day' ? fmtDate(anchor, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    : view === 'week' ? `${fmtDate(from, { day: 'numeric', month: 'short' })} – ${fmtDate(to, { day: 'numeric', month: 'short', year: 'numeric' })}`
    : parseISO(anchor).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
  const link = (params) => { const p = new URLSearchParams({ view, date: anchor, vet: vetFilter, ...params }); [...p.keys()].forEach(k => !p.get(k) && p.delete(k)); return '#calendar?' + p.toString(); };
  const closed = String(Session.clinic.closed_days || '').split(',').filter(Boolean);
  let grid;
  if (view === 'day') {
    // A column for each vet (and one for unassigned), a row for each half hour of opening time.
    const cols = vetFilter ? vets.filter(v => String(v.id) === vetFilter) : [...vets, { id: '', name: 'Not assigned' }];
    const [oh, om] = String(Session.clinic.open_time || '08:00').split(':').map(Number);
    const [ch, cm] = String(Session.clinic.close_time || '19:00').split(':').map(Number);
    const rows = [];
    for (let t = oh * 60 + om; t < ch * 60 + cm; t += 30) rows.push(`${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`);
    const day = byDay[anchor] || [];
    const slotOf = a => { const [h, m] = a.appt_time.split(':').map(Number); const t = h * 60 + m; return `${String(Math.floor(t / 60)).padStart(2, '0')}:${t % 60 < 30 ? '00' : '30'}`; };
    const outside = day.filter(a => !rows.includes(slotOf(a)));
    grid = `<div class="table-responsive"><table class="table cal-day mb-0"><thead><tr><th class="cal-tcol"></th>${cols.map(c => `<th>${esc(c.name)}</th>`).join('')}</tr></thead><tbody>
      ${rows.map(r => `<tr><th class="cal-tcol">${r}</th>${cols.map(c => `<td>${day.filter(a => slotOf(a) === r && String(a.vet_id || '') === String(c.id)).map(calChip).join('')}</td>`).join('')}</tr>`).join('')}
      </tbody></table></div>${outside.length ? `<div class="p-3 small"><strong>Outside opening hours:</strong> ${outside.map(calChip).join(' ')}</div>` : ''}`;
  } else if (view === 'week') {
    const days = [...Array(7)].map((_, i) => addDays(from, i));
    grid = `<div class="cal-week">${days.map(d => `<div class="cal-col ${d === todayISO() ? 'today' : ''} ${closed.includes(String(parseISO(d).getDay())) ? 'closed' : ''}">
      <a class="cal-head" href="${link({ view: 'day', date: d })}">${fmtDate(d, { weekday: 'short', day: 'numeric', month: 'short' })}${(byDay[d] || []).length ? `<span class="cal-count">${byDay[d].length}</span>` : ''}</a>
      ${(byDay[d] || []).map(calChip).join('') || '<div class="small text-muted text-center py-2">—</div>'}</div>`).join('')}</div>`;
  } else {
    const month = parseISO(anchor).getMonth();
    const days = [...Array(42)].map((_, i) => addDays(from, i));
    grid = `<div class="cal-month">${['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(n => `<div class="cal-mhead">${n}</div>`).join('')}
      ${days.map(d => { const items = byDay[d] || []; return `<div class="cal-cell ${parseISO(d).getMonth() !== month ? 'other' : ''} ${d === todayISO() ? 'today' : ''}">
        <a class="cal-date" href="${link({ view: 'day', date: d })}">${parseISO(d).getDate()}</a>
        ${items.slice(0, 3).map(calChip).join('')}${items.length > 3 ? `<a class="small" href="${link({ view: 'day', date: d })}">+${items.length - 3} more</a>` : ''}</div>`; }).join('')}</div>`;
  }
  $view.innerHTML = pageHead('Calendar', can('appointments.manage') ? `<button class="btn btn-brand" data-act="walkin"><i class="fa-solid fa-person-walking-arrow-right me-1"></i>New walk-in</button><button class="btn btn-outline-brand" data-act="book"><i class="fa-solid fa-plus me-1"></i>New appointment</button>` : '') + `
    <div class="card-x p-3 mb-3 d-flex flex-wrap align-items-center gap-2">
      <div class="btn-group"><a class="btn btn-sm btn-light" href="${link({ date: prev })}" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></a>
        <a class="btn btn-sm btn-light" href="${link({ date: todayISO() })}">Today</a>
        <a class="btn btn-sm btn-light" href="${link({ date: next })}" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></a></div>
      <strong class="mx-2">${esc(title)}</strong>
      <div class="ms-auto d-flex flex-wrap gap-2">
        <select class="form-select form-select-sm" id="calVet" style="width:auto"><option value="">All vets</option>${vets.map(v => `<option value="${v.id}" ${String(v.id) === vetFilter ? 'selected' : ''}>${esc(v.name)}</option>`).join('')}</select>
        <div class="btn-group">${['day', 'week', 'month'].map(v => `<a class="btn btn-sm ${v === view ? 'btn-brand' : 'btn-light'}" href="${link({ view: v })}">${v[0].toUpperCase() + v.slice(1)}</a>`).join('')}</div>
      </div>
    </div>
    <div class="card-x cal-wrap">${grid}</div>
    <div class="cal-legend small text-muted mt-2">${Object.entries(SERVICE_CATEGORY).map(([k, l]) => `<span><i class="cal-dot cat-${k}"></i>${l}</span>`).join('')}<span><i class="fa-solid fa-triangle-exclamation text-danger"></i> health alert</span></div>`;
  document.getElementById('calVet').onchange = e => { location.hash = link({ vet: e.target.value }).slice(1); };
  ACTIONS.book = () => bookForm({});
  ACTIONS.walkin = () => walkinForm();
  ACTIONS.calOpen = id => {
    const a = appts.find(x => String(x.id) === String(id));
    openView(`${a.pet_name} · ${fmtDate(a.appt_date, { weekday: 'short', day: 'numeric', month: 'short' })} ${a.appt_time}`,
      `${petAlerts(a)}<div class="mb-2"><strong>${esc(a.service_name || '')}</strong> · ${statusBadge(a.status)}</div>
       <div class="small mb-1">Pet parent: ${esc(a.owner_name)} · ${esc(a.owner_phone || '')}</div>
       <div class="small mb-1">Vet: ${esc(a.vet_name || 'Not assigned')}</div>
       ${a.reason ? `<div class="small mb-2">Reason: ${esc(a.reason)}</div>` : ''}
       <div class="small mb-3">Payment: ${apptPayCell(a)}</div>${apptButtons(a)}`, 'modal-md');
  };
  registerApptActions(appts);
}

// ---------------------------------------------------------------------------
// Appointments
// ---------------------------------------------------------------------------
function apptPayCell(a) {
  if (['cancelled', 'no_show'].includes(a.status) || !a.price) return '<span class="text-muted">—</span>';
  if (a.balance <= 0) return `<span class="text-success small fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Paid</span>`;
  return `<span class="small">${money(a.balance)} due</span>`;
}

function apptButtons(a) {
  const b = [];
  const open = ['pending', 'confirmed'].includes(a.status);
  const payable = a.balance > 0 && !['cancelled', 'no_show'].includes(a.status);
  if (isOwner()) {
    if (payable) b.push(btn('pay', a.id, 'Pay', 'btn-brand', 'fa-wallet'));
    if (a.price > 0) b.push(btn('invoice', a.id, '', 'btn-light', 'fa-file-invoice', 'title="Invoice"'));
    if (open) b.push(btn('reschedule', a.id, '', 'btn-light', 'fa-clock-rotate-left', 'title="Reschedule"'));
    if (open) b.push(btn('cancel', a.id, '', 'btn-light text-danger', 'fa-xmark', 'title="Cancel"'));
  } else {
    const mg = can('appointments.manage');
    const isToday = a.appt_date === todayISO();
    if (a.status === 'pending' && mg) b.push(btn('confirm', a.id, 'Confirm', 'btn-teal', 'fa-check'));
    if (['pending', 'confirmed'].includes(a.status) && isToday && can('checkin')) b.push(btn('checkin', a.id, 'Arrived', 'btn-brand', 'fa-door-open'));
    if (['confirmed', 'checked_in'].includes(a.status) && mg) b.push(btn('start', a.id, 'Start', 'btn-light', 'fa-play'));
    if (['confirmed', 'checked_in', 'in_progress'].includes(a.status) && mg) b.push(btn('complete', a.id, 'Complete', 'btn-teal', 'fa-flag-checkered'));
    if (payable && can('billing')) b.push(btn('cash', a.id, '', 'btn-light', 'fa-money-bill', 'title="Record payment"'));
    b.push(btn('invoice', a.id, '', 'btn-light', 'fa-file-invoice', 'title="Invoice & charges"'));
    const more = [];
    if (open && mg) more.push(['reschedule', 'Reschedule']);
    if ((open || ['checked_in', 'in_progress'].includes(a.status)) && mg) more.push(['assign', 'Assign vet']);
    if (payable && can('billing')) more.push(['pay', 'Send M-Pesa / card request']);
    if (a.status === 'checked_in' && can('checkin')) more.push(['uncheckin', 'Undo arrival']);
    if (a.status === 'confirmed' && mg) more.push(['noshow', 'Mark as no-show']);
    if ((open || ['checked_in', 'in_progress'].includes(a.status)) && mg) more.push(['cancel', 'Cancel appointment']);
    if (a.status === 'cancelled' && mg) more.push(['reopen', 'Reopen']);
    if (more.length) {
      b.push(`<div class="btn-group"><button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-label="More"><i class="fa-solid fa-ellipsis"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">${more.map(([act, l]) => `<li><button class="dropdown-item ${act === 'cancel' ? 'text-danger' : ''}" data-act="${act}" data-id="${a.id}">${l}</button></li>`).join('')}</ul></div>`);
    }
  }
  return `<div class="d-flex gap-1 justify-content-end flex-wrap">${b.join('')}</div>`;
}

function apptTable(list, emptyText) {
  return tableHTML([
    { label: 'When', render: a => `<strong>${fmtDate(a.appt_date, { weekday: 'short', day: 'numeric', month: 'short' })}</strong><div class="small text-muted">${esc(a.appt_time)}${a.source === 'walk_in' ? ' · <span class="badge text-bg-light">Walk-in</span>' : ''}</div>` },
    { label: 'Pet', render: a => `${esc(a.pet_name)}${isStaff() ? petAlerts(a, true) + demoTag(a) + `<div class="small text-muted">${esc(a.owner_name)} · ${esc(a.owner_phone || '')}</div>` : ''}` },
    { label: 'Service', render: a => `${esc(a.service_name || '—')}${a.reason ? `<div class="small text-muted text-truncate" style="max-width:220px" title="${esc(a.reason)}">${esc(a.reason)}</div>` : ''}` },
    { label: 'Vet', render: a => a.vet_name ? esc(a.vet_name) : '<span class="text-muted small">Any available</span>' },
    { label: 'Status', render: a => statusBadge(a.status) },
    { label: 'Payment', render: apptPayCell },
    { label: '', cls: 'text-end', render: apptButtons },
  ], list, emptyText);
}

function registerApptActions(list) {
  const byId = id => list.find(a => String(a.id) === String(id));
  const setStatus = async (id, status, msg) => { await run(() => api('appointment_status', { id, status }), msg); rerender(); };
  ACTIONS.pay = id => payModal(byId(id));
  ACTIONS.confirm = id => setStatus(id, 'confirmed', 'Appointment confirmed');
  ACTIONS.start = id => setStatus(id, 'in_progress', 'Appointment started');
  ACTIONS.checkin = id => setStatus(id, 'checked_in', `${byId(id).pet_name} is in the waiting room`);
  ACTIONS.uncheckin = id => setStatus(id, 'confirmed', 'Arrival undone');
  ACTIONS.reopen = id => setStatus(id, 'pending', 'Appointment reopened');
  ACTIONS.noshow = async id => { if (await confirmBox('Mark this appointment as a no-show?', 'Mark no-show')) setStatus(id, 'no_show', 'Marked as no-show'); };
  ACTIONS.cancel = async id => {
    const a = byId(id);
    if (await confirmBox(`Cancel ${a.pet_name}'s ${a.service_name} on ${fmtDate(a.appt_date)} at ${a.appt_time}?`, 'Cancel appointment')) setStatus(id, 'cancelled', 'Appointment cancelled');
  };
  ACTIONS.complete = id => completeForm(byId(id));
  ACTIONS.reschedule = id => rescheduleForm(byId(id));
  ACTIONS.assign = id => assignForm(byId(id));
  ACTIONS.cash = id => cashForm(byId(id));
}

async function pageAppointments() {
  const tab = route.params.get('tab') || 'upcoming';
  const scope = route.params.get('scope') || (Session.user.role === 'vet' ? 'mine' : 'all');
  const date = route.params.get('date') || '';
  const filter = { status: tab === 'all' ? '' : tab === 'waiting' ? 'checked_in' : tab, date };
  if (Session.user.role === 'vet') filter.scope = scope;
  const list = await api('appointments_list', filter);
  if (tab === 'upcoming') list.sort((x, y) => (x.appt_date + x.appt_time).localeCompare(y.appt_date + y.appt_time));
  const heads = isOwner() ? `<button class="btn btn-brand" data-act="book"><i class="fa-solid fa-plus me-1"></i>Book appointment</button>`
    : can('appointments.manage') ? `<button class="btn btn-brand" data-act="walkin"><i class="fa-solid fa-person-walking-arrow-right me-1"></i>New walk-in</button><button class="btn btn-outline-brand" data-act="book"><i class="fa-solid fa-plus me-1"></i>New appointment</button>` : '';
  $view.innerHTML = pageHead('Appointments', heads) + `
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
      ${tabsHTML('tab', [['upcoming', 'Upcoming'], ...(isStaff() ? [['waiting', 'Waiting room']] : []), ['pending', 'Awaiting confirmation'], ['completed', 'Completed'], ['cancelled', 'Cancelled'], ['all', 'All']], tab)}
      ${isStaff() ? `<div class="d-flex gap-2 mb-3">
        ${Session.user.role === 'vet' ? `<select class="form-select form-select-sm" id="scopeSel" style="width:auto"><option value="mine" ${scope === 'mine' ? 'selected' : ''}>My patients + unassigned</option><option value="all" ${scope === 'all' ? 'selected' : ''}>Whole clinic</option></select>` : ''}
        <input type="date" class="form-control form-control-sm" id="dateSel" value="${esc(date)}" style="width:auto" title="Filter by date">
        ${date ? '<button class="btn btn-sm btn-light" id="clearDate">Clear</button>' : ''}</div>` : ''}
    </div>
    <div class="card-x">${apptTable(list, tab === 'upcoming' ? 'No upcoming appointments' : 'No appointments found')}</div>`;
  const setParam = (k, v) => { const p = new URLSearchParams(route.params); v ? p.set(k, v) : p.delete(k); location.hash = 'appointments?' + p.toString(); };
  ACTIONS.__tab = v => setParam('tab', v);
  document.getElementById('scopeSel')?.addEventListener('change', e => setParam('scope', e.target.value));
  document.getElementById('dateSel')?.addEventListener('change', e => setParam('date', e.target.value));
  document.getElementById('clearDate')?.addEventListener('click', () => setParam('date', ''));
  ACTIONS.book = () => bookForm({});
  ACTIONS.walkin = () => walkinForm();
  registerApptActions(list);
  if (route.params.get('book')) {
    const service = route.params.get('service');
    const pet = route.params.get('pet');
    setHashQuiet('appointments');
    bookForm({ serviceId: service, petId: pet });
  }
}

async function bookForm({ serviceId, petId }) {
  const [services, vets] = await Promise.all([getServices(), getVets()]);
  const active = services.filter(s => Number(s.active) === 1);
  let pets = [];
  const fields = [];
  if (isStaff()) {
    const owners = await getOwners();
    let ownerId = '';
    if (petId) {
      const p = await api('pet_get', { id: petId });
      ownerId = p.owner_id;
      pets = await api('pets_list', { owner_id: ownerId });
    }
    fields.push({ name: 'owner_id', label: 'Client', type: 'select', required: true, placeholder: 'Choose client…', options: owners.map(o => [o.id, `${o.name} · ${o.phone || realEmail(o.email)}`]), value: ownerId });
  } else {
    pets = await getMyPets();
    if (!pets.filter(p => p.status === 'active').length) {
      toast('Add your pet first, then book an appointment.', 'info');
      location.hash = 'pets?add=1';
      return;
    }
  }
  const petOptions = list => list.filter(p => p.status === 'active').map(p => [p.id, `${p.name} (${p.species})`]);
  fields.push(
    { name: 'pet_id', label: 'Pet', type: 'select', required: true, placeholder: 'Choose pet…', options: petOptions(pets), value: petId || (pets.length === 1 ? pets[0].id : ''), col: 'col-md-6' },
    { name: 'service_id', label: 'Service', type: 'select', required: true, placeholder: 'Choose service…', options: active.map(s => [s.id, `${s.name} — ${money(s.price)}`]), value: serviceId || '', col: 'col-md-6' },
    { name: 'vet_id', label: 'Veterinarian', type: 'select', placeholder: 'Any available vet', options: vets.map(v => [v.id, v.name + (v.specialization ? ` (${v.specialization})` : '')]), col: 'col-md-6' },
    { name: 'date', label: 'Date', type: 'date', required: true, min: todayISO(), max: todayISO(180), value: todayISO(1), col: 'col-md-6' },
    { name: 'time', type: 'hidden' },
    { name: 'slots', type: 'html', html: '<label class="form-label">Time <span class="text-danger">*</span></label><div id="slotBox" class="slot-grid"></div>' },
    { name: 'reason', label: 'Reason for visit / symptoms', type: 'textarea', rows: 2, placeholder: 'Optional, but helps the vet prepare' },
  );
  openForm({
    title: isOwner() ? 'Book an appointment' : 'New appointment',
    fields, submitText: 'Book appointment',
    intro: Session.clinic.hours ? `<div class="small text-muted"><i class="fa-regular fa-clock me-1"></i>Opening hours: ${esc(Session.clinic.hours)}</div>` : '',
    onRender: form => {
      const loadSlots = async () => {
        const box = form.querySelector('#slotBox');
        form.elements.time.value = '';
        if (!form.elements.date.value) { box.innerHTML = ''; return; }
        box.innerHTML = '<span class="small text-muted"><span class="spinner-border spinner-border-sm"></span> Checking availability…</span>';
        try {
          const slots = await api('appointment_slots', { date: form.elements.date.value, vet_id: form.elements.vet_id.value });
          box.innerHTML = slots.length ? slots.map(t => `<button type="button" class="btn btn-outline-secondary" data-slot="${t}">${t}</button>`).join('')
            : '<span class="small text-danger">No free times on this day. Please choose another date.</span>';
        } catch (e) { box.innerHTML = `<span class="small text-danger">${esc(e.message)}</span>`; }
      };
      form.querySelector('#slotBox').onclick = e => {
        const b = e.target.closest('[data-slot]');
        if (!b) return;
        form.querySelectorAll('[data-slot]').forEach(x => x.classList.replace('btn-brand', 'btn-outline-secondary'));
        b.classList.replace('btn-outline-secondary', 'btn-brand');
        form.elements.time.value = b.dataset.slot;
      };
      form.elements.date.onchange = loadSlots;
      form.elements.vet_id.onchange = loadSlots;
      if (form.elements.owner_id) {
        form.elements.owner_id.onchange = async () => {
          const sel = form.elements.pet_id;
          sel.innerHTML = '<option value="">Loading…</option>';
          const list = form.elements.owner_id.value ? await api('pets_list', { owner_id: form.elements.owner_id.value }) : [];
          const opts = petOptions(list);
          sel.innerHTML = `<option value="">${opts.length ? 'Choose pet…' : 'This client has no pets yet'}</option>` + opts.map(([v, l]) => `<option value="${v}">${esc(l)}</option>`).join('');
          if (opts.length === 1) sel.value = opts[0][0];
        };
      }
      loadSlots();
    },
    onSubmit: async d => {
      if (!d.time) throw new Error('Please pick a time');
      const a = await api('appointment_book', d);
      if (route.page === 'appointments') rerender(); else location.hash = 'appointments';
      return isOwner() ? `Booked! ${a.pet_name}'s ${a.service_name} on ${fmtDate(a.appt_date)} at ${a.appt_time}. We'll confirm shortly.` : 'Appointment booked';
    },
  });
}

/** Front desk: register a pet that walked in without a booking (new or existing client). */
async function walkinForm() {
  const [services, vets, owners] = await Promise.all([getServices(), getVets(), getOwners()]);
  const active = services.filter(x => Number(x.active) === 1);
  openForm({
    title: 'New walk-in',
    intro: '<div class="small text-muted">For a pet that arrived without a booking. The visit starts now and goes straight into the waiting room.</div>',
    fields: [
      { name: 'owner_id', label: 'Pet parent', type: 'select', placeholder: '➕ New pet parent (fill in below)', options: owners.map(o => [o.id, `${o.name} · ${o.phone || realEmail(o.email)}`]), col: 'col-md-12' },
      { type: 'html', name: '_newowner', html: `<div class="row g-3" id="newOwnerBox">
        ${fieldHTML({ name: 'owner_name', label: 'Pet parent name', required: true, col: 'col-md-4' })}
        ${fieldHTML({ name: 'owner_phone', label: 'Phone', type: 'tel', required: true, col: 'col-md-4', placeholder: '07XX XXX XXX' })}
        ${fieldHTML({ name: 'owner_email', label: 'Email (optional)', type: 'email', col: 'col-md-4' })}</div>` },
      { name: 'pet_id', label: 'Pet', type: 'select', placeholder: '➕ New pet (fill in below)', options: [], col: 'col-md-12' },
      { type: 'html', name: '_newpet', html: `<div class="row g-3" id="newPetBox">
        ${fieldHTML({ name: 'pet_name', label: 'Pet name', required: true, col: 'col-md-4' })}
        ${fieldHTML({ name: 'species', label: 'Species', type: 'select', options: SPECIES, col: 'col-md-4' }, 'Dog')}
        ${fieldHTML({ name: 'breed', label: 'Breed', col: 'col-md-4' })}</div>` },
      { name: 'service_id', label: 'Service', type: 'select', required: true, placeholder: 'Choose service…', options: active.map(x => [x.id, `${x.name} — ${money(x.price)}`]), col: 'col-md-6' },
      { name: 'vet_id', label: 'Veterinarian', type: 'select', placeholder: 'Next available', options: vets.map(v => [v.id, v.name]), col: 'col-md-6' },
      { name: 'reason', label: 'Reason for visit / symptoms', type: 'textarea', rows: 2 },
    ],
    submitText: 'Check in now',
    onRender: form => {
      const ownerBox = form.querySelector('#newOwnerBox'), petBox = form.querySelector('#newPetBox');
      const show = (box, on) => { box.classList.toggle('d-none', !on); box.querySelectorAll('input,select').forEach(i => { i.disabled = !on; }); };
      const sync = () => { show(ownerBox, !form.elements.owner_id.value); show(petBox, !form.elements.pet_id.value); };
      form.elements.owner_id.onchange = async () => {
        const sel = form.elements.pet_id;
        const list = form.elements.owner_id.value ? await api('pets_list', { owner_id: form.elements.owner_id.value }) : [];
        sel.innerHTML = '<option value="">➕ New pet (fill in below)</option>' + list.filter(p => p.status === 'active').map(p => `<option value="${p.id}">${esc(p.name)} (${esc(p.species)})</option>`).join('');
        if (list.length === 1) sel.value = list[0].id;
        sync();
      };
      form.elements.pet_id.onchange = sync;
      sync();
    },
    onSubmit: async d => {
      const need = [];
      if (!d.owner_id && (!d.owner_name || !d.owner_phone)) need.push('pet parent name and phone');
      if (!d.pet_id && !d.pet_name) need.push('pet name');
      if (need.length) throw new Error('Please fill in the ' + need.join(' and '));
      if (d.owner_id) { delete d.owner_name; delete d.owner_phone; delete d.owner_email; }
      if (d.pet_id) { delete d.pet_name; delete d.species; delete d.breed; }
      const a = await api('walkin_create', d);
      delete cache.owners;
      if (['appointments', 'overview', 'calendar'].includes(route.page)) rerender();
      return `${a.pet_name} is checked in and waiting`;
    },
  });
}

function slotPicker(form, getVet, ignoreId) {
  const load = async () => {
    const box = form.querySelector('#slotBox');
    form.elements.time.value = '';
    box.innerHTML = '<span class="small text-muted">Checking availability…</span>';
    const slots = await api('appointment_slots', { date: form.elements.date.value, vet_id: getVet() });
    box.innerHTML = slots.length ? slots.map(t => `<button type="button" class="btn btn-outline-secondary" data-slot="${t}">${t}</button>`).join('')
      : '<span class="small text-danger">No free times on this day.</span>';
  };
  form.querySelector('#slotBox').onclick = e => {
    const b = e.target.closest('[data-slot]');
    if (!b) return;
    form.querySelectorAll('[data-slot]').forEach(x => x.classList.replace('btn-brand', 'btn-outline-secondary'));
    b.classList.replace('btn-outline-secondary', 'btn-brand');
    form.elements.time.value = b.dataset.slot;
  };
  form.elements.date.onchange = load;
  load();
}

function rescheduleForm(a) {
  openForm({
    title: `Reschedule ${a.pet_name}'s appointment`, size: 'modal-md',
    intro: `<div class="small text-muted">Currently: ${fmtDate(a.appt_date)} at ${esc(a.appt_time)}${a.vet_name ? ' with ' + esc(a.vet_name) : ''}</div>`,
    fields: [
      { name: 'date', label: 'New date', type: 'date', required: true, min: todayISO(), value: a.appt_date >= todayISO() ? a.appt_date : todayISO(1) },
      { name: 'time', type: 'hidden' },
      { name: 'slots', type: 'html', html: '<label class="form-label">New time</label><div id="slotBox" class="slot-grid"></div>' },
    ],
    submitText: 'Reschedule',
    onRender: form => slotPicker(form, () => a.vet_id || '', a.id),
    onSubmit: async d => {
      if (!d.time) throw new Error('Please pick a time');
      await api('appointment_reschedule', { id: a.id, date: d.date, time: d.time });
      rerender();
      return 'Appointment rescheduled';
    },
  });
}

async function assignForm(a) {
  const vets = await getVets();
  openForm({
    title: 'Assign veterinarian', size: 'modal-md',
    fields: [{ name: 'vet_id', label: 'Veterinarian', type: 'select', placeholder: 'Unassigned', options: vets.map(v => [v.id, v.name]), value: a.vet_id }],
    onSubmit: async d => { await api('appointment_assign', { id: a.id, vet_id: d.vet_id }); rerender(); return 'Vet assigned'; },
  });
}

const getProducts = () => api('products_list', { active: 1 });
const productLabel = pr => `${pr.name} — ${money(pr.price)}/${pr.unit} (${Number(pr.stock_qty)} in stock)`;

async function completeForm(a) {
  const products = await getProducts();
  const productOpts = products.filter(pr => pr.stock_qty > 0).map(pr => `<option value="${pr.id}">${esc(productLabel(pr))}</option>`).join('');
  openForm({
    title: `Complete visit: ${a.pet_name}`,
    intro: `<div class="small text-muted">${esc(a.service_name)} · ${esc(a.owner_name)}${a.reason ? ` · Reason: ${esc(a.reason)}` : ''}</div>`,
    fields: [
      { name: 'vet_notes', label: 'Visit notes (shown to the pet parent)', type: 'textarea', value: a.vet_notes || '' },
      { name: 'meds', type: 'html', html: `<label class="form-label">Medicines &amp; supplies dispensed <span class="text-muted small">(taken from stock and added to the bill)</span></label>
        <div id="medRows"></div>
        ${products.length ? `<button type="button" class="btn btn-sm btn-light" id="addMed"><i class="fa-solid fa-plus me-1"></i>Add medicine</button>` : '<div class="small text-muted">No items in the inventory yet. Add them under Inventory.</div>'}` },
      { name: 'extras', type: 'html', html: `<label class="form-label mt-2">Other charges <span class="text-muted small">(lab tests, procedures…)</span></label>
        <div id="extraRows"></div><button type="button" class="btn btn-sm btn-light" id="addExtra"><i class="fa-solid fa-plus me-1"></i>Add charge</button>` },
      { name: 'add_record', label: 'Also add a health record to the pet file', type: 'checkbox', value: '1' },
      { name: 'title', label: 'Record title', value: a.service_name, col: 'col-md-6' },
      { name: 'record_type', label: 'Type', type: 'select', options: RECORD_TYPES.map(t => [t, t[0].toUpperCase() + t.slice(1)]), value: /vacc/i.test(a.service_name) ? 'vaccination' : /dental/i.test(a.service_name) ? 'dental' : 'checkup', col: 'col-md-3' },
      { name: 'weight_kg', label: 'Weight (kg)', type: 'number', step: '0.01', min: 0, col: 'col-md-3' },
      { name: 'diagnosis', label: 'Findings / diagnosis', type: 'textarea', rows: 2 },
      { name: 'treatment', label: 'Treatment given', type: 'textarea', rows: 2, col: 'col-md-6' },
      { name: 'prescription', label: 'Prescription notes', type: 'textarea', rows: 2, col: 'col-md-6', help: 'Dispensed medicines are added here automatically.' },
      { name: 'files', label: 'Attach lab results / X-rays', type: 'file', multiple: true, accept: DOC_ACCEPT },
    ],
    submitText: 'Mark as completed',
    onRender: form => {
      const toggle = () => ['title', 'record_type', 'weight_kg', 'diagnosis', 'treatment', 'prescription', 'files'].forEach(n => {
        form.elements[n].closest('[class*=col]').classList.toggle('d-none', !form.elements.add_record.checked);
      });
      form.elements.add_record.onchange = toggle;
      toggle();
      const medRows = form.querySelector('#medRows');
      const addMed = form.querySelector('#addMed');
      if (addMed) addMed.onclick = () => {
        medRows.insertAdjacentHTML('beforeend', `<div class="dispense-row" data-med>
          <select class="form-select form-select-sm" data-f="product"><option value="">Choose item…</option>${productOpts}</select>
          <input class="form-control form-control-sm" type="number" min="0.01" step="0.01" value="1" data-f="qty" title="Quantity">
          <input class="form-control form-control-sm" data-f="note" placeholder="Dosage, e.g. 1 tab twice daily for 5 days">
          <button type="button" class="btn btn-sm btn-light text-danger" data-remove title="Remove"><i class="fa-solid fa-xmark"></i></button></div>`);
      };
      form.querySelector('#addExtra').onclick = () => {
        form.querySelector('#extraRows').insertAdjacentHTML('beforeend', `<div class="dispense-row" data-extra>
          <input class="form-control form-control-sm" data-f="desc" placeholder="Description, e.g. Blood test (CBC)">
          <input class="form-control form-control-sm" type="number" min="0" step="0.01" data-f="price" placeholder="KSh">
          <select class="form-select form-select-sm" data-f="type"><option value="lab">Lab test</option><option value="service">Procedure / service</option><option value="other">Other</option></select>
          <button type="button" class="btn btn-sm btn-light text-danger" data-remove title="Remove"><i class="fa-solid fa-xmark"></i></button></div>`);
      };
      form.addEventListener('click', e => { const r = e.target.closest('[data-remove]'); if (r) r.parentElement.remove(); });
    },
    onSubmit: async (d, form) => {
      if (d.add_record === '1' && !d.title) throw new Error('Record title is required');
      const meds = [...form.querySelectorAll('[data-med]')].map(r => ({ product_id: r.querySelector('[data-f=product]').value, quantity: r.querySelector('[data-f=qty]').value, note: r.querySelector('[data-f=note]').value.trim() })).filter(m => m.product_id);
      const extras = [...form.querySelectorAll('[data-extra]')].map(r => ({ description: r.querySelector('[data-f=desc]').value.trim(), unit_price: r.querySelector('[data-f=price]').value, item_type: r.querySelector('[data-f=type]').value })).filter(x => x.description || x.unit_price);
      for (const x of extras) if (!x.description || x.unit_price === '') throw new Error('Each extra charge needs a description and a price');
      const lines = [];
      for (const m of meds) {
        await api('invoice_item_add', { appointment_id: a.id, ...m });
        const pr = products.find(p => String(p.id) === String(m.product_id));
        lines.push(`${pr.name} × ${m.quantity} ${pr.unit}${m.note ? ' — ' + m.note : ''}`);
      }
      for (const x of extras) await api('invoice_item_add', { appointment_id: a.id, ...x, quantity: 1 });
      if (d.add_record === '1') {
        const prescription = [d.prescription, ...lines].filter(Boolean).join('\n');
        const rec = await api('record_save', { pet_id: a.pet_id, appointment_id: a.id, record_date: todayISO() > a.appt_date ? a.appt_date : todayISO(), record_type: d.record_type, title: d.title, diagnosis: d.diagnosis, treatment: d.treatment, prescription, weight_kg: d.weight_kg });
        await uploadAll('record', rec.id, [...form.elements.files.files]);
      }
      const done = await api('appointment_status', { id: a.id, status: 'completed', vet_notes: d.vet_notes });
      rerender();
      return done.balance > 0 ? `Visit completed. ${money(done.balance)} is due — the pet parent has been notified.` : 'Visit completed';
    },
  });
}

/** Itemised invoice: everyone can view & print; staff can add/remove charges. */
async function invoiceModal(apptId) {
  const inv = await run(() => api('invoice_get', { appointment_id: apptId }));
  const a = inv.appointment;
  const editable = can('billing') && !['cancelled', 'no_show'].includes(a.status);
  const total = inv.items.reduce((s, i) => s + i.line_total, 0);
  const discounts = inv.items.reduce((s, i) => s + (i.discount || 0), 0);
  const body = openView(`Invoice · ${a.pet_name}`, `
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
      <div><div class="fw-semibold">${esc(a.service_name || 'Visit')} · ${fmtDate(a.appt_date)} ${esc(a.appt_time)}</div>
        <div class="small text-muted">${esc(a.owner_name)}${a.vet_name ? ' · ' + esc(a.vet_name) : ''} · ${statusBadge(a.status)}</div>${isStaff() ? petAlerts(a) : ''}</div>
      <a class="btn btn-sm btn-dark align-self-start" target="_blank" href="invoice.php?id=${a.id}"><i class="fa-solid fa-print me-1"></i>Print invoice</a>
    </div>
    <div class="table-responsive"><table class="table inv-table">
      <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Amount</th>${editable ? '<th></th>' : ''}</tr></thead>
      <tbody>${inv.items.map(i => `<tr><td>${esc(i.description)}<div class="small text-muted text-capitalize">${esc(i.item_type)}${i.discount ? ` · <span class="text-success">${money(i.discount)} discount</span>` : ''}</div></td>
        <td class="text-end">${Number(i.quantity)}${i.unit ? ' ' + esc(i.unit) : ''}</td><td class="text-end">${money(i.unit_price)}</td><td class="text-end">${i.discount ? `<s class="text-muted small">${money(i.quantity * i.unit_price)}</s> ` : ''}${money(i.line_total)}</td>
        ${editable ? `<td class="text-end text-nowrap"><button class="btn btn-sm btn-light" data-inv-disc="${i.id}" title="Discount"><i class="fa-solid fa-percent"></i></button> <button class="btn btn-sm btn-light text-danger" data-inv-del="${i.id}" title="Remove"><i class="fa-solid fa-trash"></i></button></td>` : ''}</tr>`).join('')
        || `<tr><td colspan="5" class="text-muted">No charges yet.</td></tr>`}</tbody>
      <tfoot>${discounts ? `<tr><td colspan="3" class="text-end text-success">Discounts</td><td class="text-end text-success">−${money(discounts)}</td>${editable ? '<td></td>' : ''}</tr>` : ''}<tr><th colspan="3" class="text-end">Total</th><th class="text-end">${money(total)}</th>${editable ? '<th></th>' : ''}</tr>
        <tr><td colspan="3" class="text-end text-muted">Paid</td><td class="text-end text-muted">${money(a.amount_paid)}</td>${editable ? '<td></td>' : ''}</tr>
        <tr><th colspan="3" class="text-end">Balance due</th><th class="text-end ${a.balance > 0 ? 'text-danger' : 'text-success'}">${money(a.balance)}</th>${editable ? '<th></th>' : ''}</tr></tfoot>
    </table></div>
    ${a.balance > 0 && !['cancelled', 'no_show'].includes(a.status) ? `<button class="btn btn-brand" data-inv-pay><i class="fa-solid fa-wallet me-1"></i>${isOwner() ? 'Pay now' : 'Request / record payment'}</button>` : ''}
    ${editable ? `<hr><h6>Add a charge</h6>
      <div class="row g-2 align-items-end" id="invAdd">
        <div class="col-md-3"><label class="form-label small">Type</label><select class="form-select form-select-sm" data-f="kind"><option value="stock">Medicine / supply from stock</option><option value="lab">Lab test</option><option value="service">Procedure / service</option><option value="other">Other</option></select></div>
        <div class="col-md-5" data-when="stock"><label class="form-label small">Item</label><select class="form-select form-select-sm" data-f="product"><option value="">Loading…</option></select></div>
        <div class="col-md-5 d-none" data-when="custom"><label class="form-label small">Description</label><input class="form-control form-control-sm" data-f="desc"></div>
        <div class="col-4 col-md-1"><label class="form-label small">Qty</label><input class="form-control form-control-sm" type="number" min="0.01" step="0.01" value="1" data-f="qty"></div>
        <div class="col-8 col-md-3"><label class="form-label small">Unit price (KSh)</label><input class="form-control form-control-sm" type="number" min="0" step="0.01" data-f="price" placeholder="Default price"></div>
        <div class="col-md-9" data-when="stock"><input class="form-control form-control-sm" data-f="note" placeholder="Dosage / note (optional)"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" type="number" min="0" step="0.01" data-f="discount" placeholder="Discount (KSh)"></div>
        <div class="col-12"><div class="text-danger small mb-1" data-inv-err></div><button class="btn btn-sm btn-teal" data-inv-add><i class="fa-solid fa-plus me-1"></i>Add to invoice</button></div>
      </div>` : ''}`);
  const modalEl = document.getElementById('viewModal');
  modalEl.addEventListener('hidden.bs.modal', () => {
    if (!document.querySelector('.modal.show') && ['appointments', 'payments', 'overview'].includes(route.page)) rerender();
  }, { once: true });
  body.querySelector('[data-inv-pay]')?.addEventListener('click', () => (isOwner() ? payModal(a) : (async () => {
    const choice = await confirmBox('Record a payment received at the clinic? (Choose "No" to send an M-Pesa request to the client instead.)', 'Record payment', false);
    choice ? cashForm(a) : payModal(a);
  })()));
  body.querySelectorAll('[data-inv-disc]').forEach(b => b.onclick = () => {
    const item = inv.items.find(i => String(i.id) === b.dataset.invDisc);
    openForm({
      title: `Discount · ${item.description}`, size: 'modal-md',
      intro: `<div class="small text-muted">Line total before discount: <strong>${money(item.quantity * item.unit_price)}</strong>. Enter a percentage <em>or</em> an amount. Use 0 to remove the discount.</div>`,
      fields: [
        { name: 'percent', label: 'Percent off', type: 'number', min: 0, max: 100, step: '1', col: 'col-6' },
        { name: 'discount', label: 'or amount off (KSh)', type: 'number', min: 0, step: '0.01', col: 'col-6', value: item.discount || '' },
      ],
      submitText: 'Apply discount',
      onSubmit: async d => {
        await api('invoice_item_discount', { id: item.id, percent: d.percent, discount: d.discount || '0' });
        setTimeout(() => invoiceModal(apptId), 350);
        return 'Discount saved';
      },
    });
  });
  body.querySelectorAll('[data-inv-del]').forEach(b => b.onclick = async () => {
    await withBusy(b, async () => { try { await api('invoice_item_delete', { id: b.dataset.invDel }); toast('Charge removed'); invoiceModal(apptId); } catch (e) { toast(e.message, 'error'); } });
  });
  if (!editable) return;
  const box = body.querySelector('#invAdd');
  const products = await getProducts();
  const sel = box.querySelector('[data-f=product]');
  sel.innerHTML = '<option value="">Choose item…</option>' + products.map(pr => `<option value="${pr.id}" ${pr.stock_qty <= 0 ? 'disabled' : ''}>${esc(productLabel(pr))}</option>`).join('');
  const kind = box.querySelector('[data-f=kind]');
  kind.onchange = () => {
    const stock = kind.value === 'stock';
    box.querySelectorAll('[data-when=stock]').forEach(x => x.classList.toggle('d-none', !stock));
    box.querySelectorAll('[data-when=custom]').forEach(x => x.classList.toggle('d-none', stock));
  };
  sel.onchange = () => { const pr = products.find(p => String(p.id) === sel.value); box.querySelector('[data-f=price]').placeholder = pr ? `Default ${money(pr.price)}` : 'Default price'; };
  box.querySelector('[data-inv-add]').onclick = async e => {
    const err = box.querySelector('[data-inv-err]');
    err.textContent = '';
    const v = f => box.querySelector(`[data-f=${f}]`).value.trim();
    const data = { appointment_id: a.id, quantity: v('qty'), discount: v('discount') || '0' };
    if (kind.value === 'stock') {
      if (!v('product')) { err.textContent = 'Choose an item'; return; }
      Object.assign(data, { product_id: v('product'), note: v('note') });
      if (v('price') !== '') data.unit_price = v('price');
    } else {
      if (!v('desc') || v('price') === '') { err.textContent = 'Enter a description and a price'; return; }
      Object.assign(data, { item_type: kind.value, description: v('desc'), unit_price: v('price') });
    }
    await withBusy(e.currentTarget, async () => { try { await api('invoice_item_add', data); toast('Charge added'); invoiceModal(apptId); } catch (ex) { err.textContent = ex.message; } });
  };
}

function cashForm(a) {
  openForm({
    title: 'Record payment received', size: 'modal-md',
    intro: `<div class="small">${esc(a.pet_name)} · ${esc(a.service_name)} · ${esc(a.owner_name)}<br>Balance due: <strong>${money(a.balance)}</strong></div>`,
    fields: [
      { name: 'amount', label: 'Amount received (KSh)', type: 'number', step: '0.01', min: 1, max: a.balance, value: a.balance, required: true, col: 'col-md-6' },
      { name: 'method', label: 'Method', type: 'select', options: [['cash', 'Cash'], ['mpesa', 'M-Pesa (paid to till/paybill directly)'], ['card', 'Card (clinic POS)']], col: 'col-md-6' },
      { name: 'provider_ref', label: 'Transaction code (optional)', placeholder: 'e.g. M-Pesa code QJK3XXXXX' },
    ],
    submitText: 'Record payment',
    onSubmit: async d => {
      const p = await api('payment_record_cash', { appointment_id: a.id, ...d });
      rerender();
      toast('Payment recorded');
      window.open('receipt.php?ref=' + encodeURIComponent(p.reference), '_blank');
      return false;
    },
  });
}

// ---------------------------------------------------------------------------
// Payment flow (M-Pesa / card)
// ---------------------------------------------------------------------------
async function payModal(a) {
  const opts = await run(() => api('payment_options'));
  const test = opts.mpesa.mode === 'simulate' || opts.card.mode === 'simulate';
  const phone = isOwner() ? (Session.user.phone || '') : (a.owner_phone || '');
  const body = openView('Make a payment', `
    <div class="bg-cream rounded-3 p-3 mb-3 d-flex justify-content-between align-items-center">
      <div><div class="fw-semibold">${esc(a.service_name)} · ${esc(a.pet_name)}</div><div class="small text-muted">${fmtDate(a.appt_date)} at ${esc(a.appt_time)}</div></div>
      <div class="h4 mb-0 text-brand">${money(a.balance)}</div>
    </div>
    ${test ? '<div class="alert alert-warning small py-2"><i class="fa-solid fa-flask me-1"></i>Payments are in <strong>test mode</strong>. No real money will be charged.</div>' : ''}
    <div id="payStep">
      <div class="row g-2 mb-3">
        <div class="col-6"><div class="pay-option active" data-method="mpesa"><i class="fa-solid fa-mobile-screen-button text-success me-2"></i><strong>M-Pesa</strong><div class="small text-muted">Pay from your phone</div></div></div>
        <div class="col-6"><div class="pay-option" data-method="card"><i class="fa-regular fa-credit-card text-primary me-2"></i><strong>Card</strong><div class="small text-muted">Visa / Mastercard</div></div></div>
      </div>
      <div id="mpesaFields" class="mb-3">
        <label class="form-label">M-Pesa phone number</label>
        <input class="form-control" id="payPhone" value="${esc(phone)}" placeholder="0712 345 678" inputmode="tel">
        <div class="form-text">${isOwner() ? 'You' : 'The client'} will get a prompt on this phone to enter the M-Pesa PIN.</div>
      </div>
      <div id="cardFields" class="mb-3 d-none small text-muted">${opts.card.mode === 'live' ? 'You will be taken to our secure card payment page (Paystack) to enter your card details.' : 'Test mode: the card payment will be approved immediately.'}</div>
      <div class="mb-3">
        <label class="form-label" for="payAmount">Amount to pay now</label>
        <div class="input-group"><span class="input-group-text">KSh</span><input class="form-control" id="payAmount" type="number" min="1" max="${esc(a.balance)}" step="1" value="${esc(Math.round(Number(a.balance) * 100) / 100)}" inputmode="decimal"></div>
        <div class="form-text">You can pay part now and the rest later. The full balance is ${money(a.balance)}.</div>
      </div>
      <div class="text-danger small mb-2" id="payError"></div>
      <button class="btn btn-brand w-100 btn-lg" id="payBtn">Pay ${money(a.balance)}</button>
    </div>`, 'modal-md');
  let method = 'mpesa';
  body.querySelectorAll('.pay-option').forEach(o => o.onclick = () => {
    method = o.dataset.method;
    body.querySelectorAll('.pay-option').forEach(x => x.classList.toggle('active', x === o));
    body.querySelector('#mpesaFields').classList.toggle('d-none', method !== 'mpesa');
    body.querySelector('#cardFields').classList.toggle('d-none', method !== 'card');
  });
  const errBox = body.querySelector('#payError');
  const amtIn = body.querySelector('#payAmount'), payBtn = body.querySelector('#payBtn');
  amtIn.oninput = () => { const v = Number(amtIn.value); payBtn.textContent = 'Pay ' + money(v > 0 ? v : a.balance); };
  payBtn.onclick = async e => {
    errBox.textContent = '';
    const amount = Number(amtIn.value);
    if (!(amount >= 1) || amount > Number(a.balance) + 0.001) { errBox.textContent = `Enter an amount between ${money(1)} and ${money(a.balance)}.`; return; }
    await withBusy(e.currentTarget, async () => {
      try {
        if (method === 'mpesa') {
          const r = await api('pay_mpesa', { appointment_id: a.id, phone: body.querySelector('#payPhone').value, amount });
          waitForPayment(body, r.payment, r.message);
        } else {
          const r = await api('pay_card', { appointment_id: a.id, amount });
          if (r.redirect) { window.location.href = r.redirect; return; }
          paymentDone(body, r.payment);
        }
      } catch (ex) { errBox.textContent = ex.message; }
    });
  };
}

function waitForPayment(body, payment, message) {
  const step = body.querySelector('#payStep');
  step.innerHTML = `<div class="text-center py-3"><div class="spinner-ring mb-3"></div>
    <h5>Waiting for M-Pesa confirmation…</h5><p class="small text-muted">${esc(message)}</p>
    <button class="btn btn-sm btn-light" id="payCancel">Cancel</button></div>`;
  let stopped = false;
  const started = Date.now();
  step.querySelector('#payCancel').onclick = async () => {
    stopped = true;
    await api('payment_cancel', { id: payment.id }).catch(() => {});
    hideModals();
  };
  document.getElementById('viewModal').addEventListener('hidden.bs.modal', () => { stopped = true; rerender(); }, { once: true });
  const poll = async () => {
    if (stopped) return;
    try {
      const p = await api('payment_status', { id: payment.id });
      if (p.status === 'completed') return paymentDone(body, p);
      if (p.status === 'failed' || p.status === 'cancelled') {
        step.innerHTML = `<div class="text-center py-3"><i class="fa-solid fa-circle-xmark text-danger fa-3x mb-3"></i>
          <h5>Payment not completed</h5><p class="small text-muted">${esc(p.failure_reason || 'The payment was cancelled or failed.')}</p>
          <button class="btn btn-brand" data-bs-dismiss="modal">Close</button></div>`;
        return;
      }
    } catch (e) { /* keep trying */ }
    if (Date.now() - started > 190000) {
      step.innerHTML = `<div class="text-center py-3"><h5>Still waiting…</h5><p class="small text-muted">If you completed the payment, it will show in your payments list shortly.</p>
        <button class="btn btn-brand" data-bs-dismiss="modal">Close</button></div>`;
      return;
    }
    setTimeout(poll, 3000);
  };
  setTimeout(poll, 3000);
}

function paymentDone(body, p) {
  body.querySelector('#payStep').innerHTML = `<div class="text-center py-3"><i class="fa-solid fa-circle-check text-success fa-3x mb-3"></i>
    <h5>Payment successful</h5><p class="small text-muted">${money(p.amount)} received · Ref ${esc(p.reference)}${p.provider_ref ? ' · ' + esc(p.provider_ref) : ''}</p>
    <a class="btn btn-brand" target="_blank" href="receipt.php?ref=${encodeURIComponent(p.reference)}"><i class="fa-solid fa-receipt me-1"></i>View receipt</a></div>`;
  toast('Payment received. Thank you!');
}

// ---------------------------------------------------------------------------
// Health records & vaccinations
// ---------------------------------------------------------------------------
async function petSelectOptions() {
  const pets = await api('pets_list');
  return pets.map(p => [p.id, isStaff() ? `${p.name} (${p.species}) – ${p.owner_name}` : `${p.name} (${p.species})`]);
}

async function pageRecords() {
  const petId = route.params.get('pet') || '';
  const q = route.params.get('q') || '';
  const [records, petOpts] = await Promise.all([api('records_list', { pet_id: petId, q }), petSelectOptions()]);
  $view.innerHTML = pageHead('Health Records', can('medical.write') ? `<button class="btn btn-brand" data-act="add"><i class="fa-solid fa-plus me-1"></i>Add record</button>` : '') + `
    <div class="card-x">
      <div class="p-3 d-flex flex-wrap gap-2">
        <select class="form-select" id="petFilter" style="max-width:280px"><option value="">All pets</option>${petOpts.map(([v, l]) => `<option value="${v}" ${String(v) === petId ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>
        ${isStaff() ? `<form id="recSearch" class="d-flex gap-2"><input class="form-control" name="q" value="${esc(q)}" placeholder="Search records"><button class="btn btn-light"><i class="fa-solid fa-magnifying-glass"></i></button></form>` : ''}
      </div>
      ${tableHTML([
        { label: 'Date', render: r => fmtDate(r.record_date) },
        { label: 'Pet', render: r => `${esc(r.pet_name)}${isStaff() ? `<div class="small text-muted">${esc(r.owner_name)}</div>` : ''}` },
        { label: 'Record', render: r => `<strong>${esc(r.title)}</strong> <span class="badge bg-light text-dark text-capitalize">${esc(r.record_type)}</span>${isStaff() && r.locked ? ' <i class="fa-solid fa-lock text-muted small" title="Locked"></i>' : ''}${isStaff() && Number(r.version_count) ? ` <button class="btn btn-link btn-sm p-0" data-act="recHistory" data-id="${r.id}">edited</button>` : ''}${r.diagnosis ? `<div class="small text-muted text-truncate" style="max-width:320px">${esc(r.diagnosis)}</div>` : ''}` },
        { label: 'Vet', render: r => esc(r.vet_name || '—') },
        { label: 'Files', render: r => Number(r.file_count) ? `<span class="small"><i class="fa-solid fa-paperclip me-1"></i>${r.file_count}</span>` : '' },
        { label: '', cls: 'text-end text-nowrap', render: r => btn('view', r.id, 'View', 'btn-light') + (isStaff() ? ' ' + btn('attach', r.id, '', 'btn-light', 'fa-paperclip', 'title="Attach lab result / X-ray"') : '') + (can('medical.write') && (!r.locked || isAdmin()) ? ' ' + btn('edit', r.id, '', 'btn-light', 'fa-pen') + ' ' + btn('del', r.id, '', 'btn-light text-danger', 'fa-trash') : '') },
      ], records, 'No health records yet')}
    </div>`;
  document.getElementById('petFilter').onchange = e => { location.hash = 'records' + (e.target.value ? '?pet=' + e.target.value : ''); };
  const s = document.getElementById('recSearch');
  if (s) s.onsubmit = e => { e.preventDefault(); location.hash = 'records?q=' + encodeURIComponent(s.q.value.trim()); };
  const byId = id => records.find(r => String(r.id) === String(id));
  ACTIONS.add = () => recordForm(null, petId);
  ACTIONS.view = id => { const r = byId(id); openView(`${r.pet_name}: ${r.title}`, recordCard(r), 'modal-md'); if (Number(r.file_count)) showRecordFiles(r.id); };
  ACTIONS.attach = async id => {
    const files = await pickFiles(DOC_ACCEPT, true);
    if (!files.length) return;
    await run(() => uploadAll('record', id, files), files.length > 1 ? `${files.length} files attached` : 'File attached');
    rerender();
  };
  ACTIONS.edit = id => recordForm(byId(id));
  ACTIONS.del = async id => {
    if (await confirmBox('Delete this health record? This cannot be undone.', 'Delete')) {
      await run(() => api('record_delete', { id }), 'Record deleted');
      rerender();
    }
  };
}

async function recordForm(r, petId) {
  const petOpts = await petSelectOptions();
  openForm({
    title: r ? 'Edit health record' : 'Add health record',
    fields: [
      { name: 'pet_id', label: 'Pet', type: 'select', required: true, placeholder: 'Choose pet…', options: petOpts, col: 'col-md-6' },
      { name: 'record_date', label: 'Date', type: 'date', required: true, max: todayISO(), col: 'col-md-3' },
      { name: 'record_type', label: 'Type', type: 'select', options: RECORD_TYPES.map(t => [t, t[0].toUpperCase() + t.slice(1)]), col: 'col-md-3' },
      { name: 'title', label: 'Title', required: true, placeholder: 'e.g. Annual check-up', col: 'col-md-8' },
      { name: 'weight_kg', label: 'Weight (kg)', type: 'number', step: '0.01', min: 0, col: 'col-md-4' },
      { name: 'diagnosis', label: 'Findings / diagnosis', type: 'textarea' },
      { name: 'treatment', label: 'Treatment', type: 'textarea', rows: 2, col: 'col-md-6' },
      { name: 'prescription', label: 'Prescription', type: 'textarea', rows: 2, col: 'col-md-6' },
      { name: 'files', label: 'Attach lab results / X-rays (optional)', type: 'file', multiple: true, accept: DOC_ACCEPT, help: 'PDF or photos, up to 5 MB each.' },
      ...(r ? [{ name: 'change_reason', label: 'Reason for the change', placeholder: 'e.g. Corrected the dose', help: 'The previous version is kept in the record history.' }] : []),
    ],
    intro: r ? '' : '<div class="small text-muted"><i class="fa-solid fa-lock me-1"></i>Records can be edited for 24 hours. After that they are locked and only an administrator can correct them.</div>',
    values: r || { pet_id: petId, record_date: todayISO(), record_type: 'checkup' },
    onSubmit: async (d, form) => {
      if (r) d.id = r.id;
      delete d.files;
      const saved = await api('record_save', d);
      await uploadAll('record', saved.id, [...form.elements.files.files]);
      if (route.page === 'records') rerender();
      return r ? 'Record updated' : 'Record added';
    },
  });
}

async function pageVaccinations() {
  const tab = route.params.get('tab') || 'all';
  const all = await api('vaccinations_list', tab === 'due' ? { due: 1 } : null);
  const list = ['vaccine', 'deworming', 'flea_tick'].includes(tab) ? all.filter(v => (v.kind || 'vaccine') === tab) : all;
  $view.innerHTML = pageHead('Vaccines & Treatments', can('treatment.write') ? `<button class="btn btn-brand" data-act="add"><i class="fa-solid fa-plus me-1"></i>Record vaccine / treatment</button>` : '') +
    tabsHTML('tab', [['all', 'All'], ['vaccine', 'Vaccines'], ['deworming', 'Deworming'], ['flea_tick', 'Flea & tick'], ['due', 'Due soon & overdue']], tab) + `
    <div class="card-x">${tableHTML([
      { label: 'Pet', render: v => `${esc(v.pet_name)}${isStaff() ? `<div class="small text-muted">${esc(v.owner_name)} · ${esc(v.owner_phone || '')}</div>` : ''}` },
      { label: 'Vaccine / product', render: v => `<strong>${esc(v.vaccine)}</strong> <span class="badge text-bg-light">${esc(TREATMENT_LABEL[v.kind] || 'Vaccine')}</span>${v.batch_no ? `<div class="small text-muted">Batch ${esc(v.batch_no)}</div>` : ''}` },
      { label: 'Given', render: v => fmtDate(v.date_given) },
      { label: 'Next due', render: v => v.next_due ? fmtDate(v.next_due) : '—' },
      { label: 'Status', render: v => statusBadge(v.due_status, { overdue: 'Overdue', due_soon: 'Due soon', up_to_date: 'Up to date', complete: 'Complete' }[v.due_status]) },
      { label: '', cls: 'text-end text-nowrap', render: v => can('treatment.write')
          ? btn('edit', v.id, '', 'btn-light', 'fa-pen') + ' ' + btn('del', v.id, '', 'btn-light text-danger', 'fa-trash')
          : (['overdue', 'due_soon'].includes(v.due_status) && (isOwner() || can('appointments.manage')) ? btn('book', v.pet_id, 'Book', 'btn-brand', 'fa-calendar-plus') : '') },
    ], list, tab === 'due' ? 'Nothing due in the next 30 days' : 'Nothing recorded yet')}</div>`;
  ACTIONS.__tab = v => { location.hash = 'vaccinations?tab=' + v; };
  const byId = id => list.find(v => String(v.id) === String(id));
  ACTIONS.add = () => vaccinationForm();
  ACTIONS.edit = id => vaccinationForm(byId(id));
  ACTIONS.book = async petId => {
    const s = (await getServices()).find(x => /vaccin/i.test(x.name));
    bookForm({ petId, serviceId: s ? s.id : '' });
  };
  ACTIONS.del = async id => {
    if (await confirmBox('Delete this record?', 'Delete')) {
      await run(() => api('vaccination_delete', { id }), 'Deleted');
      rerender();
    }
  };
}

async function vaccinationForm(v, petId) {
  const petOpts = await petSelectOptions();
  openForm({
    title: v ? 'Edit vaccine / treatment' : 'Record vaccine or treatment',
    fields: [
      { name: 'pet_id', label: 'Pet', type: 'select', required: true, placeholder: 'Choose pet…', options: petOpts, col: 'col-md-6' },
      { name: 'kind', label: 'Type', type: 'select', options: Object.entries(TREATMENT_LABEL), col: 'col-md-6' },
      { name: 'vaccine', label: 'Vaccine or product', required: true, placeholder: 'e.g. Rabies, DHPP, Milbemax, Bravecto', col: 'col-md-12' },
      { name: 'date_given', label: 'Date given', type: 'date', required: true, max: todayISO(), col: 'col-md-4' },
      { name: 'next_due', label: 'Next dose due', type: 'date', col: 'col-md-4', help: 'The owner gets a reminder 2 weeks before.' },
      { name: 'batch_no', label: 'Batch number', col: 'col-md-4' },
      { name: 'notes', label: 'Notes', type: 'textarea', rows: 2 },
    ],
    values: v || { pet_id: petId, kind: 'vaccine', date_given: todayISO(), next_due: todayISO(365) },
    onRender: form => {
      // Typical repeat intervals: vaccines yearly, deworming every 3 months, flea & tick monthly.
      const months = { vaccine: 12, deworming: 3, flea_tick: 1 };
      const suggest = () => {
        if (v || !form.elements.date_given.value) return;
        const d = new Date(form.elements.date_given.value + 'T00:00:00');
        d.setMonth(d.getMonth() + (months[form.elements.kind.value] || 12));
        form.elements.next_due.value = isoOf(d);
      };
      form.elements.date_given.addEventListener('change', suggest);
      form.elements.kind.addEventListener('change', suggest);
    },
    onSubmit: async d => {
      if (v) d.id = v.id;
      await api('vaccination_save', d);
      if (route.page === 'vaccinations') rerender();
      return v ? 'Updated' : 'Recorded';
    },
  });
}

// ---------------------------------------------------------------------------
// Payments
// ---------------------------------------------------------------------------
function paymentsTable(list) {
  return tableHTML([
    { label: 'Date', render: p => fmtDateTime(p.paid_at || p.created_at) },
    ...(isStaff() ? [{ label: 'Client', render: p => esc(p.user_name) }] : []),
    { label: 'Description', render: p => `${esc(p.description || '')}<div class="small text-muted">Ref ${esc(p.reference)}${p.provider_ref ? ' · ' + esc(p.provider_ref) : ''}</div>` },
    { label: 'Method', render: p => esc(METHOD_LABEL[p.method] || p.method) },
    { label: 'Amount', cls: 'text-end', render: p => `<strong>${money(p.amount)}</strong>` },
    { label: 'Status', render: p => statusBadge(p.status) + (p.failure_reason && p.status !== 'completed' ? `<div class="small text-muted">${esc(p.failure_reason)}</div>` : '') },
    { label: '', cls: 'text-end text-nowrap', render: p => p.status === 'completed'
        ? `<a class="btn btn-sm btn-light" target="_blank" href="receipt.php?ref=${encodeURIComponent(p.reference)}"><i class="fa-solid fa-receipt me-1"></i>Receipt</a>`
        : (p.status === 'pending' ? btn('checkPay', p.id, 'Check', 'btn-light', 'fa-rotate') + ' ' + btn('cancelPay', p.id, '', 'btn-light text-danger', 'fa-xmark', 'title="Cancel"') : '') },
  ], list, 'No payments yet');
}

function registerPaymentActions(list) {
  ACTIONS.checkPay = async id => {
    const p = await run(() => api('payment_status', { id }));
    toast(`Payment is ${p.status}`, p.status === 'completed' ? 'success' : 'info');
    rerender();
  };
  ACTIONS.cancelPay = async id => {
    if (await confirmBox('Cancel this pending payment?', 'Cancel payment')) {
      await run(() => api('payment_cancel', { id }), 'Payment cancelled');
      rerender();
    }
  };
}

async function pagePayments() {
  const result = route.params.get('result');
  if (result) {
    setHashQuiet('payments');
    toast(result === 'completed' ? 'Card payment successful. Thank you!' : result === 'pending' ? 'Card payment is being confirmed.' : 'The card payment was not completed.', result === 'completed' ? 'success' : 'warning');
  }
  const status = route.params.get('status') || '';
  const [payments, appts] = await Promise.all([api('payments_list', { status }), api('appointments_list')]);
  const ownerFilter = route.params.get('owner') || '';
  const due = appts.filter(a => a.balance > 0 && (isOwner() ? !['cancelled', 'no_show'].includes(a.status) : ['completed', 'checked_in', 'in_progress'].includes(a.status)) && (!ownerFilter || String(a.owner_id) === ownerFilter))
    .sort((x, y) => (x.appt_date).localeCompare(y.appt_date));
  const totalDue = due.reduce((s, a) => s + a.balance, 0);
  const paid = payments.filter(p => p.status === 'completed').reduce((s, p) => s + p.amount, 0);
  $view.innerHTML = pageHead('Payments') + `
    <div class="row g-3 mb-4">
      ${statCard('fa-file-invoice', totalDue ? 'i-red' : 'i-teal', money(totalDue), isOwner() ? 'Amount due' : 'Outstanding (all clients)')}
      ${statCard('fa-circle-check', 'i-teal', money(paid), isOwner() ? 'Total paid' : 'Collected (shown below)')}
    </div>
    <div class="card-x mb-4" id="unpaidCard"><div class="p-3 d-flex flex-wrap justify-content-between gap-2"><h2 class="h6 mb-0">${isOwner() ? 'Bills to pay' : 'Unpaid invoices'}${ownerFilter && due[0] ? ` · ${esc(due[0].owner_name)}` : ''}</h2>
      ${ownerFilter ? '<a class="small" href="#payments">Show all clients</a>' : ''}</div>
      ${tableHTML([
        { label: 'Visit', render: a => `<strong>${esc(a.service_name)}</strong> · ${esc(a.pet_name)}<div class="small text-muted">${fmtDate(a.appt_date)} ${esc(a.appt_time)} · ${statusBadge(a.status)}</div>` },
        ...(isStaff() ? [{ label: 'Client', render: a => `${esc(a.owner_name)}<div class="small text-muted">${esc(a.owner_phone || '')}</div>` }] : []),
        { label: 'Due', cls: 'text-end', render: a => `<strong>${money(a.balance)}</strong>` },
        { label: '', cls: 'text-end text-nowrap', render: a => isOwner()
            ? btn('pay', a.id, 'Pay now', 'btn-brand', 'fa-wallet')
            : btn('invoice', a.id, '', 'btn-light', 'fa-file-invoice', 'title="Invoice"') + (can('billing') ? ' ' + btn('cash', a.id, 'Record payment', 'btn-teal', 'fa-money-bill') + ' ' + btn('pay', a.id, 'M-Pesa request', 'btn-light', 'fa-mobile-screen') : '') },
      ], due, 'Nothing to pay. You are all settled!')}
    </div>
    <div class="card-x"><div class="p-3 d-flex flex-wrap justify-content-between gap-2 align-items-center"><h2 class="h6 mb-0">Payment history</h2>
      <select class="form-select form-select-sm" id="payStatus" style="width:auto">
        ${[['', 'All statuses'], ['completed', 'Completed'], ['pending', 'Pending'], ['failed', 'Failed'], ['cancelled', 'Cancelled']].map(([v, l]) => `<option value="${v}" ${v === status ? 'selected' : ''}>${l}</option>`).join('')}
      </select></div>
      ${paymentsTable(payments)}</div>`;
  document.getElementById('payStatus').onchange = e => { location.hash = 'payments' + (e.target.value ? '?status=' + e.target.value : ''); };
  registerApptActions(due);
  registerPaymentActions(payments);
  if (route.params.get('tab') === 'unpaid') document.getElementById('unpaidCard').scrollIntoView({ block: 'start' });
}

// ---------------------------------------------------------------------------
// Adoption
// ---------------------------------------------------------------------------
async function pageAdoption() {
  const [listings, requests] = await Promise.all([api('adoption_list'), api('adoption_requests')]);
  const reqLabel = { pending: 'Request pending', approved: 'Approved', rejected: 'Not approved' };
  $view.innerHTML = pageHead('Adoption', isStaff() ? `<button class="btn btn-brand" data-act="add"><i class="fa-solid fa-plus me-1"></i>Add pet for adoption</button>` : '') + `
    <p class="text-muted">${isOwner() ? 'Give a loving home to a pet in need. Apply below and our team will contact you.' : 'Manage pets available for adoption and review applications.'}</p>
    ${listings.length ? `<div class="row g-3 mb-4">${listings.map(l => `
      <div class="col-sm-6 col-xl-4"><div class="card-x pet-card">
        ${l.photo_id ? `<img class="photo-card-img" src="file.php?id=${l.photo_id}" alt="${esc(l.name)}">` : ''}
        <div class="d-flex gap-3 align-items-center mb-2">${l.photo_id ? '' : petAvatar(l.species)}
          <div class="flex-grow-1"><div class="h5 mb-0">${esc(l.name)}</div><div class="small text-muted">${esc(l.breed || l.species)}${l.age_text ? ' · ' + esc(l.age_text) : ''}${l.sex ? ' · ' + esc(l.sex) : ''}</div></div>
          ${statusBadge(l.status)}</div>
        <p class="small flex-grow-1">${esc(l.description || '')}</p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          ${isOwner() ? (l.my_request && l.my_request !== 'rejected' ? statusBadge(l.my_request === 'approved' ? 'approved' : 'pending', reqLabel[l.my_request]) : btn('apply', l.id, 'Apply to adopt', 'btn-brand', 'fa-heart'))
                      : `${btn('edit', l.id, 'Edit', 'btn-light', 'fa-pen')} ${btn('del', l.id, '', 'btn-light text-danger', 'fa-trash')} ${Number(l.pending_requests) ? `<span class="small text-muted">${l.pending_requests} request(s)</span>` : ''}`}
        </div>
      </div></div>`).join('')}</div>` : `<div class="card-x mb-4">${emptyHTML('No pets are up for adoption right now. Check back soon!', 'fa-heart')}</div>`}
    <div class="card-x"><div class="p-3"><h2 class="h6 mb-0">${isOwner() ? 'My adoption requests' : 'Adoption requests'}</h2></div>
      ${tableHTML([
        { label: 'Date', render: r => fmtDate(r.created_at) },
        { label: 'Pet', render: r => esc(r.pet_name) },
        ...(isStaff() ? [{ label: 'Applicant', render: r => `${esc(r.user_name)}<div class="small text-muted">${esc(r.user_phone || '')} · ${esc(r.user_email)}</div>` }] : []),
        { label: 'Message', render: r => `<div class="small" style="max-width:360px">${esc(r.message || '')}</div>` },
        { label: 'Status', render: r => statusBadge(r.status) },
        ...(isStaff() ? [{ label: '', cls: 'text-end text-nowrap', render: r => r.status === 'pending' ? btn('approve', r.id, 'Approve', 'btn-teal', 'fa-check') + ' ' + btn('reject', r.id, 'Decline', 'btn-light') : '' }] : []),
      ], requests, 'No adoption requests yet')}</div>`;
  const byId = id => listings.find(l => String(l.id) === String(id));
  ACTIONS.add = () => adoptionForm();
  ACTIONS.edit = id => adoptionForm(byId(id));
  ACTIONS.del = async id => {
    if (await confirmBox('Remove this adoption listing?', 'Remove')) { await run(() => api('adoption_delete', { id }), 'Listing removed'); rerender(); }
  };
  ACTIONS.apply = id => {
    const l = byId(id);
    openForm({
      title: `Adopt ${l.name}`, size: 'modal-md',
      fields: [{ name: 'message', label: 'Tell us about your home', type: 'textarea', rows: 5, required: true, placeholder: 'Who lives with you, other pets, garden or apartment, your experience with pets…' }],
      submitText: 'Send request',
      onSubmit: async d => { await api('adoption_request', { listing_id: id, message: d.message }); rerender(); return 'Request sent. The clinic will contact you.'; },
    });
  };
  const decide = async (id, status) => { await run(() => api('adoption_decide', { id, status }), status === 'approved' ? 'Request approved' : 'Request declined'); rerender(); };
  ACTIONS.approve = async id => { if (await confirmBox('Approve this adoption? The pet will be marked as adopted and other requests declined.', 'Approve', false)) decide(id, 'approved'); };
  ACTIONS.reject = id => decide(id, 'rejected');
}

function adoptionForm(l) {
  openForm({
    title: l ? `Edit ${l.name}` : 'Add pet for adoption',
    fields: [
      { name: 'name', label: 'Name', required: true, col: 'col-md-6' },
      { name: 'species', label: 'Species', type: 'select', options: SPECIES, required: true, col: 'col-md-6' },
      { name: 'breed', label: 'Breed', col: 'col-md-4' },
      { name: 'age_text', label: 'Age', placeholder: 'e.g. 2 years', col: 'col-md-4' },
      { name: 'sex', label: 'Sex', type: 'select', options: ['Male', 'Female', 'Unknown'], placeholder: '—', col: 'col-md-4' },
      { name: 'status', label: 'Status', type: 'select', options: [['available', 'Available'], ['pending', 'Pending'], ['adopted', 'Adopted']] },
      { name: 'description', label: 'Description', type: 'textarea', rows: 4, placeholder: 'Temperament, health, vaccinations, ideal home…' },
      { name: 'photo', label: l && l.photo_id ? 'Replace photo (optional)' : 'Photo', type: 'file', accept: PHOTO_ACCEPT, help: 'Listings with a photo get far more interest.' },
    ],
    values: l || { species: 'Dog', status: 'available' },
    onSubmit: async (d, form) => {
      if (l) d.id = l.id;
      delete d.photo;
      const saved = await api('adoption_save', d);
      const photo = form.elements.photo.files[0];
      if (photo) await apiUpload('adoption', saved.id, photo, 'photo');
      rerender();
      return 'Listing saved';
    },
  });
}

// ---------------------------------------------------------------------------
// Lost & found
// ---------------------------------------------------------------------------
async function pageLostfound() {
  const type = route.params.get('type') || '';
  const status = route.params.get('status') || 'open';
  const list = await api('lostfound_list', { type, status });
  $view.innerHTML = pageHead('Lost & Found', `<button class="btn btn-brand" data-act="add"><i class="fa-solid fa-bullhorn me-1"></i>Report lost / found pet</button>`) + `
    <div class="d-flex flex-wrap gap-3">
      ${tabsHTML('type', [['', 'All'], ['lost', 'Lost'], ['found', 'Found']], type)}
      ${tabsHTML('status', [['open', 'Still open'], ['resolved', 'Reunited']], status)}
    </div>
    ${list.length ? `<div class="row g-3">${list.map(r => `
      <div class="col-md-6 col-xl-4"><div class="card-x pet-card">
        ${r.photo_id ? `<a href="file.php?id=${r.photo_id}" target="_blank"><img class="photo-card-img" src="file.php?id=${r.photo_id}" alt="Photo of the ${esc(r.report_type)} pet"></a>` : ''}
        <div class="d-flex justify-content-between mb-2"><span class="badge ${r.report_type === 'lost' ? 'bg-danger' : 'bg-success'} text-uppercase">${esc(r.report_type)}</span>${statusBadge(r.status, r.status === 'resolved' ? 'Reunited' : 'Open')}</div>
        <div class="h6 mb-1">${esc(r.pet_name || 'Unnamed')} <span class="text-muted fw-normal">· ${esc(r.species)}</span></div>
        <p class="small flex-grow-1 mb-2">${esc(r.description)}</p>
        <div class="small text-muted mb-2"><i class="fa-solid fa-location-dot me-1"></i>${esc(r.location)}<br><i class="fa-regular fa-calendar me-1"></i>${fmtDate(r.event_date)}
          ${r.contact_phone ? `<br><i class="fa-solid fa-phone me-1"></i><a href="tel:${esc(r.contact_phone)}">${esc(r.contact_phone)}</a>` : ''}<br><i class="fa-regular fa-user me-1"></i>${esc(r.reporter)}</div>
        ${r.mine || isStaff() ? `<div class="d-flex gap-2">${btn('resolve', r.id, r.status === 'open' ? 'Mark reunited' : 'Reopen', 'btn-light', 'fa-check')}
          ${r.mine ? btn('edit', r.id, '', 'btn-light', 'fa-pen') : ''}${r.mine || isAdmin() ? btn('del', r.id, '', 'btn-light text-danger', 'fa-trash') : ''}</div>` : ''}
      </div></div>`).join('')}</div>` : `<div class="card-x">${emptyHTML('No reports here', 'fa-magnifying-glass-location')}</div>`}`;
  const setParam = (k, v) => { const p = new URLSearchParams(route.params); v ? p.set(k, v) : p.delete(k); location.hash = 'lostfound?' + p.toString(); };
  ACTIONS.__type = v => setParam('type', v);
  ACTIONS.__status = v => setParam('status', v);
  const byId = id => list.find(r => String(r.id) === String(id));
  ACTIONS.add = () => lostForm();
  ACTIONS.edit = id => lostForm(byId(id));
  ACTIONS.resolve = async id => { await run(() => api('lostfound_resolve', { id }), 'Report updated'); rerender(); };
  ACTIONS.del = async id => { if (await confirmBox('Delete this report?', 'Delete')) { await run(() => api('lostfound_delete', { id }), 'Report deleted'); rerender(); } };
}

function lostForm(r) {
  openForm({
    title: r ? 'Edit report' : 'Report a lost or found pet',
    fields: [
      { name: 'report_type', label: 'I have…', type: 'select', options: [['lost', 'Lost a pet'], ['found', 'Found a pet']], required: true, col: 'col-md-6' },
      { name: 'species', label: 'Species', type: 'select', options: SPECIES, required: true, col: 'col-md-6' },
      { name: 'pet_name', label: 'Pet name (if known)', col: 'col-md-6' },
      { name: 'event_date', label: 'Date lost / found', type: 'date', required: true, max: todayISO(), col: 'col-md-6' },
      { name: 'location', label: 'Where', required: true, placeholder: 'Area, landmark or street' },
      { name: 'description', label: 'Description', type: 'textarea', required: true, placeholder: 'Colour, size, collar, markings, behaviour…' },
      { name: 'contact_phone', label: 'Contact phone', type: 'tel', placeholder: Session.user.phone || '07XX XXX XXX' },
      { name: 'photo', label: r && r.photo_id ? 'Replace photo (optional)' : 'Photo (recommended)', type: 'file', accept: PHOTO_ACCEPT, help: 'A clear photo helps people recognise the pet.' },
    ],
    values: r || { report_type: 'lost', species: 'Dog', event_date: todayISO(), contact_phone: Session.user.phone || '' },
    onSubmit: async (d, form) => {
      if (r) d.id = r.id;
      delete d.photo;
      const saved = await api('lostfound_save', d);
      const photo = form.elements.photo.files[0];
      if (photo) await apiUpload('lostfound', saved.id, photo, 'photo');
      rerender();
      return 'Report saved';
    },
  });
}

// ---------------------------------------------------------------------------
// Forum
// ---------------------------------------------------------------------------
async function pageForum() {
  const tab = route.params.get('tab') || 'community';
  const posts = await api('forum_list', tab === 'pending' ? { status: 'pending' } : (tab === 'all' ? { all: 1 } : null));
  const tabs = isAdmin() ? tabsHTML('tab', [['community', 'Community'], ['pending', 'Awaiting approval'], ['all', 'All posts']], tab) : '';
  $view.innerHTML = pageHead('Community Forum', `<button class="btn btn-brand" data-act="add"><i class="fa-solid fa-pen me-1"></i>New post</button>`) + tabs + `
    ${isOwner() ? '<p class="text-muted small">Share tips and ask other pet parents for advice. New posts appear after a quick review by our team.</p>' : ''}
    ${posts.length ? posts.map(p => `
      <div class="card-x p-3 mb-3">
        <div class="d-flex justify-content-between gap-2 flex-wrap">
          <div><a href="#" class="h6 text-reset text-decoration-none" data-act="open" data-id="${p.id}">${esc(p.title)}</a>
            ${p.status !== 'approved' ? statusBadge(p.status) : ''}
            <div class="small text-muted">${esc(p.author)}${p.author_role !== 'owner' ? ` <span class="badge bg-light text-dark">${esc(ROLE_LABEL[p.author_role])}</span>` : ''} · ${timeAgo(p.created_at)}</div></div>
          <div class="d-flex gap-1 align-items-start">
            ${isAdmin() && p.status !== 'approved' ? btn('approve', p.id, 'Approve', 'btn-teal', 'fa-check') : ''}
            ${isAdmin() && p.status === 'pending' ? btn('reject', p.id, 'Reject', 'btn-light') : ''}
            ${isAdmin() || Number(p.user_id) === Session.user.id ? btn('del', p.id, '', 'btn-light text-danger', 'fa-trash') : ''}
          </div>
        </div>
        <p class="small mt-2 mb-2">${esc(p.content.length > 280 ? p.content.slice(0, 280) + '…' : p.content)}</p>
        <a href="#" class="small" data-act="open" data-id="${p.id}"><i class="fa-regular fa-comment me-1"></i>${p.replies} repl${Number(p.replies) === 1 ? 'y' : 'ies'}</a>
      </div>`).join('') : `<div class="card-x">${emptyHTML(tab === 'pending' ? 'No posts waiting for approval' : 'No posts yet. Start the conversation!', 'fa-comments')}</div>`}`;
  ACTIONS.__tab = v => { location.hash = 'forum?tab=' + v; };
  ACTIONS.add = () => openForm({
    title: 'New forum post',
    fields: [{ name: 'title', label: 'Title', required: true }, { name: 'content', label: 'Message', type: 'textarea', rows: 6, required: true }],
    submitText: 'Post',
    onSubmit: async d => { await api('forum_create', d); rerender(); return isStaff() ? 'Posted' : 'Thanks! Your post will appear once approved.'; },
  });
  ACTIONS.open = id => openPost(id);
  const moderate = async (id, status) => { await run(() => api('forum_moderate', { id, status }), `Post ${status}`); rerender(); };
  ACTIONS.approve = id => moderate(id, 'approved');
  ACTIONS.reject = id => moderate(id, 'rejected');
  ACTIONS.del = async id => { if (await confirmBox('Delete this post and its replies?', 'Delete')) { await run(() => api('forum_delete', { id }), 'Post deleted'); hideModals(); rerender(); } };
}

async function openPost(id) {
  const p = await run(() => api('forum_get', { id }));
  const body = openView(p.title, `
    <div class="small text-muted mb-2">${esc(p.author)} · ${fmtDateTime(p.created_at)} ${p.status !== 'approved' ? statusBadge(p.status) : ''}</div>
    <p style="white-space:pre-wrap">${esc(p.content)}</p>
    <hr><h6>${p.replies.length} repl${p.replies.length === 1 ? 'y' : 'ies'}</h6>
    ${p.replies.map(r => `<div class="reply"><div class="small"><strong>${esc(r.author)}</strong>${r.author_role !== 'owner' ? ` <span class="badge bg-light text-dark">${esc(ROLE_LABEL[r.author_role])}</span>` : ''} <span class="text-muted">· ${timeAgo(r.created_at)}</span>
      ${isAdmin() || Number(r.user_id) === Session.user.id ? `<button class="btn btn-link btn-sm text-danger p-0 ms-2" data-act="delReply" data-id="${r.id}">Delete</button>` : ''}</div>
      <div style="white-space:pre-wrap">${esc(r.content)}</div></div>`).join('')}
    ${p.status === 'approved' ? `<form id="replyForm" class="mt-3"><textarea class="form-control mb-2" name="content" rows="3" placeholder="Write a reply…" required></textarea>
      <button class="btn btn-brand btn-sm">Reply</button></form>` : '<div class="alert alert-info small">Replies open once this post is approved.</div>'}`);
  const f = body.querySelector('#replyForm');
  if (f) f.onsubmit = async e => {
    e.preventDefault();
    if (!f.content.value.trim()) return;
    await withBusy(f.querySelector('button'), async () => {
      await run(() => api('forum_reply', { post_id: id, content: f.content.value.trim() }), 'Reply posted');
      openPost(id);
    });
  };
  ACTIONS.delReply = async rid => { await run(() => api('forum_reply_delete', { id: rid }), 'Reply deleted'); openPost(id); };
}

// ---------------------------------------------------------------------------
// Admin: users, services, reports, settings
// ---------------------------------------------------------------------------
async function pageUsers() {
  const clientsOnly = route.page === 'clients';
  const status = clientsOnly ? '' : (route.params.get('status') || '');
  const role = clientsOnly ? 'owner' : (status ? '' : (route.params.get('role') || ''));
  const q = route.params.get('q') || '';
  const users = await api('users_list', { role, q, status });
  const showBal = users.some(u => u.balance !== undefined);
  $view.innerHTML = pageHead(clientsOnly ? 'Pet Parents' : 'Users', (!clientsOnly || can('appointments.manage') ? `<button class="btn btn-brand" data-act="add"><i class="fa-solid fa-user-plus me-1"></i>${clientsOnly ? 'Add pet parent' : 'Add user'}</button>` : '')) + `
    <div class="d-flex flex-wrap justify-content-between gap-2">
      ${clientsOnly ? '<div></div>' : tabsHTML('role', [['', 'Everyone'], ['owner', 'Pet parents'], ['vet', 'Vets'], ['reception', 'Reception'], ['assistant', 'Assistants'], ['admin', 'Admins'], ['pending', 'Awaiting approval']], status === 'pending' ? 'pending' : role)}
      <form id="userSearch" class="d-flex gap-2 mb-3"><input class="form-control form-control-sm" name="q" value="${esc(q)}" placeholder="Name, email or phone"><button class="btn btn-sm btn-light"><i class="fa-solid fa-magnifying-glass"></i></button></form>
    </div>
    <div class="card-x">${tableHTML([
      { label: 'Name', render: u => `<strong>${esc(u.name)}</strong>${/@demo\.local$/i.test(u.email || '') ? ' <span class="badge-demo">Demo</span>' : ''}${u.specialization ? `<div class="small text-muted">${esc(u.specialization)}</div>` : ''}` },
      { label: 'Contact', render: u => `${esc(realEmail(u.email) || '—')}<div class="small text-muted">${esc(u.phone || '')}</div>` },
      ...(clientsOnly ? [] : [{ label: 'Role', render: u => esc(ROLE_LABEL[u.role]) }]),
      { label: 'Pets', render: u => u.role === 'owner' ? `<a href="#pets?q=${encodeURIComponent(u.name)}">${esc(u.pet_count)}</a>` : '—' },
      ...(showBal ? [{ label: 'Balance', cls: 'text-end', render: u => u.role === 'owner' ? (u.balance > 0 ? `<a class="text-danger fw-semibold" href="#payments?tab=unpaid&owner=${u.id}">${money(u.balance)}</a>` : '<span class="text-muted">—</span>') : '' }] : []),
      ...(clientsOnly ? [] : [{ label: 'Status', render: u => statusBadge(u.status, u.status === 'pending' ? 'Awaiting approval' : '') },
      { label: 'Last login', render: u => u.last_login ? timeAgo(u.last_login) : '<span class="text-muted small">Never</span>' }]),
      { label: '', cls: 'text-end text-nowrap', render: u => clientsOnly ? (can('appointments.manage') ? btn('edit', u.id, '', 'btn-light', 'fa-pen', 'title="Edit"') : '') : u.status === 'pending'
          ? btn('approve', u.id, 'Approve', 'btn-teal', 'fa-check') + ' ' + btn('del', u.id, 'Decline', 'btn-light text-danger')
          : (u.role === 'vet' ? `<a class="btn btn-sm btn-light" href="#schedule?vet=${u.id}" title="Working hours"><i class="fa-solid fa-clock"></i></a> ` : '') + btn('edit', u.id, '', 'btn-light', 'fa-pen', 'title="Edit"') + ' ' +
          (u.id !== Session.user.id ? btn('toggle', u.id, '', 'btn-light', u.status === 'active' ? 'fa-user-slash' : 'fa-user-check', `title="${u.status === 'active' ? 'Deactivate' : 'Activate'}"`) + ' ' + btn('del', u.id, '', 'btn-light text-danger', 'fa-trash', 'title="Delete"') : '') },
    ], users, 'No users found')}</div>`;
  ACTIONS.__role = v => { location.hash = 'users' + (v === 'pending' ? '?status=pending' : v ? '?role=' + v : ''); };
  ACTIONS.approve = async id => {
    const u = byId(id);
    if (await confirmBox(`Approve ${u.name} as ${ROLE_LABEL[u.role].toLowerCase()}? They will be able to see clinic records.`, 'Approve', false)) {
      await run(() => api('user_save', { ...u, status: 'active' }), `${u.name} approved`);
      delete cache.vets;
      rerender();
    }
  };
  const s = document.getElementById('userSearch');
  s.onsubmit = e => { e.preventDefault(); location.hash = clientsOnly ? `clients?q=${encodeURIComponent(s.q.value.trim())}` : `users?role=${role}&q=${encodeURIComponent(s.q.value.trim())}`; };
  const byId = id => users.find(u => String(u.id) === String(id));
  ACTIONS.add = () => userForm();
  ACTIONS.edit = id => userForm(byId(id));
  ACTIONS.toggle = async id => {
    const u = byId(id);
    await run(() => api('user_save', { ...u, status: u.status === 'active' ? 'inactive' : 'active' }), u.status === 'active' ? `${u.name} deactivated` : `${u.name} activated`);
    delete cache.owners; delete cache.vets;
    rerender();
  };
  ACTIONS.del = async id => {
    const u = byId(id);
    if (await confirmBox(`Delete ${u.name}? ${u.role === 'owner' ? 'Their pets, appointments and payment history will also be deleted. Consider deactivating instead.' : ''}`, 'Delete')) {
      await run(() => api('user_delete', { id }), 'User deleted');
      delete cache.owners; delete cache.vets;
      rerender();
    }
  };
}

function userForm(u) {
  const client = !isAdmin();
  openForm({
    title: u ? `Edit ${u.name}` : (client ? 'Add pet parent' : 'Add user'),
    fields: [
      { name: 'name', label: 'Full name', required: true, col: 'col-md-6' },
      { name: 'email', label: 'Email', type: 'email', required: !client, col: 'col-md-6', help: client ? 'Optional. Without an email the pet parent cannot log in.' : '' },
      { name: 'phone', label: 'Phone', type: 'tel', required: client, col: 'col-md-6' },
      client ? { name: 'role', type: 'hidden' } : { name: 'role', label: 'Role', type: 'select', options: [['owner', 'Pet parent (client)'], ['vet', 'Veterinarian'], ['reception', 'Receptionist'], ['assistant', 'Vet assistant'], ['admin', 'Administrator']], required: true, col: 'col-md-6',
        help: 'Receptionists book, check in and take payments but cannot read medical notes. Vet assistants see records, give vaccines and manage stock.' },
      ...(client ? [] : [{ name: 'specialization', label: 'Specialisation (vets)', col: 'col-md-6' },
      { name: 'status', label: 'Status', type: 'select', options: [['active', 'Active'], ['inactive', 'Inactive']], col: 'col-md-6' }]),
      { name: 'address', label: 'Address' },
      { name: 'emergency_name', label: 'Emergency contact name', col: 'col-md-6' },
      { name: 'emergency_phone', label: 'Emergency contact phone', type: 'tel', col: 'col-md-6' },
      ...(client ? [] : [{ name: 'password', label: u ? 'New password (leave blank to keep current)' : 'Password', type: 'password', required: !u, autocomplete: 'new-password', help: 'At least 8 characters with letters and numbers. Share it with the user securely.' }]),
    ],
    values: u ? { ...u, email: realEmail(u.email) } : { role: 'owner', status: 'active' },
    onSubmit: async d => {
      if (u) d.id = u.id;
      await api('user_save', d);
      delete cache.owners; delete cache.vets;
      if (route.page === 'users' || route.page === 'clients') rerender();
      return u ? 'User updated' : 'User created';
    },
  });
}

async function pageServices() {
  delete cache.services;
  const services = await getServices();
  $view.innerHTML = pageHead('Services & Prices', `<button class="btn btn-brand" data-act="add"><i class="fa-solid fa-plus me-1"></i>Add service</button>`) + `
    <p class="text-muted small">These services and prices appear on the website and in the booking form. The price is saved with each booking, so changing it does not affect existing appointments.</p>
    <div class="card-x">${tableHTML([
      { label: 'Service', render: s => `<strong>${esc(s.name)}</strong><div class="small text-muted">${esc(s.description || '')}</div>` },
      { label: 'Category', render: s => `<span class="cal-dot cat-${esc(s.category || 'other')}"></span>${esc(SERVICE_CATEGORY[s.category] || 'Other')}` },
      { label: 'Price', cls: 'text-end', render: s => money(s.price) },
      { label: 'Duration', render: s => `${esc(s.duration_minutes)} min` },
      { label: 'Bookings', render: s => esc(s.bookings) },
      { label: 'Status', render: s => statusBadge(Number(s.active) ? 'active' : 'inactive', Number(s.active) ? 'Visible' : 'Hidden') },
      { label: '', cls: 'text-end text-nowrap', render: s => btn('edit', s.id, '', 'btn-light', 'fa-pen') + ' ' + btn('del', s.id, '', 'btn-light text-danger', 'fa-trash') },
    ], services, 'No services yet')}</div>`;
  const byId = id => services.find(s => String(s.id) === String(id));
  const form = s => openForm({
    title: s ? 'Edit service' : 'Add service', size: 'modal-md',
    fields: [
      { name: 'name', label: 'Service name', required: true },
      { name: 'category', label: 'Category', type: 'select', options: Object.entries(SERVICE_CATEGORY), help: 'Used for calendar colours and reports.' },
      { name: 'price', label: 'Price (KSh)', type: 'number', min: 0, step: '0.01', required: true, col: 'col-md-6' },
      { name: 'duration_minutes', label: 'Duration (minutes)', type: 'number', min: 10, max: 480, col: 'col-md-6' },
      { name: 'description', label: 'Description', type: 'textarea' },
      { name: 'active', label: 'Show on website and allow booking', type: 'checkbox' },
    ],
    values: s ? { ...s, active: String(s.active) } : { duration_minutes: 30, active: '1', category: 'consultation' },
    onSubmit: async d => { if (s) d.id = s.id; await api('service_save', d); delete cache.services; rerender(); return 'Service saved'; },
  });
  ACTIONS.add = () => form();
  ACTIONS.edit = id => form(byId(id));
  ACTIONS.del = async id => {
    if (await confirmBox('Delete this service?', 'Delete')) {
      const r = await run(() => api('service_delete', { id }));
      toast(r.message || 'Service deleted');
      delete cache.services;
      rerender();
    }
  };
}

async function pageReports() {
  const from = route.params.get('from') || '';
  const to = route.params.get('to') || '';
  const r = await api('reports', { from, to });
  const max = Math.max(1, ...r.revenue.map(m => Number(m.total)));
  const total = r.revenue.reduce((s, m) => s + Number(m.total), 0);
  const exportUrl = type => `${API_URL}?action=report_export&type=${type}&from=${r.from}&to=${r.to}`;
  const monthLabel = m => new Date(m + '-01T00:00:00').toLocaleDateString('en-GB', { month: 'short', year: '2-digit' });
  const small = (rows, cols) => tableHTML(cols, rows, 'No data');
  const bars = (rows, label, val, fmt = v => esc(v), cls = '') => {
    if (!rows.length) return emptyHTML('No data', 'fa-chart-bar');
    const top = Math.max(1, ...rows.map(x => Number(val(x))));
    return `<div class="p-3 pt-2">${rows.map(x => `<div class="hbar"><span class="hbar-label">${label(x)}</span><span class="hbar-track"><span class="hbar-fill ${cls}" style="width:${Number(val(x)) / top * 100}%"></span></span><span class="hbar-val">${fmt(val(x))}</span></div>`).join('')}</div>`;
  };
  $view.innerHTML = pageHead('Reports') + `
    <form class="card-x p-3 mb-4 d-flex flex-wrap gap-2 align-items-end" id="repForm">
      <div><label class="form-label small">From</label><input type="date" class="form-control" name="from" value="${esc(r.from)}"></div>
      <div><label class="form-label small">To</label><input type="date" class="form-control" name="to" value="${esc(r.to)}"></div>
      <button class="btn btn-brand">Update</button>
      <div class="ms-auto dropdown"><button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fa-solid fa-file-csv me-1"></i>Export CSV</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="${exportUrl('payments')}">Payments</a></li>
          <li><a class="dropdown-item" href="${exportUrl('appointments')}">Appointments</a></li>
          <li><a class="dropdown-item" href="${exportUrl('clients')}">Clients</a></li>
          <li><a class="dropdown-item" href="${exportUrl('pets')}">Pets</a></li>
        </ul></div>
    </form>
    <div class="row g-3 mb-4">
      ${statCard('fa-sack-dollar', 'i-orange', money(total), 'Revenue in period')}
      ${statCard('fa-receipt', 'i-teal', r.revenue.reduce((s, m) => s + Number(m.count), 0), 'Payments received')}
      ${statCard('fa-calendar-check', 'i-blue', r.byStatus.reduce((s, x) => s + Number(x.count), 0), 'Appointments')}
      ${statCard('fa-user-plus', 'i-purple', r.newClients, 'New clients')}
    </div>
    <div class="row g-3 mb-4">
      ${statCard('fa-file-invoice-dollar', r.outstanding.total ? 'i-red' : 'i-teal', money(r.outstanding.total), `Unpaid now (${r.outstanding.count} invoices)`, 'payments?tab=unpaid')}
      ${statCard('fa-syringe', 'i-teal', r.compliance.pets ? Math.round(r.compliance.up_to_date / r.compliance.pets * 100) + '%' : '—', `Dogs & cats up to date on vaccines (${r.compliance.up_to_date}/${r.compliance.pets})`, 'vaccinations?tab=due')}
      ${statCard('fa-rotate', 'i-blue', r.retention.clients ? Math.round(r.retention.returning / r.retention.clients * 100) + '%' : '—', `Returning clients (${r.retention.returning} of ${r.retention.clients} seen)`)}
      ${statCard('fa-triangle-exclamation', r.compliance.overdue ? 'i-red' : 'i-teal', r.compliance.overdue, `Overdue on a vaccine · ${r.compliance.never} never vaccinated`, 'vaccinations?tab=due')}
    </div>
    <div class="card-x p-3 mb-4"><h2 class="h6">Revenue by month</h2>
      ${r.revenue.length ? `<div class="bar-chart mt-4">${r.revenue.map(m => `<div class="bar" style="height:${Math.max(2, Number(m.total) / max * 100)}%" title="${esc(monthLabel(m.month))}: ${money(m.total)}"><span>${Number(m.total) >= 1000 ? Math.round(Number(m.total) / 1000) + 'k' : Math.round(Number(m.total))}</span></div>`).join('')}</div>
        <div class="bar-labels mt-1">${r.revenue.map(m => `<div>${esc(monthLabel(m.month))}</div>`).join('')}</div>` : emptyHTML('No payments in this period', 'fa-chart-column')}
    </div>
    <div class="card-x p-3 mb-4"><h2 class="h6">Visits by type</h2>
      ${r.byCategory.length ? (() => { const top = Math.max(...r.byCategory.map(c => Number(c.count))); return r.byCategory.map(c => `<div class="hbar"><span class="hbar-label">${esc(SERVICE_CATEGORY[c.category] || c.category)}</span>
        <span class="hbar-track"><span class="hbar-fill cat-${esc(c.category)}" style="width:${Number(c.count) / top * 100}%"></span></span><span class="hbar-val">${esc(c.count)}</span></div>`).join(''); })() : emptyHTML('No visits in this period', 'fa-chart-bar')}
    </div>
    <div class="row g-3">
      <div class="col-lg-6"><div class="card-x h-100"><div class="p-3 pb-0"><h2 class="h6">Appointments by service</h2></div>
        ${small(r.byService, [{ label: 'Service', render: x => esc(x.service) }, { label: 'Booked', cls: 'text-end', render: x => esc(x.count) }, { label: 'Completed', cls: 'text-end', render: x => esc(x.completed) }])}</div></div>
      <div class="col-lg-6"><div class="card-x h-100"><div class="p-3 pb-0"><h2 class="h6">Revenue by payment method</h2></div>
        ${bars(r.byMethod, x => `${esc(METHOD_LABEL[x.method] || x.method)} <small class="text-muted">(${esc(x.count)})</small>`, x => x.total, v => money(v), 'hbar-wide')}</div></div>
      <div class="col-lg-4"><div class="card-x h-100"><div class="p-3 pb-0"><h2 class="h6">Appointment status</h2></div>
        ${bars(r.byStatus, x => statusBadge(x.status), x => x.count)}</div></div>
      <div class="col-lg-4"><div class="card-x h-100"><div class="p-3 pb-0"><h2 class="h6">Staff workload: completed visits by vet</h2></div>
        ${bars(r.byVet, x => esc(x.vet), x => x.count)}</div></div>
      <div class="col-lg-4"><div class="card-x h-100"><div class="p-3 pb-0"><h2 class="h6">Active patients by species</h2></div>
        ${bars(r.species, x => esc(x.species), x => x.count)}</div></div>
    </div>`;
  const f = document.getElementById('repForm');
  f.onsubmit = e => { e.preventDefault(); location.hash = `reports?from=${f.from.value}&to=${f.to.value}`; };
}

async function pageSettings() {
  const s = await api('settings_get');
  const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const closed = (s.closed_days || '').split(',');
  const pm = s._payments;
  const mode = m => m === 'simulate' ? '<span class="badge bg-warning text-dark">Test mode</span>' : m === 'sandbox' ? '<span class="badge bg-info text-dark">Sandbox</span>' : '<span class="badge bg-success">Live</span>';
  $view.innerHTML = pageHead('Settings') + `
    <div class="row g-3">
      <div class="col-lg-8" id="settingsMain"><form class="card-x p-4" id="settingsForm">
        <h2 class="h6 mb-3">Clinic details</h2>
        <div class="row g-3 mb-4">
          ${fieldHTML({ name: 'clinic_name', label: 'Clinic name', required: true, col: 'col-md-6' }, s.clinic_name)}
          ${fieldHTML({ name: 'clinic_phone', label: 'Phone', col: 'col-md-6' }, s.clinic_phone)}
          ${fieldHTML({ name: 'clinic_email', label: 'Email', type: 'email', col: 'col-md-6' }, s.clinic_email)}
          ${fieldHTML({ name: 'clinic_address', label: 'Address', col: 'col-md-6' }, s.clinic_address)}
          ${fieldHTML({ name: 'clinic_hours', label: 'Opening hours (as shown on the website)' }, s.clinic_hours)}
        </div>
        <h2 class="h6 mb-3">Online booking</h2>
        <div class="row g-3 mb-3">
          ${fieldHTML({ name: 'open_time', label: 'First appointment from', type: 'time', col: 'col-md-4' }, s.open_time)}
          ${fieldHTML({ name: 'close_time', label: 'Clinic closes at', type: 'time', col: 'col-md-4' }, s.close_time)}
          ${fieldHTML({ name: 'session_timeout', label: 'Auto log-out after (minutes idle)', type: 'number', min: 5, max: 1440, col: 'col-md-4' }, s.session_timeout)}
        </div>
        <label class="form-label">Closed on</label>
        <div class="mb-4">${days.map((d, i) => `<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" data-day="${i}" id="day${i}" ${closed.includes(String(i)) ? 'checked' : ''}><label class="form-check-label" for="day${i}">${d}</label></div>`).join('')}</div>
        <button class="btn btn-brand">Save settings</button>
      </form></div>
      <div class="col-lg-4"><div class="card-x p-4">
        <h2 class="h6 mb-3">Integrations</h2>
        <div class="d-flex justify-content-between mb-2"><span>M-Pesa (Daraja)</span>${mode(pm.mpesa)}</div>
        <div class="d-flex justify-content-between mb-2"><span>Card payments (Paystack)</span>${mode(pm.card)}</div>
        <div class="d-flex justify-content-between mb-2"><span>Email</span>${pm.email && pm.email !== 'off' ? `<span class="badge bg-success">${pm.email === 'brevo' ? 'Brevo' : 'On'}</span>` : pm.mail ? '<span class="badge bg-success">On</span>' : '<span class="badge bg-secondary">Off</span>'}</div>
        <div class="d-flex justify-content-between mb-3"><span>SMS (Africa's Talking)</span>${pm.sms === 'live' ? '<span class="badge bg-success">Live</span>' : pm.sms === 'sandbox' ? '<span class="badge bg-info text-dark">Sandbox</span>' : '<span class="badge bg-secondary">Off</span>'}</div>
        <p class="small text-muted mb-0">Payment, SMS and email keys are configured in the <code>.env</code> file on the server for security. See the README for step-by-step instructions.</p>
      </div>
      <div class="card-x p-4 mt-3">
        <h2 class="h6 mb-2"><i class="fa-solid fa-database me-1 text-brand"></i>Backups</h2>
        <p class="small text-muted">Free hosting does not back up your data. Download a backup at least once a week and keep it somewhere safe (Google Drive, a USB stick).</p>
        <div class="small mb-3">Last database backup: <strong>${s._backup.last ? fmtDateTime(s._backup.last) : 'never'}</strong></div>
        <div class="d-grid gap-2">
          <a class="btn btn-brand" href="${API_URL}?action=backup_download" data-backup><i class="fa-solid fa-download me-1"></i>Download database backup (.sql)</a>
          ${s._backup.zip ? `<a class="btn btn-light" href="${API_URL}?action=backup_files"><i class="fa-solid fa-file-zipper me-1"></i>Download uploaded photos &amp; files (.zip)</a>` : ''}
        </div>
        <p class="small text-muted mt-2 mb-0">To restore: phpMyAdmin → your database → Import → choose the .sql file.</p>
      </div>
      <div class="card-x p-4 mt-3 ${s._demo_users ? 'border border-warning' : ''}">
        <h2 class="h6 mb-2"><i class="fa-solid fa-flask me-1 ${s._demo_users ? 'text-warning' : 'text-brand'}"></i>Demo data</h2>
        ${s._demo_users
          ? `<p class="small">Demo data is loaded: ${s._demo_users === 1 ? '1 demo account' : `${s._demo_users} demo accounts`} (emails ending in <code>@demo.local</code>, password <code>Demo1234</code>) with sample pets, appointments, payments, records and ${s._demo_products} stock items. Each is marked <span class="badge-demo">Demo</span>. Remove it before real clients start using the system.</p>`
          : `<p class="small text-muted">Add sample vets, pet parents, pets, appointments, payments, records and medicines to try the system or show it to someone. Everything added is marked <span class="badge-demo">Demo</span> and can be removed in one click. Your real data is never changed.</p>`}
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-brand btn-sm" data-act="loadDemo" ${s._demo_users ? 'disabled' : ''}><i class="fa-solid fa-flask me-1"></i>Load demo data</button>
          <button class="btn btn-outline-danger btn-sm" data-act="removeDemo" ${s._demo_users || s._demo_products ? '' : 'disabled'}><i class="fa-solid fa-trash me-1"></i>Remove demo data</button>
        </div>
      </div>
      </div>
    </div>`;
  document.querySelector('[data-backup]').addEventListener('click', () => setTimeout(() => rerender(), 4000));
  ACTIONS.loadDemo = async () => {
    if (!(await confirmBox('Add sample vets, pet parents, pets, appointments, payments, records and medicines? They are all marked Demo and can be removed later. Your real data is not changed.', 'Load demo data', false))) return;
    const r = await run(() => api('demo_data_load', {}));
    toast(r.message);
    delete cache.vets; delete cache.owners;
    rerender();
  };
  ACTIONS.removeDemo = async () => {
    if (!(await confirmBox('Permanently delete all demo accounts and their pets, appointments, payments, records and adoption listings? Real accounts are not touched.', 'Remove demo data'))) return;
    const r = await run(() => api('demo_data_remove', {}));
    toast(r.message);
    delete cache.vets; delete cache.owners;
    rerender();
  };
  const f = document.getElementById('settingsForm');
  f.onsubmit = async e => {
    e.preventDefault();
    const d = formData(f);
    d.closed_days = [...f.querySelectorAll('[data-day]')].filter(c => c.checked).map(c => c.dataset.day).join(',');
    await withBusy(f.querySelector('button'), () => run(() => api('settings_save', d), 'Settings saved'));
    await loadSession();
    buildNav();
  };
  if (window.afterSettings) window.afterSettings(s);
}

// ---------------------------------------------------------------------------
// Profile
// ---------------------------------------------------------------------------
async function pageProfile() {
  const u = Session.user;
  $view.innerHTML = pageHead('My Profile') + `
    <div class="row g-3">
      <div class="col-lg-7"><form class="card-x p-4" id="profileForm">
        <h2 class="h6 mb-3">Personal details</h2>
        <div class="row g-3 mb-3">
          ${fieldHTML({ name: 'name', label: 'Full name', required: true, col: 'col-md-6' }, u.name)}
          ${fieldHTML({ name: 'email', label: 'Email', type: 'email', required: true, col: 'col-md-6' }, u.email)}
          ${fieldHTML({ name: 'phone', label: 'Phone (used for M-Pesa)', type: 'tel', col: 'col-md-6' }, u.phone)}
          ${u.role === 'vet' ? fieldHTML({ name: 'specialization', label: 'Specialisation', col: 'col-md-6' }, u.specialization) : ''}
          ${fieldHTML({ name: 'address', label: 'Address' }, u.address)}
          ${fieldHTML({ name: 'emergency_name', label: 'Emergency contact name', col: 'col-md-6', help: isOwner() ? 'Someone the clinic can call if they cannot reach you.' : '' }, u.emergency_name)}
          ${fieldHTML({ name: 'emergency_phone', label: 'Emergency contact phone', type: 'tel', col: 'col-md-6' }, u.emergency_phone)}
        </div>
        <div class="text-danger small mb-2" data-error></div>
        <button class="btn btn-brand">Save changes</button>
      </form></div>
      <div class="col-lg-5">
        <form class="card-x p-4 mb-3" id="pwForm">
          <h2 class="h6 mb-3">Change password</h2>
          ${fieldHTML({ name: 'current_password', label: 'Current password', type: 'password', autocomplete: 'current-password' })}
          <div class="my-3">${fieldHTML({ name: 'new_password', label: 'New password', type: 'password', autocomplete: 'new-password', help: 'At least 8 characters with letters and numbers.' })}</div>
          ${fieldHTML({ name: 'new_password2', label: 'Confirm new password', type: 'password', autocomplete: 'new-password' })}
          <div class="text-danger small my-2" data-error></div>
          <button class="btn btn-teal">Update password</button>
        </form>
        <div class="card-x p-4 border border-danger-subtle">
          <h2 class="h6 text-danger">Delete account</h2>
          <p class="small text-muted">This permanently deletes your account${u.role === 'owner' ? ', your pets, their records and your payment history' : ''}.</p>
          <button class="btn btn-outline-danger btn-sm" data-act="deleteAccount">Delete my account</button>
        </div>
      </div>
    </div>`;
  const pf = document.getElementById('profileForm');
  pf.onsubmit = async e => {
    e.preventDefault();
    pf.querySelector('[data-error]').textContent = '';
    await withBusy(pf.querySelector('button'), async () => {
      try {
        Session.user = { ...Session.user, ...(await api('update_profile', formData(pf))) };
        buildNav();
        toast('Profile updated');
      } catch (ex) { pf.querySelector('[data-error]').textContent = ex.message; }
    });
  };
  const pw = document.getElementById('pwForm');
  pw.onsubmit = async e => {
    e.preventDefault();
    const d = formData(pw), err = pw.querySelector('[data-error]');
    err.textContent = '';
    if (d.new_password !== d.new_password2) { err.textContent = 'New passwords do not match'; return; }
    await withBusy(pw.querySelector('button'), async () => {
      try { await api('change_password', d); pw.reset(); toast('Password changed'); } catch (ex) { err.textContent = ex.message; }
    });
  };
  ACTIONS.deleteAccount = () => openForm({
    title: 'Delete your account', size: 'modal-md', danger: true,
    intro: '<div class="alert alert-danger small mb-0">This cannot be undone.</div>',
    fields: [{ name: 'password', label: 'Enter your password to confirm', type: 'password', required: true }],
    submitText: 'Delete my account permanently',
    onSubmit: async d => { await api('delete_account', d); setTimeout(() => { location.href = 'index.html'; }, 800); return 'Account deleted'; },
  });
}

// ---------------------------------------------------------------------------
// Inventory
// ---------------------------------------------------------------------------
const CATEGORY_LABEL = { medicine: 'Medicine', vaccine: 'Vaccine', supply: 'Supply', food: 'Food', grooming: 'Grooming product' };
const MOVE_LABEL = { opening: 'Opening stock', restock: 'Restocked', adjustment: 'Stock count adjustment', expired: 'Expired', damaged: 'Damaged', dispensed: 'Dispensed', returned: 'Returned to stock' };

async function pageInventory() {
  const tab = route.params.get('tab') || 'all';
  const q = route.params.get('q') || '';
  const items = await api('products_list', { q, low: tab === 'low' ? 1 : '' });
  const lowCount = items.filter(i => i.low).length;
  const manage = can('stock.manage');
  const active = items.filter(i => Number(i.active));
  const saleValue = active.reduce((t, i) => t + Math.max(0, i.stock_qty) * i.price, 0);
  const costValue = active.reduce((t, i) => t + Math.max(0, i.stock_qty) * (i.cost_price || 0), 0);
  const noCost = active.filter(i => i.cost_price === null && i.stock_qty > 0).length;
  $view.innerHTML = pageHead('Inventory', manage ? `<button class="btn btn-brand" data-act="add"><i class="fa-solid fa-plus me-1"></i>Add item</button>` : '') + `
    ${manage && tab === 'all' && !q ? `<div class="row g-3 mb-3">
      ${statCard('fa-boxes-stacked', 'i-blue', active.length, 'Items in use')}
      ${statCard('fa-tags', 'i-teal', money(saleValue), 'Stock value at selling price')}
      ${statCard('fa-coins', 'i-orange', money(costValue), noCost ? `Stock value at cost (${noCost} item${noCost > 1 ? 's' : ''} without a cost price)` : 'Stock value at cost')}
      ${statCard('fa-triangle-exclamation', lowCount ? 'i-red' : 'i-teal', lowCount, 'Running low', 'inventory?tab=low')}
    </div>` : ''}
    <p class="text-muted small">Medicines, vaccines, food, grooming products and supplies. Stock goes down automatically when a vet dispenses an item on a visit, and comes back if it's removed from the invoice.</p>
    <div class="d-flex flex-wrap justify-content-between gap-2">
      ${tabsHTML('tab', [['all', 'All items'], ['low', 'Low stock']], tab)}
      <form id="invSearch" class="d-flex gap-2 mb-3"><input class="form-control form-control-sm" name="q" value="${esc(q)}" placeholder="Search items"><button class="btn btn-sm btn-light"><i class="fa-solid fa-magnifying-glass"></i></button></form>
    </div>
    ${tab === 'all' && lowCount ? `<div class="alert alert-warning small"><i class="fa-solid fa-triangle-exclamation me-1"></i>${lowCount} item${lowCount > 1 ? 's are' : ' is'} at or below the reorder level.</div>` : ''}
    <div class="card-x">${tableHTML([
      { label: 'Item', render: i => `<strong>${esc(i.name)}</strong>${i.is_demo ? ' <span class="badge-demo">Demo</span>' : ''}<div class="small text-muted">${esc(CATEGORY_LABEL[i.category] || i.category)}${Number(i.active) ? '' : ' · hidden'}</div>` },
      { label: 'Price', cls: 'text-end', render: i => `${money(i.price)}<div class="small text-muted">per ${esc(i.unit)}${manage && i.cost_price !== null ? ` · cost ${money(i.cost_price)}` : ''}</div>` },
      { label: 'In stock', cls: 'text-end', render: i => `<strong class="${i.low ? 'text-danger' : ''}">${Number(i.stock_qty)}</strong> ${esc(i.unit)}${i.low ? '<div><span class="status s-overdue">Reorder</span></div>' : ''}` },
      { label: 'Reorder at', cls: 'text-end', render: i => `${Number(i.reorder_level)}` },
      { label: '', cls: 'text-end text-nowrap', render: i => (manage ? btn('adjust', i.id, 'Stock', 'btn-teal', 'fa-box-open') + ' ' : '') + btn('history', i.id, '', 'btn-light', 'fa-clock-rotate-left', 'title="History"') + (manage ? ' ' + btn('edit', i.id, '', 'btn-light', 'fa-pen', 'title="Edit"') : '') + (isAdmin() ? ' ' + btn('del', i.id, '', 'btn-light text-danger', 'fa-trash', 'title="Delete"') : '') },
    ], items, tab === 'low' ? 'Nothing is running low.' : 'No items yet. Add your medicines, vaccines and supplies.')}</div>`;
  ACTIONS.__tab = v => { location.hash = 'inventory?tab=' + v; };
  const f = document.getElementById('invSearch');
  f.onsubmit = e => { e.preventDefault(); location.hash = `inventory?tab=${tab}&q=${encodeURIComponent(f.q.value.trim())}`; };
  const byId = id => items.find(i => String(i.id) === String(id));
  const form = item => openForm({
    title: item ? `Edit ${item.name}` : 'Add inventory item', size: 'modal-md',
    fields: [
      { name: 'name', label: 'Name', required: true, placeholder: 'e.g. Amoxicillin 250mg' },
      { name: 'category', label: 'Category', type: 'select', options: Object.entries(CATEGORY_LABEL), col: 'col-md-6' },
      { name: 'unit', label: 'Unit', placeholder: 'tablet, ml, dose, pair…', col: 'col-md-6' },
      { name: 'price', label: 'Selling price per unit (KSh)', type: 'number', min: 0, step: '0.01', required: true, col: 'col-md-6' },
      { name: 'cost_price', label: 'Cost price per unit (KSh)', type: 'number', min: 0, step: '0.01', col: 'col-md-6', help: 'What you pay the supplier. Optional; used for stock value.' },
      { name: 'reorder_level', label: 'Warn me when stock falls to', type: 'number', min: 0, step: '0.01', col: 'col-md-6' },
      ...(item ? [] : [{ name: 'stock_qty', label: 'Opening stock', type: 'number', min: 0, step: '0.01' }]),
      { name: 'active', label: 'Available for dispensing', type: 'checkbox' },
    ],
    values: item ? { ...item, active: String(item.active) } : { category: 'medicine', unit: 'tablet', reorder_level: 10, stock_qty: 0, active: '1' },
    onSubmit: async d => { if (item) d.id = item.id; await api('product_save', d); rerender(); return 'Item saved'; },
  });
  ACTIONS.add = () => form();
  ACTIONS.edit = id => form(byId(id));
  ACTIONS.adjust = id => {
    const i = byId(id);
    openForm({
      title: `Update stock: ${i.name}`, size: 'modal-md',
      intro: `<div class="small">Currently <strong>${Number(i.stock_qty)} ${esc(i.unit)}</strong> in stock.</div>`,
      fields: [
        { name: 'reason', label: 'What happened?', type: 'select', options: [['restock', 'New stock arrived'], ['adjustment', 'Correct the count (+ or −)'], ['expired', 'Expired — remove'], ['damaged', 'Damaged / lost — remove']], col: 'col-md-7' },
        { name: 'change', label: `Quantity (${i.unit})`, type: 'number', step: '0.01', required: true, col: 'col-md-5', help: 'For a correction, use a minus sign to remove, e.g. -3.' },
        { name: 'note', label: 'Note (supplier, invoice no., batch…)', },
      ],
      values: { reason: 'restock' },
      submitText: 'Update stock',
      onSubmit: async d => { await api('stock_adjust', { product_id: i.id, ...d }); rerender(); return 'Stock updated'; },
    });
  };
  ACTIONS.history = async id => {
    const i = byId(id);
    const moves = await run(() => api('stock_movements', { product_id: id }));
    openView(`Stock history: ${i.name}`, tableHTML([
      { label: 'Date', render: m => fmtDateTime(m.created_at) },
      { label: 'Change', cls: 'text-end', render: m => `<strong class="${Number(m.change_qty) < 0 ? 'text-danger' : 'text-success'}">${Number(m.change_qty) > 0 ? '+' : ''}${Number(m.change_qty)}</strong>` },
      { label: 'Reason', render: m => esc(MOVE_LABEL[m.reason] || m.reason) + (m.note ? `<div class="small text-muted">${esc(m.note)}</div>` : '') },
      { label: 'By', render: m => esc(m.user_name || '—') },
    ], moves, 'No stock changes yet'));
  };
  ACTIONS.del = async id => {
    if (!(await confirmBox('Delete this item?', 'Delete'))) return;
    const r = await run(() => api('product_delete', { id }));
    toast(r.message || 'Item deleted');
    rerender();
  };
}

// ---------------------------------------------------------------------------
// Vet schedules
// ---------------------------------------------------------------------------
const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

async function pageSchedule() {
  let vetId = route.params.get('vet') || '';
  let vets = [];
  const picker = Session.user.role !== 'vet';           // admin and front-desk staff choose a vet
  const readOnly = !isAdmin() && Session.user.role !== 'vet';
  if (picker) {
    vets = await getVets();
    if (!vets.length) {
      $view.innerHTML = pageHead('Vet Schedules') + `<div class="card-x">${emptyHTML('No active vets yet. Add vets under Users.', 'fa-user-doctor')}</div>`;
      return;
    }
    if (!vetId) vetId = String(vets[0].id);
  }
  const data = await api('schedule_get', picker ? { vet_id: vetId } : null);
  const closed = (data.clinic.closed_days || '').split(',');
  const byDay = {};
  data.weekly.forEach(w => { byDay[w.weekday] = w; });
  const noSchedule = !data.weekly.length;
  const order = [1, 2, 3, 4, 5, 6, 0];
  $view.innerHTML = pageHead(picker ? 'Vet Schedules' : 'My Schedule') + `
    ${readOnly ? '<p class="small text-muted">View only. Ask an administrator or the vet to change working hours.</p>' : ''}${picker ? `<div class="mb-3" style="max-width:360px"><label class="form-label small">Veterinarian</label><select class="form-select" id="vetPick">${vets.map(v => `<option value="${v.id}" ${String(v.id) === vetId ? 'selected' : ''}>${esc(v.name)}</option>`).join('')}</select></div>` : ''}
    <div class="row g-3">
      <div class="col-lg-7"><form class="card-x p-4" id="schedForm">
        <h2 class="h6">Weekly working hours${picker ? ' · ' + esc(data.vet.name) : ''}</h2>
        <p class="small text-muted">Clients can only book ${picker ? 'this vet' : 'you'} during these hours. Clinic hours are ${esc(data.clinic.open)}–${esc(data.clinic.close)}.
          ${noSchedule ? '<br><strong>No hours set yet, so the full clinic hours are used.</strong>' : ''}</p>
        ${order.map(d => {
          const w = byDay[d];
          const clinicClosed = closed.includes(String(d));
          return `<div class="sched-row"><div class="form-check"><input class="form-check-input" type="checkbox" id="wd${d}" data-day="${d}" ${w || (noSchedule && !clinicClosed) ? 'checked' : ''} ${clinicClosed ? 'disabled' : ''}>
            <label class="form-check-label" for="wd${d}">${DAY_NAMES[d]}</label></div>
            ${clinicClosed ? '<span class="small text-muted" style="grid-column: span 2">Clinic closed</span>' : `
            <input type="time" class="form-control form-control-sm" data-start="${d}" value="${esc(w ? w.start : data.clinic.open)}">
            <div class="d-flex align-items-center gap-2"><span class="small text-muted">to</span><input type="time" class="form-control form-control-sm" data-end="${d}" value="${esc(w ? w.end : data.clinic.close)}"></div>`}
          </div>`;
        }).join('')}
        <button class="btn btn-brand mt-3">Save working hours</button>
      </form></div>
      <div class="col-lg-5"><div class="card-x p-4">
        <h2 class="h6">Time off &amp; leave</h2>
        <p class="small text-muted">No bookings can be made on these days.</p>
        <form class="row g-2 mb-3" id="offForm">
          <div class="col-6"><label class="form-label small">From</label><input type="date" class="form-control form-control-sm" name="date_from" min="${todayISO()}" required></div>
          <div class="col-6"><label class="form-label small">To</label><input type="date" class="form-control form-control-sm" name="date_to" min="${todayISO()}" required></div>
          <div class="col-12"><input class="form-control form-control-sm" name="reason" placeholder="Reason (optional), e.g. Annual leave"></div>
          <div class="col-12"><button class="btn btn-sm btn-teal">Add time off</button></div>
        </form>
        ${data.time_off.length ? `<ul class="list-group">${data.time_off.map(t => `<li class="list-group-item d-flex justify-content-between align-items-center">
          <span>${fmtDate(t.date_from)}${t.date_to !== t.date_from ? ' – ' + fmtDate(t.date_to) : ''}${t.reason ? `<div class="small text-muted">${esc(t.reason)}</div>` : ''}</span>
          <button class="btn btn-sm btn-light text-danger" data-act="delOff" data-id="${t.id}" title="Remove"><i class="fa-solid fa-xmark"></i></button></li>`).join('')}</ul>` : '<div class="small text-muted">No upcoming time off.</div>'}
      </div></div>
    </div>`;
  const extra = isAdmin() ? { vet_id: vetId } : {};
  document.getElementById('vetPick')?.addEventListener('change', e => { location.hash = 'schedule?vet=' + e.target.value; });
  const sf = document.getElementById('schedForm');
  if (readOnly) {
    document.querySelectorAll('#schedForm input, #offForm').forEach(x => { if (x.id === 'offForm') x.remove(); else x.disabled = true; });
    sf.querySelector('button')?.remove();
    document.querySelectorAll('[data-act="delOff"]').forEach(b => b.remove());
    return;
  }
  sf.onsubmit = async e => {
    e.preventDefault();
    const weekly = [...sf.querySelectorAll('[data-day]')].filter(c => c.checked && !c.disabled).map(c => {
      const d = c.dataset.day;
      return { weekday: Number(d), start: sf.querySelector(`[data-start="${d}"]`).value, end: sf.querySelector(`[data-end="${d}"]`).value };
    });
    if (!weekly.length && !(await confirmBox('No working days are ticked, so nobody will be able to book. Save anyway?', 'Save', true))) return;
    await withBusy(sf.querySelector('button'), () => run(() => api('schedule_save', { ...extra, weekly }), 'Working hours saved'));
    rerender();
  };
  const of = document.getElementById('offForm');
  of.onsubmit = async e => {
    e.preventDefault();
    const d = formData(of);
    if (!d.date_from || !d.date_to) return toast('Choose both dates', 'warning');
    const r = await withBusy(of.querySelector('button'), () => run(() => api('time_off_add', { ...extra, ...d }), 'Time off added'));
    if (r && r.clashes) toast(`Heads up: ${r.clashes} existing appointment${r.clashes > 1 ? 's fall' : ' falls'} on these days. Reschedule or reassign ${r.clashes > 1 ? 'them' : 'it'}.`, 'warning');
    rerender();
  };
  ACTIONS.delOff = async id => { await run(() => api('time_off_delete', { id }), 'Time off removed'); rerender(); };
}

// ---------------------------------------------------------------------------
// Activity log (admin)
// ---------------------------------------------------------------------------
const AREA_LABEL = { account: 'Accounts & logins', pets: 'Pets', appointments: 'Appointments', records: 'Medical', payments: 'Payments & invoices', stock: 'Stock', users: 'Users', settings: 'Settings', schedule: 'Schedules', community: 'Community', files: 'Files' };
async function pageActivity() {
  const area = route.params.get('area') || '';
  const date = route.params.get('date') || '';
  const q = route.params.get('q') || '';
  const rows = await api('activity_list', { area, date, q });
  $view.innerHTML = pageHead('Activity Log') + `
    <p class="text-muted small">Every change made in the system: who did it and when. The latest 300 entries are shown.</p>
    <form class="card-x p-3 mb-3 d-flex flex-wrap gap-2" id="actFilter">
      <select class="form-select form-select-sm" name="area" style="width:auto"><option value="">All areas</option>${Object.entries(AREA_LABEL).map(([k, l]) => `<option value="${k}" ${k === area ? 'selected' : ''}>${l}</option>`).join('')}</select>
      <input type="date" class="form-control form-control-sm" name="date" value="${esc(date)}" style="width:auto">
      <input class="form-control form-control-sm" name="q" value="${esc(q)}" placeholder="Search person or description" style="max-width:260px">
      <button class="btn btn-sm btn-light"><i class="fa-solid fa-magnifying-glass me-1"></i>Filter</button>
      ${area || date || q ? '<a class="btn btn-sm btn-link" href="#activity">Clear</a>' : ''}
    </form>
    <div class="card-x">${tableHTML([
      { label: 'When', render: r => `<span title="${esc(r.created_at)}">${fmtDateTime(r.created_at)}</span><div class="small text-muted">${timeAgo(r.created_at)}</div>` },
      { label: 'Who', render: r => r.user_name ? `${esc(r.user_name)}<div class="small text-muted">${esc(ROLE_LABEL[r.user_role] || '')}</div>` : '<span class="text-muted">Deleted user</span>' },
      { label: 'Area', render: r => `<span class="badge text-bg-light">${esc(AREA_LABEL[r.entity_type] || r.entity_type)}</span>` },
      { label: 'What happened', render: r => esc(r.summary) },
    ], rows, 'No activity found')}</div>`;
  const f = document.getElementById('actFilter');
  f.onsubmit = e => { e.preventDefault(); const p = new URLSearchParams(formData(f)); [...p.keys()].forEach(k => !p.get(k) && p.delete(k)); location.hash = 'activity?' + p.toString(); };
}

const PAGES = {
  overview: pageOverview, pets: pagePets, appointments: pageAppointments, records: pageRecords,
  vaccinations: pageVaccinations, payments: pagePayments, adoption: pageAdoption, lostfound: pageLostfound,
  forum: pageForum, users: pageUsers, services: pageServices, reports: pageReports, settings: pageSettings, profile: pageProfile,
  inventory: pageInventory, schedule: pageSchedule, calendar: pageCalendar, clients: pageUsers, activity: pageActivity,
};

// ---------------------------------------------------------------------------
// Global events & start-up
// ---------------------------------------------------------------------------
document.addEventListener('click', e => {
  const tab = e.target.closest('[data-tab]');
  if (tab && ACTIONS['__' + tab.dataset.tab]) {
    e.preventDefault();
    ACTIONS['__' + tab.dataset.tab](tab.dataset.value);
    return;
  }
  const el = e.target.closest('[data-act]');
  const fn = el && (ACTIONS[el.dataset.act] || GLOBAL_ACTIONS[el.dataset.act]);
  if (fn) {
    e.preventDefault();
    Promise.resolve(fn(el.dataset.id, el)).catch(err => { if (err && err.message) console.warn(err); });
  }
  const notif = e.target.closest('[data-notif]');
  if (notif) {
    api('notifications_read', { id: notif.dataset.notif }).then(r => setUnread(r.unread)).catch(() => {});
    bootstrap.Dropdown.getInstance(document.getElementById('notifBtn'))?.hide();
  }
  if (e.target.closest('[data-logout]')) logout();
});

async function logout() {
  try { await api('logout', {}); } catch (e) { /* ignore */ }
  location.href = 'index.html';
}

document.getElementById('logoutBtn').onclick = logout;
document.getElementById('menuBtn').onclick = () => {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('backdrop').classList.add('show');
};
document.getElementById('backdrop').onclick = closeSidebar;
document.getElementById('notifBtn').addEventListener('show.bs.dropdown', () => loadNotifications().catch(() => {}));
document.getElementById('markAllRead').onclick = async () => {
  const r = await api('notifications_read', {});
  setUnread(r.unread);
  loadNotifications();
};
window.addEventListener('hashchange', render);

(async function init() {
  try {
    await loadSession();
  } catch (e) {
    $view.innerHTML = `<div class="alert alert-danger m-4">${esc(e.message)}</div>`;
    return;
  }
  if (!Session.user) return goLogin();
  buildNav();
  setUnread(Session.unread);
  if (isAdmin()) {
    api('payment_options').then(o => {
      if (o.mpesa.mode === 'simulate' || o.card.mode === 'simulate') {
        document.getElementById('modeBanner').innerHTML = `<div class="demo-banner px-4 py-2"><i class="fa-solid fa-flask me-1"></i>Payments are in <strong>test mode</strong> — no real money moves. Add your M-Pesa / Paystack keys in <code>.env</code> to go live (see README).</div>`;
      }
    }).catch(() => {});
  }
  render();
  // Refresh the notification badge every 2 minutes while the tab is visible.
  setInterval(() => {
    if (document.visibilityState === 'visible') {
      api('notifications').then(list => setUnread(list.filter(n => !Number(n.is_read)).length)).catch(err => { if (err.status === 401) goLogin(); });
    }
  }, 120000);
})();
