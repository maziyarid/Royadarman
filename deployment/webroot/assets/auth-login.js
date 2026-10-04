(() => {
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
  }
  const root = document.querySelector('[data-auth-login]');
  if (!root) return;
  const locale = root.dataset.locale || 'fa';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const passwordForm = document.getElementById('password-form');
  const challengeForm = document.getElementById('challenge-form');
  const verifyForm = document.getElementById('verify-form');
  const showOtp = document.getElementById('show-otp');
  const showPassword = document.getElementById('show-password');
  const mfaFields = document.querySelector('[data-mfa-fields]');
  const message = document.getElementById('message');
  let phase = passwordForm ? 'password' : 'challenge';
  let generation = 0;
  let pending = null;
  let challengeId = null;
  let requiresMfa = false;
  let webOtpController = null;

  const show = (text, error = false) => {
    if (!message) return;
    message.textContent = text || '';
    message.className = 'notice' + (error ? ' error' : '');
  };
  const current = operation => pending === operation && operation.generation === generation;
  const release = operation => {
    for (const [control, disabled] of operation.controls) control.disabled = disabled;
    if (operation.busy === null) operation.form.removeAttribute('aria-busy');
    else operation.form.setAttribute('aria-busy', operation.busy);
  };
  const begin = (form, expectedPhase) => {
    // A disabled submit button alone does not fence keyboard/programmatic events.
    if (pending || phase !== expectedPhase) return null;
    const operation = {
      form, generation, controller: new AbortController(),
      busy: form.getAttribute('aria-busy'),
      controls: [...form.querySelectorAll('button[type="submit"], input[type="submit"]')]
        .map(control => [control, control.disabled]),
    };
    pending = operation;
    form.setAttribute('aria-busy', 'true');
    for (const [control] of operation.controls) control.disabled = true;
    return operation;
  };
  const finish = operation => {
    // An obsolete request must not unlock a newer one; accepted login remains
    // fenced until navigation, even if the next page takes time to load.
    if (!current(operation) || phase === 'redirecting') return;
    release(operation);
    pending = null;
  };
  const stopWebOtp = () => {
    webOtpController?.abort();
    webOtpController = null;
  };
  const displayMethod = (method, focus = true) => {
    generation++;
    if (pending) {
      const previous = pending;
      pending = null;
      previous.controller.abort();
      release(previous);
    }
    stopWebOtp();
    challengeId = null;
    requiresMfa = false;
    if (mfaFields) mfaFields.hidden = true;
    phase = method;
    passwordForm?.classList[method === 'password' ? 'remove' : 'add']('hidden');
    challengeForm?.classList[method === 'challenge' ? 'remove' : 'add']('hidden');
    verifyForm?.classList.add('hidden');
    showOtp?.classList[method === 'password' ? 'remove' : 'add']('hidden');
    showPassword?.classList[method === 'challenge' ? 'remove' : 'add']('hidden');
    if (focus) document.getElementById(method === 'password' ? 'username' : 'mobile')?.focus();
    show('');
  };
  const choose = method => {
    if (phase !== 'redirecting') displayMethod(method);
  };
  window.addEventListener('pagehide', () => {
    // A history-restored document must not retain an accepted/pending login or
    // let a late credential result repopulate a different visit.
    displayMethod(passwordForm ? 'password' : 'challenge', false);
    for (const id of ['username', 'password', 'password-totp', 'password-recovery', 'mobile', 'code', 'totp_code', 'recovery_code']) {
      const input = document.getElementById(id);
      if (input) input.value = '';
    }
  });
  const armWebOtp = async () => {
    if (!('OTPCredential' in window) || !navigator.credentials) return;
    stopWebOtp();
    const controller = new AbortController();
    const expectedGeneration = generation;
    const expectedChallenge = challengeId;
    webOtpController = controller;
    try {
      const credential = await navigator.credentials.get({ otp: { transport: ['sms'] }, signal: controller.signal });
      // Abort may race completion; the result also belongs to one exact view
      // generation and challenge, never whichever input is now on screen.
      if (controller.signal.aborted || controller !== webOtpController || phase !== 'verify'
        || generation !== expectedGeneration || challengeId !== expectedChallenge) return;
      const codeInput = document.getElementById('code');
      if (credential?.code && codeInput && String(credential.code).length === Number(root.dataset.otpLength || 6)) {
        codeInput.value = credential.code;
        codeInput.focus();
      }
    } catch {
      // Manual OTP entry remains available after denial, cancellation or failure.
    }
  };
  const api = async (url, payload, signal) => {
    const response = await fetch(url, {
      method: 'POST', credentials: 'same-origin', signal,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Locale': locale },
      body: JSON.stringify(payload),
    });
    const data = await response.json().catch(() => null);
    if (!response.ok || response.redirected || !data || typeof data !== 'object' || Array.isArray(data)) throw new Error(data?.error?.message || data?.message || root.dataset.error);
    return data;
  };
  showOtp?.addEventListener('click', () => choose('challenge'));
  showPassword?.addEventListener('click', () => choose('password'));

  passwordForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const operation = begin(passwordForm, 'password');
    if (!operation) return;
    show('…');
    try {
      await api('/api/v1/auth/password', {
        username: document.getElementById('username').value, password: document.getElementById('password').value,
        totp_code: document.getElementById('password-totp')?.value || null,
        recovery_code: document.getElementById('password-recovery')?.value || null, locale,
      }, operation.controller.signal);
      if (!current(operation)) return;
      phase = 'redirecting';
      window.location.assign(root.dataset.panel);
    } catch (error) {
      if (current(operation)) {
        phase = 'password';
        show(error.message || root.dataset.invalid || root.dataset.error, true);
      }
    } finally {
      finish(operation);
    }
  });
  challengeForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const operation = begin(challengeForm, 'challenge');
    if (!operation) return;
    show('…');
    try {
      const result = await api('/api/v1/auth/otp/challenge', { mobile: document.getElementById('mobile').value, locale }, operation.controller.signal);
      if (!current(operation)) return;
      if (typeof result?.data?.challenge_id !== 'string' || !result.data.challenge_id.trim()) throw new Error(root.dataset.error);
      challengeId = result.data.challenge_id;
      requiresMfa = result.data.requires_mfa === true;
      phase = 'verify';
      for (const id of ['code', 'totp_code', 'recovery_code']) {
        const input = document.getElementById(id);
        if (input) input.value = '';
      }
      if (mfaFields) mfaFields.hidden = !requiresMfa;
      challengeForm.classList.add('hidden');
      verifyForm.classList.remove('hidden');
      showPassword?.classList.remove('hidden');
      show(requiresMfa ? root.dataset.mfaHint : root.dataset.sent);
      document.getElementById('code')?.focus();
      void armWebOtp();
    } catch (error) {
      if (current(operation)) show(error.message || root.dataset.error, true);
    } finally {
      finish(operation);
    }
  });
  verifyForm?.addEventListener('submit', async event => {
    event.preventDefault();
    if (!challengeId) return;
    const operation = begin(verifyForm, 'verify');
    if (!operation) return;
    stopWebOtp();
    show('…');
    try {
      await api('/api/v1/auth/otp/verify', {
        challenge_id: challengeId, code: document.getElementById('code').value,
        totp_code: requiresMfa ? (document.getElementById('totp_code')?.value || null) : null,
        recovery_code: requiresMfa ? (document.getElementById('recovery_code')?.value || null) : null,
      }, operation.controller.signal);
      if (!current(operation)) return;
      phase = 'redirecting';
      window.location.assign(root.dataset.panel);
    } catch (error) {
      if (current(operation)) {
        phase = 'verify';
        show(error.message || root.dataset.error, true);
      }
    } finally {
      finish(operation);
    }
  });
})();
