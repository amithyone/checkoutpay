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
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="{{ route('home') }}" class="flex items-center flex-1">
                    @php
                        $logo = \App\Models\Setting::get('site_logo');
                        $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
                    @endphp
                    @if($logo && $logoPath && file_exists($logoPath))
                        <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-8 sm:h-10 object-contain mr-2 sm:mr-3" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 bg-primary rounded-lg flex items-center justify-center mr-2 sm:mr-3" style="display: none;">
                            <i class="fas fa-shield-alt text-white text-lg sm:text-xl"></i>
                        </div>
                    @else
                        <div class="h-8 w-8 sm:h-10 sm:w-10 bg-primary rounded-lg flex items-center justify-center mr-2 sm:mr-3">
                            <i class="fas fa-shield-alt text-white text-lg sm:text-xl"></i>
                        </div>
                    @endif
                    <div>
                        <h1 class="text-base sm:text-xl font-bold text-gray-900">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h1>
                    </div>
                </a>
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-4">
                    <a href="{{ route('home') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Home</a>
                    <a href="{{ route('pricing') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Pricing</a>
                    <a href="{{ route('contact') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Contact</a>
                </div>
                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="md:hidden p-2 rounded-md text-gray-700 hover:text-primary focus:outline-none">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
            <!-- Mobile Navigation Menu -->
            <div id="mobile-menu" class="hidden md:hidden pb-4 border-t border-gray-200 mt-2">
                <div class="flex flex-col space-y-2 pt-4">
                    <a href="{{ route('home') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Home</a>
                    <a href="{{ route('pricing') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Pricing</a>
                    <a href="{{ route('contact') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Contact</a>
                </div>
            </div>
        </div>
    </nav>

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

    <!-- Footer -->
    <footer class="bg-gray-900 text-white mt-12 sm:mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="border-t border-gray-800 mt-6 sm:mt-8 pt-6 sm:pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center space-y-3 sm:space-y-4 md:space-y-0">
                    <div class="flex flex-wrap justify-center md:justify-start gap-4 sm:gap-6 text-xs sm:text-sm">
                        <a href="{{ route('privacy-policy') }}" class="text-gray-400 hover:text-white transition-colors">Privacy Policy</a>
                        <a href="{{ route('terms') }}" class="text-gray-400 hover:text-white transition-colors">Terms & Conditions</a>
                        <a href="{{ route('contact') }}" class="text-gray-400 hover:text-white transition-colors">Contact Us</a>
                    </div>
                    <p class="text-xs sm:text-sm text-gray-400">&copy; {{ date('Y') }} CheckoutPay. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
                const icon = this.querySelector('i');
                if (mobileMenu.classList.contains('hidden')) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                } else {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                }
            });
        }
    </script>
</body>
</html>
