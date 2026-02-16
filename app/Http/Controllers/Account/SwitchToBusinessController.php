<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class SwitchToBusinessController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->business_id) {
            $business = Business::where('email', $user->email)->first();
            if ($business) {
                $user->update(['business_id' => $business->id]);
            }
        }
        return redirect()->route('business.login')->with('email', $user->email);
    }
}
