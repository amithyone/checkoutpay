<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventCouponController extends Controller
{
    /**
     * Store a newly created coupon
     */
    public function store(Request $request, Event $event)
    {
        $business = Auth::guard('business')->user();

        // Ensure event belongs to business
        if ($event->business_id !== $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:event_coupons,code',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
        ]);

        // Validate percentage discount
        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return back()->withInput()->with('error', 'Percentage discount cannot exceed 100%');
        }

        $coupon = EventCoupon::create([
            'event_id' => $event->id,
            'code' => strtoupper($validated['code']),
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'usage_limit' => $validated['usage_limit'] ?? null,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('business.tickets.events.show', $event)
            ->with('success', 'Coupon code created successfully!');
    }

    /**
     * Remove the specified coupon
     */
    public function destroy(Event $event, EventCoupon $coupon)
    {
        $business = Auth::guard('business')->user();

        // Ensure event belongs to business
        if ($event->business_id !== $business->id) {
            abort(403);
        }

        // Ensure coupon belongs to event
        if ($coupon->event_id !== $event->id) {
            abort(403);
        }

        $coupon->delete();

        return redirect()->route('business.tickets.events.show', $event)
            ->with('success', 'Coupon code deleted successfully!');
    }
}
