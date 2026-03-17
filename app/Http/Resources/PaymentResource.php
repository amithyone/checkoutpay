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
            // Expected/original amount requested when creating the payment
            'amount' => (float) $this->amount,
            // Actual amount received on the bank/email side (may differ when there is a mismatch or manual approval)
            'received_amount' => $this->received_amount !== null ? (float) $this->received_amount : null,
            // Convenience flag so API consumers can easily tell if the amount was updated
            'is_amount_updated' => $this->received_amount !== null && (float) $this->received_amount !== (float) $this->amount,
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
