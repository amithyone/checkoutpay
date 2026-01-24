# Transaction Transfer Feature Control

## Overview
The transaction transfer feature allows super admins to transfer transactions from businesses to the super admin business. This feature can be enabled/disabled via code.

## How to Enable/Disable

### Method 1: Environment Variable (Recommended)
Add to your `.env` file:
```env
TRANSACTION_TRANSFER_ENABLED=true   # Enable
TRANSACTION_TRANSFER_ENABLED=false  # Disable
```

### Method 2: Config File
Edit `/var/www/checkout/config/transaction_transfer.php`:
```php
'enabled' => true,   // Enable
'enabled' => false,  // Disable
```

### Method 3: Database Setting (Runtime)
```php
// Enable
\App\Models\Setting::set('transaction_transfer_enabled', true, 'boolean', 'admin', 'Enable transaction transfer feature');

// Disable
\App\Models\Setting::set('transaction_transfer_enabled', false, 'boolean', 'admin', 'Enable transaction transfer feature');
```

### Method 4: Tinker (Quick Toggle)
```bash
php artisan tinker
```
Then:
```php
// Enable
\App\Models\Setting::set('transaction_transfer_enabled', true, 'boolean');

// Disable
\App\Models\Setting::set('transaction_transfer_enabled', false, 'boolean');

// Check status
\App\Models\Setting::get('transaction_transfer_enabled', config('transaction_transfer.enabled', true));
```

## Priority Order
1. Database setting (`transaction_transfer_enabled`) - highest priority
2. Config file (`config/transaction_transfer.enabled`)
3. Environment variable (`TRANSACTION_TRANSFER_ENABLED`)
4. Default: `true`

## Access Control
- **Only Super Admins** can see and use this feature
- Regular admins, support, and staff **cannot** see or access it
- Feature must be enabled via one of the methods above

## Notes
- When disabled, the transfer button will not appear in the UI
- API endpoints will return 403 if accessed when disabled
- Changes take effect immediately (no cache clearing needed for database/ENV changes)
