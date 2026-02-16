<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter code - My Account</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script> tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#3C50E0' } } } } } </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-8 px-4">
    <div class="max-w-md w-full">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Enter your code</h1>
            <p class="mt-1 text-sm text-gray-600">We sent a 6-digit code to <strong>{{ $email }}</strong></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
            @if(session('error'))
                <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            <form action="{{ route('account.login.verify-otp') }}" method="POST">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                <input type="text" name="code" id="code" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-center text-2xl tracking-widest focus:ring-2 focus:ring-primary" placeholder="000000" autofocus>
                <button type="submit" class="mt-4 w-full bg-primary text-white py-2.5 rounded-lg hover:bg-primary/90 font-medium">Verify and log in</button>
            </form>
            <p class="mt-4 text-center text-sm text-gray-500"><a href="{{ route('account.login') }}" class="text-primary hover:underline">Back to login</a></p>
        </div>
    </div>
</body>
</html>
