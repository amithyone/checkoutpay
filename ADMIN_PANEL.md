# Admin Panel Documentation

## Overview

The admin panel provides comprehensive management tools for the Email Payment Gateway system.

## Access

- **URL**: `/admin`
- **Default Login**: 
  - Email: `admin@paymentgateway.com`
  - Password: `password` (⚠️ Change in production!)

## Features

### 1. Dashboard
- Overview statistics
- Recent payments
- Pending withdrawals
- Account number status

### 2. Account Number Management

#### Pool Account Numbers
- Shared account numbers available to all businesses
- Automatically assigned when business doesn't have specific account
- Least-used account is selected first

#### Business-Specific Account Numbers
- Dedicated account numbers for specific businesses
- Takes priority over pool accounts
- One business can have multiple account numbers

**CRUD Operations:**
- Create new account numbers (pool or business-specific)
- Edit account details
- Activate/Deactivate accounts
- View usage statistics

### 3. Business Management

**Features:**
- Create new businesses
- View business details
- Manage business accounts
- Regenerate API keys
- View payment history
- View withdrawal history
- Manage balance

**Business Balance:**
- Automatically updated when payments are approved
- Deducted when withdrawals are approved

### 4. Payment Management

**Features:**
- View all payments
- Filter by status, business, date range
- View payment details
- See account number assigned
- View email matching data

### 5. Withdrawal Request Management

**Workflow:**
1. Business submits withdrawal request via API
2. Admin reviews request in admin panel
3. Admin approves or rejects
4. If approved, balance is deducted
5. Admin can mark as processed after transfer

**Actions:**
- Approve withdrawal
- Reject withdrawal (with reason)
- Mark as processed
- View withdrawal history

## API Integration

### For Businesses

#### 1. Get Account Number for Payment

When creating a payment request, the system automatically assigns an account number:

```bash
POST /api/v1/payment-request
Headers:
  X-API-Key: your-api-key

Body:
{
  "amount": 5000,
  "payer_name": "John Doe",
  "webhook_url": "https://yourwebsite.com/webhook"
}

Response includes:
{
  "data": {
    "account_number": "1234567890",
    "account_details": {
      "account_name": "Payment Gateway Pool 1",
      "bank_name": "GTB"
    }
  }
}
```

#### 2. Create Withdrawal Request

```bash
POST /api/v1/withdrawal
Headers:
  X-API-Key: your-api-key

Body:
{
  "amount": 10000,
  "account_number": "9876543210",
  "account_name": "Your Account Name",
  "bank_name": "GTB"
}
```

#### 3. Check Balance

```bash
GET /api/v1/balance
Headers:
  X-API-Key: your-api-key
```

## Account Number Assignment Logic

1. **Check Business-Specific Account**: If business has active account number(s), use the primary one
2. **Fallback to Pool**: If no business-specific account, assign from pool (least used first)
3. **Increment Usage**: Usage count is incremented for tracking

## Security

- Admin authentication required for all admin routes
- API key authentication for business API endpoints
- Role-based access control (Super Admin, Admin, Support)
- Password hashing for admin accounts

## Setup

1. **Run Migrations**:
```bash
php artisan migrate
```

2. **Seed Admin Users**:
```bash
php artisan db:seed --class=AdminSeeder
```

3. **Seed Sample Account Numbers**:
```bash
php artisan db:seed --class=AccountNumberSeeder
```

4. **Create Admin User** (Alternative):
```bash
php artisan tinker
>>> Admin::create([
    'name' => 'Your Name',
    'email' => 'your@email.com',
    'password' => Hash::make('your-password'),
    'role' => 'super_admin',
    'is_active' => true,
]);
```

## Admin Roles

- **Super Admin**: Full access to all features
- **Admin**: Can manage account numbers, businesses, payments, withdrawals
- **Support**: Read-only access, can view but not modify

## Notes

- ⚠️ Change default admin passwords in production!
- Account numbers are soft-deleted (can be restored)
- Business balance is automatically managed
- Withdrawal requests require admin approval
- API keys are auto-generated for new businesses
