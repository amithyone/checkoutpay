<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#3C50E0' },
                        rental: { DEFAULT: '#BE845D' },
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @if(config('services.recaptcha.enabled') && config('services.recaptcha.site_key'))
    <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}" async defer></script>
    @endif
</head>
<body class="bg-gray-50">
    @php $rentalsColor = \App\Models\Setting::get('rentals_accent_color', '#000000'); @endphp
    @include('partials.nav')

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-24 sm:pb-8">
        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 mb-4 sm:mb-6">Checkout</h1>

        @if(!$hasDates)
            {{-- Step 1: Pick one item, then set its rental dates (each item has its own dates) --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-2">Set dates per item</h2>
                <p class="text-sm text-gray-600 mb-4">Each gear can have different availability. Click an item below to select it, then pick its rental dates on the calendar (tap to select/deselect). Grey = already booked for that item.</p>

                @php $datesConflictItemIds = $datesConflictItemIds ?? []; @endphp
                @if(count($datesConflictItemIds) > 0)
                    <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
                        <strong>Fix dates for {{ count($datesConflictItemIds) }} item(s):</strong> Those items are not available for the dates you applied. Click each red item below to set different dates. You cannot proceed until all are fixed.
                    </div>
                @endif

                {{-- Item list: click to highlight and show calendar for that item --}}
                <div class="mb-4">
                    <p class="text-xs font-medium text-gray-500 mb-2">Click an item to set its dates</p>
                    <div class="space-y-2" id="rental-item-list">
                        @foreach($items as $item)
                            @php
                                $itemDates = $item->cart_selected_dates ?? [];
                                $daysCount = count($itemDates);
                                $daysLabel = $daysCount === 1 ? 'day' : 'days';
                                $hasConflict = in_array($item->id, $datesConflictItemIds);
                            @endphp
                            <button type="button" class="rental-item-btn w-full text-left rounded-xl border-2 p-3 sm:p-4 transition-colors flex justify-between items-center gap-2 {{ $hasConflict ? 'border-red-500 bg-red-50' : 'border-gray-200' }}"
                                data-item-id="{{ $item->id }}"
                                data-item-name="{{ e($item->name) }}"
                                data-selected-dates="{{ !empty($itemDates) ? json_encode($itemDates) : '[]' }}">
                                <div class="min-w-0 flex items-center gap-2">
                                    @if($hasConflict)
                                        <span class="flex-shrink-0 w-2 h-2 rounded-full bg-red-500" title="Not available for applied dates – click to fix"></span>
                                    @endif
                                    <div>
                                        <span class="font-semibold text-gray-900 text-sm sm:text-base block truncate">{{ $item->name }}</span>
                                        <span class="text-xs sm:text-sm {{ $hasConflict ? 'text-red-600' : ($daysCount ? 'text-green-600' : 'text-gray-500') }}">
                                            @if($hasConflict)
                                                Unavailable for applied dates – click to fix
                                            @elseif($daysCount)
                                                {{ $daysCount }} {{ $daysLabel }} selected
                                            @else
                                                No dates set
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <form action="{{ route('rentals.cart.remove', $item->id) }}" method="POST" onsubmit="return confirm('Remove this item?');" class="flex-shrink-0" onclick="event.stopPropagation();">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs sm:text-sm p-1"><i class="fas fa-trash"></i></button>
                                </form>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Calendar: shown when an item is selected; uses that item's availability --}}
                <div id="rental-calendar-section" class="hidden">
                    <p class="text-sm font-medium text-gray-800 mb-2" id="rental-calendar-item-label">Select dates for <span id="rental-calendar-item-name"></span></p>
                    <div class="rental-calendar mb-4" data-unavailable-url="{{ route('rentals.checkout.unavailable-dates') }}" data-accent="{{ $rentalsColor }}" data-today="{{ date('Y-m-d') }}">
                        <div class="flex items-center justify-between mb-3">
                            <button type="button" class="rental-cal-prev w-10 h-10 sm:w-12 sm:h-12 rounded-xl border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100" aria-label="Previous month">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span class="rental-cal-month text-base sm:text-lg font-semibold text-gray-900"></span>
                            <button type="button" class="rental-cal-next w-10 h-10 sm:w-12 sm:h-12 rounded-xl border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100" aria-label="Next month">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <div class="grid grid-cols-7 gap-0.5 sm:gap-1 text-center">
                            <span class="text-xs sm:text-sm font-medium text-gray-500 py-1">Sun</span>
                            <span class="text-xs sm:text-sm font-medium text-gray-500 py-1">Mon</span>
                            <span class="text-xs sm:text-sm font-medium text-gray-500 py-1">Tue</span>
                            <span class="text-xs sm:text-sm font-medium text-gray-500 py-1">Wed</span>
                            <span class="text-xs sm:text-sm font-medium text-gray-500 py-1">Thu</span>
                            <span class="text-xs sm:text-sm font-medium text-gray-500 py-1">Fri</span>
                            <span class="text-xs sm:text-sm font-medium text-gray-500 py-1">Sat</span>
                            <div class="rental-cal-days col-span-7 grid grid-cols-7 gap-0.5 sm:gap-1"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2 flex items-center gap-2 flex-wrap">
                            <span class="inline-flex w-6 h-6 rounded border border-gray-300 bg-gray-100"></span> Booked
                            <span class="inline-flex w-6 h-6 rounded ml-2" style="background-color: {{ $rentalsColor }}20;"></span> Selected
                        </p>
                    </div>

                    <div class="rental-cal-summary hidden mb-4 p-3 rounded-xl border border-gray-200 bg-gray-50">
                        <p class="text-sm font-medium text-gray-800"><span class="rental-cal-summary-text"></span></p>
                    </div>

                    <form action="{{ route('rentals.cart.dates') }}" method="POST" id="rental-dates-form" class="hidden">
                        @csrf
                        <input type="hidden" name="apply_to_all" id="rental-apply-to-all-input" value="0">
                        <input type="hidden" name="item_id" id="rental-dates-item-id" value="">
                        <div id="selected-dates-inputs"></div>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <button type="submit" id="rental-dates-apply" class="flex-1 text-white py-2.5 sm:py-3 rounded-xl font-medium text-sm sm:text-base" style="background-color: {{ $rentalsColor }};">
                                Save dates for <span id="rental-dates-apply-name"></span>
                            </button>
                            <button type="button" id="rental-dates-apply-all" class="flex-1 py-2.5 sm:py-3 rounded-xl font-medium text-sm sm:text-base border-2 border-gray-300 text-gray-700 hover:bg-gray-50">
                                Apply these dates to all items
                            </button>
                        </div>
                    </form>
                </div>

                <p id="rental-pick-item-hint" class="text-sm text-gray-500">Select an item above to set its rental dates.</p>
            </div>

            <script>
            (function() {
                var calEl = document.querySelector('.rental-calendar');
                var today = calEl.dataset.today;
                var accent = calEl.dataset.accent;
                var url = calEl.dataset.unavailableUrl;
                var current = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
                var unavailByItem = {};
                var selectedDates = {};
                var selectedItemId = null;
                var selectedItemName = '';

                function monthKey(d) {
                    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                }
                function dateStr(d) {
                    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                }

                function getUnavailKey(month) {
                    return (selectedItemId || '') + ':' + month;
                }

                function fetchUnavailable(month, cb) {
                    var key = getUnavailKey(month);
                    if (unavailByItem[key]) { cb(); return; }
                    var q = '?month=' + month;
                    if (selectedItemId) q += '&item_id=' + selectedItemId;
                    fetch(url + q)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            unavailByItem[key] = (data.unavailable || []).reduce(function(o, d) { o[d] = true; return o; }, {});
                            cb();
                        })
                        .catch(function() { unavailByItem[key] = {}; cb(); });
                }

                function render() {
                    var year = current.getFullYear();
                    var month = current.getMonth();
                    var mk = monthKey(current);
                    document.querySelector('.rental-cal-month').textContent = new Date(year, month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

                    var first = new Date(year, month, 1);
                    var last = new Date(year, month + 1, 0);
                    var startPad = first.getDay();
                    var daysInMonth = last.getDate();
                    var unav = unavailByItem[getUnavailKey(mk)] || {};

                    var html = '';
                    for (var i = 0; i < startPad; i++) html += '<span class="aspect-square"></span>';
                    for (var d = 1; d <= daysInMonth; d++) {
                        var dt = new Date(year, month, d);
                        var ds = dateStr(dt);
                        var isPast = ds < today;
                        var isUnav = unav[ds];
                        var disabled = isPast || isUnav;
                        var isSelected = selectedDates[ds];
                        var cls = 'aspect-square min-w-[36px] min-h-[36px] sm:min-w-[44px] sm:min-h-[44px] flex items-center justify-center rounded-lg text-sm sm:text-base font-medium select-none ';
                        if (disabled) cls += ' bg-gray-100 text-gray-400 cursor-not-allowed ';
                        else cls += ' cursor-pointer hover:opacity-90 ';
                        if (isSelected) cls += ' text-white ';
                        html += '<button type="button" class="' + cls + '" data-date="' + ds + '" ' + (disabled ? 'disabled' : '') + ' style="' + (disabled ? '' : (isSelected ? 'background-color:' + accent : '')) + '">' + d + '</button>';
                    }
                    document.querySelector('.rental-cal-days').innerHTML = html;

                    document.querySelectorAll('.rental-cal-days button[data-date]').forEach(function(btn) {
                        if (btn.disabled) return;
                        btn.addEventListener('click', function() {
                            var d = this.dataset.date;
                            if (selectedDates[d]) delete selectedDates[d];
                            else selectedDates[d] = true;
                            render();
                            updateSummary();
                        });
                    });
                }

                function updateSummary() {
                    var form = document.getElementById('rental-dates-form');
                    var summaryEl = document.querySelector('.rental-cal-summary');
                    var summaryText = document.querySelector('.rental-cal-summary-text');
                    var list = Object.keys(selectedDates).sort();
                    if (list.length > 0) {
                        var txt = list.length + ' day' + (list.length !== 1 ? 's' : '') + ' selected';
                        if (list.length <= 5) {
                            txt += ': ' + list.map(function(d) { return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }); }).join(', ');
                        } else {
                            txt += ': ' + list.slice(0, 2).map(function(d) { return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }); }).join(', ') + ' … +' + (list.length - 2) + ' more';
                        }
                        summaryText.textContent = txt;
                        summaryEl.classList.remove('hidden');
                        form.classList.remove('hidden');
                    } else {
                        summaryEl.classList.add('hidden');
                        form.classList.add('hidden');
                    }
                }

                function selectItem(btn) {
                    document.querySelectorAll('.rental-item-btn').forEach(function(b) {
                        b.classList.add('border-gray-200');
                        b.style.borderColor = '';
                    });
                    btn.classList.remove('border-gray-200');
                    btn.style.borderColor = accent;

                    selectedItemId = parseInt(btn.dataset.itemId, 10);
                    selectedItemName = btn.dataset.itemName || '';
                    var datesJson = btn.dataset.selectedDates || '[]';
                    try {
                        var arr = JSON.parse(datesJson);
                        selectedDates = {};
                        arr.forEach(function(d) { selectedDates[d] = true; });
                    } catch (e) {
                        selectedDates = {};
                    }

                    document.getElementById('rental-calendar-section').classList.remove('hidden');
                    document.getElementById('rental-pick-item-hint').classList.add('hidden');
                    document.getElementById('rental-calendar-item-name').textContent = selectedItemName;
                    document.getElementById('rental-dates-apply-name').textContent = selectedItemName;
                    document.getElementById('rental-dates-item-id').value = selectedItemId;

                    current = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
                    fetchUnavailable(monthKey(current), function() {
                        render();
                        updateSummary();
                    });
                }

                document.getElementById('rental-item-list').addEventListener('click', function(e) {
                    var btn = e.target.closest('.rental-item-btn');
                    if (btn) selectItem(btn);
                });

                document.getElementById('rental-dates-form').addEventListener('submit', function(e) {
                    var list = Object.keys(selectedDates).sort();
                    if (list.length === 0) {
                        e.preventDefault();
                        return;
                    }
                    document.getElementById('rental-apply-to-all-input').value = '0';
                    document.getElementById('rental-dates-item-id').value = selectedItemId;
                    var container = document.getElementById('selected-dates-inputs');
                    container.innerHTML = list.map(function(d) { return '<input type="hidden" name="selected_dates[]" value="' + d + '">'; }).join('');
                });

                document.getElementById('rental-dates-apply-all').addEventListener('click', function() {
                    var list = Object.keys(selectedDates).sort();
                    if (list.length === 0) return;
                    var container = document.getElementById('selected-dates-inputs');
                    container.innerHTML = list.map(function(d) { return '<input type="hidden" name="selected_dates[]" value="' + d + '">'; }).join('');
                    document.getElementById('rental-apply-to-all-input').value = '1';
                    document.getElementById('rental-dates-form').submit();
                });

                document.querySelector('.rental-cal-prev').addEventListener('click', function() {
                    current.setMonth(current.getMonth() - 1);
                    fetchUnavailable(monthKey(current), render);
                });
                document.querySelector('.rental-cal-next').addEventListener('click', function() {
                    current.setMonth(current.getMonth() + 1);
                    fetchUnavailable(monthKey(current), render);
                });

                var firstBtn = document.querySelector('.rental-item-btn');
                if (firstBtn) selectItem(firstBtn);
            })();
            </script>
        @else
            {{-- Cart with dates (each item has its own dates) --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-2 sm:mb-4">Your rental items</h2>
                <p class="text-xs sm:text-sm text-gray-600 mb-3 sm:mb-4">Each item has its own rental dates.</p>
                @foreach($items as $item)
                    @php
                        $itemDays = !empty($item->cart_selected_dates)
                            ? count($item->cart_selected_dates)
                            : \Carbon\Carbon::parse($item->cart_start_date)->diffInDays(\Carbon\Carbon::parse($item->cart_end_date)) + 1;
                        $itemTotal = $item->getRateForPeriod($itemDays) * $item->cart_quantity;
                        $dayLabel = $itemDays === 1 ? 'day' : 'days';
                    @endphp
                    <div class="border-b border-gray-100 pb-3 sm:pb-4 mb-3 sm:mb-4 last:border-0 last:mb-0">
                        <div class="flex justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-gray-900 text-sm sm:text-base">{{ $item->name }}</h3>
                                <p class="text-xs sm:text-sm text-gray-600">
                                    {{ \Carbon\Carbon::parse($item->cart_start_date)->format('M d') }} – {{ \Carbon\Carbon::parse($item->cart_end_date)->format('M d, Y') }}
                                    ({{ $itemDays }} {{ $dayLabel }}) • Qty: {{ $item->cart_quantity }}
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="font-semibold text-gray-900 text-sm sm:text-base">₦{{ number_format($itemTotal, 2) }}</p>
                                <form action="{{ route('rentals.cart.remove', $item->id) }}" method="POST" class="mt-1" onsubmit="return confirm('Remove this item?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs sm:text-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
                <p class="text-xs sm:text-sm text-gray-500 mt-3">
                    <a href="{{ route('rentals.checkout', ['change_dates' => 1]) }}" class="font-medium" style="color: {{ $rentalsColor }};">Change dates</a>
                </p>
            </div>

            @if($isRenter)
                {{-- Logged-in renter: Your information (no password; KYC only if needed) --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-2 sm:mb-4">Your information</h2>
                    <p class="text-sm text-gray-600 mb-4">Confirm your details to continue. {{ $needsKyc ? 'Complete KYC below if not done yet.' : 'KYC already verified.' }}</p>

                    <form action="{{ route('rentals.checkout.continue') }}" method="POST" id="renterInfoForm" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3 sm:mb-4">
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" value="{{ $renter->email }}" disabled class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm sm:text-base bg-gray-50 text-gray-600">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-3 sm:mb-4">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Phone *</label>
                                <input type="tel" name="phone" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm sm:text-base" value="{{ old('phone', $renter->phone) }}" required placeholder="Your phone number">
                            </div>
                            <div class="sm:col-span-2 sm:col-span-1">
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Address *</label>
                                <input type="text" name="address" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm sm:text-base" value="{{ old('address', $renter->address) }}" required placeholder="Your address">
                            </div>
                        </div>

                        @if($needsKyc)
                            <div class="border-t border-gray-200 pt-4 mb-4">
                                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-2">Account verification (KYC)</h3>
                                <p class="text-xs sm:text-sm text-gray-600 mb-3">Bank account details to verify your identity.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-3 sm:mb-4">
                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Account number *</label>
                                        <input type="text" name="account_number" id="account_number_renter" required maxlength="10" pattern="[0-9]{10}" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm sm:text-base" placeholder="10 digits" value="{{ old('account_number', $renter->verified_account_number) }}">
                                    </div>
                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Bank *</label>
                                        <div class="relative">
                                            <input type="text" id="bank_search_renter" autocomplete="off" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm sm:text-base" placeholder="Search bank..." value="{{ old('bank_name', $renter->verified_bank_name) }}">
                                            <input type="hidden" name="bank_code" id="bank_code_renter" value="{{ old('bank_code', $renter->verified_bank_code) }}">
                                            <div id="bank_dropdown_renter" class="hidden absolute z-10 w-full bg-white border border-gray-300 rounded-xl shadow-lg max-h-48 overflow-y-auto mt-1"></div>
                                        </div>
                                        <p id="verified_account_name_renter" class="text-xs sm:text-sm text-green-600 mt-1 hidden"></p>
                                    </div>
                                </div>
                                <div class="mt-3 sm:mt-4">
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">ID card (photo or scan) *</label>
                                    <input type="file" name="id_card" id="id_card_renter" accept=".jpg,.jpeg,.png,.pdf" required class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:cursor-pointer border border-gray-300 rounded-xl">
                                    <p class="text-xs text-gray-500 mt-1">JPEG, PNG or PDF, max 5MB</p>
                                    @error('id_card')
                                        <p class="text-red-600 text-xs sm:text-sm mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @endif

                        <button type="submit" id="submitBtnRenter" class="w-full text-white py-2.5 sm:py-3 rounded-xl font-medium text-sm sm:text-base" style="background-color: {{ $rentalsColor }};">
                            <i class="fas fa-arrow-right mr-2"></i> Continue
                        </button>
                    </form>
                </div>
            @else
                {{-- Guest: Create account (email, password, KYC) --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-2 sm:mb-4">Create account</h2>
                    <p class="text-sm text-gray-600 mb-4">Enter your email, set a password, and verify with your bank details to continue.</p>

                    <form action="{{ route('rentals.account.create') }}" method="POST" id="accountForm" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3 sm:mb-4">
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" name="email" id="email" required class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm sm:text-base" value="{{ old('email') }}" placeholder="you@example.com">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-3 sm:mb-4">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Password *</label>
                                <input type="password" name="password" required minlength="8" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm sm:text-base" placeholder="Min 8 characters">
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Confirm password *</label>
                                <input type="password" name="password_confirmation" required class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm sm:text-base">
                            </div>
                        </div>
                        <div class="border-t border-gray-200 pt-4 mb-4">
                            <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-2">Account verification (KYC)</h3>
                            <p class="text-xs sm:text-sm text-gray-600 mb-3">Bank account details to verify your identity.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-3 sm:mb-4">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Account number *</label>
                                    <input type="text" name="account_number" id="account_number" required maxlength="10" pattern="[0-9]{10}" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm sm:text-base" placeholder="10 digits" value="{{ old('account_number') }}">
                                </div>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Bank *</label>
                                    <div class="relative">
                                        <input type="text" id="bank_search" autocomplete="off" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm sm:text-base" placeholder="Search bank...">
                                        <input type="hidden" name="bank_code" id="bank_code" required>
                                        <div id="bank_dropdown" class="hidden absolute z-10 w-full bg-white border border-gray-300 rounded-xl shadow-lg max-h-48 overflow-y-auto mt-1"></div>
                                    </div>
                                    <p id="verified_account_name" class="text-xs sm:text-sm text-green-600 mt-1 hidden"></p>
                                </div>
                            </div>
                            <div class="mt-3 sm:mt-4">
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">ID card (photo or scan) *</label>
                                <input type="file" name="id_card" id="id_card_guest" accept=".jpg,.jpeg,.png,.pdf" required class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:cursor-pointer border border-gray-300 rounded-xl">
                                <p class="text-xs text-gray-500 mt-1">JPEG, PNG or PDF, max 5MB</p>
                                @error('id_card')
                                    <p class="text-red-600 text-xs sm:text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-3 sm:mb-4">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="tel" name="phone" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm sm:text-base" value="{{ old('phone') }}" placeholder="Optional">
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Address</label>
                                <input type="text" name="address" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm sm:text-base" value="{{ old('address') }}" placeholder="Optional">
                            </div>
                        </div>
                        @if(config('services.recaptcha.enabled') && config('services.recaptcha.site_key'))
                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" value="">
                        @error('g-recaptcha-response')
                            <p class="text-red-600 text-xs sm:text-sm mt-1 mb-2">{{ $message }}</p>
                        @enderror
                        @endif
                        <button type="submit" id="submitBtn" class="w-full text-white py-2.5 sm:py-3 rounded-xl font-medium text-sm sm:text-base" style="background-color: {{ $rentalsColor }};">
                            <i class="fas fa-user-check mr-2"></i> Verify & create account
                        </button>
                    </form>
                </div>
            @endif
        @endif

        @if($hasDates && !$isRenter)
        <script>
            const banks = @json(config('banks', []));
            const bankSearch = document.getElementById('bank_search');
            const bankDropdown = document.getElementById('bank_dropdown');
            const bankCodeInput = document.getElementById('bank_code');
            const accountNumberInput = document.getElementById('account_number');
            const verifiedAccountName = document.getElementById('verified_account_name');
            const submitBtn = document.getElementById('submitBtn');

            if (bankSearch) {
                bankSearch.addEventListener('input', function() {
                    const search = this.value.toLowerCase();
                    if (search.length < 2) { bankDropdown.classList.add('hidden'); return; }
                    const filtered = banks.filter(bank => bank.bank_name.toLowerCase().includes(search)).slice(0, 10);
                    if (filtered.length > 0) {
                        bankDropdown.innerHTML = filtered.map(bank => {
                            const code = bank.code.replace(/'/g, "\\'");
                            const name = bank.bank_name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                            return `<div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm" onclick="selectBank('${code}', '${name}')">${bank.bank_name}</div>`;
                        }).join('');
                        bankDropdown.classList.remove('hidden');
                    } else { bankDropdown.classList.add('hidden'); }
                });
            }
            function selectBank(code, name) {
                bankSearch.value = name;
                bankCodeInput.value = code;
                bankDropdown.classList.add('hidden');
                if (accountNumberInput && accountNumberInput.value.length === 10) verifyAccount();
            }
            function verifyAccount() {
                const an = accountNumberInput.value.replace(/\D/g, '');
                const bc = bankCodeInput.value;
                if (an.length !== 10 || !bc) { verifiedAccountName.classList.add('hidden'); return; }
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Verifying...';
                fetch('{{ route("rentals.kyc.verify") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ account_number: an, bank_code: bc })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.account_name) {
                        verifiedAccountName.textContent = 'Verified: ' + data.account_name;
                        verifiedAccountName.classList.remove('hidden');
                    } else {
                        verifiedAccountName.textContent = 'Verification failed';
                        verifiedAccountName.classList.remove('text-green-600');
                        verifiedAccountName.classList.add('text-red-600');
                        verifiedAccountName.classList.remove('hidden');
                    }
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-user-check mr-2"></i> Verify & create account';
                })
                .catch(() => {
                    verifiedAccountName.textContent = 'Verification error';
                    verifiedAccountName.classList.add('text-yellow-600');
                    verifiedAccountName.classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-user-check mr-2"></i> Verify & create account';
                });
            }
            if (accountNumberInput) accountNumberInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 10);
                if (this.value.length === 10 && bankCodeInput.value) verifyAccount();
            });
            document.addEventListener('click', function(e) {
                if (bankDropdown && !bankSearch.contains(e.target) && !bankDropdown.contains(e.target)) bankDropdown.classList.add('hidden');
            });
            @if(config('services.recaptcha.enabled') && config('services.recaptcha.site_key'))
            const accountForm = document.getElementById('accountForm');
            const recaptchaInput = document.getElementById('g-recaptcha-response');
            if (accountForm && recaptchaInput) {
                accountForm.addEventListener('submit', function(e) {
                    if (recaptchaInput.value) return;
                    e.preventDefault();
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Please wait...';
                    grecaptcha.ready(function() {
                        grecaptcha.execute({{ json_encode(config('services.recaptcha.site_key')) }}, { action: 'rental_register' }).then(function(token) {
                            recaptchaInput.value = token;
                            accountForm.submit();
                        }).catch(function() {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-user-check mr-2"></i> Verify & create account';
                        });
                    });
                });
            }
            @endif
        </script>
        @endif

        @if($hasDates && $isRenter && $needsKyc)
        <script>
            (function() {
                const banks = @json(config('banks', []));
                const bankSearch = document.getElementById('bank_search_renter');
                const bankDropdown = document.getElementById('bank_dropdown_renter');
                const bankCodeInput = document.getElementById('bank_code_renter');
                const accountNumberInput = document.getElementById('account_number_renter');
                if (!bankSearch || !bankDropdown) return;
                bankSearch.addEventListener('input', function() {
                    const search = this.value.toLowerCase();
                    if (search.length < 2) { bankDropdown.classList.add('hidden'); return; }
                    const filtered = banks.filter(bank => bank.bank_name.toLowerCase().includes(search)).slice(0, 10);
                    if (filtered.length > 0) {
                        bankDropdown.innerHTML = filtered.map(bank => {
                            const code = bank.code.replace(/'/g, "\\'");
                            const name = bank.bank_name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                            return `<div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm" onclick="document.getElementById('bank_code_renter').value='${code}'; document.getElementById('bank_search_renter').value='${name}'; document.getElementById('bank_dropdown_renter').classList.add('hidden');">${bank.bank_name}</div>`;
                        }).join('');
                        bankDropdown.classList.remove('hidden');
                    } else { bankDropdown.classList.add('hidden'); }
                });
                if (accountNumberInput) accountNumberInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '').slice(0, 10);
                });
                document.addEventListener('click', function(e) {
                    if (!bankSearch.contains(e.target) && !bankDropdown.contains(e.target)) bankDropdown.classList.add('hidden');
                });
            })();
        </script>
        @endif
    </div>

    <div id="toast-container" class="fixed top-20 right-4 z-50 space-y-2"></div>

    @php $cartCount = count(session('rental_cart', [])); @endphp
    @if($cartCount > 0)
        <a href="{{ route('rentals.checkout') }}" class="fixed bottom-20 right-4 sm:bottom-6 sm:right-6 text-white rounded-full p-4 shadow-lg z-50" style="background-color: {{ $rentalsColor }};">
            <i class="fas fa-shopping-cart text-xl"></i>
            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center">{{ $cartCount }}</span>
        </a>
    @endif

    <script>
        function showToast(message, type) {
            var c = document.getElementById('toast-container');
            if (!c) return;
            var t = document.createElement('div');
            t.className = (type === 'success' ? 'bg-green-500' : 'bg-red-500') + ' text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-2 text-sm';
            t.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i><span>' + message + '</span>';
            c.appendChild(t);
            setTimeout(function(){ t.remove(); }, 3000);
        }
        @if(session('success')) showToast('{{ session('success') }}', 'success'); @endif
        @if(session('error')) showToast('{{ session('error') }}', 'error'); @endif
        @if(session('warning')) showToast('{{ session('warning') }}', 'error'); @endif
    </script>
</body>
</html>
