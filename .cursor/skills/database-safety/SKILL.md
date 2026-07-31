---
name: database-safety
description: >-
  Enforced for Laravel/DB work: never run db:wipe, migrate:fresh, or full DROP
  without explicit user approval naming command and environment; use safer fixes.
---

# Database safety (non-negotiable)

**Do not run destructive database commands on your own** — not to “fix migrations”, not to “clean state”, not in CI debugging. If the user did not type an explicit instruction that names the exact command **and** the target environment (e.g. “run `migrate:fresh` on my local Docker DB only”), **refuse and suggest safer alternatives**.

## Forbidden unless the user explicitly orders it in writing **and** a backup file was just created

- `php artisan db:wipe` (**never** use this to troubleshoot migrations)
- `php artisan migrate:fresh` / `migrate:fresh --seed`
- `php artisan schema:dump` when used to replace a live schema destructively
- `DROP DATABASE`, `DROP SCHEMA`
- `mysql`/`psql` commands that drop or truncate **all** tables or the whole database
- Any Docker/CI step equivalent to the above

## If a migration fails

- Fix the migration and run `php artisan migrate` again (or `--path` to one migration).
- Single bad table: `DROP TABLE …` only after the user agrees — never wipe the whole DB.

## Before destructive SQL the user requests

1. Backup first (`mysqldump`, project script, or provider snapshot).
2. Show the exact command; confirm if anything is ambiguous.

Also see `.cursor/rules/database-safety.mdc` in this repo (always-on rule).
