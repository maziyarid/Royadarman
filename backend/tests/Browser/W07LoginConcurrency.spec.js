/**
 * Actual auth-login.js event handlers with synthetic DOM/fetch/WebOTP adapters.
 * Run: node --test backend/tests/Browser/W07LoginConcurrency.spec.js
 * No browser, backend session, real credential or message delivery is exercised.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../../deployment/webroot/assets/auth-login.js', import.meta.url), 'utf8');
const settle = async () => { await new Promise(setImmediate); await new Promise(setImmediate); };

function harness({locale='en', webOtp=false, protocol='https:'}={}) {
  const elements = new Map(); const windowHandlers = new Map();
  const document = {activeElement:null};
  class Element {
    constructor(id, classes='') {
      Object.assign(this, {id, className:classes, value:'', textContent:'', hidden:false, disabled:false, dataset:{}});
      this.handlers = new Map(); this.attributes = new Map(); this.submitControls = [];
      this.classList = {
        add:(...names)=>{this.className=[...new Set([...this.className.split(/\s+/).filter(Boolean),...names])].join(' ');},
        remove:(...names)=>{this.className=this.className.split(/\s+/).filter(n=>!names.includes(n)).join(' ');},
        contains:name=>this.className.split(/\s+/).includes(name),
      };
    }
    addEventListener(name, handler) { this.handlers.set(name,[...(this.handlers.get(name)||[]),handler]); }
    setAttribute(name, value) { this.attributes.set(name,String(value)); }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    removeAttribute(name) { this.attributes.delete(name); }
    querySelectorAll() { return this.submitControls; }
    focus() { document.activeElement=this; }
    async dispatch(name) {
      // Submit is deliberately dispatched even on a disabled button: the
      // handler must guard duplicate keyboard/programmatic submissions itself.
      if (name==='click' && this.disabled) return;
      const event={target:this,currentTarget:this,preventDefault(){}};
      await Promise.all((this.handlers.get(name)||[]).map(handler=>handler(event)));
    }
  }
  const ids=['password-form','challenge-form','verify-form','show-otp','show-password','username','password','password-totp','password-recovery','mobile','code','totp_code','recovery_code','message'];
  const hidden=new Set(['challenge-form','verify-form','show-password','message']);
  for(const id of ids) elements.set(id,new Element(id,hidden.has(id)?'hidden':''));
  for(const id of ['password-form','challenge-form','verify-form']) elements.get(id).submitControls=[new Element(id+'-submit')];
  const root=new Element('root');
  root.dataset={locale,otpLength:'6',error:'Synthetic request failed',invalid:'Synthetic rejected',sent:'Synthetic challenge sent',mfaHint:'Synthetic MFA needed',panel:`/${locale}/panel`};
  const mfa=new Element('mfa'); mfa.hidden=true;
  document.getElementById=id=>elements.get(id)??null;
  document.querySelector=selector=>selector==='[data-auth-login]'?root:selector==='[data-mfa-fields]'?mfa:selector==='meta[name="csrf-token"]'?{content:'SYNTHETIC_CSRF'}:null;
  const pending=[]; const credentials=[]; const redirects=[]; const registrations=[];
  const fetch=(url,options={})=>new Promise((resolve,reject)=>{
    // Deliberately ignore abort so stale-result checks are independently tested.
    pending.push({url,options,respond:resolve,resolve:(data,status=200)=>resolve(new Response(JSON.stringify(data),{status,headers:{'Content-Type':'application/json'}})),
      fail:(status=422)=>resolve(new Response(JSON.stringify({error:{message:'Synthetic rejected'}}),{status,headers:{'Content-Type':'application/json'}})),reject});
  });
  const navigator={serviceWorker:{register:(...args)=>{registrations.push(args);return Promise.resolve();}}};
  if(webOtp) navigator.credentials={get:options=>new Promise((resolve,reject)=>credentials.push({options,resolve,reject}))};
  const window={location:{assign:url=>redirects.push(url)},addEventListener:(name,handler)=>windowHandlers.set(name,[...(windowHandlers.get(name)||[]),handler])};
  if(webOtp) window.OTPCredential=class {};
  const forbidden=new Proxy({}, {get(){throw new Error('Login must not persist secrets in browser storage');}});
  vm.runInNewContext(source,{window,document,navigator,location:{protocol},fetch,AbortController,DOMException,console,
    localStorage:forbidden,sessionStorage:forbidden,indexedDB:forbidden}, {filename:'actual-auth-login.js',timeout:2000});
  elements.get('username').value='SYNTHETIC_USER';elements.get('password').value='SYNTHETIC_NOT_A_CREDENTIAL';
  elements.get('mobile').value='SYNTHETIC_MOBILE';elements.get('code').value='000000';
  return {elements,document,mfa,pending,credentials,redirects,registrations,
    control:id=>elements.get(id).submitControls[0],
    visible:id=>!elements.get(id).hidden&&!elements.get(id).classList.contains('hidden'),
    dispatch:(id,event)=>elements.get(id).dispatch(event),
    windowEvent:async(name)=>{await Promise.all((windowHandlers.get(name)||[]).map(h=>h({})));},
  };
}
async function challenge(h,{id='SYNTHETIC_CHALLENGE',mfa=false}={}) {
  await h.dispatch('show-otp','click');
  const operation=h.dispatch('challenge-form','submit');
  h.pending.at(-1).resolve({data:{challenge_id:id,requires_mfa:mfa}},202);await operation;await settle();
}

for(const locale of ['fa','ar','en']) test(`password login retains ${locale} request, CSRF, MFA fields and destination`,async()=>{
  const h=harness({locale});h.elements.get('password-totp').value='123456';h.elements.get('password-recovery').value='SYNTHETIC_RECOVERY';
  const operation=h.dispatch('password-form','submit');const request=h.pending[0];
  assert.equal(request.url,'/api/v1/auth/password');assert.equal(request.options.method,'POST');assert.equal(request.options.credentials,'same-origin');
  assert.equal(request.options.headers['X-CSRF-TOKEN'],'SYNTHETIC_CSRF');assert.equal(request.options.headers['X-Locale'],locale);
  assert.deepEqual(JSON.parse(request.options.body),{username:'SYNTHETIC_USER',password:'SYNTHETIC_NOT_A_CREDENTIAL',totp_code:'123456',recovery_code:'SYNTHETIC_RECOVERY',locale});
  request.resolve({data:{role:'patient',locale}});await operation;assert.deepEqual(h.redirects,[`/${locale}/panel`]);
});
for(const form of ['password-form','challenge-form','verify-form']) test(`${form} ignores overlapping submit events`,async()=>{
  const h=harness();if(form==='challenge-form')await h.dispatch('show-otp','click');if(form==='verify-form')await challenge(h);
  const count=h.pending.length;const first=h.dispatch(form,'submit');const second=h.dispatch(form,'submit');
  const requested=h.pending.length-count;
  for(const p of h.pending.slice(count))p.fail();await Promise.all([first,second]);
  assert.equal(requested,1,'exactly one request may be in flight for this form');
});
test('pending password form has disabled submit and aria-busy, error restores it',async()=>{
  const h=harness();const op=h.dispatch('password-form','submit');
  assert.equal(h.control('password-form').disabled,true);assert.equal(h.elements.get('password-form').getAttribute('aria-busy'),'true');
  h.pending[0].fail();await op;assert.equal(h.control('password-form').disabled,false);assert.notEqual(h.elements.get('password-form').getAttribute('aria-busy'),'true');
});
test('an explicit retry after a failed password request is possible without losing input',async()=>{
  const h=harness();let op=h.dispatch('password-form','submit');h.pending[0].fail(419);await op;
  assert.equal(h.redirects.length,0);assert.equal(h.elements.get('password').value,'SYNTHETIC_NOT_A_CREDENTIAL');
  assert.equal(h.elements.get('message').classList.contains('error'),true);
  op=h.dispatch('password-form','submit');assert.equal(h.pending.length,2);h.pending[1].resolve({data:{}});await op;assert.equal(h.redirects.length,1);
});
test('network failure is an error, not a login success or automatic retry',async()=>{
  const h=harness();const op=h.dispatch('password-form','submit');h.pending[0].reject(new TypeError('Synthetic offline'));await op;
  assert.equal(h.pending.length,1);assert.equal(h.redirects.length,0);assert.equal(h.elements.get('message').classList.contains('error'),true);
});
test('late OTP challenge success cannot revive verification after switching to password',async()=>{
  const h=harness();await h.dispatch('show-otp','click');const op=h.dispatch('challenge-form','submit');await h.dispatch('show-password','click');
  h.pending[0].resolve({data:{challenge_id:'SYNTHETIC_OLD',requires_mfa:true}});await op;
  assert.equal(h.visible('password-form'),true);assert.equal(h.visible('verify-form'),false);assert.equal(h.document.activeElement.id,'username');
  assert.equal(h.pending[0].options.signal?.aborted,true);
});
test('late challenge failure cannot overwrite the selected password view',async()=>{
  const h=harness();await h.dispatch('show-otp','click');const op=h.dispatch('challenge-form','submit');await h.dispatch('show-password','click');
  h.pending[0].fail();await op;assert.equal(h.elements.get('message').textContent,'');assert.equal(h.visible('verify-form'),false);
});
test('late password success cannot navigate after choosing OTP',async()=>{
  const h=harness();const op=h.dispatch('password-form','submit');await h.dispatch('show-otp','click');h.pending[0].resolve({data:{}});await op;
  assert.equal(h.redirects.length,0);assert.equal(h.visible('challenge-form'),true);assert.equal(h.document.activeElement.id,'mobile');
});
test('old request cleanup cannot unlock a newer pending request',async()=>{
  const h=harness();const old=h.dispatch('password-form','submit');await h.dispatch('show-otp','click');
  const current=h.dispatch('challenge-form','submit');h.pending[0].fail();await old;
  assert.equal(h.control('challenge-form').disabled,true);assert.equal(h.elements.get('challenge-form').getAttribute('aria-busy'),'true');
  assert.equal(h.elements.get('message').textContent,'…');h.pending[1].fail();await current;assert.equal(h.control('challenge-form').disabled,false);
});
test('normal OTP challenge and required MFA retain the bound identifier and credentials',async()=>{
  const h=harness();await challenge(h,{mfa:true});assert.equal(h.visible('verify-form'),true);assert.equal(h.mfa.hidden,false);
  h.elements.get('code').value='123456';h.elements.get('totp_code').value='654321';h.elements.get('recovery_code').value='SYNTHETIC_RECOVERY';
  const op=h.dispatch('verify-form','submit');const request=h.pending[1];
  assert.deepEqual(JSON.parse(request.options.body),{challenge_id:'SYNTHETIC_CHALLENGE',code:'123456',totp_code:'654321',recovery_code:'SYNTHETIC_RECOVERY'});
  request.resolve({data:{}});await op;assert.deepEqual(h.redirects,['/en/panel']);
});
test('optional MFA values are omitted when the challenge does not request them',async()=>{
  const h=harness();await challenge(h);h.elements.get('totp_code').value='123456';h.elements.get('recovery_code').value='SYNTHETIC_UNUSED';
  const op=h.dispatch('verify-form','submit');const payload=JSON.parse(h.pending[1].options.body);
  assert.equal(payload.totp_code,null);assert.equal(payload.recovery_code,null);h.pending[1].fail();await op;
});
test('failed verification keeps the current challenge available for explicit retry',async()=>{
  const h=harness();await challenge(h);let op=h.dispatch('verify-form','submit');h.pending[1].fail();await op;
  assert.equal(h.redirects.length,0);op=h.dispatch('verify-form','submit');
  assert.equal(JSON.parse(h.pending[2].options.body).challenge_id,'SYNTHETIC_CHALLENGE');h.pending[2].resolve({data:{}});await op;assert.equal(h.redirects.length,1);
});
test('late verify success is discarded when the user changes login method',async()=>{
  const h=harness();await challenge(h);const op=h.dispatch('verify-form','submit');await h.dispatch('show-password','click');
  h.pending[1].resolve({data:{}});await op;assert.equal(h.redirects.length,0);assert.equal(h.visible('password-form'),true);
});
test('a hidden obsolete verification form cannot submit an old challenge',async()=>{
  const h=harness();await challenge(h);await h.dispatch('show-password','click');
  const count=h.pending.length;const op=h.dispatch('verify-form','submit');
  for(const p of h.pending.slice(count))p.fail();await op;assert.equal(h.pending.length,count);
});
test('late WebOTP result cannot fill a hidden code or steal password focus',async()=>{
  const h=harness({webOtp:true});await challenge(h);assert.equal(h.credentials.length,1);
  await h.dispatch('show-password','click');h.elements.get('code').value='';h.credentials[0].resolve({code:'123456'});await settle();
  assert.equal(h.elements.get('code').value,'');assert.equal(h.document.activeElement.id,'username');assert.equal(h.credentials[0].options.signal.aborted,true);
});
test('old WebOTP credential cannot populate a newer challenge',async()=>{
  const h=harness({webOtp:true});await challenge(h,{id:'SYNTHETIC_OLD'});await h.dispatch('show-password','click');await challenge(h,{id:'SYNTHETIC_NEW'});
  h.elements.get('code').value='';h.credentials[0].resolve({code:'111111'});await settle();assert.equal(h.elements.get('code').value,'');
  h.credentials[1].resolve({code:'222222'});await settle();assert.equal(h.elements.get('code').value,'222222');
});
test('malformed successful challenge does not expose an unusable verification step',async()=>{
  const h=harness();await h.dispatch('show-otp','click');const op=h.dispatch('challenge-form','submit');h.pending[0].resolve({data:{}});await op;
  assert.equal(h.visible('verify-form'),false);assert.equal(h.visible('challenge-form'),true);assert.equal(h.elements.get('message').classList.contains('error'),true);
});
test('accepted password login cannot be submitted again while navigation is pending',async()=>{
  const h=harness();let op=h.dispatch('password-form','submit');h.pending[0].resolve({data:{}});await op;
  op=h.dispatch('password-form','submit');for(const p of h.pending.slice(1))p.fail();await op;
  assert.equal(h.pending.length,1);assert.equal(h.redirects.length,1);
});
test('PWA registration remains same-origin and HTTPS-only',async()=>{
  const secure=harness();await secure.windowEvent('load');assert.deepEqual(secure.registrations.map(r=>[r[0],r[1].scope]),[['/sw.js','/']]);
  const plain=harness({protocol:'http:'});await plain.windowEvent('load');assert.equal(plain.registrations.length,0);
});


test('HTML error page returned as HTTP200 never counts as a successful login',async()=>{
  const h=harness();const op=h.dispatch('password-form','submit');
  h.pending[0].respond(new Response('<!doctype html><title>Synthetic error</title>',{status:200,headers:{'Content-Type':'text/html'}}));
  await op;assert.equal(h.redirects.length,0);assert.equal(h.elements.get('message').classList.contains('error'),true);assert.equal(h.control('password-form').disabled,false);
});
test('followed HTTP redirect never counts as a successful login response',async()=>{
  const h=harness();const op=h.dispatch('password-form','submit');
  const response=new Response(JSON.stringify({data:{role:'patient',locale:'en'}}),{status:200,headers:{'Content-Type':'application/json'}});
  Object.defineProperty(response,'redirected',{value:true});h.pending[0].respond(response);await op;
  assert.equal(h.redirects.length,0);assert.equal(h.elements.get('message').classList.contains('error'),true);
});
test('new challenge clears obsolete OTP and challenge-specific MFA input',async()=>{
  const h=harness();h.elements.get('code').value='111111';h.elements.get('totp_code').value='222222';h.elements.get('recovery_code').value='SYNTHETIC_OLD';
  await challenge(h,{id:'SYNTHETIC_NEW',mfa:true});
  for(const name of ['code','totp_code','recovery_code'])assert.equal(h.elements.get(name).value,'');
  assert.equal(h.elements.get('password').value,'SYNTHETIC_NOT_A_CREDENTIAL');
});

test('leaving the page invalidates a pending login response and clears transient secrets',async()=>{
  const h=harness();const op=h.dispatch('password-form','submit');await h.windowEvent('pagehide');
  h.pending[0].resolve({data:{role:'patient',locale:'en'}});await op;
  assert.equal(h.redirects.length,0);assert.equal(h.pending[0].options.signal?.aborted,true);
  assert.equal(h.elements.get('password').value,'');assert.equal(h.control('password-form').disabled,false);
});
test('a restored login document is not stuck in the previous redirecting state',async()=>{
  const h=harness();let op=h.dispatch('password-form','submit');h.pending[0].resolve({data:{role:'patient',locale:'en'}});await op;
  await h.windowEvent('pagehide');await h.windowEvent('pageshow');
  assert.equal(h.visible('password-form'),true);assert.equal(h.control('password-form').disabled,false);
  h.elements.get('username').value='SYNTHETIC_RETURN';h.elements.get('password').value='SYNTHETIC_RETRY';
  op=h.dispatch('password-form','submit');assert.equal(h.pending.length,2);h.pending[1].fail();await op;
});
test('leaving the page discards pending WebOTP without restoring hidden fields',async()=>{
  const h=harness({webOtp:true});await challenge(h);await h.windowEvent('pagehide');
  h.credentials[0].resolve({code:'111111'});await settle();assert.equal(h.elements.get('code').value,'');
  assert.equal(h.visible('verify-form'),false);assert.equal(h.credentials[0].options.signal.aborted,true);
});
