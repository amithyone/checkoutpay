<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Page;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Home Page Content
        Page::updateOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'CheckoutPay',
                'content' => $this->getHomeContent(),
                'meta_title' => 'CheckoutPay',
                'meta_description' => 'Payments for Nigerian businesses.',
                'is_published' => true,
                'order' => 1,
            ]
        );

        // Pricing Page Content
        Page::updateOrCreate(
            ['slug' => 'pricing'],
            [
                'title' => 'Pricing - CheckoutPay | Finest Payment Gateway in Nigeria',
                'content' => $this->getPricingContent(),
                'meta_title' => 'Pricing - CheckoutPay | Finest Payment Gateway in Nigeria',
                'meta_description' => 'The finest payment gateway rates in Nigeria. Just 1% + ₦50 per transaction. No hidden fees, no monthly charges.',
                'is_published' => true,
                'order' => 2,
            ]
        );

        // Privacy Policy (placeholder)
        Page::updateOrCreate(
            ['slug' => 'privacy-policy'],
            [
                'title' => 'Privacy Policy',
                'content' => '<h2>Privacy Policy</h2><p>This privacy policy will be updated with your actual privacy policy content.</p>',
                'meta_title' => 'Privacy Policy - CheckoutPay',
                'meta_description' => 'CheckoutPay privacy policy',
                'is_published' => true,
                'order' => 3,
            ]
        );

        // Terms and Conditions (placeholder)
        Page::updateOrCreate(
            ['slug' => 'terms-and-conditions'],
            [
                'title' => 'Terms and Conditions',
                'content' => '<h2>Terms and Conditions</h2><p>These terms and conditions will be updated with your actual terms content.</p>',
                'meta_title' => 'Terms and Conditions - CheckoutPay',
                'meta_description' => 'CheckoutPay terms and conditions',
                'is_published' => true,
                'order' => 4,
            ]
        );
    }

    private function getHomeContent(): string
    {
        // Return as JSON string - Laravel will auto-cast to array when retrieved
        return json_encode([
            'hero' => [
                'badge_text' => null,
                'badge_icon' => null,
                'title' => 'CheckoutPay',
                'title_highlight' => null,
                'description' => 'Built to stay out of the way while everything else continues.',
                'pricing_text' => '1% + ₦50 per transaction',
                'cta_primary' => 'Get started',
                'cta_secondary' => 'View pricing',
            ],
            'what_it_is' => [
                'title' => null,
                'description' => 'A payment gateway. Built to accept and process payments in Nigeria. Nothing more.',
            ],
            'features' => [
                'title' => null,
                'subtitle' => null,
                'items' => [
                    [
                        'icon' => 'fas fa-credit-card',
                        'title' => 'Accept online payments',
                        'description' => 'Receive NGN payments through bank transfers.',
                    ],
                    [
                        'icon' => 'fas fa-file-invoice',
                        'title' => 'Create invoices',
                        'description' => 'Generate and send invoices to customers.',
                    ],
                    [
                        'icon' => 'fas fa-ticket-alt',
                        'title' => 'Sell tickets',
                        'description' => 'Create and manage event tickets.',
                    ],
                    [
                        'icon' => 'fas fa-key',
                        'title' => 'Manage memberships',
                        'description' => 'Handle recurring membership payments.',
                    ],
                    [
                        'icon' => 'fas fa-home',
                        'title' => 'Handle rentals',
                        'description' => 'Process rental payments and deposits.',
                    ],
                    [
                        'icon' => 'fas fa-list',
                        'title' => 'Manage transactions',
                        'description' => 'Track payments and view transaction history.',
                    ],
                ],
            ],
            'who_its_for' => [
                'title' => null,
                'description' => 'For Nigerian businesses. Small or established. If you need to accept payments, this works.',
            ],
            'security' => [
                'title' => null,
                'description' => 'Transactions are protected. Data is handled securely. That\'s it.',
            ],
            'pricing_section' => [
                'title' => 'Pricing',
                'subtitle' => 'Competitive rates. Clear charges. No surprises.',
                'badge_text' => null,
                'plan_name' => 'Pay As You Go',
                'rate_percentage' => '1%',
                'rate_fixed' => '₦50',
                'rate_description' => 'per successful transaction',
                'included' => [
                    'Unlimited transactions',
                    'API access & documentation',
                    'Hosted checkout page',
                    'Real-time webhook notifications',
                    'Dashboard & analytics',
                    '24/7 support',
                ],
                'examples' => [
                    ['amount' => '₦1,000', 'fee' => '₦60'],
                    ['amount' => '₦5,000', 'fee' => '₦100'],
                    ['amount' => '₦10,000', 'fee' => '₦150'],
                    ['amount' => '₦50,000', 'fee' => '₦550'],
                    ['amount' => '₦100,000', 'fee' => '₦1,050'],
                ],
                'cta_text' => 'Get started',
                'cta_note' => 'No setup fees. No monthly fees. Pay only for successful transactions.',
                'comparison_badge' => null,
            ],
            'how_it_works' => [
                'title' => 'How it works',
                'subtitle' => 'It takes a few minutes.',
                'steps' => [
                    ['number' => 1, 'title' => 'Create an account', 'description' => 'Register your business'],
                    ['number' => 2, 'title' => 'Set up your business', 'description' => 'Configure your settings'],
                    ['number' => 3, 'title' => 'Start accepting payments', 'description' => 'Use our API or hosted checkout page'],
                    ['number' => 4, 'title' => 'Get paid', 'description' => 'Payments are verified automatically'],
                ],
            ],
            'cta' => [
                'title' => 'Create an account',
                'description' => null,
                'cta_primary' => 'Get started',
                'cta_secondary' => 'View pricing',
            ],
            'footer' => [
                'description' => null,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function getPricingContent(): string
    {
        // Return as JSON string - Laravel will auto-cast to array when retrieved
        return json_encode([
            'hero' => [
                'badge_text' => 'Finest Rates in Nigeria',
                'badge_icon' => 'fas fa-trophy',
                'title' => 'Simple, Transparent Pricing',
                'description' => 'Premium payment gateway with competitive rates. Pay only for successful transactions.',
                'rate_percentage' => '1%',
                'rate_fixed' => '₦50',
                'rate_description' => 'per successful transaction',
            ],
            'pricing_card' => [
                'badge_text' => 'Best Value',
                'plan_name' => 'Pay As You Go',
                'description' => 'No setup fees. No monthly fees. Pay only for successful transactions.',
                'rate_percentage' => '1%',
                'rate_fixed' => '₦50',
                'rate_description' => 'per transaction',
                'included' => [
                    [
                        'title' => 'Unlimited Transactions',
                        'description' => 'Process as many payments as you need',
                    ],
                    [
                        'title' => 'API Access & Documentation',
                        'description' => 'Full API access with comprehensive docs',
                    ],
                    [
                        'title' => 'Hosted Checkout Page',
                        'description' => 'Ready-to-use payment page option',
                    ],
                    [
                        'title' => 'Real-time Webhooks',
                        'description' => 'Instant notifications for every transaction',
                    ],
                    [
                        'title' => 'Dashboard & Analytics',
                        'description' => 'Track all transactions and insights',
                    ],
                    [
                        'title' => '24/7 Support',
                        'description' => 'Dedicated support team always available',
                    ],
                    [
                        'title' => 'Secure Transactions',
                        'description' => 'Bank-level security and encryption',
                    ],
                ],
                'examples' => [
                    ['amount' => '₦1,000', 'calculation' => '1% = ₦10 + ₦50', 'fee' => '₦60'],
                    ['amount' => '₦5,000', 'calculation' => '1% = ₦50 + ₦50', 'fee' => '₦100'],
                    ['amount' => '₦10,000', 'calculation' => '1% = ₦100 + ₦50', 'fee' => '₦150'],
                    ['amount' => '₦50,000', 'calculation' => '1% = ₦500 + ₦50', 'fee' => '₦550'],
                    ['amount' => '₦100,000', 'calculation' => '1% = ₦1,000 + ₦50', 'fee' => '₦1,050'],
                ],
                'cta_text' => 'Get Started Now',
                'cta_note' => 'No setup fees. No monthly fees. No hidden charges.',
            ],
            'comparison' => [
                'title' => 'Why We\'re the Cheapest',
                'subtitle' => 'Transparent pricing with no surprises',
                'items' => [
                    [
                        'icon' => 'fas fa-times-circle',
                        'title' => 'No Setup Fees',
                        'description' => 'Start accepting payments immediately with zero setup cost',
                    ],
                    [
                        'icon' => 'fas fa-times-circle',
                        'title' => 'No Monthly Fees',
                        'description' => 'No recurring charges. Pay only for what you use',
                    ],
                    [
                        'icon' => 'fas fa-check-circle',
                        'title' => 'Low Transaction Fees',
                        'description' => 'Just 1% + ₦50 per successful transaction',
                    ],
                ],
            ],
            'faq' => [
                'title' => 'Frequently Asked Questions',
                'items' => [
                    [
                        'question' => 'How are fees calculated?',
                        'answer' => 'Fees are calculated as 1% of the transaction amount plus a fixed ₦50 charge. For example, a ₦10,000 transaction costs ₦150 (1% = ₦100 + ₦50 = ₦150).',
                    ],
                    [
                        'question' => 'Are there any hidden fees?',
                        'answer' => 'No hidden fees. The pricing is transparent - just 1% + ₦50 per successful transaction. No setup fees, no monthly fees, no annual fees.',
                    ],
                    [
                        'question' => 'When do I pay the fees?',
                        'answer' => 'Fees are deducted automatically from each successful transaction. You receive the remaining amount in your account balance.',
                    ],
                    [
                        'question' => 'Is there a minimum transaction amount?',
                        'answer' => 'No minimum transaction amount. However, for very small amounts, we recommend considering the fee structure to ensure profitability.',
                    ],
                    [
                        'question' => 'What payment methods do you support?',
                        'answer' => 'We support bank transfers from all major Nigerian banks. Payments are verified automatically through our intelligent system.',
                    ],
                ],
            ],
            'cta' => [
                'title' => 'Ready to Get Started?',
                'description' => 'Join businesses using the finest payment gateway in Nigeria',
                'cta_text' => 'Create Your Account',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
