@extends('layouts.business')

@section('title', 'Create Event')
@section('page-title', 'Create Event')

@section('content')
<div class="space-y-4 lg:space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Create New Event</h1>
        <a href="{{ route('business.tickets.events.index') }}" class="text-primary hover:text-primary/80">
            <i class="fas fa-arrow-left mr-2"></i> Back to Events
        </a>
    </div>

    <form action="{{ route('business.tickets.events.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- Basic Information -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-bold mb-4">Basic Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Event Title *</label>
                    <input type="text" name="title" required value="{{ old('title') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    @error('title')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Event Type *</label>
                    <select name="event_type" id="event_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="toggleAddressField()">
                        <option value="offline" {{ old('event_type', 'offline') === 'offline' ? 'selected' : '' }}>Offline (Physical Event)</option>
                        <option value="online" {{ old('event_type') === 'online' ? 'selected' : '' }}>Online (Virtual Event)</option>
                    </select>
                    @error('event_type')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg">{{ old('description') }}</textarea>
                @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Venue/Location *</label>
                    <input type="text" name="venue" id="venue" required value="{{ old('venue') }}" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                           placeholder="{{ old('event_type') === 'online' ? 'e.g., Zoom, Google Meet, YouTube Live' : 'e.g., Conference Hall, Stadium' }}">
                    <p class="text-xs text-gray-500 mt-1" id="venue-hint">Physical venue name</p>
                    @error('venue')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div id="address-field-container">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Address <span id="address-required" class="text-red-500">*</span>
                    </label>
                    <input type="text" name="address" id="address" value="{{ old('address') }}" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                           placeholder="Full address for physical events">
                    @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date & Time *</label>
                    <input type="datetime-local" name="start_date" id="start_date" required value="{{ old('start_date') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    @error('start_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date & Time *</label>
                    <input type="datetime-local" name="end_date" id="end_date" required value="{{ old('end_date') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    @error('end_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    <p id="end_date_error" class="text-red-500 text-xs mt-1 hidden">End date must be after start date</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cover Image (Featured Image)</label>
                    <input type="file" name="cover_image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">Recommended: 1200x600px. This will be displayed as the hero background.</p>
                    @error('cover_image')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Background Color</label>
                    
                    <!-- Selected Color Preview -->
                    @php
                        $colorOptions = [
                            ['value' => '#1e3a5f', 'name' => 'Oxford Blue', 'class' => 'bg-[#1e3a5f]'],
                            ['value' => '#0f4c3c', 'name' => 'Deep Teal', 'class' => 'bg-[#0f4c3c]'],
                            ['value' => '#7c2d12', 'name' => 'Burgundy', 'class' => 'bg-[#7c2d12]'],
                            ['value' => '#1e3a1e', 'name' => 'Forest Green', 'class' => 'bg-[#1e3a1e]'],
                            ['value' => '#4c1d4f', 'name' => 'Slate Purple', 'class' => 'bg-[#4c1d4f]'],
                            ['value' => '#000000', 'name' => 'Black', 'class' => 'bg-[#000000]'],
                            ['value' => '#ffffff', 'name' => 'White', 'class' => 'bg-[#ffffff] border-2'],
                        ];
                        $selectedColor = old('background_color', '#1e3a5f');
                        $selectedColorName = collect($colorOptions)->firstWhere('value', $selectedColor)['name'] ?? 'Oxford Blue';
                    @endphp
                    
                    <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="w-16 h-16 rounded-lg border-2 border-gray-300 shadow-sm" 
                                 style="background-color: {{ $selectedColor }};"
                                 id="selected-color-preview"></div>
                            <div>
                                <p class="text-sm font-medium text-gray-700">Selected Color</p>
                                <p class="text-xs text-gray-500">{{ $selectedColorName }} ({{ $selectedColor }})</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 flex-wrap">
                        @foreach($colorOptions as $color)
                            <label class="cursor-pointer group">
                                <input type="radio" name="background_color" value="{{ $color['value'] }}" 
                                       {{ $selectedColor === $color['value'] ? 'checked' : '' }} 
                                       class="hidden color-radio">
                                <div class="flex flex-col items-center gap-1">
                                    <div class="w-12 h-12 {{ $color['class'] }} rounded-lg border-2 transition-all 
                                                {{ $selectedColor === $color['value'] ? 'border-gray-800 ring-2 ring-gray-400 ring-offset-2' : ($color['value'] === '#ffffff' ? 'border-gray-400' : 'border-gray-300 hover:border-gray-400') }} 
                                                shadow-sm"></div>
                                    <span class="text-xs text-gray-600 group-hover:text-gray-800">{{ $color['name'] }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Select a color for your event page background. White text will be used for readability.</p>
                    @error('background_color')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Attendees</label>
                    <input type="number" name="max_attendees" min="1" value="{{ old('max_attendees') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Tickets Per Customer</label>
                <input type="number" name="max_tickets_per_customer" min="1" value="{{ old('max_tickets_per_customer') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>

        <!-- Ticket Types -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-bold mb-4">Ticket Types</h2>
            <div id="ticket-types-container">
                <div class="ticket-type-item border border-gray-200 rounded-lg p-4 mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input type="text" name="ticket_types[0][name]" required placeholder="e.g., VIP, Regular" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price (₦) *</label>
                            <input type="number" name="ticket_types[0][price]" required min="0" step="0.01" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="0 for free tickets">
                            <p class="text-xs text-gray-500 mt-1">Enter 0 for free tickets</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Available *</label>
                            <input type="number" name="ticket_types[0][quantity_available]" required min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="ticket_types[0][description]" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" onclick="addTicketType()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                <i class="fas fa-plus mr-2"></i> Add Another Ticket Type
            </button>
        </div>

        <!-- Speakers/Artists -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-bold mb-4">Speakers/Artists (Optional)</h2>
            <p class="text-sm text-gray-600 mb-4">Add up to 10 speakers or artists for this event</p>
            <div id="speakers-container" class="space-y-4">
                <!-- Speakers will be added here dynamically -->
            </div>
            <button type="button" onclick="addSpeaker()" class="mt-4 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300" id="add-speaker-btn">
                <i class="fas fa-plus mr-2"></i> Add Speaker/Artist
            </button>
        </div>

        <!-- Settings -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-bold mb-4">Settings</h2>
            <div class="space-y-4">
                <div class="flex items-center">
                    <input type="checkbox" name="allow_refunds" value="1" checked id="allow_refunds" class="mr-2">
                    <label for="allow_refunds" class="text-sm text-gray-700">Allow refunds for this event</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Refund Policy</label>
                    <textarea name="refund_policy" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Enter refund policy..."></textarea>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-4">
            <a href="{{ route('business.tickets.events.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</a>
            <button type="submit" name="status" value="draft" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">Save as Draft</button>
            <button type="submit" name="status" value="published" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Publish Event</button>
        </div>
    </form>
</div>

    <script>
let ticketTypeIndex = 1;
let speakerIndex = 0;
const MAX_SPEAKERS = 10;

function addSpeaker() {
    if (speakerIndex >= MAX_SPEAKERS) {
        alert('Maximum of ' + MAX_SPEAKERS + ' speakers allowed');
        return;
    }
    
    const container = document.getElementById('speakers-container');
    const speakerDiv = document.createElement('div');
    speakerDiv.className = 'border border-gray-200 rounded-lg p-4 speaker-item';
    speakerDiv.innerHTML = `
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold">Speaker/Artist ${speakerIndex + 1}</h3>
            <button type="button" onclick="removeSpeaker(this)" class="text-red-600 hover:text-red-800">
                <i class="fas fa-times"></i> Remove
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                <input type="text" name="speakers[${speakerIndex}][name]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Speaker/Artist name">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Topic/Performance</label>
                <input type="text" name="speakers[${speakerIndex}][topic]" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="What they'll speak about or perform">
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Photo</label>
            <input type="file" name="speakers[${speakerIndex}][photo]" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            <p class="text-xs text-gray-500 mt-1">Recommended: Square image, max 2MB</p>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Bio/Description (Optional)</label>
            <textarea name="speakers[${speakerIndex}][bio]" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Brief bio or description"></textarea>
        </div>
    `;
    container.appendChild(speakerDiv);
    speakerIndex++;
    
    // Hide add button if max reached
    if (speakerIndex >= MAX_SPEAKERS) {
        document.getElementById('add-speaker-btn').style.display = 'none';
    }
}

function removeSpeaker(button) {
    button.closest('.speaker-item').remove();
    speakerIndex--;
    
    // Show add button if under max
    if (speakerIndex < MAX_SPEAKERS) {
        document.getElementById('add-speaker-btn').style.display = 'block';
    }
    
    // Renumber speakers
    const speakers = document.querySelectorAll('.speaker-item');
    speakers.forEach((speaker, index) => {
        speaker.querySelector('h3').textContent = `Speaker/Artist ${index + 1}`;
    });
}

// Toggle address field based on event type
function toggleAddressField() {
    const eventType = document.getElementById('event_type').value;
    const addressField = document.getElementById('address');
    const addressContainer = document.getElementById('address-field-container');
    const addressRequired = document.getElementById('address-required');
    const venueField = document.getElementById('venue');
    const venueHint = document.getElementById('venue-hint');
    
    if (eventType === 'online') {
        addressField.removeAttribute('required');
        addressRequired.classList.add('hidden');
        addressField.value = '';
        addressField.placeholder = 'Optional: Online platform link';
        venueField.placeholder = 'e.g., Zoom, Google Meet, YouTube Live';
        venueHint.textContent = 'Online platform or virtual location';
    } else {
        addressField.setAttribute('required', 'required');
        addressRequired.classList.remove('hidden');
        addressField.placeholder = 'Full address for physical events';
        venueField.placeholder = 'e.g., Conference Hall, Stadium';
        venueHint.textContent = 'Physical venue name';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleAddressField();
});

function addTicketType() {
    const container = document.getElementById('ticket-types-container');
    const newItem = document.createElement('div');
    newItem.className = 'ticket-type-item border border-gray-200 rounded-lg p-4 mb-4';
    newItem.innerHTML = `
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold">Ticket Type ${ticketTypeIndex + 1}</h3>
            <button type="button" onclick="this.closest('.ticket-type-item').remove()" class="text-red-600 hover:text-red-800">
                <i class="fas fa-times"></i> Remove
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                <input type="text" name="ticket_types[${ticketTypeIndex}][name]" required placeholder="e.g., VIP, Regular" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Price (₦) *</label>
                <input type="number" name="ticket_types[${ticketTypeIndex}][price]" required min="0" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Available *</label>
                <input type="number" name="ticket_types[${ticketTypeIndex}][quantity_available]" required min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="ticket_types[${ticketTypeIndex}][description]" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
            </div>
        </div>
    `;
    container.appendChild(newItem);
    ticketTypeIndex++;
}

// Date validation: Ensure end date is after start date
(function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const endDateError = document.getElementById('end_date_error');
    const form = startDateInput.closest('form');

    function updateEndDateMin() {
        const startDate = startDateInput.value;
        if (startDate) {
            endDateInput.min = startDate;
            
            // If end date is set and is before start date, clear it and show error
            if (endDateInput.value && endDateInput.value < startDate) {
                endDateInput.value = '';
                endDateInput.classList.add('border-red-500');
                endDateError.classList.remove('hidden');
            } else {
                endDateInput.classList.remove('border-red-500');
                endDateError.classList.add('hidden');
            }
        }
    }

    function validateEndDate() {
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        
        if (startDate && endDate && endDate < startDate) {
            endDateInput.classList.add('border-red-500');
            endDateError.classList.remove('hidden');
            return false;
        } else {
            endDateInput.classList.remove('border-red-500');
            endDateError.classList.add('hidden');
            return true;
        }
    }

    // Update min when start date changes
    startDateInput.addEventListener('change', function() {
        updateEndDateMin();
    });

    // Validate when end date changes
    endDateInput.addEventListener('change', function() {
        validateEndDate();
    });

    // Validate on form submit
    form.addEventListener('submit', function(e) {
        if (!validateEndDate()) {
            e.preventDefault();
            endDateInput.focus();
            return false;
        }
    });

    // Set initial min value if start date is already set
    if (startDateInput.value) {
        updateEndDateMin();
    }
})();

// Handle color selection with radio buttons
document.querySelectorAll('input[name="background_color"].color-radio').forEach(function(radio) {
    radio.addEventListener('change', function() {
        // Update preview
        const preview = document.getElementById('selected-color-preview');
        const previewText = preview?.nextElementSibling?.querySelector('p:last-child');
        if (preview) {
            preview.style.backgroundColor = this.value;
        }
        if (previewText) {
            const colorName = this.closest('label').querySelector('span').textContent.trim();
            previewText.textContent = colorName + ' (' + this.value + ')';
        }
        
        // Update visual selection state
        document.querySelectorAll('.color-radio').forEach(function(r) {
            const colorDiv = r.closest('label').querySelector('div > div');
            const isWhite = r.value === '#ffffff';
            if (r.checked) {
                colorDiv.classList.remove('border-gray-300', 'border-gray-400', 'hover:border-gray-400');
                colorDiv.classList.add('border-gray-800', 'ring-2', 'ring-gray-400', 'ring-offset-2');
            } else {
                colorDiv.classList.remove('border-gray-800', 'ring-2', 'ring-gray-400', 'ring-offset-2');
                if (isWhite) {
                    colorDiv.classList.add('border-gray-400');
                } else {
                    colorDiv.classList.add('border-gray-300', 'hover:border-gray-400');
                }
            }
        });
    });
});
</script>
@endsection
