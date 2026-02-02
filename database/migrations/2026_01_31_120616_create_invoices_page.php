<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Page;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the invoices page if it doesn't exist
        $pageContent = <<<'HTML'
<!-- Hero Section -->
<section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-12 sm:py-16 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-primary/10 rounded-full mb-6">
                <i class="fas fa-file-invoice text-primary text-3xl"></i>
            </div>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4 sm:mb-6">
                Professional Invoices Made Simple
            </h1>
            <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                Create beautiful, professional invoices for your clients. Free to use with integrated payment links for seamless payment collection.
            </p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                <a href="/dashboard/register" class="w-full sm:w-auto bg-primary text-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/90 font-medium text-base sm:text-lg transition-colors shadow-lg">
                    Get Started Free
                </a>
                <a href="#features" class="w-full sm:w-auto bg-white text-primary border-2 border-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/5 font-medium text-base sm:text-lg transition-colors">
                    Learn More
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-12 sm:py-16 md:py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">Everything You Need to Invoice Like a Pro</h2>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">Powerful invoice management with integrated payment collection</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
            <!-- Feature 1 -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-gift text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">100% Free</h3>
                <p class="text-gray-600">
                    Create unlimited invoices at no cost. No hidden fees, no subscription required. Professional invoicing for everyone.
                </p>
            </div>

            <!-- Feature 2 -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-paint-brush text-blue-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Professional Templates</h3>
                <p class="text-gray-600">
                    Beautiful, customizable invoice templates that reflect your brand. Add your logo, colors, and branding.
                </p>
            </div>

            <!-- Feature 3 -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-link text-purple-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Payment Links</h3>
                <p class="text-gray-600">
                    Generate secure payment links for each invoice. Clients can pay directly from the invoice with one click.
                </p>
            </div>

            <!-- Feature 4 -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-envelope text-orange-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Email Invoices</h3>
                <p class="text-gray-600">
                    Send invoices directly to clients via email. Professional email templates with invoice PDF attachments.
                </p>
            </div>

            <!-- Feature 5 -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-file-pdf text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">PDF Export</h3>
                <p class="text-gray-600">
                    Download invoices as professional PDF files. Perfect for record-keeping and offline sharing.
                </p>
            </div>

            <!-- Feature 6 -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-chart-line text-indigo-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Track Payments</h3>
                <p class="text-gray-600">
                    Monitor invoice status in real-time. See which invoices are paid, pending, or overdue at a glance.
                </p>
            </div>

            <!-- Feature 7 -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-mobile-alt text-teal-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Mobile Friendly</h3>
                <p class="text-gray-600">
                    Create and manage invoices from any device. Fully responsive design works perfectly on mobile, tablet, and desktop.
                </p>
            </div>

            <!-- Feature 8 -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-qrcode text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">QR Code Payments</h3>
                <p class="text-gray-600">
                    Generate QR codes for payment links. Clients can scan and pay instantly using their mobile banking apps.
                </p>
            </div>

            <!-- Feature 9 -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-pink-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-bell text-pink-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Payment Notifications</h3>
                <p class="text-gray-600">
                    Get instant notifications when invoices are paid. Webhook support for seamless integration with your systems.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-12 sm:py-16 md:py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">How It Works</h2>
            <p class="text-lg text-gray-600">Get started in minutes</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Step 1 -->
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                    1
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Create Your Invoice</h3>
                <p class="text-gray-600">
                    Add client details, line items, taxes, and discounts. Customize with your branding and notes.
                </p>
            </div>

            <!-- Step 2 -->
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                    2
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Generate Payment Link</h3>
                <p class="text-gray-600">
                    Create a secure payment link for the invoice. Share via email, SMS, or embed on your website.
                </p>
            </div>

            <!-- Step 3 -->
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                    3
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Get Paid Instantly</h3>
                <p class="text-gray-600">
                    Clients pay directly through the link. You receive instant notifications and automatic invoice updates.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Pricing Section -->
<section class="py-12 sm:py-16 md:py-20 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">Simple, Transparent Pricing</h2>
            <p class="text-lg text-gray-600">Invoices are free. Payment links are free by default.</p>
        </div>
        <div class="bg-gradient-to-br from-primary/10 to-primary/5 rounded-2xl p-8 sm:p-12 border-2 border-primary/20">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center px-4 py-2 bg-green-100 text-green-800 rounded-full text-sm font-semibold mb-4">
                    <i class="fas fa-check-circle mr-2"></i> Free Forever
                </div>
                <h3 class="text-3xl font-bold text-gray-900 mb-2">Invoice Creation</h3>
                <p class="text-xl text-gray-600 mb-6">Create unlimited invoices at no cost</p>
            </div>
            <div class="space-y-4 mb-8">
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                    <span class="text-gray-700">Unlimited invoices</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                    <span class="text-gray-700">Professional templates</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                    <span class="text-gray-700">PDF export</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                    <span class="text-gray-700">Email sending</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                    <span class="text-gray-700">Payment link generation</span>
                </div>
            </div>
            <div class="border-t border-gray-200 pt-6">
                <p class="text-sm text-gray-600 text-center mb-4">
                    <strong>Payment Links:</strong> Free by default. Charges may apply when invoice amount exceeds admin-set threshold.
                </p>
                <p class="text-xs text-gray-500 text-center">
                    Standard payment processing fees apply when charges are enabled (typically 1% + ₦100 per transaction)
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Use Cases Section -->
<section class="py-12 sm:py-16 md:py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">Perfect For</h2>
            <p class="text-lg text-gray-600">Whether you're a freelancer, small business, or enterprise</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                <i class="fas fa-user-tie text-primary text-3xl mb-4"></i>
                <h3 class="font-bold text-gray-900 mb-2">Freelancers</h3>
                <p class="text-sm text-gray-600">Professional invoices for your clients</p>
            </div>
            <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                <i class="fas fa-store text-primary text-3xl mb-4"></i>
                <h3 class="font-bold text-gray-900 mb-2">Small Business</h3>
                <p class="text-sm text-gray-600">Streamline your billing process</p>
            </div>
            <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                <i class="fas fa-building text-primary text-3xl mb-4"></i>
                <h3 class="font-bold text-gray-900 mb-2">Agencies</h3>
                <p class="text-sm text-gray-600">Manage multiple client invoices</p>
            </div>
            <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                <i class="fas fa-briefcase text-primary text-3xl mb-4"></i>
                <h3 class="font-bold text-gray-900 mb-2">Consultants</h3>
                <p class="text-sm text-gray-600">Quick invoice generation and payment</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-12 sm:py-16 md:py-20 bg-gradient-to-r from-primary to-primary/90">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-4">Ready to Start Invoicing?</h2>
        <p class="text-lg md:text-xl text-primary-100 mb-8">Create your first professional invoice in minutes. It's completely free!</p>
        <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
            <a href="/dashboard/register" class="w-full sm:w-auto bg-white text-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-gray-100 font-medium text-base sm:text-lg transition-colors shadow-lg">
                Create Free Account
            </a>
            <a href="/dashboard/login" class="w-full sm:w-auto bg-transparent text-white border-2 border-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-white/10 font-medium text-base sm:text-lg transition-colors">
                Already have an account? Login
            </a>
        </div>
    </div>
</section>
HTML;

        Page::firstOrCreate(
            ['slug' => 'products-invoices'],
            [
                'title' => 'Invoices',
                'content' => $pageContent,
                'meta_title' => 'Invoices - Professional Invoice Management',
                'meta_description' => 'Create beautiful, professional invoices for your clients. Free to use with integrated payment links for seamless payment collection.',
                'is_published' => true,
                'order' => 0,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Page::where('slug', 'products-invoices')->delete();
    }
};
