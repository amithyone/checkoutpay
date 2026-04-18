<?php

namespace App\Services\Rentals;

use App\Models\Business;
use App\Models\Renter;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Keeps rentals portal "renter" rows aligned with existing User / Business accounts
 * (same emails as login fallback in {@see \App\Http\Controllers\Api\Rentals\AuthController::login}).
 */
final class RenterPortalAccountBridge
{
    public static function normalizedEmail(string $rawEmail): string
    {
        return strtolower(trim($rawEmail));
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
}
