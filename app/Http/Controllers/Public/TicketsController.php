<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketsController extends Controller
{
    /**
     * Display public tickets/events listing
     */
    public function index(Request $request): View
    {
        $query = Event::with(['business', 'ticketTypes'])
            ->where('status', 'published');

        // Filter by date
        if ($request->filled('date')) {
            if ($request->date === 'upcoming') {
                $query->where('start_date', '>=', now());
            } elseif ($request->date === 'past') {
                $query->where('start_date', '<', now());
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('venue', 'like', "%{$search}%")
                  ->orWhereHas('business', function ($bq) use ($search) {
                      $bq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Sort
        $sort = $request->get('sort', 'upcoming');
        switch ($sort) {
            case 'newest':
                $query->latest('created_at');
                break;
            case 'oldest':
                $query->oldest('created_at');
                break;
            case 'price_low':
                $query->orderByRaw('(SELECT MIN(price) FROM ticket_types WHERE ticket_types.event_id = events.id) ASC');
                break;
            case 'price_high':
                $query->orderByRaw('(SELECT MIN(price) FROM ticket_types WHERE ticket_types.event_id = events.id) DESC');
                break;
            case 'upcoming':
            default:
                $query->orderByRaw('CASE WHEN start_date >= ? THEN 0 ELSE 1 END', [now()])
                    ->orderBy('start_date', 'asc');
                break;
        }

        $events = $query->paginate(12);

        return view('tickets.index', compact('events'));
    }
}
