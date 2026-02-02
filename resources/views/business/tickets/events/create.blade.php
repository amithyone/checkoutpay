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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cover Image</label>
                    <input type="file" name="cover_image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    @error('cover_image')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
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
</script>
@endsection
