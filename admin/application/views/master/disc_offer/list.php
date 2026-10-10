  <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
      <!-- Content Header (Page header) -->
      <section class="content-header">
        <h1>
          Offer Master
        </h1>
        <ol class="breadcrumb">
          <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
          <li><a href="#">Catalog Master</a></li>
          <li class="active">Offer Master</li>
        </ol>
      </section>

      <!-- Main content -->
      <section class="content">
        <div class="row">
          <div class="col-xs-12">

            <!-- Alert -->
            <?php
              if($this->session->flashdata('chit_alert'))
              {
                $message = $this->session->flashdata('chit_alert');
            ?>
              <div class="alert alert-<?php echo $message['class']; ?> alert-dismissable">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <h4><i class="icon fa fa-check"></i> <?php echo $message['title']; ?>!</h4>
                <?php echo $message['message']; ?>
              </div>
            <?php } ?>

            <div class="box box-primary">
              <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-gift"></i> Offer List</h3>
              </div><!-- /.box-header -->
              <div class="box-body">

                <!-- Filter row -->
                <div class="row" style="margin-bottom:10px;">
                  <div class="col-md-2">
                    <div class="form-group" style="margin-bottom:0;">
                      <label>Status</label>
                      <select id="status_filter" class="form-control">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                      </select>
                    </div>
                  </div>

                  <div class="col-md-2 pull-right">
                    <div class="form-group" style="margin-bottom:0;">
                      <label>&nbsp;</label><br>
                      <?php if(isset($access['add']) && $access['add'] == 1) { ?>
                      <a class="btn btn-success btn-flat pull-right" href="<?php echo base_url('index.php/admin_ret_offer/offer_master/add'); ?>"><i class="fa fa-plus-circle"></i> Add Offer</a>
                      <?php } ?>
                    </div>
                  </div>
                </div>

                <!-- DataTable -->
                <div class="table-responsive">
                  <table id="offer_master_list" class="table table-bordered table-striped text-center" style="width:100%;">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Offer Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Metal Rate Disc</th>
                        <th>MC Disc %</th>
                        <th>VA Disc %</th>
                        <th>Diamond Disc</th>
                        <th>Taxable Value %</th>
                        <th>Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                  </table>
                </div>

              </div><!-- /.box-body -->
            </div><!-- /.box -->
          </div><!-- /.col -->
        </div><!-- /.row -->
      </section><!-- /.content -->
    </div><!-- /.content-wrapper -->

<!-- Delete Confirm Modal -->
<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h4 class="modal-title" id="myModalLabel"><i class="fa fa-exclamation-triangle text-red"></i> Delete Offer</h4>
      </div>
      <div class="modal-body">
        <strong>Are you sure! You want to delete this Offer record?</strong>
      </div>
      <div class="modal-footer">
        <a href="#" class="btn btn-danger btn-flat btn-confirm"><i class="fa fa-trash"></i> Delete</a>
        <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- / Delete Confirm Modal -->

<link rel="stylesheet" href="<?php echo base_url('assets/css/ret_offer.css'); ?>">

<!--
  NOTE: Using DOMContentLoaded because jQuery loads in footer.php AFTER this view.
-->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ==========================================
    // DATATABLE INIT
    // ==========================================
    var offerTable = $('#offer_master_list').DataTable({
        processing: true,
        ajax: {
            url: base_url + 'index.php/admin_ret_offer/offer_master/ajax_list',
            type: 'POST',
            dataSrc: 'data',
            data: function(d) {
                d.status_filter = $('#status_filter').val();
            },
            error: function(xhr, error, thrown) {
                console.error('DataTable AJAX error:', xhr.responseText);
                $.toaster({ priority: 'danger', title: 'Error', message: 'Failed to load data. Check console for details.' });
            }
        },
        columns: [
            { data: 'id_offer' },
            { data: 'offer_name' },
            { data: 'start_date' },
            { data: 'end_date' },
            { data: 'metal_rate_disc' },
            { data: 'mc_disc' },
            { data: 'va_disc' },
            { data: 'diamond_disc' },
            { data: 'taxable_value' },
            { data: 'status_badge' },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: "No offers found. Click 'Add Offer' to create one.",
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i>'
        }
    });

    // ==========================================
    // STATUS FILTER — auto-reload on change
    // ==========================================
    $('#status_filter').on('change', function() {
        offerTable.ajax.reload();
    });

    // ==========================================
    // DELETE OFFER
    // ==========================================
    var deleteId = null;

    $(document).on('click', '.btn-delete-offer', function() {
        deleteId = $(this).data('id');
        $('#confirm-delete').modal('show');
    });

    $('#confirm-delete .btn-confirm').on('click', function(e) {
        e.preventDefault();
        if (!deleteId) return;

        $.ajax({
            url: base_url + 'index.php/admin_ret_offer/offer_master/delete/' + deleteId,
            type: 'POST',
            dataType: 'json',
            success: function(res) {
                $('#confirm-delete').modal('hide');
                if (res.status) {
                    $.toaster({ priority: 'success', title: 'Success', message: res.msg });
                    offerTable.ajax.reload();
                } else {
                    $.toaster({ priority: 'danger', title: 'Error', message: res.msg });
                }
            },
            error: function() {
                $('#confirm-delete').modal('hide');
                $.toaster({ priority: 'danger', title: 'Error', message: 'Failed to delete. Please try again.' });
            }
        });
    });

    // ==========================================
    // TOGGLE STATUS
    // ==========================================
    $(document).on('click', '.btn-toggle-status', function() {
        var id = $(this).data('id');
        var $btn = $(this);

        $.ajax({
            url: base_url + 'index.php/admin_ret_offer/offer_master/toggle_status/' + id,
            type: 'POST',
            dataType: 'json',
            success: function(res) {
                if (res.status) {
                    var newLabel = res.new_status == 1 ? 'Activated' : 'Deactivated';
                    $.toaster({ priority: 'success', title: 'Success', message: 'Offer ' + newLabel + ' successfully' });
                    offerTable.ajax.reload(null, false);
                } else {
                    $.toaster({ priority: 'danger', title: 'Error', message: res.msg || 'Failed to toggle status' });
                }
            },
            error: function() {
                $.toaster({ priority: 'danger', title: 'Error', message: 'Something went wrong.' });
            }
        });
    });

});
</script>
