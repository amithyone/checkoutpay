<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'transaction_id' => $this->transaction_id,
            'amount' => (float) $this->amount,
            'payer_name' => $this->payer_name,
            'bank' => $this->bank,
            'webhook_url' => $this->webhook_url,
            'account_number' => $this->account_number,
            'account_details' => $this->accountNumberDetails ? [
                'account_name' => $this->accountNumberDetails->account_name,
                'bank_name' => $this->accountNumberDetails->bank_name,
            ] : null,
            'status' => $this->status,
            'email_data' => $this->email_data,
            'matched_at' => $this->matched_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
