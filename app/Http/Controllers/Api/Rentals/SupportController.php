<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Renter;
use App\Models\SupportTicketReply;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    /**
     * GET /api/v1/rentals/support/messages?limit=20
     * Returns latest support replies for accounts linked to a business profile.
     */
    public function messages(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();
        $limit = max(1, min(50, (int) $request->query('limit', 20)));

        $businessId = Business::query()
            ->whereRaw('LOWER(email) = LOWER(?)', [$renter->email])
            ->value('id');

        if (! $businessId) {
            return response()->json(['data' => []]);
        }

        $rows = SupportTicketReply::query()
            ->whereHas('ticket', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            })
            ->where('is_internal_note', false)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'ticket_id', 'user_type', 'message', 'created_at'])
            ->map(function (SupportTicketReply $reply) {
                return [
                    'id' => $reply->id,
                    'ticket_id' => $reply->ticket_id,
                    'user_type' => $reply->user_type,
                    'message' => $reply->message,
                    'created_at' => optional($reply->created_at)->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return response()->json(['data' => $rows]);
    }
}

