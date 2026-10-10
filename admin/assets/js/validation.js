/**
 * ============================================================
 * Validation - Common Input Validation Library
 * ============================================================
 * Centralized validation functions for all modules.
 * Include this file globally (via footer.php) BEFORE any
 * module-specific JS files.
 *
 * Usage:
 *   1. Add data-validate="rule_name" to any <input> element
 *   2. Rules are auto-applied on DOM ready and on dynamically
 *      added elements via delegated events.
 *   3. Or call functions directly:
 *      Validation.sanitize(value, 'nameField')
 *      Validation.isValid(value, 'alphanumeric')
 *      etc.
 *
 * Duplicate Check Usage (data attributes):
 *   Add these attributes to any input for auto duplicate checking:
 *     data-duplicate-table="metal"        - DB table name
 *     data-duplicate-column="metal"       - Column to check
 *     data-duplicate-id-column="id_metal" - Primary key column (for edit exclude)
 *     data-duplicate-edit-id=""           - ID to exclude (set via JS for edit mode)
 *     data-duplicate-label="Metal Name"   - Label for error message
 *
 * Toast Usage:
 *   Validation.showToast('Message', 'warning')  // Types: success, warning, danger, info
 * ============================================================
 */

var Validation = (function () {

    // ─── Toast Debounce Timestamps ─────────────────────────────
    var lastToastTime = 0;
    var _delegateToastTime = 0;

    // ─── Regex Patterns ───────────────────────────────────────
    var PATTERNS = {
        // Only letters (no spaces, no numbers, no special chars)
        lettersOnly: /[^A-Za-z]/g,
        // Only letters and spaces
        textOnly: /[^A-Za-z\s]/g,
        // Letters, numbers, spaces
        alphanumeric: /[^A-Za-z0-9\s]/g,
        // Letters, numbers, underscore, hyphen (NO spaces)
        alphanumericStrict: /[^A-Za-z0-9_\-]/g,
        // Letters, numbers, spaces, underscore, hyphen
        nameField: /[^A-Za-z0-9\s_\-]/g,
        // Only digits
        numericOnly: /[^0-9]/g,
        // Digits and dot (for decimal numbers)
        decimal: /[^0-9.]/g,
        // Alphanumeric without spaces (for codes)
        codeField: /[^A-Za-z0-9]/g,
        // HSN/SAC codes: digits only
        hsnCode: /[^0-9]/g,
        // Email basic pattern
        email: /[^A-Za-z0-9@._\-]/g,
        // Phone: digits, plus, hyphen, spaces
        phone: /[^0-9+\-\s]/g,
        // GST number pattern: alphanumeric
        gstNumber: /[^A-Za-z0-9]/g,
        // PAN number: alphanumeric uppercase
        panNumber: /[^A-Za-z0-9]/g,
        // Pincode: digits only
        pincode: /[^0-9]/g,
        // Address: letters, numbers, common punctuation
        address: /[^A-Za-z0-9\s,.\-\/\#\(\)]/g,
        // Description: allows most printable chars except dangerous ones
        description: null,
        // Letters, numbers, spaces, common punctuation for general text
        generalText: /[^A-Za-z0-9\s,.\-\/\(\)\&\'\"]/g,
        // Tax name: letters, numbers, spaces, %, underscore, hyphen
        taxName: /[^A-Za-z0-9\s%_\-]/g,
        // Tax code: letters, numbers, spaces, %, underscore, hyphen
        taxCode: /[^A-Za-z0-9\s%_\-]/g,
        // Tax percentage: numbers, decimal point, %
        taxPercentage: /[^0-9.%]/g,
        // Charge code: letters, numbers, dot, underscore, hyphen
        chargeCode: /[^A-Za-z0-9._\-]/g
    };

    // ─── Validation Rules Config ──────────────────────────────
    // Each rule maps to: { regex, max, msg }
    // regex: characters to STRIP (inverse pattern)
    // max: maximum character length (null = no limit)
    // msg: user-friendly field description for error messages
    var RULES = {
        // === LETTERS ONLY (No spaces, numbers, or special chars) ===
        lettersOnly: { regex: PATTERNS.lettersOnly, max: 50, msg: 'Only letters are allowed. No numbers, spaces or special characters' },

        // === NAME FIELDS (Letters, Numbers, Spaces, Underscore, Hyphen) ===
        nameField: { regex: PATTERNS.nameField, max: 50, msg: 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed' },

        // === TEXT ONLY (Letters and Spaces) ===
        textOnly: { regex: PATTERNS.textOnly, max: 50, msg: 'Only letters and spaces are allowed' },

        // === ALPHANUMERIC (Letters, Numbers, Spaces) ===
        alphanumeric: { regex: PATTERNS.alphanumeric, max: 30, msg: 'Only letters, numbers and spaces are allowed' },

        // === ALPHANUMERIC STRICT (Letters, Numbers, Underscore, Hyphen — no spaces) ===
        alphanumericStrict: { regex: PATTERNS.alphanumericStrict, max: 30, msg: 'Only letters, numbers, underscore and hyphen are allowed' },

        // === CODE FIELDS (Short codes — alphanumeric, no spaces) ===
        codeField: { regex: PATTERNS.codeField, max: 10, msg: 'Only letters and numbers are allowed' },
        alphanumericNoSpace: { regex: PATTERNS.codeField, max: 50, msg: 'Only letters and numbers are allowed (no spaces or special characters)' },
        shortCode: { regex: PATTERNS.alphanumericStrict, max: 5, msg: 'Only letters, numbers, underscore (_) and hyphen (-) are allowed (max 5)' },

        // === NUMERIC FIELDS ===
        numericOnly: { regex: PATTERNS.numericOnly, max: 15, msg: 'Only numbers are allowed' },
        decimal: { regex: PATTERNS.decimal, max: 15, msg: 'Only numbers and decimal point are allowed' },

        // === SPECIFIC FIELD TYPES ===
        hsnCode: { regex: PATTERNS.numericOnly, max: 8, msg: 'Only numbers allowed (max 8 digits)' },
        taxName: { regex: PATTERNS.taxName, max: 30, msg: 'Only letters, numbers, spaces, %, underscore (_) and hyphen (-) are allowed' },
        taxCode: { regex: PATTERNS.taxCode, max: 10, msg: 'Only letters, numbers, spaces, %, underscore (_) and hyphen (-) are allowed' },
        taxPercentage: { regex: PATTERNS.taxPercentage, max: 10, msg: 'Only numbers, decimal point and % are allowed' },
        email: { regex: PATTERNS.email, max: 100, msg: 'Invalid characters in email' },
        phone: { regex: PATTERNS.phone, max: 15, msg: 'Only numbers, +, - and spaces are allowed' },
        gstNumber: { regex: PATTERNS.gstNumber, max: 15, msg: 'Only letters and numbers are allowed for GST' },
        panNumber: { regex: PATTERNS.panNumber, max: 10, msg: 'Only letters and numbers are allowed for PAN' },
        pincode: { regex: PATTERNS.pincode, max: 6, msg: 'Only numbers are allowed for pincode' },
        address: { regex: PATTERNS.address, max: 200, msg: 'Special characters are not allowed in address' },
        description: { regex: PATTERNS.description, max: 300, msg: '' },
        generalText: { regex: PATTERNS.generalText, max: 200, msg: 'Some special characters are not allowed' },

        // === CATALOG MASTER SPECIFIC ===
        masterName: { regex: PATTERNS.nameField, max: 30, msg: 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed' },
        categoryName: { regex: PATTERNS.nameField, max: 30, msg: 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed' },
        metalName: { regex: PATTERNS.nameField, max: 30, msg: 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed' },
        metalCode: { regex: PATTERNS.nameField, max: 6, msg: 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed' },
        productName: { regex: PATTERNS.nameField, max: 50, msg: 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed' },
        productCode: { regex: PATTERNS.codeField, max: 3, msg: 'Only letters and numbers are allowed (max 3 characters)' },
        chargeValue: { regex: PATTERNS.decimal, max: 6, msg: 'Only numbers and decimal point are allowed (max 6 digits)' },
        chargeCode: { regex: PATTERNS.chargeCode, max: 9, msg: 'Only letters, numbers, dot (.), underscore (_) and hyphen (-) are allowed (max 5 before dot, 3 after dot)' },
        shapeName: { regex: PATTERNS.nameField, max: 30, msg: 'Only letters, numbers, spaces, underscore and hyphen are allowed' },
        designName: { regex: PATTERNS.nameField, max: 50, msg: 'Only letters, numbers, spaces, underscore and hyphen are allowed' },
        stoneName: { regex: PATTERNS.nameField, max: 30, msg: 'Only letters, numbers, spaces, underscore and hyphen are allowed' },
        materialName: { regex: PATTERNS.nameField, max: 30, msg: 'Only letters, numbers, spaces, underscore and hyphen are allowed' },
        maxMarkup: { regex: PATTERNS.numericOnly, max: 3, msg: 'Only numbers are allowed' },
        minMarkup: { regex: PATTERNS.numericOnly, max: 3, msg: 'Only numbers are allowed' },
        noOfPieces: { regex: PATTERNS.numericOnly, max: 5, msg: 'Only numbers are allowed' },
        // ret_size Units field — short unit names like cm, mm, size (max 20)
        sizeUnits: { regex: PATTERNS.nameField, max: 20, msg: 'Only letters, numbers, spaces, underscore (_) and hyphen (-) are allowed (max 20 characters)' },

        // === MASTER NAME WITH SPACES (Cancel Reason, Section name, etc.) ===
        // Allows: letters, numbers, spaces, hyphen, dot, slash
        // Blocks: quotes, semicolons, angle brackets, @, #, $, etc.
        masterNameWithSpace: { regex: /[^A-Za-z0-9\s\-\.\/]/g, max: 100, msg: "Only letters, numbers, spaces, hyphen (-), dot (.) and slash (/) are allowed. Characters like quotes, semicolons, < > are not permitted" },

        // === SUPPLIER CATALOG SPECIFIC ===
        supplierSize: { regex: PATTERNS.numericOnly, max: 10, msg: 'Only numbers are allowed (max 10 digits)' },
        supplierWeight: { regex: PATTERNS.decimal, max: 10, msg: 'Only numbers and decimal point are allowed (max 10 characters, e.g. 3000.000)' }

    };

    // ─── Core Sanitizer ───────────────────────────────────────
    /**
     * Sanitize a value based on a rule name.
     * @param {string} value - Input value to sanitize
     * @param {string} ruleName - Name of the rule from RULES object
     * @returns {string} Sanitized value
     */
    function sanitize(value, ruleName) {
        var rule = RULES[ruleName];
        if (!rule) {
            console.warn('[Validation] Unknown rule: ' + ruleName);
            return value;
        }
        if (rule.regex) {
            value = value.replace(rule.regex, '');
        }
        if (rule.max) {
            value = value.substring(0, rule.max);
        }
        return value;
    }

    // ─── Validator (returns true/false) ───────────────────────
    /**
     * Check if a value is valid for a given rule.
     * @param {string} value - Input value to validate
     * @param {string} ruleName - Name of the rule
     * @returns {boolean} true if valid
     */
    function isValid(value, ruleName) {
        var rule = RULES[ruleName];
        if (!rule) return true;
        if (rule.regex) {
            // Reset lastIndex BEFORE test to avoid intermittent false positives
            rule.regex.lastIndex = 0;
            if (rule.regex.test(value)) {
                return false;
            }
        }
        if (rule.max && value.length > rule.max) {
            return false;
        }
        return true;
    }

    // ─── Get Error Message ────────────────────────────────────
    function getMessage(ruleName) {
        var rule = RULES[ruleName];
        if (!rule) return '';
        var msg = rule.msg || '';
        if (rule.max) {
            msg += (msg ? '. ' : '') + 'Maximum ' + rule.max + ' characters allowed';
        }
        return msg;
    }

    // ─── Common Toast Notification ────────────────────────────
    /**
     * Show a toast notification. Uses jquery.toaster if available,
     * falls back to #chit_alert Bootstrap alert.
     * @param {string} message - Message text
     * @param {string} type    - 'success', 'warning', 'danger', 'info' (default: 'warning')
     * @param {string} title   - Optional title (default: auto from type)
     */
    function showToast(message, type, title) {
        type = type || 'warning';
        if (!title) {
            var titles = { success: 'Success!', warning: 'Warning!', danger: 'Error!', info: 'Info' };
            title = titles[type] || 'Notice';
        }

        // Try jquery.toaster first
        if (typeof $.toaster === 'function') {
            $.toaster({ priority: type, title: title, message: message });
            return;
        }

        // Fallback: inject into #chit_alert or #error-msg (whichever exists)
        var alertHtml = '<div class="alert alert-' + type + ' alert-dismissable">' +
            '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
            '<strong>' + title + '</strong> ' + message +
            '</div>';

        var $target = $('#chit_alert');
        if (!$target.length) $target = $('#error-msg');
        if (!$target.length) $target = $('#error-msg1');

        if ($target.length) {
            $target.html(alertHtml);
            // Auto-dismiss after 5 seconds
            setTimeout(function () { $target.find('.alert').fadeOut(400, function () { $(this).remove(); }); }, 5000);
        }
    }

    // ─── Apply Validation to Element ──────────────────────────
    /**
     * Apply real-time input sanitization to a single element.
     * @param {jQuery} $el - jQuery element
     * @param {string} ruleName - Rule name
     */
    function applyToElement($el, ruleName) {
        var rule = RULES[ruleName];
        if (!rule) return;

        // Mark element as directly bound so delegated handler skips toast for it
        $el.attr('data-lmx-direct-bound', '1');

        $el.on('input.lmxValidate', function () {
            var val = $(this).val();
            var cursorPos = this.selectionStart;
            var origLen = val.length;
            // Silent mode: sanitize only, skip toast (module-level validator handles its own toast)
            var silentMode = $(this).attr('data-validate-silent') === '1';

            if (rule.regex) {
                val = val.replace(rule.regex, '');
            }
            if (rule.max) {
                val = val.substring(0, rule.max);
            }

            if (val !== $(this).val()) {
                $(this).val(val);
                // Adjust cursor position after stripping characters
                var diff = origLen - val.length;
                var newPos = Math.max(0, cursorPos - diff);
                try {
                    this.setSelectionRange(newPos, newPos);
                } catch (e) {
                    // Some input types don't support setSelectionRange
                }
                // Show toast when invalid characters stripped (debounced 3s) — skip if silent
                if (!silentMode) {
                    var now = Date.now();
                    if (rule.msg && diff > 0 && (now - lastToastTime > 3000)) {
                        lastToastTime = now;
                        showToast(rule.msg, 'warning');
                    }
                }
            }
            // Show toast when max length reached (debounced 3s) — skip if silent
            if (!silentMode) {
                var elMax = parseInt($el.attr('maxlength')) || rule.max;
                var now2 = Date.now();
                if (elMax && val.length >= elMax && origLen >= elMax && (now2 - lastToastTime > 3000)) {
                    lastToastTime = now2;
                    showToast('Maximum ' + elMax + ' characters allowed', 'warning');
                }
            }
        });

        // Also handle paste events
        $el.on('paste.lmxValidate', function () {
            var $this = $(this);
            setTimeout(function () {
                var val = $this.val();
                if (rule.regex) {
                    val = val.replace(rule.regex, '');
                }
                if (rule.max) {
                    val = val.substring(0, rule.max);
                }
                $this.val(val);
            }, 10);
        });
    }

    // ─── Apply Validation by ID + Rule Map ────────────────────
    /**
     * Apply validation rules to elements by their IDs.
     * @param {Object} ruleMap - { elementId: ruleName, ... }
     * Example: LMX_Validate.applyRules({ 'category_name': 'masterName', 'hsn_code': 'hsnCode' })
     */
    function applyRules(ruleMap) {
        $.each(ruleMap, function (id, ruleName) {
            var $el = $('#' + id);
            if ($el.length) {
                applyToElement($el, ruleName);
            }
        });
    }

    // ─── Auto-initialize from data-validate attributes ────────
    /**
     * Auto-scan DOM for elements with data-validate="ruleName"
     * and apply validation. Called automatically on DOM ready.
     */
    function autoInit() {
        $('[data-validate]').each(function () {
            // Skip elements with data-validate-silent — module-level validator handles them
            if ($(this).attr('data-validate-silent') === '1') return;
            var ruleName = $(this).attr('data-validate');
            if (ruleName && RULES[ruleName]) {
                applyToElement($(this), ruleName);
            }
        });
    }

    // ─── Delegated Event Handler for Dynamic Elements ─────────
    // NOTE: This handles ONLY elements NOT yet directly bound by applyToElement().
    // For elements with data-lmx-direct-bound, sanitization still runs but toast is skipped
    // to prevent double-toast when both applyToElement + delegated handler fire on the same input.
    function initDelegatedEvents() {
        $(document).on('input.lmxValidateDelegate', '[data-validate]', function () {
            var ruleName = $(this).attr('data-validate');
            var rule = RULES[ruleName];
            if (!rule) return;

            // Fully skip elements with data-validate-silent — module-level validator handles them
            if ($(this).attr('data-validate-silent') === '1') return;

            // Skip toast if this element is already directly bound (applyToElement handles it)
            var isDirectBound = $(this).attr('data-lmx-direct-bound') === '1';

            var val = $(this).val();
            var cursorPos = this.selectionStart;
            var origLen = val.length;

            if (rule.regex) {
                val = val.replace(rule.regex, '');
            }
            if (rule.max) {
                val = val.substring(0, rule.max);
            }

            if (val !== $(this).val()) {
                $(this).val(val);
                var diff = origLen - val.length;
                var newPos = Math.max(0, cursorPos - diff);
                try {
                    this.setSelectionRange(newPos, newPos);
                } catch (e) { }
                // Only show toast if NOT already handled by direct binding
                if (!isDirectBound) {
                    var now = Date.now();
                    if (rule.msg && diff > 0 && (now - _delegateToastTime > 3000)) {
                        _delegateToastTime = now;
                        showToast(rule.msg, 'warning');
                    }
                }
            }
            // Only show max-length toast if NOT already handled by direct binding
            if (!isDirectBound) {
                var elMax = parseInt($(this).attr('maxlength')) || rule.max;
                var now2 = Date.now();
                if (elMax && val.length >= elMax && origLen >= elMax && (now2 - _delegateToastTime > 3000)) {
                    _delegateToastTime = now2;
                    showToast('Maximum ' + elMax + ' characters allowed', 'warning');
                }
            }
        });

        $(document).on('paste.lmxValidateDelegate', '[data-validate]', function () {
            var $this = $(this);
            var ruleName = $this.attr('data-validate');
            var rule = RULES[ruleName];
            if (!rule) return;

            setTimeout(function () {
                var val = $this.val();
                if (rule.regex) {
                    val = val.replace(rule.regex, '');
                }
                if (rule.max) {
                    val = val.substring(0, rule.max);
                }
                $this.val(val);
            }, 10);
        });
    }

    // ─── Duplicate Entry Check (AJAX) ──────────────────────────
    /**
     * Check if a value already exists in a database table.
     * @param {Object} options - { table, column, value, excludeId, idColumn }
     *   table    : DB table name (e.g. 'metal')
     *   column   : Column to check (e.g. 'metal')
     *   value    : Value to check for
     *   excludeId: (optional) ID to exclude (for edit mode)
     *   idColumn : (optional) Primary key column name (default: 'id')
     * @param {Function} callback - function(isDuplicate, message)
     */
    function checkDuplicate(options, callback) {
        if (!options || !options.table || !options.column || !options.value) {
            if (callback) callback(false, '');
            return;
        }

        $.ajax({
            url: base_url + 'index.php/admin_ret_catalog/check_duplicate',
            type: 'POST',
            dataType: 'json',
            data: {
                table: options.table,
                column: options.column,
                value: $.trim(options.value),
                exclude_id: options.excludeId || '',
                id_column: options.idColumn || 'id'
            },
            success: function (response) {
                if (response && response.exists) {
                    if (callback) callback(true, response.msg || 'This entry already exists!');
                } else {
                    if (callback) callback(false, '');
                }
            },
            error: function () {
                if (callback) callback(false, '');
            }
        });
    }

    // ─── Auto Duplicate Check via Data Attributes ─────────────
    /**
     * Auto-scan DOM for inputs with data-duplicate-table attribute
     * and attach blur-based duplicate checking.
     *
     * Required data attributes:
     *   data-duplicate-table="metal"         - Table name
     *   data-duplicate-column="metal"        - Column name
     *   data-duplicate-id-column="id_metal"  - PK column (for edit exclusion)
     *   data-duplicate-label="Metal Name"    - Display label for toast
     *
     * Optional:
     *   data-duplicate-edit-id=""            - Set via JS when editing
     */
    function initDuplicateChecks() {
        $(document).on('blur.lmxDuplicate', '[data-duplicate-table]', function () {
            var $el = $(this);
            var val = $.trim($el.val());
            if (!val) return;

            var table = $el.attr('data-duplicate-table');
            var column = $el.attr('data-duplicate-column');
            var idColumn = $el.attr('data-duplicate-id-column') || 'id';
            var label = $el.attr('data-duplicate-label') || 'Entry';
            var excludeId = $el.attr('data-duplicate-edit-id') || '';

            checkDuplicate({
                table: table,
                column: column,
                value: val,
                excludeId: excludeId,
                idColumn: idColumn
            }, function (isDuplicate, msg) {
                if (isDuplicate) {
                    showToast(label + ' "' + val + '" already exists! Please enter a different value.', 'danger', 'Duplicate Entry!');
                    $el.val('');
                    $el.focus();
                    // Mark as invalid
                    $el.closest('.form-group').addClass('has-error');
                } else {
                    $el.closest('.form-group').removeClass('has-error');
                }
            });
        });
    }

    // ─── Public API ───────────────────────────────────────────
    return {
        PATTERNS: PATTERNS,
        RULES: RULES,
        sanitize: sanitize,
        isValid: isValid,
        getMessage: getMessage,
        applyToElement: applyToElement,
        applyRules: applyRules,
        autoInit: autoInit,
        initDelegatedEvents: initDelegatedEvents,
        checkDuplicate: checkDuplicate,
        initDuplicateChecks: initDuplicateChecks,
        showToast: showToast
    };

})();

// ─── Auto-Initialize on DOM Ready ────────────────────────────
$(function () {
    Validation.autoInit();
    Validation.initDelegatedEvents();
    Validation.initDuplicateChecks();

    // ─── Global: Reset Add Modal Forms on Open ───────────────
    // When any "Add" modal (id="confirm-add") is opened,
    // clear all form fields so stale data from previous close is wiped.
    // This covers ALL Catalog Master sub-modules automatically.
    $('#confirm-add').on('show.bs.modal', function () {
        var $modal = $(this);

        // Clear text inputs and textareas
        $modal.find('input[type="text"], input[type="number"], input[type="email"], input[type="tel"], textarea').val('');

        // Clear hidden inputs (except status and CSRF tokens)
        $modal.find('input[type="hidden"]').each(function () {
            var id = $(this).attr('id') || '';
            var name = $(this).attr('name') || '';
            // Don't clear status fields, CSRF tokens, or CI hidden fields
            if (id.indexOf('status') === -1 && name.indexOf('csrf') === -1 && name.indexOf('ci_csrf') === -1) {
                $(this).val('');
            }
        });

        // Reset file inputs
        $modal.find('input[type="file"]').val('');

        // Reset image previews to default
        $modal.find('img[id$="_preview"]').each(function () {
            var defaultSrc = $(this).data('default-src');
            if (!defaultSrc) {
                // Try to find base_url no_image path
                if (typeof base_url !== 'undefined') {
                    defaultSrc = base_url + 'assets/img/no_image.png';
                }
            }
            if (defaultSrc) {
                $(this).attr('src', defaultSrc);
            }
        });

        // Reset select dropdowns to first option (except multi-selects)
        $modal.find('select:not([multiple])').each(function () {
            $(this).prop('selectedIndex', 0);
            // Trigger change for Select2 sync
            if ($(this).data('select2')) {
                $(this).trigger('change');
            }
        });

        // Clear multi-select selections
        $modal.find('select[multiple]').val([]);

        // Clear error messages
        $modal.find('.help-block').html('');
        $modal.find('#error-msg').html('');
        $modal.find('.has-error').removeClass('has-error');

        // Clear duplicate edit IDs (reset for add mode)
        $modal.find('[data-duplicate-edit-id]').attr('data-duplicate-edit-id', '');

        // Reset checkboxes to default checked state
        try {
            $modal.find('input[type="checkbox"].status').bootstrapSwitch('state', true);
        } catch (e) { /* bootstrapSwitch may not be initialized yet */ }
    });

    // Re-apply validation bindings when Add modal is fully shown
    // This guarantees data-validate rules are active even after modal reset
    $('#confirm-add').on('shown.bs.modal', function () {
        $(this).find('[data-validate]').each(function () {
            // Skip elements with data-validate-silent — module-level validator handles them
            if ($(this).attr('data-validate-silent') === '1') return;
            var ruleName = $(this).attr('data-validate');
            if (ruleName && Validation.RULES[ruleName]) {
                // Unbind first to avoid double-binding, then re-bind
                $(this).off('input.lmxValidate paste.lmxValidate');
                Validation.applyToElement($(this), ruleName);
            }
        });
    });

    // Re-apply validation bindings when Edit modal is fully shown
    $('#confirm-edit').on('shown.bs.modal', function () {
        $(this).find('[data-validate]').each(function () {
            // Skip elements with data-validate-silent — module-level validator handles them
            if ($(this).attr('data-validate-silent') === '1') return;
            var ruleName = $(this).attr('data-validate');
            if (ruleName && Validation.RULES[ruleName]) {
                $(this).off('input.lmxValidate paste.lmxValidate');
                Validation.applyToElement($(this), ruleName);
            }
        });
    });
});
