<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - My Account</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script> tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#3C50E0' } } } } } </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-8 px-4">
    <div class="max-w-md w-full">
        <div class="text-center mb-6">
            <div class="mx-auto h-14 w-14 bg-primary rounded-lg flex items-center justify-center">
                <i class="fas fa-user text-white text-2xl"></i>
            </div>
            <h1 class="mt-4 text-2xl font-bold text-gray-900">My Account</h1>
            <p class="mt-1 text-sm text-gray-600">Log in with password or use a one-time code</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
            @if(session('status'))<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('status') }}</div>@endif
            @if(session('error'))<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            <form action="{{ route('account.login.post') }}" method="POST" class="mb-6">
                @csrf
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Login with password</h2>
                <div class="space-y-3">
                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="Email" class="w-full px-4 py-2.5 border rounded-lg">
                    <input type="password" name="password" required placeholder="Password" class="w-full px-4 py-2.5 border rounded-lg">
                    <button type="submit" class="w-full bg-primary text-white py-2.5 rounded-lg hover:bg-primary/90 font-medium">Log in</button>
                </div>
            </form>
            <div class="flex items-center gap-3 my-6"><span class="flex-1 border-t"></span><span class="text-xs text-gray-500">Or</span><span class="flex-1 border-t"></span></div>
            <form action="{{ route('account.login.send-otp') }}" method="POST">
                @csrf
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Use one-time code</h2>
                <div class="flex gap-2">
                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="Email" class="flex-1 px-4 py-2.5 border rounded-lg">
                    <button type="submit" class="bg-gray-100 px-4 py-2.5 rounded-lg text-sm font-medium">Send code</button>
                </div>
            </form>
        </div>
        <p class="mt-6 text-center text-sm"><a href="{{ route('business.login') }}" class="text-primary hover:underline">Business login</a></p>
    </div>
</body>
</html>
