(function () {
    const API_BASE = (window.CP_SUPPORT_API_BASE || '/api/v1').replace(/\/$/, '');
    const STORAGE_KEY = 'cp_support_public_token';
    const INTAKE_STORAGE_KEY = 'cp_support_intake_token';
    const POLL_MS = window.CP_SUPPORT_POLL_MS || 4000;

    const STEP_PAYMENT_ISSUE = 'payment_issue';
    const STEP_PAYEE_BANK = 'payee_bank';
    const STEP_DESTINATION_ACCOUNT = 'destination_account';
    const STEP_SESSION_ID = 'session_id';
    const STEP_SESSION_ID_FOR_CHAT = 'session_id_for_chat';
    const STEP_MONIEPOINT_CHARGES = 'moniepoint_charges';
    const STEP_MONIEPOINT_AMOUNT_FIX = 'moniepoint_amount_fix';
    const STEP_NAME = 'name';
    const STEP_AMOUNT = 'amount';
    const STEP_MATCH_AMOUNT_VERIFY = 'match_amount_verify';
    const STEP_MATCH_AMOUNT_FIX = 'match_amount_fix';
    const STEP_BANK_FROM = 'bank_from';
    const STEP_RECEIPT = 'receipt';
    const STEP_CONTACT_MODE = 'contact_mode';
    const STEP_PHONE = 'phone';
    const STEP_DONE = 'done';
    const STEP_RESTART = 'restart';

    let publicToken = '';
    let intakeToken = '';
    let intakeState = null;
    let lastMessageId = 0;
    let pollTimer = null;
    let supportOptions = null;
    let intakeBusy = false;
    let intakeBusyButton = null;

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

        let chat = body.querySelector('.cp-intake-chat');
        let actions = document.getElementById('cp-intake-actions');
        let errorEl = document.getElementById('cp-intake-error');

        if (!chat || !actions) {
            body.innerHTML =
                '<div class="cp-intake-chat"></div>' +
                '<div id="cp-intake-actions"></div>' +
                '<p id="cp-intake-error" class="cp-onboard-error" style="display:none"></p>';
            chat = body.querySelector('.cp-intake-chat');
            actions = document.getElementById('cp-intake-actions');
            errorEl = document.getElementById('cp-intake-error');
        }

        chat.innerHTML = '';
        (messages || []).forEach(function (m, idx) {
            const role = m.role === 'user' ? 'cp-msg-visitor' : 'cp-msg-bot';
            const div = document.createElement('div');
            div.className = 'cp-msg ' + role;
            div.setAttribute('data-intake-idx', String(idx));
            div.textContent = m.body || '';
            chat.appendChild(div);
        });

        if (errorEl) {
            errorEl.style.display = 'none';
            errorEl.textContent = '';
        }

        body.scrollTop = body.scrollHeight;
    }

    function needsSessionIdForChat() {
        if (!intakeState) {
            return false;
        }
        if (typeof intakeState.needs_session_id_for_chat === 'boolean') {
            return intakeState.needs_session_id_for_chat;
        }

        return intakeState.current_step === STEP_SESSION_ID_FOR_CHAT;
    }

    function appendSessionIdInput(actions, step) {
        actions.appendChild(textInputAction(step || STEP_SESSION_ID_FOR_CHAT, getPlaceholder(step || STEP_SESSION_ID_FOR_CHAT)));
    }

    function showIntakeTyping() {
        if (!body) {
            return;
        }
        hideIntakeTyping();
        const chat = body.querySelector('.cp-intake-chat');
        if (!chat) {
            return;
        }
        const el = document.createElement('div');
        el.className = 'cp-msg cp-msg-bot cp-msg-typing';
        el.id = 'cp-intake-typing';
        el.setAttribute('aria-label', 'CheckoutPay is typing');
        el.innerHTML =
            '<span class="cp-typing-dots"><span></span><span></span><span></span></span>';
        chat.appendChild(el);
        body.scrollTop = body.scrollHeight;
    }

    function hideIntakeTyping() {
        const el = document.getElementById('cp-intake-typing');
        if (el) {
            el.remove();
        }
    }

    function setButtonLoading(btn, loading) {
        if (!btn) {
            return;
        }
        if (loading) {
            if (!btn.dataset.cpOriginalText) {
                btn.dataset.cpOriginalText = btn.textContent;
            }
            btn.disabled = true;
            btn.classList.add('cp-btn-loading');
            btn.setAttribute('aria-busy', 'true');
        } else {
            btn.disabled = false;
            btn.classList.remove('cp-btn-loading');
            btn.removeAttribute('aria-busy');
            if (btn.dataset.cpOriginalText) {
                btn.textContent = btn.dataset.cpOriginalText;
            }
        }
    }

    function setIntakeBusy(busy, triggerBtn) {
        intakeBusy = busy;
        intakeBusyButton = busy ? triggerBtn || null : null;

        const actions = document.getElementById('cp-intake-actions');
        if (actions) {
            actions.classList.toggle('cp-intake-actions-busy', busy);
            actions.querySelectorAll('input, select, textarea, button').forEach(function (el) {
                el.disabled = busy;
                if (!busy) {
                    el.classList.remove('cp-btn-loading');
                    el.removeAttribute('aria-busy');
                }
            });
        }

        if (busy) {
            if (triggerBtn) {
                setButtonLoading(triggerBtn, true);
            }
            showIntakeTyping();
        } else {
            hideIntakeTyping();
            if (triggerBtn) {
                setButtonLoading(triggerBtn, false);
            }
        }
    }

    function renderIntakeActions() {
        const actions = document.getElementById('cp-intake-actions');
        if (!actions || !intakeState) {
            return;
        }
        actions.classList.remove('cp-intake-actions-busy');
        actions.innerHTML = '';

        if (intakeState.is_terminal || intakeState.is_locked) {
            if (intakeState.is_locked && intakeState.locked_until) {
                const lockMsg = document.createElement('p');
                lockMsg.className = 'text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded p-2 mb-2';
                lockMsg.textContent = formatLockoutMessage(intakeState.locked_until);
                actions.appendChild(lockMsg);
            }
            if (intakeState.can_restart) {
                appendRestartButton(actions);
            } else {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'cp-btn-primary';
                btn.textContent = 'Close';
                btn.addEventListener('click', function () {
                    if (intakeState.is_locked) {
                        intakeToken = '';
                        intakeState = null;
                        try {
                            localStorage.removeItem(INTAKE_STORAGE_KEY);
                        } catch (e) {
                            /* ignore */
                        }
                    }
                    closePanel();
                });
                actions.appendChild(btn);
            }
            return;
        }

        const step = intakeState.current_step;

        if (step === STEP_PAYMENT_ISSUE) {
            actions.appendChild(actionButton('Yes — bank transfer issue', function (btn) {
                advanceIntake(STEP_PAYMENT_ISSUE, true, btn);
            }));
            actions.appendChild(actionButton('No — something else', function (btn) {
                advanceIntake(STEP_PAYMENT_ISSUE, false, btn);
            }));
        } else if (step === STEP_PAYEE_BANK) {
            const banks = intakeState.payee_banks || [];
            banks.forEach(function (bank) {
                actions.appendChild(
                    actionButton(bank.label, function (btn) {
                        advanceIntake(STEP_PAYEE_BANK, bank.key, btn);
                    })
                );
            });
        } else if (step === STEP_MONIEPOINT_CHARGES) {
            actions.appendChild(
                actionButton('Yes — I sent the full amount with charges', function (btn) {
                    advanceIntake(STEP_MONIEPOINT_CHARGES, true, btn);
                })
            );
            actions.appendChild(
                actionButton('No — I did not include charges', function (btn) {
                    advanceIntake(STEP_MONIEPOINT_CHARGES, false, btn);
                })
            );
        } else if (step === STEP_MONIEPOINT_AMOUNT_FIX || step === STEP_MATCH_AMOUNT_FIX) {
            actions.appendChild(
                actionButton('I updated the amount on the checkout page', function (btn) {
                    advanceIntake(STEP_RESTART, true, btn);
                })
            );
        } else if (step === STEP_MATCH_AMOUNT_VERIFY) {
            actions.appendChild(
                actionButton('Yes — exact amount with charges', function (btn) {
                    advanceIntake(STEP_MATCH_AMOUNT_VERIFY, true, btn);
                })
            );
            actions.appendChild(
                actionButton('No — wrong amount or missing charges', function (btn) {
                    advanceIntake(STEP_MATCH_AMOUNT_VERIFY, false, btn);
                })
            );
        } else if (step === STEP_SESSION_ID_FOR_CHAT) {
            appendSessionIdInput(actions, step);
        } else if (
            step === STEP_DESTINATION_ACCOUNT ||
            step === STEP_SESSION_ID ||
            step === STEP_NAME
        ) {
            actions.appendChild(textInputAction(step, getPlaceholder(step)));
        } else if (step === STEP_AMOUNT) {
            actions.appendChild(numberInputAction());
        } else if (step === STEP_RECEIPT) {
            actions.appendChild(receiptUploadAction());
            actions.appendChild(actionButton('Skip receipt', function (btn) {
                advanceIntake(STEP_RECEIPT, 'skip', btn);
            }));
        } else if (step === STEP_CONTACT_MODE) {
            const modes = intakeState.allowed_contact_modes || ['browser'];
            const canChat = intakeState.can_continue_chat !== false;
            const showSessionInput = needsSessionIdForChat();
            let sessionInput = null;

            if (showSessionInput) {
                const sessionWrap = textInputAction(STEP_SESSION_ID_FOR_CHAT, getPlaceholder(STEP_SESSION_ID_FOR_CHAT));
                sessionInput = sessionWrap.querySelector('input');
                const sessionSend = sessionWrap.querySelector('button');
                if (sessionSend) {
                    sessionSend.remove();
                }
                actions.appendChild(sessionWrap);
            }

            if (modes.indexOf('browser') !== -1 && canChat) {
                actions.appendChild(actionButton('Continue in this chat', function (btn) {
                    if (sessionInput) {
                        const sid = sessionInput.value.trim();
                        if (!sid) {
                            showIntakeError('Please enter your bank session ID.');
                            return;
                        }
                        advanceIntake(STEP_CONTACT_MODE, { mode: 'browser', session_id: sid }, btn);
                        return;
                    }
                    advanceIntake(STEP_CONTACT_MODE, 'browser', btn);
                }));
            }
            if (modes.indexOf('whatsapp') !== -1) {
                actions.appendChild(actionButton('Link WhatsApp (verified)', function (btn) {
                    advanceIntake(STEP_CONTACT_MODE, 'whatsapp', btn);
                }));
            } else {
                const hint = document.createElement('p');
                hint.className = 'text-xs text-gray-500 mt-2';
                hint.textContent = intakeState.requires_session_id === false
                    ? 'WhatsApp linking is available after we verify your account, name, and amount.'
                    : 'WhatsApp is available after we confirm your bank session ID matches the account you paid to.';
                actions.appendChild(hint);
            }
        } else if (step === STEP_PHONE) {
            actions.appendChild(phoneAction());
        }

        appendRestartButton(actions);
    }

    function getPlaceholder(step) {
        const map = {
            destination_account: 'Account number you paid TO',
            session_id: 'Bank session ID from receipt',
            session_id_for_chat: 'Bank session ID from receipt',
            name: 'Your name (as on bank transfer)',
        };
        return map[step] || '';
    }

    function appendRestartButton(actions) {
        if (!intakeState || !intakeState.can_restart) {
            return;
        }
        actions.appendChild(
            actionButton('Start over', function (btn) {
                advanceIntake(STEP_RESTART, true, btn);
            }, 'cp-btn-secondary cp-btn-block')
        );
    }

    function actionButton(label, onClick, className) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = className || 'cp-btn-primary cp-btn-block';
        btn.textContent = label;
        btn.addEventListener('click', function () {
            if (intakeBusy) {
                return;
            }
            onClick(btn);
        });
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
            if (!v || intakeBusy) {
                return;
            }
            advanceIntake(step, v, btn);
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
            if (intakeBusy) {
                return;
            }
            advanceIntake(STEP_AMOUNT, input.value, btn);
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
            if (intakeBusy) {
                return;
            }
            uploadReceipt(input.files[0], btn);
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
            if (intakeBusy) {
                return;
            }
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
            }, btn);
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

    function formatLockoutMessage(iso) {
        if (!iso) {
            return 'Please wait before trying support again.';
        }
        const until = new Date(iso);
        const mins = Math.max(1, Math.ceil((until.getTime() - Date.now()) / 60000));
        return (
            'Too many account numbers that are not ours were entered. Please wait about ' +
            mins +
            ' minute' +
            (mins === 1 ? '' : 's') +
            ', then open support again.'
        );
    }

    function handleIntakeHttpError(e, data, res) {
        if (res && res.status === 429) {
            setIntakeBusy(false);
            intakeToken = '';
            intakeState = { is_terminal: true, is_locked: true, locked_until: data.locked_until, messages: [] };
            try {
                localStorage.removeItem(INTAKE_STORAGE_KEY);
            } catch (err) {
                /* ignore */
            }
            if (body) {
                body.innerHTML = '';
            }
            renderIntakeMessages([
                { role: 'bot', body: formatLockoutMessage(data.locked_until) || e.message },
            ]);
            renderIntakeActions();
            return true;
        }
        return false;
    }

    function applyIntakePayload(data) {
        setIntakeBusy(false, intakeBusyButton);
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
        try {
            const res = await fetch(API_BASE + '/public/support/intake/start', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ channel: 'checkout_web' }),
            });
            const data = await res.json().catch(function () {
                return {};
            });
            if (!res.ok) {
                if (handleIntakeHttpError(new Error(data.message || 'Request failed'), data, res)) {
                    return;
                }
                throw new Error(data.message || 'Request failed');
            }
            intakeToken = data.data.intake_token;
            applyIntakePayload(data.data);
        } catch (e) {
            showIntakeError(e.message || 'Could not start support');
        }
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

    async function advanceIntake(step, value, triggerBtn) {
        if (intakeBusy) {
            return;
        }
        const errEl = document.getElementById('cp-intake-error');
        if (errEl) {
            errEl.style.display = 'none';
        }
        setIntakeBusy(true, triggerBtn || null);
        try {
            const res = await fetch(
                API_BASE + '/public/support/intake/' + encodeURIComponent(intakeToken) + '/advance',
                {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ step: step, value: value }),
                }
            );
            const data = await res.json().catch(function () {
                return {};
            });
            if (!res.ok) {
                if (handleIntakeHttpError(new Error(data.message || 'Request failed'), data, res)) {
                    setIntakeBusy(false, triggerBtn || null);
                    return;
                }
                throw new Error(data.message || 'Request failed');
            }
            applyIntakePayload(data.data);
        } catch (e) {
            setIntakeBusy(false, triggerBtn || null);
            showIntakeError(e.message || 'Could not continue');
        }
    }

    async function uploadReceipt(file, triggerBtn) {
        if (intakeBusy) {
            return;
        }
        setIntakeBusy(true, triggerBtn || null);
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
            setIntakeBusy(false, triggerBtn || null);
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
        if (!text || !publicToken || sendBtn.classList.contains('cp-btn-loading')) {
            return;
        }
        setButtonLoading(sendBtn, true);
        composer.disabled = true;
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
            setButtonLoading(sendBtn, false);
            composer.disabled = false;
            composer.focus();
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
