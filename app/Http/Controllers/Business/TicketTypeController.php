<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:business');
    }

    /**
     * Store a newly created ticket type
     */
    public function store(Request $request, Event $event)
    {
        $business = Auth::guard('business')->user();
        
        if ($event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'min_per_order' => 'nullable|integer|min:1',
            'max_per_order' => 'nullable|integer|min:1',
            'sales_start_date' => 'nullable|date',
            'sales_end_date' => 'nullable|date|after:sales_start_date',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['event_id'] = $event->id;
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        TicketType::create($validated);

        return back()->with('success', 'Ticket type created successfully');
    }

    /**
     * Update the specified ticket type
     */
    public function update(Request $request, TicketType $ticketType)
    {
        $business = Auth::guard('business')->user();
        
        if ($ticketType->event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'min_per_order' => 'nullable|integer|min:1',
            'max_per_order' => 'nullable|integer|min:1',
            'sales_start_date' => 'nullable|date',
            'sales_end_date' => 'nullable|date|after:sales_start_date',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $ticketType->update($validated);

        return back()->with('success', 'Ticket type updated successfully');
    }

    /**
     * Remove the specified ticket type
     */
    public function destroy(TicketType $ticketType)
    {
        $business = Auth::guard('business')->user();
        
        if ($ticketType->event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        // Check if tickets have been sold
        if ($ticketType->sold_quantity > 0) {
            return back()->withErrors(['error' => 'Cannot delete ticket type with sold tickets']);
        }

        $ticketType->delete();

        return back()->with('success', 'Ticket type deleted successfully');
    }
}
