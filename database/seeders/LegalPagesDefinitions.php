<?php

namespace Database\Seeders;

/**
 * HTML content for public Legal & Security pages (CheckoutPay).
 * Kept high-level: no internal APIs, email-matching mechanics, or vendor enumeration.
 */
final class LegalPagesDefinitions
{
    public const LAST_UPDATED = 'May 2026';

    /**
     * @return array<string, array{title: string, content: string, meta_title: string, meta_description: string, order: int}>
     */
    public static function all(): array
    {
        $u = self::LAST_UPDATED;

        return [
            'security' => [
                'title' => 'Security',
                'meta_title' => 'Security - CheckoutPay',
                'meta_description' => 'How CheckoutPay protects accounts, payments, and business data.',
                'order' => 10,
                'content' => self::wrap(<<<'HTML'
<p class="text-gray-600 text-sm mb-6"><strong>Last updated:</strong> LAST_UPDATED_PLACEHOLDER</p>
<p>CheckoutPay is built so businesses can collect and manage payments with confidence. This page summarises our security posture in plain language. It is not an exhaustive technical specification.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Our approach</h2>
<p>We apply industry-recognised controls for a cloud payment platform: secure transport, access controls, monitoring, and responsible change management. Security is reviewed on an ongoing basis as threats and standards evolve.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Accounts and authentication</h2>
<p>Business accounts are protected by sign-in credentials you control. Use a strong, unique password, enable any additional protections we offer, and restrict who on your team can access financial settings. Never share one-time codes or recovery links with anyone claiming to be support.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Payments and settlement</h2>
<p>Payment experiences are designed to reduce exposure of sensitive data in browsers and apps. Settlement and safeguarding of funds are handled in line with applicable Nigerian regulatory requirements through our regulated partner arrangements (see our Privacy Policy for the role of <strong>MetroOven Innovations</strong>).</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Your responsibilities</h2>
<ul class="list-disc pl-6 space-y-2 text-gray-700">
<li>Keep devices and browsers up to date.</li>
<li>Verify you are on the genuine CheckoutPay site before signing in.</li>
<li>Report suspected unauthorised access to your account immediately via official support channels.</li>
<li>Train staff on phishing and social engineering.</li>
</ul>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Incident reporting</h2>
<p>If you believe a vulnerability affects CheckoutPay, contact us through the official support route published on this website. Please provide enough detail for us to reproduce and investigate. Do not perform intrusive testing without written authorisation.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Changes</h2>
<p>We may update this page to reflect material improvements or clarifications. The “Last updated” date will change when we do.</p>
HTML
                    , $u),
            ],

            'privacy-policy' => [
                    'title' => 'Privacy Policy',
                    'meta_title' => 'Privacy Policy - CheckoutPay',
                    'meta_description' => 'How CheckoutPay handles personal and business information.',
                    'order' => 11,
                    'content' => self::wrap(<<<'HTML'
<p class="text-gray-600 text-sm mb-6"><strong>Last updated:</strong> LAST_UPDATED_PLACEHOLDER</p>
<p>This Privacy Policy describes how <strong>CheckoutPay</strong> (“we”, “us”) handles information in connection with the services offered through this platform. It is meant to be clear and practical. Where we rely on a regulated partner for certain payment or safeguarding functions, that is explained below.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Who we are</h2>
<p>CheckoutPay provides payment and business tools for merchants and organisations operating in Nigeria. Certain regulated payment, settlement, and customer-protection functions are delivered in cooperation with <strong>MetroOven Innovations</strong>, which operates within the applicable frameworks of the <strong>Central Bank of Nigeria (CBN)</strong> and the <strong>Nigeria Deposit Insurance Corporation (NDIC)</strong> as relevant to its licence and role.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Information we may process</h2>
<p>Depending on how you use CheckoutPay, we may process categories such as: business profile and contact details; account and security-related data; transaction records necessary to operate balances, settlements, and reconciliation; support communications you send us; and limited technical data (for example device or connection metadata) needed to secure the service.</p>
<p>We collect what is reasonably necessary to provide the product, meet legal obligations, and protect users against fraud and abuse. We do not publish a catalogue of internal systems in this document.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">How we use information</h2>
<ul class="list-disc pl-6 space-y-2 text-gray-700">
<li>To create and maintain your account and deliver features you request.</li>
<li>To process payments, fees, refunds, and disputes in line with our Terms.</li>
<li>To detect, prevent, and investigate fraud, security incidents, and misuse.</li>
<li>To comply with law, regulatory requests, and audit requirements.</li>
<li>To communicate important service or policy changes.</li>
</ul>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Legal bases and retention</h2>
<p>Where the law requires a legal basis for processing, we rely on combinations of contract performance, legal obligation, legitimate interests (such as securing the platform), and consent where appropriate. We retain information for as long as needed to provide the service, meet regulatory and tax record-keeping duties, and resolve disputes, then delete or anonymise it in line with internal schedules.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Sharing of information</h2>
<p>We do not sell personal data. We may share information with professional advisers, law enforcement or regulators when required, and with service providers under confidentiality and security obligations who help us host or operate the platform. Payment safeguarding and related disclosures to regulators may flow through our regulated partner structure as required by law.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Your choices and rights</h2>
<p>Subject to applicable law, you may request access, correction, or deletion where appropriate, object to certain processing, or escalate concerns. Use the official support channel on this website. We will respond within reasonable timelines and may need to verify your identity.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">International transfers</h2>
<p>Where data is processed outside Nigeria, we take steps consistent with applicable law to ensure appropriate safeguards.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Children</h2>
<p>CheckoutPay is a business service and is not directed at children. If you believe a minor has provided personal data, contact us and we will take appropriate steps.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Updates</h2>
<p>We may revise this Privacy Policy from time to time. Material changes will be reflected by updating the “Last updated” date and, where appropriate, additional notice through the product or website.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Contact</h2>
<p>For privacy requests, use the contact or support options published on this website.</p>
HTML
                        , $u),
            ],

            'terms-and-conditions' => [
                        'title' => 'Terms and Conditions',
                        'meta_title' => 'Terms and Conditions - CheckoutPay',
                        'meta_description' => 'Terms governing use of CheckoutPay services.',
                        'order' => 12,
                        'content' => self::wrap(<<<'HTML'
<p class="text-gray-600 text-sm mb-6"><strong>Last updated:</strong> LAST_UPDATED_PLACEHOLDER</p>
<p>These Terms and Conditions (“Terms”) govern your access to and use of <strong>CheckoutPay</strong> and related services (the “Services”). By registering for or using the Services, you agree to these Terms. If you do not agree, do not use the Services.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Eligibility and accounts</h2>
<p>The Services are intended for businesses and organisations that can lawfully enter into contracts in Nigeria. You are responsible for the accuracy of information you provide and for maintaining the confidentiality of your credentials. You must promptly notify us of suspected unauthorised use.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">The Services</h2>
<p>CheckoutPay provides payment acceptance, reconciliation, and related business tools as described on our website and in-product documentation. Features may change over time. We may suspend or limit access where necessary for security, compliance, or operational reasons.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Regulatory context</h2>
<p>Certain payment, safeguarding, and settlement activities are conducted in line with Nigerian law through arrangements involving <strong>MetroOven Innovations</strong>, operating within applicable <strong>CBN</strong> and <strong>NDIC</strong> frameworks as relevant to its licence. Your use of payment features constitutes acknowledgement that those channels may apply.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Fees</h2>
<p>Fees, if any, are as published on our pricing or fee schedule at the time of the transaction unless otherwise agreed in writing. Taxes, where applicable, are your responsibility unless stated otherwise.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Acceptable use</h2>
<p>You will not use the Services for unlawful, deceptive, or abusive purposes, including fraud, money laundering, financing of illegal activity, or infringement of third-party rights. We may investigate violations and cooperate with regulators and law enforcement.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Disclaimers</h2>
<p>To the fullest extent permitted by law, the Services are provided on an “as is” and “as available” basis. We do not guarantee uninterrupted or error-free operation. Some jurisdictions do not allow certain disclaimers; in those cases, our liability is limited to the maximum extent permitted.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Limitation of liability</h2>
<p>Subject to applicable law, CheckoutPay’s aggregate liability arising out of or relating to the Services is limited to the fees you paid to us in the three (3) months preceding the claim (or, if none, a modest statutory cap where applicable). We are not liable for indirect, consequential, or punitive damages except where such exclusion is prohibited.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Indemnity</h2>
<p>You will defend and indemnify CheckoutPay against claims arising from your misuse of the Services, violation of these Terms, or violation of third-party rights, except to the extent caused by our wilful misconduct.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Suspension and termination</h2>
<p>We may suspend or terminate access for breach of these Terms, risk to the platform, or legal requirement. You may stop using the Services at any time; certain provisions survive termination (for example, fees owed, liability limits, and dispute terms).</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Governing law and disputes</h2>
<p>These Terms are governed by the laws of the <strong>Federal Republic of Nigeria</strong>. Parties will first attempt to resolve disputes in good faith through support channels. Where mandatory law prescribes a venue or process, that law prevails.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Changes</h2>
<p>We may modify these Terms. We will post the updated version and adjust the “Last updated” date. Continued use after changes become effective constitutes acceptance, except where a stricter consent rule applies by law.</p>
HTML
                            , $u),
            ],

            'esg-policy' => [
                            'title' => 'ESG Policy',
                            'meta_title' => 'ESG Policy - CheckoutPay',
                            'meta_description' => 'Environmental, social, and governance commitments for CheckoutPay.',
                            'order' => 13,
                            'content' => self::wrap(<<<'HTML'
<p class="text-gray-600 text-sm mb-6"><strong>Last updated:</strong> LAST_UPDATED_PLACEHOLDER</p>
<p>CheckoutPay recognises that environmental, social, and governance (“ESG”) factors matter to customers, partners, and regulators. This policy sets out our commitments at a high level. It is not a sustainability report with quantitative metrics; those may be published separately where appropriate.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Environment</h2>
<p>We favour efficient infrastructure and responsible use of computing resources to limit unnecessary energy consumption. We encourage digital delivery of receipts and records to reduce paper waste. We comply with applicable environmental laws in the jurisdictions where we operate.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Social</h2>
<p>Our mission includes expanding safe access to payment tools for legitimate Nigerian businesses. We support financial integrity through fraud awareness, clear communications, and cooperation with lawful regulatory requests. We treat customers and employees with respect and do not tolerate discrimination or harassment.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Governance</h2>
<p>We maintain internal controls appropriate to a financial technology platform, including segregation of duties where practicable, approval processes for sensitive changes, and documented policies for security and compliance. Payment safeguarding and regulatory alignment are supported through our relationship with <strong>MetroOven Innovations</strong> under applicable <strong>CBN</strong> and <strong>NDIC</strong> frameworks as relevant to its licence.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Reporting concerns</h2>
<p>Ethics or compliance concerns may be raised through official support or whistle-blowing channels we publish. Retaliation against good-faith reporters is prohibited.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Review</h2>
<p>We review this policy periodically and update the “Last updated” date when we make substantive changes.</p>
HTML
                                , $u),
            ],

            'fraud-awareness' => [
                                'title' => 'Fraud Awareness',
                                'meta_title' => 'Fraud Awareness - CheckoutPay',
                                'meta_description' => 'Protect your business from payment fraud and social engineering.',
                                'order' => 14,
                                'content' => self::wrap(<<<'HTML'
<p class="text-gray-600 text-sm mb-6"><strong>Last updated:</strong> LAST_UPDATED_PLACEHOLDER</p>
<p>Fraudsters target businesses as well as consumers. Staying alert protects your funds, your customers, and the integrity of the payment system. This page lists common risks and practical steps. It does not replace law enforcement or bank security advice.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Common scams</h2>
<ul class="list-disc pl-6 space-y-2 text-gray-700">
<li><strong>Phishing:</strong> Fake emails, SMS, or websites that imitate CheckoutPay or your bank to steal passwords or OTPs.</li>
<li><strong>CEO / vendor fraud:</strong> Urgent messages pretending to be a director or supplier asking for a change of bank details.</li>
<li><strong>Overpayment and refund fraud:</strong> A payer sends too much and pressures you to refund outside normal channels.</li>
<li><strong>Account takeover:</strong> Weak passwords or reused credentials allow criminals to access your dashboard.</li>
</ul>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Protective habits</h2>
<ul class="list-disc pl-6 space-y-2 text-gray-700">
<li>Always sign in via the official CheckoutPay website or verified app distribution channels.</li>
<li>Verify payment instructions independently (call the known number) before changing settlement details.</li>
<li>Use strong, unique passwords and limit admin access to trusted staff.</li>
<li>Never share OTPs, PINs, or recovery codes with “support” who contacted you unsolicited.</li>
<li>Monitor balances and settlement reports regularly; report anomalies immediately.</li>
</ul>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Regulated ecosystem</h2>
<p>CheckoutPay operates within Nigeria’s regulated payments environment. Customer funds and settlement integrity are supported through arrangements with <strong>MetroOven Innovations</strong>, which operates within applicable <strong>CBN</strong> and <strong>NDIC</strong> frameworks as relevant to its licence. That does not remove your obligation to verify counterparties and invoices on your side.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">If you suspect fraud</h2>
<p>Contact us immediately through the official support channel on this website. Preserve evidence (screenshots, headers, transaction references). Report criminal activity to the <strong>Nigeria Police Force</strong> and your bank as appropriate. You may also use public reporting channels maintained by Nigerian financial authorities for certain categories of complaint.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Education</h2>
<p>We may publish alerts or guidance as new threats emerge. Check this page periodically for updates.</p>
HTML
                                    , $u),
            ],
        ];
    }

    private static function wrap(string $html, string $lastUpdated): string
    {
        return str_replace('LAST_UPDATED_PLACEHOLDER', htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'), $html);
    }

    public static function syncToDatabase(): void
    {
        foreach (self::all() as $slug => $data) {
            \App\Models\Page::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'meta_title' => $data['meta_title'],
                    'meta_description' => $data['meta_description'],
                    'is_published' => true,
                    'order' => $data['order'],
                ]
            );
        }
    }
}
