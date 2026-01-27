<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Display a listing of published events
     */
    public function index(Request $request)
    {
        $query = Event::published()->upcoming();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('venue_city', 'like', "%{$search}%");
            });
        }

        // Filter by date
        if ($request->has('date')) {
            $date = $request->date;
            if ($date === 'today') {
                $query->whereDate('start_date', today());
            } elseif ($date === 'this-week') {
                $query->whereBetween('start_date', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($date === 'this-month') {
                $query->whereMonth('start_date', now()->month)
                      ->whereYear('start_date', now()->year);
            }
        }

        // Filter by location
        if ($request->has('location')) {
            $query->where('venue_city', $request->location);
        }

        $events = $query->orderBy('start_date', 'asc')->paginate(12);

        // Get unique cities for filter
        $cities = Event::published()->upcoming()
            ->whereNotNull('venue_city')
            ->distinct()
            ->pluck('venue_city')
            ->sort()
            ->values();

        return view('public.events.index', compact('events', 'cities'));
    }

    /**
     * Display the specified event
     */
    public function show(Event $event)
    {
        // Only show published events
        if ($event->status !== 'published') {
            abort(404);
        }

        // Check if event is available for registration
        if (!$event->isAvailableForRegistration()) {
            return view('public.events.show', compact('event'))->with('unavailable', true);
        }

        $event->load(['activeTicketTypes']);

        return view('public.events.show', compact('event'));
    }
}
