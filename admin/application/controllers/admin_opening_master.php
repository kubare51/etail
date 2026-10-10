<?php if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Admin_opening_master extends CI_Controller
{
    const VIEW_FOLDER = 'opening_master/';
    const SETT_MOD = 'admin_settings_model';
    const OP_MODEL = 'opening_master_model';
    const LOG_MODEL = 'log_model';

    public function __construct()
    {
        parent::__construct();
        ini_set('date.timezone', 'Asia/Calcutta');
        if (!$this->session->userdata('is_logged')) {
            redirect('admin/login');
        }
        $this->load->model(self::OP_MODEL);
        $this->load->model(self::SETT_MOD);
        $this->load->model(self::LOG_MODEL);
    }

    /**
     * Cash Opening Balance — standard list/add/edit/save/update/delete/ajax pattern
     * URL: admin_opening_master/cash_opening/{type}/{id}
     */
    public function cash_opening($type = '', $id = '')
    {
        $model = self::OP_MODEL;
        $SETT_MOD = self::SETT_MOD;

        switch ($type) {

            case 'list':

                $data['main_content'] = self::VIEW_FOLDER . 'cash_opening/list';
                $data['branches'] = $this->$model->get_branches();
                $this->load->view('layout/template', $data);

            break;

            case 'add':

                $data['main_content'] = self::VIEW_FOLDER . 'cash_opening/form';
                $data['branches'] = $this->$model->get_branches();
                $this->load->view('layout/template', $data);

            break;

            case 'edit':

                $data['edit_data'] = $this->$model->get_cash_opening_by_id($id);
                if (empty($data['edit_data'])) {
                    $this->session->set_flashdata('chit_alert', array(
                        'message' => 'Record not found',
                        'class'   => 'danger',
                        'title'   => 'Cash Opening'
                    ));
                    redirect('admin_opening_master/cash_opening/list');
                }
                $data['main_content'] = self::VIEW_FOLDER . 'cash_opening/form';
                $data['branches'] = $this->$model->get_branches();
                $this->load->view('layout/template', $data);

            break;

            case 'save':

                $post = $this->input->post('cash_opening');

                if (empty($post)) {
                    echo json_encode(array('status' => false, 'msg' => 'No data received.'));
                    return;
                }

                // Validate
                if (empty($post['id_branch'])) {
                    echo json_encode(array('status' => false, 'msg' => 'Branch is required.'));
                    return;
                }
                if (!isset($post['amount']) || $post['amount'] === '' || floatval($post['amount']) < 0) {
                    echo json_encode(array('status' => false, 'msg' => 'Amount must be a positive value.'));
                    return;
                }
                if (empty($post['opening_date'])) {
                    echo json_encode(array('status' => false, 'msg' => 'Opening Date is required.'));
                    return;
                }

                // Security: sanitize remarks text field
                $this->load->helper('customer_validation');
                $clean_remarks = NULL;
                if (!empty($post['remarks'])) {
                    $remarks_check = sanitize_and_validate_text($this, $post['remarks'], 'Remarks', 500);
                    if (!$remarks_check['valid']) {
                        echo json_encode(array('status' => false, 'msg' => implode(' ', $remarks_check['errors'])));
                        return;
                    }
                    $clean_remarks = $remarks_check['clean'];
                }

                // Check duplicate branch
                if ($this->$model->branch_has_cash_opening((int)$post['id_branch'], 0)) {
                    echo json_encode(array('status' => false, 'msg' => 'This branch already has a cash opening balance.'));
                    return;
                }

                $op_date = date_format(date_create($post['opening_date']), 'Y-m-d');

                $data = array(
                    'type'         => 1, // 1 = Cash
                    'id_branch'    => (int)$post['id_branch'],
                    'opening_date' => $op_date,
                    'amount'       => floatval($post['amount']),
                    'remarks'      => $clean_remarks,
                    'created_by'   => $this->session->userdata('uid'),
                    'created_at'   => date('Y-m-d H:i:s'),
                );

                $ref_no = $this->$model->generate_ref_no(1); // type 1 = Cash
                $data['ref_id'] = $ref_no;

                $this->db->trans_begin();
                $this->db->insert('opening_master', $data);

                if ($this->db->trans_status() === TRUE) {
                    $this->db->trans_commit();

                    $log_data = array(
                        'id_log'     => $this->session->userdata('id_log'),
                        'event_date' => date('Y-m-d H:i:s'),
                        'module'     => 'Cash Opening Balance',
                        'operation'  => 'Save',
                        'record'     => $this->db->insert_id(),
                        'remark'     => 'Cash opening balance saved'
                    );
                    $this->log_model->log_detail('insert', '', $log_data);

                    $return_data = array('status' => true, 'msg' => 'Cash Opening Saved Successfully.');
                } else {
                    $this->db->trans_rollback();
                    $return_data = array('status' => false, 'msg' => 'Unable to save. Please try again.');
                }

                echo json_encode($return_data);

            break;

            case 'update':

                $post = $this->input->post('cash_opening');

                if (empty($post) || empty($id)) {
                    echo json_encode(array('status' => false, 'msg' => 'Invalid request.'));
                    return;
                }

                // Validate
                if (empty($post['id_branch'])) {
                    echo json_encode(array('status' => false, 'msg' => 'Branch is required.'));
                    return;
                }
                if (!isset($post['amount']) || $post['amount'] === '' || floatval($post['amount']) < 0) {
                    echo json_encode(array('status' => false, 'msg' => 'Amount must be a positive value.'));
                    return;
                }
                if (empty($post['opening_date'])) {
                    echo json_encode(array('status' => false, 'msg' => 'Opening Date is required.'));
                    return;
                }

                // Security: sanitize remarks text field
                $this->load->helper('customer_validation');
                $clean_remarks = NULL;
                if (!empty($post['remarks'])) {
                    $remarks_check = sanitize_and_validate_text($this, $post['remarks'], 'Remarks', 500);
                    if (!$remarks_check['valid']) {
                        echo json_encode(array('status' => false, 'msg' => implode(' ', $remarks_check['errors'])));
                        return;
                    }
                    $clean_remarks = $remarks_check['clean'];
                }

                // Check duplicate branch (excluding current record)
                if ($this->$model->branch_has_cash_opening((int)$post['id_branch'], $id)) {
                    echo json_encode(array('status' => false, 'msg' => 'This branch already has a cash opening balance.'));
                    return;
                }

                $op_date = date_format(date_create($post['opening_date']), 'Y-m-d');

                $data = array(
                    'id_branch'    => (int)$post['id_branch'],
                    'opening_date' => $op_date,
                    'amount'       => floatval($post['amount']),
                    'remarks'      => $clean_remarks,
                    'updated_by'   => $this->session->userdata('uid'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                );

                $this->db->trans_begin();
                $this->db->where('id', $id);
                $this->db->update('opening_master', $data);

                if ($this->db->trans_status() === TRUE) {
                    $this->db->trans_commit();

                    $log_data = array(
                        'id_log'     => $this->session->userdata('id_log'),
                        'event_date' => date('Y-m-d H:i:s'),
                        'module'     => 'Cash Opening Balance',
                        'operation'  => 'Edit',
                        'record'     => $id,
                        'remark'     => 'Cash opening balance updated'
                    );
                    $this->log_model->log_detail('insert', '', $log_data);

                    $this->session->set_flashdata('chit_alert', array(
                        'message' => 'Cash Opening Updated Successfully',
                        'class'   => 'success',
                        'title'   => 'Cash Opening'
                    ));
                    $return_data = array('status' => true, 'msg' => 'Cash Opening Updated Successfully.');
                } else {
                    $this->db->trans_rollback();
                    $return_data = array('status' => false, 'msg' => 'Unable to update. Please try again.');
                }

                echo json_encode($return_data);

            break;

            case 'ajax':

                $list = $this->$model->get_cash_openings();

                echo json_encode(array(
                    'status' => true,
                    'list'   => $list
                ));

            break;

            case 'delete':

                if (empty($id)) {
                    echo json_encode(array('status' => false, 'msg' => 'Invalid record.'));
                    return;
                }

                $user_id = $this->session->userdata('uid');
                $result = $this->$model->soft_delete($id, $user_id);

                if ($result['status']) {
                    $log_data = array(
                        'id_log'     => $this->session->userdata('id_log'),
                        'event_date' => date('Y-m-d H:i:s'),
                        'module'     => 'Cash Opening Balance',
                        'operation'  => 'Delete',
                        'record'     => $id,
                        'remark'     => 'Cash opening balance deleted'
                    );
                    $this->log_model->log_detail('insert', '', $log_data);
                }

                echo json_encode(array(
                    'status' => $result['status'],
                    'msg'    => $result['message']
                ));

            break;

        }
    }

    /**
     * Default index redirects to cash_opening list
     */
    public function index()
    {
        redirect('admin_opening_master/cash_opening/list');
    }
}
