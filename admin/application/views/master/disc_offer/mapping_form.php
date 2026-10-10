<?php
  $is_edit = (isset($mapping['id_mapping']) && $mapping['id_mapping'] > 0);
  $page_title = $is_edit ? 'Edit Mapping' : 'Add Mapping';

  // Pre-selected CSV values for edit mode
  $sel_metals     = !empty($mapping['id_metal_type']) ? explode(',', $mapping['id_metal_type']) : [];
  $sel_categories = !empty($mapping['id_category']) ? explode(',', $mapping['id_category']) : [];
  $sel_products   = !empty($mapping['id_product']) ? explode(',', $mapping['id_product']) : [];
  $sel_designs    = !empty($mapping['id_design']) ? explode(',', $mapping['id_design']) : [];
  $sel_sub_designs = !empty($mapping['id_sub_design']) ? explode(',', $mapping['id_sub_design']) : [];
  $sel_branches   = !empty($mapping['id_branch']) ? explode(',', $mapping['id_branch']) : [];
  $sel_slabs      = !empty($mapping['id_age_slab']) ? explode(',', $mapping['id_age_slab']) : [];
  $has_slab       = !empty($mapping['id_age_slab']);
?>
  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
      <h1>
        Offer Mapping
        <small><?php echo $page_title; ?></small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Catalog Master</a></li>
        <li><a href="<?php echo base_url('index.php/admin_ret_offer/offer_mapping/list'); ?>">Offer Mapping</a></li>
        <li class="active"><?php echo $page_title; ?></li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">

      <!-- Alert -->
      <?php if($this->session->flashdata('chit_alert')) { $message = $this->session->flashdata('chit_alert'); ?>
        <div class="alert alert-<?php echo $message['class']; ?> alert-dismissable">
          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
          <h4><i class="icon fa fa-check"></i> <?php echo $message['title']; ?>!</h4>
          <?php echo $message['message']; ?>
        </div>
      <?php } ?>

      <!-- Form box -->
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-link"></i> <?php echo $page_title; ?></h3>
          <div class="box-tools pull-right">
            <button class="btn btn-box-tool" data-widget="collapse" data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
          </div>
        </div>
        <div class="box-body">

          <form id="mapping_form" method="post" action="javascript:void(0);">

            <!-- Hidden fields -->
            <?php if ($is_edit): ?>
              <input type="hidden" name="id_mapping" value="<?php echo $mapping['id_mapping']; ?>">
            <?php endif; ?>
            <input type="hidden" name="id_metal_type" id="id_metal_type" value="<?php echo htmlspecialchars($mapping['id_metal_type']); ?>">
            <input type="hidden" name="id_category" id="id_category" value="<?php echo htmlspecialchars($mapping['id_category']); ?>">
            <input type="hidden" name="id_product" id="id_product" value="<?php echo htmlspecialchars($mapping['id_product']); ?>">
            <input type="hidden" name="id_design" id="id_design" value="<?php echo htmlspecialchars($mapping['id_design']); ?>">
            <input type="hidden" name="id_sub_design" id="id_sub_design" value="<?php echo htmlspecialchars($mapping['id_sub_design']); ?>">
            <input type="hidden" name="id_branch" id="id_branch" value="<?php echo htmlspecialchars($mapping['id_branch']); ?>">
            <input type="hidden" name="id_age_slab" id="id_age_slab" value="<?php echo htmlspecialchars($mapping['id_age_slab']); ?>">

            <!-- ===== SECTION 1: Offer + Branch + Metal ===== -->
            <div class="mapping-section">
              <div class="mapping-section-header">
                <i class="fa fa-gift" style="color:#dd4b39;"></i> Offer Selection
              </div>
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label><i class="fa fa-gift text-muted" style="margin-right:4px;"></i> Offer <span class="text-red">*</span></label>
                    <select name="id_offer" id="id_offer" style="width:100%;">
                      <option value="">-- Select Offer --</option>
                      <?php foreach ($offers as $offer): ?>
                        <option value="<?php echo $offer['id_offer']; ?>" <?php echo ($mapping['id_offer'] == $offer['id_offer']) ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($offer['offer_name']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label><i class="fa fa-building text-muted" style="margin-right:4px;"></i> Branches</label>
                    <select id="id_branch_select" multiple="multiple" style="width:100%;">
                      <option value="all">All</option>
                      <?php if (!empty($branches)): ?>
                        <?php foreach ($branches as $branch): ?>
                          <option value="<?php echo $branch['id_branch']; ?>" <?php echo in_array($branch['id_branch'], $sel_branches) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['name']); ?>
                          </option>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label><i class="fa fa-diamond text-muted" style="margin-right:4px;"></i> Metal Type</label>
                    <select id="id_metal_type_select" multiple="multiple" style="width:100%;">
                      <option value="all">All</option>
                      <?php if (!empty($metals)): ?>
                        <?php foreach ($metals as $metal): ?>
                          <?php if (!empty($metal['metal_status']) && $metal['metal_status'] == 1): ?>
                          <option value="<?php echo $metal['id_metal']; ?>" <?php echo in_array($metal['id_metal'], $sel_metals) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($metal['metal']); ?>
                          </option>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- ===== SECTION 2: Product Scope ===== -->
            <div class="mapping-section">
              <div class="mapping-section-header">
                <i class="fa fa-filter" style="color:#00a65a;"></i> Product Scope
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label><i class="fa fa-th-large text-muted" style="margin-right:4px;"></i> Categories</label>
                    <select id="id_category_select" multiple="multiple" style="width:100%;">
                      <option value="all">All</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label><i class="fa fa-cube text-muted" style="margin-right:4px;"></i> Products</label>
                    <select id="id_product_select" multiple="multiple" style="width:100%;">
                      <option value="all">All</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label><i class="fa fa-paint-brush text-muted" style="margin-right:4px;"></i> Designs</label>
                    <select id="id_design_select" multiple="multiple" style="width:100%;">
                      <option value="all">All</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label><i class="fa fa-object-group text-muted" style="margin-right:4px;"></i> Sub-Designs</label>
                    <select id="id_sub_design_select" multiple="multiple" style="width:100%;">
                      <option value="all">All</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- ===== SECTION 3: Stock Age Filter ===== -->
            <div class="mapping-section">
              <div class="mapping-section-header">
                <i class="fa fa-clock-o" style="color:#605ca8;"></i> Stock Age Filter
              </div>
              <div class="row">
                <div class="col-md-5">
                  <p class="help-block" style="margin:0 0 8px; font-size:12px; color:#777;">
                    <i class="fa fa-info-circle text-info"></i> Filter products by how long they've been in stock (based on tag date / lot date).
                  </p>
                  <div class="age-filter-toggle">
                    <label class="age-radio-label">
                      <input type="radio" name="age_filter_type" value="all" <?php echo !$has_slab ? 'checked' : ''; ?>>
                      <span class="age-radio-text">
                        <i class="fa fa-globe" style="margin-right:3px;"></i> All Products
                        <small class="text-muted">(no age filter)</small>
                      </span>
                    </label>
                    <label class="age-radio-label" style="margin-left:20px;">
                      <input type="radio" name="age_filter_type" value="slab" <?php echo $has_slab ? 'checked' : ''; ?>>
                      <span class="age-radio-text">
                        <i class="fa fa-sliders" style="margin-right:3px;"></i> Select Age Slab(s)
                      </span>
                    </label>
                  </div>
                </div>
                <div class="col-md-7">
                  <div id="slab_selection_area" style="display:<?php echo $has_slab ? 'block' : 'none'; ?>; padding-top:20px;">
                    <div class="slab-select-row">
                      <div class="slab-select-col">
                        <select id="id_age_slab_select" multiple="multiple" style="width:100%;">
                          <?php if (!empty($slabs)): ?>
                            <?php foreach ($slabs as $slab): ?>
                              <?php
                                $label = htmlspecialchars($slab['slab_name']) . ' (' . $slab['min_days'];
                                $label .= ($slab['max_days'] !== null) ? '-' . $slab['max_days'] : '+';
                                $label .= ' days)';
                              ?>
                              <option value="<?php echo $slab['id_slab']; ?>" <?php echo in_array($slab['id_slab'], $sel_slabs) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                              </option>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </select>
                      </div>
                      <div class="slab-btn-col">
                        <button type="button" id="btn_quick_add_slab" class="btn btn-success btn-flat btn-sm" style="padding:6px 14px;">
                          <i class="fa fa-plus"></i> Quick Add Slab
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ===== Action Buttons ===== -->
            <div class="mapping-actions">
              <button type="submit" id="btn_save_mapping" class="btn btn-primary btn-flat mapping-btn-save">
                <i class="fa fa-save"></i> Save Mapping
              </button>
              <a href="<?php echo base_url('index.php/admin_ret_offer/offer_mapping/list'); ?>" class="btn btn-default btn-flat mapping-btn-cancel">
                <i class="fa fa-arrow-left"></i> Cancel
              </a>
            </div>

          </form>
        </div>
      </div>

    </section>
  </div>

  <!-- Quick Add Slab Modal -->
  <div class="modal fade" id="modal_add_slab" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" role="document" style="max-width:440px;">
      <div class="modal-content" style="border-radius:6px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.18);">
        <div class="modal-header" style="background:linear-gradient(135deg, #605ca8 0%, #3c8dbc 100%); border-bottom:none; padding:16px 20px;">
          <button type="button" class="close" data-dismiss="modal" style="color:#fff; text-shadow:none; opacity:0.8;">&times;</button>
          <h4 class="modal-title" style="color:#fff; font-weight:600; font-size:16px;">
            <i class="fa fa-plus-circle" style="margin-right:6px;"></i> Create Age Slab
          </h4>
        </div>
        <div class="modal-body" style="padding:22px 24px 14px;">
          <div class="form-group" style="margin-bottom:18px;">
            <label style="font-weight:600; font-size:12.5px; color:#444; margin-bottom:6px;">
              <i class="fa fa-bookmark-o text-muted" style="margin-right:3px;"></i> Slab Name <span class="text-red">*</span>
            </label>
            <input type="text" id="slab_name" class="form-control" placeholder="e.g., Fresh Stock, Aged, Dead Stock" autofocus
              style="border-radius:4px; height:38px; font-size:13px; border:1px solid #d2d6de; transition:border-color 0.2s;">
          </div>
          <div class="row">
            <div class="col-xs-6">
              <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:600; font-size:12.5px; color:#444; margin-bottom:6px;">
                  <i class="fa fa-arrow-right text-muted" style="margin-right:3px;"></i> Min Days <span class="text-red">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-addon" style="background:#f7f7f7; border-radius:4px 0 0 4px; font-size:12px; color:#888; min-width:40px;">
                    <i class="fa fa-calendar-o"></i>
                  </span>
                  <input type="number" id="slab_min_days" class="form-control" min="0" value="0" placeholder="0"
                    style="border-radius:0 4px 4px 0; height:38px; font-size:14px; font-weight:600; text-align:center;">
                </div>
              </div>
            </div>
            <div class="col-xs-6">
              <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:600; font-size:12.5px; color:#444; margin-bottom:6px;">
                  <i class="fa fa-arrow-left text-muted" style="margin-right:3px;"></i> Max Days
                  <small class="text-muted" style="font-weight:400;">(blank = ∞)</small>
                </label>
                <div class="input-group">
                  <span class="input-group-addon" style="background:#f7f7f7; border-radius:4px 0 0 4px; font-size:12px; color:#888; min-width:40px;">
                    <i class="fa fa-calendar"></i>
                  </span>
                  <input type="number" id="slab_max_days" class="form-control" min="0" placeholder="∞"
                    style="border-radius:0 4px 4px 0; height:38px; font-size:14px; font-weight:600; text-align:center;">
                </div>
              </div>
            </div>
          </div>
          <div style="background:#f8f9fa; border-radius:4px; padding:10px 12px; margin-top:2px; border-left:3px solid #3c8dbc;">
            <p style="font-size:11.5px; margin:0; color:#666; line-height:1.5;">
              <i class="fa fa-info-circle" style="color:#3c8dbc; margin-right:4px;"></i>
              Leave Max Days blank for open-ended ranges (e.g., <strong>"365+ days"</strong>).
              The slab will match products aged from Min to Max days.
            </p>
          </div>
        </div>
        <div class="modal-footer" style="padding:14px 24px; background:#fafafa; border-top:1px solid #eee;">
          <button type="button" class="btn btn-default btn-flat" data-dismiss="modal" style="padding:7px 20px; font-weight:500; border-radius:4px;">Cancel</button>
          <button type="button" id="btn_save_slab" class="btn btn-success btn-flat" style="padding:7px 22px; font-weight:600; border-radius:4px; min-width:110px;">
            <i class="fa fa-check"></i> Save Slab
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- CSS -->
  <link rel="stylesheet" href="<?php echo base_url('assets/css/ret_offer.css'); ?>">

  <script>
  window.addEventListener('load', function() {
    var base_url = '<?php echo base_url(); ?>';

    // Pre-selected values for edit mode
    var preselected_categories  = <?php echo json_encode($sel_categories); ?>;
    var preselected_products    = <?php echo json_encode($sel_products); ?>;
    var preselected_designs     = <?php echo json_encode($sel_designs); ?>;
    var preselected_sub_designs = <?php echo json_encode($sel_sub_designs); ?>;

    // ==========================================
    // INIT SELECT2
    // ==========================================
    $('#id_offer').select2({ placeholder: '-- Select Offer --', allowClear: true });
    $('#id_metal_type_select').select2({ placeholder: 'All Metal Types', allowClear: true });
    $('#id_category_select').select2({ placeholder: 'All Categories', allowClear: true });
    $('#id_product_select').select2({ placeholder: 'All Products', allowClear: true });
    $('#id_design_select').select2({ placeholder: 'All Designs', allowClear: true });
    $('#id_sub_design_select').select2({ placeholder: 'All Sub-Designs', allowClear: true });
    $('#id_branch_select').select2({ placeholder: 'All Branches', allowClear: true });
    $('#id_age_slab_select').select2({ placeholder: 'Select age slab(s)...', allowClear: true });

    // ==========================================
    // "ALL" OPTION LOGIC
    // When "All" is selected, clear specific items.
    // When specific items are selected, remove "All".
    // ==========================================
    function handleAllOption(selectId, hiddenId) {
      $(document).on('change', selectId, function() {
        var selected = $(this).val() || [];
        if (selected.indexOf('all') > -1 && selected.length > 1) {
          // If "All" was just added alongside specifics, keep only "All"
          if (selected[selected.length - 1] === 'all') {
            $(this).val(['all']).trigger('change.select2');
            $(hiddenId).val('');
          } else {
            // Specific item added after "All" — remove "All"
            selected = selected.filter(function(v) { return v !== 'all'; });
            $(this).val(selected).trigger('change.select2');
            $(hiddenId).val(selected.join(','));
          }
        } else if (selected.indexOf('all') > -1) {
          $(hiddenId).val('');
        } else {
          $(hiddenId).val(selected.length > 0 ? selected.join(',') : '');
        }
      });
    }

    handleAllOption('#id_metal_type_select', '#id_metal_type');
    handleAllOption('#id_category_select', '#id_category');
    handleAllOption('#id_product_select', '#id_product');
    handleAllOption('#id_design_select', '#id_design');
    handleAllOption('#id_sub_design_select', '#id_sub_design');
    handleAllOption('#id_branch_select', '#id_branch');

    // ==========================================
    // AGE FILTER — Radio toggle
    // ==========================================
    $('input[name="age_filter_type"]').on('change', function() {
      if ($(this).val() === 'slab') {
        $('#slab_selection_area').slideDown(200);
      } else {
        $('#slab_selection_area').slideUp(200);
        $('#id_age_slab_select').val(null).trigger('change');
        $('#id_age_slab').val('');
      }
    });

    // Sync slab select → hidden field
    $(document).on('change', '#id_age_slab_select', function() {
      var selected = $(this).val();
      $('#id_age_slab').val(selected ? selected.join(',') : '');
    });

    // ==========================================
    // CASCADING: Metal Type → Categories
    // ==========================================
    $(document).on('change', '#id_metal_type_select', function() {
      var selected = $(this).val() || [];
      var metals = selected.filter(function(v) { return v !== 'all'; });
      if (metals.length > 0) {
        loadCategories(metals.join(','), []);
      } else {
        // "All" or nothing — clear cascaded
        $('#id_category_select').find('option:not([value="all"])').remove();
        $('#id_category_select').val(null).trigger('change.select2');
        $('#id_category').val('');
      }
      // Reset downstream
      $('#id_product_select').find('option:not([value="all"])').remove();
      $('#id_product_select').val(null).trigger('change.select2');
      $('#id_product').val('');
      $('#id_design_select').find('option:not([value="all"])').remove();
      $('#id_design_select').val(null).trigger('change.select2');
      $('#id_design').val('');
      $('#id_sub_design_select').find('option:not([value="all"])').remove();
      $('#id_sub_design_select').val(null).trigger('change.select2');
      $('#id_sub_design').val('');
    });

    function loadCategories(metalCsv, preselect) {
      $.ajax({
        type: 'POST',
        url: base_url + 'index.php/admin_ret_offer/get_categories_by_metal',
        data: { id_metal: metalCsv },
        dataType: 'json',
        success: function(resp) {
          var $sel = $('#id_category_select');
          $sel.find('option:not([value="all"])').remove();
          if (resp.status && resp.data.length > 0) {
            $.each(resp.data, function(i, item) {
              $sel.append(new Option(item.name, item.id_ret_category, false, false));
            });
          }
          if (preselect.length > 0) {
            $sel.val(preselect).trigger('change');
          } else {
            $sel.val(null).trigger('change.select2');
          }
        }
      });
    }

    // ==========================================
    // CASCADING: Categories → Products
    // ==========================================
    $(document).on('change', '#id_category_select', function() {
      var selected = $(this).val() || [];
      var cats = selected.filter(function(v) { return v !== 'all'; });
      if (cats.length > 0) {
        loadProducts(cats.join(','), []);
      } else {
        $('#id_product_select').find('option:not([value="all"])').remove();
        $('#id_product_select').val(null).trigger('change.select2');
        $('#id_product').val('');
      }
      // Reset downstream
      $('#id_design_select').find('option:not([value="all"])').remove();
      $('#id_design_select').val(null).trigger('change.select2');
      $('#id_design').val('');
      $('#id_sub_design_select').find('option:not([value="all"])').remove();
      $('#id_sub_design_select').val(null).trigger('change.select2');
      $('#id_sub_design').val('');
    });

    function loadProducts(categoryCsv, preselect) {
      $.ajax({
        type: 'POST',
        url: base_url + 'index.php/admin_ret_offer/get_products_by_category',
        data: { id_category: categoryCsv },
        dataType: 'json',
        success: function(resp) {
          var $sel = $('#id_product_select');
          $sel.find('option:not([value="all"])').remove();
          if (resp.status && resp.data.length > 0) {
            $.each(resp.data, function(i, item) {
              $sel.append(new Option(item.product_name, item.pro_id, false, false));
            });
          }
          if (preselect.length > 0) {
            $sel.val(preselect).trigger('change');
          } else {
            $sel.val(null).trigger('change.select2');
          }
        }
      });
    }

    // ==========================================
    // CASCADING: Products → Designs
    // ==========================================
    $(document).on('change', '#id_product_select', function() {
      var selected = $(this).val() || [];
      var prods = selected.filter(function(v) { return v !== 'all'; });
      loadDesigns(prods.length > 0 ? prods.join(',') : '', []);
      // Reset sub-designs
      $('#id_sub_design_select').find('option:not([value="all"])').remove();
      $('#id_sub_design_select').val(null).trigger('change.select2');
      $('#id_sub_design').val('');
    });

    function loadDesigns(productCsv, preselect) {
      $.ajax({
        type: 'POST',
        url: base_url + 'index.php/admin_ret_offer/get_designs_by_product',
        data: { id_product: productCsv },
        dataType: 'json',
        success: function(resp) {
          var $sel = $('#id_design_select');
          $sel.find('option:not([value="all"])').remove();
          if (resp.status && resp.data.length > 0) {
            $.each(resp.data, function(i, item) {
              $sel.append(new Option(item.design_name, item.design_no, false, false));
            });
          }
          if (preselect.length > 0) {
            $sel.val(preselect).trigger('change');
          } else {
            $sel.val(null).trigger('change.select2');
          }
        }
      });
    }

    // ==========================================
    // CASCADING: Designs → Sub-Designs
    // ==========================================
    $(document).on('change', '#id_design_select', function() {
      var selected = $(this).val() || [];
      var designs = selected.filter(function(v) { return v !== 'all'; });
      loadSubDesigns(designs.length > 0 ? designs.join(',') : '', []);
    });

    function loadSubDesigns(designCsv, preselect) {
      $.ajax({
        type: 'POST',
        url: base_url + 'index.php/admin_ret_offer/get_sub_designs_by_design',
        data: { id_design: designCsv },
        dataType: 'json',
        success: function(resp) {
          var $sel = $('#id_sub_design_select');
          $sel.find('option:not([value="all"])').remove();
          if (resp.status && resp.data.length > 0) {
            $.each(resp.data, function(i, item) {
              $sel.append(new Option(item.sub_design_name, item.id_sub_design, false, false));
            });
          }
          if (preselect.length > 0) {
            $sel.val(preselect).trigger('change');
          } else {
            $sel.val(null).trigger('change.select2');
          }
        }
      });
    }

    // ==========================================
    // QUICK ADD SLAB — Modal
    // ==========================================
    $('#btn_quick_add_slab').on('click', function() {
      $('#slab_name').val('');
      $('#slab_min_days').val('0');
      $('#slab_max_days').val('');
      $('#modal_add_slab').modal('show');
      setTimeout(function() { $('#slab_name').focus(); }, 300);
    });

    $('#btn_save_slab').on('click', function() {
      var name = $.trim($('#slab_name').val());
      var min_days = $('#slab_min_days').val();
      var max_days = $('#slab_max_days').val();

      if (!name) { $.toaster({ priority: 'danger', title: 'Error', message: 'Slab name is required.' }); $('#slab_name').focus(); return; }
      if (min_days === '' || parseInt(min_days) < 0) { $.toaster({ priority: 'danger', title: 'Error', message: 'Min days must be 0 or greater.' }); return; }
      if (max_days !== '' && parseInt(max_days) < 0) { $.toaster({ priority: 'danger', title: 'Error', message: 'Max days must be 0 or greater.' }); return; }
      if (max_days !== '' && parseInt(max_days) < parseInt(min_days)) { $.toaster({ priority: 'danger', title: 'Error', message: 'Max days must be ≥ Min days.' }); return; }
      if (parseInt(min_days) === 0 && max_days !== '' && parseInt(max_days) === 0) { $.toaster({ priority: 'danger', title: 'Error', message: 'Min and Max cannot both be 0.' }); return; }

      var $btn = $(this);
      $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

      $.ajax({
        type: 'POST',
        url: base_url + 'index.php/admin_ret_offer/save_age_slab',
        data: { slab_name: name, min_days: min_days, max_days: max_days },
        dataType: 'json',
        success: function(resp) {
          $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Save Slab');
          if (resp.status) {
            $.toaster({ priority: 'success', title: 'Success', message: resp.msg });
            var newOption = new Option(resp.data.label, resp.data.id_slab, true, true);
            $('#id_age_slab_select').append(newOption).trigger('change');
            var current = $('#id_age_slab').val();
            var csv = current ? current + ',' + resp.data.id_slab : '' + resp.data.id_slab;
            $('#id_age_slab').val(csv);
            $('#modal_add_slab').modal('hide');
          } else {
            $.toaster({ priority: 'danger', title: 'Error', message: resp.msg });
          }
        },
        error: function() {
          $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Save Slab');
          $.toaster({ priority: 'danger', title: 'Error', message: 'Something went wrong.' });
        }
      });
    });

    // ==========================================
    // EDIT MODE — Load cascaded data
    // ==========================================
    <?php if ($is_edit && !empty($mapping['id_metal_type'])): ?>
    var editMetals = '<?php echo $mapping['id_metal_type']; ?>';
    loadCategories(editMetals, preselected_categories);
    setTimeout(function() {
      if (preselected_categories.length > 0) {
        loadProducts('<?php echo $mapping['id_category']; ?>', preselected_products);
      }
      setTimeout(function() {
        if (preselected_products.length > 0) {
          loadDesigns('<?php echo $mapping['id_product']; ?>', preselected_designs);
        }
        setTimeout(function() {
          if (preselected_designs.length > 0) {
            loadSubDesigns('<?php echo $mapping['id_design']; ?>', preselected_sub_designs);
          }
        }, 600);
      }, 600);
    }, 600);
    <?php endif; ?>

    // ==========================================
    // FORM SUBMIT
    // ==========================================
    $('#mapping_form').on('submit', function(e) {
      e.preventDefault();

      if (!$('#id_offer').val()) {
        $.toaster({ priority: 'danger', title: 'Error', message: 'Please select an Offer.' });
        return;
      }

      // Age slab validation
      var ageType = $('input[name="age_filter_type"]:checked').val();
      if (ageType === 'slab') {
        var slabVal = $('#id_age_slab_select').val();
        if (!slabVal || slabVal.length === 0) {
          $.toaster({ priority: 'danger', title: 'Error', message: 'Please select at least one Age Slab.' });
          return;
        }
      } else {
        $('#id_age_slab').val('');
      }

      var $btn = $('#btn_save_mapping');
      $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

      $.ajax({
        type: 'POST',
        url: base_url + 'index.php/admin_ret_offer/offer_mapping/save',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(resp) {
          if (resp.status) {
            $.toaster({ priority: 'success', title: 'Success', message: resp.msg });
            setTimeout(function() {
              window.location.href = base_url + 'index.php/admin_ret_offer/offer_mapping/list';
            }, 800);
          } else {
            $.toaster({ priority: 'danger', title: 'Error', message: resp.msg });
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Mapping');
          }
        },
        error: function() {
          $.toaster({ priority: 'danger', title: 'Error', message: 'Something went wrong. Please try again.' });
          $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Mapping');
        }
      });
    });
  });
  </script>
