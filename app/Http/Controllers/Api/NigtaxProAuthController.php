<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\NigtaxProPendingRegistration;
use App\Models\NigtaxProUser;
use App\Models\Payment;
use App\Mail\MembershipPaymentInstructionsMail;
use App\Services\NigtaxProSubscriptionService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class NigtaxProAuthController extends Controller
{
    public function __construct(
        protected NigtaxProSubscriptionService $proSubscription
    ) {}

    /**
     * Public: membership slug, subscribe URL, and price for the Taxcalculate UI.
     */
    public function config(): JsonResponse
    {
        $membership = $this->proSubscription->findMembership();
        $slug = $this->proSubscription->membershipSlug();

        return response()->json([
            'membership_slug' => $slug,
            'subscribe_url' => url('/memberships/'.$slug.'/payment'),
            'price' => $membership ? (float) $membership->price : 2000.0,
            'currency' => $membership?->currency ?? 'NGN',
            'duration_label' => $membership ? $membership->formatted_duration : '1 Month',
            'name' => $membership?->name ?? 'NigTax PRO',
        ]);
    }

    /**
     * Create a PRO login account. Requires an active membership subscription for the same email
     * (after successful ₦2,000/month payment via the standard membership checkout).
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = $this->proSubscription->normalizeEmail($validated['email']);

        if (! $this->proSubscription->hasActiveSubscription($email)) {
            throw ValidationException::withMessages([
                'email' => ['No active NigTax PRO subscription for this email. Pay for PRO first using this same email, then create your account here.'],
            ]);
        }

        if (NigtaxProUser::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account already exists for this email. Sign in instead.'],
            ]);
        }

        $user = NigtaxProUser::query()->create([
            'email' => $email,
            'password' => $validated['password'],
        ]);

        $token = $user->createToken('nigtax-pro')->plainTextToken;
        $sub = $this->proSubscription->activeSubscriptionForEmail($email);

        return response()->json([
            'token' => $token,
            'user' => ['email' => $user->email],
            'pro_active' => true,
            'subscription_expires_at' => $sub?->expires_at?->toIso8601String(),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $this->proSubscription->normalizeEmail($validated['email']);

        $user = NigtaxProUser::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $this->proSubscription->hasActiveSubscription($user->email)) {
            return response()->json([
                'message' => 'Your NigTax PRO subscription is not active. Renew at checkout using the same email, then sign in again.',
                'subscription_required' => true,
            ], 403);
        }

        $token = $user->createToken('nigtax-pro')->plainTextToken;
        $sub = $this->proSubscription->activeSubscriptionForEmail($user->email);

        return response()->json([
            'token' => $token,
            'user' => ['email' => $user->email],
            'pro_active' => true,
            'subscription_expires_at' => $sub?->expires_at?->toIso8601String(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof NigtaxProUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $sub = $this->proSubscription->activeSubscriptionForEmail($user->email);
        $active = $sub !== null;

        return response()->json([
            'email' => $user->email,
            'pro_active' => $active,
            'subscription_expires_at' => $sub?->expires_at?->toIso8601String(),
            'subscription_number' => $sub?->subscription_number,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof NigtaxProUser) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Start NigTax PRO membership payment from NigTax (virtual account + pending password).
     */
    public function startMembershipCheckout(Request $request, PaymentService $paymentService): JsonResponse
    {
        $validated = $request->validate([
            'member_name' => 'required|string|max:255',
            'member_email' => 'required|email|max:255',
            'member_phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = $this->proSubscription->normalizeEmail($validated['member_email']);

        if (NigtaxProUser::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'member_email' => ['An account already exists for this email. Use Sign in.'],
            ]);
        }

        $membership = Membership::query()
            ->where('slug', $this->proSubscription->membershipSlug())
            ->where('is_active', true)
            ->with('business')
            ->first();

        if (! $membership || ! $membership->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'NigTax PRO membership is not available right now.',
            ], 422);
        }

        $slug = $membership->slug;

        try {
            $paymentData = [
                'amount' => (float) $membership->price,
                'payer_name' => $validated['member_name'],
                'webhook_url' => route('memberships.payment.webhook', ['slug' => $slug]),
                'service' => 'membership',
                'business_website_id' => null,
            ];

            $payment = $paymentService->createPayment(
                $paymentData,
                $membership->business,
                $request,
                false
            );

            $emailData = $payment->email_data ?? [];
            $emailData['membership_id'] = $membership->id;
            $emailData['member_name'] = $validated['member_name'];
            $emailData['member_email'] = $validated['member_email'];
            $emailData['member_phone'] = $validated['member_phone'];
            $payment->update(['email_data' => $emailData]);

            NigtaxProPendingRegistration::query()->create([
                'payment_id' => $payment->id,
                'email' => $email,
                'password_hash' => Hash::make($validated['password']),
                'member_name' => $validated['member_name'],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not start payment. Please try again in a moment.',
            ], 503);
        }

        $payment->load('accountNumberDetails');

        try {
            Mail::to($validated['member_email'])->send(new MembershipPaymentInstructionsMail(
                $payment->fresh(['accountNumberDetails']),
                $membership,
                [
                    'member_name' => $validated['member_name'],
                    'member_email' => $validated['member_email'],
                    'member_phone' => $validated['member_phone'],
                ]
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'transaction_id' => $payment->transaction_id,
                'amount' => (float) $payment->amount,
                'account_number' => $payment->account_number,
                'account_name' => $payment->accountNumberDetails->account_name ?? null,
                'bank_name' => $payment->accountNumberDetails->bank_name ?? null,
                'payment_status' => $payment->status,
                'expires_at' => $payment->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Poll payment + whether the PRO account row exists (after approval + listener).
     */
    public function membershipPaymentStatus(string $transactionId): JsonResponse
    {
        $payment = Payment::query()->where('transaction_id', $transactionId)->first();
        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
            ], 404);
        }

        $membershipId = $payment->email_data['membership_id'] ?? null;
        $membership = $membershipId ? Membership::query()->find($membershipId) : null;
        if (! $membership || $membership->slug !== $this->proSubscription->membershipSlug()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
            ], 404);
        }

        $payment->loadMissing('accountNumberDetails');

        $memberEmailRaw = (string) ($payment->email_data['member_email'] ?? '');
        $normEmail = $memberEmailRaw !== ''
            ? $this->proSubscription->normalizeEmail($memberEmailRaw)
            : '';

        $payload = [
            'transaction_id' => $payment->transaction_id,
            'payment_status' => $payment->status,
            'amount' => (float) $payment->amount,
            'expires_at' => $payment->expires_at?->toIso8601String(),
        ];

        if ($payment->status === Payment::STATUS_PENDING) {
            $payload['account_number'] = $payment->account_number;
            $payload['account_name'] = $payment->accountNumberDetails->account_name ?? null;
            $payload['bank_name'] = $payment->accountNumberDetails->bank_name ?? null;
        }

        $payload['account_ready'] = $normEmail !== ''
            && NigtaxProUser::query()->where('email', $normEmail)->exists();
        $payload['subscription_active'] = $normEmail !== ''
            && $this->proSubscription->hasActiveSubscription($normEmail);

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
