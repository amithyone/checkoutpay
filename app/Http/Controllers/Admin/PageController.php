<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PageController extends Controller
{
    public function index(): View
    {
        $pages = Page::orderBy('order')->latest()->get();
        return view('admin.pages.index', compact('pages'));
    }

    public function create(): View
    {
        return view('admin.pages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:255|unique:pages,slug',
            'title' => 'required|string|max:255',
            'content' => 'nullable',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'order' => 'nullable|integer|min:0',
        ]);

        // If content is array, encode it to JSON
        if (is_array($validated['content'] ?? null)) {
            $validated['content'] = json_encode($validated['content']);
        }

        Page::create($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page created successfully.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:255|unique:pages,slug,' . $page->id,
            'title' => 'required|string|max:255',
            'content' => 'nullable',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'order' => 'nullable|integer|min:0',
        ]);

        // Handle content: For HTML pages (like products-invoices), keep as string
        // For JSON pages (like home, pricing), encode to JSON if it's an array
        if (isset($validated['content'])) {
            // Check if it's a JSON page that expects array content
            if (in_array($page->slug, ['home', 'pricing'])) {
                // Try to decode JSON string to array
                if (is_string($validated['content'])) {
                    $decoded = json_decode($validated['content'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $validated['content'] = $decoded;
                    }
                }
            }
            // For HTML pages (like products-invoices), content is already a string, keep it as is
            // The Page model's setContentAttribute will handle it correctly
        }

        $page->update($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page updated successfully.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page deleted successfully.');
    }
}
