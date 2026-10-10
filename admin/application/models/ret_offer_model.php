<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ret_offer_model
 *
 * Model for managing retail offer master records.
 * Handles CRUD operations, server-side DataTable processing,
 * validation, duplicate detection, and soft-delete logic
 * for the ret_offer_master table.
 *
 * @package     RetailERP
 * @subpackage  Models
 * @category    Offers & Discounts
 */
class Ret_offer_model extends CI_Model
{
    /**
     * Primary table for offer master records.
     *
     * @var string
     */
    private $table = 'ret_offer_master';

    /**
     * Audit log table — checked before hard/soft delete.
     *
     * @var string
     */
    private $audit_table = 'ret_offer_audit_log';

    /**
     * Discount fields that qualify as "at least one must be > 0".
     *
     * @var array
     */
    private $discount_fields = [
        'disc_metal_rate_pct',
        'disc_metal_rate_flat',
        'disc_mc_pct',
        'disc_va_pct',
        'disc_diamond_per_ct',
        'disc_diamond_flat_pct',
        'disc_taxable_value_pct',
    ];

    // ──────────────────────────────────────────────
    // Constructor
    // ──────────────────────────────────────────────

    /**
     * Constructor — load database.
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    // ──────────────────────────────────────────────
    // READ — List & Single
    // ──────────────────────────────────────────────

    /**
     * Get all non-deleted offers with optional filters.
     *
     * @param  array $filters  Optional keys: status (0|1), search (string),
     *                         date_from (Y-m-d), date_to (Y-m-d)
     * @return array           Array of offer rows, empty array if none found.
     */
    public function get_offer_master_list($filters = [])
    {
        $this->db->select('id_offer, offer_name, start_date, end_date, disc_metal_rate_pct, disc_metal_rate_flat, disc_mc_pct, disc_va_pct, disc_diamond_per_ct, disc_diamond_flat_pct, disc_taxable_value_pct, status, created_at');
        $this->db->from($this->table);
        $this->db->where('is_deleted', 0);

        // --- Optional filters ---

        // Status filter (strict comparison — 0 is a valid value)
        if (isset($filters['status']) && $filters['status'] !== '') {
            $this->db->where('status', (int) $filters['status']);
        }

        // Free-text search on offer_name
        if (!empty($filters['search'])) {
            $this->db->like('offer_name', $filters['search'], 'both');
        }

        // Date range: offers whose period overlaps with the filter range
        if (!empty($filters['date_from'])) {
            $this->db->where('end_date >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('start_date <=', $filters['date_to']);
        }

        $this->db->order_by('id_offer', 'DESC');

        $query = $this->db->get();

        return $query->num_rows() > 0 ? $query->result_array() : [];
    }

    /**
     * Server-side DataTable processing for offers list.
     *
     * Expects standard DataTable parameters (draw, start, length, search,
     * order) plus an optional custom filter: status_filter.
     *
     * @param  array $data  DataTable request parameters.
     * @return array        DataTable-compatible response with draw,
     *                      recordsTotal, recordsFiltered, data.
     */
    public function get_offer_master_ajax_list($data)
    {
        // Column mapping — index must match the front-end column order
        $columns = [
            0 => 'id_offer',
            1 => 'offer_name',
            2 => 'start_date',
            3 => 'end_date',
            4 => 'disc_metal_rate_pct',
            5 => 'disc_mc_pct',
            6 => 'disc_va_pct',
            7 => 'disc_diamond_per_ct',
            8 => 'disc_taxable_value_pct',
            9 => 'status',
        ];

        $draw   = isset($data['draw'])   ? (int) $data['draw']   : 1;
        $start  = isset($data['start'])  ? (int) $data['start']  : 0;
        $length = isset($data['length']) ? (int) $data['length'] : 10;

        $search_value  = isset($data['search']['value']) ? trim($data['search']['value']) : '';
        $order_column  = isset($data['order'][0]['column']) ? (int) $data['order'][0]['column'] : 0;
        $order_dir     = isset($data['order'][0]['dir']) && strtolower($data['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';
        $status_filter = isset($data['status_filter']) && $data['status_filter'] !== '' ? $data['status_filter'] : null;

        // Resolve sort column safely
        $sort_col = isset($columns[$order_column]) ? $columns[$order_column] : 'id_offer';

        // ── 1. Total records (unfiltered, non-deleted) ──
        $this->db->where('is_deleted', 0);
        $records_total = $this->db->count_all_results($this->table);

        // ── 2. Filtered count ──
        $this->db->where('is_deleted', 0);
        if ($status_filter !== null) {
            $this->db->where('status', (int) $status_filter);
        }
        if ($search_value !== '') {
            $this->db->group_start();
            $this->db->like('offer_name', $search_value, 'both');
            $this->db->or_like('start_date', $search_value, 'both');
            $this->db->or_like('end_date', $search_value, 'both');
            $this->db->group_end();
        }
        $records_filtered = $this->db->count_all_results($this->table);

        // ── 3. Data query with pagination ──
        $this->db->select('id_offer, offer_name, start_date, end_date, disc_metal_rate_pct, disc_metal_rate_flat, disc_mc_pct, disc_va_pct, disc_diamond_per_ct, disc_diamond_flat_pct, disc_taxable_value_pct, status, created_at');
        $this->db->from($this->table);
        $this->db->where('is_deleted', 0);
        if ($status_filter !== null) {
            $this->db->where('status', (int) $status_filter);
        }
        if ($search_value !== '') {
            $this->db->group_start();
            $this->db->like('offer_name', $search_value, 'both');
            $this->db->or_like('start_date', $search_value, 'both');
            $this->db->or_like('end_date', $search_value, 'both');
            $this->db->group_end();
        }
        $this->db->order_by($sort_col, $order_dir);
        $this->db->limit($length, $start);

        $query = $this->db->get();
        $rows  = $query->num_rows() > 0 ? $query->result_array() : [];

        // ── Format rows for DataTable ──
        $formatted = [];
        foreach ($rows as $row) {
            $formatted[] = $this->_format_datatable_row($row);
        }

        return [
            'draw'            => $draw,
            'recordsTotal'    => (int) $records_total,
            'recordsFiltered' => (int) $records_filtered,
            'data'            => $formatted,
        ];
    }

    /**
     * Get a single offer record by ID.
     *
     * @param  int   $id_offer  Primary key.
     * @return array             Single row as associative array, or empty array.
     */
    public function get_offer_master($id_offer)
    {
        $this->db->where('id_offer', (int) $id_offer);
        $this->db->where('is_deleted', 0);

        $query = $this->db->get($this->table);

        return $query->num_rows() > 0 ? $query->row_array() : [];
    }

    // ──────────────────────────────────────────────
    // AUDIT LOG — Usage tracking
    // ──────────────────────────────────────────────

    /**
     * Record that an offer discount was actually persisted against a
     * bill/estimation. Called from Ret_estimation_model and
     * Ret_billing_model's _sanitize_offer_data() — the single choke point
     * every ret_estimation_items / ret_bill_details write passes through —
     * once coin/wastage exclusion and chit-weight scaling have produced
     * the final discount amount for that row.
     *
     * Writes one row per individual offer ID (id_offer may be a CSV of
     * combined offers, e.g. "4,7") so delete_offer_master()'s simple
     * equality lookup against this table works without needing FIND_IN_SET.
     *
     * Schema matches the `ret_offer_audit_log` table as it already exists
     * on live/staging databases (reference_type/reference_id/reference_no,
     * total_discount_amt, applied_by/at, id_branch). The per-component
     * breakdown columns (disc_metal_amt, disc_mc_amt, disc_va_amt,
     * disc_diamond_amt, disc_taxable_amt) and the MSV-override columns
     * (msv_override, override_otp_id, override_approved_by) are left at
     * their defaults here — that data isn't available at this layer yet;
     * only a combined discount total reaches _sanitize_offer_data().
     *
     * @param  array $params  Keys: id_offer (CSV or single ID), source_type
     *                        ('estimation'|'billing'), source_id (esti/bill
     *                        ID, required — rows with no resolvable
     *                        reference are skipped), reference_no
     *                        (esti_no/bill_no, optional), id_branch
     *                        (optional), discount_amount.
     * @return bool  true on success (or nothing to log), false if the write
     *               itself failed — callers must treat false as non-fatal
     *               (log/ignore), never as a reason to roll back a sale.
     */
    public function log_offer_usage($params)
    {
        $offer_csv = isset($params['id_offer']) ? trim($params['id_offer']) : '';
        if ($offer_csv === '') {
            return true;
        }

        $offer_ids = array_filter(array_map('intval', explode(',', $offer_csv)));
        if (empty($offer_ids)) {
            return true;
        }

        // Guard proactively rather than catching after the fact: a failed
        // INSERT here would set $this->db->_trans_status = FALSE even with
        // db_debug off, silently rolling back the ENCLOSING bill/estimation
        // transaction this is called from. table_exists() runs a harmless
        // SHOW TABLES lookup that can't itself fail, so checking first keeps
        // a missing/out-of-band-mismatched audit table from ever touching
        // the caller's transaction status.
        if (!$this->db->table_exists($this->audit_table)) {
            log_message('error', 'log_offer_usage skipped: ' . $this->audit_table . ' does not exist');
            return false;
        }

        // reference_id is NOT NULL in the schema — nothing to log without one.
        $reference_id = !empty($params['source_id']) ? (int) $params['source_id'] : null;
        if ($reference_id === null) {
            return true;
        }

        $reference_type = (isset($params['source_type']) && $params['source_type'] === 'billing')
            ? 'bill'
            : 'estimation';
        $reference_no  = isset($params['reference_no']) && $params['reference_no'] !== '' ? $params['reference_no'] : null;
        $id_branch     = !empty($params['id_branch']) ? (int) $params['id_branch'] : null;
        $combined_amt  = isset($params['discount_amount']) ? (float) $params['discount_amount'] : 0.0;
        // Split evenly across the offers actually applied to this line, so a
        // naive SUM(total_discount_amt) per reference_id isn't inflated by
        // writing the full combined amount once per CSV entry (one row per
        // offer id is kept, since delete_offer_master()'s usage guard relies
        // on a simple equality lookup against this column).
        $per_offer_amt = round($combined_amt / count($offer_ids), 2);
        $applied_by    = $this->session->userdata('uid');
        $applied_at    = date('Y-m-d H:i:s');

        try {
            foreach ($offer_ids as $id_offer) {
                $this->db->insert($this->audit_table, [
                    'id_offer'           => $id_offer,
                    'reference_type'     => $reference_type,
                    'reference_id'       => $reference_id,
                    'reference_no'       => $reference_no,
                    'total_discount_amt' => $per_offer_amt,
                    'applied_by'         => $applied_by,
                    'applied_at'         => $applied_at,
                    'id_branch'          => $id_branch,
                ]);
            }
        } catch (Exception $e) {
            log_message('error', 'log_offer_usage failed for reference_type=' . $reference_type . ' reference_id=' . $reference_id . ': ' . $e->getMessage());
            return false;
        }

        return true;
    }

    // ──────────────────────────────────────────────
    // WRITE — Save / Delete / Toggle
    // ──────────────────────────────────────────────

    /**
     * Save (insert or update) an offer master record.
     *
     * Validates input, checks for duplicate offers, then inserts or
     * updates within a database transaction.
     *
     * @param  array $data  Offer fields from the form.
     * @return array        ['status' => bool, 'msg' => string, 'id' => int]
     */
    public function save_offer_master($data)
    {
        // Step 1: Validate
        $validation = $this->validate_offer_data($data);
        if (!$validation['status']) {
            return ['status' => false, 'msg' => $validation['msg']];
        }

        // Step 2: Duplicate check
        $exclude_id  = !empty($data['id_offer']) ? (int) $data['id_offer'] : null;
        $is_duplicate = $this->check_duplicate_offer(
            $data['offer_name'],
            $data['start_date'],
            $data['end_date'],
            $exclude_id
        );

        if ($is_duplicate) {
            return [
                'status' => false,
                'msg'    => 'An active offer with the same name already exists for an overlapping date range.',
            ];
        }

        // Step 3: Prepare save payload (strip non-column keys)
        $save_data = $this->_prepare_save_payload($data);

        // Step 4: Insert or Update inside a transaction
        $this->db->trans_start();

        if (!empty($data['id_offer'])) {
            // ── UPDATE ──
            $id_offer = (int) $data['id_offer'];
            $save_data['updated_by'] = $this->session->userdata('uid');
            $save_data['updated_at'] = date('Y-m-d H:i:s');

            $this->updateData($save_data, 'id_offer', $id_offer, $this->table);
        } else {
            // ── INSERT ──
            $save_data['created_by'] = $this->session->userdata('uid');
            $save_data['created_at'] = date('Y-m-d H:i:s');
            $save_data['is_deleted'] = 0;
            if (!isset($save_data['status'])) {
                $save_data['status'] = 1; // Default to active only if not set
            }

            $this->insertData($save_data, $this->table);
            $id_offer = $this->db->insert_id();
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['status' => false, 'msg' => 'Database error — could not save offer.'];
        }

        return [
            'status' => true,
            'msg'    => 'Offer saved successfully.',
            'id'     => (int) $id_offer,
        ];
    }

    /**
     * Soft-delete an offer master record.
     *
     * Checks whether the offer has been applied before allowing deletion.
     * The audit log alone isn't authoritative — logging is intentionally
     * non-fatal (a missing/mismatched ret_offer_audit_log must never roll
     * back a live sale), so a bill/estimation can carry id_offer while the
     * audit log has no matching row for it. Also check ret_bill_details /
     * ret_estimation_items directly via FIND_IN_SET, since id_offer there
     * is a CSV string (e.g. "4,7"), not a plain equality match.
     *
     * @param  int   $id_offer  Primary key.
     * @return array            ['status' => bool, 'msg' => string]
     */
    public function delete_offer_master($id_offer)
    {
        $id_offer = (int) $id_offer;

        // Guard: check if offer has been used in audit log
        $this->db->where('id_offer', $id_offer);
        $usage_count = $this->db->count_all_results($this->audit_table);

        // Guard: also check the transactional tables directly (see docblock).
        $this->db->where("FIND_IN_SET({$id_offer}, id_offer) >", 0);
        $bill_usage_count = $this->db->count_all_results('ret_bill_details');

        $this->db->where("FIND_IN_SET({$id_offer}, id_offer) >", 0);
        $estimation_usage_count = $this->db->count_all_results('ret_estimation_items');

        if ($usage_count > 0 || $bill_usage_count > 0 || $estimation_usage_count > 0) {
            return [
                'status' => false,
                'msg'    => 'Cannot delete — offer has been applied to bills/estimations.',
            ];
        }

        // Soft delete
        $update = [
            'is_deleted'  => 1,
            'updated_by'  => $this->session->userdata('uid'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $this->updateData($update, 'id_offer', $id_offer, $this->table);

        return ['status' => true, 'msg' => 'Offer deleted successfully.'];
    }

    /**
     * Toggle the status of an offer (Active ↔ Inactive).
     *
     * @param  int   $id_offer  Primary key.
     * @return array            ['status' => bool, 'new_status' => int]
     */
    public function toggle_offer_status($id_offer)
    {
        $id_offer = (int) $id_offer;

        // Fetch current status
        $this->db->select('status');
        $this->db->where('id_offer', $id_offer);
        $this->db->where('is_deleted', 0);
        $query = $this->db->get($this->table);

        if ($query->num_rows() === 0) {
            return ['status' => false, 'msg' => 'Offer not found.'];
        }

        $current    = (int) $query->row()->status;
        $new_status = $current === 1 ? 0 : 1;

        $update = [
            'status'     => $new_status,
            'updated_by' => $this->session->userdata('uid'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->updateData($update, 'id_offer', $id_offer, $this->table);

        return ['status' => true, 'new_status' => $new_status];
    }

    // ──────────────────────────────────────────────
    // VALIDATION & DUPLICATE CHECK
    // ──────────────────────────────────────────────

    /**
     * Check for a duplicate active offer with the same name and
     * an overlapping date range.
     *
     * Overlap condition:
     *   existing.start_date <= $end_date
     *   AND existing.end_date >= $start_date
     *   AND LOWER(offer_name) = LOWER($offer_name)
     *
     * @param  string   $offer_name  Offer name to check.
     * @param  string   $start_date  Start date (Y-m-d).
     * @param  string   $end_date    End date (Y-m-d).
     * @param  int|null $exclude_id  ID to exclude (edit mode).
     * @return bool                  TRUE if a duplicate exists.
     */
    public function check_duplicate_offer($offer_name, $start_date, $end_date, $exclude_id = null)
    {
        $this->db->from($this->table);
        $this->db->where('is_deleted', 0);
        $this->db->where('LOWER(offer_name)', strtolower(trim($offer_name)));
        $this->db->where('start_date <=', $end_date);
        $this->db->where('end_date >=', $start_date);

        if ($exclude_id !== null) {
            $this->db->where('id_offer !=', (int) $exclude_id);
        }

        return $this->db->count_all_results() > 0;
    }

    /**
     * Validate offer data before save.
     *
     * Rules:
     *  - offer_name: required, max 100 chars
     *  - start_date & end_date: required, end >= start
     *  - At least one discount field must be > 0
     *  - Metal rate: cannot have BOTH pct AND flat > 0
     *  - Diamond: cannot have BOTH per_ct AND flat_pct > 0
     *  - No discount field may be negative
     *  - Percentage-type discount fields cannot exceed 100
     *
     * @param  array $data  Offer form data.
     * @return array        ['status' => bool, 'msg' => string (on failure)]
     */
    public function validate_offer_data($data)
    {
        // ── Offer name ──
        if (empty($data['offer_name']) || trim($data['offer_name']) === '') {
            return ['status' => false, 'msg' => 'Offer name is required.'];
        }
        if (strlen(trim($data['offer_name'])) > 100) {
            return ['status' => false, 'msg' => 'Offer name must not exceed 100 characters.'];
        }

        // ── Dates ──
        if (empty($data['start_date'])) {
            return ['status' => false, 'msg' => 'Start date is required.'];
        }
        if (empty($data['end_date'])) {
            return ['status' => false, 'msg' => 'End date is required.'];
        }
        if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
            return ['status' => false, 'msg' => 'End date must be equal to or after the start date.'];
        }

        // ── At least one discount > 0 ──
        $has_discount = false;
        foreach ($this->discount_fields as $field) {
            if (isset($data[$field]) && floatval($data[$field]) > 0) {
                $has_discount = true;
                break;
            }
        }
        if (!$has_discount) {
            return ['status' => false, 'msg' => 'At least one discount field must have a value greater than zero.'];
        }

        // ── Metal rate: pct and flat are mutually exclusive ──
        $metal_pct  = isset($data['disc_metal_rate_pct'])  ? floatval($data['disc_metal_rate_pct'])  : 0;
        $metal_flat = isset($data['disc_metal_rate_flat']) ? floatval($data['disc_metal_rate_flat']) : 0;
        if ($metal_pct > 0 && $metal_flat > 0) {
            return [
                'status' => false,
                'msg'    => 'Metal rate discount cannot have both percentage and flat amount. Choose one.',
            ];
        }

        // ── Diamond: per_ct and flat_pct are mutually exclusive ──
        $diamond_ct   = isset($data['disc_diamond_per_ct'])   ? floatval($data['disc_diamond_per_ct'])   : 0;
        $diamond_flat = isset($data['disc_diamond_flat_pct']) ? floatval($data['disc_diamond_flat_pct']) : 0;
        if ($diamond_ct > 0 && $diamond_flat > 0) {
            return [
                'status' => false,
                'msg'    => 'Diamond discount cannot have both per-carat and flat percentage. Choose one.',
            ];
        }

        // ── Range checks (BRS §7.1): no discount field may be negative;
        //    percentage-type fields cannot exceed 100 ──
        foreach ($this->discount_fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                continue;
            }
            if (floatval($data[$field]) < 0) {
                return [
                    'status' => false,
                    'msg'    => 'Discount fields cannot be negative.',
                ];
            }
        }

        $percentage_fields = [
            'disc_metal_rate_pct',
            'disc_mc_pct',
            'disc_va_pct',
            'disc_diamond_flat_pct',
            'disc_taxable_value_pct',
        ];
        foreach ($percentage_fields as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null && floatval($data[$field]) > 100) {
                return [
                    'status' => false,
                    'msg'    => 'Percentage discount fields cannot exceed 100.',
                ];
            }
        }

        return ['status' => true];
    }

    // ──────────────────────────────────────────────
    // GENERIC HELPERS
    // ──────────────────────────────────────────────

    /**
     * Generic insert helper.
     *
     * @param  array  $data   Associative array of column => value.
     * @param  string $table  Table name.
     * @return bool           Insert result.
     */
    public function insertData($data, $table)
    {
        return $this->db->insert($table, $data);
    }

    /**
     * Generic update helper.
     *
     * @param  array  $data   Associative array of column => value.
     * @param  string $field  WHERE column name.
     * @param  mixed  $value  WHERE column value.
     * @param  string $table  Table name.
     * @return bool           Update result.
     */
    public function updateData($data, $field, $value, $table)
    {
        $this->db->where($field, $value);
        return $this->db->update($table, $data);
    }

    // ──────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────

    /**
     * Format a single database row for DataTable output.
     *
     * Generates HTML for status badges, discount display, action
     * buttons, and date formatting.
     *
     * @param  array $row  Raw database row.
     * @return array       Formatted row for DataTable JSON.
     */
    private function _format_datatable_row($row)
    {
        $id = (int) $row['id_offer'];

        // ── Date formatting (Y-m-d → d-m-Y) ──
        $start_date = !empty($row['start_date'])
            ? date('d-m-Y', strtotime($row['start_date']))
            : '-';
        $end_date = !empty($row['end_date'])
            ? date('d-m-Y', strtotime($row['end_date']))
            : '-';
        $created_at = !empty($row['created_at'])
            ? date('d-m-Y', strtotime($row['created_at']))
            : '-';

        // ── Status badge (AdminLTE label styling) ──
        $status_int = (int) $row['status'];
        if ($status_int === 1) {
            $status_badge = '<span class="label label-success">Active</span>';
        } else {
            $status_badge = '<span class="label label-danger">Inactive</span>';
        }

        // ── Per-column discount display ──
        $metal_rate_disc = '-';
        if (floatval($row['disc_metal_rate_pct']) > 0) {
            $metal_rate_disc = floatval($row['disc_metal_rate_pct']) . '%';
        } elseif (floatval($row['disc_metal_rate_flat']) > 0) {
            $metal_rate_disc = 'Rs.' . number_format(floatval($row['disc_metal_rate_flat']), 2);
        }

        $mc_disc = floatval($row['disc_mc_pct']) > 0
            ? floatval($row['disc_mc_pct']) . '%'
            : '-';

        $va_disc = floatval($row['disc_va_pct']) > 0
            ? floatval($row['disc_va_pct']) . '%'
            : '-';

        $diamond_disc = '-';
        if (floatval($row['disc_diamond_per_ct']) > 0) {
            $diamond_disc = 'Rs.' . number_format(floatval($row['disc_diamond_per_ct']), 2) . '/ct';
        } elseif (floatval($row['disc_diamond_flat_pct']) > 0) {
            $diamond_disc = floatval($row['disc_diamond_flat_pct']) . '%';
        }

        $taxable_value = floatval($row['disc_taxable_value_pct']) > 0
            ? floatval($row['disc_taxable_value_pct']) . '%'
            : '-';

        // ── Action buttons ──
        $toggle_label = $status_int === 1 ? 'Deactivate' : 'Activate';
        $toggle_class = $status_int === 1 ? 'btn-warning' : 'btn-success';
        $toggle_icon  = $status_int === 1 ? 'fa-ban' : 'fa-check';

        $actions  = '<div class="btn-group btn-group-sm" role="group">';
        $actions .= '<a href="' . base_url('index.php/admin_ret_offer/offer_master/edit/' . $id) . '" class="btn btn-info" title="Edit">';
        $actions .= '<i class="fa fa-pencil"></i></a>';
        $actions .= '<button type="button" class="btn ' . $toggle_class . ' btn-toggle-status" data-id="' . $id . '" title="' . $toggle_label . '">';
        $actions .= '<i class="fa ' . $toggle_icon . '"></i></button>';
        $actions .= '<button type="button" class="btn btn-danger btn-delete-offer" data-id="' . $id . '" title="Delete">';
        $actions .= '<i class="fa fa-trash"></i></button>';
        $actions .= '</div>';

        return [
            'id_offer'         => $id,
            'offer_name'       => htmlspecialchars($row['offer_name'], ENT_QUOTES, 'UTF-8'),
            'start_date'       => $start_date,
            'end_date'         => $end_date,
            'metal_rate_disc'  => $metal_rate_disc,
            'mc_disc'          => $mc_disc,
            'va_disc'          => $va_disc,
            'diamond_disc'     => $diamond_disc,
            'taxable_value'    => $taxable_value,
            'status_badge'     => $status_badge,
            'action'           => $actions,
        ];
    }

    /**
     * Prepare the save payload by extracting only valid DB columns
     * from the input data array.
     *
     * @param  array $data  Raw form data.
     * @return array        Cleaned associative array for insert/update.
     */
    private function _prepare_save_payload($data)
    {
        $allowed_columns = [
            'offer_name',
            'start_date',
            'end_date',
            'disc_metal_rate_pct',
            'disc_metal_rate_flat',
            'disc_mc_pct',
            'disc_va_pct',
            'disc_diamond_per_ct',
            'disc_diamond_flat_pct',
            'disc_taxable_value_pct',
            'status',
        ];

        $payload = [];
        foreach ($allowed_columns as $col) {
            if (array_key_exists($col, $data)) {
                $payload[$col] = $data[$col];
            }
        }

        // Sanitise: null-ish discount values → 0
        foreach ($this->discount_fields as $field) {
            if (isset($payload[$field])) {
                $payload[$field] = floatval($payload[$field]);
            } else {
                $payload[$field] = 0;
            }
        }

        // Trim name
        if (isset($payload['offer_name'])) {
            $payload['offer_name'] = trim($payload['offer_name']);
        }

        return $payload;
    }

    // ══════════════════════════════════════════════
    //  OFFER MAPPING — CRUD
    // ══════════════════════════════════════════════

    /**
     * Get all non-deleted mappings for the listing page.
     * JOINs offer name and metal type name for display.
     *
     * @return array  Array of mapping rows with resolved names.
     */
    public function get_mapping_list()
    {
        $this->db->select('
            om.id_mapping,
            om.id_offer,
            o.offer_name,
            om.id_metal_type,
            om.id_category,
            om.id_product,
            om.id_design,
            om.id_sub_design,
            om.id_branch,
            om.id_age_slab,
            om.created_at
        ', FALSE);
        $this->db->from('ret_offer_mapping om');
        $this->db->join('ret_offer_master o', 'o.id_offer = om.id_offer', 'left');
        $this->db->where('om.is_deleted', 0);
        $this->db->order_by('om.id_mapping', 'DESC');

        $query = $this->db->get();
        $rows = $query->num_rows() > 0 ? $query->result_array() : [];

        // Resolve CSV IDs to names for display
        foreach ($rows as &$row) {
            $row['metal_names'] = $this->_resolve_csv_names(
                $row['id_metal_type'], 'metal', 'id_metal', 'metal'
            );
            $row['category_names'] = $this->_resolve_csv_names(
                $row['id_category'], 'ret_category', 'id_ret_category', 'name'
            );
            $row['product_names'] = $this->_resolve_csv_names(
                $row['id_product'], 'ret_product_master', 'pro_id', 'product_name'
            );
            $row['design_names'] = $this->_resolve_csv_names(
                $row['id_design'], 'ret_design_master', 'design_no', 'design_name'
            );
            $row['sub_design_names'] = $this->_resolve_csv_names(
                $row['id_sub_design'], 'ret_sub_design_master', 'id_sub_design', 'sub_design_name'
            );
            $row['branch_names'] = $this->_resolve_csv_names(
                $row['id_branch'], 'branch', 'id_branch', 'name'
            );
            $row['slab_names'] = $this->_resolve_csv_names(
                $row['id_age_slab'], 'ret_offer_age_slab', 'id_slab', 'slab_name'
            );
        }

        return $rows;
    }

    /**
     * Get a single mapping record by ID.
     *
     * @param  int   $id_mapping  Primary key.
     * @return array              Single row, or empty array.
     */
    public function get_mapping($id_mapping)
    {
        $this->db->select('om.*, o.offer_name');
        $this->db->from('ret_offer_mapping om');
        $this->db->join('ret_offer_master o', 'o.id_offer = om.id_offer', 'left');
        $this->db->where('om.id_mapping', (int) $id_mapping);
        $this->db->where('om.is_deleted', 0);

        $query = $this->db->get();

        return $query->num_rows() > 0 ? $query->row_array() : [];
    }

    /**
     * Save (insert or update) an offer mapping record.
     *
     * @param  array $data  Mapping fields from the form.
     * @return array        ['status' => bool, 'msg' => string]
     */
    public function save_mapping($data)
    {
        // Step 1: Validate required fields
        if (empty($data['id_offer'])) {
            return ['status' => false, 'msg' => 'Offer is required.'];
        }

        // Verify offer exists and is not deleted
        $this->db->where('id_offer', (int) $data['id_offer']);
        $this->db->where('is_deleted', 0);
        $offer_check = $this->db->count_all_results($this->table);
        if ($offer_check === 0) {
            return ['status' => false, 'msg' => 'Offer not found or has been deleted.'];
        }

        // Note: Metal type is now optional multi-select (empty = "All metals")

        // Step 2: Duplicate check
        $exclude_id = !empty($data['id_mapping']) ? (int) $data['id_mapping'] : null;
        if ($this->_check_duplicate_mapping($data, $exclude_id)) {
            return [
                'status' => false,
                'msg' => 'A mapping with the same Offer, Metal Type, Categories, Products, and Branches already exists.',
            ];
        }

        // Step 3: Prepare save payload
        $save_data = [
            'id_offer'      => (int) $data['id_offer'],
            'id_metal_type' => !empty($data['id_metal_type']) ? trim($data['id_metal_type']) : null,
            'id_category'   => !empty($data['id_category']) ? trim($data['id_category']) : null,
            'id_product'    => !empty($data['id_product']) ? trim($data['id_product']) : null,
            'id_design'     => !empty($data['id_design']) ? trim($data['id_design']) : null,
            'id_sub_design' => !empty($data['id_sub_design']) ? trim($data['id_sub_design']) : null,
            'id_branch'     => !empty($data['id_branch']) ? trim($data['id_branch']) : null,
            'id_age_slab'   => !empty($data['id_age_slab']) ? trim($data['id_age_slab']) : null,
        ];

        // Step 4: Insert or Update
        $this->db->trans_start();

        if (!empty($data['id_mapping'])) {
            // ── UPDATE ──
            $save_data['updated_by'] = $this->session->userdata('uid');
            $save_data['updated_at'] = date('Y-m-d H:i:s');

            $this->updateData($save_data, 'id_mapping', (int) $data['id_mapping'], 'ret_offer_mapping');
        } else {
            // ── INSERT ──
            $save_data['created_by'] = $this->session->userdata('uid');
            $save_data['created_at'] = date('Y-m-d H:i:s');
            $save_data['is_deleted'] = 0;

            $this->insertData($save_data, 'ret_offer_mapping');
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['status' => false, 'msg' => 'Database error — could not save mapping.'];
        }

        return ['status' => true, 'msg' => 'Mapping saved successfully.'];
    }

    /**
     * Soft-delete a mapping record.
     *
     * @param  int   $id_mapping  Primary key.
     * @return array              ['status' => bool, 'msg' => string]
     */
    public function delete_mapping($id_mapping)
    {
        $id_mapping = (int) $id_mapping;

        $update = [
            'is_deleted'  => 1,
            'updated_by'  => $this->session->userdata('uid'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $this->updateData($update, 'id_mapping', $id_mapping, 'ret_offer_mapping');

        return ['status' => true, 'msg' => 'Mapping deleted successfully.'];
    }

    /**
     * Get active offers for dropdown (non-deleted only).
     *
     * @return array  Array of [id_offer, offer_name].
     */
    /**
     * Get offers for the mapping "Select Offer" dropdown.
     *
     * Only Active offers are selectable (BRS §7.2: "Select Offer — Dropdown
     * (Active Offers only)"). $include_offer_id lets the edit form keep
     * showing a mapping's already-assigned offer even if it has since been
     * deactivated, so editing an existing mapping never silently blanks out
     * or reassigns its offer.
     *
     * @param  int|null $include_offer_id  Offer ID to include regardless of status (edit mode).
     * @return array
     */
    public function get_active_offers_dropdown($include_offer_id = null)
    {
        $this->db->select('id_offer, offer_name');
        $this->db->from($this->table);
        $this->db->where('is_deleted', 0);

        if (!empty($include_offer_id)) {
            $this->db->where('(status = 1 OR id_offer = ' . (int) $include_offer_id . ')', null, false);
        } else {
            $this->db->where('status', 1);
        }

        $this->db->order_by('offer_name', 'ASC');

        $query = $this->db->get();

        return $query->num_rows() > 0 ? $query->result_array() : [];
    }

    /**
     * Check for duplicate mapping:
     * same offer + same metal type + same category CSV + same product CSV + same branch CSV.
     *
     * @param  array    $data        Mapping data.
     * @param  int|null $exclude_id  ID to exclude (edit mode).
     * @return array|false           FALSE if no overlap, or ['field' => string, 'ids' => array] if overlap found.
     */
    private function _check_duplicate_mapping($data, $exclude_id = null)
    {
        // Get all existing mappings for the same offer
        $this->db->select('id_mapping, id_metal_type, id_category, id_product, id_design, id_sub_design, id_branch');
        $this->db->from('ret_offer_mapping');
        $this->db->where('is_deleted', 0);
        $this->db->where('id_offer', (int) $data['id_offer']);

        if ($exclude_id !== null) {
            $this->db->where('id_mapping !=', (int) $exclude_id);
        }

        $query = $this->db->get();
        $existing = $query->num_rows() > 0 ? $query->result_array() : [];

        if (empty($existing)) {
            return false;
        }

        // Parse new mapping's CSV IDs
        $new_metals   = $this->_csv_to_array(!empty($data['id_metal_type']) ? $data['id_metal_type'] : '');
        $new_cats     = $this->_csv_to_array(!empty($data['id_category']) ? $data['id_category'] : '');
        $new_products = $this->_csv_to_array(!empty($data['id_product']) ? $data['id_product'] : '');
        $new_designs  = $this->_csv_to_array(!empty($data['id_design']) ? $data['id_design'] : '');
        $new_sub_des  = $this->_csv_to_array(!empty($data['id_sub_design']) ? $data['id_sub_design'] : '');
        $new_branches = $this->_csv_to_array(!empty($data['id_branch']) ? $data['id_branch'] : '');

        foreach ($existing as $row) {
            $ex_metals   = $this->_csv_to_array($row['id_metal_type']);
            $ex_cats     = $this->_csv_to_array($row['id_category']);
            $ex_products = $this->_csv_to_array($row['id_product']);
            $ex_designs  = $this->_csv_to_array($row['id_design']);
            $ex_sub_des  = $this->_csv_to_array($row['id_sub_design']);
            $ex_branches = $this->_csv_to_array($row['id_branch']);

            // All dimensions must overlap for it to be a true conflict
            $metal_overlap   = $this->_has_overlap($new_metals, $ex_metals);
            $cat_overlap     = $this->_has_overlap($new_cats, $ex_cats);
            $product_overlap = $this->_has_overlap($new_products, $ex_products);
            $design_overlap  = $this->_has_overlap($new_designs, $ex_designs);
            $sub_des_overlap = $this->_has_overlap($new_sub_des, $ex_sub_des);
            $branch_overlap  = $this->_has_overlap($new_branches, $ex_branches);

            if ($metal_overlap && $cat_overlap && $product_overlap && $design_overlap && $sub_des_overlap && $branch_overlap) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a CSV string to an array of integers.
     * Returns empty array if input is NULL/empty (meaning "All").
     *
     * @param  string|null $csv
     * @return array
     */
    private function _csv_to_array($csv)
    {
        if (empty($csv)) {
            return []; // Empty = "All"
        }
        return array_map('intval', array_filter(explode(',', $csv)));
    }

    /**
     * Check if two ID arrays overlap.
     * Rule: empty array = "All", which overlaps with everything.
     *
     * @param  array $a  New mapping IDs (empty = "All").
     * @param  array $b  Existing mapping IDs (empty = "All").
     * @return bool      TRUE if they overlap.
     */
    private function _has_overlap($a, $b)
    {
        // If either is "All" (empty), it overlaps with everything
        if (empty($a) || empty($b)) {
            return true;
        }
        // Both have specific IDs — check intersection
        return count(array_intersect($a, $b)) > 0;
    }

    /**
     * Resolve a CSV string of IDs to a comma-separated string of names.
     *
     * @param  string|null $csv_ids    Comma-separated IDs (e.g., "1,5,12").
     * @param  string      $table      Table to query.
     * @param  string      $id_col     ID column name.
     * @param  string      $name_col   Name column name.
     * @return string                  Comma-separated names, or "All" if NULL/empty.
     */
    private function _resolve_csv_names($csv_ids, $table, $id_col, $name_col)
    {
        if (empty($csv_ids)) {
            return 'All';
        }

        $ids = array_map('intval', explode(',', $csv_ids));
        $this->db->select($name_col);
        $this->db->from($table);
        $this->db->where_in($id_col, $ids);

        $query = $this->db->get();

        if ($query->num_rows() === 0) {
            return 'All';
        }

        $names = [];
        foreach ($query->result_array() as $row) {
            $names[] = $row[$name_col];
        }

        return implode(', ', $names);
    }

    // ══════════════════════════════════════════════
    //  AGE SLAB — CRUD (Quick Add from mapping form)
    // ══════════════════════════════════════════════

    /**
     * Save a new age slab (from quick-add modal).
     *
     * @param  array $data  Slab fields: slab_name, min_days, max_days.
     * @return array        ['status' => bool, 'msg' => string, 'data' => [id_slab, slab_name]]
     */
    public function save_slab($data)
    {
        // Validate slab name
        $slab_name = isset($data['slab_name']) ? trim($data['slab_name']) : '';
        if (empty($slab_name)) {
            return ['status' => false, 'msg' => 'Slab name is required.'];
        }

        // Validate min_days
        if (!isset($data['min_days']) || $data['min_days'] === '' || $data['min_days'] === null) {
            return ['status' => false, 'msg' => 'Min days is required.'];
        }
        $min_days = (int) $data['min_days'];
        if ($min_days < 0) {
            return ['status' => false, 'msg' => 'Min days must be 0 or greater.'];
        }

        // Validate max_days (can be empty = open-ended)
        $max_days = null;
        if (isset($data['max_days']) && $data['max_days'] !== '') {
            $max_days = (int) $data['max_days'];
            if ($max_days < 0) {
                return ['status' => false, 'msg' => 'Max days must be 0 or greater.'];
            }
            if ($max_days < $min_days) {
                return ['status' => false, 'msg' => 'Max days must be greater than or equal to Min days.'];
            }
        }

        // Both zero check
        if ($min_days === 0 && $max_days === 0) {
            return ['status' => false, 'msg' => 'Min and Max cannot both be 0.'];
        }

        // Duplicate name check
        if ($this->_check_duplicate_slab($slab_name)) {
            return ['status' => false, 'msg' => 'A slab with this name already exists.'];
        }

        // Build display label
        $label = $slab_name . ' (' . $min_days;
        if ($max_days !== null) {
            $label .= '-' . $max_days;
        } else {
            $label .= '+';
        }
        $label .= ' days)';

        // Insert
        $save_data = [
            'slab_name'  => $slab_name,
            'min_days'   => $min_days,
            'max_days'   => $max_days,
            'status'     => 1,
            'created_by' => $this->session->userdata('uid'),
            'created_at' => date('Y-m-d H:i:s'),
            'is_deleted' => 0,
        ];

        $this->insertData($save_data, 'ret_offer_age_slab');
        $id_slab = $this->db->insert_id();

        return [
            'status' => true,
            'msg'    => 'Age slab created successfully.',
            'data'   => [
                'id_slab'   => $id_slab,
                'slab_name' => $slab_name,
                'label'     => $label,
            ],
        ];
    }

    /**
     * Get all active, non-deleted age slabs for dropdown.
     *
     * @return array  Array of slab rows.
     */
    public function get_active_slabs()
    {
        $this->db->select('id_slab, slab_name, min_days, max_days');
        $this->db->from('ret_offer_age_slab');
        $this->db->where('status', 1);
        $this->db->where('is_deleted', 0);
        $this->db->order_by('min_days', 'ASC');

        $query = $this->db->get();

        return $query->num_rows() > 0 ? $query->result_array() : [];
    }

    /**
     * Check for duplicate slab name.
     *
     * @param  string $slab_name  Name to check.
     * @return bool               TRUE if duplicate exists.
     */
    private function _check_duplicate_slab($slab_name)
    {
        $this->db->from('ret_offer_age_slab');
        $this->db->where('is_deleted', 0);
        $this->db->where('LOWER(slab_name)', strtolower(trim($slab_name)));

        return $this->db->count_all_results() > 0;
    }

    // ──────────────────────────────────────────────
    // Estimation Offer Eligibility Engine
    // ──────────────────────────────────────────────

    /**
     * Get all eligible offers for a set of estimation items.
     *
     * Checks active offers within date range, then matches each item
     * against offer mappings (metal, category, product, design, branch, age slab).
     * An item matches an offer if it matches ANY of that offer's mappings.
     * One offer per product — user picks in the popup.
     *
     * @param  array $params  Keys: id_branch, est_date, items[]
     * @return array          ['offers' => [...], 'item_offers' => [row_index => [offer_ids]]]
     */
    public function get_eligible_offers($params)
    {
        $empty_result = ['offers' => [], 'item_offers' => []];

        $branch   = isset($params['id_branch']) ? (int) $params['id_branch'] : 0;
        $est_date = isset($params['est_date']) ? $params['est_date'] : date('Y-m-d');
        $items    = isset($params['items']) ? $params['items'] : [];

        if (empty($items)) {
            return $empty_result;
        }

        // ── Step 1: Get all active offers within date range ──
        $this->db->select('id_offer, offer_name, start_date, end_date,
            disc_metal_rate_pct, disc_metal_rate_flat,
            disc_mc_pct, disc_va_pct,
            disc_diamond_per_ct, disc_diamond_flat_pct,
            disc_taxable_value_pct');
        $this->db->from('ret_offer_master');
        $this->db->where('status', 1);
        $this->db->where('is_deleted', 0);
        $this->db->where('start_date <=', $est_date);
        $this->db->where('end_date >=', $est_date);

        $offer_query = $this->db->get();
        $offers = $offer_query->num_rows() > 0 ? $offer_query->result_array() : [];

        if (empty($offers)) {
            return $empty_result;
        }

        // Build offer ID list and indexed lookup
        $offer_ids = [];
        $offer_map = []; // id_offer => offer row
        foreach ($offers as $offer) {
            $offer_ids[] = (int) $offer['id_offer'];
            $offer_map[(int) $offer['id_offer']] = $offer;
        }

        // ── Step 2: Get all mappings for active offers (single query) ──
        $this->db->select('id_mapping, id_offer, id_metal_type, id_category,
            id_product, id_design, id_sub_design, id_branch, id_age_slab');
        $this->db->from('ret_offer_mapping');
        $this->db->where('is_deleted', 0);
        $this->db->where_in('id_offer', $offer_ids);

        $mapping_query = $this->db->get();
        $mappings = $mapping_query->num_rows() > 0 ? $mapping_query->result_array() : [];

        if (empty($mappings)) {
            return $empty_result;
        }

        // Group mappings by offer ID
        $offer_mappings = []; // id_offer => [mapping rows]
        foreach ($mappings as $m) {
            $oid = (int) $m['id_offer'];
            if (!isset($offer_mappings[$oid])) {
                $offer_mappings[$oid] = [];
            }
            $offer_mappings[$oid][] = $m;
        }

        // ── Step 3: Load age slabs if any mapping uses them ──
        $slab_ids_used = [];
        foreach ($mappings as $m) {
            if (!empty($m['id_age_slab'])) {
                $slab_csv = explode(',', $m['id_age_slab']);
                foreach ($slab_csv as $sid) {
                    $slab_ids_used[] = (int) trim($sid);
                }
            }
        }
        $slab_ids_used = array_unique($slab_ids_used);

        $slab_map = []; // id_slab => ['min_days', 'max_days']
        if (!empty($slab_ids_used)) {
            $this->db->select('id_slab, min_days, max_days');
            $this->db->from('ret_offer_age_slab');
            $this->db->where('status', 1);
            $this->db->where('is_deleted', 0);
            $this->db->where_in('id_slab', $slab_ids_used);

            $slab_query = $this->db->get();
            if ($slab_query->num_rows() > 0) {
                foreach ($slab_query->result_array() as $slab) {
                    $slab_map[(int) $slab['id_slab']] = $slab;
                }
            }
        }

        // ── Step 4: Resolve product details (metal type, category ID) from DB to override DOM scraper gaps ──
        $product_ids = [];
        foreach ($items as $item) {
            if (!empty($item['product_id'])) {
                $product_ids[] = (int) $item['product_id'];
            }
        }
        $product_ids = array_unique($product_ids);

        $product_details = [];
        if (!empty($product_ids)) {
            $this->db->select('p.pro_id, p.cat_id, p.metal_type, p.product_name, p.min_wastage, p.max_wastage, c.name as category_name');
            $this->db->from('ret_product_master p');
            $this->db->join('ret_category c', 'p.cat_id = c.id_ret_category', 'left');
            $this->db->where_in('p.pro_id', $product_ids);
            $query = $this->db->get();
            if ($query->num_rows() > 0) {
                foreach ($query->result_array() as $p_row) {
                    $product_details[(int) $p_row['pro_id']] = [
                        'id_category'   => $p_row['cat_id'],
                        'id_metal'      => $p_row['metal_type'],
                        'product_name'  => $p_row['product_name'],
                        'category_name' => $p_row['category_name'],
                        'min_wastage'   => $p_row['min_wastage'],
                        'max_wastage'   => $p_row['max_wastage'],
                    ];
                }
            }
        }

        // ── Step 4b: Resolve tag dates from DB for age slab checks ──
        $tag_ids = [];
        foreach ($items as $item) {
            if (!empty($item['tag_id'])) {
                $tag_ids[] = (int) $item['tag_id'];
            }
        }
        $tag_ids = array_unique($tag_ids);

        $tag_dates = []; // tag_id => 'Y-m-d'
        if (!empty($tag_ids)) {
            $this->db->select('tag_id, tag_datetime');
            $this->db->from('ret_taging');
            $this->db->where_in('tag_id', $tag_ids);
            $query = $this->db->get();
            if ($query->num_rows() > 0) {
                foreach ($query->result_array() as $t_row) {
                    // Extract date portion from datetime
                    $tag_dates[(int) $t_row['tag_id']] = substr($t_row['tag_datetime'], 0, 10);
                }
            }
        }

        // ── Step 5: Match items against offer mappings ──
        $item_offers  = []; // row_index => [offer_ids]
        $matched_offer_ids = []; // collect which offers actually matched

        foreach ($items as $item) {
            $row_idx = isset($item['row_index']) ? (int) $item['row_index'] : 0;
            $item_product  = isset($item['product_id']) ? trim($item['product_id']) : '';

            // Override metal and category from DB master record if found
            $p_id = (int) $item_product;
            if (isset($product_details[$p_id])) {
                $item_metal    = (string) $product_details[$p_id]['id_metal'];
                $item_category = (string) $product_details[$p_id]['id_category'];
                
                // Exclude coin products or categories (Section 6.5)
                $p_name = $product_details[$p_id]['product_name'];
                $c_name = $product_details[$p_id]['category_name'];
                if (stripos($p_name, 'coin') !== false || stripos($c_name, 'coin') !== false) {
                    continue;
                }
                
                // Exclude products without configured wastage (Section 6.5)
                $min_wast = isset($product_details[$p_id]['min_wastage']) ? (float)$product_details[$p_id]['min_wastage'] : 0.0;
                $max_wast = isset($product_details[$p_id]['max_wastage']) ? (float)$product_details[$p_id]['max_wastage'] : 0.0;
                if ($min_wast == 0.0 && $max_wast == 0.0) {
                    continue;
                }
            } else {
                $item_metal    = isset($item['id_metal']) ? trim($item['id_metal']) : '';
                $item_category = isset($item['id_category']) ? trim($item['id_category']) : '';
            }

            $item_design   = isset($item['design_id']) ? trim($item['design_id']) : '';

            // Resolve tag_date: prefer DB lookup, fallback to front-end value
            $item_tag_id = isset($item['tag_id']) ? (int) $item['tag_id'] : 0;
            if ($item_tag_id > 0 && isset($tag_dates[$item_tag_id])) {
                $item_tag_date = $tag_dates[$item_tag_id];
            } else {
                $item_tag_date = isset($item['tag_date']) ? trim($item['tag_date']) : '';
            }

            // Calculate item age in days (from tag_date to est_date)
            $item_age_days = 0;
            if (!empty($item_tag_date) && $item_tag_date !== '0000-00-00') {
                $tag_ts = strtotime($item_tag_date);
                $est_ts = strtotime($est_date);
                if ($tag_ts && $est_ts && $est_ts >= $tag_ts) {
                    $item_age_days = (int) floor(($est_ts - $tag_ts) / 86400);
                }
            }

            foreach ($offer_ids as $oid) {
                if (!isset($offer_mappings[$oid])) {
                    continue; // Offer has no mappings
                }

                $offer_matches = false;

                foreach ($offer_mappings[$oid] as $m) {
                    // Check each dimension — NULL/empty means "ALL" (match any)
                    if (!$this->_csv_matches($m['id_metal_type'], $item_metal)) continue;
                    if (!$this->_csv_matches($m['id_category'], $item_category)) continue;
                    if (!$this->_csv_matches($m['id_product'], $item_product)) continue;
                    if (!$this->_csv_matches($m['id_design'], $item_design)) continue;
                    if (!$this->_csv_matches($m['id_branch'], (string) $branch)) continue;

                    // Age slab check (if mapping has slab restriction)
                    if (!empty($m['id_age_slab'])) {
                        $slab_matched = false;
                        $slab_csv = array_map('intval', explode(',', $m['id_age_slab']));
                        foreach ($slab_csv as $sid) {
                            if (isset($slab_map[$sid])) {
                                $min = (int) $slab_map[$sid]['min_days'];
                                $max = (int) $slab_map[$sid]['max_days'];
                                if ($item_age_days >= $min && ($max == 0 || $item_age_days <= $max)) {
                                    $slab_matched = true;
                                    break;
                                }
                            }
                        }
                        if (!$slab_matched) continue;
                    }

                    // All checks passed — this mapping matches
                    $offer_matches = true;
                    break; // One matching mapping is enough
                }

                if ($offer_matches) {
                    if (!isset($item_offers[$row_idx])) {
                        $item_offers[$row_idx] = [];
                    }
                    $item_offers[$row_idx][] = $oid;
                    $matched_offer_ids[] = $oid;
                }
            }
        }

        if (empty($item_offers)) {
            return $empty_result;
        }

        // ── Step 5: Return only offers that matched at least one item ──
        $matched_offer_ids = array_unique($matched_offer_ids);
        $result_offers = [];
        foreach ($matched_offer_ids as $oid) {
            if (isset($offer_map[$oid])) {
                $result_offers[] = $offer_map[$oid];
            }
        }

        return [
            'offers'      => $result_offers,
            'item_offers' => $item_offers,
        ];
    }

    /**
     * Check if a single value matches a CSV field.
     * NULL/empty CSV = matches everything (means "All").
     *
     * @param  string|null $csv_field   Comma-separated IDs (e.g., '1,3,5') or NULL
     * @param  string      $value       Single value to check
     * @return bool                     TRUE if match
     */
    private function _csv_matches($csv_field, $value)
    {
        // NULL or empty = "All" — matches any value
        if ($csv_field === null || trim($csv_field) === '') {
            return true;
        }

        // Empty item value — cannot match a specific filter
        if ($value === '' || $value === null) {
            return false;
        }

        $ids = array_map('trim', explode(',', $csv_field));
        return in_array((string) $value, $ids, false);
    }
}
