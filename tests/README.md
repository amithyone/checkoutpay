# Tests

## Running tests

From the project root with a full Laravel app (including `.env` and database configured for testing):

```bash
php artisan test
# or
./vendor/bin/phpunit
```

To run only the payment amount correction API tests:

```bash
php artisan test tests/Feature/Api/PaymentAmountCorrectionTest.php
```

## Payment amount correction tests

`Tests\Feature\Api\PaymentAmountCorrectionTest` verifies that:

1. **PATCH /api/v1/payment/{transactionId}/amount** updates a pending payment's amount, recalculates charges, returns the full payment resource, and dispatches `CheckPaymentEmails` so the matching engine re-scans for emails with the new amount.
2. Non-pending payments cannot be updated (400).
3. Unknown transaction IDs return 404.

These tests use `RefreshDatabase` and `Bus::fake()` to assert that `CheckPaymentEmails` is dispatched after a successful amount update.
