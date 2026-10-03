(function () {
    var root = document.querySelector('[data-rph98-calendar]');
    if (!root) return;
    var buttons = root.querySelectorAll('[data-rph98-filter]');
    var filterEmpty = root.querySelector('[data-rph98-filter-empty]');
    function apply(kind) {
        root.querySelectorAll('[data-rph98-event]').forEach(function (el) {
            var show = kind === 'all' || el.getAttribute('data-kind') === kind;
            if (show) el.removeAttribute('hidden');
            else el.setAttribute('hidden', '');
        });
        buttons.forEach(function (btn) {
            btn.setAttribute('aria-pressed', btn.getAttribute('data-rph98-filter') === kind ? 'true' : 'false');
        });
        if (!filterEmpty) return;
        var total = root.querySelectorAll('[data-rph98-agenda] [data-rph98-event]').length;
        var visible = root.querySelectorAll('[data-rph98-agenda] [data-rph98-event]:not([hidden])').length;
        if (total > 0 && visible === 0) filterEmpty.removeAttribute('hidden');
        else filterEmpty.setAttribute('hidden', '');
    }
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            apply(btn.getAttribute('data-rph98-filter'));
        });
    });
})();
