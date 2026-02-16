<?php

namespace App\Console\Commands;

use App\Models\Rental;
use App\Models\RentalReturnReminder;
use App\Mail\RentalReturnReminder as RentalReturnReminderMail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendRentalReturnReminders extends Command
{
    protected $signature = 'rentals:send-return-reminders';

    protected $description = 'Send return reminder emails (5h–1h before deadline, then 1h–4h after overdue)';

    /** Reminder types and window: hours from now for deadline (before = positive, after = negative). */
    private const WINDOWS = [
        '5h_before' => [5, 6],
        '4h_before' => [4, 5],
        '3h_before' => [3, 4],
        '2h_before' => [2, 3],
        '1h_before' => [1, 2],
        '1h_after'  => [-2, -1],
        '2h_after'  => [-3, -2],
        '3h_after'  => [-4, -3],
        '4h_after'  => [-5, -4],
    ];

    public function handle(): int
    {
        $sent = 0;
        foreach (self::WINDOWS as $reminderType => [$low, $high]) {
            $rentals = $this->rentalsForWindow($reminderType, $low, $high);
            foreach ($rentals as $rental) {
                try {
                    Mail::to($rental->renter_email)->send(new RentalReturnReminderMail($rental, $reminderType));
                    RentalReturnReminder::create([
                        'rental_id' => $rental->id,
                        'reminder_type' => $reminderType,
                        'sent_at' => now(),
                    ]);
                    $sent++;
                } catch (\Throwable $e) {
                    Log::error('Rental return reminder send failed', [
                        'rental_id' => $rental->id,
                        'reminder_type' => $reminderType,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($sent > 0) {
            $this->info("Sent {$sent} return reminder(s).");
        }
        return 0;
    }

    /** @return \Illuminate\Support\Collection<int, Rental> */
    private function rentalsForWindow(string $reminderType, int $lowHours, int $highHours): \Illuminate\Support\Collection
    {
        $now = Carbon::now();
        $deadlineStart = $now->copy()->addHours($lowHours);
        $deadlineEnd   = $now->copy()->addHours($highHours);
        if ($deadlineStart->gt($deadlineEnd)) {
            [$deadlineStart, $deadlineEnd] = [$deadlineEnd, $deadlineStart];
        }

        $rentals = Rental::query()
            ->whereNull('returned_at')
            ->whereIn('status', [Rental::STATUS_ACTIVE, Rental::STATUS_APPROVED])
            ->with('business')
            ->get()
            ->filter(function (Rental $rental) use ($deadlineStart, $deadlineEnd) {
                $d = $rental->returnDeadline();
                return $d->gte($deadlineStart) && $d->lt($deadlineEnd);
            });

        $ids = $rentals->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }
        $sentIds = RentalReturnReminder::where('reminder_type', $reminderType)->whereIn('rental_id', $ids)->pluck('rental_id');
        return $rentals->whereIn('id', $ids->diff($sentIds)->all());
    }
}
