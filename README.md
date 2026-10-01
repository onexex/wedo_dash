# wedo_dash

## Regression tests

PHPUnit tests drive the real pages and endpoints (each request in its own PHP
process) against a **throwaway** database built from the structure-only
snapshot in `tests/fixtures/schema.sql`. Your real data is never touched.

```bash
composer install     # once — dev-only tooling, never deployed
composer test
```

The test database defaults to local XAMPP (`root`, no password, `wedo_test`).
Override with `TEST_DB_HOST`, `TEST_DB_USER`, `TEST_DB_PASS`, `TEST_DB_NAME`
(the name must contain `test` — it is dropped and recreated on every run).

CI runs the same suite before every staging and production deploy.
If the database structure changes, refresh the snapshot:

```bash
mysqldump --no-data --skip-comments wedodb2020 | sed -E 's/ AUTO_INCREMENT=[0-9]+//' > tests/fixtures/schema.sql
```
