@extends('panel.layout')
@section('title', __('panel.nav.profile'))
@section('heading', __('panel.nav.profile'))
@section('content')
<section class="card pad">
    <div class="facts">
        <div class="fact"><small>{{ __('panel.profile.role') }}</small>{{ __('ui.dashboard.roles.'.$panelKey) }}</div>
        <div class="fact"><small>{{ __('panel.profile.locale') }}</small>{{ strtoupper($profileUser->locale) }}</div>
        @if($profileUser->name)
            <div class="fact"><small>{{ __('panel.profile.name') }}</small>{{ $profileUser->name }}</div>
        @endif
    </div>
</section>
@if($isDemo)
    <p class="notice">{{ __('panel.demo_mutations_disabled') }}</p>
@else
    <section class="card pad">
        <h2>{{ __('panel.profile.preferences') }}</h2>
        <form method="post" action="{{ route('panel.profile.update', ['locale' => $locale]) }}">
            @csrf
            @method('PATCH')
            <div class="field">
                <label for="name">{{ __('panel.profile.name') }}</label>
                <input id="name" name="name" maxlength="80" value="{{ old('name', $profileUser->name) }}">
            </div>
            <div class="field">
                <label for="pref-locale">{{ __('panel.profile.locale') }}</label>
                <select id="pref-locale" name="locale" required>
                    @foreach(['fa' => 'فارسی', 'ar' => 'العربية', 'en' => 'English'] as $code => $label)
                        <option value="{{ $code }}" @selected(old('locale', $profileUser->locale) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn primary" type="submit">{{ __('panel.profile.save') }}</button>
        </form>
    </section>

    <section class="card pad" style="margin-top:16px">
        <h2>{{ __('panel.sessions.title') }}</h2>
        <p class="muted" style="margin-top:0">{{ __('panel.sessions.intro') }}</p>
        @if(($sessions ?? collect())->isEmpty())
            <p class="muted">{{ __('panel.sessions.empty') }}</p>
        @else
            <div class="task-grid compact" style="padding:0;margin:12px 0 0">
                @foreach($sessions as $row)
                    <div class="task-card">
                        <div class="task-card-main">
                            <strong>
                                {{ $row['device_label'] }}
                                @if($row['is_current'])
                                    <span class="sla-chip sla-ok">{{ __('panel.sessions.current') }}</span>
                                @endif
                            </strong>
                            <span class="task-meta">
                                {{ $row['ip_address'] ?? '—' }}
                                · {{ __('panel.sessions.last_active') }}:
                                {{ \Illuminate\Support\Carbon::createFromTimestamp($row['last_activity'])->diffForHumans() }}
                            </span>
                        </div>
                        @if(! $row['is_current'])
                            <form method="post" action="{{ route('panel.profile.sessions.revoke', ['locale' => $locale, 'session' => $row['id']]) }}" onsubmit="return confirm(@json(__('panel.sessions.confirm_one')))">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger sm" type="submit">{{ __('panel.sessions.revoke') }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px">
                <form method="post" action="{{ route('panel.profile.sessions.revoke_others', ['locale' => $locale]) }}" onsubmit="return confirm(@json(__('panel.sessions.confirm_others')))">
                    @csrf
                    <button class="btn" type="submit">{{ __('panel.sessions.revoke_others') }}</button>
                </form>
                <form method="post" action="{{ route('panel.profile.sessions.revoke_all', ['locale' => $locale]) }}" onsubmit="return confirm(@json(__('panel.sessions.confirm_all')))">
                    @csrf
                    <button class="btn danger" type="submit">{{ __('panel.sessions.revoke_all') }}</button>
                </form>
            </div>
        @endif
    </section>
@endif
@endsection
