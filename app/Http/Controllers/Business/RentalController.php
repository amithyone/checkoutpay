<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\RentalCategory;
use App\Services\RentalPaymentService;
use App\Mail\RentalApprovedPayNow;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RentalController extends Controller
{
    protected function maybeFinalizeReturn(Rental $rental): void
    {
        $rental->refresh();
        if ($rental->returned_at) {
            return;
        }
        if (! $rental->renter_return_requested_at || ! $rental->business_return_confirmed_at) {
            return;
        }
        $rental->update([
            'returned_at' => now(),
            'completed_at' => $rental->completed_at ?? now(),
            'status' => Rental::STATUS_COMPLETED,
        ]);
    }


    /**
     * Display a listing of rental requests
     */
    public function index(Request $request): View
    {
        $business = $request->user('business');
        
        $query = Rental::where('business_id', $business->id)
            ->with(['renter', 'items'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('rental_number', 'like', "%{$search}%")
                  ->orWhere('renter_name', 'like', "%{$search}%")
                  ->orWhere('renter_email', 'like', "%{$search}%");
            });
        }

        $rentals = $query->paginate(20);

        $stats = [
            'total' => Rental::where('business_id', $business->id)->count(),
            'pending' => Rental::where('business_id', $business->id)->where('status', 'pending')->count(),
            'approved' => Rental::where('business_id', $business->id)->where('status', 'approved')->count(),
            'active' => Rental::where('business_id', $business->id)->where('status', 'active')->count(),
            'completed' => Rental::where('business_id', $business->id)->where('status', 'completed')->count(),
        ];

        return view('business.rentals.index', compact('rentals', 'stats'));
    }

    /**
     * Show the form for creating a new rental item
     */
    public function createItem(): View
    {
        $business = request()->user('business');
        $categories = RentalCategory::where('is_active', true)->get();
        return view('business.rentals.create-item', compact('categories'));
    }

    /**
     * Store a newly created rental item
     */
    public function storeItem(Request $request): RedirectResponse
    {
        $business = $request->user('business');

        $payloadForLog = $request->except(['images', '_token']);

        Log::info('rental_items.store.start', [
            'business_id' => $business?->id,
            'route' => $request->path(),
            'method' => $request->method(),
            'has_images' => $request->hasFile('images'),
            'images_count' => is_array($request->file('images')) ? count($request->file('images')) : 0,
            'payload' => $payloadForLog,
        ]);

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:rental_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
            'daily_rate' => 'required|numeric|min:0',
            'weekly_rate' => 'nullable|numeric|min:0',
            'monthly_rate' => 'nullable|numeric|min:0',
            'caution_fee_enabled' => 'sometimes|boolean',
            'caution_fee_percent' => 'nullable|numeric|min:0|max:100',
            'quantity_available' => 'required|integer|min:1',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
            'specifications' => 'nullable|array',
            'specifications_json' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'is_featured' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'is_available' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            Log::warning('rental_items.store.validation_failed', [
                'business_id' => $business?->id,
                'errors' => $validator->errors()->toArray(),
                'payload' => $payloadForLog,
            ]);
            throw new ValidationException($validator);
        }

        try {
            $validated = $validator->validated();

            $validated['business_id'] = $business->id;
            $validated['is_active'] = $validated['is_active'] ?? true;
            $validated['is_available'] = $validated['is_available'] ?? true;
            $validated['caution_fee_enabled'] = (bool) ($validated['caution_fee_enabled'] ?? false);
            $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
            $validated['currency'] = strtoupper((string) ($validated['currency'] ?? 'NGN'));
            $validated['caution_fee_percent'] = $validated['caution_fee_enabled']
                ? (float) ($validated['caution_fee_percent'] ?? 0)
                : 0;

            if (isset($validated['specifications_json']) && trim((string) $validated['specifications_json']) !== '') {
                $decodedSpecs = json_decode($validated['specifications_json'], true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedSpecs)) {
                    Log::warning('rental_items.store.invalid_specifications_json', [
                        'business_id' => $business?->id,
                        'error' => json_last_error_msg(),
                        'value' => $validated['specifications_json'],
                    ]);
                    throw ValidationException::withMessages([
                        'specifications_json' => 'Specifications JSON must be a valid JSON object or array.',
                    ]);
                }
                $validated['specifications'] = $decodedSpecs;
            }
            unset($validated['specifications_json']);

            // Handle image uploads
            if ($request->hasFile('images')) {
                $imagePaths = [];
                foreach ($request->file('images') as $image) {
                    $path = $image->store('rental-items', 'public');
                    $imagePaths[] = $path;
                }
                $validated['images'] = $imagePaths;
            }

            $item = RentalItem::create($validated);

            Log::info('rental_items.store.success', [
                'business_id' => $business->id,
                'rental_item_id' => $item->id,
                'name' => $item->name,
            ]);
        } catch (\Throwable $e) {
            Log::error('rental_items.store.exception', [
                'business_id' => $business?->id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'payload' => $payloadForLog,
            ]);
            throw $e;
        }

        return redirect()->route('business.rentals.items')
            ->with('success', 'Rental item created successfully.');
    }

    /**
     * Display rental items
     */
    public function items(Request $request): View
    {
        $business = $request->user('business');
        
        $query = RentalItem::where('business_id', $business->id)
            ->with('category');

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true)->where('is_available', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'unavailable') {
                $query->where('is_available', false);
            }
        }

        $items = $query->latest()->paginate(20);
        $categories = RentalCategory::where('is_active', true)->get();

        return view('business.rentals.items', compact('items', 'categories'));
    }

    /**
     * Browse all uploaded rental items (for cloning).
     */
    public function catalog(Request $request): View
    {
        $business = $request->user('business');

        $query = RentalItem::query()
            ->whereNull('deleted_at')
            ->with(['category', 'business'])
            ->latest();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('state', 'like', "%{$q}%");
            });
        }

        $items = $query->paginate(20)->withQueryString();
        $categories = RentalCategory::where('is_active', true)->get();

        return view('business.rentals.catalog', compact('items', 'categories', 'business'));
    }

    /**
     * Show clone screen (only description editable).
     */
    public function cloneItem(Request $request, RentalItem $item): View
    {
        $business = $request->user('business');

        Log::info('rental_items.clone.view', [
            'business_id' => $business?->id,
            'source_item_id' => $item->id,
            'source_item_business_id' => $item->business_id,
        ]);

        $item->load(['category', 'business']);

        return view('business.rentals.clone-item', compact('item', 'business'));
    }

    /**
     * Store a cloned item owned by the current business.
     * Only description is editable during cloning.
     */
    public function storeClonedItem(Request $request, RentalItem $item): RedirectResponse
    {
        $business = $request->user('business');

        Log::info('rental_items.clone.start', [
            'business_id' => $business?->id,
            'source_item_id' => $item->id,
            'source_item_business_id' => $item->business_id,
        ]);

        $validated = $request->validate([
            'description' => 'nullable|string',
        ]);

        $data = $item->only([
            'category_id',
            'name',
            'city',
            'state',
            'address',
            'daily_rate',
            'weekly_rate',
            'monthly_rate',
            'currency',
            'caution_fee_enabled',
            'caution_fee_percent',
            'quantity_available',
            'specifications',
            'terms_and_conditions',
            'is_featured',
        ]);

        $data['business_id'] = $business->id;
        $data['description'] = $validated['description'] ?? $item->description;
        $data['is_active'] = true;
        $data['is_available'] = true;
        $data['is_featured'] = (bool) ($data['is_featured'] ?? false);

        // Copy images so one business can't delete another's files.
        $newImages = [];
        $sourceImages = is_array($item->images) ? $item->images : [];
        foreach ($sourceImages as $src) {
            $src = (string) $src;
            if ($src === '') {
                continue;
            }
            if (! Storage::disk('public')->exists($src)) {
                continue;
            }
            $ext = pathinfo($src, PATHINFO_EXTENSION);
            $dest = 'rental-items/' . $business->id . '/clones/' . Str::random(20) . ($ext ? ('.' . $ext) : '');
            Storage::disk('public')->copy($src, $dest);
            $newImages[] = $dest;
        }
        if (count($newImages) > 0) {
            $data['images'] = $newImages;
        }

        try {
            $cloned = RentalItem::create($data);
        } catch (\Throwable $e) {
            Log::error('rental_items.clone.exception', [
                'business_id' => $business?->id,
                'source_item_id' => $item->id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        Log::info('rental_items.clone.success', [
            'business_id' => $business?->id,
            'source_item_id' => $item->id,
            'cloned_item_id' => $cloned->id,
        ]);

        return redirect()->route('business.rentals.items.edit', $cloned)
            ->with('success', 'Item cloned. You can now edit your copy.');
    }

    /**
     * Show the form for editing a rental item
     */
    public function editItem(RentalItem $item): View
    {
        $business = request()->user('business');
        
        if ((int) $item->business_id !== (int) $business->id) {
            abort(403);
        }

        $categories = RentalCategory::where('is_active', true)->get();
        return view('business.rentals.edit-item', compact('item', 'categories'));
    }

    /**
     * Update a rental item
     */
    public function updateItem(Request $request, RentalItem $item): RedirectResponse
    {
        $business = $request->user('business');
        
        if ((int) $item->business_id !== (int) $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:rental_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'daily_rate' => 'required|numeric|min:0',
            'weekly_rate' => 'nullable|numeric|min:0',
            'monthly_rate' => 'nullable|numeric|min:0',
            'caution_fee_enabled' => 'sometimes|boolean',
            'caution_fee_percent' => 'nullable|numeric|min:0|max:100',
            'quantity_available' => 'required|integer|min:1',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
            'specifications' => 'nullable|array',
            'is_featured' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'is_available' => 'sometimes|boolean',
            'remove_images' => 'nullable|array',
        ]);
        $validated['caution_fee_enabled'] = (bool) ($validated['caution_fee_enabled'] ?? false);
        if (array_key_exists('is_featured', $validated)) {
            $validated['is_featured'] = (bool) $validated['is_featured'];
        }
        $validated['caution_fee_percent'] = $validated['caution_fee_enabled']
            ? (float) ($validated['caution_fee_percent'] ?? 0)
            : 0;

        // Handle image removal
        if ($request->filled('remove_images')) {
            $currentImages = $item->images ?? [];
            foreach ($request->remove_images as $imageToRemove) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($imageToRemove)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($imageToRemove);
                }
                $currentImages = array_filter($currentImages, function($img) use ($imageToRemove) {
                    return $img !== $imageToRemove;
                });
            }
            $validated['images'] = array_values($currentImages);
        }

        // Handle new image uploads
        if ($request->hasFile('images')) {
            $imagePaths = $item->images ?? [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('rental-items', 'public');
                $imagePaths[] = $path;
            }
            $validated['images'] = $imagePaths;
        }

        $item->update($validated);

        return redirect()->route('business.rentals.items')
            ->with('success', 'Rental item updated successfully.');
    }

    /**
     * Quick-update daily rate (inline edit from items list)
     */
    public function updateDailyRate(Request $request, RentalItem $item): JsonResponse
    {
        $business = $request->user('business');
        if ((int) $item->business_id !== (int) $business->id) {
            abort(403);
        }
        $validated = $request->validate([
            'daily_rate' => 'required|numeric|min:0',
        ]);
        $item->update(['daily_rate' => $validated['daily_rate']]);
        return response()->json([
            'success' => true,
            'daily_rate' => (float) $item->daily_rate,
            'formatted' => '₦' . number_format($item->daily_rate, 2),
        ]);
    }

    /**
     * Add a photo to a rental item (quick upload from items list)
     */
    public function addItemPhoto(Request $request, RentalItem $item): JsonResponse
    {
        $business = $request->user('business');
        if ((int) $item->business_id !== (int) $business->id) {
            abort(403);
        }
        $request->validate([
            'photo' => 'required|image|max:2048',
        ]);
        $path = $request->file('photo')->store('rental-items', 'public');
        $images = $item->images ?? [];
        $images[] = $path;
        $item->update(['images' => $images]);
        return response()->json([
            'success' => true,
            'image_path' => $path,
            'image_url' => asset('storage/' . $path),
        ]);
    }

    /**
     * Delete a rental item
     */
    public function deleteItem(RentalItem $item): RedirectResponse
    {
        $business = request()->user('business');
        
        if ((int) $item->business_id !== (int) $business->id) {
            abort(403);
        }

        $item->delete();

        return redirect()->route('business.rentals.items')
            ->with('success', 'Rental item deleted successfully.');
    }

    /**
     * Update rental status
     */
    public function updateStatus(Request $request, Rental $rental): RedirectResponse
    {
        $business = $request->user('business');
        
        if ((int) $rental->business_id !== (int) $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,active,completed,cancelled,rejected',
        ]);

        $wasApproved = $rental->isApproved();
        $rental->update(['status' => $validated['status']]);

        if ($validated['status'] === 'approved' && !$wasApproved) {
            try {
                $paymentService = app(RentalPaymentService::class);
                $paymentService->createPaymentForRental($rental->fresh());
                Mail::to($rental->renter_email)->send(new RentalApprovedPayNow($rental->fresh()));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to create rental payment on status update', [
                    'rental_id' => $rental->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($validated['status'] === 'active' && !$rental->started_at) {
            $rental->update(['started_at' => now()]);
        }

        if ($validated['status'] === 'completed') {
            $rental->markAsReturned();
        }

        if ($validated['status'] === 'cancelled' && !$rental->cancelled_at) {
            $rental->update(['cancelled_at' => now()]);
        }

        return redirect()->route('business.rentals.show', $rental)
            ->with('success', 'Rental status updated successfully.');
    }

    /**
     * Show a specific rental request
     */
    public function show(Rental $rental): View
    {
        $business = request()->user('business');
        
        if ((int) $rental->business_id !== (int) $business->id) {
            abort(403);
        }

        $rental->load(['renter', 'items', 'business']);

        return view('business.rentals.show', compact('rental'));
    }

    public function markPickedUp(Request $request, Rental $rental): RedirectResponse
    {
        $business = $request->user('business');
        if ((int) $rental->business_id !== (int) $business->id) {
            abort(403);
        }

        if (! in_array($rental->status, [Rental::STATUS_APPROVED, Rental::STATUS_ACTIVE], true)) {
            return redirect()->route('business.rentals.show', $rental)
                ->with('error', 'Rental must be approved before it can be marked as picked up.');
        }

        if (! $rental->started_at || $rental->status !== Rental::STATUS_ACTIVE) {
            $rental->update([
                'status' => Rental::STATUS_ACTIVE,
                'started_at' => $rental->started_at ?? now(),
            ]);
        }

        return redirect()->route('business.rentals.show', $rental)
            ->with('success', 'Pickup confirmed. Rental is now active.');
    }

    public function confirmReturn(Request $request, Rental $rental): RedirectResponse
    {
        $business = $request->user('business');
        if ((int) $rental->business_id !== (int) $business->id) {
            abort(403);
        }

        if (! in_array($rental->status, [Rental::STATUS_ACTIVE, Rental::STATUS_APPROVED, Rental::STATUS_COMPLETED], true)) {
            return redirect()->route('business.rentals.show', $rental)
                ->with('error', 'Rental must be active (or approved) to confirm return.');
        }

        if (! $rental->business_return_confirmed_at) {
            $rental->update(['business_return_confirmed_at' => now()]);
        }

        $this->maybeFinalizeReturn($rental);

        return redirect()->route('business.rentals.show', $rental)
            ->with('success', $rental->fresh()->returned_at ? 'Return completed.' : 'Return confirmed by business. Awaiting renter confirmation.');
    }

    /**
     * Approve a rental request (creates payment; renter pays via link in email)
     */
    public function approve(Request $request, Rental $rental): RedirectResponse
    {
        $business = $request->user('business');

        if ((int) $rental->business_id !== (int) $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'business_notes' => 'nullable|string|max:1000',
        ]);

        $rental->approve($validated['business_notes'] ?? null);

        try {
            $paymentService = app(RentalPaymentService::class);
            $paymentService->createPaymentForRental($rental->fresh());
            Mail::to($rental->renter_email)->send(new RentalApprovedPayNow($rental->fresh()));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create rental payment or send email', [
                'rental_id' => $rental->id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('business.rentals.show', $rental)
                ->with('error', 'Rental approved but payment link could not be created. Please try again or contact support.');
        }

        return redirect()->route('business.rentals.show', $rental)
            ->with('success', 'Rental approved. The renter has been sent a payment link.');
    }

    /**
     * Reject a rental request
     */
    public function reject(Request $request, Rental $rental): RedirectResponse
    {
        $business = $request->user('business');
        
        if ($rental->business_id !== $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'business_notes' => 'required|string|max:1000',
        ]);

        $rental->reject($validated['business_notes']);

        return redirect()->route('business.rentals.show', $rental)
            ->with('success', 'Rental request rejected.');
    }
}
