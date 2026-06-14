<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\MarketingPricing;
use App\Support\Seo;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /business',
            'Disallow: /wallet/',
            'Disallow: /api/',
            'Disallow: /pay',
            'Disallow: /my-account',
            '',
            'User-agent: GPTBot',
            'Allow: /',
            'Allow: /llms.txt',
            'Allow: /faqs',
            '',
            'User-agent: Claude-Web',
            'Allow: /',
            'Allow: /llms.txt',
            'Allow: /faqs',
            '',
            'User-agent: Google-Extended',
            'Allow: /',
            '',
            'Sitemap: '.$base.'/sitemap.xml',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $paths = config('seo.sitemap_paths', ['/']);
        $excludeSlugs = config('seo.sitemap_exclude_page_slugs', []);
        $priorities = config('seo.sitemap_priority', []);

        $dedicatedPaths = collect($paths)->map(fn ($p) => ltrim($p, '/'))->filter()->values()->all();

        foreach (Page::query()->where('is_published', true)->get(['slug', 'updated_at']) as $page) {
            $slug = $page->slug;
            if (! is_string($slug) || $slug === '' || in_array($slug, $excludeSlugs, true)) {
                continue;
            }
            if (in_array($slug, $dedicatedPaths, true)) {
                continue;
            }
            $path = Seo::publicPathForPageSlug($slug);
            if (! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths);

        $lastmods = [];
        foreach (Page::query()->where('is_published', true)->get(['slug', 'updated_at']) as $page) {
            if ($page->updated_at) {
                $lastmods[Seo::publicPathForPageSlug((string) $page->slug)] = $page->updated_at->toAtomString();
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($paths as $path) {
            $loc = htmlspecialchars($base.$path, ENT_XML1);
            $priority = $priorities[$path] ?? ($path === '/' ? '1.0' : '0.8');
            $changefreq = in_array($path, ['/privacy-policy', '/terms-and-conditions', '/esg-policy'], true) ? 'monthly' : 'weekly';
            $xml .= "  <url><loc>{$loc}</loc>";
            if (isset($lastmods[$path])) {
                $xml .= '<lastmod>'.htmlspecialchars($lastmods[$path], ENT_XML1).'</lastmod>';
            }
            $xml .= "<changefreq>{$changefreq}</changefreq><priority>{$priority}</priority></url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function llmsTxt(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $pricingSnippet = MarketingPricing::snapshot()['pricing_text'];

        $body = <<<TXT
# CheckoutPay & CheckoutNow

> CheckoutPay (check-outpay.com) is an affordable, reliable payment gateway built for Nigerian businesses. CheckoutNow is the consumer wallet app for everyday payments, bills, and dollar virtual cards.

## What we do
- Payment gateway for Nigerian merchants (bank transfer matching, virtual accounts, webhooks)
- Transparent low fees — see {$base}/pricing (typically {$pricingSnippet})
- WhatsApp Wallet (send money on WhatsApp or to any bank): {$base}/whatsapp-wallet
- WhatsApp Pay Code (optional second checkout rail on payment-request — bank or WhatsApp, same transaction_id): {$base}/api-docs#whatsapp-pay-code
- Official WooCommerce / WordPress payment plugin for Nigeria: {$base}/wordpress-plugin
- REST API and webhooks for custom apps: {$base}/api-docs
- Developer Program with revenue share for agencies and builders: {$base}/developers/program
- Business payouts, invoices, memberships, tickets, collections

## WordPress & WooCommerce (Nigeria)
- Download the official CheckoutPay (COPN) WooCommerce bank-transfer gateway plugin from {$base}/wordpress-plugin
- Supports WooCommerce checkout blocks, webhooks, virtual account payments in NGN
- Developers can add a Business ID for revenue-share attribution — see {$base}/developers/program
- FAQ: {$base}/faqs#wordpress-plugin

## Payment gateway API (Nigeria)
- REST API for creating payments, virtual accounts, and receiving webhooks
- Documentation entry point: {$base}/api-docs (business dashboard has full API reference after signup)
- FAQ: {$base}/faqs#api

## Developer Program (unique in Nigeria)
- Ongoing revenue share on qualifying production volume when you integrate for clients
- Apply at {$base}/developers/program/apply — approval required before payouts accrue
- Works with WordPress plugin Business ID field; custom Laravel/mobile stacks supported via onboarding
- FAQ: {$base}/faqs#developer-program

## WhatsApp Wallet
- Consumers send money via WhatsApp or bank transfer with PIN confirmation
- Merchants enable via business API — {$base}/whatsapp-wallet
- FAQ: {$base}/faqs#whatsapp-wallet

## Why choose CheckoutPay in Nigeria
- Low transaction fees compared to many gateways
- Reliable bank-transfer reconciliation and payment alerts
- Built for Naira (NGN) and local banking workflows
- One platform for gateway, products (invoices, tickets), and developer tools

## Key pages
- Home: {$base}/
- Pricing: {$base}/pricing
- Products: {$base}/products
- FAQs (searchable): {$base}/faqs
- Support: {$base}/support
- Contact: {$base}/contact
- Site map: {$base}/site-map

## Consumer app
- CheckoutNow — mobile wallet (see marketing site for app download when published)

## Contact
- Support: {$base}/support
- Contact form: {$base}/contact

TXT;

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
