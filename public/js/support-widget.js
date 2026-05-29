(function () {
    const API_BASE = (window.CP_SUPPORT_API_BASE || '/api/v1').replace(/\/$/, '');
    const STORAGE_KEY = 'cp_support_public_token';
    const INTAKE_STORAGE_KEY = 'cp_support_intake_token';
    const POLL_MS = window.CP_SUPPORT_POLL_MS || 4000;

    const STEP_PAYMENT_ISSUE = 'payment_issue';
    const STEP_DESTINATION_ACCOUNT = 'destination_account';
    const STEP_SESSION_ID = 'session_id';
    const STEP_NAME = 'name';
    const STEP_AMOUNT = 'amount';
    const STEP_BANK_FROM = 'bank_from';
    const STEP_RECEIPT = 'receipt';
    const STEP_CONTACT_MODE = 'contact_mode';
    const STEP_PHONE = 'phone';
    const STEP_DONE = 'done';

    let publicToken = '';
    let intakeToken = '';
    let intakeState = null;
    let lastMessageId = 0;
    let pollTimer = null;
    let supportOptions = null;

    let launcher = null;
    let panel = null;
    let closeBtn = null;
    let body = null;
    let footer = null;
    let composer = null;
    let sendBtn = null;

    function bindDom() {
        const root = document.getElementById('cp-support-widget-root');
        if (!root) {
            return false;
        }
        launcher = document.getElementById('cp-support-launcher');
        panel = document.getElementById('cp-support-panel');
        closeBtn = document.getElementById('cp-support-close');
        body = document.getElementById('cp-support-body');
        footer = document.getElementById('cp-support-footer');
        composer = document.getElementById('cp-support-composer');
        sendBtn = document.getElementById('cp-support-send');
        return Boolean(launcher && panel && body && closeBtn);
    }

    async function loadOptions() {
        if (supportOptions) {
            return supportOptions;
        }
        const data = await api('/public/support/options');
        supportOptions = data.data;
        return supportOptions;
    }

    function openPanel() {
        if (!panel) {
            return;
        }
        panel.classList.add('cp-open');
        if (publicToken) {
            showChat();
            pollMessages(true);
            startPoll();
        } else if (intakeToken) {
            resumeIntake();
        } else {
            loadOptions()
                .then(function () {
                    return startIntake();
                })
                .catch(function (e) {
                    if (body) {
                        body.innerHTML =
                            '<p class="cp-onboard-error">' + escapeHtml(e.message || 'Could not load support') + '</p>';
                    }
                });
        }
    }

    function closePanel() {
        if (!panel) {
            return;
        }
        panel.classList.remove('cp-open');
        stopPoll();
    }

    function togglePanel() {
        if (!panel) {
            return;
        }
        if (panel.classList.contains('cp-open')) {
            closePanel();
        } else {
            openPanel();
        }
    }

    function stopPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function startPoll() {
        stopPoll();
        if (!publicToken) {
            return;
        }
        pollTimer = setInterval(function () {
            pollMessages(false);
        }, POLL_MS);
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function renderIntakeMessages(messages) {
        if (!body) {
            return;
        }
        let html = '<div class="cp-intake-chat">';
        (messages || []).forEach(function (m, idx) {
            const role = m.role === 'user' ? 'cp-msg-visitor' : 'cp-msg-bot';
            html +=
                '<div class="cp-msg ' +
                role +
                '" data-intake-idx="' +
                idx +
                '">' +
                escapeHtml(m.body || '') +
                '</div>';
        });
        html += '</div><div id="cp-intake-actions"></div><p id="cp-intake-error" class="cp-onboard-error" style="display:none"></p>';
        body.innerHTML = html;
        body.scrollTop = body.scrollHeight;
    }

    function renderIntakeActions() {
        const actions = document.getElementById('cp-intake-actions');
        if (!actions || !intakeState) {
            return;
        }
        actions.innerHTML = '';

        if (intakeState.is_terminal) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cp-btn-primary';
            btn.textContent = 'Close';
            btn.addEventListener('click', closePanel);
            actions.appendChild(btn);
            return;
        }

        const step = intakeState.current_step;

        if (step === STEP_PAYMENT_ISSUE) {
            actions.appendChild(actionButton('Yes — bank transfer issue', function () {
                advanceIntake(STEP_PAYMENT_ISSUE, true);
            }));
            actions.appendChild(actionButton('No — something else', function () {
                advanceIntake(STEP_PAYMENT_ISSUE, false);
            }));
            return;
        }

        if (
            step === STEP_DESTINATION_ACCOUNT ||
            step === STEP_SESSION_ID ||
            step === STEP_NAME ||
            step === STEP_BANK_FROM
        ) {
            actions.appendChild(textInputAction(step, getPlaceholder(step)));
            return;
        }

        if (step === STEP_AMOUNT) {
            actions.appendChild(numberInputAction());
            return;
        }

        if (step === STEP_RECEIPT) {
            actions.appendChild(receiptUploadAction());
            actions.appendChild(actionButton('Skip receipt', function () {
                advanceIntake(STEP_RECEIPT, 'skip');
            }));
            return;
        }

        if (step === STEP_CONTACT_MODE) {
            const modes = intakeState.allowed_contact_modes || ['browser'];
            if (modes.indexOf('browser') !== -1) {
                actions.appendChild(actionButton('Continue in this chat', function () {
                    advanceIntake(STEP_CONTACT_MODE, 'browser');
                }));
            }
            if (modes.indexOf('whatsapp') !== -1) {
                actions.appendChild(actionButton('Link WhatsApp (verified)', function () {
                    advanceIntake(STEP_CONTACT_MODE, 'whatsapp');
                }));
            } else {
                const hint = document.createElement('p');
                hint.className = 'text-xs text-gray-500 mt-2';
                hint.textContent =
                    'WhatsApp is available after we confirm your bank session ID matches the account you paid to.';
                actions.appendChild(hint);
            }
            return;
        }

        if (step === STEP_PHONE) {
            actions.appendChild(phoneAction());
            return;
        }
    }

    function getPlaceholder(step) {
        const map = {
            destination_account: 'Account number you paid TO',
            session_id: 'Bank session ID from receipt',
            name: 'Your name',
            bank_from: 'Bank you sent from',
        };
        return map[step] || '';
    }

    function actionButton(label, onClick) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cp-btn-primary cp-btn-block';
        btn.textContent = label;
        btn.addEventListener('click', onClick);
        return btn;
    }

    function textInputAction(step, placeholder) {
        const wrap = document.createElement('div');
        wrap.className = 'cp-intake-input-row';
        const input = document.createElement('input');
        input.type = 'text';
        input.placeholder = placeholder;
        input.className = 'cp-intake-input';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cp-btn-primary';
        btn.textContent = 'Send';
        btn.addEventListener('click', function () {
            const v = input.value.trim();
            if (!v) {
                return;
            }
            advanceIntake(step, v);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                btn.click();
            }
        });
        wrap.appendChild(input);
        wrap.appendChild(btn);
        return wrap;
    }

    function numberInputAction() {
        const wrap = document.createElement('div');
        wrap.className = 'cp-intake-input-row';
        const input = document.createElement('input');
        input.type = 'number';
        input.min = '1';
        input.step = '0.01';
        input.placeholder = 'Amount in ₦';
        input.className = 'cp-intake-input';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cp-btn-primary';
        btn.textContent = 'Send';
        btn.addEventListener('click', function () {
            advanceIntake(STEP_AMOUNT, input.value);
        });
        wrap.appendChild(input);
        wrap.appendChild(btn);
        return wrap;
    }

    function receiptUploadAction() {
        const wrap = document.createElement('div');
        wrap.className = 'cp-intake-input-row';
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*,application/pdf';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cp-btn-primary';
        btn.textContent = 'Upload receipt';
        btn.addEventListener('click', function () {
            if (!input.files || !input.files[0]) {
                showIntakeError('Choose a file first.');
                return;
            }
            uploadReceipt(input.files[0]);
        });
        wrap.appendChild(input);
        wrap.appendChild(btn);
        return wrap;
    }

    function phoneAction() {
        const wrap = document.createElement('div');
        wrap.className = 'cp-intake-phone-block';
        const suggested = (supportOptions && supportOptions.suggested_country) || 'NG';
        const countries = (supportOptions && supportOptions.countries) || [];

        let countryHtml = '<select id="cp-intake-country" class="cp-country-select">';
        countries.forEach(function (c) {
            const sel = c.iso === suggested ? ' selected' : '';
            countryHtml += '<option value="' + c.iso + '"' + sel + '>' + escapeHtml(c.label) + '</option>';
        });
        countryHtml += '</select>';

        wrap.innerHTML =
            countryHtml +
            '<input type="tel" id="cp-intake-phone" class="cp-intake-input" placeholder="WhatsApp number">' +
            '<label class="cp-intake-consent"><input type="checkbox" id="cp-intake-wallet-consent"> I agree to link WhatsApp after my payment details are verified</label>';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cp-btn-primary cp-btn-block';
        btn.textContent = 'Start chat';
        btn.addEventListener('click', function () {
            const phone = document.getElementById('cp-intake-phone');
            const country = document.getElementById('cp-intake-country');
            const consent = document.getElementById('cp-intake-wallet-consent');
            if (!consent || !consent.checked) {
                showIntakeError('Please accept the WhatsApp terms.');
                return;
            }
            advanceIntake(STEP_PHONE, {
                phone: phone ? phone.value.trim() : '',
                country_iso: country ? country.value : '',
            });
        });
        wrap.appendChild(btn);
        return wrap;
    }

    function showIntakeError(msg) {
        const el = document.getElementById('cp-intake-error');
        if (el) {
            el.textContent = msg;
            el.style.display = 'block';
        }
    }

    function applyIntakePayload(data) {
        intakeState = data;
        intakeToken = data.intake_token || intakeToken;
        try {
            localStorage.setItem(INTAKE_STORAGE_KEY, intakeToken);
        } catch (e) {
            /* ignore */
        }
        renderIntakeMessages(data.messages || []);
        renderIntakeActions();

        if (data.public_token) {
            finishToTicket(data.public_token);
        }
    }

    async function startIntake() {
        if (footer) {
            footer.style.display = 'none';
        }
        const data = await api('/public/support/intake/start', {
            method: 'POST',
            body: JSON.stringify({ channel: 'checkout_web' }),
        });
        intakeToken = data.data.intake_token;
        applyIntakePayload(data.data);
    }

    async function resumeIntake() {
        if (footer) {
            footer.style.display = 'none';
        }
        try {
            const data = await api('/public/support/intake/' + encodeURIComponent(intakeToken));
            if (data.data.public_token) {
                finishToTicket(data.data.public_token);
                return;
            }
            applyIntakePayload(data.data);
        } catch (e) {
            intakeToken = '';
            localStorage.removeItem(INTAKE_STORAGE_KEY);
            await startIntake();
        }
    }

    async function advanceIntake(step, value) {
        const errEl = document.getElementById('cp-intake-error');
        if (errEl) {
            errEl.style.display = 'none';
        }
        try {
            const data = await api('/public/support/intake/' + encodeURIComponent(intakeToken) + '/advance', {
                method: 'POST',
                body: JSON.stringify({ step: step, value: value }),
            });
            applyIntakePayload(data.data);
        } catch (e) {
            showIntakeError(e.message || 'Could not continue');
        }
    }

    async function uploadReceipt(file) {
        const form = new FormData();
        form.append('receipt', file);
        try {
            const res = await fetch(
                API_BASE + '/public/support/intake/' + encodeURIComponent(intakeToken) + '/receipt',
                {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: form,
                }
            );
            const data = await res.json().catch(function () {
                return {};
            });
            if (!res.ok) {
                throw new Error(data.message || 'Upload failed');
            }
            applyIntakePayload(data.data);
        } catch (e) {
            showIntakeError(e.message || 'Upload failed');
        }
    }

    async function completeIntake() {
        const data = await api('/public/support/intake/' + encodeURIComponent(intakeToken) + '/complete', {
            method: 'POST',
            body: JSON.stringify({ consent_accepted: true }),
        });
        if (data.data.public_token) {
            finishToTicket(data.data.public_token);
        }
    }

    function finishToTicket(token) {
        publicToken = token;
        intakeToken = '';
        try {
            localStorage.setItem(STORAGE_KEY, publicToken);
            localStorage.removeItem(INTAKE_STORAGE_KEY);
        } catch (e) {
            /* ignore */
        }
        lastMessageId = 0;
        if (body) {
            body.innerHTML = '';
        }
        showChat();
        pollMessages(true);
        startPoll();
    }

    function showChat() {
        if (!footer) {
            return;
        }
        footer.style.display = 'flex';
        if (body && !body.querySelector('.cp-msg')) {
            body.innerHTML = '<p class="text-xs text-gray-500 text-center">Loading conversation…</p>';
        }
    }

    function appendMessage(msg) {
        if (!body) {
            return;
        }
        const existing = body.querySelector('[data-id="' + msg.id + '"]');
        if (existing) {
            return;
        }
        const loading = body.querySelector('.text-gray-500.text-center');
        if (loading) {
            loading.remove();
        }

        const el = document.createElement('div');
        let className = 'cp-msg-staff';
        if (msg.user_type === 'visitor') {
            className = 'cp-msg-visitor';
        } else if (msg.user_type === 'bot') {
            className = 'cp-msg-bot';
        }
        el.className = 'cp-msg ' + className;
        el.dataset.id = String(msg.id);
        el.textContent = msg.message;
        body.appendChild(el);
        body.scrollTop = body.scrollHeight;
        if (msg.id > 0) {
            lastMessageId = Math.max(lastMessageId, msg.id);
        }
    }

    async function api(path, options) {
        const res = await fetch(API_BASE + path, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(options && options.headers),
            },
            ...options,
        });
        const data = await res.json().catch(function () {
            return {};
        });
        if (!res.ok) {
            throw new Error(data.message || 'Request failed');
        }
        return data;
    }

    async function pollMessages(reset) {
        if (!publicToken) {
            return;
        }
        try {
            const q = reset ? '' : '?after_id=' + lastMessageId;
            const data = await api(
                '/public/support/conversations/' + encodeURIComponent(publicToken) + '/messages' + q
            );
            (data.data.messages || []).forEach(function (m) {
                appendMessage(m);
            });
        } catch (e) {
            if (e.message && e.message.indexOf('not found') !== -1) {
                localStorage.removeItem(STORAGE_KEY);
                publicToken = '';
                startIntake();
                stopPoll();
            }
        }
    }

    async function sendMessage() {
        if (!composer || !sendBtn) {
            return;
        }
        const text = composer.value.trim();
        if (!text || !publicToken) {
            return;
        }
        sendBtn.disabled = true;
        try {
            const data = await api(
                '/public/support/conversations/' + encodeURIComponent(publicToken) + '/messages',
                {
                    method: 'POST',
                    body: JSON.stringify({ message: text }),
                }
            );
            appendMessage(data.data.message);
            composer.value = '';
        } catch (e) {
            alert(e.message || 'Send failed');
        } finally {
            sendBtn.disabled = false;
        }
    }

    function wireEvents() {
        if (launcher) {
            launcher.addEventListener('click', function (e) {
                e.preventDefault();
                togglePanel();
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                closePanel();
            });
        }
        if (sendBtn) {
            sendBtn.addEventListener('click', sendMessage);
        }
        if (composer) {
            composer.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }

        document.querySelectorAll('[data-cp-support-open]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openPanel();
            });
        });
    }

    function init() {
        try {
            publicToken = localStorage.getItem(STORAGE_KEY) || '';
            intakeToken = localStorage.getItem(INTAKE_STORAGE_KEY) || '';
        } catch (e) {
            publicToken = '';
            intakeToken = '';
        }

        if (!bindDom()) {
            return;
        }
        wireEvents();
    }

    window.CpSupport = {
        open: openPanel,
        close: closePanel,
        toggle: togglePanel,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
