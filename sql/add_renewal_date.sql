-- One-time migration: adds the renewal_date column to an existing members table.
-- Run this once against u288510777_kscdb (phpMyAdmin -> SQL tab -> paste -> Go).
-- Safe to run only once; schema.sql already includes this column for fresh installs.
-- Starts NULL for every existing member -- there's no prior data to backfill it from,
-- so it'll need to be filled in by hand (on the Members page) as members come up for renewal.

ALTER TABLE `members`
    ADD COLUMN `renewal_date` DATE NULL DEFAULT NULL AFTER `is_guest`;
