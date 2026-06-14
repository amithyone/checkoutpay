<?php

$root = __DIR__.'/../resources/views';

$files = [
    'rentals/show.blade.php',
    'memberships/show.blade.php',
    'public/peer-loans/index.blade.php',
    'public/peer-loans/show.blade.php',
];

foreach ($files as $relative) {
    $path = $root.'/'.$relative;
    if (! is_readable($path)) {
        echo "Missing: {$relative}\n";
        continue;
    }
    $content = file_get_contents($path);
    if (! str_contains($content, '<!DOCTYPE html>')) {
        echo "Skip: {$relative}\n";
        continue;
    }

    preg_match("/['\"]seoPath['\"]\s*=>\s*['\"]([^'\"]+)['\"]/", $content, $m);
    $seoPath = $m[1] ?? '/';

    preg_match('/<style>.*?<\/style>/s', $content, $styleMatch);
    $stylePush = ! empty($styleMatch[0]) ? "@push('head')\n    {$styleMatch[0]}\n@endpush\n\n" : '';

    $titleSection = "@include('partials.marketing-head', ['seoPath' => '{$seoPath}'])";
    if (preg_match("/@include\('partials\.marketing-head',\s*\[(.*?)\]\)/s", $content, $headMatch)) {
        $titleSection = "@include('partials.marketing-head', [{$headMatch[1]}])";
    }

    $body = preg_replace('/^.*?@include\(\'partials\.nav\'\)\s*/s', '', $content);
    $body = preg_replace('/\s*@include\(\'partials\.footer\'\)\s*/', '', $body);
    $body = preg_replace('/\s*<\/body>\s*<\/html>\s*$/s', '', $body);
    $body = str_replace('max-w-7xl', 'max-w-container', $body);
    $body = str_replace('text-gray-900', 'text-midnight-deep', $body);
    $body = str_replace('text-gray-600', 'text-slate-600', $body);
    $body = str_replace('bg-gray-50', 'bg-surface-container-low/50', $body);

    $new = <<<BLADE
@extends('layouts.marketing')

@section('title')
    {$titleSection}
@endsection

{$stylePush}@section('content')
{$body}
@endsection

BLADE;

    file_put_contents($path, $new);
    echo "Migrated: {$relative}\n";
}
