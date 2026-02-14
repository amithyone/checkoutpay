<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\CharityCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class CharityController extends Controller
{
    public function index(): View
    {
        $business = Auth::guard('business')->user();
        $campaigns = CharityCampaign::where('business_id', $business->id)->latest()->paginate(20);
        return view('business.charity.index', compact('campaigns'));
    }

    public function create(): View
    {
        return view('business.charity.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'story' => 'nullable|string',
            'goal_amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'end_date' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
        $validated['business_id'] = $business->id;
        $validated['status'] = 'pending';
        $validated['slug'] = Str::slug($validated['title']);
        while (CharityCampaign::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = Str::slug($validated['title']) . '-' . rand(100, 999);
        }
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $validated['image'] = $request->file('image')->store('charity', 'public');
        }
        CharityCampaign::create($validated);
        return redirect()->route('business.charity.index')->with('success', 'Campaign submitted for review. It will appear on the public page once approved by admin.');
    }

    public function edit(CharityCampaign $campaign): View
    {
        $business = Auth::guard('business')->user();
        if ((int) $campaign->business_id !== (int) $business->id) {
            abort(403);
        }
        return view('business.charity.edit', compact('campaign'));
    }

    public function update(Request $request, CharityCampaign $campaign): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        if ((int) $campaign->business_id !== (int) $business->id) {
            abort(403);
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'story' => 'nullable|string',
            'goal_amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'end_date' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            if ($campaign->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($campaign->image)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($campaign->image);
            }
            $validated['image'] = $request->file('image')->store('charity', 'public');
        }
        $campaign->update($validated);
        return redirect()->route('business.charity.index')->with('success', 'Campaign updated.');
    }
}
