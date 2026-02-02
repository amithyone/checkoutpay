@extends('layouts.business')

@section('title', 'Edit Event')
@section('page-title', 'Edit Event')

@section('content')
<div class="space-y-4 lg:space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Edit Event</h1>
        <a href="{{ route('business.tickets.events.show', $event) }}" class="text-primary hover:text-primary/80">
            <i class="fas fa-arrow-left mr-2"></i> Back to Event
        </a>
    </div>

    <form action="{{ route('business.tickets.events.update', $event) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Basic Information -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-bold mb-4">Basic Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Event Title *</label>
                    <input type="text" name="title" required value="{{ old('title', $event->title) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    @error('title')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Event Type *</label>
                    <select name="event_type" id="event_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="toggleAddressField()">
                        <option value="offline" {{ old('event_type', $event->event_type ?? 'offline') === 'offline' ? 'selected' : '' }}>Offline (Physical Event)</option>
                        <option value="online" {{ old('event_type', $event->event_type ?? 'offline') === 'online' ? 'selected' : '' }}>Online (Virtual Event)</option>
                    </select>
                    @error('event_type')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg">{{ old('description', $event->description) }}</textarea>
                @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Venue/Location *</label>
                    <input type="text" name="venue" id="venue" required value="{{ old('venue', $event->venue) }}" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                           placeholder="{{ ($event->event_type ?? 'offline') === 'online' ? 'e.g., Zoom, Google Meet' : 'e.g., Conference Hall' }}">
                    <p class="text-xs text-gray-500 mt-1" id="venue-hint">
                        {{ ($event->event_type ?? 'offline') === 'online' ? 'Online platform or virtual location' : 'Physical venue name' }}
                    </p>
                    @error('venue')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div id="address-field-container">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Address <span id="address-required" class="text-red-500 {{ ($event->event_type ?? 'offline') === 'online' ? 'hidden' : '' }}">*</span>
                    </label>
                    <input type="text" name="address" id="address" value="{{ old('address', $event->address) }}" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                           placeholder="{{ ($event->event_type ?? 'offline') === 'online' ? 'Optional: Online platform link' : 'Full address for physical events' }}"
                           {{ ($event->event_type ?? 'offline') === 'offline' ? 'required' : '' }}>
                    @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date & Time *</label>
                    <input type="datetime-local" name="start_date" id="start_date" required value="{{ old('start_date', $event->start_date->format('Y-m-d\TH:i')) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    @error('start_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date & Time *</label>
                    <input type="datetime-local" name="end_date" id="end_date" required value="{{ old('end_date', $event->end_date->format('Y-m-d\TH:i')) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    @error('end_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    <p id="end_date_error" class="text-red-500 text-xs mt-1 hidden">End date must be after start date</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cover Image (Featured Image)</label>
                    @if($event->cover_image)
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . $event->cover_image) }}" alt="Current cover" class="w-32 h-20 object-cover rounded-lg border border-gray-300">
                        </div>
                    @endif
                    <input type="file" name="cover_image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">Recommended: 1200x600px. Leave empty to keep current image.</p>
                    @error('cover_image')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Background Color</label>
                    <div class="flex gap-2">
                        <input type="color" name="background_color" value="{{ old('background_color', $event->background_color ?? '#1e293b') }}" class="h-10 w-20 border border-gray-300 rounded-lg cursor-pointer">
                        <input type="text" name="background_color_text" id="background_color_text" value="{{ old('background_color', $event->background_color ?? '#1e293b') }}" placeholder="#1e293b" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg" onchange="updateColorPicker(this.value)">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Used as fallback if no cover image is uploaded.</p>
                    @error('background_color')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Attendees</label>
                    <input type="number" name="max_attendees" min="1" value="{{ old('max_attendees', $event->max_attendees) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Tickets Per Customer</label>
                <input type="number" name="max_tickets_per_customer" min="1" value="{{ old('max_tickets_per_customer', $event->max_tickets_per_customer) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>

        <!-- Speakers/Artists -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-bold mb-4">Speakers/Artists (Optional)</h2>
            <p class="text-sm text-gray-600 mb-4">Add up to 10 speakers or artists for this event</p>
            <div id="speakers-container" class="space-y-4">
                @if($event->speakers->count() > 0)
                    @foreach($event->speakers as $index => $speaker)
                        <div class="border border-gray-200 rounded-lg p-4 speaker-item">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-semibold">Speaker/Artist {{ $index + 1 }}</h3>
                                <button type="button" onclick="removeSpeaker(this)" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-times"></i> Remove
                                </button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                    <input type="text" name="speakers[{{ $index }}][name]" required value="{{ old("speakers.{$index}.name", $speaker->name) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Speaker/Artist name">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Topic/Performance</label>
                                    <input type="text" name="speakers[{{ $index }}][topic]" value="{{ old("speakers.{$index}.topic", $speaker->topic) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="What they'll speak about or perform">
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Photo</label>
                                @if($speaker->photo)
                                    <div class="mb-2">
                                        <img src="{{ asset('storage/' . $speaker->photo) }}" alt="{{ $speaker->name }}" class="h-20 w-20 object-cover rounded-lg">
                                        <input type="hidden" name="speakers[{{ $index }}][existing_photo]" value="{{ $speaker->photo }}">
                                    </div>
                                @endif
                                <input type="file" name="speakers[{{ $index }}][photo]" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                <p class="text-xs text-gray-500 mt-1">Leave empty to keep current photo, or upload new one</p>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bio/Description (Optional)</label>
                                <textarea name="speakers[{{ $index }}][bio]" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Brief bio or description">{{ old("speakers.{$index}.bio", $speaker->bio) }}</textarea>
                            </div>
                        </div>
                    @endforeach
                @endif
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
                    <input type="checkbox" name="allow_refunds" value="1" {{ old('allow_refunds', $event->allow_refunds) ? 'checked' : '' }} id="allow_refunds" class="mr-2">
                    <label for="allow_refunds" class="text-sm text-gray-700">Allow refunds for this event</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Refund Policy</label>
                    <textarea name="refund_policy" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Enter refund policy...">{{ old('refund_policy', $event->refund_policy) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="draft" {{ old('status', $event->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="published" {{ old('status', $event->status) === 'published' ? 'selected' : '' }}>Published</option>
                        <option value="cancelled" {{ old('status', $event->status) === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-4">
            <a href="{{ route('business.tickets.events.show', $event) }}" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Update Event</button>
        </div>
    </form>
</div>

<script>
// Toggle address field based on event type
function toggleAddressField() {
    const eventType = document.getElementById('event_type').value;
    const addressField = document.getElementById('address');
    const addressRequired = document.getElementById('address-required');
    const venueField = document.getElementById('venue');
    const venueHint = document.getElementById('venue-hint');
    
    if (eventType === 'online') {
        addressField.removeAttribute('required');
        addressRequired.classList.add('hidden');
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
    updateSpeakerCount();
});

// Speakers management
let speakerIndex = {{ $event->speakers->count() }};
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
    updateSpeakerCount();
}

function removeSpeaker(button) {
    button.closest('.speaker-item').remove();
    speakerIndex--;
    updateSpeakerCount();
    
    // Renumber speakers
    const speakers = document.querySelectorAll('.speaker-item');
    speakers.forEach((speaker, index) => {
        speaker.querySelector('h3').textContent = `Speaker/Artist ${index + 1}`;
    });
}

function updateSpeakerCount() {
    const addBtn = document.getElementById('add-speaker-btn');
    if (speakerIndex >= MAX_SPEAKERS) {
        addBtn.style.display = 'none';
    } else {
        addBtn.style.display = 'block';
    }
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
</script>

<script>
// Sync color picker and text input
document.querySelector('input[name="background_color"]')?.addEventListener('input', function(e) {
    document.getElementById('background_color_text').value = e.target.value;
});

function updateColorPicker(value) {
    if (/^#[0-9A-F]{6}$/i.test(value)) {
        document.querySelector('input[name="background_color"]').value = value;
    }
}
</script>
@endsection
