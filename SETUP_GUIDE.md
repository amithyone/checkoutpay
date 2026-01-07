# Complete Setup Guide

## 🚀 Quick Start

### 1. Install Dependencies
```bash
composer install
```

### 2. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure Email (Gmail)
Edit `.env`:
```env
EMAIL_USER=your-email@gmail.com
EMAIL_PASSWORD=your-app-password
```

See [GMAIL_SETUP.md](GMAIL_SETUP.md) for detailed Gmail setup.

### 4. Run Migrations
```bash
php artisan migrate
```

### 5. Seed Initial Data
```bash
# Create admin users
php artisan db:seed --class=AdminSeeder

# Create sample pool account numbers
php artisan db:seed --class=AccountNumberSeeder
```

### 6. Create Your First Business
```bash
php artisan tinker
```

```php
$business = \App\Models\Business::create([
    'name' => 'Your Business Name',
    'email' => 'business@example.com',
    'is_active' => true,
]);

echo "API Key: " . $business->api_key . "\n";
```

### 7. Start Services
```bash
# Terminal 1: Start server
php artisan serve

# Terminal 2: Start queue worker
php artisan queue:work

# Terminal 3: Start scheduler
php artisan schedule:work
```

## 📋 Admin Panel Access

1. Go to: `http://localhost:8000/admin`
2. Login with:
   - Email: `admin@paymentgateway.com`
   - Password: `password`
3. ⚠️ **Change password immediately in production!**

## 🔑 API Usage

### Get Your API Key
- Admin Panel → Businesses → View Business → Copy API Key
- Or check database: `businesses` table → `api_key` column

### Create Payment Request
```bash
curl -X POST http://localhost:8000/api/v1/payment-request \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key-here" \
  -d '{
    "amount": 5000,
    "payer_name": "John Doe",
    "webhook_url": "https://yourwebsite.com/webhook"
  }'
```

**Response includes assigned account number:**
```json
{
  "success": true,
  "data": {
    "account_number": "1234567890",
    "account_details": {
      "account_name": "Payment Gateway Pool 1",
      "bank_name": "GTB"
    }
  }
}
```

### Create Withdrawal Request
```bash
curl -X POST http://localhost:8000/api/v1/withdrawal \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key-here" \
  -d '{
    "amount": 10000,
    "account_number": "9876543210",
    "account_name": "Your Account Name",
    "bank_name": "GTB"
  }'
```

### Check Balance
```bash
curl http://localhost:8000/api/v1/balance \
  -H "X-API-Key: your-api-key-here"
```

## 🏦 Account Number Management

### Pool Accounts
- Shared accounts available to all businesses
- Automatically assigned when business has no specific account
- Least-used account is selected first

### Business-Specific Accounts
- Dedicated accounts for specific businesses
- Takes priority over pool accounts
- Can have multiple accounts per business

### How Assignment Works
1. Check if business has specific account → Use it
2. If not → Assign from pool (least used)
3. Increment usage count

## 💰 Balance Management

- **Credited**: When payment is approved
- **Debited**: When withdrawal is approved
- **View**: Admin panel or API endpoint

## 📊 Features Overview

### Admin Panel
- ✅ Dashboard with statistics
- ✅ Account number management
- ✅ Business management
- ✅ Payment management
- ✅ Withdrawal request management

### API Features
- ✅ Payment request with auto account assignment
- ✅ Withdrawal requests
- ✅ Balance checking
- ✅ Payment status tracking

### Automatic Features
- ✅ Email monitoring (every 30 seconds)
- ✅ Payment matching
- ✅ Balance updates
- ✅ Webhook notifications
- ✅ Payment expiration (24 hours)

## 🔒 Security

1. **Change default admin passwords**
2. **Use strong API keys** (auto-generated)
3. **Enable HTTPS in production**
4. **Use App Passwords for Gmail** (not regular passwords)
5. **Rotate API keys periodically**

## 📝 Next Steps

1. Create businesses via admin panel
2. Add account numbers (pool or business-specific)
3. Test payment flow
4. Configure webhook endpoints
5. Monitor via admin dashboard

See [ADMIN_PANEL.md](ADMIN_PANEL.md) for detailed admin panel documentation.
