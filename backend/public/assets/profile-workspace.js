(() => {
  'use strict';
  const root = document.querySelector('[data-profile-workspace]');
  if (!root) return;
  const status = root.querySelector('[data-profile-action-status]');
  const announce = (text) => {
    if (!status) return;
    status.textContent = text;
    status.hidden = !text;
  };

  root.querySelectorAll('[data-select-value]').forEach((input) => {
    input.addEventListener('focus', () => input.select());
    input.addEventListener('click', () => input.select());
  });
  root.querySelectorAll('[data-copy-value]').forEach((button) => {
    const input = root.querySelector(button.dataset.copyValue);
    if (!input) return;
    button.hidden = false;
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        if (!window.isSecureContext || !navigator.clipboard?.writeText) throw new Error('clipboard_unavailable');
        // Copy only on an explicit click. Never store or include secrets in messages.
        await navigator.clipboard.writeText(input.value);
        announce(root.dataset.copySuccess);
      } catch {
        input.focus();
        input.select();
        announce(root.dataset.copyFallback);
      } finally {
        button.disabled = false;
      }
    });
  });

  const forms = [...root.querySelectorAll('[data-profile-form]')].map((form) => ({
    form,
    buttons: [...form.querySelectorAll('button[type="submit"]')].map((button) => ({
      button, text: button.textContent, disabled: button.disabled,
    })),
  }));
  const resetForms = () => forms.forEach(({ form, buttons }) => {
    form.removeAttribute('aria-busy');
    buttons.forEach(({ button, text, disabled }) => {
      button.textContent = text;
      button.disabled = disabled;
    });
  });
  forms.forEach(({ form, buttons }) => form.addEventListener('submit', (event) => {
    // workspace.js owns confirmation. A cancelled confirmation must not lock a form.
    if (event.defaultPrevented) return;
    if (form.getAttribute('aria-busy') === 'true') {
      event.preventDefault();
      return;
    }
    form.setAttribute('aria-busy', 'true');
    buttons.forEach(({ button }) => {
      button.disabled = true;
      button.textContent = root.dataset.saving;
    });
  }));
  window.addEventListener('pageshow', resetForms);

  const errors = root.querySelector('[data-profile-errors]');
  if (errors) {
    errors.focus();
    errors.querySelectorAll('a[href^="#"]').forEach((link) => link.addEventListener('click', () => {
      const input = document.getElementById(link.getAttribute('href').slice(1));
      if (!input) return;
      const details = input.closest('details');
      if (details) details.open = true;
      input.focus();
    }));
  }
})();
