@extends('panel.layout')
@section('title', __('panel.policies.title'))
@section('heading', __('panel.policies.title'))

@section('content')
<section class="hero-panel">
    <h1>{{ __('panel.policies.title') }}</h1>
    <p>{{ __('panel.policies.intro') }}</p>
</section>

@if(!$legalApproved)
    <div class="notice">
        <strong>{{ __('panel.policies.legal_gate_title') }}</strong>
        <p>{{ __('panel.policies.legal_gate_help') }}</p>
    </div>
@endif

<section class="card pad">
    <h2>{{ __('panel.policies.new_draft') }}</h2>
    <form method="post" action="{{ route('panel.policies.store', ['locale'=>$locale]) }}">
        @csrf
        <div class="grid grid-3">
            <div class="field">
                <label for="policy-key">{{ __('panel.policies.policy') }}</label>
                <select id="policy-key" name="policy_key" required>
                    @foreach($policyKeys as $key)
                        <option value="{{ $key }}">{{ __('panel.policies.keys.'.$key) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="policy-locale">{{ __('panel.policies.locale') }}</label>
                <select id="policy-locale" name="locale" required>
                    @foreach($policyLocales as $policyLocale)
                        <option value="{{ $policyLocale }}">{{ strtoupper($policyLocale) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="policy-version">{{ __('panel.policies.version') }}</label>
                <input id="policy-version" name="version" maxlength="50" placeholder="2026-09-21-1" required dir="ltr">
            </div>
        </div>
        <div class="field">
            <label for="policy-content">{{ __('panel.policies.content') }}</label>
            <textarea id="policy-content" name="content" required minlength="50" maxlength="50000" rows="12" placeholder="{{ __('panel.policies.content_hint') }}"></textarea>
        </div>
        <button class="btn primary" type="submit">{{ __('panel.policies.create_draft') }}</button>
    </form>
</section>

<section class="policy-matrix">
    @foreach($policyKeys as $key)
        <article class="card pad">
            <div class="heading-row">
                <div>
                    <h2>{{ __('panel.policies.keys.'.$key) }}</h2>
                    <p class="muted"><code>{{ $key }}</code></p>
                </div>
            </div>

            <div class="policy-locale-grid">
                @foreach($policyLocales as $policyLocale)
                    @php
                        $cell = $matrix[$key][$policyLocale];
                        $published = $cell['published'];
                    @endphp
                    <section class="policy-locale-card">
                        <header>
                            <strong>{{ strtoupper($policyLocale) }}</strong>
                            @if($published)
                                <span class="gate-chip ok">{{ __('panel.policies.published') }}</span>
                            @else
                                <span class="gate-chip blocked">{{ __('panel.policies.missing_published') }}</span>
                            @endif
                        </header>

                        @if($published)
                            <div class="policy-version-meta">
                                <span>{{ __('panel.policies.version') }}: <bdi>{{ $published->version }}</bdi></span>
                                <span>{{ __('panel.policies.published_at') }}: <bdi>{{ $published->published_at }}</bdi></span>
                                <span>SHA-256: <code>{{ substr($published->content_hash, 0, 14) }}…</code></span>
                            </div>
                            <details>
                                <summary>{{ __('panel.policies.view_published') }}</summary>
                                <pre class="policy-content-preview">{{ $published->content }}</pre>
                            </details>
                        @endif

                        @forelse($cell['drafts'] as $draft)
                            <details class="policy-draft">
                                <summary>
                                    {{ __('panel.policies.draft') }} · <bdi>{{ $draft->version }}</bdi>
                                </summary>
                                <form method="post" action="{{ route('panel.policies.update', ['locale'=>$locale, 'policy'=>$draft->id]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="policy_key" value="{{ $draft->policy_key }}">
                                    <input type="hidden" name="locale" value="{{ $draft->locale }}">
                                    <div class="field">
                                        <label>{{ __('panel.policies.version') }}</label>
                                        <input name="version" value="{{ $draft->version }}" maxlength="50" required dir="ltr">
                                    </div>
                                    <div class="field">
                                        <label>{{ __('panel.policies.content') }}</label>
                                        <textarea name="content" rows="12" minlength="50" maxlength="50000" required>{{ $draft->content }}</textarea>
                                    </div>
                                    <div class="row-actions">
                                        <button class="btn" type="submit">{{ __('panel.policies.save_draft') }}</button>
                                    </div>
                                </form>

                                <div class="policy-draft-actions">
                                    @if($canPublish)
                                        <form method="post" action="{{ route('panel.policies.publish', ['locale'=>$locale, 'policy'=>$draft->id]) }}">
                                            @csrf
                                            <button class="btn primary" type="submit" @disabled(!$legalApproved) data-confirm="{{ __('panel.policies.publish_confirm') }}">
                                                {{ __('panel.policies.publish') }}
                                            </button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('panel.policies.destroy', ['locale'=>$locale, 'policy'=>$draft->id]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn danger" type="submit" data-confirm="{{ __('panel.policies.delete_confirm') }}">{{ __('panel.policies.delete_draft') }}</button>
                                    </form>
                                </div>
                            </details>
                        @empty
                            <p class="muted">{{ __('panel.policies.no_drafts') }}</p>
                        @endforelse
                    </section>
                @endforeach
            </div>
        </article>
    @endforeach
</section>

<p class="hint">{{ __('panel.policies.immutability_note') }}</p>
@endsection
