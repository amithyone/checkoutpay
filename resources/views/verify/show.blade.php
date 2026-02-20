<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script> tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#3C50E0' } } } } } </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-8 px-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-6 sm:px-6 text-center {{ $valid ? 'bg-green-50 border-b border-green-100' : 'bg-red-50 border-b border-red-100' }}">
                <div class="mx-auto h-16 w-16 rounded-full flex items-center justify-center {{ $valid ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                    @if($valid)
                        <i class="fas fa-check text-3xl"></i>
                    @else
                        <i class="fas fa-times text-3xl"></i>
                    @endif
                </div>
                <h1 class="mt-4 text-xl font-bold text-gray-900">{{ $title }}</h1>
                <p class="mt-2 text-sm {{ $valid ? 'text-green-800' : 'text-red-800' }}">{{ $message }}</p>
            </div>
            @if(!empty($details))
                <div class="p-4 sm:p-6">
                    <dl class="space-y-3 text-sm">
                        @foreach($details as $label => $value)
                            @if($value !== null && $value !== '')
                                <div class="flex justify-between gap-2">
                                    <dt class="text-gray-500">{{ $label }}</dt>
                                    <dd class="text-gray-900 font-medium text-right">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>
        <p class="mt-4 text-center text-xs text-gray-500">Scanned from a purchase QR code</p>
    </div>
</body>
</html>
