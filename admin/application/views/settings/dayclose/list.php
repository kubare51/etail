  <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
          <h1>
            Auto Day Close
            <small>Configure the automatic day close time</small>
          </h1>
          <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="#">Settings</a></li>
            <li class="active">Auto Day Close</li>
          </ol>
        </section>

        <!-- Main content -->
        <section class="content">
          <div class="row">
            <div class="col-xs-12">

              <div class="box box-primary">
                <div class="box-header with-border">
                  <h3 class="box-title">Auto Day Close Settings</h3>
                </div>
                <div class="box-body">
                  <div id="chit_alert"></div>

                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="dc_default_time">Default close time (all branches)</label>
                        <div class="input-group">
                          <select id="dc_default_time" class="form-control"></select>
                          <span class="input-group-btn">
                            <button class="btn btn-primary" id="save_default_time" type="button">Save</button>
                          </span>
                        </div>
                        <p class="help-block">
                          <i class="fa fa-info-circle"></i> Applies from the next business day onwards.
                          To change today's time for a branch, use "Extend today" on the branch row below.
                        </p>
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-12">
                      <a data-toggle="collapse" href="#dc_advanced"><i class="fa fa-caret-right"></i> Advanced</a>
                      <div class="collapse" id="dc_advanced" style="margin-top:10px;">
                        <div class="col-md-4">
                          <div class="form-group">
                            <label for="dc_unlock_fallback">Unlock fallback time</label>
                            <div class="input-group">
                              <select id="dc_unlock_fallback" class="form-control"></select>
                              <span class="input-group-btn">
                                <button class="btn btn-default" id="save_unlock_fallback" type="button">Save</button>
                              </span>
                            </div>
                            <p class="help-block">If the morning reset does not run by this time, the ticker unlocks the branch itself instead.</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div><!-- /.box-body -->
              </div><!-- /.box -->

              <div class="box box-primary">
                <div class="box-header with-border">
                  <h3 class="box-title">Branches</h3>
                  <span id="total_branches" class="badge bg-green"></span>
                  <button class="btn btn-default pull-right" id="refresh_schedule" type="button"><i class="fa fa-refresh"></i> Refresh</button>
                  <button class="btn btn-warning pull-right" id="extend_all_branches" type="button" style="margin-right:10px;"><i class="fa fa-clock-o"></i> Extend All Branches Today</button>
                  <?php if ($this->session->userdata('branch_settings') == 1) { ?>
                  <div class="pull-right" style="margin-right:10px;width:200px;">
                    <select id="branch_select" class="form-control branch_filter"></select>
                  </div>
                  <?php } ?>
                </div>
                <div class="box-body">
                  <div class="table-responsive">
                    <table id="dayclose_list" class="table table-bordered table-striped text-center">
                      <thead>
                        <tr>
                          <th>Branch</th>
                          <th>Business Date</th>
                          <th>Status</th>
                          <th>Closes Today (Effective)</th>
                          <th>Branch Default</th>
                          <th>Today Only</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>
                  </div>
                  <div class="overlay" style="display:none">
                    <i class="fa fa-refresh fa-spin"></i>
                  </div>
                </div><!-- /.box-body -->
              </div><!-- /.box -->

            </div><!-- /.col -->
          </div><!-- /.row -->
        </section><!-- /.content -->
      </div><!-- /.content-wrapper -->

<!-- Extend / Change today's close time -->
<div class="modal fade" id="confirm-override" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Extend today's day close - <span id="ov_branch_name"></span></h4>
      </div>
      <div class="modal-body">
        <div class="row"><div class="col-md-offset-1 col-md-10" id="error-msg-ov"></div></div>
        <input type="hidden" id="ov_id_branch" value="">
        <div class="form-group">
          <label class="col-md-4 col-md-offset-1">Business date</label>
          <div class="col-md-6"><p class="form-control-static" id="ov_entry_date"></p></div>
        </div>
        <div class="form-group">
          <label class="col-md-4 col-md-offset-1">Currently closes at</label>
          <div class="col-md-6"><p class="form-control-static" id="ov_current_effective"></p></div>
        </div>
        <div class="form-group">
          <label class="col-md-4 col-md-offset-1">New close time <span class="error">*</span></label>
          <div class="col-md-6">
            <select id="ov_close_time" class="form-control"></select>
            <p class="help-block">Only times after now are listed.</p>
          </div>
        </div>
        <div class="form-group">
          <label class="col-md-4 col-md-offset-1">Reason <span class="error">*</span></label>
          <div class="col-md-6">
            <input type="text" class="form-control" id="ov_remarks" placeholder="e.g. Client requested extra audit time">
          </div>
        </div>
        <div class="row">
          <div class="col-md-10 col-md-offset-1">
            <div class="alert alert-info" style="margin-top:10px;">
              <i class="fa fa-info-circle"></i> Applies to <span id="ov_entry_date2"></span> only. Tomorrow returns to the standing default automatically - no reset needed.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" id="ov_remove" style="display:none;">Remove extension</button>
        <a href="#" id="save_override" class="btn btn-success">Save</a>
        <button type="button" class="btn btn-warning" data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>
<!-- / modal -->

<!-- Extend all branches today -->
<div class="modal fade" id="confirm-override-all" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Extend today's day close - All Branches</h4>
      </div>
      <div class="modal-body">
        <div class="row"><div class="col-md-offset-1 col-md-10" id="error-msg-ova"></div></div>
        <div class="row">
          <div class="col-md-10 col-md-offset-1">
            <div class="alert alert-info">
              <i class="fa fa-info-circle"></i> Applies to every branch that is still <strong>open</strong> today. Branches that have already closed for the day are left untouched - their close time cannot be changed once closed.
            </div>
          </div>
        </div>
        <div class="form-group">
          <label class="col-md-4 col-md-offset-1">New close time <span class="error">*</span></label>
          <div class="col-md-6">
            <select id="ova_close_time" class="form-control"></select>
            <p class="help-block">Only times after now are listed.</p>
          </div>
        </div>
        <div class="form-group">
          <label class="col-md-4 col-md-offset-1">Reason <span class="error">*</span></label>
          <div class="col-md-6">
            <input type="text" class="form-control" id="ova_remarks" placeholder="e.g. Client requested extra audit time">
          </div>
        </div>
        <div class="row">
          <div class="col-md-10 col-md-offset-1">
            <div class="alert alert-info" style="margin-top:10px;">
              <i class="fa fa-info-circle"></i> Applies to today only. Tomorrow every branch returns to its own standing default automatically - no reset needed.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="#" id="save_override_all" class="btn btn-success">Save</a>
        <button type="button" class="btn btn-warning" data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>
<!-- / modal -->

<!-- AM time confirmation - shared by every close-time save on this page -->
<div class="modal fade" id="confirm-am-warning" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-yellow" style="border-bottom:none;">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-exclamation-triangle"></i> Confirm AM Time</h4>
      </div>
      <div class="modal-body text-center" style="padding:25px;">
        <i class="fa fa-clock-o" style="font-size:54px;color:#f39c12;"></i>
        <p style="font-size:16px;margin-top:15px;margin-bottom:5px;">
          You have selected <strong id="am_warning_time" style="color:#f39c12;"></strong>
        </p>
        <p style="margin-bottom:15px;">This is an <strong>AM</strong> time. Auto Day Close normally runs in the evening -
          setting an AM time will close the branch's business day within minutes of it opening.</p>
        <p><strong>Are you sure this is what you intended?</strong></p>
      </div>
      <div class="modal-footer" style="text-align:center;border-top:none;">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="am_warning_confirm"><i class="fa fa-check"></i> Yes, use this AM time</button>
      </div>
    </div>
  </div>
</div>
<!-- / modal -->
