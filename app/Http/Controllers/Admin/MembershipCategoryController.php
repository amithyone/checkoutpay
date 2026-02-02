<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class MembershipCategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index(): View
    {
        $categories = MembershipCategory::orderBy('sort_order')->get();
        return view('admin.membership-categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category
     */
    public function create(): View
    {
        return view('admin.membership-categories.create');
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:membership_categories,name',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        MembershipCategory::create($validated);

        return redirect()->route('admin.membership-categories.index')
            ->with('success', 'Category created successfully.');
    }

    /**
     * Show the form for editing a category
     */
    public function edit(MembershipCategory $membershipCategory): View
    {
        return view('admin.membership-categories.edit', compact('membershipCategory'));
    }

    /**
     * Update a category
     */
    public function update(Request $request, MembershipCategory $membershipCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:membership_categories,name,' . $membershipCategory->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $membershipCategory->update($validated);

        return redirect()->route('admin.membership-categories.index')
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Delete a category
     */
    public function destroy(MembershipCategory $membershipCategory): RedirectResponse
    {
        if ($membershipCategory->memberships()->count() > 0) {
            return redirect()->route('admin.membership-categories.index')
                ->with('error', 'Cannot delete category with existing memberships.');
        }

        $membershipCategory->delete();
        return redirect()->route('admin.membership-categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
