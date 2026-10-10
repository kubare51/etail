-- Migration: Seed SKIP_LEGACY_PAN_KYC_INSERT feature flag
-- Run once per client database.
-- Sets status=0 (OFF) by default — legacy PAN insert in mobile_api.php keeps running unchanged.
-- Enable via Admin > Settings > Feature Flags UI once a client has moved to the kyc_master-driven
-- dynamic KYC system, to stop mobile_api.php's hardcoded kyc_type=2 insert on account signup from
-- colliding with the new id_mas_kyc-based numbering.

UPDATE chit_settings
SET feature_flags = JSON_ARRAY_APPEND(
    IFNULL(feature_flags, '[]'), '$',
    JSON_OBJECT(
        'code',        'SKIP_LEGACY_PAN_KYC_INSERT',
        'description', 'Skip the legacy kyc_type=2 PAN insert in mobile_api.php on new account signup',
        'status',      0
    )
)
WHERE id_chit_settings = 1
  AND JSON_SEARCH(feature_flags, 'one', 'SKIP_LEGACY_PAN_KYC_INSERT') IS NULL;
