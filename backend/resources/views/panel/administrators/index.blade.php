@extends('panel.layout')
@section('title', __('administrators.title'))
@section('heading', __('administrators.title'))
@push('scripts')
    <link rel="stylesheet" href="/assets/administrator-workspace.css?v=20261003">
@endpush

@section('content')
@php
    // This client-supplied hint only associates validation feedback with its form.
    // It never chooses an account, authorises a mutation or widens backend access.
    $failedEditId = null;
    foreach ($staff as $staffRow) {
        if (!$staffRow['demo'] && !$staffRow['current'] && old('_staff_form') === 'edit-'.$staffRow['id']) {
            $failedEditId = $staffRow['id'];
            break;
        }
    }
    $creatingErrors = $failedEditId === null;
    $errorFieldIds = $creatingErrors
        ? ['name' => 'staff-name', 'mobile' => 'staff-mobile', 'role' => 'staff-role', 'locale' => 'staff-locale']
        : ['name' => 'staff-'.$failedEditId.'-name', 'role' => 'staff-'.$failedEditId.'-role', 'locale' => 'staff-'.$failedEditId.'-locale', 'is_active' => 'staff-'.$failedEditId.'-active'];
@endphp
<div class="administrator-workspace">
    <section class="hero-panel administrator-hero">
        <h2>{{ __('administrators.title') }}</h2>
        <p>{{ __('administrators.intro') }}</p>
        <nav class="administrator-section-links" aria-label="{{ __('administrators.sections') }}">
            <a href="#administrator-add">{{ __('administrators.add_title') }}</a>
            <a href="#administrator-staff">{{ __('administrators.list_title') }}</a>
        </nav>
    </section>

    <aside class="notice administrator-reauthentication" aria-labelledby="administrator-reauthentication-title">
        <h2 id="administrator-reauthentication-title">{{ __('administrators.reauthentication_title') }}</h2>
        <p>{{ __('panel.security.reauthenticate') }}</p>
        <p>{{ __('administrators.reauthentication_help') }}</p>
    </aside>

    @if($errors->any())
        <section class="notice error administrator-errors" id="administrator-validation-errors" role="alert" tabindex="-1">
            <h2>{{ __('administrators.validation_failed') }}</h2>
            <ul>
                @foreach($errors->messages() as $field => $messages)
                    @foreach($messages as $message)
                        <li>@if(isset($errorFieldIds[$field]))<a href="#{{ $errorFieldIds[$field] }}">{{ $message }}</a>@else{{ $message }}@endif</li>
                    @endforeach
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card pad administrator-section" id="administrator-add" aria-labelledby="administrator-add-title">
        <h2 id="administrator-add-title">{{ __('administrators.add_title') }}</h2>
        <p class="muted">{{ __('administrators.add_help') }}</p>
        <form method="post" action="{{ route('administrators.store', ['locale' => $locale]) }}" autocomplete="off">
            @csrf
            <input type="hidden" name="_staff_form" value="create">
            <div class="form-grid">
                <div class="field">
                    <label for="staff-name">{{ __('administrators.name') }}</label>
                    <input id="staff-name" name="name" value="{{ $creatingErrors ? old('name') : '' }}" maxlength="80" required @if($creatingErrors && $errors->has('name')) aria-invalid="true" aria-describedby="staff-name-error" @endif>
                    @if($creatingErrors) @error('name')<p class="administrator-field-error" id="staff-name-error">{{ $message }}</p>@enderror @endif
                </div>
                <div class="field">
                    <label for="staff-mobile">{{ __('administrators.mobile') }}</label>
                    <input id="staff-mobile" name="mobile" value="{{ $creatingErrors ? old('mobile') : '' }}" dir="ltr" inputmode="tel" placeholder="09xxxxxxxxx" maxlength="32" required @if($creatingErrors && $errors->has('mobile')) aria-invalid="true" aria-describedby="staff-mobile-error" @endif>
                    @if($creatingErrors) @error('mobile')<p class="administrator-field-error" id="staff-mobile-error">{{ $message }}</p>@enderror @endif
                </div>
                <div class="field">
                    <label for="staff-role">{{ __('administrators.role') }}</label>
                    <select id="staff-role" name="role" required @if($creatingErrors && $errors->has('role')) aria-invalid="true" aria-describedby="staff-role-error" @endif>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" @selected($creatingErrors && old('role') === $role->value)>{{ __('administrators.roles.'.$role->value) }}</option>
                        @endforeach
                    </select>
                    @if($creatingErrors) @error('role')<p class="administrator-field-error" id="staff-role-error">{{ $message }}</p>@enderror @endif
                </div>
                <div class="field">
                    <label for="staff-locale">{{ __('administrators.locale') }}</label>
                    <select id="staff-locale" name="locale" required @if($creatingErrors && $errors->has('locale')) aria-invalid="true" aria-describedby="staff-locale-error" @endif>
                        @foreach(['fa'=>'فارسی','ar'=>'العربية','en'=>'English'] as $code=>$label)
                            <option value="{{ $code }}" @selected(($creatingErrors ? old('locale', 'fa') : 'fa') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($creatingErrors) @error('locale')<p class="administrator-field-error" id="staff-locale-error">{{ $message }}</p>@enderror @endif
                </div>
            </div>
            <button class="btn primary" type="submit">{{ __('administrators.create') }}</button>
        </form>
    </section>

    <section class="card pad administrator-section" id="administrator-staff" aria-labelledby="administrator-list-title">
        <h2 id="administrator-list-title">{{ __('administrators.list_title') }}</h2>
        <div class="task-grid compact administrator-records">
            @forelse($staff as $row)
                @php($rowHasErrors = $failedEditId === $row['id'] && $errors->any())
                <article class="administrator-staff-card">
                    <header class="administrator-staff-header">
                        <h3>{{ $row['name'] }}</h3>
                        <div class="administrator-statuses">
                            @if($row['demo']) <span class="sla-chip">{{ __('administrators.demo') }}</span> @endif
                            @if($row['current']) <span class="sla-chip sla-ok">{{ __('administrators.you') }}</span> @endif
                            <span class="badge">{{ $row['active'] ? __('administrators.active') : __('administrators.inactive') }}</span>
                        </div>
                    </header>
                    <dl class="administrator-staff-facts">
                        <div><dt>{{ __('administrators.mobile') }}</dt><dd><bdi>{{ $row['phone'] }}</bdi></dd></div>
                        <div><dt>{{ __('administrators.role') }}</dt><dd>{{ __('administrators.roles.'.$row['role']) }}</dd></div>
                        <div><dt>{{ __('administrators.authenticator') }}</dt><dd>{{ $row['mfa'] ? __('administrators.authenticator_on') : __('administrators.authenticator_off') }}</dd></div>
                    </dl>

                    @if(!$row['demo'] && !$row['current'])
                        <details class="administrator-controls" id="staff-{{ $row['id'] }}-controls" @if($rowHasErrors) open @endif>
                            <summary>{{ __('administrators.manage') }}<span class="sr-only"> · {{ $row['name'] }}</span></summary>
                            <form method="post" action="{{ route('administrators.update', ['locale'=>$locale,'user'=>$row['id']]) }}" class="administrator-edit-form">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="_staff_form" value="edit-{{ $row['id'] }}">
                                <div class="form-grid">
                                    <div class="field">
                                        <label for="staff-{{ $row['id'] }}-name">{{ __('administrators.name') }}</label>
                                        <input id="staff-{{ $row['id'] }}-name" name="name" value="{{ $rowHasErrors ? old('name', $row['name']) : $row['name'] }}" maxlength="80" required @if($rowHasErrors && $errors->has('name')) aria-invalid="true" aria-describedby="staff-{{ $row['id'] }}-name-error" @endif>
                                        @if($rowHasErrors) @error('name')<p class="administrator-field-error" id="staff-{{ $row['id'] }}-name-error">{{ $message }}</p>@enderror @endif
                                    </div>
                                    <div class="field">
                                        <label for="staff-{{ $row['id'] }}-role">{{ __('administrators.role') }}</label>
                                        <select id="staff-{{ $row['id'] }}-role" name="role" @if($rowHasErrors && $errors->has('role')) aria-invalid="true" aria-describedby="staff-{{ $row['id'] }}-role-error" @endif>
                                            @foreach($roles as $role)
                                                <option value="{{ $role->value }}" @selected(($rowHasErrors ? old('role', $row['role']) : $row['role']) === $role->value)>{{ __('administrators.roles.'.$role->value) }}</option>
                                            @endforeach
                                        </select>
                                        @if($rowHasErrors) @error('role')<p class="administrator-field-error" id="staff-{{ $row['id'] }}-role-error">{{ $message }}</p>@enderror @endif
                                    </div>
                                    <div class="field">
                                        <label for="staff-{{ $row['id'] }}-locale">{{ __('administrators.locale') }}</label>
                                        <select id="staff-{{ $row['id'] }}-locale" name="locale" @if($rowHasErrors && $errors->has('locale')) aria-invalid="true" aria-describedby="staff-{{ $row['id'] }}-locale-error" @endif>
                                            @foreach(['fa'=>'فارسی','ar'=>'العربية','en'=>'English'] as $code=>$label)
                                                <option value="{{ $code }}" @selected(($rowHasErrors ? old('locale', $row['locale']) : $row['locale']) === $code)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @if($rowHasErrors) @error('locale')<p class="administrator-field-error" id="staff-{{ $row['id'] }}-locale-error">{{ $message }}</p>@enderror @endif
                                    </div>
                                    <div class="field">
                                        <label for="staff-{{ $row['id'] }}-active">{{ __('administrators.status') }}</label>
                                        <select id="staff-{{ $row['id'] }}-active" name="is_active" @if($rowHasErrors && $errors->has('is_active')) aria-invalid="true" aria-describedby="staff-{{ $row['id'] }}-active-error" @endif>
                                            <option value="1" @selected((string) ($rowHasErrors ? old('is_active', (int) $row['active']) : (int) $row['active']) === '1')>{{ __('administrators.active') }}</option>
                                            <option value="0" @selected((string) ($rowHasErrors ? old('is_active', (int) $row['active']) : (int) $row['active']) === '0')>{{ __('administrators.inactive') }}</option>
                                        </select>
                                        @if($rowHasErrors) @error('is_active')<p class="administrator-field-error" id="staff-{{ $row['id'] }}-active-error">{{ $message }}</p>@enderror @endif
                                    </div>
                                </div>
                                <button class="btn primary" type="submit">{{ __('administrators.save') }}</button>
                            </form>

                            <div class="administrator-security-actions">
                                <form method="post" action="{{ route('administrators.sessions.revoke', ['locale'=>$locale,'user'=>$row['id']]) }}" data-confirm="{{ __('administrators.confirm_revoke_sessions', ['name' => $row['name']]) }}">
                                    @csrf
                                    <button class="btn" type="submit">{{ __('administrators.revoke_sessions') }}</button>
                                </form>
                                <form method="post" action="{{ route('administrators.mfa.reset', ['locale'=>$locale,'user'=>$row['id']]) }}" data-confirm="{{ __('administrators.confirm_mfa_reset') }}">
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
</div>
@endsection
