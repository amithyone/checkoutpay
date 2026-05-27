<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.marketing-head', ['seoPath' => '/blog'])</head>
    @include('partials.tailwind-assets')
<body class="bg-white">
    @include('partials.nav')
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-br from-primary/10 via-white to-primary/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-6 text-center">Blog</h1>
            <p class="text-center text-gray-600">Coming soon - Stay tuned for updates and insights!</p>
        </div>
    </section>
    @include('partials.footer')
</body>
</html>
