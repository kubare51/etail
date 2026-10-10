  <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
          <h1>
            Auto Day Close - Run Log
            <small>Every tick of the auto day close ticker</small>
          </h1>
          <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="#">Settings</a></li>
            <li><a href="<?php echo base_url('index.php/settings/dayclose/list'); ?>">Auto Day Close</a></li>
            <li class="active">Run Log</li>
          </ol>
        </section>

        <!-- Main content -->
        <section class="content">
          <div class="row">
            <div class="col-xs-12">

              <div class="box box-solid" id="dc_summary_box">
                <div class="box-body">
                  <div class="row text-center">
                    <div class="col-md-3">
                      <strong>Last cron tick</strong><br>
                      <span id="dc_last_run">-</span>
                    </div>
                    <div class="col-md-3">
                      <strong>Closed today</strong><br>
                      <span id="dc_closed_today">-</span>
                    </div>
                    <div class="col-md-3">
                      <strong>Fallback repairs (30 days)</strong><br>
                      <span id="dc_fallback_30d">-</span>
                    </div>
                    <div class="col-md-3">
                      <strong>Failures (30 days)</strong><br>
                      <span id="dc_failed_30d">-</span>
                    </div>
                  </div>
                  <div id="dc_stale_warning" class="alert alert-danger" style="display:none;margin-top:10px;margin-bottom:0;">
                    <i class="fa fa-warning"></i> Cron may not be running - last tick was <span id="dc_stale_minutes"></span> ago.
                  </div>
                </div>
              </div>

              <div class="box box-primary">
                <div class="box-header with-border">
                  <h3 class="box-title">Run Log</h3>
                </div>
                <div class="box-body">
                  <div class="row">
                    <?php if ($this->session->userdata('branch_settings') == 1) { ?>
                    <div class="col-md-3">
                      <label>Branch</label>
                      <select id="log_branch_select" class="form-control branch_filter"></select>
                    </div>
                    <?php } ?>
                    <div class="col-md-3">
                      <label>From date</label>
                      <input type="text" class="form-control" id="log_from_date">
                    </div>
                    <div class="col-md-3">
                      <label>To date</label>
                      <input type="text" class="form-control" id="log_to_date">
                    </div>
                    <div class="col-md-3">
                      <label>&nbsp;</label><br>
                      <button class="btn btn-primary" id="log_filter" type="button"><i class="fa fa-filter"></i> Filter</button>
                    </div>
                  </div>
                  <div class="table-responsive" style="margin-top:15px;">
                    <table id="dayclose_log_list" class="table table-bordered table-striped text-center">
                      <thead>
                        <tr>
                          <th>Run At</th>
                          <th>Branch</th>
                          <th>Business Date</th>
                          <th>Effective Time</th>
                          <th>Source</th>
                          <th>Action</th>
                          <th>Message</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div><!-- /.box-body -->
              </div><!-- /.box -->

            </div><!-- /.col -->
          </div><!-- /.row -->
        </section><!-- /.content -->
      </div><!-- /.content-wrapper -->
