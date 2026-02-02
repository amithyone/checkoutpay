@extends('layouts.business')

@section('title', 'Edit Membership')
@section('page-title', 'Edit Membership')

@section('content')
<div class="max-w-5xl">
    <form action="{{ route('business.memberships.update', $membership) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="space-y-6">
            <!-- Basic Information -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Membership Name *</label>
                        <input type="text" name="name" id="name" required value="{{ old('name', $membership->name) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category_id" id="category_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id', $membership->category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="is_featured" class="flex items-center mt-6">
                            <input type="checkbox" name="is_featured" id="is_featured" value="1" {{ old('is_featured', $membership->is_featured) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-primary focus:ring-primary">
                            <span class="ml-2 text-sm text-gray-700">Feature this membership</span>
                        </label>
                    </div>
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" id="description" rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">{{ old('description', $membership->description) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Who is it for Section -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Who is it for?</h3>
                <p class="text-sm text-gray-600 mb-4">Describe your target audience for this membership</p>
                
                <div class="mb-4">
                    <label for="who_is_it_for" class="block text-sm font-medium text-gray-700 mb-1">Target Audience Description</label>
                    <textarea name="who_is_it_for" id="who_is_it_for" rows="3" placeholder="e.g., Perfect for fitness enthusiasts looking to build strength and endurance..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">{{ old('who_is_it_for', $membership->who_is_it_for) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quick Suggestions (Click to add)</label>
                    <div class="flex flex-wrap gap-2 mb-3" id="suggestions-container">
                        @foreach($defaultSuggestions as $suggestion)
                            <button type="button" onclick="addSuggestion('{{ $suggestion }}')" 
                                class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-full hover:bg-primary hover:text-white transition-colors">
                                {{ $suggestion }}
                            </button>
                        @endforeach
                    </div>
                    <div id="selected-suggestions" class="flex flex-wrap gap-2">
                        @php
                            $existingSuggestions = old('who_is_it_for_suggestions', $membership->who_is_it_for_suggestions ?? []);
                        @endphp
                        @foreach($existingSuggestions as $suggestion)
                            <span class="px-3 py-1 text-sm bg-primary text-white rounded-full flex items-center gap-2">
                                {{ $suggestion }}
                                <button type="button" onclick="removeSuggestion(this)" class="hover:text-gray-200">
                                    <i class="fas fa-times"></i>
                                </button>
                                <input type="hidden" name="who_is_it_for_suggestions[]" value="{{ $suggestion }}">
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Pricing & Duration -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Pricing & Duration</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price *</label>
                        <input type="number" name="price" id="price" step="0.01" min="0" required value="{{ old('price', $membership->price) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label for="currency" class="block text-sm font-medium text-gray-700 mb-1">Currency *</label>
                        <select name="currency" id="currency" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            <option value="NGN" {{ old('currency', $membership->currency) === 'NGN' ? 'selected' : '' }}>NGN (₦)</option>
                            <option value="USD" {{ old('currency', $membership->currency) === 'USD' ? 'selected' : '' }}>USD ($)</option>
                            <option value="GBP" {{ old('currency', $membership->currency) === 'GBP' ? 'selected' : '' }}>GBP (£)</option>
                            <option value="EUR" {{ old('currency', $membership->currency) === 'EUR' ? 'selected' : '' }}>EUR (€)</option>
                        </select>
                    </div>
                    <div>
                        <label for="duration_value" class="block text-sm font-medium text-gray-700 mb-1">Duration Value *</label>
                        <input type="number" name="duration_value" id="duration_value" min="1" required value="{{ old('duration_value', $membership->duration_value) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label for="duration_type" class="block text-sm font-medium text-gray-700 mb-1">Duration Type *</label>
                        <select name="duration_type" id="duration_type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            <option value="days" {{ old('duration_type', $membership->duration_type) === 'days' ? 'selected' : '' }}>Days</option>
                            <option value="weeks" {{ old('duration_type', $membership->duration_type) === 'weeks' ? 'selected' : '' }}>Weeks</option>
                            <option value="months" {{ old('duration_type', $membership->duration_type) === 'months' ? 'selected' : '' }}>Months</option>
                            <option value="years" {{ old('duration_type', $membership->duration_type) === 'years' ? 'selected' : '' }}>Years</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Features -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Features & Benefits</h3>
                <div id="features-container">
                    <div class="space-y-2 mb-3">
                        @php
                            $features = old('features', $membership->features ?? []);
                            if (empty($features)) {
                                $features = [''];
                            }
                        @endphp
                        @foreach($features as $feature)
                            <div class="flex gap-2 feature-item">
                                <input type="text" name="features[]" value="{{ $feature }}" placeholder="e.g., Access to all gym equipment"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                                <button type="button" onclick="removeFeature(this)" class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" onclick="addFeature()" class="text-sm text-primary hover:text-primary/80">
                        <i class="fas fa-plus mr-1"></i> Add Feature
                    </button>
                </div>
            </div>

            <!-- Images -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Images</h3>
                @if($membership->images && count($membership->images) > 0)
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        @foreach($membership->images as $image)
                            <div class="relative">
                                <img src="{{ asset('storage/' . $image) }}" alt="Membership Image" class="w-full h-32 object-cover rounded-lg">
                                <label class="absolute top-2 right-2">
                                    <input type="checkbox" name="remove_images[]" value="{{ $image }}" class="rounded border-gray-300">
                                    <span class="ml-1 text-xs text-white bg-red-500 px-2 py-1 rounded">Remove</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div>
                    <label for="images" class="block text-sm font-medium text-gray-700 mb-2">Upload New Images</label>
                    <input type="file" name="images[]" id="images" multiple accept="image/*"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    <p class="mt-1 text-xs text-gray-500">You can upload multiple images. Max 2MB per image.</p>
                </div>
            </div>

            <!-- Membership Card Design -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Membership Card Design</h3>
                <p class="text-sm text-gray-600 mb-4">Customize the design of membership cards issued to members</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="card_logo" class="block text-sm font-medium text-gray-700 mb-2">Card Logo</label>
                        @if($membership->card_logo)
                            <div class="mb-2">
                                <p class="text-xs text-gray-600 mb-1">Current Logo:</p>
                                <img src="{{ asset('storage/' . $membership->card_logo) }}" alt="Card Logo" class="w-24 h-24 object-contain border border-gray-300 rounded-lg p-2">
                            </div>
                        @endif
                        <input type="file" name="card_logo" id="card_logo" accept="image/*"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <p class="mt-1 text-xs text-gray-500">Logo displayed on membership card (PNG/JPG, max 2MB)</p>
                    </div>
                    <div>
                        <label for="card_graphics" class="block text-sm font-medium text-gray-700 mb-2">Card Background Graphics</label>
                        @if($membership->card_graphics)
                            <div class="mb-2">
                                <p class="text-xs text-gray-600 mb-1">Current Background:</p>
                                <img src="{{ asset('storage/' . $membership->card_graphics) }}" alt="Card Graphics" class="w-24 h-24 object-cover border border-gray-300 rounded-lg">
                            </div>
                        @endif
                        <input type="file" name="card_graphics" id="card_graphics" accept="image/*"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <p class="mt-1 text-xs text-gray-500">Background image for card (PNG/JPG, max 2MB)</p>
                    </div>
                </div>
            </div>

            <!-- Member Limits -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Member Limits</h3>
                <div>
                    <label for="max_members" class="block text-sm font-medium text-gray-700 mb-1">Maximum Members (Optional)</label>
                    <input type="number" name="max_members" id="max_members" min="1" value="{{ old('max_members', $membership->max_members) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    <p class="mt-1 text-xs text-gray-500">Leave empty for unlimited members. Current: {{ $membership->current_members }}</p>
                </div>
            </div>

            <!-- Terms & Conditions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Terms & Conditions</h3>
                <div>
                    <label for="terms_and_conditions" class="block text-sm font-medium text-gray-700 mb-1">Terms & Conditions</label>
                    <textarea name="terms_and_conditions" id="terms_and_conditions" rows="5"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">{{ old('terms_and_conditions', $membership->terms_and_conditions) }}</textarea>
                </div>
            </div>

            <!-- Status -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $membership->is_active) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="ml-2 text-sm text-gray-700">Activate this membership</span>
                </label>
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-4">
                <a href="{{ route('business.memberships.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    Update Membership
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function addSuggestion(suggestion) {
    const container = document.getElementById('selected-suggestions');
    const existing = Array.from(container.querySelectorAll('input[type="hidden"]')).some(input => input.value === suggestion);
    
    if (!existing) {
        const span = document.createElement('span');
        span.className = 'px-3 py-1 text-sm bg-primary text-white rounded-full flex items-center gap-2';
        span.innerHTML = `
            ${suggestion}
            <button type="button" onclick="removeSuggestion(this)" class="hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
            <input type="hidden" name="who_is_it_for_suggestions[]" value="${suggestion}">
        `;
        container.appendChild(span);
    }
}

function removeSuggestion(button) {
    button.closest('span').remove();
}

function addFeature() {
    const container = document.querySelector('#features-container .space-y-2');
    const div = document.createElement('div');
    div.className = 'flex gap-2 feature-item';
    div.innerHTML = `
        <input type="text" name="features[]" placeholder="e.g., Access to all gym equipment"
            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
        <button type="button" onclick="removeFeature(this)" class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(div);
}

function removeFeature(button) {
    button.closest('.feature-item').remove();
}

function toggleCityField() {
    const isGlobal = document.getElementById('is_global').checked;
    const cityField = document.getElementById('city-field');
    const citySelect = document.getElementById('city');
    
    if (isGlobal) {
        cityField.style.display = 'none';
        citySelect.value = '';
    } else {
        cityField.style.display = 'block';
    }
}
</script>
@endsection
