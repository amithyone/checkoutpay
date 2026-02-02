<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $membership->name }} - Memberships</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="{{ route('memberships.index') }}" class="text-primary hover:underline mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Memberships
        </a>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 p-6">
                <!-- Images -->
                <div>
                    @if($membership->images && count($membership->images) > 0)
                        <img src="{{ asset('storage/' . $membership->images[0]) }}" alt="{{ $membership->name }}" class="w-full h-96 object-cover rounded-lg mb-4">
                        @if(count($membership->images) > 1)
                            <div class="grid grid-cols-4 gap-2">
                                @foreach(array_slice($membership->images, 1, 4) as $image)
                                    <img src="{{ asset('storage/' . $image) }}" alt="{{ $membership->name }}" class="w-full h-20 object-cover rounded">
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="w-full h-96 bg-gradient-to-br from-primary/20 to-primary/5 rounded-lg flex items-center justify-center">
                            <i class="fas fa-id-card text-primary text-6xl"></i>
                        </div>
                    @endif
                </div>

                <!-- Details -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        @if($membership->is_featured)
                            <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">Featured</span>
                        @endif
                        @if($membership->category)
                            <span class="bg-primary/10 text-primary px-3 py-1 rounded-full text-sm">{{ $membership->category->name }}</span>
                        @endif
                        @if($membership->is_global)
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Global</span>
                        @elseif($membership->city)
                            <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded">
                                <i class="fas fa-map-marker-alt mr-1"></i>{{ $membership->city }}
                            </span>
                        @endif
                    </div>

                    <h1 class="text-3xl font-bold mb-4">{{ $membership->name }}</h1>

                    <div class="mb-6">
                        <h2 class="text-3xl font-bold text-primary mb-2">
                            {{ $membership->currency }} {{ number_format($membership->price, 2) }}
                            <span class="text-lg text-gray-600 font-normal">/ {{ $membership->formatted_duration }}</span>
                        </h2>
                        @if($membership->max_members)
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-users"></i> {{ $membership->current_members }} / {{ $membership->max_members }} members
                            </p>
                        @endif
                    </div>

                    <!-- Who is it for Section -->
                    @if($membership->who_is_it_for || ($membership->who_is_it_for_suggestions && count($membership->who_is_it_for_suggestions) > 0))
                        <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                            <h3 class="font-semibold text-gray-900 mb-2">
                                <i class="fas fa-user-check text-primary mr-2"></i>Who is it for?
                            </h3>
                            @if($membership->who_is_it_for)
                                <p class="text-gray-700 mb-3">{{ $membership->who_is_it_for }}</p>
                            @endif
                            @if($membership->who_is_it_for_suggestions && count($membership->who_is_it_for_suggestions) > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($membership->who_is_it_for_suggestions as $suggestion)
                                        <span class="px-3 py-1 text-sm bg-primary text-white rounded-full">{{ $suggestion }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($membership->description)
                        <div class="mb-6">
                            <h3 class="font-semibold mb-2">Description</h3>
                            <p class="text-gray-700 whitespace-pre-wrap">{{ $membership->description }}</p>
                        </div>
                    @endif

                    @if($membership->features && count($membership->features) > 0)
                        <div class="mb-6">
                            <h3 class="font-semibold mb-3">Features & Benefits</h3>
                            <ul class="space-y-2">
                                @foreach($membership->features as $feature)
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                                        <span class="text-gray-700">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- CTA Button -->
                    <div class="mt-8">
                        @if($membership->isAvailable())
                            <a href="{{ route('memberships.payment.show', $membership->slug) }}" class="block w-full bg-primary text-white text-center py-3 rounded-lg hover:bg-primary/90 font-medium text-lg">
                                <i class="fas fa-id-card mr-2"></i> Join Now
                            </a>
                            <p class="text-sm text-gray-600 text-center mt-2">Complete payment to activate your membership</p>
                        @else
                            <button disabled class="block w-full bg-gray-300 text-gray-600 text-center py-3 rounded-lg font-medium text-lg cursor-not-allowed">
                                Membership Full
                            </button>
                            <p class="text-sm text-gray-600 text-center mt-2">This membership has reached its capacity limit</p>
                        @endif
                    </div>
                </div>
            </div>

            @if($membership->terms_and_conditions)
                <div class="border-t border-gray-200 p-6">
                    <h3 class="font-semibold mb-3">Terms & Conditions</h3>
                    <p class="text-gray-700 whitespace-pre-wrap text-sm">{{ $membership->terms_and_conditions }}</p>
                </div>
            @endif
        </div>

        <!-- Related Memberships -->
        @if($relatedMemberships->count() > 0)
            <div class="mt-8">
                <h2 class="text-2xl font-bold mb-4">More from {{ $membership->business->name }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($relatedMemberships as $related)
                        <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
                            @if($related->images && count($related->images) > 0)
                                <img src="{{ asset('storage/' . $related->images[0]) }}" alt="{{ $related->name }}" class="w-full h-48 object-cover rounded-t-lg">
                            @else
                                <div class="w-full h-48 bg-gradient-to-br from-primary/20 to-primary/5 rounded-t-lg flex items-center justify-center">
                                    <i class="fas fa-id-card text-primary text-4xl"></i>
                                </div>
                            @endif
                            <div class="p-4">
                                <h3 class="font-semibold mb-2">{{ $related->name }}</h3>
                                <p class="text-primary font-bold mb-2">{{ $related->currency }} {{ number_format($related->price, 2) }} / {{ $related->formatted_duration }}</p>
                                <a href="{{ route('memberships.show', $related->slug) }}" class="block w-full bg-primary text-white text-center py-2 rounded-md hover:bg-primary/90 text-sm">
                                    View Details
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @include('partials.footer')
</body>
</html>
