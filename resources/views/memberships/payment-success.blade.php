<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Activated - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
<body class="bg-gray-50 min-h-screen">
    @include('partials.nav')

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-lg shadow-lg p-8 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-6">
                <i class="fas fa-check text-green-600 text-4xl"></i>
            </div>
            
            <h1 class="text-3xl font-bold text-gray-900 mb-4">Membership Activated!</h1>
            <p class="text-gray-600 mb-8">Your membership has been successfully activated.</p>

            <div class="bg-gray-50 rounded-lg p-6 mb-6 text-left">
                <h2 class="text-lg font-semibold mb-4">Membership Details</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subscription Number:</span>
                        <span class="font-medium">{{ $subscription->subscription_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Membership:</span>
                        <span class="font-medium">{{ $subscription->membership->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Member Name:</span>
                        <span class="font-medium">{{ $subscription->member_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Start Date:</span>
                        <span class="font-medium">{{ $subscription->start_date->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Expires:</span>
                        <span class="font-medium text-red-600">{{ $subscription->expires_at->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Days Remaining:</span>
                        <span class="font-medium">{{ $subscription->days_remaining }} days</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('memberships.card.download', $subscription->subscription_number) }}" class="bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary/90 font-medium">
                    <i class="fas fa-download mr-2"></i> Download Membership Card (PDF)
                </a>
                <a href="{{ route('memberships.index') }}" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 font-medium">
                    Browse More Memberships
                </a>
            </div>

            <p class="text-sm text-gray-500 mt-6">
                Your membership card has been sent to <strong>{{ $subscription->member_email }}</strong>
            </p>
        </div>
    </div>

    @include('partials.footer')
</body>
</html>
