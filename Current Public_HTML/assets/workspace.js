(() => {
  const root = document.querySelector('[data-workspace]');
  document.querySelectorAll('[data-mobile-nav]').forEach(btn => btn.addEventListener('click', () => root?.classList.toggle('nav-open')));
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener(el.tagName === 'FORM' ? 'submit' : 'click', event => {
      if (!window.confirm(el.dataset.confirm || 'Are you sure?')) event.preventDefault();
    });
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
      btn.addEventListener('click', () => {
        ed.focus();
        if ('rteLink' in btn.dataset) { const url = window.prompt('URL:', 'https://'); if (url) document.execCommand('createLink', false, url); }
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
