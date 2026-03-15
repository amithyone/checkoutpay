# Memory (RAM) Management – Checkout App

## Already in place

- **Match runs (manual + global cron + MatchController):** Batch limits on emails and payments (config: `match_batch_size_emails`, `match_batch_size_payments`). Only **today’s** unmatched emails **with amount** are used; payments whose **payer_name** contains "checkout" are excluded.
- **PaymentMatchingService:** `matchPaymentToStoredEmail()` and `reverseSearchPaymentInEmails()` cap potential emails per payment (`match_per_payment_email_limit`). `matchEmail()` caps potential payments per email.
- **CheckPaymentEmails job:** Potential emails per payment are limited by `match_per_payment_email_limit`.
- **Config:** `config/payment.php` defines `match_batch_size_payments`, `match_batch_size_emails`, `match_per_payment_email_limit` (env: `MATCH_BATCH_SIZE_PAYMENTS`, `MATCH_BATCH_SIZE_EMAILS`, `MATCH_PER_PAYMENT_EMAIL_LIMIT`).
- **Match log:** Auto-clear when unmatched match attempts reach 100.
- **Inbox list:** ProcessedEmail index uses `select()` without `text_body`/`html_body` to reduce RAM per row.
- **Match loops:** `unset($processedEmail)` / `unset($payment)` at end of each iteration in match routes to help GC.
- **Peak memory:** Master cron and global match cron log `peak_memory_mb` (and global match includes it in JSON results).

---

## Deployment / ops checklist (no code changes)

Do these on the server or in your deployment config.

**1. Tune via .env**  
Add or edit in `.env` (see `.env.example`):

```env
# Optional: override defaults 200 / 300 / 100 if your server has less RAM
MATCH_BATCH_SIZE_PAYMENTS=200
MATCH_BATCH_SIZE_EMAILS=300
MATCH_PER_PAYMENT_EMAIL_LIMIT=100
```

**2. Queue workers**  
Run workers with a restart so memory is freed periodically:

```bash
php artisan queue:work --max-jobs=500
# or
php artisan queue:work --max-time=3600
```

Example Supervisor config (`/etc/supervisor/conf.d/checkout-queue.conf`):

```ini
[program:checkout-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/checkout/artisan queue:work --sleep=3 --max-jobs=500
directory=/var/www/checkout
autostart=true
autorestart=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/checkout-queue.log
```

**3. PHP limits**  
In `php.ini` or `.user.ini` (e.g. for web):

```ini
memory_limit=512M
max_execution_time=60
```

For CLI (cron/queue) you can use higher limits if needed:

```ini
memory_limit=1G
max_execution_time=300
```

**4. Cache (Redis)**  
If using Redis for cache, set a max memory and eviction policy in `redis.conf`:

```
maxmemory 256mb
maxmemory-policy allkeys-lru
```

In `.env`: `CACHE_STORE=redis` (and ensure Redis connection is configured).

---

## What Can Eat RAM

### 1. **Match crons / match routes (biggest)**

- **Where:** `routes/web.php` (manual match + global match cron), `MatchController`, `ProcessedEmailController` (checkMatch).
- **What:** Loading many `ProcessedEmail` (with `text_body`/`html_body`) and `Payment` with `->get()` can use hundreds of MB.
- **Mitigated:** Batch limits via config; only today’s emails with amount; payer_name containing "checkout" excluded. See "Already in place" above.

### 2. **ProcessedEmail full rows**

- **Where:** Any `ProcessedEmail::...->get()` without `select()` (e.g. `fromWhitelisted()->where(...)->get()`).
- **What:** Loads every column, including `text_body`, `html_body`, `extracted_data`.
- **Why it hurts:** Same as above – large text columns multiply with row count.
- **Fix:** Where you only need metadata (id, subject, from_email, amount, email_date), use `->select(['id', 'subject', ...])->get()`. Where you need bodies for extraction, keep batching (limit/chunk) so you never load 10k+ full emails at once.

### 3. **Queue worker (long‑running process)**

- **Where:** `php artisan queue:work` (or supervisor running it 24/7).
- **What:** One PHP process handles many jobs; PHP can hold onto memory between jobs (e.g. static caches, leaked references).
- **Why it hurts:** RAM grows over time and is only freed when the worker process restarts.
- **Fix:**
  - Restart worker after N jobs:  
    `php artisan queue:work --max-jobs=500` (or 1000).
  - Or use `--max-time=3600` (restart every hour).
  - In production, run the worker under Supervisor with `numprocs=1` and the above flags so it restarts regularly.

### 4. **Cache**

- **Where:** `AccountNumberService` (pool/pending/last-used), `Setting::get()`, other `Cache::remember`/`Cache::put`.
- **What:** Data stored in cache (file/Redis/database).
- **Why it can hurt:** If driver is `array` or in-memory, or if Redis has no eviction and many keys, cache can grow. Your app uses TTLs (e.g. 300s, 24h), so growth is bounded unless you add unbounded keys.
- **Fix:** Prefer Redis with `maxmemory` and `allkeys-lru` (or similar). Avoid long TTLs for very large values. Keep using TTLs for all cache keys.

### 5. **Other large `->get()` calls**

- **Where:** e.g. `Payment::pending()->...->get()`, `ProcessedEmail::fromWhitelisted()->...->get()`, `Business::...->get()`, some dashboard queries.
- **What:** Loading full collections with no `limit()` or `chunk()`.
- **Why it hurts:** All rows and their columns are loaded into PHP memory at once.
- **Fix:** Add `->limit(N)` for one-off operations, or use `->chunk(200, function ($items) { ... })` when processing in bulk. For dashboards, keep limits (e.g. “last 10”) and avoid “all” without pagination.

### 6. **Whitelist scope**

- **Where:** `ProcessedEmail::scopeFromWhitelisted()`.
- **What:** Loads all active `WhitelistedEmailAddress` with `->get()` to build the query.
- **Why it’s usually fine:** Whitelist is small (tens of rows, small columns). Not a major RAM consumer unless you have thousands of whitelist entries.

---

## Recommended Changes (in order of impact)

1. **Cap match runs (web.php + MatchController)**  
   - Limit number of **unmatched emails** and **pending payments** per run (e.g. 300 emails, 200 payments).  
   - Process only the most recent (e.g. `latest()->limit(300)`).  
   - Prevents one cron/request from loading the entire table.

2. **Queue worker restarts**  
   - Use `queue:work --max-jobs=500` (or similar) and/or `--max-time=3600` so the worker process restarts and frees memory periodically.

3. **Chunk or limit in heavy commands**  
   - Any artisan command that loads many `ProcessedEmail` or `Payment` rows should use `chunk()` or `limit()` so no single run loads 10k+ full rows.

4. **Select only needed columns where possible**  
   - For “list” or “pick one” queries on `ProcessedEmail`, avoid selecting `text_body`/`html_body` if not needed. Use `select([...])` to reduce memory per row.

5. **PHP limits**  
   - `memory_limit` (e.g. 256M–512M for web, 512M–1G for cron/queue if needed).  
   - `max_execution_time` for web/cron so a single run can’t run forever.

6. **Monitor**  
   - Log or measure peak memory in critical routes/crons (e.g. `memory_get_peak_usage(true)` at end of match cron).  
   - Use queue worker restarts and batch limits first; then tune PHP limits if needed.

---

## Additional improvements you can make

- **Tune batch sizes:** Set `MATCH_BATCH_SIZE_PAYMENTS`, `MATCH_BATCH_SIZE_EMAILS`, `MATCH_PER_PAYMENT_EMAIL_LIMIT` in `.env` if default 200/300/100 are too high or too low for your server.
- **Queue worker restarts:** Run `php artisan queue:work --max-jobs=500` or `--max-time=3600` (e.g. via Supervisor) so workers restart and release memory.
- **PHP limits:** Set `memory_limit` (e.g. 256M–512M for web, 512M–1G for queue/cron) and `max_execution_time` where appropriate.
- **Unset in long loops:** In very long `foreach` over emails/payments, you can `unset($item)` at the end of each iteration to help the garbage collector (optional; only if you still see growth).
- **Select only needed columns:** For queries that only need metadata (e.g. listing emails without body), use `->select(['id', 'subject', 'from_email', 'amount', 'email_date', ...])` and avoid loading `text_body`/`html_body` until needed.
- **Cache driver:** Prefer Redis with `maxmemory` and an eviction policy (`allkeys-lru` or similar) instead of in-memory or unbounded file cache.
- **Artisan commands:** Any command that loads many `ProcessedEmail` or `Payment` rows should use `->chunk(200, ...)` or `->limit(N)`.

---

## Quick reference

| Area                    | Risk        | Action / status                             |
|-------------------------|-------------|---------------------------------------------|
| Match cron/routes       | Very high  | Done: batch limits + today-only; config in `payment.php` |
| PaymentMatchingService  | High       | Done: per-payment/per-email limits         |
| CheckPaymentEmails job  | Medium     | Done: limit potential emails per payment   |
| ProcessedEmail queries  | High       | Done: inbox list uses select without body columns |
| Queue worker            | Medium     | Use `--max-jobs=500` or `--max-time=3600` |
| Cache                   | Low–medium | Use TTLs; Redis + maxmemory if in-memory    |
| Other get()             | Medium     | Prefer limit/chunk; avoid “all” without cap  |
