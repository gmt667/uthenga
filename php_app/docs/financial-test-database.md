# Financial callback test database

The only environment template is `php_app/.env.example`. Do not create or commit a `.env` file.

This workspace has no Docker, Docker Compose, MySQL, or MariaDB client available, so no database was created or contacted. When an operator provisions a dedicated local MySQL/MariaDB schema, set these process-local variables only:

`UTHENGA_ENV=test`, `UTHENGA_TEST_DB_HOST=127.0.0.1`, `UTHENGA_TEST_DB_PORT`, `UTHENGA_TEST_DB_NAME=uthenga_financial_test`, `UTHENGA_TEST_DB_USER`, and `UTHENGA_TEST_DB_PASSWORD`.

The guard accepts only `localhost`/`127.0.0.1` and a database name ending in `_test`. It never falls back to normal application variables. The bootstrap is CLI-only and applies tracked filenames in this order: 073, 075, 076, 081, 082, 083. It does not create, drop, truncate, or clean any schema.

`075_uthenga_fee_rules.sql` was corrected for fresh installations to use `created_by BIGINT UNSIGNED`, matching `users.id`. `084_fee_rule_actor_compatibility.sql` preserves legacy VARCHAR actor values in `created_by_legacy` and maps only deterministically matching numeric values to `created_by_user_id`. The test bootstrap now applies the real migration chain; it no longer pre-creates a substitute fee-rule table.

If an earlier bootstrap failed, do not rerun against an unknown partial schema. After the guard and preflight pass, an authorized operator must reset only `uthenga_financial_test` using their database-administration procedure, then rerun the bootstrap. The limited test application user should not be granted drop/create privileges and the harness will never issue those operations.

Run `php php_app/tests/financial_test_database_guard_test.php` to test the guard. After an operator has created the dedicated schema and limited test user, run `php php_app/tools/financial_test_bootstrap.php`. PayChangu activation remains prohibited until the full database-backed receipt, ledger, rollback, Shop, replay, and concurrency suite passes.
