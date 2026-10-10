-- Migration: Seed CAPTURE_NOMINEE_ON_ACCOUNT_CREATE feature flag
-- Run once per client database.
-- Backfills the flag row for the nominee-capture-on-signup feature (mobile_api.php), which was
-- already shipped in code but never had a corresponding seed migration.
-- Sets status=0 (OFF) by default — enable via Admin > Settings > Feature Flags UI.

UPDATE chit_settings
SET feature_flags = JSON_ARRAY_APPEND(
    IFNULL(feature_flags, '[]'), '$',
    JSON_OBJECT(
        'code',        'CAPTURE_NOMINEE_ON_ACCOUNT_CREATE',
        'description', 'Capture nominee_name/nominee_mobile on non-digi account creation in mobile_api.php',
        'status',      0
    )
)
WHERE id_chit_settings = 1
  AND JSON_SEARCH(feature_flags, 'one', 'CAPTURE_NOMINEE_ON_ACCOUNT_CREATE') IS NULL;
