# Memory (RAM) Management – Checkout App

## What Can Eat RAM

### 1. **Match crons / match routes (biggest)**

- **Where:** `routes/web.php` (manual match + global match cron), `MatchController`, `ProcessedEmailController` (checkMatch).
- **What:** They load **all** unmatched `ProcessedEmail` and **all** pending `Payment` with `->get()` with **no limit**.
- **Why it hurts:** Each `ProcessedEmail` includes `text_body` and `html_body` (often 50–500KB+ each). Example: 2,000 unmatched emails × 200KB ≈ **400MB** in one request. Plus pending payments and PHP overhead.
- **Fix:** Process in **bounded batches** (e.g. limit 200–500 emails per run, 100–200 payments). Use `limit()` and/or `chunk()` so no single run loads tens of thousands of rows.

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

## Quick reference

| Area              | Risk        | Action                                      |
|-------------------|------------|---------------------------------------------|
| Match cron/routes | Very high  | Add `limit(300)` (or similar) on emails/payments |
| ProcessedEmail    | High       | Batch + limit; select only needed columns  |
| Queue worker      | Medium     | `--max-jobs=500` or `--max-time=3600`       |
| Cache             | Low–medium | Use TTLs; Redis + maxmemory if in-memory    |
| Other get()       | Medium     | Prefer limit/chunk; avoid “all” without cap  |
