/**
 * W07 PWA handler specification, runnable without a browser or production data.
 * Run: node --test backend/tests/Browser/W07PwaPrivacy.spec.js
 * The directory is the W01-granted path, NOT a claim of browser execution.
 * Web API adapters model deterministic handler branches; real browser CSP,
 * integrity enforcement, install/update/bfcache and device proof remain separate.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {createHash} from 'node:crypto';
import vm from 'node:vm';

const BASE = new URL('../../resources/pwa/', import.meta.url);
const source = readFileSync(new URL('sw.js', BASE), 'utf8');
const offline = readFileSync(new URL('offline.html', BASE), 'utf8');
const ORIGIN = 'https://w07.invalid';
const urlOf = r => new URL(typeof r === 'string' ? r : r.url, ORIGIN).href;
const settle = async () => { await new Promise(setImmediate); await new Promise(setImmediate); };

function harness() {
  const handlers = new Map(); const stores = new Map();
  const state = {account:'SYNTHETIC_A', offline:false, failPinned:false, privatePinned:false, redirectPinned:false, wrongMime:false, varyCookie:false};
  const calls = {fetch:[], skipWaiting:0, claim:0};
  const fakeFetch = async (request, options={}) => {
    const url=urlOf(request); const path=new URL(url).pathname;
    const option = key => options[key] ?? request?.[key];
    calls.fetch.push({url, cache:option('cache'), credentials:option('credentials'), redirect:option('redirect'), integrity:option('integrity')});
    if(state.offline || state.failPinned) throw new TypeError('Synthetic network/integrity rejection');
    const sensitive=path.includes('sensitive-fixture');
    const text = sensitive ? state.account : path==='/offline.html' ? offline : 'SYNTHETIC_PUBLIC_ASSET';
    const type = state.wrongMime ? 'application/json' : path.endsWith('.css') ? 'text/css' : path.endsWith('.svg') ? 'image/svg+xml' : path.endsWith('.html') ? 'text/html' : 'text/plain';
    const r=new Response(text,{status:200,headers:{'Content-Type':type,'Cache-Control':(sensitive||state.privatePinned)?'private, no-store':'public, max-age=86400'}});
    if(state.varyCookie)r.headers.set('Vary','Accept-Encoding, Cookie');
    Object.defineProperties(r,{url:{value:url},type:{value:'basic'},redirected:{value:state.redirectPinned}});
    return r;
  };
  // Responses from CacheStorage retain headers, URL and response type in browsers.
  const clone = r => {
    const copy=r.clone();
    Object.defineProperties(copy,{url:{value:r.url},type:{value:r.type},redirected:{value:r.redirected}});
    return copy;
  };
  const caches = {
    async keys(){return [...stores.keys()];},
    async delete(name){return stores.delete(name);},
    async open(name){
      if(!stores.has(name))stores.set(name,new Map());
      const items=stores.get(name);
      return {
        async put(key,response){items.set(urlOf(key),clone(response));},
        async match(key){const r=items.get(urlOf(key)); return r?clone(r):undefined;},
        async keys(){return [...items.keys()].map(url=>({url}));},
        async addAll(keys){for(const key of keys)items.set(urlOf(key),await fakeFetch(key));},
      };
    },
    async match(key){for(const items of stores.values()){const r=items.get(urlOf(key));if(r)return clone(r);}return undefined;},
  };
  const self={location:{origin:ORIGIN},addEventListener:(n,f)=>handlers.set(n,f),
    skipWaiting:()=>{calls.skipWaiting++;return Promise.resolve();},
    clients:{claim:()=>{calls.claim++;return Promise.resolve();}}};
  const ctx=vm.createContext({self,caches,fetch:fakeFetch,URL,Request,Response,console});
  vm.runInContext(source,ctx,{filename:'actual-pwa-source.js',timeout:2000});
  const metadata=vm.runInContext(`({ cache: typeof CACHE === 'undefined' ? null : CACHE,
    assets: typeof PUBLIC_ASSETS === 'undefined' ? [] : PUBLIC_ASSETS })`,ctx);
  const lifecycle=async name=>{
    const work=[]; handlers.get(name)?.({waitUntil:p=>work.push(p)});
    await Promise.all(work); await settle();
  };
  const request=async(path,{method='GET',mode='cors'}={})=>{
    let intercepted=false,promise; const work=[];
    const request={url:new URL(path,ORIGIN).href,method,mode};
    handlers.get('fetch')?.({request,waitUntil:p=>work.push(p),respondWith:p=>{assert.equal(intercepted,false);intercepted=true;promise=Promise.resolve(p);}});
    const response=intercepted?await promise:undefined; await Promise.all(work); await settle();
    return {intercepted,response};
  };
  return {handlers,stores,state,calls,caches,metadata,lifecycle,request};
}
async function installed(){const h=harness();await h.lifecycle('install');await h.lifecycle('activate');return h;}

test('activation preserves unrelated same-origin caches',async()=>{
  const h=harness();await(await h.caches.open('other-app-v1')).put('/other',new Response('SYNTHETIC_OTHER'));
  await h.lifecycle('install');await h.lifecycle('activate');assert.equal(h.stores.has('other-app-v1'),true);
});
test('activation removes the known inherited Royadarman private cache',async()=>{
  const h=harness();await(await h.caches.open('royadarman-static-v2')).put('/api/private',new Response('SYNTHETIC_OLD_PRIVATE'));
  await h.lifecycle('install');await h.lifecycle('activate');assert.equal(h.stores.has('royadarman-static-v2'),false);
});
test('worker neither calls skipWaiting nor claims open clients',async()=>{
  const h=await installed();assert.equal(h.calls.skipWaiting,0);assert.equal(h.calls.claim,0);
});
test('arbitrary asset and token query remain network-only across synthetic account change',async()=>{
  const h=await installed();const path='/assets/sensitive-fixture.json?token=SYNTHETIC_ONLY';
  assert.equal(await(await h.request(path)).response.text(),'SYNTHETIC_A');
  assert.equal(Boolean(await h.caches.match(path)),false);h.state.account='SYNTHETIC_B';
  assert.equal(await(await h.request(path)).response.text(),'SYNTHETIC_B');
  assert.equal(h.calls.fetch.filter(r=>r.url===urlOf(path)).length,2);
});
test('private API, admin and locale panel routes stay uncached',async()=>{
  const h=await installed();for(const path of ['/api/v1/sensitive-fixture','/admin/sensitive-fixture','/fa/panel/sensitive-fixture','/ar/panel/sensitive-fixture','/en/panel/sensitive-fixture']){
    await h.request(path);assert.equal(Boolean(await h.caches.match(path)),false);
  }
});
test('online navigations use fresh network state and are never stored',async()=>{
  const h=await installed();const path='/en/panel/sensitive-fixture';await h.request(path,{mode:'navigate'});
  assert.equal(h.calls.fetch.at(-1).cache,'no-store');assert.equal(Boolean(await h.caches.match(path)),false);
});
test('failed navigation returns exact neutral offline shell',async()=>{
  const h=await installed();h.state.offline=true;const r=await h.request('/en/panel/sensitive-fixture',{mode:'navigate'});
  assert.equal(await r.response.text(),offline);assert.equal(Boolean(await h.caches.match('/en/panel/sensitive-fixture')),false);
});
test('offline private API rejects rather than returning fake success',async()=>{
  const h=await installed();h.state.offline=true;await assert.rejects(h.request('/api/v1/sensitive-fixture'));
});
test('non-GET and cross-origin requests are not intercepted',async()=>{
  const h=await installed();for(const method of ['POST','PUT','PATCH','DELETE'])assert.equal((await h.request('/api/v1/sensitive-fixture',{method})).intercepted,false);
  assert.equal((await h.request('https://other.invalid/assets/file.js')).intercepted,false);
});
test('public cache is fixed, explicitly versioned and integrity-pinned',async()=>{
  const h=await installed();assert.equal(h.metadata.assets.length,3);
  for(const asset of h.metadata.assets){
    assert.match(asset.url,/\?v=[a-f0-9]{64}$/);assert.match(asset.integrity,/^sha256-[A-Za-z0-9+/]{43}=$/);
    const expected=Buffer.from(new URL(asset.url,ORIGIN).searchParams.get('v'),'hex').toString('base64');
    assert.equal(asset.integrity,'sha256-'+expected);
  }
  const keys=await(await h.caches.open(h.metadata.cache)).keys();assert.equal(keys.length,3);
});
test('pinned fetches omit credentials, reject redirects and carry browser integrity metadata',async()=>{
  const h=await installed();assert.equal(h.metadata.assets.length,3);for(const asset of h.metadata.assets){
    const r=h.calls.fetch.find(r=>r.url===urlOf(asset.url));assert.ok(r);assert.equal(r.credentials,'omit');assert.equal(r.redirect,'error');assert.equal(r.integrity,asset.integrity);assert.equal(r.cache,'reload');
  }
});
test('offline document digest matches the allowlist pin',async()=>{
  const h=await installed();const asset=h.metadata.assets.find(a=>a.url.startsWith('/offline.html?'));assert.ok(asset);
  assert.equal(new URL(asset.url,ORIGIN).searchParams.get('v'),createHash('sha256').update(offline).digest('hex'));
});
test('modified or token-extended public URL cannot use a pinned cache entry',async()=>{
  const h=await installed();assert.equal(h.metadata.assets.length,3);const asset=h.metadata.assets[1];
  const path=asset.url+'&token=SYNTHETIC_ONLY';await h.request(path);assert.equal(Boolean(await h.caches.match(path)),false);
});
test('an integrity/network rejection fails installation without deleting another cache',async()=>{
  const h=harness();await(await h.caches.open('other-app-v1')).put('/other',new Response('SYNTHETIC_OTHER'));h.state.failPinned=true;
  await assert.rejects(h.lifecycle('install'));assert.equal(h.stores.has('other-app-v1'),true);
});
for(const [label,flag] of [['private/no-store response','privatePinned'],['redirected response','redirectPinned'],['wrong content type','wrongMime'],['cookie-varying response','varyCookie']]){
  test(`installation rejects a pinned ${label}`,async()=>{
    const h=harness();h.state[flag]=true;await assert.rejects(h.lifecycle('install'));
  });
}
test('unknown message cannot force activation',async()=>{
  const h=await installed();h.handlers.get('message')?.({data:{type:'SKIP_WAITING'}});assert.equal(h.calls.skipWaiting,0);
});
test('neutral offline HTML has no inline style/script/event handler or forms',()=>{
  assert.doesNotMatch(offline,/<style\b|<script\b|\son\w+\s*=|\sstyle\s*=|<form\b/i);
});
test('offline recovery has separate Persian, Arabic and English real links',()=>{
  for(const locale of ['fa','ar','en']){assert.match(offline,new RegExp(`lang="${locale}"`));assert.match(offline,new RegExp(`href="/${locale}/login"`));}
});

test('unknown requests cannot grow the fixed public cache',async()=>{
  const h=await installed();assert.equal(h.metadata.assets.length,3);
  for(let n=0;n<25;n++)await h.request(`/assets/sensitive-fixture-${n}.json?token=SYNTHETIC_${n}`);
  assert.equal((await(await h.caches.open(h.metadata.cache)).keys()).length,3);
});
test('cache eviction returns neutral 503 instead of an inherited cached document',async()=>{
  const h=await installed();h.stores.delete(h.metadata.cache);
  await(await h.caches.open('other-app-v1')).put('/offline.html',new Response('SYNTHETIC_FOREIGN_CONTENT'));
  h.state.offline=true;const result=await h.request('/fa/panel/sensitive-fixture',{mode:'navigate'});
  assert.equal(result.response.status,503);assert.equal(result.response.headers.get('Cache-Control'),'no-store');
  assert.doesNotMatch(await result.response.text(),/SYNTHETIC_FOREIGN_CONTENT/);
});
test('repeated runtime reads use only the pinned cache without network writes',async()=>{
  const h=await installed();assert.equal(h.metadata.assets.length,3);
  const before=h.calls.fetch.length;for(const asset of h.metadata.assets){await h.request(asset.url);await h.request(asset.url);}
  assert.equal(h.calls.fetch.length,before);assert.equal((await(await h.caches.open(h.metadata.cache)).keys()).length,3);
});
