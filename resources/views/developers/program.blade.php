<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Program — {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    <meta name="description" content="Built by a developer who integrated Paystack, Flutterwave, and more—without ever earning a share. {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} pays partners via Business ID attribution, including our WordPress plugin.">
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#3C50E0' },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-white text-gray-900">
    @include('partials.nav')

    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-12 sm:py-16 md:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-sm font-semibold text-primary uppercase tracking-wide mb-3">Developer Program</p>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4 sm:mb-6">Built by a developer who was tired of working for free</h1>
            <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 max-w-3xl mx-auto">
                If you ship payment integrations for clients, you deserve a share—not just a line on your portfolio. We pay approved partners through clear attribution, starting with your <strong class="text-gray-800">Business ID</strong> in our WordPress / WooCommerce plugin.
            </p>
            <p class="text-sm sm:text-base text-amber-950 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 max-w-2xl mx-auto mb-8">
                <strong>Apply first:</strong> You must <strong>submit an application and be approved</strong> into the Developer Program before any revenue share can accrue—even if you already added a Business ID in the plugin.
                <a href="{{ url('/developers/program/apply') }}" class="block mt-2 text-primary font-semibold underline hover:text-primary/80">Open application form →</a>
            </p>
            <div class="flex flex-col gap-4 justify-center items-stretch sm:items-center max-w-lg mx-auto">
                <div class="flex flex-col sm:flex-row gap-3 w-full sm:justify-center">
                <a href="{{ url('/developers/program/apply') }}" class="inline-flex items-center justify-center px-6 py-3.5 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium touch-manipulation order-1">
                    Apply to the program
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
                <a href="{{ route('developers.index') }}" class="inline-flex items-center justify-center px-6 py-3 border-2 border-primary text-primary rounded-lg hover:bg-primary/5 font-medium touch-manipulation order-2">
                    Developer hub
                </a>
                </div>
                <p class="text-xs text-center text-gray-500 sm:pt-1">Need help before you apply?</p>
                <a href="{{ route('contact') }}" class="inline-flex items-center justify-center px-6 py-2.5 text-gray-600 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 font-medium text-sm touch-manipulation">
                    Contact us (general questions)
                </a>
            </div>
        </div>
    </section>

    <article class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <section class="mb-14" id="story">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Why this program exists</h2>
            <div class="text-gray-700 leading-relaxed space-y-5 text-base sm:text-lg border-l-4 border-primary/40 pl-5 sm:pl-6">
                <p>
                    I have been a developer for more than ten years. Over that time I integrated payment gateways my clients asked for—names you know, like <strong class="text-gray-900">Paystack</strong> and <strong class="text-gray-900">Flutterwave</strong>—and many others. The businesses kept running, transactions kept flowing, and the brands got stronger.
                </p>
                <p>
                    What I did <em>not</em> get was a commission. Not a recurring share, not a referral fee tied to the volume I helped unlock. I had effectively become <strong class="text-gray-900">free advertising</strong> for those gateways: real implementations, real trust, real revenue—for them—while my upside stopped at the invoice for “integration hours.”
                </p>
                <p>
                    That experience is why {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} exists, and why we run this developer program. I know the pain from the inside. If you put our API or plugin on a site you build or maintain, we want a <strong class="text-gray-900">fair, documented way</strong> for you to earn an ongoing percentage—not goodwill, but a rule-based share credited to <em>your</em> business account when you claim the integration.
                </p>
            </div>
        </section>

        <section class="mb-14 bg-white rounded-xl border-2 border-primary/30 shadow-sm p-6 sm:p-8" id="how-we-pay-you">
            <div class="flex items-start gap-3 mb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><i class="fab fa-wordpress text-xl"></i></span>
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">How we identify you—and pay you</h2>
                    <p class="text-gray-600 mt-1 text-sm sm:text-base">WordPress / WooCommerce plugin: <strong class="text-gray-900">Developer Business ID</strong></p>
                </div>
            </div>
            <p class="text-gray-600 leading-relaxed mb-5">
                We are adding a dedicated field in our <strong class="text-gray-900">WordPress / WooCommerce plugin</strong> so you can claim your work without awkward spreadsheets or disputed “who introduced this merchant?” debates.
            </p>
            <ol class="list-decimal pl-5 space-y-4 text-gray-700 leading-relaxed">
                <li>
                    <strong class="text-gray-900">Apply for the Developer Program</strong> using our online form: your name, <strong class="text-gray-900">Business ID</strong> (if you already have a business account), phone, email, WhatsApp number, and whether you want to join the developer community on <strong class="text-gray-900">Slack</strong>, <strong class="text-gray-900">WhatsApp</strong>, or both. We review applications and only <strong class="text-gray-900">approved</strong> partners are eligible for revenue share.
                </li>
                <li>
                    <strong class="text-gray-900">Register</strong> a <strong class="text-gray-900">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} business account</strong> if you do not have one yet—that is the account that will receive your revenue share once you are in the program.
                </li>
                <li>
                    In your business dashboard, use your <strong class="text-gray-900">Business ID</strong> (the identifier for that account—we show it where you manage API keys and business settings).
                </li>
                <li>
                    When you configure the plugin on your client’s WordPress site, you—or the merchant, if you hand off the site—enter <strong class="text-gray-900">your Business ID</strong> into the plugin’s <strong class="text-gray-900">Developer / partner Business ID</strong> field. The store still uses <em>the merchant’s</em> API credentials for checkout; your ID is only there to <strong class="text-gray-900">attribute</strong> the integration to you.
                </li>
                <li>
                    After approval, eligible volume from that store is tied to your ID. Your <strong class="text-gray-900">shared percentage</strong> accrues to <strong class="text-gray-900">your business account</strong>—the same account as that Business ID—so payouts and reporting stay in one place you already control.
                </li>
            </ol>
            <p class="text-sm text-gray-500 mt-5 border-t border-gray-100 pt-4">
                API-only or custom stacks: we use the same idea—an explicit partner or developer identifier on the integration—so attribution stays auditable. WordPress is the first place we surface it as a simple, copy-paste field.
            </p>
        </section>

        <section class="mb-14" id="overview">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">How the program fits together</h2>
            <p class="text-gray-600 leading-relaxed mb-4">
                You remain the builder helping the merchant go live. <strong class="text-gray-800">You must apply and be approved</strong> into the Developer Program first; until then, no revenue share accrues. After approval, we handle processing and you receive a defined share of qualifying revenue when your Business ID (or approved equivalent) is on file and the integration meets program rules. This is separate from normal merchant pricing: it exists so developers are not unpaid distribution for another brand’s growth.
            </p>
        </section>

        <section class="mb-14 bg-amber-50 border border-amber-200 rounded-xl p-6 sm:p-8" id="revenue-share">
            <h2 class="text-xl font-bold text-gray-900 mb-3 flex items-center gap-2">
                <i class="fas fa-percent text-amber-700"></i>
                Revenue share
            </h2>
            @if($developerProgramFeeSharePercent !== null && $developerProgramFeeSharePercent !== '')
                @php
                    $pctFormatted = rtrim(rtrim(number_format((float) $developerProgramFeeSharePercent, 2, '.', ''), '0'), '.');
                @endphp
                <p class="text-gray-700 leading-relaxed mb-4">
                    Approved partners can earn <strong class="text-gray-900">{{ $pctFormatted }}%</strong> of
                    <strong class="text-gray-900">{{ $developerProgramFeeShareBaseDescription }}</strong>,
                    subject to program rules, attribution (including your Business ID where required), and your signed agreement.
                </p>
                <ul class="list-disc pl-5 space-y-2 text-gray-700 text-sm sm:text-base">
                    <li><strong>Partner rate:</strong> {{ $pctFormatted }}% of the base described above, for qualifying production volume after approval.</li>
                    <li><strong>Tiers:</strong> Volume bands or custom rates may apply per partner agreement; your onboarding email will confirm if your rate differs from the published default.</li>
                    <li><strong>Exclusions:</strong> Test/sandbox traffic, chargebacks, fraud, program violations, or unattributed volume do not earn share.</li>
                    <li><strong>Currency &amp; tax:</strong> Payouts follow your business account currency; withholding and reporting are as described in your partner terms.</li>
                </ul>
            @else
                <p class="text-sm text-amber-900 mb-4 font-medium">We are finalizing the published partner percentage. Apply now; your agreement will state the exact rate and base.</p>
                <ul class="list-disc pl-5 space-y-2 text-gray-700 text-sm sm:text-base">
                    <li><strong>Partner rate:</strong> Set in your approval email and partner agreement (matches the rate configured in our admin for the program).</li>
                    <li><strong>Base:</strong> A defined portion of {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}’s fee revenue on attributed transactions—not a hidden charge to your client’s customers.</li>
                    <li><strong>Exclusions:</strong> Test/sandbox traffic, chargebacks, fraud, program violations, or unattributed volume do not earn share.</li>
                    <li><strong>Currency &amp; tax:</strong> As described in your partner terms.</li>
                </ul>
            @endif
        </section>

        <section class="mb-14" id="qualify">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Who qualifies</h2>
            <ul class="space-y-3 text-gray-600">
                <li class="flex gap-3"><i class="fas fa-check text-primary mt-1"></i><span>Developers, agencies, and freelancers who implement {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} for third-party merchants and can supply a valid Business ID for payouts.</span></li>
                <li class="flex gap-3"><i class="fas fa-check text-primary mt-1"></i><span>Product teams embedding our checkout in WordPress, WooCommerce, or custom applications.</span></li>
                <li class="flex gap-3"><i class="fas fa-check text-primary mt-1"></i><span>Partners who keep attribution honest: the merchant knows who built the integration, and your ID reflects the work you actually performed.</span></li>
            </ul>
        </section>

        <section class="mb-14" id="integrations">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">What counts as a qualifying integration</h2>
            <div class="space-y-6 text-gray-600 leading-relaxed">
                <div class="border border-gray-200 rounded-lg p-5 ring-1 ring-primary/10">
                    <h3 class="font-semibold text-gray-900 mb-2 flex items-center gap-2"><i class="fab fa-wordpress text-purple-600"></i> WordPress / WooCommerce (plugin + Business ID)</h3>
                    <p>Production use of our official plugin with the merchant’s API keys, plus your <strong class="text-gray-800">Developer Business ID</strong> entered in the plugin field so shareable volume can be credited to your business account. Sandbox-only or missing IDs may not qualify.</p>
                </div>
                <div class="border border-gray-200 rounded-lg p-5">
                    <h3 class="font-semibold text-gray-900 mb-2">REST API &amp; server-to-server</h3>
                    <p>Production API keys on behalf of a registered merchant, with webhooks and patterns documented in our API reference. Partner attribution follows the same principle as the plugin: an explicit, approved identifier—not guesswork from IP or domain alone.</p>
                </div>
                <div class="border border-gray-200 rounded-lg p-5">
                    <h3 class="font-semibold text-gray-900 mb-2">Hosted checkout &amp; payment links</h3>
                    <p>Flows that complete on our infrastructure with clear merchant identification and any partner metadata we agree during onboarding.</p>
                </div>
            </div>
        </section>

        <section class="mb-14" id="attribution">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Attribution &amp; tracking</h2>
            <p class="text-gray-600 leading-relaxed mb-4">
                The <strong class="text-gray-800">Business ID field in the WordPress plugin</strong> is the default way we know which developer account to credit, <strong class="text-gray-800">after you are approved</strong> into the Developer Program. For other channels, we align on the same idea: a partner or developer identifier you place at integration time, documented in your partner agreement.
            </p>
            <p class="text-gray-600 leading-relaxed">
                If attribution cannot be determined unambiguously—for example, conflicting IDs or missing plugin configuration—that activity may be excluded from payouts until resolved. You agree not to obscure or misrepresent the merchant relationship.
            </p>
        </section>

        <section class="mb-14" id="payouts">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Payouts, minimums, and reporting</h2>
            <p class="text-gray-600 leading-relaxed mb-4">
                Your share accrues to the <strong class="text-gray-800">business account linked to the Business ID</strong> you used to claim the integration, <strong class="text-gray-800">only while you remain an approved program partner</strong>. <strong class="text-gray-800">Placeholder:</strong> add your payout schedule (e.g. monthly net-30), minimum balance, withdrawal methods, and where partners see statements in the dashboard or exports.
            </p>
            <p class="text-gray-600 leading-relaxed">
                Partners are responsible for valid tax and banking information on that business account. We may withhold payouts if fraud checks are open or program terms are breached.
            </p>
        </section>

        <section class="mb-14" id="compliance">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Compliance &amp; merchant consent</h2>
            <ul class="list-disc pl-5 space-y-2 text-gray-600">
                <li>Merchants must understand they are contracting with {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} for payment processing; your Business ID is for attribution and revenue share, not to hide who processes payments.</li>
                <li>No false claims about rates, approval odds, or regulatory status. Direct merchants to official docs for PCI and data handling.</li>
                <li>Do not store secret API keys in client-side code or public repositories.</li>
                <li>Honor opt-out and data-deletion requests consistent with the merchant’s policies and applicable law.</li>
            </ul>
        </section>

        <section class="mb-14" id="eligibility">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Application &amp; ongoing eligibility</h2>
            <p class="text-gray-600 leading-relaxed mb-4">
                Participation starts with the <a href="{{ url('/developers/program/apply') }}" class="text-primary font-medium hover:underline">online application</a> (name, Business ID if available, phone, email, WhatsApp, and Slack/WhatsApp community preference). We review each submission and may request more detail about your integrations. We may accept, defer, or decline applications at our discretion. Approved partners should keep integrations maintained (security updates, API changes) to remain in good standing.
            </p>
            <p class="text-gray-600 leading-relaxed">
                We may update program terms with notice. Continued participation after changes constitutes acceptance unless you exit the program according to the notice period we specify.
            </p>
        </section>

        <section class="mb-14" id="faq">
            <h2 class="text-2xl font-bold text-gray-900 mb-8">Frequently asked questions</h2>
            <div class="space-y-3">
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Do I have to apply before I can receive revenue share?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">Yes. You must <a href="{{ url('/developers/program/apply') }}" class="text-primary font-medium hover:underline">submit the Developer Program application</a> and be <strong class="text-gray-800">approved</strong>. Until you are in the program, we do not accrue or pay developer revenue share—even if you already use our API or plugin.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        I integrated Paystack, Flutterwave, and others for years and never got a share—is this different?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">Many gateways treat developers as implementation help while the brand captures all long-term value from processing. Our program is meant to fix that gap: when you are approved and attribution is valid (for example your <strong class="text-gray-800">Business ID</strong> on a qualifying integration), you can earn a defined share on eligible volume—not just a one-off project fee.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Why do I need my own {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} business account if I only build for clients?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">Your share is credited to <strong class="text-gray-800">your</strong> business account—the same place your Business ID lives—so you have statements, tax identity, and withdrawals in one profile. You are not “becoming the merchant”; you are claiming the developer attribution channel we use to pay you.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Where do I find my Business ID?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">After you <a href="{{ route('business.register') }}" class="text-primary font-medium hover:underline">create a business account</a>, use the Business ID shown in your dashboard (alongside API keys and business settings). That is the value you paste into the WordPress plugin’s developer / partner field.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Does the merchant’s Business ID replace mine?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">No. The merchant connects their store with their own API credentials. Your Business ID is a separate field used only to attribute the developer share to your account.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Is it safe to put my Business ID on a client’s WordPress site? What does the store owner see?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">The field is for <strong class="text-gray-800">attribution</strong>, not for sharing secret keys. Merchants should know you are listed as the integrating developer and that the ID is how we route your revenue share. Treat it like any other admin setting: only people who should manage payments should have wp-admin access.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Does my client pay extra so I get paid?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">Your share comes from the program’s agreed economics on <strong class="text-gray-800">eligible</strong> processing—not from sneaking a hidden surcharge onto the shopper. The headline rate on our Developer Program page is kept in sync with admin configuration; your partner agreement may include additional detail.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Can my agency use one Business ID for every client site?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">Usually yes: one partner business account, one Business ID, many stores—each installs the plugin with the same developer field so volume rolls up to you. If you need separate entities for tax or accounting, create separate business accounts and use the matching IDs.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        I only do custom code / Laravel / mobile apps—no WordPress. How do I get paid?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">The WordPress field is the first simple pattern we ship; the <strong class="text-gray-800">same principle</strong> applies elsewhere: an explicit partner or developer identifier we agree at onboarding (metadata, referral codes, or documented API patterns). <a href="{{ route('contact') }}" class="text-primary font-medium hover:underline">Contact us</a> with your stack so we can align attribution before go-live.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Can I get paid on stores I built before the plugin had a developer field?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">Retroactive credit is <strong class="text-gray-800">not guaranteed</strong>. Once the field is available, add your Business ID and apply to the program; we will confirm what history—if any—can be attributed fairly.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        What if someone removes my Business ID from the plugin after launch?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">Attribution follows what is configured in production. If the ID is removed or overwritten, new volume may no longer map to you until it is restored. Disputes between you and a merchant are a commercial relationship; we can only pay against valid, current attribution and program rules.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Do test or sandbox transactions count toward my share?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">No. Revenue share applies to <strong class="text-gray-800">qualifying production</strong> volume under the program. Test keys and sandbox traffic are for integration quality, not payouts.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Does every integration automatically qualify?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">No. You should apply to the program, meet technical and compliance rules, and use production traffic with a valid Business ID (or approved equivalent). We may decline or pause partners if attribution is unclear or terms are breached.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Can I stack this with another referral or affiliate program?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">Stacking is governed by your <strong class="text-gray-800">partner agreement</strong>. Default assumption is one primary developer attribution per merchant integration unless we document an exception.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        What if my client already has an account?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">You can often still add your Developer Business ID in the plugin when the program allows it for that merchant. Migrations and legacy setups vary—<a href="{{ route('contact') }}" class="text-primary font-medium hover:underline">contact us</a> during application so we can confirm the cleanest path.</p>
                </details>
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center">
                        Where do I get API keys and documentation?
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">Start at the <a href="{{ route('developers.index') }}" class="text-primary font-medium hover:underline">Developer hub</a> and <a href="{{ route('business.api-documentation.index') }}" class="text-primary font-medium hover:underline">API documentation</a>. Product downloads and plugin details are linked from <a href="{{ route('products.index') }}#wordpress-plugin" class="text-primary font-medium hover:underline">Products</a>.</p>
                </details>
            </div>
        </section>

        <section class="bg-primary/5 border border-primary/20 rounded-xl p-8 text-center">
            <h2 class="text-xl font-bold text-gray-900 mb-2">Ready to stop working for free?</h2>
            <p class="text-gray-600 mb-6 max-w-xl mx-auto">Submit the Developer Program application with your contact details and how you want to join our Slack or WhatsApp community. After approval, your Business ID can start earning on qualifying integrations.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ url('/developers/program/apply') }}" class="inline-flex items-center justify-center px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium touch-manipulation">Apply to the program</a>
                <a href="{{ route('contact') }}" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-gray-800 rounded-lg hover:bg-white font-medium">Contact us</a>
                <a href="{{ route('business.register') }}" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-gray-800 rounded-lg hover:bg-white font-medium">Create a business account</a>
            </div>
        </section>
    </article>

    @include('partials.footer')
</body>
</html>
