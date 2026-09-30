(() => {
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    window.addEventListener('load', () => { navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}); });
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
  let challengeId = null;
  let requiresMfa = false;
  let webOtpController = null;
  const armWebOtp = async () => {
    if (!('OTPCredential' in window) || !navigator.credentials) return;
    webOtpController?.abort(); webOtpController = new AbortController();
    try {
      const credential = await navigator.credentials.get({ otp: { transport: ['sms'] }, signal: webOtpController.signal });
      const codeInput = document.getElementById('code');
      if (credential?.code && codeInput && String(credential.code).length === Number(root.dataset.otpLength || 6)) { codeInput.value = credential.code; codeInput.focus(); }
    } catch (error) { if (error?.name !== 'AbortError') { /* Manual OTP entry remains available. */ } }
  };
  const show = (text, error = false) => {
    if (!message) return; message.textContent = text || ''; message.className = 'notice' + (error ? ' error' : '');
  };
  const api = async (url, payload) => {
    const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Locale': locale }, body: JSON.stringify(payload) });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data?.error?.message || data?.message || root.dataset.error);
    return data;
  };
  showOtp?.addEventListener('click', () => {
    passwordForm?.classList.add('hidden'); showOtp.classList.add('hidden'); challengeForm?.classList.remove('hidden'); showPassword?.classList.remove('hidden'); document.getElementById('mobile')?.focus(); show('');
  });
  showPassword?.addEventListener('click', () => {
    webOtpController?.abort(); challengeForm?.classList.add('hidden'); verifyForm?.classList.add('hidden'); showPassword.classList.add('hidden'); passwordForm?.classList.remove('hidden'); showOtp?.classList.remove('hidden'); document.getElementById('username')?.focus(); show('');
  });
  passwordForm?.addEventListener('submit', async (event) => {
    event.preventDefault(); show('…');
    try {
      await api('/api/v1/auth/password', { username: document.getElementById('username').value, password: document.getElementById('password').value, totp_code: document.getElementById('password-totp')?.value || null, recovery_code: document.getElementById('password-recovery')?.value || null, locale });
      window.location.assign(root.dataset.panel);
    } catch (error) { show(error.message || root.dataset.invalid || root.dataset.error, true); }
  });
  challengeForm?.addEventListener('submit', async (event) => {
    event.preventDefault(); show('…');
    try {
      const result = await api('/api/v1/auth/otp/challenge', { mobile: document.getElementById('mobile').value, locale });
      challengeId = result.data.challenge_id; requiresMfa = result.data.requires_mfa === true;
      if (mfaFields) mfaFields.hidden = !requiresMfa;
      challengeForm.classList.add('hidden'); verifyForm.classList.remove('hidden'); showPassword?.classList.remove('hidden');
      show(requiresMfa ? root.dataset.mfaHint : root.dataset.sent); document.getElementById('code')?.focus(); void armWebOtp();
    } catch (error) { show(error.message, true); }
  });
  verifyForm?.addEventListener('submit', async (event) => {
    event.preventDefault(); webOtpController?.abort(); show('…');
    try {
      await api('/api/v1/auth/otp/verify', { challenge_id: challengeId, code: document.getElementById('code').value, totp_code: requiresMfa ? (document.getElementById('totp_code')?.value || null) : null, recovery_code: requiresMfa ? (document.getElementById('recovery_code')?.value || null) : null });
      window.location.assign(root.dataset.panel);
    } catch (error) { show(error.message, true); }
  });
})();
