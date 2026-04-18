<?php

namespace App\Services\Rentals;

use App\Models\Business;
use App\Models\Renter;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Rentals portal identity model:
 *
 * - **Renter** — everyone using the Abuja/Cam rentals SPA signs in as a `Renter` (browse, book, wallet, KYC).
 * - **Business** — optional. If a `businesses` row exists with the **same email** as the renter, that account
 *   may call host APIs (`/api/v1/rentals/business/*`). Otherwise those routes return 403 ("Business access required").
 * - **User** (`users` table) — legacy "My Account" only on some installs; when present, login/password-reset
 *   can create or match a renter row from the same email (see {@see self::usersTableExists}).
 *
 * Managing inventory and rental orders as a **host** remains tied to an approved **business** profile
 * (typically created on the main Checkout business dashboard), not to renter-only accounts.
 */
final class RenterPortalAccountBridge
{
    public static function normalizedEmail(string $rawEmail): string
    {
        return strtolower(trim($rawEmail));
    }

    /**
     * Some production DBs only have `businesses` (no legacy `users` / My Account table).
     * Skip User lookups when the table is missing to avoid SQLSTATE 42S02.
     */
    private static function usersTableExists(): bool
    {
        return Schema::hasTable((new User)->getTable());
    }

    /**
     * If no renter row exists yet but a User or Business exists with this email, create the
     * shadow renter row so the renters password broker can send a reset link.
     * Does not verify the caller knows the password (same threat model as typical reset flows).
     */
    public static function provisionShadowRenterIfLinkedAccountExists(string $rawEmail): string
    {
        $email = self::normalizedEmail($rawEmail);

        if (Renter::query()->where('email', $email)->exists()) {
            return $email;
        }

        if (self::usersTableExists()) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user && (string) $user->getAuthPassword() !== '') {
                Renter::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $user->name ?? $user->email,
                        'email_verified_at' => $user->email_verified_at,
                        'password' => $user->getAuthPassword(),
                    ]
                );

                return $email;
            }
        }

        $business = Business::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($business && (string) $business->getAuthPassword() !== '') {
            Renter::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $business->name ?? $business->email,
                    'email_verified_at' => $business->email_verified_at,
                    'password' => $business->getAuthPassword(),
                    'phone' => $business->phone ?? null,
                ]
            );

            return $email;
        }

        return $email;
    }

    /**
     * Used by login: when renter credentials fail, try User then Business password and return a renter row.
     */
    public static function linkRenterWithPasswordFromUserOrBusiness(string $rawEmail, string $plainPassword): ?Renter
    {
        $email = self::normalizedEmail($rawEmail);

        if (self::usersTableExists()) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user && Hash::check($plainPassword, $user->getAuthPassword())) {
                return Renter::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $user->name ?? $user->email,
                        'email_verified_at' => $user->email_verified_at,
                        'password' => $user->getAuthPassword(),
                    ]
                );
            }
        }

        $business = Business::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($business && Hash::check($plainPassword, $business->getAuthPassword())) {
            return Renter::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $business->name ?? $business->email,
                    'email_verified_at' => $business->email_verified_at,
                    'password' => $business->getAuthPassword(),
                    'phone' => $business->phone ?? null,
                ]
            );
        }

        return null;
    }

    /**
     * Business profile linked to this renter for host tools (same email, case-insensitive).
     */
    public static function businessLinkedToRenterEmail(string $renterEmail): ?Business
    {
        $email = self::normalizedEmail($renterEmail);

        return Business::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }
}
