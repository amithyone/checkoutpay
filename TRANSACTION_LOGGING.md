# Transaction Logging System

## Overview

Complete transaction logging system that tracks all payment requests, updates, and webhook notifications. Every transaction has a unique ID that is included in all updates.

## ✅ Features

### 1. Unique Transaction IDs
- **Format**: `TXN-{timestamp}-{random}`
- **Uniqueness**: Database-checked to ensure no duplicates
- **Included in**: All payment requests, webhooks, and updates

### 2. Comprehensive Logging

All events are logged with:
- Transaction ID
- Event type
- Description
- Metadata (JSON)
- IP address and user agent
- Timestamp

### 3. Logged Events

#### Payment Events
- ✅ `payment_requested` - When payment request is created
- ✅ `account_assigned` - When account number is assigned
- ✅ `email_received` - When email notification is received
- ✅ `payment_matched` - When payment matches email data
- ✅ `payment_approved` - When payment is approved
- ✅ `payment_rejected` - When payment is rejected
- ✅ `payment_expired` - When payment expires

#### Webhook Events
- ✅ `webhook_sent` - When webhook is sent successfully
- ✅ `webhook_failed` - When webhook fails

#### Withdrawal Events
- ✅ `withdrawal_requested` - When withdrawal is requested
- ✅ `withdrawal_approved` - When withdrawal is approved
- ✅ `withdrawal_rejected` - When withdrawal is rejected
- ✅ `withdrawal_processed` - When withdrawal is marked as processed

## 🔄 Transaction Flow

### Payment Request Flow

1. **Business sends payment request** → `payment_requested` logged
2. **Account number assigned** → `account_assigned` logged
3. **Email received** → `email_received` logged
4. **Payment matched** → `payment_matched` logged
5. **Payment approved** → `payment_approved` logged
6. **Webhook sent** → `webhook_sent` logged (with transaction_id)

### Webhook Payload

**All webhooks include transaction_id:**

```json
{
  "success": true,
  "status": "approved",
  "transaction_id": "TXN-1234567890-abc123",  // Always included
  "amount": 5000,
  "payer_name": "emaka ofo",
  "bank": "GTB",
  "approved_at": "2024-01-01T12:05:00.000Z",
  "message": "Payment has been verified and approved"
}
```

**Rejected Payment:**
```json
{
  "success": false,
  "status": "rejected",
  "transaction_id": "TXN-1234567890-abc123",  // Always included
  "amount": 5000,
  "rejected_at": "2024-01-01T12:05:00.000Z",
  "reason": "Name mismatch",
  "message": "Payment has been rejected"
}
```

## 📊 Admin Panel

### Transaction Logs View

Access: `/admin/transaction-logs`

**Features:**
- View all transaction logs
- Filter by:
  - Transaction ID
  - Event type
  - Business
  - Date range
- View transaction timeline for specific transaction
- See metadata for each event

### Transaction Timeline

View complete history of a transaction:
- All events in chronological order
- Event descriptions
- Metadata details
- IP addresses and user agents

## 🔍 Usage

### View Transaction Logs

```bash
GET /admin/transaction-logs
```

### View Specific Transaction

```bash
GET /admin/transaction-logs/{transactionId}
```

### Filter Logs

```bash
GET /admin/transaction-logs?transaction_id=TXN-123&event_type=payment_approved
```

## 📝 Database Schema

```sql
transaction_logs
- id
- transaction_id (indexed)
- payment_id (nullable, FK)
- business_id (nullable, FK)
- event_type (enum)
- description (text)
- metadata (json)
- ip_address
- user_agent
- created_at
- updated_at
```

## 🎯 Benefits

1. **Complete Audit Trail**: Every action is logged
2. **Transaction Tracking**: Follow complete transaction lifecycle
3. **Debugging**: Easy to trace issues
4. **Compliance**: Full record of all transactions
5. **Analytics**: Can analyze transaction patterns
6. **Webhook Tracking**: Know when webhooks were sent/failed

## 🔧 Implementation

All logging is automatic:
- Payment requests → Auto-logged
- Account assignments → Auto-logged
- Email processing → Auto-logged
- Payment matching → Auto-logged
- Webhook sending → Auto-logged
- Withdrawal actions → Auto-logged

No manual logging required!

## 📋 Example Transaction Timeline

```
1. payment_requested - Payment request created: ₦5,000
2. account_assigned - Account number assigned: 1234567890
3. email_received - Email received for payment matching
4. payment_matched - Payment matched with email data
5. payment_approved - Payment approved: ₦5,000
6. webhook_sent - Webhook sent successfully
```

## 🚀 Next Steps

1. Run migration: `php artisan migrate`
2. Access logs: `/admin/transaction-logs`
3. View transaction: Click on any transaction ID
4. Filter as needed

All transaction updates include the transaction_id for easy tracking! 🎉
