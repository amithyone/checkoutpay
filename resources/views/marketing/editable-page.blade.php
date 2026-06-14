@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', [
        'seoPath' => $seoPath,
        'jsonLdExtra' => $jsonLdExtra ?? [],
        'seoOverrides' => array_filter([
            'title' => $page->meta_title ?? null,
            'description' => $page->meta_description ?? null,
        ]),
    ])
@endsection

@section('content')
    <section class="py-12 sm:py-16 md:py-20">
        <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8 product-page-content {{ $contentClass ?? '' }}">
            {!! $page->content !!}
        </div>
    </section>
@endsection
