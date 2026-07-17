<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\BusinessWithdrawalAccount;
use App\Models\Rental;
use App\Models\RentalCategory;
use App\Models\RentalConditionReport;
use App\Models\RentalDispute;
use App\Models\RentalEscrow;
use App\Models\RentalFavorite;
use App\Models\RentalItem;
use App\Models\RentalItemReview;
use App\Models\RentalVendorApplication;
use App\Models\Renter;
use App\Models\WithdrawalRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Idempotent QA account for rentals frontend / native testing.
 *
 * Host + guest share one login (same email → Business bridge).
 * A second guest renter books against the host catalog.
 */
class SeedRentalsQaDemoCommand extends Command
{
    protected $signature = 'rentals:seed-qa-demo
                            {--email=qa.rentals.demo@checkoutnow.test : Host/renter login email}
                            {--password=CheckoutQa2468! : Shared password}
                            {--guest-email=qa.rentals.guest@checkoutnow.test : Guest renter who books from the host}
                            {--fresh-orders : Delete prior QA rentals for these emails before re-seeding orders}';

    protected $description = 'Seed a full rentals QA account (KYC, vendor, catalog, multi-status orders, escrow, withdrawal)';

    public function handle(): int
    {
        if (! Schema::hasTable('renters') || ! Schema::hasTable('rental_items')) {
            $this->error('Rentals core tables missing. Run migrations first (renters, rental_items, rentals, …).');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->option('email')));
        $password = (string) $this->option('password');
        $guestEmail = strtolower(trim((string) $this->option('guest-email')));

        $this->info('Seeding rentals QA demo…');

        $host = $this->upsertHostRenter($email, $password);
        $business = $this->upsertHostBusiness($host, $password);
        $this->upsertVendorApplication($host, $business);
        $this->upsertWithdrawalSetup($business);
        $items = $this->upsertCatalog($business);
        $guest = $this->upsertGuestRenter($guestEmail, $password);

        if ($this->option('fresh-orders')) {
            $this->purgeQaOrders($host, $guest, $business);
        }

        $this->seedOrders($host, $guest, $business, $items);
        $this->seedExtras($host, $guest, $items);

        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['Host login (renter + business)', $email],
                ['Guest login (bookings only)', $guestEmail],
                ['Password (both)', $password],
                ['Host renter id', (string) $host->id],
                ['Business id', (string) $business->id],
                ['Guest renter id', (string) $guest->id],
                ['Catalog items', (string) count($items)],
                ['Renter wallet', number_format((float) $host->wallet_balance, 2)],
                ['Business balance', number_format((float) $business->balance, 2)],
            ]
        );

        $this->warn('Use the host login in rentals-frontend to exercise guest + vendor screens.');
        $this->line('API: POST /api/v1/rentals/auth/login');

        return self::SUCCESS;
    }

    protected function upsertHostRenter(string $email, string $password): Renter
    {
        $renter = Renter::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($renter?->trashed()) {
            $renter->restore();
        }

        $payload = [
            'name' => 'QA Demo Renter',
            'email' => $email,
            'password' => Hash::make($password),
            'phone' => '+2348140000001',
            'address' => '12 QA Test Street, Victoriayi, Lagos',
            'is_active' => true,
            'email_verified_at' => now(),
            'wallet_balance' => 250000,
            'verified_account_number' => '0123456789',
            'verified_account_name' => 'QA DEMO RENTER',
            'verified_bank_name' => 'Access Bank',
            'verified_bank_code' => '044',
            'kyc_verified_at' => now(),
            'kyc_id_status' => Renter::KYC_ID_STATUS_APPROVED,
            'kyc_id_type' => 'nin',
            'kyc_id_front_path' => 'qa/demo-id-front.jpg',
            'kyc_id_back_path' => 'qa/demo-id-back.jpg',
            'kyc_id_reviewed_at' => now(),
            'bvn' => '22222222222',
            'age' => 28,
            'instagram_url' => 'https://instagram.com/checkoutnow_qa',
        ];

        if ($renter) {
            $renter->fill($payload)->save();
        } else {
            $renter = Renter::query()->create($payload);
        }

        $this->line("Host renter #{$renter->id}");

        return $renter->fresh();
    }

    protected function upsertGuestRenter(string $email, string $password): Renter
    {
        $renter = Renter::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($renter?->trashed()) {
            $renter->restore();
        }

        $payload = [
            'name' => 'QA Demo Guest',
            'email' => $email,
            'password' => Hash::make($password),
            'phone' => '+2348140000002',
            'address' => '5 Guest Avenue, Ikeja, Lagos',
            'is_active' => true,
            'email_verified_at' => now(),
            'wallet_balance' => 150000,
            'verified_account_number' => '0987654321',
            'verified_account_name' => 'QA DEMO GUEST',
            'verified_bank_name' => 'GTBank',
            'verified_bank_code' => '058',
            'kyc_verified_at' => now(),
            'kyc_id_status' => Renter::KYC_ID_STATUS_APPROVED,
            'kyc_id_type' => 'nin',
            'kyc_id_front_path' => 'qa/guest-id-front.jpg',
            'kyc_id_back_path' => 'qa/guest-id-back.jpg',
            'kyc_id_reviewed_at' => now(),
            'bvn' => '33333333333',
            'age' => 31,
        ];

        if ($renter) {
            $renter->fill($payload)->save();
        } else {
            $renter = Renter::query()->create($payload);
        }

        $this->line("Guest renter #{$renter->id}");

        return $renter->fresh();
    }

    protected function upsertHostBusiness(Renter $host, string $password): Business
    {
        $business = Business::withTrashed()->whereRaw('LOWER(email) = ?', [strtolower($host->email)])->first();
        if ($business?->trashed()) {
            $business->restore();
        }

        $payload = [
            'name' => 'QA Demo Rentals Hub',
            'email' => $host->email,
            'password' => Hash::make($password),
            'phone' => $host->phone,
            'address' => $host->address,
            'website' => 'https://check-outnow.com',
            'website_approved' => true,
            'is_active' => true,
            'email_verified_at' => now(),
            'balance' => 500000,
            'currency' => 'NGN',
            'rental_auto_approve' => false,
            'rental_global_caution_fee_enabled' => true,
            'rental_global_caution_fee_percent' => 10,
            // businesses.business_id is a 5-char public code (see Business::generateBusinessId).
            'business_id' => $business?->business_id ?: Business::generateBusinessId(),
            'withdrawal_pin_hash' => Hash::make('2468'),
            'withdrawal_pin_set_at' => now(),
        ];

        if ($business) {
            $business->fill($payload)->save();
        } else {
            $business = Business::query()->create($payload);
        }

        $this->line("Business #{$business->id}");

        return $business->fresh();
    }

    protected function upsertVendorApplication(Renter $host, Business $business): void
    {
        if (! Schema::hasTable('rental_vendor_applications')) {
            $this->warn('Skipping vendor application (table missing — run rentals phase1 migrations).');

            return;
        }

        $app = RentalVendorApplication::query()->firstOrNew(['renter_id' => $host->id]);
        $app->fill([
            'business_name' => $business->name,
            'address' => (string) $business->address,
            'phone' => (string) $business->phone,
            'description' => 'CheckoutNow QA vendor — seeded for end-to-end rentals testing.',
            'documents' => [['type' => 'cac', 'url' => 'https://example.com/qa-cac.pdf']],
            'status' => RentalVendorApplication::STATUS_APPROVED,
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now()->subDays(2),
            'business_id' => $business->id,
            'rejection_reason' => null,
        ])->save();
    }

    protected function upsertWithdrawalSetup(Business $business): void
    {
        if (Schema::hasTable('business_withdrawal_accounts')) {
            BusinessWithdrawalAccount::query()->updateOrCreate(
                [
                    'business_id' => $business->id,
                    'account_number' => '0123456789',
                ],
                [
                    'account_name' => 'QA DEMO RENTALS HUB',
                    'bank_name' => 'Access Bank',
                    'bank_code' => '044',
                    'is_default' => true,
                    'is_active' => true,
                ]
            );
        }

        if (Schema::hasTable('withdrawal_requests')) {
            $exists = WithdrawalRequest::query()
                ->where('business_id', $business->id)
                ->where('status', WithdrawalRequest::STATUS_PENDING)
                ->where('account_number', '0123456789')
                ->exists();

            if (! $exists) {
                WithdrawalRequest::query()->create([
                    'business_id' => $business->id,
                    'amount' => 25000,
                    'account_number' => '0123456789',
                    'account_name' => 'QA DEMO RENTALS HUB',
                    'bank_name' => 'Access Bank',
                    'status' => WithdrawalRequest::STATUS_PENDING,
                    'notes' => 'QA seeded pending withdrawal',
                ]);
            }
        }
    }

    /**
     * @return list<RentalItem>
     */
    protected function upsertCatalog(Business $business): array
    {
        $categoryId = RentalCategory::query()->value('id');
        if (! $categoryId && Schema::hasTable('rental_categories')) {
            $categoryId = RentalCategory::query()->create([
                'name' => 'Camera',
                'slug' => 'camera-qa',
                'icon' => 'fas fa-camera',
            ])->id;
        }

        $defs = [
            [
                'slug' => 'qa-sony-a7iv-kit',
                'name' => 'QA Sony A7 IV Kit',
                'brand' => 'Sony',
                'daily_rate' => 45000,
                'weekly_rate' => 250000,
                'description' => 'Full-frame mirrorless kit for QA bookings.',
                'is_featured' => true,
                'featured_tag' => 'QA pick',
                'how_to_videos' => [
                    ['title' => 'Setup basics', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ],
            ],
            [
                'slug' => 'qa-godox-lighting-set',
                'name' => 'QA Godox Lighting Set',
                'brand' => 'Godox',
                'daily_rate' => 18000,
                'weekly_rate' => 90000,
                'description' => 'Two softboxes + stands for studio tests.',
                'is_featured' => false,
                'featured_tag' => null,
                'how_to_videos' => [],
            ],
            [
                'slug' => 'qa-rode-wireless-go',
                'name' => 'QA Rode Wireless GO II',
                'brand' => 'Rode',
                'daily_rate' => 12000,
                'weekly_rate' => 60000,
                'description' => 'Wireless lav kit for interview tests.',
                'is_featured' => true,
                'featured_tag' => 'Audio',
                'discount_active' => true,
                'discount_percent' => 15,
                'how_to_videos' => [],
            ],
        ];

        $items = [];
        foreach ($defs as $def) {
            $item = RentalItem::withTrashed()
                ->where('business_id', $business->id)
                ->where('slug', $def['slug'])
                ->first();

            if ($item?->trashed()) {
                $item->restore();
            }

            $payload = [
                'business_id' => $business->id,
                'category_id' => $categoryId,
                'name' => $def['name'],
                'brand' => $def['brand'],
                'slug' => $def['slug'],
                'description' => $def['description'],
                'city' => 'Lagos',
                'state' => 'Lagos',
                'address' => (string) $business->address,
                'daily_rate' => $def['daily_rate'],
                'weekly_rate' => $def['weekly_rate'],
                'monthly_rate' => $def['daily_rate'] * 25,
                'currency' => 'NGN',
                'caution_fee_enabled' => false,
                'quantity_available' => 5,
                'is_available' => true,
                'is_active' => true,
                'is_featured' => (bool) ($def['is_featured'] ?? false),
                'featured_tag' => $def['featured_tag'] ?? null,
                'featured_sort' => 10,
                'discount_active' => (bool) ($def['discount_active'] ?? false),
                'discount_percent' => $def['discount_percent'] ?? null,
                'images' => [
                    'https://check-outpay.com/assets/images/logo.png',
                ],
                'specifications' => ['qa' => true, 'seeded' => true],
                'how_to_videos' => $def['how_to_videos'] ?? [],
                'terms_and_conditions' => 'QA demo item — return in same condition.',
            ];

            if ($item) {
                $item->fill($payload)->save();
            } else {
                $item = RentalItem::query()->create($payload);
            }
            $items[] = $item->fresh();
        }

        $this->line('Catalog items: '.count($items));

        return $items;
    }

    protected function purgeQaOrders(Renter $host, Renter $guest, Business $business): void
    {
        $ids = Rental::withTrashed()
            ->where('business_id', $business->id)
            ->where(function ($q) use ($host, $guest) {
                $q->whereIn('renter_id', [$host->id, $guest->id])
                    ->orWhere('renter_email', $host->email)
                    ->orWhere('renter_email', $guest->email);
            })
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        if (Schema::hasTable('rental_item_reviews')) {
            RentalItemReview::query()->whereIn('rental_id', $ids)->delete();
        }
        if (Schema::hasTable('rental_disputes')) {
            RentalDispute::query()->whereIn('rental_id', $ids)->delete();
        }
        if (Schema::hasTable('rental_condition_reports')) {
            RentalConditionReport::query()->whereIn('rental_id', $ids)->delete();
        }
        if (Schema::hasTable('rental_escrows')) {
            RentalEscrow::query()->whereIn('rental_id', $ids)->delete();
        }

        Rental::withTrashed()->whereIn('id', $ids)->forceDelete();
        $this->line('Purged '.count($ids).' prior QA rentals.');
    }

    /**
     * @param  list<RentalItem>  $items
     */
    protected function seedOrders(Renter $host, Renter $guest, Business $business, array $items): void
    {
        if ($items === []) {
            return;
        }

        $camera = $items[0];
        $light = $items[1] ?? $items[0];
        $audio = $items[2] ?? $items[0];

        // Guest → host business across statuses
        $this->makeOrder($guest, $business, $camera, Rental::STATUS_PENDING, now()->addDays(2), now()->addDays(4), 45000, 4500);
        $approved = $this->makeOrder($guest, $business, $light, Rental::STATUS_APPROVED, now()->addDays(5), now()->addDays(7), 18000, 1800);
        $approved->update(['approved_at' => now()]);
        $active = $this->makeOrder($guest, $business, $audio, Rental::STATUS_ACTIVE, now()->subDays(1), now()->addDays(2), 12000, 1200);
        $active->update(['approved_at' => now()->subDays(2), 'started_at' => now()->subDay()]);
        $completed = $this->makeOrder($guest, $business, $camera, Rental::STATUS_COMPLETED, now()->subDays(10), now()->subDays(8), 45000, 4500);
        $completed->update([
            'approved_at' => now()->subDays(12),
            'started_at' => now()->subDays(10),
            'returned_at' => now()->subDays(8),
            'completed_at' => now()->subDays(8),
            'business_return_confirmed_at' => now()->subDays(8),
            'renter_return_requested_at' => now()->subDays(8),
        ]);
        $cancelled = $this->makeOrder($guest, $business, $light, Rental::STATUS_CANCELLED, now()->addDays(20), now()->addDays(22), 18000, 0);
        $cancelled->update(['cancelled_at' => now()->subDay(), 'cancel_reason' => 'QA cancel scenario']);
        $rejected = $this->makeOrder($guest, $business, $audio, Rental::STATUS_REJECTED, now()->addDays(25), now()->addDays(27), 12000, 0);
        $rejected->update(['business_notes' => 'QA reject scenario']);

        // Host as guest on own? skip — use host as renter for one pending against same business is weird.
        // Host favorites their own catalog + review on completed guest rental.
        if (Schema::hasTable('rental_escrows')) {
            RentalEscrow::query()->updateOrCreate(
                ['rental_id' => $active->id],
                [
                    'status' => RentalEscrow::STATUS_HELD,
                    'rent_held' => 12000,
                    'deposit_held' => 1200,
                ]
            );
            RentalEscrow::query()->updateOrCreate(
                ['rental_id' => $completed->id],
                [
                    'status' => RentalEscrow::STATUS_RELEASED,
                    'rent_held' => 0,
                    'deposit_held' => 0,
                    'rent_released' => 45000,
                    'deposit_released' => 4500,
                    'rent_released_at' => now()->subDays(8),
                    'deposit_released_at' => now()->subDays(8),
                ]
            );
        }

        if (Schema::hasTable('rental_condition_reports')) {
            RentalConditionReport::query()->firstOrCreate(
                [
                    'rental_id' => $active->id,
                    'phase' => 'pickup',
                ],
                [
                    'submitted_by_business_id' => $business->id,
                    'notes' => 'QA pickup condition — all good.',
                    'images' => [],
                ]
            );
        }

        if (Schema::hasTable('rental_disputes')) {
            RentalDispute::query()->firstOrCreate(
                [
                    'rental_id' => $completed->id,
                    'opened_by_renter_id' => $guest->id,
                ],
                [
                    'reason' => 'other',
                    'description' => 'QA sample dispute for UI coverage.',
                    'status' => RentalDispute::STATUS_RESOLVED,
                    'resolution' => 'release_deposit',
                    'resolution_notes' => 'Seeded resolved dispute.',
                    'resolved_at' => now()->subDays(7),
                ]
            );
        }

        if (Schema::hasTable('rental_item_reviews')) {
            RentalItemReview::query()->updateOrCreate(
                [
                    'rental_id' => $completed->id,
                    'rental_item_id' => $camera->id,
                    'renter_id' => $guest->id,
                ],
                [
                    'rating' => 5,
                    'condition' => 'good',
                    'missing_items' => null,
                    'remarks' => 'QA review — gear worked great.',
                ]
            );
        }

        $this->line('Seeded multi-status rentals + escrow/dispute/review where tables exist.');
    }

    protected function makeOrder(
        Renter $renter,
        Business $business,
        RentalItem $item,
        string $status,
        $start,
        $end,
        float $total,
        float $deposit
    ): Rental {
        $days = max(1, (int) $start->diffInDays($end) + 1);
        $rental = Rental::query()->create([
            'renter_id' => $renter->id,
            'business_id' => $business->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'daily_rate' => round($total / $days, 2),
            'total_amount' => $total,
            'deposit_amount' => $deposit,
            'currency' => 'NGN',
            'status' => $status,
            'verified_account_number' => $renter->verified_account_number,
            'verified_account_name' => $renter->verified_account_name,
            'verified_bank_name' => $renter->verified_bank_name,
            'verified_bank_code' => $renter->verified_bank_code,
            'renter_name' => $renter->name,
            'renter_email' => $renter->email,
            'renter_phone' => $renter->phone,
            'renter_address' => $renter->address,
            'business_phone' => $business->phone,
            'fulfillment_method' => 'pickup',
            'return_method' => 'dropoff',
            'renter_notes' => 'QA seeded rental ('.$status.')',
        ]);

        $rental->items()->attach($item->id, [
            'quantity' => 1,
            'unit_rate' => round($total / $days, 2),
            'total_amount' => $total,
        ]);

        return $rental;
    }

    /**
     * @param  list<RentalItem>  $items
     */
    protected function seedExtras(Renter $host, Renter $guest, array $items): void
    {
        if (! Schema::hasTable('rental_favorites') || $items === []) {
            return;
        }

        foreach ([$host, $guest] as $renter) {
            foreach (array_slice($items, 0, 2) as $item) {
                RentalFavorite::query()->firstOrCreate([
                    'renter_id' => $renter->id,
                    'rental_item_id' => $item->id,
                ]);
            }
        }
    }
}
