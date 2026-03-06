# CheckoutPay Payment Gateway for WooCommerce

A WordPress plugin that integrates CheckoutPay payment gateway with WooCommerce, allowing store owners to accept payments through email-based payment verification.

## Features

- ✅ Seamless WooCommerce integration
- ✅ Secure payment processing via CheckoutPay API
- ✅ Email-based payment verification
- ✅ Webhook support for automatic order status updates
- ✅ Test mode for development
- ✅ Real-time payment status checking
- ✅ Customizable payment instructions

## Installation

1. Download or clone this repository
2. Upload the `checkoutpay-gateway` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin panel
4. Ensure WooCommerce is installed and activated
5. Go to **WooCommerce > Settings > Payments**
6. Enable **CheckoutPay** payment gateway
7. Configure your API URL and API Key
8. Save changes

## Configuration

### API Settings

1. **API URL**: Your CheckoutPay API base URL including version (e.g. `https://check-outpay.com/api/v1`)
2. **API Key**: Your CheckoutPay API key (from your CheckoutPay dashboard)
3. **Test Mode**: Enable to use test credentials

### Webhook Setup

Configure your site’s webhook URL in CheckoutPay (per-website or business settings):

```
https://your-site.com/?wc-api=wc_checkoutpay_webhook
```

The plugin accepts POST requests with `event`, `transaction_id`, `status`, `amount`, `received_amount`, `charges`, etc. No `reference` field is required.

## API Integration

The plugin uses these CheckoutPay API endpoints:

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/v1/payment-request` | Create payment (returns `transaction_id`, account details, charges) |
| GET | `/api/v1/payment/{transactionId}` | Check payment status |
| PATCH | `/api/v1/payment/{transactionId}/amount` | Correct amount for a pending payment (then re-match emails) |

### Create payment (payment request)

Request body: `name`, `amount`, `service`, `webhook_url` (all required as per API).  
Response: `success`, `data` with `transaction_id`, `amount`, `account_number`, `account_name`, `bank_name`, `status`, `expires_at`, `charges`, etc.

### Check payment status

`GET /api/v1/payment/{transactionId}` with header `X-API-Key`.  
Response: `success`, `data` with `transaction_id`, `amount`, `status` (`pending` | `approved` | `rejected`), `matched_at`, `approved_at`, `charges`, etc.

### Correct transaction amount

If the order amount was wrong and the customer paid a different sum:

```http
PATCH /api/v1/payment/{transactionId}/amount
Content-Type: application/json
X-API-Key: your_api_key

{ "new_amount": 7500.00 }
```

Only pending, non-expired payments can be updated. Recommended flow: call PATCH to correct the amount, then poll `GET /payment/{transactionId}` until status changes or wait for the webhook. The webhook payload when a payment is approved is **unchanged** when the payment was matched after an amount correction (same `payment.approved` payload).

**In the plugin:** On the order thank-you page, if the order is still pending, the customer sees a "Paid a different amount?" section where they can enter the actual amount they paid and click "Update amount & check status". The plugin calls the amount-correction API and then re-checks status.

### Webhook payload (payment confirmation)

When a payment is approved, CheckoutPay POSTs to your webhook URL with a JSON body like below. This includes payments that were matched after an amount correction—the payload is the same.

```json
{
  "event": "payment.approved",
  "transaction_id": "TXN-...",
  "status": "approved",
  "amount": 5000.00,
  "received_amount": 5000.00,
  "payer_name": "John Doe",
  "bank": "GTBank",
  "payer_account_number": "0123456789",
  "account_number": "0987654321",
  "is_mismatch": false,
  "mismatch_reason": null,
  "charges": { "percentage": 50, "fixed": 50, "total": 100, "business_receives": 4900 },
  "timestamp": "2024-01-15T10:35:00Z",
  "email_data": {}
}
```

Use `transaction_id` to find the order; use `status === 'approved'` (or `event === 'payment.approved'`) to mark the order paid. Use `received_amount` and `charges.business_receives` for reconciliation.

## Development

### Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+

### File Structure

```
checkoutpay-gateway/
├── checkoutpay-gateway.php (Main plugin file)
├── includes/
│   └── class-checkoutpay-gateway.php (Gateway class)
├── assets/
│   └── images/
│       └── checkoutpay-logo.png
├── readme.txt
└── README.md
```

## Support

For support, please contact CheckoutPay support or visit https://checkoutpay.com

## License

GPL v2 or later
