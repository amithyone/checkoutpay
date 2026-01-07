# Email Payment Gateway - Laravel

A robust, production-ready email-based payment gateway built with Laravel 10. Automatically monitors email inbox for bank transfer notifications and verifies payments by matching transaction details.

## 🏗️ Built With Laravel Best Practices

- ✅ **Eloquent ORM** - Clean database interactions
- ✅ **Migrations** - Version-controlled database schema
- ✅ **Jobs & Queues** - Asynchronous email processing
- ✅ **Events & Listeners** - Decoupled webhook notifications
- ✅ **Service Classes** - Business logic separation
- ✅ **Form Requests** - Request validation
- ✅ **API Resources** - Consistent API responses
- ✅ **Scheduled Commands** - Automated email monitoring
- ✅ **Configuration Files** - Environment-based settings
- ✅ **PSR Standards** - Clean, maintainable code

## 🚀 Features

- 📧 **Automatic Email Monitoring** - Checks inbox every 30 seconds
- 🔍 **Intelligent Payment Extraction** - Parses amount and sender name from emails
- ✅ **Payment Verification** - Matches amount and name (if provided)
- 🔔 **Webhook Notifications** - Sends approval/rejection to your website
- 💾 **Database Storage** - Persistent payment records with Eloquent
- 🎯 **Queue Processing** - Handles emails asynchronously
- 🔄 **Retry Logic** - Automatic webhook retry on failure
- 📊 **API Endpoints** - RESTful API for payment management

## 📋 Requirements

- PHP >= 8.1
- Composer
- SQLite (default) or MySQL/PostgreSQL
- Email account (Gmail recommended)

## 🛠️ Installation

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

### 3. Configure Email Settings

Edit `.env` file:

```env
EMAIL_HOST=imap.gmail.com
EMAIL_PORT=993
EMAIL_ENCRYPTION=ssl
EMAIL_VALIDATE_CERT=false
EMAIL_USER=your-email@gmail.com
EMAIL_PASSWORD=your-16-character-app-password
```

**📧 Gmail Setup:**
- ✅ **Gmail is fully supported!** See detailed guide: [GMAIL_SETUP.md](GMAIL_SETUP.md)
- Quick steps:
  1. Enable 2-Factor Authentication on your Google Account
  2. Generate App Password: https://myaccount.google.com/apppasswords
  3. Use the 16-character App Password (not your regular password)
  4. Copy it to `EMAIL_PASSWORD` in `.env`

### 4. Setup Database

Run migrations:

```bash
php artisan migrate
```

### 5. Setup Queue Worker

The system uses queues for processing emails. Start the queue worker:

```bash
php artisan queue:work
```

Or use supervisor/systemd for production.

### 6. Start Scheduler

The email monitoring runs via Laravel's scheduler. Add this to your cron:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Or for development, run:

```bash
php artisan schedule:work
```

## 📡 API Endpoints

### Base URL
```
http://localhost:8000/api/v1
```

### 1. Submit Payment Request

**POST** `/payment-request`

Request:
```json
{
  "amount": 5000,
  "payer_name": "emaka ofo",
  "bank": "GTB",
  "webhook_url": "https://yourwebsite.com/webhook/payment-status",
  "transaction_id": "optional-custom-id"
}
```

Response:
```json
{
  "success": true,
  "message": "Payment request received and monitoring started",
  "data": {
    "transaction_id": "TXN-1234567890-abc123",
    "amount": 5000,
    "payer_name": "emaka ofo",
    "bank": "GTB",
    "webhook_url": "https://yourwebsite.com/webhook/payment-status",
    "status": "pending",
    "created_at": "2024-01-01T12:00:00.000Z"
  }
}
```

### 2. Get Payment Status

**GET** `/payment/{transactionId}`

Response:
```json
{
  "success": true,
  "data": {
    "transaction_id": "TXN-1234567890-abc123",
    "amount": 5000,
    "status": "approved",
    "matched_at": "2024-01-01T12:05:00.000Z"
  }
}
```

### 3. Get All Payments

**GET** `/payments?status=pending&from_date=2024-01-01&to_date=2024-01-31`

Query Parameters:
- `status` - Filter by status (pending, approved, rejected)
- `from_date` - Filter from date (YYYY-MM-DD)
- `to_date` - Filter to date (YYYY-MM-DD)
- `per_page` - Results per page (default: 15)

### 4. Health Check

**GET** `/api/health`

## 🔄 How It Works

1. **Website sends payment request** → POST `/api/v1/payment-request`
2. **Gateway stores payment** → Saves to database with `pending` status
3. **Email monitoring** → Scheduled command checks inbox every 30 seconds
4. **Email processing** → New emails are queued for processing
5. **Payment matching** → Service compares email data with pending payments
6. **Payment approval** → If matched, payment status updated to `approved`
7. **Event dispatched** → `PaymentApproved` event fired
8. **Webhook sent** → Listener sends webhook notification to your website

## 🎯 Payment Matching Logic

- **Amount**: Must match exactly (with 0.01 tolerance for rounding)
- **Payer Name**: 
  - If provided in payment request → Must match exactly (case-insensitive)
  - If not provided → Any name is accepted
- **Rejection**: If amount matches but name doesn't match → Payment rejected

## 📧 Webhook Payload

When payment is approved, your webhook URL receives:

```json
{
  "success": true,
  "status": "approved",
  "transaction_id": "TXN-1234567890-abc123",
  "amount": 5000,
  "payer_name": "emaka ofo",
  "bank": "GTB",
  "approved_at": "2024-01-01T12:05:00.000Z",
  "message": "Payment has been verified and approved"
}
```

## 🏗️ Project Structure

```
app/
├── Console/Commands/
│   └── MonitorEmails.php          # Scheduled email monitoring
├── Events/
│   └── PaymentApproved.php        # Payment approval event
├── Http/
│   ├── Controllers/Api/
│   │   └── PaymentController.php  # API endpoints
│   ├── Requests/
│   │   └── PaymentRequest.php     # Form validation
│   └── Resources/
│       └── PaymentResource.php    # API response formatting
├── Jobs/
│   ├── ProcessEmailPayment.php    # Email processing job
│   └── SendWebhookNotification.php # Webhook sending job
├── Listeners/
│   └── SendPaymentWebhook.php     # Event listener
├── Models/
│   └── Payment.php                # Eloquent model
└── Services/
    ├── PaymentService.php         # Payment creation logic
    └── PaymentMatchingService.php # Payment matching logic

config/
└── payment.php                    # Payment gateway config

database/
└── migrations/
    └── create_payments_table.php  # Database schema
```

## 🔧 Configuration

All configuration is in `config/payment.php`:

- Email settings (host, port, credentials)
- Payment matching tolerance
- Webhook timeout and retry attempts

## 🧪 Testing

### Manual Testing

1. **Test Gmail Connection** (recommended first):
```bash
php artisan payment:test-gmail
```

2. Start the server:
```bash
php artisan serve
```

3. Start queue worker:
```bash
php artisan queue:work
```

4. Start scheduler:
```bash
php artisan schedule:work
```

5. Submit a payment request:
```bash
curl -X POST http://localhost:8000/api/v1/payment-request \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 5000,
    "payer_name": "emaka ofo",
    "webhook_url": "https://yourwebsite.com/webhook"
  }'
```

5. Send a test email to your configured email address with payment details

## 🚨 Production Considerations

1. **Queue Worker**: Use supervisor/systemd to keep queue worker running
2. **Scheduler**: Add Laravel scheduler to cron
3. **Database**: Use MySQL/PostgreSQL instead of SQLite
4. **Caching**: Configure Redis/Memcached for better performance
5. **Logging**: Configure proper log channels
6. **Security**: Add API authentication (Sanctum/Passport)
7. **Rate Limiting**: Already configured in API routes
8. **HTTPS**: Use HTTPS for webhook URLs

## 📝 License

MIT

## 🤝 Contributing

Contributions are welcome! Please follow Laravel coding standards and PSR-12.
