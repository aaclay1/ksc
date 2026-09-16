-- Kearney Senior Center Report - standalone schema
-- Run this against the NEW database (u288510777_kscdb) before running migrate.php.
-- Table names match what db.php / the app pages expect (no "wp_ksc_" prefix).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `members` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(100) NOT NULL DEFAULT '',
    `last_name` VARCHAR(100) NOT NULL DEFAULT '',
    `phone_number` VARCHAR(20) NOT NULL DEFAULT '',
    `email` VARCHAR(150) NOT NULL DEFAULT '',
    `is_60_plus` TINYINT(1) NOT NULL DEFAULT 0,
    `is_clay_resident` TINYINT(1) NOT NULL DEFAULT 0,
    `barcode` VARCHAR(20) NOT NULL DEFAULT '',
    `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `barcode` (`barcode`),
    KEY `name` (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activity_types` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `activity_type` VARCHAR(150) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `activity_type` (`activity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activities` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `activity_name` VARCHAR(150) NOT NULL DEFAULT '',
    `activity_type_id` INT UNSIGNED NULL,
    `barcode` VARCHAR(20) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `activity_name` (`activity_name`),
    UNIQUE KEY `barcode` (`barcode`),
    KEY `activity_type_id` (`activity_type_id`),
    CONSTRAINT `fk_activities_type` FOREIGN KEY (`activity_type_id`) REFERENCES `activity_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `member_signin` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id` INT UNSIGNED NOT NULL,
    `activity_id` INT UNSIGNED NOT NULL,
    `volunteer_hours` DECIMAL(6,2) NOT NULL DEFAULT 0,
    `signin_date` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `member_id` (`member_id`),
    KEY `activity_id` (`activity_id`),
    KEY `signin_date` (`signin_date`),
    CONSTRAINT `fk_signin_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_signin_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lunch_reservations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id` INT UNSIGNED NOT NULL,
    `res_date` DATE NOT NULL,
    `bus_rider` TINYINT(1) NOT NULL DEFAULT 0,
    `eat_lunch` TINYINT(1) NOT NULL DEFAULT 0,
    `showed` TINYINT(1) NOT NULL DEFAULT 0,
    `walkin` TINYINT(1) NOT NULL DEFAULT 0,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `member_date` (`member_id`, `res_date`),
    KEY `res_date` (`res_date`),
    CONSTRAINT `fk_reservation_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
