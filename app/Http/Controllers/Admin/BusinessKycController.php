<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessVerification;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessKycController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        $requiredTypes = BusinessVerification::getRequiredTypes();

        $query = Business::query()
            ->with(['verifications' => function ($q) use ($requiredTypes) {
                $q->whereIn('verification_type', $requiredTypes);
            }])
            ->withCount([
                'verifications as pending_kyc_docs_count' => function ($q) use ($requiredTypes) {
                    $q->whereIn('verification_type', $requiredTypes)
                        ->whereIn('status', [BusinessVerification::STATUS_PENDING, BusinessVerification::STATUS_UNDER_REVIEW]);
                },
                'verifications as rejected_kyc_docs_count' => function ($q) use ($requiredTypes) {
                    $q->whereIn('verification_type', $requiredTypes)
                        ->where('status', BusinessVerification::STATUS_REJECTED);
                },
            ])
            ->orderByDesc('created_at');

        if ($status === 'verified') {
            // Must have all required types and all approved.
            foreach ($requiredTypes as $type) {
                $query->whereHas('verifications', function ($q) use ($type) {
                    $q->where('verification_type', $type)
                        ->where('status', BusinessVerification::STATUS_APPROVED);
                });
            }
        } elseif ($status === 'pending') {
            $query->whereHas('verifications', function ($q) use ($requiredTypes) {
                $q->whereIn('verification_type', $requiredTypes)
                    ->whereIn('status', [BusinessVerification::STATUS_PENDING, BusinessVerification::STATUS_UNDER_REVIEW]);
            });
        } elseif ($status === 'rejected') {
            $query->whereHas('verifications', function ($q) use ($requiredTypes) {
                $q->whereIn('verification_type', $requiredTypes)
                    ->where('status', BusinessVerification::STATUS_REJECTED);
            });
        } elseif ($status === 'not_submitted') {
            $query->whereDoesntHave('verifications', function ($q) use ($requiredTypes) {
                $q->whereIn('verification_type', $requiredTypes);
            });
        } else {
            // "all" or unknown: no additional filtering
        }

        $businesses = $query->paginate(20)->withQueryString();

        $requiredTypeCount = count($requiredTypes);
        $businessKycMeta = [];

        foreach ($businesses as $business) {
            $verifications = $business->verifications;
            $submittedTypes = $verifications
                ->pluck('verification_type')
                ->unique()
                ->values()
                ->all();

            $approvedTypes = $verifications
                ->where('status', BusinessVerification::STATUS_APPROVED)
                ->pluck('verification_type')
                ->unique()
                ->values()
                ->all();

            $missingDocs = array_values(array_diff($requiredTypes, $submittedTypes));
            $isAllSubmitted = count($submittedTypes) === $requiredTypeCount;
            $isAllApproved = count($approvedTypes) === $requiredTypeCount;

            $computed = 'pending';
            if ($isAllApproved) {
                $computed = 'verified';
            } elseif ($verifications->where('status', BusinessVerification::STATUS_REJECTED)->count() > 0) {
                $computed = 'rejected';
            } elseif ($isAllSubmitted) {
                $computed = 'under_review';
            } elseif (count($missingDocs) > 0) {
                $computed = 'incomplete';
            }

            $businessKycMeta[$business->id] = [
                'computed_status' => $computed,
                'missing_count' => count($missingDocs),
                'missing_docs' => $missingDocs,
                'submitted_count' => count($submittedTypes),
                'approved_count' => count($approvedTypes),
            ];
        }

        return view('admin.businesses-kyc.index', [
            'businesses' => $businesses,
            'status' => $status,
            'businessKycMeta' => $businessKycMeta,
            'requiredTypeCount' => $requiredTypeCount,
        ]);
    }
}

