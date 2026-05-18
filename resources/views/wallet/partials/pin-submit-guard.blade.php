<style>
    button[type="submit"].is-loading {
        opacity: 0.8;
        cursor: wait;
    }
    button[type="submit"]:disabled {
        opacity: 0.65;
        cursor: not-allowed;
    }
    .btn-spinner {
        display: inline-block;
        width: 1em;
        height: 1em;
        border: 2px solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: wa-pin-spin 0.65s linear infinite;
        vertical-align: -0.15em;
        margin-right: 0.4em;
    }
    @keyframes wa-pin-spin {
        to { transform: rotate(360deg); }
    }
</style>
<script>
(function () {
    document.querySelectorAll('form[data-wa-pin-once]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var btn = form.querySelector('button[type="submit"]');
            if (!btn || btn.disabled) {
                event.preventDefault();
                return;
            }
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.classList.add('is-loading');
            var label = btn.getAttribute('data-loading-label') || 'Please wait…';
            btn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span>' + label;
            // Only disable the submit button. Disabling PIN/password inputs drops them from POST
            // (validation then fails with "field required").
        });
    });
})();
</script>
