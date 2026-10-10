<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Field Validation Helper — Character Format Rules
 * ============================================================
 * Provides validate_master_field(), called by lmx_validate()
 * (lmx/core/validation_helper.php) for every rule that sets a
 * 'rule' key. Allowlist-based (reject anything not explicitly
 * permitted), not blocklist-based — a blocklist is always
 * incomplete and doesn't defend against the DataTables
 * unescaped-render stored-XSS path these fields feed into.
 *
 * Baseline character sets were checked against real production
 * data (ret_financial_year.fin_year_name = "FY 22-23",
 * ret_section.section_short_code = "G E", metal.metal_code =
 * "Diamond ") — all pass the baselines below without needing a
 * per-field override.
 *
 * @version  1.0.0
 * @created  2026-07-13
 * ============================================================
 */

/**
 * Single source of truth for the 4 format rules — both validate_master_field()
 * below (server-side enforcement) and lmx_rule_js_patterns() (validation_helper.php,
 * exposes the same character sets to the browser for keystroke filtering +
 * pre-submit checks) read from here, so PHP and JS can't silently drift apart
 * on what a rule name actually allows.
 *
 * 'charset' rules allow ONLY the listed characters (an allowlist regex class
 * body, no delimiters/anchors). 'blocklist' rules allow anything EXCEPT the
 * listed characters. 'pattern' rules aren't charset-based at all — 'numeric'
 * is a fixed shape, not a set of individually-valid characters.
 *
 * @return array  ruleName => ['type', 'chars'|'body', 'unicode', 'message']
 */
function lmx_rule_definitions()
{
    return [
        'nameField' => [
            'type'    => 'charset',
            // Letters (unicode), digits, spaces, and a deliberate punctuation
            // set. Explicitly excludes < > " ' ` — blocks the stored-XSS path
            // into DataTables cells, which render field values as raw HTML.
            'chars'   => '\p{L}0-9 \-&()\/.',
            'unicode' => true,
            'message' => 'can only contain letters, numbers, spaces, and - & ( ) / .',
        ],
        'shortCode' => [
            'type'    => 'charset',
            // Letters, digits, single internal spaces. No punctuation — codes
            // don't need it, and it narrows the injection surface further.
            'chars'   => 'A-Za-z0-9 ',
            'unicode' => false,
            'message' => 'can only contain letters, numbers, and spaces',
        ],
        'remarks' => [
            'type'    => 'blocklist',
            // Broadest bucket (free text) — still block < > since these
            // render unescaped in DataTables cells same as every other field.
            'chars'   => '<>',
            'unicode' => false,
            'message' => 'cannot contain < or > characters',
        ],
        'numeric' => [
            'type'      => 'pattern',
            // User-typed quantities only (e.g. purity value). FK id fields
            // (tgrp_id, id_metal, id_product) are select-populated and
            // validated for existence via fk_check, not format — don't route
            // those through this rule.
            'body'      => '-?\d+(\.\d+)?',
            // Mid-typing states ("-", "12.", "") that 'body' itself rejects —
            // see lmx_rule_js_patterns() for why this can't be auto-derived.
            'keystroke' => '-?\d*\.?\d*',
            'unicode'   => false,
            'message'   => 'must be a valid number',
        ],
        // Numeric SHAPE variants beyond the general 'numeric' rule — added as
        // named rules (not by stretching 'numeric' with per-field flags) so
        // both PHP and JS keep getting them for free from this one array.
        // For "restrict to positive" alone, prefer min_val => 0 on the field's
        // rule instead of a new rule — that's a range check, not a shape one,
        // and 'numeric' + min_val already covers it without adding anything.
        'wholeNumber' => [
            'type'      => 'pattern',
            // Non-negative integers only — no sign, no decimal point at all
            // (e.g. a piece count or a quantity field).
            'body'      => '\d+',
            'keystroke' => '\d*',
            'unicode'   => false,
            'message'   => 'must be a whole number',
        ],
        'decimal2' => [
            'type'      => 'pattern',
            // Like 'numeric' but capped at 2 decimal places (e.g. currency
            // amounts) — still signed, since min_val is the right tool for
            // "no negative", not the format rule.
            'body'      => '-?\d+(\.\d{1,2})?',
            'keystroke' => '-?\d*\.?\d{0,2}',
            'unicode'   => false,
            'message'   => 'must be a valid number with at most 2 decimal places',
        ],
        // Ported from admin/assets/js/validation.js (the `support` branch's
        // parallel validation library — see stabilization tracker notes) for
        // naming/shape parity, so reconciling the two systems later is less
        // painful. NOTE: deliberately skipped 'hsnCode' and 'pincode' from
        // that file — both are just 'wholeNumber' + a max_length/pattern on
        // the field's own rule entry (see old_metal_cat.hsn_code), adding a
        // same-shaped rule under a second name would just invite drift.
        'percentage' => [
            'type'    => 'charset',
            // Digits, one decimal point, and % — 'numeric'/'decimal2' don't
            // allow % at all (e.g. a wastage/discount percentage field where
            // the user types the literal '%' character).
            'chars'   => '0-9.%',
            'unicode' => false,
            'message' => 'can only contain numbers, a decimal point, and %',
        ],
        'alphanumericNoSpace' => [
            'type'    => 'charset',
            // Letters and digits, no spaces at all — unlike 'shortCode' which
            // allows internal spaces (e.g. GST/PAN-style identifiers).
            'chars'   => 'A-Za-z0-9',
            'unicode' => false,
            'message' => 'can only contain letters and numbers, no spaces',
        ],
        'chargeCode' => [
            'type'    => 'charset',
            // Letters, digits, dot, underscore, hyphen — no spaces. Distinct
            // from 'shortCode' (allows spaces, no dot) and 'nameField' (allows
            // spaces and unicode letters); charge codes look like "MC.001".
            'chars'   => 'A-Za-z0-9._\-',
            'unicode' => false,
            'message' => 'can only contain letters, numbers, dot (.), underscore (_) and hyphen (-)',
        ],
        'phone' => [
            'type'    => 'charset',
            'chars'   => '0-9+\- ',
            'unicode' => false,
            'message' => 'can only contain numbers, +, - and spaces',
        ],
        'email' => [
            'type'    => 'charset',
            'chars'   => 'A-Za-z0-9@._\-',
            'unicode' => false,
            'message' => 'contains characters not allowed in an email address',
        ],
    ];
}

/**
 * JS-ready regex source strings derived from lmx_rule_definitions() — the
 * SAME character sets validate_master_field() enforces server-side, so the
 * browser's keystroke filter and pre-submit check can't drift from what the
 * server actually allows. Two variants per rule:
 *  - 'strict': full-value match, equivalent to the server's own pattern —
 *    used for the pre-submit "is this actually valid" check.
 *  - 'keystroke': permissive prefix match (allows the empty string and any
 *    partial state a user passes through while typing) — used for character-
 *    by-character filtering as they type.
 * For charset/blocklist rules 'keystroke' is mechanically derivable from
 * 'strict' (any prefix of a charset-only string is itself valid), so it's
 * generated here. 'pattern' rules (numeric, wholeNumber, decimal2) aren't
 * charset-based — their strict body rejects valid mid-typing states (e.g.
 * numeric's '-?\d+(\.\d+)?' rejects "-", "12.", or "") that a typing user
 * passes through — so each carries its own explicit 'keystroke' body in
 * lmx_rule_definitions() rather than one derived automatically.
 *
 * @return array  ruleName => ['strict', 'keystroke', 'flags', 'message']
 */
function lmx_rule_js_patterns()
{
    $out = [];
    foreach (lmx_rule_definitions() as $name => $def) {
        $flags = $def['unicode'] ? 'u' : '';
        switch ($def['type']) {
            case 'charset':
                $out[$name] = [
                    'strict'    => '^[' . $def['chars'] . ']+$',
                    'keystroke' => '^[' . $def['chars'] . ']*$',
                ];
                break;
            case 'blocklist':
                $out[$name] = [
                    'strict'    => '^[^' . $def['chars'] . ']*$',
                    'keystroke' => '^[^' . $def['chars'] . ']*$',
                ];
                break;
            case 'pattern':
                $out[$name] = [
                    'strict'    => '^' . $def['body'] . '$',
                    'keystroke' => '^' . $def['keystroke'] . '$',
                ];
                break;
        }
        $out[$name]['flags'] = $flags;
        $out[$name]['message'] = $def['message'];
    }
    return $out;
}

/**
 * Validate a single field's value against a named format rule, or a per-field
 * custom 'pattern' — the escape hatch for the rare field whose allowed shape
 * doesn't match any shared named rule and isn't worth adding one for (e.g. a
 * fixed-length code like '^\d{1,4}$'). 'pattern' can stand alone with no
 * 'rule' at all, or sit alongside one — when both are set, 'rule' still
 * drives client-side keystroke filtering (see lmx_rule_js_patterns()) while
 * 'pattern' is the one actually enforced as the final full-value check, here
 * and in the browser's pre-submit validation.
 *
 * @param  string|null $ruleName  'nameField' | 'shortCode' | 'remarks' | 'numeric' | 'wholeNumber' | 'decimal2' | null
 * @param  string $fieldTitle  Human label for error messages
 * @param  mixed  $value
 * @param  array  $ruleConfig  The field's full rule entry (for 'min', 'allow_extra', 'pattern', 'pattern_flags', 'pattern_message')
 * @return array  ['valid' => bool, 'errors' => array]
 */
function validate_master_field($ruleName, $fieldTitle, $value, $ruleConfig = [])
{
    $errors = [];
    $value  = (string) $value;

    if (!empty($ruleConfig['min']) && mb_strlen($value) < (int) $ruleConfig['min']) {
        $errors[] = $fieldTitle . ' must be at least ' . $ruleConfig['min'] . ' characters';
    }

    if (!empty($ruleConfig['pattern'])) {
        $flags = isset($ruleConfig['pattern_flags']) ? $ruleConfig['pattern_flags'] : '';
        if (!preg_match('/' . $ruleConfig['pattern'] . '/' . $flags, $value)) {
            $errors[] = $fieldTitle . ' ' . (isset($ruleConfig['pattern_message']) ? $ruleConfig['pattern_message'] : 'is not in the allowed format');
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    $definitions = lmx_rule_definitions();
    if (!isset($definitions[$ruleName])) {
        // Unknown/absent rule name and no 'pattern' either — don't silently
        // block save; the missing rule itself is the thing to fix.
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    $def = $definitions[$ruleName];

    // Per-field escape hatch: extra characters layered on top of the baseline,
    // for the rare field whose real data needs more than the shared default.
    $extra = isset($ruleConfig['allow_extra']) ? $ruleConfig['allow_extra'] : '';
    $modifier = $def['unicode'] ? 'u' : '';

    switch ($def['type']) {
        case 'charset':
            if (!preg_match('/^[' . $def['chars'] . $extra . ']+$/' . $modifier, $value)) {
                $errors[] = $fieldTitle . ' ' . $def['message'];
            }
            break;

        case 'blocklist':
            if (preg_match('/[' . $def['chars'] . ']/' . $modifier, $value)) {
                $errors[] = $fieldTitle . ' ' . $def['message'];
            }
            break;

        case 'pattern':
            if (!preg_match('/^' . $def['body'] . '$/' . $modifier, $value)) {
                $errors[] = $fieldTitle . ' ' . $def['message'];
            }
            break;
    }

    return ['valid' => empty($errors), 'errors' => $errors];
}
