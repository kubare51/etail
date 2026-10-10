  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
      <h1>
        Offer Mapping
        <small>Mapping List</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Catalog Master</a></li>
        <li class="active">Offer Mapping</li>
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
              <h3 class="box-title"><i class="fa fa-link"></i> Mapping List</h3>
            </div>
            <div class="box-body">

              <!-- Add Button -->
              <div class="row" style="margin-bottom:10px;">
                <div class="col-md-12">
                  <?php if(isset($access['add']) && $access['add'] == 1) { ?>
                  <a class="btn btn-success btn-flat pull-right" href="<?php echo base_url('index.php/admin_ret_offer/offer_mapping/add'); ?>"><i class="fa fa-plus-circle"></i> Add Mapping</a>
                  <?php } ?>
                </div>
              </div>

              <!-- DataTable -->
              <table id="mapping_list_table" class="table table-bordered table-striped" style="width:100%;">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Offer Name</th>
                    <th>Metal Type</th>
                    <th>Categories</th>
                    <th>Products</th>
                    <th>Designs</th>
                    <th>Sub-Designs</th>
                    <th>Branches</th>
                    <th>Age Filter</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
    </section>
  </div>

  <!-- CSS -->
  <link rel="stylesheet" href="<?php echo base_url('assets/css/ret_offer.css'); ?>">

  <script>
  window.addEventListener('load', function() {
    var base_url = '<?php echo base_url(); ?>';

    // ==========================================
    // DATATABLE — Client-side with AJAX source
    // ==========================================
    var mappingTable = $('#mapping_list_table').DataTable({
      processing: true,
      ajax: {
        url: base_url + 'index.php/admin_ret_offer/offer_mapping/ajax_list',
        type: 'POST',
        dataSrc: 'data',
        error: function(xhr, error, thrown) {
          console.error('DataTable AJAX error:', xhr.responseText);
          $.toaster({ priority: 'danger', title: 'Error', message: 'Failed to load mappings.' });
        }
      },
      columns: [
        { data: 'id_mapping' },
        { data: 'offer_name' },
        { data: 'metal_names' },
        { data: 'category_names' },
        { data: 'product_names' },
        { data: 'design_names' },
        { data: 'sub_design_names' },
        { data: 'branch_names' },
        { data: 'age_filter' },
        { data: 'action', orderable: false, searchable: false }
      ],
      order: [[0, 'desc']],
      pageLength: 25,
      language: {
        emptyTable: "No mappings found. Click 'Add Mapping' to create one.",
        processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i>'
      }
    });

    // ==========================================
    // DELETE MAPPING
    // ==========================================
    $(document).on('click', '.btn-delete-mapping', function() {
      var id = $(this).data('id');
      if (!confirm('Are you sure you want to delete this mapping?')) {
        return;
      }

      $.ajax({
        type: 'POST',
        url: base_url + 'index.php/admin_ret_offer/offer_mapping/delete/' + id,
        dataType: 'json',
        success: function(resp) {
          if (resp.status) {
            $.toaster({ priority: 'success', title: 'Success', message: resp.msg });
            mappingTable.ajax.reload();
          } else {
            $.toaster({ priority: 'danger', title: 'Error', message: resp.msg });
          }
        },
        error: function() {
          $.toaster({ priority: 'danger', title: 'Error', message: 'Failed to delete mapping.' });
        }
      });
    });
  });
  </script>
