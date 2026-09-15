@extends('panel.layout')
@section('title', __('panel.nav.support'))
@section('heading', __('panel.nav.support'))
@section('actions')
    @if(!$isDemo && $panelKey === 'patient')
        <a class="btn primary" href="#new-thread">{{ __('panel.support.new') }}</a>
    @endif
@endsection
@section('content')
<form class="filters" method="get">
    <div class="field">
        <label for="status">{{ __('panel.table.status') }}</label>
        <select id="status" name="status">
            <option value="">{{ __('panel.support.all_status') }}</option>
            @foreach(['open','in_progress','awaiting_patient','resolved','closed','reopened'] as $st)
                <option value="{{ $st }}" @selected($filterStatus === $st)>{{ __('panel.support.status.'.$st) }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn" type="submit">{{ __('panel.filter_apply') }}</button>
</form>
<section class="card">
    <div class="card-head"><span>{{ __('panel.nav.support') }}</span><span class="muted">{{ $conversations->total() }}</span></div>
    @if($conversations->isEmpty())
        <div class="empty">{{ __('panel.support.empty') }}</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('panel.support.subject') }}</th>
                        <th scope="col">{{ __('panel.support.category') }}</th>
                        <th scope="col">{{ __('panel.table.status') }}</th>
                        <th scope="col">{{ __('panel.table.updated') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($conversations as $c)
                        @php $status = $c->status instanceof \BackedEnum ? $c->status->value : $c->status; $cat = $c->category instanceof \BackedEnum ? $c->category->value : $c->category; @endphp
                        <tr>
                            <td>
                                <a class="case-link" href="{{ route('panel.support.show', ['locale' => $locale, 'conversation' => $c->id]) }}">
                                    {{ $c->subject ?: __('panel.support.untitled') }}
                                </a>
                                @if($c->case)
                                    <div class="muted"><bdi>{{ $c->case->public_reference }}</bdi></div>
                                @endif
                            </td>
                            <td>{{ __('panel.support.category_values.'.$cat) }}</td>
                            <td><span class="badge {{ $status }}">{{ __('panel.support.status.'.$status) }}</span></td>
                            <td><bdi>{{ $c->opened_at }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $conversations->links() }}</div>
    @endif
</section>
@if(!$isDemo && $panelKey === 'patient')
    <section class="card pad" id="new-thread">
        <h2>{{ __('panel.support.new') }}</h2>
        <form method="post" action="{{ route('panel.support.store', ['locale' => $locale]) }}">
            @csrf
            <div class="field">
                <label for="subject">{{ __('panel.support.subject') }}</label>
                <input id="subject" name="subject" maxlength="200">
            </div>
            <div class="field">
                <label for="category">{{ __('panel.support.category') }}</label>
                <select id="category" name="category" required>
                    @foreach(['general','coordination','referral','technical'] as $cat)
                        <option value="{{ $cat }}">{{ __('panel.support.category_values.'.$cat) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="message">{{ __('panel.support.message') }}</label>
                <textarea id="message" name="message" required maxlength="5000"></textarea>
            </div>
            <button class="btn primary" type="submit">{{ __('panel.support.send') }}</button>
        </form>
    </section>
@elseif($isDemo && $panelKey === 'patient')
    <p class="notice">{{ __('panel.demo_mutations_disabled') }}</p>
@endif
@endsection
