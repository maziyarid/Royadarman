(() => {
  'use strict';

  const root = document.querySelector('[data-patient-request]');
  if (!root) return;

  const locale = root.dataset.locale || 'fa';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const form = document.getElementById('request-form');
  if (!form) return;

  const intakeOn = root.dataset.intake === '1';
  const isDemo = root.dataset.demo === '1';
  const steps = Array.from(form.querySelectorAll('.request-step'));
  const total = steps.length;
  let current = 1;
  let policy = null;
  let savedDraft = null;
  let draftKey = null;
  let submitKey = null;
  let busy = false;

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
  const escalate = form.querySelector('[data-escalate]');

  const key = () => (crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);
  const headers = (id) => {
    const result = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf,
      'X-Locale': locale,
    };
    if (id) result['Idempotency-Key'] = id;
    return result;
  };
  const errorText = (payload) => {
    const details = Object.values(payload?.error?.details || {}).flat().filter(Boolean);
    return details.join(' · ') || payload?.error?.message || payload?.error?.code || payload?.message || root.dataset.error;
  };
  const show = (text, kind = 'error') => {
    if (!message) return;
    message.className = `notice ${kind}`;
    message.textContent = text || '';
  };
  const clearMessage = () => {
    if (!message) return;
    message.className = '';
    message.textContent = '';
  };

  const selectedService = () => form.querySelector('input[name="service_type"]:checked')?.value || '';
  const selectedPriority = () => form.querySelector('input[name="priority"]:checked')?.value || 'normal';

  const optionLabel = (selectId, value) => {
    const element = document.getElementById(selectId);
    if (!element) return value || '—';
    const option = Array.from(element.options).find((candidate) => candidate.value === value);
    return option ? option.textContent : value || '—';
  };

  const radioLabel = (name, value) => {
    const input = form.querySelector(`input[name="${name}"][value="${value}"]`);
    const strong = input?.closest('label')?.querySelector('strong');
    return strong?.textContent || value || '—';
  };

  const updateHomeNote = () => {
    const home = selectedService() === 'home_dentistry';
    if (homeNote) homeNote.hidden = !home;
    if (area) area.required = home && !area.disabled;
  };

  const updateEscalate = () => {
    if (escalate) escalate.hidden = selectedPriority() !== 'urgent';
  };

  const fillSummary = () => {
    const set = (name, text) => {
      const element = form.querySelector(`[data-sum="${name}"]`);
      if (element) element.textContent = text || '—';
    };
    set('service', radioLabel('service_type', selectedService()));
    set('priority', radioLabel('priority', selectedPriority()));
    const location = [
      optionLabel('province', document.getElementById('province')?.value || ''),
      document.getElementById('city')?.value?.trim(),
      document.getElementById('neighborhood')?.value?.trim(),
      area && !area.disabled && area.value ? optionLabel('tehran_area', area.value) : '',
    ].filter(Boolean).join(' · ');
    set('area', location || '—');
    set('time', optionLabel('contact_time', document.getElementById('contact_time')?.value || ''));
    set('budget', optionLabel('budget', document.getElementById('budget')?.value || ''));
    set('name', document.getElementById('name')?.value?.trim() || '—');
    set('reason', document.getElementById('reason')?.value?.trim() || '—');
  };

  const blockedReason = () => {
    if (isDemo) return root.dataset.demoReadonly || root.dataset.error;
    if (!intakeOn) return root.dataset.intakeClosed || root.dataset.error;
    return '';
  };

  const go = (number) => {
    current = Math.max(1, Math.min(total, number));
    steps.forEach((step) => { step.hidden = Number(step.dataset.step) !== current; });
    progress?.setAttribute('aria-valuenow', String(current));
    if (progressBar) {
      progressBar.max = total;
      progressBar.value = current;
    }
    if (progressLabel) {
      const template = root.dataset.stepOf || ':current / :total';
      progressLabel.textContent = template.replaceAll(':current', String(current)).replaceAll(':total', String(total));
    }
    if (prevBtn) prevBtn.hidden = current === 1;
    if (nextBtn) nextBtn.hidden = current === total;
    if (submit) submit.hidden = current !== total;
    if (current === total) {
      fillSummary();
      const blocked = blockedReason();
      submit.disabled = busy || Boolean(blocked) || !policy || !accept?.checked;
      if (blocked) show(blocked);
    }
    updateHomeNote();
    updateEscalate();
    if (current !== total) clearMessage();
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
    if (current === 3) {
      for (const field of [document.getElementById('province'), document.getElementById('city')]) {
        if (field && (!String(field.value || '').trim() || !field.checkValidity())) {
          field.reportValidity();
          field.focus();
          return false;
        }
      }
      if (selectedService() === 'home_dentistry' && area && !area.disabled && !area.value) {
        show(root.dataset.error);
        area.focus();
        return false;
      }
    }
    if (current === 4 && !document.getElementById('budget')?.value) {
      show(root.dataset.error);
      return false;
    }
    if (current === 7 && (!policy || !accept?.checked)) {
      show(root.dataset.error);
      return false;
    }
    return true;
  };

  nextBtn?.addEventListener('click', () => {
    if (validateStep()) go(current + 1);
  });
  prevBtn?.addEventListener('click', () => go(current - 1));
  form.addEventListener('locationchange', updateHomeNote);
  form.querySelectorAll('input[name="service_type"]').forEach((element) => element.addEventListener('change', updateHomeNote));
  form.querySelectorAll('input[name="priority"]').forEach((element) => element.addEventListener('change', updateEscalate));
  form.querySelectorAll('[data-neighborhood]').forEach((button) => {
    button.addEventListener('click', () => {
      if (area?.disabled) return;
      if (area) area.value = button.getAttribute('data-area') || '';
      form.querySelectorAll('[data-neighborhood]').forEach((element) => element.classList.toggle('is-on', element === button));
      updateHomeNote();
    });
  });

  fetch('/api/v1/policies/case_coordination', {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json', 'X-Locale': locale },
  }).then(async (response) => {
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || !payload.data) throw new Error(errorText(payload));
    policy = payload.data;
    if (consentText) consentText.textContent = policy.content;
    if (accept) accept.disabled = false;
  }).catch(() => {
    if (consentText) consentText.textContent = root.dataset.consentUnavailable;
    if (accept) accept.disabled = true;
    if (submit) submit.disabled = true;
  });

  accept?.addEventListener('change', () => {
    if (current === total && submit) submit.disabled = busy || Boolean(blockedReason()) || !accept.checked || !policy;
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (current !== total) {
      if (validateStep()) go(current + 1);
      return;
    }
    const blocked = blockedReason();
    if (blocked) {
      show(blocked);
      return;
    }
    if (!policy || !accept?.checked || busy) return;

    busy = true;
    submit.disabled = true;
    submit.textContent = root.dataset.working;
    clearMessage();

    try {
      if (!savedDraft) {
        const body = {
          service_type: selectedService(),
          name: document.getElementById('name')?.value?.trim() || null,
          tehran_area: area?.disabled ? null : (area?.value || null),
          province: document.getElementById('province')?.value || null,
          city: document.getElementById('city')?.value?.trim() || null,
          neighborhood: document.getElementById('neighborhood')?.value?.trim() || null,
          address: document.getElementById('address')?.value?.trim() || null,
          latitude: form.querySelector('[data-latitude]')?.value || null,
          longitude: form.querySelector('[data-longitude]')?.value || null,
          location_consent: form.querySelector('[data-location-consent]')?.checked === true,
          preferred_contact_time: document.getElementById('contact_time')?.value || 'any',
          contact_reason: document.getElementById('reason')?.value?.trim() || null,
          budget_band: document.getElementById('budget')?.value,
          budget_input_unit: 'toman',
          source_language: locale,
          priority: selectedPriority(),
        };
        draftKey ||= key();
        const draftResponse = await fetch('/api/v1/cases/draft', {
          method: 'POST',
          credentials: 'same-origin',
          headers: headers(draftKey),
          body: JSON.stringify(body),
        });
        const draftPayload = await draftResponse.json().catch(() => ({}));
        if (!draftResponse.ok || !draftPayload.data) {
          if (draftResponse.status === 422) draftKey = null;
          throw new Error(errorText(draftPayload));
        }
        savedDraft = draftPayload.data;
        form.querySelectorAll('input, select, textarea').forEach((control) => { control.disabled = true; });
        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
      }

      submitKey ||= key();
      const submitResponse = await fetch(`/api/v1/cases/${encodeURIComponent(savedDraft.id)}/submit`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers(submitKey),
        body: JSON.stringify({
          version: savedDraft.version,
          policy_version: policy.version,
          content_hash: policy.content_hash,
        }),
      });
      const submitPayload = await submitResponse.json().catch(() => ({}));
      if (!submitResponse.ok || !submitPayload.data) throw new Error(errorText(submitPayload));

      show(root.dataset.success, 'success');
      window.location.assign(`/${encodeURIComponent(locale)}/panel/cases/${encodeURIComponent(savedDraft.id)}`);
    } catch (error) {
      show(error?.message || root.dataset.error);
      submit.disabled = false;
      submit.textContent = root.dataset.submit;
    } finally {
      busy = false;
    }
  });

  go(1);
})();
