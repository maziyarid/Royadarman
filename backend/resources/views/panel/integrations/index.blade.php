@extends('panel.layout')
@section('title', __('integrations.title'))
@section('heading', __('integrations.title'))

@section('content')
<section class="hero-panel">
    <h1>{{ __('integrations.title') }}</h1>
    <p>{{ __('integrations.intro') }}</p>
</section>

@if($errors->any())
    <div class="errors" role="alert">
        <strong>{{ __('integrations.validation_failed') }}</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="notice">
    {{ __('integrations.security_note') }}
</div>

<form method="post" action="{{ route('integrations.update', ['locale' => $locale]) }}" autocomplete="off">
    @csrf
    @method('PUT')

    @php($groups = ['neshan', 'tsms', 'sms', 'email'])
    @if($canEditOperations)
        @php($groups[] = 'operations')
    @endif

    @foreach($groups as $group)
        <section class="card pad">
            <h2>{{ __('integrations.groups.'.$group.'.title') }}</h2>
            <p class="hint">{{ __('integrations.groups.'.$group.'.help') }}</p>

            @foreach($definitions as $key => $definition)
                @continue($definition['group'] !== $group)
                @php($setting = $settings[$key])
                <div class="row">
                    <div class="field">
                        <label for="{{ $key }}">{{ __('integrations.fields.'.$key.'.label') }}</label>

                        @if($definition['type'] === 'provider')
                            <select id="{{ $key }}" name="{{ $key }}">
                                <option value="tsms" @selected(old($key, $setting['value']) === 'tsms')>TSMS</option>
                                <option value="http" @selected(old($key, $setting['value']) === 'http')>{{ __('integrations.generic_http') }}</option>
                            </select>
                        @elseif($definition['type'] === 'boolean')
                            <input type="hidden" name="{{ $key }}" value="0">
                            <label>
                                <input id="{{ $key }}" type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $setting['value']) === '1')>
                                {{ __('integrations.fields.'.$key.'.enabled') }}
                            </label>
                        @else
                            <input
                                id="{{ $key }}"
                                name="{{ $key }}"
                                type="{{ $definition['secret'] ? 'password' : ($definition['type'] === 'url' ? 'url' : ($definition['type'] === 'integer' ? 'number' : 'text')) }}"
                                value="{{ $definition['secret'] ? '' : old($key, $setting['value']) }}"
                                placeholder="{{ $definition['secret'] && $setting['configured'] ? __('integrations.secret_placeholder') : '' }}"
                                @if($definition['secret']) autocomplete="new-password" @endif
                                @if($definition['type'] === 'integer') min="1" step="1" @endif
                                @if($key === 'retention_document_days') max="3650" @endif
                                @if($key === 'referral_grant_ttl_minutes') max="43200" @endif
                                dir="ltr"
                            >
                        @endif

                        <p class="hint">{{ __('integrations.fields.'.$key.'.help') }}</p>
                        <p class="meta">
                            {{ $setting['configured'] ? __('integrations.configured') : __('integrations.not_configured') }}
                            · {{ __('integrations.source.'.$setting['source']) }}
                        </p>

                        @if($setting['source'] === 'database')
                            <label class="hint">
                                <input type="checkbox" name="clear[]" value="{{ $key }}">
                                {{ __('integrations.clear_override') }}
                            </label>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endforeach

    <section class="card pad">
        <button class="btn primary" type="submit">{{ __('integrations.save') }}</button>
    </section>
</form>
@endsection
