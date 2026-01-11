# Webhook Payload Reference

## Overview

When a payment transaction is matched and approved (or rejected), the system sends a POST request to the business's `webhook_url` with a JSON payload.

## Request Details

### HTTP Method
```
POST
```

### Headers
```
Content-Type: application/json
User-Agent: EmailPaymentGateway/1.0
```

### Request Timeout
30 seconds

### Retry Logic
- **Attempts:** 3 tries
- **Backoff:** 30 seconds, 60 seconds, 120 seconds (between attempts)
- **Retry on:** 5xx server errors

---

## Payload Structure

### ✅ Approved Payment Payload

When a payment is successfully matched and approved:

```json
{
  "success": true,
  "status": "approved",
  "transaction_id": "TXN-20260110123456-abc123",
  "amount": 5000.00,
  "payer_name": "John Doe",
  "bank": "GTBank",
  "approved_at": "2026-01-10T12:30:45.000000Z",
  "message": "Payment has been verified and approved"
}
```

**Field Descriptions:**

| Field | Type | Description | Required |
|-------|------|-------------|----------|
| `success` | boolean | Always `true` for approved payments | ✅ Yes |
| `status` | string | Payment status: `"approved"` | ✅ Yes |
| `transaction_id` | string | Unique transaction identifier (format: `TXN-{timestamp}-{random}`) | ✅ Yes |
| `amount` | float | Payment amount (e.g., `5000.00`) | ✅ Yes |
| `payer_name` | string\|null | Name of the person who made the payment (may be null if not extracted) | ⚠️ Optional |
| `bank` | string\|null | Bank name (may be null if not extracted) | ⚠️ Optional |
| `approved_at` | string | ISO 8601 timestamp when payment was approved (e.g., `2026-01-10T12:30:45.000000Z`) | ✅ Yes |
| `message` | string | Human-readable message: `"Payment has been verified and approved"` | ✅ Yes |

---

### ❌ Rejected Payment Payload

When a payment is rejected (rarely used, typically payments expire instead):

```json
{
  "success": false,
  "status": "rejected",
  "transaction_id": "TXN-20260110123456-abc123",
  "amount": 5000.00,
  "payer_name": "John Doe",
  "rejected_at": "2026-01-10T12:30:45.000000Z",
  "reason": "Payment verification failed",
  "message": "Payment has been rejected"
}
```

**Field Descriptions:**

| Field | Type | Description | Required |
|-------|------|-------------|----------|
| `success` | boolean | Always `false` for rejected payments | ✅ Yes |
| `status` | string | Payment status: `"rejected"` | ✅ Yes |
| `transaction_id` | string | Unique transaction identifier | ✅ Yes |
| `amount` | float | Payment amount | ✅ Yes |
| `payer_name` | string\|null | Name of the payer (may be null) | ⚠️ Optional |
| `rejected_at` | string | ISO 8601 timestamp when payment was rejected | ✅ Yes |
| `reason` | string | Reason for rejection (e.g., `"Payment verification failed"`) | ✅ Yes |
| `message` | string | Human-readable message: `"Payment has been rejected"` | ✅ Yes |

---

### ⏰ Expired Payment Payload

When a payment expires (sent via `SendExpiredPaymentWebhook`):

```json
{
  "success": false,
  "status": "expired",
  "transaction_id": "TXN-20260110123456-abc123",
  "amount": 5000.00,
  "message": "Payment status updated"
}
```

**Field Descriptions:**

| Field | Type | Description | Required |
|-------|------|-------------|----------|
| `success` | boolean | Always `false` for expired payments | ✅ Yes |
| `status` | string | Payment status: `"expired"` | ✅ Yes |
| `transaction_id` | string | Unique transaction identifier | ✅ Yes |
| `amount` | float | Payment amount | ✅ Yes |
| `message` | string | Human-readable message: `"Payment status updated"` | ✅ Yes |

---

## Example Real-World Payloads

### Example 1: GTBank Payment Approved

```json
{
  "success": true,
  "status": "approved",
  "transaction_id": "TXN-20260110152300-xyz789",
  "amount": 15000.50,
  "payer_name": "AMITHY ONE M",
  "bank": "GTBank",
  "approved_at": "2026-01-10T15:25:10.123456Z",
  "message": "Payment has been verified and approved"
}
```

### Example 2: Payment with Missing Payer Name

```json
{
  "success": true,
  "status": "approved",
  "transaction_id": "TXN-20260110153422-def456",
  "amount": 2500.00,
  "payer_name": null,
  "bank": null,
  "approved_at": "2026-01-10T15:35:05.789012Z",
  "message": "Payment has been verified and approved"
}
```

### Example 3: Small Amount Payment

```json
{
  "success": true,
  "status": "approved",
  "transaction_id": "TXN-20260110161233-ghi789",
  "amount": 500.00,
  "payer_name": "Jane Smith",
  "bank": "Access Bank",
  "approved_at": "2026-01-10T16:13:15.456789Z",
  "message": "Payment has been verified and approved"
}
```

---

## Webhook Endpoint Requirements

### Your Webhook Endpoint Should:

1. **Accept POST requests** with `Content-Type: application/json`
2. **Return HTTP 200-299** status codes for successful processing
3. **Process webhooks idempotently** (same transaction_id may be sent multiple times)
4. **Handle timeouts gracefully** (webhook has 30-second timeout)
5. **Validate `transaction_id`** to ensure you haven't already processed this payment

### Recommended Webhook Handler

```php
// Example PHP webhook handler
<?php
header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($payload['transaction_id']) || !isset($payload['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$transactionId = $payload['transaction_id'];
$status = $payload['status'];

// Check if already processed (idempotency)
if (alreadyProcessed($transactionId)) {
    http_response_code(200);
    echo json_encode(['message' => 'Already processed']);
    exit;
}

// Process based on status
if ($status === 'approved' && $payload['success'] === true) {
    // Update your database
    processPayment($transactionId, $payload['amount'], $payload);
    
    // Mark as processed
    markAsProcessed($transactionId);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Payment processed']);
} else {
    // Handle rejected/expired payments
    handleRejectedPayment($transactionId, $payload);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Status updated']);
}
```

---

## Webhook Security

### Current Implementation

Currently, webhooks are sent **without authentication signatures**. The system relies on:

1. **HTTPS URLs** (recommended) - ensures payload is encrypted in transit
2. **Unique transaction_id** - allows idempotency checking on your end
3. **Webhook URL stored per payment** - URL is provided when creating payment request

### Recommended Security Measures

1. **Use HTTPS** for your webhook URLs
2. **Validate transaction_id** to prevent duplicate processing
3. **Store transaction_id** to prevent replay attacks
4. **Verify amount matches** your expected payment amount
5. **Check timestamp** (`approved_at`) is recent

### Future Enhancements (Optional)

We could add:
- **HMAC signature** in `X-Webhook-Signature` header
- **API key authentication** in `X-API-Key` header
- **Timestamp validation** with `X-Webhook-Timestamp` header

---

## Testing Webhooks

### Using cURL

```bash
# Test approved payment webhook
curl -X POST https://your-webhook-url.com/payment-webhook \
  -H "Content-Type: application/json" \
  -H "User-Agent: EmailPaymentGateway/1.0" \
  -d '{
    "success": true,
    "status": "approved",
    "transaction_id": "TXN-TEST-123",
    "amount": 5000.00,
    "payer_name": "Test User",
    "bank": "Test Bank",
    "approved_at": "2026-01-10T12:30:45.000000Z",
    "message": "Payment has been verified and approved"
  }'
```

### Using Webhook Testing Services

1. **Webhook.site** - https://webhook.site (shows incoming webhooks)
2. **RequestBin** - https://requestbin.com (temporary webhook endpoints)
3. **ngrok** - https://ngrok.com (expose local server for testing)

### Using Postman

1. Create a new POST request
2. URL: Your webhook endpoint
3. Headers:
   - `Content-Type: application/json`
   - `User-Agent: EmailPaymentGateway/1.0`
4. Body (raw JSON): Use one of the example payloads above
5. Send request

---

## Webhook Delivery Status

### Success Response

Your endpoint should return **HTTP 200-299**:

```json
{
  "success": true,
  "message": "Webhook received"
}
```

### Failure Handling

- **HTTP 4xx (Client Errors):** Webhook is logged as failed, **not retried**
- **HTTP 5xx (Server Errors):** Webhook is **retried** up to 3 times
- **Timeout (30 seconds):** Webhook is **retried** up to 3 times
- **Network Errors:** Webhook is **retried** up to 3 times

### Webhook Logs

Webhook delivery status is logged in:
- **Laravel logs:** `storage/logs/laravel.log`
- **Transaction logs:** `transaction_logs` table (if enabled)
- **Admin panel:** Transaction Logs section (if implemented)

---

## Common Use Cases

### 1. Update Order Status

```php
if ($payload['status'] === 'approved' && $payload['success'] === true) {
    $order = Order::where('transaction_id', $payload['transaction_id'])->first();
    if ($order) {
        $order->update([
            'status' => 'paid',
            'paid_at' => $payload['approved_at'],
            'payment_amount' => $payload['amount'],
        ]);
    }
}
```

### 2. Send Email Notification

```php
if ($payload['status'] === 'approved') {
    Mail::to($customer->email)->send(new PaymentConfirmedMail($payload));
}
```

### 3. Update Customer Balance

```php
if ($payload['status'] === 'approved') {
    $customer = Customer::where('transaction_id', $payload['transaction_id'])->first();
    if ($customer) {
        $customer->increment('balance', $payload['amount']);
    }
}
```

### 4. Trigger Internal Processes

```php
if ($payload['status'] === 'approved') {
    // Trigger fulfillment
    dispatch(new FulfillOrderJob($payload['transaction_id']));
    
    // Send SMS
    SMS::send($customer->phone, "Payment of ₦{$payload['amount']} confirmed!");
    
    // Update analytics
    Analytics::track('payment_approved', $payload);
}
```

---

## Troubleshooting

### Webhook Not Received

1. **Check webhook URL** is correct and accessible
2. **Verify HTTPS** is working (if using HTTPS)
3. **Check server logs** for webhook sending errors
4. **Test endpoint** manually with cURL
5. **Check firewall** isn't blocking requests

### Webhook Received But Payment Not Processed

1. **Validate transaction_id** - may be duplicate
2. **Check payment status** - ensure status is 'approved'
3. **Verify amount** matches expected payment
4. **Check your logs** for processing errors

### Webhook Timeout

1. **Process webhook asynchronously** - return 200 immediately, process in background
2. **Optimize webhook handler** - reduce processing time
3. **Use queue system** - queue payment processing for later

---

## Additional Resources

- **Transaction Logs:** Check admin panel for webhook delivery status
- **Laravel Logs:** `storage/logs/laravel.log` for detailed webhook logs
- **Payment Model:** See `app/Models/Payment.php` for payment structure
- **Webhook Job:** See `app/Jobs/SendWebhookNotification.php` for implementation
