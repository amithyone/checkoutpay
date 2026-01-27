<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketType;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    protected $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
        $this->middleware('auth:business');
    }

    /**
     * Display a listing of events
     */
    public function index()
    {
        $business = Auth::guard('business')->user();
        $events = Event::where('business_id', $business->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('business.events.index', compact('events'));
    }

    /**
     * Show the form for creating a new event
     */
    public function create()
    {
        return view('business.events.create');
    }

    /**
     * Store a newly created event
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'event_image' => 'nullable|image|max:2048',
            'event_banner' => 'nullable|image|max:2048',
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'venue_city' => 'nullable|string|max:100',
            'venue_state' => 'nullable|string|max:100',
            'venue_country' => 'nullable|string|max:100',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
            'timezone' => 'nullable|string|max:50',
            'max_attendees' => 'nullable|integer|min:1',
            'registration_deadline' => 'nullable|date|before:start_date',
            'allow_waitlist' => 'nullable|boolean',
            'waitlist_capacity' => 'nullable|integer|min:1',
            'organizer_name' => 'nullable|string|max:255',
            'organizer_email' => 'nullable|email|max:255',
            'organizer_phone' => 'nullable|string|max:50',
            'terms_and_conditions' => 'nullable|string',
            'refund_policy' => 'nullable|string',
            'social_links' => 'nullable|array',
            'status' => 'nullable|in:draft,published',
        ]);

        $business = Auth::guard('business')->user();
        
        try {
            $event = $this->eventService->createEvent($business, $validated);
            
            return redirect()->route('business.events.show', $event)
                ->with('success', 'Event created successfully');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['error' => 'Failed to create event: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified event
     */
    public function show(Event $event)
    {
        $business = Auth::guard('business')->user();
        
        // Ensure event belongs to business
        if ($event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $event->load(['ticketTypes', 'orders', 'tickets']);
        
        return view('business.events.show', compact('event'));
    }

    /**
     * Show the form for editing the specified event
     */
    public function edit(Event $event)
    {
        $business = Auth::guard('business')->user();
        
        if ($event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        return view('business.events.edit', compact('event'));
    }

    /**
     * Update the specified event
     */
    public function update(Request $request, Event $event)
    {
        $business = Auth::guard('business')->user();
        
        if ($event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'event_image' => 'nullable|image|max:2048',
            'event_banner' => 'nullable|image|max:2048',
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'venue_city' => 'nullable|string|max:100',
            'venue_state' => 'nullable|string|max:100',
            'venue_country' => 'nullable|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'timezone' => 'nullable|string|max:50',
            'max_attendees' => 'nullable|integer|min:1',
            'registration_deadline' => 'nullable|date|before:start_date',
            'allow_waitlist' => 'nullable|boolean',
            'waitlist_capacity' => 'nullable|integer|min:1',
            'organizer_name' => 'nullable|string|max:255',
            'organizer_email' => 'nullable|email|max:255',
            'organizer_phone' => 'nullable|string|max:50',
            'terms_and_conditions' => 'nullable|string',
            'refund_policy' => 'nullable|string',
            'social_links' => 'nullable|array',
            'status' => 'nullable|in:draft,published,cancelled',
        ]);

        try {
            $event = $this->eventService->updateEvent($event, $validated);
            
            return redirect()->route('business.events.show', $event)
                ->with('success', 'Event updated successfully');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['error' => 'Failed to update event: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified event
     */
    public function destroy(Event $event)
    {
        $business = Auth::guard('business')->user();
        
        if ($event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        try {
            $this->eventService->deleteEvent($event);
            
            return redirect()->route('business.events.index')
                ->with('success', 'Event deleted successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete event: ' . $e->getMessage()]);
        }
    }

    /**
     * Publish event
     */
    public function publish(Event $event)
    {
        $business = Auth::guard('business')->user();
        
        if ($event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $event = $this->eventService->publishEvent($event);
        
        return back()->with('success', 'Event published successfully');
    }

    /**
     * Cancel event
     */
    public function cancel(Event $event)
    {
        $business = Auth::guard('business')->user();
        
        if ($event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $event = $this->eventService->cancelEvent($event);
        
        return back()->with('success', 'Event cancelled successfully');
    }
}
