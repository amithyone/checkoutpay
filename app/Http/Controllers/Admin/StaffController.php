<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $query = Admin::query();

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $staff = $query->latest()->paginate(20)->withQueryString();

        return view('admin.staff.index', compact('staff'));
    }

    public function create(): View
    {
        return view('admin.staff.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in([Admin::ROLE_STAFF, Admin::ROLE_ADMIN, Admin::ROLE_SUPPORT])],
            'is_active' => 'boolean',
        ]);

        Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.staff.index')
            ->with('success', 'Staff member created successfully.');
    }

    public function edit(Admin $staff): View
    {
        // Prevent editing super admins
        if ($staff->isSuperAdmin()) {
            abort(403, 'Cannot edit super admin accounts.');
        }

        return view('admin.staff.edit', compact('staff'));
    }

    public function update(Request $request, Admin $staff): RedirectResponse
    {
        // Prevent editing super admins
        if ($staff->isSuperAdmin()) {
            abort(403, 'Cannot edit super admin accounts.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('admins', 'email')->ignore($staff->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => ['required', Rule::in([Admin::ROLE_STAFF, Admin::ROLE_ADMIN, Admin::ROLE_SUPPORT])],
            'is_active' => 'boolean',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $staff->update($updateData);

        return redirect()->route('admin.staff.index')
            ->with('success', 'Staff member updated successfully.');
    }

    public function destroy(Admin $staff): RedirectResponse
    {
        // Prevent deleting super admins
        if ($staff->isSuperAdmin()) {
            abort(403, 'Cannot delete super admin accounts.');
        }

        // Prevent deleting yourself
        if ($staff->id === auth('admin')->id()) {
            return redirect()->route('admin.staff.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $staff->delete();

        return redirect()->route('admin.staff.index')
            ->with('success', 'Staff member deleted successfully.');
    }

    public function toggleStatus(Admin $staff): RedirectResponse
    {
        // Prevent toggling super admins
        if ($staff->isSuperAdmin()) {
            abort(403, 'Cannot modify super admin accounts.');
        }

        $staff->update([
            'is_active' => !$staff->is_active,
        ]);

        $status = $staff->is_active ? 'activated' : 'deactivated';
        return redirect()->route('admin.staff.index')
            ->with('success', "Staff member {$status} successfully.");
    }
}
