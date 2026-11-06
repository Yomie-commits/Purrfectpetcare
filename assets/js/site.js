/* Public website: prices, clinic details, login/registration */
'use strict';

(function () {
  const authModalEl = document.getElementById('authModal');
  const authModal = new bootstrap.Modal(authModalEl);
  let services = [];
  let afterLogin = 'app.html';

  document.getElementById('year').textContent = new Date().getFullYear();

  function fillClinic(c) {
    document.querySelectorAll('[data-clinic]').forEach(el => {
      const v = c[el.dataset.clinic];
      if (v) el.textContent = v;
    });
    if (c.name) document.title = `${c.name} | Veterinary Clinic`;
    const tel = document.querySelector('[data-clinic-tel]');
    if (c.phone) {
      tel.href = 'tel:' + c.phone.replace(/\s/g, '');
      tel.innerHTML = `<i class="fa-solid fa-phone me-2"></i>Call us: ${esc(c.phone)}`;
    } else {
      tel.classList.add('d-none');
    }
  }

  function renderPrices() {
    const box = document.getElementById('priceList');
    if (!services.length) {
      box.innerHTML = '<p class="text-center text-muted">Prices will be published soon.</p>';
      return;
    }
    box.innerHTML = services.map(s => `
      <div class="col-sm-6 col-lg-3">
        <div class="card price-card text-center p-3">
          <div class="card-body d-flex flex-column">
            <h3 class="h6 text-uppercase text-muted">${esc(s.name)}</h3>
            <div class="price my-2">${money(s.price)}</div>
            <p class="small text-muted flex-grow-1">${esc(s.description || '')}</p>
            <div class="small text-muted mb-3"><i class="fa-regular fa-clock me-1"></i>${esc(s.duration_minutes)} min</div>
            <button class="btn btn-brand btn-sm" data-book-id="${s.id}">Book</button>
          </div>
        </div>
      </div>`).join('');
  }

  function renderNav() {
    const box = document.getElementById('navAuth');
    if (Session.user) {
      box.innerHTML = `<a class="btn btn-brand btn-sm px-3" href="app.html"><i class="fa-solid fa-gauge me-1"></i>My dashboard</a>`;
    } else {
      box.innerHTML = `<button class="btn btn-brand btn-sm px-3" data-auth="login">Log in</button>`;
    }
  }

  function openAuth(tab = 'login', note = '') {
    const target = tab === 'register' ? '#tabRegister' : '#tabLogin';
    bootstrap.Tab.getOrCreateInstance(authModalEl.querySelector(`[data-bs-target="${target}"]`)).show();
    const n = document.getElementById('authNote');
    n.textContent = note;
    n.classList.toggle('d-none', !note);
    authModalEl.querySelectorAll('[data-error]').forEach(e => { e.textContent = ''; });
    authModal.show();
  }

  function bookTarget(serviceName, serviceId) {
    let id = serviceId;
    if (!id && serviceName) {
      const s = services.find(x => x.name.toLowerCase() === serviceName.toLowerCase());
      id = s ? s.id : '';
    }
    return 'app.html#appointments?book=1' + (id ? `&service=${id}` : '');
  }

  document.addEventListener('click', e => {
    const auth = e.target.closest('[data-auth]');
    const book = e.target.closest('[data-book],[data-book-id]');
    const needs = e.target.closest('[data-needs-login]');
    if (auth) {
      e.preventDefault();
      afterLogin = 'app.html';
      openAuth(auth.dataset.auth);
    } else if (book) {
      e.preventDefault();
      const target = bookTarget(book.dataset.book, book.dataset.bookId);
      if (Session.user) {
        window.location.href = target;
      } else {
        afterLogin = target;
        openAuth('login', 'Log in or create a free account to book your appointment.');
      }
    } else if (needs && !Session.user) {
      e.preventDefault();
      afterLogin = needs.getAttribute('href');
      openAuth('login');
    }
    // Close the mobile menu after picking a link
    if (e.target.closest('#navLinks .nav-link')) {
      const c = bootstrap.Collapse.getInstance(document.getElementById('navLinks'));
      if (c) c.hide();
    }
  });

  function setError(form, msg, ok = false) {
    const el = form.querySelector('[data-error]');
    el.textContent = msg;
    el.className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger');
  }

  document.getElementById('loginForm').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target, d = formData(f);
    if (!d.email || !d.password) return setError(f, 'Enter your email and password.');
    await withBusy(f.querySelector('button[type=submit]'), async () => {
      try {
        await api('login', { email: d.email, password: d.password });
        window.location.href = afterLogin;
      } catch (err) { setError(f, err.message); }
    });
  });

  document.getElementById('registerForm').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target, d = formData(f);
    if (!d.name || !d.email || !d.password) return setError(f, 'Please fill in your name, email and password.');
    if (d.password !== d.password2) return setError(f, 'Passwords do not match.');
    await withBusy(f.querySelector('button[type=submit]'), async () => {
      try {
        const r = await api('register', { role: d.role, name: d.name, email: d.email, phone: d.phone, password: d.password, specialization: d.specialization });
        if (r.pending) {
          f.reset();
          document.getElementById('regRole').dispatchEvent(new Event('change'));
          setError(f, r.message, true);
          return;
        }
        window.location.href = afterLogin === 'app.html' ? 'app.html#pets?add=1' : afterLogin;
      } catch (err) { setError(f, err.message); }
    });
  });

  document.getElementById('regRole').addEventListener('change', e => {
    const staff = e.target.value !== 'owner';
    document.getElementById('regRoleNote').classList.toggle('d-none', !staff);
    document.getElementById('regSpecWrap').classList.toggle('d-none', e.target.value !== 'vet');
  });

  document.getElementById('forgotForm').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target, d = formData(f);
    if (!d.email) return setError(f, 'Enter your email.');
    await withBusy(f.querySelector('button[type=submit]'), async () => {
      try {
        const r = await api('forgot_password', { email: d.email });
        setError(f, r.message, true);
        if (r.debug_link) console.info('Reset link (debug mode):', r.debug_link);
      } catch (err) { setError(f, err.message); }
    });
  });

  document.getElementById('contactForm').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target, d = formData(f);
    if (!d.name || !d.subject || !d.message) return setError(f, 'Please fill in your name, a subject and your message.');
    if (!d.phone && !d.email) return setError(f, 'Please give a phone number or email so we can reply.');
    await withBusy(f.querySelector('button[type=submit]'), async () => {
      try {
        const r = await api('enquiry_create', d);
        f.reset();
        setError(f, r.message || 'Thank you! Your message has been sent.', true);
      } catch (err) { setError(f, err.message); }
    });
  });

  (async function init() {
    try {
      await loadSession();
      renderNav();
      const info = await api('public_info');
      services = info.services || [];
      fillClinic(info.clinic || {});
      renderPrices();
      const params = new URLSearchParams(location.search);
      if (params.has('login') && !Session.user) openAuth('login', 'Please log in to continue.');
    } catch (err) {
      document.getElementById('priceList').innerHTML = `<div class="col-12"><div class="alert alert-warning text-center">${esc(err.message)}</div></div>`;
    }
  })();
})();
