(() => {
  const root = document.querySelector('[data-workspace]');
  document.querySelectorAll('[data-mobile-nav]').forEach(btn => btn.addEventListener('click', () => root?.classList.toggle('nav-open')));

  const labels = () => ({
    ok: document.body?.dataset.dialogOk || 'OK',
    cancel: document.body?.dataset.dialogCancel || 'Cancel',
    url: document.body?.dataset.dialogUrl || 'URL',
  });

  const dialog = ({ title = '', body = '', confirmLabel, cancelLabel, input = false, inputValue = '', hideCancel = false } = {}) => new Promise((resolve) => {
    document.getElementById('rd-dialog')?.remove();
    const L = labels();
    const overlay = document.createElement('div');
    overlay.id = 'rd-dialog';
    overlay.className = 'rd-dialog-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'rd-dialog-title');
    overlay.innerHTML = `<div class="rd-dialog" role="document"><h2 id="rd-dialog-title"></h2><div class="rd-dialog-body"></div>${input ? '<label class="rd-dialog-field"><span class="rd-dialog-input-label"></span><input class="rd-dialog-input" type="url" inputmode="url" dir="ltr" autocomplete="url"></label>' : ''}<div class="rd-dialog-actions">${hideCancel ? '' : '<button type="button" class="btn" data-rd-cancel></button>'}<button type="button" class="btn primary" data-rd-ok></button></div></div>`;
    overlay.querySelector('#rd-dialog-title').textContent = title;
    overlay.querySelector('.rd-dialog-body').textContent = body;
    overlay.querySelector('[data-rd-ok]').textContent = confirmLabel || L.ok;
    const cancelBtn = overlay.querySelector('[data-rd-cancel]');
    if (cancelBtn) cancelBtn.textContent = cancelLabel || L.cancel;
    const field = overlay.querySelector('.rd-dialog-input');
    if (field) {
      overlay.querySelector('.rd-dialog-input-label').textContent = L.url;
      field.value = inputValue;
    }
    const previouslyFocused = document.activeElement;
    const focusables = () => [...overlay.querySelectorAll('button, input, textarea, select, a[href], [tabindex]:not([tabindex="-1"])')].filter((el) => !el.hasAttribute('disabled'));
    const close = (value) => {
      overlay.remove();
      document.removeEventListener('keydown', onKey);
      if (previouslyFocused && typeof previouslyFocused.focus === 'function') previouslyFocused.focus();
      resolve(value);
    };
    const onKey = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        close(false);
        return;
      }
      if (event.key !== 'Tab') return;
      const items = focusables();
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    overlay.querySelector('[data-rd-ok]').addEventListener('click', () => {
      if (!input) {
        close(true);
        return;
      }
      const value = (field?.value || '').trim();
      close(value || false);
    });
    cancelBtn?.addEventListener('click', () => close(false));
    overlay.addEventListener('click', (event) => { if (event.target === overlay) close(false); });
    document.addEventListener('keydown', onKey);
    (document.getElementById('rd-dialog-root') || document.body).appendChild(overlay);
    (field || overlay.querySelector('[data-rd-ok]')).focus();
    if (field) field.select();
  });
  window.royadarmanDialog = dialog;

  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener(el.tagName === 'FORM' ? 'submit' : 'click', async (event) => {
      if (el.dataset.rdConfirmed === '1') {
        el.dataset.rdConfirmed = '0';
        return;
      }
      event.preventDefault();
      event.stopImmediatePropagation();
      const L = labels();
      const ok = await dialog({
        title: el.dataset.confirmTitle || '',
        body: el.dataset.confirm || '',
        confirmLabel: el.dataset.confirmOk || L.ok,
        cancelLabel: el.dataset.confirmCancel || L.cancel,
      });
      if (!ok) return;
      el.dataset.rdConfirmed = '1';
      if (el.tagName === 'FORM') el.requestSubmit();
      else el.click();
    }, { capture: true });
  });

  document.querySelectorAll('[data-tab]').forEach(btn => btn.addEventListener('click', () => {
    const name = btn.dataset.tab;
    const tabs = btn.closest('.tabs')?.querySelectorAll('[data-tab]') || [];
    tabs.forEach(item => item.classList.toggle('active', item === btn));
    document.querySelectorAll('[data-panel]').forEach(panel => panel.classList.toggle('active', panel.dataset.panel === name));
  }));
  const logout = document.querySelector('[data-logout]');
  if (logout) logout.addEventListener('click', async () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const locale = logout.dataset.locale || document.documentElement.lang || 'fa';
    const response = await fetch('/api/v1/auth/logout', {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Locale':locale}});
    if (response.ok) window.location.assign(logout.dataset.home || '/');
  });
  document.querySelectorAll('[data-rte-editor]').forEach(ed => {
    const locale = ed.dataset.rteEditor;
    const source = document.querySelector(`[data-rte-source="${locale}"]`);
    if (!source) return;
    ed.innerHTML = source.value || '<p><br></p>';
    const toolbar = document.querySelector(`[data-rte-toolbar="${locale}"]`);
    toolbar?.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('mousedown', e => e.preventDefault());
      btn.addEventListener('click', async () => {
        ed.focus();
        if ('rteLink' in btn.dataset) {
          const url = await dialog({ title: labels().url, body: '', input: true, inputValue: 'https://' });
          if (url) document.execCommand('createLink', false, url);
        }
        else if (btn.dataset.cmdBlock) document.execCommand('formatBlock', false, btn.dataset.cmdBlock);
        else if (btn.dataset.cmd === 'formatBlock' && btn.dataset.value) document.execCommand('formatBlock', false, btn.dataset.value);
        else if (btn.dataset.cmd) document.execCommand(btn.dataset.cmd, false, null);
        source.value = ed.innerHTML;
      });
    });
    ed.addEventListener('input', () => source.value = ed.innerHTML);
    ed.addEventListener('blur', () => source.value = ed.innerHTML);
  });
  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
    document.querySelectorAll('[data-rte-editor]').forEach(ed => {
      const source = document.querySelector(`[data-rte-source="${ed.dataset.rteEditor}"]`);
      if (source) source.value = ed.innerHTML;
    });
  }, {capture:true}));
})();
