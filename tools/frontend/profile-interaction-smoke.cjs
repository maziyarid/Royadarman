// Synthetic DOM behaviour checks. This is not a browser/layout/WebAuthn test.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('backend/public/assets/profile-workspace.js', 'utf8');

const element = (extra = {}) => ({
  listeners: {}, attrs: {},
  addEventListener(name, handler) { (this.listeners[name] ||= []).push(handler); },
  async emit(name, event = {}) { for (const handler of this.listeners[name] || []) await handler(event); },
  setAttribute(key, value) { this.attrs[key] = value; },
  getAttribute(key) { return this.attrs[key] ?? null; },
  removeAttribute(key) { delete this.attrs[key]; },
  focus() { this.focused = true; },
  ...extra,
});

function fixture({ clipboard, secure = true } = {}) {
  const status = element({ hidden: true, textContent: '' });
  const input = element({ value: 'synthetic-private-key', selected: 0, select() { this.selected++; } });
  const copy = element({ hidden: true, disabled: false, dataset: { copyValue: '#manual-key' } });
  const button = element({ textContent: 'Save', disabled: false });
  const form = element({ querySelectorAll() { return [button]; } });
  const details = { open: false };
  const invalidInput = element({ closest() { return details; } });
  const errorLink = element({ getAttribute() { return '#invalid-field'; } });
  const errors = element({ querySelectorAll() { return [errorLink]; } });
  const root = element({
    dataset: { copySuccess: 'Copied privately', copyFallback: 'Select and copy manually', saving: 'Applying change' },
    querySelector(selector) {
      return ({ '[data-profile-action-status]': status, '#manual-key': input, '[data-profile-errors]': errors })[selector] || null;
    },
    querySelectorAll(selector) {
      return ({ '[data-select-value]': [input], '[data-copy-value]': [copy], '[data-profile-form]': [form] })[selector] || [];
    },
  });
  const window = element({ isSecureContext: secure });
  vm.runInNewContext(source, {
    document: { querySelector() { return root; }, getElementById() { return invalidInput; } },
    window, navigator: { clipboard },
  });
  return { status, input, copy, button, form, details, invalidInput, errorLink, errors, window };
}

(async () => {
  let copied;
  const f = fixture({ clipboard: { async writeText(value) { copied = value; } } });
  assert.equal(f.copy.hidden, false);
  assert.equal(f.errors.focused, true);
  await f.input.emit('focus');
  await f.input.emit('click');
  assert.equal(f.input.selected, 2);
  await f.copy.emit('click');
  assert.equal(copied, 'synthetic-private-key');
  assert.equal(f.status.textContent, 'Copied privately');
  assert.equal(f.status.textContent.includes(copied), false);
  assert.equal(f.copy.disabled, false);

  const denied = fixture({ clipboard: { async writeText() { throw new Error('denied'); } } });
  await denied.copy.emit('click');
  assert.equal(denied.input.focused, true);
  assert.equal(denied.input.selected, 1);
  assert.equal(denied.status.textContent, 'Select and copy manually');
  assert.equal(denied.copy.disabled, false);
  const unsupported = fixture({ secure: false });
  await unsupported.copy.emit('click');
  assert.equal(unsupported.input.selected, 1);
  assert.equal(unsupported.status.textContent, 'Select and copy manually');

  // A cancelled external confirmation must not leave the real form locked.
  await f.form.emit('submit', { defaultPrevented: true });
  assert.equal(f.button.disabled, false);
  assert.equal(f.form.getAttribute('aria-busy'), null);
  await f.form.emit('submit', { defaultPrevented: false });
  assert.equal(f.button.disabled, true);
  assert.equal(f.button.textContent, 'Applying change');
  assert.equal(f.form.getAttribute('aria-busy'), 'true');
  let duplicatePrevented = false;
  await f.form.emit('submit', { defaultPrevented: false, preventDefault() { duplicatePrevented = true; } });
  assert.equal(duplicatePrevented, true);
  await f.window.emit('pageshow');
  assert.equal(f.button.disabled, false);
  assert.equal(f.button.textContent, 'Save');
  assert.equal(f.form.getAttribute('aria-busy'), null);
  await f.errorLink.emit('click');
  assert.equal(f.details.open, true);
  assert.equal(f.invalidInput.focused, true);
  console.log('PASS: explicit secret copying, denied/unsupported fallback, selection, validation focus, cancelled confirmation, duplicate prevention and back-navigation reset. DOM adapter only.');
})().catch((error) => { console.error(error); process.exitCode = 1; });
