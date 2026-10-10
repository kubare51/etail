<?php
/**
 * ============================================================
 * LMX Generic Master — Shared Form Field Renderer
 * ============================================================
 * Renders one add/edit form field (label + control) for the
 * generic master modals. Used by both the Add and Edit modals
 * so the two stay in lockstep.
 *
 * Expected vars (passed via $this->load->view):
 *   $field            string  DB column / POST field name
 *   $type             mixed   inputs spec: [cast, sanitize, widget?]
 *   $config           array   Full entity config
 *   $mode             string  'add' | 'edit'
 *   $dropdown_options array   Optional map: field => [ ['id'=>..,'label'=>..], ... ]
 *   $field_meta       array   Optional map: field => ['rule','pattern','pattern_flags',
 *                              'pattern_message','max_length','required','min_val',
 *                              'max_val','has_unique','extra_where_field'],
 *                              from _master_field_meta() — drives client-side
 *                              keystroke filtering, maxlength/min/max, and the
 *                              live duplicate-check-on-blur so instant feedback
 *                              matches exactly what the server will enforce
 *                              (see master_list.php's DOMContentLoaded block
 *                              for the JS side).
 * ============================================================
 */

$dropdown_options = isset($dropdown_options) ? $dropdown_options : [];
$field_meta = isset($field_meta) ? $field_meta : [];
$label    = isset($config['rules'][$field]['label']) ? $config['rules'][$field]['label'] : ucfirst($field);
$required = !empty($config['rules'][$field]['required']);
$req_attr = $required ? 'required' : '';
$id_attr  = ($mode === 'edit') ? 'id="ed_' . $field . '"' : '';
$widget   = function_exists('_lmx_master_widget') ? _lmx_master_widget($field, $type, $config) : 'text';
$options  = isset($dropdown_options[$field]) ? $dropdown_options[$field] : [];
$meta       = isset($field_meta[$field]) ? $field_meta[$field] : [];
$rule_attr  = !empty($meta['rule']) ? 'data-rule="' . $meta['rule'] . '"' : '';
// Per-field custom pattern escape hatch — takes precedence over 'rule' for
// the strict check client-side too (see lmxValidateForm() in master_list.php).
$pattern_attr = !empty($meta['pattern'])
    ? 'data-pattern="' . htmlspecialchars($meta['pattern'], ENT_QUOTES) . '"'
        . ' data-pattern-flags="' . htmlspecialchars($meta['pattern_flags'], ENT_QUOTES) . '"'
        . (!empty($meta['pattern_message']) ? ' data-pattern-message="' . htmlspecialchars($meta['pattern_message'], ENT_QUOTES) . '"' : '')
    : '';
$maxlen_attr = !empty($meta['max_length']) ? 'maxlength="' . (int) $meta['max_length'] . '"' : '';
$minval_attr = isset($meta['min_val']) ? 'min="' . $meta['min_val'] . '" data-min="' . $meta['min_val'] . '"' : '';
$maxval_attr = isset($meta['max_val']) ? 'max="' . $meta['max_val'] . '" data-max="' . $meta['max_val'] . '"' : '';
// Live "already exists" check on blur — value alone for a single-column
// unique, plus the sibling field's current value for a composite check
// (e.g. Metal Purity Rate's id_purity unique only within the same id_metal).
$unique_attr = !empty($meta['has_unique']) ? 'data-check-unique="1"' . (!empty($meta['extra_where_field']) ? ' data-extra-field="' . $meta['extra_where_field'] . '"' : '') : '';

// Support inline fixed-option selects: ['int', '', {options: [['val', 'Label'], ...]}]
$inlineOptions = [];
if (is_array($type) && isset($type[2]) && is_array($type[2]) && isset($type[2]['options'])) {
    $inlineOptions = $type[2]['options'];
}
?>
<div class="row">
    <div class="form-group">
        <label class="col-md-4 col-md-offset-1">
            <?php echo $label; ?>
            <?php if ($required): ?><span class="error">*</span><?php endif; ?>
        </label>
        <div class="col-md-6">
            <?php if ($widget === 'select'): ?>
                <select class="form-control" name="<?php echo $field; ?>" <?php echo $id_attr; ?> <?php echo $req_attr; ?> <?php echo $unique_attr; ?>>
                    <option value="">-- Select --</option>
                    <?php foreach ($options as $opt): ?>
                        <option value="<?php echo $opt['id']; ?>"><?php echo $opt['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($widget === 'fixed-select' && !empty($inlineOptions)): ?>
                <select class="form-control" name="<?php echo $field; ?>" <?php echo $id_attr; ?> <?php echo $req_attr; ?>>
                    <option value="">-- Select --</option>
                    <?php foreach ($inlineOptions as $opt): ?>
                        <option value="<?php echo $opt[0]; ?>"><?php echo $opt[1]; ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($widget === 'checkbox'): ?>
                <input type="checkbox" class="switch" name="<?php echo $field; ?>" <?php echo $id_attr; ?> value="1" data-on-text="YES" data-off-text="NO" />
                <span class="help-block">Check to enable</span>
            <?php elseif ($widget === 'textarea'): ?>
                <textarea class="form-control" name="<?php echo $field; ?>" <?php echo $id_attr; ?> <?php echo $req_attr; ?> <?php echo $rule_attr; ?> <?php echo $maxlen_attr; ?>></textarea>
            <?php elseif ($widget === 'number'): ?>
                <input type="number" step="any" class="form-control" name="<?php echo $field; ?>" <?php echo $id_attr; ?> <?php echo $req_attr; ?> <?php echo $rule_attr; ?> <?php echo $minval_attr; ?> <?php echo $maxval_attr; ?> <?php echo $unique_attr; ?> <?php echo $pattern_attr; ?> />
            <?php elseif ($widget === 'date'): ?>
                <input type="date" class="form-control" name="<?php echo $field; ?>" <?php echo $id_attr; ?> <?php echo $req_attr; ?> />
            <?php else: ?>
                <input type="text" class="form-control" name="<?php echo $field; ?>" <?php echo $id_attr; ?> <?php echo $req_attr; ?> <?php echo $rule_attr; ?> <?php echo $maxlen_attr; ?> <?php echo $unique_attr; ?> <?php echo $pattern_attr; ?> />
            <?php endif; ?>
        </div>
    </div>
</div>
<br/>
