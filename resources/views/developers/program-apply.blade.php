<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply — Developer Program — {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#3C50E0' },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-900">
    @include('partials.nav')

    <div class="max-w-xl mx-auto px-4 sm:px-6 py-10 sm:py-14">
        <p class="text-sm font-semibold text-primary uppercase tracking-wide mb-2">Developer Program</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Apply to the program</h1>
        <p class="text-gray-600 text-sm sm:text-base mb-8">
            You must <strong class="text-gray-800">apply and be approved</strong> before any revenue share can accrue. After we review your application, we will follow up by email or WhatsApp. You will also be able to join our developer community on Slack or WhatsApp.
        </p>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
            <form action="{{ route('developers.program.apply.store') }}" method="post" class="space-y-5">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full name <span class="text-red-600">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required maxlength="255"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/30 focus:border-primary @error('name') border-red-500 @enderror">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="business_id" class="block text-sm font-medium text-gray-700 mb-1">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} Business ID</label>
                    <input type="text" name="business_id" id="business_id" value="{{ old('business_id') }}" maxlength="191" placeholder="Optional if you are registering after approval"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/30 focus:border-primary @error('business_id') border-red-500 @enderror">
                    <p class="mt-1 text-xs text-gray-500">From your business dashboard. Leave blank only if you do not have an account yet—we will help you create one.</p>
                    @error('business_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone number <span class="text-red-600">*</span></label>
                    <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" required maxlength="64" autocomplete="tel"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/30 focus:border-primary @error('phone') border-red-500 @enderror">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-600">*</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/30 focus:border-primary @error('email') border-red-500 @enderror">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="whatsapp" class="block text-sm font-medium text-gray-700 mb-1">WhatsApp number <span class="text-red-600">*</span></label>
                    <input type="tel" name="whatsapp" id="whatsapp" value="{{ old('whatsapp') }}" required maxlength="64" placeholder="Include country code, e.g. +234…"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/30 focus:border-primary @error('whatsapp') border-red-500 @enderror">
                    @error('whatsapp')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <fieldset>
                    <legend class="block text-sm font-medium text-gray-700 mb-2">Join the developer community <span class="text-red-600">*</span></legend>
                    <p class="text-xs text-gray-500 mb-3">Choose how you want to connect with other integrators and our team.</p>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="community" value="slack" class="text-primary focus:ring-primary" {{ old('community') === 'slack' ? 'checked' : '' }}>
                            <span class="text-sm text-gray-800"><i class="fab fa-slack text-purple-600 mr-1"></i> Slack</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="community" value="whatsapp" class="text-primary focus:ring-primary" {{ old('community') === 'whatsapp' ? 'checked' : '' }}>
                            <span class="text-sm text-gray-800"><i class="fab fa-whatsapp text-green-600 mr-1"></i> WhatsApp group</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="community" value="both" class="text-primary focus:ring-primary" {{ old('community') === 'both' ? 'checked' : '' }}>
                            <span class="text-sm text-gray-800">Both Slack and WhatsApp</span>
                        </label>
                    </div>
                    @error('community')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </fieldset>
                <button type="submit" class="w-full px-4 py-3 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium text-sm sm:text-base">
                    Submit application
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-sm text-gray-600">
            <a href="{{ route('developers.program') }}" class="text-primary font-medium hover:underline">Back to Developer Program</a>
        </p>
    </div>

    @include('partials.footer')
</body>
</html>
