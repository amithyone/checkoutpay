<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{

    public function index(Request $request): View
    {
        $renter = $request->user('renter');
        
        $rentals = Rental::where('renter_id', $renter->id)
            ->with(['business', 'items'])
            ->latest()
            ->paginate(10);

        $stats = [
            'total' => Rental::where('renter_id', $renter->id)->count(),
            'pending' => Rental::where('renter_id', $renter->id)->where('status', 'pending')->count(),
            'approved' => Rental::where('renter_id', $renter->id)->where('status', 'approved')->count(),
            'active' => Rental::where('renter_id', $renter->id)->where('status', 'active')->count(),
            'completed' => Rental::where('renter_id', $renter->id)->where('status', 'completed')->count(),
        ];

        return view('renter.dashboard.index', compact('rentals', 'stats', 'renter'));
    }

    public function show(Rental $rental): View
    {
        $renter = request()->user('renter');
        
        // Ensure the rental belongs to the authenticated renter
        if ($rental->renter_id !== $renter->id) {
            abort(403, 'Unauthorized access to this rental.');
        }

        $rental->load(['business', 'items.category']);

        return view('renter.dashboard.show', compact('rental'));
    }
}
