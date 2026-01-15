<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebsitesController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();
        $websites = $business->websites()->latest()->get();
        
        return view('business.websites.index', compact('websites'));
    }

    public function store(Request $request)
    {
        $business = Auth::guard('business')->user();

        $validated = $request->validate([
            'website_url' => 'required|url|max:500',
        ]);

        $website = BusinessWebsite::create([
            'business_id' => $business->id,
            'website_url' => $validated['website_url'],
            'is_approved' => false, // Requires admin approval
        ]);

        // Send notification to business
        $business->notify(new \App\Notifications\WebsiteAddedNotification($website));

        return redirect()->route('business.websites.index')
            ->with('success', 'Website added successfully. It is pending admin approval.');
    }

    public function destroy(BusinessWebsite $website)
    {
        $business = Auth::guard('business')->user();

        // Ensure the website belongs to the authenticated business
        if ($website->business_id !== $business->id) {
            abort(403, 'Unauthorized action.');
        }

        $website->delete();

        return redirect()->route('business.websites.index')
            ->with('success', 'Website removed successfully.');
    }
}
