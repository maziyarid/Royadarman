@props(['chips', 'label'])
<div class="rph98-filters" role="group" aria-label="{{ $label }}">
    @foreach($chips as $chip)
        <button
            class="rph98-filter"
            type="button"
            data-rph98-filter="{{ $chip['id'] }}"
            aria-pressed="{{ $chip['id'] === 'all' ? 'true' : 'false' }}"
        >{{ $chip['label'] }}</button>
    @endforeach
</div>
