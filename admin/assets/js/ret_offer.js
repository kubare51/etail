$(document).ready(function() {

    // base_url is set as a global JS variable in layout/footer.php.
    // NOTE: do not redeclare it with `var` here — `var` is function-scoped,
    // so `var base_url = ...` anywhere in this callback would shadow the
    // global for the entire function via hoisting, even inside an `if`
    // that never runs its assignment.
    if (typeof window.base_url === 'undefined') {
        window.base_url = $('#base_url').val() || '';
    }

    // ==========================================
    // OFFER MASTER LIST PAGE
    // Handled entirely by the inline script in
    // views/master/disc_offer/list.php — do not
    // re-initialize #offer_master_list here.
    // ==========================================

    // ==========================================
    // OFFER MASTER FORM PAGE
    // ==========================================
    if ($('#offer_master_form').length) {

        // Initialize datepickers, linked so end_date >= start_date
        $('#start_date').datepicker({
            format: 'dd-mm-yyyy',
            autoclose: true,
            todayHighlight: true
        }).on('changeDate', function(ev) {
            $('#end_date').datepicker('setStartDate', ev.date);
            var endVal = $('#end_date').val();
            if (endVal) {
                var endParts = endVal.split('-');
                var endDate = new Date(endParts[2], endParts[1] - 1, endParts[0]);
                if (endDate < ev.date) {
                    $('#end_date').val('').datepicker('update');
                }
            }
        });

        $('#end_date').datepicker({
            format: 'dd-mm-yyyy',
            autoclose: true,
            todayHighlight: true
        }).on('changeDate', function(ev) {
            $('#start_date').datepicker('setEndDate', ev.date);
        });

        // Pre-set constraints on page load (for edit mode)
        var initStart = $('#start_date').val();
        if (initStart) {
            var sp = initStart.split('-');
            var startObj = new Date(sp[2], sp[1] - 1, sp[0]);
            $('#end_date').datepicker('setStartDate', startObj);
        }
        var initEnd = $('#end_date').val();
        if (initEnd) {
            var ep = initEnd.split('-');
            var endObj = new Date(ep[2], ep[1] - 1, ep[0]);
            $('#start_date').datepicker('setEndDate', endObj);
        }

        // Metal Rate Radio Toggle
        $('input[name="metal_rate_type"]').on('change', function() {
            var type = $(this).val();
            if (type === 'pct') {
                $('#metal_pct_group').removeClass('offer-field-disabled');
                $('#disc_metal_rate_pct').prop('disabled', false).focus();
                $('#metal_flat_group').addClass('offer-field-disabled');
                $('#disc_metal_rate_flat').prop('disabled', true).val('');
            } else {
                $('#metal_flat_group').removeClass('offer-field-disabled');
                $('#disc_metal_rate_flat').prop('disabled', false).focus();
                $('#metal_pct_group').addClass('offer-field-disabled');
                $('#disc_metal_rate_pct').prop('disabled', true).val('');
            }
        });

        // Diamond Radio Toggle
        $('input[name="diamond_disc_type"]').on('change', function() {
            var type = $(this).val();
            if (type === 'per_ct') {
                $('#diamond_ct_group').removeClass('offer-field-disabled');
                $('#disc_diamond_per_ct').prop('disabled', false).focus();
                $('#diamond_pct_group').addClass('offer-field-disabled');
                $('#disc_diamond_flat_pct').prop('disabled', true).val('');
            } else {
                $('#diamond_pct_group').removeClass('offer-field-disabled');
                $('#disc_diamond_flat_pct').prop('disabled', false).focus();
                $('#diamond_ct_group').addClass('offer-field-disabled');
                $('#disc_diamond_per_ct').prop('disabled', true).val('');
            }
        });

        // Status Radio — toggle .checked class for the label styling
        function updateStatusRadio() {
            $('.status-radio-label').removeClass('checked');
            $('input[name="status"]:checked').closest('.status-radio-label').addClass('checked');
        }
        updateStatusRadio();
        $('input[name="status"]').on('change', updateStatusRadio);

        // Initialize radio state on page load (handles edit mode pre-fill)
        initRadioState();

        // Form submit
        $('#offer_master_form').on('submit', function(e) {
            e.preventDefault();

            // Enable disabled fields temporarily so they are included in serialize
            $('#offer_master_form :disabled').prop('disabled', false);

            if (!validateOfferForm()) {
                // Re-apply disabled state based on radio selection
                initRadioState();
                return false;
            }

            var formData = $(this).serialize();

            $.ajax({
                url: base_url + 'index.php/admin_ret_offer/offer_master/save',
                type: 'POST',
                data: formData,
                dataType: 'json',
                beforeSend: function() {
                    $('#btn_save').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
                },
                success: function(res) {
                    if (res.status) {
                        $.toaster({ priority: 'success', title: 'Success', message: res.msg });
                        setTimeout(function() {
                            window.location.href = base_url + 'index.php/admin_ret_offer/offer_master/list';
                        }, 1000);
                    } else {
                        $.toaster({ priority: 'danger', title: 'Error', message: res.msg });
                        $('#btn_save').prop('disabled', false).html('<i class="fa fa-save"></i> Save');
                        initRadioState();
                    }
                },
                error: function() {
                    $.toaster({ priority: 'danger', title: 'Error', message: 'Something went wrong. Please try again.' });
                    $('#btn_save').prop('disabled', false).html('<i class="fa fa-save"></i> Save');
                    initRadioState();
                }
            });
        });
    }

    // ==========================================
    // HELPER FUNCTIONS
    // ==========================================

    /**
     * Set the correct radio state and disable/enable the corresponding fields.
     * Called on page load (edit mode) and after form errors.
     */
    function initRadioState() {
        // Metal rate: if flat value exists and > 0, select flat; else default to pct
        var metalFlat = $('#disc_metal_rate_flat').val();
        if (metalFlat && parseFloat(metalFlat) > 0) {
            $('input[name="metal_rate_type"][value="flat"]').prop('checked', true).trigger('change');
        } else {
            $('input[name="metal_rate_type"][value="pct"]').prop('checked', true).trigger('change');
        }

        // Diamond: if flat_pct value exists and > 0, select flat_pct; else default to per_ct
        var diaFlat = $('#disc_diamond_flat_pct').val();
        if (diaFlat && parseFloat(diaFlat) > 0) {
            $('input[name="diamond_disc_type"][value="flat_pct"]').prop('checked', true).trigger('change');
        } else {
            $('input[name="diamond_disc_type"][value="per_ct"]').prop('checked', true).trigger('change');
        }
    }

    /**
     * Validate the offer form before submission.
     * Returns true if valid, false otherwise. Shows a toaster error.
     */
    function validateOfferForm() {
        var errors = [];

        // Offer name: required, max 100 chars
        var name = $.trim($('#offer_name').val());
        if (!name) {
            errors.push('Offer Name is required');
        } else if (name.length > 100) {
            errors.push('Offer Name must be 100 characters or less');
        }

        // Dates: required
        var startDate = $.trim($('#start_date').val());
        var endDate = $.trim($('#end_date').val());
        if (!startDate) errors.push('Start Date is required');
        if (!endDate) errors.push('End Date is required');

        // End date must be >= start date
        if (startDate && endDate) {
            var start = parseDMY(startDate);
            var end = parseDMY(endDate);
            if (end < start) {
                errors.push('End Date must be on or after Start Date');
            }
        }

        // At least one discount field must have a value > 0
        var hasDiscount = false;
        var discFields = [
            'disc_metal_rate_pct', 'disc_metal_rate_flat',
            'disc_mc_pct', 'disc_va_pct',
            'disc_diamond_per_ct', 'disc_diamond_flat_pct',
            'disc_taxable_value_pct'
        ];
        for (var i = 0; i < discFields.length; i++) {
            var val = parseFloat($('#' + discFields[i]).val());
            if (!isNaN(val) && val > 0) {
                hasDiscount = true;
                break;
            }
        }
        if (!hasDiscount) {
            errors.push('At least one discount field must have a value greater than 0');
        }

        // Metal rate: mutual exclusion check
        var metalPct = parseFloat($('#disc_metal_rate_pct').val()) || 0;
        var metalFlat = parseFloat($('#disc_metal_rate_flat').val()) || 0;
        if (metalPct > 0 && metalFlat > 0) {
            errors.push('Metal Rate discount: choose either Percentage OR Flat, not both');
        }

        // Diamond: mutual exclusion check
        var diaPerCt = parseFloat($('#disc_diamond_per_ct').val()) || 0;
        var diaFlatPct = parseFloat($('#disc_diamond_flat_pct').val()) || 0;
        if (diaPerCt > 0 && diaFlatPct > 0) {
            errors.push('Diamond discount: choose either Per Carat OR Flat %, not both');
        }

        // Range checks: no discount field may be negative; percentage-type
        // fields cannot exceed 100
        var percentageFields = ['disc_metal_rate_pct', 'disc_mc_pct', 'disc_va_pct', 'disc_diamond_flat_pct', 'disc_taxable_value_pct'];
        for (var k = 0; k < discFields.length; k++) {
            var rawVal = $('#' + discFields[k]).val();
            if (rawVal === '' || rawVal === undefined) continue;
            var numVal = parseFloat(rawVal);
            if (isNaN(numVal)) continue;
            if (numVal < 0) {
                errors.push('Discount fields cannot be negative');
                break;
            }
        }
        for (var m = 0; m < percentageFields.length; m++) {
            var pctVal = parseFloat($('#' + percentageFields[m]).val());
            if (!isNaN(pctVal) && pctVal > 100) {
                errors.push('Percentage discount fields cannot exceed 100');
                break;
            }
        }

        if (errors.length > 0) {
            $.toaster({ priority: 'danger', title: 'Validation', message: errors.join('<br>') });
            return false;
        }
        return true;
    }

    /**
     * Parse a dd-mm-yyyy date string into a JS Date object.
     * @param {string} dateStr - Date in dd-mm-yyyy format
     * @returns {Date}
     */
    function parseDMY(dateStr) {
        var parts = dateStr.split('-');
        return new Date(parseInt(parts[2]), parseInt(parts[1]) - 1, parseInt(parts[0]));
    }

});

// ==========================================
// ESTIMATION OFFER INTEGRATION
// Runs synchronously when this script loads (before ret_estimation.js).
// Uses capture-phase click so Save is blocked until offer flow completes.
// ==========================================
(function initEstimationOfferIntegration() {
    if (!$('#est_form').length) {
        return;
    }

    if (typeof base_url === 'undefined') {
        window.base_url = $('#base_url').val() || '';
    }

    window.EstOfferGate = {
        active: false,
        allowed: false,
        shouldBlock: function() {
            return this.active && !this.allowed;
        },
        open: function() {
            this.active = true;
            this.allowed = false;
        },
        allow: function() {
            this.allowed = true;
        },
        close: function() {
            this.active = false;
            this.allowed = false;
        }
    };

    var _offerBypass = false;
    var _offerCheckInProgress = false;
    var _offerData = null;
    var _selectedOffers = {};
    var _pendingClickedBtn = null;
    var _pendingItems = null;

    function _resolveSaveButton(target) {
        if (!target) {
            return null;
        }
        if (target.id === 'est_print' || target.id === 'est_eda_print') {
            return target;
        }
        if (target.closest) {
            return target.closest('#est_print, #est_eda_print');
        }
        return null;
    }

    function _shouldInterceptSave() {
        var isOfferEnabled = $('#is_offer_on_estimation').val();
        if (isOfferEnabled !== '1') {
            return false;
        }

        var estiFor = $("input[name='estimation[esti_for]']:checked").val();
        if (estiFor != 1) {
            return false;
        }
        return _collectEstimationItems().length > 0;
    }

    function _disableSaveButtons() {
        $('#est_print, #est_eda_print').prop('disabled', true);
    }

    function _enableSaveButtons() {
        $('#est_print, #est_eda_print').prop('disabled', false);
    }

    function _releaseGateWithoutSave() {
        _enableSaveButtons();
        window.EstOfferGate.close();
        _pendingClickedBtn = null;
        _pendingItems = null;
    }

    function _startOfferCheck(clickedBtn, items) {
        if (_offerCheckInProgress) {
            return;
        }
        _offerCheckInProgress = true;
        _pendingClickedBtn = clickedBtn;
        _pendingItems = items;

        var branchId = $('#id_branch').val();
        var estDate = new Date().toISOString().split('T')[0];

        $.ajax({
            url: base_url + 'index.php/admin_ret_offer/check_estimation_offers',
            type: 'POST',
            dataType: 'JSON',
            data: {
                id_branch: branchId,
                est_date: estDate,
                items: JSON.stringify(items)
            },
            beforeSend: function() {
                $('#offerModalBody').html(
                    '<div class="text-center" style="padding:40px;">' +
                    '<i class="fa fa-spinner fa-spin fa-2x" style="color:#4285f4;"></i>' +
                    '<p style="margin-top:12px;color:#5f6368;">Checking eligible offers...</p>' +
                    '</div>'
                );
                $('#offerSummaryBar').hide();
                $('#offerApplyBtn').hide();
                $('#offerSelectionModal').modal('show');
            },
            success: function(resp) {
                if (!resp.status || !resp.data) {
                    _renderErrorState(items, clickedBtn, 'Invalid response from offer service.');
                    return;
                }

                // If master settings disable offers on estimation, bypass popup entirely
                if (resp.data.is_enabled === false || resp.data.is_enabled === 0) {
                    _clearOfferFields(items);
                    _proceedToSave(clickedBtn);
                    return;
                }

                _offerData = resp.data;
                var offers = resp.data.offers || [];
                var itemOffers = resp.data.item_offers || {};

                if (offers.length === 0 || Object.keys(itemOffers).length === 0) {
                    _renderNoOffersState(items, clickedBtn);
                    return;
                }

                _renderOfferModal(items, offers, itemOffers, clickedBtn);
            },
            error: function(xhr, status, err) {
                console.error('Offer check failed:', err);
                _renderErrorState(items, clickedBtn, 'Unable to check offers. Please retry or skip offers to continue.');
            },
            complete: function() {
                _offerCheckInProgress = false;
            }
        });
    }

    function _renderNoOffersState(items, clickedBtn) {
        $('#offerModalBody').html(
            '<div class="text-center" style="padding:30px;color:#5f6368;">' +
            '<i class="fa fa-info-circle fa-2x" style="color:#dadce0;"></i>' +
            '<p style="margin-top:10px;">No eligible offers found for the current items.</p>' +
            '<p style="font-size:13px;">Click <strong>Skip Offers</strong> to save without applying any offer.</p>' +
            '</div>'
        );
        $('#offerSummaryBar').hide();
        _bindModalActions(items, clickedBtn, false);
    }

    function _renderErrorState(items, clickedBtn, message) {
        $('#offerModalBody').html(
            '<div class="text-center" style="padding:30px;color:#5f6368;">' +
            '<i class="fa fa-exclamation-triangle fa-2x" style="color:#e67e22;"></i>' +
            '<p style="margin-top:10px;">' + _escHtml(message) + '</p>' +
            '<button type="button" class="btn btn-primary" id="offerRetryBtn" style="margin-top:14px;border-radius:6px;">' +
            '<i class="fa fa-refresh" style="margin-right:4px;"></i> Retry</button>' +
            '</div>'
        );
        $('#offerSummaryBar').hide();
        _bindModalActions(items, clickedBtn, false);

        $('#offerRetryBtn').off('click').on('click', function() {
            _startOfferCheck(clickedBtn, items);
        });
    }

    function _bindModalActions(items, clickedBtn, showApply) {
        if (showApply) {
            $('#offerApplyBtn').show();
        } else {
            $('#offerApplyBtn').hide();
        }

        $('#offerApplyBtn').off('click.offerGate').on('click.offerGate', function() {
            _applySelectedOffers(items);
            _proceedToSave(clickedBtn);
            $('#offerSelectionModal').modal('hide');
        });

        $('#offerSkipBtn').off('click.offerGate').on('click.offerGate', function() {
            _clearOfferFields(items);
            _proceedToSave(clickedBtn);
            $('#offerSelectionModal').modal('hide');
        });
    }

    function _proceedToSave(btnId) {
        window.EstOfferGate.allow();
        _offerBypass = true;
        _enableSaveButtons();

        var btn = document.getElementById(btnId);
        if (btn) {
            btn.click();
        }

        window.EstOfferGate.close();
        _pendingClickedBtn = null;
        _pendingItems = null;
    }

    document.addEventListener('mousedown', function(e) {
        var btn = _resolveSaveButton(e.target);
        if (!btn || _offerBypass) {
            return;
        }
        if (_shouldInterceptSave()) {
            window.EstOfferGate.open();
        }
    }, true);

    document.addEventListener('click', function(e) {
        var btn = _resolveSaveButton(e.target);
        if (!btn) {
            return;
        }

        if (_offerBypass) {
            _offerBypass = false;
            return;
        }

        if (!_shouldInterceptSave()) {
            window.EstOfferGate.close();
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        window.EstOfferGate.open();
        _disableSaveButtons();
        _startOfferCheck(btn.id, _collectEstimationItems());
    }, true);

    $('#offerSelectionModal').on('hidden.bs.modal', function() {
        if (window.EstOfferGate.shouldBlock()) {
            _releaseGateWithoutSave();
        }
    });

    var DIAMOND_STONE_TYPE = 1;

    /**
     * Sum diamond weight (carats) and value (₹) from a row's stone_details
     * hidden field. Diamond stones use UOM "carat", so stone_wt is already
     * in carats — no unit conversion needed.
     */
    function _extractDiamondTotals($row) {
        var totals = { diamond_wt: 0, diamond_value: 0 };
        var raw = $row.find('.stone_details').val();
        if (!raw) return totals;

        var stones;
        try {
            stones = JSON.parse(raw);
        } catch (e) {
            return totals;
        }
        if (!stones || !stones.length) return totals;

        for (var i = 0; i < stones.length; i++) {
            var s = stones[i];
            if (parseInt(s.stones_type) === DIAMOND_STONE_TYPE) {
                totals.diamond_wt += parseFloat(s.stone_wt) || 0;
                totals.diamond_value += parseFloat(s.stone_price) || 0;
            }
        }
        return totals;
    }

    function _collectEstimationItems() {
            var items = [];
            var idx = 0;

            // ── TAG items ──
            $('#estimation_tag_details tbody tr').each(function() {
                var $row = $(this);
                var productId = $row.find('.pro_id').val();
                if (!productId || productId === '') return;

                var diamondTotals = _extractDiamondTotals($row);

                items.push({
                    row_index: idx,
                    item_type: 0,
                    table_type: 'est_tag',
                    product_id: productId,
                    tag_id: $row.find('.est_tag_id').val() || '',
                    tag_code: $row.find('.est_tag_name').val() || '',
                    design_id: $row.find("input[name='est_tag[design_id][]']").val() || '',
                    id_metal: $row.find('.metal_type').val() || '',
                    id_category: $row.find("input[name='est_tag[id_category][]']").val() || $row.find('.id_category').val() || '',
                    tag_date: $row.find("input[name='est_tag[tag_date][]']").val() || '',
                    product_name: $row.find('.prodct_name').text().trim() || 'Tag Item #' + (idx + 1),
                    item_cost: parseFloat($row.find("input[name='est_tag[cost][]']").val()) || 0,
                    tax_amount: parseFloat($row.find("input[name='est_tag[tax_price][]']").val()) || 0,
                    mc_value: parseFloat($row.find("input[name='est_tag[mc][]']").val()) || 0,
                    net_wt: parseFloat($row.find("input[name='est_tag[nwt][]']").val()) || 0,
                    wastage_percent: parseFloat($row.find("input[name='est_tag[wastage][]']").val()) || 0,
                    rate_per_grm: parseFloat($row.find("input[name='est_tag[est_rate_per_grm][]']").val()) || 0,
                    diamond_wt: diamondTotals.diamond_wt,
                    diamond_value: diamondTotals.diamond_value,
                    dom_row: $row
                });
                idx++;
            });

            // ── CATALOG items ──
            $('#estimation_catalog_details tbody tr').each(function() {
                var $row = $(this);
                var productId = $row.find('.cat_pro_id').val() || $row.find("input[name='est_catalog[pro_id][]']").val();
                if (!productId || productId === '') return;

                items.push({
                    row_index: idx,
                    item_type: 1,
                    table_type: 'est_catalog',
                    product_id: productId,
                    design_id: $row.find("input[name='est_catalog[des_id][]']").val() || '',
                    id_metal: $row.find('.metal_type').val() || '',
                    id_category: $row.find('.id_category').val() || '',
                    tag_date: '',
                    product_name: $row.find('.prodct_name').text().trim() || $row.find('.cat_product option:selected').text().trim() || 'Catalog Item',
                    item_cost: parseFloat($row.find("input[name='est_catalog[amount][]']").val()) || 0,
                    tax_amount: parseFloat($row.find("input[name='est_catalog[tax_price][]']").val()) || 0,
                    mc_value: parseFloat($row.find("input[name='est_catalog[mc][]']").val()) || 0,
                    net_wt: parseFloat($row.find("input[name='est_catalog[nwt][]']").val()) || 0,
                    wastage_percent: parseFloat($row.find("input[name='est_catalog[wastage][]']").val()) || 0,
                    rate_per_grm: parseFloat($row.find("input[name='est_catalog[est_rate_per_grm][]']").val()) || 0,
                    dom_row: $row
                });
                idx++;
            });

            // ── CUSTOM items ──
            $('#estimation_custom_details tbody tr').each(function() {
                var $row = $(this);
                var productId = $row.find('.cus_product_id').val() || $row.find("input[name='est_custom[pro_id][]']").val();
                if (!productId || productId === '') return;

                items.push({
                    row_index: idx,
                    item_type: 2,
                    table_type: 'est_custom',
                    product_id: productId,
                    design_id: $row.find("input[name='est_custom[des_id][]']").val() || '',
                    id_metal: $row.find('.metal_type').val() || '',
                    id_category: $row.find('.id_category').val() || '',
                    tag_date: '',
                    product_name: $row.find('.prodct_name').text().trim() || $row.find('.cus_product option:selected').text().trim() || 'Custom Item',
                    item_cost: parseFloat($row.find("input[name='est_custom[amount][]']").val()) || 0,
                    tax_amount: parseFloat($row.find("input[name='est_custom[tax_price][]']").val()) || 0,
                    mc_value: parseFloat($row.find("input[name='est_custom[mc][]']").val()) || 0,
                    net_wt: parseFloat($row.find("input[name='est_custom[nwt][]']").val()) || 0,
                    wastage_percent: parseFloat($row.find("input[name='est_custom[wastage][]']").val()) || 0,
                    rate_per_grm: parseFloat($row.find("input[name='est_custom[est_rate_per_grm][]']").val()) || 0,
                    dom_row: $row
                });
                idx++;
            });

            return items;
        }

        /**
         * Render the offer selection modal with per-item radio buttons.
         */
        function _renderOfferModal(items, offers, itemOffers, clickedBtn) {
            _selectedOffers = {};

            // Build offer lookup
            var offerMap = {};
            for (var i = 0; i < offers.length; i++) {
                offerMap[offers[i].id_offer] = offers[i];
            }

            var html = '';

            // Items with offers
            var hasOffers = false;
            for (var ri in itemOffers) {
                if (!itemOffers.hasOwnProperty(ri)) continue;
                hasOffers = true;
                var rowIdx = parseInt(ri);
                var item = null;
                for (var j = 0; j < items.length; j++) {
                    if (items[j].row_index === rowIdx) { item = items[j]; break; }
                }
                if (!item) continue;

                var offerIds = itemOffers[ri];
                var typeBadge = item.item_type === 0
                    ? '<span style="background:#e8f0fe;color:#1a73e8;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">TAG</span>'
                    : (item.item_type === 1
                        ? '<span style="background:#fce8e6;color:#d93025;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">CATALOG</span>'
                        : '<span style="background:#e6f4ea;color:#137333;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">CUSTOM</span>');

                // Build item identifier: tag code + product name
                var itemLabel = _escHtml(item.product_name);
                if (item.tag_code) {
                    itemLabel = '<span style="background:#f1f3f4;color:#5f6368;padding:1px 6px;border-radius:4px;font-size:11px;font-family:monospace;font-weight:600;margin-right:6px;">' + _escHtml(item.tag_code) + '</span>' + itemLabel;
                }

                html += '<div class="offer-item-card" data-row="' + rowIdx + '" style="background:#fff;border:1px solid #e8eaed;border-radius:10px;padding:16px;margin-bottom:14px;transition:all .2s ease;">';
                html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">';
                html += '<div>';
                html += typeBadge + ' ';
                html += '<strong style="font-size:14px;color:#202124;">' + itemLabel + '</strong>';
                html += '</div>';
                html += '<div style="text-align:right;">';
                html += '<div style="font-size:13px;color:#5f6368;">Cost: <strong>\u20b9 ' + item.item_cost.toFixed(2) + '</strong></div>';
                html += '<div class="item-discount-label" style="font-size:12px;color:#d93025;font-weight:600;display:none;margin-top:2px;">Discount: -\u20b9 0.00</div>';
                html += '<div class="item-net-cost-label" style="font-size:12px;color:#137333;font-weight:600;display:none;margin-top:2px;">Net Cost: \u20b9 ' + item.item_cost.toFixed(2) + '</div>';
                html += '</div>';
                html += '</div>';

                // Offer checkbox options
                html += '<div style="padding-left:4px;">';

                for (var k = 0; k < offerIds.length; k++) {
                    var oid = offerIds[k];
                    var offer = offerMap[oid];
                    if (!offer) continue;

                    // Build discount description
                    var discParts = [];
                    if (parseFloat(offer.disc_metal_rate_pct) > 0) discParts.push('Metal Rate: ' + offer.disc_metal_rate_pct + '%');
                    if (parseFloat(offer.disc_metal_rate_flat) > 0) discParts.push('Metal Flat: \u20b9' + parseFloat(offer.disc_metal_rate_flat).toFixed(2));
                    if (parseFloat(offer.disc_mc_pct) > 0) discParts.push('MC: ' + offer.disc_mc_pct + '%');
                    if (parseFloat(offer.disc_va_pct) > 0) discParts.push('VA: ' + offer.disc_va_pct + '%');
                    if (parseFloat(offer.disc_diamond_per_ct) > 0) discParts.push('Diamond/ct: \u20b9' + parseFloat(offer.disc_diamond_per_ct).toFixed(2));
                    if (parseFloat(offer.disc_diamond_flat_pct) > 0) discParts.push('Diamond Flat: ' + offer.disc_diamond_flat_pct + '%');
                    if (parseFloat(offer.disc_taxable_value_pct) > 0) discParts.push('Taxable: ' + offer.disc_taxable_value_pct + '%');
                    var discDesc = discParts.length > 0 ? discParts.join(' | ') : 'Special Offer';

                    var existingOfferIds = (item.dom_row.find('.offer_hidden_id').val() || '').split(',');
                    var wasApplied = existingOfferIds.indexOf(String(oid)) > -1;

                    html += '<label style="display:flex;align-items:flex-start;padding:10px 12px;margin:4px 0;border-radius:8px;cursor:pointer;background:#fff;border:1px solid #e8eaed;transition:all .15s ease;" class="offer-check-label">';
                    html += '<input type="checkbox" value="' + oid + '" class="offer-check" data-row="' + rowIdx + '"' + (wasApplied ? ' checked' : '') + ' style="margin-right:10px;margin-top:3px;accent-color:#1a73e8;width:16px;height:16px;">';
                    html += '<div style="flex:1;">';
                    html += '<div style="font-weight:600;color:#202124;font-size:13px;">' + _escHtml(offer.offer_name) + '</div>';
                    html += '<div style="font-size:12px;color:#5f6368;margin-top:2px;">' + discDesc + '</div>';
                    html += '<div style="font-size:11px;color:#80868b;margin-top:2px;">Valid: ' + offer.start_date + ' to ' + offer.end_date + '</div>';
                    html += '</div>';
                    html += '</label>';
                }

                html += '</div>'; // offers container
                html += '</div>'; // card

            }

            if (!hasOffers) {
                html = '<div class="text-center" style="padding:30px;color:#5f6368;"><i class="fa fa-info-circle fa-2x" style="color:#dadce0;"></i><p style="margin-top:10px;">No eligible offers found for the current items.</p></div>';
            }

            // Top bar with Select All / Deselect All
            var topBar = '';
            if (hasOffers) {
                topBar = '<div style="margin-bottom:14px;padding:10px 14px;background:#e8f0fe;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">';
                topBar += '<span style="font-size:13px;color:#1a73e8;font-weight:600;">Select offers to apply. Multiple offers can be combined per item.</span>';
                topBar += '<div>';
                topBar += '<button type="button" class="btn btn-sm" id="offerSelectAll" style="background:#1a73e8;color:#fff;border-radius:6px;padding:4px 14px;font-size:12px;margin-right:6px;">Select All</button>';
                topBar += '<button type="button" class="btn btn-sm" id="offerDeselectAll" style="background:#fff;color:#5f6368;border:1px solid #dadce0;border-radius:6px;padding:4px 14px;font-size:12px;">Clear All</button>';
                topBar += '</div>';
                topBar += '</div>';
            }

            $('#offerModalBody').html(topBar + html);
            $('#offerSummaryBar').show();
            _updateOfferSummary(items);
            $('.offer-check:checked').closest('.offer-check-label').css({ 'background': '#e8f0fe', 'border-color': '#1a73e8' });
            _bindModalActions(items, clickedBtn, true);

            // Bind checkbox change to update summary + highlight
            $(document).off('change.offerCheck').on('change.offerCheck', '.offer-check', function() {
                var $label = $(this).closest('.offer-check-label');
                if ($(this).is(':checked')) {
                    $label.css({ 'background': '#e8f0fe', 'border-color': '#1a73e8' });
                } else {
                    $label.css({ 'background': '#fff', 'border-color': '#e8eaed' });
                }
                _updateOfferSummary(items);
            });

            // Select All button — check all checkboxes
            $(document).off('click.offerSelectAll').on('click.offerSelectAll', '#offerSelectAll', function() {
                $('.offer-check').prop('checked', true).trigger('change');
            });

            // Deselect All button — uncheck all checkboxes
            $(document).off('click.offerDeselectAll').on('click.offerDeselectAll', '#offerDeselectAll', function() {
                $('.offer-check').prop('checked', false).trigger('change');
            });
        }

        /**
         * Calculate offer discount amount for a single item.
         * Post-tax deduction (offer amount is computed, then deducted after tax).
         */
        function _calcOfferDiscount(offer, item) {
            var disc = 0;

            // Metal rate discount (percentage of item_cost minus tax)
            var metalValue = item.item_cost - item.tax_amount;
            if (parseFloat(offer.disc_metal_rate_pct) > 0) {
                disc += (metalValue * parseFloat(offer.disc_metal_rate_pct) / 100);
            }
            // Metal rate flat (per gram × net weight)
            if (parseFloat(offer.disc_metal_rate_flat) > 0) {
                disc += (parseFloat(offer.disc_metal_rate_flat) * (item.net_wt || 0));
            }
            // MC discount
            if (parseFloat(offer.disc_mc_pct) > 0) {
                disc += ((item.mc_value || 0) * parseFloat(offer.disc_mc_pct) / 100);
            }
            // VA discount — isolate the wastage weight's rupee value only
            // (net_wt × wastage% × rate), not the item's full metal value.
            if (parseFloat(offer.disc_va_pct) > 0) {
                var wastageWt = (item.net_wt || 0) * ((item.wastage_percent || 0) / 100);
                var vaValue = wastageWt * (item.rate_per_grm || 0);
                if (vaValue < 0) vaValue = 0;
                disc += (vaValue * parseFloat(offer.disc_va_pct) / 100);
            }
            // Diamond per carat — ₹ per carat × total diamond weight (carats) on this item.
            // Tag items only for now; catalog/custom items don't carry diamond_wt (known gap).
            if (parseFloat(offer.disc_diamond_per_ct) > 0) {
                disc += ((item.diamond_wt || 0) * parseFloat(offer.disc_diamond_per_ct));
            }

            // Diamond flat pct — % off the diamond stones' own value on this item.
            if (parseFloat(offer.disc_diamond_flat_pct) > 0) {
                disc += ((item.diamond_value || 0) * parseFloat(offer.disc_diamond_flat_pct) / 100);
            }

            // Taxable value discount
            if (parseFloat(offer.disc_taxable_value_pct) > 0) {
                disc += (item.item_cost * parseFloat(offer.disc_taxable_value_pct) / 100);
            }

            // NaN guard + floor at 0
            if (isNaN(disc) || disc < 0) disc = 0;

            return parseFloat(disc.toFixed(2));
        }

        /**
         * Update the summary bar based on current checkbox selections.
         * Supports multiple offers per item — discounts are additive.
         */
        function _updateOfferSummary(items) {
            var totalDisc = 0;
            var itemCount = 0;
            _selectedOffers = {};

            if (!_offerData || !_offerData.offers) return;

            var offerMap = {};
            for (var i = 0; i < _offerData.offers.length; i++) {
                offerMap[_offerData.offers[i].id_offer] = _offerData.offers[i];
            }

            // Collect checked offers grouped by row
            $('.offer-check:checked').each(function() {
                var offerId = parseInt($(this).val());
                var rowIdx  = parseInt($(this).data('row'));
                if (isNaN(offerId) || offerId === 0) return;

                var offer = offerMap[offerId];
                var item  = null;
                for (var j = 0; j < items.length; j++) {
                    if (items[j].row_index === rowIdx) { item = items[j]; break; }
                }
                if (!offer || !item) return;

                var disc = _calcOfferDiscount(offer, item);

                if (!_selectedOffers[rowIdx]) {
                    _selectedOffers[rowIdx] = {
                        offer_ids: [],
                        offer_names: [],
                        disc_amt: 0
                    };
                }
                _selectedOffers[rowIdx].offer_ids.push(offerId);
                _selectedOffers[rowIdx].offer_names.push(offer.offer_name);
                _selectedOffers[rowIdx].disc_amt += disc;
                totalDisc += disc;
            });

            // Count items with at least one offer selected and update per-item UI labels
            for (var ri in _selectedOffers) {
                if (_selectedOffers.hasOwnProperty(ri)) itemCount++;
            }

            // Update individual item discount/net-cost labels in the DOM
            $('.offer-item-card').each(function() {
                var cardRow = parseInt($(this).data('row'));
                var itemObj = null;
                for (var j = 0; j < items.length; j++) {
                    if (items[j].row_index === cardRow) { itemObj = items[j]; break; }
                }
                if (!itemObj) return;

                var $card = $(this);
                var selItem = _selectedOffers[cardRow];

                if (selItem && selItem.disc_amt > 0) {
                    var netCost = itemObj.item_cost - selItem.disc_amt;
                    if (netCost < 0) netCost = 0;

                    $card.find('.item-discount-label').text('Discount: -\u20b9 ' + selItem.disc_amt.toFixed(2)).show();
                    $card.find('.item-net-cost-label').text('Net Cost: \u20b9 ' + netCost.toFixed(2)).show();
                } else {
                    $card.find('.item-discount-label').hide();
                    $card.find('.item-net-cost-label').hide();
                }
            });

            $('#offerTotalDiscount').text('\u20b9 ' + totalDisc.toFixed(2));
            $('#offerItemCount').text(itemCount);
        }

        /**
         * Apply selected offers: inject hidden fields into form rows.
         */
        function _applySelectedOffers(items) {
            var totalOfferDisc = 0;

            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var sel  = _selectedOffers[item.row_index];
                var $row = item.dom_row;

                // Remove any previous offer hidden fields
                $row.find('.offer_hidden_id, .offer_hidden_amt').remove();

                if (sel && sel.offer_ids && sel.offer_ids.length > 0) {
                    // Store CSV of offer IDs (e.g., "4,7")
                    var fieldName = item.table_type + '[offer_id][]';
                    var amtName   = item.table_type + '[offer_disc_amt][]';
                    var csvIds    = sel.offer_ids.join(',');
                    var discAmt   = parseFloat(sel.disc_amt.toFixed(2));
                    $row.append('<input type="hidden" class="offer_hidden_id" name="' + fieldName + '" value="' + csvIds + '">');
                    $row.append('<input type="hidden" class="offer_hidden_amt" name="' + amtName + '" value="' + discAmt + '">');
                    totalOfferDisc += discAmt;
                } else {
                    var fieldName2 = item.table_type + '[offer_id][]';
                    var amtName2   = item.table_type + '[offer_disc_amt][]';
                    $row.append('<input type="hidden" class="offer_hidden_id" name="' + fieldName2 + '" value="">');
                    $row.append('<input type="hidden" class="offer_hidden_amt" name="' + amtName2 + '" value="0">');
                }
            }

            // Set master-level total
            $('#total_offer_disc_amt').val(totalOfferDisc.toFixed(2));

            if (typeof calculateFinalCost === 'function') {
                calculateFinalCost();
            }
        }

        /**
         * Clear all offer fields (when skipping offers).
         */
        function _clearOfferFields(items) {
            for (var i = 0; i < items.length; i++) {
                var $row = items[i].dom_row;
                $row.find('.offer_hidden_id, .offer_hidden_amt').remove();
                var fieldName = items[i].table_type + '[offer_id][]';
                var amtName   = items[i].table_type + '[offer_disc_amt][]';
                $row.append('<input type="hidden" class="offer_hidden_id" name="' + fieldName + '" value="">');
                $row.append('<input type="hidden" class="offer_hidden_amt" name="' + amtName + '" value="0">');
            }
            $('#total_offer_disc_amt').val('0');

            if (typeof calculateFinalCost === 'function') {
                calculateFinalCost();
            }
        }

        /**
         * Escape HTML for safe rendering in modal.
         */
        function _escHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

})();
