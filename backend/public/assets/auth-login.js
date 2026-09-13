(() => {
  const root = document.querySelector('[data-auth-login]'); if (!root) return;
  const locale = root.dataset.locale || 'fa';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const challengeForm = document.getElementById('challenge-form');
  const verifyForm = document.getElementById('verify-form');
  const message = document.getElementById('message');
  let challengeId = null;
  const show = (text, error=false) => { message.textContent=text; message.className='notice'+(error?' error':''); };
  const api = async (url, payload) => {
    const response = await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Locale':locale},body:JSON.stringify(payload)});
    const data = await response.json().catch(()=>({}));
    if(!response.ok) throw new Error(data?.error?.message || data?.message || root.dataset.error);
    return data;
  };
  challengeForm?.addEventListener('submit', async event => {
    event.preventDefault(); show('…');
    try { const result=await api('/api/v1/auth/otp/challenge',{mobile:document.getElementById('mobile').value,locale}); challengeId=result.data.challenge_id; challengeForm.classList.add('hidden'); verifyForm.classList.remove('hidden'); show(root.dataset.sent); document.getElementById('code')?.focus(); }
    catch(error){ show(error.message,true); }
  });
  verifyForm?.addEventListener('submit', async event => {
    event.preventDefault(); show('…');
    try { await api('/api/v1/auth/otp/verify',{challenge_id:challengeId,code:document.getElementById('code').value,totp_code:document.getElementById('totp_code').value||null,recovery_code:document.getElementById('recovery_code').value||null}); window.location.assign(root.dataset.panel); }
    catch(error){ show(error.message,true); }
  });
})();
