@php
    $siteName = \App\Models\Setting::get('site_name', 'CheckoutPay');
    $initials = collect(preg_split('/\s+/', trim((string) $link->business->name)))
        ->filter()
        ->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
    $payAmount = $selectedPayment
        ? $link->currency.' '.number_format((float) $selectedPayment->amount, 2)
        : $link->formattedAmount();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Pay {{ $link->title }} - {{ $link->business->name }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    @include('partials.tailwind-assets')
    @include('payment-links.partials.payer-styles')
</head>
<body class="pl-app">
    <div class="pl-atmosphere" aria-hidden="true">
        <div class="pl-blob pl-blob-a"></div>
        <div class="pl-blob pl-blob-b"></div>
        <div class="pl-blob pl-blob-c"></div>
    </div>

    <div class="pl-shell">
        <div class="pl-brand">{{ $siteName }}</div>

        <div class="pl-hero">
            <div class="pl-avatar">{{ $initials !== '' ? $initials : 'P' }}</div>
            <div>
                <h1>Pay {{ $link->business->name }}</h1>
                <p>{{ $link->title }}</p>
            </div>
        </div>

        <div class="pl-amount">
            <label>Amount</label>
            <strong>{{ $payAmount }}</strong>
            @if($link->description)
                <p class="pl-note">{{ $link->description }}</p>
            @endif
        </div>

        @if(session('error'))
            <div class="pl-error">{{ session('error') }}</div>
        @endif

        @if(!empty($paymentSetupError))
            <div class="pl-card pl-center">
                <p class="pl-kicker">Try again</p>
                <h2>Payment setup issue</h2>
                <p class="pl-hint">{{ $paymentSetupError }}</p>
                <a href="{{ route('payment-links.pay', $link->code) }}" class="pl-btn pl-btn-primary">Try again</a>
            </div>
        @elseif($selectedPayment && $selectedPayment->isApproved())
            <div class="pl-card pl-center">
                <i class="fas fa-check-circle" style="color:#22c55e"></i>
                <h2>Payment received</h2>
                <p class="pl-hint">Thank you. This payment has been confirmed.</p>
                <p class="pl-hint" style="font-family:ui-monospace,monospace">{{ $selectedPayment->transaction_id }}</p>
            </div>
        @elseif($selectedPayment && $selectedPayment->account_number)
            <div class="pl-card">
                <p class="pl-kicker">Payment instructions</p>
                <p class="pl-hint">Transfer the exact amount to this account.</p>

                <div class="pl-row">
                    <div class="pl-row-main">
                        <div class="pl-ico purple"><i class="fas fa-credit-card"></i></div>
                        <div>
                            <p>Account Number</p>
                            <strong class="pl-acc" id="accountNumber">{{ $selectedPayment->account_number }}</strong>
                        </div>
                    </div>
                    <button type="button" class="pl-copy" id="copyBtn" title="Copy"><i class="fas fa-copy"></i></button>
                </div>
                <div class="pl-rule"></div>
                <div class="pl-row">
                    <div class="pl-row-main">
                        <div class="pl-ico cyan"><i class="fas fa-university"></i></div>
                        <div>
                            <p>Bank Institution</p>
                            <strong>{{ $selectedPayment->accountNumberDetails->bank_name ?? 'N/A' }}</strong>
                        </div>
                    </div>
                </div>
                <div class="pl-rule"></div>
                <div class="pl-row">
                    <div class="pl-row-main">
                        <div class="pl-ico blue"><i class="fas fa-user"></i></div>
                        <div>
                            <p>Account Name</p>
                            <strong>{{ $selectedPayment->accountNumberDetails->account_name ?? 'N/A' }}</strong>
                        </div>
                    </div>
                </div>

                @if($selectedPayment->isPending())
                    <div id="pay-wait" class="pl-wait">
                        <div id="pay-wait-spinner" class="pay-wait-spinner" aria-hidden="true"></div>
                        <div class="pay-wait-copy pl-wait-copy">
                            <p id="pay-wait-title" class="pl-wait-title">Waiting for payment</p>
                            <p id="pay-wait-sub" class="pl-wait-sub">We’ll confirm it after you transfer.</p>
                        </div>
                    </div>
                    <button type="button" id="check-payment-status" class="pl-btn pl-btn-primary">Check payment status</button>
                @endif

                @if(!empty($cardPaymentsEnabled))
                    <details class="pl-alt">
                        <summary>Pay with card instead</summary>
                        <form method="POST" action="{{ route('payment-links.start', $link->code) }}" class="pl-form">
                            @csrf
                            <input type="hidden" name="payment_method" value="card">
                            <input type="hidden" name="payer_name" value="{{ $selectedPayment->payer_name }}">
                            @if($link->isOpenAmount())
                                <input type="hidden" name="amount" value="{{ $selectedPayment->amount }}">
                            @endif
                            <div>
                                <label>Email for card receipt</label>
                                <input type="email" name="email" value="{{ old('email') }}" required>
                                @error('email')<p class="pl-hint" style="color:#fecaca">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="pl-btn pl-btn-primary">Continue to card payment</button>
                        </form>
                    </details>
                @endif
            </div>
        @else
            <div class="pl-card">
                <p class="pl-kicker">Continue to pay</p>
                <form method="POST" action="{{ route('payment-links.start', $link->code) }}" class="pl-form" id="pay-start-form">
                    @csrf
                    @if(!empty($cardPaymentsEnabled))
                        <div>
                            <p class="pl-hint" style="margin-bottom:10px">How do you want to pay?</p>
                            <div class="pl-methods" role="radiogroup" aria-label="How do you want to pay?">
                                <label class="pl-method is-transfer">
                                    <input type="radio" name="payment_method" value="bank_transfer" {{ old('payment_method', 'bank_transfer') === 'bank_transfer' ? 'checked' : '' }}>
                                    <span class="pl-method-ico" aria-hidden="true"><i class="fas fa-university"></i></span>
                                    <strong>Transfer</strong>
                                    <span>Account number</span>
                                </label>
                                <label class="pl-method is-card">
                                    <input type="radio" name="payment_method" value="card" {{ old('payment_method') === 'card' ? 'checked' : '' }}>
                                    <span class="pl-method-ico" aria-hidden="true"><i class="fas fa-credit-card"></i></span>
                                    <strong>Card</strong>
                                    <span>Card payment</span>
                                </label>
                            </div>
                            @error('payment_method')<p class="pl-hint" style="color:#fecaca">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <div class="pl-field">
                        <label>Your name</label>
                        <input type="text" name="payer_name" value="{{ old('payer_name') }}" required>
                        @error('payer_name')<p class="pl-hint" style="color:#fecaca">{{ $message }}</p>@enderror
                    </div>
                    <div id="card-email-field" class="pl-field {{ old('payment_method') === 'card' ? '' : 'hidden' }}">
                        <label>Email</label>
                        <input type="email" name="email" id="card-email-input" value="{{ old('email') }}">
                        <p class="pl-hint">Required for card checkout</p>
                        @error('email')<p class="pl-hint" style="color:#fecaca">{{ $message }}</p>@enderror
                    </div>
                    @if($link->isOpenAmount())
                        <div class="pl-field">
                            <label>Amount (₦, minimum 100)</label>
                            <input type="number" name="amount" step="0.01" min="100" value="{{ old('amount') }}" required>
                            @error('amount')<p class="pl-hint" style="color:#fecaca">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <button type="submit" id="pay-submit" class="pl-btn pl-btn-primary{{ !empty($cardPaymentsEnabled) && old('payment_method') === 'card' ? ' is-card' : (!empty($cardPaymentsEnabled) ? ' is-transfer' : '') }}">{{ !empty($cardPaymentsEnabled) ? 'Get account number' : 'Get payment details' }}</button>
                </form>
            </div>
        @endif
    </div>

    @include('payment-links.partials.create-own')

    @if(!empty($cardPaymentsEnabled) && (empty($selectedPayment) || empty($selectedPayment->account_number)))
    <script>
    (function () {
        const form = document.getElementById('pay-start-form');
        if (!form) return;
        const emailWrap = document.getElementById('card-email-field');
        const emailInput = document.getElementById('card-email-input');
        const submit = document.getElementById('pay-submit');

        function sync() {
            const method = (form.querySelector('input[name="payment_method"]:checked') || {}).value;
            const isCard = method === 'card';
            emailWrap.classList.toggle('hidden', !isCard);
            emailInput.required = isCard;
            submit.textContent = isCard ? 'Continue to card payment' : 'Get account number';
            submit.classList.toggle('is-card', isCard);
            submit.classList.toggle('is-transfer', !isCard);
        }

        form.querySelectorAll('input[name="payment_method"]').forEach(function (el) {
            el.addEventListener('change', sync);
        });
        sync();
    })();
    </script>
    @endif
    @if($selectedPayment && $selectedPayment->isPending() && $selectedPayment->account_number)
    <script>
    (function () {
        const statusUrl = @json(route('payment-links.status', ['code' => $link->code, 'payment_id' => $selectedPayment->id]));
        const title = document.getElementById('pay-wait-title');
        const sub = document.getElementById('pay-wait-sub');
        const spinner = document.getElementById('pay-wait-spinner');
        const copy = document.querySelector('.pay-wait-copy');
        const btn = document.getElementById('check-payment-status');
        const copyBtn = document.getElementById('copyBtn');
        const accountEl = document.getElementById('accountNumber');
        if (copyBtn && accountEl) {
            copyBtn.addEventListener('click', function () {
                navigator.clipboard.writeText(accountEl.textContent.trim()).then(function () {
                    copyBtn.innerHTML = '<i class="fas fa-check"></i>';
                    setTimeout(function () { copyBtn.innerHTML = '<i class="fas fa-copy"></i>'; }, 1600);
                });
            });
        }
        if (!btn || !title) return;

        let busy = false;

        function setState(mode, message) {
            const checking = mode === 'checking';
            spinner.classList.toggle('is-checking', checking);
            if (copy) copy.classList.toggle('is-checking', checking);
            title.textContent = checking ? 'Checking for payment' : 'Waiting for payment';
            sub.textContent = message || (checking
                ? 'Looking up this transfer now…'
                : 'We’ll confirm it after you transfer.');
            btn.disabled = checking;
            btn.textContent = checking ? 'Checking…' : 'Check payment status';
        }

        function checkStatus(manual) {
            if (busy) return;
            busy = true;
            setState('checking');

            fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.paid && data.redirect_url) {
                        title.textContent = 'Payment received';
                        sub.textContent = 'Taking you to the confirmation page…';
                        window.location.href = data.redirect_url;
                        return;
                    }
                    if (data && data.status === 'rejected') {
                        title.textContent = 'Payment not completed';
                        sub.textContent = data.message || 'This payment was not completed.';
                        spinner.classList.remove('is-checking');
                        if (copy) copy.classList.remove('is-checking');
                        btn.disabled = false;
                        btn.textContent = 'Check payment status';
                        busy = false;
                        return;
                    }
                    setState('waiting', manual
                        ? 'No payment yet. Transfer the exact amount, then check again.'
                        : 'Still waiting for payment.');
                    busy = false;
                })
                .catch(function () {
                    setState('waiting', 'Could not check just now. Try again in a moment.');
                    busy = false;
                });
        }

        btn.addEventListener('click', function () { checkStatus(true); });
        const poll = setInterval(function () { checkStatus(false); }, 8000);
        window.addEventListener('beforeunload', function () { clearInterval(poll); });
    })();
    </script>
    @endif
</body>
</html>
