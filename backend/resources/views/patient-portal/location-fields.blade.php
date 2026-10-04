@php($locationProfile = $locationProfile ?? null)
<div data-iran-location data-locations-url="{{ route('patient.locations', ['locale' => $locale]) }}" data-locale="{{ $locale }}" data-gps-saved="{{ __('patient_portal.gps_saved') }}" data-gps-failed="{{ __('patient_portal.gps_failed') }}" data-city-help="{{ __('patient_portal.city_help') }}" data-loaded="{{ __('patient_portal.location_loaded') }}">
<div class="patient-location-grid">
<div class="field"><label for="province">{{ __('patient_portal.province') }}</label><select id="province" name="province" required autocomplete="address-level1"><option value="">{{ __('patient_portal.none') }}</option>@foreach($provinces as $code => $name)<option value="{{ $code }}" @selected(old('province', $locationProfile?->province) === $code)>{{ $name }}</option>@endforeach</select></div>
<div class="field"><label for="city">{{ __('patient_portal.city') }}</label><input id="city" name="city" value="{{ old('city', $locationProfile?->city) }}" list="iran-city-suggestions" maxlength="100" required autocomplete="address-level2" aria-describedby="city-help"><datalist id="iran-city-suggestions"></datalist><small id="city-help">{{ __('patient_portal.city_help') }}</small></div>
<div class="field"><label for="neighborhood">{{ __('patient_portal.neighborhood') }}</label><input id="neighborhood" name="neighborhood" value="{{ old('neighborhood', $locationProfile?->neighborhood) }}" maxlength="120" autocomplete="address-level3"></div>
<div class="field"><label for="address">{{ __('patient_portal.address') }}</label><textarea id="address" name="address" rows="2" maxlength="1000" autocomplete="street-address">{{ old('address', $locationProfile?->address) }}</textarea></div>
</div>
<p class="hint">{{ __('patient_portal.coverage') }}</p><p class="hint">{{ __('patient_portal.gps_help') }}</p>
<input type="hidden" name="latitude" data-latitude value="{{ old('latitude') }}"><input type="hidden" name="longitude" data-longitude value="{{ old('longitude') }}">
<div class="actions"><button class="btn" type="button" data-use-location>{{ __('patient_portal.gps') }}</button><button class="btn" type="button" data-clear-location>{{ __('patient_portal.gps_clear') }}</button></div>
<label class="check"><input type="checkbox" name="location_consent" value="1" data-location-consent @checked(old('location_consent'))> <span>{{ __('patient_portal.gps_consent') }}</span></label>
<p role="status" aria-live="polite" data-location-status>{{ __('patient_portal.location_loading') }}</p>
</div>
