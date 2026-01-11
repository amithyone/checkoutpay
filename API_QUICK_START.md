# API Quick Start Guide

## Overview

This guide provides a quick overview of the Payment Gateway API. For detailed documentation, see [API_DOCUMENTATION.md](./API_DOCUMENTATION.md).

## Key Endpoints

### 1. Get Account Number (Create Payment Request)

**Endpoint**: `POST /api/v1/payment-request`

**Headers**:
```
X-API-Key: pk_your_api_key_here
Content-Type: application/json
```

**Request Body**:
```json
{
  "name": "John Doe",
  "amount": 5000.00,
  "service": "PRODUCT-123",
  "webhook_url": "https://yourwebsite.com/webhook/payment-status"
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "transaction_id": "TXN-1234567890-abc123",
    "account_number": "1234567890",
    "account_details": {
      "account_name": "Your Business Name",
      "bank_name": "GTBank"
    },
    "amount": 5000.00,
    "status": "pending"
  }
}
```

### 2. Check Transaction Status

**Endpoint**: `GET /api/v1/payment/{transaction_id}`

**Headers**:
```
X-API-Key: pk_your_api_key_here
```

**Response**:
```json
{
  "success": true,
  "data": {
    "transaction_id": "TXN-1234567890-abc123",
    "status": "approved",
    "amount": 5000.00,
    "matched_at": "2024-01-01T12:05:30.000000Z"
  }
}
```

### 3. Webhook Notifications

Your webhook URL receives POST requests when payment status changes.

**Approved Payment Webhook**:
```json
{
  "success": true,
  "status": "approved",
  "transaction_id": "TXN-1234567890-abc123",
  "amount": 5000.00,
  "payer_name": "john doe",
  "bank": "GTB",
  "account_number": "1234567890",
  "approved_at": "2024-01-01T12:05:30.000000Z",
  "message": "Payment has been verified and approved"
}
```

**Rejected Payment Webhook**:
```json
{
  "success": false,
  "status": "rejected",
  "transaction_id": "TXN-1234567890-abc123",
  "amount": 5000.00,
  "rejected_at": "2024-01-01T13:00:00.000000Z",
  "reason": "Payment expired",
  "message": "Payment has been rejected"
}
```

## Required Fields

### Payment Request
- **name** (required): Payer name as it appears on bank account
- **amount** (required): Payment amount (minimum: 0.01)
- **service** (optional): Service/product identifier
- **webhook_url** (required): Your webhook URL (must be from approved domain)

## URL Approval

Before you can use the API:
1. Register your business account
2. Provide your webhook URL domain for approval
3. Wait for admin approval
4. Once approved, you can start making API requests

## Sample Payment Page

A sample payment page is provided at `resources/views/sample-payment-page.html`. This page:
- Matches your application's design style (Tailwind CSS)
- Collects name and amount fields
- Optionally collects service/product ID
- Displays account details after request
- Shows payment instructions

**Important**: The sample page includes the API key in client-side code for demonstration only. In production, always make API calls from your backend server to protect your API key.

## Integration Flow

1. **Customer initiates payment** on your website
2. **Your backend** calls `POST /api/v1/payment-request` with customer details
3. **API returns** account number and transaction ID
4. **Display account details** to customer
5. **Customer transfers** money to the account number
6. **System verifies** payment automatically
7. **Webhook sent** to your webhook URL when payment is confirmed
8. **Your system** processes the payment (update order status, etc.)

## Error Handling

Always check for errors:

```javascript
if (!response.ok) {
  const error = await response.json();
  console.error('Error:', error.message);
  // Handle error
}
```

Common errors:
- `401`: Invalid or inactive API key
- `422`: Validation error (missing/invalid fields)
- `429`: Rate limit exceeded
- `404`: Transaction not found

## Next Steps

1. Read the full [API Documentation](./API_DOCUMENTATION.md)
2. Review the [Sample Payment Page](./resources/views/sample-payment-page.html)
3. Implement API integration on your backend
4. Test with small amounts first
5. Set up webhook endpoint to receive notifications
