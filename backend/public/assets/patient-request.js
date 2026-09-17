(() => {
  const root = document.querySelector('[data-patient-request]');
  if (!root) return;
  const locale = root.dataset.locale;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const form = document.getElementById('request-form');
  if (!form) return;

  const steps = Array.from(form.querySelectorAll('.request-step'));
  const total = steps.length;
  let current = 1;
  let policy = null;

  const area = document.getElementById('tehran_area');
  const accept = document.getElementById('accept');
  const submit = document.getElementById('submit');
  const consentText = document.getElementById('consent-text');
  const message = document.getElementById('message');
  const nextBtn = form.querySelector('[data-next]');
  const prevBtn = form.querySelector('[data-prev]');
  const progress = form.querySelector('[data-progress]') || root.querySelector('[data-progress]');
  const progressBar = root.querySelector('[data-progress-bar]');
  const progressLabel = root.querySelector('[data-progress-label]');
  const homeNote = form.querySelector('[data-home-area-note]');

  const key = () => (crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);
  const headers = (id) => {
    const h = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf,
      'X-Locale': locale,
    };
    if (id) h['Idempotency-Key'] = id;
    return h;
  };
  const errorText = (p) => p?.error?.message || p?.error?.code || root.dataset.error;
  const show = (text, kind = 'error') => {
    message.className = `notice ${kind}`;
    message.textContent = text;
  };
  const clearMessage = () => {
    message.className = '';
    message.textContent = '';
  };

  const selectedService = () => form.querySelector('input[name="service_type"]:checked')?.value || '';
  const selectedPriority = () => form.querySelector('input[name="priority"]:checked')?.value || 'normal';

  const optionLabel = (selectId, value) => {
    const el = document.getElementById(selectId);
    if (!el) return value || '—';
    const opt = Array.from(el.options).find((o) => o.value === value);
    return opt ? opt.textContent : value || '—';
  };

  const radioLabel = (name, value) => {
    const input = form.querySelector(`input[name="${name}"][value="${value}"]`);
    if (!input) return value || '—';
    const strong = input.closest('label')?.querySelector('strong');
    return strong ? strong.textContent : value;
  };

  const updateHomeNote = () => {
    const home = selectedService() === 'home_dentistry';
    if (homeNote) homeNote.hidden = !home;
    if (area) area.required = home;
  };

  const fillSummary = () => {
    const set = (key, text) => {
      const el = form.querySelector(`[data-sum="${key}"]`);
      if (el) el.textContent = text || '—';
    };
    set('service', radioLabel('service_type', selectedService()));
    set('priority', radioLabel('priority', selectedPriority()));
    set('area', optionLabel('tehran_area', area?.value || '') || '—');
    set('time', optionLabel('contact_time', document.getElementById('contact_time')?.value || ''));
    set('budget', optionLabel('budget', document.getElementById('budget')?.value || ''));
    set('name', document.getElementById('name')?.value?.trim() || '—');
    set('reason', document.getElementById('reason')?.value?.trim() || '—');
  };

  const go = (n) => {
    current = Math.max(1, Math.min(total, n));
    steps.forEach((step) => {
      const num = Number(step.dataset.step);
      step.hidden = num !== current;
    });
    if (progress) {
      progress.setAttribute('aria-valuenow', String(current));
    }
    if (progressBar) {
      progressBar.style.width = `${(current / total) * 100}%`;
    }
    if (progressLabel) {
      const template = root.dataset.stepOf || ':current / :total';
      progressLabel.textContent = template
        .replace(':current', String(current))
        .replace(':total', String(total))
        .replace(':current', String(current));
    }
    prevBtn.hidden = current === 1;
    nextBtn.hidden = current === total;
    submit.hidden = current !== total;
    if (current === total) {
      fillSummary();
      submit.disabled = !policy || !accept?.checked;
    }
    updateHomeNote();
    clearMessage();
  };

  const validateStep = () => {
    if (current === 1 && !selectedService()) {
      show(root.dataset.error);
      return false;
    }
    if (current === 2 && !selectedPriority()) {
      show(root.dataset.error);
      return false;
    }
    if (current === 3 && selectedService() === 'home_dentistry' && !area?.value) {
      show(root.dataset.error);
      area?.focus();
      return false;
    }
    if (current === 4) {
      const budget = document.getElementById('budget');
      if (!budget?.value) {
        show(root.dataset.error);
        return false;
      }
    }
    if (current === 7) {
      if (!policy || !accept?.checked) {
        show(root.dataset.error);
        return false;
      }
    }
    return true;
  };

  nextBtn?.addEventListener('click', () => {
    if (!validateStep()) return;
    go(current + 1);
  });
  prevBtn?.addEventListener('click', () => go(current - 1));

  form.querySelectorAll('input[name="service_type"]').forEach((el) => {
    el.addEventListener('change', updateHomeNote);
  });

  fetch('/api/v1/policies/case_coordination', {
    headers: { Accept: 'application/json', 'X-Locale': locale },
  })
    .then(async (r) => {
      const d = await r.json();
      if (!r.ok || !d.data) throw new Error(errorText(d));
      policy = d.data;
      if (consentText) consentText.textContent = policy.content;
      if (accept) accept.disabled = false;
    })
    .catch(() => {
      if (consentText) consentText.textContent = root.dataset.consentUnavailable;
      if (accept) accept.disabled = true;
      if (submit) submit.disabled = true;
    });

  accept?.addEventListener('change', () => {
    if (current === total) {
      submit.disabled = !accept.checked || !policy;
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (current !== total) {
      if (validateStep()) go(current + 1);
      return;
    }
    if (!policy || !accept?.checked) return;
    submit.disabled = true;
    submit.textContent = root.dataset.working;
    clearMessage();
    try {
      const body = {
        service_type: selectedService(),
        name: document.getElementById('name')?.value || null,
        tehran_area: area?.value || null,
        preferred_contact_time: document.getElementById('contact_time')?.value || 'any',
        contact_reason: document.getElementById('reason')?.value || null,
        budget_band: document.getElementById('budget')?.value,
        budget_input_unit: 'toman',
        source_language: locale,
        priority: selectedPriority(),
      };
      const dr = await fetch('/api/v1/cases/draft', {
        method: 'POST',
        headers: headers(key()),
        body: JSON.stringify(body),
      });
      const dp = await dr.json();
      if (!dr.ok || !dp.data) throw new Error(errorText(dp));
      const draft = dp.data;
      const sr = await fetch(`/api/v1/cases/${encodeURIComponent(draft.id)}/submit`, {
        method: 'POST',
        headers: headers(key()),
        body: JSON.stringify({
          version: draft.version,
          policy_version: policy.version,
          content_hash: policy.content_hash,
        }),
      });
      const sp = await sr.json();
      if (!sr.ok || !sp.data) throw new Error(errorText(sp));
      show(root.dataset.success, 'success');
      window.location.assign(`/${encodeURIComponent(locale)}/panel/cases/${encodeURIComponent(draft.id)}`);
    } catch (error) {
      show(error?.message || root.dataset.error);
      submit.disabled = false;
      submit.textContent = root.dataset.submit;
    }
  });

  go(1);
})();
