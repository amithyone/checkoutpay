# Payment Gateway API Documentation

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Base URL](#base-url)
4. [Account Number Request](#account-number-request)
5. [Transaction Updates](#transaction-updates)
6. [Webhook Notifications](#webhook-notifications)
7. [Error Handling](#error-handling)
8. [Rate Limits](#rate-limits)
9. [Sample Integration](#sample-integration)

---

## Overview

The Payment Gateway API allows businesses to integrate payment collection into their applications. When a customer needs to make a payment, your application requests an account number from the gateway, displays it to the customer, and receives webhook notifications when the payment is verified.

### Key Features

- **Account Number Generation**: Request unique account numbers for each transaction
- **Real-time Webhooks**: Receive instant notifications when payments are verified
- **Transaction Status**: Check payment status at any time
- **Secure Authentication**: API key-based authentication

---

## Authentication

All API requests require authentication using an API key provided when your business account is registered.

### Getting Your API Key

1. Register your business account through the admin panel
2. Provide your callback URL (must be approved before you can make requests)
3. Once approved, your API key will be generated automatically
4. Find your API key in your business dashboard under Settings

### Using Your API Key

Include your API key in the request header:

```
X-API-Key: pk_your_api_key_here
```

**Important**: Never expose your API key in client-side code. Always use server-side code to make API requests.

---

## Base URL

```
Production: https://your-domain.com/api/v1
Development: http://localhost:8000/api/v1
```

---

## Account Number Request

Create a payment request and receive an account number for the customer to pay into.

### Endpoint

```
POST /payment-request
```

### Headers

```
Content-Type: application/json
X-API-Key: pk_your_api_key_here
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Payer name (customer's name as it appears on their bank account) |
| `amount` | number | Yes | Payment amount (minimum: 0.01) |
| `service` | string | No | Service/product identifier (for your internal tracking) |
| `webhook_url` | string | Yes | Your webhook URL to receive payment notifications |

**Note**: The `webhook_url` must be from an approved domain. During business registration, you provide your domain/URL which must be approved before making requests.

### Request Example

```json
{
  "name": "John Doe",
  "amount": 5000.00,
  "service": "PRODUCT-123",
  "webhook_url": "https://yourwebsite.com/webhook/payment-status"
}
```

### Response

**Success (201 Created)**

```json
{
  "success": true,
  "message": "Payment request received and monitoring started",
  "data": {
    "transaction_id": "TXN-1234567890-abc123",
    "amount": 5000.00,
    "payer_name": "john doe",
    "bank": null,
    "webhook_url": "https://yourwebsite.com/webhook/payment-status",
    "account_number": "1234567890",
    "account_details": {
      "account_name": "Your Business Name",
      "bank_name": "GTBank"
    },
    "status": "pending",
    "email_data": null,
    "matched_at": null,
    "expires_at": "2024-01-02T12:00:00.000000Z",
    "created_at": "2024-01-01T12:00:00.000000Z",
    "updated_at": "2024-01-01T12:00:00.000000Z"
  }
}
```

**Error (401 Unauthorized)**

```json
{
  "success": false,
  "message": "Invalid or inactive API key"
}
```

**Error (422 Validation Error)**

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "amount": ["The amount field is required."],
    "name": ["The name field is required."]
  }
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `transaction_id` | string | Unique transaction identifier (use this to track the payment) |
| `account_number` | string | Bank account number for customer to pay into |
| `account_details` | object | Account name and bank name |
| `amount` | number | Payment amount |
| `status` | string | Payment status: `pending`, `approved`, `rejected` |
| `expires_at` | string | ISO 8601 timestamp when payment request expires |
| `created_at` | string | ISO 8601 timestamp when request was created |

---

## Transaction Updates

Check the current status of a payment transaction.

### Endpoint

```
GET /payment/{transaction_id}
```

### Headers

```
X-API-Key: pk_your_api_key_here
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `transaction_id` | string | Yes | The transaction ID returned from the payment request |

### Response

**Success (200 OK)**

```json
{
  "success": true,
  "data": {
    "transaction_id": "TXN-1234567890-abc123",
    "amount": 5000.00,
    "payer_name": "john doe",
    "bank": "GTB",
    "webhook_url": "https://yourwebsite.com/webhook/payment-status",
    "account_number": "1234567890",
    "account_details": {
      "account_name": "Your Business Name",
      "bank_name": "GTBank"
    },
    "status": "approved",
    "email_data": {
      "amount": 5000.00,
      "payer_name": "john doe",
      "bank": "GTB",
      "transaction_date": "2024-01-01T12:05:00Z"
    },
    "matched_at": "2024-01-01T12:05:30.000000Z",
    "expires_at": "2024-01-02T12:00:00.000000Z",
    "created_at": "2024-01-01T12:00:00.000000Z",
    "updated_at": "2024-01-01T12:05:30.000000Z"
  }
}
```

**Error (404 Not Found)**

```json
{
  "success": false,
  "message": "No query results for model [App\\Models\\Payment]"
}
```

### Status Values

- `pending`: Payment is waiting to be verified
- `approved`: Payment has been verified and confirmed
- `rejected`: Payment verification failed (amount mismatch, expired, etc.)

---

## Webhook Notifications

Your webhook URL will receive POST requests when payment status changes.

### Webhook Security

**URL Approval**: Your webhook URL domain must be approved during business registration. Only requests from approved domains will be accepted.

**Best Practice**: Always verify the webhook is from our servers by:
1. Using HTTPS for your webhook endpoint
2. Validating the payload structure
3. Checking the transaction_id against your records

### Webhook Endpoint Requirements

Your webhook endpoint should:
- Accept POST requests
- Return HTTP 200 status on success
- Process the payload asynchronously if possible (respond quickly)
- Handle duplicate webhooks (idempotent)

### Payment Approved Webhook

Sent when a payment is verified and approved.

**Payload**:

```json
{
  "success": true,
  "status": "approved",
  "transaction_id": "TXN-1234567890-abc123",
  "amount": 5000.00,
  "payer_name": "john doe",
  "bank": "GTB",
  "account_number": "1234567890",
  "payer_account_number": "0987654321",
  "approved_at": "2024-01-01T12:05:30.000000Z",
  "message": "Payment has been verified and approved",
  "is_mismatch": false,
  "name_mismatch": false
}
```

**With Amount Mismatch**:

```json
{
  "success": true,
  "status": "approved",
  "transaction_id": "TXN-1234567890-abc123",
  "amount": 5000.00,
  "received_amount": 4900.00,
  "payer_name": "john doe",
  "bank": "GTB",
  "account_number": "1234567890",
  "payer_account_number": "0987654321",
  "approved_at": "2024-01-01T12:05:30.000000Z",
  "message": "Payment has been verified and approved (amount mismatch detected)",
  "is_mismatch": true,
  "mismatch_reason": "Amount mismatch: expected 5000.00, received 4900.00",
  "name_mismatch": false
}
```

**With Name Mismatch**:

```json
{
  "success": true,
  "status": "approved",
  "transaction_id": "TXN-1234567890-abc123",
  "amount": 5000.00,
  "payer_name": "john doe",
  "bank": "GTB",
  "account_number": "1234567890",
  "payer_account_number": "0987654321",
  "approved_at": "2024-01-01T12:05:30.000000Z",
  "message": "Payment has been verified and approved (name mismatch detected)",
  "is_mismatch": false,
  "name_mismatch": true,
  "name_similarity_percent": 85
}
```

### Payment Rejected Webhook

Sent when a payment verification fails.

**Payload**:

```json
{
  "success": false,
  "status": "rejected",
  "transaction_id": "TXN-1234567890-abc123",
  "amount": 5000.00,
  "payer_name": "john doe",
  "bank": "GTB",
  "account_number": "1234567890",
  "payer_account_number": null,
  "rejected_at": "2024-01-01T13:00:00.000000Z",
  "reason": "Payment expired - no payment received within time limit",
  "message": "Payment has been rejected"
}
```

### Webhook Payload Fields

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | `true` if approved, `false` if rejected |
| `status` | string | Payment status: `approved` or `rejected` |
| `transaction_id` | string | Unique transaction identifier |
| `amount` | number | Original payment amount requested |
| `received_amount` | number | Actual amount received (only if mismatch) |
| `payer_name` | string | Payer's name from bank transaction |
| `bank` | string | Bank name |
| `account_number` | string | Account number where payment was sent TO |
| `payer_account_number` | string | Account number where payment was sent FROM |
| `approved_at` / `rejected_at` | string | ISO 8601 timestamp |
| `message` | string | Human-readable status message |
| `is_mismatch` | boolean | True if amount mismatch detected |
| `mismatch_reason` | string | Reason for mismatch (if applicable) |
| `name_mismatch` | boolean | True if name mismatch detected |
| `name_similarity_percent` | number | Name similarity percentage (if name mismatch) |
| `reason` | string | Rejection reason (if rejected) |

---

## Error Handling

### HTTP Status Codes

| Status Code | Meaning |
|-------------|---------|
| 200 | Success |
| 201 | Created (payment request successful) |
| 400 | Bad Request |
| 401 | Unauthorized (invalid API key) |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

### Error Response Format

```json
{
  "success": false,
  "message": "Error message description",
  "errors": {
    "field_name": ["Error message for this field"]
  }
}
```

### Common Errors

**Invalid API Key**

```json
{
  "success": false,
  "message": "Invalid or inactive API key"
}
```

**Unauthorized Domain**

```json
{
  "success": false,
  "message": "Webhook URL domain not approved. Please contact support to approve your domain."
}
```

**Validation Error**

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "amount": ["The amount must be at least 0.01."],
    "name": ["The name field is required."],
    "webhook_url": ["The webhook url must be a valid URL."]
  }
}
```

---

## Rate Limits

API rate limits apply to prevent abuse:

- **Account Number Requests**: 100 requests per minute per API key
- **Transaction Status Checks**: 300 requests per minute per API key

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1609459200
```

If you exceed the rate limit, you'll receive a 429 status code:

```json
{
  "success": false,
  "message": "Too many requests. Please try again later."
}
```

---

## Sample Integration

### PHP (Laravel)

```php
<?php

use Illuminate\Support\Facades\Http;

class PaymentService
{
    private $apiKey = 'pk_your_api_key_here';
    private $baseUrl = 'https://your-domain.com/api/v1';

    public function createPaymentRequest($name, $amount, $service = null)
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/payment-request", [
            'name' => $name,
            'amount' => $amount,
            'service' => $service,
            'webhook_url' => route('webhook.payment-status'),
        ]);

        if ($response->successful()) {
            return $response->json()['data'];
        }

        throw new \Exception($response->json()['message']);
    }

    public function checkPaymentStatus($transactionId)
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->get("{$this->baseUrl}/payment/{$transactionId}");

        if ($response->successful()) {
            return $response->json()['data'];
        }

        throw new \Exception($response->json()['message']);
    }
}

// Webhook Handler
Route::post('/webhook/payment-status', function (Request $request) {
    $payload = $request->all();
    
    if ($payload['status'] === 'approved') {
        // Update order status
        $order = Order::where('transaction_id', $payload['transaction_id'])->first();
        if ($order) {
            $order->update(['status' => 'paid']);
        }
    }
    
    return response()->json(['success' => true], 200);
})->middleware('webhook');
```

### JavaScript (Node.js)

```javascript
const axios = require('axios');

class PaymentService {
    constructor(apiKey, baseUrl = 'https://your-domain.com/api/v1') {
        this.apiKey = apiKey;
        this.baseUrl = baseUrl;
    }

    async createPaymentRequest(name, amount, service = null) {
        try {
            const response = await axios.post(
                `${this.baseUrl}/payment-request`,
                {
                    name,
                    amount,
                    service,
                    webhook_url: 'https://yourwebsite.com/webhook/payment-status'
                },
                {
                    headers: {
                        'X-API-Key': this.apiKey,
                        'Content-Type': 'application/json'
                    }
                }
            );
            
            return response.data.data;
        } catch (error) {
            throw new Error(error.response?.data?.message || 'Payment request failed');
        }
    }

    async checkPaymentStatus(transactionId) {
        try {
            const response = await axios.get(
                `${this.baseUrl}/payment/${transactionId}`,
                {
                    headers: {
                        'X-API-Key': this.apiKey
                    }
                }
            );
            
            return response.data.data;
        } catch (error) {
            throw new Error(error.response?.data?.message || 'Failed to check status');
        }
    }
}

// Express.js Webhook Handler
app.post('/webhook/payment-status', (req, res) => {
    const payload = req.body;
    
    if (payload.status === 'approved') {
        // Process approved payment
        console.log(`Payment approved: ${payload.transaction_id}`);
    }
    
    res.status(200).json({ success: true });
});
```

### Python

```python
import requests

class PaymentService:
    def __init__(self, api_key, base_url='https://your-domain.com/api/v1'):
        self.api_key = api_key
        self.base_url = base_url
        self.headers = {
            'X-API-Key': self.api_key,
            'Content-Type': 'application/json'
        }
    
    def create_payment_request(self, name, amount, service=None):
        url = f"{self.base_url}/payment-request"
        data = {
            'name': name,
            'amount': amount,
            'service': service,
            'webhook_url': 'https://yourwebsite.com/webhook/payment-status'
        }
        
        response = requests.post(url, json=data, headers=self.headers)
        response.raise_for_status()
        
        return response.json()['data']
    
    def check_payment_status(self, transaction_id):
        url = f"{self.base_url}/payment/{transaction_id}"
        
        response = requests.get(url, headers=self.headers)
        response.raise_for_status()
        
        return response.json()['data']

# Flask Webhook Handler
@app.route('/webhook/payment-status', methods=['POST'])
def payment_webhook():
    payload = request.json
    
    if payload['status'] == 'approved':
        # Process approved payment
        print(f"Payment approved: {payload['transaction_id']}")
    
    return jsonify({'success': True}), 200
```

---

## URL Approval Process

### During Business Registration

1. When registering your business account, provide your webhook URL domain
   - Example: `https://yourwebsite.com`
   - Only the domain needs approval, not specific paths

2. Your URL will be reviewed by the admin team

3. Once approved, you'll receive a confirmation email and can start making API requests

4. You can check your approval status in the business dashboard

### Important Notes

- Only approved domains can be used in webhook URLs
- You can request domain approval from your business dashboard
- Changes to your domain require re-approval
- Use HTTPS URLs for security

---

## Support

For API support, please contact:
- Email: support@your-domain.com
- Documentation: https://your-domain.com/docs
- Status Page: https://status.your-domain.com
