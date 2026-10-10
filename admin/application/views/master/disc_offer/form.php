<?php
  // Determine add vs edit mode
  $is_edit = (isset($offer['id_offer']) && $offer['id_offer'] > 0);
  $page_title = $is_edit ? 'Edit Offer' : 'Add Offer';
?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        Offer Master
        <small><?php echo $page_title; ?></small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Catalog Master</a></li>
        <li><a href="<?php echo base_url('index.php/admin_ret_offer/offer_master/list'); ?>">Offer Master</a></li>
        <li class="active"><?php echo $page_title; ?></li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">

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

      <!-- Default box -->
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-gift"></i> <?php echo $page_title; ?></h3>
          <div class="box-tools pull-right">
            <button class="btn btn-box-tool" data-widget="collapse" data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
          </div>
        </div>
        <div class="box-body">

          <form id="offer_master_form" method="post" action="javascript:void(0);">

            <!-- Hidden field for edit mode -->
            <input type="hidden" id="id_offer" name="id_offer" value="<?php echo isset($offer['id_offer']) ? $offer['id_offer'] : ''; ?>">

            <!-- Row 1: Offer Name, Start Date, End Date -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="offer_name">Offer Name <span class="text-red">*</span></label>
                  <input type="text" class="form-control" id="offer_name" name="offer_name" maxlength="100" placeholder="e.g. Summer Dhamaka 2026" value="<?php echo isset($offer['offer_name']) ? htmlspecialchars($offer['offer_name']) : ''; ?>" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="start_date">Start Date <span class="text-red">*</span></label>
                  <div class="input-group">
                    <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
                    <input type="text" class="form-control" id="start_date" name="start_date" placeholder="dd-mm-yyyy" value="<?php echo isset($offer['start_date']) && $offer['start_date'] ? date('d-m-Y', strtotime($offer['start_date'])) : ''; ?>" required>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="end_date">End Date <span class="text-red">*</span></label>
                  <div class="input-group">
                    <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
                    <input type="text" class="form-control" id="end_date" name="end_date" placeholder="dd-mm-yyyy" value="<?php echo isset($offer['end_date']) && $offer['end_date'] ? date('d-m-Y', strtotime($offer['end_date'])) : ''; ?>" required>
                  </div>
                </div>
              </div>
            </div>

            <hr style="border-top:1px dashed #ddd; margin:10px 0 15px;">

            <!-- Row 2: Metal Rate + Diamond Discount panels side by side -->
            <div class="row">
              <div class="col-md-6">
                <div class="panel panel-default" style="border-color:#3c8dbc; margin-bottom:15px;">
                  <div class="panel-heading" style="background:#3c8dbc; color:#fff; padding:8px 12px;">
                    <i class="fa fa-cubes"></i> Discount on Metal Rate (% / ₹ per gram)
                  </div>
                  <div class="panel-body" style="padding:12px;">
                    <div class="row" style="margin-bottom:8px;">
                      <div class="col-xs-12">
                        <label class="radio-inline" style="font-weight:normal;">
                          <input type="radio" name="metal_rate_type" value="pct" <?php echo (!isset($offer['disc_metal_rate_flat']) || empty($offer['disc_metal_rate_flat']) || floatval($offer['disc_metal_rate_flat']) <= 0) ? 'checked' : ''; ?>> Percentage (%)
                        </label>
                        <label class="radio-inline" style="font-weight:normal;">
                          <input type="radio" name="metal_rate_type" value="flat" <?php echo (isset($offer['disc_metal_rate_flat']) && floatval($offer['disc_metal_rate_flat']) > 0) ? 'checked' : ''; ?>> Flat Amount (₹)
                        </label>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-xs-6">
                        <div class="form-group" style="margin-bottom:5px;" id="metal_pct_group">
                          <label style="font-size:12px;">Percentage</label>
                          <div class="input-group">
                            <input type="number" class="form-control" id="disc_metal_rate_pct" name="disc_metal_rate_pct" step="0.01" min="0" max="100" placeholder="0.00" value="<?php echo isset($offer['disc_metal_rate_pct']) ? $offer['disc_metal_rate_pct'] : ''; ?>">
                            <span class="input-group-addon">%</span>
                          </div>
                        </div>
                      </div>
                      <div class="col-xs-6">
                        <div class="form-group" style="margin-bottom:5px;" id="metal_flat_group">
                          <label style="font-size:12px;">Flat (per gram)</label>
                          <div class="input-group">
                            <span class="input-group-addon">₹</span>
                            <input type="number" class="form-control" id="disc_metal_rate_flat" name="disc_metal_rate_flat" step="0.01" min="0" placeholder="0.00" value="<?php echo isset($offer['disc_metal_rate_flat']) ? $offer['disc_metal_rate_flat'] : ''; ?>">
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="panel panel-default" style="border-color:#00a65a; margin-bottom:15px;">
                  <div class="panel-heading" style="background:#00a65a; color:#fff; padding:8px 12px;">
                    <i class="fa fa-diamond"></i> Diamond Discount (₹ per ct / %)
                  </div>
                  <div class="panel-body" style="padding:12px;">
                    <div class="row" style="margin-bottom:8px;">
                      <div class="col-xs-12">
                        <label class="radio-inline" style="font-weight:normal;">
                          <input type="radio" name="diamond_disc_type" value="per_ct" <?php echo (!isset($offer['disc_diamond_flat_pct']) || empty($offer['disc_diamond_flat_pct']) || floatval($offer['disc_diamond_flat_pct']) <= 0) ? 'checked' : ''; ?>> Per Carat (₹)
                        </label>
                        <label class="radio-inline" style="font-weight:normal;">
                          <input type="radio" name="diamond_disc_type" value="flat_pct" <?php echo (isset($offer['disc_diamond_flat_pct']) && floatval($offer['disc_diamond_flat_pct']) > 0) ? 'checked' : ''; ?>> Flat Percentage (%)
                        </label>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-xs-6">
                        <div class="form-group" style="margin-bottom:5px;" id="diamond_ct_group">
                          <label style="font-size:12px;">Per Carat</label>
                          <div class="input-group">
                            <span class="input-group-addon">₹</span>
                            <input type="number" class="form-control" id="disc_diamond_per_ct" name="disc_diamond_per_ct" step="0.01" min="0" placeholder="0.00" value="<?php echo isset($offer['disc_diamond_per_ct']) ? $offer['disc_diamond_per_ct'] : ''; ?>">
                          </div>
                        </div>
                      </div>
                      <div class="col-xs-6">
                        <div class="form-group" style="margin-bottom:5px;" id="diamond_pct_group">
                          <label style="font-size:12px;">Flat Percentage</label>
                          <div class="input-group">
                            <input type="number" class="form-control" id="disc_diamond_flat_pct" name="disc_diamond_flat_pct" step="0.01" min="0" max="100" placeholder="0.00" value="<?php echo isset($offer['disc_diamond_flat_pct']) ? $offer['disc_diamond_flat_pct'] : ''; ?>">
                            <span class="input-group-addon">%</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Row 3: MC, VA, Taxable Value, Status -->
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label for="disc_mc_pct">MC Discount (%)</label>
                  <div class="input-group">
                    <input type="number" class="form-control" id="disc_mc_pct" name="disc_mc_pct" step="0.01" min="0" max="100" placeholder="0.00" value="<?php echo isset($offer['disc_mc_pct']) ? $offer['disc_mc_pct'] : ''; ?>">
                    <span class="input-group-addon">%</span>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="disc_va_pct">VA Discount (%)</label>
                  <div class="input-group">
                    <input type="number" class="form-control" id="disc_va_pct" name="disc_va_pct" step="0.01" min="0" max="100" placeholder="0.00" value="<?php echo isset($offer['disc_va_pct']) ? $offer['disc_va_pct'] : ''; ?>">
                    <span class="input-group-addon">%</span>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="disc_taxable_value_pct">Product Taxable Value (%)</label>
                  <div class="input-group">
                    <input type="number" class="form-control" id="disc_taxable_value_pct" name="disc_taxable_value_pct" step="0.01" min="0" max="100" placeholder="0.00" value="<?php echo isset($offer['disc_taxable_value_pct']) ? $offer['disc_taxable_value_pct'] : ''; ?>">
                    <span class="input-group-addon">%</span>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Status</label>
                  <div class="status-radio-toggle" style="padding-top:4px;">
                    <label class="status-radio-label status-active">
                      <input type="radio" name="status" value="1" <?php echo (!isset($offer['status']) || $offer['status'] == 1) ? 'checked' : ''; ?>>
                      <span class="status-radio-text">Active</span>
                    </label>
                    <label class="status-radio-label status-inactive">
                      <input type="radio" name="status" value="0" <?php echo (isset($offer['status']) && $offer['status'] == 0) ? 'checked' : ''; ?>>
                      <span class="status-radio-text">Inactive</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Buttons -->
            <div class="row">
              <div class="col-xs-12 text-center">
                <hr style="border-top:1px dashed #ddd;">
                <button type="submit" id="btn_save" class="btn btn-primary btn-flat"><i class="fa fa-save"></i> Save Offer</button>
                <a href="<?php echo base_url('index.php/admin_ret_offer/offer_master/list'); ?>" class="btn btn-default btn-flat"><i class="fa fa-arrow-left"></i> Cancel</a>
              </div>
            </div>

          </form>

        </div><!-- /.box-body -->
        <div class="overlay" style="display:none">
          <i class="fa fa-refresh fa-spin"></i>
        </div>
      </div><!-- /.box -->

    </section><!-- /.content -->
  </div><!-- /.content-wrapper -->

<link rel="stylesheet" href="<?php echo base_url('assets/css/ret_offer.css'); ?>">

<!--
  All Offer Master form behavior (datepickers, radio toggles, validation,
  AJAX submit) lives in assets/js/ret_offer.js. This view previously also
  had its own copy of the same logic inline, which meant every Save click
  fired two independent submit handlers and two POSTs to
  offer_master/save — duplicate offers on a single click. Removed here;
  ret_offer.js is the single source of truth for this page now.
-->

