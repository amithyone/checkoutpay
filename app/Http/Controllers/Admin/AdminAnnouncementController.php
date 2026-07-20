<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAnnouncement;
use App\Services\Admin\AdminAnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminAnnouncementController extends Controller
{
    public function __construct(
        private AdminAnnouncementService $announcements,
    ) {}

    public function index(): View
    {
        $items = AdminAnnouncement::query()
            ->with('admin:id,name,email')
            ->orderByDesc('id')
            ->paginate(20);

        $reach = $this->announcements->estimateReach(AdminAnnouncement::AUDIENCES);

        return view('admin.announcements.index', [
            'items' => $items,
            'reach' => $reach,
        ]);
    }

    public function create(): View
    {
        $reach = $this->announcements->estimateReach(AdminAnnouncement::AUDIENCES);

        return view('admin.announcements.create', [
            'reach' => $reach,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:160',
            'body' => 'required|string|max:5000',
            'audiences' => 'required|array|min:1',
            'audiences.*' => 'in:wallet,rentals,business',
            'channel_email' => 'nullable|boolean',
            'channel_push' => 'nullable|boolean',
            'push_screen' => 'nullable|string|max:32|in:home,history,saving,card,profile,support',
        ]);

        $channelEmail = $request->boolean('channel_email');
        $channelPush = $request->boolean('channel_push');
        if (! $channelEmail && ! $channelPush) {
            return back()->withInput()->withErrors([
                'channel_email' => 'Select at least email or app push.',
            ]);
        }

        $admin = Auth::guard('admin')->user();
        $announcement = $this->announcements->queue($admin, [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'audiences' => $validated['audiences'],
            'channel_email' => $channelEmail,
            'channel_push' => $channelPush,
            'push_screen' => $validated['push_screen'] ?? null,
        ]);

        $msg = config('queue.default') === 'sync'
            ? 'Announcement sent (email + app push only — never WhatsApp).'
            : 'Announcement queued. Ensure a queue worker is running, or open it and click “Process now”.';

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with('success', $msg);
    }

    public function show(AdminAnnouncement $announcement): View
    {
        $announcement->load('admin:id,name,email');

        return view('admin.announcements.show', [
            'item' => $announcement,
        ]);
    }

    public function processNow(AdminAnnouncement $announcement): RedirectResponse
    {
        if ($announcement->status === AdminAnnouncement::STATUS_SENT) {
            return back()->with('success', 'Already sent.');
        }

        try {
            $this->announcements->processNow($announcement->fresh());
        } catch (\Throwable $e) {
            return back()->with('error', 'Send failed: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with('success', 'Announcement processed (email + app push only).');
    }
}
