<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Opening_master_model extends CI_Model {

    private $table = 'opening_master';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all cash openings (type=1), joined with branch name
     * @param int|null $branch_id Filter by branch
     * @return array
     */
    public function get_cash_openings($branch_id = null)
    {
        $this->db->select('om.id, om.id_branch, om.amount, om.opening_date, om.payment_mode, om.remarks, om.ref_id, b.name as branch_name');
        $this->db->from($this->table . ' om');
        $this->db->join('branch b', 'b.id_branch = om.id_branch', 'left');
        $this->db->where('om.type', 1); // 1 = Cash
        $this->db->where('om.status', 1); // Active only

        if ($branch_id) {
            $this->db->where('om.id_branch', $branch_id);
        }

        $this->db->order_by('om.id', 'ASC');
        $query = $this->db->get();
        return $query->result_array();
    }

    /**
     * Get single opening by ID
     * @param int $id
     * @return array|null
     */
    public function get_opening_by_id($id)
    {
        $this->db->where('id', $id);
        $this->db->where('status', 1);
        $query = $this->db->get($this->table);
        return $query->row_array();
    }

    /**
     * Batch save cash openings — insert new rows, update existing ones
     * @param array $rows Array of row data
     * @param int $user_id Current user ID
     * @return array status + message
     */
    public function batch_save_cash($rows, $user_id)
    {
        $this->db->trans_start();

        foreach ($rows as $row) {
            $data = array(
                'type'         => 1, // Cash
                'id_branch'    => $row['id_branch'],
                'amount'       => (isset($row['amount']) && $row['amount'] != '' ? $row['amount'] : 0),
                'opening_date' => (isset($row['opening_date']) && $row['opening_date'] != '' 
                                    ? date('Y-m-d', strtotime($row['opening_date'])) 
                                    : date('Y-m-d')),
                'payment_mode' => (isset($row['payment_mode']) && $row['payment_mode'] != '' ? $row['payment_mode'] : null),
                'remarks'      => (isset($row['remarks']) && $row['remarks'] != '' ? $row['remarks'] : null),
            );

            if (isset($row['id']) && $row['id'] != '' && $row['id'] > 0) {
                // Update existing
                $data['updated_by'] = $user_id;
                $this->db->where('id', $row['id']);
                $this->db->where('status', 1);
                $this->db->update($this->table, $data);
            } else {
                // Insert new
                $data['ref_id']     = $this->get_next_ref_no(1); // type=1 Cash
                $data['created_by'] = $user_id;
                $this->db->insert($this->table, $data);
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === TRUE) {
            return array('status' => true, 'message' => 'Cash opening saved successfully.');
        } else {
            return array('status' => false, 'message' => 'Failed to save cash opening.');
        }
    }

    /**
     * Soft delete an opening record
     * @param int $id
     * @param int $user_id
     * @return array
     */
    public function soft_delete($id, $user_id)
    {
        $this->db->where('id', $id);
        $this->db->update($this->table, array(
            'status'     => 0,
            'updated_by' => $user_id
        ));

        if ($this->db->affected_rows() > 0) {
            return array('status' => true, 'message' => 'Opening deleted successfully.');
        } else {
            return array('status' => false, 'message' => 'Failed to delete opening.');
        }
    }

    /**
     * Generate next reference number for a given type
     * @param int $type Opening type (1=Cash, 2=Supplier, etc.)
     * @return string
     */
    public function get_next_ref_no($type)
    {
        $prefix_map = array(
            1 => 'CASH',
            2 => 'SUP',
            3 => 'CUST',
            4 => 'KAR',
            5 => 'BANK'
        );

        $prefix = isset($prefix_map[$type]) ? $prefix_map[$type] : 'OP';

        $this->db->select_max('id', 'max_id');
        $this->db->where('type', $type);
        $query = $this->db->get($this->table);
        $result = $query->row_array();

        $next_num = ($result['max_id'] ? $result['max_id'] + 1 : 1);
        return $prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Check if a branch already has a cash opening
     * @param int $branch_id
     * @param int|null $exclude_id Exclude this ID (for edit)
     * @return bool
     */
    public function branch_has_cash_opening($branch_id, $exclude_id = null)
    {
        $this->db->where('type', 1);
        $this->db->where('id_branch', $branch_id);
        $this->db->where('status', 1);

        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }

        return ($this->db->count_all_results($this->table) > 0);
    }

    /**
     * Get branch list for dropdown
     * @return array
     */
    public function get_branches()
    {
        $this->db->select('id_branch, name as branch_name');
        $this->db->where('active', 1);
        $this->db->order_by('name', 'ASC');
        $query = $this->db->get('branch');
        return $query->result_array();
    }

    /**
     * Get single cash opening by ID with branch name for edit form
     * @param int $id
     * @return array|null
     */
    public function get_cash_opening_by_id($id)
    {
        $this->db->select('om.*, b.name as branch_name, date_format(om.opening_date, "%d-%m-%Y") as formatted_date', FALSE);
        $this->db->from($this->table . ' om');
        $this->db->join('branch b', 'b.id_branch = om.id_branch', 'left');
        $this->db->where('om.id', $id);
        $this->db->where('om.type', 1); // Cash only
        $this->db->where('om.status', 1);
        $query = $this->db->get();
        return $query->row_array();
    }

    /**
     * Generate ref no — alias for controller compatibility
     * @param int $type
     * @return string
     */
    public function generate_ref_no($type)
    {
        return $this->get_next_ref_no($type);
    }
}
