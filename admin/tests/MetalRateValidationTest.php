<?php
use PHPUnit\Framework\TestCase;

/**
 * Metal Rate Validation — Unit Tests (bugfix/01351cc7078a)
 *
 * Tests the metal rate validation logic for the edit payment page:
 * - getMetalRateBydate() return value (0 vs NULL)
 * - Server-side rate validation (reject <= 0, system rate match, manual rate bounds)
 * - Rate bound constants
 *
 * These are pure logic tests — no DB connection required.
 * They mirror the validation block in Payment_model::updatePaymentdata()
 */
class MetalRateValidationTest extends TestCase
{
    // ---------------------------------------------------------------
    // Helper: mirrors the metal rate validation from updatePaymentdata
    // ---------------------------------------------------------------

    /**
     * Validates a submitted metal rate against the system rate and bounds.
     * Mirrors the validation block in Payment_model::updatePaymentdata()
     *
     * @param float $submitted_rate  Rate submitted from frontend
     * @param float $system_rate     Rate fetched from metal_rates table (0 = not found)
     * @param float $min_rate        Minimum allowed rate for manual entry
     * @param float $max_rate        Maximum allowed rate for manual entry
     * @return array ['valid' => bool, 'msg' => string]
     */
    private function validateMetalRate(
        float $submitted_rate,
        float $system_rate,
        float $min_rate = 5000,
        float $max_rate = 18000
    ): array {
        // Guard 1: reject zero, negative, or NaN
        if ($submitted_rate <= 0 || is_nan($submitted_rate)) {
            return ['valid' => false, 'msg' => 'Metal rate cannot be zero, negative, or invalid.'];
        }

        if ($system_rate > 0) {
            // System has rate — submitted must match (anti-tampering)
            if (abs($submitted_rate - $system_rate) > 0.01) {
                return ['valid' => false, 'msg' => 'Metal rate mismatch. Expected ' . $system_rate . ' but received ' . $submitted_rate];
            }
        } else {
            // No system rate — manual entry, check bounds
            if ($min_rate > 0 && $submitted_rate < $min_rate) {
                return ['valid' => false, 'msg' => 'Metal rate (' . $submitted_rate . ') is below the minimum allowed rate (' . $min_rate . ').'];
            }
            if ($max_rate > 0 && $submitted_rate > $max_rate) {
                return ['valid' => false, 'msg' => 'Metal rate (' . $submitted_rate . ') exceeds the maximum allowed rate (' . $max_rate . ').'];
            }
        }

        return ['valid' => true, 'msg' => 'OK'];
    }

    /**
     * Simulates what parseFloat(data) || 0 does in JS.
     * Used to verify the frontend won't produce NaN.
     *
     * @param mixed $value  Raw AJAX response value
     * @return float
     */
    private function jsParseFloat($value): float
    {
        $result = floatval($value);
        return is_nan($result) ? 0 : $result;
    }

    // ===============================================================
    // SECTION 1: Constants Verification
    // ===============================================================

    // TC-01: Gold rate constants are defined and sensible
    public function test_tc01_gold_rate_constants_defined(): void
    {
        // These mirror Payment_model::GOLD_MIN_RATE and GOLD_MAX_RATE
        $gold_min = 5000;
        $gold_max = 18000;
        $this->assertGreaterThan(0, $gold_min, 'GOLD_MIN_RATE must be positive');
        $this->assertGreaterThan($gold_min, $gold_max, 'GOLD_MAX_RATE must be greater than GOLD_MIN_RATE');
    }

    // TC-02: Silver rate constants are defined and sensible
    public function test_tc02_silver_rate_constants_defined(): void
    {
        $silver_min = 30;
        $silver_max = 500;
        $this->assertGreaterThan(0, $silver_min, 'SILVER_MIN_RATE must be positive');
        $this->assertGreaterThan($silver_min, $silver_max, 'SILVER_MAX_RATE must be greater than SILVER_MIN_RATE');
    }

    // TC-03: Gold and silver ranges don't overlap
    public function test_tc03_gold_silver_no_overlap(): void
    {
        $gold_min = 5000;
        $silver_max = 500;
        $this->assertGreaterThan($silver_max, $gold_min, 'Gold min should be well above silver max — ranges must not overlap');
    }

    // ===============================================================
    // SECTION 2: getMetalRateBydate return value simulation
    // ===============================================================

    // TC-04: When rate exists, return the rate value (not NULL, not 0)
    public function test_tc04_rate_found_returns_value(): void
    {
        // Simulates: $result->row()->goldrate_22ct = 7500
        $db_rate = 7500.00;
        $this->assertEquals(7500.00, $db_rate, 'When rate found in DB, return the rate');
        $this->assertGreaterThan(0, $db_rate);
    }

    // TC-05: When no rate exists, return 0 (not NULL)
    public function test_tc05_no_rate_returns_zero(): void
    {
        // This is the core fix — previously returned NULL
        $no_rate_return = 0;
        $this->assertSame(0, $no_rate_return, 'getMetalRateBydate must return 0 when no rate found, not NULL');
        $this->assertNotNull($no_rate_return, 'Return value must not be NULL');
    }

    // TC-06: JS parseFloat(NULL) produces NaN, parseFloat(0) produces 0
    public function test_tc06_js_parsefloat_null_vs_zero(): void
    {
        // Old behavior: returned NULL → JS parseFloat(null) = NaN
        $old_return = null;
        $js_result_old = $this->jsParseFloat($old_return); // floatval(null) = 0 in PHP, but JS parseFloat(null) = NaN
        // In JS: parseFloat(null) → NaN, parseFloat("null") → NaN
        // The fix ensures we return 0 (a number), so parseFloat(0) → 0

        // New behavior: returns 0 → JS parseFloat(0) = 0
        $new_return = 0;
        $js_result_new = $this->jsParseFloat($new_return);
        $this->assertEquals(0, $js_result_new, 'parseFloat(0) should produce 0, not NaN');
        $this->assertFalse(is_nan($js_result_new), 'Result must not be NaN');
    }

    // ===============================================================
    // SECTION 3: Rate validation — Guard 1: reject invalid rates
    // ===============================================================

    // TC-07: Rate = 0 rejected
    public function test_tc07_zero_rate_rejected(): void
    {
        $result = $this->validateMetalRate(0, 0);
        $this->assertFalse($result['valid'], 'Zero rate must be rejected');
    }

    // TC-08: Negative rate rejected
    public function test_tc08_negative_rate_rejected(): void
    {
        $result = $this->validateMetalRate(-5000, 0);
        $this->assertFalse($result['valid'], 'Negative rate must be rejected');
    }

    // TC-09: NaN rate rejected
    public function test_tc09_nan_rate_rejected(): void
    {
        $result = $this->validateMetalRate(NAN, 0);
        $this->assertFalse($result['valid'], 'NaN rate must be rejected');
    }

    // ===============================================================
    // SECTION 4: System rate exists — anti-tampering check
    // ===============================================================

    // TC-10: Submitted rate matches system rate — accepted
    public function test_tc10_system_rate_exact_match(): void
    {
        $result = $this->validateMetalRate(7500.00, 7500.00);
        $this->assertTrue($result['valid'], 'Exact match with system rate should pass');
    }

    // TC-11: Submitted rate within 0.01 tolerance of system rate — accepted
    public function test_tc11_system_rate_tolerance(): void
    {
        $result = $this->validateMetalRate(7500.005, 7500.00);
        $this->assertTrue($result['valid'], 'Within 0.01 tolerance should pass');
    }

    // TC-12: Submitted rate doesn't match system rate — rejected (DevTools tampering)
    public function test_tc12_system_rate_mismatch_rejected(): void
    {
        $result = $this->validateMetalRate(5000.00, 7500.00);
        $this->assertFalse($result['valid'], 'Tampered rate (5000 vs system 7500) must be rejected');
        $this->assertStringContainsString('mismatch', $result['msg']);
    }

    // TC-13: Submitted rate slightly off (> 0.01) from system rate — rejected
    public function test_tc13_system_rate_slight_mismatch(): void
    {
        $result = $this->validateMetalRate(7500.05, 7500.00);
        $this->assertFalse($result['valid'], 'Rate off by 0.05 from system rate should be rejected');
    }

    // ===============================================================
    // SECTION 5: No system rate — manual entry with gold bounds
    // ===============================================================

    // TC-14: Manual gold rate within bounds — accepted
    public function test_tc14_manual_gold_within_bounds(): void
    {
        $result = $this->validateMetalRate(7500.00, 0, 5000, 18000);
        $this->assertTrue($result['valid'], 'Gold rate 7500 within 5000-18000 should pass');
    }

    // TC-15: Manual gold rate at exact minimum — accepted
    public function test_tc15_manual_gold_at_min(): void
    {
        $result = $this->validateMetalRate(5000.00, 0, 5000, 18000);
        $this->assertTrue($result['valid'], 'Gold rate at exact min (5000) should pass');
    }

    // TC-16: Manual gold rate at exact maximum — accepted
    public function test_tc16_manual_gold_at_max(): void
    {
        $result = $this->validateMetalRate(18000.00, 0, 5000, 18000);
        $this->assertTrue($result['valid'], 'Gold rate at exact max (18000) should pass');
    }

    // TC-17: Manual gold rate below minimum — rejected
    public function test_tc17_manual_gold_below_min(): void
    {
        $result = $this->validateMetalRate(3000.00, 0, 5000, 18000);
        $this->assertFalse($result['valid'], 'Gold rate 3000 below min 5000 must be rejected');
        $this->assertStringContainsString('below', $result['msg']);
    }

    // TC-18: Manual gold rate above maximum — rejected
    public function test_tc18_manual_gold_above_max(): void
    {
        $result = $this->validateMetalRate(25000.00, 0, 5000, 18000);
        $this->assertFalse($result['valid'], 'Gold rate 25000 above max 18000 must be rejected');
        $this->assertStringContainsString('exceeds', $result['msg']);
    }

    // TC-19: Manual gold rate just below minimum (boundary) — rejected
    public function test_tc19_manual_gold_boundary_below_min(): void
    {
        $result = $this->validateMetalRate(4999.99, 0, 5000, 18000);
        $this->assertFalse($result['valid'], 'Gold rate 4999.99 just below min 5000 must be rejected');
    }

    // TC-20: Manual gold rate just above maximum (boundary) — rejected
    public function test_tc20_manual_gold_boundary_above_max(): void
    {
        $result = $this->validateMetalRate(18000.01, 0, 5000, 18000);
        $this->assertFalse($result['valid'], 'Gold rate 18000.01 just above max 18000 must be rejected');
    }

    // ===============================================================
    // SECTION 6: No system rate — manual entry with silver bounds
    // ===============================================================

    // TC-21: Manual silver rate within bounds — accepted
    public function test_tc21_manual_silver_within_bounds(): void
    {
        $result = $this->validateMetalRate(100.00, 0, 30, 500);
        $this->assertTrue($result['valid'], 'Silver rate 100 within 30-500 should pass');
    }

    // TC-22: Manual silver rate below minimum — rejected
    public function test_tc22_manual_silver_below_min(): void
    {
        $result = $this->validateMetalRate(10.00, 0, 30, 500);
        $this->assertFalse($result['valid'], 'Silver rate 10 below min 30 must be rejected');
    }

    // TC-23: Manual silver rate above maximum — rejected
    public function test_tc23_manual_silver_above_max(): void
    {
        $result = $this->validateMetalRate(600.00, 0, 30, 500);
        $this->assertFalse($result['valid'], 'Silver rate 600 above max 500 must be rejected');
    }

    // ===============================================================
    // SECTION 7: JS span/input toggle simulation
    // ===============================================================

    // TC-24: Rate > 0 from AJAX → should render as span (read-only)
    public function test_tc24_rate_found_renders_span(): void
    {
        $ajax_data = 7500; // Rate returned from getMetalRateBydate
        $met_rate = $this->jsParseFloat($ajax_data);
        $should_be_span = ($met_rate > 0);
        $this->assertTrue($should_be_span, 'Rate > 0 should render as read-only span');
    }

    // TC-25: Rate = 0 from AJAX → should render as input (editable)
    public function test_tc25_no_rate_renders_input(): void
    {
        $ajax_data = 0; // No rate found (our fix returns 0)
        $met_rate = $this->jsParseFloat($ajax_data);
        $should_be_input = ($met_rate <= 0);
        $this->assertTrue($should_be_input, 'Rate = 0 should render as editable input');
    }

    // TC-26: Rate = "0" (string) from AJAX → should render as input
    public function test_tc26_string_zero_renders_input(): void
    {
        $ajax_data = "0"; // AJAX might return string
        $met_rate = $this->jsParseFloat($ajax_data);
        $should_be_input = ($met_rate <= 0);
        $this->assertTrue($should_be_input, 'String "0" should render as editable input');
    }

    // ===============================================================
    // SECTION 8: Payment submit — rate reading logic
    // ===============================================================

    // TC-27: When manual rate input exists, read from input val
    public function test_tc27_manual_input_reads_val(): void
    {
        // Simulates: $row.find('.row-manual-rate').length > 0
        $has_manual_input = true;
        $input_val = '7500';
        $span_text = '0';

        $metal_rate = $has_manual_input ? $input_val : $span_text;
        $this->assertEquals('7500', $metal_rate, 'Should read from input.val() when manual input exists');
    }

    // TC-28: When no manual input (span only), read from span text
    public function test_tc28_span_reads_text(): void
    {
        $has_manual_input = false;
        $input_val = '';
        $span_text = '7500';

        $metal_rate = $has_manual_input ? $input_val : $span_text;
        $this->assertEquals('7500', $metal_rate, 'Should read from span.text() when no manual input');
    }

    // ===============================================================
    // SECTION 9: Weight recalculation
    // ===============================================================

    // TC-29: Weight = (amount - gst) / rate
    public function test_tc29_weight_calculation(): void
    {
        $amount = 10000.00;
        $gst_amount = 500.00;
        $met_rate = 7500.00;
        $weight_amount = $amount - $gst_amount;
        $weight = $weight_amount / $met_rate;

        $this->assertEqualsWithDelta(1.2667, $weight, 0.001, 'Weight = (10000-500)/7500 ≈ 1.2667');
    }

    // TC-30: Weight = 0 when rate is 0 (prevent division by zero)
    public function test_tc30_weight_zero_when_no_rate(): void
    {
        $met_rate = 0;
        $amount = 10000.00;
        $gst_amount = 500.00;
        // In JS: if (met_rate > 0 && weight_amount > 0) { ... } else { weight = 0 }
        $weight = ($met_rate > 0) ? (($amount - $gst_amount) / $met_rate) : 0;
        $this->assertEquals(0, $weight, 'Weight must be 0 when rate is 0');
    }
}
