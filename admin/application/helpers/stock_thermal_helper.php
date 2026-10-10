<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Stock In & Out Report — Thermal Print helpers.
 *
 * Pure functions (no DB, no session, no CI dependency) extracted from
 * admin_ret_reports::stock_details_v1/v2 'thermal_print' so they can be unit
 * tested directly. See:
 *   Documentation/Reports/Stock_InOut_Report_Business_Logic.md
 *   admin/tests/StockThermalPrintTest.php
 *
 * TWO THINGS TO KNOW BEFORE CHANGING ANY OF THIS:
 *
 * 1. get_stock_details_v*() returns a THREE-level nested array —
 *    $list[level1][level2][] = $row — where the level keys depend on group_by.
 *    A single foreach yields arrays, not rows.
 *
 * 2. The model also emits its own `closing_pcs` (an independent "<= ToDt"
 *    snapshot from the `current` sub-query) and THE GRID NEVER READS IT.
 *    What the user sees on screen is recomputed in JS at ret_reports.js:11885.
 *    These functions reproduce the JS formula so print and screen agree.
 */

if (!function_exists('stock_thermal_num')) {
	/**
	 * Safe numeric read for a stock row — absent/blank/non-numeric columns count as 0.
	 *
	 * Matters because the model's no-branch query variant omits the pur_ret and
	 * kar_iss sub-queries entirely, so ret_pcs / iss_pcs simply do not exist on
	 * those rows (defect C11). The grid does parseFloat(undefined) => NaN and
	 * renders a blank cell; we treat them as 0 instead.
	 */
	function stock_thermal_num($row, $key)
	{
		if (!is_array($row) || !isset($row[$key])) { return 0; }
		if ($row[$key] === '' || $row[$key] === null) { return 0; }
		return is_numeric($row[$key]) ? (float) $row[$key] : 0;
	}
}

if (!function_exists('stock_thermal_label_fields')) {
	/**
	 * Which column carries the leaf row's display label, per grouping.
	 *
	 * Tagged mirrors ret_reports.js:11893; non-tag mirrors ret_reports.js:11994.
	 * They DIFFER at group_by 3: the non-tag query selects no `weight_range`
	 * column at all, so it falls back to `section_name`.
	 *
	 * @return array [tagged_field, non_tag_field]
	 */
	function stock_thermal_label_fields($group_by)
	{
		switch ((string) $group_by) {
			case '2': return array('category_name',   'category_name');
			case '3': return array('weight_range',    'section_name');
			case '4': return array('sub_design_name', 'sub_design_name');
			default:  return array('product_name',    'product_name');
		}
	}
}

if (!function_exists('stock_thermal_closing_pcs')) {
	/**
	 * Closing pieces for one leaf row — the number printed on the slip.
	 *
	 * TAGGED   (ret_reports.js:11885):
	 *     op_blc_pcs + inw_pcs - (sold_pcs + br_out_pcs + ret_pcs + iss_pcs)
	 *
	 * NON-TAG  (ret_reports.js:12164):
	 *     op_blc_pcs + inw_pcs - sales_pcs - br_out_pcs
	 *   Note the different sold field name (`sales_pcs`, not `sold_pcs`) and the
	 *   absent ret_/iss_ terms — purchase return and karigar issue are already
	 *   folded into br_out_* by the non-tag query itself.
	 *
	 * @param bool $is_non_tag
	 */
	function stock_thermal_closing_pcs($row, $is_non_tag = false)
	{
		if ($is_non_tag) {
			return stock_thermal_num($row, 'op_blc_pcs')
			     + stock_thermal_num($row, 'inw_pcs')
			     - stock_thermal_num($row, 'sales_pcs')
			     - stock_thermal_num($row, 'br_out_pcs');
		}

		return stock_thermal_num($row, 'op_blc_pcs')
		     + stock_thermal_num($row, 'inw_pcs')
		     - (stock_thermal_num($row, 'sold_pcs')
		      + stock_thermal_num($row, 'br_out_pcs')
		      + stock_thermal_num($row, 'ret_pcs')
		      + stock_thermal_num($row, 'iss_pcs'));
	}
}

if (!function_exists('stock_thermal_build_rows')) {
	/**
	 * Flattens the 3-level report arrays into label => pcs, plus a grand total.
	 *
	 * Rows are aggregated by label, so a product appearing under two metals
	 * prints once with a combined figure. Insertion order is preserved (the
	 * model already sorts by c.sort, p.pro_id).
	 *
	 * Zero-closing rows are skipped. NEGATIVE rows are KEPT — a negative closing
	 * is a real data condition (stock issued that was never booked in) and a
	 * stock-count slip that silently omits it is worse than one that shows it.
	 * The on-screen grid hides them (defect C38); we deliberately do not.
	 *
	 * @return array ['rows' => [label => pcs], 'total_pcs' => float]
	 */
	function stock_thermal_build_rows($list, $non_tag_items, $group_by)
	{
		list($label_field, $nt_label_field) = stock_thermal_label_fields($group_by);

		$rows      = array();
		$total_pcs = 0;

		$sources = array(
			array(is_array($list) ? $list : array(),          $label_field,    false),
			array(is_array($non_tag_items) ? $non_tag_items : array(), $nt_label_field, true),
		);

		foreach ($sources as $source) {
			list($data, $field, $is_non_tag) = $source;

			foreach ($data as $level1) {
				if (!is_array($level1)) { continue; }

				foreach ($level1 as $level2) {
					if (!is_array($level2)) { continue; }

					foreach ($level2 as $row) {
						if (!is_array($row)) { continue; }

						$label = isset($row[$field]) ? trim((string) $row[$field]) : '';
						if ($label === '') { continue; }

						$closing_pcs = stock_thermal_closing_pcs($row, $is_non_tag);
						if ($closing_pcs == 0) { continue; }

						if (!isset($rows[$label])) { $rows[$label] = 0; }
						$rows[$label] += $closing_pcs;
						$total_pcs    += $closing_pcs;
					}
				}
			}
		}

		return array('rows' => $rows, 'total_pcs' => $total_pcs);
	}
}

if (!function_exists('stock_thermal_sanitize_post')) {
	/**
	 * Normalises the request before it reaches the model.
	 *
	 * WHY THIS EXISTS: get_stock_details_v*() interpolates dt_range straight into
	 * SQL with no guard. An empty or malformed range yields `BETWEEN '' AND ''`,
	 * which MySQL rejects with error 1525 "Incorrect DATE value: ''" — a raw
	 * DB_driver stack trace in the user's face. It also makes strtotime() return
	 * epoch, so the opening-balance cut-off becomes '1969-12-31'.
	 *
	 * An empty group_by is just as bad but fails silently: the model's 4-way
	 * ternary falls through to the group-by-4 branch rather than Product.
	 *
	 * @param string $today 'd/m/Y'; injectable so tests are not clock-dependent.
	 */
	function stock_thermal_sanitize_post($post, $today = null)
	{
		if (!is_array($post)) { $post = array(); }
		if ($today === null)  { $today = date('d/m/Y'); }

		// dt_range must be "dd/mm/yyyy - dd/mm/yyyy"; the model explodes on '-'.
		$range = isset($post['dt_range']) ? trim((string) $post['dt_range']) : '';
		$parts = explode('-', $range);
		if ($range === '' || count($parts) < 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
			$post['dt_range'] = $today . ' - ' . $today;
		}

		if (!isset($post['group_by']) || !in_array((string) $post['group_by'], array('1', '2', '3', '4'), true)) {
			$post['group_by'] = 1;
		}

		if (!isset($post['list_by']) || !in_array((string) $post['list_by'], array('0', '1', '2'), true)) {
			$post['list_by'] = 0;
		}

		if (!isset($post['id_branch']) || $post['id_branch'] === '') {
			$post['id_branch'] = 0;
		}

		return $post;
	}
}
