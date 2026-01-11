<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();
        return view('business.settings.index', compact('business'));
    }

    public function update(Request $request)
    {
        $business = Auth::guard('business')->user();

        $validated = $request->validate([
            'webhook_url' => 'nullable|url|max:500',
        ]);

        $business->update($validated);

        return redirect()->route('business.settings.index')
            ->with('success', 'Settings updated successfully');
    }

    public function regenerateApiKey()
    {
        $business = Auth::guard('business')->user();
        
        $business->update([
            'api_key' => 'pk_' . Str::random(32),
        ]);

        return redirect()->route('business.settings.index')
            ->with('success', 'API key regenerated successfully');
    }
}
