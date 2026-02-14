@extends('layouts.admin')

@section('title', 'Edit Invoice')
@section('page-title', 'Edit Invoice')

@section('content')
<div class="max-w-5xl">
    <form action="{{ route('admin.invoices.update', $invoice) }}" method="POST" id="invoiceForm" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="space-y-6">
            <!-- Business Selection -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Business</h3>
                <div>
                    <label for="business_id" class="block text-sm font-medium text-gray-700 mb-1">Business *</label>
                    <select name="business_id" id="business_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @foreach($businesses as $business)
                            <option value="{{ $business->id }}" {{ old('business_id', $invoice->business_id) == $business->id ? 'selected' : '' }}>
                                {{ $business->name }} ({{ $business->business_id }})
                            </option>
                        @endforeach
                    </select>
                    @error('business_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Client Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Client Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="client_name" class="block text-sm font-medium text-gray-700 mb-1">Client Name *</label>
                        <input type="text" name="client_name" id="client_name" required value="{{ old('client_name', $invoice->client_name) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @error('client_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="client_email" class="block text-sm font-medium text-gray-700 mb-1">Client Email *</label>
                        <input type="email" name="client_email" id="client_email" required value="{{ old('client_email', $invoice->client_email) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @error('client_email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="client_phone" class="block text-sm font-medium text-gray-700 mb-1">Client Phone</label>
                        <input type="text" name="client_phone" id="client_phone" value="{{ old('client_phone', $invoice->client_phone) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label for="client_company" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                        <input type="text" name="client_company" id="client_company" value="{{ old('client_company', $invoice->client_company) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label for="client_address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea name="client_address" id="client_address" rows="2"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">{{ old('client_address', $invoice->client_address) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Invoice Details -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Invoice Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="invoice_date" class="block text-sm font-medium text-gray-700 mb-1">Invoice Date *</label>
                        <input type="date" name="invoice_date" id="invoice_date" required value="{{ old('invoice_date', $invoice->invoice_date->format('Y-m-d')) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @error('invoice_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                        <input type="date" name="due_date" id="due_date" value="{{ old('due_date', $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label for="currency" class="block text-sm font-medium text-gray-700 mb-1">Currency *</label>
                        <select name="currency" id="currency" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            <option value="NGN" {{ old('currency', $invoice->currency) === 'NGN' ? 'selected' : '' }}>NGN (₦)</option>
                            <option value="USD" {{ old('currency', $invoice->currency) === 'USD' ? 'selected' : '' }}>USD ($)</option>
                            <option value="GBP" {{ old('currency', $invoice->currency) === 'GBP' ? 'selected' : '' }}>GBP (£)</option>
                            <option value="EUR" {{ old('currency', $invoice->currency) === 'EUR' ? 'selected' : '' }}>EUR (€)</option>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                        <select name="status" id="status" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            <option value="draft" {{ old('status', $invoice->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="sent" {{ old('status', $invoice->status) === 'sent' ? 'selected' : '' }}>Sent</option>
                            <option value="viewed" {{ old('status', $invoice->status) === 'viewed' ? 'selected' : '' }}>Viewed</option>
                            <option value="paid" {{ old('status', $invoice->status) === 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="overdue" {{ old('status', $invoice->status) === 'overdue' ? 'selected' : '' }}>Overdue</option>
                            <option value="cancelled" {{ old('status', $invoice->status) === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    <div class="md:col-span-2" id="paid-confirmation-notes-wrap" style="{{ old('status', $invoice->status) === 'paid' ? '' : 'display: none' }}">
                        <label for="paid_confirmation_notes" class="block text-sm font-medium text-gray-700 mb-1">Link to received email / confirmation note</label>
                        <input type="text" name="paid_confirmation_notes" id="paid_confirmation_notes" maxlength="500"
                            value="{{ old('paid_confirmation_notes', $invoice->paid_confirmation_notes) }}"
                            placeholder="e.g. Gmail link or 'Confirmed via email from business on ...'"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <p class="text-xs text-gray-500 mt-1">When marking as paid (e.g. business confirmed client paid directly), paste the email link or a short note for audit.</p>
                    </div>
                    <div>
                        <label for="reference_number" class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                        <input type="text" name="reference_number" id="reference_number" value="{{ old('reference_number', $invoice->reference_number) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                </div>
            </div>

            <!-- Split payment (number of times + share by percentage) -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Split payment</h3>
                <div class="space-y-4">
                    <div class="flex items-center">
                        <input type="hidden" name="allow_split_payment" value="0">
                        <input type="checkbox" name="allow_split_payment" id="allow_split_payment" value="1" {{ old('allow_split_payment', $invoice->allow_split_payment) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-primary focus:ring-primary" onchange="toggleSplitOptions()">
                        <label for="allow_split_payment" class="ml-2 text-sm text-gray-700">Allow split payment (client can pay in multiple installments)</label>
                    </div>
                    <div id="split-options" class="{{ old('allow_split_payment', $invoice->allow_split_payment) ? '' : 'hidden' }} space-y-4 pl-0 border-t border-gray-200 pt-4 mt-4">
                        <div>
                            <label for="split_installments" class="block text-sm font-medium text-gray-700 mb-1">Number of installments</label>
                            <input type="number" name="split_installments" id="split_installments" min="2" max="12" value="{{ old('split_installments', $invoice->split_installments ?? 2) }}"
                                class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary" onchange="buildSplitPercentages()">
                            <p class="text-xs text-gray-500 mt-1">How many times the client can pay (e.g. 3 = 3 separate payments).</p>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium text-gray-700">Share by percentage (%)</label>
                                <button type="button" onclick="equalSplit()" class="text-sm text-primary hover:underline">Equal split</button>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Percentages must add up to 100%. Each value is the share for that installment.</p>
                            <div id="split-percentages-container" class="flex flex-wrap gap-3"></div>
                            <p id="split-percent-sum" class="text-sm mt-2 font-medium"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Items -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Invoice Items</h3>
                    <button type="button" onclick="addItem()" class="px-3 py-1.5 bg-primary text-white rounded-lg hover:bg-primary-dark text-sm">
                        <i class="fas fa-plus mr-1"></i> Add Item
                    </button>
                </div>
                <div id="items-container">
                    <!-- Items will be populated from existing invoice -->
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="flex justify-end">
                        <div class="w-full md:w-96 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal:</span>
                                <span class="font-medium" id="subtotal">₦0.00</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex-1">
                                    <label for="tax_rate" class="block text-xs text-gray-600 mb-1">Tax Rate (%)</label>
                                    <input type="number" name="tax_rate" id="tax_rate" step="0.01" min="0" max="100" value="{{ old('tax_rate', $invoice->tax_rate) }}"
                                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-primary focus:border-primary" onchange="calculateTotals()">
                                </div>
                                <div class="flex-1">
                                    <span class="block text-xs text-gray-600 mb-1">Tax Amount</span>
                                    <span class="block font-medium text-sm" id="tax_amount">₦0.00</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex-1">
                                    <label for="discount_type" class="block text-xs text-gray-600 mb-1">Discount Type</label>
                                    <select name="discount_type" id="discount_type" onchange="calculateTotals()"
                                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-primary focus:border-primary">
                                        <option value="">None</option>
                                        <option value="fixed" {{ old('discount_type', $invoice->discount_type) === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                        <option value="percentage" {{ old('discount_type', $invoice->discount_type) === 'percentage' ? 'selected' : '' }}>Percentage</option>
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <label for="discount_amount" class="block text-xs text-gray-600 mb-1">Discount</label>
                                    <input type="number" name="discount_amount" id="discount_amount" step="0.01" min="0" value="{{ old('discount_amount', $invoice->discount_amount) }}"
                                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-primary focus:border-primary" onchange="calculateTotals()">
                                </div>
                            </div>
                            <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-200">
                                <span>Total:</span>
                                <span id="total">₦0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes & Terms -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Additional Information</h3>
                <div class="space-y-4">
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" id="notes" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                            placeholder="Additional notes for the client...">{{ old('notes', $invoice->notes) }}</textarea>
                    </div>
                    <div>
                        <label for="terms_and_conditions" class="block text-sm font-medium text-gray-700 mb-1">Terms & Conditions</label>
                        <textarea name="terms_and_conditions" id="terms_and_conditions" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                            placeholder="Payment terms and conditions...">{{ old('terms_and_conditions', $invoice->terms_and_conditions) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.invoices.show', $invoice) }}" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark font-medium">
                        <i class="fas fa-save mr-2"></i> Update Invoice
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let itemIndex = 0;
const existingItems = @json($invoice->items);
const existingSplitPercentages = @json(old('split_percentages', $invoice->split_percentages ?? []));

function toggleSplitOptions() {
    const opts = document.getElementById('split-options');
    const cb = document.getElementById('allow_split_payment');
    if (cb.checked) {
        opts.classList.remove('hidden');
        buildSplitPercentages();
    } else {
        opts.classList.add('hidden');
    }
}

function buildSplitPercentages() {
    const n = Math.min(12, Math.max(2, parseInt(document.getElementById('split_installments').value, 10) || 2));
    document.getElementById('split_installments').value = n;
    const container = document.getElementById('split-percentages-container');
    container.innerHTML = '';
    const existing = Array.isArray(existingSplitPercentages) && existingSplitPercentages.length === n ? existingSplitPercentages : null;
    const equalPct = (100 / n).toFixed(2);
    for (let i = 0; i < n; i++) {
        const val = existing ? (existing[i] ?? equalPct) : equalPct;
        const div = document.createElement('div');
        div.className = 'flex items-center gap-1';
        div.innerHTML = `
            <label class="text-xs text-gray-600 w-16">Installment ${i + 1}</label>
            <input type="number" name="split_percentages[]" step="0.01" min="0" max="100" value="${val}"
                class="w-20 px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-primary focus:border-primary" oninput="updateSplitSum()">
            <span class="text-gray-500 text-sm">%</span>
        `;
        container.appendChild(div);
    }
    updateSplitSum();
}

function equalSplit() {
    const n = Math.min(12, Math.max(2, parseInt(document.getElementById('split_installments').value, 10) || 2));
    const inputs = document.querySelectorAll('input[name="split_percentages[]"]');
    const pct = (100 / n).toFixed(2);
    const lastPct = (100 - (n - 1) * parseFloat(pct)).toFixed(2);
    inputs.forEach((inp, i) => { inp.value = i === n - 1 ? lastPct : pct; });
    updateSplitSum();
}

function updateSplitSum() {
    const inputs = document.querySelectorAll('input[name="split_percentages[]"]');
    let sum = 0;
    inputs.forEach(inp => { sum += parseFloat(inp.value) || 0; });
    const el = document.getElementById('split-percent-sum');
    if (el) {
        el.textContent = 'Total: ' + sum.toFixed(2) + '%' + (Math.abs(sum - 100) < 0.02 ? ' ✓' : ' (must equal 100%)');
        el.className = 'text-sm mt-2 font-medium ' + (Math.abs(sum - 100) < 0.02 ? 'text-green-600' : 'text-amber-600');
    }
}

function addItem(item = null) {
    const container = document.getElementById('items-container');
    const itemHtml = `
        <div class="item-row border border-gray-200 rounded-lg p-4 mb-3" data-index="${itemIndex}">
            <div class="grid grid-cols-12 gap-3">
                <div class="col-span-12 md:col-span-5">
                    <label class="block text-xs text-gray-600 mb-1">Description *</label>
                    <input type="text" name="items[${itemIndex}][description]" required
                        class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-primary focus:border-primary"
                        value="${item?.description || ''}" onchange="calculateTotals()">
                </div>
                <div class="col-span-4 md:col-span-2">
                    <label class="block text-xs text-gray-600 mb-1">Quantity *</label>
                    <input type="number" name="items[${itemIndex}][quantity]" step="0.01" min="0.01" required
                        class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-primary focus:border-primary"
                        value="${item?.quantity || 1}" onchange="calculateTotals()">
                </div>
                <div class="col-span-4 md:col-span-2">
                    <label class="block text-xs text-gray-600 mb-1">Unit</label>
                    <input type="text" name="items[${itemIndex}][unit]" placeholder="e.g., pcs, hrs"
                        class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-primary focus:border-primary"
                        value="${item?.unit || ''}">
                </div>
                <div class="col-span-4 md:col-span-2">
                    <label class="block text-xs text-gray-600 mb-1">Unit Price *</label>
                    <input type="number" name="items[${itemIndex}][unit_price]" step="0.01" min="0" required
                        class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-primary focus:border-primary"
                        value="${item?.unit_price || 0}" onchange="calculateTotals()">
                </div>
                <div class="col-span-12 md:col-span-1 flex items-end">
                    <button type="button" onclick="removeItem(${itemIndex})" class="w-full px-2 py-1.5 text-red-600 hover:bg-red-50 rounded">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="mt-2">
                <label class="block text-xs text-gray-600 mb-1">Notes</label>
                <input type="text" name="items[${itemIndex}][notes]"
                    class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-primary focus:border-primary"
                    value="${item?.notes || ''}">
            </div>
            <input type="hidden" name="items[${itemIndex}][sort_order]" value="${itemIndex}">
        </div>
    `;
    container.insertAdjacentHTML('beforeend', itemHtml);
    itemIndex++;
    calculateTotals();
}

function removeItem(index) {
    const row = document.querySelector(`[data-index="${index}"]`);
    if (row) {
        row.remove();
        calculateTotals();
    }
}

function calculateTotals() {
    const currency = document.getElementById('currency').value;
    const currencySymbol = currency === 'NGN' ? '₦' : currency === 'USD' ? '$' : currency === 'GBP' ? '£' : '€';
    
    let subtotal = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
        const unitPrice = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
        subtotal += quantity * unitPrice;
    });

    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    const taxAmount = subtotal * (taxRate / 100);

    const discountType = document.getElementById('discount_type').value;
    const discountAmount = parseFloat(document.getElementById('discount_amount').value) || 0;
    let discount = 0;
    if (discountType === 'fixed') {
        discount = discountAmount;
    } else if (discountType === 'percentage') {
        discount = subtotal * (discountAmount / 100);
    }

    const total = subtotal + taxAmount - discount;

    document.getElementById('subtotal').textContent = `${currencySymbol}${subtotal.toFixed(2)}`;
    document.getElementById('tax_amount').textContent = `${currencySymbol}${taxAmount.toFixed(2)}`;
    document.getElementById('total').textContent = `${currencySymbol}${total.toFixed(2)}`;
}

// Load existing items on page load
document.addEventListener('DOMContentLoaded', function() {
    existingItems.forEach(item => {
        addItem(item);
    });
    
    if (existingItems.length === 0) {
        addItem();
    }
    
    calculateTotals();
    toggleSplitOptions();
    if (document.getElementById('allow_split_payment').checked) {
        buildSplitPercentages();
    }

    document.getElementById('currency').addEventListener('change', function() {
        calculateTotals();
    });

    function togglePaidConfirmationNotes() {
        const status = document.getElementById('status').value;
        const wrap = document.getElementById('paid-confirmation-notes-wrap');
        if (wrap) wrap.style.display = status === 'paid' ? 'block' : 'none';
    }
    document.getElementById('status').addEventListener('change', togglePaidConfirmationNotes);
    togglePaidConfirmationNotes();
});

// Logo preview function
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('logo-preview');
            const previewImg = document.getElementById('logo-preview-img');
            previewImg.src = e.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endsection
