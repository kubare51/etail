<div class="content-wrapper">
    <section class="content-header">
        <h1>
            New Print Template
            <small>Create Template</small>
        </h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Template Details</h3>
                    </div>
                    
                    <form role="form" action="<?php echo base_url('index.php/print-templates/save'); ?>" method="post" enctype="multipart/form-data">
                        <div class="box-body">

                            <?php if ($this->session->flashdata('template_error')): ?>
                                <div class="alert alert-danger">
                                    <i class="fa fa-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($this->session->flashdata('template_error')); ?>
                                </div>
                            <?php endif; ?>

                            <div class="form-group" style="background:#fcf8e3; border:1px solid #faebcc; border-radius:3px; padding:12px;">
                                <label for="template_file"><i class="fa fa-upload"></i> Start from an exported template <span style="font-weight:normal; color:#8a6d3b;">(optional)</span></label>
                                <input type="file" name="template_file" id="template_file" accept=".json,application/json">
                                <span class="help-block" style="font-size:11px; color:#8a6d3b; margin-bottom:0;">
                                    Upload a <code>.json</code> file downloaded from any template row. The design and computed variables
                                    are taken from the file; the details you fill in below are still used for the new record.
                                    Leave empty to start from a blank canvas.
                                </span>
                            </div>

                            <div class="form-group">
                                <label for="template_name">Template Name</label>
                                <input type="text" class="form-control" name="template_name" id="template_name" placeholder="E.g. Standard Billing Invoice" required>
                            </div>

                            <div class="form-group">
                                <label for="code">Code (Group Identifier)</label>
                                <input type="text" class="form-control" name="code" id="code" placeholder="E.g. global, est_001">
                                <span class="help-block" style="font-size:11px; color:#999;">Templates with the same code are grouped together. Used for Revert to Default.</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="template_code">Template Code (Unique)</label>
                                <input type="text" class="form-control" name="template_code" id="template_code" placeholder="E.g. BILL_STD_A4" required>
                            </div>

                            <div class="form-group">
                                <label>Category</label>
                                <select class="form-control" name="template_category">
                                    <?php foreach($categories as $id => $name): ?>
                                        <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Paper Size</label>
                                        <select class="form-control" name="paper_size" id="paper_size">
                                            <?php foreach($paper_sizes as $size): ?>
                                                <option value="<?php echo $size; ?>"><?php echo $size; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Orientation</label>
                                        <select class="form-control" name="page_orientation">
                                            <option value="portrait">Portrait</option>
                                            <option value="landscape">Landscape</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row" id="custom_dims_row" style="display:none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Custom Width (mm)</label>
                                        <input type="number" class="form-control" name="paper_width" step="0.1" min="10" max="2000" placeholder="E.g. 186.7">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Custom Height (mm)</label>
                                        <input type="number" class="form-control" name="paper_height" step="0.1" min="10" max="2000" placeholder="E.g. 365.3">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <span class="help-block" style="font-size:11px; color:#999; margin-top:-5px;">Exact physical page size in millimetres — the designer canvas and print output use these dimensions.</span>
                                </div>
                            </div>

                             <div class="form-group">
                                <label>Branch (Optional)</label>
                                <select class="form-control" name="id_branch">
                                    <option value="">All Branches</option>
                                    <!-- Populate if branches available -->
                                </select>
                            </div>

                        </div>

                        <div class="box-footer">
                            <a href="<?php echo base_url('print-templates'); ?>" class="btn btn-default">Cancel</a>
                            <button type="submit" class="btn btn-primary pull-right">Create & Open Designer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
(function () {
    var sel = document.getElementById('paper_size');
    var row = document.getElementById('custom_dims_row');
    if (!sel || !row) return;
    function toggleDims() {
        row.style.display = (sel.value === 'Custom') ? '' : 'none';
    }
    sel.addEventListener('change', toggleDims);
    toggleDims();
})();
</script>
