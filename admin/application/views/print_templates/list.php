<div class="content-wrapper">
    <section class="content-header">
        <h1>
            Print Templates
            <small>Manage Print Formats</small>
        </h1>
        <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
            <li class="active">Print Templates</li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title">Template List</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-warning btn-sm" id="btnUploadTemplate" onclick="openImport()"><i class="fa fa-upload"></i> Upload Template</button>
                            <a href="<?php echo base_url('index.php/print-templates/add'); ?>" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> New Template</a>
                        </div>
                    </div>
                    <!-- Bulk Action Toolbar — ALWAYS visible -->
                    <div id="bulkActionBar" style="background:#f5f5f5; border-bottom:2px solid #ddd; padding:10px 15px;">
                        <button type="button" class="btn btn-default btn-sm" id="btnSelectAll" onclick="doSelectAll()">
                            <i class="fa fa-check-square-o"></i> Select All
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="btnBulkDelete" onclick="confirmBulkDelete()" style="margin-left:8px;">
                            <i class="fa fa-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                        </button>
                        <button type="button" class="btn btn-default btn-sm" id="btnDeselectAll" onclick="doDeselectAll()" style="margin-left:8px;">
                            <i class="fa fa-times"></i> Clear Selection
                        </button>
                        <span class="text-muted" style="margin-left:15px; font-size:12px;">
                            <i class="fa fa-info-circle"></i> Max 24 records per bulk delete | Active &amp; Default templates are excluded
                        </span>

                        <!-- Revert to Default — inline -->
                        <span style="display:inline-flex; align-items:center; margin-left:15px; gap:5px;">
                            <label style="font-weight:600; font-size:12px; margin:0; white-space:nowrap;">
                                <i class="fa fa-undo"></i> Revert to Default:
                            </label>
                            <select id="revertTemplateSelect" style="width:auto; min-width:120px;">
                                <option value="">-- Select Template --</option>
                                <?php if (!empty($template_groups)): ?>
                                    <?php foreach ($template_groups as $grp): ?>
                                        <option value="<?php echo htmlspecialchars($grp['code']); ?>"><?php echo htmlspecialchars($grp['template_name']); ?> (<?php echo htmlspecialchars($grp['code']); ?>)</option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <button type="button" class="btn btn-warning btn-sm" id="btnRevertDefault" onclick="doRevertDefault()">
                                <i class="fa fa-undo"></i> Revert
                            </button>
                        </span>
                    </div>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <table id="templateTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th style="width:30px; text-align:center;"></th>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Template Code</th>
                                    <th>Category</th>
                                    <th>Size</th>
                                    <th>Default</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populated via server-side DataTables AJAX -->
                            </tbody>
                        </table>
                    </div>
                    <!-- /.box-body -->
                </div>
                <!-- /.box -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </section>
</div>

<script>
  // All variables declared at top level (no jQuery dependency)
  var activateId = null;
  var activateUrl = '<?php echo base_url('index.php/print-templates/toggle-active/'); ?>';
  var bulkDeleteUrl = '<?php echo base_url('index.php/admin_print_template/bulk_delete'); ?>';
  var ajaxListUrl = '<?php echo base_url('index.php/print-templates/ajax-list'); ?>';
  var revertDefaultUrl = '<?php echo base_url('index.php/admin_print_template/revert_default'); ?>';
  var importUrl = '<?php echo base_url('index.php/print-templates/import'); ?>';
  var BULK_DELETE_MAX = 24;
  var templateDT = null;

  // Wait for jQuery + DataTables to load (they are in footer.php which loads AFTER this view)
  (function waitForLibs() {
      if (typeof jQuery === 'undefined' || typeof jQuery.fn === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
          return setTimeout(waitForLibs, 100);
      }

      var $ = jQuery;

      // ── Activate Functions ──
      window.confirmActivate = function(id, name, code) {
          activateId = id;
          var msg = 'Are you sure you want to activate template <strong>"' + name + '"</strong> (ID: ' + id + ')?';
          if (code) {
              msg += '<br><br><small class="text-muted">All other versions with code <strong>"' + code + '"</strong> will be deactivated.</small>';
          }
          $('#confirm-activate .activate-msg').html(msg);
          $('#confirm-activate').modal('show');
      };

      window.doActivate = function() {
          if (!activateId) return;
          var $btn = $('#btn-confirm-activate');
          $btn.prop('disabled', true).text('Activating...');
          $.ajax({
              url: activateUrl + '/' + activateId,
              type: 'GET',
              dataType: 'json',
              success: function(res) {
                  if (res && res.success) {
                      $('#confirm-activate').modal('hide');
                      templateDT.ajax.reload(null, false);
                  } else {
                      alert('Failed to activate template.');
                      $btn.prop('disabled', false).text('Yes, Activate');
                  }
              },
              error: function() {
                  alert('Failed to activate template.');
                  $btn.prop('disabled', false).text('Yes, Activate');
              }
          });
      };

      // ── Bulk Delete Functions ──
      function updateSelectedCount() {
          var count = $('.row-check:checked').length;
          $('#selectedCount').text(count);
      }

      window.doSelectAll = function() {
          $('.row-check').prop('checked', false);
          var selected = 0;
          var rows = templateDT.rows({ page: 'current' }).nodes();
          $(rows).each(function() {
              if (selected >= BULK_DELETE_MAX) return false;
              var $cb = $(this).find('.row-check');
              if ($cb.length) {
                  $cb.prop('checked', true);
                  selected++;
              }
          });
          updateSelectedCount();
          if (selected === 0) {
              alert('No inactive templates on this page to select.');
          }
      };

      window.doDeselectAll = function() {
          $('.row-check').prop('checked', false);
          updateSelectedCount();
      };

      // ── Revert to Default ──
      // Initialize Select2 on the revert dropdown
      $('#revertTemplateSelect').select2({
          placeholder: '-- Select Template --',
          allowClear: true,
          width: 'resolve'
      });

      window.doRevertDefault = function() {
          var code = $('#revertTemplateSelect').val();
          if (!code) {
              alert('Please select a template to revert.');
              return;
          }

          var templateText = $('#revertTemplateSelect option:selected').text().trim();
          if (!confirm('Are you sure you want to revert "' + templateText + '" to its default version?\n\nThis will:\n• Copy the default template as a new record\n• Deactivate the current active version\n• Make the new copy active')) {
              return;
          }

          var $btn = $('#btnRevertDefault');
          $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Reverting...');

          $.ajax({
              url: revertDefaultUrl,
              type: 'POST',
              data: { code: code },
              dataType: 'json',
              success: function(res) {
                  if (res && res.success) {
                      alert(res.message);
                      templateDT.ajax.reload(null, false);
                      $('#revertTemplateSelect').val('').trigger('change');
                  } else {
                      alert('Error: ' + (res.message || 'Failed to revert template.'));
                  }
                  $btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Revert');
              },
              error: function() {
                  alert('Server error while reverting template.');
                  $btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Revert');
              }
          });
      };

      window.confirmBulkDelete = function() {
          var count = $('.row-check:checked').length;
          if (count === 0) {
              alert('No templates selected.\n\nPlease select at least one inactive template to delete.');
              return;
          }
          if (count > BULK_DELETE_MAX) {
              alert('You can only delete up to ' + BULK_DELETE_MAX + ' records at a time.\nCurrently selected: ' + count + '\n\nPlease deselect some records.');
              return;
          }
          $('#bulkDeleteCount').text(count);
          $('#confirm-bulk-delete').modal('show');
      };

      window.doBulkDelete = function() {
          var ids = [];
          $('.row-check:checked').each(function() {
              ids.push($(this).val());
          });
          if (ids.length === 0) return;
          if (ids.length > BULK_DELETE_MAX) {
              alert('Maximum ' + BULK_DELETE_MAX + ' records allowed per bulk delete.');
              return;
          }
          var $btn = $('#btn-confirm-bulk-delete');
          $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');
          $.ajax({
              url: bulkDeleteUrl,
              type: 'POST',
              data: { ids: ids },
              dataType: 'json',
              success: function(res) {
                  if (res && res.success) {
                      $('#confirm-bulk-delete').modal('hide');
                      alert('Successfully deleted ' + res.deleted_count + ' template(s).\nBackup saved to /template/ folder.');
                      templateDT.ajax.reload(null, false);
                  } else {
                      alert('Error: ' + (res.message || 'Failed to delete templates.'));
                      $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Yes, Delete All');
                  }
              },
              error: function() {
                  alert('Server error while deleting templates.');
                  $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Yes, Delete All');
              }
          });
      };

      // ── Initialize DataTable ──
      templateDT = $('#templateTable').DataTable({
          "processing": true,
          "serverSide": true,
          "ajax": {
              "url": ajaxListUrl,
              "type": "POST",
              "error": function(xhr, error, thrown) {
                  console.error('DataTables AJAX error:', error, thrown, xhr.responseText);
                  alert('Failed to load templates.\n\nStatus: ' + xhr.status + '\nError: ' + thrown);
              }
          },
          "pageLength": 25,
          "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
          "ordering": true,
          "info": true,
          "autoWidth": false,
          "columnDefs": [
              { "orderable": false, "targets": [0, 9] },
              { "className": "text-center", "targets": [0, 1] }
          ],
          "order": [[1, "desc"]]
      });

      // Reset selection count on page change
      templateDT.on('draw', function() {
          updateSelectedCount();
      });

      // Bind delete modal
      $(document).on('click', '.btn-del', function(e) {
          e.preventDefault();
          var link = $(this).data('href');
          $('#confirm-delete .btn-confirm').attr('href', link);
          $('#confirm-delete').modal('show');
      });

      // ── Upload — inserts the uploaded file as a NEW template record ──
      window.openImport = function() {
          $('#importFile').val('');
          $('#importError').hide().text('');
          $('#btn-confirm-import').prop('disabled', false).html('<i class="fa fa-upload"></i> Upload &amp; Create');
          $('#confirm-import').modal('show');
      };

      window.doImport = function() {
          var file = $('#importFile')[0].files[0];
          if (!file) {
              $('#importError').text('Choose a .json template file first.').show();
              return;
          }

          var fd = new FormData();
          fd.append('template_file', file);

          var $btn = $('#btn-confirm-import');
          $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Uploading...');
          $('#importError').hide();

          $.ajax({
              url: importUrl,
              type: 'POST',
              data: fd,
              processData: false,
              contentType: false,
              dataType: 'json',
              success: function(res) {
                  if (res && res.success) {
                      $('#confirm-import').modal('hide');
                      alert(res.message);
                      templateDT.ajax.reload(null, false);
                  } else {
                      $('#importError').text(res && res.message ? res.message : 'Upload failed.').show();
                      $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Upload &amp; Create');
                  }
              },
              error: function() {
                  $('#importError').text('Server error while uploading the template.').show();
                  $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Upload &amp; Create');
              }
          });
      };

      // Reset modals on close
      $('#confirm-activate').on('hidden.bs.modal', function() {
          $('#btn-confirm-activate').prop('disabled', false).text('Yes, Activate');
          activateId = null;
      });
      $('#confirm-bulk-delete').on('hidden.bs.modal', function() {
          $('#btn-confirm-bulk-delete').prop('disabled', false).html('<i class="fa fa-trash"></i> Yes, Delete All');
      });

      // Individual checkbox change
      $('#templateTable tbody').on('change', '.row-check', function() {
          var totalChecked = $('.row-check:checked').length;
          if (totalChecked > BULK_DELETE_MAX) {
              $(this).prop('checked', false);
              alert('Cannot select more than ' + BULK_DELETE_MAX + ' records.\nPlease deselect some before selecting more.');
              totalChecked--;
          }
          updateSelectedCount();
      });
  })();
</script>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h4 class="modal-title" id="myModalLabel">Delete Template</h4>
      </div>
      <div class="modal-body">
               <strong>Are you sure! You want to delete this template?</strong>
      </div>
      <div class="modal-footer">
      	<a href="#" class="btn btn-danger btn-confirm">Delete</a>
        <button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Activate Confirmation Modal -->
<div class="modal fade" id="confirm-activate" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background-color: #00a65a; color: #fff;">
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-check-circle"></i> Activate Template</h4>
      </div>
      <div class="modal-body">
        <div class="activate-msg"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" id="btn-confirm-activate" onclick="doActivate()">Yes, Activate</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Import (Upload) Template Modal -->
<div class="modal fade" id="confirm-import" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background-color: #f39c12; color: #fff;">
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-upload"></i> Upload Template</h4>
      </div>
      <div class="modal-body">
        <p>Create a <strong>new template record</strong> from an exported template file.</p>

        <div class="form-group">
          <label for="importFile">Template file (.json)</label>
          <input type="file" id="importFile" accept=".json,application/json">
          <p class="help-block"><small>Use a file produced by the <i class="fa fa-download"></i> Download button on any template row.</small></p>
        </div>

        <p class="text-muted"><small><i class="fa fa-info-circle"></i> Existing templates are not modified. The new record is added as <strong>Inactive</strong> with a fresh Template Code &mdash; click <strong>Activate</strong> on that row when you want to print with it.</small></p>

        <div class="alert alert-danger" id="importError" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-warning" id="btn-confirm-import" onclick="doImport()"><i class="fa fa-upload"></i> Upload &amp; Create</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div class="modal fade" id="confirm-bulk-delete" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background-color: #dd4b39; color: #fff;">
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-exclamation-triangle"></i> Bulk Delete Templates</h4>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete <strong><span id="bulkDeleteCount">0</span></strong> selected template(s)?</p>
        <p class="text-muted"><small><i class="fa fa-info-circle"></i> The template data (gjs_data) will be backed up to files before deletion.</small></p>
        <p class="text-danger"><strong>This action cannot be undone!</strong></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="btn-confirm-bulk-delete" onclick="doBulkDelete()"><i class="fa fa-trash"></i> Yes, Delete All</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

