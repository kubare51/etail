<!-- Content Wrapper -->
<div id="lmx-toast-container" style="position:fixed; top:70px; right:20px; z-index:99999; width:320px;"></div>
<div class="content-wrapper">
    <section class="content-header">
        <h1>
            <?php echo $entity_name; ?>
            <small>Master List</small>
        </h1>
    </section>

    <section class="content">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><?php echo $entity_name; ?> List</h3>
                <?php if (isset($access['add']) && $access['add'] == 1): ?>
                    <button class="btn btn-success pull-right" data-toggle="modal" data-target="#lmx-modal-add">
                        <i class="fa fa-plus"></i> Add
                    </button>
                <?php endif; ?>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table id="lmx_master_table" class="table table-bordered table-striped text-center" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <?php foreach ($config['inputs'] as $field => $type): ?>
                                    <th><?php echo isset($config['rules'][$field]['label']) ? $config['rules'][$field]['label'] : ucfirst($field); ?></th>
                                <?php endforeach; ?>
                                <?php if (isset($config['status_col'])): ?>
                                    <th>Status</th>
                                <?php endif; ?>
                                <th>Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Load Generic Modals -->
<?php $this->load->view('ret_catalog/add_generic', ['config' => $config, 'entity_name' => $entity_name, 'entity_key' => $entity_key, 'dropdown_options' => isset($dropdown_options) ? $dropdown_options : [], 'field_meta' => isset($field_meta) ? $field_meta : []]); ?>

<!-- Universal LMX Engine JS Init -->
<script>
// NOTE: this view is rendered before footer.php loads jQuery, so a top-level
// $(document).ready(...) call throws "$ is not defined" and silently aborts
// this entire block (DataTable, edit/delete/save handlers, switches — none
// of it ever runs). DOMContentLoaded is safe: it only fires once every
// synchronous script in the document — including footer's jQuery — has run.
document.addEventListener('DOMContentLoaded', function() {
    var entity_key = "<?php echo $entity_key; ?>";
    var table_id = "#lmx_master_table";
    var base_url = "<?php echo base_url(); ?>";
    // I-GMSJ-005: url_key overrides the URL segment when the controller
    // method name doesn't match the entity key (e.g. Size -> ret_size()).
    var fetch_url = base_url + "index.php/admin_ret_catalog/" + "<?php echo isset($config['url_key']) ? $config['url_key'] : $entity_key; ?>";

    // Non-blocking toast — generic master pages don't load toastr/sweetalert,
    // and the shared LMX.notify() (lmx/core.js) falls all the way back to a
    // native alert() when neither is present, which freezes the tab on a
    // modal OS dialog until dismissed (confirmed: also hangs headless/
    // automated testing). Self-contained so it never depends on LMX being
    // loaded at all.
    function lmxNotify(message, type) {
        var cls = (type === 'error') ? 'alert-danger' : (type === 'warning' ? 'alert-warning' : 'alert-success');
        var $toast = $('<div class="alert ' + cls + '" style="margin-bottom:8px;">' + message + '</div>');
        $('#lmx-toast-container').append($toast);
        setTimeout(function() { $toast.fadeOut(300, function() { $toast.remove(); }); }, 5000);
    }

    // Sourced from lmx_rule_js_patterns() (field_validation_helper.php) —
    // the SAME character-set definitions validate_master_field() enforces
    // server-side, sent down as regex source strings so PHP and JS can't
    // silently drift apart on what a rule name allows. Built into real
    // RegExp objects here since JSON can't carry a RegExp directly.
    var LMX_RULE_DEFS = <?php echo json_encode(isset($rule_patterns) ? $rule_patterns : []); ?>;
    var LMX_RULES_STRICT = {};
    var LMX_RULES_KEYSTROKE = {};
    var LMX_RULE_MESSAGES = {};
    Object.keys(LMX_RULE_DEFS).forEach(function(name) {
        var def = LMX_RULE_DEFS[name];
        LMX_RULES_STRICT[name] = new RegExp(def.strict, def.flags);
        LMX_RULES_KEYSTROKE[name] = new RegExp(def.keystroke, def.flags);
        LMX_RULE_MESSAGES[name] = def.message;
    });

    // Keystroke filtering: strip characters the server would reject anyway,
    // same allowlist approach as validate_master_field() (block first, not
    // block-list) so the stored-XSS path into DataTables' unescaped cell
    // render can't be reached via generic-master forms either. Cursor
    // position preservation and the debounced toast are ported from
    // admin/assets/js/validation.js (the `support` branch's parallel
    // validation library) — typing into the middle of a value without either
    // jumps the caret to the end, and firing a toast on every keystroke
    // instead of once per burst is just noise.
    var lmxLastFilterToastTime = 0;
    $(document).on('input', '[data-rule]', function() {
        var rule = $(this).data('rule');
        var pattern = LMX_RULES_KEYSTROKE[rule];
        if (!pattern) return;
        var val = this.value;
        var cursorPos = this.selectionStart;
        var origLen = val.length;
        var filtered = '';
        for (var i = 0; i < val.length; i++) {
            if (pattern.test(filtered + val[i])) filtered += val[i];
        }
        if (filtered !== val) {
            this.value = filtered;
            var diff = origLen - filtered.length;
            var newPos = Math.max(0, cursorPos - diff);
            try { this.setSelectionRange(newPos, newPos); } catch (e) { /* some input types don't support it */ }

            var now = Date.now();
            if (LMX_RULE_MESSAGES[rule] && now - lmxLastFilterToastTime > 3000) {
                lmxLastFilterToastTime = now;
                lmxNotify(LMX_RULE_MESSAGES[rule], 'warning');
            }
        }
    });

    // Pre-submit validation: required + rule + maxlength + min/max + the
    // cached live-uniqueness result, across the whole form including
    // dynamically added child-table rows. Returns error strings labeled by
    // the field's own <label> text so messages read naturally.
    function lmxValidateForm(form) {
        var errors = [];
        form.find('[data-rule], [data-pattern], [required], [data-min], [data-max], [data-check-unique]').each(function() {
            var $f = $(this);
            var value = ($f.val() || '').toString().trim();
            var label = $f.closest('.form-group, td').find('label').first().text().replace('*', '').trim() || $f.attr('name');
            if ($f.prop('required') && value === '') {
                errors.push(label + ' is required');
                return;
            }
            if (value === '') return;
            // A per-field custom pattern (escape hatch, see _master_one_field_meta())
            // overrides the named rule's own strict check entirely when present.
            var customPattern = $f.data('pattern');
            if (customPattern) {
                var re = new RegExp(customPattern, $f.data('pattern-flags') || '');
                if (!re.test(value)) {
                    errors.push(label + ' ' + ($f.data('pattern-message') || 'is not in the allowed format'));
                }
            } else {
                var rule = $f.data('rule');
                if (rule && LMX_RULES_STRICT[rule] && !LMX_RULES_STRICT[rule].test(value)) {
                    errors.push(label + ' ' + (LMX_RULE_MESSAGES[rule] || 'is invalid'));
                }
            }
            var maxlen = $f.attr('maxlength');
            if (maxlen && value.length > parseInt(maxlen, 10)) {
                errors.push(label + ' must not exceed ' + maxlen + ' characters');
            }
            var minVal = $f.data('min');
            if (minVal !== undefined && parseFloat(value) < parseFloat(minVal)) {
                errors.push(label + ' must be at least ' + minVal);
            }
            var maxVal = $f.data('max');
            if (maxVal !== undefined && parseFloat(value) > parseFloat(maxVal)) {
                errors.push(label + ' must not exceed ' + maxVal);
            }
            // Cached result from the blur-time live check (lmxWireUniqueCheck
            // below) — only trusted when it was computed for this exact value,
            // so an edit after the check re-triggers a fresh one at blur.
            if ($f.data('checkedValue') === value && $f.data('checkedExists') === true) {
                errors.push(label + ' "' + value + '" already exists');
            }
        });
        return { valid: errors.length === 0, errors: errors };
    }

    // Cross-field rules (date_range, range_order) — same two types and same
    // semantics as lmx_validate_cross_fields() server-side: date_range is a
    // strict "from < to", range_order allows equality ("from <= to").
    function lmxCrossFieldCheck(rule, from, to) {
        if (from === '' || to === '') return null;
        if (rule.type === 'date_range' && new Date(from) >= new Date(to)) {
            return rule.message || 'Start date must be before end date';
        }
        if (rule.type === 'range_order' && parseFloat(from) > parseFloat(to)) {
            return rule.message || (rule.from + ' must not exceed ' + rule.to);
        }
        return null;
    }

    // Top-level fields have literal names (e.g. "fin_year_from").
    function lmxValidateCrossFields(form, rules) {
        var errors = [];
        (rules || []).forEach(function(rule) {
            var from = (form.find('[name="' + rule.from + '"]').val() || '').trim();
            var to = (form.find('[name="' + rule.to + '"]').val() || '').trim();
            var err = lmxCrossFieldCheck(rule, from, to);
            if (err) errors.push(err);
        });
        return errors;
    }

    // Child-table rows are named "postKey[idx][field]" with a non-sequential,
    // reusable idx (rows can be removed) — so each <tr> is checked against
    // its own from/to pair via a "name ends with [field]" match, not by index.
    function lmxValidateChildCrossFields(tbody, rules) {
        var errors = [];
        tbody.find('tr').each(function(rowNum) {
            var $row = $(this);
            (rules || []).forEach(function(rule) {
                var from = ($row.find('[name$="[' + rule.from + ']"]').val() || '').trim();
                var to = ($row.find('[name$="[' + rule.to + ']"]').val() || '').trim();
                var err = lmxCrossFieldCheck(rule, from, to);
                if (err) errors.push('Row ' + (rowNum + 1) + ': ' + err);
            });
        });
        return errors;
    }

    // Live "already exists" check on blur. Caches the checked value + result
    // on the element so lmxValidateForm() above can trust it at submit time
    // without re-querying (and so it self-invalidates the moment the value
    // changes again).
    $(document).on('blur', '[data-check-unique]', function() {
        var $f = $(this);
        var value = ($f.val() || '').trim();
        if (value === '') return;
        var data = { field: $f.attr('name'), value: value };
        var editId = $f.closest('form').find('.master-id').val();
        if (editId) data.exclude_id = editId;
        var extraField = $f.data('extra-field');
        if (extraField) {
            data.extra_value = $f.closest('form').find('[name="' + extraField + '"]').val();
        }
        $.ajax({
            url: fetch_url + '/check_unique',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(res) {
                $f.data('checkedValue', value);
                $f.data('checkedExists', !!res.exists);
                $f.closest('.form-group').toggleClass('has-error', !!res.exists);
            }
        });
    });

    <?php if (isset($config['child_table'])): $ct = $config['child_table']; $dropdown_options = isset($dropdown_options) ? $dropdown_options : []; $field_meta = isset($field_meta) ? $field_meta : []; ?>
    // Parent/detail repeater (e.g. Tax Group's tax components, Attribute's
    // value list) — field metadata for building rows client-side, since rows
    // are added/removed dynamically and Edit needs a fresh row per fetched
    // child record.
    var childTable = {
        postKey: <?php echo json_encode($ct['post_key']); ?>,
        pk: <?php echo json_encode($ct['pk']); ?>,
        fields: <?php
            $fieldDefs = [];
            foreach ($ct['inputs'] as $field => $type) {
                $widget = function_exists('_lmx_master_widget') ? _lmx_master_widget($field, $type, $ct) : 'text';
                $inline = (is_array($type) && isset($type[2]) && is_array($type[2]) && isset($type[2]['options'])) ? $type[2]['options'] : [];
                $opts = !empty($inline) ? $inline : (isset($dropdown_options[$field]) ? array_map(function($o) { return [$o['id'], $o['label']]; }, $dropdown_options[$field]) : []);
                $meta = isset($field_meta[$field]) ? $field_meta[$field] : [];
                $fieldDefs[] = [
                    'name'      => $field,
                    'widget'    => $widget,
                    'options'   => $opts,
                    'rule'      => isset($meta['rule']) ? $meta['rule'] : null,
                    'maxLength' => isset($meta['max_length']) ? $meta['max_length'] : null,
                    'required'  => !empty($meta['required']),
                ];
            }
            echo json_encode($fieldDefs);
        ?>
    };

    function lmxChildRowHtml(rowIndex, data) {
        data = data || {};
        var cells = childTable.fields.map(function(f) {
            var name = childTable.postKey + '[' + rowIndex + '][' + f.name + ']';
            var val = (data[f.name] !== undefined && data[f.name] !== null) ? data[f.name] : '';
            var control;
            if (f.widget === 'select' || f.widget === 'fixed-select') {
                var opts = '<option value="">-- Select --</option>';
                f.options.forEach(function(o) {
                    var selected = (String(o[0]) === String(val)) ? 'selected' : '';
                    opts += '<option value="' + o[0] + '" ' + selected + '>' + o[1] + '</option>';
                });
                control = '<select class="form-control" name="' + name + '">' + opts + '</select>';
            } else if (f.widget === 'number') {
                control = '<input type="number" step="any" class="form-control" name="' + name + '" value="' + val + '"' + (f.required ? ' required' : '') + ' />';
            } else {
                var ruleAttr = f.rule ? ' data-rule="' + f.rule + '"' : '';
                var maxAttr = f.maxLength ? ' maxlength="' + f.maxLength + '"' : '';
                control = '<input type="text" class="form-control" name="' + name + '" value="' + val + '"' + (f.required ? ' required' : '') + ruleAttr + maxAttr + ' />';
            }
            return '<td>' + control + '</td>';
        }).join('');
        var pkVal = (data[childTable.pk] !== undefined && data[childTable.pk] !== null) ? data[childTable.pk] : '';
        return '<tr>' + cells +
            '<td><input type="hidden" name="' + childTable.postKey + '[' + rowIndex + '][_pk]" value="' + pkVal + '" />' +
            '<button type="button" class="btn btn-danger btn-sm lmx-child-remove-row"><i class="fa fa-trash"></i></button></td></tr>';
    }

    $(document).on('click', '.lmx-child-add-row', function() {
        var tbody = $('#' + $(this).data('tbody-id'));
        tbody.append(lmxChildRowHtml(tbody.children().length));
    });
    $(document).on('click', '.lmx-child-remove-row', function() {
        $(this).closest('tr').remove();
    });
    <?php endif; ?>

    // Define columns dynamically based on PHP config
    var cols = [
        { "data": "<?php echo $config['pk']; ?>" }
    ];

    <?php foreach ($config['inputs'] as $field => $type):
        $listFieldWidget = function_exists('_lmx_master_widget') ? _lmx_master_widget($field, $type, $config) : 'text';
    ?>
        <?php if (!empty($config['dropdowns'][$field])): ?>
            // I-GMSJ-005: FK columns were showing the raw internal id in the
            // list grid (e.g. Selling Diamond's quality_code_id) instead of a
            // human-readable value — the engine already resolves this via
            // _resolve_fk_labels() into "<field>_label" for every dropdown,
            // just wasn't used here.
            cols.push({
                "data": "<?php echo $field; ?>",
                "render": function(data, type, row) {
                    return row["<?php echo $field; ?>_label"] || data;
                }
            });
        <?php elseif ($listFieldWidget === 'date'): ?>
            // I-GMSJ-005: DATETIME columns (e.g. Financial Year's From/To)
            // render their bare "00:00:00" time portion in the list grid —
            // meaningless for a date-only field, show just the date part.
            cols.push({
                "data": "<?php echo $field; ?>",
                "render": function(data) {
                    return data ? String(data).split(/[T ]/)[0] : data;
                }
            });
        <?php else: ?>
            cols.push({ "data": "<?php echo $field; ?>" });
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (isset($config['status_col'])): ?>
        cols.push({
            "data": "<?php echo $config['status_col']; ?>",
            "render": function(data, type, row) {
                var statusLabel = (data == 1) ? 'Active' : 'Inactive';
                var statusClass = (data == 1) ? 'label-success' : 'label-danger';
                return '<span class="label ' + statusClass + '">' + statusLabel + '</span>';
            }
        });
    <?php endif; ?>

    // Action column
    cols.push({
        "data": null,
        "render": function(data, type, row) {
            var pk_val = row["<?php echo $config['pk']; ?>"];
            var actions = '';
            <?php if (isset($access['edit']) && $access['edit'] == 1): ?>
                actions += '<a href="javascript:void(0)" class="btn btn-primary btn-sm lmx-edit-btn" data-id="'+pk_val+'" data-row=\''+JSON.stringify(row)+'\'><i class="fa fa-edit"></i></a> ';
            <?php endif; ?>
            <?php if (isset($access['delete']) && $access['delete'] == 1): ?>
                <?php if (isset($config['status_col'])): ?>
                    // This action deactivates (status = 0), it never removes the row — see
                    // _master_delete() in master_engine_helper.php. Icon/text/confirm/success
                    // message all say "deactivate", not "delete", so the UI doesn't promise
                    // something that isn't happening. Already-inactive rows get a visually
                    // disabled (dimmed, no click handler) button instead — deactivating an
                    // already-inactive row is a no-op, and offering it as if it does something
                    // is its own confusing-UI problem. Reactivation stays on Edit for now.
                    if (row["<?php echo $config['status_col']; ?>"] == 0) {
                        actions += '<a href="javascript:void(0)" class="btn btn-danger btn-sm disabled" title="Already inactive — reactivate via Edit" style="opacity:0.4; pointer-events:none; cursor:default;"><i class="fa fa-ban"></i></a>';
                    } else {
                        actions += '<a href="javascript:void(0)" class="btn btn-danger btn-sm lmx-delete-btn" title="Deactivate" data-id="'+pk_val+'"><i class="fa fa-ban"></i></a>';
                    }
                <?php else: ?>
                    actions += '<a href="javascript:void(0)" class="btn btn-danger btn-sm lmx-delete-btn" title="Delete" data-id="'+pk_val+'"><i class="fa fa-trash"></i></a>';
                <?php endif; ?>
            <?php endif; ?>
            return actions;
        }
    });

    var dt = $(table_id).DataTable({
        "processing": true,
        "serverSide": false,
        "ajax": {
            "url": fetch_url,
            "type": "POST",
            "dataSrc": "responseData"
        },
        "columns": cols,
        "order": [[0, "desc"]],
        "dom": 'lBfrtip',
        "buttons": ['excel', 'print']
    });
    // Generic masters don't load catalog_master.js, so init switches ourselves
    if (typeof $.fn.bootstrapSwitch === 'function') {
        $('#lmx-modal-add .switch, #lmx-modal-edit .switch').bootstrapSwitch();
    }

    // Handle Edit Click
    $(document).on('click', '.lmx-edit-btn', function() {
        var row = JSON.parse($(this).attr('data-row'));
        var id = $(this).attr('data-id');
        
        // Populate edit modal fields
        $('#lmx-modal-edit #edit_id').val(id);
        <?php foreach ($config['inputs'] as $field => $type):
            $fieldWidget = function_exists('_lmx_master_widget') ? _lmx_master_widget($field, $type, $config) : 'text';
        ?>
            <?php if ($fieldWidget === 'checkbox'): ?>
                $('#lmx-modal-edit #ed_<?php echo $field; ?>').bootstrapSwitch('state', row["<?php echo $field; ?>"] == 1);
            <?php elseif ($fieldWidget === 'select'): ?>
                // I-GMSJ-005: dropdown <option>s are filtered to active-only
                // (correct for choosing a NEW value), so a record saved while
                // its referenced row was active, then edited AFTER that row was
                // deactivated, has no matching <option> — .val() silently
                // leaves the field blank. Fall back to injecting one option
                // for the record's current (possibly inactive) value, using
                // the label the engine already resolves server-side
                // (_resolve_fk_labels -> "<field>_label"), so the user can see
                // what's actually selected instead of a blank dropdown.
                (function() {
                    var $sel = $('#lmx-modal-edit #ed_<?php echo $field; ?>');
                    var val = row["<?php echo $field; ?>"];
                    $sel.val(val);
                    if ($sel.val() === null && val !== null && val !== '') {
                        var label = row["<?php echo $field; ?>_label"] || ('#' + val);
                        $sel.append($('<option>', { value: val, text: label + ' (inactive)' }));
                        $sel.val(val);
                    }
                })();
            <?php elseif ($fieldWidget === 'date'): ?>
                // I-GMSJ-005: DATETIME columns come back from the AJAX list as
                // e.g. "2026-07-29T18:30:00.000Z" (or "... 00:00:00"), but a
                // native <input type="date"> only accepts a bare "YYYY-MM-DD"
                // — .val() silently no-ops on anything else, leaving the field
                // blank on Edit (Material Rate's effective_date, Financial
                // Year's From/To).
                (function() {
                    var raw = row["<?php echo $field; ?>"];
                    var datePart = raw ? String(raw).split(/[T ]/)[0] : '';
                    $('#lmx-modal-edit #ed_<?php echo $field; ?>').val(datePart);
                })();
            <?php else: ?>
                $('#lmx-modal-edit #ed_<?php echo $field; ?>').val(row["<?php echo $field; ?>"]);
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (isset($config['status_col'])): ?>
            var status_val = row["<?php echo $config['status_col']; ?>"];
            $('#lmx-modal-edit #ed_status').bootstrapSwitch('state', status_val == 1);
        <?php endif; ?>

        <?php if (isset($config['child_table'])): ?>
            // Child rows aren't in the DataTable row (only the parent's own
            // columns are) — fetch them before showing the modal.
            var childTbody = $('#lmx-child-rows-edit');
            childTbody.empty();
            $.ajax({
                url: fetch_url + '/child_rows/' + id,
                type: 'GET',
                dataType: 'json',
                success: function(childRows) {
                    childRows.forEach(function(childRow, i) {
                        childTbody.append(lmxChildRowHtml(i, childRow));
                    });
                    $('#lmx-modal-edit').modal('show');
                }
            });
        <?php else: ?>
            $('#lmx-modal-edit').modal('show');
        <?php endif; ?>
    });

    // Handle Save Form (Add / Edit)
    $('.lmx-save-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);

        var validation = lmxValidateForm(form);
        var crossFieldErrors = lmxValidateCrossFields(form, <?php echo json_encode(isset($config['cross_field_rules']) ? $config['cross_field_rules'] : []); ?>)
            .concat(lmxValidateChildCrossFields(form.find('.lmx-child-table tbody'), <?php echo json_encode(isset($config['child_table']['cross_field_rules']) ? $config['child_table']['cross_field_rules'] : []); ?>));
        var allErrors = validation.errors.concat(crossFieldErrors);
        if (allErrors.length > 0) {
            lmxNotify(allErrors.join('<br>'), 'error');
            return;
        }

        var type = form.data('type'); // 'add' or 'edit'
        var id = form.find('.master-id').val() || '';

        var submit_url = fetch_url + '/' + type;
        if (type == 'edit') submit_url += '/' + id;
        
        $.ajax({
            url: submit_url,
            type: 'POST',
            data: form.serialize(),
            dataType: 'JSON',
            success: function(res) {
                if (res.status == true || res.class == 'success') {
                    lmxNotify(res.message || res.msg, 'success');
                    $('.modal').modal('hide');
                    dt.ajax.reload();
                    form[0].reset();
                    form.find('.switch').each(function() {
                        $(this).bootstrapSwitch('state', $(this).prop('checked'));
                    });
                    // Dynamically added child rows aren't cleared by reset() —
                    // both modals start clean next time (edit repopulates via AJAX anyway).
                    form.find('.lmx-child-table tbody').empty();
                } else {
                    lmxNotify(res.message || res.msg || 'Error saving record', 'error');
                }
            },
            error: function() {
                lmxNotify('Server error — please try again', 'error');
            }
        });
    });

    // Handle Delete (soft-deactivate when this master has a status column —
    // see _master_delete() in master_engine_helper.php; the row is never
    // actually removed, so the confirm text must say so, not "delete").
    var isSoftDeactivate = <?php echo isset($config['status_col']) ? 'true' : 'false'; ?>;
    var deleteConfirmText = isSoftDeactivate
        ? 'Deactivate this record? It will stop appearing in dropdowns for new entries, but existing records referencing it are unaffected. You can reactivate it later from Edit.'
        : 'Are you sure you want to delete this record?';
    $(document).on('click', '.lmx-delete-btn', function() {
        var id = $(this).attr('data-id');
        if (confirm(deleteConfirmText)) {
            $.ajax({
                url: fetch_url + '/delete/' + id,
                type: 'POST',
                dataType: 'JSON',
                success: function(res) {
                    if (res.status == true || res.class == 'success') {
                        lmxNotify(res.message || res.msg, 'success');
                        dt.ajax.reload();
                    } else {
                        lmxNotify(res.message || res.msg || (isSoftDeactivate ? 'Error deactivating' : 'Error deleting'), 'error');
                    }
                }
            });
        }
    });
});
</script>
