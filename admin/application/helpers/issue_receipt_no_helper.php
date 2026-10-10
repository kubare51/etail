<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * issue_receipt_no_helper.php
 *
 * Display-time number formatting for Issue / Receipt Vouchers (ret_issue_receipt),
 * mirroring the bill_no_format mechanism used for billing documents
 * (ret_billing_model::get_bill_no_format_detail).
 *
 * The stored ret_issue_receipt.bill_no serial is never modified -- these
 * functions only render it. Formats live in `issue_receipt_no_format`, keyed on
 * (voucher_mode, voucher_type); see the migration for the token vocabulary.
 *
 * Lives in a helper rather than a model method on purpose: the billing resolver
 * is copy-pasted into ret_billing_model, ret_reports_model and
 * ret_estimation_model, so every change there has to be made three times.
 *
 * Client override: define client_ir_no_format_row($row, $templates) in
 * clients/{CLIENT_ID}/helpers/ret_billing_helper.php to replace the whole
 * substitution step.
 */

if (!defined('IR_NO_DEFAULT_FORMAT')) {
	// Used when a voucher type has no row and the mode has no fallback row
	// either. Matches the shipped default: VD26-27/00042
	define('IR_NO_DEFAULT_FORMAT', '@@branch_code@@@@fin_year@@@@slash@@@@bill_no@@');
}

if (!function_exists('ir_no_short_code')) {

	/**
	 * Short code for a voucher, hardcoded exactly like the billing
	 * SA/PU/SR/CC map in get_bill_no_format_detail().
	 *
	 * @param  int $type         ret_issue_receipt.type (1 = Issue, 2 = Receipt)
	 * @param  int $receipt_type ret_issue_receipt.receipt_type
	 * @param  int $issue_type   ret_issue_receipt.issue_type
	 * @return string            '' when the sub-type has no code of its own
	 */
	function ir_no_short_code($type, $receipt_type, $issue_type)
	{
		if ((int) $type === 2) {
			// Receipt Voucher. 4/6/9 (Opening Blc, Chit Close, Amount Receipt)
			// have no code. Types 3 (Adv Deposit) and 5 (OrdAdv->GenAdv) belong
			// to the "Advance Receipt" family -- they print under that label and
			// share its ADV code (see the template alias in ir_no_format_row()).
			$map = array(
				1 => 'CC',   // Credit Collection
				2 => 'ADV',  // Advance Receipt
				3 => 'ADV',  // Advance Deposit  (Advance Receipt family)
				5 => 'ADV',  // OrdAdv->GenAdv   (Advance Receipt family)
				7 => 'ADT',  // Advance Transfer
				8 => 'PCR',  // Petty Cash Receipt
			);

			return isset($map[(int) $receipt_type]) ? $map[(int) $receipt_type] : '';
		}

		// Issue Voucher. 4 (Existing Credit Sales) has no code of its own.
		$map = array(
			1 => 'PCI',  // Petty Cash Issue
			2 => 'CR',   // Credit Payment
			3 => 'ADR',  // Advance Refund
			5 => 'EXP',  // Expenses
			6 => 'NJE',  // Non Jewellery Expense
		);

		return isset($map[(int) $issue_type]) ? $map[(int) $issue_type] : '';
	}
}

if (!function_exists('ir_no_templates')) {

	/**
	 * All configured formats as $templates[voucher_mode][voucher_type] = format.
	 * Queried once per request -- callers may hit this in a loop.
	 *
	 * @param  object $db CI database object
	 * @return array
	 */
	function ir_no_templates($db, $refresh = FALSE)
	{
		static $cache = NULL;

		if ($cache !== NULL && !$refresh) {
			return $cache;
		}

		$cache = array();

		// Table ships with 20260805_000001_add_issue_receipt_no_format.sql.
		// Until that migration runs the feature stays inert and vouchers keep
		// rendering through IR_NO_DEFAULT_FORMAT.
		if (!$db->table_exists('issue_receipt_no_format')) {
			return $cache;
		}

		$rows = $db->query("SELECT voucher_mode, voucher_type, no_format FROM issue_receipt_no_format")->result_array();

		foreach ($rows as $row) {
			$cache[(int) $row['voucher_mode']][(int) $row['voucher_type']] = $row['no_format'];
		}

		return $cache;
	}
}

if (!function_exists('ir_no_pick_template')) {

	/**
	 * Format string for one voucher: its own row, else the mode's fallback
	 * row (voucher_type 0), else the hardcoded default.
	 */
	function ir_no_pick_template($templates, $mode, $voucher_type)
	{
		$mode         = (int) $mode;
		$voucher_type = (int) $voucher_type;

		if (isset($templates[$mode][$voucher_type]) && $templates[$mode][$voucher_type] !== '') {
			return $templates[$mode][$voucher_type];
		}

		if (isset($templates[$mode][0]) && $templates[$mode][0] !== '') {
			return $templates[$mode][0];
		}

		return IR_NO_DEFAULT_FORMAT;
	}
}

if (!function_exists('ir_no_format_row')) {

	/**
	 * Render one voucher number. Pure string work -- no queries.
	 *
	 * $row needs: type, receipt_type, issue_type, bill_no, fin_year_code,
	 *             short_name (branch short code).
	 *
	 * @param  array $row
	 * @param  array $templates from ir_no_templates()
	 * @return string
	 */
	function ir_no_format_row($row, $templates)
	{
		// --- Client Override Hook ---
		// Drop clients/{CLIENT_ID}/helpers/ret_billing_helper.php to override
		if (function_exists('client_ir_no_format_row')) {
			return client_ir_no_format_row($row, $templates);
		}

		$mode = isset($row['type']) ? (int) $row['type'] : 2;

		$voucher_type = ($mode === 2)
			? (isset($row['receipt_type']) ? (int) $row['receipt_type'] : 0)
			: (isset($row['issue_type']) ? (int) $row['issue_type'] : 0);

		// Advance Receipt family. receipt_type 3 (Advance Deposit) and 5
		// (Order-Advance -> General-Advance) are shown to the customer under the
		// "Advance Receipt" label and have no settings row of their own; render
		// them through the Advance Receipt (type 2) format so they inherit its
		// layout and ADV code instead of dropping to Others/Default. The short
		// code itself is keyed on the real receipt_type in ir_no_short_code().
		$template_type = ($mode === 2 && ($voucher_type === 3 || $voucher_type === 5))
			? 2
			: $voucher_type;

		$format = ir_no_pick_template($templates, $mode, $template_type);

		// Separators are explicit @@hyphen@@ / @@slash@@ tokens. Formats written
		// before the settings screen existed instead baked the separator into
		// each token as a leading '-', so the template opened with one that is
		// not wanted in the output. The billing resolver chops that first
		// character unconditionally with substr($fmt, 1); only do so when a dash
		// is actually there, otherwise a format that starts with a real
		// character loses it.
		if (isset($format[0]) && $format[0] === '-') {
			$format = substr($format, 1);
		}

		$map = array(
			'@@short_code@@'  => ir_no_short_code(
				$mode,
				isset($row['receipt_type']) ? $row['receipt_type'] : 0,
				isset($row['issue_type']) ? $row['issue_type'] : 0
			),
			'@@branch_code@@' => isset($row['short_name']) ? $row['short_name'] : '',
			'@@fin_year@@'    => isset($row['fin_year_code']) ? $row['fin_year_code'] : '',
			'@@bill_no@@'     => isset($row['bill_no']) ? $row['bill_no'] : '',
			// Separators, placed from the settings screen's token list.
			'@@hyphen@@'      => '-',
			'@@slash@@'       => '/',
		);

		$out = strtr($format, $map);

		// A token that resolves to '' -- an unmapped short code, a branch with
		// no short_name -- leaves its separators behind: "/ADV00042",
		// "MAIN//00042". Collapse repeats of the same separator and trim any
		// left dangling at either end.
		//
		// Repeats of the SAME character only: fin_year_code is free text and
		// commonly holds a dash itself ('26-27'), and a deliberate '-/' pair
		// should survive.
		$out = preg_replace('#([-/])\1+#', '$1', $out);
		$out = trim($out, '-/');

		return $out;
	}
}

if (!function_exists('ir_no_format_apply')) {

	/**
	 * Batch entry point: rewrite the voucher number on every row of a result
	 * set. Two queries total regardless of row count, so report SQL does not
	 * have to change -- it only needs to carry the id column.
	 *
	 * @param object $db      CI database object
	 * @param array  $rows    result_array(), modified in place
	 * @param string $id_key  column holding id_issue_receipt (some reports alias it as bill_id)
	 * @param string $out_key column to overwrite with the formatted number
	 */
	function ir_no_format_apply($db, &$rows, $id_key = 'id_issue_receipt', $out_key = 'bill_no')
	{
		if (empty($rows) || !is_array($rows)) {
			return;
		}

		$ids = array();

		foreach ($rows as $row) {
			if (isset($row[$id_key]) && (int) $row[$id_key] > 0) {
				$ids[(int) $row[$id_key]] = TRUE;
			}
		}

		if (empty($ids)) {
			return;
		}

		$details  = ir_no_fetch_details($db, array_keys($ids));
		$templates = ir_no_templates($db);

		foreach ($rows as &$row) {
			$id = isset($row[$id_key]) ? (int) $row[$id_key] : 0;

			if ($id > 0 && isset($details[$id])) {
				$row[$out_key] = ir_no_format_row($details[$id], $templates);
			}
		}
		unset($row);
	}
}

if (!function_exists('ir_no_fetch_details')) {

	/**
	 * Raw inputs the formatter needs, keyed by id_issue_receipt.
	 *
	 * @param  object $db
	 * @param  array  $ids
	 * @return array
	 */
	function ir_no_fetch_details($db, $ids)
	{
		$out = array();

		if (empty($ids)) {
			return $out;
		}

		// Ints only -- these reach the query directly.
		$ids = array_map('intval', $ids);
		$ids = array_filter($ids);

		if (empty($ids)) {
			return $out;
		}

		$sql = "SELECT r.id_issue_receipt, r.type, r.receipt_type, r.issue_type,
					r.bill_no, r.fin_year_code, IFNULL(b.short_name,'') as short_name
				FROM ret_issue_receipt r
				LEFT JOIN branch b ON b.id_branch = r.id_branch
				WHERE r.id_issue_receipt IN (" . implode(',', $ids) . ")";

		foreach ($db->query($sql)->result_array() as $row) {
			$out[(int) $row['id_issue_receipt']] = $row;
		}

		return $out;
	}
}

if (!function_exists('ir_no_format_detail')) {

	/**
	 * Formatted number for a single voucher. Convenience wrapper mirroring
	 * ret_billing_model::get_bill_no_format_detail($bill_id).
	 *
	 * @param  object $db
	 * @param  int    $id_issue_receipt
	 * @return string '' when the voucher does not exist
	 */
	function ir_no_format_detail($db, $id_issue_receipt)
	{
		$id_issue_receipt = (int) $id_issue_receipt;

		if ($id_issue_receipt <= 0) {
			return '';
		}

		$details = ir_no_fetch_details($db, array($id_issue_receipt));

		if (!isset($details[$id_issue_receipt])) {
			return '';
		}

		return ir_no_format_row($details[$id_issue_receipt], ir_no_templates($db));
	}
}
