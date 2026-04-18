/* ===================================================================
   SafeCity — auth.js
   Obsługa formularzy logowania i rejestracji (Fetch API)
   =================================================================== */

const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/* --- Helpers ----------------------------------------------------- */

function clearErrors() {
  document.querySelectorAll('.field-error').forEach(el => (el.textContent = ''));
  document.querySelectorAll('input').forEach(el => el.classList.remove('is-invalid'));
  document.getElementById('general-error')?.classList.add('hidden');
}

function showErrors(errors) {
  Object.entries(errors).forEach(([field, msg]) => {
    if (field === 'general') {
      const el = document.getElementById('general-error');
      if (el) { el.textContent = msg; el.classList.remove('hidden'); }
      return;
    }
    const errEl = document.getElementById(`${field}-error`);
    const input = document.getElementById(field);
    if (errEl) errEl.textContent = msg;
    if (input) input.classList.add('is-invalid');
  });
}

function setLoading(btn, loading) {
  btn.disabled = loading;
  btn.textContent = loading ? 'Proszę czekać…' : btn.dataset.label;
}

async function postJson(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrf,
    },
    body: JSON.stringify(data),
  });
  return { status: res.status, data: await res.json() };
}

/* --- Login form -------------------------------------------------- */

const loginForm = document.getElementById('login-form');
if (loginForm) {
  const btn = document.getElementById('submit-btn');
  btn.dataset.label = btn.textContent;

  // Pokaż komunikat sukcesu przekazany z rejestracji
  const params = new URLSearchParams(window.location.search);
  if (params.get('registered')) {
    const el = document.getElementById('success-msg');
    if (el) {
      el.textContent = 'Konto zostało utworzone. Możesz się zalogować.';
      el.classList.remove('hidden');
    }
  }

  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors();
    setLoading(btn, true);

    const { status, data } = await postJson('/api/auth/login', {
      email:    document.getElementById('email').value.trim(),
      password: document.getElementById('password').value,
    });

    setLoading(btn, false);

    if (status === 200 && data.redirect) {
      window.location.href = data.redirect;
    } else {
      showErrors(data.errors ?? { general: 'Wystąpił nieoczekiwany błąd.' });
    }
  });
}

/* --- Register form ----------------------------------------------- */

const registerForm = document.getElementById('register-form');
if (registerForm) {
  const btn = document.getElementById('submit-btn');
  btn.dataset.label = btn.textContent;

  registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors();
    setLoading(btn, true);

    const { status, data } = await postJson('/api/auth/register', {
      full_name:        document.getElementById('full_name').value.trim(),
      email:            document.getElementById('email').value.trim(),
      password:         document.getElementById('password').value,
      password_confirm: document.getElementById('password_confirm').value,
    });

    setLoading(btn, false);

    if (status === 200 && data.redirect) {
      window.location.href = data.redirect + '?registered=1';
    } else {
      showErrors(data.errors ?? { general: 'Wystąpił nieoczekiwany błąd.' });
    }
  });
}
