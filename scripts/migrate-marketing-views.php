<?php

/**
 * One-off: migrate standalone marketing Blade views to layouts.marketing.
 */
$root = __DIR__.'/../resources/views';

$files = [
    'products/invoices.blade.php',
    'products/memberships.blade.php',
    'products/memberships-info.blade.php',
    'products/rentals-info.blade.php',
    'products/tickets-info.blade.php',
    'payout/index.blade.php',
    'collections/index.blade.php',
    'checkout-demo/index.blade.php',
    'wordpress-plugin/index.blade.php',
    'developers/index.blade.php',
    'developers/program.blade.php',
    'developers/program-apply.blade.php',
    'developers/program-apply-thanks.blade.php',
    'marketplace/index.blade.php',
];

$classMap = [
    'max-w-7xl' => 'max-w-container',
    'text-gray-900' => 'text-midnight-deep',
    'text-gray-800' => 'text-midnight-deep',
    'text-gray-700' => 'text-slate-700',
    'text-gray-600' => 'text-slate-600',
    'text-gray-500' => 'text-slate-500',
    'bg-gray-50' => 'bg-surface-container-low',
    'bg-gradient-to-br from-primary/10 via-white to-primary/5' => 'py-14 sm:py-20',
    'bg-gradient-to-r from-primary to-primary/90' => 'bg-midnight-deep',
    'rounded-xl shadow-lg border border-gray-200' => 'card-marketing',
    'bg-white rounded-xl shadow-lg border border-gray-200' => 'card-marketing bg-white',
    'bg-white rounded-lg p-6 shadow-sm border border-gray-200' => 'card-marketing p-6',
    'bg-primary text-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/90' => 'btn-brand',
    'inline-flex items-center px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium' => 'btn-brand',
    'text-primary hover:text-primary/80' => 'text-brand-primary hover:text-brand-secondary',
    'text-primary-100' => 'text-slate-300',
    'font-bold text-gray-900' => 'font-bold text-midnight-deep',
];

foreach ($files as $relative) {
    $path = $root.'/'.$relative;
    if (! is_readable($path)) {
        echo "Skip missing: {$relative}\n";
        continue;
    }

    $content = file_get_contents($path);
    if (! str_contains($content, '<!DOCTYPE html>')) {
        echo "Skip (already migrated): {$relative}\n";
        continue;
    }

    $seoPath = '/';
    if (preg_match("/['\"]seoPath['\"]\s*=>\s*['\"]([^'\"]+)['\"]/", $content, $m)) {
        $seoPath = $m[1];
    } elseif (preg_match("/Seo::forPath\(['\"]([^'\"]+)['\"]\)/", $content, $m)) {
        $seoPath = $m[1];
    }

    $titleSection = "@include('partials.marketing-head', ['seoPath' => '{$seoPath}'])";
    if (preg_match("/@include\('partials\.marketing-head',\s*\[(.*?)\]\)/s", $content, $m)) {
        $titleSection = "@include('partials.marketing-head', [{$m[1]}])";
    } elseif (preg_match("/<title>(.*?)<\/title>/s", $content, $m)) {
        $titleSection = '<title>'.$m[1].'</title>'."\n    @include('partials.seo-head', ['seoOverrides' => \\App\\Support\\Seo::forPath('{$seoPath}')])";
    }

    $body = preg_replace('/^.*?@include\(\'partials\.nav\'\)\s*/s', '', $content);
    $body = preg_replace('/\s*@include\(\'partials\.footer\'\)\s*<\/body>\s*<\/html>\s*$/s', '', $body);

    foreach ($classMap as $from => $to) {
        $body = str_replace($from, $to, $body);
    }

    // Replace old hero gradient sections opening tag already handled; fix CTA buttons
    $body = str_replace('bg-white text-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-gray-100', 'btn-brand bg-white text-midnight-deep hover:bg-slate-100', $body);

    $new = <<<BLADE
@extends('layouts.marketing')

@section('title')
    {$titleSection}
@endsection

@section('content')
{$body}
@endsection

BLADE;

    file_put_contents($path, $new);
    echo "Migrated: {$relative}\n";
}

echo "Done.\n";
