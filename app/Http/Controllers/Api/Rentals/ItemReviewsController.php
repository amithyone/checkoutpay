<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\RentalItemReview;
use App\Models\Renter;
use App\Services\Rentals\RentalCatalogFormatter;
use Illuminate\Http\Request;

class ItemReviewsController extends Controller
{
    /**
     * GET /api/v1/rentals/items/{id}/reviews
     */
    public function index(Request $request, int $id)
    {
        $item = RentalItem::query()
            ->where('is_active', true)
            ->findOrFail($id);

        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));

        $reviewsQuery = RentalItemReview::query()
            ->where('rental_item_id', $item->id)
            ->where(function ($q) {
                $q->whereNotNull('rating')
                    ->orWhereNotNull('condition')
                    ->orWhereNotNull('missing_items')
                    ->orWhereNotNull('remarks');
            })
            ->with(['renter:id,name'])
            ->latest();

        $reviews = $reviewsQuery->paginate($perPage);

        $stats = RentalItemReview::query()
            ->where('rental_item_id', $item->id)
            ->where(function ($q) {
                $q->whereNotNull('rating')
                    ->orWhereNotNull('condition')
                    ->orWhereNotNull('missing_items')
                    ->orWhereNotNull('remarks');
            })
            ->selectRaw('AVG(CASE WHEN rating IS NOT NULL THEN rating END) as average_rating')
            ->selectRaw("SUM(CASE WHEN `condition` = 'new' THEN 1 ELSE 0 END) as condition_new")
            ->selectRaw("SUM(CASE WHEN `condition` = 'good' THEN 1 ELSE 0 END) as condition_good")
            ->selectRaw("SUM(CASE WHEN `condition` = 'old' THEN 1 ELSE 0 END) as condition_old")
            ->selectRaw("SUM(CASE WHEN `condition` = 'bad' THEN 1 ELSE 0 END) as condition_bad")
            ->first();

        $reviewsCount = (int) RentalItemReview::query()
            ->where('rental_item_id', $item->id)
            ->where(function ($q) {
                $q->whereNotNull('rating')
                    ->orWhereNotNull('condition')
                    ->orWhereNotNull('missing_items')
                    ->orWhereNotNull('remarks');
            })
            ->count();

        $averageRating = RentalCatalogFormatter::averageRatingFromReviews(
            (float) ($stats->average_rating ?? 0),
            (int) RentalItemReview::query()
                ->where('rental_item_id', $item->id)
                ->whereNotNull('rating')
                ->count()
        );

        return response()->json([
            'success' => true,
            'data' => [
                'item_id' => $item->id,
                'average_rating' => $averageRating,
                'reviews_count' => $reviewsCount,
                'condition_summary' => [
                    'new' => (int) ($stats->condition_new ?? 0),
                    'good' => (int) ($stats->condition_good ?? 0),
                    'old' => (int) ($stats->condition_old ?? 0),
                    'bad' => (int) ($stats->condition_bad ?? 0),
                ],
                'reviews' => collect($reviews->items())
                    ->map(fn (RentalItemReview $review) => RentalCatalogFormatter::reviewEntry($review))
                    ->values()
                    ->all(),
            ],
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/rentals/requests/{rental}/reviews
     */
    public function store(Request $request, Rental $rental)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ((int) $rental->renter_id !== (int) $renter->id) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        if ($rental->status !== Rental::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'Reviews can only be submitted after the rental is completed.',
            ], 422);
        }

        $rental->loadMissing('items');

        $entries = $this->normalizeReviewEntries($request);
        if ($entries === []) {
            return response()->json([
                'success' => true,
                'message' => 'No feedback submitted.',
                'data' => ['saved' => []],
            ]);
        }

        $allowedItemIds = $rental->items->pluck('id')->all();
        $saved = [];

        foreach ($entries as $entry) {
            $itemId = (int) ($entry['item_id'] ?? 0);
            if (! in_array($itemId, $allowedItemIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Item {$itemId} is not part of this rental.",
                ], 422);
            }

            $payload = [
                'rating' => isset($entry['rating']) && $entry['rating'] !== '' ? (int) $entry['rating'] : null,
                'condition' => RentalItemReview::normalizeCondition($entry['condition'] ?? null),
                'missing_items' => filled($entry['missing_items'] ?? null) ? trim((string) $entry['missing_items']) : null,
                'remarks' => filled($entry['remarks'] ?? null) ? trim((string) $entry['remarks']) : null,
            ];

            if ($payload['rating'] !== null && ($payload['rating'] < 1 || $payload['rating'] > 5)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rating must be between 1 and 5.',
                ], 422);
            }

            if (
                $payload['rating'] === null
                && $payload['condition'] === null
                && $payload['missing_items'] === null
                && $payload['remarks'] === null
            ) {
                continue;
            }

            $review = RentalItemReview::query()->updateOrCreate(
                [
                    'rental_id' => $rental->id,
                    'rental_item_id' => $itemId,
                    'renter_id' => $renter->id,
                ],
                $payload
            );

            $review->load('renter:id,name');
            $saved[] = RentalCatalogFormatter::reviewEntry($review);
        }

        return response()->json([
            'success' => true,
            'message' => $saved === [] ? 'No feedback submitted.' : 'Thank you for your feedback.',
            'data' => [
                'saved' => $saved,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeReviewEntries(Request $request): array
    {
        if ($request->has('reviews') && is_array($request->input('reviews'))) {
            return array_values($request->input('reviews'));
        }

        if ($request->has('item_id')) {
            return [[
                'item_id' => $request->input('item_id'),
                'rating' => $request->input('rating'),
                'condition' => $request->input('condition'),
                'missing_items' => $request->input('missing_items'),
                'remarks' => $request->input('remarks'),
            ]];
        }

        return [];
    }
}
