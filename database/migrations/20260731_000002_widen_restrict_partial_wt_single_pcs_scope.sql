-- Migration: Widen restrict_partial_wt_single_pcs scope description
-- Purpose: The QC Issue/Receipt partial-weight guard (see
--          20260731_000001_restrict_partial_wt_single_pcs.sql) was generalized from
--          literal 1-Pc lines to a piece/weight boundary rule that applies at ANY piece
--          count: taking/rejecting an entire remaining or issued piece count together
--          requires the full corresponding weight, and taking/rejecting zero pieces
--          requires zero weight. Genuine partial splits remain unrestricted. This only
--          updates the setting's description text so admins understand its true scope;
--          the setting name, default, and `value` column are unchanged, so
--          isSinglePcsRestrictionEnabled() behaves identically before and after.

-- UP
UPDATE `ret_settings`
   SET `description` = 'Restrict partial weight processing at piece boundaries in QC Issue and QC Receipt: taking/rejecting all available pieces requires the full weight, and zero pieces requires zero weight. Partial piece splits are unrestricted. 1 = Enabled, 0 = Disabled'
 WHERE `name` = 'restrict_partial_wt_single_pcs';

-- DOWN
UPDATE `ret_settings`
   SET `description` = 'Restrict partial weight processing for single-piece (1 Pc) items in QC Issue and QC Receipt. 1 = Enabled, 0 = Disabled'
 WHERE `name` = 'restrict_partial_wt_single_pcs';
