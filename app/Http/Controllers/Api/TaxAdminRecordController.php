<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipSubscription;
use App\Models\NigtaxProUser;
use App\Services\NigtaxProSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TaxAdminRecordController extends Controller
{
    public function businessRecords(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);
        $q = trim((string) $request->query('q', ''));

        $query = DB::table('nigtax_business_records')->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('business_name', 'like', '%'.$q.'%')
                    ->orWhere('phone_number', 'like', '%'.$q.'%')
                    ->orWhere('rc_number', 'like', '%'.$q.'%')
                    ->orWhere('tin_number', 'like', '%'.$q.'%');
                if (Schema::hasColumn('nigtax_business_records', 'email')) {
                    $sub->orWhere('email', 'like', '%'.$q.'%');
                }
            });
        }

        $paginator = $query->paginate($perPage);

        return response()->json($paginator);
    }

    public function personalRecords(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);
        $q = trim((string) $request->query('q', ''));

        $query = DB::table('nigtax_personal_records')->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('individual_name', 'like', '%'.$q.'%');
                if (Schema::hasColumn('nigtax_personal_records', 'email')) {
                    $sub->orWhere('email', 'like', '%'.$q.'%');
                }
            });
        }

        $paginator = $query->paginate($perPage);

        return response()->json($paginator);
    }

    /**
     * NigTax PRO calculator accounts (nigtax_pro_users), with subscription and saved-query hints.
     */
    public function proUsers(Request $request): JsonResponse
    {
        if (! Schema::hasTable('nigtax_pro_users')) {
            return response()->json(new LengthAwarePaginator([], 0, 25));
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $q = trim((string) $request->query('q', ''));

        $query = NigtaxProUser::query()->orderByDesc('id');

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where('email', 'like', $like);
        }

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage);
        $users = $paginator->getCollection();

        $proMembershipId = app(NigtaxProSubscriptionService::class)->findMembership()?->id;
        $today = now()->toDateString();

        $subsByEmail = [];
        if ($proMembershipId && $users->isNotEmpty()) {
            $normSet = $users->map(fn (NigtaxProUser $u) => strtolower(trim($u->email)))->unique()->filter()->values();
            if ($normSet->isNotEmpty()) {
                $candidates = MembershipSubscription::query()
                    ->where('membership_id', $proMembershipId)
                    ->where('status', 'active')
                    ->where('expires_at', '>=', $today)
                    ->where(function ($subq) use ($normSet) {
                        foreach ($normSet as $em) {
                            $subq->orWhereRaw('LOWER(TRIM(member_email)) = ?', [$em]);
                        }
                    })
                    ->orderByDesc('expires_at')
                    ->get(['member_email', 'expires_at']);
            } else {
                $candidates = collect();
            }

            foreach ($candidates as $sub) {
                $key = strtolower(trim((string) $sub->member_email));
                if ($key === '') {
                    continue;
                }
                if (! isset($subsByEmail[$key]) || $sub->expires_at > $subsByEmail[$key]) {
                    $subsByEmail[$key] = $sub->expires_at;
                }
            }
        }

        $savedCounts = [];
        if (Schema::hasTable('nigtax_pro_saved_queries') && $users->isNotEmpty()) {
            $ids = $users->pluck('id')->all();
            $savedCounts = DB::table('nigtax_pro_saved_queries')
                ->selectRaw('nigtax_pro_user_id, COUNT(*) as c')
                ->whereIn('nigtax_pro_user_id', $ids)
                ->groupBy('nigtax_pro_user_id')
                ->pluck('c', 'nigtax_pro_user_id')
                ->all();
        }

        $paginator->setCollection(
            $users->map(function (NigtaxProUser $user) use ($subsByEmail, $savedCounts) {
                $key = strtolower(trim($user->email));
                $expires = $subsByEmail[$key] ?? null;

                return [
                    'id' => $user->id,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                    'pro_subscription_active' => $expires !== null,
                    'pro_subscription_expires_at' => $expires instanceof \Carbon\CarbonInterface
                        ? $expires->toDateString()
                        : null,
                    'saved_queries_count' => (int) ($savedCounts[$user->id] ?? 0),
                ];
            })
        );

        return response()->json($paginator);
    }
}
