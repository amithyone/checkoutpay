<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unavailable — {{ $link->title }}</title>
    @include('partials.tailwind-assets')
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 max-w-md text-center">
        <i class="fas fa-ban text-gray-500 text-4xl mb-4"></i>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">This payment link is closed</h1>
        <p class="text-gray-600">{{ $link->title }} is not accepting payments right now. Contact {{ $link->business->name }} if you still need to pay.</p>
    </div>
</body>
</html>
