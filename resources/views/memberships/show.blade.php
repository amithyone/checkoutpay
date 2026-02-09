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
                    <div class="mt-8 space-y-3">
                        @if($membership->isAvailable())
                            <a href="{{ route('memberships.payment.show', $membership->slug) }}" class="block w-full bg-primary text-white text-center py-3 rounded-lg hover:bg-primary/90 font-medium text-lg">
                                <i class="fas fa-id-card mr-2"></i> Join Now
                            </a>
                            <p class="text-sm text-gray-600 text-center">Complete payment to activate your membership</p>
                        @else
                            <button disabled class="block w-full bg-gray-300 text-gray-600 text-center py-3 rounded-lg font-medium text-lg cursor-not-allowed">
                                Membership Full
                            </button>
                            <p class="text-sm text-gray-600 text-center">This membership has reached its capacity limit</p>
                        @endif
                        
                        <!-- Find My Membership Button -->
                        <button onclick="openFindModal()" class="block w-full bg-gray-100 text-gray-700 text-center py-3 rounded-lg hover:bg-gray-200 font-medium text-lg border border-gray-300">
                            <i class="fas fa-search mr-2"></i> Find My Membership Card
                        </button>
                        <p class="text-sm text-gray-600 text-center">Lost your card? Find and download it here</p>
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

    <!-- Find Membership Modal -->
    <div id="findModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold">Find My Membership</h2>
                    <button onclick="closeFindModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="findMembershipForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" name="email" id="findEmail" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="your@email.com">
                    </div>
                    
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">OR</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" name="phone" id="findPhone" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="+234 800 000 0000">
                    </div>
                    
                    <div id="findError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm"></div>
                    
                    <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-primary/90 font-medium">
                        <i class="fas fa-search mr-2"></i> Find Membership
                    </button>
                </form>
                
                <!-- Results Section -->
                <div id="findResults" class="hidden mt-6 space-y-4">
                    <h3 class="font-semibold text-lg">Your Memberships</h3>
                    <div id="findResultsList" class="space-y-3"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openFindModal() {
            document.getElementById('findModal').classList.remove('hidden');
        }
        
        function closeFindModal() {
            document.getElementById('findModal').classList.add('hidden');
            document.getElementById('findError').classList.add('hidden');
            document.getElementById('findResults').classList.add('hidden');
            document.getElementById('findMembershipForm').reset();
        }
        
        document.getElementById('findMembershipForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('findEmail').value.trim();
            const phone = document.getElementById('findPhone').value.trim();
            const errorDiv = document.getElementById('findError');
            const resultsDiv = document.getElementById('findResults');
            const resultsList = document.getElementById('findResultsList');
            
            if (!email && !phone) {
                errorDiv.textContent = 'Please enter either an email address or phone number.';
                errorDiv.classList.remove('hidden');
                return;
            }
            
            errorDiv.classList.add('hidden');
            resultsDiv.classList.add('hidden');
            
            try {
                const response = await fetch('{{ route("memberships.find") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ email, phone })
                });
                
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    errorDiv.textContent = data.message || 'No membership found. Please check your email or phone number.';
                    errorDiv.classList.remove('hidden');
                    return;
                }
                
                // Display results
                resultsList.innerHTML = '';
                data.subscriptions.forEach(function(subscription) {
                    const isExpired = subscription.is_expired || subscription.status === 'expired';
                    const cardHtml = `
                        <div class="border border-gray-200 rounded-lg p-4 ${isExpired ? 'bg-red-50 border-red-200' : 'bg-white'}">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-semibold text-lg">${subscription.membership_name}</h4>
                                    <p class="text-sm text-gray-600">${subscription.business_name}</p>
                                </div>
                                ${isExpired ? '<span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded font-semibold">EXPIRED</span>' : '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded font-semibold">ACTIVE</span>'}
                            </div>
                            <div class="text-sm text-gray-600 space-y-1 mb-3">
                                <p><strong>Member:</strong> ${subscription.member_name}</p>
                                <p><strong>Category:</strong> ${subscription.category}</p>
                                <p><strong>Subscription #:</strong> ${subscription.subscription_number}</p>
                                <p><strong>Expires:</strong> ${subscription.expires_at}</p>
                            </div>
                            ${isExpired ? `
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-3">
                                    <p class="text-sm text-yellow-800 font-semibold mb-2">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> This membership has expired
                                    </p>
                                    <a href="${subscription.renewal_url}" class="block w-full bg-primary text-white text-center py-2 rounded-lg hover:bg-primary/90 text-sm font-medium">
                                        <i class="fas fa-sync-alt mr-1"></i> Renew Membership
                                    </a>
                                </div>
                            ` : ''}
                            <div class="flex gap-2">
                                <a href="${subscription.download_url}" class="flex-1 bg-primary text-white text-center py-2 rounded-lg hover:bg-primary/90 text-sm font-medium">
                                    <i class="fas fa-download mr-1"></i> Download Card
                                </a>
                                <a href="${subscription.view_url}" target="_blank" class="flex-1 bg-gray-100 text-gray-700 text-center py-2 rounded-lg hover:bg-gray-200 text-sm font-medium">
                                    <i class="fas fa-eye mr-1"></i> View Card
                                </a>
                            </div>
                        </div>
                    `;
                    resultsList.innerHTML += cardHtml;
                });
                
                resultsDiv.classList.remove('hidden');
            } catch (error) {
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.classList.remove('hidden');
                console.error('Error:', error);
            }
        });
        
        // Close modal on outside click
        document.getElementById('findModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeFindModal();
            }
        });
    </script>
</body>
</html>
