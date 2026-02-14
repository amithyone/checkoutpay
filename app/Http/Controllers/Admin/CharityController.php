<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CharityCampaign;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class CharityController extends Controller
{
    public function index(): View
    {
        $campaigns = CharityCampaign::with('business')->latest()->paginate(20);
        return view('admin.charity.index', compact('campaigns'));
    }

    public function create(): View
    {
        $businesses = Business::orderBy('name')->get();
        return view('admin.charity.create', compact('businesses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_id' => 'nullable|exists:businesses,id',
            'title' => 'required|string|max:255',
            'story' => 'nullable|string',
            'goal_amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'end_date' => 'nullable|date',
            'status' => 'required|in:pending,approved,rejected',
            'is_featured' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['slug'] = Str::slug($validated['title']);
        while (CharityCampaign::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = Str::slug($validated['title']) . '-' . rand(100, 999);
        }
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $validated['image'] = $request->file('image')->store('charity', 'public');
        }
        CharityCampaign::create($validated);
        return redirect()->route('admin.charity.index')->with('success', 'Campaign created.');
    }

    public function show(CharityCampaign $campaign): View
    {
        $campaign->load('business');
        return view('admin.charity.show', compact('campaign'));
    }

    public function edit(CharityCampaign $campaign): View
    {
        $campaign->load('business');
        $businesses = Business::orderBy('name')->get();
        return view('admin.charity.edit', compact('campaign', 'businesses'));
    }

    public function update(Request $request, CharityCampaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'business_id' => 'nullable|exists:businesses,id',
            'title' => 'required|string|max:255',
            'story' => 'nullable|string',
            'goal_amount' => 'required|numeric|min:0',
            'raised_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'end_date' => 'nullable|date',
            'status' => 'required|in:pending,approved,rejected',
            'is_featured' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
        $validated['is_featured'] = $request->boolean('is_featured');
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            if ($campaign->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($campaign->image)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($campaign->image);
            }
            $validated['image'] = $request->file('image')->store('charity', 'public');
        }
        $campaign->update($validated);
        return redirect()->route('admin.charity.index')->with('success', 'Campaign updated.');
    }

    public function destroy(CharityCampaign $campaign): RedirectResponse
    {
        if ($campaign->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($campaign->image)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($campaign->image);
        }
        $campaign->delete();
        return redirect()->route('admin.charity.index')->with('success', 'Campaign deleted.');
    }
}
