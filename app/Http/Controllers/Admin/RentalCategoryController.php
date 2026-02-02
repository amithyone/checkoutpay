<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RentalCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class RentalCategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index(): View
    {
        $categories = RentalCategory::orderBy('sort_order')->get();
        return view('admin.rental-categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category
     */
    public function create(): View
    {
        return view('admin.rental-categories.create');
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:rental_categories,name',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        RentalCategory::create($validated);

        return redirect()->route('admin.rental-categories.index')
            ->with('success', 'Category created successfully.');
    }

    /**
     * Show the form for editing a category
     */
    public function edit(RentalCategory $rentalCategory): View
    {
        return view('admin.rental-categories.edit', compact('rentalCategory'));
    }

    /**
     * Update a category
     */
    public function update(Request $request, RentalCategory $rentalCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:rental_categories,name,' . $rentalCategory->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $rentalCategory->update($validated);

        return redirect()->route('admin.rental-categories.index')
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Delete a category
     */
    public function destroy(RentalCategory $rentalCategory): RedirectResponse
    {
        if ($rentalCategory->items()->count() > 0) {
            return redirect()->route('admin.rental-categories.index')
                ->with('error', 'Cannot delete category with existing items.');
        }

        $rentalCategory->delete();
        return redirect()->route('admin.rental-categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
