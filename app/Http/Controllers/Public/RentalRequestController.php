<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Renter;
use App\Services\NubanValidationService;
use App\Mail\RentalRequestReceived;
use App\Mail\RentalReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class RentalRequestController extends Controller
{
    public function __construct(
        protected NubanValidationService $nubanService
    ) {}

    /**
     * Show checkout/rental request page
     */
    public function checkout(Request $request): View
    {
        $cart = session('rental_cart', []);
        
        if (empty($cart)) {
            return redirect()->route('rentals.index')
                ->with('error', 'Your cart is empty.');
        }

        // Load items from cart
        $items = RentalItem::whereIn('id', array_keys($cart))
            ->with(['business', 'category'])
            ->get()
            ->map(function ($item) use ($cart) {
                $item->cart_quantity = $cart[$item->id]['quantity'] ?? 1;
                $item->cart_start_date = $cart[$item->id]['start_date'] ?? null;
                $item->cart_end_date = $cart[$item->id]['end_date'] ?? null;
                return $item;
            });

        return view('rentals.checkout', compact('items'));
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
        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:renters,email',
            'password' => 'required|string|min:8|confirmed',
            'account_number' => 'required|string|size:10',
            'bank_code' => 'required|string',
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

        // Create renter account with KYC data
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

        // Send email verification
        $renter->sendEmailVerificationNotification();

        // Log in renter
        Auth::guard('renter')->login($renter);

        return redirect()->route('rentals.verify-email')
            ->with('success', 'Account created! Please verify your email to continue.');
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
            ]);

            // Validate account number
            $result = $this->nubanService->validate($validated['account_number'], $validated['bank_code']);

            if (!$result || !isset($result['account_name'])) {
                return back()->withInput()->with('error', 'Invalid account number. Please verify and try again.');
            }

            // Update renter with verified account info
            $renter->update([
                'verified_account_number' => $validated['account_number'],
                'verified_account_name' => $result['account_name'],
                'verified_bank_name' => $result['bank_name'] ?? null,
                'verified_bank_code' => $result['bank_code'] ?? $validated['bank_code'],
                'kyc_verified_at' => now(),
            ]);

            // Update name to match account name
            $renter->update(['name' => $result['account_name']]);

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
            $startDate = \Carbon\Carbon::parse($cartData['start_date']);
            $endDate = \Carbon\Carbon::parse($cartData['end_date']);
            $days = $startDate->diffInDays($endDate) + 1;
            
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
            }

            // Clear cart
            session()->forget('rental_cart');

            return redirect()->route('rentals.success')
                ->with('success', 'Rental request submitted successfully!');
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
     * Add item to cart
     */
    public function addToCart(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:rental_items,id',
            'quantity' => 'required|integer|min:1',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $item = RentalItem::findOrFail($validated['item_id']);

        // Check availability
        if (!$item->isAvailableForDates($validated['start_date'], $validated['end_date'])) {
            return back()->with('error', 'Item is not available for the selected dates.');
        }

        // Add to cart
        $cart = session('rental_cart', []);
        $cart[$validated['item_id']] = [
            'quantity' => $validated['quantity'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ];
        session(['rental_cart' => $cart]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Item added to cart!',
                'cart_count' => count($cart),
            ]);
        }

        return back()->with('success', 'Item added to cart!');
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart(Request $request, int $itemId): RedirectResponse
    {
        $cart = session('rental_cart', []);
        unset($cart[$itemId]);
        session(['rental_cart' => $cart]);

        return back()->with('success', 'Item removed from cart.');
    }
}
