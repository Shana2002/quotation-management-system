-- ---------------------------------------------------------------------
-- 2026-09-23 — Plan letter templates: SCHEMA
-- ---------------------------------------------------------------------
-- Adds the two `${token}` templates that drive the quotation letter's
-- "Investment Summary" and "Terms & Conditions" blocks. Both are authored per
-- plan in plan edit mode and resolved per quotation by App\Core\Placeholder.
--
-- RUN THIS ONCE per database. Adding a column is not supported with
-- IF NOT EXISTS on MySQL 8 (MariaDB does support it, and you may add it).
-- Re-running this file will fail with "Duplicate column name" — that is
-- harmless, and the backfill script is separate so it still applies cleanly.
--
--   "C:/xampp/mysql/bin/mysql.exe" -u root -h 127.0.0.1 --default-character-set=utf8mb4 qms < database/migrations/2026_09_23_plan_letter_templates_schema.sql
--   "C:/xampp/mysql/bin/mysql.exe" -u root -h 127.0.0.1 --default-character-set=utf8mb4 qms < database/migrations/2026_09_23_plan_letter_templates_backfill.sql
-- ---------------------------------------------------------------------

ALTER TABLE `plans`
    ADD COLUMN `summary_template` TEXT DEFAULT NULL AFTER `benefits`,
    ADD COLUMN `terms_template`   TEXT DEFAULT NULL AFTER `summary_template`;
