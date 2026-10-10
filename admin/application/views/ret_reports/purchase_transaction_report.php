<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <style>
        @media print {

            html,
            body {
                height: auto;
                width: 190vh;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden;
            }
        }
    </style>
    <section class="content-header">
        <h1>
            Reports
            <small>Purchase Transaction</small>
        </h1>
        <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="#">Retail Reports</a></li>
            <li class="active">Purchase Transaction Report</li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <div class="row">
                            <div class="col-md-2">
                                <?php if ($this->session->userdata('branch_settings') == 1 && $this->session->userdata('id_branch') == 0) { ?>
                                    <label>Select Branch</label>
                                    <div class="form-group">
                                        <select id="branch_select" class="form-control branch_filter" style="width:100%;" multiple></select>
                                    </div>
                                <?php } else { ?>
                                    <input type="hidden" id="branch_filter" value="<?php echo $this->session->userdata('id_branch') ?>">
                                    <input type="hidden" id="branch_name" value="<?php echo $this->session->userdata('branch_name') ?>">
                                <?php } ?>
                            </div>

                            <div class="col-md-2">
                                <label>Select Type</label>
                                <select id="st_type_details" class="form-control" style="width:100%;">
                                    <option value="1">Inward</option>
                                    <option value="2" selected>Outward</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label>Date Group By</label>
                                <select id="st_date_group_details" class="form-control" style="width:100%;">
                                    <option value="1" selected>Created Date</option>
                                    <option value="2">Download Date</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label>Report Type</label>
                                <select id="report_type" class="form-control" style="width:100%;">
                                    <option value="1" selected>Purchase</option>
                                    <option value="2">Purchase Return</option>
                                    <option value="3">Sales Return</option>
                                    <option value="4">Old metal</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label>Select Metal</label>
                                <select id="metal" class="form-control" style="width:100%;" multiple></select>
                            </div>

                            <div class="col-md-2">
                                <label>State Filter</label>
                                <select id="group_state" class="form-control group_state" style="width:100%;"></select>
                            </div>
                        </div>
                        <div class="row" style="margin-top:10px;">
                            <div class="col-md-2">
                                <label>Status</label>
                                <select id="status_filter" class="form-control" style="width:100%;">
                                    <option value="0" selected>All</option>
                                    <option value="2">In-Transit</option>
                                    <option value="4">Received</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label></label>
                                <div class="form-group">
                                    <button class="btn btn-default btn_date_range" id="rpt_date_picker">
                                        <i class="fa fa-calendar"></i> Date range picker
                                        <i class="fa fa-caret-down"></i>
                                    </button>
                                    <span style="display:none;" id="rpt_from_date"></span>
                                    <span style="display:none;" id="rpt_to_date"></span>
                                </div>
                            </div>

                            <div class="col-md-1">
                                <label></label>
                                <div class="form-group">
                                    <button type="button" id="st_return_report_search" class="btn btn-info">Search</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table id="purchase_trans_report" class="table table-bordered table-striped text-center">
                                        <thead>
                                            <tr>
                                                <th>Bill No</th>
                                                <th>Date</th>
                                                <th>From Branch</th>
                                                <th>To Branch</th>
                                                <th>Category</th>
                                                <th>Pieces</th>
                                                <th>Gross Wt</th>
                                                <th>Net Wt</th>
                                                <th>Dia Wt</th>
                                                <th>Status</th>
                                                <th>Downloaded Date</th>
                                                <th>Downloaded Pieces</th>
                                                <th>Downloaded Wt</th>
                                                <th>Downloaded By</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                        <tfoot>
                                            <tr>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div><!-- /.box-body -->
                    <div class="overlay" style="display:none">
                        <i class="fa fa-refresh fa-spin"></i>
                    </div>
                </div>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
