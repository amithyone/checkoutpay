<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - CheckoutPay</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
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
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <div class="text-center mb-8 sm:mb-12">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-3 sm:mb-4">Contact Us</h1>
            <p class="text-base sm:text-lg text-gray-600">Get in touch with our team</p>
        </div>

        <div class="grid md:grid-cols-2 gap-6 sm:gap-8">
            <!-- Contact Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 sm:p-6">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4 sm:mb-6">Get in Touch</h2>
                
                <div class="space-y-4">
                    @if(\App\Models\Setting::get('contact_email'))
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-envelope text-primary text-lg sm:text-xl mt-0.5 sm:mt-1 flex-shrink-0"></i>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-sm sm:text-base text-gray-900">Email</p>
                            <a href="mailto:{{ \App\Models\Setting::get('contact_email') }}" class="text-primary hover:underline text-sm sm:text-base break-words">
                                {{ \App\Models\Setting::get('contact_email') }}
                            </a>
                        </div>
                    </div>
                    @endif

                    @if(\App\Models\Setting::get('contact_phone'))
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-phone text-primary text-lg sm:text-xl mt-0.5 sm:mt-1 flex-shrink-0"></i>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-sm sm:text-base text-gray-900">Phone</p>
                            <a href="tel:{{ \App\Models\Setting::get('contact_phone') }}" class="text-primary hover:underline text-sm sm:text-base break-words">
                                {{ \App\Models\Setting::get('contact_phone') }}
                            </a>
                        </div>
                    </div>
                    @endif

                    @if(\App\Models\Setting::get('contact_address'))
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-map-marker-alt text-primary text-lg sm:text-xl mt-0.5 sm:mt-1 flex-shrink-0"></i>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-sm sm:text-base text-gray-900">Address</p>
                            <p class="text-sm sm:text-base text-gray-600 whitespace-pre-line break-words">{{ \App\Models\Setting::get('contact_address') }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Contact Form -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 sm:p-6">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4 sm:mb-6">Send us a Message</h2>
                
                <form action="#" method="POST" class="space-y-3 sm:space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Name</label>
                        <input type="text" name="name" required
                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Email</label>
                        <input type="email" name="email" required
                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Subject</label>
                        <input type="text" name="subject" required
                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Message</label>
                        <textarea name="message" rows="5" required
                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"></textarea>
                    </div>
                    <button type="submit" class="w-full px-4 sm:px-6 py-2.5 sm:py-3 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium text-sm sm:text-base">
                        Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>

    @include('partials.footer')
</body>
</html>
