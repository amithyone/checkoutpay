@extends('layouts.business')

@section('title', 'Create Invoice')
@section('page-title', 'Create Invoice')

@section('content')
<div class="max-w-5xl">
    <form action="{{ route('business.invoices.store') }}" method="POST" id="invoiceForm" enctype="multipart/form-data">
        @csrf

        <div class="space-y-6">
            <!-- Logo Upload -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Invoice Logo</h3>
                <div>
                    <label for="logo" class="block text-sm font-medium text-gray-700 mb-2">Upload Logo (Optional)</label>
                    <input type="file" name="logo" id="logo" accept="image/*"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        onchange="previewLogo(this)">
                    <p class="mt-1 text-xs text-gray-500">Recommended: PNG or JPG, max 2MB. Logo will appear on the invoice.</p>
                    @error('logo')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <div id="logo-preview" class="mt-3 hidden">
                        <img id="logo-preview-img" src="" alt="Logo Preview" class="max-w-xs max-h-32 object-contain border border-gray-300 rounded-lg p-2">
                    </div>
                </div>
            </div>

            <!-- Client Information -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Client Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="client_name" class="block text-sm font-medium text-gray-700 mb-1">Client Name *</label>
                        <input type="text" name="client_name" id="client_name" required value="{{ old('client_name') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @error('client_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="client_email" class="block text-sm font-medium text-gray-700 mb-1">Client Email *</label>
                        <input type="email" name="client_email" id="client_email" required value="{{ old('client_email') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @error('client_email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="client_phone" class="block text-sm font-medium text-gray-700 mb-1">Client Phone</label>
                        <input type="text" name="client_phone" id="client_phone" value="{{ old('client_phone') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label for="client_company" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                        <input type="text" name="client_company" id="client_company" value="{{ old('client_company') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label for="client_address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea name="client_address" id="client_address" rows="2"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">{{ old('client_address') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Invoice Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Invoice Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="invoice_date" class="block text-sm font-medium text-gray-700 mb-1">Invoice Date *</label>
                        <input type="date" name="invoice_date" id="invoice_date" required value="{{ old('invoice_date', date('Y-m-d')) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @error('invoice_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                        <input type="date" name="due_date" id="due_date" value="{{ old('due_date') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label for="currency" class="block text-sm font-medium text-gray-700 mb-1">Currency *</label>
                        <select name="currency" id="currency" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            <option value="NGN" {{ old('currency', 'NGN') === 'NGN' ? 'selected' : '' }}>NGN (₦)</option>
                            <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>USD ($)</option>
                            <option value="GBP" {{ old('currency') === 'GBP' ? 'selected' : '' }}>GBP (£)</option>
                            <option value="EUR" {{ old('currency') === 'EUR' ? 'selected' : '' }}>EUR (€)</option>
                        </select>
                    </div>
                    <div>
                        <label for="reference_number" class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                        <input type="text" name="reference_number" id="reference_number" value="{{ old('reference_number') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                </div>
            </div>

            <!-- Invoice Items -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Invoice Items</h3>
                    <button type="button" onclick="addItem()" class="px-3 py-1.5 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                        <i class="fas fa-plus mr-1"></i> Add Item
                    </button>
                </div>
                <div id="items-container">
                    <!-- Items will be added here dynamically -->
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
                                    <input type="number" name="tax_rate" id="tax_rate" step="0.01" min="0" max="100" value="{{ old('tax_rate', 0) }}"
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
                                        <option value="fixed">Fixed Amount</option>
                                        <option value="percentage">Percentage</option>
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <label for="discount_amount" class="block text-xs text-gray-600 mb-1">Discount</label>
                                    <input type="number" name="discount_amount" id="discount_amount" step="0.01" min="0" value="{{ old('discount_amount', 0) }}"
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
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Additional Information</h3>
                <div class="space-y-4">
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" id="notes" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                            placeholder="Additional notes for the client...">{{ old('notes') }}</textarea>
                    </div>
                    <div>
                        <label for="terms_and_conditions" class="block text-sm font-medium text-gray-700 mb-1">Terms & Conditions</label>
                        <textarea name="terms_and_conditions" id="terms_and_conditions" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                            placeholder="Payment terms and conditions...">{{ old('terms_and_conditions') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" name="send_email" id="send_email" value="1" checked
                            class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary">
                        <label for="send_email" class="ml-2 text-sm text-gray-700">Send invoice via email</label>
                    </div>
                    <div class="flex gap-3">
                        <a href="{{ route('business.invoices.index') }}" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium">
                            <i class="fas fa-save mr-2"></i> Create Invoice
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let itemIndex = 0;

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

// Add first item on page load
document.addEventListener('DOMContentLoaded', function() {
    addItem();
    
    // Update currency symbol when currency changes
    document.getElementById('currency').addEventListener('change', function() {
        calculateTotals();
    });
});
</script>
@endsection
