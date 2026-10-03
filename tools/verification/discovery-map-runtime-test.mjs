// Controlled source-level runtime regression; not a browser/WebGL acceptance test.
// Run: node --experimental-vm-modules tools/verification/discovery-map-runtime-test.mjs
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const sourcePath = new URL('../../backend/resources/js/discovery-map.js', import.meta.url);
const source = await readFile(sourcePath, 'utf8');
const lock = JSON.parse(await readFile(new URL('../../backend/package-lock.json', import.meta.url), 'utf8'));
assert.equal(lock.packages['node_modules/maplibre-gl'].version, '6.10.0', 'Revalidate the controlled MapLibre API fixture when its locked version changes.');

class Element {
  hidden = false;
  attributes = new Map();
  listeners = new Map();
  classes = new Set();
  classList = {
    add: (value) => this.classes.add(value),
    toggle: (value, enabled) => enabled ? this.classes.add(value) : this.classes.delete(value),
  };
  setAttribute(key, value) { this.attributes.set(key, value); }
  addEventListener(type, callback) { this.listeners.set(type, callback); }
  click() { this.listeners.get('click')?.(); }
}

async function harness({ webgl = true, tile = true, constructorFails = false, controlFails = false } = {}) {
  const constructed = [];
  const markers = [];
  const container = new Element();
  const fallback = new Element();
  fallback.hidden = true;
  const list = { textContent: 'Existing clinic list and directions stay usable' };
  const root = {
    dataset: { originLng: '51.4103', originLat: '35.7572', ...(tile ? { tileUrl: 'https://example.invalid/{z}/{x}/{y}.png' } : {}) },
    querySelector: (selector) => ({ '[data-discovery-map]': container, '[data-discovery-map-fallback]': fallback, '[data-discovery-list]': list })[selector] ?? null,
  };
  class MapLibreMap {
    constructor(options) {
      // The imported Map constructor is NOT a zero-argument native collection.
      assert.ok(options?.container, 'MapLibre constructor requires map options');
      if (constructorFails) throw new Error('Synthetic constructor failure');
      this.options = options;
      this.handlers = new Map();
      this.fitCalls = [];
      this.removed = 0;
      constructed.push(this);
    }
    addControl() { if (controlFails) throw new Error('Synthetic control failure'); }
    on(event, callback) { this.handlers.set(event, callback); }
    emit(event) { this.handlers.get(event)?.({ error: new Error('Synthetic private library error') }); }
    fitBounds(bounds, options) {
      assert.equal(this.removed, 0, 'Never refilter a removed map');
      this.fitCalls.push({ coordinates: bounds.coordinates, options });
    }
    remove() { this.removed += 1; }
  }
  class Marker {
    constructor(options) { this.element = options.element; this.removed = 0; markers.push(this); }
    setLngLat(coordinates) { this.coordinates = coordinates; return this; }
    addTo(map) { assert.equal(map.removed, 0, 'Never attach a marker to a removed map'); this.map = map; return this; }
    remove() { this.removed += 1; }
  }
  class LngLatBounds {
    coordinates = [];
    extend(value) { this.coordinates.push(value); return this; }
    isEmpty() { return !this.coordinates.length; }
  }
  class NavigationControl {}
  let gpsCalls = 0;
  let networkCalls = 0;
  const context = vm.createContext({
    Map, console,
    ...(webgl ? { WebGLRenderingContext: function SyntheticWebGL() {} } : {}),
    document: { querySelectorAll: () => [], createElement: () => new Element() },
    navigator: { geolocation: { getCurrentPosition() { gpsCalls += 1; throw new Error('No GPS reads authorised by this test'); } } },
    fetch() { networkCalls += 1; throw new Error('No network requests authorised by this test'); },
  });
  const library = new vm.SyntheticModule(['LngLatBounds', 'Map', 'Marker', 'NavigationControl'], function () {
    this.setExport('LngLatBounds', LngLatBounds);
    this.setExport('Map', MapLibreMap);
    this.setExport('Marker', Marker);
    this.setExport('NavigationControl', NavigationControl);
  }, { context });
  const css = new vm.SyntheticModule([], function () {}, { context });
  // Test exports exist only in the evaluated copy, never in the production source.
  const module = new vm.SourceTextModule(source + '\nexport { createMap, updateMap, updateEphemeralUserMarker };', {
    context, identifier: fileURLToPath(sourcePath),
  });
  await module.link((specifier) => {
    if (specifier === 'maplibre-gl') return library;
    if (specifier === 'maplibre-gl/dist/maplibre-gl.css') return css;
    throw new Error('Unexpected source import: ' + specifier);
  });
  await module.evaluate();
  return { api: module.namespace, root, container, fallback, list, constructed, markers, MapLibreMap,
    assertNoExternalReads() { assert.equal(gpsCalls, 0); assert.equal(networkCalls, 0); } };
}

const clinic = (id, lat = 35.7572, lng = 51.4103) => ({ clinic_id: id, name: 'Synthetic clinic ' + id, latitude: lat, longitude: lng });
const origin = { lat: 35.7572, lng: 51.4103 };
const cases = [
  ['map constructor and native marker collection remain distinct', async () => {
    const h = await harness();
    let selected = null;
    const state = h.api.createMap(h.root, (id) => { selected = id; });
    assert.ok(state);
    assert.ok(state.map instanceof h.MapLibreMap);
    assert.ok(state.markers instanceof Map);
    assert.equal(h.constructed.length, 1);
    assert.equal(h.container.hidden, false);
    assert.equal(h.fallback.hidden, true);
    h.api.updateMap(state, h.root, [clinic('actual-created-map')], origin);
    state.markers.get('actual-created-map').element.click();
    assert.equal(selected, 'actual-created-map');
    h.assertNoExternalReads();
  }],
  ['real marker callbacks, wrapper removal, refilter and empty transition', async () => {
    const h = await harness();
    let selected = null;
    // Independent state exposes the wrapper-removal bug even when createMap is broken.
    const state = { map: new h.MapLibreMap({ container: h.container }), markers: new Map(), userMarker: null, onSelect: (id) => { selected = id; } };
    h.api.updateMap(state, h.root, [clinic('one'), clinic('two', 35.8, 51.4), clinic('invalid', NaN)], origin);
    assert.deepEqual([...state.markers.keys()], ['one', 'two']);
    state.markers.get('one').element.click();
    assert.equal(selected, 'one');
    const old = [...state.markers.values()];
    h.api.updateMap(state, h.root, [clinic('replacement')], origin);
    old.forEach(({ marker }) => assert.equal(marker.removed, 1));
    assert.deepEqual([...state.markers.keys()], ['replacement']);
    const replacement = state.markers.get('replacement').marker;
    h.api.updateMap(state, h.root, [], origin);
    assert.equal(replacement.removed, 1);
    assert.equal(state.markers.size, 0);
    assert.equal(state.map.fitCalls.length, 3);
    assert.equal(state.map.fitCalls.at(-1).coordinates.length, 1);
    h.assertNoExternalReads();
  }],
  ...[['missing WebGL', { webgl: false }], ['missing tile configuration', { tile: false }], ['constructor failure', { constructorFails: true }]].map(([name, options]) => [name + ' exposes translated fallback and hides blank canvas', async () => {
    const h = await harness(options);
    assert.equal(h.api.createMap(h.root, () => {}), null);
    assert.equal(h.fallback.hidden, false);
    assert.equal(h.container.hidden, true);
    assert.equal(h.list.textContent, 'Existing clinic list and directions stay usable');
    h.assertNoExternalReads();
  }]),
  ['partial construction failure removes the constructed map', async () => {
    const h = await harness({ controlFails: true });
    assert.equal(h.api.createMap(h.root, () => {}), null);
    assert.equal(h.constructed.length, 1);
    assert.equal(h.constructed[0].removed, 1);
    assert.equal(h.fallback.hidden, false);
    assert.equal(h.container.hidden, true);
  }],
  ['runtime error retires markers and map; subsequent filters keep list fallback', async () => {
    const h = await harness();
    const state = h.api.createMap(h.root, () => {});
    assert.ok(state);
    h.api.updateMap(state, h.root, [clinic('one')], origin);
    h.api.updateEphemeralUserMarker(state, origin);
    const existingMarkers = [...h.markers];
    const fits = state.map.fitCalls.length;
    state.map.emit('error');
    assert.equal(state.failed, true);
    assert.equal(state.map.removed, 1);
    assert.equal(state.markers.size, 0);
    assert.equal(state.userMarker, null);
    existingMarkers.forEach((marker) => assert.equal(marker.removed, 1));
    assert.equal(h.fallback.hidden, false);
    assert.equal(h.container.hidden, true);
    assert.equal(h.list.textContent, 'Existing clinic list and directions stay usable');
    h.api.updateMap(state, h.root, [clinic('later')], origin);
    h.api.updateEphemeralUserMarker(state, origin);
    assert.equal(h.markers.length, existingMarkers.length);
    assert.equal(state.map.fitCalls.length, fits);
    state.map.emit('error');
    assert.equal(state.map.removed, 1);
    h.assertNoExternalReads();
  }],
];

const failures = [];
for (const [name, check] of cases) {
  try { await check(); } catch (error) { failures.push({ name, message: error.message }); }
}
console.log(JSON.stringify({ tests: cases.length, passed: cases.length - failures.length, failed: failures.length, failures, evidence: 'controlled fake MapLibre 6.10.0 API and DOM; no browser/WebGL/provider proof' }));
if (failures.length) process.exitCode = 1;
