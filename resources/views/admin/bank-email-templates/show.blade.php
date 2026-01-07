@extends('layouts.admin')

@section('title', 'Bank Email Template Details')
@section('page-title', 'Bank Email Template Details')

@section('content')
<div class="space-y-6">
    <!-- Back Button -->
    <div>
        <a href="{{ route('admin.bank-email-templates.index') }}" class="text-primary hover:underline">
            <i class="fas fa-arrow-left mr-2"></i> Back to Templates
        </a>
    </div>

    <!-- Template Details -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">{{ $bankEmailTemplate->bank_name }}</h2>
                    <div class="mt-2 flex items-center gap-4 text-sm text-gray-600">
                        @if($bankEmailTemplate->sender_email)
                            <span><i class="fas fa-envelope mr-1"></i> {{ $bankEmailTemplate->sender_email }}</span>
                        @endif
                        @if($bankEmailTemplate->sender_domain)
                            <span><i class="fas fa-globe mr-1"></i> {{ $bankEmailTemplate->sender_domain }}</span>
                        @endif
                        <span><i class="fas fa-sort-numeric-up mr-1"></i> Priority: {{ $bankEmailTemplate->priority }}</span>
                    </div>
                </div>
                <div>
                    @if($bankEmailTemplate->is_active)
                        <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                    @else
                        <span class="px-3 py-1 text-sm font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <!-- Field Mappings -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Field Mappings</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Amount Field</label>
                        <p class="mt-1 text-sm text-gray-900 font-semibold">
                            {{ $bankEmailTemplate->amount_field_label ?? '-' }}
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Sender Name Field</label>
                        <p class="mt-1 text-sm text-gray-900 font-semibold">
                            {{ $bankEmailTemplate->sender_name_field_label ?? '-' }}
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Account Number Field</label>
                        <p class="mt-1 text-sm text-gray-900 font-semibold">
                            {{ $bankEmailTemplate->account_number_field_label ?? '-' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Extraction Patterns -->
            @if($bankEmailTemplate->amount_pattern || $bankEmailTemplate->sender_name_pattern || $bankEmailTemplate->account_number_pattern)
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Extraction Patterns</h3>
                    <div class="space-y-4">
                        @if($bankEmailTemplate->amount_pattern)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Amount Pattern</label>
                                <p class="mt-1 text-sm text-gray-900 font-mono bg-gray-50 p-2 rounded">
                                    {{ $bankEmailTemplate->amount_pattern }}
                                </p>
                            </div>
                        @endif
                        @if($bankEmailTemplate->sender_name_pattern)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Sender Name Pattern</label>
                                <p class="mt-1 text-sm text-gray-900 font-mono bg-gray-50 p-2 rounded">
                                    {{ $bankEmailTemplate->sender_name_pattern }}
                                </p>
                            </div>
                        @endif
                        @if($bankEmailTemplate->account_number_pattern)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Account Number Pattern</label>
                                <p class="mt-1 text-sm text-gray-900 font-mono bg-gray-50 p-2 rounded">
                                    {{ $bankEmailTemplate->account_number_pattern }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Sample HTML -->
            @if($bankEmailTemplate->sample_html)
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Sample HTML</h3>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <pre class="text-xs text-gray-700 overflow-x-auto whitespace-pre-wrap">{{ $bankEmailTemplate->sample_html }}</pre>
                    </div>
                </div>
            @endif

            <!-- Sample Text -->
            @if($bankEmailTemplate->sample_text)
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Sample Text</h3>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <pre class="text-xs text-gray-700 whitespace-pre-wrap">{{ $bankEmailTemplate->sample_text }}</pre>
                    </div>
                </div>
            @endif

            <!-- Notes -->
            @if($bankEmailTemplate->extraction_notes)
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Extraction Notes</h3>
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $bankEmailTemplate->extraction_notes }}</p>
                    </div>
                </div>
            @endif

            <!-- Actions -->
            <div class="border-t border-gray-200 pt-6 flex items-center justify-end gap-4">
                <a href="{{ route('admin.bank-email-templates.edit', $bankEmailTemplate) }}" 
                    class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90">
                    <i class="fas fa-edit mr-2"></i> Edit Template
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
