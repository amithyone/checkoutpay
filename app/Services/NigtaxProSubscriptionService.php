<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\MembershipSubscription;

class NigtaxProSubscriptionService
{
    public function membershipSlug(): string
    {
        return (string) config('services.nigtax.pro_membership_slug', 'nigtax-pro');
    }

    public function findMembership(): ?Membership
    {
        return Membership::query()
            ->where('slug', $this->membershipSlug())
            ->where('is_active', true)
            ->first();
    }

    /**
     * Normalize email the same way we store NigtaxProUser emails (lowercase).
     */
    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Latest active PRO subscription for this email (matches membership slug).
     */
    public function activeSubscriptionForEmail(string $email): ?MembershipSubscription
    {
        $norm = $this->normalizeEmail($email);
        $slug = $this->membershipSlug();

        return MembershipSubscription::query()
            ->whereRaw('LOWER(TRIM(member_email)) = ?', [$norm])
            ->where('status', 'active')
            ->where('expires_at', '>=', now()->toDateString())
            ->whereHas('membership', function ($q) use ($slug) {
                $q->where('slug', $slug);
            })
            ->orderByDesc('expires_at')
            ->first();
    }

    public function hasActiveSubscription(string $email): bool
    {
        return $this->activeSubscriptionForEmail($email) !== null;
    }
}
