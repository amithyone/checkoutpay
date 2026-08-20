<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RentalsAdminAppSession;
use App\Models\RentalsAdminAppSessionEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalsAdminAppSessionAdminController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = $this->filteredSessionsQuery($request)
            ->paginate(25)
            ->withQueryString();

        return view('admin.rentals-app-sessions.index', [
            'sessions' => $sessions,
            'eventTypes' => $this->eventTypeOptions(),
            'activeCount' => RentalsAdminAppSession::query()->whereNull('ended_at')->count(),
        ]);
    }

    public function show(RentalsAdminAppSession $appSession): View
    {
        $appSession->load([
            'admin:id,name,email,role',
            'events' => fn ($q) => $q->orderByDesc('created_at')->limit(200),
        ]);

        return view('admin.rentals-app-sessions.show', [
            'session' => $appSession,
            'eventTypes' => $this->eventTypeOptions(),
        ]);
    }

    public function events(Request $request): View
    {
        $events = $this->filteredEventsQuery($request)
            ->paginate(50)
            ->withQueryString();

        return view('admin.rentals-app-sessions.events', [
            'events' => $events,
            'eventTypes' => $this->eventTypeOptions(),
        ]);
    }

    /** @return Builder<RentalsAdminAppSession> */
    private function filteredSessionsQuery(Request $request): Builder
    {
        $q = RentalsAdminAppSession::query()
            ->with(['admin:id,name,email,role'])
            ->orderByDesc('started_at');

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $q->where(function (Builder $inner) use ($term) {
                $inner->where('admin_email', 'like', '%'.$term.'%')
                    ->orWhere('admin_name', 'like', '%'.$term.'%')
                    ->orWhere('session_uuid', 'like', '%'.$term.'%')
                    ->orWhere('device_label', 'like', '%'.$term.'%')
                    ->orWhere('ip_address', 'like', '%'.$term.'%');
            });
        }

        if ($request->filled('platform')) {
            $q->where('platform', (string) $request->input('platform'));
        }

        if ($request->filled('login_method')) {
            $q->where('login_method', (string) $request->input('login_method'));
        }

        if ($request->input('status') === 'active') {
            $q->whereNull('ended_at');
        } elseif ($request->input('status') === 'ended') {
            $q->whereNotNull('ended_at');
        }

        if ($request->filled('from')) {
            $q->whereDate('started_at', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $q->whereDate('started_at', '<=', (string) $request->input('to'));
        }

        return $q;
    }

    /** @return Builder<RentalsAdminAppSessionEvent> */
    private function filteredEventsQuery(Request $request): Builder
    {
        $q = RentalsAdminAppSessionEvent::query()
            ->with(['session:id,session_uuid,login_method,admin_email'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $q->where(function (Builder $inner) use ($term) {
                $inner->where('admin_email', 'like', '%'.$term.'%')
                    ->orWhere('summary', 'like', '%'.$term.'%')
                    ->orWhere('ip_address', 'like', '%'.$term.'%');
            });
        }

        if ($request->filled('event_type')) {
            $q->where('event_type', (string) $request->input('event_type'));
        }

        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', (string) $request->input('to'));
        }

        return $q;
    }

    /** @return array<string, string> */
    private function eventTypeOptions(): array
    {
        return [
            RentalsAdminAppSessionEvent::TYPE_LOGIN => 'Login',
            RentalsAdminAppSessionEvent::TYPE_LOGOUT => 'Logout',
            RentalsAdminAppSessionEvent::TYPE_SESSION_EXPIRED => 'Session expired',
            RentalsAdminAppSessionEvent::TYPE_HEARTBEAT => 'Heartbeat',
        ];
    }
}
