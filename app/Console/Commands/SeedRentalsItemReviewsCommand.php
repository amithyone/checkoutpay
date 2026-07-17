<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\RentalItemReview;
use App\Models\Renter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seed sample star ratings + comments on existing catalog tiles using existing renters.
 * Safe to re-run: skips item+renter pairs that already have a review unless --fresh.
 */
class SeedRentalsItemReviewsCommand extends Command
{
    protected $signature = 'rentals:seed-item-reviews
                            {--items=12 : How many catalog items (tiles) to rate}
                            {--per-item=3 : Reviews per item (different existing renters)}
                            {--min-stars=5 : Minimum star rating (1-5); use 5 for all five-stars}
                            {--fresh : Replace prior seeded demo reviews for selected items}
                            {--dry-run : Show what would be seeded without writing}';

    protected $description = 'Seed five-star (or mixed) ratings + comments on existing rental items from existing renters';

    /** @var list<array{rating: int, condition: string, remarks: string}> */
    private array $templates = [
        [
            'rating' => 5,
            'condition' => 'good',
            'remarks' => 'Excellent gear — arrived clean and worked perfectly for my shoot.',
        ],
        [
            'rating' => 5,
            'condition' => 'new',
            'remarks' => 'Five stars. Host was responsive and the item matched the listing photos.',
        ],
        [
            'rating' => 5,
            'condition' => 'good',
            'remarks' => 'Would rent again. Smooth pickup and no issues during the rental period.',
        ],
        [
            'rating' => 5,
            'condition' => 'good',
            'remarks' => 'Top quality. Exactly what I needed for the weekend event.',
        ],
        [
            'rating' => 5,
            'condition' => 'good',
            'remarks' => 'Super happy with this. Well maintained and easy to use.',
        ],
        [
            'rating' => 4,
            'condition' => 'good',
            'remarks' => 'Very good overall — minor cosmetic wear but performed great.',
        ],
        [
            'rating' => 5,
            'condition' => 'good',
            'remarks' => 'Flawless experience. Highly recommend this listing.',
        ],
        [
            'rating' => 5,
            'condition' => 'new',
            'remarks' => 'Looked brand new. Instructions were clear and return was easy.',
        ],
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('rental_item_reviews') || ! Schema::hasTable('rental_items') || ! Schema::hasTable('rentals')) {
            $this->error('Rentals review tables missing. Run migrations first (rental_item_reviews, rental_items, rentals).');

            return self::FAILURE;
        }

        $itemLimit = max(1, (int) $this->option('items'));
        $perItem = max(1, min(8, (int) $this->option('per-item')));
        $minStars = max(1, min(5, (int) $this->option('min-stars')));
        $dryRun = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');

        $items = RentalItem::query()
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereNull('deleted_at')
            ->orderByDesc('is_featured')
            ->orderByDesc('id')
            ->limit($itemLimit)
            ->get();

        if ($items->isEmpty()) {
            $this->error('No active catalog items found to rate.');

            return self::FAILURE;
        }

        $renters = Renter::query()
            ->where('is_active', true)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        if ($renters->count() < 1) {
            $this->error('No active renters found to leave reviews.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Seeding reviews on %d item(s), up to %d review(s) each (min %d★)%s…',
            $items->count(),
            $perItem,
            $minStars,
            $dryRun ? ' [dry-run]' : ''
        ));

        $created = 0;
        $skipped = 0;
        $removed = 0;

        foreach ($items as $item) {
            if ($fresh && ! $dryRun) {
                $removed += $this->purgeSeededReviewsForItem($item);
            }

            $ownerRenterId = $this->businessOwnerRenterId($item);
            $candidates = $renters
                ->filter(fn (Renter $r) => (int) $r->id !== $ownerRenterId)
                ->values();

            if ($candidates->isEmpty()) {
                $candidates = $renters->values();
            }

            $picked = $candidates->shuffle()->take($perItem);

            foreach ($picked as $index => $renter) {
                $existing = RentalItemReview::query()
                    ->where('rental_item_id', $item->id)
                    ->where('renter_id', $renter->id)
                    ->first();

                // Never create a second review for the same renter+item (unique is per rental).
                // --fresh only removes prior [demo] rows; real reviews are left alone.
                if ($existing !== null) {
                    $skipped++;
                    continue;
                }

                $template = $this->pickTemplate($index, $minStars);

                if ($dryRun) {
                    $this->line(sprintf(
                        '  [dry] item #%d %s ← %s (%d★) “%s”',
                        $item->id,
                        $item->name,
                        $renter->name,
                        $template['rating'],
                        $template['remarks']
                    ));
                    $created++;
                    continue;
                }

                $rental = $this->ensureCompletedRental($renter, $item);
                RentalItemReview::query()->updateOrCreate(
                    [
                        'rental_id' => $rental->id,
                        'rental_item_id' => $item->id,
                        'renter_id' => $renter->id,
                    ],
                    [
                        'rating' => $template['rating'],
                        'condition' => $template['condition'],
                        'missing_items' => null,
                        'remarks' => $this->seedRemarkPrefix().$template['remarks'],
                    ]
                );

                $created++;
                $this->line(sprintf(
                    '  ✓ item #%d %s ← %s (%d★)',
                    $item->id,
                    Str::limit((string) $item->name, 40),
                    $renter->name,
                    $template['rating']
                ));
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Items targeted', (string) $items->count()],
                ['Reviews written', (string) $created],
                ['Skipped (already rated)', (string) $skipped],
                ['Seeded reviews removed (--fresh)', (string) $removed],
            ]
        );

        if (! $dryRun) {
            $sample = $items->take(5);
            foreach ($sample as $item) {
                $avg = RentalItemReview::query()
                    ->where('rental_item_id', $item->id)
                    ->whereNotNull('rating')
                    ->avg('rating');
                $count = RentalItemReview::query()
                    ->where('rental_item_id', $item->id)
                    ->whereNotNull('rating')
                    ->count();
                $this->line(sprintf(
                    'Tile #%d “%s” → avg %s★ (%d reviews)',
                    $item->id,
                    Str::limit((string) $item->name, 36),
                    $avg !== null ? number_format((float) $avg, 1) : '—',
                    $count
                ));
            }
        }

        $this->info('Done. Check catalog tiles / GET /api/v1/rentals/items/{id}/reviews');

        return self::SUCCESS;
    }

    private function seedRemarkPrefix(): string
    {
        return '[demo] ';
    }

    private function purgeSeededReviewsForItem(RentalItem $item): int
    {
        $reviews = RentalItemReview::query()
            ->where('rental_item_id', $item->id)
            ->where('remarks', 'like', $this->seedRemarkPrefix().'%')
            ->get();

        $count = $reviews->count();
        $rentalIds = $reviews->pluck('rental_id')->unique()->filter()->all();

        foreach ($reviews as $review) {
            $review->delete();
        }

        // Soft-delete completed seed rentals that only existed for these reviews.
        if ($rentalIds !== []) {
            Rental::withoutEvents(function () use ($rentalIds) {
                Rental::query()
                    ->whereIn('id', $rentalIds)
                    ->where('renter_notes', 'like', 'SEED_ITEM_REVIEW:%')
                    ->each(function (Rental $rental) {
                        $rental->items()->detach();
                        $rental->delete();
                    });
            });
        }

        return $count;
    }

    private function businessOwnerRenterId(RentalItem $item): ?int
    {
        if (! $item->business_id) {
            return null;
        }

        $business = Business::query()->find($item->business_id);
        if ($business === null) {
            return null;
        }

        // Common bridge: business email matches renter email.
        if (filled($business->email)) {
            $owner = Renter::query()->where('email', $business->email)->first();
            if ($owner !== null) {
                return (int) $owner->id;
            }
        }

        return null;
    }

    /**
     * @return array{rating: int, condition: string, remarks: string}
     */
    private function pickTemplate(int $index, int $minStars): array
    {
        $pool = array_values(array_filter(
            $this->templates,
            fn (array $t) => $t['rating'] >= $minStars
        ));

        if ($pool === []) {
            $pool = [[
                'rating' => $minStars,
                'condition' => 'good',
                'remarks' => 'Great rental experience.',
            ]];
        }

        return $pool[$index % count($pool)];
    }

    private function ensureCompletedRental(Renter $renter, RentalItem $item): Rental
    {
        $noteKey = 'SEED_ITEM_REVIEW:'.$item->id.':'.$renter->id;

        $existing = Rental::query()
            ->where('renter_id', $renter->id)
            ->where('business_id', $item->business_id)
            ->where('renter_notes', $noteKey)
            ->first();

        if ($existing !== null) {
            if (! $existing->items()->where('rental_items.id', $item->id)->exists()) {
                $existing->items()->syncWithoutDetaching([
                    $item->id => [
                        'quantity' => 1,
                        'unit_rate' => (float) $item->daily_rate,
                        'total_amount' => (float) $item->daily_rate,
                    ],
                ]);
            }

            return $existing;
        }

        $start = now()->subDays(14)->startOfDay();
        $end = now()->subDays(12)->startOfDay();
        $rate = (float) ($item->daily_rate ?? 1000);

        return Rental::withoutEvents(function () use ($renter, $item, $noteKey, $start, $end, $rate) {
            $rental = Rental::query()->create([
                'renter_id' => $renter->id,
                'business_id' => $item->business_id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => 3,
                'daily_rate' => $rate,
                'total_amount' => $rate * 3,
                'deposit_amount' => round($rate * 0.5, 2),
                'currency' => $item->currency ?? 'NGN',
                'status' => Rental::STATUS_COMPLETED,
                'renter_name' => $renter->name,
                'renter_email' => $renter->email,
                'renter_phone' => $renter->phone,
                'renter_address' => $renter->address,
                'fulfillment_method' => 'pickup',
                'return_method' => 'dropoff',
                'renter_notes' => $noteKey,
                'completed_at' => now()->subDays(11),
                'returned_at' => now()->subDays(11),
                'approved_at' => now()->subDays(15),
                'started_at' => $start,
            ]);

            $rental->items()->attach($item->id, [
                'quantity' => 1,
                'unit_rate' => $rate,
                'total_amount' => $rate * 3,
            ]);

            return $rental;
        });
    }
}
