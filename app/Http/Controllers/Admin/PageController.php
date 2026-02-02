<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

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
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'order' => 'nullable|integer|min:0',
        ]);

        // If content is array, encode it to JSON
        if (is_array($validated['content'] ?? null)) {
            $validated['content'] = json_encode($validated['content']);
        }

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('pages/featured', 'public');
        }

        // Handle multiple images upload
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('pages/images', 'public');
            }
            $validated['images'] = $images;
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
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'remove_featured_image' => 'nullable|boolean',
            'remove_images' => 'nullable|array',
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

        // Handle featured image removal
        if ($request->has('remove_featured_image') && $request->remove_featured_image) {
            if ($page->featured_image) {
                Storage::disk('public')->delete($page->featured_image);
            }
            $validated['featured_image'] = null;
        } elseif ($request->hasFile('featured_image')) {
            // Delete old featured image
            if ($page->featured_image) {
                Storage::disk('public')->delete($page->featured_image);
            }
            $validated['featured_image'] = $request->file('featured_image')->store('pages/featured', 'public');
        } else {
            // Keep existing featured image
            unset($validated['featured_image']);
        }

        // Handle images removal
        if ($request->has('remove_images') && is_array($request->remove_images)) {
            $currentImages = $page->images ?? [];
            $imagesToRemove = $request->remove_images;
            $remainingImages = array_filter($currentImages, function ($image) use ($imagesToRemove) {
                return !in_array($image, $imagesToRemove);
            });
            
            // Delete removed images from storage
            foreach ($imagesToRemove as $imageToRemove) {
                if (Storage::disk('public')->exists($imageToRemove)) {
                    Storage::disk('public')->delete($imageToRemove);
                }
            }
            
            $validated['images'] = array_values($remainingImages);
        }

        // Handle new images upload
        if ($request->hasFile('images')) {
            $currentImages = $validated['images'] ?? ($page->images ?? []);
            foreach ($request->file('images') as $image) {
                $currentImages[] = $image->store('pages/images', 'public');
            }
            $validated['images'] = $currentImages;
        }

        $page->update($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page updated successfully.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        // Delete featured image
        if ($page->featured_image) {
            Storage::disk('public')->delete($page->featured_image);
        }

        // Delete all images
        if ($page->images && is_array($page->images)) {
            foreach ($page->images as $image) {
                if (Storage::disk('public')->exists($image)) {
                    Storage::disk('public')->delete($image);
                }
            }
        }

        $page->delete();

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page deleted successfully.');
    }
}
