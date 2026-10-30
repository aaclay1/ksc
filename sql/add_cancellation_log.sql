-- Adds the `cancellation_log` table used by the new "Log a
-- Cancellation / No-Show" feature on the Reservations page. Safe to run
-- any time (CREATE TABLE IF NOT EXISTS) -- it won't touch anything if the
-- table already exists.
--
-- This is separate from the `cancelled` flag on `lunch_reservations`:
-- that flag just marks one specific existing reservation row as
-- cancelled. This table is a general incident log -- a cancellation or
-- no-show can be logged for a member/date even if there was no
-- lunch_reservations row at all -- and is what the ban-eligibility check
-- on the Add a Reservation form reads from.

CREATE TABLE IF NOT EXISTS `cancellation_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id` INT UNSIGNED NOT NULL,
    `cancel_date` DATE NOT NULL,
    `type` ENUM('CX','NCNS') NOT NULL,
    `notes` TEXT NULL,
    `excused` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `member_id` (`member_id`),
    KEY `cancel_date` (`cancel_date`),
    CONSTRAINT `fk_cancellation_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
