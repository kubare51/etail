<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Admin Retail Offer Controller
 *
 * Handles all offer-related operations including:
 * - Offer Master CRUD (list, add, edit, save, delete)
 * - AJAX DataTable listing with server-side processing
 * - Status toggle (active/inactive)
 *
 * @package     RetailERP
 * @subpackage  Controllers
 * @category    Offer Management
 */
class Admin_ret_offer extends CI_Controller {

    /**
     * Reference name for the primary model
     * @var string
     */
    public $model;

    /**
     * Constructor
     *
     * - Validates user session (redirects to login if not authenticated)
     * - Loads required models
     * - Sets model alias for dynamic calls
     */
    public function __construct()
    {
        parent::__construct();

        ini_set('date.timezone', 'Asia/Calcutta');

        // Load models
        $this->load->model('ret_offer_model');
        $this->load->model('admin_settings_model');

        // Session authentication check
        if (!$this->session->userdata('is_logged')) {
            redirect('admin/login');
        }

        // Set model alias for $this->{$this->model}-> calls
        $this->model = 'ret_offer_model';
    }

    // -------------------------------------------------------------------------
    // Offer Master
    // -------------------------------------------------------------------------

    /**
     * Offer Master — unified entry point
     *
     * Dispatches to list / add / edit / save / ajax_list / delete / toggle_status
     * based on the $type parameter.
     *
     * @param  string $type  Action type (list|add|edit|save|ajax_list|delete|toggle_status)
     * @param  string $id    Record ID (used by edit, delete, toggle_status)
     * @return void
     */
    public function offer_master($type = 'list', $id = '')
    {
        switch ($type) {

            // -----------------------------------------------------------------
            // LIST — render the DataTable listing page
            // -----------------------------------------------------------------
            case 'list':
                $data['access'] = $this->admin_settings_model->get_access('admin_ret_offer/offer_master/list');
                $data['main_content'] = 'master/disc_offer/list';
                $this->load->view('layout/template', $data);
                break;

            // -----------------------------------------------------------------
            // ADD — render empty form for new offer
            // -----------------------------------------------------------------
            case 'add':
                $data['access'] = $this->admin_settings_model->get_access('admin_ret_offer/offer_master/list');
                $data['offer']  = $this->get_empty_offer_record();
                $data['main_content'] = 'master/disc_offer/form';
                $this->load->view('layout/template', $data);
                break;

            // -----------------------------------------------------------------
            // EDIT — fetch existing record and render form
            // -----------------------------------------------------------------
            case 'edit':
                $data['access'] = $this->admin_settings_model->get_access('admin_ret_offer/offer_master/list');
                $data['offer']  = $this->{$this->model}->get_offer_master($id);

                if (empty($data['offer'])) {
                    $this->session->set_flashdata('chit_alert', [
                        'class'   => 'danger',
                        'title'   => 'Error',
                        'message' => 'Offer record not found.'
                    ]);
                    redirect('admin_ret_offer/offer_master/list');
                }

                $data['main_content'] = 'master/disc_offer/form';
                $this->load->view('layout/template', $data);
                break;

            // -----------------------------------------------------------------
            // SAVE — collect POST, sanitise, persist via model
            // -----------------------------------------------------------------
            case 'save':
                $this->_save_offer_master();
                break;

            // -----------------------------------------------------------------
            // AJAX LIST — server-side DataTable data
            // -----------------------------------------------------------------
            case 'ajax_list':
                $post_data = $this->input->post();
                $post_data['status_filter'] = $this->input->post('status_filter');

                $result = $this->{$this->model}->get_offer_master_ajax_list($post_data);
                echo json_encode($result);
                break;

            // -----------------------------------------------------------------
            // DELETE — soft-delete an offer
            // -----------------------------------------------------------------
            case 'delete':
                $result = $this->{$this->model}->delete_offer_master($id);
                echo json_encode($result);
                break;

            // -----------------------------------------------------------------
            // TOGGLE STATUS — flip active/inactive
            // -----------------------------------------------------------------
            case 'toggle_status':
                $result = $this->{$this->model}->toggle_offer_status($id);
                echo json_encode($result);
                break;

            // -----------------------------------------------------------------
            // DEFAULT — unknown type, redirect to list
            // -----------------------------------------------------------------
            default:
                redirect('admin_ret_offer/offer_master/list');
                break;
        }
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Process and save offer master form data
     *
     * - Collects all POST fields
     * - Converts dd-mm-yyyy dates to Y-m-d for MySQL
     * - Cleans empty strings to NULL for decimal columns
     * - Delegates to model for insert/update
     *
     * @return void
     */
    private function _save_offer_master()
    {
        $uid = $this->session->userdata('uid');

        // Collect POST data
        $save_data = [
            'offer_name'              => $this->input->post('offer_name'),
            'start_date'              => $this->input->post('start_date'),
            'end_date'                => $this->input->post('end_date'),
            'disc_metal_rate_pct'     => $this->input->post('disc_metal_rate_pct'),
            'disc_metal_rate_flat'    => $this->input->post('disc_metal_rate_flat'),
            'disc_mc_pct'             => $this->input->post('disc_mc_pct'),
            'disc_va_pct'             => $this->input->post('disc_va_pct'),
            'disc_diamond_per_ct'     => $this->input->post('disc_diamond_per_ct'),
            'disc_diamond_flat_pct'   => $this->input->post('disc_diamond_flat_pct'),
            'disc_taxable_value_pct'  => $this->input->post('disc_taxable_value_pct'),
            'status'                  => $this->input->post('status'),
        ];

        // Hidden field — present only on edit
        $id_offer = $this->input->post('id_offer');
        if (!empty($id_offer)) {
            $save_data['id_offer'] = $id_offer;
        }

        // ---- Date conversion: dd-mm-yyyy → Y-m-d ----
        if (!empty($save_data['start_date'])) {
            $save_data['start_date'] = $this->_convert_date_to_mysql($save_data['start_date']);
        }
        if (!empty($save_data['end_date'])) {
            $save_data['end_date'] = $this->_convert_date_to_mysql($save_data['end_date']);
        }

        // ---- Clean empty strings to NULL for decimal/numeric fields ----
        $decimal_fields = [
            'disc_metal_rate_pct',
            'disc_metal_rate_flat',
            'disc_mc_pct',
            'disc_va_pct',
            'disc_diamond_per_ct',
            'disc_diamond_flat_pct',
            'disc_taxable_value_pct',
        ];
        foreach ($decimal_fields as $field) {
            if ($save_data[$field] === '' || $save_data[$field] === null) {
                $save_data[$field] = null;
            }
        }


        // Delegate to model (model handles audit fields + validation)
        $result = $this->{$this->model}->save_offer_master($save_data);

        // Return JSON for AJAX
        echo json_encode($result);
    }

    /**
     * Convert date from dd-mm-yyyy to Y-m-d (MySQL format)
     *
     * If the value is already in Y-m-d or an unrecognised format,
     * it is returned as-is to avoid data corruption.
     *
     * @param  string $date  Date string in dd-mm-yyyy format
     * @return string        Date string in Y-m-d format
     */
    private function _convert_date_to_mysql($date)
    {
        if (empty($date)) {
            return null;
        }

        // dd-mm-yyyy pattern
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
            $parts = explode('-', $date);
            // $parts[0] = dd, $parts[1] = mm, $parts[2] = yyyy
            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }

        // Already Y-m-d or other format — return unchanged
        return $date;
    }

    /**
     * Return an empty offer record array
     *
     * Used when rendering the add form so the view can reference
     * $offer['field_name'] without undefined index errors.
     *
     * @return array  Associative array with all offer fields set to defaults
     */
    private function get_empty_offer_record()
    {
        return [
            'id_offer'              => null,
            'offer_name'            => '',
            'start_date'            => '',
            'end_date'              => '',
            'disc_metal_rate_pct'   => null,
            'disc_metal_rate_flat'  => null,
            'disc_mc_pct'           => null,
            'disc_va_pct'           => null,
            'disc_diamond_per_ct'   => null,
            'disc_diamond_flat_pct' => null,
            'disc_taxable_value_pct' => null,
            'status'                => '1', // Default: active (1 = active)
        ];
    }

    // =========================================================================
    // Offer Mapping
    // =========================================================================

    /**
     * Offer Mapping — unified entry point
     *
     * @param  string $type  Action (list|add|edit|save|ajax_list|delete)
     * @param  string $id    Record ID
     * @return void
     */
    public function offer_mapping($type = 'list', $id = '')
    {
        $this->load->model('ret_catalog_model');
        $this->load->model('employee_model');

        switch ($type) {

            // LIST
            case 'list':
                $data['access'] = $this->admin_settings_model->get_access('admin_ret_offer/offer_mapping/list');
                $data['main_content'] = 'master/disc_offer/mapping_list';
                $this->load->view('layout/template', $data);
                break;

            // ADD
            case 'add':
                $data['access']  = $this->admin_settings_model->get_access('admin_ret_offer/offer_mapping/list');
                $data['mapping'] = $this->_get_empty_mapping_record();
                $data['offers']  = $this->{$this->model}->get_active_offers_dropdown();
                $data['metals']  = $this->ret_catalog_model->getActiveMetals();
                $data['branches'] = $this->employee_model->branchname_list();
                $data['slabs']   = $this->{$this->model}->get_active_slabs();
                $data['main_content'] = 'master/disc_offer/mapping_form';
                $this->load->view('layout/template', $data);
                break;

            // EDIT
            case 'edit':
                $data['access']  = $this->admin_settings_model->get_access('admin_ret_offer/offer_mapping/list');
                $data['mapping'] = $this->{$this->model}->get_mapping($id);

                if (empty($data['mapping'])) {
                    $this->session->set_flashdata('chit_alert', [
                        'class'   => 'danger',
                        'title'   => 'Error',
                        'message' => 'Mapping record not found.'
                    ]);
                    redirect('admin_ret_offer/offer_mapping/list');
                }

                $data['offers']   = $this->{$this->model}->get_active_offers_dropdown($data['mapping']['id_offer']);
                $data['metals']   = $this->ret_catalog_model->getActiveMetals();
                $data['branches'] = $this->employee_model->branchname_list();
                $data['slabs']    = $this->{$this->model}->get_active_slabs();
                $data['main_content'] = 'master/disc_offer/mapping_form';
                $this->load->view('layout/template', $data);
                break;

            // SAVE
            case 'save':
                $save_data = [
                    'id_offer'      => $this->input->post('id_offer'),
                    'id_metal_type' => $this->input->post('id_metal_type'),
                    'id_category'   => $this->input->post('id_category'),
                    'id_product'    => $this->input->post('id_product'),
                    'id_design'     => $this->input->post('id_design'),
                    'id_sub_design' => $this->input->post('id_sub_design'),
                    'id_branch'     => $this->input->post('id_branch'),
                    'id_age_slab'   => $this->input->post('id_age_slab'),
                ];

                $id_mapping = $this->input->post('id_mapping');
                if (!empty($id_mapping)) {
                    $save_data['id_mapping'] = $id_mapping;
                }

                $result = $this->{$this->model}->save_mapping($save_data);
                echo json_encode($result);
                break;

            // AJAX LIST
            case 'ajax_list':
                $mappings = $this->{$this->model}->get_mapping_list();

                // Format for DataTable
                $formatted = [];
                foreach ($mappings as $row) {
                    $age_display = !empty($row['slab_names']) && $row['slab_names'] !== 'All'
                        ? '<span class="label label-info">' . htmlspecialchars($row['slab_names']) . '</span>'
                        : '<span class="label label-default">All Products</span>';

                    $actions = '<div class="btn-group">';
                    $actions .= '<a href="' . base_url('index.php/admin_ret_offer/offer_mapping/edit/' . $row['id_mapping']) . '" class="btn btn-xs btn-primary" title="Edit"><i class="fa fa-pencil"></i></a>';
                    $actions .= ' <button type="button" class="btn btn-xs btn-danger btn-delete-mapping" data-id="' . $row['id_mapping'] . '" title="Delete"><i class="fa fa-trash"></i></button>';
                    $actions .= '</div>';

                    $formatted[] = [
                        'id_mapping'       => $row['id_mapping'],
                        'offer_name'       => htmlspecialchars($row['offer_name']),
                        'metal_names'      => $row['metal_names'],
                        'category_names'   => $row['category_names'],
                        'product_names'    => $row['product_names'],
                        'design_names'     => $row['design_names'],
                        'sub_design_names' => $row['sub_design_names'],
                        'branch_names'     => $row['branch_names'],
                        'age_filter'       => $age_display,
                        'action'           => $actions,
                    ];
                }

                echo json_encode(['data' => $formatted]);
                break;

            // DELETE
            case 'delete':
                $result = $this->{$this->model}->delete_mapping($id);
                echo json_encode($result);
                break;

            default:
                redirect('admin_ret_offer/offer_mapping/list');
                break;
        }
    }

    /**
     * AJAX: Get categories filtered by metal type.
     *
     * @return void  JSON response.
     */
    public function get_categories_by_metal()
    {
        $this->load->model('ret_catalog_model');
        $id_metal = $this->input->post('id_metal');

        $this->db->select('id_ret_category, name');
        $this->db->from('ret_category');
        $this->db->where('status', 1);
        if (!empty($id_metal)) {
            $this->db->where('id_metal', (int) $id_metal);
        }
        $this->db->order_by('name', 'ASC');

        $query = $this->db->get();
        $data = $query->num_rows() > 0 ? $query->result_array() : [];

        echo json_encode(['status' => true, 'data' => $data]);
    }

    /**
     * AJAX: Get products filtered by category IDs (CSV).
     *
     * @return void  JSON response.
     */
    public function get_products_by_category()
    {
        $id_category_csv = $this->input->post('id_category');

        $this->db->select('pro_id, product_name');
        $this->db->from('ret_product_master');
        $this->db->where('product_status', 1);
        if (!empty($id_category_csv)) {
            $cat_ids = array_map('intval', explode(',', $id_category_csv));
            $this->db->where_in('cat_id', $cat_ids);
        }
        $this->db->order_by('product_name', 'ASC');

        $query = $this->db->get();
        $data = $query->num_rows() > 0 ? $query->result_array() : [];

        echo json_encode(['status' => true, 'data' => $data]);
    }

    /**
     * Return an empty mapping record array.
     *
     * @return array
     */
    private function _get_empty_mapping_record()
    {
        return [
            'id_mapping'    => null,
            'id_offer'      => null,
            'id_metal_type' => null,
            'id_category'   => null,
            'id_product'    => null,
            'id_design'     => null,
            'id_sub_design' => null,
            'id_branch'     => null,
            'id_age_slab'   => null,
        ];
    }

    /**
     * AJAX: Save a new age slab (from quick-add modal on mapping form).
     *
     * @return void  JSON response.
     */
    public function save_age_slab()
    {
        $data = [
            'slab_name' => $this->input->post('slab_name'),
            'min_days'  => $this->input->post('min_days'),
            'max_days'  => $this->input->post('max_days'),
        ];

        $result = $this->{$this->model}->save_slab($data);
        echo json_encode($result);
    }

    /**
     * AJAX: Get all active age slabs for dropdown.
     *
     * @return void  JSON response.
     */
    public function get_age_slabs()
    {
        $slabs = $this->{$this->model}->get_active_slabs();
        echo json_encode(['status' => true, 'data' => $slabs]);
    }

    /**
     * AJAX: Get designs filtered by product IDs (CSV).
     *
     * @return void  JSON response.
     */
    public function get_designs_by_product()
    {
        $id_product = $this->input->post('id_product');
        $this->load->model('ret_catalog_model');

        if (empty($id_product)) {
            // No product filter — return all active designs
            $data = $this->ret_catalog_model->get_ActiveDesign();
            echo json_encode(['status' => true, 'data' => $data]);
            return;
        }

        $product_ids = array_map('intval', explode(',', $id_product));

        $this->db->select('DISTINCT(dm.design_no) as design_no, dm.design_name', FALSE);
        $this->db->from('ret_product_mapping pm');
        $this->db->join('ret_design_master dm', 'dm.design_no = pm.id_design', 'inner');
        $this->db->where_in('pm.pro_id', $product_ids);
        $this->db->where('dm.design_status', 1);
        $this->db->order_by('dm.design_name', 'ASC');

        $query = $this->db->get();
        $data = $query->num_rows() > 0 ? $query->result_array() : [];

        echo json_encode(['status' => true, 'data' => $data]);
    }

    /**
     * AJAX: Get sub-designs filtered by design IDs (CSV).
     *
     * @return void  JSON response.
     */
    public function get_sub_designs_by_design()
    {
        $id_design = $this->input->post('id_design');

        if (empty($id_design)) {
            // No design filter — return all active sub-designs
            $this->load->model('ret_catalog_model');
            $data = $this->ret_catalog_model->getActiveSubDesigns();
            echo json_encode(['status' => true, 'data' => $data]);
            return;
        }

        $design_ids = array_map('intval', explode(',', $id_design));

        $this->db->select('DISTINCT(sd.id_sub_design) as id_sub_design, sd.sub_design_name', FALSE);
        $this->db->from('ret_sub_design_mapping sdm');
        $this->db->join('ret_sub_design_master sd', 'sd.id_sub_design = sdm.id_sub_design', 'inner');
        $this->db->where_in('sdm.id_design', $design_ids);
        $this->db->where('sd.status', 1);
        $this->db->order_by('sd.sub_design_name', 'ASC');

        $query = $this->db->get();
        $data = $query->num_rows() > 0 ? $query->result_array() : [];

        echo json_encode(['status' => true, 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    // Estimation Offer Eligibility Check (AJAX)
    // ──────────────────────────────────────────────

    /**
     * AJAX: Check which offers are eligible for estimation items.
     *
     * Called from ret_offer.js when user clicks Save on estimation form.
     * Collects item data (product, metal, category, design, tag_date, values)
     * and checks against active offer mappings.
     *
     * POST params:
     *   - id_branch   (int)    Current branch
     *   - est_date    (string) Estimation date (Y-m-d)
     *   - items       (string) JSON-encoded array of item objects
     *
     * @return void  Outputs JSON: {status, data: {offers[], item_offers{}}}
     */
    public function check_estimation_offers()
    {
        // Load settings model if not loaded
        if (!isset($this->admin_settings_model)) {
            $this->load->model('admin_settings_model');
        }
        $setting = $this->admin_settings_model->get_ret_settings('is_offer_on_estimation');
        $is_enabled = ($setting !== null) ? (int) $setting : 0;

        if ($is_enabled !== 1) {
            echo json_encode([
                'status' => true,
                'data'   => [
                    'is_enabled'  => 0,
                    'offers'      => [],
                    'item_offers' => []
                ],
            ]);
            return;
        }

        $branch   = $this->input->post('id_branch');
        $est_date = $this->input->post('est_date');
        $items_json = $this->input->post('items');

        $items = json_decode($items_json, true);

        if (empty($items) || !is_array($items)) {
            echo json_encode([
                'status' => true,
                'data'   => [
                    'is_enabled'  => 1,
                    'offers'      => [],
                    'item_offers' => []
                ],
            ]);
            return;
        }

        $result = $this->ret_offer_model->get_eligible_offers([
            'id_branch' => $branch,
            'est_date'  => $est_date,
            'items'     => $items,
        ]);

        // Add enabling status flag to output
        $result['is_enabled'] = 1;

        echo json_encode(['status' => true, 'data' => $result]);
    }
}
