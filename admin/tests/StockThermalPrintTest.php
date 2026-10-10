<?php
use PHPUnit\Framework\TestCase;

/**
 * Thermal Print — Stock In & Out Report (v1 "Old" and v2 "New")
 *
 * Exercises the REAL production code in
 *   admin/application/helpers/stock_thermal_helper.php
 * (not a re-implementation), which is where the flattening, the closing-pcs
 * arithmetic and the request sanitisation live.
 *
 * Background: Documentation/Reports/Stock_InOut_Report_Business_Logic.md
 *
 * The two facts every test here defends:
 *   1. get_stock_details_v*() returns a THREE-level nested array
 *      $list[level1][level2][] = $row — a single foreach yields arrays, not rows.
 *   2. Closing pcs is NOT the model's `closing_pcs` column. It is recomputed as
 *      op + inw - (sold + br_out + ret + iss), matching ret_reports.js:11885.
 */
class StockThermalPrintTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('BASEPATH')) {
            define('BASEPATH', __DIR__);   // helper guards on this
        }
        require_once __DIR__ . '/../application/helpers/stock_thermal_helper.php';
    }

    // ---------------------------------------------------------------
    // Fixtures — shaped exactly like the model's output
    // ---------------------------------------------------------------

    /** One tagged leaf row with all eight pcs-relevant columns present. */
    private function tagRow(array $over = []): array
    {
        return array_merge([
            'product_name'    => 'GOLD BANGLES',
            'category_name'   => 'GOLD',
            'weight_range'    => '0-5',
            'sub_design_name' => 'PLAIN',
            'op_blc_pcs'      => 100,
            'inw_pcs'         => 20,
            'sold_pcs'        => 10,
            'br_out_pcs'      => 5,
            'ret_pcs'         => 2,
            'iss_pcs'         => 3,
            'closing_pcs'     => 999,   // the model's snapshot — MUST be ignored
        ], $over);
    }

    /** One non-tag leaf row: different sold field, no ret_/iss_ columns. */
    private function nonTagRow(array $over = []): array
    {
        return array_merge([
            'product_name'    => 'PACKET GOLD',
            'category_name'   => 'GOLD',
            'section_name'    => 'RAW',
            'sub_design_name' => 'NA',
            'op_blc_pcs'      => 50,
            'inw_pcs'         => 10,
            'sales_pcs'       => 4,
            'br_out_pcs'      => 6,
        ], $over);
    }

    /** Wraps leaf rows into the model's 3-level nesting. */
    private function nest(array $rows, string $l1 = 'GOLD', string $l2 = 'BANGLE'): array
    {
        return [$l1 => [$l2 => $rows]];
    }

    // ===============================================================
    // 1. Closing-pcs arithmetic — the core formula
    // ===============================================================

    public function test_tagged_closing_uses_grid_formula_not_model_column(): void
    {
        // 100 + 20 - (10 + 5 + 2 + 3) = 100.  NOT 999 (the model's closing_pcs).
        $this->assertSame(100.0, stock_thermal_closing_pcs($this->tagRow()));
    }

    public function test_non_tag_closing_uses_sales_pcs_not_sold_pcs(): void
    {
        // 50 + 10 - 4 - 6 = 50
        $this->assertSame(50.0, stock_thermal_closing_pcs($this->nonTagRow(), true));
    }

    public function test_non_tag_ignores_ret_and_iss_columns(): void
    {
        // Purchase return / karigar issue are already folded into br_out_* by the
        // non-tag query. Subtracting them again would double-count.
        $row = $this->nonTagRow(['ret_pcs' => 999, 'iss_pcs' => 999]);
        $this->assertSame(50.0, stock_thermal_closing_pcs($row, true));
    }

    public function test_tagged_row_passed_as_non_tag_would_ignore_sold_pcs(): void
    {
        // Guards the tag/non-tag flag itself: a tagged row read with the non-tag
        // formula finds no `sales_pcs`, so sold_pcs (10) is silently NOT deducted.
        // 100 + 20 - 0 - 5 = 115, proving the flag genuinely changes behaviour.
        $this->assertSame(115.0, stock_thermal_closing_pcs($this->tagRow(), true));
    }

    // ===============================================================
    // 2. Defect C11 — no-branch query omits ret_/iss_ entirely
    // ===============================================================

    public function test_missing_ret_and_iss_columns_count_as_zero_not_nan(): void
    {
        // With "All branches" the model omits the pur_ret and kar_iss sub-queries,
        // so these keys do not exist. The grid does parseFloat(undefined) => NaN
        // and prints a blank cell; the slip must still produce a real number.
        $row = $this->tagRow();
        unset($row['ret_pcs'], $row['iss_pcs']);

        $result = stock_thermal_closing_pcs($row);

        $this->assertFalse(is_nan($result), 'closing must never be NaN');
        $this->assertSame(105.0, $result);   // 100 + 20 - (10 + 5)
    }

    // ===============================================================
    // 3. stock_thermal_num — defensive numeric read
    // ===============================================================

    public function test_num_handles_missing_blank_null_and_non_numeric(): void
    {
        $row = ['a' => 5, 'blank' => '', 'null' => null, 'text' => 'abc', 'numeric_string' => '7.5'];

        $this->assertSame(5.0,  stock_thermal_num($row, 'a'));
        $this->assertSame(0,    stock_thermal_num($row, 'missing'));
        $this->assertSame(0,    stock_thermal_num($row, 'blank'));
        $this->assertSame(0,    stock_thermal_num($row, 'null'));
        $this->assertSame(0,    stock_thermal_num($row, 'text'));
        $this->assertSame(7.5,  stock_thermal_num($row, 'numeric_string'));
    }

    public function test_num_on_non_array_returns_zero(): void
    {
        $this->assertSame(0, stock_thermal_num('not an array', 'x'));
        $this->assertSame(0, stock_thermal_num(null, 'x'));
    }

    // ===============================================================
    // 4. Label field selection per group_by
    // ===============================================================

    public function test_label_fields_per_group_by(): void
    {
        $this->assertSame(['product_name', 'product_name'],       stock_thermal_label_fields(1));
        $this->assertSame(['category_name', 'category_name'],     stock_thermal_label_fields(2));
        $this->assertSame(['sub_design_name', 'sub_design_name'], stock_thermal_label_fields(4));
    }

    public function test_group_by_3_differs_between_tag_and_non_tag(): void
    {
        // THE trap: the non-tag query selects no `weight_range` column at all.
        // Using the tagged field for both would drop every non-tag row.
        $this->assertSame(['weight_range', 'section_name'], stock_thermal_label_fields(3));
    }

    public function test_unknown_group_by_falls_back_to_product(): void
    {
        foreach ([null, '', 0, 9, 'abc'] as $bad) {
            $this->assertSame(
                ['product_name', 'product_name'],
                stock_thermal_label_fields($bad),
                'group_by ' . var_export($bad, true) . ' must fall back to Product'
            );
        }
    }

    // ===============================================================
    // 5. The 3-level flattening — the bug that printed blank rows
    // ===============================================================

    public function test_flattens_three_level_nesting(): void
    {
        $out = stock_thermal_build_rows($this->nest([$this->tagRow()]), [], 1);

        $this->assertSame(['GOLD BANGLES' => 100.0], $out['rows']);
        $this->assertSame(100.0, $out['total_pcs']);
    }

    public function test_multiple_level1_and_level2_groups_all_traversed(): void
    {
        $list = [
            'GOLD' => [
                'BANGLE'  => [$this->tagRow(['product_name' => 'GOLD BANGLES'])],
                'CHAIN'   => [$this->tagRow(['product_name' => 'GOLD CHAIN', 'op_blc_pcs' => 10,
                                             'inw_pcs' => 0, 'sold_pcs' => 0, 'br_out_pcs' => 0,
                                             'ret_pcs' => 0, 'iss_pcs' => 0])],
            ],
            'SILVER' => [
                'RING' => [$this->tagRow(['product_name' => 'SILVER RING', 'op_blc_pcs' => 7,
                                          'inw_pcs' => 0, 'sold_pcs' => 0, 'br_out_pcs' => 0,
                                          'ret_pcs' => 0, 'iss_pcs' => 0])],
            ],
        ];

        $out = stock_thermal_build_rows($list, [], 1);

        $this->assertCount(3, $out['rows']);
        $this->assertSame(117.0, $out['total_pcs']);   // 100 + 10 + 7
    }

    public function test_same_label_across_groups_is_aggregated_once(): void
    {
        // Same product under two metals must print as ONE line, summed.
        $list = [
            'GOLD'   => ['A' => [$this->tagRow(['product_name' => 'HOOK', 'op_blc_pcs' => 10,
                                  'inw_pcs' => 0, 'sold_pcs' => 0, 'br_out_pcs' => 0,
                                  'ret_pcs' => 0, 'iss_pcs' => 0])]],
            'SILVER' => ['B' => [$this->tagRow(['product_name' => 'HOOK', 'op_blc_pcs' => 5,
                                  'inw_pcs' => 0, 'sold_pcs' => 0, 'br_out_pcs' => 0,
                                  'ret_pcs' => 0, 'iss_pcs' => 0])]],
        ];

        $out = stock_thermal_build_rows($list, [], 1);

        $this->assertSame(['HOOK' => 15.0], $out['rows']);
        $this->assertSame(15.0, $out['total_pcs']);
    }

    public function test_tag_and_non_tag_are_combined(): void
    {
        $out = stock_thermal_build_rows(
            $this->nest([$this->tagRow()]),
            $this->nest([$this->nonTagRow()], 'GOLD', 'RAW'),
            1
        );

        $this->assertSame(150.0, $out['total_pcs']);   // 100 tagged + 50 non-tag
        $this->assertArrayHasKey('GOLD BANGLES', $out['rows']);
        $this->assertArrayHasKey('PACKET GOLD',  $out['rows']);
    }

    public function test_non_tag_group_by_3_uses_section_name(): void
    {
        $out = stock_thermal_build_rows(
            [],
            $this->nest([$this->nonTagRow()], 'RAW', 'PACKET GOLD'),
            3
        );

        // Would be empty if the tagged field (weight_range) were used for non-tag.
        $this->assertSame(['RAW' => 50.0], $out['rows']);
    }

    // ===============================================================
    // 6. Row inclusion / exclusion rules
    // ===============================================================

    public function test_zero_closing_rows_are_skipped(): void
    {
        $zero = $this->tagRow(['op_blc_pcs' => 10, 'inw_pcs' => 0, 'sold_pcs' => 10,
                               'br_out_pcs' => 0, 'ret_pcs' => 0, 'iss_pcs' => 0]);

        $out = stock_thermal_build_rows($this->nest([$zero]), [], 1);

        $this->assertSame([], $out['rows']);
        $this->assertSame(0, $out['total_pcs']);
    }

    public function test_negative_closing_rows_are_kept(): void
    {
        // Real condition — e.g. GOLD HOOK at HEAD OFFICE = -111, caused by karigar
        // issues against stock that was never booked inward. The on-screen grid
        // hides these (x > 0 ? x : ''); the slip must not.
        $neg = $this->tagRow(['product_name' => 'GOLD HOOK', 'op_blc_pcs' => 0,
                              'inw_pcs' => 0, 'sold_pcs' => 111, 'br_out_pcs' => 0,
                              'ret_pcs' => 0, 'iss_pcs' => 0]);

        $out = stock_thermal_build_rows($this->nest([$neg]), [], 1);

        $this->assertSame(['GOLD HOOK' => -111.0], $out['rows']);
        $this->assertSame(-111.0, $out['total_pcs']);
    }

    public function test_negative_and_positive_offset_in_the_total(): void
    {
        $list = [
            'GOLD' => [
                'A' => [$this->tagRow(['product_name' => 'BANGLE', 'op_blc_pcs' => 200,
                        'inw_pcs' => 0, 'sold_pcs' => 0, 'br_out_pcs' => 0, 'ret_pcs' => 0, 'iss_pcs' => 0])],
                'B' => [$this->tagRow(['product_name' => 'HOOK', 'op_blc_pcs' => 0,
                        'inw_pcs' => 0, 'sold_pcs' => 111, 'br_out_pcs' => 0, 'ret_pcs' => 0, 'iss_pcs' => 0])],
            ],
        ];

        $out = stock_thermal_build_rows($list, [], 1);

        $this->assertSame(89.0, $out['total_pcs']);   // 200 - 111
    }

    public function test_rows_with_blank_or_missing_label_are_skipped(): void
    {
        $rows = [
            $this->tagRow(['product_name' => '']),
            $this->tagRow(['product_name' => '   ']),
        ];
        $noLabel = $this->tagRow();
        unset($noLabel['product_name']);
        $rows[] = $noLabel;

        $out = stock_thermal_build_rows($this->nest($rows), [], 1);

        $this->assertSame([], $out['rows']);
    }

    public function test_label_is_trimmed(): void
    {
        $out = stock_thermal_build_rows($this->nest([$this->tagRow(['product_name' => '  GOLD RING  '])]), [], 1);

        $this->assertArrayHasKey('GOLD RING', $out['rows']);
    }

    // ===============================================================
    // 7. Edge cases — empty / malformed model output
    // ===============================================================

    public function test_empty_result_sets_produce_empty_slip(): void
    {
        $out = stock_thermal_build_rows([], [], 1);

        $this->assertSame([], $out['rows']);
        $this->assertSame(0, $out['total_pcs']);
    }

    public function test_non_array_inputs_do_not_fatal(): void
    {
        foreach ([null, false, '', 0, 'garbage'] as $bad) {
            $out = stock_thermal_build_rows($bad, $bad, 1);
            $this->assertSame([], $out['rows']);
        }
    }

    public function test_malformed_nesting_is_skipped_not_fatal(): void
    {
        // Scalars appearing at any of the three levels must be stepped over.
        $list = [
            'GOLD'   => 'not-an-array',
            'SILVER' => ['RING' => 'not-an-array'],
            'BRASS'  => ['X' => ['not-an-array', $this->tagRow(['product_name' => 'SURVIVOR'])]],
        ];

        $out = stock_thermal_build_rows($list, [], 1);

        $this->assertSame(['SURVIVOR' => 100.0], $out['rows']);
    }

    public function test_list_by_2_shape_non_tag_only(): void
    {
        // list_by = 2 makes the controller pass an empty tagged array.
        $out = stock_thermal_build_rows([], $this->nest([$this->nonTagRow()], 'GOLD', 'RAW'), 1);

        $this->assertSame(['PACKET GOLD' => 50.0], $out['rows']);
    }

    public function test_fractional_pieces_are_preserved(): void
    {
        $row = $this->tagRow(['op_blc_pcs' => '10.5', 'inw_pcs' => 0, 'sold_pcs' => 0,
                              'br_out_pcs' => 0, 'ret_pcs' => 0, 'iss_pcs' => 0]);

        $out = stock_thermal_build_rows($this->nest([$row]), [], 1);

        $this->assertSame(10.5, $out['total_pcs']);
    }

    // ===============================================================
    // 8. Request sanitisation — MySQL error 1525 guard
    // ===============================================================

    public function test_empty_dt_range_defaults_to_today(): void
    {
        // Regression: an empty dt_range reached the model as BETWEEN '' AND '',
        // producing "Incorrect DATE value: ''" (MySQL 1525) and a raw stack trace.
        $out = stock_thermal_sanitize_post(['dt_range' => ''], '21/07/2026');

        $this->assertSame('21/07/2026 - 21/07/2026', $out['dt_range']);
    }

    /** @dataProvider malformedRanges */
    public function test_malformed_dt_range_defaults_to_today($bad): void
    {
        $out = stock_thermal_sanitize_post(['dt_range' => $bad], '21/07/2026');

        $this->assertSame('21/07/2026 - 21/07/2026', $out['dt_range'],
            'must reject ' . var_export($bad, true));
    }

    public static function malformedRanges(): array
    {
        return [
            'empty'            => [''],
            'whitespace'       => ['   '],
            'no separator'     => ['21/07/2026'],
            'missing left'     => [' - 21/07/2026'],
            'missing right'    => ['21/07/2026 - '],
            'separator only'   => ['-'],
        ];
    }

    public function test_valid_dt_range_is_left_untouched(): void
    {
        $out = stock_thermal_sanitize_post(['dt_range' => '01/03/2026 - 21/07/2026'], '21/07/2026');

        $this->assertSame('01/03/2026 - 21/07/2026', $out['dt_range']);
    }

    public function test_missing_dt_range_key_defaults_to_today(): void
    {
        $out = stock_thermal_sanitize_post([], '21/07/2026');

        $this->assertSame('21/07/2026 - 21/07/2026', $out['dt_range']);
    }

    public function test_empty_group_by_defaults_to_product_not_group_four(): void
    {
        // The model's 4-way ternary treats anything unrecognised as the LAST
        // branch (group 4), so an empty value silently grouped by design/sub-design.
        foreach (['', null, '0', 'abc', 5] as $bad) {
            $out = stock_thermal_sanitize_post(['group_by' => $bad], '21/07/2026');
            $this->assertSame(1, $out['group_by'], 'group_by ' . var_export($bad, true));
        }
    }

    public function test_valid_group_by_values_survive(): void
    {
        foreach (['1', '2', '3', '4'] as $ok) {
            $out = stock_thermal_sanitize_post(['group_by' => $ok], '21/07/2026');
            $this->assertSame($ok, $out['group_by']);
        }
    }

    public function test_list_by_defaults_to_all_when_invalid(): void
    {
        foreach (['', null, '3', 'x'] as $bad) {
            $out = stock_thermal_sanitize_post(['list_by' => $bad], '21/07/2026');
            $this->assertSame(0, $out['list_by']);
        }
    }

    public function test_list_by_zero_is_preserved_not_treated_as_empty(): void
    {
        // '0' is falsy in PHP — a naive isset/empty check would clobber it.
        $out = stock_thermal_sanitize_post(['list_by' => '0'], '21/07/2026');

        $this->assertSame('0', $out['list_by']);
    }

    public function test_missing_id_branch_becomes_zero(): void
    {
        $this->assertSame(0, stock_thermal_sanitize_post([], '21/07/2026')['id_branch']);
        $this->assertSame(0, stock_thermal_sanitize_post(['id_branch' => ''], '21/07/2026')['id_branch']);
    }

    public function test_other_post_keys_pass_through_untouched(): void
    {
        $out = stock_thermal_sanitize_post([
            'dt_range'  => '01/03/2026 - 21/07/2026',
            'id_metal'  => ['1', '2'],
            'id_branch' => '13',
            'custom'    => 'kept',
        ], '21/07/2026');

        $this->assertSame(['1', '2'], $out['id_metal']);
        $this->assertSame('13', $out['id_branch']);
        $this->assertSame('kept', $out['custom']);
    }

    public function test_non_array_post_does_not_fatal(): void
    {
        $out = stock_thermal_sanitize_post(null, '21/07/2026');

        $this->assertSame('21/07/2026 - 21/07/2026', $out['dt_range']);
        $this->assertSame(1, $out['group_by']);
    }

    // ===============================================================
    // 9. End-to-end shape — mirrors the real GOLD HOOK finding
    // ===============================================================

    public function test_realistic_mixed_slip(): void
    {
        $tagged = [
            'GOLD' => [
                'BANGLE' => [$this->tagRow(['product_name' => 'GOLD BANGLES', 'op_blc_pcs' => 580,
                             'inw_pcs' => 0, 'sold_pcs' => 0, 'br_out_pcs' => 0, 'ret_pcs' => 0, 'iss_pcs' => 0])],
                'COIN'   => [$this->tagRow(['product_name' => 'GOLD COIN', 'op_blc_pcs' => 162,
                             'inw_pcs' => 0, 'sold_pcs' => 0, 'br_out_pcs' => 0, 'ret_pcs' => 0, 'iss_pcs' => 0])],
            ],
        ];
        $nonTag = [
            'GOLD' => [
                'RAW' => [
                    // HEAD OFFICE reality: issued more than ever received.
                    $this->nonTagRow(['product_name' => 'GOLD HOOK', 'op_blc_pcs' => 0,
                                      'inw_pcs' => 0, 'sales_pcs' => 111, 'br_out_pcs' => 0]),
                    $this->nonTagRow(['product_name' => 'PACKET BESARI', 'op_blc_pcs' => 100,
                                      'inw_pcs' => 0, 'sales_pcs' => 0, 'br_out_pcs' => 0]),
                ],
            ],
        ];

        $out = stock_thermal_build_rows($tagged, $nonTag, 1);

        $this->assertSame([
            'GOLD BANGLES'  =>  580.0,
            'GOLD COIN'     =>  162.0,
            'GOLD HOOK'     => -111.0,
            'PACKET BESARI' =>  100.0,
        ], $out['rows']);

        $this->assertSame(731.0, $out['total_pcs']);   // 580 + 162 - 111 + 100
    }

    public function test_total_equals_sum_of_printed_rows(): void
    {
        // The acceptance invariant: the Total line must equal what is printed
        // above it — no row silently excluded from one but not the other.
        $out = stock_thermal_build_rows(
            $this->nest([
                $this->tagRow(['product_name' => 'A', 'op_blc_pcs' => 5,  'inw_pcs' => 0, 'sold_pcs' => 0, 'br_out_pcs' => 0, 'ret_pcs' => 0, 'iss_pcs' => 0]),
                $this->tagRow(['product_name' => 'B', 'op_blc_pcs' => 0,  'inw_pcs' => 0, 'sold_pcs' => 3, 'br_out_pcs' => 0, 'ret_pcs' => 0, 'iss_pcs' => 0]),
                $this->tagRow(['product_name' => 'C', 'op_blc_pcs' => 10, 'inw_pcs' => 0, 'sold_pcs' => 10, 'br_out_pcs' => 0, 'ret_pcs' => 0, 'iss_pcs' => 0]),
            ]),
            [],
            1
        );

        $this->assertSame(array_sum($out['rows']), $out['total_pcs']);
    }
}
