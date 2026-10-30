-- ONE-TIME DATA FIX -- run this exactly once.
--
-- Until now, the Renewal Date field on the Members page stored whatever
-- date staff picked, as-is. The app was just changed so that field now
-- represents the date paperwork was filled out, and automatically stores
-- that date PLUS 2 YEARS (the actual expiration date) instead. Existing
-- records were saved under the old behavior, so every renewal_date
-- currently on file is 2 years too early and needs to be bumped forward
-- to match.
--
-- This mirrors the exact same leap-day handling the app's JavaScript uses:
-- a renewal date of Feb 29 bumps to Feb 28 if the resulting year isn't a
-- leap year (so it never lands on a nonexistent date like Feb 29, 2027).
--
-- SAFETY: back up the `members` table (or at least the renewal_date
-- column) before running this, and do NOT run it more than once -- running
-- it twice will bump every date forward by 4 years instead of 2.
--
-- Suggested check before running (see what will change):
--   SELECT id, first_name, last_name, renewal_date,
--          CASE
--              WHEN MONTH(renewal_date) = 2 AND DAY(renewal_date) = 29
--                   AND NOT ( (YEAR(renewal_date) + 2) % 4 = 0
--                             AND ( (YEAR(renewal_date) + 2) % 100 <> 0
--                                   OR (YEAR(renewal_date) + 2) % 400 = 0 ) )
--              THEN STR_TO_DATE(CONCAT(YEAR(renewal_date) + 2, '-02-28'), '%Y-%m-%d')
--              ELSE STR_TO_DATE(CONCAT(YEAR(renewal_date) + 2, '-', LPAD(MONTH(renewal_date), 2, '0'), '-', LPAD(DAY(renewal_date), 2, '0')), '%Y-%m-%d')
--          END AS new_renewal_date
--   FROM `members`
--   WHERE renewal_date IS NOT NULL;

UPDATE `members`
SET `renewal_date` = CASE
        WHEN MONTH(`renewal_date`) = 2 AND DAY(`renewal_date`) = 29
             AND NOT ( (YEAR(`renewal_date`) + 2) % 4 = 0
                       AND ( (YEAR(`renewal_date`) + 2) % 100 <> 0
                             OR (YEAR(`renewal_date`) + 2) % 400 = 0 ) )
        THEN STR_TO_DATE(CONCAT(YEAR(`renewal_date`) + 2, '-02-28'), '%Y-%m-%d')
        ELSE STR_TO_DATE(CONCAT(YEAR(`renewal_date`) + 2, '-', LPAD(MONTH(`renewal_date`), 2, '0'), '-', LPAD(DAY(`renewal_date`), 2, '0')), '%Y-%m-%d')
    END
WHERE `renewal_date` IS NOT NULL;
