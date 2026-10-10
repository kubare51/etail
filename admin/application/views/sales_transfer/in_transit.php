  <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
          <h1>
            In-Transit Dashboard
            <small>Sales Transfer & Return items currently in transit</small>
          </h1>
        </section>
        <!-- Main content -->
        <section class="content">
          <!-- KPI Summary Cards -->
          <div class="row" id="transit_kpi_row">
            <div class="col-md-3 col-sm-6 col-xs-12">
              <div class="info-box bg-aqua">
                <span class="info-box-icon"><i class="fa fa-truck"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">In-Transit Bills</span>
                  <span class="info-box-number" id="kpi_total_bills">0</span>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6 col-xs-12">
              <div class="info-box bg-green">
                <span class="info-box-icon"><i class="fa fa-tags"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Total Items</span>
                  <span class="info-box-number" id="kpi_total_items">0</span>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6 col-xs-12">
              <div class="info-box bg-yellow">
                <span class="info-box-icon"><i class="fa fa-inr"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Total Value</span>
                  <span class="info-box-number" id="kpi_total_value">₹0</span>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6 col-xs-12">
              <div class="info-box bg-red">
                <span class="info-box-icon"><i class="fa fa-clock-o"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Aging > 3 Days</span>
                  <span class="info-box-number" id="kpi_aging_count">0</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Filter Row -->
          <div class="row">
            <div class="col-xs-12">
              <div class="box box-primary">
                <div class="box-header with-border">
                  <h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
                  <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                  </div>
                </div>
                <div class="box-body">
                  <div class="row">
                    <div class="col-md-3">
                      <label>From Branch</label>
                      <select class="form-control select2" id="filter_from_branch">
                        <option value="">All</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label>To Branch</label>
                      <select class="form-control select2" id="filter_to_branch">
                        <option value="">All</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label>Transfer Type</label>
                      <select class="form-control" id="filter_trans_type">
                        <option value="">All</option>
                        <option value="1">Tagged</option>
                        <option value="2">Non-Tagged</option>
                        <option value="3">Old Gold</option>
                        <option value="4">Sales Return</option>
                        <option value="5">Partly Sold</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label>Direction</label>
                      <select class="form-control" id="filter_direction">
                        <option value="">All</option>
                        <option value="13">Sales Transfer (Outward)</option>
                        <option value="14">Sales Return Transfer (Inward)</option>
                      </select>
                    </div>
                  </div>
                  <div class="row" style="margin-top:10px;">
                    <div class="col-md-12 text-right">
                      <button type="button" class="btn btn-primary" id="btn_filter_transit"><i class="fa fa-search"></i> Filter</button>
                      <button type="button" class="btn btn-default" id="btn_reset_filter"><i class="fa fa-refresh"></i> Reset</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Data Table -->
          <div class="row">
            <div class="col-xs-12">
               <div class="box box-primary">
                <div class="box-header with-border">
                  <h3 class="box-title"><i class="fa fa-list"></i> In-Transit Items</h3>
                  <div class="pull-right">
                    <button type="button" class="btn btn-success btn-sm" id="btn_refresh_transit"><i class="fa fa-refresh"></i> Refresh</button>
                    <a class="btn btn-primary btn-sm" href="<?php echo base_url('index.php/admin_ret_sales_transfer/sales_transfer/list');?>"><i class="fa fa-arrow-left"></i> Back to List</a>
                  </div>
                </div>
                <div class="box-body">
                  <div class="table-responsive">
                    <table id="transit_list" class="table table-bordered table-striped text-center" style="width:100%">
                      <thead>
                        <tr>
                          <th width="8%">Bill No</th>
                          <th width="8%">Bill Date</th>
                          <th width="10%">From Branch</th>
                          <th width="10%">To Branch</th>
                          <th width="8%">Direction</th>
                          <th width="8%">Type</th>
                          <th width="6%">Items</th>
                          <th width="10%">Amount (₹)</th>
                          <th width="8%">Days In Transit</th>
                          <th width="10%">Status</th>
                        </tr>
                      </thead>
                    </table>
                  </div>
                </div>
                <div class="overlay" id="transit_overlay" style="display:none">
                  <i class="fa fa-refresh fa-spin"></i>
                </div>
              </div>
            </div>
          </div>

        </section><!-- /.content -->
      </div><!-- /.content-wrapper -->

