<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Renter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RenterController extends Controller
{
    /**
     * List all rental (renter) users for admin.
     */
    public function index(Request $request): View
    {
        $query = Renter::query()->latest('updated_at');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($request->has('active') && $request->query('active') !== '' && $request->query('active') !== null) {
            $query->where('is_active', $request->boolean('active'));
        }

        $renters = $query->paginate(24)->withQueryString();

        return view('admin.renters.index', [
            'renters' => $renters,
            'search' => $request->query('q', ''),
            'activeFilter' => $request->query('active'),
        ]);
    }
}
