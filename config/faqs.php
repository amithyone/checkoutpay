<?php

return [
    'categories' => [
        'payment-gateway' => [
            'label' => 'Payment gateway',
            'slug' => 'payment-gateway',
            'pages' => ['/', '/pricing'],
        ],
        'wordpress-plugin' => [
            'label' => 'WordPress & WooCommerce',
            'slug' => 'wordpress-plugin',
            'pages' => ['/wordpress-plugin'],
        ],
        'api' => [
            'label' => 'API & webhooks',
            'slug' => 'api',
            'pages' => ['/api-docs', '/developers'],
        ],
        'developer-program' => [
            'label' => 'Developer program',
            'slug' => 'developer-program',
            'pages' => ['/developers', '/developers/program'],
        ],
        'whatsapp-wallet' => [
            'label' => 'WhatsApp Wallet',
            'slug' => 'whatsapp-wallet',
            'pages' => ['/whatsapp-wallet'],
        ],
        'invoices-billing' => [
            'label' => 'Invoices & billing',
            'slug' => 'invoices-billing',
            'pages' => ['/products/invoices', '/products'],
        ],
        'payouts-collections' => [
            'label' => 'Payouts & collections',
            'slug' => 'payouts-collections',
            'pages' => ['/payout', '/collections'],
        ],
        'security-compliance' => [
            'label' => 'Security & compliance',
            'slug' => 'security-compliance',
            'pages' => ['/security', '/support'],
        ],
        'support' => [
            'label' => 'Support',
            'slug' => 'support',
            'pages' => ['/support'],
        ],
    ],

    'items' => [
        // Payment gateway (8)
        [
            'category' => 'payment-gateway',
            'q' => 'What is CheckoutPay and who is it for in Nigeria?',
            'a' => 'CheckoutPay is a payment gateway built for Nigerian businesses that need to accept NGN online payments—especially bank transfers—with reliable matching, virtual accounts, and clear fees. It suits e-commerce, SaaS, services, events, and any merchant selling in Naira.',
            'keywords' => ['payment gateway Nigeria', 'accept payments', 'NGN'],
        ],
        [
            'category' => 'payment-gateway',
            'q' => 'How does bank transfer payment matching work?',
            'a' => 'When a customer pays to your assigned virtual account or reference, CheckoutPay reconciles the incoming transfer against the open payment. Successful matches update order or invoice status and fire webhooks to your store or app automatically.',
            'keywords' => ['bank transfer matching', 'virtual account', 'reconciliation'],
        ],
        [
            'category' => 'payment-gateway',
            'q' => 'What are CheckoutPay fees for merchants in Nigeria?',
            'a' => 'Pricing is transparent: typically 1% plus ₦50 per successful transaction with no setup or monthly fees on standard pay-as-you-go. See {pricing_url} for current rates.',
            'keywords' => ['fees', 'cheapest payment gateway Nigeria', 'pricing'],
        ],
        [
            'category' => 'payment-gateway',
            'q' => 'How long does settlement take after a successful payment?',
            'a' => 'Confirmed payments credit your CheckoutPay business balance according to your account rules and risk profile. You can withdraw to Nigerian bank accounts from the business dashboard when funds are available.',
            'keywords' => ['settlement', 'payout timing'],
        ],
        [
            'category' => 'payment-gateway',
            'q' => 'Do I need a Nigerian business to use CheckoutPay?',
            'a' => 'Merchants register a business profile and complete verification (KYC) as required for compliance. The platform is designed for legitimate Nigerian and Nigeria-facing businesses accepting local bank payments.',
            'keywords' => ['KYC', 'onboarding', 'business account'],
        ],
        [
            'category' => 'payment-gateway',
            'q' => 'Can I run multiple stores or brands on one account?',
            'a' => 'Your business dashboard can manage websites, API keys, and products under one verified business. For separate legal entities or accounting, create additional business accounts.',
            'keywords' => ['multi-store', 'websites'],
        ],
        [
            'category' => 'payment-gateway',
            'q' => 'How is CheckoutPay different from Paystack or Flutterwave?',
            'a' => 'CheckoutPay focuses on affordable bank-transfer-first checkout, transparent fees, and tools like WhatsApp Wallet and a developer revenue-share program. The best choice depends on your stack, volume, and whether you need our specific integrations.',
            'keywords' => ['Paystack', 'Flutterwave', 'comparison'],
        ],
        [
            'category' => 'payment-gateway',
            'q' => 'How do I get started accepting payments?',
            'a' => 'Create a business account at {register_url}, complete verification, add your website or integration (API, WooCommerce plugin, or hosted checkout), and go live with production API keys.',
            'keywords' => ['get started', 'sign up'],
        ],

        // WordPress plugin (8)
        [
            'category' => 'wordpress-plugin',
            'q' => 'Is there an official WordPress payment plugin for Nigeria?',
            'a' => 'Yes. CheckoutPay publishes the COPN Payment Gateway for Nigerian Businesses—a WooCommerce extension for bank transfer checkout with virtual accounts and automatic order updates. Download it from {wordpress_url}.',
            'keywords' => ['WordPress payment plugin Nigeria', 'WooCommerce', 'COPN'],
        ],
        [
            'category' => 'wordpress-plugin',
            'q' => 'How do I install the CheckoutPay WooCommerce plugin?',
            'a' => 'Download the ZIP from {wordpress_url}, go to WordPress Admin → Plugins → Add New → Upload, activate the plugin, then open WooCommerce → Settings → Payments → CheckoutPay and enter your API URL and API key from your CheckoutPay business dashboard.',
            'keywords' => ['install', 'upload plugin'],
        ],
        [
            'category' => 'wordpress-plugin',
            'q' => 'Does the plugin work with WooCommerce checkout blocks?',
            'a' => 'Yes. The plugin supports WooCommerce Cart and Checkout blocks. After enabling the gateway, update WooCommerce and clear cache if the method does not appear immediately.',
            'keywords' => ['blocks', 'HPOS', 'checkout blocks'],
        ],
        [
            'category' => 'wordpress-plugin',
            'q' => 'How do webhooks update WooCommerce orders?',
            'a' => 'Configure the webhook URL shown in plugin settings inside your CheckoutPay dashboard. When a bank transfer is confirmed, CheckoutPay notifies your store and the plugin marks the order paid.',
            'keywords' => ['webhook', 'order status'],
        ],
        [
            'category' => 'wordpress-plugin',
            'q' => 'Can I test the plugin before going live?',
            'a' => 'Use test API credentials from your business dashboard in a staging site. Sandbox or test traffic does not count toward developer revenue share—only qualifying production volume does.',
            'keywords' => ['test mode', 'sandbox'],
        ],
        [
            'category' => 'wordpress-plugin',
            'q' => 'Why is CheckoutPay missing at WooCommerce checkout?',
            'a' => 'Enable the gateway under WooCommerce → Payments, save settings, and confirm both API URL and API key are correct. The store must use a supported WooCommerce and PHP version listed on {wordpress_url}.',
            'keywords' => ['troubleshooting', 'missing gateway'],
        ],
        [
            'category' => 'wordpress-plugin',
            'q' => 'What WordPress and WooCommerce versions are required?',
            'a' => 'See the Requirements section on {wordpress_url} for the current minimum WordPress, WooCommerce, and PHP versions bundled with each plugin release.',
            'keywords' => ['requirements', 'PHP version'],
        ],
        [
            'category' => 'wordpress-plugin',
            'q' => 'Can my developer earn revenue share from my WooCommerce store?',
            'a' => 'Yes, if they are approved in the CheckoutPay Developer Program and add their Business ID in the plugin developer field. Merchants keep their own API keys; the ID only attributes share to the integrator. Learn more at {program_url}.',
            'keywords' => ['developer Business ID', 'agency'],
        ],

        // API (8)
        [
            'category' => 'api',
            'q' => 'Does CheckoutPay offer a payment gateway API in Nigeria?',
            'a' => 'Yes. Merchants use REST APIs to create payments, virtual accounts, and subscriptions, with webhook callbacks for success and failure. Start at {api_docs_url} and complete signup for full keys and reference docs in the business dashboard.',
            'keywords' => ['payment gateway API Nigeria', 'REST API'],
        ],
        [
            'category' => 'api',
            'q' => 'How do I authenticate API requests?',
            'a' => 'Generate API keys from your CheckoutPay business dashboard. Send the secret key in the Authorization header as documented in your account API reference. Never expose secrets in client-side mobile or browser code.',
            'keywords' => ['API key', 'authentication'],
        ],
        [
            'category' => 'api',
            'q' => 'What webhook events should my app handle?',
            'a' => 'At minimum, handle payment success and failure events so you can fulfill orders or grant access. Verify webhook signatures when provided, respond quickly with HTTP 200, and make handlers idempotent using your payment reference IDs.',
            'keywords' => ['webhooks', 'events'],
        ],
        [
            'category' => 'api',
            'q' => 'How do virtual account payments work via API?',
            'a' => 'Create a payment with amount and customer metadata; CheckoutPay returns account details or payment instructions. When the customer transfers from a Nigerian bank, matching completes the payment and triggers your webhook.',
            'keywords' => ['virtual account API', 'bank transfer'],
        ],
        [
            'category' => 'api',
            'q' => 'Is there a sandbox for developers?',
            'a' => 'Use test credentials from your dashboard for integration testing. Sandbox transactions are for quality assurance only and do not qualify for developer revenue share.',
            'keywords' => ['sandbox', 'test keys'],
        ],
        [
            'category' => 'api',
            'q' => 'How should I handle duplicate webhook deliveries?',
            'a' => 'Store processed event IDs or payment references and ignore repeats. Design fulfillment logic to be idempotent so the same successful payment never ships twice.',
            'keywords' => ['idempotency', 'retries'],
        ],
        [
            'category' => 'api',
            'q' => 'Can I integrate Laravel, mobile apps, or custom checkout?',
            'a' => 'Yes. Any stack that can call HTTPS APIs and receive webhooks can integrate. Agencies in the Developer Program should align partner attribution during onboarding at {program_url}.',
            'keywords' => ['Laravel', 'mobile', 'custom integration'],
        ],
        [
            'category' => 'api',
            'q' => 'Where is the full API reference?',
            'a' => 'Public overview lives at {api_docs_url}. After registration, open API documentation inside your business dashboard for endpoints, payloads, and examples tied to your environment.',
            'keywords' => ['documentation', 'reference'],
        ],

        // Developer program (8)
        [
            'category' => 'developer-program',
            'q' => 'What is the CheckoutPay Developer Program in Nigeria?',
            'a' => 'It is a partner program for developers and agencies who integrate CheckoutPay for clients. Approved partners can earn ongoing revenue share on qualifying production volume—not just a one-off project fee.',
            'keywords' => ['developer payment gateway program Nigeria', 'revenue share'],
        ],
        [
            'category' => 'developer-program',
            'q' => 'Do I need to apply before earning revenue share?',
            'a' => 'Yes. Submit the application at {apply_url} and be approved. Until you are in the program, we do not accrue developer share even if you already use our API or plugin.',
            'keywords' => ['apply', 'approval'],
        ],
        [
            'category' => 'developer-program',
            'q' => 'How is this different from integrating Paystack or Flutterwave for years?',
            'a' => 'Many gateways treat developers as implementation help while the brand keeps long-term processing value. CheckoutPay is built so approved partners with valid attribution can earn a defined share on eligible volume.',
            'keywords' => ['Paystack', 'Flutterwave', 'agency'],
        ],
        [
            'category' => 'developer-program',
            'q' => 'Where do I put my Business ID on a client WordPress site?',
            'a' => 'In the WooCommerce CheckoutPay gateway settings, use the developer or partner Business ID field. The merchant still uses their own API credentials; your ID only routes revenue share to your account.',
            'keywords' => ['Business ID', 'WordPress'],
        ],
        [
            'category' => 'developer-program',
            'q' => 'I build Laravel or mobile apps—how do I get paid without WordPress?',
            'a' => 'The WordPress field is the first simple pattern we ship. The same principle applies elsewhere: an explicit partner identifier agreed at onboarding (metadata, referral codes, or documented API patterns). Contact us via {contact_url} with your stack before go-live.',
            'keywords' => ['Laravel', 'mobile', 'API attribution'],
        ],
        [
            'category' => 'developer-program',
            'q' => 'Does my client pay extra so I get paid?',
            'a' => 'Your share comes from the program economics on eligible processing—not from a hidden surcharge on the shopper. Rates and terms are described on {program_url} and in your partner agreement.',
            'keywords' => ['commission', 'fees'],
        ],
        [
            'category' => 'developer-program',
            'q' => 'Do test or sandbox transactions count toward my share?',
            'a' => 'No. Revenue share applies to qualifying production volume under program rules with valid attribution. Test keys are for integration only.',
            'keywords' => ['sandbox', 'production'],
        ],
        [
            'category' => 'developer-program',
            'q' => 'Can one agency Business ID cover many client stores?',
            'a' => 'Usually yes: one partner business account and one Business ID across many stores, each using the plugin or API with your attribution field. Separate legal entities may need separate business accounts.',
            'keywords' => ['agency', 'multiple clients'],
        ],

        // WhatsApp wallet (8)
        [
            'category' => 'whatsapp-wallet',
            'q' => 'What is CheckoutPay WhatsApp Wallet?',
            'a' => 'WhatsApp Wallet lets consumers send money on WhatsApp or to any Nigerian bank account, with PIN-protected confirmations on secure CheckoutPay pages. It is powered by the same backend as merchant tools on check-outpay.com.',
            'keywords' => ['WhatsApp wallet', 'send money Nigeria'],
        ],
        [
            'category' => 'whatsapp-wallet',
            'q' => 'Is WhatsApp Wallet the same as CheckoutNow?',
            'a' => 'CheckoutNow is the consumer wallet brand experience; WhatsApp Wallet is the channel many users start from. Business tools and gateway features live on CheckoutPay for merchants.',
            'keywords' => ['CheckoutNow', 'consumer'],
        ],
        [
            'category' => 'whatsapp-wallet',
            'q' => 'Can I send money to someone without a wallet?',
            'a' => 'Yes. Use bank transfer to their account details. For wallet-to-wallet credits on WhatsApp, the recipient typically opens WALLET once on WhatsApp to receive P2P credits.',
            'keywords' => ['bank transfer', 'P2P'],
        ],
        [
            'category' => 'whatsapp-wallet',
            'q' => 'How do merchants accept payments via WhatsApp?',
            'a' => 'Register a CheckoutPay business, obtain an API key, and request WhatsApp wallet API enablement on your account. Integrate pay/start flows from your server as documented for partners.',
            'keywords' => ['merchant', 'WhatsApp payments API'],
        ],
        [
            'category' => 'whatsapp-wallet',
            'q' => 'Is WhatsApp Wallet safe?',
            'a' => 'Outgoing transfers require your wallet PIN on HTTPS confirmation pages. Merchants cannot silently debit customers without the same confirmation pattern used for charges.',
            'keywords' => ['security', 'PIN'],
        ],
        [
            'category' => 'whatsapp-wallet',
            'q' => 'What can I pay for with WhatsApp Wallet?',
            'a' => 'Depending on enabled features, users may send P2P transfers, pay approved merchants, buy airtime or data (VTU), and use partner payment links—availability varies by account and region.',
            'keywords' => ['VTU', 'bills', 'airtime'],
        ],
        [
            'category' => 'whatsapp-wallet',
            'q' => 'How do I start as a consumer?',
            'a' => 'Message the official WhatsApp contact shown on the marketing site or open the wallet web app link to fund your wallet and set your PIN.',
            'keywords' => ['get started', 'consumer'],
        ],
        [
            'category' => 'whatsapp-wallet',
            'q' => 'Where do I get help with a WhatsApp transfer?',
            'a' => 'Visit {support_url} or {contact_url} with your transaction reference. Never share your PIN or OTP with anyone claiming to be support.',
            'keywords' => ['support', 'help'],
        ],

        // Invoices & billing (6)
        [
            'category' => 'invoices-billing',
            'q' => 'Can I send invoices and get paid by bank transfer in Nigeria?',
            'a' => 'Yes. Create invoices in your business dashboard, share the payment link, and customers pay via bank transfer. CheckoutPay matches payment and updates invoice status automatically.',
            'keywords' => ['invoice payment Nigeria', 'bank transfer invoice'],
        ],
        [
            'category' => 'invoices-billing',
            'q' => 'Do invoices support partial payments or reminders?',
            'a' => 'Invoice workflows support standard payment tracking from your dashboard. Configure amounts and follow up with customers using the shareable link until paid.',
            'keywords' => ['partial payment', 'reminders'],
        ],
        [
            'category' => 'invoices-billing',
            'q' => 'Can customers download a PDF invoice?',
            'a' => 'Customers can view and download invoice PDFs from the public invoice link where enabled for your business.',
            'keywords' => ['PDF', 'download'],
        ],
        [
            'category' => 'invoices-billing',
            'q' => 'How do invoice fees compare to checkout fees?',
            'a' => 'Successful invoice payments use the same transparent gateway pricing as other CheckoutPay collections—see {pricing_url}.',
            'keywords' => ['invoice fees', 'pricing'],
        ],
        [
            'category' => 'invoices-billing',
            'q' => 'Can I sell tickets or memberships on the same account?',
            'a' => 'Yes. CheckoutPay includes tickets, memberships, rentals, and hosted checkout alongside invoicing under one business account.',
            'keywords' => ['tickets', 'memberships', 'products'],
        ],
        [
            'category' => 'invoices-billing',
            'q' => 'Do invoices fire webhooks to my system?',
            'a' => 'Paid invoices follow the same payment and webhook model as API-created payments where configured, so your back office can automate fulfillment.',
            'keywords' => ['webhook', 'automation'],
        ],

        // Payouts & collections (6)
        [
            'category' => 'payouts-collections',
            'q' => 'What are Collections on CheckoutPay?',
            'a' => 'Collections let you receive payments via payment links and checkout flows, including bank transfer, without building a full custom cart.',
            'keywords' => ['collections', 'payment links Nigeria'],
        ],
        [
            'category' => 'payouts-collections',
            'q' => 'How do business payouts work?',
            'a' => 'From your available balance, request withdrawal to verified Nigerian bank accounts. Payout timing depends on risk review and banking rails.',
            'keywords' => ['payout', 'withdrawal'],
        ],
        [
            'category' => 'payouts-collections',
            'q' => 'Is there a minimum payout amount?',
            'a' => 'Minimum withdrawal amounts and fees, if any, are shown in your business dashboard payout section.',
            'keywords' => ['minimum payout'],
        ],
        [
            'category' => 'payouts-collections',
            'q' => 'Can I pay suppliers or staff from my balance?',
            'a' => 'Yes. Use payout features to send funds to Nigerian bank accounts you add and verify according to compliance rules.',
            'keywords' => ['suppliers', 'payroll'],
        ],
        [
            'category' => 'payouts-collections',
            'q' => 'How do collections differ from the payment API?',
            'a' => 'Collections are productized payment links and flows in the dashboard. The API gives full control for custom apps; both settle into your business balance.',
            'keywords' => ['API vs collections'],
        ],
        [
            'category' => 'payouts-collections',
            'q' => 'What happens if a payout fails?',
            'a' => 'Failed payouts are reversed or marked for retry depending on the failure reason. Check your dashboard and contact {support_url} with the payout reference.',
            'keywords' => ['failed payout', 'bank'],
        ],

        // Security & compliance (6)
        [
            'category' => 'security-compliance',
            'q' => 'How does CheckoutPay protect merchant and customer data?',
            'a' => 'We use HTTPS everywhere, secure credential storage for API keys, and access controls in the business dashboard. See {support_url} and our security page for practices and reporting channels.',
            'keywords' => ['security', 'data protection'],
        ],
        [
            'category' => 'security-compliance',
            'q' => 'What KYC documents do businesses need?',
            'a' => 'Verification requirements depend on your business type and volume. The dashboard guides you through identity and business documents needed to unlock full features.',
            'keywords' => ['KYC', 'verification'],
        ],
        [
            'category' => 'security-compliance',
            'q' => 'How should I report suspected fraud?',
            'a' => 'Use our fraud awareness resources and contact {contact_url} immediately with transaction references. Never move money based on unsolicited WhatsApp or email instructions.',
            'keywords' => ['fraud', 'scam'],
        ],
        [
            'category' => 'security-compliance',
            'q' => 'Are API keys revocable?',
            'a' => 'Yes. Rotate or revoke keys from the business dashboard if exposed. Update your live integrations promptly after rotation.',
            'keywords' => ['API key rotation'],
        ],
        [
            'category' => 'security-compliance',
            'q' => 'Where is customer card or bank data stored?',
            'a' => 'CheckoutPay is bank-transfer-first for many flows. Sensitive operations use secure pages; merchants should not store full account passwords or PINs.',
            'keywords' => ['PCI', 'bank data'],
        ],
        [
            'category' => 'security-compliance',
            'q' => 'Is CheckoutPay licensed or regulated?',
            'a' => 'We operate under applicable Nigerian financial services and partnership arrangements required for our products. Specific licensing details are available on request for enterprise merchants.',
            'keywords' => ['regulated', 'compliance Nigeria'],
        ],

        // Support (6)
        [
            'category' => 'support',
            'q' => 'How do I contact CheckoutPay support?',
            'a' => 'Visit {support_url} for help articles and channels, or {contact_url} for direct enquiries. Include your business ID and transaction reference for faster help.',
            'keywords' => ['support', 'contact'],
        ],
        [
            'category' => 'support',
            'q' => 'What are your support hours?',
            'a' => 'We aim to respond to merchant and developer issues as quickly as possible. Critical payment outages receive priority; exact hours may vary by channel listed on {support_url}.',
            'keywords' => ['hours', 'response time'],
        ],
        [
            'category' => 'support',
            'q' => 'Where can I check if CheckoutPay is down?',
            'a' => 'See the status page linked from {support_url} for platform availability and incident updates.',
            'keywords' => ['status', 'downtime'],
        ],
        [
            'category' => 'support',
            'q' => 'I need help with the WordPress plugin—where do I start?',
            'a' => 'Read the troubleshooting section on {wordpress_url} and the WordPress FAQ category at /faqs#wordpress-plugin. Still stuck? Contact support with your WooCommerce version and webhook URL.',
            'keywords' => ['WordPress help', 'plugin support'],
        ],
        [
            'category' => 'support',
            'q' => 'How do developers get integration help?',
            'a' => 'Review {api_docs_url}, apply to {program_url} if you want revenue share, and email support with API request IDs from your dashboard logs.',
            'keywords' => ['developer support', 'integration'],
        ],
        [
            'category' => 'support',
            'q' => 'Can I request a feature or partnership?',
            'a' => 'Yes. Use {contact_url} and describe your use case, expected volume, and technical stack. Enterprise and agency partnerships are welcome.',
            'keywords' => ['partnership', 'feature request'],
        ],
    ],
];
