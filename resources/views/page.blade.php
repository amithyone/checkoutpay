<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->meta_title ?? $page->title }} - CheckoutPay</title>
    @if($page->meta_description)
    <meta name="description" content="{{ $page->meta_description }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">

@section('title', $page->title)

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <article class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ $page->title }}</h1>
        
        <div class="prose max-w-none">
            {!! $page->content !!}
        </div>
    </article>
</div>
</body>
</html>
