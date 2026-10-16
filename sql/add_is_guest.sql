-- One-time migration: adds the is_guest column to an existing members table.
-- Run this once against u288510777_kscdb (phpMyAdmin -> SQL tab -> paste -> Go).
-- Safe to run only once; schema.sql already includes this column for fresh installs.

ALTER TABLE `members`
    ADD COLUMN `is_guest` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_volunteer`;
