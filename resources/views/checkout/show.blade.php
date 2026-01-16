<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - CheckoutPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#3C50E0',
                        },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-lg mb-4">
                <i class="fas fa-lock text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Secure Payment</h1>
            <p class="text-gray-600">Complete your payment using the details below</p>
        </div>

        <!-- Payment Form Card -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <form action="{{ route('checkout.store') }}" method="POST" class="space-y-6">
                @csrf
                
                <input type="hidden" name="business_id" value="{{ $business->id }}">
                <input type="hidden" name="amount" value="{{ $amount }}">
                <input type="hidden" name="service" value="{{ $service ?? '' }}">
                <input type="hidden" name="return_url" value="{{ $returnUrl }}">
                <input type="hidden" name="cancel_url" value="{{ $cancelUrl }}">

                <!-- Business Info -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Paying to</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $business->name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">Amount</p>
                            <p class="text-2xl font-bold text-primary">₦{{ number_format($amount, 2) }}</p>
                        </div>
                    </div>
                    @if($service)
                    <div class="mt-3 pt-3 border-t border-gray-200">
                        <p class="text-sm text-gray-600">Service/Product</p>
                        <p class="text-sm font-medium text-gray-900">{{ $service }}</p>
                    </div>
                    @endif
                </div>

                <!-- Name Field -->
                <div>
                    <label for="payer_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="payer_name" 
                        name="payer_name" 
                        required
                        placeholder="Enter your name as it appears on your bank account"
                        value="{{ old('payer_name') }}"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary transition-colors @error('payer_name') border-red-500 @enderror"
                    >
                    <p class="mt-1 text-xs text-gray-500">Enter the exact name on your bank account</p>
                    @error('payer_name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Error Messages -->
                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-600 mt-0.5 mr-3"></i>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-red-900 mb-1">Error</p>
                                <ul class="text-sm text-red-700 list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    id="submitBtn"
                    class="w-full bg-primary text-white py-3 px-6 rounded-lg font-medium hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span id="submitText">Continue to Payment</span>
                    <i class="fas fa-arrow-right ml-2" id="submitIcon"></i>
                </button>

                <!-- Cancel Link -->
                <div class="text-center">
                    <a href="{{ $cancelUrl }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel Payment</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Prevent double submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const submitIcon = document.getElementById('submitIcon');
            
            submitBtn.disabled = true;
            submitText.textContent = 'Processing...';
            submitIcon.className = 'fas fa-spinner fa-spin ml-2';
        });
    </script>
</body>
</html>
