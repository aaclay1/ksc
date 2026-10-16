ALTER TABLE `lunch_reservations`
    ADD COLUMN `cancelled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `walkin`;
