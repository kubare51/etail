-- Fix: Sales Transfer / billing print header showed "INVOICE NO" — should read "BILL NO".
-- The label lives inside the active 'billing' Konva print template's gjs_data JSON
-- (a canvas Text node's `text`/`_rawText` attrs), not in a source code file.
-- Uses hex literals for the search/replace values to avoid backslash-escaping
-- ambiguity across differing sql_mode settings. Idempotent: if the pattern is
-- already replaced (or absent), REPLACE() is a safe no-op — 0 rows changed,
-- no error.

-- UP
UPDATE print_templates
SET gjs_data = REPLACE(
    REPLACE(gjs_data,
      0x5C22746578745C223A5C22494E564F494345204E4F5C22,
      0x5C22746578745C223A5C2242494C4C204E4F5C22
    ),
    0x5C225F726177546578745C223A5C22494E564F494345204E4F5C22,
    0x5C225F726177546578745C223A5C2242494C4C204E4F5C22
)
WHERE code = 'billing' AND is_active = 1
ORDER BY id_template DESC
LIMIT 1;

-- DOWN
UPDATE print_templates
SET gjs_data = REPLACE(
    REPLACE(gjs_data,
      0x5C22746578745C223A5C2242494C4C204E4F5C22,
      0x5C22746578745C223A5C22494E564F494345204E4F5C22
    ),
    0x5C225F726177546578745C223A5C2242494C4C204E4F5C22,
    0x5C225F726177546578745C223A5C22494E564F494345204E4F5C22
)
WHERE code = 'billing' AND is_active = 1
ORDER BY id_template DESC
LIMIT 1;
