<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Admin_tally_transfer extends CI_Controller
{
    const VIEW_FOLDER = 'tally_transfer/';

    const MODEL = 'tally_transfer_model';

    function __construct()
    {
        parent::__construct();

        ini_set('date.timezone', 'Asia/Calcutta');

        $this->load->model(self::MODEL);
        $this->load->model('admin_settings_model');
        $this->load->model('log_model');

        if (!$this->session->userdata('is_logged')) {
            redirect('admin/login');
        } elseif ($this->session->userdata('access_time_from') != NULL && $this->session->userdata('access_time_from') != "") {
            $now = time();
            $from = $this->session->userdata('access_time_from');
            $to = $this->session->userdata('access_time_to');
            $allowedAccess = ($now > $from && $now < $to) ? TRUE : FALSE;
            if ($allowedAccess == FALSE) {
                $this->session->set_flashdata('login_errMsg', 'Exceeded allowed access time!!');
                redirect('chit_admin/logout');
            }
        }
    }

    function reset_flag($type = "")
    {
        $model = self::MODEL;

        switch ($type) {
            case 'list':
                $data['api_list'] = $this->$model->get_api_list();
                $data['access'] = $this->admin_settings_model->get_access('admin_tally_transfer/reset_flag/list');
                $data['main_content'] = self::VIEW_FOLDER . 'reset_flag';
                $this->load->view('layout/template', $data);
                break;

            case 'preview':
                $api = $this->input->post('api');
                $mode = $this->input->post('mode');

                if ($this->$model->get_api_config($api) === null) {
                    echo json_encode(array('error' => 'Please select a valid API.'));
                    break;
                }

                $status = $this->input->post('status');

                if ($mode == 'billids') {
                    $billIds = $this->input->post('bill_ids');
                    if (trim((string) $billIds) == '') {
                        echo json_encode(array('error' => 'Please enter at least one bill ID / reference number.'));
                        break;
                    }
                    $filters = array('mode' => 'billids', 'bill_ids' => $billIds, 'status' => $status);
                } else {
                    $fromDate = $this->input->post('from_date');
                    $toDate = $this->input->post('to_date');
                    if (trim((string) $fromDate) == '' || trim((string) $toDate) == '') {
                        echo json_encode(array('error' => 'Please select a date range.'));
                        break;
                    }
                    $filters = array(
                        'mode' => 'daterange',
                        'from_date' => $fromDate,
                        'to_date' => $toDate,
                        'id_branch' => $this->input->post('id_branch'),
                        'status' => $status,
                    );
                }

                $result = $this->$model->preview_records($api, $filters);
                echo json_encode($result);
                break;

            case 'reset':
                $api = $this->input->post('api');
                $ids = $this->input->post('ids');
                $filterSummary = $this->input->post('filter_summary');

                $cfg = $this->$model->get_api_config($api);
                if ($cfg === null) {
                    echo json_encode(array('error' => 'Please select a valid API.'));
                    break;
                }

                if (empty($ids) || !is_array($ids)) {
                    echo json_encode(array('error' => 'No records selected to update.'));
                    break;
                }

                $result = $this->$model->reset_flags($api, $ids);

                $log_data = array(
                    'id_log' => $this->session->userdata('id_log'),
                    'event_date' => date('Y-m-d H:i:s'),
                    'module' => 'Tally Transfer Flag Reset',
                    'operation' => $cfg['label'],
                    'record' => implode(',', $result['updated']),
                    'remark' => 'Filter: ' . $filterSummary . ' | Updated: ' . count($result['updated']) . ' | Not found: ' . count($result['not_found']) . (count($result['not_found']) ? ' (' . implode(',', $result['not_found']) . ')' : ''),
                );
                $this->log_model->log_detail('insert', '', $log_data);

                echo json_encode($result);
                break;
        }
    }
}
