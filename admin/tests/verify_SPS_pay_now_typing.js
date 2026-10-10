/*
 * SPS: Supplier Payment "Pay Now" box - keystroke behaviour
 *
 * Run: node admin/tests/verify_SPS_pay_now_typing.js
 *
 * Regression cover for a defect reported from the Supplier Payment screen: after typing
 * a single character into Pay Now, the cursor jumped to Net Amount and the amount could
 * not be completed. Two causes, both fixed in ret_purchase_order.js:
 *
 *   1. sps_sync_row() compared box.val() against amount.toFixed(2) as STRINGS, so typing
 *      "1" rewrote the box to "1.00" on the next keystroke and no multi-digit figure
 *      could ever be entered.
 *   2. calculateSelectedTotal() ends with $("#balance_amount").focus(). That predates
 *      this feature and was harmless while the function only ran on a checkbox click,
 *      but it now also runs on every keyup in a Pay Now box.
 *
 * sps_sync_row() and the relevant part of calculateSelectedTotal() are reproduced below
 * verbatim from admin/assets/js/ret_purchase_order.js and driven against a minimal
 * jQuery stub - jsdom is not installed in this repo. If either function is edited, copy
 * the change here too or this file stops testing the shipped code.
 *
 * The last case guards the fix from over-reaching: focus must STILL move to Net Amount
 * when the total changes through a checkbox rather than through typing.
 */

// ------------------------------------------------------------------ DOM stub
var DOM = {};
var activeElementId = null;

function El(id, val, cls) {
    this.id = id; this._val = val; this._cls = cls || []; this.length = 1;
}
El.prototype.val = function (v) {
    if (v === undefined) return this._val;
    this._val = String(v);
    return this;
};
El.prototype.is = function (sel) {
    if (sel === ':checked') return this._checked === true;
    return false;
};
El.prototype.hasClass = function (c) { return this._cls.indexOf(c) >= 0; };
El.prototype.prop = function (k, v) {
    if (v === undefined) return this[k];
    this[k] = v; return this;
};
El.prototype.text = function () { return this; };
El.prototype.focus = function () { activeElementId = this.id; return this; };
El.prototype.each = function () { return this; };

var EMPTY = { length: 0, val: function () { return undefined; }, is: function () { return false; },
              hasClass: function () { return false; }, focus: function () {}, text: function () { return this; },
              each: function () { return this; }, prop: function () { return this; } };

function $(sel) {
    if (typeof sel === 'object' && sel !== null) return sel;            // $(document.activeElement)
    var key = String(sel).replace(/\\\\/g, '').replace(/\\/g, '');       // unescape jQuery id escapes
    if (key.charAt(0) === '#') key = key.slice(1);
    return DOM[key] || EMPTY;
}

var document = {
    get activeElement() { return DOM[activeElementId] || EMPTY; }
};

// ------------------------------------------- functions under test (verbatim from source)
function sps_row_outstanding(rowId) {
    return parseFloat($('#billing\\[bill_amount_' + rowId + '\\]').val()) || 0;
}

function sps_sync_row(rowId) {
    var checked = $('#cb_' + rowId).is(':checked');
    var outstanding = sps_row_outstanding(rowId);
    var box = $('#amount_paid_' + rowId);

    var raw = box.length ? (parseFloat(box.val()) || 0) : outstanding;
    var amount = raw;

    if (amount > outstanding)   { amount = outstanding; }
    if (amount < 0)             { amount = 0; }

    if (box.length && Math.abs(amount - raw) > 0.0001) {
        box.val(amount.toFixed(2));
    }

    $('#billing\\[amount_paid_' + rowId + '\\]').val(checked ? amount.toFixed(2) : '0');

    return checked ? amount : 0;
}

function calculateSelectedTotal() {
    var total = sps_sync_row(0);
    total = Math.round(total * 100) / 100;
    $('#balance_amount').val(total.toFixed(2));
    if (!$(document.activeElement).hasClass('pay_amt_input')) {
        $('#balance_amount').focus();
    }
    return total;
}

// ------------------------------------------------------------------- harness
function reset(outstanding) {
    DOM = {};
    DOM['billing[bill_amount_0]'] = new El('billing[bill_amount_0]', String(outstanding));
    DOM['billing[amount_paid_0]'] = new El('billing[amount_paid_0]', '0');
    DOM['amount_paid_0']          = new El('amount_paid_0', '', ['pay_amt_input']);
    DOM['balance_amount']         = new El('balance_amount', '');
    DOM['cb_0']                   = new El('cb_0', ''); DOM['cb_0']._checked = true;
    activeElementId = 'amount_paid_0';   // operator is typing in the Pay Now box
}

var pass = 0, fail = 0;
function check(label, got, want) {
    var ok = String(got) === String(want);
    if (ok) { pass++; console.log('  PASS  ' + label); }
    else { fail++; console.log('  FAIL  ' + label + '\n        got  ' + got + '\n        want ' + want); }
}

// ============================================================ typing 15489, outstanding 155489
console.log('\n[typing "15489" into a bill outstanding 1,55,489.00]');
reset(155489.00);
var typed = '';
'15489'.split('').forEach(function (ch) {
    typed += ch;
    DOM['amount_paid_0'].val(typed);   // browser puts the character in the box
    calculateSelectedTotal();          // our keyup handler runs
    console.log('     after "' + ch + '" -> box=' + DOM['amount_paid_0'].val() +
                '  focus=' + activeElementId);
});
check('box holds the full typed amount', DOM['amount_paid_0'].val(), '15489');
check('focus never left the Pay Now box', activeElementId, 'amount_paid_0');
check('hidden posted amount', DOM['billing[amount_paid_0]'].val(), '15489.00');
check('net amount reflects allocation', DOM['balance_amount'].val(), '15489.00');

// ============================================================ decimals
console.log('\n[typing a decimal "1234.50"]');
reset(155489.00);
typed = '';
'1234.50'.split('').forEach(function (ch) {
    typed += ch;
    DOM['amount_paid_0'].val(typed);
    calculateSelectedTotal();
});
check('decimal survives typing', DOM['amount_paid_0'].val(), '1234.50');
check('focus retained', activeElementId, 'amount_paid_0');

// ============================================================ clamping still works
console.log('\n[over-typing past the outstanding balance]');
reset(1.10);
DOM['amount_paid_0'].val('11');
calculateSelectedTotal();
check('clamped down to outstanding', DOM['amount_paid_0'].val(), '1.10');
check('posted amount clamped too', DOM['billing[amount_paid_0]'].val(), '1.10');

// ============================================================ focus still moves on checkbox
console.log('\n[checkbox click - focus SHOULD still jump to Net Amount]');
reset(155489.00);
DOM['amount_paid_0'].val('155489.00');
activeElementId = 'cb_0';              // operator clicked the checkbox, not the box
calculateSelectedTotal();
check('old focus behaviour preserved', activeElementId, 'balance_amount');

console.log('\n==== ' + pass + ' passed, ' + fail + ' failed ====');
process.exit(fail > 0 ? 1 : 0);
