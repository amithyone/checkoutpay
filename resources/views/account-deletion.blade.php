<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.marketing-head', ['seoPath' => '/account-deletion'])
@include('partials.tailwind-assets')
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    @php
        $supportEmail = \App\Models\Setting::get('contact_email', 'notify@check-outnow.com');
        $supportPhone = \App\Models\Setting::get('contact_phone', '+234 814 438 7915');
        $deletionMailSubject = rawurlencode('Account and data deletion request');
        $deletionMailBody = rawurlencode("Registered phone number:\n\nReason (optional):\n\nI confirm I want my CheckoutPay consumer wallet account and associated personal data deleted where the law allows.");
    @endphp

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <div class="text-center mb-8 sm:mb-10">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-3">Request account deletion</h1>
            <p class="text-base sm:text-lg text-gray-600 max-w-2xl mx-auto">
                Use this page to ask us to delete your <strong>CheckoutNow</strong> or <strong>WhatsApp Wallet</strong> consumer account and associated personal data.
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8 space-y-8 text-gray-700 leading-relaxed">
            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">Who this is for</h2>
                <p class="mb-3">This process applies to <strong>consumer wallet accounts</strong> (CheckoutNow mobile app and CheckoutPay WhatsApp Wallet). It does not close merchant or business accounts — business users should contact support from their dashboard or email us from their registered business address.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">Before you request deletion</h2>
                <ul class="list-disc pl-6 space-y-2">
                    <li>Withdraw or spend any remaining wallet balance. We cannot delete an account that still holds funds until the balance is zero.</li>
                    <li>If you have an active <strong>Dollar Virtual Card</strong>, withdraw card funds and freeze the card first.</li>
                    <li>Resolve any open support tickets or disputes linked to your wallet.</li>
                    <li>Deletion is permanent. You will need to register again if you want to use the wallet in future.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">What we delete</h2>
                <p class="mb-3">After we verify your identity, we delete or anonymise active personal data tied to your consumer wallet where we are not required to keep it, including:</p>
                <ul class="list-disc pl-6 space-y-2">
                    <li>Wallet profile details (display name, notification preferences, PIN credentials)</li>
                    <li>Linked app device tokens and in-app session data</li>
                    <li>KYC documents and verification artefacts stored for your consumer profile, where retention is not required</li>
                    <li>Virtual card request and card notification settings associated with your wallet</li>
                    <li>WhatsApp Wallet menu state and messaging preferences used only to operate your wallet</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">What we may keep</h2>
                <p class="mb-3">We may retain certain records after account closure where required by law, regulation, fraud prevention, or accounting rules, including:</p>
                <ul class="list-disc pl-6 space-y-2">
                    <li>Transaction, transfer, and payment history (often for several years)</li>
                    <li>Records needed for tax, AML/CTF, dispute resolution, or regulatory reporting</li>
                    <li>Support correspondence related to financial activity</li>
                    <li>Aggregated or de-identified analytics that no longer identify you</li>
                </ul>
                <p class="mt-3 text-sm text-gray-600">See our <a href="{{ route('privacy-policy') }}" class="text-primary hover:underline">Privacy Policy</a> for more on retention and your rights.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">How to submit your request</h2>
                <p class="mb-4">Email us from the address on your KYC profile if you have one, or send a message that includes your <strong>registered wallet phone number</strong> (including country code, e.g. +234…). We may ask for further information to confirm you own the account.</p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="mailto:{{ $supportEmail }}?subject={{ $deletionMailSubject }}&body={{ $deletionMailBody }}"
                        class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary/90 text-sm sm:text-base">
                        <i class="fas fa-envelope"></i>
                        Email deletion request
                    </a>
                    <a href="{{ route('support.index') }}"
                        class="inline-flex items-center justify-center gap-2 px-5 py-3 border border-gray-300 text-gray-800 font-semibold rounded-lg hover:bg-gray-50 text-sm sm:text-base">
                        <i class="fas fa-comments"></i>
                        Contact support instead
                    </a>
                </div>
                <p class="mt-4 text-sm text-gray-600">
                    Email: <a href="mailto:{{ $supportEmail }}" class="text-primary hover:underline">{{ $supportEmail }}</a>
                    @if($supportPhone)
                        · Phone: <a href="tel:{{ preg_replace('/\D+/', '', $supportPhone) }}" class="text-primary hover:underline">{{ $supportPhone }}</a>
                    @endif
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">What happens next</h2>
                <ol class="list-decimal pl-6 space-y-2">
                    <li>We acknowledge your request and verify your identity (usually within a few business days).</li>
                    <li>We check for remaining balance, open disputes, or regulatory holds.</li>
                    <li>We delete or anonymise eligible data and confirm when your consumer account is closed.</li>
                </ol>
                <p class="mt-3 text-sm text-gray-600">If we cannot complete deletion immediately (for example because of a legal hold), we will explain why and give an expected timeline.</p>
            </section>
        </div>
    </div>

    @include('partials.footer')
</body>
</html>
