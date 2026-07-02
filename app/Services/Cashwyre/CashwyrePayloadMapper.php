<?php

namespace App\Services\Cashwyre;

use Illuminate\Support\Carbon;

final class CashwyrePayloadMapper
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCardPayload(array $payload): array
    {
        $phoneNumber = preg_replace('/\D/', '', (string) ($payload['phoneNumber'] ?? '')) ?? '';
        if (strlen($phoneNumber) === 13 && str_starts_with($phoneNumber, '234')) {
            $phoneNumber = substr($phoneNumber, 3);
        }
        if (strlen($phoneNumber) === 11 && str_starts_with($phoneNumber, '0')) {
            $phoneNumber = substr($phoneNumber, 1);
        }

        $brand = trim((string) config('cashwyre.default_card_brand', 'Visa'));
        if ($brand === '') {
            $brand = 'Visa';
        }

        return array_filter([
            'firstName' => (string) ($payload['firstName'] ?? ''),
            'lastName' => (string) ($payload['lastName'] ?? ''),
            'email' => (string) ($payload['email'] ?? ''),
            'phoneCode' => (string) config('cashwyre.default_phone_code', '+234'),
            'phoneNumber' => $phoneNumber,
            'dateOfBirth' => $this->toIso8601Date((string) ($payload['dob'] ?? '')),
            'homeAddressNumber' => (string) ($payload['homeNumber'] ?? ''),
            'homeAddress' => (string) ($payload['homeAddress'] ?? ''),
            'cardName' => (string) ($payload['cardName'] ?? trim(((string) ($payload['firstName'] ?? '')).' '.((string) ($payload['lastName'] ?? '')))),
            'cardType' => 'virtual',
            'cardBrand' => $brand,
            'amountInUSD' => round((float) ($payload['amount'] ?? 0), 2),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    public function toIso8601Date(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        try {
            return Carbon::parse($date, 'UTC')->startOfDay()->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable) {
            return $date;
        }
    }
}
