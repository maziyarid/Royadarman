// Controlled actual-source clipboard regression, not physical-browser clipboard proof.
// Run: node --experimental-vm-modules tools/verification/discovery-copy-runtime-test.mjs
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = await readFile(new URL('../../backend/resources/js/discovery-map.js', import.meta.url), 'utf8');
const copy = { destination_copied: 'Copied', destination_copy_failed: 'Copy failed; copy manually' };
const destination = '35.7572,51.4103';

async function harness({ clipboard = 'reject', command = false, strictStyles = true } = {}) {
  const buffers = [], clipboardValues = [], commands = [], pending = [];
  const status = { textContent: '' }, fallback = { hidden: true };
  let forbiddenReads = 0;
  const document = {
    querySelectorAll: () => [],
    body: { append(buffer) { buffer.attached = true; } },
    createElement(tag) {
      assert.equal(tag, 'textarea');
      const buffer = { value: '', className: '', attached: false, removed: 0, selected: 0, attributes: {},
        // Enforce our no-inline-style authoring contract, not browser CSP behavior.
        style: new Proxy({}, { set() { if (strictStyles) throw new Error('Inline styles forbidden'); return true; } }),
        setAttribute(key, value) { this.attributes[key] = value; },
        select() { this.selected += 1; },
        focus() { document.activeElement = this; },
        remove() { this.attached = false; this.removed += 1; },
      };
      buffers.push(buffer);
      return buffer;
    },
  };
  const previous = { focused: 0, focus() { this.focused += 1; document.activeElement = this; } };
  document.activeElement = previous;
  const manual = { value: '', focused: 0, selected: 0,
    focus() { this.focused += 1; document.activeElement = this; },
    select() { this.selected += 1; },
  };
  const root = { querySelector: (selector) => ({
    '[data-discovery-status]': status,
    '[data-discovery-copy-fallback]': fallback,
    '[data-discovery-copy-value]': manual,
  })[selector] ?? null };
  let behavior = clipboard;
  const navigator = { ...(clipboard === 'missing' ? {} : { clipboard: {
    async writeText(value) {
      clipboardValues.push(value);
      if (behavior === 'deferred') return new Promise((resolve, reject) => pending.push({ resolve, reject }));
      if (behavior !== 'success') throw new Error('Private clipboard rejection');
    },
  } }), geolocation: { getCurrentPosition() { forbiddenReads += 1; throw new Error('GPS forbidden'); } } };
  if (command !== 'missing') document.execCommand = (name) => {
    commands.push(name);
    assert.ok(buffers.at(-1).attached);
    assert.equal(buffers.at(-1).selected, 1);
    if (command === 'throw') throw new Error('Private command failure');
    return command;
  };
  const context = vm.createContext({ Map, document, navigator,
    fetch() { forbiddenReads += 1; throw new Error('Network forbidden'); },
    localStorage: new Proxy({}, { get() { forbiddenReads += 1; throw new Error('Storage forbidden'); } }),
  });
  const library = new vm.SyntheticModule(['LngLatBounds', 'Map', 'Marker', 'NavigationControl'], function () {
    for (const name of ['LngLatBounds', 'Map', 'Marker', 'NavigationControl']) this.setExport(name, class {});
  }, { context });
  const css = new vm.SyntheticModule([], function () {}, { context });
  const module = new vm.SourceTextModule(source + '\nexport { copyDestination };', { context });
  await module.link((specifier) => {
    if (specifier === 'maplibre-gl') return library;
    if (specifier === 'maplibre-gl/dist/maplibre-gl.css') return css;
    throw new Error('Unexpected source import');
  });
  await module.evaluate();
  return { run: (value = destination) => module.namespace.copyDestination(root, value, copy),
    status, fallback, manual, previous, buffers, commands, clipboardValues, document,
    setClipboardSuccess() { behavior = 'success'; },
    resolveClipboard(index) { pending[index].resolve(); },
    rejectClipboard(index) { pending[index].reject(new Error('Private delayed clipboard rejection')); },
    assertClean() {
      assert.equal(forbiddenReads, 0);
      buffers.forEach((buffer) => {
        assert.equal(buffer.removed, 1);
        assert.equal(buffer.attached, false);
        assert.equal(buffer.className, 'visually-hidden');
        assert.equal(buffer.attributes.readonly, '');
      });
    },
  };
}

const cases = [
  ['primary clipboard success announces copied without a buffer', async () => {
    const h = await harness({ clipboard: 'success' });
    await h.run();
    assert.equal(h.status.textContent, copy.destination_copied);
    assert.equal(h.fallback.hidden, true);
    assert.equal(h.manual.value, '');
    assert.deepEqual(h.clipboardValues, [destination]);
    assert.equal(h.buffers.length, 0);
    assert.equal(h.commands.length, 0);
    assert.equal(h.document.activeElement, h.previous);
    h.assertClean();
  }],
  ['successful legacy command announces copied and restores prior focus', async () => {
    const h = await harness({ command: true });
    await h.run();
    assert.equal(h.status.textContent, copy.destination_copied);
    assert.equal(h.fallback.hidden, true);
    assert.equal(h.manual.value, '');
    assert.deepEqual(h.commands, ['copy']);
    assert.equal(h.document.activeElement, h.previous);
    h.assertClean();
  }],
  ['false legacy command never announces copied even if inline styles are tolerated', async () => {
    const h = await harness({ command: false, strictStyles: false });
    await h.run();
    assert.equal(h.status.textContent, copy.destination_copy_failed);
    assert.deepEqual(h.commands, ['copy']);
    assert.equal(h.fallback.hidden, false);
    assert.equal(h.manual.value, destination);
    h.assertClean();
  }],
  ...[['false command', false], ['throwing command', 'throw'], ['missing command', 'missing']].map(([name, command]) => [name + ' exposes truthful public manual copy and removes buffer', async () => {
    const h = await harness({ command });
    await h.run();
    assert.equal(h.status.textContent, copy.destination_copy_failed);
    assert.equal(h.fallback.hidden, false);
    assert.equal(h.manual.value, destination);
    assert.equal(h.document.activeElement, h.manual);
    assert.equal(h.manual.selected, 1);
    h.assertClean();
  }]),
  ['missing clipboard API can still use a true legacy command', async () => {
    const h = await harness({ clipboard: 'missing', command: true });
    await h.run();
    assert.equal(h.status.textContent, copy.destination_copied);
    assert.deepEqual(h.commands, ['copy']);
    h.assertClean();
  }],
  ['neither clipboard nor command is available exposes manual destination', async () => {
    const h = await harness({ clipboard: 'missing', command: 'missing' });
    await h.run();
    assert.equal(h.status.textContent, copy.destination_copy_failed);
    assert.equal(h.fallback.hidden, false);
    assert.equal(h.manual.value, destination);
    h.assertClean();
  }],
  ['repeated failures replace the manual value; subsequent success clears it', async () => {
    const h = await harness();
    await h.run();
    await h.run('35.8,51.4');
    assert.equal(h.manual.value, '35.8,51.4');
    assert.equal(h.fallback.hidden, false);
    h.setClipboardSuccess();
    await h.run('35.9,51.5');
    assert.equal(h.status.textContent, copy.destination_copied);
    assert.equal(h.fallback.hidden, true);
    assert.equal(h.manual.value, '');
    assert.equal(h.buffers.length, 2);
    h.assertClean();
  }],
  ['stale success cannot clear newer failed attempt destination or status', async () => {
    const h = await harness({ clipboard: 'deferred' });
    const earlier = h.run(destination), latest = h.run('35.8,51.4');
    h.rejectClipboard(1);
    await latest;
    const focusCount = h.manual.focused;
    assert.equal(h.status.textContent, copy.destination_copy_failed);
    h.resolveClipboard(0);
    await earlier;
    assert.equal(h.status.textContent, copy.destination_copy_failed);
    assert.equal(h.fallback.hidden, false);
    assert.equal(h.manual.value, '35.8,51.4');
    assert.equal(h.manual.focused, focusCount);
    assert.equal(h.document.activeElement, h.manual);
    assert.equal(h.buffers.length, 1);
    h.assertClean();
  }],
  ['stale failure cannot overwrite newer success or enter fallback and steal focus', async () => {
    const h = await harness({ clipboard: 'deferred' });
    const earlier = h.run(destination), latest = h.run('35.8,51.4');
    h.resolveClipboard(1);
    await latest;
    h.rejectClipboard(0);
    await earlier;
    assert.equal(h.status.textContent, copy.destination_copied);
    assert.equal(h.fallback.hidden, true);
    assert.equal(h.manual.value, '');
    assert.equal(h.buffers.length, 0);
    assert.equal(h.commands.length, 0);
    assert.equal(h.manual.focused, 0);
    assert.equal(h.document.activeElement, h.previous);
    h.assertClean();
  }],
];
const failures = [];
for (const [name, check] of cases) {
  try { await check(); } catch (error) { failures.push({ name, message: error.message }); }
}
console.log(JSON.stringify({ tests: cases.length, passed: cases.length - failures.length, failed: failures.length,
  failures, evidence: 'controlled actual-source clipboard/DOM fixture; no physical-browser clipboard or CSP proof' }));
if (failures.length) process.exitCode = 1;
