@php
    $status = $status ?? '';
@endphp
@if($status === 'active')
    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
@elseif($status === 'submitted')
    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Submitted</span>
@elseif($status === 'pending')
    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
@elseif($status === 'preparing')
    <span class="px-2 py-1 text-xs font-medium bg-violet-100 text-violet-800 rounded-full">Preparing</span>
@elseif($status === 'failed')
    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Failed</span>
@else
    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">{{ $status }}</span>
@endif
