<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $business = Auth::guard('business')->user();
        
        $events = Event::where('business_id', $business->id)
            ->withCount(['ticketOrders as total_orders', 'tickets as total_tickets_sold'])
            ->latest()
            ->paginate(15);

        return view('business.tickets.events.index', compact('events'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('business.tickets.events.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEventRequest $request)
    {
        $business = Auth::guard('business')->user();

        try {
            DB::beginTransaction();

            // Handle cover image upload
            $coverImagePath = null;
            if ($request->hasFile('cover_image')) {
                $coverImagePath = $request->file('cover_image')->store('events/cover-images', 'public');
            }

            // Create event
            $event = Event::create([
                'business_id' => $business->id,
                'title' => $request->title,
                'description' => $request->description,
                'venue' => $request->venue,
                'address' => $request->address,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'timezone' => $request->timezone ?? 'Africa/Lagos',
                'cover_image' => $coverImagePath,
                'max_attendees' => $request->max_attendees,
                'max_tickets_per_customer' => $request->max_tickets_per_customer,
                'allow_refunds' => $request->boolean('allow_refunds', true),
                'refund_policy' => $request->refund_policy,
                'commission_percentage' => 0, // Commission set by admin only
                'status' => $request->status ?? Event::STATUS_DRAFT,
            ]);

            // Create ticket types
            foreach ($request->ticket_types as $ticketTypeData) {
                TicketType::create([
                    'event_id' => $event->id,
                    'name' => $ticketTypeData['name'],
                    'description' => $ticketTypeData['description'] ?? null,
                    'price' => $ticketTypeData['price'],
                    'quantity_available' => $ticketTypeData['quantity_available'],
                    'sales_start_date' => $ticketTypeData['sales_start_date'] ?? null,
                    'sales_end_date' => $ticketTypeData['sales_end_date'] ?? null,
                    'is_active' => true,
                ]);
            }

            DB::commit();

            return redirect()->route('business.tickets.events.show', $event)
                ->with('success', 'Event created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($coverImagePath) {
                Storage::disk('public')->delete($coverImagePath);
            }

            return back()->withInput()->with('error', 'Failed to create event: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        $business = Auth::guard('business')->user();

        // Ensure event belongs to business
        if ($event->business_id !== $business->id) {
            abort(403);
        }

        $event->load(['ticketTypes', 'ticketOrders' => function ($query) {
            $query->latest()->limit(10);
        }]);

        $stats = [
            'total_orders' => $event->ticketOrders()->count(),
            'paid_orders' => $event->ticketOrders()->where('payment_status', 'paid')->count(),
            'total_revenue' => $event->ticketOrders()->where('payment_status', 'paid')->sum('total_amount'),
            'total_commission' => $event->ticketOrders()->where('payment_status', 'paid')->sum('commission_amount'),
            'total_tickets_sold' => $event->tickets()->whereHas('ticketOrder', function ($q) {
                $q->where('payment_status', 'paid');
            })->count(),
            'unique_buyers' => $event->unique_buyers_count,
            'view_count' => $event->view_count ?? 0,
        ];

        $event->load('coupons');

        return view('business.tickets.events.show', compact('event', 'stats'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        $business = Auth::guard('business')->user();

        if ($event->business_id !== $business->id) {
            abort(403);
        }

        $event->load('ticketTypes');

        return view('business.tickets.events.edit', compact('event'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        $business = Auth::guard('business')->user();

        if ($event->business_id !== $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'timezone' => 'nullable|string|max:50',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'max_attendees' => 'nullable|integer|min:1',
            'max_tickets_per_customer' => 'nullable|integer|min:1',
            'allow_refunds' => 'nullable|boolean',
            'refund_policy' => 'nullable|string|max:1000',
            'status' => 'nullable|in:draft,published,cancelled',
        ]);

        try {
            // Handle cover image upload
            if ($request->hasFile('cover_image')) {
                if ($event->cover_image) {
                    Storage::disk('public')->delete($event->cover_image);
                }
                $validated['cover_image'] = $request->file('cover_image')->store('events/cover-images', 'public');
            }

            $event->update($validated);

            return redirect()->route('business.tickets.events.show', $event)
                ->with('success', 'Event updated successfully!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update event: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event)
    {
        $business = Auth::guard('business')->user();

        if ($event->business_id !== $business->id) {
            abort(403);
        }

        // Check if event has any paid orders
        if ($event->ticketOrders()->where('payment_status', 'paid')->exists()) {
            return back()->with('error', 'Cannot delete event with paid orders. Cancel the event instead.');
        }

        try {
            if ($event->cover_image) {
                Storage::disk('public')->delete($event->cover_image);
            }

            $event->delete();

            return redirect()->route('business.tickets.events.index')
                ->with('success', 'Event deleted successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete event: ' . $e->getMessage());
        }
    }

    /**
     * Publish event
     */
    public function publish(Event $event)
    {
        $business = Auth::guard('business')->user();

        if ($event->business_id !== $business->id) {
            abort(403);
        }

        $event->update(['status' => Event::STATUS_PUBLISHED]);

        return back()->with('success', 'Event published successfully!');
    }

    /**
     * Cancel event
     */
    public function cancel(Event $event)
    {
        $business = Auth::guard('business')->user();

        if ($event->business_id !== $business->id) {
            abort(403);
        }

        $event->update(['status' => Event::STATUS_CANCELLED]);

        return back()->with('success', 'Event cancelled successfully!');
    }
}
