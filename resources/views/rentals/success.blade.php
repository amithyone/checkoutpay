<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Request Submitted - Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-lg shadow-lg p-8 text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-green-600 text-2xl"></i>
            </div>
            
            <h1 class="text-2xl font-bold mb-4">Rental Request Submitted!</h1>
            <p class="text-gray-600 mb-6">
                Your rental request has been successfully submitted. The business will review your request and contact you soon.
            </p>

            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                <h3 class="font-semibold mb-2">What's Next?</h3>
                <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                    <li>You will receive an email receipt shortly</li>
                    <li>The business will review your request</li>
                    <li>You'll be notified once your request is approved</li>
                    <li>Contact the business directly using the phone number provided</li>
                </ul>
            </div>

            <a href="{{ route('rentals.index') }}" class="inline-block bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary/90 font-medium">
                Browse More Rentals
            </a>
        </div>
    </div>
</body>
</html>
