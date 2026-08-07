{{-- Silently renews session + CSRF on long-lived admin / investor pages. --}}
<script>
(function () {
    var url = @json(route('session.keepalive'));
    var everyMs = {{ max(60, (int) config('session.keepalive_interval_seconds', 300)) * 1000 }};

    function applyToken(token) {
        if (!token) return;
        document.querySelectorAll('meta[name="csrf-token"]').forEach(function (el) {
            el.setAttribute('content', token);
        });
        document.querySelectorAll('input[name="_token"]').forEach(function (el) {
            el.value = token;
        });
        if (window.axios && axios.defaults && axios.defaults.headers) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }
    }

    function ping() {
        if (document.hidden) return;
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
        })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (data && data.csrf_token) applyToken(data.csrf_token);
            })
            .catch(function () { /* ignore transient network errors */ });
    }

    setInterval(ping, everyMs);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) ping();
    });
})();
</script>
