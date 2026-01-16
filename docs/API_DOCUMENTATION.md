# Payment Gateway API Documentation

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Base URL](#base-url)
4. [Account Number Request (API Integration)](#account-number-request)
5. [Hosted Checkout Page (Alternative Option)](#hosted-checkout-page-alternative-option)
6. [Transaction Updates](#transaction-updates)
7. [Webhook Notifications](#webhook-notifications)
8. [Error Handling](#error-handling)
9. [Rate Limits](#rate-limits)
10. [Sample Integration](#sample-integration)

---

## Overview

The Payment Gateway API allows businesses to integrate payment collection into their applications. When a customer needs to make a payment, your application requests an account number from the gateway, displays it to the customer, and receives webhook notifications when the payment is verified.

**Production API Base URL:** `https://check-outpay.com/api/v1`

### Key Features

- **Account Number Generation**: Request unique account numbers for each transaction
- **Real-time Webhooks**: Receive instant notifications when payments are verified
- **Transaction Status**: Check payment status at any time
- **Secure Authentication**: API key-based authentication

---

## Authentication

All API requests require authentication using an API key provided when your business account is registered.

### Getting Your API Key

1. Register your business account at `https://check-outpay.com/dashboard/register`
2. Provide your website URL (must be approved before you can make requests)
3. Once your website is approved, your API key will be generated automatically
4. Find your API key and Business ID in your business dashboard at `https://check-outpay.com/dashboard/keys` or under Settings
5. Your **Business ID** is a 5-character alphanumeric code (e.g., `A3B9K`) displayed on your dashboard - use this for hosted checkout page integration

### Using Your API Key

Include your API key in the request header:

```
X-API-Key: pk_your_api_key_here
```

**Important**: Never expose your API key in client-side code. Always use server-side code to make API requests.

---

## Base URL

```
Production: https://check-outpay.com/api/v1
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
| `name` | string | Yes | Payer name (customer's name as it appears on their bank account). This will be normalized to lowercase and stored as `payer_name`. |
| `amount` | number | Yes | Payment amount (minimum: 0.01) |
| `service` | string | No | Service/product identifier (for your internal tracking). This field is accepted but not stored in the payment record - use it for your own reference. |
| `webhook_url` | string | Yes | Your webhook URL to receive payment notifications. Must be from an approved domain. |

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
    "updated_at": "2024-01-01T12:00:00.000000Z",
    "charges": {
      "percentage": 50.00,
      "fixed": 100.00,
      "total": 150.00,
      "paid_by_customer": false,
      "amount_to_pay": 5000.00,
      "business_receives": 4850.00
    },
    "website": {
      "id": 1,
      "url": "https://yourwebsite.com"
    }
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
    "name": ["The name field is required."],
    "webhook_url": ["The webhook url must be a valid URL."]
  }
}
```

**Note**: The `name` field you send will be normalized (converted to lowercase) and returned as `payer_name` in the response. This normalization helps with payment matching.

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `transaction_id` | string | Unique transaction identifier (use this to track the payment) |
| `account_number` | string | Bank account number for customer to pay into |
| `account_details` | object | Account name and bank name |
| `amount` | number | Payment amount |
| `payer_name` | string | Normalized payer name (lowercase version of the `name` you sent) |
| `bank` | string\|null | Bank name (null until payment is matched) |
| `webhook_url` | string | Your webhook URL |
| `status` | string | Payment status: `pending`, `approved`, `rejected` |
| `email_data` | object\|null | Payment verification data from email (null until payment is matched) |
| `matched_at` | string\|null | ISO 8601 timestamp when payment was matched (null if pending) |
| `expires_at` | string | ISO 8601 timestamp when payment request expires |
| `created_at` | string | ISO 8601 timestamp when request was created |
| `updated_at` | string | ISO 8601 timestamp when payment was last updated |
| `charges` | object | Charge breakdown (see Charges section below) |
| `website` | object\|null | Website information (id and url) |

---

## Hosted Checkout Page (Alternative Option)

Instead of integrating the API directly, you can redirect customers to CheckoutPay's hosted payment page. This option is simpler to implement and doesn't require API integration.

### How It Works

1. **Redirect customer** to CheckoutPay's payment page with payment parameters
2. **Customer enters** their name on CheckoutPay's page
3. **Customer sees** account details and completes payment via bank transfer
4. **Automatic redirect** back to your website once payment is verified

### Endpoint

```
GET https://check-outpay.com/pay
```

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `business_id` | string | Yes | Your 5-character Business ID (found in your dashboard, e.g., "A3B9K") |
| `amount` | number | Yes | Payment amount (minimum: 0.01) |
| `return_url` | string | Yes | URL to redirect customer after payment (must be from approved domain) |
| `service` | string | No | Service/product identifier (for your internal tracking) |
| `cancel_url` | string | No | URL to redirect if customer cancels (defaults to return_url) |

### Example Redirect URL

```
https://check-outpay.com/pay?business_id=A3B9K&amount=5000.00&return_url=https://yourwebsite.com/payment/success&service=ORDER-123
```

**Note**: Your Business ID is a 5-character alphanumeric code (e.g., `A3B9K`, `X7M2P`) displayed in your business dashboard. This is different from your database ID.

### Return URL Parameters

When the customer is redirected back to your `return_url`, the following parameters are appended:

**Success:**
```
?status=success&transaction_id=TXN-1234567890-abc123&amount=5000.00
```

**Failed/Rejected:**
```
?status=failed&transaction_id=TXN-1234567890-abc123&reason=Payment+was+rejected
```

### Example Implementation

**HTML/JavaScript:**
```html
<a href="https://check-outpay.com/pay?business_id=A3B9K&amount=5000.00&return_url=https://yourwebsite.com/payment/success&service=ORDER-123">
    Pay with CheckoutPay
</a>
```

**PHP:**
```php
$businessId = 'A3B9K'; // Your 5-character Business ID from dashboard
$amount = 5000.00;
$returnUrl = urlencode('https://yourwebsite.com/payment/success');
$service = 'ORDER-123';

$checkoutUrl = "https://check-outpay.com/pay?business_id={$businessId}&amount={$amount}&return_url={$returnUrl}&service={$service}";

header("Location: {$checkoutUrl}");
exit;
```

**JavaScript/Node.js:**
```javascript
const businessId = 'A3B9K'; // Your 5-character Business ID from dashboard
const amount = 5000.00;
const returnUrl = encodeURIComponent('https://yourwebsite.com/payment/success');
const service = 'ORDER-123';

const checkoutUrl = `https://check-outpay.com/pay?business_id=${businessId}&amount=${amount}&return_url=${returnUrl}&service=${service}`;

window.location.href = checkoutUrl;
```

### Handling the Return

When customer is redirected back to your website:

```php
// Handle return from CheckoutPay
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $transactionId = $_GET['transaction_id'];
    $amount = $_GET['amount'];
    
    // Update order status, send confirmation email, etc.
    // Payment is automatically credited to your business account
} else {
    // Payment failed or was rejected
    $reason = $_GET['reason'] ?? 'Payment failed';
    // Handle failure
}
```

### Advantages of Hosted Checkout

- **No API integration required** - Just redirect customers
- **Secure** - Payment processing happens on CheckoutPay's servers
- **Automatic redirect** - Customers are redirected back automatically
- **Account credited automatically** - Balance updates without webhook handling

### When to Use Hosted Checkout vs API

**Use Hosted Checkout if:**
- You want a quick, simple integration
- You don't need custom payment UI
- You want CheckoutPay to handle the payment flow

**Use API if:**
- You need a custom payment UI
- You want to embed payment in your checkout process
- You need more control over the payment flow

---

## Transaction Updates

Check the current status of a payment transaction or list all transactions.

### Get Single Transaction

**Endpoint:**

```
GET /payment/{transaction_id}
```

**Alternative Endpoint:**

```
GET /payments/{transaction_id}
```

### List All Transactions

**Endpoint:**

```
GET /payments
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | Filter by status: `pending`, `approved`, `rejected` |
| `from_date` | string | No | Filter from date (YYYY-MM-DD) |
| `to_date` | string | No | Filter to date (YYYY-MM-DD) |
| `website_id` | integer | No | Filter by website ID |
| `per_page` | integer | No | Results per page (default: 15) |
| `page` | integer | No | Page number |

**List Response Example:**

```json
{
  "success": true,
  "data": [
    {
      "transaction_id": "TXN-1234567890-abc123",
      "amount": 5000.00,
      "payer_name": "john doe",
      "bank": "GTB",
      "account_number": "1234567890",
      "account_name": "Your Business Name",
      "bank_name": "GTBank",
      "status": "approved",
      "expires_at": "2024-01-02T12:00:00.000000Z",
      "matched_at": "2024-01-01T12:05:30.000000Z",
      "approved_at": "2024-01-01T12:05:30.000000Z",
      "created_at": "2024-01-01T12:00:00.000000Z",
      "charges": {
        "percentage": 50.00,
        "fixed": 100.00,
        "total": 150.00,
        "paid_by_customer": false,
        "business_receives": 4850.00
      },
      "website": {
        "id": 1,
        "url": "https://yourwebsite.com"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
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
    "account_name": "Your Business Name",
    "bank_name": "GTBank",
    "status": "approved",
    "email_data": {
      "amount": 5000.00,
      "payer_name": "john doe",
      "bank": "GTB",
      "transaction_date": "2024-01-01T12:05:00Z"
    },
    "matched_at": "2024-01-01T12:05:30.000000Z",
    "approved_at": "2024-01-01T12:05:30.000000Z",
    "expires_at": "2024-01-02T12:00:00.000000Z",
    "created_at": "2024-01-01T12:00:00.000000Z",
    "updated_at": "2024-01-01T12:05:30.000000Z",
    "charges": {
      "percentage": 50.00,
      "fixed": 100.00,
      "total": 150.00,
      "paid_by_customer": false,
      "business_receives": 4850.00
    },
    "website": {
      "id": 1,
      "url": "https://yourwebsite.com"
    }
  }
}
```

**Note**: The `payer_name` field in the response is the normalized (lowercase) version of the `name` you sent in the request. This normalization helps with automatic payment matching.

### Charges Information

The `charges` object provides details about transaction fees:

| Field | Type | Description |
|-------|------|-------------|
| `percentage` | number | Percentage charge amount (e.g., 1% of payment amount) |
| `fixed` | number | Fixed charge amount (e.g., ₦100) |
| `total` | number | Total charges (percentage + fixed) |
| `paid_by_customer` | boolean | `true` if customer pays charges, `false` if business pays |
| `amount_to_pay` | number | Total amount customer needs to pay (includes charges if `paid_by_customer` is true) |
| `business_receives` | number | Amount business will receive after charges are deducted |

**Charge Calculation Examples:**

- **Business Pays Charges** (default):
  - Payment: ₦10,000
  - Charges: ₦150 (1% = ₦100 + ₦100 fixed)
  - Customer pays: ₦10,000
  - Business receives: ₦9,850

- **Customer Pays Charges**:
  - Payment: ₦10,000
  - Charges: ₦150 (1% = ₦100 + ₦100 fixed)
  - Customer pays: ₦10,150
  - Business receives: ₦10,000

**Default Charges:** 1% + ₦100 (configurable per business by admin)

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
  "event": "payment.approved",
  "transaction_id": "TXN-1234567890-abc123",
  "status": "approved",
  "amount": 5000.00,
  "received_amount": 5000.00,
  "payer_name": "john doe",
  "bank": "GTB",
  "account_number": "1234567890",
  "payer_account_number": "0987654321",
  "account_details": {
    "account_name": "Your Business Name",
    "bank_name": "GTBank"
  },
  "is_mismatch": false,
  "name_mismatch": false,
  "matched_at": "2024-01-01T12:05:30.000000Z",
  "approved_at": "2024-01-01T12:05:30.000000Z",
  "created_at": "2024-01-01T12:00:00.000000Z",
  "timestamp": "2024-01-01T12:05:30.000000Z",
  "charges": {
    "percentage": 50.00,
    "fixed": 100.00,
    "total": 150.00,
    "paid_by_customer": false,
    "business_receives": 4850.00
  },
  "website": {
    "id": 1,
    "url": "https://yourwebsite.com"
  }
}
```

**With Amount Mismatch**:

```json
{
  "event": "payment.approved",
  "transaction_id": "TXN-1234567890-abc123",
  "status": "approved",
  "amount": 5000.00,
  "received_amount": 4900.00,
  "payer_name": "john doe",
  "bank": "GTB",
  "account_number": "1234567890",
  "payer_account_number": "0987654321",
  "account_details": {
    "account_name": "Your Business Name",
    "bank_name": "GTBank"
  },
  "is_mismatch": true,
  "mismatch_reason": "Amount mismatch: expected 5000.00, received 4900.00",
  "name_mismatch": false,
  "matched_at": "2024-01-01T12:05:30.000000Z",
  "approved_at": "2024-01-01T12:05:30.000000Z",
  "created_at": "2024-01-01T12:00:00.000000Z",
  "timestamp": "2024-01-01T12:05:30.000000Z",
  "charges": {
    "percentage": 49.00,
    "fixed": 100.00,
    "total": 149.00,
    "paid_by_customer": false,
    "business_receives": 4751.00
  },
  "website": {
    "id": 1,
    "url": "https://yourwebsite.com"
  }
}
```

**With Name Mismatch**:

```json
{
  "event": "payment.approved",
  "transaction_id": "TXN-1234567890-abc123",
  "status": "approved",
  "amount": 5000.00,
  "received_amount": 5000.00,
  "payer_name": "john doe",
  "bank": "GTB",
  "account_number": "1234567890",
  "payer_account_number": "0987654321",
  "account_details": {
    "account_name": "Your Business Name",
    "bank_name": "GTBank"
  },
  "is_mismatch": false,
  "name_mismatch": true,
  "name_similarity_percent": 85,
  "matched_at": "2024-01-01T12:05:30.000000Z",
  "approved_at": "2024-01-01T12:05:30.000000Z",
  "created_at": "2024-01-01T12:00:00.000000Z",
  "timestamp": "2024-01-01T12:05:30.000000Z",
  "charges": {
    "percentage": 50.00,
    "fixed": 100.00,
    "total": 150.00,
    "paid_by_customer": false,
    "business_receives": 4850.00
  },
  "website": {
    "id": 1,
    "url": "https://yourwebsite.com"
  }
}
```

### Payment Rejected Webhook

Sent when a payment verification fails.

**Payload**:

```json
{
  "event": "payment.rejected",
  "transaction_id": "TXN-1234567890-abc123",
  "status": "rejected",
  "amount": 5000.00,
  "payer_name": "john doe",
  "bank": "GTB",
  "account_number": "1234567890",
  "payer_account_number": null,
  "account_details": {
    "account_name": "Your Business Name",
    "bank_name": "GTBank"
  },
  "rejected_at": "2024-01-01T13:00:00.000000Z",
  "reason": "Payment expired - no payment received within time limit",
  "created_at": "2024-01-01T12:00:00.000000Z",
  "timestamp": "2024-01-01T13:00:00.000000Z",
  "charges": {
    "percentage": 0,
    "fixed": 0,
    "total": 0,
    "paid_by_customer": false,
    "business_receives": 0
  },
  "website": {
    "id": 1,
    "url": "https://yourwebsite.com"
  }
}
```

### Webhook Payload Fields

| Field | Type | Description |
|-------|------|-------------|
| `event` | string | Event type: `payment.approved` or `payment.rejected` |
| `status` | string | Payment status: `approved` or `rejected` |
| `transaction_id` | string | Unique transaction identifier |
| `amount` | number | Original payment amount requested |
| `received_amount` | number | Actual amount received (may differ from amount if mismatch) |
| `payer_name` | string | Payer's name from bank transaction |
| `bank` | string | Bank name |
| `account_number` | string | Account number where payment was sent TO |
| `payer_account_number` | string | Account number where payment was sent FROM |
| `account_details` | object | Account details (account_name, bank_name) |
| `approved_at` / `rejected_at` | string | ISO 8601 timestamp |
| `matched_at` | string | ISO 8601 timestamp when payment was matched |
| `created_at` | string | ISO 8601 timestamp when payment was created |
| `timestamp` | string | ISO 8601 timestamp of webhook event |
| `is_mismatch` | boolean | True if amount mismatch detected |
| `mismatch_reason` | string | Reason for mismatch (if applicable) |
| `name_mismatch` | boolean | True if name mismatch detected |
| `name_similarity_percent` | number | Name similarity percentage (if name mismatch) |
| `reason` | string | Rejection reason (if rejected) |
| `charges` | object | Charge breakdown (see Charges section below) |
| `website` | object\|null | Website information (id and url) |
| `email` | object\|null | Email data (subject, from, date) if available |

### Charges Information in Webhooks

The `charges` object in webhook payloads provides details about transaction fees:

| Field | Type | Description |
|-------|------|-------------|
| `percentage` | number | Percentage charge amount applied |
| `fixed` | number | Fixed charge amount applied |
| `total` | number | Total charges (percentage + fixed) |
| `paid_by_customer` | boolean | `true` if customer pays charges, `false` if business pays |
| `business_receives` | number | Amount business receives after charges |

**Note:** Charges are calculated based on the business's charge settings. If the business is exempt from charges, all charge values will be 0.

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
    private $baseUrl = 'https://check-outpay.com/api/v1';

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
    constructor(apiKey, baseUrl = 'https://check-outpay.com/api/v1') {
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
    def __init__(self, api_key, base_url='https://check-outpay.com/api/v1'):
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

4. You can check your approval status in the business dashboard at `https://check-outpay.com/dashboard/settings`

### Important Notes

- Only approved domains can be used in webhook URLs
- You can check your website approval status at `https://check-outpay.com/dashboard/settings`
- Only businesses with approved websites can request account numbers
- Changes to your domain require re-approval
- Use HTTPS URLs for security

---

## Support

For API support, please contact:
- Email: support@check-outpay.com
- Documentation: https://check-outpay.com/docs
- Status Page: https://status.check-outpay.com
