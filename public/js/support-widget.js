(function () {
    const API_BASE = (window.CP_SUPPORT_API_BASE || '/api/v1').replace(/\/$/, '');
    const STORAGE_KEY = 'cp_support_public_token';
    const POLL_MS = window.CP_SUPPORT_POLL_MS || 4000;

    let publicToken = '';
    let lastMessageId = 0;
    let pollTimer = null;
    let supportOptions = null;
    let optionsLoaded = false;

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
        if (optionsLoaded && supportOptions) {
            return supportOptions;
        }
        const data = await api('/public/support/options');
        supportOptions = data.data;
        optionsLoaded = true;
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
        } else {
            loadOptions()
                .then(function () {
                    showOnboarding();
                })
                .catch(function (e) {
                    if (body) {
                        body.innerHTML =
                            '<p class="cp-onboard-error">' + (e.message || 'Could not load support options') + '</p>';
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

    function issueTypeSelectHtml() {
        const types = (supportOptions && supportOptions.issue_types) || [];
        const sessionLabel = (supportOptions && supportOptions.payment_session_label) || 'Bank session ID';
        const sessionHint = (supportOptions && supportOptions.payment_session_hint) || '';
        let html = '<label>What do you need help with?</label>';
        html += '<select id="cp-issue-type" class="cp-country-select">';
        types.forEach(function (t, i) {
            const sel = i === 0 ? ' selected' : '';
            html += '<option value="' + t.key + '" data-requires-payment="' + (t.requires_payment ? '1' : '0') + '"' + sel + '>' + t.label + '</option>';
        });
        if (types.length === 0) {
            html += '<option value="general">Other question</option>';
        }
        html += '</select>';
        html +=
            '<div id="cp-payment-block" class="cp-payment-block" style="display:none">' +
            '<p id="cp-issue-hint" class="text-xs text-gray-500 mb-2"></p>' +
            '<label>' + sessionLabel + '</label>' +
            (sessionHint ? '<p class="text-xs text-gray-500 mb-1">' + sessionHint + '</p>' : '') +
            '<input type="text" id="cp-session-id" placeholder="From bank transfer receipt" autocomplete="off">' +
            '<label>Amount you transferred (₦)</label>' +
            '<input type="number" id="cp-amount-paid" min="1" step="0.01" placeholder="5000">' +
            '</div>';
        return html;
    }

    function whatsappStepHtml(suggested) {
        return (
            '<div id="cp-whatsapp-step" class="cp-whatsapp-step">' +
            '<p id="cp-whatsapp-wait-hint" class="cp-whatsapp-wait-hint" style="display:none">' +
            'Enter your bank session ID and amount above, then choose WhatsApp or browser chat below.' +
            '</p>' +
            '<p class="text-xs font-medium text-gray-700 mb-2">How should we reach you?</p>' +
            '<div class="cp-mode-choice">' +
            '<label class="cp-mode-option"><input type="radio" name="cp-mode" value="wallet" checked> Link WhatsApp &amp; wallet</label>' +
            '<label class="cp-mode-option"><input type="radio" name="cp-mode" value="anonymous"> Quick chat (no WhatsApp)</label>' +
            '</div>' +
            '<div id="cp-anon-block" style="display:none">' +
            '<div class="cp-consent-box"><p>Chat in this browser only. We will not create a wallet or send WhatsApp messages.</p></div>' +
            '<label><input type="checkbox" id="cp-consent-anon"> I agree to chat with support</label>' +
            '</div>' +
            '<div id="cp-wallet-block">' +
            '<div class="cp-consent-box">' +
            '<p>Your WhatsApp number saves this chat and links a CheckoutPay wallet.</p>' +
            '<p class="mt-1">You will receive a prompt on WhatsApp. Approved refunds can be credited to your wallet.</p>' +
            '</div>' +
            '<label>Country</label>' +
            countrySelectHtml(suggested) +
            '<label>WhatsApp number</label>' +
            '<input type="tel" id="cp-phone" placeholder="Mobile number" autocomplete="tel">' +
            '<label><input type="checkbox" id="cp-consent-wallet"> I agree to link WhatsApp and create or use a wallet</label>' +
            '</div>' +
            '</div>'
        );
    }

    function selectedIssueRequiresPayment() {
        const sel = document.getElementById('cp-issue-type');
        if (!sel) {
            return false;
        }
        const opt = sel.options[sel.selectedIndex];
        return opt && opt.getAttribute('data-requires-payment') === '1';
    }

    function paymentFieldsComplete() {
        const sessionInput = document.getElementById('cp-session-id');
        const amountInput = document.getElementById('cp-amount-paid');
        return (
            sessionInput &&
            sessionInput.value.trim().length >= 4 &&
            amountInput &&
            parseFloat(amountInput.value) > 0
        );
    }

    function countrySelectHtml(selectedIso) {
        const countries = (supportOptions && supportOptions.countries) || [];
        let html = '<select id="cp-country" class="cp-country-select">';
        countries.forEach(function (c) {
            const sel = c.iso === selectedIso ? ' selected' : '';
            html += '<option value="' + c.iso + '"' + sel + '>' + c.label + ' (+' + c.dial + ')</option>';
        });
        html += '</select>';
        return html;
    }

    function showOnboarding() {
        if (!footer || !body) {
            return;
        }
        footer.style.display = 'none';
        const suggested = (supportOptions && supportOptions.suggested_country) || 'NG';

        body.innerHTML =
            '<div class="cp-support-onboarding">' +
            '<p class="text-sm font-semibold text-gray-900 mb-2">Start live support</p>' +
            '<p class="text-xs text-gray-600 mb-3">Payment issue? Add the session ID from your bank transfer receipt, then choose how we should contact you.</p>' +
            issueTypeSelectHtml() +
            whatsappStepHtml(suggested) +
            '<label>Your name (optional)</label>' +
            '<input type="text" id="cp-name" placeholder="Your name">' +
            '<label>Extra details (optional)</label>' +
            '<input type="text" id="cp-first-msg" placeholder="Anything else we should know?">' +
            '<button type="button" class="cp-btn-primary" id="cp-start-chat" disabled>Start chat</button>' +
            '<p id="cp-onboard-error" class="cp-onboard-error" style="display:none"></p>' +
            '</div>';

        const issueSelect = document.getElementById('cp-issue-type');
        const paymentBlock = document.getElementById('cp-payment-block');
        const issueHint = document.getElementById('cp-issue-hint');
        const sessionInput = document.getElementById('cp-session-id');
        const amountInput = document.getElementById('cp-amount-paid');
        const whatsappStep = document.getElementById('cp-whatsapp-step');
        const modeRadios = body.querySelectorAll('input[name="cp-mode"]');
        const anonBlock = document.getElementById('cp-anon-block');
        const walletBlock = document.getElementById('cp-wallet-block');
        const consentAnon = document.getElementById('cp-consent-anon');
        const consentWallet = document.getElementById('cp-consent-wallet');
        const phone = document.getElementById('cp-phone');
        const startBtn = document.getElementById('cp-start-chat');

        function isWalletMode() {
            const checked = body.querySelector('input[name="cp-mode"]:checked');
            return checked && checked.value === 'wallet';
        }

        function applyWalletModeUi() {
            const wallet = isWalletMode();
            if (anonBlock) {
                anonBlock.style.display = wallet ? 'none' : 'block';
            }
            if (walletBlock) {
                walletBlock.style.display = wallet ? 'block' : 'none';
            }
        }

        function paymentStepComplete() {
            if (!selectedIssueRequiresPayment()) {
                return true;
            }
            return paymentFieldsComplete();
        }

        function updateWhatsappStepVisibility() {
            const ready = paymentStepComplete();
            const waitHint = document.getElementById('cp-whatsapp-wait-hint');
            if (whatsappStep) {
                whatsappStep.classList.toggle('cp-whatsapp-step-pending', !ready);
            }
            if (waitHint) {
                waitHint.style.display =
                    selectedIssueRequiresPayment() && !ready ? 'block' : 'none';
            }
            applyWalletModeUi();
            validate();
        }

        function updateIssueUi() {
            const needsPayment = selectedIssueRequiresPayment();
            if (paymentBlock) {
                paymentBlock.style.display = needsPayment ? 'block' : 'none';
            }
            if (issueSelect && issueHint) {
                const types = (supportOptions && supportOptions.issue_types) || [];
                const row = types.find(function (t) {
                    return t.key === issueSelect.value;
                });
                issueHint.textContent = row && row.hint ? row.hint : '';
            }
            updateWhatsappStepVisibility();
        }

        function validate() {
            if (!startBtn) {
                return;
            }
            if (!paymentStepComplete()) {
                startBtn.disabled = true;
                return;
            }
            let ok = false;
            if (isWalletMode()) {
                ok =
                    consentWallet &&
                    consentWallet.checked &&
                    phone &&
                    phone.value.trim().length >= 8;
            } else {
                ok = consentAnon && consentAnon.checked;
            }
            if (selectedIssueRequiresPayment()) {
                ok = ok && paymentFieldsComplete();
            }
            startBtn.disabled = !ok;
        }

        if (issueSelect) {
            issueSelect.addEventListener('change', updateIssueUi);
        }
        if (sessionInput) {
            sessionInput.addEventListener('input', updateWhatsappStepVisibility);
        }
        if (amountInput) {
            amountInput.addEventListener('input', updateWhatsappStepVisibility);
        }

        modeRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                applyWalletModeUi();
                validate();
            });
        });

        if (consentAnon) {
            consentAnon.addEventListener('change', validate);
        }
        if (consentWallet) {
            consentWallet.addEventListener('change', validate);
        }
        if (phone) {
            phone.addEventListener('input', validate);
        }
        if (startBtn) {
            startBtn.addEventListener('click', startConversation);
        }
        updateIssueUi();
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
        const isVisitor = msg.user_type === 'visitor';
        el.className = 'cp-msg ' + (isVisitor ? 'cp-msg-visitor' : 'cp-msg-staff');
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

    async function startConversation() {
        const errEl = document.getElementById('cp-onboard-error');
        if (errEl) {
            errEl.style.display = 'none';
        }
        const modeChecked = body.querySelector('input[name="cp-mode"]:checked');
        const walletMode = modeChecked && modeChecked.value === 'wallet';
        const nameEl = document.getElementById('cp-name');
        const firstEl = document.getElementById('cp-first-msg');
        const name = nameEl ? nameEl.value.trim() : '';
        const first = firstEl ? firstEl.value.trim() : '';

        const issueEl = document.getElementById('cp-issue-type');
        const sessionEl = document.getElementById('cp-session-id');
        const amountEl = document.getElementById('cp-amount-paid');

        const payload = {
            link_whatsapp_wallet: walletMode,
            issue_type: issueEl ? issueEl.value : undefined,
            name: name || undefined,
            channel: 'checkout_web',
            consent_accepted: true,
            first_message: first || undefined,
        };

        if (issueEl && issueEl.options[issueEl.selectedIndex].getAttribute('data-requires-payment') === '1') {
            payload.payment_transaction_id = sessionEl ? sessionEl.value.trim() : '';
            payload.payment_amount_reported = amountEl ? parseFloat(amountEl.value) : undefined;
        }

        if (walletMode) {
            const phoneEl = document.getElementById('cp-phone');
            const countryEl = document.getElementById('cp-country');
            payload.phone = phoneEl ? phoneEl.value.trim() : '';
            payload.country_iso = countryEl ? countryEl.value : '';
            payload.wallet_consent_accepted = true;
        }

        try {
            const data = await api('/public/support/conversations', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            publicToken = data.data.public_token;
            localStorage.setItem(STORAGE_KEY, publicToken);
            lastMessageId = 0;
            if (body) {
                body.innerHTML = '';
            }
            showChat();
            if (first) {
                appendMessage({ id: 1, user_type: 'visitor', message: first });
            }
            await pollMessages(true);
            startPoll();
        } catch (e) {
            if (errEl) {
                errEl.textContent = e.message || 'Could not start chat';
                errEl.style.display = 'block';
            }
        }
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
                showOnboarding();
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
        } catch (e) {
            publicToken = '';
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
