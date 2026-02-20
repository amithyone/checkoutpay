<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CharityCampaign;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CharityController extends Controller
{
    public function index(Request $request): View
    {
        $query = CharityCampaign::approved()
            ->with('business')
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            });

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")->orWhere('story', 'like', "%{$s}%");
            });
        }

        $sort = $request->get('sort', 'featured');
        switch ($sort) {
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            case 'most_funded':
                $query->orderByDesc('raised_amount');
                break;
            case 'ending_soon':
                $query->orderByRaw('CASE WHEN end_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('end_date', 'asc');
                break;
            case 'featured':
            default:
                $query->orderByDesc('is_featured')->orderByDesc('created_at');
                break;
        }

        $campaigns = $query->paginate(12);
        return view('charity.index', compact('campaigns'));
    }

    public function show(string $slug): View
    {
        $campaign = CharityCampaign::where('slug', $slug)
            ->approved()
            ->with('business')
            ->firstOrFail();

        if ($campaign->end_date && $campaign->end_date->isPast()) {
            abort(404);
        }

        return view('charity.show', compact('campaign'));
    }
}
