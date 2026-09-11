<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ __('auth_ui.title') }}</title>
<style>:root{font-family:system-ui,sans-serif;color:#17231f;background:#f4f7f5}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:18px}.card{width:min(100%,500px);background:#fff;border:1px solid #e0e7e3;border-radius:22px;padding:28px;box-shadow:0 18px 60px rgba(20,50,40,.08)}h1{margin:0 0 10px}p{color:#60716a;line-height:1.7}.field{display:flex;flex-direction:column;gap:7px;margin:16px 0}label{font-weight:650}input{font:inherit;padding:11px 12px;border:1px solid #c8d5cf;border-radius:10px}.btn{width:100%;font:inherit;padding:12px 14px;border:0;border-radius:11px;background:#134437;color:#fff;cursor:pointer;font-weight:700}.secondary{display:block;text-align:center;color:#36554b;margin-top:18px;text-decoration:none}.hidden{display:none}.notice{margin:14px 0;padding:11px 12px;border-radius:9px;background:#eef5f1;color:#315247}.error{background:#fff0f0;color:#8c2f2f}.staff{border-top:1px solid #edf1ef;margin-top:18px;padding-top:4px}</style>
</head>
<body><main class="card">
<h1>{{ __('auth_ui.title') }}</h1><p>{{ __('auth_ui.subtitle') }}</p>
<div id="message" class="notice hidden" role="status"></div>
<form id="challenge-form">
<div class="field"><label for="mobile">{{ __('auth_ui.mobile') }}</label><input id="mobile" name="mobile" dir="ltr" inputmode="tel" autocomplete="tel" placeholder="0912…" required maxlength="32"></div>
<button class="btn" type="submit">{{ __('auth_ui.send') }}</button>
</form>
<form id="verify-form" class="hidden">
<div class="field"><label for="code">{{ __('auth_ui.code') }}</label><input id="code" name="code" dir="ltr" inputmode="numeric" autocomplete="one-time-code" required maxlength="6" minlength="6"></div>
<div class="staff"><div class="field"><label for="totp_code">{{ __('auth_ui.totp') }}</label><input id="totp_code" name="totp_code" dir="ltr" inputmode="numeric" autocomplete="one-time-code"></div><div class="field"><label for="recovery_code">{{ __('auth_ui.recovery') }}</label><input id="recovery_code" name="recovery_code" dir="ltr" autocomplete="off"></div></div>
<button class="btn" type="submit">{{ __('auth_ui.verify') }}</button>
</form>
<a class="secondary" href="{{ route('public.home',['locale'=>$locale]) }}">{{ __('auth_ui.back') }}</a>
</main>
<script>
(() => {
 const locale = @json($locale), csrf = document.querySelector('meta[name="csrf-token"]').content;
 const challengeForm = document.getElementById('challenge-form'), verifyForm = document.getElementById('verify-form'), message = document.getElementById('message');
 let challengeId = null;
 const show = (text, error=false) => { message.textContent=text; message.className='notice'+(error?' error':''); };
 const api = async (url, payload) => {
   const response = await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Locale':locale},body:JSON.stringify(payload)});
   const data = await response.json().catch(()=>({}));
   if(!response.ok) throw new Error(data?.error?.message || data?.message || @json(__('auth_ui.error')));
   return data;
 };
 challengeForm.addEventListener('submit', async event => {
   event.preventDefault(); show('…');
   try {
     const result = await api('/api/v1/auth/otp/challenge',{mobile:document.getElementById('mobile').value,locale});
     challengeId = result.data.challenge_id; challengeForm.classList.add('hidden'); verifyForm.classList.remove('hidden'); show(@json(__('auth_ui.sent'))); document.getElementById('code').focus();
   } catch(error){ show(error.message,true); }
 });
 verifyForm.addEventListener('submit', async event => {
   event.preventDefault(); show('…');
   try {
     await api('/api/v1/auth/otp/verify',{challenge_id:challengeId,code:document.getElementById('code').value,totp_code:document.getElementById('totp_code').value || null,recovery_code:document.getElementById('recovery_code').value || null});
     window.location.assign('/'+locale+'/panel');
   } catch(error){ show(error.message,true); }
 });
})();
</script>
</body></html>
