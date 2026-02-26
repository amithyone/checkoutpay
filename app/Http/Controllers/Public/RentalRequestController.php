<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Renter;
use App\Services\NubanValidationService;
use App\Services\RecaptchaService;
use App\Mail\RentalRequestReceived;
use App\Mail\RentalReceipt;
use App\Mail\RentalApprovedPayNow;
use App\Services\RentalPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class RentalRequestController extends Controller
{
    public function __construct(
        protected NubanValidationService $nubanService,
        protected RecaptchaService $recaptcha
    ) {}

    /**
     * Show checkout/rental request page
     */
    public function checkout(Request $request): View|RedirectResponse
    {
        $cart = session('rental_cart', []);
        
        if (empty($cart)) {
            return redirect()->route('rentals.index')
                ->with('error', 'Your cart is empty.');
        }

        // Allow clearing dates to choose a new period
        if ($request->boolean('change_dates')) {
            foreach (array_keys($cart) as $itemId) {
                $cart[$itemId] = ['quantity' => $cart[$itemId]['quantity'] ?? 1];
            }
            session([
                'rental_cart' => $cart,
                'rental_dates_conflict_ids' => [],
            ]);
            return redirect()->route('rentals.checkout');
        }
        // Normalize legacy cart: if start/end but no selected_dates, set selected_dates from range
        foreach ($cart as $itemId => $data) {
            if (empty($data['selected_dates']) && !empty($data['start_date']) && !empty($data['end_date'])) {
                $s = \Carbon\Carbon::parse($data['start_date']);
                $e = \Carbon\Carbon::parse($data['end_date']);
                $cart[$itemId]['selected_dates'] = [];
                for ($d = $s->copy(); $d->lte($e); $d->addDay()) {
                    $cart[$itemId]['selected_dates'][] = $d->format('Y-m-d');
                }
            }
        }
        session(['rental_cart' => $cart]);

        // Load items from cart; dates may be set on cart page (selected_dates or start/end)
        $items = RentalItem::whereIn('id', array_keys($cart))
            ->with(['business', 'category'])
            ->get()
            ->map(function ($item) use ($cart) {
                $item->cart_quantity = $cart[$item->id]['quantity'] ?? 1;
                $item->cart_selected_dates = $cart[$item->id]['selected_dates'] ?? null;
                $item->cart_start_date = $cart[$item->id]['start_date'] ?? null;
                $item->cart_end_date = $cart[$item->id]['end_date'] ?? null;
                return $item;
            });

        $datesConflictItemIds = session('rental_dates_conflict_ids', []);
        $hasDates = $items->every(function ($item) {
            $dates = $item->cart_selected_dates ?? [];
            if (!empty($dates)) {
                return true;
            }
            return $item->cart_start_date && $item->cart_end_date;
        }) && empty($datesConflictItemIds);

        // Logged-in renter, or logged-in user (web): show "Your information" with prefilled email, no password, KYC only if needed
        $isRenter = Auth::guard('renter')->check();
        $renter = $isRenter ? Auth::guard('renter')->user() : null;

        if (!$renter && Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            $renter = Renter::firstOrCreate(
                ['email' => strtolower($user->email)],
                [
                    'name' => $user->name ?? $user->email,
                    'email_verified_at' => $user->email_verified_at ?? null,
                    'password' => Hash::make(Str::random(32)),
                ]
            );
            $isRenter = true;
        }

        $needsKyc = $renter && !$renter->isKycVerified();

        $cartItemIds = $items->pluck('id')->all();

        return view('rentals.checkout', compact('items', 'hasDates', 'isRenter', 'renter', 'needsKyc', 'cartItemIds', 'datesConflictItemIds'));
    }

    /**
     * JSON: unavailable dates for cart in a given month (for calendar).
     * ?month=YYYY-MM&item_id=123 = unavailable for that item only (for per-item date selection).
     * Without item_id = union of all cart items (backward compatibility).
     */
    public function unavailableDates(Request $request): \Illuminate\Http\JsonResponse
    {
        $cart = session('rental_cart', []);
        $itemIds = array_keys($cart);
        if (empty($itemIds)) {
            return response()->json(['unavailable' => []]);
        }

        $itemId = $request->get('item_id');
        if ($itemId !== null) {
            $itemId = (int) $itemId;
            if (!isset($cart[$itemId])) {
                return response()->json(['unavailable' => []]);
            }
            $itemIds = [$itemId];
        }

        $month = $request->get('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::parse($month . '-01')->startOfDay();
        $end = $start->copy()->endOfMonth();

        $items = RentalItem::whereIn('id', $itemIds)->get();
        $unavailableSet = [];
        foreach ($items as $item) {
            foreach ($item->getUnavailableDatesInRange($start, $end) as $d) {
                $unavailableSet[$d] = true;
            }
        }
        $unavailable = array_keys($unavailableSet);
        sort($unavailable);

        return response()->json(['unavailable' => $unavailable]);
    }

    /**
     * AJAX KYC verification endpoint (public, for checkout form)
     */
    public function verifyKycAjax(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'account_number' => 'required|string|size:10',
            'bank_code' => 'required|string',
        ]);

        $result = $this->nubanService->validate($validated['account_number'], $validated['bank_code']);

        if (!$result || !isset($result['account_name'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify account. Please check your account number and bank.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'account_name' => $result['account_name'],
            'bank_name' => $result['bank_name'] ?? null,
        ]);
    }

    /**
     * Step 1: Create account (email/password + KYC)
     */
    public function createAccount(Request $request): RedirectResponse
    {
        if ($this->recaptcha->isEnabled()) {
            $request->validate([
                'g-recaptcha-response' => 'required',
            ], [
                'g-recaptcha-response.required' => 'Please complete the reCAPTCHA verification.',
            ]);

            if (!$this->recaptcha->verify($request->input('g-recaptcha-response'), $request->ip())) {
                return back()->withErrors([
                    'g-recaptcha-response' => 'reCAPTCHA verification failed. Please try again.',
                ])->withInput($request->except('password', 'password_confirmation'));
            }
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:renters,email',
            'password' => 'required|string|min:8|confirmed',
            'account_number' => 'required|string|size:10',
            'bank_code' => 'required|string',
            'id_card' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        // Verify account number and get account name
        $kycResult = $this->nubanService->validate($validated['account_number'], $validated['bank_code']);
        
        if (!$kycResult || !isset($kycResult['account_name'])) {
            return back()->withErrors([
                'account_number' => 'Unable to verify account. Please check your account number and bank.',
            ])->withInput($request->except('password', 'password_confirmation'));
        }

        // Use verified account name as the renter's name
        $accountName = $kycResult['account_name'];

        // Create renter account with KYC data (id_card stored after create so we have id)
        $renter = Renter::create([
            'name' => $accountName, // Use verified account name
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'verified_account_number' => $validated['account_number'],
            'verified_account_name' => $accountName,
            'verified_bank_name' => $kycResult['bank_name'] ?? null,
            'verified_bank_code' => $kycResult['bank_code'] ?? $validated['bank_code'],
            'kyc_verified_at' => now(), // Mark KYC as verified
        ]);

        $idCardPath = $this->storeKycIdCard($request->file('id_card'), $renter->id);
        $renter->update(['kyc_id_card_path' => $idCardPath]);

        // Send email verification
        $renter->sendEmailVerificationNotification();

        // Log in renter
        Auth::guard('renter')->login($renter);

        return redirect()->route('rentals.verify-email')
            ->with('success', 'Account created! Please verify your email to continue.');
    }

    /**
     * Logged-in renter or web user: update info/KYC and continue to next step.
     * If web user, find or create Renter by email then log in as renter for the rest of the flow.
     */
    public function checkoutContinue(Request $request): RedirectResponse
    {
        $renter = Auth::guard('renter')->user();

        if (!$renter && Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            $renter = Renter::firstOrCreate(
                ['email' => strtolower($user->email)],
                [
                    'name' => $user->name ?? $user->email,
                    'email_verified_at' => $user->email_verified_at ?? null,
                    'password' => Hash::make(Str::random(32)),
                ]
            );
            Auth::guard('renter')->login($renter);
        }

        if (!$renter) {
            return redirect()->route('rentals.checkout');
        }

        $rules = [
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
        ];
        if ($renter->isKycVerified()) {
            // No KYC fields required
        } else {
            $rules['account_number'] = 'required|string|size:10';
            $rules['bank_code'] = 'required|string';
            $rules['id_card'] = 'required|file|mimes:jpeg,jpg,png,pdf|max:5120';
        }
        $validated = $request->validate($rules);

        $renter->update([
            'phone' => $validated['phone'] ?? $renter->phone,
            'address' => $validated['address'] ?? $renter->address,
        ]);

        if (!$renter->isKycVerified() && !empty($validated['account_number'])) {
            $result = $this->nubanService->validate($validated['account_number'], $validated['bank_code']);
            if ($result && isset($result['account_name'])) {
                $updateData = [
                    'verified_account_number' => $validated['account_number'],
                    'verified_account_name' => $result['account_name'],
                    'verified_bank_name' => $result['bank_name'] ?? null,
                    'verified_bank_code' => $validated['bank_code'],
                    'kyc_verified_at' => now(),
                    'name' => $result['account_name'],
                ];
                if ($request->hasFile('id_card')) {
                    $updateData['kyc_id_card_path'] = $this->storeKycIdCard($request->file('id_card'), $renter->id);
                }
                $renter->update($updateData);
                $renter->refresh();
            }
        }

        if (!$renter->hasVerifiedEmail()) {
            return redirect()->route('rentals.verify-email');
        }
        if (!$renter->isKycVerified()) {
            return redirect()->route('rentals.kyc');
        }
        return redirect()->route('rentals.review');
    }

    /**
     * Verify email via link
     */
    public function verify(Request $request): RedirectResponse
    {
        $renter = \App\Models\Renter::findOrFail($request->route('id'));

        if (!hash_equals((string) $request->route('hash'), sha1($renter->getEmailForVerification()))) {
            return redirect()->route('rentals.verify-email')
                ->withErrors(['email' => 'Invalid verification link.']);
        }

        if ($renter->hasVerifiedEmail()) {
            Auth::guard('renter')->login($renter);
            return redirect()->route('rentals.kyc')
                ->with('success', 'Your email has already been verified.');
        }

        if ($renter->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($renter));
        }

        Auth::guard('renter')->login($renter);

        return redirect()->route('rentals.kyc')
            ->with('success', 'Your email has been verified! Please complete KYC verification.');
    }

    /**
     * Resend verification email
     */
    public function resendVerification(Request $request): RedirectResponse
    {
        $renter = Auth::guard('renter')->user() ?? \App\Models\Renter::where('email', $request->email)->first();

        if (!$renter) {
            return back()->withErrors(['email' => 'Email address not found.']);
        }

        if ($renter->hasVerifiedEmail()) {
            return redirect()->route('rentals.kyc')
                ->with('info', 'Your email is already verified.');
        }

        $renter->sendEmailVerificationNotification();

        return back()->with('status', 'Verification email sent! Please check your inbox.');
    }

    /**
     * Verify email via PIN
     */
    public function verifyPin(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'pin' => 'required|digits:6',
        ]);

        $renter = \App\Models\Renter::where('email', $request->email)->first();

        if (!$renter) {
            return back()->withErrors(['email' => 'Email address not found.']);
        }

        if ($renter->hasVerifiedEmail()) {
            Auth::guard('renter')->login($renter);
            return redirect()->route('rentals.kyc')
                ->with('success', 'Your email has already been verified.');
        }

        // Verify PIN from cache
        $cachedPin = \Illuminate\Support\Facades\Cache::get('renter_email_verification_pin_' . $renter->id);

        if (!$cachedPin || $cachedPin !== $request->pin) {
            return back()->withErrors(['pin' => 'Invalid or expired verification PIN.']);
        }

        // Mark email as verified
        if ($renter->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($renter));
        }

        // Clear PIN from cache
        \Illuminate\Support\Facades\Cache::forget('renter_email_verification_pin_' . $renter->id);

        // Auto-login
        Auth::guard('renter')->login($renter);

        return redirect()->route('rentals.kyc')
            ->with('success', 'Your email has been verified! Please complete KYC verification.');
    }

    /**
     * Show email verification page
     */
    public function verifyEmail(): View
    {
        $renter = Auth::guard('renter')->user();
        
        if ($renter->hasVerifiedEmail()) {
            return redirect()->route('rentals.kyc');
        }

        return view('rentals.verify-email', compact('renter'));
    }

    /**
     * Step 2: KYC Verification (Account Number + Bank)
     */
    public function kyc(Request $request): View|RedirectResponse
    {
        $renter = Auth::guard('renter')->user();

        if (!$renter->hasVerifiedEmail()) {
            return redirect()->route('rentals.verify-email')
                ->with('error', 'Please verify your email first.');
        }

        // If already KYC verified, skip to review
        if ($renter->isKycVerified()) {
            return redirect()->route('rentals.review');
        }

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'account_number' => 'required|string|size:10',
                'bank_code' => 'required|string',
                'id_card' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120',
            ]);

            // Validate account number
            $result = $this->nubanService->validate($validated['account_number'], $validated['bank_code']);

            if (!$result || !isset($result['account_name'])) {
                return back()->withInput()->with('error', 'Invalid account number. Please verify and try again.');
            }

            $idCardPath = $this->storeKycIdCard($request->file('id_card'), $renter->id);

            // Update renter with verified account info and ID card
            $renter->update([
                'verified_account_number' => $validated['account_number'],
                'verified_account_name' => $result['account_name'],
                'verified_bank_name' => $result['bank_name'] ?? null,
                'verified_bank_code' => $result['bank_code'] ?? $validated['bank_code'],
                'kyc_verified_at' => now(),
                'kyc_id_card_path' => $idCardPath,
                'name' => $result['account_name'],
            ]);

            return redirect()->route('rentals.review')
                ->with('success', 'Account verified successfully!');
        }

        return view('rentals.kyc', compact('renter'));
    }

    /**
     * Step 3: Review and submit rental request
     */
    public function review(Request $request): View|RedirectResponse
    {
        $renter = Auth::guard('renter')->user();

        if (!$renter->hasVerifiedEmail()) {
            return redirect()->route('rentals.verify-email');
        }

        if (!$renter->isKycVerified()) {
            return redirect()->route('rentals.kyc');
        }

        $cart = session('rental_cart', []);
        
        if (empty($cart)) {
            return redirect()->route('rentals.index')
                ->with('error', 'Your cart is empty.');
        }

        // Load items
        $items = RentalItem::whereIn('id', array_keys($cart))
            ->with(['business', 'category'])
            ->get();

        // Calculate totals
        $totalAmount = 0;
        $businesses = [];
        
        foreach ($items as $item) {
            $cartData = $cart[$item->id];
            $selectedDates = $cartData['selected_dates'] ?? null;
            if (!empty($selectedDates)) {
                $days = count($selectedDates);
                $startDate = \Carbon\Carbon::parse($selectedDates[0]);
                $endDate = \Carbon\Carbon::parse($selectedDates[array_key_last($selectedDates)]);
            } else {
                $startDate = \Carbon\Carbon::parse($cartData['start_date']);
                $endDate = \Carbon\Carbon::parse($cartData['end_date']);
                $days = $startDate->diffInDays($endDate) + 1;
            }
            
            $rate = $item->getRateForPeriod($days);
            $itemTotal = $rate * ($cartData['quantity'] ?? 1);
            $totalAmount += $itemTotal;
            
            if (!isset($businesses[$item->business_id])) {
                $businesses[$item->business_id] = [
                    'business' => $item->business,
                    'items' => [],
                    'total' => 0,
                ];
            }
            
            $businesses[$item->business_id]['items'][] = [
                'item' => $item,
                'quantity' => $cartData['quantity'] ?? 1,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $days,
                'rate' => $rate,
                'total' => $itemTotal,
            ];
            $businesses[$item->business_id]['total'] += $itemTotal;
        }

        if ($request->isMethod('post')) {
            return $this->submitRental($request, $renter, $items, $cart, $businesses);
        }

        return view('rentals.review', compact('items', 'cart', 'totalAmount', 'businesses', 'renter'));
    }

    /**
     * Submit rental request
     */
    protected function submitRental(Request $request, Renter $renter, $items, $cart, $businesses): RedirectResponse
    {
        $validated = $request->validate([
            'renter_notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Create rental for each business
            foreach ($businesses as $businessData) {
                $business = $businessData['business'];
                $businessItems = $businessData['items'];
                
                // Calculate business total
                $businessTotal = 0;
                $totalDays = 0;
                foreach ($businessItems as $itemData) {
                    $businessTotal += $itemData['total'];
                    $totalDays = max($totalDays, $itemData['days']);
                }

                // Create rental
                $rental = Rental::create([
                    'renter_id' => $renter->id,
                    'business_id' => $business->id,
                    'start_date' => $businessItems[0]['start_date'],
                    'end_date' => $businessItems[0]['end_date'],
                    'days' => $totalDays,
                    'daily_rate' => $businessTotal / $totalDays,
                    'total_amount' => $businessTotal,
                    'deposit_amount' => 0, // Can be configured later
                    'currency' => 'NGN',
                    'status' => Rental::STATUS_PENDING,
                    'verified_account_number' => $renter->verified_account_number,
                    'verified_account_name' => $renter->verified_account_name,
                    'verified_bank_name' => $renter->verified_bank_name,
                    'verified_bank_code' => $renter->verified_bank_code,
                    'renter_name' => $renter->name,
                    'renter_email' => $renter->email,
                    'renter_phone' => $renter->phone,
                    'renter_address' => $renter->address,
                    'business_phone' => $business->phone,
                    'renter_notes' => $validated['renter_notes'] ?? null,
                ]);

                // Attach items to rental
                foreach ($businessItems as $itemData) {
                    $rental->items()->attach($itemData['item']->id, [
                        'quantity' => $itemData['quantity'],
                        'unit_rate' => $itemData['rate'],
                        'total_amount' => $itemData['total'],
                    ]);
                }

                // Send email to business
                Mail::to($business->email)->send(new RentalRequestReceived($rental));

                // Send receipt to renter
                Mail::to($renter->email)->send(new RentalReceipt($rental));

                // Auto-approve and send payment link if business has rental_auto_approve
                if ($business->rental_auto_approve ?? false) {
                    $rental->approve();
                    try {
                        $paymentService = app(RentalPaymentService::class);
                        $paymentService->createPaymentForRental($rental->fresh());
                        Mail::to($rental->renter_email)->send(new RentalApprovedPayNow($rental->fresh()));
                    } catch (\Exception $e) {
                        Log::error('Rental auto-approve: failed to create payment or send pay link', [
                            'rental_id' => $rental->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Clear cart
            session()->forget('rental_cart');

            $autoApproved = false;
            foreach ($businesses as $businessData) {
                if ($businessData['business']->rental_auto_approve ?? false) {
                    $autoApproved = true;
                    break;
                }
            }

            return redirect()->route('rentals.success')
                ->with('success', $autoApproved
                    ? 'Rental request submitted and approved! Check your email for the payment link.'
                    : 'Rental request submitted successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to submit rental request', [
                'renter_id' => $renter->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Failed to submit rental request: ' . $e->getMessage());
        }
    }

    /**
     * Success page
     */
    public function success(): View
    {
        return view('rentals.success');
    }

    /**
     * Add item to cart (quantity only; dates are set on checkout/cart page)
     */
    public function addToCart(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:rental_items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = session('rental_cart', []);
        $cart[$validated['item_id']] = [
            'quantity' => $validated['quantity'],
        ];
        session(['rental_cart' => $cart]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Item added to cart! Select your rental dates on the cart page.',
                'cart_count' => count($cart),
            ]);
        }

        return back()->with('success', 'Item added to cart! Select your rental dates on the cart page.');
    }

    /**
     * Set rental dates: either for one item (item_id + selected_dates) or apply to all (apply_to_all + selected_dates).
     * When applying to all, items that are not available for those dates get a red indicator and must be fixed.
     */
    public function updateCartDates(Request $request): RedirectResponse
    {
        $applyToAll = $request->boolean('apply_to_all');
        $selectedInput = array_values(array_filter((array) $request->input('selected_dates', [])));

        if ($applyToAll) {
            return $this->updateCartDatesApplyToAll($request, $selectedInput);
        }

        $request->merge([
            'item_id' => $request->input('item_id'),
            'selected_dates' => $selectedInput,
        ]);
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:rental_items,id',
            'selected_dates' => 'required|array',
            'selected_dates.*' => 'required|date|after_or_equal:today',
        ]);

        $itemId = (int) $validated['item_id'];
        $selected = array_values(array_unique($validated['selected_dates']));
        sort($selected);
        if (empty($selected)) {
            return redirect()->route('rentals.checkout')->with('error', 'Please select at least one date for this item.');
        }

        $cart = session('rental_cart', []);
        if (empty($cart) || !isset($cart[$itemId])) {
            return redirect()->route('rentals.checkout')->with('error', 'Item not in cart.');
        }

        $item = RentalItem::find($itemId);
        foreach ($selected as $dateStr) {
            if (!$item->isAvailableForDates($dateStr, $dateStr)) {
                return redirect()->route('rentals.checkout')
                    ->with('error', $dateStr . ' is not available for ' . $item->name . '. Please update your selection.');
            }
        }

        $startStr = $selected[0];
        $endStr = $selected[array_key_last($selected)];

        $cart[$itemId]['selected_dates'] = $selected;
        $cart[$itemId]['start_date'] = $startStr;
        $cart[$itemId]['end_date'] = $endStr;
        $cart[$itemId]['quantity'] = $cart[$itemId]['quantity'] ?? 1;
        session(['rental_cart' => $cart]);

        // Clear this item from conflict list when user fixes dates
        $conflictIds = array_values(array_diff(session('rental_dates_conflict_ids', []), [$itemId]));
        session(['rental_dates_conflict_ids' => $conflictIds]);

        return redirect()->route('rentals.checkout')->with('success', 'Dates saved for ' . $item->name . ' (' . count($selected) . ' day(s)).');
    }

    /**
     * Apply the same selected_dates to all cart items. Items not available get added to conflict list.
     */
    protected function updateCartDatesApplyToAll(Request $request, array $selectedInput): RedirectResponse
    {
        $validated = $request->validate([
            'selected_dates' => 'required|array',
            'selected_dates.*' => 'required|date|after_or_equal:today',
        ]);
        $selected = array_values(array_unique($validated['selected_dates']));
        sort($selected);
        if (empty($selected)) {
            return redirect()->route('rentals.checkout')->with('error', 'Please select at least one date.');
        }

        $cart = session('rental_cart', []);
        if (empty($cart)) {
            return redirect()->route('rentals.checkout')->with('error', 'Your cart is empty.');
        }

        $startStr = $selected[0];
        $endStr = $selected[array_key_last($selected)];
        $conflictIds = [];
        $items = RentalItem::whereIn('id', array_keys($cart))->get();

        foreach ($items as $item) {
            $allAvailable = true;
            foreach ($selected as $dateStr) {
                if (!$item->isAvailableForDates($dateStr, $dateStr)) {
                    $allAvailable = false;
                    break;
                }
            }
            if ($allAvailable) {
                $cart[$item->id]['selected_dates'] = $selected;
                $cart[$item->id]['start_date'] = $startStr;
                $cart[$item->id]['end_date'] = $endStr;
                $cart[$item->id]['quantity'] = $cart[$item->id]['quantity'] ?? 1;
            } else {
                $conflictIds[] = $item->id;
                // Clear dates for this item so user must set them
                $cart[$item->id] = ['quantity' => $cart[$item->id]['quantity'] ?? 1];
            }
        }

        session([
            'rental_cart' => $cart,
            'rental_dates_conflict_ids' => $conflictIds,
        ]);

        if (!empty($conflictIds)) {
            $count = count($conflictIds);
            return redirect()->route('rentals.checkout')
                ->with('warning', 'Dates applied to some items. ' . $count . ' item(s) are not available for those dates – click each red item to set different dates.');
        }

        return redirect()->route('rentals.checkout')->with('success', 'Same dates applied to all items (' . count($selected) . ' day(s)).');
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart(Request $request, int $itemId): RedirectResponse
    {
        $cart = session('rental_cart', []);
        unset($cart[$itemId]);
        session(['rental_cart' => $cart]);

        $conflictIds = array_values(array_diff(session('rental_dates_conflict_ids', []), [$itemId]));
        session(['rental_dates_conflict_ids' => $conflictIds]);

        return back()->with('success', 'Item removed from cart.');
    }

    /**
     * Store KYC ID card file and return the path.
     */
    protected function storeKycIdCard(\Illuminate\Http\UploadedFile $file, ?int $renterId = null): string
    {
        $prefix = $renterId ? "renters/{$renterId}" : 'renters';
        $path = $file->store("rentals/kyc/{$prefix}", 'local');
        return $path;
    }
}
