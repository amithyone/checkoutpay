<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Admin\AdminPagePermissionCatalog;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $query = Admin::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $staff = $query->latest()->paginate(20)->withQueryString();

        return view('admin.staff.index', compact('staff'));
    }

    public function create(): View
    {
        return view('admin.staff.create', [
            'pageCatalog' => app(AdminPagePermissionCatalog::class)->forStaffForm(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->validationRules());

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ];

        $data = array_merge($data, $this->walletSupportFieldsFromRequest($request, $validated['role']));
        $data['admin_page_permissions'] = $this->pagePermissionsFromRequest($request, $validated['role']);

        Admin::create($data);

        return redirect()->route('admin.staff.index')
            ->with('success', 'Staff member created successfully.');
    }

    public function edit(Admin $staff): View
    {
        if ($staff->isSuperAdmin()) {
            abort(403, 'Cannot edit super admin accounts.');
        }

        return view('admin.staff.edit', [
            'staff' => $staff,
            'pageCatalog' => app(AdminPagePermissionCatalog::class)->forStaffForm(),
        ]);
    }

    public function update(Request $request, Admin $staff): RedirectResponse
    {
        if ($staff->isSuperAdmin()) {
            abort(403, 'Cannot edit super admin accounts.');
        }

        $validated = $request->validate($this->validationRules($staff));

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $updateData = array_merge($updateData, $this->walletSupportFieldsFromRequest($request, $validated['role']));
        $updateData['admin_page_permissions'] = $this->pagePermissionsFromRequest($request, $validated['role']);

        $staff->update($updateData);

        return redirect()->route('admin.staff.index')
            ->with('success', 'Staff member updated successfully.');
    }

    public function destroy(Admin $staff): RedirectResponse
    {
        if ($staff->isSuperAdmin()) {
            abort(403, 'Cannot delete super admin accounts.');
        }

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
        if ($staff->isSuperAdmin()) {
            abort(403, 'Cannot modify super admin accounts.');
        }

        $staff->update([
            'is_active' => ! $staff->is_active,
        ]);

        $status = $staff->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.staff.index')
            ->with('success', "Staff member {$status} successfully.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(?Admin $staff = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('admins', 'email')->ignore($staff?->id)],
            'role' => ['required', Rule::in([Admin::ROLE_STAFF, Admin::ROLE_ADMIN, Admin::ROLE_SUPPORT, Admin::ROLE_TAX, Admin::ROLE_WALLET_SUPPORT])],
            'is_active' => 'boolean',
            'whatsapp_e164' => 'nullable|string|max:20',
            'notify_wallet_signup' => 'boolean',
            'handles_wallet_support_in_app' => 'boolean',
            'page_permissions' => 'nullable|array',
            'page_permissions.*' => 'string',
        ];

        if ($staff === null) {
            $rules['password'] = 'required|string|min:8|confirmed';
        } else {
            $rules['password'] = 'nullable|string|min:8|confirmed';
        }

        return $rules;
    }

    /**
     * @return array{whatsapp_e164: ?string, notify_wallet_signup: bool, handles_wallet_support_in_app: bool}
     */
    private function walletSupportFieldsFromRequest(Request $request, string $role): array
    {
        if ($role !== Admin::ROLE_WALLET_SUPPORT) {
            return [
                'whatsapp_e164' => null,
                'notify_wallet_signup' => false,
                'handles_wallet_support_in_app' => false,
            ];
        }

        $rawPhone = trim((string) $request->input('whatsapp_e164', ''));
        $phone = $rawPhone !== '' ? PhoneNormalizer::canonicalAuthE164Digits($rawPhone) : null;

        if ($rawPhone !== '' && $phone === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'whatsapp_e164' => 'Enter a valid WhatsApp number for a supported country.',
            ]);
        }

        return [
            'whatsapp_e164' => $phone,
            'notify_wallet_signup' => $request->boolean('notify_wallet_signup', true),
            'handles_wallet_support_in_app' => $request->boolean('handles_wallet_support_in_app', true),
        ];
    }

    /**
     * @return list<string>|null
     */
    private function pagePermissionsFromRequest(Request $request, string $role): ?array
    {
        if (! in_array($role, [Admin::ROLE_WALLET_SUPPORT, Admin::ROLE_STAFF, Admin::ROLE_SUPPORT], true)) {
            return null;
        }

        $selected = $request->input('page_permissions', []);
        if (! is_array($selected)) {
            return null;
        }

        $valid = array_flip(array_keys(config('admin_pages.pages', [])));
        $clean = [];
        foreach ($selected as $key) {
            if (is_string($key) && isset($valid[$key])) {
                $clean[] = $key;
            }
        }

        return $clean === [] ? null : array_values(array_unique($clean));
    }
}
