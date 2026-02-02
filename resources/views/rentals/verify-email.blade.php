<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-lg shadow-lg p-8 text-center">
            <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-envelope text-primary text-2xl"></i>
            </div>
            
            <h1 class="text-2xl font-bold mb-4">Verify Your Email</h1>
            <p class="text-gray-600 mb-6">
                We've sent a verification email to <strong>{{ $renter->email }}</strong>. 
                Please check your inbox and click the verification link, or enter the PIN code below.
            </p>

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-4">
                    {{ $errors->first() }}
                </div>
            @endif

            <!-- PIN Verification Form -->
            <form action="{{ route('rentals.verification.verify-pin') }}" method="POST" class="mb-6">
                @csrf
                <input type="hidden" name="email" value="{{ $renter->email }}">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Enter 6-digit PIN</label>
                    <input type="text" name="pin" maxlength="6" pattern="[0-9]{6}" required 
                           class="w-full max-w-xs mx-auto border-gray-300 rounded-md text-center text-2xl tracking-widest"
                           placeholder="000000">
                </div>

                <button type="submit" class="w-full max-w-xs mx-auto bg-primary text-white py-3 rounded-lg hover:bg-primary/90 font-medium">
                    Verify Email
                </button>
            </form>

            <div class="text-sm text-gray-600">
                <p class="mb-2">Didn't receive the email?</p>
                <form action="{{ route('rentals.verification.resend') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="email" value="{{ $renter->email }}">
                    <button type="submit" class="text-primary hover:underline">
                        Resend Verification Email
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
