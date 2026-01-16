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

1. **API URL**: Your CheckoutPay API endpoint (e.g., `https://checkoutpay.com/api`)
2. **API Key**: Your CheckoutPay API key (found in your CheckoutPay dashboard)
3. **Test Mode**: Enable to use test credentials

### Webhook Setup

The plugin automatically creates a webhook endpoint at:
```
your-site.com/?wc-api=wc_checkoutpay_webhook
```

Make sure to configure this URL in your CheckoutPay dashboard under Webhook Settings.

## API Integration

The plugin makes API requests to:
- `POST /api/payments` - Create payment
- `GET /api/payments/{id}` - Check payment status

### Payment Request Format

```json
{
  "amount": 1000.00,
  "currency": "NGN",
  "reference": "WC-123-1234567890",
  "customer_email": "customer@example.com",
  "customer_name": "John Doe",
  "callback_url": "https://yoursite.com/?wc-api=wc_checkoutpay_webhook",
  "metadata": {
    "order_id": 123,
    "order_key": "wc_order_abc123"
  }
}
```

### Webhook Payload Format

```json
{
  "reference": "WC-123-1234567890",
  "status": "approved",
  "amount": 1000.00,
  "signature": "optional_signature"
}
```

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
