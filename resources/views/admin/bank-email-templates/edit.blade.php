@extends('layouts.admin')

@section('title', 'Edit Bank Email Template')
@section('page-title', 'Edit Bank Email Template')

@section('content')
<div class="max-w-4xl">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form action="{{ route('admin.bank-email-templates.update', $bankEmailTemplate) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-6">
                <!-- Basic Info -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-1">Bank Name *</label>
                            <input type="text" name="bank_name" id="bank_name" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                                value="{{ old('bank_name', $bankEmailTemplate->bank_name) }}" placeholder="e.g., GTBank, Access Bank">
                        </div>

                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                            <input type="number" name="priority" id="priority" min="0" max="100"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                                value="{{ old('priority', $bankEmailTemplate->priority) }}">
                            <p class="text-xs text-gray-500 mt-1">Higher priority templates are checked first (0-100)</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label for="sender_email" class="block text-sm font-medium text-gray-700 mb-1">Sender Email</label>
                            <input type="email" name="sender_email" id="sender_email"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                                value="{{ old('sender_email', $bankEmailTemplate->sender_email) }}" placeholder="e.g., alerts@gtbank.com">
                            <p class="text-xs text-gray-500 mt-1">Exact sender email (optional)</p>
                        </div>

                        <div>
                            <label for="sender_domain" class="block text-sm font-medium text-gray-700 mb-1">Sender Domain</label>
                            <input type="text" name="sender_domain" id="sender_domain"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                                value="{{ old('sender_domain', $bankEmailTemplate->sender_domain) }}" placeholder="e.g., @gtbank.com">
                            <p class="text-xs text-gray-500 mt-1">Domain pattern (optional, e.g., @gtbank.com)</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $bankEmailTemplate->is_active) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-primary focus:ring-primary">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>
                </div>

                <!-- Field Mappings -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Field Mappings</h3>
                    <p class="text-sm text-gray-600 mb-4">Specify which HTML table fields contain the amount and sender name</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="amount_field_label" class="block text-sm font-medium text-gray-700 mb-1">Amount Field Label</label>
                            <input type="text" name="amount_field_label" id="amount_field_label"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                                value="{{ old('amount_field_label', $bankEmailTemplate->amount_field_label) }}" placeholder="e.g., Amount, Sum, Value">
                            <p class="text-xs text-gray-500 mt-1">Label in HTML table</p>
                        </div>

                        <div>
                            <label for="sender_name_field_label" class="block text-sm font-medium text-gray-700 mb-1">Sender Name Field Label</label>
                            <input type="text" name="sender_name_field_label" id="sender_name_field_label"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                                value="{{ old('sender_name_field_label', $bankEmailTemplate->sender_name_field_label) }}" placeholder="e.g., Description, From, Sender">
                            <p class="text-xs text-gray-500 mt-1">Label in HTML table</p>
                        </div>

                        <div>
                            <label for="account_number_field_label" class="block text-sm font-medium text-gray-700 mb-1">Account Number Field Label</label>
                            <input type="text" name="account_number_field_label" id="account_number_field_label"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                                value="{{ old('account_number_field_label', $bankEmailTemplate->account_number_field_label) }}" placeholder="e.g., Account Number">
                            <p class="text-xs text-gray-500 mt-1">Label in HTML table (optional)</p>
                        </div>
                    </div>
                </div>

                <!-- Extraction Patterns -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Extraction Patterns (Advanced)</h3>
                    <p class="text-sm text-gray-600 mb-4">Regex patterns for extracting data (optional, uses field labels if not specified)</p>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="amount_pattern" class="block text-sm font-medium text-gray-700 mb-1">Amount Pattern</label>
                            <input type="text" name="amount_pattern" id="amount_pattern"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary font-mono text-sm"
                                value="{{ old('amount_pattern', $bankEmailTemplate->amount_pattern) }}" placeholder="e.g., /(?:ngn|naira|₦)\s*([\d,]+\.?\d*)/i">
                            <p class="text-xs text-gray-500 mt-1">Regex pattern to extract amount</p>
                        </div>

                        <div>
                            <label for="sender_name_pattern" class="block text-sm font-medium text-gray-700 mb-1">Sender Name Pattern</label>
                            <input type="text" name="sender_name_pattern" id="sender_name_pattern"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary font-mono text-sm"
                                value="{{ old('sender_name_pattern', $bankEmailTemplate->sender_name_pattern) }}" placeholder="e.g., /from\s+([A-Z][A-Z\s]+?)\s+to/i">
                            <p class="text-xs text-gray-500 mt-1">Regex pattern to extract sender name</p>
                        </div>

                        <div>
                            <label for="account_number_pattern" class="block text-sm font-medium text-gray-700 mb-1">Account Number Pattern</label>
                            <input type="text" name="account_number_pattern" id="account_number_pattern"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary font-mono text-sm"
                                value="{{ old('account_number_pattern', $bankEmailTemplate->account_number_pattern) }}" placeholder="e.g., /account\s*number[\s:]+(\d+)/i">
                            <p class="text-xs text-gray-500 mt-1">Regex pattern to extract account number</p>
                        </div>
                    </div>
                </div>

                <!-- Sample Email -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Sample Email</h3>
                    <p class="text-sm text-gray-600 mb-4">Paste a sample email HTML/Text for reference</p>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="sample_html" class="block text-sm font-medium text-gray-700 mb-1">Sample HTML</label>
                            <textarea name="sample_html" id="sample_html" rows="8"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary font-mono text-xs"
                                placeholder="Paste sample HTML email here...">{{ old('sample_html', $bankEmailTemplate->sample_html) }}</textarea>
                        </div>

                        <div>
                            <label for="sample_text" class="block text-sm font-medium text-gray-700 mb-1">Sample Text</label>
                            <textarea name="sample_text" id="sample_text" rows="6"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary font-mono text-xs"
                                placeholder="Paste sample text email here...">{{ old('sample_text', $bankEmailTemplate->sample_text) }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label for="extraction_notes" class="block text-sm font-medium text-gray-700 mb-1">Extraction Notes</label>
                    <textarea name="extraction_notes" id="extraction_notes" rows="4"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        placeholder="Notes on how to extract data from this bank's emails...">{{ old('extraction_notes', $bankEmailTemplate->extraction_notes) }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">Documentation for future reference</p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.bank-email-templates.index') }}" 
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90">
                        <i class="fas fa-save mr-2"></i> Update Template
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
