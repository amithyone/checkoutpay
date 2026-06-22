<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsumerAppSession;
use App\Models\ConsumerAppSessionEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsumerAppSessionAdminController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = $this->filteredSessionsQuery($request)
            ->paginate(25)
            ->withQueryString();

        return view('admin.app-sessions.index', [
            'sessions' => $sessions,
            'eventTypes' => $this->eventTypeOptions(),
            'activeCount' => ConsumerAppSession::query()->whereNull('ended_at')->count(),
        ]);
    }

    public function show(ConsumerAppSession $appSession): View
    {
        $appSession->load([
            'account:id,phone_e164,whatsapp_wallet_id,last_app_active_at',
            'wallet:id,phone_e164,kyc_fname,kyc_lname,sender_name',
            'events' => fn ($q) => $q->orderByDesc('created_at')->limit(200),
        ]);

        return view('admin.app-sessions.show', [
            'session' => $appSession,
            'eventTypes' => $this->eventTypeOptions(),
        ]);
    }

    public function events(Request $request): View
    {
        $events = $this->filteredEventsQuery($request)
            ->paginate(50)
            ->withQueryString();

        return view('admin.app-sessions.events', [
            'events' => $events,
            'eventTypes' => $this->eventTypeOptions(),
        ]);
    }

    /** @return Builder<ConsumerAppSession> */
    private function filteredSessionsQuery(Request $request): Builder
    {
        $q = ConsumerAppSession::query()
            ->with(['wallet:id,phone_e164,kyc_fname,kyc_lname,sender_name'])
            ->orderByDesc('started_at');

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $q->where(function (Builder $inner) use ($term) {
                $inner->where('phone_e164', 'like', '%'.$term.'%')
                    ->orWhere('session_uuid', 'like', '%'.$term.'%')
                    ->orWhere('device_label', 'like', '%'.$term.'%');
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

    /** @return Builder<ConsumerAppSessionEvent> */
    private function filteredEventsQuery(Request $request): Builder
    {
        $q = ConsumerAppSessionEvent::query()
            ->with(['session:id,session_uuid,login_method'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $q->where(function (Builder $inner) use ($term) {
                $inner->where('phone_e164', 'like', '%'.$term.'%')
                    ->orWhere('summary', 'like', '%'.$term.'%');
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
            ConsumerAppSessionEvent::TYPE_LOGIN => 'Login',
            ConsumerAppSessionEvent::TYPE_LOGOUT => 'Logout',
            ConsumerAppSessionEvent::TYPE_TRANSFER_P2P => 'P2P transfer',
            ConsumerAppSessionEvent::TYPE_TRANSFER_BANK => 'Bank transfer',
            ConsumerAppSessionEvent::TYPE_DEVICE_STEPUP => 'Device step-up',
            ConsumerAppSessionEvent::TYPE_PASSKEY_REGISTER => 'Passkey register',
        ];
    }
}
