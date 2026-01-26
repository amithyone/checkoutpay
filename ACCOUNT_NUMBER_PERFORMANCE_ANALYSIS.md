# Account Number Assignment Performance Analysis

## Overview
This document identifies performance bottlenecks in the account number assignment process that could slow down payment creation and make the site slow.

## Critical Performance Issues

### 🔴 **HIGH PRIORITY - Major Bottlenecks**

#### 1. **moveOrphanedAccountsToPool() Runs on Every Request**
**Location**: `AccountNumberService::assignAccountNumber()` line 19

**Problem**: 
- This method runs a database query **every single time** an account number is assigned
- It queries for orphaned accounts and potentially updates them
- This happens even when there are no orphaned accounts (most of the time)

**Impact**:
- Adds unnecessary database overhead to every payment creation
- Can cause table locks if updates occur frequently
- Slows down the critical path of payment creation

**Current Code**:
```php
public function assignAccountNumber(?Business $business = null): ?AccountNumber
{
    // First, ensure any account numbers that should be in pool are moved there
    $this->moveOrphanedAccountsToPool(); // ❌ Runs every time!
    // ...
}
```

**Solution**:
- Move this to a scheduled cron job (run every hour or daily)
- Or cache the check and only run it periodically
- Or use database triggers/events to handle this automatically

---

#### 2. **Loading ALL Pool Accounts into Memory**
**Location**: `AccountNumberService::assignAccountNumber()` lines 32-35

**Problem**:
- Fetches **all** pool accounts from database every time
- If you have hundreds or thousands of pool accounts, this loads them all into memory
- Uses `->get()` which loads entire collection

**Impact**:
- High memory usage with large account pools
- Slow query execution with many accounts
- Unnecessary data transfer from database

**Current Code**:
```php
$poolAccounts = AccountNumber::pool()
    ->active()
    ->orderBy('id')
    ->get(); // ❌ Loads ALL accounts!
```

**Solution**:
- Cache the pool account list (Redis/Memcached)
- Use pagination or limit if pool is very large
- Consider using a more efficient selection algorithm

---

#### 3. **Querying ALL Pending Payments**
**Location**: `AccountNumberService::assignAccountNumber()` lines 45-54

**Problem**:
- Queries **all** pending payments to find which account numbers are in use
- This query runs on every account assignment
- With thousands of pending payments, this becomes very slow

**Impact**:
- Extremely slow with high volume of pending payments
- Database load increases linearly with pending payment count
- Missing composite index for this specific query pattern

**Current Code**:
```php
$pendingAccountNumbers = \App\Models\Payment::where('status', \App\Models\Payment::STATUS_PENDING)
    ->whereNotNull('account_number')
    ->where(function ($query) {
        $query->whereNull('expires_at')
            ->orWhere('expires_at', '>', now());
    })
    ->pluck('account_number')
    ->unique()
    ->toArray(); // ❌ Loads ALL pending payments!
```

**Missing Index**: 
- Need composite index: `(status, account_number, expires_at)`
- Current indexes don't optimize this query pattern

**Solution**:
- Add composite index: `['status', 'account_number', 'expires_at']`
- Cache pending account numbers (update cache when payments are created/approved)
- Use Redis set to track active account numbers

---

#### 4. **Querying Last Payment Separately**
**Location**: `AccountNumberService::assignAccountNumber()` lines 60-67

**Problem**:
- Separate query to find the last used account number
- Could be combined with the previous query
- Another database round-trip

**Impact**:
- Additional query overhead
- Could be optimized by combining queries

**Current Code**:
```php
$lastPayment = \App\Models\Payment::where('status', \App\Models\Payment::STATUS_PENDING)
    ->whereNotNull('account_number')
    ->where(function ($query) {
        $query->whereNull('expires_at')
            ->orWhere('expires_at', '>', now());
    })
    ->orderBy('created_at', 'desc')
    ->first(); // ❌ Separate query!
```

**Solution**:
- Combine with pending payments query
- Use `max()` or subquery to get last used account
- Cache the last used account number

---

#### 5. **Inefficient Collection Operations**
**Location**: `AccountNumberService::assignAccountNumber()` lines 72, 83-85, 100-117

**Problem**:
- Multiple O(n) operations on collections:
  - `firstWhere()` - searches entire collection
  - `search()` - searches entire collection (twice!)
  - Loop through all accounts to find available one

**Impact**:
- CPU overhead increases with pool size
- Inefficient algorithm (O(n²) complexity in worst case)

**Current Code**:
```php
// Line 72: O(n) search
$lastUsedAccount = $poolAccounts->firstWhere('account_number', $lastUsedAccountNumber);

// Lines 83-85: O(n) search
$lastUsedIndex = $poolAccounts->search(function ($account) use ($lastUsedAccountId) {
    return $account->id === $lastUsedAccountId;
});

// Lines 100-117: O(n) loop
for ($i = 0; $i < $poolCount; $i++) {
    // ...
}
```

**Solution**:
- Use array key mapping for O(1) lookups
- Pre-index accounts by account_number and ID
- Optimize selection algorithm

---

### 🟡 **MEDIUM PRIORITY - Optimization Opportunities**

#### 6. **Business Account Number Checks**
**Location**: `Business::hasAccountNumber()` and `Business::primaryAccountNumber()`

**Problem**:
- `hasAccountNumber()` runs a query every time
- `primaryAccountNumber()` runs another query
- These could be cached or eager loaded

**Impact**:
- Additional queries for businesses with account numbers
- Could be optimized with relationship caching

**Solution**:
- Cache business account number status
- Eager load account numbers when loading business
- Use relationship caching

---

#### 7. **Missing Database Indexes**

**Missing Composite Indexes**:
1. **Payments table**: `(status, account_number, expires_at)` - Critical for pending payments query
2. **Payments table**: `(status, account_number, created_at)` - For last payment query
3. **Account Numbers table**: `(is_pool, is_active, id)` - Already exists but verify it's being used

**Current Indexes** (from migrations):
- ✅ `payments.status` - exists
- ✅ `payments.account_number` - exists  
- ✅ `payments.expires_at` - exists
- ✅ `payments.status + created_at` - exists
- ❌ **Missing**: `payments(status, account_number, expires_at)` - **CRITICAL**

---

### 🟢 **LOW PRIORITY - Nice to Have**

#### 8. **No Caching Strategy**
- Account number assignment results could be cached
- Pool account list could be cached
- Pending account numbers could be cached

#### 9. **No Rate Limiting**
- No protection against rapid-fire account number requests
- Could cause database overload

---

## Performance Impact Summary

### Current Performance (Estimated):
- **Best Case**: ~50-100ms per account assignment (small pool, few pending payments)
- **Worst Case**: 500ms-2s+ per account assignment (large pool, many pending payments)
- **Database Queries**: 4-6 queries per assignment
- **Memory Usage**: Loads all pool accounts + all pending payments into memory

### After Optimizations (Estimated):
- **Best Case**: ~10-20ms per account assignment (with caching)
- **Worst Case**: ~50-100ms per account assignment (without caching)
- **Database Queries**: 1-2 queries per assignment (with caching: 0-1)
- **Memory Usage**: Minimal (only selected account loaded)

---

## Recommended Fix Priority

### **IMMEDIATE (Do Today)**:
1. ✅ Add composite index: `payments(status, account_number, expires_at)`
2. ✅ Move `moveOrphanedAccountsToPool()` to cron job
3. ✅ Cache pending account numbers (Redis)

### **THIS WEEK**:
4. ✅ Optimize collection operations (use array keys for O(1) lookups)
5. ✅ Cache pool account list
6. ✅ Combine pending payments queries

### **THIS MONTH**:
7. ✅ Implement full caching strategy
8. ✅ Add performance monitoring
9. ✅ Optimize Business account number checks

---

## Code Examples for Key Fixes

### Fix 1: Add Missing Index
```php
// Create migration: add_composite_index_to_payments_for_account_assignment.php
Schema::table('payments', function (Blueprint $table) {
    $table->index(['status', 'account_number', 'expires_at'], 'idx_status_account_expires');
});
```

### Fix 2: Cache Pending Account Numbers
```php
// In AccountNumberService
public function assignAccountNumber(?Business $business = null): ?AccountNumber
{
    // Use cache instead of query
    $pendingAccountNumbers = Cache::remember('pending_account_numbers', 60, function () {
        return \App\Models\Payment::where('status', Payment::STATUS_PENDING)
            ->whereNotNull('account_number')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->pluck('account_number')
            ->unique()
            ->toArray();
    });
    
    // Invalidate cache when payment is created/approved
}
```

### Fix 3: Optimize Collection Lookups
```php
// Instead of firstWhere() and search(), use array keys
$poolAccountsById = [];
$poolAccountsByNumber = [];
foreach ($poolAccounts as $account) {
    $poolAccountsById[$account->id] = $account;
    $poolAccountsByNumber[$account->account_number] = $account;
}

// O(1) lookup instead of O(n)
$lastUsedAccount = $poolAccountsByNumber[$lastUsedAccountNumber] ?? null;
```

### Fix 4: Move Orphaned Accounts to Cron
```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        app(AccountNumberService::class)->moveOrphanedAccountsToPool();
    })->hourly();
}
```

---

## Monitoring Recommendations

Add logging to track:
1. Account assignment duration
2. Number of pool accounts loaded
3. Number of pending payments queried
4. Cache hit/miss rates
5. Database query counts

---

## Conclusion

The account number assignment process has several performance bottlenecks that can significantly slow down payment creation, especially under load. The most critical issues are:

1. **Querying all pending payments** without proper indexing
2. **Loading all pool accounts** into memory
3. **Running orphaned account cleanup** on every request
4. **Inefficient collection operations**

Fixing these issues should improve performance by **5-10x** in typical scenarios and **10-50x** under high load.
