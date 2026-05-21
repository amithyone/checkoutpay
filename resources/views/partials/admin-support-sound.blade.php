@if(auth('admin')->check() && auth('admin')->user()->canManageSupportTickets())
<script>
(function () {
    const INBOX_URL = @json(route('admin.support.inbox'));
    const POLL_MS = {{ (int) config('support.poll_interval_seconds', 4) * 1000 }};
    const STORAGE_KEY = 'admin_support_unread_total';

    let lastChimeAt = 0;

    function playSupportChime() {
        const now = Date.now();
        if (now - lastChimeAt < 2000) {
            return;
        }
        lastChimeAt = now;
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            const ctx = new Ctx();
            const playTone = (freq, start, duration) => {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.type = 'sine';
                o.frequency.value = freq;
                o.connect(g);
                g.connect(ctx.destination);
                g.gain.setValueAtTime(0.0001, start);
                g.gain.exponentialRampToValueAtTime(0.12, start + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, start + duration);
                o.start(start);
                o.stop(start + duration + 0.05);
            };
            const t = ctx.currentTime;
            playTone(880, t, 0.12);
            playTone(1174.66, t + 0.14, 0.18);
            setTimeout(() => ctx.close().catch(() => {}), 600);
        } catch (e) {
            /* ignore */
        }
    }

    window.AdminSupportNotify = {
        play: playSupportChime,
    };

    let lastUnread = parseInt(sessionStorage.getItem(STORAGE_KEY) || '0', 10);
    let polling = false;
    let inboxInitialized = false;

    async function pollInbox() {
        if (polling) return;
        polling = true;
        try {
            const res = await fetch(INBOX_URL, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success || !data.data) return;
            const unread = parseInt(data.data.unread_total, 10) || 0;
            if (!inboxInitialized) {
                lastUnread = unread;
                inboxInitialized = true;
                sessionStorage.setItem(STORAGE_KEY, String(unread));
                return;
            }
            if (unread > lastUnread) {
                playSupportChime();
                if (typeof document !== 'undefined' && document.title) {
                    const prefix = unread > lastUnread ? '● ' : '';
                    if (!document.title.startsWith('● ')) {
                        document.title = prefix + document.title.replace(/^● /, '');
                    }
                }
            }
            lastUnread = unread;
            sessionStorage.setItem(STORAGE_KEY, String(unread));
        } catch (e) {
            console.warn('support inbox poll failed', e);
        } finally {
            polling = false;
        }
    }

    if (!window.__adminSupportInboxPoll) {
        window.__adminSupportInboxPoll = setInterval(pollInbox, POLL_MS);
        setTimeout(pollInbox, 1500);
    }
})();
</script>
@endif
