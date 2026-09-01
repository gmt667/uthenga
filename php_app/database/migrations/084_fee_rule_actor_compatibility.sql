-- Forward-only compatibility for installations that received the original
-- VARCHAR(30) fee-rule actor column. Preserve its text before adding the
-- canonical numeric relationship. Run through the normal migration runner.
ALTER TABLE uthenga_fee_rules
    ADD COLUMN IF NOT EXISTS created_by_legacy VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS created_by_user_id BIGINT UNSIGNED NULL,
    ADD INDEX IF NOT EXISTS idx_fee_rules_created_by_user (created_by_user_id);

-- Numeric legacy values map only when a real user exists. Non-numeric and
-- orphaned values remain intact in created_by_legacy for historical display.
UPDATE uthenga_fee_rules
SET created_by_legacy = COALESCE(created_by_legacy, CAST(created_by AS CHAR))
WHERE created_by IS NOT NULL AND created_by_legacy IS NULL;

UPDATE uthenga_fee_rules r
INNER JOIN users u ON CAST(r.created_by_legacy AS UNSIGNED) = u.id
SET r.created_by_user_id = u.id
WHERE r.created_by_legacy REGEXP '^[0-9]+$' AND r.created_by_user_id IS NULL;
