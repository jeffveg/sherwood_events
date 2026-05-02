-- Sherwood Events — migration 004
--
-- Adds an intake_ref column so external systems (currently the
-- Sherwood_Schedule booking app) can push draft events into events
-- without creating duplicates on retry. The schedule app's booking_ref
-- (e.g. "SA-2026-001") is stored here as the idempotency key.
--
-- Run once against the production DB:
--   mysql -h <host> -u <user> -p <db> < sql/004_intake.sql

ALTER TABLE events
    ADD COLUMN intake_ref VARCHAR(60) NULL AFTER updated_at;

-- UNIQUE so a retry from the same booking_ref returns the existing
-- draft instead of creating a duplicate. NULL is allowed (manually-
-- created events won't have an intake_ref) and MariaDB lets multiple
-- NULLs coexist under a UNIQUE index.
ALTER TABLE events
    ADD UNIQUE INDEX idx_intake_ref (intake_ref);
