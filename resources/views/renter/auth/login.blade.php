<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renter Login - Rentals</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <!-- Login Type Selector -->
                <div class="flex justify-center mb-6 bg-gray-100 rounded-lg p-1">
                    <a href="{{ route('business.login') }}" class="flex-1 text-center py-2 px-4 rounded-md text-gray-600 hover:text-gray-900">
                        <i class="fas fa-building mr-2"></i> Business
                    </a>
                    <a href="{{ route('renter.login') }}" class="flex-1 text-center py-2 px-4 rounded-md bg-white text-primary font-medium shadow-sm">
                        <i class="fas fa-camera mr-2"></i> Rentals
                    </a>
                </div>
                
                <div class="mx-auto h-16 w-16 bg-primary rounded-lg flex items-center justify-center">
                    <i class="fas fa-camera text-white text-2xl"></i>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Renter Login
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Access your rentals dashboard
                </p>
            </div>
            <form class="mt-8 space-y-6 bg-white p-6 sm:p-8 rounded-xl shadow-sm border border-gray-200" action="{{ route('business.login') }}" method="POST">
                @csrf
                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                        <ul class="list-disc list-inside text-sm">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        @if(session('unverified_email'))
                            <div class="mt-3 pt-3 border-t border-red-200">
                                <p class="text-sm mb-2">Didn't receive the verification email?</p>
                                <form action="{{ route('rentals.verification.resend') }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="email" value="{{ session('unverified_email') }}">
                                    <button type="submit" class="text-sm text-primary hover:underline font-medium">
                                        <i class="fas fa-paper-plane mr-1"></i> Resend verification email
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input id="email" name="email" type="email" autocomplete="email" required 
                                class="appearance-none block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm" 
                                placeholder="your@email.com" value="{{ old('email') }}">
                        </div>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input id="password" name="password" type="password" autocomplete="current-password" required 
                                class="appearance-none block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm" 
                                placeholder="Enter your password">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-900">Remember me</label>
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-sign-in-alt text-white"></i>
                        </span>
                        Sign in
                    </button>
                </div>

                <div class="text-center">
                    <p class="text-sm text-gray-600">
                        Don't have an account? 
                        <a href="{{ route('rentals.index') }}" class="text-primary hover:underline">Browse Rentals</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
