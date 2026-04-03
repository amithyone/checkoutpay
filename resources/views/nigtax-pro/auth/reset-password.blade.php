<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NigTax PRO — reset password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#10b981' } } } } };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <div class="mx-auto h-16 w-16 bg-primary rounded-lg flex items-center justify-center">
                    <i class="fas fa-lock text-white text-2xl"></i>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">NigTax PRO</h2>
                <p class="mt-2 text-center text-sm text-gray-600">Set a new password for the tax calculator</p>
            </div>

            <form class="mt-8 space-y-6 bg-white p-8 rounded-lg shadow-sm border border-gray-200" action="{{ route('nigtax-pro.password.update') }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input id="email" name="email" type="email" autocomplete="email" required
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary sm:text-sm"
                            value="{{ old('email', $email) }}">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary sm:text-sm"
                            placeholder="At least 8 characters">
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary sm:text-sm">
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center py-3 px-4 rounded-lg text-white bg-primary hover:bg-emerald-600 font-medium text-sm">
                    Update password
                </button>
            </form>
        </div>
    </div>
</body>
</html>
