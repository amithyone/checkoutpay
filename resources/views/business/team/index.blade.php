@extends('layouts.business')

@section('title', 'Team')
@section('page-title', 'Team Management')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="text-center py-12">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-users text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Team Management</h3>
            <p class="text-sm text-gray-600 mb-6">
                Team management features will be available soon. You can invite team members and manage their access to your payment gateway account.
            </p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 max-w-md mx-auto">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    This feature is coming soon. Contact support for more information.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
