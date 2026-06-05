<?php

namespace App\Services\Account;

use App\Models\Business;
use App\Models\Renter;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves My Account login targets (User, Business, or Renter) by email.
 *
 * Some production DBs only have `businesses` (no legacy `users` table).
 * Skip User lookups when the table is missing to avoid SQLSTATE 42S02.
 */
final class AccountLoginResolver
{
    public static function normalizedEmail(string $rawEmail): string
    {
        return strtolower(trim($rawEmail));
    }

    public static function usersTableExists(): bool
    {
        return Schema::hasTable((new User)->getTable());
    }

    /**
     * @return array{guard: string, id: int}|null
     */
    public static function resolveByEmail(string $rawEmail): ?array
    {
        $email = self::normalizedEmail($rawEmail);

        if (self::usersTableExists()) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user) {
                return ['guard' => 'web', 'id' => $user->id];
            }
        }

        $business = Business::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($business) {
            return ['guard' => 'business', 'id' => $business->id];
        }

        $renter = Renter::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($renter) {
            return ['guard' => 'renter', 'id' => $renter->id];
        }

        return null;
    }
}
