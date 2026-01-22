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
            'webhook_url' => 'nullable|url|max:500',
        ]);

        $website = BusinessWebsite::create([
            'business_id' => $business->id,
            'website_url' => $validated['website_url'],
            'webhook_url' => $validated['webhook_url'] ?? null,
            'is_approved' => false, // Requires admin approval
        ]);

        // Send notification to business
        $business->notify(new \App\Notifications\WebsiteAddedNotification($website));

        return redirect()->route('business.websites.index')
            ->with('success', 'Website added successfully. It is pending admin approval.');
    }

    public function update(Request $request, BusinessWebsite $website)
    {
        $business = Auth::guard('business')->user();

        // Ensure the website belongs to the authenticated business
        // Use strict comparison and check both website and business exist
        if (!$website) {
            abort(404, 'Website not found.');
        }

        // Compare business_id (from business_websites table) with business id (from businesses table)
        // Use == instead of === to handle potential type differences
        if ((int)$website->business_id !== (int)$business->id) {
            \Log::error('Website ownership mismatch', [
                'website_id' => $website->id,
                'website_business_id' => $website->business_id,
                'website_business_id_type' => gettype($website->business_id),
                'authenticated_business_id' => $business->id,
                'authenticated_business_id_type' => gettype($business->id),
            ]);
            abort(403, 'Website does not belong to this business.');
        }

        $validated = $request->validate([
            'webhook_url' => 'nullable|url|max:500',
        ]);

        $website->update([
            'webhook_url' => $validated['webhook_url'],
        ]);

        return redirect()->route('business.websites.index')
            ->with('success', 'Webhook URL updated successfully.');
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
