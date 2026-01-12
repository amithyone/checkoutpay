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
                'title' => 'CheckoutPay - Intelligent Payment Gateway',
                'content' => $this->getHomeContent(),
                'meta_title' => 'CheckoutPay - Intelligent Payment Gateway',
                'meta_description' => 'Intelligent payment gateway for businesses. Accept payments with the cheapest rates in the market - just 1% + ₦50 per transaction.',
                'is_published' => true,
                'order' => 1,
            ]
        );

        // Pricing Page Content
        Page::updateOrCreate(
            ['slug' => 'pricing'],
            [
                'title' => 'Pricing - CheckoutPay | Cheapest Payment Gateway in Nigeria',
                'content' => $this->getPricingContent(),
                'meta_title' => 'Pricing - CheckoutPay | Cheapest Payment Gateway in Nigeria',
                'meta_description' => 'The cheapest payment gateway rates in Nigeria. Just 1% + ₦50 per transaction. No hidden fees, no monthly charges.',
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
        return json_encode([
            'hero' => [
                'badge_text' => 'Cheapest Rates in the Market',
                'badge_icon' => 'fas fa-tag',
                'title' => 'Intelligent Payment Gateway',
                'title_highlight' => 'For Your Business',
                'description' => 'Accept payments instantly with the most affordable rates. Fast, secure, and intelligent payment processing.',
                'pricing_text' => 'Just 1% + ₦50 per transaction',
                'cta_primary' => 'Get Started Free',
                'cta_secondary' => 'View Pricing',
            ],
            'features' => [
                'title' => 'Why Choose CheckoutPay?',
                'subtitle' => 'Intelligent payment processing designed for modern businesses',
                'items' => [
                    [
                        'icon' => 'fas fa-brain',
                        'title' => 'Intelligent Processing',
                        'description' => 'Smart payment processing with automatic reconciliation. Reduce manual work and focus on growing your business.',
                    ],
                    [
                        'icon' => 'fas fa-money-bill-wave',
                        'title' => 'Lowest Rates',
                        'description' => 'The cheapest payment gateway in the market. Just 1% + ₦50 per transaction. No hidden fees.',
                    ],
                    [
                        'icon' => 'fas fa-bolt',
                        'title' => 'Fast Integration',
                        'description' => 'Get started in minutes with our simple API or hosted checkout page. No complex setup required.',
                    ],
                    [
                        'icon' => 'fas fa-shield-alt',
                        'title' => 'Secure & Reliable',
                        'description' => 'Bank-level security with encrypted transactions. Trusted by businesses across Nigeria. Intelligent fraud detection.',
                    ],
                    [
                        'icon' => 'fas fa-bell',
                        'title' => 'Instant Notifications',
                        'description' => 'Get instant webhook notifications for every transaction. Stay updated in real-time with intelligent alerts.',
                    ],
                    [
                        'icon' => 'fas fa-chart-line',
                        'title' => 'Dashboard & Analytics',
                        'description' => 'Comprehensive dashboard with transaction history, statistics, and withdrawal management.',
                    ],
                ],
            ],
            'pricing_section' => [
                'title' => 'Simple, Transparent Pricing',
                'subtitle' => 'The cheapest rates in the market. No hidden fees, no surprises.',
                'badge_text' => 'Best Value',
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
                'cta_text' => 'Get Started Now',
                'cta_note' => 'No setup fees. No monthly fees. Pay only for successful transactions.',
                'comparison_badge' => 'Cheapest payment gateway rates in Nigeria',
            ],
            'how_it_works' => [
                'title' => 'How It Works',
                'subtitle' => 'Get started in 4 simple steps',
                'steps' => [
                    ['number' => 1, 'title' => 'Register', 'description' => 'Create your business account in minutes'],
                    ['number' => 2, 'title' => 'Integrate', 'description' => 'Use our API or hosted checkout page'],
                    ['number' => 3, 'title' => 'Accept Payments', 'description' => 'Customers pay via bank transfer'],
                    ['number' => 4, 'title' => 'Get Paid', 'description' => 'Instant verification and notifications'],
                ],
            ],
            'cta' => [
                'title' => 'Ready to Get Started?',
                'description' => 'Join businesses using CheckoutPay - the intelligent payment gateway with the cheapest rates',
                'cta_primary' => 'Create Your Account',
                'cta_secondary' => 'View Pricing',
            ],
            'footer' => [
                'description' => 'The cheapest payment gateway in the market. Just 1% + ₦50 per transaction.',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function getPricingContent(): string
    {
        return json_encode([
            'hero' => [
                'badge_text' => 'Cheapest Rates in Nigeria',
                'badge_icon' => 'fas fa-trophy',
                'title' => 'Simple, Transparent Pricing',
                'description' => 'The most affordable payment gateway in the market. Pay only for successful transactions.',
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
                'description' => 'Join businesses using the cheapest payment gateway in Nigeria',
                'cta_text' => 'Create Your Account',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
