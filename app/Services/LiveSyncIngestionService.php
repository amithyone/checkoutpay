<?php

namespace App\Services;

use App\Models\Business;
use App\Models\LiveSyncEvent;
use App\Models\Payment;
use App\Models\Renter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LiveSyncIngestionService
{
    public function ingest(array $payload): array
    {
        $eventId = (string) $payload['event_id'];
        $entity = (string) $payload['entity'];
        $operation = (string) $payload['operation'];
        $source = isset($payload['source']) ? (string) $payload['source'] : null;
        $data = (array) $payload['data'];

        $existing = LiveSyncEvent::where('event_id', $eventId)->first();
        if ($existing) {
            return [
                'status' => 'duplicate',
                'event_id' => $eventId,
                'entity' => $existing->entity,
                'operation' => $existing->operation,
            ];
        }

        $event = LiveSyncEvent::create([
            'event_id' => $eventId,
            'source' => $source,
            'entity' => $entity,
            'operation' => $operation,
            'status' => 'pending',
            'payload' => Arr::only($payload, ['event_id', 'source', 'entity', 'operation', 'data', 'sent_at']),
        ]);

        try {
            $recordKey = DB::transaction(function () use ($entity, $operation, $data) {
                return match ($entity) {
                    'payment' => $this->handlePayment($operation, $data),
                    'business' => $this->handleBusiness($operation, $data),
                    'renter' => $this->handleRenter($operation, $data),
                    default => throw new \InvalidArgumentException('Unsupported entity.'),
                };
            });

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            return [
                'status' => 'processed',
                'event_id' => $eventId,
                'entity' => $entity,
                'operation' => $operation,
                'record' => $recordKey,
            ];
        } catch (\Throwable $e) {
            $event->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000, '...'),
            ]);

            throw $e;
        }
    }

    private function handlePayment(string $operation, array $data): string
    {
        $transactionId = trim((string) ($data['transaction_id'] ?? ''));
        if ($transactionId === '') {
            throw new \InvalidArgumentException('payment.transaction_id is required.');
        }

        if ($operation === 'delete') {
            $payment = Payment::where('transaction_id', $transactionId)->first();
            if ($payment) {
                $payment->delete();
            }
            return $transactionId;
        }

        if ($operation !== 'upsert') {
            throw new \InvalidArgumentException('Unsupported payment operation.');
        }

        $allowed = [
            'transaction_id', 'amount', 'payer_name', 'bank', 'webhook_url',
            'account_number', 'payer_account_number', 'business_id', 'user_id',
            'renter_id', 'business_website_id', 'rental_id', 'status',
            'payment_source', 'external_reference', 'received_amount',
            'is_mismatch', 'mismatch_reason', 'matched_at', 'expires_at',
        ];

        $attributes = Arr::only($data, $allowed);
        $attributes['transaction_id'] = $transactionId;

        if (isset($attributes['status']) && !in_array($attributes['status'], [
            Payment::STATUS_PENDING,
            Payment::STATUS_APPROVED,
            Payment::STATUS_REJECTED,
        ], true)) {
            throw new \InvalidArgumentException('Invalid payment status.');
        }

        $payment = Payment::withTrashed()->where('transaction_id', $transactionId)->first();
        if (!$payment) {
            if (!isset($attributes['amount'])) {
                throw new \InvalidArgumentException('payment.amount is required for create.');
            }
            if (!isset($attributes['status'])) {
                $attributes['status'] = Payment::STATUS_PENDING;
            }
            Payment::create($attributes);
            return $transactionId;
        }

        if (method_exists($payment, 'trashed') && $payment->trashed()) {
            $payment->restore();
        }

        $payment->fill($attributes)->save();

        return $transactionId;
    }

    private function handleBusiness(string $operation, array $data): string
    {
        $businessId = trim((string) ($data['business_id'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($businessId === '' && $email === '') {
            throw new \InvalidArgumentException('business.business_id or business.email is required.');
        }

        $business = Business::query()
            ->when($businessId !== '', fn ($q) => $q->where('business_id', $businessId))
            ->when($businessId === '' && $email !== '', fn ($q) => $q->where('email', $email))
            ->first();

        if ($operation === 'delete') {
            if ($business) {
                $business->delete();
            }
            return $businessId !== '' ? $businessId : $email;
        }

        if ($operation !== 'upsert') {
            throw new \InvalidArgumentException('Unsupported business operation.');
        }

        $allowed = [
            'business_id', 'name', 'email', 'phone', 'address', 'website',
            'webhook_url', 'is_active', 'website_approved', 'timezone',
            'currency', 'charges_paid_by_customer', 'charge_percentage',
            'charge_fixed', 'charge_exempt',
        ];
        $attributes = Arr::only($data, $allowed);
        if (isset($attributes['email'])) {
            $attributes['email'] = strtolower(trim((string) $attributes['email']));
        }
        if (isset($attributes['business_id'])) {
            $attributes['business_id'] = strtoupper(trim((string) $attributes['business_id']));
        }

        if (!$business) {
            if (empty($attributes['email']) || empty($attributes['name'])) {
                throw new \InvalidArgumentException('business.name and business.email are required for create.');
            }
            $attributes['password'] = Str::random(40);
            Business::create($attributes);
            return (string) ($attributes['business_id'] ?? $attributes['email']);
        }

        $business->fill($attributes)->save();

        return (string) ($business->business_id ?: $business->email);
    }

    private function handleRenter(string $operation, array $data): string
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '') {
            throw new \InvalidArgumentException('renter.email is required.');
        }

        $renter = Renter::withTrashed()->where('email', $email)->first();
        if ($operation === 'delete') {
            if ($renter) {
                $renter->delete();
            }
            return $email;
        }

        if ($operation !== 'upsert') {
            throw new \InvalidArgumentException('Unsupported renter operation.');
        }

        $allowed = [
            'name', 'email', 'phone', 'address', 'wallet_balance',
            'verified_account_number', 'verified_account_name', 'verified_bank_name',
            'verified_bank_code', 'kyc_verified_at', 'kyc_id_status', 'is_active',
            'whatsapp_phone_e164', 'whatsapp_verified_at',
        ];
        $attributes = Arr::only($data, $allowed);
        $attributes['email'] = $email;

        if (!$renter) {
            if (empty($attributes['name'])) {
                throw new \InvalidArgumentException('renter.name is required for create.');
            }
            $attributes['password'] = Str::random(40);
            Renter::create($attributes);
            return $email;
        }

        if (method_exists($renter, 'trashed') && $renter->trashed()) {
            $renter->restore();
        }

        $renter->fill($attributes)->save();
        return $email;
    }
}
