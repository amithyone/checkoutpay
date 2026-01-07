@extends('layouts.admin')

@section('title', 'Create Account Number')
@section('page-title', 'Create Account Number')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form action="{{ route('admin.account-numbers.store') }}" method="POST">
            @csrf
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Type</label>
                    <div class="flex items-center space-x-4">
                        <label class="flex items-center">
                            <input type="radio" name="is_pool" value="1" checked class="mr-2" onchange="toggleBusinessSelect()">
                            <span>Pool Account</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="is_pool" value="0" class="mr-2" onchange="toggleBusinessSelect()">
                            <span>Business-Specific</span>
                        </label>
                    </div>
                </div>

                <div id="business-select" style="display: none;">
                    <label for="business_id" class="block text-sm font-medium text-gray-700 mb-1">Business</label>
                    <select name="business_id" id="business_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Select Business</option>
                        @foreach($businesses as $business)
                            <option value="{{ $business->id }}">{{ $business->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="account_number" class="block text-sm font-medium text-gray-700 mb-1">Account Number *</label>
                    <input type="text" name="account_number" id="account_number" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('account_number') }}">
                </div>

                <div>
                    <label for="account_name" class="block text-sm font-medium text-gray-700 mb-1">Account Name *</label>
                    <input type="text" name="account_name" id="account_name" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('account_name') }}">
                </div>

                <div>
                    <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-1">Bank Name *</label>
                    <input type="text" name="bank_name" id="bank_name" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('bank_name') }}">
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.account-numbers.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                        Create Account Number
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function toggleBusinessSelect() {
        const isPool = document.querySelector('input[name="is_pool"]:checked').value === '1';
        document.getElementById('business-select').style.display = isPool ? 'none' : 'block';
        if (isPool) {
            document.getElementById('business_id').value = '';
        }
    }
</script>
@endpush
@endsection
