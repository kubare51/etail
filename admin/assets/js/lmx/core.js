/**
 * ============================================================
 * LMX Core JS — Standardized JS Utilities
 * ============================================================
 * Safe AJAX, notifications, form parsing, and math utilities.
 * Used by BOTH old and new application screens.
 * 
 * @version 1.0.0
 * @created 2026-07-01
 * ============================================================
 */

var LMX = window.LMX || {};

(function($) {
    "use strict";

    /**
     * Safe AJAX wrapper.
     * - Auto-handles CSRF token
     * - Works with BOTH old (class="success") and new (status=true) response formats
     * - Never fails silently
     */
    LMX.ajax = function(url, data, onSuccess, onError) {
        // Assume base_url is globally available
        var fullUrl = typeof base_url !== 'undefined' ? base_url + "admin/index.php/" + url : url;
        
        $.ajax({
            url: fullUrl,
            type: "POST",
            data: data,
            dataType: "json",
            success: function(response) {
                // Compatible with both lmx_success (status) and lmx_legacy_success (class)
                var isSuccess = (response.status === true) || (response.class === "success");
                
                if (isSuccess) {
                    if (typeof onSuccess === 'function') onSuccess(response);
                } else {
                    var msg = response.message || response.msg || "Operation failed";
                    if (typeof onError === 'function') {
                        onError(response);
                    } else {
                        LMX.notify(msg, "error");
                    }
                }
            },
            error: function(xhr, status, err) {
                LMX.notify("Server error: " + (err || "Please try again"), "error");
                if (typeof onError === 'function') onError({message: err});
            }
        });
    };

    /**
     * Notification helper.
     * Tries toastr, then sweetalert, then fallback alert.
     */
    LMX.notify = function(message, type) {
        type = type || "success";
        if (typeof toastr !== "undefined") {
            var tType = type === "error" ? "error" : "success";
            toastr[tType](message);
        } else if (typeof swal !== "undefined") {
            var sType = type === "error" ? "error" : "success";
            swal({title: message, type: sType});
        } else {
            alert(message);
        }
    };

    /**
     * Safe form serialization to JS Object.
     */
    LMX.formData = function(formSelector) {
        var data = {};
        $(formSelector).serializeArray().forEach(function(item) {
            // Handle arrays (e.g. checkboxes named 'chk[]')
            if (data[item.name]) {
                if (!Array.isArray(data[item.name])) {
                    data[item.name] = [data[item.name]];
                }
                data[item.name].push(item.value);
            } else {
                data[item.name] = item.value;
            }
        });
        return data;
    };

    /**
     * Safe parseFloat with NaN guard. Use for ALL financial calculations.
     */
    LMX.num = function(val) {
        var n = parseFloat(val);
        return isNaN(n) ? 0 : n;
    };

    /**
     * Standard money formatter.
     */
    LMX.money = function(val) {
        return LMX.num(val).toFixed(2);
    };

    /**
     * Generic Save-Button Guard.
     * Scans ALL data-validate inputs inside a container (modal/form).
     * Returns { valid: true/false, errors: ['Field X is required', ...] }
     *
     * Usage:
     *   var result = LMX.validateForm('#confirm-add');
     *   if (!result.valid) { LMX.notify(result.errors.join('\n'), 'error'); return; }
     */
    LMX.validateForm = function(formSelector) {
        var errors = [];
        $(formSelector).find('[data-validate]').each(function() {
            var $field = $(this);
            var rule = $field.attr('data-validate');
            var label = $field.closest('.form-group').find('label').text().replace('*', '').trim();
            if (!label) label = $field.attr('name') || 'Field';
            var value = ($field.val() || '').trim();

            // Required check
            if ($field.prop('required') && value === '') {
                errors.push(label + ' is required');
                $field.closest('.form-group').addClass('has-error');
                return; // continue to next field
            }

            // Rule check (if Validation object exists from validation.js)
            if (value && typeof Validation !== 'undefined' && typeof Validation.isValid === 'function') {
                if (!Validation.isValid(value, rule)) {
                    var msg = (typeof Validation.getErrorMsg === 'function')
                        ? Validation.getErrorMsg(rule)
                        : 'Invalid format';
                    errors.push(label + ': ' + msg);
                    $field.closest('.form-group').addClass('has-error');
                }
            }
        });

        // Clear previous error styling on valid fields
        $(formSelector).find('.form-group.has-error').each(function() {
            var $fg = $(this);
            var $field = $fg.find('[data-validate]');
            var value = ($field.val() || '').trim();
            var hasError = false;
            if ($field.prop('required') && value === '') hasError = true;
            if (!hasError) $fg.removeClass('has-error');
        });

        return { valid: errors.length === 0, errors: errors };
    };

    /**
     * Double-Submit Prevention.
     * Disables a button and changes text while an AJAX operation runs.
     *
     * Usage:
     *   var restore = LMX.preventDoubleSubmit('#save_btn', 'Saving...');
     *   $.ajax({ ..., complete: restore });
     *
     * @param  {string} btnSelector  jQuery selector for the button
     * @param  {string} loadingText  Text to show while saving (default: 'Saving...')
     * @return {function}            Call this function to restore the button
     */
    LMX.preventDoubleSubmit = function(btnSelector, loadingText) {
        var $btn = $(btnSelector);
        var originalText = $btn.text();
        var originalHtml = $btn.html();
        loadingText = loadingText || 'Saving...';

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + loadingText);

        return function() {
            $btn.prop('disabled', false).html(originalHtml);
        };
    };

    /**
     * Confirmation dialog before destructive actions.
     *
     * Usage:
     *   LMX.confirmAction('Deactivate this Theme?', function() { /* proceed */ });
     */
    LMX.confirmAction = function(message, onConfirm, onCancel) {
        if (typeof swal !== 'undefined') {
            swal({
                title: 'Are you sure?',
                text: message,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, proceed',
                cancelButtonText: 'Cancel'
            }, function(confirmed) {
                if (confirmed && typeof onConfirm === 'function') {
                    onConfirm();
                } else if (!confirmed && typeof onCancel === 'function') {
                    onCancel();
                }
            });
        } else {
            if (confirm(message)) {
                if (typeof onConfirm === 'function') onConfirm();
            } else {
                if (typeof onCancel === 'function') onCancel();
            }
        }
    };

})(jQuery);
