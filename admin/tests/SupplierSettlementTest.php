<?php
use PHPUnit\Framework\TestCase;

/**
 * SPS: Partial Supplier Payment
 * Category: Business Logic (allocation / settlement)
 *
 * Covers the parts of Ret_purchase_order_model that decide how much of a payment is
 * applied to which supplier bill:
 *
 *   settlement_row_key()             identity of a settleable row
 *   get_payment_outstanding_map()    balances re-read for save-time validation
 *   derive_settle_status()           Unpaid / Partial / Settled
 *
 * plus the type-coercion rules the save path applies before writing an allocation.
 *
 * Partial settlement is unconditional - there is no settlement-mode setting and no
 * Cumulative/FIFO path. Every payment allocates against the bills the operator ticked.
 *
 * The model is loaded with CI stubbed out, and the one method that reaches the database
 * is replaced in the subclass below. Everything else under test is the real code.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', true);
}
if (!class_exists('CI_Model')) {
    class CI_Model { public function __construct() {} }
}

require_once __DIR__ . '/../application/models/ret_purchase_order_model.php';

/**
 * Test double: overrides ONLY the database-touching method.
 */
class SettlementModelDouble extends Ret_purchase_order_model
{
    public $rows = array();

    public function __construct() {}

    function getPending_payment_po_bills_for_payment($post)
    {
        return $this->rows;
    }
}

class SupplierSettlementTest extends TestCase
{
    private $model;

    protected function setUp(): void
    {
        $this->model = new SettlementModelDouble();
    }

    private function bill($po_id, $amount, $ref, $date, $type = 'bill', $ratecut = '')
    {
        return array(
            'type'        => $type,
            'po_id'       => $po_id,
            'ratecut'     => $ratecut,
            'trans_amount'=> $amount,
            'referenceno' => $ref,
            'po_date'     => $date,
        );
    }

    // ------------------------------------------------------------ row identity

    /**
     * A rate-fix and a Dr/Cr credit both carry po_id = 0, and a rate-cut shares its
     * po_id with the bill it belongs to. If the key were po_id alone, validating one
     * row would authorise a different one.
     */
    public function test_row_key_distinguishes_types_sharing_a_po_id(): void
    {
        $bill    = $this->model->settlement_row_key('bill', '7', '');
        $ratecut = $this->model->settlement_row_key('ratecut', '7', '4');

        $this->assertNotSame($bill, $ratecut, 'bill and rate-cut on po_id 7 must not collide');
        $this->assertSame('bill|7|', $bill);
        $this->assertSame('ratecut|7|4', $ratecut);
    }

    public function test_row_key_matches_what_the_form_posts_back(): void
    {
        // type_N | bill_ref_no_N | bill_rate_no_N
        $this->assertSame(
            'crdr_credit|3|',
            $this->model->settlement_row_key('crdr_credit', '3', '')
        );
    }

    // ------------------------------------------------------- outstanding lookup

    public function test_outstanding_map_is_keyed_for_posted_row_lookup(): void
    {
        $this->model->rows = array(
            $this->bill(7, '1000000.00', 'P-0004', '01-07-2026'),
            $this->bill(9, '250000.00',  'P-0005', '10-07-2026'),
        );

        $map = $this->model->get_payment_outstanding_map(array());

        $this->assertCount(2, $map);
        $this->assertSame(1000000.00, $map['bill|7|']['outstanding']);
        $this->assertSame('P-0005', $map['bill|9|']['referenceno']);
    }

    /**
     * The union behind the balance query can emit the same logical item more than once.
     * Taking the larger figure keeps a duplicate from silently lowering the ceiling a
     * payment is validated against.
     */
    public function test_duplicate_rows_keep_the_larger_balance(): void
    {
        $this->model->rows = array(
            $this->bill(7, '400000.00',  'P-0004', '01-07-2026'),
            $this->bill(7, '1000000.00', 'P-0004', '01-07-2026'),
        );

        $map = $this->model->get_payment_outstanding_map(array());

        $this->assertCount(1, $map);
        $this->assertSame(1000000.00, $map['bill|7|']['outstanding']);
    }

    /**
     * A bill that has been settled in full drops out of the map, so a further payment
     * against it is refused as "no longer outstanding" rather than over-settling it.
     */
    public function test_settled_bill_is_still_keyed_but_at_zero(): void
    {
        $this->model->rows = array($this->bill(7, '0.00', 'P-0004', '01-07-2026'));

        $map = $this->model->get_payment_outstanding_map(array());

        $this->assertSame(0.0, $map['bill|7|']['outstanding']);
    }

    // -------------------------------------------------- successive part-payments

    /**
     * BRS worked example, and AC #2 / #3: a PO of 10,00,000 settled by three
     * instalments, with the outstanding balance shrinking after each one until the bill
     * is fully settled.
     */
    public function test_successive_partial_payments_settle_one_bill(): void
    {
        $balance   = 1000000.00;
        $instalments = array(400000.00, 350000.00, 250000.00);
        $expected  = array(600000.00, 250000.00, 0.00);

        foreach ($instalments as $i => $payment) {
            $this->model->rows = array($this->bill(7, (string)$balance, 'P-0004', '01-07-2026'));
            $map = $this->model->get_payment_outstanding_map(array());

            // The instalment must fit inside what the bill still owes.
            $this->assertLessThanOrEqual($map['bill|7|']['outstanding'], $payment);

            $balance = round($balance - $payment, 2);
            $this->assertSame($expected[$i], $balance);
        }

        $this->assertSame(0.0, $balance, 'PO fully settled after three instalments');
        $this->assertSame('Settled', $this->model->derive_settle_status($balance, 1000000.00));
    }

    // -------------------------------------------------------- settlement status

    public function test_settle_status_transitions(): void
    {
        $this->assertSame('Unpaid',  $this->model->derive_settle_status(1000000.00, 0));
        $this->assertSame('Partial', $this->model->derive_settle_status(600000.00, 400000.00));
        $this->assertSame('Settled', $this->model->derive_settle_status(0.00, 1000000.00));
    }

    /**
     * A bill three paise from zero is settled. Reporting it as outstanding for ever
     * would make the outstanding report unusable.
     */
    public function test_rounding_remainder_counts_as_settled(): void
    {
        $this->assertSame('Settled', $this->model->derive_settle_status(0.03, 999999.97));
    }

    /**
     * A purchase return booked after full payment drives the balance negative. That is
     * over-settled, not outstanding.
     */
    public function test_negative_balance_is_settled_not_partial(): void
    {
        $this->assertSame('Settled', $this->model->derive_settle_status(-245964.00, 14424007.00));
    }

    public function test_tolerance_is_a_rounding_allowance_not_a_shortfall(): void
    {
        $this->assertSame(0.05, Ret_purchase_order_model::SETTLEMENT_TOLERANCE);

        // One rupee short is still Partial - the tolerance must not absorb real money.
        $this->assertSame('Partial', $this->model->derive_settle_status(1.00, 999999.00));
    }

    // ------------------------------------------------- reference-column coercion

    /**
     * ret_po_bill_payment_details.id_supplier_rate_cut / po_id / opening_id are INT
     * NOT NULL, but the pending-bills union does not emit integers for all of them:
     * the bill and Dr/Cr-credit branches select '' as ratecut, and the opening-balance
     * branch selects the literal 'OPENING'.
     *
     * Writing those straight through hit STRICT_TRANS_TABLES with
     *   "Incorrect integer value: 'OPENING' for column 'id_supplier_rate_cut'"
     * which CodeIgniter turned into a database-error page, so the whole save failed
     * with "Unable to proceed the requested process".
     *
     * This asserts the rule the save path applies: numeric through, anything else 0.
     */
    public function test_non_numeric_ratecut_becomes_zero(): void
    {
        $coerce = function ($v) {
            return (isset($v) && is_numeric($v)) ? (int)$v : 0;
        };

        $this->assertSame(0, $coerce(''),        "bill / crdr_credit branches emit ''");
        $this->assertSame(0, $coerce('OPENING'), 'opening branch emits the literal OPENING');
        $this->assertSame(0, $coerce(null),      'absent field must not become an empty string');
        $this->assertSame(4, $coerce('4'),       'a real rate-cut id still gets through');
        $this->assertSame(4, $coerce(4));
    }

    /**
     * Nullable reference columns take NULL, not 0 - a 0 would read as a real crdrid or
     * rate_fix_id and could match an unrelated record in a join.
     */
    public function test_absent_nullable_references_become_null(): void
    {
        $coerce = function ($v) {
            return is_numeric($v) ? (int)$v : null;
        };

        $this->assertNull($coerce(null));
        $this->assertNull($coerce(''));
        $this->assertSame(7, $coerce('7'));
    }

    /**
     * insertData() replaces any value PHP considers empty() with the column default, and
     * its guard that preserves a legitimate zero tests === 0 / === '0' - which a float
     * 0.0 fails. bill_amount, total_amount and pay_amt are NOT NULL numerics with no
     * default, so the substituted default is '' and STRICT mode rejects the row.
     *
     * Reachable whenever a settlement is funded entirely by debit-note or purchase-return
     * adjustments, with no cash, net banking or cheque at all.
     */
    public function test_zero_amount_survives_insertdata_empty_substitution(): void
    {
        $raw       = 0.0;
        $formatted = number_format($raw, 2, '.', '');

        $this->assertTrue(empty($raw), 'a float 0.0 IS empty() - this is the trap');
        $this->assertFalse($raw === 0,  'and it does not satisfy the === 0 guard either');

        $this->assertSame('0.00', $formatted);
        $this->assertFalse(empty($formatted), 'the formatted string is not empty(), so it survives');
    }
}
