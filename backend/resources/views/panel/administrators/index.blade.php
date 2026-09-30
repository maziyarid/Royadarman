@extends('panel.layout')
@section('title', __('administrators.title'))
@section('heading', __('administrators.title'))

@section('content')
<section class="hero-panel">
    <h1>{{ __('administrators.title') }}</h1>
    <p>{{ __('administrators.intro') }}</p>
</section>

@if($errors->any())
    <div class="errors" role="alert">
        <strong>{{ __('administrators.validation_failed') }}</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<section class="card pad">
    <h2>{{ __('administrators.add_title') }}</h2>
    <p class="muted">{{ __('administrators.add_help') }}</p>
    <form method="post" action="{{ route('administrators.store', ['locale' => $locale]) }}" autocomplete="off">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label for="staff-name">{{ __('administrators.name') }}</label>
                <input id="staff-name" name="name" value="{{ old('name') }}" maxlength="80" required>
            </div>
            <div class="field">
                <label for="staff-mobile">{{ __('administrators.mobile') }}</label>
                <input id="staff-mobile" name="mobile" value="{{ old('mobile') }}" dir="ltr" inputmode="tel" placeholder="09xxxxxxxxx" maxlength="32" required>
            </div>
            <div class="field">
                <label for="staff-role">{{ __('administrators.role') }}</label>
                <select id="staff-role" name="role" required>
                    @foreach($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ __('administrators.roles.'.$role->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="staff-locale">{{ __('administrators.locale') }}</label>
                <select id="staff-locale" name="locale" required>
                    @foreach(['fa'=>'فارسی','ar'=>'العربية','en'=>'English'] as $code=>$label)
                        <option value="{{ $code }}" @selected(old('locale', 'fa') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <button class="btn primary" type="submit">{{ __('administrators.create') }}</button>
    </form>
</section>

<section class="card pad" style="margin-top:16px">
    <h2>{{ __('administrators.list_title') }}</h2>
    <div class="task-grid compact" style="padding:0;margin-top:12px">
        @forelse($staff as $row)
            <article class="task-card">
                <div class="task-card-main">
                    <strong>{{ $row['name'] }}
                        @if($row['demo']) <span class="sla-chip">{{ __('administrators.demo') }}</span> @endif
                        @if($row['current']) <span class="sla-chip sla-ok">{{ __('administrators.you') }}</span> @endif
                    </strong>
                    <span class="task-meta">
                        {{ $row['phone'] }} · {{ __('administrators.roles.'.$row['role']) }}
                        · {{ $row['active'] ? __('administrators.active') : __('administrators.inactive') }}
                        · {{ $row['mfa'] ? __('administrators.mfa_on') : __('administrators.mfa_off') }}
                    </span>
                </div>

                @if(!$row['demo'] && !$row['current'])
                    <details style="width:100%">
                        <summary>{{ __('administrators.manage') }}</summary>
                        <form method="post" action="{{ route('administrators.update', ['locale'=>$locale,'user'=>$row['id']]) }}" style="margin-top:12px">
                            @csrf
                            @method('PATCH')
                            <div class="form-grid">
                                <div class="field">
                                    <label>{{ __('administrators.name') }}</label>
                                    <input name="name" value="{{ $row['name'] }}" maxlength="80" required>
                                </div>
                                <div class="field">
                                    <label>{{ __('administrators.role') }}</label>
                                    <select name="role">
                                        @foreach($roles as $role)
                                            <option value="{{ $role->value }}" @selected($row['role']===$role->value)>{{ __('administrators.roles.'.$role->value) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label>{{ __('administrators.locale') }}</label>
                                    <select name="locale">
                                        @foreach(['fa'=>'فارسی','ar'=>'العربية','en'=>'English'] as $code=>$label)
                                            <option value="{{ $code }}" @selected($row['locale']===$code)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label>{{ __('administrators.status') }}</label>
                                    <select name="is_active">
                                        <option value="1" @selected($row['active'])>{{ __('administrators.active') }}</option>
                                        <option value="0" @selected(!$row['active'])>{{ __('administrators.inactive') }}</option>
                                    </select>
                                </div>
                            </div>
                            <button class="btn primary" type="submit">{{ __('administrators.save') }}</button>
                        </form>

                        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:12px">
                            <form method="post" action="{{ route('administrators.sessions.revoke', ['locale'=>$locale,'user'=>$row['id']]) }}">
                                @csrf
                                <button class="btn" type="submit">{{ __('administrators.revoke_sessions') }}</button>
                            </form>
                            <form method="post" action="{{ route('administrators.mfa.reset', ['locale'=>$locale,'user'=>$row['id']]) }}" onsubmit="return confirm(@json(__('administrators.confirm_mfa_reset')))">
                                @csrf
                                <button class="btn danger" type="submit">{{ __('administrators.reset_mfa') }}</button>
                            </form>
                        </div>
                    </details>
                @endif
            </article>
        @empty
            <p class="muted">{{ __('administrators.empty') }}</p>
        @endforelse
    </div>
</section>
@endsection
