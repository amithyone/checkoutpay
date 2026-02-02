<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MembershipSubscription;
use App\Services\MembershipCardPdfService;
use Illuminate\Http\Request;

class MembershipCardController extends Controller
{
    public function __construct(
        protected MembershipCardPdfService $cardPdfService
    ) {}

    /**
     * Download membership card PDF
     */
    public function download(string $subscriptionNumber)
    {
        $subscription = MembershipSubscription::where('subscription_number', $subscriptionNumber)
            ->with(['membership.business', 'membership.category'])
            ->firstOrFail();

        return $this->cardPdfService->downloadPdf($subscription);
    }

    /**
     * View membership card PDF
     */
    public function view(string $subscriptionNumber)
    {
        $subscription = MembershipSubscription::where('subscription_number', $subscriptionNumber)
            ->with(['membership.business', 'membership.category'])
            ->firstOrFail();

        return $this->cardPdfService->streamPdf($subscription);
    }
}
