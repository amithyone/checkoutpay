<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\View\View;

class CheckoutDemoController extends Controller
{
    public function index(): View
    {
        // Get first active business for demo
        $demoBusiness = Business::where('is_active', true)->first();
        
        // Ensure business has approved website for demo return URL
        if ($demoBusiness) {
            $currentUrl = config('app.url');
            $hasApprovedWebsite = $demoBusiness->approvedWebsites()
                ->where('website_url', 'like', '%' . parse_url($currentUrl, PHP_URL_HOST) . '%')
                ->where('is_approved', true)
                ->exists();
            
            if (!$hasApprovedWebsite) {
                // Create approved website for demo
                $demoBusiness->websites()->create([
                    'website_url' => $currentUrl,
                    'is_approved' => true,
                    'approved_at' => now(),
                ]);
            }
        }
        
        return view('checkout-demo.index', [
            'demoBusinessId' => $demoBusiness ? $demoBusiness->id : null,
            'demoBusinessName' => $demoBusiness ? $demoBusiness->name : 'Demo Business',
        ]);
    }
}
