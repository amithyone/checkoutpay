<?php

namespace App\Console\Commands;

use App\Models\Rental;
use App\Models\RentalReturnReminder;
use App\Mail\RentalReturnReminder as RentalReturnReminderMail;
use App\Services\Rentals\RentalsPushNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SendRentalReturnReminders extends Command
{
    protected $signature = 'rentals:send-return-reminders';

    protected $description = 'Push + email return reminders (due soon ~1h before; overdue after deadline)';

    /**
     * Windows relative to return deadline (hours from now).
     * due_soon ≈ end_date - 60 minutes; overdue buckets after deadline.
     *
     * @var array<string, array{0: int, 1: int, 2: string}>
     */
    private const WINDOWS = [
        // [lowHours, highHours, pushKind] — hours offset from now for deadline window
        '1h_before' => [1, 2, 'due_soon'],
        '1h_after' => [-2, -1, 'overdue'],
        '2h_after' => [-3, -2, 'overdue'],
        '4h_after' => [-5, -4, 'overdue'],
    ];

    public function handle(RentalsPushNotifier $push): int
    {
        if (! Schema::hasTable('rental_return_reminders')) {
            $this->error('Table rental_return_reminders missing — run migrations.');

            return self::FAILURE;
        }

        $sent = 0;
        foreach (self::WINDOWS as $reminderType => [$low, $high, $kind]) {
            $rentals = $this->rentalsForWindow($reminderType, $low, $high);
            foreach ($rentals as $rental) {
                try {
                    if ($rental->renter_email) {
                        Mail::to($rental->renter_email)->send(new RentalReturnReminderMail($rental, $reminderType));
                    }

                    if ($kind === 'due_soon') {
                        $push->returnDueSoon($rental);
                    } else {
                        $push->returnOverdue($rental);
                    }

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

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, Rental> */
    private function rentalsForWindow(string $reminderType, int $lowHours, int $highHours): \Illuminate\Support\Collection
    {
        $now = Carbon::now();
        $deadlineStart = $now->copy()->addHours($lowHours);
        $deadlineEnd = $now->copy()->addHours($highHours);
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
