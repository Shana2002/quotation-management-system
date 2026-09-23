SET NAMES utf8mb4;

-- Interest plans (Royal Plus, Guaranteed Plus) now store ONE rate per tenure:
-- `annual_rate`. The monthly figure the letter quotes is derived as
-- annual_rate / 12 when a quotation is computed, which makes `monthly_rate`
-- dead weight — and a misleading kind, since an admin editing it would change
-- nothing. This drops it, keeping `annual_rate` where present and seeding it
-- from `monthly_rate × 12` where it is missing, so no rate is lost.
--
-- A row that carried both with different values resolves to `annual_rate`:
-- Royal Plus year 1 held monthly_rate 28 against annual_rate 24, and today the
-- two drive different quotes (28% a month on one method, 24% a year on the
-- other). Where the surviving rate needs re-deciding, edit it in the plan form
-- — the app reads `annual_rate` and nothing else.
--
-- Two MariaDB behaviours shape the SQL below:
--   * A value read out of a LONGTEXT column comes back string-typed, and
--     JSON_SET would happily store it back as the string "24". The `+ 0` on
--     every extract is what keeps the rate a JSON number.
--   * JSON_SET will not create a missing parent object, so adding a whole new
--     tenure (statement 6) has to go through JSON_MERGE_PATCH instead.
--
-- Tenures are those each type allows: Royal Plus 1–5, Guaranteed Plus 2–5.
-- Year keys in the JSON are strings, hence the quoted paths ('$.years."1"').
-- Every statement is guarded on the plan still holding the old key, so
-- re-running the file is a no-op.
-- ---------------------------------------------------------------------

-- 1–5. Per tenure: keep annual_rate, drop monthly_rate. The trailing 24 is the
--       type's own default, reached only if a row's monthly_rate is not a
--       number (the app would fall back to the same 24).
UPDATE `plans` SET `parameters` = JSON_REMOVE(
        JSON_SET(`parameters`, '$.years."1".annual_rate',
            COALESCE(JSON_EXTRACT(`parameters`, '$.years."1".annual_rate') + 0,
                     (JSON_EXTRACT(`parameters`, '$.years."1".monthly_rate') + 0) * 12,
                     24)),
        '$.years."1".monthly_rate')
WHERE `plan_type` IN ('royal_plus', 'guaranteed_plus')
  AND JSON_CONTAINS_PATH(`parameters`, 'one', '$.years."1".monthly_rate');

UPDATE `plans` SET `parameters` = JSON_REMOVE(
        JSON_SET(`parameters`, '$.years."2".annual_rate',
            COALESCE(JSON_EXTRACT(`parameters`, '$.years."2".annual_rate') + 0,
                     (JSON_EXTRACT(`parameters`, '$.years."2".monthly_rate') + 0) * 12,
                     24)),
        '$.years."2".monthly_rate')
WHERE `plan_type` IN ('royal_plus', 'guaranteed_plus')
  AND JSON_CONTAINS_PATH(`parameters`, 'one', '$.years."2".monthly_rate');

UPDATE `plans` SET `parameters` = JSON_REMOVE(
        JSON_SET(`parameters`, '$.years."3".annual_rate',
            COALESCE(JSON_EXTRACT(`parameters`, '$.years."3".annual_rate') + 0,
                     (JSON_EXTRACT(`parameters`, '$.years."3".monthly_rate') + 0) * 12,
                     24)),
        '$.years."3".monthly_rate')
WHERE `plan_type` IN ('royal_plus', 'guaranteed_plus')
  AND JSON_CONTAINS_PATH(`parameters`, 'one', '$.years."3".monthly_rate');

UPDATE `plans` SET `parameters` = JSON_REMOVE(
        JSON_SET(`parameters`, '$.years."4".annual_rate',
            COALESCE(JSON_EXTRACT(`parameters`, '$.years."4".annual_rate') + 0,
                     (JSON_EXTRACT(`parameters`, '$.years."4".monthly_rate') + 0) * 12,
                     24)),
        '$.years."4".monthly_rate')
WHERE `plan_type` IN ('royal_plus', 'guaranteed_plus')
  AND JSON_CONTAINS_PATH(`parameters`, 'one', '$.years."4".monthly_rate');

UPDATE `plans` SET `parameters` = JSON_REMOVE(
        JSON_SET(`parameters`, '$.years."5".annual_rate',
            COALESCE(JSON_EXTRACT(`parameters`, '$.years."5".annual_rate') + 0,
                     (JSON_EXTRACT(`parameters`, '$.years."5".monthly_rate') + 0) * 12,
                     24)),
        '$.years."5".monthly_rate')
WHERE `plan_type` IN ('royal_plus', 'guaranteed_plus')
  AND JSON_CONTAINS_PATH(`parameters`, 'one', '$.years."5".monthly_rate');

-- 6. Royal Plus offers a 5th tenure that the original seed predates, so a
--    five-year quote reads no rate and silently falls back to the type's
--    default instead of following the plan. Seed it from year 1, the rate the
--    other tenures already carry.
UPDATE `plans` SET `parameters` = JSON_MERGE_PATCH(
        `parameters`,
        JSON_OBJECT('years', JSON_OBJECT('5', JSON_OBJECT('annual_rate',
            COALESCE(JSON_EXTRACT(`parameters`, '$.years."1".annual_rate') + 0, 24)))))
WHERE `plan_type` = 'royal_plus'
  AND JSON_CONTAINS_PATH(`parameters`, 'one', '$.years."1"')
  AND NOT JSON_CONTAINS_PATH(`parameters`, 'one', '$.years."5"');
