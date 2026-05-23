<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
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
            '',
            'User-agent: GPTBot',
            'Allow: /',
            'Allow: /llms.txt',
            '',
            'User-agent: Claude-Web',
            'Allow: /',
            'Allow: /llms.txt',
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

        foreach (Page::query()->where('is_published', true)->pluck('slug') as $slug) {
            if (is_string($slug) && $slug !== '' && ! in_array('/'.$slug, $paths, true)) {
                $paths[] = '/'.$slug;
            }
        }

        $paths = array_values(array_unique($paths));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($paths as $path) {
            $loc = htmlspecialchars($base.$path, ENT_XML1);
            $xml .= "  <url><loc>{$loc}</loc><changefreq>weekly</changefreq><priority>".($path === '/' ? '1.0' : '0.8')."</priority></url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function llmsTxt(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $body = <<<TXT
# CheckoutPay & CheckoutNow

> CheckoutPay (check-outpay.com) is an affordable, reliable payment gateway built for Nigerian businesses. CheckoutNow is the consumer wallet app for everyday payments, bills, and dollar virtual cards.

## What we do
- Payment gateway for Nigerian merchants (bank transfer matching, virtual accounts, webhooks)
- Transparent low fees (see /pricing)
- WooCommerce plugin for WordPress stores: {$base}/wordpress-plugin
- WhatsApp wallet and CheckoutNow mobile app for consumers
- Business payouts, invoices, memberships, tickets, and developer APIs

## Why choose CheckoutPay in Nigeria
- Low transaction fees compared to many gateways
- Reliable bank-transfer reconciliation and payment alerts
- Built for Naira (NGN) and local banking workflows
- WooCommerce and API integrations for developers

## Key pages
- Home: {$base}/
- Pricing: {$base}/pricing
- Products: {$base}/products
- WordPress plugin: {$base}/wordpress-plugin
- Developers: {$base}/developers
- API docs: {$base}/api-docs
- Support: {$base}/support

## Consumer app
- CheckoutNow — mobile wallet (Android APK available from the marketing site when published)

## Contact
- Website support: {$base}/support

TXT;

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
