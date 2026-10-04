(() => {
  'use strict';
  const root = document.querySelector('[data-iran-location]');
  if (!root) return;
  const province = root.querySelector('[name="province"]');
  const city = root.querySelector('[name="city"]');
  const suggestions = root.querySelector('datalist');
  const status = root.querySelector('[data-location-status]');
  const latitude = root.querySelector('[data-latitude]');
  const longitude = root.querySelector('[data-longitude]');
  const consent = root.querySelector('[data-location-consent]');
  const gpsButton = root.querySelector('[data-use-location]');
  let catalogue = [];
  const normalized = value => String(value || '').trim().toLowerCase().replaceAll('ي','ی').replaceAll('ك','ک').replace(/[\s‌-]/g, '');
  const clear = () => { latitude.value = ''; longitude.value = ''; consent.checked = false; };
  const update = () => {
    suggestions.replaceChildren();
    const selected = catalogue.find(item => item['province-en'] === province.value);
    (selected?.cities || []).forEach(item => { const option = document.createElement('option'); option.value = item['city-fa']; option.label = item['city-en']; suggestions.append(option); });
    const isTehran = province.value === 'tehran' && ['تهران','tehran'].includes(normalized(city.value));
    const tehran = document.querySelector('[data-tehran-fields]');
    if (tehran) {
      tehran.hidden = !isTehran;
      tehran.querySelectorAll('select,button').forEach(control => { control.disabled = !isTehran; });
      if (!isTehran) { const area = tehran.querySelector('#tehran_area'); if (area) { area.value = ''; area.required = false; } }
    }
    root.dispatchEvent(new CustomEvent('locationchange', { bubbles: true, detail: { isTehran } }));
  };
  province.addEventListener('change', () => { city.value = ''; clear(); update(); city.focus(); });
  city.addEventListener('input', () => { clear(); update(); });
  consent.addEventListener('change', () => { if (!consent.checked) clear(); });
  root.querySelector('[data-clear-location]').addEventListener('click', () => { clear(); status.textContent = root.dataset.cityHelp; });
  gpsButton.addEventListener('click', () => {
    if (!navigator.geolocation) { status.textContent = root.dataset.gpsFailed; return; }
    gpsButton.disabled = true;
    navigator.geolocation.getCurrentPosition(position => {
      gpsButton.disabled = false;
      const lat = position.coords.latitude, lng = position.coords.longitude;
      if (!Number.isFinite(lat) || !Number.isFinite(lng) || lat < 24 || lat > 41 || lng < 43 || lng > 64) { clear(); status.textContent = root.dataset.gpsFailed; return; }
      latitude.value = lat.toFixed(6); longitude.value = lng.toFixed(6); consent.checked = true; status.textContent = root.dataset.gpsSaved;
    }, () => { gpsButton.disabled = false; clear(); status.textContent = root.dataset.gpsFailed; }, { enableHighAccuracy: false, maximumAge: 0, timeout: 10000 });
  });
  fetch(root.dataset.locationsUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' })
    .then(response => { if (!response.ok) throw new Error('Catalogue unavailable'); return response.json(); })
    .then(payload => { if (!Array.isArray(payload.data)) throw new Error('Invalid catalogue'); catalogue = payload.data; update(); status.textContent = root.dataset.loaded; })
    .catch(() => { status.textContent = root.dataset.cityHelp; });
  update();
})();
