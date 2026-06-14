@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', [
        'seoPath' => '/developers/program',
        'jsonLdExtra' => [\App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('developer-program'))],
    ])
@endsection

@section('content')
    <x-marketing.product-hero
        badge="Developer Program"
        title="Built by a developer who was tired of working for free"
        subtitle="If you ship payment integrations for clients, you deserve a share—not just a line on your portfolio. We pay approved partners through clear attribution, starting with your Business ID in our WordPress / WooCommerce plugin."
        align="center"
    >
        <x-slot:actions>
            <a href="{{ route('developers.program.apply') }}" class="btn-brand">
                Apply to the program
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
            <a href="{{ route('developers.index') }}" class="btn-brand-outline">Developer hub</a>
        </x-slot:actions>
    </x-marketing.product-hero>

    <x-marketing.product-section bg="white">
        <div class="max-w-4xl mx-auto">
            <div class="bg-amber-50 border border-amber-200 rounded-2xl px-4 py-3 mb-10 text-sm text-amber-950 font-medium">
                <strong>Apply first:</strong> You must submit an application and be approved before any revenue share can accrue—even if you already added a Business ID in the plugin.
                <a href="{{ route('developers.program.apply') }}" class="block mt-2 text-brand-primary font-semibold hover:underline">Open application form →</a>
            </div>
        </div>
    </x-marketing.product-section>

    <article class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-16 sm:pb-20">
        <section class="mb-14" id="story">
            <h2 class="text-2xl font-bold text-midnight-deep mb-6">Why this program exists</h2>
            <div class="text-slate-700 leading-relaxed space-y-5 text-base sm:text-lg border-l-4 border-primary/40 pl-5 sm:pl-6">
                <p>
                    I have been a developer for more than ten years. Over that time I integrated payment gateways my clients asked for—names you know, like <strong class="text-midnight-deep">Paystack</strong> and <strong class="text-midnight-deep">Flutterwave</strong>—and many others. The businesses kept running, transactions kept flowing, and the brands got stronger.
                </p>
                <p>
                    What I did <em>not</em> get was a commission. Not a recurring share, not a referral fee tied to the volume I helped unlock. I had effectively become <strong class="text-midnight-deep">free advertising</strong> for those gateways: real implementations, real trust, real revenue—for them—while my upside stopped at the invoice for “integration hours.”
                </p>
                <p>
                    That experience is why {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} exists, and why we run this developer program. I know the pain from the inside. If you put our API or plugin on a site you build or maintain, we want a <strong class="text-midnight-deep">fair, documented way</strong> for you to earn an ongoing percentage—not goodwill, but a rule-based share credited to <em>your</em> business account when you claim the integration.
                </p>
            </div>
        </section>

        <section class="mb-14 card-marketing border-brand-primary/20 bg-gradient-to-br from-brand-primary/5 to-white p-6 sm:p-8" id="how-we-pay-you">
            <div class="flex items-start gap-3 mb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><i class="fab fa-wordpress text-xl"></i></span>
                <div>
                    <h2 class="text-2xl font-bold text-midnight-deep">How we identify you—and pay you</h2>
                    <p class="text-slate-600 mt-1 text-sm sm:text-base">WordPress / WooCommerce plugin: <strong class="text-midnight-deep">Developer Business ID</strong></p>
                </div>
            </div>
            <p class="text-slate-600 leading-relaxed mb-5">
                We are adding a dedicated field in our <strong class="text-midnight-deep">WordPress / WooCommerce plugin</strong> so you can claim your work without awkward spreadsheets or disputed “who introduced this merchant?” debates.
            </p>
            <ol class="list-decimal pl-5 space-y-4 text-slate-700 leading-relaxed">
                <li>
                    <strong class="text-midnight-deep">Apply for the Developer Program</strong> using our online form: your name, <strong class="text-midnight-deep">Business ID</strong> (if you already have a business account), phone, email, WhatsApp number, and whether you want to join the developer community on <strong class="text-midnight-deep">Slack</strong>, <strong class="text-midnight-deep">WhatsApp</strong>, or both. We review applications and only <strong class="text-midnight-deep">approved</strong> partners are eligible for revenue share.
                </li>
                <li>
                    <strong class="text-midnight-deep">Register</strong> a <strong class="text-midnight-deep">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} business account</strong> if you do not have one yet—that is the account that will receive your revenue share once you are in the program.
                </li>
                <li>
                    In your business dashboard, use your <strong class="text-midnight-deep">Business ID</strong> (the identifier for that account—we show it where you manage API keys and business settings).
                </li>
                <li>
                    When you configure the plugin on your client’s WordPress site, you—or the merchant, if you hand off the site—enter <strong class="text-midnight-deep">your Business ID</strong> into the plugin’s <strong class="text-midnight-deep">Developer / partner Business ID</strong> field. The store still uses <em>the merchant’s</em> API credentials for checkout; your ID is only there to <strong class="text-midnight-deep">attribute</strong> the integration to you.
                </li>
                <li>
                    After approval, eligible volume from that store is tied to your ID. Your <strong class="text-midnight-deep">shared percentage</strong> accrues to <strong class="text-midnight-deep">your business account</strong>—the same account as that Business ID—so payouts and reporting stay in one place you already control.
                </li>
            </ol>
            <p class="text-sm text-slate-500 mt-5 border-t border-gray-100 pt-4">
                API-only or custom stacks: we use the same idea—an explicit partner or developer identifier on the integration—so attribution stays auditable. WordPress is the first place we surface it as a simple, copy-paste field.
            </p>
        </section>

        <section class="mb-14" id="overview">
            <h2 class="text-2xl font-bold text-midnight-deep mb-4">How the program fits together</h2>
            <p class="text-slate-600 leading-relaxed mb-4">
                You remain the builder helping the merchant go live. <strong class="text-midnight-deep">You must apply and be approved</strong> into the Developer Program first; until then, no revenue share accrues. After approval, we handle processing and you receive a defined share of qualifying revenue when your Business ID (or approved equivalent) is on file and the integration meets program rules. This is separate from normal merchant pricing: it exists so developers are not unpaid distribution for another brand’s growth.
            </p>
        </section>

        <section class="mb-14 card-marketing bg-amber-50 border-amber-200 p-6 sm:p-8" id="revenue-share">
            <h2 class="text-xl font-bold text-midnight-deep mb-3 flex items-center gap-2">
                <i class="fas fa-percent text-amber-700"></i>
                Revenue share
            </h2>
            @if($developerProgramFeeSharePercent !== null && $developerProgramFeeSharePercent !== '')
                @php
                    $pctFormatted = rtrim(rtrim(number_format((float) $developerProgramFeeSharePercent, 2, '.', ''), '0'), '.');
                @endphp
                <p class="text-slate-700 leading-relaxed mb-4">
                    Approved partners can earn <strong class="text-midnight-deep">{{ $pctFormatted }}%</strong> of
                    <strong class="text-midnight-deep">{{ $developerProgramFeeShareBaseDescription }}</strong>,
                    subject to program rules, attribution (including your Business ID where required), and your signed agreement.
                </p>
                <ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm sm:text-base">
                    <li><strong>Partner rate:</strong> {{ $pctFormatted }}% of the base described above, for qualifying production volume after approval.</li>
                    <li><strong>Tiers:</strong> Volume bands or custom rates may apply per partner agreement; your onboarding email will confirm if your rate differs from the published default.</li>
                    <li><strong>Exclusions:</strong> Test/sandbox traffic, chargebacks, fraud, program violations, or unattributed volume do not earn share.</li>
                    <li><strong>Currency &amp; tax:</strong> Payouts follow your business account currency; withholding and reporting are as described in your partner terms.</li>
                </ul>
            @else
                <p class="text-sm text-amber-900 mb-4 font-medium">We are finalizing the published partner percentage. Apply now; your agreement will state the exact rate and base.</p>
                <ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm sm:text-base">
                    <li><strong>Partner rate:</strong> Set in your approval email and partner agreement (matches the rate configured in our admin for the program).</li>
                    <li><strong>Base:</strong> A defined portion of {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}’s fee revenue on attributed transactions—not a hidden charge to your client’s customers.</li>
                    <li><strong>Exclusions:</strong> Test/sandbox traffic, chargebacks, fraud, program violations, or unattributed volume do not earn share.</li>
                    <li><strong>Currency &amp; tax:</strong> As described in your partner terms.</li>
                </ul>
            @endif
        </section>

        <section class="mb-14" id="qualify">
            <h2 class="text-2xl font-bold text-midnight-deep mb-4">Who qualifies</h2>
            <ul class="space-y-3 text-slate-600">
                <li class="flex gap-3"><i class="fas fa-check text-primary mt-1"></i><span>Developers, agencies, and freelancers who implement {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} for third-party merchants and can supply a valid Business ID for payouts.</span></li>
                <li class="flex gap-3"><i class="fas fa-check text-primary mt-1"></i><span>Product teams embedding our checkout in WordPress, WooCommerce, or custom applications.</span></li>
                <li class="flex gap-3"><i class="fas fa-check text-primary mt-1"></i><span>Partners who keep attribution honest: the merchant knows who built the integration, and your ID reflects the work you actually performed.</span></li>
            </ul>
        </section>

        <section class="mb-14" id="integrations">
            <h2 class="text-2xl font-bold text-midnight-deep mb-4">What counts as a qualifying integration</h2>
            <div class="space-y-6 text-slate-600 leading-relaxed">
                <div class="card-marketing p-5">
                    <h3 class="font-semibold text-midnight-deep mb-2 flex items-center gap-2"><i class="fab fa-wordpress text-purple-600"></i> WordPress / WooCommerce (plugin + Business ID)</h3>
                    <p>Production use of our official plugin with the merchant’s API keys, plus your <strong class="text-midnight-deep">Developer Business ID</strong> entered in the plugin field so shareable volume can be credited to your business account. Sandbox-only or missing IDs may not qualify.</p>
                </div>
                <div class="card-marketing p-5">
                    <h3 class="font-semibold text-midnight-deep mb-2">REST API &amp; server-to-server</h3>
                    <p>Production API keys on behalf of a registered merchant, with webhooks and patterns documented in our API reference. Partner attribution follows the same principle as the plugin: an explicit, approved identifier—not guesswork from IP or domain alone.</p>
                </div>
                <div class="card-marketing p-5">
                    <h3 class="font-semibold text-midnight-deep mb-2">Hosted checkout &amp; payment links</h3>
                    <p>Flows that complete on our infrastructure with clear merchant identification and any partner metadata we agree during onboarding.</p>
                </div>
            </div>
        </section>

        <section class="mb-14" id="attribution">
            <h2 class="text-2xl font-bold text-midnight-deep mb-4">Attribution &amp; tracking</h2>
            <p class="text-slate-600 leading-relaxed mb-4">
                The <strong class="text-midnight-deep">Business ID field in the WordPress plugin</strong> is the default way we know which developer account to credit, <strong class="text-midnight-deep">after you are approved</strong> into the Developer Program. For other channels, we align on the same idea: a partner or developer identifier you place at integration time, documented in your partner agreement.
            </p>
            <p class="text-slate-600 leading-relaxed">
                If attribution cannot be determined unambiguously—for example, conflicting IDs or missing plugin configuration—that activity may be excluded from payouts until resolved. You agree not to obscure or misrepresent the merchant relationship.
            </p>
        </section>

        <section class="mb-14" id="payouts">
            <h2 class="text-2xl font-bold text-midnight-deep mb-4">Payouts, minimums, and reporting</h2>
            <p class="text-slate-600 leading-relaxed mb-4">
                Your share accrues to the <strong class="text-midnight-deep">business account linked to the Business ID</strong> you used to claim the integration, <strong class="text-midnight-deep">only while you remain an approved program partner</strong>. <strong class="text-midnight-deep">Placeholder:</strong> add your payout schedule (e.g. monthly net-30), minimum balance, withdrawal methods, and where partners see statements in the dashboard or exports.
            </p>
            <p class="text-slate-600 leading-relaxed">
                Partners are responsible for valid tax and banking information on that business account. We may withhold payouts if fraud checks are open or program terms are breached.
            </p>
        </section>

        <section class="mb-14" id="compliance">
            <h2 class="text-2xl font-bold text-midnight-deep mb-4">Compliance &amp; merchant consent</h2>
            <ul class="list-disc pl-5 space-y-2 text-slate-600">
                <li>Merchants must understand they are contracting with {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} for payment processing; your Business ID is for attribution and revenue share, not to hide who processes payments.</li>
                <li>No false claims about rates, approval odds, or regulatory status. Direct merchants to official docs for PCI and data handling.</li>
                <li>Do not store secret API keys in client-side code or public repositories.</li>
                <li>Honor opt-out and data-deletion requests consistent with the merchant’s policies and applicable law.</li>
            </ul>
        </section>

        <section class="mb-14" id="eligibility">
            <h2 class="text-2xl font-bold text-midnight-deep mb-4">Application &amp; ongoing eligibility</h2>
            <p class="text-slate-600 leading-relaxed mb-4">
                Participation starts with the <a href="{{ url('/developers/program/apply') }}" class="text-primary font-medium hover:underline">online application</a> (name, Business ID if available, phone, email, WhatsApp, and Slack/WhatsApp community preference). We review each submission and may request more detail about your integrations. We may accept, defer, or decline applications at our discretion. Approved partners should keep integrations maintained (security updates, API changes) to remain in good standing.
            </p>
            <p class="text-slate-600 leading-relaxed">
                We may update program terms with notice. Continued participation after changes constitutes acceptance unless you exit the program according to the notice period we specify.
            </p>
        </section>

        @include('partials.faq-section', [
            'category' => 'developer-program',
            'title' => 'Developer program FAQs',
            'showAllLink' => true,
            'sectionId' => 'faq',
        ])

    </article>

    <x-marketing.product-cta
        title="Ready to stop working for free?"
        subtitle="Submit the Developer Program application. After approval, your Business ID can start earning on qualifying integrations."
        :primary-url="route('developers.program.apply')"
        primary-label="Apply to the program"
        :secondary-url="route('contact')"
        secondary-label="Contact us"
    />
@endsection
