<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

// Each Tally API is NOT always a single table -- some (Receipt, Payment, Purchase) pull from
// several tables, each with its own "istransferred"-style flag column, and merge them into one
// payload. This map was built by tracing every live query inside
// application/models/ret_tally_api_model.php (getSalesNewVoucherList, getPurchasaeNewVoucherList,
// getSalesReturnNewVoucherList, getAllreceiptsList, getAllimporissuepaymentsList) -- it is the
// single source of truth for which table/column combination each API actually reads and gates on.
class Tally_transfer_model extends CI_Model
{
    private $apiConfig = array(
        'sales' => array(
            'label' => 'Sales',
            'sources' => array(
                array(
                    'key' => 'billing', 'label' => 'Sales Bill', 'kind' => 'billing',
                    'flag_col' => 'istransfered', 'ref_col' => 'sales_ref_no',
                ),
            ),
        ),
        'purchase' => array(
            'label' => 'Purchase',
            'sources' => array(
                array(
                    'key' => 'billing', 'label' => 'Purchase Bill (Old Metal)', 'kind' => 'billing',
                    'flag_col' => 'ispurtransfered', 'ref_col' => 'pur_ref_no',
                ),
                array(
                    'key' => 'grn', 'label' => 'GRN Entry', 'kind' => 'grn',
                    'flag_col' => 'istransfered',
                ),
            ),
        ),
        'creditnote' => array(
            'label' => 'Credit Note (Sales Return)',
            'sources' => array(
                array(
                    'key' => 'billing', 'label' => 'Sales Return Bill', 'kind' => 'billing',
                    'flag_col' => 'issrtransfered', 'ref_col' => 's_ret_refno',
                ),
            ),
        ),
        'receipt' => array(
            'label' => 'Receipt',
            'sources' => array(
                array(
                    'key' => 'billing_payment', 'label' => 'Bill Payment', 'kind' => 'billing_child',
                    'table' => 'ret_billing_payment', 'id_col' => 'id_billing_payment', 'flag_col' => 'istransfered',
                    'amount_col' => 'payment_amount', 'extra_where' => 'pay.type = 1',
                ),
                array(
                    'key' => 'gift_voucher', 'label' => 'Gift Voucher', 'kind' => 'billing_child',
                    'table' => 'ret_billing_gift_voucher_details', 'id_col' => 'bill_gift_voucher_id', 'flag_col' => 'istransfered',
                    'amount_col' => 'gift_voucher_amt', 'extra_where' => '',
                ),
                array(
                    'key' => 'chit_utilization', 'label' => 'Chit Utilization', 'kind' => 'billing_child',
                    'table' => 'ret_billing_chit_utilization', 'id_col' => 'bill_chit_ut_id', 'flag_col' => 'istransfered',
                    'amount_col' => 'utilized_amt', 'extra_where' => 'pay.scheme_account_id != 0 AND pay.utilized_amt > 0',
                ),
                array(
                    'key' => 'issue_receipt', 'label' => 'Issue Receipt', 'kind' => 'issue_receipt',
                    'flag_col' => 'istransfered', 'pay_type' => 1, 'extra_where' => 'b.amount > 0',
                ),
                array(
                    'key' => 'chit_payment', 'label' => 'Chit / Advance Payment', 'kind' => 'chit_payment',
                    'flag_col' => 'istallytransfered',
                ),
            ),
        ),
        'payment' => array(
            'label' => 'Payment (Issue)',
            'sources' => array(
                array(
                    'key' => 'billing_payment', 'label' => 'Bill Payment', 'kind' => 'billing_child',
                    'table' => 'ret_billing_payment', 'id_col' => 'id_billing_payment', 'flag_col' => 'istransfered',
                    'amount_col' => 'payment_amount', 'extra_where' => 'pay.type = 2',
                ),
                array(
                    'key' => 'issue_receipt', 'label' => 'Issue Payment', 'kind' => 'issue_receipt',
                    'flag_col' => 'istransfered', 'pay_type' => 2, 'extra_where' => '',
                ),
            ),
        ),
    );

    // Upper bound on rows returned by a single date-range preview (applied per source, then
    // again on the merged/sorted set). Large support jobs can easily exceed a few hundred rows,
    // so this is generous -- but it is still a hard cap, and preview_records() reports back when
    // a search hits it so the UI can warn the admin instead of silently under-covering the range.
    const PREVIEW_LIMIT = 5000;

    public function get_api_list()
    {
        $out = array();
        foreach ($this->apiConfig as $key => $cfg) {
            $out[] = array('key' => $key, 'label' => $cfg['label']);
        }
        return $out;
    }

    private function get_config($api)
    {
        return isset($this->apiConfig[$api]) ? $this->apiConfig[$api] : null;
    }

    public function get_api_config($api)
    {
        return $this->get_config($api);
    }

    // Translates one source's declaration into the SQL fragments every operation needs.
    // This is the only place that knows the real table/column/join shape for a source "kind" --
    // preview/count/find/update below are all generic over whatever this returns.
    private function get_source_parts($src)
    {
        switch ($src['kind']) {
            case 'billing':
                return array(
                    'select_extra' => "b." . $src['ref_col'] . " as ref_no, b.tot_bill_amount as tot_amount,
                        CONCAT(IFNULL(c.firstname,''),' ',IFNULL(c.lastname,'')) as party",
                    'from' => "FROM ret_billing b LEFT JOIN customer c ON c.id_customer = b.bill_cus_id",
                    'base_where' => "b.bill_status = 1 AND b.is_eda != 2 AND b." . $src['ref_col'] . " IS NOT NULL",
                    'date_col' => 'b.bill_date',
                    'branch_col' => 'b.id_branch',
                    'flag_col_q' => 'b.' . $src['flag_col'],
                    'id_col_q' => 'b.bill_id',
                    'id_col_bare' => 'bill_id',
                    'table' => 'ret_billing',
                    'ref_col' => $src['ref_col'],
                );

            case 'billing_child':
                $where = "b.bill_status = 1 AND b.is_eda != 2";
                if (!empty($src['extra_where'])) {
                    $where .= " AND " . $src['extra_where'];
                }
                return array(
                    'select_extra' => "b.bill_no as ref_no, pay." . $src['amount_col'] . " as tot_amount,
                        CONCAT(IFNULL(c.firstname,''),' ',IFNULL(c.lastname,'')) as party",
                    'from' => "FROM " . $src['table'] . " pay
                        LEFT JOIN ret_billing b ON b.bill_id = pay.bill_id
                        LEFT JOIN customer c ON c.id_customer = b.bill_cus_id",
                    'base_where' => $where,
                    'date_col' => 'b.bill_date',
                    'branch_col' => 'b.id_branch',
                    'flag_col_q' => 'pay.' . $src['flag_col'],
                    'id_col_q' => 'pay.' . $src['id_col'],
                    'id_col_bare' => $src['id_col'],
                    'table' => $src['table'],
                );

            case 'issue_receipt':
                $where = "b.bill_status = 1 AND b.is_eda != 2 AND pay.type = " . (int) $src['pay_type'];
                if (!empty($src['extra_where'])) {
                    $where .= " AND " . $src['extra_where'];
                }
                return array(
                    'select_extra' => "b.bill_no as ref_no, pay.payment_amount as tot_amount,
                        CONCAT(IFNULL(c.firstname,''),' ',IFNULL(c.lastname,'')) as party",
                    'from' => "FROM ret_issue_rcpt_payment pay
                        LEFT JOIN ret_issue_receipt b ON b.id_issue_receipt = pay.id_issue_rcpt
                        LEFT JOIN customer c ON c.id_customer = b.id_customer",
                    'base_where' => $where,
                    'date_col' => 'b.bill_date',
                    'branch_col' => 'b.id_branch',
                    'flag_col_q' => 'pay.' . $src['flag_col'],
                    'id_col_q' => 'pay.id_issue_rcpt_pay',
                    'id_col_bare' => 'id_issue_rcpt_pay',
                    'table' => 'ret_issue_rcpt_payment',
                );

            case 'chit_payment':
                return array(
                    'select_extra' => "p.receipt_no as ref_no, SUM(pmd.payment_amount) as tot_amount,
                        CONCAT(IFNULL(c.firstname,''),' ',IFNULL(c.lastname,'')) as party",
                    'from' => "FROM payment p
                        LEFT JOIN payment_mode_details pmd ON pmd.id_payment = p.id_payment
                        LEFT JOIN scheme_account sca ON sca.id_scheme_account = p.id_scheme_account
                        LEFT JOIN customer c ON c.id_customer = sca.id_customer",
                    'base_where' => "p.payment_status = 1 AND p.receipt_no IS NOT NULL AND pmd.payment_mode NOT IN ('CHQ','OG')",
                    'date_col' => 'p.date_payment',
                    'branch_col' => 'p.id_branch',
                    'flag_col_q' => 'p.' . $src['flag_col'],
                    'id_col_q' => 'p.id_payment',
                    'id_col_bare' => 'id_payment',
                    'table' => 'payment',
                    'group_by' => 'p.id_payment',
                );

            case 'grn':
                return array(
                    'select_extra' => "grn.grn_ref_no as ref_no, grn.grn_purchase_amt as tot_amount,
                        CONCAT(IFNULL(kar.firstname,''),' ',IFNULL(kar.lastname,'')) as party",
                    'from' => "FROM ret_grn_entry grn LEFT JOIN ret_karigar kar ON kar.id_karigar = grn.grn_karigar_id",
                    'base_where' => "grn.grn_bill_status = 1 AND grn.grn_type != 2",
                    'date_col' => 'grn.grn_date',
                    'branch_col' => null, // the live API applies no branch filter to GRN entries
                    'flag_col_q' => 'grn.' . $src['flag_col'],
                    'id_col_q' => 'grn.grn_id',
                    'id_col_bare' => 'grn_id',
                    'table' => 'ret_grn_entry',
                );
        }

        return null;
    }

    // status: '' (all), 'pending' (flag=0, still needs Tally to pick it up),
    // or 'transferred' (flag!=0, already sent -- these are the ones a reset would affect).
    public function preview_records($api, $filters)
    {
        $cfg = $this->get_config($api);
        if ($cfg === null) {
            return array('error' => 'Invalid API selected');
        }

        $status = isset($filters['status']) ? $filters['status'] : '';

        if ($filters['mode'] == 'billids') {
            return $this->preview_by_ids($cfg, $filters['bill_ids'], $status);
        }

        return $this->preview_by_daterange($cfg, $filters, $status);
    }

    private function preview_by_daterange($cfg, $filters, $status)
    {
        $from = date('Y-m-d', strtotime($filters['from_date']));
        $to   = date('Y-m-d', strtotime($filters['to_date']));
        $branch = (isset($filters['id_branch']) && $filters['id_branch'] != '' && $filters['id_branch'] > 0) ? (int) $filters['id_branch'] : 0;

        $allRows = array();
        $counts = array('total' => 0, 'pending' => 0, 'transferred' => 0);

        foreach ($cfg['sources'] as $src) {
            $allRows = array_merge($allRows, $this->preview_source_daterange($src, $from, $to, $branch, $status));
            $c = $this->count_source_daterange($src, $from, $to, $branch);
            $counts['total'] += $c['total'];
            $counts['pending'] += $c['pending'];
            $counts['transferred'] += $c['transferred'];
        }

        usort($allRows, function ($a, $b) {
            return strcmp($b['sort_date'], $a['sort_date']);
        });

        $truncated = count($allRows) > self::PREVIEW_LIMIT;
        if ($truncated) {
            $allRows = array_slice($allRows, 0, self::PREVIEW_LIMIT);
        }

        return array('rows' => $allRows, 'truncated' => $truncated, 'counts' => $counts);
    }

    private function preview_source_daterange($src, $from, $to, $branch, $status)
    {
        $p = $this->get_source_parts($src);

        $statusCond = '';
        if ($status === 'pending') {
            $statusCond = " AND " . $p['flag_col_q'] . " = 0";
        } elseif ($status === 'transferred') {
            $statusCond = " AND " . $p['flag_col_q'] . " != 0";
        }

        $branchCond = ($branch > 0 && $p['branch_col'] !== null) ? " AND " . $p['branch_col'] . " = " . $branch : "";

        $sql = "SELECT " . $p['id_col_q'] . " as row_id, " . $p['select_extra'] . ",
                    DATE_FORMAT(" . $p['date_col'] . ",'%d-%m-%Y') as display_date,
                    DATE(" . $p['date_col'] . ") as sort_date,
                    (CASE WHEN " . $p['flag_col_q'] . " = 0 THEN 1 ELSE 0 END) as pending
                " . $p['from'] . "
                WHERE " . $p['base_where'] . "
                    AND DATE(" . $p['date_col'] . ") BETWEEN " . $this->db->escape($from) . " AND " . $this->db->escape($to) . "
                    " . $branchCond . $statusCond . "
                " . (isset($p['group_by']) ? "GROUP BY " . $p['group_by'] : "") . "
                ORDER BY " . $p['date_col'] . " DESC
                LIMIT " . self::PREVIEW_LIMIT;

        $rows = $this->db->query($sql)->result_array();
        foreach ($rows as &$r) {
            $r['source'] = $src['key'];
            $r['source_label'] = $src['label'];
            $r['status'] = 'found';
        }
        return $rows;
    }

    // Unbounded (no LIMIT) total/pending/transferred breakdown for the searched range, so the
    // counts shown to the admin are exact even when the row cap truncates the preview table.
    private function count_source_daterange($src, $from, $to, $branch)
    {
        $p = $this->get_source_parts($src);
        $branchCond = ($branch > 0 && $p['branch_col'] !== null) ? " AND " . $p['branch_col'] . " = " . $branch : "";
        $dateCond = " AND DATE(" . $p['date_col'] . ") BETWEEN " . $this->db->escape($from) . " AND " . $this->db->escape($to);

        if (isset($p['group_by'])) {
            $sql = "SELECT COUNT(*) as total,
                        SUM(CASE WHEN pending_flag = 0 THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN pending_flag != 0 THEN 1 ELSE 0 END) as transferred
                    FROM (
                        SELECT " . $p['id_col_q'] . ", MAX(" . $p['flag_col_q'] . ") as pending_flag
                        " . $p['from'] . "
                        WHERE " . $p['base_where'] . $dateCond . $branchCond . "
                        GROUP BY " . $p['group_by'] . "
                    ) t";
        } else {
            $sql = "SELECT COUNT(*) as total,
                        SUM(CASE WHEN " . $p['flag_col_q'] . " = 0 THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN " . $p['flag_col_q'] . " != 0 THEN 1 ELSE 0 END) as transferred
                    " . $p['from'] . "
                    WHERE " . $p['base_where'] . $dateCond . $branchCond;
        }

        $row = $this->db->query($sql)->row_array();
        return array(
            'total'       => (int) $row['total'],
            'pending'     => (int) $row['pending'],
            'transferred' => (int) $row['transferred'],
        );
    }

    // Searches every source in the API for each pasted id/ref -- a single value could
    // legitimately match more than one source (e.g. it happens to also be a payment id), so all
    // matches are returned, tagged by source, rather than guessing which one the admin meant.
    private function preview_by_ids($cfg, $rawIds, $status)
    {
        $ids = $this->split_ids($rawIds);
        $rows = array();

        foreach ($ids as $idOrRef) {
            $matches = array();
            foreach ($cfg['sources'] as $src) {
                $row = $this->find_source_by_id($src, $idOrRef);
                if ($row !== null) {
                    $row['source'] = $src['key'];
                    $row['source_label'] = $src['label'];
                    $row['status'] = 'found';
                    $matches[] = $row;
                }
            }

            if (empty($matches)) {
                $rows[] = array(
                    'row_id'  => null,
                    'input_id' => $idOrRef,
                    'status'  => 'not_found',
                    'message' => 'Bill ID "' . $idOrRef . '" does not exist',
                );
            } else {
                $rows = array_merge($rows, $matches);
            }
        }

        return array(
            'rows'   => $this->filter_rows_by_status($rows, $status),
            'counts' => $this->summarize_counts($rows),
        );
    }

    // Splits a textarea of comma / newline separated bill ids or ref numbers into a clean list.
    private function split_ids($rawIds)
    {
        $parts = preg_split('/[\s,]+/', trim($rawIds));
        $ids = array();
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $ids[] = $p;
            }
        }
        return array_unique($ids);
    }

    private function find_source_by_id($src, $idOrRef)
    {
        $p = $this->get_source_parts($src);
        $escaped = $this->db->escape($idOrRef);

        $idMatch = $p['id_col_q'] . " = " . $escaped;
        if ($src['kind'] === 'billing' && !empty($p['ref_col'])) {
            $idMatch = "(" . $idMatch . " OR b." . $p['ref_col'] . " = " . $escaped . ")";
        }

        $sql = "SELECT " . $p['id_col_q'] . " as row_id, " . $p['select_extra'] . ",
                    DATE_FORMAT(" . $p['date_col'] . ",'%d-%m-%Y') as display_date,
                    DATE(" . $p['date_col'] . ") as sort_date,
                    (CASE WHEN " . $p['flag_col_q'] . " = 0 THEN 1 ELSE 0 END) as pending
                " . $p['from'] . "
                WHERE " . $p['base_where'] . " AND " . $idMatch . "
                " . (isset($p['group_by']) ? "GROUP BY " . $p['group_by'] : "") . "
                LIMIT 1";

        $row = $this->db->query($sql)->row_array();
        return $row ?: null;
    }

    // Keeps 'not_found' rows regardless of the status filter -- those are lookup errors,
    // not transfer-status data, and hiding them would defeat the "does this bill exist" check.
    private function filter_rows_by_status($rows, $status)
    {
        if ($status !== 'pending' && $status !== 'transferred') {
            return $rows;
        }

        $out = array();
        foreach ($rows as $row) {
            if ($row['status'] === 'not_found') {
                $out[] = $row;
                continue;
            }
            if (($status === 'pending' && $row['pending'] == 1) || ($status === 'transferred' && $row['pending'] == 0)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    private function summarize_counts($rows)
    {
        $total = 0;
        $pending = 0;
        $transferred = 0;
        foreach ($rows as $row) {
            if ($row['status'] === 'not_found') {
                continue;
            }
            $total++;
            if ($row['pending'] == 1) {
                $pending++;
            } else {
                $transferred++;
            }
        }
        return array('total' => $total, 'pending' => $pending, 'transferred' => $transferred);
    }

    // Resets the flag for the given "sourceKey|id" composite selections. Existence is checked
    // explicitly (rather than relying on affected_rows()) because MySQL reports 0 affected rows
    // when the flag is already 0 -- that is a legitimate "already pending" outcome, not a failure.
    public function reset_flags($api, $items)
    {
        $cfg = $this->get_config($api);
        if ($cfg === null) {
            return array('error' => 'Invalid API selected');
        }

        $sourcesByKey = array();
        foreach ($cfg['sources'] as $src) {
            $sourcesByKey[$src['key']] = $src;
        }

        $updated = array();
        $not_found = array();

        foreach ($items as $item) {
            $parts = explode('|', $item, 2);
            if (count($parts) != 2 || !isset($sourcesByKey[$parts[0]])) {
                $not_found[] = $item;
                continue;
            }
            list($sourceKey, $id) = $parts;
            $src = $sourcesByKey[$sourceKey];

            $exists = $this->find_source_by_id($src, $id);
            if ($exists === null) {
                $not_found[] = $src['label'] . ' #' . $id;
                continue;
            }

            $this->update_source($src, $id);
            $updated[] = $src['label'] . ' #' . $id;
        }

        return array(
            'updated'   => $updated,
            'not_found' => $not_found,
        );
    }

    private function update_source($src, $id)
    {
        $p = $this->get_source_parts($src);
        $this->db->query("UPDATE " . $p['table'] . "
            SET " . $src['flag_col'] . " = 0
            WHERE " . $p['id_col_bare'] . " = " . $this->db->escape($id));
    }
}
