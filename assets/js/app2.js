/* Phase 2: enquiries, weight history chart, digital health card (QR), reminders & messaging settings */
'use strict';

// ---------------------------------------------------------------------------
// Navigation
// ---------------------------------------------------------------------------
(function addNav() {
  const item = ['enquiries', 'Enquiries', 'fa-envelope-open-text'];
  const after = { owner: 'payments', reception: 'clients', admin: 'users' };
  Object.entries(after).forEach(([role, key]) => {
    const list = NAV[role];
    const i = list.findIndex(x => Array.isArray(x) && x[0] === key);
    list.splice(i + 1, 0, role === 'owner' ? ['enquiries', 'Messages to clinic', 'fa-envelope-open-text'] : item);
  });
  PAGES.enquiries = pageEnquiries;
  if (typeof AREA_LABEL === 'object') AREA_LABEL.enquiries = 'Enquiries';
})();

/** Load a script from a CDN once. */
const loadScript = (() => {
  const done = {};
  return src => done[src] || (done[src] = new Promise((res, rej) => {
    const s = document.createElement('script');
    s.src = src; s.onload = res; s.onerror = () => { delete done[src]; rej(new Error('Could not load ' + src)); };
    document.head.appendChild(s);
  }));
})();
const QR_LIB = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
async function qrSVG(text) {
  await loadScript(QR_LIB);
  const qr = qrcode(0, 'M');
  qr.addData(text);
  qr.make();
  return qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
}

// ---------------------------------------------------------------------------
// Pet profile: weight tab and health card
// ---------------------------------------------------------------------------
window.afterViewPet = function (p, body, canEdit) {
  const pane = body.querySelector('#pt-wt');
  if (pane) loadWeights(p, pane, canEdit);
  ACTIONS.petCard = () => petCardModal(p.id);
};

async function loadWeights(p, pane, canEdit) {
  let data;
  try { data = await api('pet_weights', { pet_id: p.id }); } catch (e) { pane.innerHTML = `<div class="text-danger small">${esc(e.message)}</div>`; return; }
  const w = data.weights;
  const mayAdd = canEdit && (!isStaff() || can('treatment.write') || can('appointments.manage'));
  const last = w[w.length - 1], first = w[0];
  let change = '';
  if (w.length > 1) {
    const d = last.weight_kg - first.weight_kg;
    const pct = first.weight_kg ? Math.round(d / first.weight_kg * 100) : 0;
    change = `<span class="${d > 0 ? 'text-success' : d < 0 ? 'text-danger' : 'text-muted'}">${d > 0 ? '+' : ''}${d.toFixed(1)} kg (${pct > 0 ? '+' : ''}${pct}%)</span> since ${fmtDate(first.measured_on)}`;
  }
  pane.innerHTML = `
    ${w.length ? `<div class="d-flex flex-wrap gap-3 align-items-baseline mb-2"><div><span class="h4 mb-0">${esc(last.weight_kg)} kg</span> <span class="small text-muted">on ${fmtDate(last.measured_on)}</span></div><div class="small">${change}</div></div>` : ''}
    ${w.length > 1 ? weightChart(w) : w.length ? '<p class="small text-muted">Add another weight to see the growth chart.</p>' : emptyHTML('No weights recorded yet', 'fa-weight-scale')}
    ${mayAdd ? `<form class="row g-2 align-items-end my-3" data-wform>
      <div class="col-4 col-md-3"><label class="form-label small mb-1">Weight (kg)</label><input class="form-control form-control-sm" name="weight_kg" type="number" step="0.01" min="0.01" max="1000" required></div>
      <div class="col-4 col-md-3"><label class="form-label small mb-1">Date</label><input class="form-control form-control-sm" name="measured_on" type="date" value="${todayISO()}" max="${todayISO()}"></div>
      <div class="col-4 col-md-4"><label class="form-label small mb-1">Note</label><input class="form-control form-control-sm" name="note" maxlength="200" placeholder="optional"></div>
      <div class="col-12 col-md-2"><button class="btn btn-sm btn-brand w-100"><i class="fa-solid fa-plus me-1"></i>Add</button></div>
    </form>` : ''}
    ${w.length ? `<details class="small"><summary class="text-muted">All weights (${w.length})</summary>${tableHTML([
      { label: 'Date', render: x => fmtDate(x.measured_on) },
      { label: 'Weight', cls: 'text-end', render: x => esc(x.weight_kg) + ' kg' },
      { label: 'Note', render: x => esc(x.note || '') + (x.by_name ? ` <span class="text-muted">· ${esc(x.by_name)}</span>` : '') },
      { label: '', cls: 'text-end', render: x => mayAdd && !x.record_id ? `<button class="btn btn-sm btn-link text-danger p-0" data-wdel="${x.id}" title="Remove"><i class="fa-solid fa-trash"></i></button>` : '' },
    ], [...w].reverse())}</details>` : ''}`;
  const f = pane.querySelector('[data-wform]');
  if (f) f.onsubmit = async e => {
    e.preventDefault();
    await withBusy(f.querySelector('button'), async () => {
      try { await api('weight_add', { pet_id: p.id, ...formData(f) }); toast('Weight saved'); loadWeights(p, pane, canEdit); } catch (ex) { toast(ex.message, 'error'); }
    });
  };
  pane.querySelectorAll('[data-wdel]').forEach(b => b.onclick = async () => {
    try { await api('weight_delete', { id: b.dataset.wdel }); toast('Weight removed'); loadWeights(p, pane, canEdit); } catch (ex) { toast(ex.message, 'error'); }
  });
}

function weightChart(w) {
  const W = 600, H = 220, L = 40, R = 14, T = 14, B = 28;
  const ts = w.map(x => new Date(x.measured_on + 'T00:00:00').getTime());
  const ys = w.map(x => x.weight_kg);
  let t0 = Math.min(...ts), t1 = Math.max(...ts);
  if (t0 === t1) { t0 -= 86400000; t1 += 86400000; }
  let y0 = Math.min(...ys), y1 = Math.max(...ys);
  const pad = Math.max((y1 - y0) * 0.15, y1 * 0.05, 0.2);
  y0 = Math.max(0, y0 - pad); y1 += pad;
  const X = t => L + (t - t0) / (t1 - t0) * (W - L - R);
  const Y = v => T + (1 - (v - y0) / (y1 - y0)) * (H - T - B);
  const pts = w.map((x, i) => [X(ts[i]), Y(x.weight_kg)]);
  const line = pts.map((pt, i) => (i ? 'L' : 'M') + pt[0].toFixed(1) + ' ' + pt[1].toFixed(1)).join(' ');
  const area = `${line} L${pts[pts.length - 1][0].toFixed(1)} ${H - B} L${pts[0][0].toFixed(1)} ${H - B} Z`;
  const ticks = [0, 1, 2, 3].map(i => y0 + (y1 - y0) * i / 3);
  const longSpan = t1 - t0 > 200 * 86400000;
  const fmtX = t => new Date(t).toLocaleDateString('en-GB', longSpan ? { month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short' });
  const xt = [t0, (t0 + t1) / 2, t1];
  return `<svg class="wt-chart" viewBox="0 0 ${W} ${H}" role="img" aria-label="Weight over time">
    ${ticks.map(v => `<line class="grid" x1="${L}" x2="${W - R}" y1="${Y(v)}" y2="${Y(v)}"/><text x="${L - 6}" y="${Y(v) + 4}" text-anchor="end">${v.toFixed(y1 - y0 < 10 ? 1 : 0)}</text>`).join('')}
    ${xt.map((t, i) => `<text x="${X(t)}" y="${H - 8}" text-anchor="${['start', 'middle', 'end'][i]}">${fmtX(t)}</text>`).join('')}
    <path class="area" d="${area}"/><path class="line" d="${line}"/>
    ${pts.map((pt, i) => `<circle class="dot" cx="${pt[0].toFixed(1)}" cy="${pt[1].toFixed(1)}" r="4"><title>${fmtDate(w[i].measured_on)}: ${w[i].weight_kg} kg</title></circle>`).join('')}
    <text x="4" y="12">kg</text>
  </svg>`;
}

async function petCardModal(petId, fresh) {
  const p = fresh || await run(() => api('pet_get', { id: petId }));
  const on = !!p.card_token;
  const url = on ? new URL('card.php?t=' + p.card_token, location.href).href : '';
  const body = openView(`${p.name}'s health card`, on ? `
    <div class="text-center">
      <div class="qr-box mb-2" id="qrBox"><span class="spinner-border spinner-border-sm"></span></div>
      <p class="small text-muted">Print this QR code for ${esc(p.name)}'s collar tag or save it on your phone. Anyone who scans it sees ${esc(p.name)}'s photo, vaccine status and allergies, and a phone number to call if ${esc(p.name)} is found. Medical notes and your address are never shown.</p>
      <div class="input-group input-group-sm mb-3"><input class="form-control" value="${esc(url)}" readonly id="cardUrl"><button class="btn btn-light" id="copyCard"><i class="fa-regular fa-copy me-1"></i>Copy</button></div>
      <div class="d-flex flex-wrap gap-2 justify-content-center mb-3">
        <a class="btn btn-brand btn-sm" target="_blank" href="${esc(url)}"><i class="fa-solid fa-id-card me-1"></i>Open card</a>
        <a class="btn btn-light btn-sm" target="_blank" href="${esc(url)}&print=1"><i class="fa-solid fa-print me-1"></i>Print tag</a>
      </div>
      <div class="form-check form-switch d-inline-block text-start mb-3"><input class="form-check-input" type="checkbox" id="cardPhone" ${Number(p.card_show_phone) ? 'checked' : ''}>
        <label class="form-check-label small" for="cardPhone">Show the owner's phone number on the card (otherwise the clinic's number is shown)</label></div>
      <div class="d-flex gap-2 justify-content-center">
        <button class="btn btn-sm btn-outline-secondary" id="cardNew"><i class="fa-solid fa-rotate me-1"></i>New link (old QR stops working)</button>
        <button class="btn btn-sm btn-outline-danger" id="cardOff"><i class="fa-solid fa-ban me-1"></i>Turn off</button>
      </div>
    </div>` : `
    <div class="text-center py-2">
      <i class="fa-solid fa-qrcode fa-3x text-brand mb-3"></i>
      <p>Create a digital health card with a QR code for ${esc(p.name)}. If ${esc(p.name)} gets lost, whoever finds them can scan the tag to see who to call. Vets can see vaccine status and allergies at a glance.</p>
      <div class="form-check form-switch d-inline-block text-start mb-3"><input class="form-check-input" type="checkbox" id="cardPhone">
        <label class="form-check-label small" for="cardPhone">Show the owner's phone number (otherwise the clinic's number is shown)</label></div>
      <div><button class="btn btn-brand" id="cardOn"><i class="fa-solid fa-qrcode me-1"></i>Create health card</button></div>
    </div>`, 'modal-md');
  const phone = () => body.querySelector('#cardPhone').checked ? '1' : '0';
  const save = async extra => {
    const r = await run(() => api('pet_card_save', { pet_id: p.id, show_phone: phone(), ...extra }));
    Object.assign(p, r);
    return r;
  };
  if (on) {
    qrSVG(url).then(svg => { body.querySelector('#qrBox').innerHTML = svg; }).catch(() => {
      body.querySelector('#qrBox').innerHTML = '<div class="small text-muted p-3">QR code could not load. Use the link below.</div>';
    });
    body.querySelector('#copyCard').onclick = () => { navigator.clipboard?.writeText(url).then(() => toast('Link copied')); };
    body.querySelector('#cardPhone').onchange = () => save({}).then(() => toast('Saved'));
    body.querySelector('#cardNew').onclick = async () => {
      if (!(await confirmBox('Make a new link? Printed QR codes with the old link will stop working.', 'New link', false))) return;
      await save({ new_link: '1' });
      petCardModal(p.id, p);
    };
    body.querySelector('#cardOff').onclick = async () => {
      if (!(await confirmBox(`Turn off ${p.name}'s health card? The QR code will stop working.`, 'Turn off'))) return;
      await save({ enabled: '0' });
      toast('Health card turned off');
      petCardModal(p.id, p);
    };
  } else {
    body.querySelector('#cardOn').onclick = async () => { await save({ enabled: '1' }); petCardModal(p.id, p); };
  }
}

// ---------------------------------------------------------------------------
// Enquiries
// ---------------------------------------------------------------------------
const ENQ_STATUS = { open: 'Waiting for reply', answered: 'Answered', closed: 'Closed' };

async function pageEnquiries() {
  const staff = isStaff();
  const tab = route.params.get('tab') || (staff ? 'open' : 'all');
  const rows = await api('enquiries_list', staff && tab !== 'all' ? { status: tab } : null);
  $view.innerHTML = pageHead(staff ? 'Enquiries' : 'Messages to the clinic', staff ? '' : btn('newEnq', '', 'New message', 'btn-brand', 'fa-plus')) + `
    <p class="text-muted small">${staff ? 'Questions from the website contact form and from pet parents. Replies go to the client in the app, and by SMS or email when those are switched on.' : 'Ask the clinic a question. The front desk will reply here, and you will get a notification.'}</p>
    ${staff ? tabsHTML('enq', [['open', 'Waiting for reply'], ['answered', 'Answered'], ['closed', 'Closed'], ['all', 'All']], tab) : ''}
    <div class="card-x">${tableHTML([
      { label: 'Received', render: e => `${fmtDateTime(e.created_at)}<div class="small text-muted">${timeAgo(e.updated_at)}</div>` },
      ...(staff ? [{ label: 'From', render: e => `${esc(e.name)}${e.user_id ? '' : ' <span class="badge text-bg-light">Website</span>'}<div class="small text-muted">${esc(e.phone || '')}${e.phone && realEmail(e.email) ? ' · ' : ''}${esc(realEmail(e.email) || '')}</div>` }] : []),
      { label: 'Subject', render: e => `<a href="#" data-act="openEnq" data-id="${e.id}" class="fw-semibold">${esc(e.subject)}</a>${e.pet_name ? ` <span class="small text-muted">· ${esc(e.pet_name)}</span>` : ''}<div class="small text-muted text-truncate" style="max-width:380px">${esc(e.message)}</div>` },
      { label: 'Replies', cls: 'text-center', render: e => esc(e.replies) },
      { label: 'Status', render: e => statusBadge(e.status, ENQ_STATUS[e.status]) },
    ], rows, staff ? 'No enquiries here' : 'You have not sent any messages yet')}</div>`;
  ACTIONS.__enq = v => { location.hash = 'enquiries?tab=' + v; };
  ACTIONS.openEnq = id => enquiryView(id);
  ACTIONS.newEnq = async () => {
    const pets = await getMyPets().catch(() => []);
    openForm({
      title: 'Message the clinic', submitText: 'Send',
      fields: [
        { name: 'subject', label: 'Subject', required: true },
        ...(pets.length ? [{ name: 'pet_id', label: 'About which pet? (optional)', type: 'select', placeholder: '—', options: pets.map(x => [x.id, x.name]) }] : []),
        { name: 'message', label: 'Message', type: 'textarea', rows: 5, required: true },
      ],
      onSubmit: async d => { await api('enquiry_create', d); rerender(); return 'Message sent'; },
    });
  };
  const id = route.params.get('id');
  if (id) { setHashQuiet('enquiries', staff ? 'tab=' + tab : ''); enquiryView(id); }
}

async function enquiryView(id) {
  const e = await run(() => api('enquiry_get', { id }));
  const staff = isStaff();
  const msg = (who, role, at, text, mine) => `<div class="enq-msg ${mine ? 'staff' : ''}"><div class="meta"><strong>${esc(who)}</strong>${role ? ' · ' + esc(role) : ''} · ${fmtDateTime(at)}</div>${esc(text)}</div>`;
  const body = openView(e.subject, `
    ${staff ? `<div class="small mb-3"><strong>${esc(e.name)}</strong>${e.user_id ? '' : ' <span class="badge text-bg-light">Website</span>'} ${e.phone ? `· <a href="tel:${esc(e.phone)}">${esc(e.phone)}</a>` : ''} ${realEmail(e.email) ? `· <a href="mailto:${esc(e.email)}">${esc(e.email)}</a>` : ''}${e.pet_name ? ` · Pet: ${esc(e.pet_name)}` : ''}</div>` : ''}
    ${msg(e.name, staff ? '' : 'You', e.created_at, e.message, false)}
    ${e.thread.map(r => msg(r.author || 'Clinic', ROLE_LABEL[r.author_role] || '', r.created_at, r.message, ['vet', 'admin', 'reception', 'assistant'].includes(r.author_role))).join('')}
    ${e.status === 'closed' && !staff ? '<p class="small text-muted">This conversation is closed. Send a new message if you need anything else.</p>' : `
    <form class="mt-3" id="enqReply">
      <textarea class="form-control mb-2" name="message" rows="3" maxlength="3000" placeholder="${staff ? 'Write your reply…' : 'Add a message…'}" required></textarea>
      ${staff && !e.user_id ? `<div class="form-text mb-2">This person has no account. ${e.phone || realEmail(e.email) ? 'Your reply is sent by SMS or email when those are switched on in Settings; otherwise call them.' : 'Call them to reply.'}</div>` : ''}
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <button class="btn btn-brand btn-sm"><i class="fa-solid fa-paper-plane me-1"></i>Send</button>
        ${staff ? `<span class="ms-auto small text-muted">Status:</span>${['open', 'answered', 'closed'].map(st => `<button type="button" class="btn btn-sm ${st === e.status ? 'btn-secondary' : 'btn-light'}" data-st="${st}">${ENQ_STATUS[st]}</button>`).join('')}` : ''}
      </div>
    </form>`}`);
  const f = body.querySelector('#enqReply');
  if (!f) return;
  f.onsubmit = async ev => {
    ev.preventDefault();
    const d = formData(f);
    if (!d.message) return;
    await withBusy(f.querySelector('button'), async () => {
      try { await api('enquiry_reply', { id: e.id, message: d.message }); toast('Sent'); render.keepModals = true; enquiryView(e.id); if (route.page === 'enquiries') rerender(); } catch (ex) { toast(ex.message, 'error'); }
    });
  };
  f.querySelectorAll('[data-st]').forEach(b => b.onclick = async () => {
    try { await api('enquiry_status', { id: e.id, status: b.dataset.st }); toast('Status changed'); render.keepModals = true; enquiryView(e.id); rerender(); } catch (ex) { toast(ex.message, 'error'); }
  });
}

// ---------------------------------------------------------------------------
// Settings: reminders, SMS and email
// ---------------------------------------------------------------------------
window.afterSettings = async function () {
  const main = document.getElementById('settingsMain');
  if (!main || !isAdmin()) return;
  const card = document.createElement('div');
  card.className = 'card-x p-4 mt-3';
  card.id = 'msgCard';
  main.appendChild(card);
  let m;
  try { m = await api('messaging_get'); } catch (e) { card.innerHTML = `<div class="text-danger small">${esc(e.message)}</div>`; return; }
  const prov = (v, names) => v === 'off' ? '<span class="badge bg-secondary">Not set up</span>' : `<span class="badge ${v === 'sandbox' ? 'bg-info text-dark' : 'bg-success'}">${esc(names[v] || v)}</span>`;
  const chan = { sms: 'SMS', email: 'Email', app: 'In app', reply: 'Reply' };
  card.innerHTML = `
    <h2 class="h6 mb-2"><i class="fa-solid fa-bell me-1 text-brand"></i>Reminders &amp; messages</h2>
    <p class="small text-muted">Pet parents are reminded when a vaccine, deworming or flea &amp; tick treatment is due (a week before, and again while overdue), and the day before an appointment. Every reminder shows in the app. SMS and email go out too once their keys are added to <code>.env</code> (see the README). Demo accounts never get SMS or email.</p>
    <div class="d-flex flex-wrap gap-3 small mb-3">
      <span>SMS: ${prov(m.sms, { live: "Africa's Talking", sandbox: 'Sandbox (test)' })}</span>
      <span>Email: ${prov(m.email, { brevo: 'Brevo', mail: 'Server mail' })}</span>
      <span class="text-muted">Last run: ${m.last_run ? fmtDateTime(m.last_run) : 'never'}</span>
    </div>
    <form id="msgForm">
      <div class="d-flex flex-wrap gap-3 mb-3">
        ${[['msg_auto', 'Send reminders automatically every day'], ['msg_sms', 'Use SMS'], ['msg_email', 'Use email']].map(([k, l]) =>
          `<div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="${k}" id="m_${k}" ${m[k] === '1' ? 'checked' : ''}><label class="form-check-label" for="m_${k}">${l}</label></div>`).join('')}
      </div>
      <div class="mb-2"><label class="form-label small" for="m_tv">Vaccine &amp; treatment reminder</label><textarea class="form-control form-control-sm" id="m_tv" name="tpl_vaccine" rows="2" maxlength="320">${esc(m.tpl_vaccine)}</textarea></div>
      <div class="mb-2"><label class="form-label small" for="m_ta">Appointment reminder</label><textarea class="form-control form-control-sm" id="m_ta" name="tpl_appointment" rows="2" maxlength="320">${esc(m.tpl_appointment)}</textarea>
        <div class="form-text">You can use {pet}, {item}, {date}, {time}, {clinic} and {phone}. Keep messages short: 160 characters is one SMS.</div></div>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-brand btn-sm">Save</button>
        <button type="button" class="btn btn-light btn-sm" id="msgRun"><i class="fa-solid fa-paper-plane me-1"></i>Send due reminders now</button>
        <button type="button" class="btn btn-light btn-sm" id="msgTest"><i class="fa-solid fa-vial me-1"></i>Send a test message</button>
      </div>
    </form>
    <details class="mt-3"><summary class="small text-muted">Message log (latest ${m.log.length})</summary>
      <div class="mt-2">${tableHTML([
        { label: 'When', render: r => `<span class="small">${fmtDateTime(r.created_at)}</span>` },
        { label: 'To', render: r => `<span class="small">${esc(r.user_name || r.to_addr || '')}</span>` },
        { label: 'Channel', render: r => `<span class="badge text-bg-light">${esc(chan[r.channel] || r.channel)}</span>` },
        { label: 'Message', render: r => `<span class="small" title="${esc(r.body)}">${esc(r.subject || '')}</span>` },
        { label: 'Result', render: r => r.status === 'sent' ? '<span class="text-success small">Sent</span>' : `<span class="small ${r.status === 'failed' ? 'text-danger' : 'text-muted'}" title="${esc(r.error || '')}">${esc(r.status)}${r.error ? ': ' + esc(r.error).slice(0, 60) : ''}</span>` },
      ], m.log, 'Nothing sent yet')}</div></details>`;
  const f = card.querySelector('#msgForm');
  f.onsubmit = async e => {
    e.preventDefault();
    const d = { tpl_vaccine: f.tpl_vaccine.value, tpl_appointment: f.tpl_appointment.value };
    ['msg_auto', 'msg_sms', 'msg_email'].forEach(k => { d[k] = f[k].checked ? '1' : '0'; });
    await withBusy(f.querySelector('button'), () => run(() => api('messaging_save', d), 'Reminder settings saved'));
  };
  card.querySelector('#msgRun').onclick = async e => {
    await withBusy(e.currentTarget, async () => { const r = await run(() => api('reminders_run', {})); toast(r.message); });
    card.remove(); window.afterSettings();
  };
  card.querySelector('#msgTest').onclick = () => openForm({
    title: 'Send a test message', submitText: 'Send', size: 'modal-md',
    intro: '<p class="small text-muted mb-0">Enter a phone number for a test SMS, or an email address for a test email.</p>',
    fields: [{ name: 'to', label: 'Phone number or email', required: true, value: Session.user.phone || '' }],
    onSubmit: async d => { const r = await api('message_test', d); card.remove(); window.afterSettings(); if (r.status !== 'sent') throw new Error(r.message); return r.message; },
  });
};
