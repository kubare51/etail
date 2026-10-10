<?php
/**
 * Generic Add / Edit modals for master screens.
 * Field controls are rendered by the shared ret_catalog/_field_widget partial,
 * so Add and Edit always stay in sync and support text/textarea/select/number/date.
 *
 * Vars: $config, $entity_name, $entity_key, $dropdown_options (optional), $field_meta (optional)
 */
$dropdown_options = isset($dropdown_options) ? $dropdown_options : [];
$field_meta = isset($field_meta) ? $field_meta : [];
?>
<!-- Add Modal -->
<div class="modal fade" id="lmx-modal-add" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog">
    <form class="modal-content lmx-save-form" data-type="add">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Add <?php echo $entity_name; ?></h4>
      </div>
      <div class="modal-body">
         <?php foreach ($config['inputs'] as $field => $type): ?>
             <?php $this->load->view('ret_catalog/_field_widget', [
                 'field'            => $field,
                 'type'             => $type,
                 'config'           => $config,
                 'mode'             => 'add',
                 'dropdown_options' => $dropdown_options,
                 'field_meta'       => $field_meta,
             ]); ?>
         <?php endforeach; ?>

         <?php if (isset($config['status_col'])): ?>
             <div class="row">
                 <div class="form-group">
                     <label class="col-md-4 col-md-offset-1">Status</label>
                     <div class="col-md-6">
                         <!-- Hidden fallback: an unchecked checkbox is omitted from
                              form submission entirely, which is indistinguishable
                              server-side from an API caller that never sent this
                              field at all. This hidden input guarantees a real form
                              submission always includes the field explicitly (0
                              alone when unchecked; 0 then 1 -- last one wins -- when
                              checked), so unchecking "Active" on Add reliably saves
                              as Inactive instead of always defaulting back to 1. -->
                         <input type="hidden" name="<?php echo $config['status_col']; ?>" value="0" />
                         <input type="checkbox" class="switch" name="<?php echo $config['status_col']; ?>" value="1" checked data-on-text="YES" data-off-text="NO" />
                         <span class="help-block">Active if checked</span>
                     </div>
                 </div>
             </div>
         <?php endif; ?>

         <?php if (isset($config['child_table'])): ?>
             <?php $this->load->view('ret_catalog/_child_table', ['config' => $config, 'mode' => 'add']); ?>
         <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="lmx-modal-edit" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog">
    <form class="modal-content lmx-save-form" data-type="edit">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Edit <?php echo $entity_name; ?></h4>
      </div>
      <div class="modal-body">
         <input type="hidden" class="master-id" id="edit_id" value="" />
         <?php foreach ($config['inputs'] as $field => $type): ?>
             <?php $this->load->view('ret_catalog/_field_widget', [
                 'field'            => $field,
                 'type'             => $type,
                 'config'           => $config,
                 'mode'             => 'edit',
                 'dropdown_options' => $dropdown_options,
                 'field_meta'       => $field_meta,
             ]); ?>
         <?php endforeach; ?>

         <?php if (isset($config['status_col'])): ?>
             <div class="row">
                 <div class="form-group">
                     <label class="col-md-4 col-md-offset-1">Status</label>
                     <div class="col-md-6">
                         <!-- See the Add modal's matching comment above — same
                              unchecked-checkbox-omitted-from-POST issue applies here. -->
                         <input type="hidden" name="<?php echo $config['status_col']; ?>" value="0" />
                         <input type="checkbox" class="switch" name="<?php echo $config['status_col']; ?>" id="ed_status" value="1" data-on-text="YES" data-off-text="NO" />
                         <span class="help-block">Active if checked</span>
                     </div>
                 </div>
             </div>
         <?php endif; ?>

         <?php if (isset($config['child_table'])): ?>
             <?php $this->load->view('ret_catalog/_child_table', ['config' => $config, 'mode' => 'edit']); ?>
         <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Update</button>
        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
      </div>
    </form>
  </div>
</div>
