<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.marketing-head', ['seoPath' => '/status'])
@include('partials.tailwind-assets')
</head>
<body class="bg-white">
    @include('partials.nav')
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-br from-primary/10 via-white to-primary/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-6 text-center">System Status</h1>
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 max-w-2xl mx-auto">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-4 h-4 bg-green-500 rounded-full mr-3"></div>
                    <span class="text-lg font-semibold text-gray-900">All Systems Operational</span>
                </div>
                <p class="text-gray-600 text-center">All services are running normally.</p>
            </div>
        </div>
    </section>
    @include('partials.footer')
</body>
</html>
