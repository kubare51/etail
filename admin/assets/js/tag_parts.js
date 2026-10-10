/**
 * Partly Sale Module — Frontend JavaScript
 * 
 * Handles:
 * - Tag scan → auto-calculate minimum parts + render table
 * - Re-calculate when user increases parts
 * - Real-time weight validation (oninput)
 * - Save via AJAX → show "Add to Estimation" buttons
 * 
 * @module Partly Sale
 * @version 2.0
 */

$(document).ready(function () {

    // Prevent mouse scroll from changing number input values
    $(document).on('wheel', 'input[type="number"]', function (e) {
        $(this).blur();
        e.preventDefault();
    });


    var baseUrl = $('#base_url').val();
    var baseTagData = null;
    var childrenData = [];
    var costLimit = 200000;  // Dynamic from ret_settings.max_cash_amt
    var goldRate = 0;
    var wastagePct = 0;
    var mcType = 0;
    var mcValue = 0;
    var calcBased = 0;
    var baseCost = 0;  // Total base tag cost from server
    var taxPct = 0;    // Total tax % (CGST+SGST) for with-tax cost limit check
    var savedPartsId = null; // After save, holds the parts ID

    // =========================================================================
    // Event: Scan / Fetch Tag
    // =========================================================================

    $('#tag_scan_code').on('keydown', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            fetchTag();
        }
    });

    $('#btn_fetch_tag').click(function () {
        fetchTag();
    });

    // Auto-scan if redirected with ?tag= parameter (e.g., from list view Revert button)
    (function autoScanFromUrl() {
        var urlParams = new URLSearchParams(window.location.search);
        var tagParam = urlParams.get('tag');
        if (tagParam && tagParam.trim() !== '') {
            $('#tag_scan_code').val(tagParam.trim());
            fetchTag();
        }
    })();

    function fetchTag() {
        var tagCode = $.trim($('#tag_scan_code').val());
        if (tagCode === '') {
            showScanError('Please enter a tag code');
            return;
        }

        $('#btn_fetch_tag').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        hideScanError();

        // Clear previous tag data before loading new one
        $('.ts-parts-success').remove();
        $('#child_weight_tbody').html('');
        $('#child_weight_table tfoot').hide();
        $('#step_base_info, #step_actions, #balance_banner').hide();
        $('#tag_details_placeholder').show();
        $('#btn_save_parts').show().prop('disabled', true);
        $('#btn_reset_tag').show();
        $('#btn_calculate_parts').show();
        $('#parts_count').prop('readonly', false);
        baseTagData = null;
        childrenData = [];
        savedPartsId = null;


        $.ajax({
            url: baseUrl + 'index.php/admin_ret_tag_parts/get_tag_details',
            type: 'POST',
            data: { tag_code: tagCode },
            dataType: 'json',
            success: function (response) {
                if (response.status) {

                    // Already parted — check if we're in edit mode (from list view)
                    if (response.already_parted) {
                        var urlParams = new URLSearchParams(window.location.search);
                        var isEditMode = (urlParams.get('mode') === 'edit');

                        baseTagData = response.data;

                        // Set cost variables BEFORE displayBaseInfo — it uses baseCost
                        costLimit = response.cost_limit || 200000;
                        baseCost = response.base_cost || 0;
                        goldRate = response.gold_rate || 0;
                        wastagePct = response.wastage_pct || 0;
                        mcType = response.mc_type || 0;
                        mcValue = response.mc_value || 0;
                        calcBased = response.calc_based || 0;
                        taxPct = response.tax_pct || 0;

                        displayBaseInfo(baseTagData);

                        if (isEditMode) {
                            // Edit mode: render editable weight table with existing children
                            savedPartsId = response.parts_id;
                            var existingChildren = response.children || [];
                            var editableChildren = [];
                            $.each(existingChildren, function (i, child) {
                                editableChildren.push({
                                    child_order: child.child_order || (i + 1),
                                    tag_code: child.tag_code,
                                    child_tag_id: child.child_tag_id || null,
                                    parts_detail_id: child.parts_detail_id || null,
                                    gwt: parseFloat(child.child_gwt) || 0,
                                    nwt: parseFloat(child.child_nwt) || 0,
                                    less_wt: parseFloat(child.child_less_wt) || 0,
                                    stone_wt: parseFloat(child.child_stone_wt) || 0,
                                    dia_wt: parseFloat(child.child_dia_wt) || 0
                                });
                            });

                            childrenData = editableChildren;
                            renderChildWeightTable(editableChildren, response.base);
                            $('#parts_count').val(editableChildren.length);

                            $('#tag_details_placeholder').hide();
                            $('#step_base_info').show();
                            bindWeightInputs();
                            validatePartsWeights();
                            updateAllLegends();

                            // Lock tag scan area in edit mode — tag is already loaded, prevent changing it
                            $('#tag_scan_code').prop('readonly', true).css({ 'background': '#f0f0f0', 'cursor': 'not-allowed' });
                            $('#btn_fetch_tag').prop('disabled', true).css({ 'opacity': '0.5', 'cursor': 'not-allowed' });
                            // Hide Reset button in edit mode — not needed
                            $('#btn_reset_tag').hide();

                            $.toaster({ priority: 'info', title: 'Edit Mode', message: baseTagData.tag_code + ' — ' + editableChildren.length + ' parts loaded for editing' });
                            return;
                        }

                        // Normal mode: show success card (read-only)
                        var branchId = baseTagData.current_branch || '';
                        var childCodes = response.child_codes || [];
                        savedPartsId = response.parts_id;

                        renderSuccessCard(childCodes, response.parts_id, branchId);

                        // Hide weight form, show info
                        $('#child_weight_tbody, .totals-row, .validation-row, #step_actions').hide();
                        $('#btn_save_parts').hide();
                        $('#tag_details_placeholder').hide();
                        $('#step_base_info').show();

                        // Parts count readonly when sale is completed
                        $('#parts_count').val(childCodes.length).prop('readonly', true);
                        // Hide Reset + Re-calculate buttons — tag is already saved
                        $('#btn_reset_tag').hide();
                        $('#btn_calculate_parts').hide();

                        $.toaster({ priority: 'info', title: 'Active Parts', message: baseTagData.tag_code + ' has ' + childCodes.length + ' active parts' });
                        return;
                    }

                    baseTagData = response.data;
                    costLimit = response.cost_limit || 200000;
                    baseCost = response.base_cost || 0;
                    goldRate = response.gold_rate || 0;
                    wastagePct = response.wastage_pct || 0;
                    mcType = response.mc_type || 0;
                    mcValue = response.mc_value || 0;
                    calcBased = response.calc_based || 0;
                    taxPct = response.tax_pct || 0;

                    displayBaseInfo(baseTagData);

                    // Auto-split base weight into cost-limit-sized rows
                    var baseGwt = parseFloat(baseTagData.gross_wt);
                    var baseNwt = parseFloat(baseTagData.net_wt);
                    var baseLessWt = parseFloat(baseTagData.less_wt);
                    var baseStoneWt = parseFloat(baseTagData.stone_wt);
                    var baseDiaWt = parseFloat(baseTagData.dia_wt);

                    var maxGwtPerPart = baseGwt; // default: no split
                    if (goldRate > 0 && costLimit > 0) {
                        var costPerGram = computeCostForGwt(baseGwt) / baseGwt;
                        if (costPerGram > 0) {
                            maxGwtPerPart = Math.floor((costLimit / costPerGram) * 1000) / 1000;
                            if (maxGwtPerPart <= 0) maxGwtPerPart = baseGwt;
                        }
                    }

                    // Carat-to-gram conversion factor (1 carat = 0.2 gram → divide by 5)
                    // Derive from base tag data for accuracy
                    var ctPerGram = 5; // default
                    if (baseDiaWt > 0 && baseLessWt > baseStoneWt) {
                        ctPerGram = baseDiaWt / (baseLessWt - baseStoneWt);
                    }

                    var autoChildren = [];
                    var numParts = Math.ceil(baseGwt / maxGwtPerPart);
                    if (numParts < 1) numParts = 1;
                    var perPartGwt = Math.floor((baseGwt / numParts) * 1000) / 1000;
                    var usedGwt = 0, usedLess = 0, usedStone = 0;
                    for (var partNum = 1; partNum <= numParts; partNum++) {
                        var partGwt, partNwt, partLess, partStone, partDia;
                        if (partNum === numParts) {
                            // Last part absorbs remainder for gwt, less_wt, stone_wt
                            partGwt = Math.round((baseGwt - usedGwt) * 1000) / 1000;
                            partLess = Math.round((baseLessWt - usedLess) * 1000) / 1000;
                            partStone = Math.round((baseStoneWt - usedStone) * 1000) / 1000;
                        } else {
                            // Stagger each row by -0.01g so no two rows are identical
                            partGwt = Math.round((perPartGwt - ((partNum - 1) * 0.01)) * 1000) / 1000;
                            if (partGwt < 0.001) partGwt = perPartGwt; // safety floor

                            var ratio = partGwt / baseGwt;
                            partLess = Math.round(baseLessWt * ratio * 1000) / 1000;
                            partStone = Math.round(baseStoneWt * ratio * 1000) / 1000;
                        }

                        // DERIVE dia_wt and nwt from formula
                        // dia_wt(carats) = (less_wt - stone_wt) × ctPerGram
                        partDia = Math.round((partLess - partStone) * ctPerGram * 1000) / 1000;
                        // nwt = gwt - less_wt
                        partNwt = Math.round((partGwt - partLess) * 1000) / 1000;

                        autoChildren.push({
                            child_order: partNum,
                            tag_code: baseTagData.tag_code + '-' + partNum,
                            gwt: partGwt,
                            nwt: partNwt,
                            less_wt: partLess,
                            stone_wt: partStone,
                            dia_wt: partDia
                        });
                        usedGwt += partGwt;
                        usedLess += partLess;
                        usedStone += partStone;
                    }

                    childrenData = autoChildren;
                    renderChildWeightTable(autoChildren, response.base);
                    $('#parts_count').val(autoChildren.length);

                    $.toaster({ priority: 'success', title: 'Tag Found', message: baseTagData.tag_code + ' — divided into ' + autoChildren.length + ' parts' });
                } else {
                    var errMsg = response.message || 'Tag not found';
                    showScanError(errMsg);
                    $.toaster({ priority: 'danger', title: 'Partly Sale', message: errMsg });
                    resetForm();
                }
            },
            error: function () {
                showScanError('Server error. Please try again.');
                $.toaster({ priority: 'danger', title: 'Server Error', message: 'Server error while fetching tag. Please try again.' });
            },
            complete: function () {
                $('#btn_fetch_tag').prop('disabled', false).html('<i class="fa fa-search"></i> Scan');
            }
        });
    }

    // =========================================================================
    // Display Base Tag Info
    // =========================================================================

    function displayBaseInfo(tag) {
        $('#base_tag_code_display').text(tag.tag_code);
        $('#base_tag_id').val(tag.tag_id);
        $('#base_tag_code').val(tag.tag_code);

        $('#info_product').text(tag.product_name || '-');
        $('#info_design').text(tag.design_name || '-');
        $('#info_purity').text(tag.purity_name || '-');
        $('#info_lot').text(tag.tag_lot_id || '-');

        $('#info_gwt').text(parseFloat(tag.gross_wt).toFixed(3));
        $('#info_nwt').text(parseFloat(tag.net_wt).toFixed(3));
        $('#info_less_wt').text(parseFloat(tag.less_wt).toFixed(3));
        $('#info_stone_wt').text(parseFloat(tag.stone_wt).toFixed(3));
        $('#info_dia_wt').text(parseFloat(tag.dia_wt).toFixed(3));

        // Display estimated base cost
        if (baseCost > 0) {
            $('#info_cost').html('₹' + Math.round(baseCost).toLocaleString('en-IN'));
        } else {
            $('#info_cost').text('-');
        }

        // Set branch and load branch details for customer slider defaults
        var tagBranch = tag.current_branch || '';
        if (tagBranch) {
            $('#id_branch').val(tagBranch);
            // Fetch branch details for country/state/city defaults
            $.ajax({
                url: baseUrl + 'index.php/admin_manage/getBranchDetails',
                type: 'POST',
                dataType: 'json',
                data: { id_branch: tagBranch },
                success: function (data) {
                    $.each(data, function (key, item) {
                        if (tagBranch == item.id_branch) {
                            $('#branch_id_country').val(item.id_country);
                            $('#branch_id_state').val(item.id_state);
                            $('#branch_id_city').val(item.id_city);
                            $('#branch_pincode').val(item.pincode);
                            $('#branch_id_village').val(item.id_village);
                        }
                    });
                }
            });
        }

        // Hide zero-value detail items
        var zeroFields = ['info_less_wt', 'info_stone_wt', 'info_dia_wt'];
        $.each(zeroFields, function (i, id) {
            var v = parseFloat($('#' + id).text()) || 0;
            $('#' + id).closest('.ts-detail-item').toggle(v !== 0);
        });

        $('#tag_details_placeholder').hide();
        $('#step_base_info').show();
        $('#parts_count').focus().select();
    }

    // =========================================================================
    // Event: Re-calculate Parts
    // =========================================================================

    $('#btn_calculate_parts').click(function () {
        recalculateParts();
    });

    $('#parts_count').on('keydown', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            recalculateParts();
        }
    });

    function recalculateParts() {
        if (!baseTagData) {
            $.toaster({ priority: 'warning', title: 'Partly Sale', message: 'Please scan a tag first' });
            return;
        }

        var partsCount = parseInt($('#parts_count').val());
        if (isNaN(partsCount) || partsCount < 2 || partsCount > 99) {
            $.toaster({ priority: 'warning', title: 'Partly Sale', message: 'Parts count must be between 2 and 99' });
            return;
        }

        // min_parts is advisory only — no enforcement

        $('#btn_calculate_parts').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Calculating...');

        $.ajax({
            url: baseUrl + 'index.php/admin_ret_tag_parts/calculate_parts',
            type: 'POST',
            data: {
                tag_id: baseTagData.tag_id,
                tag_code: baseTagData.tag_code,
                parts_count: partsCount
            },
            dataType: 'json',
            success: function (response) {
                if (response.status) {
                    childrenData = response.children;
                    renderChildWeightTable(response.children, response.base);
                } else {
                    $.toaster({ priority: 'danger', title: 'Error', message: response.message || 'Calculation failed' });
                }
            },
            error: function () {
                $.toaster({ priority: 'danger', title: 'Server Error', message: 'Server error during calculation.' });
            },
            complete: function () {
                $('#btn_calculate_parts').prop('disabled', false).html('<i class="fa fa-calculator"></i> Re-calculate');
            }
        });
    }

    // =========================================================================
    // Render Child Weight Table
    // =========================================================================

    function renderChildWeightTable(children, base) {
        var tbody = '';

        $.each(children, function (i, child) {
            var legendHtml = '<td class="col-legend">' + computeLegend(child) + '</td>';
            var childTagId = child.child_tag_id || '';
            var partsDetailId = child.parts_detail_id || '';
            tbody += '<tr data-index="' + i + '" data-child-tag-id="' + childTagId + '" data-parts-detail-id="' + partsDetailId + '">' +
                '<td>' + child.child_order + '</td>' +
                '<td><span class="child-code">' + child.tag_code + '</span></td>' +
                '<td class="col-gwt"><input type="number" step="0.001" class="child-gwt" data-index="' + i + '" value="' + parseFloat(child.gwt).toFixed(3) + '"/></td>' +
                '<td class="col-nwt"><input type="number" step="0.001" class="child-nwt" data-index="' + i + '" value="' + parseFloat(child.nwt).toFixed(3) + '"/></td>' +
                '<td class="col-less"><input type="number" step="0.001" class="child-less" data-index="' + i + '" value="' + parseFloat(child.less_wt).toFixed(3) + '"/></td>' +
                '<td class="col-stone"><input type="number" step="0.001" class="child-stone" data-index="' + i + '" value="' + parseFloat(child.stone_wt).toFixed(3) + '"/></td>' +
                '<td class="col-dia"><input type="number" step="0.001" class="child-dia" data-index="' + i + '" value="' + parseFloat(child.dia_wt).toFixed(3) + '"/></td>' +
                legendHtml +
                '</tr>';
        });

        $('#child_weight_tbody').html(tbody);

        // Set base totals
        $('#base_gwt').text(parseFloat(base.gwt).toFixed(3));
        $('#base_nwt').text(parseFloat(base.nwt).toFixed(3));
        $('#base_less_wt').text(parseFloat(base.less_wt).toFixed(3));
        $('#base_stone_wt').text(parseFloat(base.stone_wt).toFixed(3));
        $('#base_dia_wt').text(parseFloat(base.dia_wt).toFixed(3));

        // Show balance banner
        if (baseTagData) {
            $('#bal_tag_code').text(baseTagData.tag_code);
            $('#bal_base_gwt').text(parseFloat(base.gwt).toFixed(3));
            $('#balance_banner').show();
        }

        // Show the actions (Save/Cancel buttons in table header)
        $('#step_actions').show();
        $('#child_weight_table tfoot').show();
        $('#child_weight_tbody, .totals-row, .validation-row').show();

        // Hide columns where base value is zero
        hideZeroColumns(base);

        // Bind validation on input change + dynamic row creation
        bindWeightInputs();

        // Initial validation
        validatePartsWeights();
    }

    // =========================================================================
    // Hide Zero Columns
    // =========================================================================

    function hideZeroColumns(base) {
        // Clear values (not columns) where base weight is zero
        var cols = [
            { key: 'less_wt', cls: 'child-less', totalId: 'total_less_wt', baseId: 'base_less_wt', validateId: 'validate_less_wt', detailId: 'info_less_wt' },
            { key: 'stone_wt', cls: 'child-stone', totalId: 'total_stone_wt', baseId: 'base_stone_wt', validateId: 'validate_stone_wt', detailId: 'info_stone_wt' },
            { key: 'dia_wt', cls: 'child-dia', totalId: 'total_dia_wt', baseId: 'base_dia_wt', validateId: 'validate_dia_wt', detailId: 'info_dia_wt' }
        ];

        $.each(cols, function (i, col) {
            var val = parseFloat(base[col.key]) || 0;
            if (val === 0) {
                // Clear input values and make them readonly
                $('#child_weight_tbody .' + col.cls).val('').attr('readonly', true).attr('placeholder', '-');
                // Clear footer texts
                $('#' + col.totalId).text('-');
                $('#' + col.baseId).text('-');
                $('#' + col.validateId).html('<span style="color:#ccc;">—</span>');
            }
        });
    }

    // =========================================================================
    // Real-time Weight Validation
    // =========================================================================

    function validatePartsWeights() {
        var tolerance = 0.001;
        var fields = [
            { class: 'child-gwt', totalId: 'total_gwt', validateId: 'validate_gwt', baseId: 'base_gwt' },
            { class: 'child-nwt', totalId: 'total_nwt', validateId: 'validate_nwt', baseId: 'base_nwt' },
            { class: 'child-less', totalId: 'total_less_wt', validateId: 'validate_less_wt', baseId: 'base_less_wt' },
            { class: 'child-stone', totalId: 'total_stone_wt', validateId: 'validate_stone_wt', baseId: 'base_stone_wt' },
            { class: 'child-dia', totalId: 'total_dia_wt', validateId: 'validate_dia_wt', baseId: 'base_dia_wt' }
        ];

        var allValid = true;

        $.each(fields, function (i, f) {
            var baseText = $('#' + f.baseId).text();
            var baseVal = parseFloat(baseText) || 0;

            // Skip zero-weight columns
            if (baseVal === 0) {
                $('#' + f.totalId).text('-');
                $('#' + f.validateId).html('<span style="color:#ccc;">—</span>');
                return true; // continue
            }

            var sum = 0;
            var hasNegative = false;

            $('#child_weight_tbody .' + f.class).each(function () {
                var val = parseFloat($(this).val()) || 0;
                if (val < 0) hasNegative = true;
                sum += val;
            });

            var diff = Math.abs(sum - baseVal);
            var valid = diff <= tolerance && !hasNegative;

            // Update totals display
            $('#' + f.totalId).text(sum.toFixed(3));

            // Update validation indicator
            if (valid) {
                $('#' + f.validateId).html('<span class="valid-indicator"><i class="fa fa-check-circle"></i></span>');
            } else {
                $('#' + f.validateId).html('<span class="invalid-indicator"><i class="fa fa-times-circle"></i></span>');
                allValid = false;
            }

            // Mark individual inputs
            $('#child_weight_tbody .' + f.class).each(function () {
                var val = parseFloat($(this).val()) || 0;
                $(this).toggleClass('is-invalid', val < 0);
                $(this).toggleClass('is-valid', val >= 0 && valid);
            });
        });

        // Gate save button
        $('#btn_save_parts').prop('disabled', !allValid);

        // Update balance banner
        var gwtTotal = parseFloat($('#total_gwt').text()) || 0;
        var baseGwt = parseFloat($('#base_gwt').text()) || 0;
        var balance = baseGwt - gwtTotal;
        $('#bal_remaining').text(balance.toFixed(3));
        if (Math.abs(balance) <= tolerance) {
            $('#bal_remaining').removeClass('ts-bal-warn ts-bal-error').addClass('ts-bal-ok');
        } else if (balance > 0) {
            $('#bal_remaining').removeClass('ts-bal-ok ts-bal-error').addClass('ts-bal-warn');
        } else {
            $('#bal_remaining').removeClass('ts-bal-ok ts-bal-warn').addClass('ts-bal-error');
        }

        // Update legend for each row
        updateAllLegends();

        // Update children data from inputs
        updateChildrenFromInputs();

        return allValid;
    }

    function updateChildrenFromInputs() {
        childrenData = [];
        $('#child_weight_tbody tr').each(function () {
            var idx = $(this).data('index');
            childrenData.push({
                child_order: idx + 1,
                child_tag_id: $(this).data('child-tag-id') || null,
                parts_detail_id: $(this).data('parts-detail-id') || null,
                gwt: parseFloat($(this).find('.child-gwt').val()) || 0,
                nwt: parseFloat($(this).find('.child-nwt').val()) || 0,
                less_wt: parseFloat($(this).find('.child-less').val()) || 0,
                stone_wt: parseFloat($(this).find('.child-stone').val()) || 0,
                dia_wt: parseFloat($(this).find('.child-dia').val()) || 0
            });
        });
    }

    // =========================================================================
    // Save Partly Sale
    // =========================================================================

    $('#btn_save_parts').click(function () {
        if (!baseTagData) {
            $.toaster({ priority: 'warning', title: 'Partly Sale', message: 'No base tag loaded' });
            return;
        }

        if (childrenData.length < 2) {
            $.toaster({ priority: 'warning', title: 'Partly Sale', message: 'Minimum 2 parts required to save. This tag is already under the cost limit — increase the parts count or add directly to estimation.' });
            return;
        }

        if (!validatePartsWeights()) {
            $.toaster({ priority: 'danger', title: 'Validation Failed', message: 'Weight totals must match the base tag exactly' });
            return;
        }

        // Ensure parts count input matches actual children rows
        var inputCount = parseInt($('#parts_count').val()) || 0;
        if (inputCount !== childrenData.length) {
            $.toaster({ priority: 'warning', title: 'Parts Mismatch', message: 'Number of Parts (' + inputCount + ') does not match table rows (' + childrenData.length + '). Please click Re-calculate first.' });
            return;
        }

        // Detect edit mode: savedPartsId is set when an already-parted tag is loaded in edit mode
        var urlParams = new URLSearchParams(window.location.search);
        var isEditMode = (urlParams.get('mode') === 'edit') && savedPartsId;

        var confirmMsg = isEditMode
            ? 'Update Weights?\n\nBase tag: ' + baseTagData.tag_code + '\nParts: ' + childrenData.length
            : 'Confirm Partly Sale?\n\nBase tag: ' + baseTagData.tag_code + '\nParts: ' + childrenData.length + '\n\nThis action cannot be easily undone.';

        if (!confirm(confirmMsg)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        if (isEditMode) {
            // Edit mode: update existing child weights via admin_modify
            $.ajax({
                url: baseUrl + 'index.php/admin_ret_tag_parts/admin_modify',
                type: 'POST',
                data: {
                    parts_id: savedPartsId,
                    children: JSON.stringify(childrenData)
                },
                dataType: 'json',
                success: function (response) {
                    if (response.status) {
                        $.toaster({ priority: 'success', title: 'Updated!', message: 'Weights updated successfully' });

                        // Use child_codes from response (re-partition) or scrape from DOM (weight-only edit)
                        var childCodes = response.child_codes || [];
                        if (childCodes.length === 0) {
                            $('#child_weight_tbody tr').each(function () {
                                var code = $(this).find('.child-code').text();
                                if (code) childCodes.push(code);
                            });
                        }

                        // Update parts ID if re-partition created a new one
                        if (response.parts_id) {
                            savedPartsId = response.parts_id;
                        }

                        var branchId = baseTagData.current_branch || '';

                        // Show success card with edit-specific message
                        renderSuccessCard(childCodes, savedPartsId, branchId, true);

                        // Hide weight form, show success
                        $('#child_weight_tbody, .totals-row, .validation-row, #step_actions').hide();
                        $btn.hide();

                        // Parts count — sync to actual and make readonly
                        $('#parts_count').val(childCodes.length).prop('readonly', true);
                        // Hide Reset + Re-calculate buttons after edit save
                        $('#btn_reset_tag').hide();
                        $('#btn_calculate_parts').hide();

                        // Remove mode=edit from URL so page refresh shows normal view
                        if (window.history && window.history.replaceState) {
                            var cleanUrl = window.location.pathname + '?tag=' + encodeURIComponent(baseTagData.tag_code);
                            window.history.replaceState(null, '', cleanUrl);
                        }
                    } else {
                        $.toaster({ priority: 'danger', title: 'Failed', message: response.message || 'Update failed' });
                        $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Save');
                    }
                },
                error: function () {
                    $.toaster({ priority: 'danger', title: 'Server Error', message: 'Server error. Please try again.' });
                    $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Save');
                }
            });
        } else {
            // New mode: create new partly sale
            $.ajax({
                url: baseUrl + 'index.php/admin_ret_tag_parts/save',
                type: 'POST',
                data: {
                    tag_code: baseTagData.tag_code,
                    parts_count: childrenData.length,
                    children: JSON.stringify(childrenData)
                },
                dataType: 'json',
                success: function (response) {
                    if (response.status) {
                        var childCodes = response.child_codes || [];
                        savedPartsId = response.parts_id;
                        $.toaster({ priority: 'success', title: 'Saved!', message: 'Partly Sale ID: ' + response.parts_id });

                        var branchId = baseTagData.current_branch || '';

                        renderSuccessCard(childCodes, response.parts_id, branchId);

                        // Hide form elements and show success
                        $('#child_weight_tbody, .totals-row, .validation-row, #step_actions').hide();
                        $btn.hide();

                        // Parts count — sync to actual and make readonly
                        $('#parts_count').val(childCodes.length).prop('readonly', true);
                        // Hide Reset + Re-calculate buttons after new save
                        $('#btn_reset_tag').hide();
                        $('#btn_calculate_parts').hide();
                    } else {
                        $.toaster({ priority: 'danger', title: 'Failed', message: response.message || 'Save operation failed' });
                        $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Save');
                    }
                },
                error: function () {
                    $.toaster({ priority: 'danger', title: 'Server Error', message: 'Server error. Please try again.' });
                    $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Save');
                }
            });
        }
    });

    // =========================================================================
    // Reusable: Render success card with child tag rows
    // =========================================================================
    function renderSuccessCard(childCodes, partsId, branchId, isEdit) {
        // Remove any existing success card
        $('.ts-parts-success').remove();

        var headingText = isEdit ? 'Partly Sale Updated!' : 'Partly Sale Completed!';

        var successHtml = '<div class="ts-parts-success" style="background:linear-gradient(135deg,#d4edda,#c3e6cb);border:2px solid #28a745;border-radius:8px;padding:20px;text-align:center;margin-top:15px;">';
        successHtml += '<h4 style="color:#155724;margin:0 0 12px;"><i class="fa fa-check-circle"></i> ' + headingText + '</h4>';
        successHtml += '<p style="color:#155724;font-size:13px;margin-bottom:15px;">ID: <b>' + partsId + '</b> | Base: <b>' + baseTagData.tag_code + '</b></p>';

        // Batch selection header
        successHtml += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;padding:0 4px;">';
        successHtml += '<label style="margin:0;font-size:12px;color:#155724;cursor:pointer;"><input type="checkbox" class="ts-child-select-all" style="margin-right:5px;" /> Select All</label>';
        successHtml += '<button class="btn btn-info btn-xs ts-batch-estimation-btn" disabled style="padding:3px 12px;font-weight:600;"><i class="fa fa-calculator"></i> Add Selected to Estimation</button>';
        successHtml += '</div>';

        successHtml += '<div style="display:flex;flex-direction:column;gap:8px;align-items:stretch;">';
        $.each(childCodes, function (i, code) {
            successHtml += '<div class="ts-child-row" style="background:#fff;border:1.5px solid #28a745;border-radius:6px;padding:8px 12px;" data-code="' + code + '" data-branch="' + branchId + '">';
            successHtml += '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">';
            successHtml += '<input type="checkbox" class="ts-child-select" data-code="' + code + '" style="margin-right:2px;" />';
            successHtml += '<b style="font-size:13px;color:#2c3e50;white-space:nowrap;">' + code + '</b>';
            successHtml += '<div class="input-group" style="flex:1;min-width:180px;">';
            successHtml += '<input type="text" class="form-control input-sm ts-cus-mobile" placeholder="Customer Mobile / Name" autocomplete="off" style="font-size:12px;" />';
            successHtml += '<input type="hidden" class="ts-cus-id" value="" />';
            successHtml += '<span class="input-group-btn">';
            successHtml += '<button class="btn btn-success btn-sm ts-add-cus-btn" type="button" title="Add New Customer" style="padding:4px 8px;"><i class="fa fa-plus"></i></button>';
            successHtml += '<button class="btn btn-primary btn-sm ts-edit-cus-btn" type="button" title="Edit Customer" style="padding:4px 8px;display:none;"><i class="fa fa-external-link"></i></button>';
            successHtml += '</span></div>';
            successHtml += '<button class="btn btn-default btn-xs ts-copy-btn" data-code="' + code + '" title="Copy Tag Code" style="padding:3px 8px;"><i class="fa fa-copy"></i> Copy</button>';
            successHtml += '<button class="btn btn-primary btn-xs ts-add-estimation-btn" title="Add to Estimation" style="padding:3px 8px;"><i class="fa fa-calculator"></i> Estimation</button>';
            successHtml += '</div>';
            successHtml += '</div>';
        });
        successHtml += '</div>';
        successHtml += '<button class="btn btn-primary btn-sm" id="btn_new_parts" style="margin-top:15px;border-radius:20px;padding:6px 20px;"><i class="fa fa-plus"></i> New Partly Sale</button>';
        successHtml += '</div>';

        $('.ts-table-scroll').append(successHtml);

        // Initialize autocomplete on each customer input
        $('.ts-parts-success .ts-cus-mobile').each(function () {
            var $input = $(this);
            var $row = $input.closest('.ts-child-row');
            $input.autocomplete({
                source: function (request, response) {
                    if (request.term.length < 3) { response([]); return; }
                    $.ajax({
                        url: baseUrl + 'index.php/admin_ret_estimation/getCustomersBySearch/',
                        type: 'POST',
                        dataType: 'json',
                        data: { searchTxt: request.term, esti_for: 1 },
                        success: function (data) { response(data || []); }
                    });
                },
                minLength: 3,
                select: function (e, i) {
                    e.preventDefault();
                    $input.val(i.item.label);
                    $row.find('.ts-cus-id').val(i.item.value);
                    $row.find('.ts-edit-cus-btn').show();
                }
            });
        });
    }

    // Copy button handler (delegated)
    $(document).on('click', '.ts-copy-btn', function () {
        var code = $(this).data('code');
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(code).select();
        document.execCommand('copy');
        tempInput.remove();
        $(this).html('<i class="fa fa-check"></i>').removeClass('btn-success btn-default').addClass('btn-default');
        var btn = $(this);
        setTimeout(function () { btn.html('<i class="fa fa-copy"></i>'); }, 1500);
        $.toaster({ priority: 'info', title: 'Copied!', message: code + ' copied to clipboard' });
    });

    // Add New Customer — opens the offcanvas slider with branch defaults
    $(document).on('click', '.ts-add-cus-btn', function () {
        var $row = $(this).closest('.ts-child-row');
        window._tsActiveChildRow = $row;

        // Clear slider form fields for fresh entry
        $('#id_customer').val('');
        $('#cus_first_name').val('');
        $('#cus_mobile').val('').prop('readonly', false);
        $('#cus_email').val('');
        $('#address1, #address2, #address3').val('');
        $('#gst_no').val('');
        $('#pin_code_add').val($('#branch_pincode').val() || '');
        $('#title').val('Mr');

        // Set branch-based country/state/city defaults
        $('#id_country').val($('#branch_id_country').val());
        $('#id_state').val($('#branch_id_state').val());
        $('#id_city').val($('#branch_id_city').val());

        // Load country dropdown and open slider
        if (typeof get_country === 'function') get_country();
        if (typeof get_villages_by_pincode === 'function') get_villages_by_pincode($('#branch_pincode').val() || '');

        $('.gst').hide();
        $('#demo').offcanvas('show');
    });

    // Edit Customer — fetch details, prefill slider, and open
    $(document).on('click', '.ts-edit-cus-btn', function () {
        var $row = $(this).closest('.ts-child-row');
        var cusId = $row.find('.ts-cus-id').val();
        if (!cusId) {
            $.toaster({ priority: 'warning', title: 'Customer', message: 'Select a customer first' });
            return;
        }
        window._tsActiveChildRow = $row;

        // Fetch customer details and prefill slider (same as estimation get_customer)
        $.ajax({
            type: 'POST',
            url: baseUrl + 'index.php/admin_ret_estimation/get_customer?nocache=' + new Date().getUTCSeconds(),
            cache: false,
            dataType: 'json',
            data: { id_customer: cusId },
            success: function (data) {
                $('#id_customer').val(cusId);
                $('#cus_first_name').val(data.firstname);
                $('#cus_mobile').val(data.mobile).prop('readonly', true);
                $('#cus_email').val(data.email);
                $('#address1').val(data.address1);
                $('#address2').val(data.address2);
                $('#address3').val(data.address3);
                $('#pin_code_add').val(data.pincode);
                $('#gst_no').val(data.gst_number);
                $('#id_country').val(data.id_country);
                $('#id_state').val(data.id_state);
                $('#id_city').val(data.id_city);
                $('#title').val(data.title);
                $('#pan').val(data.pan_no);
                $('#aadharid').val(data.aadharid);

                if (data.is_vip == 1) { $('#vip1').prop('checked', true); }
                else { $('#vip0').prop('checked', true); }

                if (typeof get_country === 'function') get_country();
                if (typeof get_profession === 'function') get_profession();
                if (typeof get_villages_by_pincode === 'function') get_villages_by_pincode(data.pincode);

                $('#demo').offcanvas('show');
            }
        });
    });

    // Add to Estimation — check tag availability (billed/reserved) then open
    $(document).on('click', '.ts-add-estimation-btn', function () {
        var $btn = $(this);
        var $row = $btn.closest('.ts-child-row');
        var code = $row.data('code') || $btn.data('code');
        var branch = $row.data('branch') || $btn.data('branch') || '';
        var cusId = $row.find('.ts-cus-id').val() || '';
        var cusName = $row.find('.ts-cus-mobile').val() || '';

        var origHtml = $btn.html();
        $btn.html('<i class="fa fa-spinner fa-spin"></i> Checking...').prop('disabled', true);

        $.ajax({
            url: baseUrl + 'index.php/admin_ret_tag_parts/checkTagEstimation',
            type: 'POST',
            dataType: 'json',
            data: { tag_code: code },
            success: function (data) {
                if (!data.available) {
                    $.toaster({ priority: 'danger', title: 'Not Available', message: data.message || 'Tag cannot be added to estimation' });
                    $btn.html(origHtml).prop('disabled', false);
                    return;
                }
                // Tag is available — open estimation
                var url = baseUrl + 'index.php/admin_ret_estimation/estimation/add?tag_code=' + encodeURIComponent(code) + '&select_tag_details=1&from_parts=1';
                if (branch) url += '&branch_id=' + encodeURIComponent(branch);
                if (cusId) {
                    url += '&cus_id=' + encodeURIComponent(cusId);
                    url += '&cus_name=' + encodeURIComponent(cusName);
                }
                window.open(url, '_blank');
                $btn.html(origHtml).prop('disabled', false);
            },
            error: function () {
                $.toaster({ priority: 'danger', title: 'Error', message: 'Could not verify tag status' });
                $btn.html(origHtml).prop('disabled', false);
            }
        });
    });

    // =========================================================================
    // Checkbox Selection — Success Card
    // =========================================================================

    // Select All checkbox in success card
    $(document).on('change', '.ts-child-select-all', function () {
        var checked = $(this).prop('checked');
        $(this).closest('.ts-parts-success').find('.ts-child-select').prop('checked', checked);
        updateBatchBtnState('.ts-parts-success', '.ts-child-select', '.ts-batch-estimation-btn');
    });

    // Individual child checkbox in success card
    $(document).on('change', '.ts-child-select', function () {
        var $container = $(this).closest('.ts-parts-success');
        var total = $container.find('.ts-child-select').length;
        var checked = $container.find('.ts-child-select:checked').length;
        $container.find('.ts-child-select-all').prop('checked', total === checked && total > 0);
        updateBatchBtnState('.ts-parts-success', '.ts-child-select', '.ts-batch-estimation-btn');
    });

    // Batch Add to Estimation — Success Card
    $(document).on('click', '.ts-batch-estimation-btn', function () {
        batchAddToEstimation('.ts-parts-success', '.ts-child-select', $(this));
    });

    // =========================================================================
    // Checkbox Selection — Detail Modal
    // =========================================================================

    // Select All checkbox in detail modal
    $(document).on('change', '.ts-modal-select-all', function () {
        var checked = $(this).prop('checked');
        $(this).closest('.modal-body, .bootbox-body').find('.ts-modal-child-select').prop('checked', checked);
        updateBatchBtnState('.modal-body, .bootbox-body', '.ts-modal-child-select', '.ts-modal-batch-estimation-btn');
    });

    // Individual child checkbox in detail modal
    $(document).on('change', '.ts-modal-child-select', function () {
        var $container = $(this).closest('.modal-body, .bootbox-body');
        var total = $container.find('.ts-modal-child-select').length;
        var checked = $container.find('.ts-modal-child-select:checked').length;
        $container.find('.ts-modal-select-all').prop('checked', total === checked && total > 0);
        updateBatchBtnState('.modal-body, .bootbox-body', '.ts-modal-child-select', '.ts-modal-batch-estimation-btn');
    });

    // Batch Add to Estimation — Detail Modal
    $(document).on('click', '.ts-modal-batch-estimation-btn', function () {
        batchAddToEstimation('.modal-body, .bootbox-body', '.ts-modal-child-select', $(this));
    });

    // =========================================================================
    // Batch Estimation Helpers
    // =========================================================================

    function updateBatchBtnState(containerSel, checkboxSel, btnSel) {
        var checked = $(containerSel).find(checkboxSel + ':checked').length;
        $(containerSel).find(btnSel).prop('disabled', checked === 0);
        // Update button text with count
        if (checked > 0) {
            $(containerSel).find(btnSel).html('<i class="fa fa-calculator"></i> Add ' + checked + ' to Estimation');
        } else {
            $(containerSel).find(btnSel).html('<i class="fa fa-calculator"></i> Add Selected to Estimation');
        }
    }

    function batchAddToEstimation(containerSel, checkboxSel, $btn) {
        var $container = $btn.closest(containerSel);
        if (!$container.length) $container = $(containerSel);

        // Collect each selected tag's code + customer info
        var selectedItems = [];
        var branchId = '';

        $container.find(checkboxSel + ':checked').each(function () {
            var code = $(this).data('code');
            if (!code) return true; // continue

            var $row = $(this).closest('.ts-child-row') || $(this).closest('tr');
            var rowCusId = $row.find('.ts-cus-id').val() || '';
            var rowCusName = $row.find('.ts-cus-mobile').val() || '';

            selectedItems.push({ code: code, cusId: rowCusId, cusName: rowCusName });

            // Get branch from row data attribute
            if (!branchId) {
                branchId = $row.data('branch') || $row.find('.ts-add-estimation-btn').data('branch') || $btn.data('branch') || '';
            }
        });

        if (selectedItems.length === 0) {
            $.toaster({ priority: 'warning', title: 'No Selection', message: 'Please select at least one tag' });
            return;
        }

        // ---------------------------------------------------------------
        // Step 1: Group by customer (synchronous)
        // ---------------------------------------------------------------
        var customerGroups = {};
        $.each(selectedItems, function (i, item) {
            var key = item.cusId || '__none__';
            if (!customerGroups[key]) {
                customerGroups[key] = { cusId: item.cusId, cusName: item.cusName, codes: [] };
            }
            customerGroups[key].codes.push(item.code);
        });

        // ---------------------------------------------------------------
        // Step 2: Build URLs and open tabs using <a> link clicks
        // Browser treats programmatic anchor clicks as user navigation,
        // not popups — bypasses Chrome's popup blocker.
        // ---------------------------------------------------------------
        var groupCount = 0;
        $.each(customerGroups, function (key, group) {
            // Sort codes in natural ascending order (GBT-01392-1, -2, -3)
            group.codes.sort(function (a, b) {
                return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
            });

            var url = baseUrl + 'index.php/admin_ret_estimation/estimation/add?tag_codes=' + encodeURIComponent(group.codes.join(',')) + '&select_tag_details=1&from_split=1';
            if (branchId) url += '&branch_id=' + encodeURIComponent(branchId);
            if (group.cusId) {
                url += '&cus_id=' + encodeURIComponent(group.cusId);
                url += '&cus_name=' + encodeURIComponent(group.cusName);
            }

            // Create hidden <a> and trigger click — bypasses popup blocker
            var link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            groupCount++;
        });

        if (groupCount > 1) {
            $.toaster({ priority: 'success', title: 'Estimation', message: groupCount + ' estimation tabs opened (one per customer)' });
        } else if (groupCount === 1) {
            $.toaster({ priority: 'success', title: 'Estimation', message: 'Estimation tab opened' });
        }
    }

    // New partly sale button
    $(document).on('click', '#btn_new_parts', function () {
        $('.ts-parts-success').remove();
        $('#child_weight_tbody, .totals-row, .validation-row').show();
        resetForm();
        $('#btn_save_parts').show().prop('disabled', false).html('<i class="fa fa-check-circle"></i> Save');
    });

    // =========================================================================
    // Cancel / Reset
    // =========================================================================

    $('#btn_cancel_parts').click(function () {
        window.location.href = baseUrl + 'index.php/admin_ret_tag_parts/list_view';
    });

    $('#btn_reset_tag').click(function () {
        resetForm();
    });

    function resetForm() {
        baseTagData = null;
        childrenData = [];
        costLimit = 200000;
        goldRate = 0;
        wastagePct = 0;
        mcType = 0;
        mcValue = 0;
        calcBased = 0;
        baseCost = 0;
        taxPct = 0;
        savedPartsId = null;
        $('#tag_scan_code').val('');
        $('#base_tag_id').val('');
        $('#base_tag_code').val('');
        $('#parts_count').attr('min', 2).val(2).prop('readonly', false);
        $('#step_base_info, #step_actions, #balance_banner').hide();
        $('#tag_details_placeholder').show();
        $('#child_weight_tbody').html('');
        $('.ts-parts-success').remove();
        hideScanError();
        $('#tag_scan_code').focus();
    }

    // =========================================================================
    // Helper: Error display
    // =========================================================================

    function showScanError(msg) {
        $('#tag_scan_error_text').text(msg);
        $('#tag_scan_error').show();
    }

    function hideScanError() {
        $('#tag_scan_error').hide();
        $('#tag_scan_error_text').text('');
    }

    // =========================================================================
    // Phase 4: Cost Legend per row (✓/✗)
    // =========================================================================

    function computeCostForGwt(gwt) {
        if (!baseTagData) return 0;
        var baseGwt = parseFloat(baseTagData.gross_wt) || 0;
        if (baseGwt <= 0) return 0;

        // Prefer sell_rate (proportional)
        if (baseCost > 0) {
            var preTax = baseCost * (gwt / baseGwt);
            return preTax * (1 + (taxPct / 100));
        }

        // Fallback: dynamic calc using gold rate + wastage + MC + Stone/Dia amounts
        if (goldRate <= 0) return 0;
        var baseNwt = parseFloat(baseTagData.net_wt) || 0;
        var ratio = gwt / baseGwt;
        var nwt = baseNwt * ratio;

        var wastBasis = (calcBased == 0) ? gwt : nwt;
        var wastWt = wastBasis * (wastagePct / 100);
        var wastAmt = wastWt * goldRate;

        var mcAmt = 0;
        if (mcType == 1) mcAmt = mcValue * ratio;
        else if (mcType == 2) mcAmt = mcValue * ((calcBased == 1) ? nwt : gwt);
        else if (mcType == 3) mcAmt = (goldRate * (nwt + wastWt)) * (mcValue / 100);

        // Stone + Dia monetary amounts (proportional to GWT ratio)
        var stoneAmt = (parseFloat(baseTagData.stone_amount) || 0) * ratio;
        var diaAmt = (parseFloat(baseTagData.dia_amount) || 0) * ratio;

        var preTaxTotal = (goldRate * nwt) + wastAmt + mcAmt + stoneAmt + diaAmt;
        return preTaxTotal * (1 + (taxPct / 100));
    }

    function computeLegend(child) {
        var gwt = parseFloat(child.gwt) || 0;
        var cost = computeCostForGwt(gwt);
        if (cost <= 0) return '<i class="fa fa-minus" style="color:#ccc;"></i>';
        if (cost <= costLimit) {
            return '<i class="fa fa-check" style="color:#16a34a;" title="₹' + Math.round(cost).toLocaleString() + ' incl. tax - within limit"></i>';
        } else {
            return '<i class="fa fa-times" style="color:#dc2626;" title="₹' + Math.round(cost).toLocaleString() + ' incl. tax - exceeds limit"></i>';
        }
    }

    function updateAllLegends() {
        $('#child_weight_tbody tr').each(function () {
            var child = {
                gwt: parseFloat($(this).find('.child-gwt').val()) || 0,
                nwt: parseFloat($(this).find('.child-nwt').val()) || 0
            };
            $(this).find('.col-legend').html(computeLegend(child));
        });
    }

    // =========================================================================
    // Dynamic Row Creation: On GWT edit, auto-add balance row
    // =========================================================================

    function bindWeightInputs() {
        // GWT edit: proportional recalculate all sub-weights
        $('#child_weight_tbody input.child-gwt').off('input').on('input', function () {
            handleGwtEdit($(this));
        });
        // Column cascade: NWT, Stone, Dia are editable — Less and GWT are computed
        $('#child_weight_tbody input.child-nwt, #child_weight_tbody input.child-stone, #child_weight_tbody input.child-dia').off('input.cap').on('input.cap', function () {
            cascadeColumnEdit($(this));
        });
        $('#child_weight_tbody input').off('input.validate').on('input.validate', function () {
            validatePartsWeights();
        });
        // Round-off: format to 3 decimal places on blur
        $('#child_weight_tbody input.child-gwt, #child_weight_tbody input.child-nwt, #child_weight_tbody input.child-less, #child_weight_tbody input.child-stone, #child_weight_tbody input.child-dia').off('blur.format').on('blur.format', function () {
            var v = parseFloat($(this).val()) || 0;
            $(this).val(v.toFixed(3));
        });
        // Make Less readonly — it is computed from formula: Less = Stone + (Dia_ct × 0.2)
        // GWT stays editable — handleGwtEdit proportionally recalculates all sub-weights
        $('#child_weight_tbody input.child-less').attr('readonly', true);
    }

    /**
     * Row + Column cascade engine.
     *
     * Core formula (jewelry weight system):
     *   Dia_grams = Dia_carats × 0.2  (1 carat = 0.2 grams)
     *   Less Wt   = Stone Wt + Dia_grams  (computed — readonly)
     *   Gross Wt  = Net Wt + Less Wt      (computed — readonly)
     *
     * Editable fields: NWT, Stone Wt, Diamond Wt (carats)
     * Computed fields: Less Wt, Gross Wt
     *
     * Cascade flow:
     *   Edit NWT/Stone/Dia → column cascade (last row absorbs for edited column)
     *                      → recompute Less & GWT for ALL rows
     */
    var CARAT_TO_GRAM = 0.2; // 1 carat = 0.2 grams (international standard)

    /**
     * Reconcile the last row to absorb rounding differences.
     * Ensures Part Total == Base for ALL weight columns.
     *
     * Strategy: adjust last row's Less (= baseLess - sumOtherLess),
     * then NWT = GWT - adjustedLess. This guarantees:
     *   - GWT total = baseGwt  (already correct from cascade/balance logic)
     *   - Less total = baseLess
     *   - NWT total = baseGwt - baseLess = baseNwt
     *   - Per-row GWT = NWT + Less
     */
    function reconcileLastRow() {
        if (!baseTagData) return;
        var baseGwt = parseFloat(baseTagData.gross_wt) || 0;
        var baseNwt = parseFloat(baseTagData.net_wt) || 0;
        var baseLess = parseFloat(baseTagData.less_wt) || 0;
        var baseStone = parseFloat(baseTagData.stone_wt) || 0;
        var baseDia = parseFloat(baseTagData.dia_wt) || 0;

        var $allRows = $('#child_weight_tbody tr');
        if ($allRows.length < 2) return;

        var $lastRow = $allRows.last();
        var sumGwt = 0, sumNwt = 0, sumLess = 0, sumStone = 0, sumDia = 0;

        // Sum all rows EXCEPT the last
        $allRows.each(function () {
            if (this !== $lastRow[0]) {
                sumGwt += parseFloat($(this).find('.child-gwt').val()) || 0;
                sumNwt += parseFloat($(this).find('.child-nwt').val()) || 0;
                sumLess += parseFloat($(this).find('.child-less').val()) || 0;
                sumStone += parseFloat($(this).find('.child-stone').val()) || 0;
                sumDia += parseFloat($(this).find('.child-dia').val()) || 0;
            }
        });

        // Last row absorbs ALL remainders
        var lastGwt = Math.round((baseGwt - sumGwt) * 1000) / 1000;
        var lastStone = Math.round((baseStone - sumStone) * 1000) / 1000;
        var lastDia = Math.round((baseDia - sumDia) * 1000) / 1000;
        var lastLess = Math.round((baseLess - sumLess) * 1000) / 1000;
        var lastNwt = Math.round((lastGwt - lastLess) * 1000) / 1000;

        // Safety floors
        if (lastStone < 0) lastStone = 0;
        if (lastDia < 0) lastDia = 0;
        if (lastLess < 0) lastLess = 0;
        if (lastNwt < 0) lastNwt = 0;

        $lastRow.find('.child-gwt').val(lastGwt.toFixed(3));
        $lastRow.find('.child-nwt').val(lastNwt.toFixed(3));
        $lastRow.find('.child-less').val(lastLess.toFixed(3));
        $lastRow.find('.child-stone').val(lastStone.toFixed(3));
        $lastRow.find('.child-dia').val(lastDia.toFixed(3));
    }

    function cascadeColumnEdit($input) {
        if (!baseTagData) return;

        var currentVal = parseFloat($input.val()) || 0;
        if (currentVal < 0) { $input.val('0.000'); currentVal = 0; }

        var $row = $input.closest('tr');
        var $allRows = $('#child_weight_tbody tr');
        var $lastRow = $allRows.last();
        var isLastRow = ($row[0] === $lastRow[0]);

        var isNwt = $input.hasClass('child-nwt');
        var isStone = $input.hasClass('child-stone');
        var isDia = $input.hasClass('child-dia');

        // Less is computed — ignore direct edits
        if ($input.hasClass('child-less') || $input.hasClass('child-gwt')) return;

        // Determine which column was edited
        var colClass, baseCol;
        if (isNwt) { colClass = 'child-nwt'; baseCol = parseFloat(baseTagData.net_wt) || 0; }
        if (isStone) { colClass = 'child-stone'; baseCol = parseFloat(baseTagData.stone_wt) || 0; }
        if (isDia) { colClass = 'child-dia'; baseCol = parseFloat(baseTagData.dia_wt) || 0; }

        if (!colClass) return;

        // ===== Column cascade =====
        var $allColInputs = $('#child_weight_tbody .' + colClass);
        var $lastColInput = $allColInputs.last();

        if (isStone || isDia) {
            // --- Stone/Dia cascade: keep rows BEFORE, zero rows AFTER, last row gets remainder ---
            var editedRowIdx = $row.index();
            var colName = isStone ? 'Stone' : 'Diamond';

            // Sum values of rows BEFORE the edited row (these are preserved)
            var sumBefore = 0;
            $allColInputs.each(function () {
                var rowIdx = $(this).closest('tr').index();
                if (rowIdx < editedRowIdx) {
                    sumBefore += parseFloat($(this).val()) || 0;
                }
            });

            // Cap: edited value cannot exceed remaining budget
            var maxForThis = Math.round((baseCol - sumBefore) * 1000) / 1000;
            if (maxForThis < 0) maxForThis = 0;
            if (currentVal > maxForThis) {
                currentVal = maxForThis;
                $input.val(maxForThis.toFixed(3));
                $.toaster({ priority: 'warning', title: 'Weight Limit', message: colName + ' Wt capped — remaining budget is ' + maxForThis.toFixed(3) });
            }

            // Zero out rows AFTER the edited row (except last row which gets remainder)
            $allColInputs.each(function () {
                var rowIdx = $(this).closest('tr').index();
                if (rowIdx > editedRowIdx && this !== $lastColInput[0]) {
                    $(this).val('0.000');
                }
            });

            // Last row gets the remainder: baseCol - sumBefore - editedVal
            var remainder = Math.round((baseCol - sumBefore - currentVal) * 1000) / 1000;
            if (remainder < 0) remainder = 0;
            $lastColInput.val(remainder.toFixed(3));

            // Edge case: if edited row IS the last row, just cap to remaining budget
            if (isLastRow) {
                $lastColInput.val(currentVal.toFixed(3));
            }

        } else if (isNwt) {
            // --- NWT cascade: last row absorbs, middle rows stay ---
            if (!isLastRow) {
                var sumExceptLast = 0;
                $allColInputs.each(function () {
                    if (this !== $lastColInput[0]) sumExceptLast += parseFloat($(this).val()) || 0;
                });
                var lastVal = Math.round((baseCol - sumExceptLast) * 1000) / 1000;

                if (lastVal < 0) {
                    lastVal = 0;
                    var maxForThis = Math.round((baseCol - (sumExceptLast - currentVal)) * 1000) / 1000;
                    if (maxForThis < 0) maxForThis = 0;
                    currentVal = maxForThis;
                    $input.val(maxForThis.toFixed(3));
                    $.toaster({ priority: 'warning', title: 'Weight Limit', message: 'Net Wt capped — total cannot exceed base (' + baseCol.toFixed(3) + ')' });
                }
                $lastColInput.val(lastVal.toFixed(3));

            } else {
                var otherSum = 0;
                $allColInputs.each(function () {
                    if (this !== $lastColInput[0]) otherSum += parseFloat($(this).val()) || 0;
                });
                var maxAllowed = Math.round((baseCol - otherSum) * 1000) / 1000;
                if (maxAllowed < 0) maxAllowed = 0;
                if (currentVal > maxAllowed) {
                    currentVal = maxAllowed;
                    $input.val(maxAllowed.toFixed(3));
                    $.toaster({ priority: 'warning', title: 'Weight Limit', message: 'Net Wt capped to ' + maxAllowed.toFixed(3) });
                }
            }
        }

        // ===== Recompute Less and GWT for ALL rows from formula =====
        // Less = Stone + (Dia_carats × 0.2)
        // GWT  = NWT + Less
        $allRows.each(function () {
            var $r = $(this);
            var stone = parseFloat($r.find('.child-stone').val()) || 0;
            var diaCt = parseFloat($r.find('.child-dia').val()) || 0;
            var diaGm = Math.round(diaCt * CARAT_TO_GRAM * 1000) / 1000;
            var less = Math.round((stone + diaGm) * 1000) / 1000;
            var nwt = parseFloat($r.find('.child-nwt').val()) || 0;
            var gwt = Math.round((nwt + less) * 1000) / 1000;

            $r.find('.child-less').val(less.toFixed(3));
            $r.find('.child-gwt').val(gwt.toFixed(3));
        });

        // Sync internal data and re-validate after cascade
        reconcileLastRow();
        validatePartsWeights();
    }

    function handleGwtEdit($input) {
        if (!baseTagData) return;
        var baseGwt = parseFloat(baseTagData.gross_wt) || 0;
        var baseNwt = parseFloat(baseTagData.net_wt) || 0;
        var baseLess = parseFloat(baseTagData.less_wt) || 0;
        var baseStone = parseFloat(baseTagData.stone_wt) || 0;
        var baseDia = parseFloat(baseTagData.dia_wt) || 0;
        if (baseGwt <= 0) return;

        var editedIdx = parseInt($input.data('index'));
        var editedGwt = parseFloat($input.val()) || 0;

        // Recalc the edited row's editable weights
        var editedRatio = editedGwt / baseGwt;
        var $editedRow = $input.closest('tr');

        // Stone/Dia: use REMAINING budget (what rows before haven't consumed)
        var usedStoneBefore = 0, usedDiaBefore = 0;
        $('#child_weight_tbody tr').each(function () {
            if (parseInt($(this).data('index')) < editedIdx) {
                usedStoneBefore += parseFloat($(this).find('.child-stone').val()) || 0;
                usedDiaBefore += parseFloat($(this).find('.child-dia').val()) || 0;
            }
        });
        var remainStone = Math.round((baseStone - usedStoneBefore) * 1000) / 1000;
        var remainDia = Math.round((baseDia - usedDiaBefore) * 1000) / 1000;
        if (remainStone < 0) remainStone = 0;
        if (remainDia < 0) remainDia = 0;

        // Cap Stone/Dia to remaining budget (proportional within remaining)
        var newStone = Math.min(Math.round(baseStone * editedRatio * 1000) / 1000, remainStone);
        var newDiaCt = Math.min(Math.round(baseDia * editedRatio * 1000) / 1000, remainDia);
        if (newStone < 0) newStone = 0;
        if (newDiaCt < 0) newDiaCt = 0;

        // Compute Less from formula: Less = Stone + (Dia_ct × 0.2)
        var newDiaGm = Math.round(newDiaCt * CARAT_TO_GRAM * 1000) / 1000;
        var newLess = Math.round((newStone + newDiaGm) * 1000) / 1000;

        // NWT derived from formula: NWT = GWT - Less (ensures GWT = NWT + Less always)
        var newNwt = Math.round((editedGwt - newLess) * 1000) / 1000;
        if (newNwt < 0) newNwt = 0;

        $editedRow.find('.child-nwt').val(newNwt.toFixed(3));
        $editedRow.find('.child-less').val(newLess.toFixed(3));
        $editedRow.find('.child-stone').val(newStone.toFixed(3));
        $editedRow.find('.child-dia').val(newDiaCt.toFixed(3));

        // Remove all rows AFTER the edited row
        $('#child_weight_tbody tr').each(function () {
            if (parseInt($(this).data('index')) > editedIdx) {
                $(this).remove();
            }
        });

        // Calculate balance from all remaining rows (all columns, not just GWT)
        var usedGwt = 0, usedNwt = 0, usedLess = 0, usedStone = 0, usedDia = 0;
        $('#child_weight_tbody tr').each(function () {
            usedGwt += parseFloat($(this).find('.child-gwt').val()) || 0;
            usedNwt += parseFloat($(this).find('.child-nwt').val()) || 0;
            usedLess += parseFloat($(this).find('.child-less').val()) || 0;
            usedStone += parseFloat($(this).find('.child-stone').val()) || 0;
            usedDia += parseFloat($(this).find('.child-dia').val()) || 0;
        });
        var balance = Math.round((baseGwt - usedGwt) * 1000) / 1000;
        var balNwt = Math.round((baseNwt - usedNwt) * 1000) / 1000;
        var balLess = Math.round((baseLess - usedLess) * 1000) / 1000;
        var balStone = Math.round((baseStone - usedStone) * 1000) / 1000;
        var balDia = Math.round((baseDia - usedDia) * 1000) / 1000;
        // Floor to 0 — never allow negative Stone/Dia in balance rows
        if (balStone < 0) balStone = 0;
        if (balDia < 0) balDia = 0;
        if (balNwt < 0) balNwt = 0;
        if (balLess < 0) balLess = 0;

        // Auto-split balance into rows respecting cost limit
        if (balance > 0.001) {
            var maxGwtPerPart = balance; // default: all in one row

            // Determine max GWT per part based on cost limit
            if (goldRate > 0 && costLimit > 0) {
                var costPerGram = computeCostForGwt(baseGwt) / baseGwt;
                if (costPerGram > 0) {
                    maxGwtPerPart = Math.floor((costLimit / costPerGram) * 1000) / 1000;
                    if (maxGwtPerPart <= 0) maxGwtPerPart = balance;
                }
            }

            // Determine how many new parts needed, then distribute evenly with stagger
            var numNewParts = Math.ceil(balance / maxGwtPerPart);
            if (numNewParts < 1) numNewParts = 1;
            var perPartGwt = Math.floor((balance / numNewParts) * 1000) / 1000;
            var usedBalance = 0, usedBalNwt = 0, usedBalLess = 0, usedBalStone = 0, usedBalDia = 0;

            for (var p = 1; p <= numNewParts; p++) {
                var partGwt;
                if (p === numNewParts) {
                    // Last part absorbs remainder
                    partGwt = Math.round((balance - usedBalance) * 1000) / 1000;
                } else {
                    // Stagger each row by -0.01g so no two rows are identical
                    partGwt = Math.round((perPartGwt - ((p - 1) * 0.01)) * 1000) / 1000;
                    if (partGwt < 0.001) partGwt = perPartGwt; // safety floor
                }

                var newIdx = $('#child_weight_tbody tr').length;
                var newChild;
                if (p === numNewParts) {
                    // Last new row absorbs exact remainder
                    var lastPartGwt = Math.round((balance - usedBalance) * 1000) / 1000;
                    var lastStone = Math.round((balStone - usedBalStone) * 1000) / 1000;
                    var lastDiaCt = Math.round((balDia - usedBalDia) * 1000) / 1000;
                    // Floor to 0 — never negative
                    if (lastStone < 0) lastStone = 0;
                    if (lastDiaCt < 0) lastDiaCt = 0;
                    // Compute Less from formula
                    var lastDiaGm = Math.round(lastDiaCt * CARAT_TO_GRAM * 1000) / 1000;
                    var lastLess = Math.round((lastStone + lastDiaGm) * 1000) / 1000;
                    // NWT = GWT - Less (formula-derived)
                    var lastNwt = Math.round((lastPartGwt - lastLess) * 1000) / 1000;
                    if (lastNwt < 0) lastNwt = 0;
                    newChild = {
                        child_order: newIdx + 1,
                        tag_code: baseTagData.tag_code + '-' + (newIdx + 1),
                        gwt: lastPartGwt,
                        nwt: lastNwt,
                        less_wt: lastLess,
                        stone_wt: lastStone,
                        dia_wt: lastDiaCt
                    };
                } else {
                    // Proportional split of the BALANCE
                    var balRatio = (balance > 0) ? (partGwt / balance) : 0;
                    var propStone = Math.round(balStone * balRatio * 1000) / 1000;
                    var propDiaCt = Math.round(balDia * balRatio * 1000) / 1000;
                    // Floor to 0 — never negative
                    if (propStone < 0) propStone = 0;
                    if (propDiaCt < 0) propDiaCt = 0;
                    // Compute Less from formula
                    var propDiaGm = Math.round(propDiaCt * CARAT_TO_GRAM * 1000) / 1000;
                    var propLess = Math.round((propStone + propDiaGm) * 1000) / 1000;
                    // NWT = GWT - Less (formula-derived)
                    var propNwt = Math.round((partGwt - propLess) * 1000) / 1000;
                    if (propNwt < 0) propNwt = 0;
                    newChild = {
                        child_order: newIdx + 1,
                        tag_code: baseTagData.tag_code + '-' + (newIdx + 1),
                        gwt: partGwt,
                        nwt: propNwt,
                        less_wt: propLess,
                        stone_wt: propStone,
                        dia_wt: propDiaCt
                    };
                }

                var legendHtml = '<td class="col-legend">' + computeLegend(newChild) + '</td>';
                var row = '<tr data-index="' + newIdx + '">' +
                    '<td>' + newChild.child_order + '</td>' +
                    '<td><span class="child-code">' + newChild.tag_code + '</span></td>' +
                    '<td class="col-gwt"><input type="number" step="0.001" class="child-gwt" data-index="' + newIdx + '" value="' + newChild.gwt.toFixed(3) + '"/></td>' +
                    '<td class="col-nwt"><input type="number" step="0.001" class="child-nwt" data-index="' + newIdx + '" value="' + newChild.nwt.toFixed(3) + '"/></td>' +
                    '<td class="col-less"><input type="number" step="0.001" class="child-less" data-index="' + newIdx + '" value="' + newChild.less_wt.toFixed(3) + '"/></td>' +
                    '<td class="col-stone"><input type="number" step="0.001" class="child-stone" data-index="' + newIdx + '" value="' + newChild.stone_wt.toFixed(3) + '"/></td>' +
                    '<td class="col-dia"><input type="number" step="0.001" class="child-dia" data-index="' + newIdx + '" value="' + newChild.dia_wt.toFixed(3) + '"/></td>' +
                    legendHtml +
                    '</tr>';

                $('#child_weight_tbody').append(row);
                usedBalance += partGwt;
                usedBalNwt += newChild.nwt;
                usedBalLess += newChild.less_wt;
                usedBalStone += newChild.stone_wt;
                usedBalDia += newChild.dia_wt;
            }

            // Re-bind inputs for new rows
            bindWeightInputs();

            // Apply zero-column rules to dynamically added rows (Bug 2 fix)
            hideZeroColumns({
                less_wt: baseLess,
                stone_wt: baseStone,
                dia_wt: baseDia
            });

            // Update parts count display
            $('#parts_count').val($('#child_weight_tbody tr').length);
        }

        // Auto-remove last row if GWT is 0 (merge balance into previous row)
        var $allCurrentRows = $('#child_weight_tbody tr');
        if ($allCurrentRows.length > 1) {
            var $lastR = $allCurrentRows.last();
            var lastGwt = parseFloat($lastR.find('.child-gwt').val()) || 0;
            if (lastGwt < 0.001) {
                $lastR.remove();
                // Recalc previous row to absorb the remainder
                $allCurrentRows = $('#child_weight_tbody tr');
                if ($allCurrentRows.length > 0) {
                    var $prevRow = $allCurrentRows.last();
                    // Recompute: sum all rows except last, last gets remainder
                    var sumGwtOthers = 0;
                    $allCurrentRows.each(function () {
                        if (this !== $prevRow[0]) sumGwtOthers += parseFloat($(this).find('.child-gwt').val()) || 0;
                    });
                    var prevGwt = Math.round((baseGwt - sumGwtOthers) * 1000) / 1000;
                    // Recompute Stone/Dia for last row from remaining budget
                    var usedS = 0, usedD = 0;
                    $allCurrentRows.each(function () {
                        if (this !== $prevRow[0]) {
                            usedS += parseFloat($(this).find('.child-stone').val()) || 0;
                            usedD += parseFloat($(this).find('.child-dia').val()) || 0;
                        }
                    });
                    var prevStone = Math.round((baseStone - usedS) * 1000) / 1000;
                    var prevDia = Math.round((baseDia - usedD) * 1000) / 1000;
                    if (prevStone < 0) prevStone = 0;
                    if (prevDia < 0) prevDia = 0;
                    var prevDiaGm = Math.round(prevDia * CARAT_TO_GRAM * 1000) / 1000;
                    var prevLess = Math.round((prevStone + prevDiaGm) * 1000) / 1000;
                    var prevNwt = Math.round((prevGwt - prevLess) * 1000) / 1000;
                    if (prevNwt < 0) prevNwt = 0;

                    $prevRow.find('.child-gwt').val(prevGwt.toFixed(3));
                    $prevRow.find('.child-nwt').val(prevNwt.toFixed(3));
                    $prevRow.find('.child-less').val(prevLess.toFixed(3));
                    $prevRow.find('.child-stone').val(prevStone.toFixed(3));
                    $prevRow.find('.child-dia').val(prevDia.toFixed(3));
                }
                bindWeightInputs();
                $('#parts_count').val($('#child_weight_tbody tr').length);
            }
        }

        // Reconcile last row to absorb rounding drift
        reconcileLastRow();

        // Update children data
        updateChildrenFromInputs();
        validatePartsWeights();
        updateAllLegends();
    }

});

// =============================================================================
// LIST VIEW — Partly Sale History & EOD Queue
// Runs only when list page elements are present
// =============================================================================

$(document).ready(function () {

    // Guard: only run on list view
    if ($('#parts_history_table').length === 0) return;

    var baseUrl = $('#base_url').val();
    var canModify = ($('#access_edit').val() == '1');
    var canRevert = ($('#access_delete').val() == '1');

    // Initialize daterangepicker
    $('#dt_range').daterangepicker({
        format: 'DD-MM-YYYY',
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        },
        startDate: moment().subtract(0, 'days'),
        endDate: moment()
    }, function (start, end) {
        $('#dt_range').val(start.format('DD-MM-YYYY') + ' - ' + end.format('DD-MM-YYYY'));
    });
    // Set default value to today so first load filters by today's date
    $('#dt_range').val(moment().format('DD-MM-YYYY') + ' - ' + moment().format('DD-MM-YYYY'));

    // Load data
    loadPartsHistory();
    loadEodQueue();

    var historyTable = null;

    function loadPartsHistory() {
        var fromDate = '';
        var toDate = '';
        var dtRange = $('#dt_range').val();
        if (dtRange && dtRange.indexOf(' - ') > -1) {
            var parts = dtRange.split(' - ');
            fromDate = parts[0].trim();
            toDate = parts[1].trim();
        }
        var params = {
            status: $('#filter_status').val(),
            from_date: fromDate,
            to_date: toDate
        };

        $.get(baseUrl + 'index.php/admin_ret_tag_parts/get_parts_history', params, function (response) {
            try {
                var data = JSON.parse(response);

                // Build row DOM nodes (not HTML string) for DataTables API
                var $rows = $();
                if (data.data && data.data.length > 0) {
                    $.each(data.data, function (i, row) {
                        var statusClass = 'ts-badge-active';
                        if (row.status == 2) statusClass = 'ts-badge-reverted';
                        else if (row.status == 3) statusClass = 'ts-badge-partial';
                        else if (row.status == 4) statusClass = 'ts-badge-billed';

                        var actions = '<button class="btn btn-info btn-xs ts-action-btn" onclick="viewPartsDetail(' + row.parts_id + ')" title="View"><i class="fa fa-eye"></i></button>';

                        // Show Modify/Revert ONLY if active AND no children billed
                        var noBilled = (parseInt(row.unbilled_count) == parseInt(row.parts_count));
                        if (canModify && row.status == 1 && noBilled) {
                            actions += ' <button class="btn btn-warning btn-xs ts-action-btn" onclick="goToRevert(\'' + row.base_tag_code + '\')" title="Modify"><i class="fa fa-pencil"></i></button>';
                        }
                        if (canRevert && row.status == 1 && noBilled) {
                            actions += ' <button class="btn btn-danger btn-xs ts-action-btn" onclick="confirmRevert(' + row.parts_id + ', \'' + row.base_tag_code + '\')" title="Revert"><i class="fa fa-reply"></i></button>';
                        }

                        var trHtml = '<tr>' +
                            '<td>' + (i + 1) + '</td>' +
                            '<td>' + row.base_tag_code + '</td>' +
                            '<td>' + row.parts_count + '</td>' +
                            '<td>' + parseFloat(row.base_gwt).toFixed(3) + '</td>' +
                            '<td>' + parseFloat(row.base_nwt).toFixed(3) + '</td>' +
                            '<td><span class="ts-badge ' + statusClass + '">' + row.status_label + '</span></td>' +
                            '<td>' + row.unbilled_count + '</td>' +
                            '<td>' + (row.created_by_name || '-') + '</td>' +
                            '<td>' + row.created_on + '</td>' +
                            '<td>' + actions + '</td>' +
                            '</tr>';
                        $rows = $rows.add($(trHtml));
                    });
                }

                if (!historyTable) {
                    // First load — populate tbody then initialize DataTable once
                    $('#parts_history_table tbody').empty().append($rows);
                    historyTable = $('#parts_history_table').DataTable({
                        order: [[8, 'desc']],
                        pageLength: 25,
                        language: { emptyTable: 'No partly sale records found' }
                    });
                } else {
                    // Subsequent loads — use API, never destroy/reinit
                    historyTable.clear();
                    if ($rows.length > 0) {
                        historyTable.rows.add($rows).draw();
                    } else {
                        historyTable.draw();
                    }
                }
            } catch (e) {
                $.toaster({ priority: 'danger', title: 'Server Error', message: 'Failed to load parts history' });
                console.error('loadPartsHistory parse error:', e, response);
            }
        });
    }

    function loadEodQueue() {
        $.get(baseUrl + 'index.php/admin_ret_tag_parts/get_eod_queue', function (response) {
            try {
                var data = JSON.parse(response);
                var tbody = '';
                var count = 0;
                if (data.data && data.data.length > 0) {
                    count = data.data.length;
                    $.each(data.data, function (i, row) {
                        var action = '<span class="text-muted"><i class="fa fa-clock-o"></i> Auto at Day Close</span>';
                        if (canRevert && parseInt(row.unbilled_count) == parseInt(row.total_children)) {
                            action = '<button class="btn btn-danger btn-xs ts-action-btn" onclick="manualRevert(' + row.parts_id + ')"><i class="fa fa-undo"></i> Manual Revert</button>';
                        }
                        tbody += '<tr>' +
                            '<td>' + row.base_tag_code + '</td>' +
                            '<td>' + row.total_children + '</td>' +
                            '<td>' + row.unbilled_count + '</td>' +
                            '<td>' + parseFloat(row.base_gwt).toFixed(3) + '</td>' +
                            '<td>' + action + '</td>' +
                            '</tr>';
                    });
                } else {
                    tbody = '<tr><td colspan="5" class="text-muted">No active parts pending revert</td></tr>';
                }
                $('#eod_queue_tbody').html(tbody);
                $('#eod_queue_count').text(count + ' active' + (count != 1 ? ' parts' : ' part'));
            } catch (e) {
                $.toaster({ priority: 'danger', title: 'Server Error', message: 'Failed to load EOD queue' });
                console.error('loadEodQueue parse error:', e, response);
            }
        });
    }

    // Filter buttons
    $('#btn_filter_history').click(function () { loadPartsHistory(); });
    $('#btn_reset_filter').click(function () {
        $('#filter_status').val('');
        $('#dt_range').val('');
        loadPartsHistory();
    });

    // Redirect to form page with tag code for modify
    window.goToRevert = function (tagCode) {
        window.location.href = baseUrl + 'index.php/admin_ret_tag_parts/form?tag=' + encodeURIComponent(tagCode) + '&mode=edit';
    };

    // Revert confirmation popup
    window.confirmRevert = function (partsId, tagCode) {
        // Remove existing modal if any
        $('#revertConfirmModal').remove();

        var modalHtml = '<div class="modal fade" id="revertConfirmModal" tabindex="-1" role="dialog">' +
            '<div class="modal-dialog modal-sm" role="document">' +
            '<div class="modal-content">' +
            '<div class="modal-header" style="background:#d9534f;color:#fff;padding:12px 15px;">' +
            '<button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;"><span>&times;</span></button>' +
            '<h4 class="modal-title"><i class="fa fa-exclamation-triangle"></i> Confirm Revert</h4>' +
            '</div>' +
            '<div class="modal-body" style="text-align:center;padding:20px;">' +
            '<p style="font-size:14px;margin-bottom:5px;">Are you sure you want to revert<br><b>' + tagCode + '</b>?</p>' +
            '<p style="font-size:12px;color:#999;">All unbilled part tags will be deleted<br>and the base tag will be restored.</p>' +
            '</div>' +
            '<div class="modal-footer" style="text-align:center;">' +
            '<button type="button" class="btn btn-default btn-sm" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>' +
            '<button type="button" class="btn btn-danger btn-sm" id="btnConfirmRevert"><i class="fa fa-check"></i> Accept</button>' +
            '</div>' +
            '</div></div></div>';

        $('body').append(modalHtml);
        $('#revertConfirmModal').modal('show');

        $('#btnConfirmRevert').off('click').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Reverting...');

            $.post(baseUrl + 'index.php/admin_ret_tag_parts/manual_revert', { parts_id: partsId }, function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status) {
                        $.toaster({ priority: 'success', title: 'Reverted!', message: tagCode + ' reverted successfully' });
                        $('#revertConfirmModal').modal('hide');
                        loadPartsHistory();
                        loadEodQueue();
                    } else {
                        $.toaster({ priority: 'danger', title: 'Revert Failed', message: data.message || 'Revert operation failed' });
                        $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Accept');
                    }
                } catch (e) {
                    $.toaster({ priority: 'danger', title: 'Server Error', message: 'Unexpected server response' });
                    console.error('revert parse error:', e, response);
                    $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Accept');
                }
            });
        });

        // Cleanup on close
        $('#revertConfirmModal').on('hidden.bs.modal', function () {
            $(this).remove();
        });
    };

    // View detail modal
    window.viewPartsDetail = function (partsId) {
        $.get(baseUrl + 'index.php/admin_ret_tag_parts/get_parts_detail/' + partsId, function (response) {
            try {
                var data = JSON.parse(response);
                if (data.status) {
                    var d = data.data;

                    // Status badge
                    var statusMap = { 1: 'Active', 2: 'Reverted', 3: 'Partial', 4: 'Billed' };
                    var badgeMap = { 1: 'ts-badge-active', 2: 'ts-badge-reverted', 3: 'ts-badge-partial', 4: 'ts-badge-billed' };
                    var statusLabel = statusMap[d.master.status] || d.master.status;
                    var badgeClass = badgeMap[d.master.status] || 'ts-badge-active';

                    // Base tag info card
                    var html = '<div style="background:#f0f8ff; border:1px solid #d0e3f0; border-radius:6px; padding:14px 18px; margin-bottom:16px;">';
                    html += '<div style="display:flex; flex-wrap:wrap; gap:20px; align-items:center;">';
                    html += '<span><b style="color:#5a6a85;">Status:</b> <span class="ts-badge ' + badgeClass + '">' + statusLabel + '</span></span>';
                    html += '<span><b style="color:#5a6a85;">GWT:</b> <b>' + parseFloat(d.master.base_gwt).toFixed(3) + '</b></span>';
                    html += '<span><b style="color:#5a6a85;">NWT:</b> <b>' + parseFloat(d.master.base_nwt).toFixed(3) + '</b></span>';
                    html += '<span><b style="color:#5a6a85;">Stone:</b> <b>' + parseFloat(d.master.base_stone_wt).toFixed(3) + '</b></span>';
                    html += '<span><b style="color:#5a6a85;">Dia:</b> <b>' + parseFloat(d.master.base_dia_wt).toFixed(3) + '</b></span>';
                    html += '<span><b style="color:#5a6a85;">Less:</b> <b>' + parseFloat(d.master.base_less_wt).toFixed(3) + '</b></span>';
                    html += '<span><b style="color:#5a6a85;">Created:</b> <b>' + d.master.created_on + '</b></span>';
                    html += '</div></div>';

                    // Batch selection header for detail modal
                    html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;padding:4px 8px;">';
                    html += '<label style="margin:0;font-size:12px;cursor:pointer;"><input type="checkbox" class="ts-modal-select-all" style="margin-right:5px;" /> Select All</label>';
                    html += '<button class="btn btn-info btn-xs ts-modal-batch-estimation-btn" disabled style="padding:3px 12px;font-weight:600;" data-branch="' + (d.master.id_branch || '') + '"><i class="fa fa-calculator"></i> Add Selected to Estimation</button>';
                    html += '</div>';

                    // Children table with premium styling
                    html += '<table class="table text-center" style="margin:0; border-collapse:collapse;">';
                    html += '<thead><tr style="background:linear-gradient(135deg,#1e3c72,#3c8dbc); color:#fff;">';
                    html += '<th style="padding:10px;border:none;width:30px;"><input type="checkbox" class="ts-modal-select-all-thead" style="display:none;" /></th><th style="padding:10px;border:none;">#</th><th style="padding:10px;border:none;">Tag Code</th><th style="padding:10px;border:none;">GWT</th><th style="padding:10px;border:none;">NWT</th><th style="padding:10px;border:none;">Less Wt</th><th style="padding:10px;border:none;">Stone Wt</th><th style="padding:10px;border:none;">Dia Wt</th><th style="padding:10px;border:none;">Status</th><th style="padding:10px;border:none;">Billed</th>';
                    html += '</tr></thead><tbody>';
                    $.each(d.children, function (i, child) {
                        var tagStatusLabel = child.tag_status == 0 ? '<span class="ts-badge ts-badge-active">On Sale</span>' : child.tag_status == 1 ? '<span class="ts-badge ts-badge-billed">Sold</span>' : '<span class="ts-badge ts-badge-reverted">Deleted</span>';
                        var canSelect = (child.tag_status == 0 && child.is_billed != 1);
                        html += '<tr style="border-bottom:1px solid #f0f3f6;">' +
                            '<td style="padding:8px;">' + (canSelect ? '<input type="checkbox" class="ts-modal-child-select" data-code="' + child.tag_code + '" />' : '') + '</td>' +
                            '<td style="padding:8px;">' + child.child_order + '</td>' +
                            '<td style="padding:8px; font-weight:600;">' + child.tag_code + ' <button class="btn btn-xs btn-default ts-copy-btn" data-code="' + child.tag_code + '" title="Copy tag code" style="padding:1px 6px;margin-left:4px;"><i class="fa fa-copy"></i></button> <button class="btn btn-xs btn-primary ts-add-estimation-btn" data-code="' + child.tag_code + '" data-branch="' + (d.master.id_branch || '') + '" title="Add to Estimation" style="padding:1px 6px;margin-left:2px;"><i class="fa fa-calculator"></i></button></td>' +
                            '<td style="padding:8px;">' + parseFloat(child.child_gwt).toFixed(3) + '</td>' +
                            '<td style="padding:8px;">' + parseFloat(child.child_nwt).toFixed(3) + '</td>' +
                            '<td style="padding:8px;">' + parseFloat(child.child_less_wt).toFixed(3) + '</td>' +
                            '<td style="padding:8px;">' + parseFloat(child.child_stone_wt).toFixed(3) + '</td>' +
                            '<td style="padding:8px;">' + parseFloat(child.child_dia_wt).toFixed(3) + '</td>' +
                            '<td style="padding:8px;">' + tagStatusLabel + '</td>' +
                            '<td style="padding:8px;">' + (child.is_billed == 1 ? '<i class="fa fa-check-circle text-success"></i>' : '<i class="fa fa-times-circle text-muted"></i>') + '</td>' +
                            '</tr>';
                    });
                    html += '</tbody></table>';

                    $('#modal_base_code').text(d.base_tag.tag_code);
                    $('#parts_detail_content').html(html);
                    $('#partsDetailModal').modal('show');
                } else {
                    $.toaster({ priority: 'danger', title: 'Error', message: data.message || 'Failed to load parts detail' });
                }
            } catch (e) {
                $.toaster({ priority: 'danger', title: 'Server Error', message: 'Unexpected server response. Check console for details.' });
                console.error('viewPartsDetail parse error:', e, response);
            }
        });
    };

    // Copy child tag code to clipboard
    $(document).on('click', '.ts-copy-btn', function (e) {
        e.stopPropagation();
        var code = $(this).data('code');
        var $btn = $(this);
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(function () {
                $btn.html('<i class="fa fa-check"></i>').addClass('btn-success').removeClass('btn-default');
                setTimeout(function () { $btn.html('<i class="fa fa-copy"></i>').removeClass('btn-success').addClass('btn-default'); }, 1500);
            });
        } else {
            // Fallback
            var $temp = $('<input>').val(code).appendTo('body').select();
            document.execCommand('copy');
            $temp.remove();
            $btn.html('<i class="fa fa-check"></i>').addClass('btn-success').removeClass('btn-default');
            setTimeout(function () { $btn.html('<i class="fa fa-copy"></i>').removeClass('btn-success').addClass('btn-default'); }, 1500);
        }
    });

    // Add to Estimation button — opens estimation form with tag_code prefilled
    $(document).on('click', '.ts-add-estimation-btn', function (e) {
        e.stopPropagation();
        var code = $(this).data('code');
        var branch = $(this).data('branch') || '';
        var url = baseUrl + 'index.php/admin_ret_estimation/estimation/add?tag_code=' + encodeURIComponent(code);
        if (branch) {
            url += '&branch_id=' + encodeURIComponent(branch);
        }
        window.open(url, '_blank');
    });

    // Modify modal — open in-page modal
    window.openModifyModal = function (partsId) {
        loadModifyData(partsId);
    };

    // =========================================================================
    // Admin Modify Weights Modal
    // =========================================================================

    function loadModifyData(partsId) {
        $('#modify_parts_id').val(partsId);
        $('#modify_parts_id_display').text(partsId);

        $.get(baseUrl + 'index.php/admin_ret_tag_parts/get_parts_detail/' + partsId, function (response) {
            try {
                var data = JSON.parse(response);
                if (!data.status) {
                    $.toaster({ priority: 'danger', title: 'Error', message: data.message || 'Failed to load parts detail' });
                    return;
                }
                var d = data.data;

                $('#modify_base_code').text(d.base_tag.tag_code);
                $('#modify_base_gwt').text(parseFloat(d.master.base_gwt).toFixed(3));
                $('#modify_base_nwt').text(parseFloat(d.master.base_nwt).toFixed(3));
                $('#modify_base_stone').text(parseFloat(d.master.base_stone_wt).toFixed(3));
                $('#modify_base_dia').text(parseFloat(d.master.base_dia_wt).toFixed(3));
                $('#modify_base_less').text(parseFloat(d.master.base_less_wt).toFixed(3));

                var tbody = '';
                $.each(d.children, function (i, child) {
                    var isBilled = child.is_billed == 1;
                    var readonlyAttr = isBilled ? 'readonly' : '';
                    var lockIcon = isBilled ? ' <i class="fa fa-lock text-danger" title="Billed — locked"></i>' : '';

                    tbody += '<tr data-detail-id="' + child.parts_detail_id + '" data-child-tag-id="' + child.child_tag_id + '" data-billed="' + child.is_billed + '">' +
                        '<td>' + child.child_order + '</td>' +
                        '<td>' + child.tag_code + lockIcon + '</td>' +
                        '<td><input type="number" step="0.001" class="modify-gwt" value="' + parseFloat(child.child_gwt).toFixed(3) + '" ' + readonlyAttr + ' style="text-align:center;width:100%;border:1.5px solid #d5dce6;border-radius:4px;padding:6px;font-size:13px;font-weight:600;"/></td>' +
                        '<td><input type="number" step="0.001" class="modify-nwt" value="' + parseFloat(child.child_nwt).toFixed(3) + '" ' + readonlyAttr + ' style="text-align:center;width:100%;border:1.5px solid #d5dce6;border-radius:4px;padding:6px;font-size:13px;font-weight:600;"/></td>' +
                        '<td><input type="number" step="0.001" class="modify-less" value="' + parseFloat(child.child_less_wt).toFixed(3) + '" ' + readonlyAttr + ' style="text-align:center;width:100%;border:1.5px solid #d5dce6;border-radius:4px;padding:6px;font-size:13px;font-weight:600;"/></td>' +
                        '<td><input type="number" step="0.001" class="modify-stone" value="' + parseFloat(child.child_stone_wt).toFixed(3) + '" ' + readonlyAttr + ' style="text-align:center;width:100%;border:1.5px solid #d5dce6;border-radius:4px;padding:6px;font-size:13px;font-weight:600;"/></td>' +
                        '<td><input type="number" step="0.001" class="modify-dia" value="' + parseFloat(child.child_dia_wt).toFixed(3) + '" ' + readonlyAttr + ' style="text-align:center;width:100%;border:1.5px solid #d5dce6;border-radius:4px;padding:6px;font-size:13px;font-weight:600;"/></td>' +
                        '<td>' + (isBilled ? '<span class="ts-badge ts-badge-billed">Billed</span>' : '<span class="ts-badge ts-badge-active">Available</span>') + '</td>' +
                        '</tr>';
                });
                $('#modify_child_tbody').html(tbody);

                $('#modify_child_tbody input').on('input', function () {
                    validateModifyWeights(d.master);
                });
                validateModifyWeights(d.master);
                $('#adminModifyModal').modal('show');
            } catch (e) {
                $.toaster({ priority: 'danger', title: 'Server Error', message: 'Unexpected server response. Check console for details.' });
                console.error('loadModifyData parse error:', e, response);
            }
        });
    }

    function validateModifyWeights(master) {
        var tolerance = 0.001;
        var fields = ['gwt', 'nwt', 'less', 'stone', 'dia'];
        var baseVals = {
            gwt: parseFloat(master.base_gwt),
            nwt: parseFloat(master.base_nwt),
            less: parseFloat(master.base_less_wt),
            stone: parseFloat(master.base_stone_wt),
            dia: parseFloat(master.base_dia_wt)
        };

        var allValid = true;
        $.each(fields, function (i, f) {
            var sum = 0;
            var hasNegative = false;
            $('#modify_child_tbody .modify-' + f).each(function () {
                var v = parseFloat($(this).val()) || 0;
                if (v < 0) hasNegative = true;
                sum += v;
            });

            var diff = Math.abs(sum - baseVals[f]);
            var valid = diff <= tolerance && !hasNegative;
            var tdId = '#modify_total_' + (f == 'less' ? 'less_wt' : f == 'stone' ? 'stone_wt' : f == 'dia' ? 'dia_wt' : f);
            var validId = '#modify_validate_' + (f == 'less' ? 'less_wt' : f == 'stone' ? 'stone_wt' : f == 'dia' ? 'dia_wt' : f);

            $(tdId).text(sum.toFixed(3));

            if (valid) {
                $(validId).html('<span class="valid-indicator"><i class="fa fa-check-circle"></i></span>');
            } else {
                $(validId).html('<span class="invalid-indicator"><i class="fa fa-times-circle"></i></span>');
                allValid = false;
            }
        });

        $('#btn_save_modify').prop('disabled', !allValid);
    }

    // Save modified weights
    $('#btn_save_modify').on('click', function () {
        var partsId = $('#modify_parts_id').val();
        var children = [];

        $('#modify_child_tbody tr').each(function () {
            if ($(this).data('billed') == 1) return;
            children.push({
                parts_detail_id: $(this).data('detail-id'),
                child_tag_id: $(this).data('child-tag-id'),
                gwt: parseFloat($(this).find('.modify-gwt').val()) || 0,
                nwt: parseFloat($(this).find('.modify-nwt').val()) || 0,
                less_wt: parseFloat($(this).find('.modify-less').val()) || 0,
                stone_wt: parseFloat($(this).find('.modify-stone').val()) || 0,
                dia_wt: parseFloat($(this).find('.modify-dia').val()) || 0
            });
        });

        if (!confirm('Save modified weights? This action is logged.')) return;

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.post(baseUrl + 'index.php/admin_ret_tag_parts/admin_modify', {
            parts_id: partsId,
            children: JSON.stringify(children)
        }, function (response) {
            try {
                var data = JSON.parse(response);
                if (data.status) {
                    $.toaster({ priority: 'success', title: 'Updated!', message: 'Child weights updated successfully' });
                    $('#adminModifyModal').modal('hide');
                    loadPartsHistory();
                } else {
                    $.toaster({ priority: 'danger', title: 'Update Failed', message: data.message || 'Update failed' });
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Modified Weights');
                }
            } catch (e) {
                $.toaster({ priority: 'danger', title: 'Server Error', message: 'Unexpected server response. Check console for details.' });
                console.error('admin_modify parse error:', e, response);
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Modified Weights');
            }
        });
    });

    // Manual revert
    window.manualRevert = function (partsId) {
        if (confirm('Are you sure you want to revert this partly sale? All unbilled child tags will be cancelled and the base tag will be restored.')) {
            $.post(baseUrl + 'index.php/admin_ret_tag_parts/manual_revert', { parts_id: partsId }, function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status) {
                        $.toaster({ priority: 'success', title: 'Reverted!', message: 'Partly sale reverted successfully' });
                        loadPartsHistory();
                        loadEodQueue();
                    } else {
                        $.toaster({ priority: 'danger', title: 'Revert Failed', message: data.message || 'Revert operation failed' });
                    }
                } catch (e) {
                    $.toaster({ priority: 'danger', title: 'Server Error', message: 'Unexpected server response. Check console for details.' });
                    console.error('manual_revert parse error:', e, response);
                }
            });
        }
    };

});
