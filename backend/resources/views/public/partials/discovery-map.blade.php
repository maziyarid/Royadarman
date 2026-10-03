@php
    $discovery = $discovery ?? null;
@endphp
@if(!empty($discovery))
    @php
        $copy = trans('site.discovery');
        $selected = $discovery['neighborhood_id'];
        $formAction = $locale === 'fa' ? route('public.referrals.fa') : route('public.referrals', ['locale' => $locale]);
        $enhancementReady = !empty($discovery['vite_ready']);
    @endphp
    <section class="section white" data-discovery-root
             data-endpoint="{{ $discovery['endpoint'] }}"
             data-service-type="{{ $discovery['service_type'] }}"
             data-neighborhood-id="{{ $selected }}"
             data-origin-lat="{{ $discovery['origin']['lat'] }}"
             data-origin-lng="{{ $discovery['origin']['lng'] }}"
             data-tile-url="{{ $discovery['tile_url'] ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png' }}">
        <script type="application/json" data-discovery-copy>{!! json_encode($copy, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
        <div class="shell">
            <div class="section-heading">
                <h2>{{ $copy['title'] }}</h2>
                <p>{{ $copy['lead'] }}</p>
            </div>

            <form class="discovery-filters" method="get" action="{{ $formAction }}" data-discovery-form>
                <label for="discovery-neighborhood">{{ $copy['neighborhood_label'] }}</label>
                <div class="discovery-filters-row">
                    <select id="discovery-neighborhood" name="neighborhood_id" data-discovery-neighborhood>
                        @foreach($discovery['neighborhoods'] as $n)
                            <option value="{{ $n['id'] }}" @selected($n['id'] === $selected)>{{ $n['label'] }}</option>
                        @endforeach
                    </select>
                    <button class="button ghost" type="submit">{{ $copy['submit_neighborhood'] }}</button>
                </div>
            </form>

            <p class="discovery-boundary">{{ $copy['not_available_claim'] }}</p>
            <p class="discovery-privacy">{{ $copy['location_ephemeral'] }}</p>
            <p class="discovery-status" data-discovery-status role="status" aria-live="polite"></p>

            <div class="discovery-layout">
                <div>
                    <div class="discovery-list-head">
                        <h3>{{ $copy['fallback_heading'] }}</h3>
                        <button type="button" class="button ghost" data-discovery-geo hidden>{{ $copy['use_location'] }}</button>
                        <button type="button" class="button ghost" data-discovery-geo-clear hidden>{{ $copy['clear_location'] }}</button>
                    </div>
                    <ul class="discovery-list" data-discovery-list>
                        @forelse($discovery['matches'] as $clinic)
                            <li>
                                <article class="discovery-card" data-clinic-id="{{ $clinic['clinic_id'] }}" data-lat="{{ $clinic['latitude'] }}" data-lng="{{ $clinic['longitude'] }}">
                                    <h3>{{ $clinic['name'] }}</h3>
                                    <p>{{ $clinic['city'] }}@if(!empty($clinic['area_code'])) · {{ $clinic['area_code'] }}@endif</p>
                                    <p>{{ __('site.discovery.distance', ['km' => $clinic['distance_km']]) }}</p>
                                    <p>{{ $copy['not_available_claim'] }}</p>
                                    <div class="discovery-card-actions">
                                        <a class="button primary" data-directions-link href="{{ $clinic['navigation']['neshan'] ?? $clinic['directions_url'] }}" rel="noopener noreferrer" target="_blank">{{ $copy['directions'] }}</a>
                                        <button type="button" class="button ghost" data-select-clinic aria-pressed="false">{{ $copy['select_clinic'] }}</button>
                                    </div>
                                    <div class="discovery-navigation">
                                        <strong class="discovery-navigation-title">{{ $copy['navigation_title'] }}</strong>
                                        <div class="discovery-navigation-links">
                                            <a class="button ghost" href="{{ $clinic['navigation']['neshan'] ?? $clinic['directions_url'] }}" rel="noopener noreferrer" target="_blank">{{ $copy['nav_neshan'] }}</a>
                                            <a class="button ghost" href="{{ $clinic['navigation']['balad'] ?? '#' }}" rel="noopener noreferrer" target="_blank">{{ $copy['nav_balad'] }}</a>
                                            <a class="button ghost" href="{{ $clinic['navigation']['google'] ?? '#' }}" rel="noopener noreferrer" target="_blank">{{ $copy['nav_google'] }}</a>
                                            <a class="button ghost" href="{{ $clinic['navigation']['waze'] ?? '#' }}" rel="noopener noreferrer" target="_blank">{{ $copy['nav_waze'] }}</a>
                                            <a class="button ghost" href="{{ $clinic['navigation']['osm'] ?? '#' }}" rel="noopener noreferrer" target="_blank">{{ $copy['nav_osm'] }}</a>
                                            @if($enhancementReady)
                                                <a class="button ghost" data-copy-destination="{{ $clinic['latitude'] }},{{ $clinic['longitude'] }}" href="https://snapp.ir/" rel="noopener noreferrer" target="_blank">{{ $copy['nav_snapp'] }}</a>
                                                <a class="button ghost" data-copy-destination="{{ $clinic['latitude'] }},{{ $clinic['longitude'] }}" href="https://tapsi.ir/" rel="noopener noreferrer" target="_blank">{{ $copy['nav_tapsi'] }}</a>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            </li>
                        @empty
                            <li class="discovery-empty" data-discovery-empty>{{ $copy['empty'] }}</li>
                        @endforelse
                    </ul>
                    <p class="discovery-note">{{ $copy['insufficient_note'] }}</p>
                </div>
                <div>
                    @if($enhancementReady)
                        <div id="discovery-map" class="discovery-map" data-discovery-map role="region" aria-label="{{ $copy['title'] }}"></div>
                        <p class="discovery-map-fallback" data-discovery-map-fallback hidden role="status">{{ $copy['map_unavailable'] }}</p>
                        <p class="discovery-map-credit">
                            {{ $copy['map_attribution_note'] }}
                            <a href="{{ $discovery['map_attribution_url'] ?? 'https://www.openstreetmap.org/copyright' }}" rel="noopener noreferrer" target="_blank">{{ $discovery['map_attribution'] ?? '© OpenStreetMap contributors' }}</a>
                        </p>
                    @else
                        <p class="discovery-map-fallback">{{ $copy['map_unavailable'] }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>
    @if($enhancementReady)
        @vite(['resources/js/discovery-map.js'])
    @endif
@endif
