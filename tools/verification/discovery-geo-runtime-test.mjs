// Controlled actual-source policy/callback regression. No real GPS/browser/provider proof.
// Run: node --experimental-vm-modules tools/verification/discovery-geo-runtime-test.mjs
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = await readFile(new URL('../../backend/resources/js/discovery-map.js', import.meta.url), 'utf8');
const copy = { location_ephemeral: 'Location stays in this tab', location_denied: 'Location denied; neighbourhood remains', map_unavailable: 'Map unavailable' };
const neighbourhoodLink = 'https://nshn.ir/maps?origin=35.7572%2C51.4103&destination=35.8%2C51.4&type=drive';
class Element {
  constructor(tag = 'div') { this.tag = tag; this.hidden = true; this.dataset = {}; this.listeners = new Map(); this.attributes = new Map(); }
  classList = { add() {}, toggle() {} };
  addEventListener(type, listener) { this.listeners.set(type, listener); }
  setAttribute(key, value) { this.attributes.set(key, value); }
  removeAttribute(key) { this.attributes.delete(key); }
  click() { return this.listeners.get('click')?.({ target: this }); }
}
async function harness({ modern = 'missing', legacy = 'missing', api = true, synchronousThrow = false } = {}) {
  const geo = new Element('button'), clear = new Element('button'), mapContainer = new Element(), fallback = new Element();
  const status = { textContent: '' }, requests = [], markers = [], maps = [];
  const direction = { href: neighbourhoodLink };
  const card = { dataset: { clinicId: 'public-clinic', lat: '35.8', lng: '51.4' }, classList: { toggle() {} },
    querySelector: (selector) => selector === '[data-directions-link]' ? direction : selector === 'h3' ? { textContent: 'Public clinic' } : null,
    setAttribute() {}, removeAttribute() {} };
  const root = new Element();
  root.dataset = { originLat: '35.7572', originLng: '51.4103', tileUrl: 'https://example.invalid/{z}/{x}/{y}.png' };
  const attributes = { 'data-origin-lat': '35.7572', 'data-origin-lng': '51.4103', 'data-neighborhood-id': 'vanak', 'data-service-type': 'guidance_referral', 'data-endpoint': '/api/v1/public/discovery/clinics' };
  root.getAttribute = (key) => attributes[key] ?? '';
  root.querySelector = (selector) => ({ '[data-discovery-copy]': { textContent: JSON.stringify(copy) }, '[data-discovery-geo]': geo,
    '[data-discovery-geo-clear]': clear, '[data-discovery-status]': status, '[data-discovery-map]': mapContainer,
    '[data-discovery-map-fallback]': fallback })[selector] ?? null;
  root.querySelectorAll = (selector) => selector === '.discovery-card' ? [card] : [];
  const document = { querySelectorAll: () => [], createElement: (tag) => new Element(tag) };
  const policy = (value) => value === 'no-method' ? {} : ({ allowsFeature(name) {
    assert.equal(name, 'geolocation');
    if (value === 'throw') throw new Error('Synthetic policy inspection failure');
    return value;
  } });
  if (modern !== 'missing') document.permissionsPolicy = policy(modern);
  if (legacy !== 'missing') document.featurePolicy = policy(legacy);
  const navigator = { ...(api ? { geolocation: { ...(api === 'missing-method' ? {} : { getCurrentPosition(success, failure, options) {
    if (synchronousThrow) throw new Error('Synthetic geolocation call failure');
    requests.push({ success, failure, options });
  } }) } } : {}) };
  let forbidden = 0;
  class MapLibreMap { constructor() { this.removed = false; maps.push(this); } on() {} addControl() {} fitBounds() {} remove() { this.removed = true; } }
  class Marker { constructor({ element }) { this.element = element; this.removed = false; markers.push(this); } setLngLat(value) { this.coordinates = value; return this; } addTo(map) { assert.equal(map.removed, false); return this; } remove() { this.removed = true; } }
  class LngLatBounds { extend() { return this; } isEmpty() { return false; } }
  const context = vm.createContext({ document, navigator, Map, WebGLRenderingContext: function () {},
    fetch() { forbidden++; throw new Error('Network not authorised'); },
    localStorage: new Proxy({}, { get() { forbidden++; throw new Error('Storage not authorised'); } }),
  });
  const library = new vm.SyntheticModule(['LngLatBounds', 'Map', 'Marker', 'NavigationControl'], function () {
    this.setExport('LngLatBounds', LngLatBounds); this.setExport('Map', MapLibreMap); this.setExport('Marker', Marker); this.setExport('NavigationControl', class {});
  }, { context });
  const css = new vm.SyntheticModule([], function () {}, { context });
  const module = new vm.SourceTextModule(source + '\nexport { initRoot };', { context });
  await module.link((name) => name === 'maplibre-gl' ? library : name === 'maplibre-gl/dist/maplibre-gl.css' ? css : assert.fail('Unexpected import'));
  await module.evaluate(); module.namespace.initRoot(root);
  assert.equal(requests.length, 0, 'No automatic coordinate request');
  return { geo, clear, direction, status, requests, document,
    success(index, lat = 36, lng = 52) { requests[index].success({ coords: { latitude: lat, longitude: lng } }); },
    failure(index) { requests[index].failure({ code: 1 }); },
    assertNoExternalEffects() { assert.equal(forbidden, 0); },
    activeUserMarkers() { return markers.filter((marker) => marker.element.tag === 'div' && !marker.removed); },
    activeClinicMarkers() { return markers.filter((marker) => marker.element.tag === 'button' && !marker.removed); },
  };
}
const cases = [
  ...[['modern denied', { modern: false }], ['legacy denied', { legacy: false }]].map(([name, options]) => [name + ' hides impossible control without requesting location', async () => {
    const h = await harness(options); assert.equal(h.geo.hidden, true); assert.equal(h.clear.hidden, true); h.geo.click(); assert.equal(h.requests.length, 0); assert.equal(h.direction.href, neighbourhoodLink); h.assertNoExternalEffects();
  }]),
  ['modern object without introspection falls back to legacy denial', async () => {
    const h = await harness({ modern: 'no-method', legacy: false }); assert.equal(h.geo.hidden, true); h.geo.click(); assert.equal(h.requests.length, 0); h.assertNoExternalEffects();
  }],
  ['modern allowed policy takes precedence over legacy denial', async () => {
    const h = await harness({ modern: true, legacy: false }); assert.equal(h.geo.hidden, false); h.geo.click(); assert.equal(h.requests.length, 1); assert.deepEqual(JSON.parse(JSON.stringify(h.requests[0].options)), { enableHighAccuracy: false, timeout: 8000, maximumAge: 0 }); h.success(0); assert.equal(h.clear.hidden, false); assert.equal(h.activeUserMarkers().length, 1); assert.equal(h.status.textContent, copy.location_ephemeral); h.assertNoExternalEffects();
  }],
  ...[['no policy inspection API', {}], ['throwing policy', { modern: 'throw' }], ['unknown nonboolean policy', { modern: 'unknown' }]].map(([name, options]) => [name + ' preserves optional click-only attempt and denial fallback', async () => {
    const h = await harness(options); assert.equal(h.geo.hidden, false); h.geo.click(); assert.equal(h.requests.length, 1); h.failure(0); assert.equal(h.direction.href, neighbourhoodLink); assert.equal(h.clear.hidden, true); assert.equal(h.status.textContent, copy.location_denied); h.assertNoExternalEffects();
  }]),
  ...[['missing API', { api: false }], ['missing method', { api: 'missing-method' }]].map(([name, options]) => [name + ' leaves neighbourhood fallback without interactive location control', async () => {
    const h = await harness(options); assert.equal(h.geo.hidden, true); assert.equal(h.direction.href, neighbourhoodLink); assert.equal(h.requests.length, 0); h.assertNoExternalEffects();
  }]),
  ['policy becomes denied before click: no location call and clear previous origin', async () => {
    const h = await harness({ modern: true }); h.geo.click(); h.success(0); h.document.permissionsPolicy.allowsFeature = () => false; h.geo.click(); assert.equal(h.requests.length, 1); assert.equal(h.geo.hidden, true); assert.equal(h.clear.hidden, true); assert.equal(h.direction.href, neighbourhoodLink); assert.equal(h.activeUserMarkers().length, 0); h.assertNoExternalEffects();
  }],
  ['synchronous API exception restores neighbourhood links and denial status', async () => {
    const h = await harness({ modern: true, synchronousThrow: true }); assert.doesNotThrow(() => h.geo.click()); assert.equal(h.direction.href, neighbourhoodLink); assert.equal(h.clear.hidden, true); assert.equal(h.status.textContent, copy.location_denied); h.assertNoExternalEffects();
  }],
  ['clear invalidates pending success and prevents reintroducing private origin', async () => {
    const h = await harness({ modern: true }); h.geo.click(); h.success(0); h.geo.click(); assert.equal(h.requests.length, 2); h.clear.click(); const status = h.status.textContent; h.success(1); assert.equal(h.direction.href, neighbourhoodLink); assert.equal(h.clear.hidden, true); assert.equal(h.activeUserMarkers().length, 0); assert.equal(h.status.textContent, status); assert.equal(h.activeClinicMarkers().length, 1); h.assertNoExternalEffects();
  }],
  ['newest request defeats old success and old denial callbacks', async () => {
    const h = await harness({ modern: true }); h.geo.click(); h.geo.click(); h.success(1, 36.5, 52.5); const link = h.direction.href; h.success(0, 37, 53); assert.equal(h.direction.href, link); h.failure(0); assert.equal(h.direction.href, link); assert.equal(h.clear.hidden, false); assert.equal(h.activeUserMarkers().length, 1); h.assertNoExternalEffects();
  }],
  ['policy denial during pending success never installs origin or marker', async () => {
    const h = await harness({ modern: true }); h.geo.click(); h.document.permissionsPolicy.allowsFeature = () => false; h.success(0); assert.equal(h.direction.href, neighbourhoodLink); assert.equal(h.geo.hidden, true); assert.equal(h.clear.hidden, true); assert.equal(h.activeUserMarkers().length, 0); h.assertNoExternalEffects();
  }],
  ['latest failed request clears an earlier origin; stale failure after clear stays silent', async () => {
    const h = await harness({ modern: true }); h.geo.click(); h.success(0); h.geo.click(); h.failure(1); assert.equal(h.direction.href, neighbourhoodLink); assert.equal(h.clear.hidden, true); assert.equal(h.activeUserMarkers().length, 0); h.clear.click(); const status = h.status.textContent; h.failure(1); assert.equal(h.status.textContent, status); h.assertNoExternalEffects();
  }],
];
const failures = [];
for (const [name, check] of cases) { try { await check(); } catch (error) { failures.push({ name, message: error.message }); } }
console.log(JSON.stringify({ tests: cases.length, passed: cases.length - failures.length, failed: failures.length, failures, evidence: 'controlled actual-source DOM/policy/callback fixture; no real GPS/browser/provider proof' }));
if (failures.length) process.exitCode = 1;
