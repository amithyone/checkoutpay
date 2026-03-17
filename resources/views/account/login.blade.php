<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - My Account</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script> tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#3C50E0' } } } } } </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-10 px-4">
    <div class="max-w-md w-full">
        <div class="flex items-center justify-center mb-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3 group">
                @php $logo = \App\Models\Setting::get('site_logo'); @endphp
                @if($logo)
                    <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-9 w-auto rounded-lg shadow-sm">
                @else
                    <div class="h-9 w-9 bg-primary rounded-xl flex items-center justify-center shadow-sm">
                        <i class="fas fa-circle text-white text-base"></i>
                    </div>
                @endif
                <div class="text-left">
                    <p class="text-xs uppercase tracking-wide text-gray-400">CheckoutPay</p>
                    <p class="text-lg font-bold text-gray-900 group-hover:text-primary transition">
                        My Account
                    </p>
                </div>
            </a>
        </div>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 sm:p-7">
            @if(session('status'))<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('status') }}</div>@endif
            @if(session('error'))<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            <form action="{{ route('account.login.post') }}" method="POST" class="mb-6 space-y-4">
                @csrf
                <h2 class="text-sm font-semibold text-gray-800">Log in with password</h2>
                <p class="text-xs text-gray-500">Use the email you used on CheckoutPay.</p>
                <div class="space-y-3 pt-1">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary/60 focus:border-primary/60 outline-none bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Password</label>
                        <input type="password" name="password" required placeholder="••••••••"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary/60 focus:border-primary/60 outline-none bg-gray-50">
                    </div>
                    <button type="submit"
                            class="w-full bg-primary text-white py-2.5 rounded-xl hover:bg-primary/90 font-semibold text-sm shadow-sm">
                        Continue
                    </button>
                </div>
            </form>
            <div class="flex items-center gap-3 my-6"><span class="flex-1 border-t"></span><span class="text-xs text-gray-500">Or</span><span class="flex-1 border-t"></span></div>
            <form action="{{ route('account.login.send-otp') }}" method="POST">
                @csrf
                <h2 class="text-sm font-semibold text-gray-800 mb-2">Use one-time code</h2>
                <div class="flex gap-2">
                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com"
                           class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary/60 focus:border-primary/60 outline-none bg-gray-50">
                    <button type="submit"
                            class="bg-gray-900 text-white px-4 py-2.5 rounded-xl text-xs font-semibold hover:bg-black/90">
                        Send code
                    </button>
                </div>
            </form>
        </div>
        <p class="mt-5 text-center text-xs text-gray-500">
            <a href="{{ route('business.login') }}" class="text-primary hover:underline font-medium">Business login</a>
            <span class="mx-1.5">•</span>
            <a href="{{ url('/') }}" class="hover:underline">Back to home</a>
        </p>
    </div>
</body>
</html>
