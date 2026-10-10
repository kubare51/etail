<!-- Content Wrapper. Contains page content -->
<?php
    // Edit mode detection
    $is_edit = isset($edit_data) && !empty($edit_data);
    $edit_id = $is_edit ? $edit_data['id'] : '';
?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            <?php echo $is_edit ? 'Edit' : 'Add'; ?> Cash Opening Balance
            <small>Opening Master</small>
        </h1>
        <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="<?php echo base_url('index.php/admin_opening_master/cash_opening/list'); ?>">Cash Opening Balance</a></li>
            <li class="active"><?php echo $is_edit ? 'Edit' : 'Add'; ?></li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><?php echo $is_edit ? 'Edit' : 'Add'; ?> Cash Opening Balance</h3>
                    </div>
                    <div class="box-body">
                        <form id="cash_opening_form">
                            <?php if ($is_edit) { ?>
                                <input type="hidden" name="cash_opening[edit_id]" id="cash_edit_id" value="<?php echo $edit_id; ?>">
                            <?php } ?>
                            <div class="col-sm-12">
                                <legend class="sub-title">Cash Opening Details</legend>

                                <!-- Branch -->
                                <div class="row">
                                    <label for="cash_branch_select" class="col-md-4 col-md-offset-1">Branch <span class="error">*</span></label>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <select class="form-control select2" name="cash_opening[id_branch]" id="cash_branch_select" style="width:100%;">
                                                <option value="">-- Select Branch --</option>
                                                <?php if (isset($branches) && !empty($branches)) {
                                                    foreach ($branches as $branch) { ?>
                                                        <option value="<?php echo $branch['id_branch']; ?>" <?php echo ($is_edit && $edit_data['id_branch'] == $branch['id_branch']) ? 'selected' : ''; ?>><?php echo $branch['branch_name']; ?></option>
                                                <?php } } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Opening Date -->
                                <div class="row">
                                    <label for="cash_opening_date" class="col-md-4 col-md-offset-1">Opening Date <span class="error">*</span></label>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <div class="input-group">
                                                <input class="form-control" readonly type="text" data-date-format="dd-mm-yyyy" value="<?php echo $is_edit ? $edit_data['formatted_date'] : date('d-m-Y'); ?>" name="cash_opening[opening_date]" id="cash_opening_date">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Amount -->
                                <div class="row">
                                    <label for="cash_opening_amount" class="col-md-4 col-md-offset-1">Amount (₹) <span class="error">*</span></label>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <div class="input-group">
                                                <input class="form-control" name="cash_opening[amount]" id="cash_opening_amount" placeholder="0.00" type="number" min="0" step="0.01" value="<?php echo $is_edit ? $edit_data['amount'] : ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Remarks -->
                                <div class="row">
                                    <label for="cash_opening_remarks" class="col-md-4 col-md-offset-1">Remarks</label>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <textarea rows="3" name="cash_opening[remarks]" id="cash_opening_remarks" class="form-control" placeholder="Enter Remarks..." maxlength="500"><?php echo $is_edit ? htmlspecialchars($edit_data['remarks']) : ''; ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <p class="help-block"></p>
                            </div><!-- /Col -->
                        </form>
                    </div><!-- /.box-body -->

                    <!-- Save / Cancel -->
                    <div class="row">
                        <div class="box box-default"><br/>
                            <div class="col-xs-offset-5">
                                <button type="button" id="cash_opening_submit" class="btn btn-primary">Save</button>
                                <a href="<?php echo base_url('index.php/admin_opening_master/cash_opening/list'); ?>" class="btn btn-default btn-cancel">Cancel</a>
                            </div> <br/>
                        </div>
                    </div>

                    <div class="overlay" style="display:none">
                        <i class="fa fa-refresh fa-spin"></i>
                    </div>
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section>
</div>
