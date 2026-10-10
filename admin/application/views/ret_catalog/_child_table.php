<?php
/**
 * Generic parent/detail repeater skeleton — renders the child-rows table for
 * masters with config['child_table'] set (Tax Group's tax components,
 * Attribute's value list). Included from add_generic.php in both the Add and
 * Edit modals; row-building/add/remove behavior lives in master_list.php's
 * script block since it's shared logic driven by config['child_table'].
 *
 * Vars: $config, $mode ('add'|'edit')
 */
$ct = $config['child_table'];
$tbody_id = 'lmx-child-rows-' . $mode;
?>
<div class="row">
    <div class="col-md-11 col-md-offset-1">
        <label><?php echo $ct['label']; ?> Rows <span class="error">*</span></label>
        <table class="table table-bordered lmx-child-table" data-tbody-id="<?php echo $tbody_id; ?>">
            <thead>
                <tr>
                    <?php foreach ($ct['inputs'] as $field => $type): ?>
                        <th><?php echo isset($ct['rules'][$field]['label']) ? $ct['rules'][$field]['label'] : ucfirst($field); ?></th>
                    <?php endforeach; ?>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="<?php echo $tbody_id; ?>"></tbody>
        </table>
        <button type="button" class="btn btn-sm btn-primary lmx-child-add-row" data-tbody-id="<?php echo $tbody_id; ?>">
            <i class="fa fa-plus"></i> Add Row
        </button>
    </div>
</div>
<br/>
