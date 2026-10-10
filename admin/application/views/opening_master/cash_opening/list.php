<div class="content-wrapper">
    <section class="content-header">
        <h1>
            Cash Opening Balance
            <small>Manage Cash Opening</small>
        </h1>
        <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="#">Opening Master</a></li>
            <li class="active">Cash Opening Balance</li>
        </ol>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Cash Opening Balance List</h3>
                        <div class="pull-right">
                            <a class="btn btn-success pull-right" id="add_cash_opening" href="<?php echo base_url('index.php/admin_opening_master/cash_opening/add'); ?>"><i class="fa fa-plus-circle"></i> Add</a>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-xs-12">
                        <?php
                        if ($this->session->flashdata('chit_alert')) {
                            $message = $this->session->flashdata('chit_alert');
                        ?>
                            <div class="alert alert-<?php echo $message['class']; ?> alert-dismissable">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                <h4><i class="icon fa fa-check"></i> <?php echo $message['title']; ?>!</h4>
                                <?php echo $message['message']; ?>
                            </div>
                        <?php } ?>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="cash_opening_list" class="table table-bordered table-striped text-center">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="10%">Ref No</th>
                                        <th width="25%">Branch</th>
                                        <th width="15%">Opening Date</th>
                                        <th width="20%">Amount (₹)</th>
                                        <th width="17%">Remarks</th>
                                        <th width="8%">Action</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div><!-- /.box-body -->
                    <div class="overlay" style="display:none">
                        <i class="fa fa-refresh fa-spin"></i>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
    </section>
</div><!-- /.content-wrapper -->
