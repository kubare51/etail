<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');
class Admin_customer extends CI_Controller
{
	const CUS_MODEL	= "customer_model";
	const ADM_MODEL	= "chitadmin_model";
	const SET_MODEL = 'admin_settings_model';
	const WALL_MODEL = 'wallet_model';
	const LOG_MODEL = "log_model";
	const SMS_MODEL = "admin_usersms_model";
	const MAIL_MODEL = "email_model";
	const CUS_VIEW = "master/customer/";
	const CUS_IMG_PATH = 'assets/img/customer/';
	const DEF_CUS_IMG_PATH = 'assets/img/default.png'; // BUG-L06: removed trailing /
	const DEF_IMG_PATH = 'assets/img/no_image.png/';
	const CUS_IMG = 'customer.jpg';
	const PAN_IMG = 'pan.jpg';
	const RATION_IMG = 'rationcard.jpg';
	const VOTERID_IMG = 'voterid.jpg';
	const KYC_PAN_PATH = 'assets/kyc/pan/';
	const KYC_AADHAR_PATH = 'assets/kyc/aadhar/';
	const KYC_PB_PATH = 'assets/kyc/pb/';
	const KYC_CH_PATH = 'assets/kyc/ch/';
	function __construct()
	{
		parent::__construct();
		ini_set('date.timezone', 'Asia/Calcutta');
		$this->load->model(self::CUS_MODEL);
		$this->load->model(self::ADM_MODEL);
		$this->load->model(self::SET_MODEL);
		$this->load->model(self::WALL_MODEL);
		$this->load->model(self::LOG_MODEL);
		$this->load->model(self::MAIL_MODEL);
		$this->load->model(self::SMS_MODEL);
		$this->load->model("sms_model");
		if (!$this->session->userdata('is_logged')) {
			redirect('admin/login');
		}
		$access = $this->admin_settings_model->get_access('customer');
		if ($access['view'] == 0) {
			redirect('admin/dashboard');
		}
	}
	public function index()
	{
		$this->cus_list();
	}
	public function check_multiple_chits()
	{
		$model_name = self::ADM_MODEL;
		$allowed = $this->$model_name->allow_multiple_chit();
		return $allowed['allow_join_multiple'];
	}
	public function ajax_get_customers()
	{
		$model_name = self::CUS_MODEL;
		$multiple_chit = $this->check_multiple_chits();
		if ($multiple_chit == 1) {
			$cus_data = $this->$model_name->ajax_get_all_customers();
		} else {
			$cus_data = $this->$model_name->ajax_get_unallocated_customers();
		}
		$customers = $cus_data;
		echo json_encode($customers);
		//echo "<pre>" print_r($customers);exit; echo "<pre>";;
	}
	//	public function ajax_get_customer($cus_name,$id)
	public function ajax_get_customer($id)
	{
		$model_name = self::CUS_MODEL;
		$customer = $this->$model_name->get_customer($id);
		$id = $customer['id_customer']; 

		$cus_img =self::CUS_IMG_PATH . $id . "/" . self::CUS_IMG;
		// print_r($cus_img);exit;
		$pan_img = self::CUS_IMG_PATH . $id . "/" . self::PAN_IMG;
		$voter_img = self::CUS_IMG_PATH . $id . "/" . self::VOTERID_IMG;
		$ration_img = self::CUS_IMG_PATH . $id . "/" . self::RATION_IMG;
		if (file_exists($cus_img)) {
			$customer['cus_img']=$cus_img;
		}
		/* if (file_exists($pan_img)) {
			$customer['customer']['pan_path']		= $pan_img;
		}
		if (file_exists($voter_img)) {
			$customer['customer']['voterid_path']		= $voter_img;
		}
		if (file_exists($ration_img)) {
			$customer['customer']['rationcard_path']		= $ration_img;
		} */
		echo json_encode($customer);
	}
	public function cus_list($msg = "")
	{
		$model_name = self::CUS_MODEL;
		$data['message'] = $msg;
		/*$cus_data=$this->$model_name->get_all_customers();
		if($cus_data)
		{
			$data['customers']=$cus_data;
		}*/
		$data['main_content'] = self::CUS_VIEW . 'list';
		$data['entry_date'] = $this->admin_settings_model->settingsDB('get', '', '');
		//	 echo"<pre>";	print_r($data);exit;
		$this->load->view('layout/template', $data);
	}

	public function ajax_check_delete($id = NULL)
	{
		if (!$id) {
			echo json_encode(array('status' => false, 'message' => 'Invalid ID.'));
			exit;
		}
		$model = self::CUS_MODEL;
		$dependencies = $this->$model->check_customer_dependencies($id);

		if (!empty($dependencies)) {
			$msg = "This customer has related records and cannot be deleted.";
			echo json_encode(array('status' => false, 'message' => $msg));
		} else {
			$msg = "<strong>Are you sure! You want to delete this customer?</strong><br/><br/>";
			echo json_encode(array('status' => true, 'message' => $msg));
		}
		exit;
	}
	public function ajax_customers()
	{
		$model_name = self::CUS_MODEL;
		$set_model = self::SET_MODEL;
		$data['access'] = $this->$set_model->get_access('customer');
		$range['from_date']  = $this->input->post('from_date');
		$range['to_date']  = $this->input->post('to_date');
		$range['id_branch']  = $this->input->post('id_branch');
		$range['id_village']  = $this->input->post('id_village');
		$range['mobile'] = $this->input->post('mobile');
		// BUG-M08: Server-side pagination — accept page/per_page, default 50 rows
		$per_page = max(1, (int) $this->input->post('per_page') ?: 50);
		$page     = max(0, (int) $this->input->post('page') ?: 0);
		$offset   = $page * $per_page;
		if ($range['from_date'] != '' && $range['to_date'] != '') {
			$data['customer'] = $this->$model_name->get_customers_by_date($range['from_date'], $range['to_date'], $range['id_branch']);
			$data['total_count'] = count($data['customer']);
		} else {
			$data['customer'] = $this->$model_name->get_all_customers($per_page, $offset, $range['id_branch'], $range['id_village'], $range['mobile']);
			$data['total_count'] = $this->$model_name->get_customers_count($range['id_branch'], $range['id_village'], $range['mobile']);
		}
		$data['page']     = $page;
		$data['per_page'] = $per_page;
		echo json_encode($data);
	}
	/**
	 * Stream customer data as CSV download.
	 * Reads same filters as the list page (date, branch, village, religion, mobile).
	 * Uses chunked queries (5000 rows) to handle 1L+ records without memory issues.
	 */
	public function export_customers()
	{
		$model_name = self::CUS_MODEL;

		// Collect filters from POST
		$filters = array(
			'from_date'   => $this->input->post('from_date'),
			'to_date'     => $this->input->post('to_date'),
			'id_branch'   => $this->input->post('id_branch'),
			'id_village'  => $this->input->post('id_village'),
			'id_religion' => $this->input->post('id_religion'),
			'mobile'      => $this->input->post('mobile'),
			'date_type'   => $this->input->post('date_type'),
		);

		// CSV headers — trigger browser file download
		$filename = 'Customer_Export_' . date('Y-m-d_His') . '.csv';
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		header('Pragma: no-cache');

		$output = fopen('php://output', 'w');

		// Write BOM for Excel UTF-8 compatibility
		fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

		// Header row
		fputcsv($output, array(
			'ID', 'Name', 'Mobile', 'Branch',
			'Active Accounts', 'Closed Accounts', 'Inactive Accounts',
			'Status', 'Date Added', 'Custom Entry Date',
			'Gender', 'Address', 'City', 'State', 'Country', 'Pincode',
			'Area', 'DOB', 'Age', 'DOW',
			'Email', 'PAN', 'Aadhaar', 'Religion',
			'Allocated Employee', 'Allocated Agent',
			'Nominee Name', 'Nominee Mobile', 'Nominee Relationship',
			'Created Through'
		));

		// Stream data in chunks of 5000
		$chunk_size = 5000;
		$offset     = 0;

		while (true) {
			$rows = $this->$model_name->get_customers_for_export($filters, $chunk_size, $offset);
			if (empty($rows)) {
				break;
			}

			foreach ($rows as $row) {
				// Map added_by codes to readable labels — same mapping used in the DataTable JS
				$added_by_label = '-';
				switch ($row['added_by']) {
					case 1: $added_by_label = 'Web'; break;
					case 2: $added_by_label = 'Android'; break;
					case 3: $added_by_label = 'iOS'; break;
					case 4: $added_by_label = 'Manual'; break;
					case 5: $added_by_label = 'Employee'; break;
					case 6: $added_by_label = 'Import'; break;
					default: $added_by_label = '-'; break;
				}

				fputcsv($output, array(
					$row['id_customer'],
					$row['name'],
					$row['mobile'],
					$row['branch'],
					$row['active_accounts'],
					$row['closed_accounts'],
					$row['inactive_accounts'],
					$row['cus_status'],
					$row['date_add'],
					$row['custom_entry_date'],
					$row['gender'],
					$row['address'],
					$row['city'],
					$row['state'],
					$row['country'],
					$row['pincode'],
					$row['village_name'],
					$row['date_of_birth'],
					$row['age'],
					$row['date_of_wed'],
					$row['email'],
					$row['pan'],
					$row['aadharid'],
					$row['religion'],
					$row['employee_allocated'],
					$row['agent_name'],
					$row['nominee_name'],
					$row['nominee_mobile'],
					$row['nominee_relationship'],
					$added_by_label,
				));
			}

			// Flush output buffer to browser — keeps download streaming
			if (ob_get_level()) {
				ob_flush();
			}
			flush();
			$offset += $chunk_size;
		}

		fclose($output);
		exit;
	}
	function birthday($birthday)
	{
		$age = date_create($birthday)->diff(date_create('today'))->y;
		return $age;
	}
	//Open form
	public function cus_form($type = "", $id = "")
	{
		$model = self::CUS_MODEL;

		$profile_id = $this->session->userdata('profile');
		$show_customer_passwd = 0;
		if ($profile_id) {
			$profile_data = $this->db->select('show_customer_passwd')->where('id_profile', $profile_id)->get('profile')->row_array();
			if (isset($profile_data['show_customer_passwd'])) {
				$show_customer_passwd = $profile_data['show_customer_passwd'];
			}
		}
		$data['show_customer_passwd'] = $show_customer_passwd;

		switch ($type) {
			case 'Add':
				/*-- Coded by ARVK --*/
				$set_model = self::SET_MODEL;
				$limit = $this->$set_model->limitDB('get', '1');
				$count = $this->$model->customer_count();
				$data['financial_year'] = $this->$model->GetFinancialYear();
				// BUG-M09: Pre-load countries server-side to eliminate country AJAX call
				$data['countries'] = $this->$set_model->get_country();
				if ($limit['limit_cust'] == 1) {
					if ($count < $limit['cust_max_count']) {
						$data['customer'] = $this->$model->empty_record();
						$data['customer']['cus_img_path']	= self::CUS_IMG_PATH . "../default.png";
						$data['customer']['pan_path']		= self::CUS_IMG_PATH . "../no_image.png";
						$data['customer']['voterid_path']	= self::CUS_IMG_PATH . "../no_image.png";
						$data['customer']['rationcard_path'] = self::CUS_IMG_PATH . "../no_image.png";
						$data['customer']['cus_img']       = NULL;
						$data['customer']['age']     		= NULL;
						// BUG-M09: Pre-load states server-side to eliminate state AJAX call
						$data['states'] = json_decode($this->$set_model->get_state(!empty($data['customer']['id_country']) ? $data['customer']['id_country'] : 101), true);
						$data['main_content'] = self::CUS_VIEW . "form";
						$this->load->view('layout/template', $data);
					} else {
						$this->session->set_flashdata('chit_alert', array('message' => 'Customer creation limit exceeded, Kindly contact Super Admin...', 'class' => 'danger', 'title' => 'Customer creation'));
						redirect('customer');
					}
				} else {
					$data['customer'] = $this->$model->empty_record();
					$data['customer']['cus_img_path']	= self::CUS_IMG_PATH . "../default.png";
					$data['customer']['pan_path']		= self::CUS_IMG_PATH . "../no_image.png";
					$data['customer']['voterid_path']	= self::CUS_IMG_PATH . "../no_image.png";
					$data['customer']['rationcard_path'] = self::CUS_IMG_PATH . "../no_image.png";
					$data['customer']['cus_img']       = NULL;
					$data['customer']['age']     		= NULL;
					// BUG-M09: Pre-load states server-side to eliminate state AJAX call
					$data['states'] = json_decode($this->$set_model->get_state(!empty($data['customer']['id_country']) ? $data['customer']['id_country'] : 101), true);
					$data['main_content'] = self::CUS_VIEW . "form";
					$this->load->view('layout/template', $data);
				}
				/*--/ Coded by ARVK --*/
				break;
			case 'Edit':
			$cus = $this->$model->get_cust($id);
			$decrypted_passwd = NULL;
			if (isset($cus['passwd']) && $cus['passwd'] != '') {
				$decrypted_passwd = base64_decode($cus['passwd']);
			}
			$data['financial_year'] = $this->$model->GetFinancialYear();
			// BUG-M09: Pre-load countries server-side (same as Add case)
			$set_model_e = self::SET_MODEL;
			$data['countries'] = $this->$set_model_e->get_country();
				$age = $this->birthday($cus['date_of_birth']);
				$data['customer'] = array(
					'id_customer'		=> (isset($cus['id_customer']) ? $cus['id_customer'] : NULL),
					'firstname'			=> (isset($cus['firstname']) ? $cus['firstname'] : NULL),
					'lastname' 			=> (isset($cus['lastname']) ? $cus['lastname'] : NULL),
					'id_branch'	    	=> (isset($cus['id_branch']) ? $cus['id_branch'] : NULL),
					'religion'		    => (isset($cus['religion']) ? $cus['religion'] : NULL),
					'post_office'		=> (isset($cus['post_office']) ? $cus['post_office'] : NULL),
					'taluk'		=> (isset($cus['taluk']) ? $cus['taluk'] : NULL),
					'id_village'		=> (isset($cus['id_village']) ? $cus['id_village'] : NULL),
					'date_of_birth'		=> (isset($cus['date_of_birth']) && $cus['date_of_birth'] != '' ? date('d/m/Y', strtotime(str_replace("/", "-", $cus['date_of_birth']))) : NULL),
					'date_of_wed'		=> (isset($cus['date_of_wed']) && $cus['date_of_wed'] != '' ? date('d/m/Y', strtotime(str_replace("/", "-", $cus['date_of_wed']))) : NULL),
					'gst_number'				=> (isset($cus['gst_number']) ? $cus['gst_number'] : NULL),
					'email'				=> (isset($cus['email']) ? $cus['email'] : NULL),
					'gender'			=> (isset($cus['gender']) ? $cus['gender'] : NULL),
					'cus_type'			=>	$cus['cus_type'],
					'mobile'			=> (isset($cus['mobile']) ? $cus['mobile'] : NULL),
					'phone'				=> (isset($cus['phone']) ? $cus['phone'] : NULL),
					'title'				=> (isset($cus['title']) ? $cus['title'] : NULL),
					'age'				=> (isset($age) ? $age : NULL),
					'nominee_name'			=> (isset($cus['nominee_name']) ? $cus['nominee_name'] : NULL),
					'nominee_relationship'		=> (isset($cus['nominee_relationship']) ? $cus['nominee_relationship'] : NULL),
					'nominee_mobile'	=> (isset($cus['nominee_mobile']) ? $cus['nominee_mobile'] : NULL),
					'pan'				=> (isset($cus['pan']) ? $cus['pan'] : NULL),
					'pan_proof'			=> (isset($cus['pan_proof']) && $cus['pan_proof'] != NULL ? $cus['pan_proof'] : self::DEF_IMG_PATH),
					'voterid'			=> (isset($cus['voterid']) ? $cus['voterid'] : NULL),
					'voterid_proof'		=> (isset($cus['voterid_proof']) && $cus['voterid_proof'] != NULL ? $cus['voterid_proof'] : self::DEF_IMG_PATH),
					'rationcard'		=> (isset($cus['rationcard']) ? $cus['rationcard'] : NULL),
					'rationcard_proof'	=> (isset($cus['rationcard_proof']) && $cus['rationcard_proof'] != NULL ? $cus['rationcard_proof'] : self::DEF_IMG_PATH),
					'comments'			=> (isset($cus['comments']) ? $cus['comments'] : NULL),
					'username'			=> (isset($cus['username']) ? $cus['username'] : NULL),
					'passwd'			=> $decrypted_passwd,
					'active'			=> (isset($cus['active']) ? $cus['active'] : 0),
					'is_cus_synced'		=> (isset($cus['is_cus_synced']) ? $cus['is_cus_synced'] : 0),
					'id_country'		=> (isset($cus['id_country']) ? $cus['id_country'] : 0),
					'id_state' 			=> (isset($cus['id_state']) ? $cus['id_state'] : 0),
					'id_city'			=> (isset($cus['id_city']) ? $cus['id_city'] : 0),
					'company_name'			=> (isset($cus['company_name']) ? $cus['company_name'] : NULL),
					'address1'			=> (isset($cus['address1']) ? $cus['address1'] : NULL),
					'address2'			=> (isset($cus['address2']) ? $cus['address2'] : NULL),
					'address3'			=> (isset($cus['address3']) ? $cus['address3'] : NULL),
					'pincode'			=> (isset($cus['pincode']) ? $cus['pincode'] : NULL),
					'cus_img'           => (isset($cus['cus_img']) && $cus['cus_img'] != NULL ? $cus['cus_img'] : self::DEF_CUS_IMG_PATH),
					'id_profession'	    	=> (isset($cus['id_profession']) ? $cus['id_profession'] : NULL),
					'aadharid'	    	=> (isset($cus['aadharid']) ? $cus['aadharid'] : NULL),
					'driving_license_no'			=> (isset($cus['driving_license_no']) ? $cus['driving_license_no'] : NULL),
					'passport_no'			        => (isset($cus['passport_no']) ? $cus['passport_no'] : NULL),
					'fin_year_code' 	     => (isset($cus['fin_year_code']) ? $cus['fin_year_code'] : NULL),
					'opening_balance_amount' => (isset($cus['opening_balance_amount']) ? $cus['opening_balance_amount'] : 0.00),
					'nominee_pan'			=> (isset($cus['nominee_pan']) ? $cus['nominee_pan'] : NULL),
					'nominee_address1'			=> (isset($cus['nominee_address1']) ? $cus['nominee_address1'] : NULL),
					'nominee_address2'			=> (isset($cus['nominee_address2']) ? $cus['nominee_address2'] : NULL),
					'marital_status'			=> (!empty($cus['marital_status']) ? $cus['marital_status'] : 0),
					'languages_known'			=> (!empty($cus['languages_known']) ? $cus['languages_known'] : NULL),
					'is_vip' =>(!empty($cus['is_vip']) ? $cus['is_vip'] : 0)
				);
				// if(is_dir(self::CUS_IMG_PATH.$id))
				// {
				$cus_img = self::CUS_IMG_PATH . $id . "/" . self::CUS_IMG;
				if (file_exists($cus_img)) {
					$data['customer']['cus_img_path']		= $cus_img;
				}

				// Bind proof image paths for preview
				if (!empty($cus['pan_proof']) && file_exists(self::CUS_IMG_PATH . $id . "/" . $cus['pan_proof'])) {
					$data['customer']['pan_path'] = self::CUS_IMG_PATH . $id . "/" . $cus['pan_proof'];
					$data['customer']['pan_img_path'] = $data['customer']['pan_path'];
				} else {
					$glob_pan = glob(self::CUS_IMG_PATH . $id . "/pan_*.*");
					if (!empty($glob_pan)) {
						$data['customer']['pan_path'] = $glob_pan[0];
						$data['customer']['pan_img_path'] = $glob_pan[0];
					}
				}

				if (!empty($cus['voterid_proof']) && file_exists(self::CUS_IMG_PATH . $id . "/" . $cus['voterid_proof'])) {
					$data['customer']['voterid_path'] = self::CUS_IMG_PATH . $id . "/" . $cus['voterid_proof'];
				} else {
					$glob_voter = glob(self::CUS_IMG_PATH . $id . "/voterid_*.*");
					if (!empty($glob_voter)) {
						$data['customer']['voterid_path'] = $glob_voter[0];
					}
				}

				if (!empty($cus['rationcard_proof']) && file_exists(self::CUS_IMG_PATH . $id . "/" . $cus['rationcard_proof'])) {
					$data['customer']['rationcard_path'] = self::CUS_IMG_PATH . $id . "/" . $cus['rationcard_proof'];
				} else {
					$glob_ration = glob(self::CUS_IMG_PATH . $id . "/rationcard_*.*");
					if (!empty($glob_ration)) {
						$data['customer']['rationcard_path'] = $glob_ration[0];
					}
				}

				$dl_img = self::CUS_IMG_PATH . $cus['id_customer'] . '/' . (isset($cus['dl_ImgName']) ? $cus['dl_ImgName'] : '');
				$pp_ImgName = self::CUS_IMG_PATH . $cus['id_customer'] . '/' . (isset($cus['pp_ImgName']) ? $cus['pp_ImgName'] : '');
				if (file_exists($dl_img)) {
					$data['customer']['dl_img_path']		= $dl_img;
				}
				if (file_exists($pp_ImgName)) {
					$data['customer']['pp_img_path']		= $pp_ImgName;
				}
				// }
				// BUG-M09: Pre-load states server-side (same as Add case)
				$data['states'] = json_decode($this->$set_model_e->get_state(!empty($data['customer']['id_country']) ? $data['customer']['id_country'] : 101), true);
				$data['main_content'] = self::CUS_VIEW . "form";
				$this->load->view('layout/template', $data);
				break;
		}
	}
	function sync_existing_data($mobile, $id_customer, $id_branch)
	{
		$data['id_customer'] = $id_customer;
		$data['id_branch'] = $id_branch;
		$data['branch_code'] = ($id_branch > 0 && $this->config->item("integrationType") == 4 ? $this->customer_model->getBranchCode() : NULL);
		$data['branchWise'] = 0;
		$data['mobile'] = $mobile;
		$res = $this->customer_model->insExisAcByMobile($data);
		//echo $this->db->last_query();exit;
		if (sizeof($res) > 0) {
			$payData = $this->customer_model->syncPayData($res);
			if (sizeof($payData['succeedIds']) > 0 || $payData['no_records'] > 0) {
				$status = $this->customer_model->updateInterTableStatus($res, $payData['succeedIds']);
				if ($status === TRUE) {
					return array("status" => TRUE, "msg" => "Purchase Plan registered successfully");
				} else {
					return array("status" => FALSE, "msg" => "Error in updating intermediate tables");
				}
			} else {
				return array("status" => FALSE, "msg" => "Error in updating payment tables");
			}
		} else {
			return array("status" => FALSE, "msg" => "No records to update in scheme account tables");
		}
	}
	//db transactions
	public function cus_post($type = "", $id = "")
	{
		$model = self::CUS_MODEL;
		$setmodel = self::SET_MODEL;
		switch ($type) {
			case 'Add':
				$cus = $this->input->post('customer');
				// --- SERVER-SIDE VALIDATION (fixes BUG-C02, C05, C06, H03-H06, M01, M03, M06, M07) ---
				$this->load->helper('customer_validation');
				$validation = validate_customer_data($this, 'add');
				if (!$validation['valid']) {
					$this->session->set_flashdata('chit_alert', array(
						'message' => implode('<br>', $validation['errors']),
						'class' => 'danger',
						'title' => 'Validation Error'
					));
					redirect('customer/add');
					return;
				}
				// --- END VALIDATION ---
				$id_branch = !empty($cus['id_branch']) ? $cus['id_branch'] : '';
				$sessionBranch = $this->session->userdata('id_branch');
				$entry_date = NULL;

				if ($id_branch != '') {
					$branch_date = $this->customer_model->get_entrydate($id_branch);
					if (isset($branch_date['edit_custom_entry_date']) && $branch_date['edit_custom_entry_date'] == 1) {
						$entry_date = isset($branch_date['custom_entry_date']) ? $branch_date['custom_entry_date'] : NULL;
					}
				}
				$cus_data = array(
					'info' => array(
						'firstname'			=> (isset($cus['firstname']) ? sanitize_customer_field(ucfirst($cus['firstname'])) : NULL),
						'lastname' 			=> (isset($cus['lastname']) ? sanitize_customer_field(ucfirst($cus['lastname'])) : NULL),
						'title' 			=> (isset($cus['title']) ? $cus['title'] : NULL),
						'id_branch' 		=> ($sessionBranch != '' ? $sessionBranch : $id_branch),
						'id_village'		=> ($cus['id_village'] != '' ? $cus['id_village'] : NULL),
						'id_employee' 	    =>  $this->session->userdata('uid'),
						'date_of_birth'		=> (isset($cus['date_of_birth']) && $cus['date_of_birth'] != '' ? date('Y-m-d', strtotime(str_replace("/", "-", $cus['date_of_birth']))) : NULL),
						'date_of_wed'		=> (isset($cus['date_of_wed']) && $cus['date_of_wed'] != '' ? date('Y-m-d', strtotime(str_replace("/", "-", $cus['date_of_wed']))) : NULL),
						'gst_number'		=> (isset($cus['gst_number']) ? sanitize_customer_field($cus['gst_number']) : NULL),
						'email'				=> (isset($cus['email']) ? trim($cus['email']) : NULL),
						'mobile'			=> (isset($cus['mobile']) ? $cus['mobile'] : NULL),
						'gender'			=> (isset($cus['gender']) ? $cus['gender'] : '-1'),
						'cus_type'			=> (isset($cus['cus_type']) ? $cus['cus_type'] : '1'),
						'phone'				=> (isset($cus['phone']) ? $cus['phone'] : NULL),
						'nominee_name'		=> (isset($cus['nominee_name']) ? sanitize_customer_field($cus['nominee_name']) : NULL),
						'nominee_relationship'		=> (isset($cus['nominee_relationship']) ? sanitize_customer_field($cus['nominee_relationship']) : NULL),
						'nominee_mobile'	=> (isset($cus['nominee_mobile']) ? $cus['nominee_mobile'] : NULL),
						'cus_img'			=>	 NULL,
						'nominee_pan'       => (isset($cus['nominee_pan']) ? $cus['nominee_pan'] : NULL),
						'pan'				=> (isset($cus['pan']) ? $cus['pan'] : NULL),
						/* 'pan_proof'			=>	NULL,  */
						'voterid'			=> (isset($cus['voterid']) ? $cus['voterid'] : NULL),
						/* 'voterid_proof'		=>	 NULL,  */
						'rationcard'		=> (isset($cus['rationcard']) ? $cus['rationcard'] : NULL),
						/* 'rationcard_proof'	=>	NULL, 	 */
						'comments'			=> (isset($cus['comments']) ? sanitize_customer_field($cus['comments']) : NULL),
						'username'			=> (isset($cus['username']) ? sanitize_customer_field($cus['username']) : NULL),
						'passwd'			=> (isset($cus['passwd']) ? $this->$model->__encrypt($cus['passwd']) : NULL),
						'active'			=> (isset($cus['active']) ? $cus['active'] : 1),
						'date_add'			=>   date("Y-m-d H:i:s"),
						//	'custom_entry_date'	=>	(isset($cus['custom_entry_date'])? date('Y-m-d',strtotime(str_replace("/","-",$cus['custom_entry_date']))): NULL), 
						//		'custom_entry_date'   =>($entry_date[0]['edit_custom_entry_date']==1 ? $entry_date[0]['custom_entry_date']:NULL),
						'custom_entry_date'   => $entry_date,
						'added_by'			=> $this->session->userdata('uid'), // BUG-M13: was hardcoded to 1
						'id_profession'	    	=> (isset($cus['id_profession']) ? $cus['id_profession'] : NULL),
						'aadharid'	    	=> (isset($cus['aadharid']) ? $cus['aadharid'] : NULL),
						'religion'	    	=> (isset($cus['religion']) && $cus['religion'] != '' ? $cus['religion'] : NULL),
						//store kyc details from kyc tab (pan & aadhar available in personal tab)....AddedOn:21/06/2023 By:Abi
						'driving_license_no'			=> (isset($cus['driving_license_no']) ? $cus['driving_license_no'] : NULL),
						'passport_no'			        => (isset($cus['passport_no']) ? $cus['passport_no'] : NULL),
						'fin_year_code'			        => (isset($cus['fin_year_code']) ? $cus['fin_year_code'] : NULL),
						'opening_balance_amount'	    => (isset($cus['opening_balance_amount']) ? $cus['opening_balance_amount'] : 0.00),
						'nominee_address1' =>(isset($cus['nominee_address1'])? $cus['nominee_address1']: ""),
						'nominee_address2' =>(isset($cus['nominee_address2'])? $cus['nominee_address2'] : ""),
						'marital_status'			=> (!empty($cus['marital_status']) ? $cus['marital_status'] : 0),
						'languages_known'			=> (!empty($cus['languages_known']) ? $cus['languages_known'] : NULL),
						'is_vip' => (isset($cus['is_vip']) ? $cus['is_vip'] : 0)
					),
					'address'				=> array(
						'id_country'		=> (isset($cus['country']) ? $cus['country'] : NULL),
						'id_state' 			=> (isset($cus['state']) ? $cus['state'] : NULL),
						'id_city'			=> (isset($cus['city']) ? $cus['city'] : NULL),
						'company_name'		=> ($cus['cus_type'] == 2 ? $cus['firstname'] : NULL),
						'address1'			=> (isset($cus['address1']) ? $cus['address1'] : NULL),
						'address2'			=> (isset($cus['address2']) ? $cus['address2'] : NULL),
						'address3'			=> (isset($cus['address3']) ? $cus['address3'] : NULL),
						'pincode'			=> (isset($cus['pincode']) ? $cus['pincode'] : NULL),
						'active'			=> (isset($cus['active']) ? $cus['active'] : 0),
						'date_add'			=>	date("Y-m-d H:i:s")
					)
				);
				// echo "<pre>";print_r($cus_data);echo "</pre>";exit;  
				$this->db->trans_begin();
				$cus_id =  $this->$model->insert_customer($cus_data);
				// echo "<pre>";print_r($cus_data);exit;
				if ($cus_id > 0) {
					// Sync Existing Data					
					if ($this->config->item("integrationType") == 3 || $this->config->item("autoSyncExisting") == 1) {
						$syncData = $this->sync_existing_data($cus_data['info']['mobile'], $cus_id, $cus_data['info']['id_branch']);
					}
					// Create wallet account
					$wallet_acc =  $this->$setmodel->settingsDB('get', '', '');
					if ($wallet_acc[0]['wallet_account_type'] == 1) {
						$this->wallet_account_create($cus_id, $cus_data['info']['mobile']);
					}
					//webcam upload, #AD On:20-12-2022,Cd:CLin,Up:AB --->starts
					if (!empty($cus['cus_img'])) {
						$p_ImgData = json_decode(rawurldecode($cus['cus_img']));
						if (is_array($p_ImgData) && count($p_ImgData) > 0 && isset($p_ImgData[0]->src)) {
							$precious = $p_ImgData[0];
							$_FILES['cus_img'] = array();
							$imgFile = $this->base64ToFile($precious->src);
							$_FILES['cus_img'] = $imgFile;
						}
					}
					//webcam upload ends...
					$this->save_dynamic_kyc($cus_id);

					// Upload image
					if (isset($_FILES['cus_img']['name']) && !empty($_FILES['cus_img']['name'])) {
						$this->set_image($cus_id);
					}
					// Upload proof images (PAN, Voter ID, Ration Card)
					$this->upload_customer_proofs($cus_id);
				}
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Customer record added successfully', 'class' => 'success', 'title' => 'Create Customer'));
					$this->session->set_userdata(array('cus_id'=>$cus_id,'cus_name'=>$cus['firstname'],'cus_mobile'=>$cus['mobile']));  // esakki 11-11
					redirect('account/add');
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Create Customer'));
					redirect('customer');
				}
				break;
			case 'Edit':
				$cus = $this->input->post('customer');
				// --- SERVER-SIDE VALIDATION (fixes BUG-C02 for Edit flow) ---
				$this->load->helper('customer_validation');
				$validation = validate_customer_data($this, 'edit', $id);
				if (!$validation['valid']) {
					$this->session->set_flashdata('chit_alert', array(
						'message' => implode('<br>', $validation['errors']),
						'class' => 'danger',
						'title' => 'Validation Error'
					));
					redirect('customer/edit/' . $id);
					return;
				}
				// --- END VALIDATION ---
				// BUG-C03/H11: Don't pre-check password — blank=keep existing, filled=re-hash with bcrypt
				//   echo "<pre>";print_r($cus);exit;
				// Fetch existing password so blank field preserves DB value (CI3 update sets NULL columns to NULL)
				$pwd_query = $this->db->select('passwd')->where('id_customer', $id)->get('customer');
				$existing_passwd = ($pwd_query->num_rows() > 0 ? $pwd_query->row()->passwd : NULL);
				$cus_data = array(
					'info' => array(
						'firstname'			=> (isset($cus['firstname']) ? $cus['firstname'] : NULL),
						'lastname' 			=> (isset($cus['lastname']) ? $cus['lastname'] : NULL),
						'title' 			=> (isset($cus['title']) ? $cus['title'] : NULL),
						'id_branch'		=> (isset($cus['id_branch']) ? $cus['id_branch'] : NULL),
						'religion'		=> (!empty($cus['religion']) ? $cus['religion'] : NULL),
						'id_village'		=> ($cus['id_village'] != '' ? $cus['id_village'] : NULL),
						'date_of_birth'		=> (isset($cus['date_of_birth']) && $cus['date_of_birth'] != '' ? date('Y-m-d', strtotime(str_replace("/", "-", $cus['date_of_birth']))) : NULL),
						'date_of_wed'		=> (isset($cus['date_of_wed']) && $cus['date_of_wed'] != '' ? date('Y-m-d', strtotime(str_replace("/", "-", $cus['date_of_wed']))) : NULL),
						'gst_number'				=> (isset($cus['gst_number']) ? $cus['gst_number'] : NULL),
						'email'				=> (isset($cus['email']) ? $cus['email'] : NULL),
						'mobile'			=> (isset($cus['mobile']) ? $cus['mobile'] : NULL),
						'gender'			=> (isset($cus['gender']) ? $cus['gender'] : '-1'),
						'cus_type'			=>	$cus['cus_type'],
						'nominee_pan'       => (isset($cus['nominee_pan']) ? $cus['nominee_pan'] : NULL),
						'phone'				=> (isset($cus['phone']) ? $cus['phone'] : NULL),
						'nominee_name'			=> (isset($cus['nominee_name']) ? sanitize_customer_field($cus['nominee_name']) : NULL),
						'nominee_relationship'		=> (isset($cus['nominee_relationship']) ? sanitize_customer_field($cus['nominee_relationship']) : NULL),
						'nominee_mobile'	=> (isset($cus['nominee_mobile']) ? $cus['nominee_mobile'] : NULL),
						/* 	'cus_img'			=> (isset($cus['cus_img'])? $cus['cus_img']: (isset($cus['customer_img']) && $cus['customer_img'] != NULL ? $cus['customer_img'] : NULL )),  */
						'pan'				=> (isset($cus['pan']) ? $cus['pan'] : NULL),
						/* 'pan_proof'			=>	(isset($cus['pan_proof'])?$cus['pan_proof']: (isset($cus['pan_img']) && $cus['pan_img'] != NULL ? $cus['pan_img'] : NULL)),  */
						'voterid'			=> (isset($cus['voterid']) ? $cus['voterid'] : NULL),
						/* 'voterid_proof'		=>	(isset($cus['voterid_proof'])?$cus['voterid_proof']: (isset($cus['voter_img']) && $cus['voter_img'] != NULL ? $cus['voter_img'] : NULL)),  */
						'rationcard'		=> (isset($cus['rationcard']) ? $cus['rationcard'] : NULL),
						/* 	'rationcard_proof'	=>	(isset($cus['rationcard_proof'])?$cus['rationcard_proof']: (isset($cus['ration_img']) && $cus['ration_img'] != NULL ? $cus['ration_img'] : NULL)), 	 */
						'comments'			=> (isset($cus['comments']) ? $cus['comments'] : NULL),
						'username'			=> (isset($cus['username']) ? $cus['username'] : NULL),
						'passwd'			=> (!empty($cus['passwd']) ? $this->$model->__encrypt($cus['passwd']) : $existing_passwd),
						'active'			=> (isset($cus['active']) ? $cus['active'] : 0),
						'date_upd'			=>   date("Y-m-d H:i:s"),
						'id_profession'	    	=> (isset($cus['id_profession']) ? $cus['id_profession'] : NULL),
						'aadharid'	    	=> (isset($cus['aadharid']) ? $cus['aadharid'] : NULL),
						'religion'	    	=> (isset($cus['religion']) && $cus['religion'] != '' ? $cus['religion'] : NULL),
						//store kyc details from kyc tab (pan & aadhar available in personal tab)....AddedOn:21/06/2023 By:Abi
						'driving_license_no'			=> (isset($cus['driving_license_no']) ? $cus['driving_license_no'] : NULL),
						'passport_no'			        => (isset($cus['passport_no']) ? $cus['passport_no'] : NULL),
						'fin_year_code'			        => (isset($cus['fin_year_code']) ? $cus['fin_year_code'] : NULL),
						'opening_balance_amount'	    => (isset($cus['opening_balance_amount']) ? $cus['opening_balance_amount'] : 0.00),
						'nominee_address1' =>(isset($cus['nominee_address1'])? trim($cus['nominee_address1']): ""),
						'nominee_address2' =>(isset($cus['nominee_address2'])? trim($cus['nominee_address2']) : ""),
						'marital_status'			=> (!empty($cus['marital_status']) ? $cus['marital_status'] : 0),
						'languages_known'			=> (!empty($cus['languages_known']) ? $cus['languages_known'] : NULL),
						'is_vip' => (isset($cus['is_vip']) ? $cus['is_vip'] : 0)
					),
					'address'				=> array(
						'id_country'		=> (isset($cus['country']) ? $cus['country'] : NULL),
						'id_state' 			=> (isset($cus['state']) ? $cus['state'] : NULL),
						'id_city'			=> (isset($cus['city']) ? $cus['city'] : NULL),
						'company_name'		=> ($cus['cus_type'] == 2 ? $cus['firstname'] : NULL),
						'address1'			=> (isset($cus['address1']) ? $cus['address1'] : NULL),
						'address2'			=> (isset($cus['address2']) ? $cus['address2'] : NULL),
						'address3'			=> (isset($cus['address3']) ? $cus['address3'] : NULL),
						'pincode'			=> (isset($cus['pincode']) ? $cus['pincode'] : NULL),
						'active'			=> (isset($cus['active']) ? $cus['active'] : 0),
						'date_upd'			=>	date("Y-m-d H:i:s")
					)
				);
				$this->db->trans_begin();
				//echo "<pre>";print_r($cus_data);exit;
				$cus_id = $this->$model->update_customer($cus_data, $id);
				if ($id > 0) {
					// Sync Existing Data					
					if ($this->config->item("integrationType") == 3 || $this->config->item("autoSyncExisting") == 1) {
						$syncData = $this->sync_existing_data($cus_data['info']['mobile'], $id, $cus_data['info']['id_branch']);
					}
					//webcam upload, #AD On:20-12-2022,Cd:CLin,Up:AB     
					if (!empty($cus['cus_img'])) {
						$p_ImgData = json_decode(rawurldecode($cus['cus_img']));
						if (is_array($p_ImgData) && count($p_ImgData) > 0 && isset($p_ImgData[0]->src)) {
							$precious = $p_ImgData[0];
							$_FILES['cus_img'] = array();
							$imgFile = $this->base64ToFile($precious->src);
							$_FILES['cus_img'] = $imgFile;
						}
					}
					//webcam upload ends...
					$this->save_dynamic_kyc($id);

					// upload image
					if (isset($_FILES['cus_img']['name']) && !empty($_FILES['cus_img']['name'])) {
						$this->set_image($id);
					}
					// Upload proof images (PAN, Voter ID, Ration Card)
					$this->upload_customer_proofs($id);
				}
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Customer record modified successfully', 'class' => 'success', 'title' => 'Edit Customer'));
					redirect('customer');
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Edit Customer'));
					redirect('customer');
				}
				break;
			case 'Delete':
				// BUG-C09: Enforce POST method for delete (prevents CSRF via GET)
				if ($this->input->method() !== 'post') {
					$this->session->set_flashdata('chit_alert', array(
						'message' => 'Delete must use POST method (BUG-C09)',
						'class'   => 'danger',
						'title'   => 'Method Not Allowed'
					));
					redirect('customer');
					return;
				}
				$id = (int) $id; // Cast to int for safety
				$acc = $this->$model->check_acc_records($id);
				$pay = $this->$model->check_pay_records($id);
				if ($acc == TRUE || $pay == TRUE) {
					if ($pay == TRUE) {
						$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request, Check whether payment entry exists for this customer', 'class' => 'danger', 'title' => 'Delete Scheme'));
					} else {
						$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request, Check whether savings scheme account is assigned for this customer', 'class' => 'danger', 'title' => 'Delete Scheme'));
					}
				} else {
					$this->db->trans_begin();
					$this->$model->delete_customer($id);
					if ($this->db->trans_status() === TRUE) {
						//Remove image and its folder
						if (is_dir(self::CUS_IMG_PATH . $id)) {
							$this->rrmdir(self::CUS_IMG_PATH . $id);
						}
						$this->db->trans_commit();
						$this->session->set_flashdata('chit_alert', array('message' => 'Customer deleted successfully', 'class' => 'success', 'title' => 'Delete Customer'));
					} else {
						$this->db->trans_rollback();
						$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Delete Customer'));
					}
				}
				redirect('customer');
				break;
		}
	}
	function check_username($username)
	{
		$model_name = self::CUS_MODEL;
		$is_taken = $this->$model_name->username_available($username);
		// BUG-M10: Standardize to JSON response
		echo json_encode(array(
			'status' => $is_taken ? TRUE : FALSE,
			'msg'    => $is_taken ? 'Username already taken, please try another' : 'Available'
		));
	}
	function check_mobile()
	{
		$mobile = $this->input->post('mobile');
		$id_customer = $this->input->post('id_customer');
		$model_name = self::CUS_MODEL;
		if ($id_customer) {
			$available = $this->$model_name->mobile_available($mobile, $id_customer);
		} else {
			$available = $this->$model_name->mobile_available($mobile);
		}
		// BUG-M10: Standardize to JSON (was raw 1/0)
		echo json_encode(array(
			'status' => $available ? TRUE : FALSE,
			'msg'    => $available ? 'Mobile number already registered' : 'Available'
		));
	}
	function check_email()
	{
		$email = $this->input->post('email');
		$id_customer = $this->input->post('id_customer');
		$model_name = self::CUS_MODEL;
		if ($id_customer) {
			$available = $this->$model_name->email_available($email, $id_customer);
		} else {
			$available = $this->$model_name->email_available($email);
		}
		// BUG-M10: Standardize to JSON (was raw TRUE/FALSE PHP booleans)
		echo json_encode(array(
			'status' => $available ? TRUE : FALSE,
			'msg'    => $available ? 'Email already registered' : 'Available'
		));
	}
	function upload_img__($field, $img_path, $filename)
	{
		if (!is_dir($img_path)) {
			mkdir($img_path, 0755, TRUE); // BUG-H12: was 0777
		}
		if ($_FILES && $_FILES[$field]["tmp_name"] != "") {
			//print_r($_FILES);
			list($w, $h) = getimagesize($_FILES[$field]["tmp_name"]);
			/* calculate new image size with ratio */
			$width = 900;
			$height = 900;
			$ratio = max($width / $w, $height / $h);
			$h = ceil($height / $ratio);
			$x = ($w - $width / $ratio) / 2;
			$w = ceil($width / $ratio);
			/* new file name */
			$path = trim($img_path) . $filename;
			/* read binary data from image file */
			$imgString = file_get_contents($_FILES[$field]['tmp_name']);
			/* create image from string */
			$image = imagecreatefromstring($imgString);
			$tmp = imagecreatetruecolor($width, $height);
			imagecopyresampled(
				$tmp,
				$image,
				0,
				0,
				$x,
				0,
				$width,
				$height,
				$w,
				$h
			);
			/* Save image */
			switch ($_FILES[$field]['type']) {
				case 'image/jpeg':
					imagejpeg($tmp, $path, 60);
					break;
				case 'image/png':
					imagepng($tmp, $path, 0);
					break;
				case 'image/gif':
					imagegif($tmp, $path);
					break;
				default:
					exit;
					break;
			}
			$file_name = $path;
			imagedestroy($image);
			imagedestroy($tmp);
		}
	}
	function upload_img($outputImage, $dst, $img)
	{
		if (($img_info = getimagesize($img)) === FALSE) {
			// die("Image not found or not an image");
			return false;
		}
		$width = $img_info[0];
		$height = $img_info[1];
		switch ($img_info[2]) {
			case IMAGETYPE_GIF:
				$src = imagecreatefromgif($img);
				$tmp = imagecreatetruecolor($width, $height);
				$kek = imagecolorallocate($tmp, 255, 255, 255);
				imagefill($tmp, 0, 0, $kek);
				break;
			case IMAGETYPE_JPEG:
				$src = imagecreatefromjpeg($img);
				$tmp = imagecreatetruecolor($width, $height);
				break;
			case IMAGETYPE_PNG:
				$src = imagecreatefrompng($img);
				$tmp = imagecreatetruecolor($width, $height);
				$kek = imagecolorallocate($tmp, 255, 255, 255);
				imagefill($tmp, 0, 0, $kek);
				break;
			default: //die("Unknown filetype");	
				return false;
		}
		imagecopyresampled($tmp, $src, 0, 0, 0, 0, $width, $height, $width, $height);
		imagejpeg($tmp, $dst);
	}
	function set_image($cus_id)
	{
		$data = array();
		$img_path = self::CUS_IMG_PATH . "/" . $cus_id;
		if (!is_dir($img_path)) {
			mkdir($img_path, 0755, TRUE); // BUG-H12: was 0777
		}
		if (isset($_FILES['cus_img']['name']) && !empty($_FILES['cus_img']['name'])) {
			//  $ext = pathinfo($_FILES['cus_img']['name'], PATHINFO_EXTENSION);
			$img = $_FILES['cus_img']['tmp_name'];
			$path = self::CUS_IMG_PATH . $cus_id . "/" . self::CUS_IMG;
			$filename = self::CUS_IMG;
			$this->upload_img('cus_img', $path, $img);
			$data['cus_img'] = $filename;
			$this->db->where('id_customer', $cus_id)->update('customer', $data);
		}

	}
	function upload_customer_proofs($cus_id)
	{
		$data = array();
		$img_path = self::CUS_IMG_PATH . $cus_id . "/";
		if (!is_dir($img_path)) {
			mkdir($img_path, 0755, TRUE);
		}

		// Handle PAN proof
		if (isset($_FILES['pan_proof']['name']) && $_FILES['pan_proof']['name'] != '') {
			$ext = pathinfo($_FILES['pan_proof']['name'], PATHINFO_EXTENSION);
			$ext = strtolower($ext) ?: 'jpg';
			$filename = 'pan_' . $cus_id . '.' . $ext;
			$target_file = $img_path . $filename;
			if (move_uploaded_file($_FILES['pan_proof']['tmp_name'], $target_file)) {
				chmod($target_file, 0755);
				$data['pan_proof'] = $filename;
			}
		}

		// Handle Voter ID proof
		if (isset($_FILES['voterid_proof']['name']) && $_FILES['voterid_proof']['name'] != '') {
			$ext = pathinfo($_FILES['voterid_proof']['name'], PATHINFO_EXTENSION);
			$ext = strtolower($ext) ?: 'jpg';
			$filename = 'voterid_' . $cus_id . '.' . $ext;
			$target_file = $img_path . $filename;
			if (move_uploaded_file($_FILES['voterid_proof']['tmp_name'], $target_file)) {
				chmod($target_file, 0755);
				$data['voterid_proof'] = $filename;
			}
		}

		// Handle Ration Card proof
		if (isset($_FILES['rationcard_proof']['name']) && $_FILES['rationcard_proof']['name'] != '') {
			$ext = pathinfo($_FILES['rationcard_proof']['name'], PATHINFO_EXTENSION);
			$ext = strtolower($ext) ?: 'jpg';
			$filename = 'rationcard_' . $cus_id . '.' . $ext;
			$target_file = $img_path . $filename;
			if (move_uploaded_file($_FILES['rationcard_proof']['tmp_name'], $target_file)) {
				chmod($target_file, 0755);
				$data['rationcard_proof'] = $filename;
			}
		}

		if (!empty($data)) {
			$this->db->where('id_customer', $cus_id)->update('customer', $data);
		}
	}
	function rrmdir($path)
	{
		// Open the source directory to read in files
		$i = new DirectoryIterator($path);
		foreach ($i as $f) {
			if ($f->isFile()) {
				unlink($f->getRealPath());
			} else if (!$f->isDot() && $f->isDir()) {
				rrmdir($f->getRealPath());
			}
		}
		rmdir($path);
	}
	function profile_status($status, $id)
	{
		// BUG-H02: Enforce POST method for profile status
		if ($this->input->method() !== 'post') {
			$this->session->set_flashdata('chit_alert', array(
				'message' => 'Status change must use POST method (BUG-H02)',
				'class'   => 'danger',
				'title'   => 'Method Not Allowed'
			));
			redirect('customer');
			return;
		}
		$id = (int) $id;
		$status = in_array($status, array('0', '1')) ? $status : '1';
		$data = array('profile_complete' => $status);
		$model = self::CUS_MODEL;
		$status = $this->$model->update_customer_only($data, $id);
		if ($status) {
			$this->session->set_flashdata('chit_alert', array('message' => 'Profile status updated as ' . ($status ? 'complete' : 'incomplete') . ' successfully.', 'class' => 'success', 'title' => 'Profile Status'));
		} else {
			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Profile Status'));
		}
		redirect('customer');
	}
	function customer_status($status, $id)
	{
		// BUG-H02: Enforce POST method for status changes
		if ($this->input->method() !== 'post') {
			$this->session->set_flashdata('chit_alert', array(
				'message' => 'Status change must use POST method (BUG-H02)',
				'class'   => 'danger',
				'title'   => 'Method Not Allowed'
			));
			redirect('customer');
			return;
		}
		$id = (int) $id;
		$status = in_array($status, array('0', '1')) ? $status : '1';
		$data = array('active' => $status);
		$model = self::CUS_MODEL;
		$status = $this->$model->update_customer_only($data, $id);
		if ($status) {
			$this->session->set_flashdata('chit_alert', array('message' => 'Customer status updated as ' . ($status ? 'active' : 'inactive') . ' successfully.', 'class' => 'success', 'title' => 'Customer Status'));
		} else {
			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Customer Status'));
		}
		redirect('customer');
	}
	//To download files
	public function download($id, $file)
	{
		// BUG-H07: Whitelist allowed file names to prevent path traversal
		$allowed_files = array('customer', 'pan', 'voterid', 'rationcard');
		if (!in_array($file, $allowed_files)) {
			show_error('Invalid file requested', 400);
			return;
		}
		$id = (int) $id; // Ensure numeric
		$img_path = self::CUS_IMG_PATH . $id . "/" . $file . ".jpg";
		if (!file_exists($img_path)) {
			show_error('File not found', 404);
			return;
		}
		//load the download helper
		$this->load->helper('download');
		$data = file_get_contents($img_path);
		$name = $file . '_' . $id . '.jpg';
		force_download($name, $data);
		return TRUE;
	}
	// wallet account insert //
	function wallet_account_create($cus_id, $mobile)
	{
		$set_model   = self::SET_MODEL;
		$wallmodel   = self::WALL_MODEL;
		$log_model   = self::LOG_MODEL;
		$chits_model = self::ADM_MODEL;
		$sms_model   = self::SMS_MODEL;
		$mail_model  = self::MAIL_MODEL;
		$wallet_acc_no =  $this->$wallmodel->get_wallet_acc_number();
		$insertData = array(
			'id_customer' 	   => (isset($cus_id) && $cus_id != '' ? $cus_id : NULL),
			'id_employee' 	   =>  $this->session->userdata('uid'),
			'wallet_acc_number' => (isset($wallet_acc_no) ? $wallet_acc_no : NULL),
			'issued_date' 	   => date('y-m-d H:i:s'),
			'remark' 		    => "Credits",
			'active'		        => 1
		);
		//inserting data                  
		$status = $this->$wallmodel->wallet_accountDB("insert", "", $insertData);
		$wallAcc = $this->$wallmodel->wallet_accountDB("get", $status['insertID']);
		//  $this->$wallmodel->insChitwallet($status['insertID'],$mobile,$cus_id);
		if ($status) {
			$log_data = array(
				'id_log'     => $this->session->userdata('id_log'),
				'event_date' => date("Y-m-d H:i:s"),
				'module'     => 'Wallet Account',
				'operation'  => 'Add',
				'record'     => $status['insertID'],
				'remark'     => 'Wallet Account added successfully'
			);
			$this->$log_model->log_detail('insert', '', $log_data);
			$serviceID = 8;
			$service =  $this->$set_model->get_service($serviceID);
			$company = $this->$set_model->get_company();
			if ($service['serv_sms'] == 1) {
				//   '0 - promotion sms , 1 - otp',
				$otp_promotion = 1;
				$id = $status['insertID'];
				$data = $this->$sms_model->get_SMS_data($serviceID, $id);
				$mobile = $data['mobile'];
				$message = $data['message'];
				$dlt_te_id = $service['dlt_te_id'];
				if ($this->config->item('sms_gateway') == '1') {
					$this->sms_model->sendSMS_MSG91($mobile, $message, $otp_promotion, $dlt_te_id);
				} elseif ($this->config->item('sms_gateway') == '2') {
					$this->sms_model->sendSMS_Nettyfish($mobile, $message, 'promo', $dlt_te_id);
				} elseif ($this->config->item('sms_gateway') == '3') {
					$this->sms_model->sendSMS_SpearUC($mobile, $message, '', $dlt_te_id);
				} elseif ($this->config->item('sms_gateway') == '4') {
					$this->sms_model->sendSMS_Asterixt($mobile, $message, '', $dlt_te_id);
				} elseif ($this->config->item('sms_gateway') == '5') {
					$this->sms_model->sendSMS_Qikberry($mobile, $message, '', $dlt_te_id);
				}
			}
			$email	=   $wallAcc['email'];
			if ($service['serv_email'] == 1  && $email != '') {
				$data['walData'] = $wallAcc;
				$data['company'] = $company;
				$data['type'] = 1;
				$to = $email;
				$subject = "Reg- " . $company['company_name'] . " saving scheme wallet account creation.";
				$message = $this->load->view('include/emailWallet', $data, true);
				$sendEmail = $this->$mail_model->send_email($to, $subject, $message);
			}
		}
	}
	// wallet account insert //
	public function get_customer_by_mobile()
	{
		$model_name = self::CUS_MODEL;
		$mobile = $this->input->post('mobile');
		$customer = $this->$model_name->get_customer_by_mobile($mobile);
		echo json_encode($customer);
	}
	public function ajax_get_customers_list()
	{
		$mobile = $this->input->post('mobile');
		$id_scheme = $this->input->post('id_scheme');
		$model_name = self::CUS_MODEL;
		$cus_data = $this->$model_name->ajax_get_customers_list($mobile, $id_scheme);
		//	echo "<pre>"; print_r($cus_data);exit;
		echo json_encode($cus_data);
	}
	public function ajax_get_village()
	{
		$model_name = self::SET_MODEL;
		// BUG-C10: Pass term param for type-ahead filtering
		$id_village = $this->input->get('id_village') ?: '';
		$cus_data = $this->$model_name->ajax_village_list($id_village);
		echo json_encode($cus_data);
	}
	public function cus_profile($type = "", $id = "")
	{
		$model = self::CUS_MODEL;
		switch ($type) {
			case 'list':
				$data['main_content'] = self::CUS_VIEW . "profile";
				$this->load->view('layout/template', $data);
				break;
			case 'edit':
				$data = $this->$model->Searchcustomer($_POST['searchTxt']);
				echo json_encode($data);
				break;
			case 'update':
				$cus = $this->input->post('customer');
				$cus_data = array(
					'info' => array(
						'firstname'			=> (isset($cus['firstname']) ? strtoupper($cus['firstname']) : NULL),
						'lastname' 			=> (isset($cus['lastname']) ? strtoupper($cus['lastname']) : NULL),
						'id_branch'		    => ($this->session->userdata('id_branch') != '' ? $this->session->userdata('id_branch') : NULL),
						'religion'		    => (!empty($cus['religion']) ? $cus['religion'] : NULL),
						'id_village'		=> ($cus['id_village'] != '' ? $cus['id_village'] : NULL),
						'date_of_birth'		=> (isset($cus['date_of_birth']) && $cus['date_of_birth'] != '' ? date('Y-m-d', strtotime(str_replace("/", "-", $cus['date_of_birth']))) : NULL),
						'date_of_wed'		=> (isset($cus['date_of_wed']) && $cus['date_of_wed'] != '' ? date('Y-m-d', strtotime(str_replace("/", "-", $cus['date_of_wed']))) : NULL),
						'email'				=> (isset($cus['email']) ? $cus['email'] : NULL),
						'gender'			=> (isset($cus['gender']) ? $cus['gender'] : '-1'),
						'send_promo_sms'	=> (isset($cus['send_promo_sms']) ? $cus['send_promo_sms'] : 0),
						'date_upd'			=>   date("Y-m-d H:i:s")
					),
					'address'				=> array(
						'id_country'		=> (isset($cus['country']) ? $cus['country'] : NULL),
						'id_state' 			=> (isset($cus['state']) ? $cus['state'] : NULL),
						'id_city'			=> (isset($cus['city']) ? $cus['city'] : NULL),
						'company_name'			=> (isset($cus['company_name']) ? $cus['company_name'] : NULL),
						'address1'			=> (isset($cus['address1']) ? strtoupper($cus['address1']) : NULL),
						'address2'			=> (isset($cus['address2']) ? strtoupper($cus['address2']) : NULL),
						'address3'			=> (isset($cus['address3']) ? strtoupper($cus['address3']) : NULL),
						'pincode'			=> (isset($cus['pincode']) ? $cus['pincode'] : NULL),
						'active'			=> (isset($cus['active']) ? $cus['active'] : 0),
						'date_upd'			=>	date("Y-m-d H:i:s")
					),
				);
				$this->db->trans_begin();
				$cus_id = $this->$model->update_customer($cus_data, $cus['id_customer']);
				//echo "<pre>"; print_r($this->db->last_query());exit;
				if ($this->db->trans_status() === TRUE) {
					$log_data = array(
						'id_log'        => $this->session->userdata('id_log'),
						'event_date'	=> date("Y-m-d H:i:s"),
						'module'      	=> 'Customer',
						'operation'   	=> 'Edit',
						'record'        => $id,
						'remark'       	=> 'Record edited successfully'
					);
					$this->log_model->log_detail('insert', '', $log_data);
					$this->db->trans_commit();
					$return_data = array('status' => true);
					$this->session->set_flashdata('chit_alert', array('message' => 'Customer record modified successfully', 'class' => 'success', 'title' => 'Edit Customer'));
				} else {
					$this->db->trans_rollback();
					$return_data = array('status' => false);
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Edit Customer'));
				}
				echo json_encode($return_data);
				break;
		}
	}
	function ajax_getAllActiveAgents()
	{
		$model_name = self::CUS_MODEL;
		$agent_select = $this->$model_name->getAllActiveAgents();
		echo json_encode($agent_select);
	}
	function allocate_agent_toCuctomers()
	{
		$model = self::CUS_MODEL;
		$cus = $this->input->post('id_customer');
		$id_agent = $this->input->post('id_agent');
		$not_allocated = array(); // BUG-H10: initialize before use
		$i = 0;
		// BUG-H10: was: $total=count($cus); $total=array(); — overwrite killed the foreach
		if (!empty($cus) && is_array($cus)) {
			$this->load->model($model);
			foreach ($cus as $cusid) {
				$cusid = (int) $cusid;
				$upd_data = array('id_agent' => (int) $id_agent);
				$cusData = $this->$model->allocate_agent($upd_data, $cusid);
				if ($this->db->trans_status() === TRUE) {
					$i++;
				} else {
					$not_allocated[] = $cusid;
				}
			}
		}
		$total_cus = is_array($cus) ? count($cus) : 0;
		if ($total_cus > 0 && $i === $total_cus) {
			echo json_encode(array('status' => 1, 'total' => $total_cus));
		} elseif ($i === 0) {
			echo json_encode(array('status' => 0, 'total' => $total_cus, 'not_allocated' => $not_allocated));
		} else {
			echo json_encode(array('status' => 2, 'total' => $total_cus, 'not_allocated' => $not_allocated));
		}
	}
	// BUG-L08: Alias with correct spelling — old method name kept for JS compatibility
	public function allocate_agent_toCustomers()
	{
		return $this->allocate_agent_toCuctomers();
	}
	//webcam upload, #AD On:20-12-2022,Cd:CLin,Up:AB	
	public function base64ToFile($imgBase64)
	{
		$data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imgBase64)); // might not work on some systems, specify your temp path if system temp dir is not writeable
		$temp_file_path = tempnam(sys_get_temp_dir(), 'tempimg');
		file_put_contents($temp_file_path, $data);
		$image_info = getimagesize($temp_file_path);
		$imgFile = array(
			'name' => uniqid() . '.' . preg_replace('!\w+/!', '', $image_info['mime']),
			'tmp_name' => $temp_file_path,
			'size'  => filesize($temp_file_path),
			'error' => UPLOAD_ERR_OK,
			'type'  => $image_info['mime'],
		);
		return $imgFile;
	}
	public function ajax_get_scheme_account_list()
	{
		$model_name = self::CUS_MODEL;
		$scheme_acc_number = $this->input->post('acc_no');
		$is_old_sch_acc_no = $this->input->post('old_acc') ?? '';
		// $scheme_code = $this->input->post('scheme_code');
		$cus_data = $this->$model_name->ajax_get_scheme_account_list($scheme_acc_number,$is_old_sch_acc_no);
		echo json_encode($cus_data);
	}
	function format_accRcptNo($type, $id)
	{
		$format = $this->customer_model->format_accRcptNo($type, $id);
		echo json_encode($format);
	}
	public function  store_KycImages($cus, $id)
	{
		$model = self::CUS_MODEL;
		//webcam kyc document upload starts... added_on : 7/6/2023 #AB...
		$pan_img  = json_decode(rawurldecode($cus['pan_img']));
		if (sizeof($pan_img) > 0) {
			$_FILES['pan_img']    =  array();
			$pan = explode(";base64,", $pan_img[0]->src);
			$panimagebase64  = base64_decode($pan[1]);
			$img_path = "assets/img/customer/" . $id . "/";
			if (!is_dir($img_path)) {
				mkdir($img_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			if (file_put_contents($img_path . 'pan_' . $id . '.png', $panimagebase64)) {
				$updCus = array('pan_ImgName' => 'pan_' . $id . '.png');
				$this->$model->updData($updCus, 'id_customer', $id, 'customer');
			}
		}
		$aadhar_img  = json_decode(rawurldecode($cus['aadhar_img']));
		if (sizeof($aadhar_img) > 0) {
			$_FILES['pan_img']    =  array();
			$aadhar = explode(";base64,", $aadhar_img[0]->src);
			$aadharimagebase64  = base64_decode($aadhar[1]);
			$img_path = "assets/img/customer/" . $id . "/";
			if (!is_dir($img_path)) {
				mkdir($img_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			if (file_put_contents($img_path . 'aadhar_' . $id . '.png', $aadharimagebase64)) {
				$updCus = array('aadhar_ImgName' => 'aadhar_' . $id . '.png');
				$this->$model->updData($updCus, 'id_customer', $id, 'customer');
			}
		}
		$dl_img  = json_decode(rawurldecode($cus['dl_img']));
		if (sizeof($dl_img) > 0) {
			$_FILES['pan_img']    =  array();
			$dl = explode(";base64,", $dl_img[0]->src);
			$dlimagebase64  = base64_decode($dl[1]);
			$img_path = "assets/img/customer/" . $id . "/";
			if (!is_dir($img_path)) {
				mkdir($img_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			if (file_put_contents($img_path . 'dl_' . $id . '.png', $dlimagebase64)) {
				$updCus = array('dl_ImgName' => 'dl_' . $id . '.png');
				$this->$model->updData($updCus, 'id_customer', $id, 'customer');
			}
		}
		$pp_img  = json_decode(rawurldecode($cus['pp_img']));
		if (sizeof($pp_img) > 0) {
			$_FILES['pan_img']    =  array();
			$pp = explode(";base64,", $pp_img[0]->src);
			$ppimagebase64  = base64_decode($pp[1]);
			$img_path = "assets/img/customer/" . $id . "/";
			if (!is_dir($img_path)) {
				mkdir($img_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			if (file_put_contents($img_path . 'pp_' . $id . '.png', $ppimagebase64)) {
				$updCus = array('pp_ImgName' => 'pp_' . $id . '.png');
				$this->$model->updData($updCus, 'id_customer', $id, 'customer');
			}
		}

		$pb_img  = json_decode(rawurldecode($cus['pb_img']));
		if (sizeof($pb_img) > 0) {
			$_FILES['pb_img']    =  array();
			$pb = explode(";base64,", $pb_img[0]->src);
			$pbimagebase64  = base64_decode($pb[1]);
			$img_path = "assets/kyc/pb/" . $id . "/";
			if (!is_dir($img_path)) {
				mkdir($img_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			if (file_put_contents($img_path . 'pb_' . $id . '.png', $pbimagebase64)) {
				$updCus = array('pb_ImgName' => 'pb_' . $id . '.png');
				$this->$model->updData($updCus, 'id_customer', $id, 'customer');
			}
		}
		//webcam kyc document upload starts
		return TRUE;
	}
	public function retrive_KycImages($cus)
	{
		//retrieve webcam kyc document file starts... added_on : 7/6/2023 #AB...	   
		$pan_img = self::CUS_IMG_PATH . $cus['id_customer'] . '/' . $cus['pan_ImgName'];
		$aadhar_img = self::CUS_IMG_PATH . $cus['id_customer'] . '/' . $cus['aadhar_ImgName'];
		$dl_img = self::CUS_IMG_PATH . $cus['id_customer'] . '/' . $cus['dl_ImgName'];
		$pp_img = self::CUS_IMG_PATH . $cus['id_customer'] . '/' . $cus['pp_ImgName'];
		if (file_exists($pan_img)) {
			$data['customer']['pan_img_path']		= $pan_img;
		}
		if (file_exists($aadhar_img)) {
			$data['customer']['aadhar_img_path']		= $aadhar_img;
		}
		if (file_exists($dl_img)) {
			$data['customer']['dl_img_path']		= $dl_img;
		}
		if (file_exists($pp_img)) {
			$data['customer']['pp_img_path']		= $pp_img;
		}
		// print_r($data);exit;
		return $data;
		//retrieve webcam kyc document file ends
	}
	public function get_kyc_images($cus, $id)
	{
		// echo "<pre>DEBUG \$cus:\n";
		// print_r($cus);
		// echo "</pre>";
		// exit;
		
		$kycData = [];
		//image pan front path for kyc master starts
		$pan_img_front  = json_decode(rawurldecode($cus['pan_img_front']));
		$kyc_panpath = "";
		if (sizeof($pan_img_front) > 0) {
			//	$_FILES['pan_images_front']    =  array();
			$pan = explode(";base64,", $pan_img_front->src);
			$panimagebase64  = base64_decode($pan[1]);
			$kyc_path = self::KYC_PAN_PATH . "" . $id;
			if (!is_dir($kyc_path)) {
				mkdir($kyc_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			$kycpath = self::KYC_PAN_PATH . "" . $id . "/pan_front.png";
			file_put_contents($kycpath, $panimagebase64);
			$kycData['pan_front_img_url'] = isset($kycpath) ? base_url() . "" . $kycpath : NULL;
			//	 echo "<pre>"; print_r($kycData);exit;
		}
		//image pan front path for kyc master ends
		//image pan back path for kyc master starts
		$pan_img_back  = json_decode(rawurldecode($cus['pan_img_back']));
		$kyc_panpath = "";
		if (sizeof($pan_img_back) > 0) {
			//	$_FILES['pan_images_back']    =  array();
			$pan = explode(";base64,", $pan_img_back->src);
			$panimagebase64  = base64_decode($pan[1]);
			$kyc_path = self::KYC_PAN_PATH . "" . $id;
			if (!is_dir($kyc_path)) {
				mkdir($kyc_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			$kycpath = self::KYC_PAN_PATH . "" . $id . "/pan_back.png";
			file_put_contents($kycpath, $panimagebase64);
			$kycData['pan_back_img_url'] = isset($kycpath) ? base_url() . "" . $kycpath : NULL;
		}
		//image pan back path for kyc master ends
		$aadhar_img_front  = json_decode(rawurldecode($cus['aadhar_img_front']));
		//print_r($aadhar_img);exit;
		$kyc_aadharpath = "";
		if (sizeof($aadhar_img_front) > 0) {
			//image path for aadhar kyc master starts
			//	$_FILES['pan_img']    =  array();
			$aadhar = explode(";base64,", $aadhar_img_front->src);
			$aadharimagebase64  = base64_decode($aadhar[1]);
			$file_path = self::KYC_AADHAR_PATH . "" . $id;
			if (!is_dir($file_path)) {
				mkdir($file_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			$kyc_aadharpath = self::KYC_AADHAR_PATH . "" . $id . "/aadhar_front.png";
			//print_r($path);exit;
			file_put_contents($kyc_aadharpath, $aadharimagebase64);
			$kycData['aadhar_img_front_url'] = isset($kyc_aadharpath) ? base_url() . "" . $kyc_aadharpath : NULL;
			//image path for aadhar kyc master ends
		}
		$aadhar_img_back  = json_decode(rawurldecode($cus['aadhar_img_back']));
		//print_r($aadhar_img);exit;
		$kyc_aadharpath = "";
		if (sizeof($aadhar_img_back) > 0) {
			//image path for aadhar kyc master starts
			//	$_FILES['pan_img']    =  array();
			$aadhar = explode(";base64,", $aadhar_img_back->src);
			$aadharimagebase64  = base64_decode($aadhar[1]);
			$file_path = self::KYC_AADHAR_PATH . "" . $id;
			if (!is_dir($file_path)) {
				mkdir($file_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			$kyc_aadharpath = self::KYC_AADHAR_PATH . "" . $id . "/aadhar_back.png";
			//print_r($path);exit;
			file_put_contents($kyc_aadharpath, $aadharimagebase64);
			$kycData['aadhar_img_back_url'] = isset($kyc_aadharpath) ? base_url() . "" . $kyc_aadharpath : NULL;
			//image path for aadhar kyc master ends
		}


		$pb_img = rawurldecode($cus['pb_img'] ?? '');

		if (!empty($pb_img)) {
			$file_path = self::KYC_PB_PATH . $id;

			if (!is_dir($file_path)) {
				mkdir($file_path, 0755, true); // BUG-H12: 0777->0755
			}

			if (strpos($pb_img, 'data:application/pdf;base64,') === 0) {
				// Handle PDF
				$base64_string = explode('base64,', $pb_img, 2)[1] ?? null;
				$pdf_content = base64_decode($base64_string);

				if ($pdf_content !== false) {
					$filename = 'pb_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
					$full_path = $file_path . '/' . $filename;
					file_put_contents($full_path, $pdf_content);

					$kycData['pb_doc_url'] = base_url() . $full_path;
				}
			} elseif (strpos($pb_img, 'data:image/') === 0) {
				// Handle Image
				preg_match('/^data:image\/(\w+);base64,/', $pb_img, $matches);
				$extension = $matches[1] ?? 'png';
				$base64_string = explode('base64,', $pb_img, 2)[1] ?? null;
				$image_data = base64_decode($base64_string);

				if ($image_data !== false) {
					$filename = 'pb_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $extension;
					$full_path = $file_path . '/' . $filename;
					file_put_contents($full_path, $image_data);

					$kycData['pb_img_url'] = base_url() . $full_path;
				}
			} 
		} 

		$ch_img = rawurldecode($cus['ch_img'] ?? '');

		if (!empty($ch_img)) {
			$file_path = self::KYC_CH_PATH . $id;

			if (!is_dir($file_path)) {
				mkdir($file_path, 0755, true); // BUG-H12: 0777->0755
			}

			if (strpos($ch_img, 'data:application/pdf;base64,') === 0) {
				// Handle PDF
				$base64_string = explode('base64,', $ch_img, 2)[1] ?? null;
				$pdf_content = base64_decode($base64_string);

				if ($pdf_content !== false) {
					$filename = 'ch_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
					$full_path = $file_path . '/' . $filename;
					file_put_contents($full_path, $pdf_content);

					$kycData['ch_doc_url'] = base_url() . $full_path;
				}
			} elseif (strpos($ch_img, 'data:image/') === 0) {
				// Handle Image
				preg_match('/^data:image\/(\w+);base64,/', $ch_img, $matches);
				$extension = $matches[1] ?? 'png';
				$base64_string = explode('base64,', $ch_img, 2)[1] ?? null;
				$image_data = base64_decode($base64_string);

				if ($image_data !== false) {
					$filename = 'ch_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $extension;
					$full_path = $file_path . '/' . $filename;
					file_put_contents($full_path, $image_data);

					$kycData['ch_img_url'] = base_url() . $full_path;
				}
			}
		}

		// $pb_img = rawurldecode($cus['pb_img']);  // decode URL encoding if any

		// if (!empty($pb_img) && strpos($pb_img, 'data:image/') === 0) {
		// 	// It's a base64 encoded image
		// 	$pb = explode(";base64,", $pb_img);
		// 	if (isset($pb[1])) {
		// 		$pbimagebase64 = base64_decode($pb[1]);
		// 	} else {
		// 		$pbimagebase64 = base64_decode($pb_img); // fallback
		// 	}

		// 	$kyc_path = self::KYC_PB_PATH . $id;
		// 	if (!is_dir($kyc_path)) {
		// 		mkdir($kyc_path, 0777, true);
		// 	}

		// 	$filename = 'pb_' . time() . '_' . bin2hex(random_bytes(3)) . '.png';
		// 	$kycpath = $kyc_path . '/' . $filename;

		// 	if (file_put_contents($kycpath, $pbimagebase64) !== false) {
		// 		$kycData['pb_img_url'] = base_url($kycpath);
		// 	} else {
		// 		$kycData['pb_img_url'] = null; // or fallback error handling
		// 	}
		// } else if (strpos($pb_img, 'data:application/pdf;base64,') === 0) {
		// 		$base64_string = explode('base64,', $pb_img)[1];
		// 		$pdf_content = base64_decode($base64_string);

		// 		$file_path = self::KYC_PB_PATH . $id;

		// 		if (!is_dir($file_path)) {
		// 			if (!mkdir($file_path, 0777, true)) {
		// 			}
		// 		}

		// 		$filename = 'pb_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
		// 		$full_path = $file_path . '/' . $filename;

		// 		file_put_contents($full_path, $pdf_content);

		// 		$kycData['pb_doc_url'] = isset($full_path) ? base_url() . "" . $full_path : NULL;
		// } 

		// $ch_img = rawurldecode($cus['ch_img']);  // decode URL encoding if any

		// if (!empty($ch_img) && strpos($ch_img, 'data:image/') === 0) {
			
		// 	$ch = explode(";base64,", $ch_img);
	
		// 	if (isset($ch[1])) {
		// 		$chimagebase64 = base64_decode($ch[1]);
		// 	} else {
		// 		// die('Invalid base64 image data');
		// 	}

		// 	$kyc_path = self::KYC_CH_PATH . $id;
		// 	if (!is_dir($kyc_path)) {
		// 		mkdir($kyc_path, 0777, true);
		// 	}

		// 	$filename = 'ch_' . time() . '_' . bin2hex(random_bytes(3)) . '.png';
		// 	$kycpath = $kyc_path . '/' . $filename;

		// 	if (file_put_contents($kycpath, $chimagebase64) === false) {
		// 		// die('Failed to save image.');
		// 	}

		// 	// Assuming base_url() takes a relative path and returns full URL
		// 	$kycData['ch_img_url'] = file_exists($kycpath) ? base_url($kycpath) : null;
		// }else {
		// 	$kycData['ch_img_url'] = $cus['ch_img'];
		// }


		if ($_FILES['pdf_file_input']['name']) {
			$fileName = $_FILES["pdf_file_input"]["tmp_name"];
			$files = $_FILES["pdf_file_input"]["name"];
			$fileExtension = pathinfo($files, PATHINFO_EXTENSION); // Get the file extension
			//	print_r($fileExtension);exit;
			$file_path = self::KYC_PAN_PATH . "" . $id;
			if (!is_dir($file_path)) {
				mkdir($file_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			$kyc_panpath = self::KYC_PAN_PATH . "" . $id . "/pan." . $fileExtension;
			// file_put_contents($kyc_panpath,$fileName);
			$content = file_get_contents($fileName); // Get content from the uploaded file
			file_put_contents($kyc_panpath, $content);
			$kycData['pan_doc_url'] = isset($kyc_panpath) ? base_url() . "" . $kyc_panpath : NULL;
			//	print_r($kycData['pan_doc_url']);exit;
		}
		if ($_FILES['pdf_aadhar_file_input']['name']) {
			$fileName = $_FILES["pdf_aadhar_file_input"]["tmp_name"];
			$files = $_FILES["pdf_aadhar_file_input"]["name"];
			$fileExtension = pathinfo($files, PATHINFO_EXTENSION); // Get the file extension
			$file_path = self::KYC_AADHAR_PATH . "" . $id;
			if (!is_dir($file_path)) {
				mkdir($file_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			$kyc_aadharpath = self::KYC_AADHAR_PATH . "" . $id . "/aadhar." . $fileExtension;
			$content = file_get_contents($fileName); // Get content from the uploaded file
			file_put_contents($kyc_aadharpath, $content);
			$kycData['aadhar_doc_url'] = isset($kyc_aadharpath) ? base_url() . "" . $kyc_aadharpath : NULL;
			//	print_r($kycData);exit;
		}

		// if ($_FILES['pdf_pb_file_input']['name']) {
		// 	$fileName = $_FILES["pdf_pb_file_input"]["tmp_name"];
		// 	$files = $_FILES["pdf_pb_file_input"]["name"];
		// 	$fileExtension = pathinfo($files, PATHINFO_EXTENSION); // Get the file extension
		// 	$file_path = self::KYC_PB_PATH . "" . $id;
		// 	if (!is_dir($file_path)) {
		// 		mkdir($file_path, 0777, TRUE);
		// 	}
		// 	$kyc_pbpath = self::KYC_PB_PATH . "" . $id . "/pb." . $fileExtension;
		// 	$content = file_get_contents($fileName); // Get content from the uploaded file
		// 	file_put_contents($kyc_pbpath, $content);
		// 	$kycData['pb_doc_url'] = isset($kyc_pbpath) ? base_url() . "" . $kyc_pbpath : NULL;
		// 	//	print_r($kycData);exit;
		// }

		 if (!empty($cus['pb_doc_url'])) {
			$data_url = $cus['pb_doc_url'];

			if (strpos($data_url, 'data:application/pdf;base64,') === 0) {
				$base64_string = explode('base64,', $data_url)[1];
				$pdf_content = base64_decode($base64_string);

				$file_path = self::KYC_PB_PATH . $id;

				if (!is_dir($file_path)) {
					if (!mkdir($file_path, 0755, true)) { // BUG-H12: 0777→0755
					}
				}

				$filename = 'pb_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
				$full_path = $file_path . '/' . $filename;

				file_put_contents($full_path, $pdf_content);

				$kycData['pb_doc_url'] = isset($full_path) ? base_url() . "" . $full_path : NULL;
			} else {
				$kycData['pb_doc_url'] = $data_url;
			}
		} else {
			// die(" 'pb_doc_url' not found in \$cus array.");
		}

		if (!empty($cus['ch_doc_url'])) {
			$data_url = $cus['ch_doc_url'];

			if (strpos($data_url, 'data:application/pdf;base64,') === 0) {
				$base64_string = explode('base64,', $data_url)[1];
				$pdf_content = base64_decode($base64_string);

				$file_path = self::KYC_CH_PATH . $id;

				if (!is_dir($file_path)) {
					if (!mkdir($file_path, 0755, true)) { // BUG-H12: 0777→0755
					}
				}

				$filename = 'ch_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
				$full_path = $file_path . '/' . $filename;

				file_put_contents($full_path, $pdf_content);

				$kycData['ch_doc_url'] = isset($full_path) ? base_url() . "" . $full_path : NULL;
			} 
		}


		
		// print_r($_FILES['pan_img_front']);exit;
		// print_r($_FILES);exit;
		if ($_FILES['pan_img_front']['name']) {
			$fileName = $_FILES["pan_img_front"]["tmp_name"];
			$files = $_FILES["pan_img_front"]["name"];
			// $fileExtension = pathinfo($files, PATHINFO_EXTENSION); // Get the file extension
			$file_path = self::KYC_PAN_PATH . "" . $id;
			if (!is_dir($file_path)) {
				mkdir($file_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			$kycpath = self::KYC_PAN_PATH . "" . $id . "/pan_front.png";
			$content = file_get_contents($fileName);
			file_put_contents($kycpath, $content);
			$kycData['pan_front_img_url'] = isset($kycpath) ? base_url() . "" . $kycpath : NULL;
			//	print_r($kycData);exit;
		}
		if ($_FILES['pan_img_back']['name']) {
			$fileName = $_FILES["pan_img_back"]["tmp_name"];
			$files = $_FILES["pan_img_back"]["name"];
			// $fileExtension = pathinfo($files, PATHINFO_EXTENSION); // Get the file extension
			$file_path = self::KYC_PAN_PATH . "" . $id;
			if (!is_dir($file_path)) {
				mkdir($file_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			$kycpath = self::KYC_PAN_PATH . "" . $id . "/pan_back.png";
			$content = file_get_contents($fileName);
			file_put_contents($kycpath, $content);
			$kycData['pan_back_img_url'] = isset($kycpath) ? base_url() . "" . $kycpath : NULL;
			//	print_r($kycData);exit;
		}
		if ($_FILES['aadhar_img_front']['name']) {
			$fileName = $_FILES["aadhar_img_front"]["tmp_name"];
			$files = $_FILES["aadhar_img_front"]["name"];
			// $fileExtension = pathinfo($files, PATHINFO_EXTENSION); // Get the file extension
			$file_path = self::KYC_AADHAR_PATH . "" . $id;
			if (!is_dir($file_path)) {
				mkdir($file_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			$kycpath = self::KYC_AADHAR_PATH . "" . $id . "/aadhar_front.png";
			$content = file_get_contents($fileName);
			file_put_contents($kycpath, $content);
			$kycData['aadhar_img_front_url'] = isset($kycpath) ? base_url() . "" . $kycpath : NULL;
			//	print_r($kycData);exit;
		}
		if ($_FILES['aadhar_img_back']['name']) {
			$fileName = $_FILES["aadhar_img_back"]["tmp_name"];
			$files = $_FILES["aadhar_img_back"]["name"];
			// $fileExtension = pathinfo($files, PATHINFO_EXTENSION); // Get the file extension
			$file_path = self::KYC_AADHAR_PATH . "" . $id;
			if (!is_dir($file_path)) {
				mkdir($file_path, 0755, TRUE); // BUG-H12: 0777→0755
			}
			$kycpath = self::KYC_AADHAR_PATH . "" . $id . "/aadhar_back.png";
			$content = file_get_contents($fileName);
			file_put_contents($kycpath, $content);
			$kycData['aadhar_img_back_url'] = isset($kycpath) ? base_url() . "" . $kycpath : NULL;
			//	print_r($kycData);exit;
		}
		return $kycData;
	}
	function getkycdata_byid()
	{
		$cus_id = $_POST['cus_id'];
		$model = self::CUS_MODEL;
		$data = $this->$model->get_kyc_byid($cus_id);
		echo json_encode($data);
	}
	//Zone Master
	public function zone($type = "", $id = "", $status = "")
	{
		$model = self::CUS_MODEL;
		$set_model   = self::SET_MODEL;
		switch ($type) {
			case "add":
				$insertData = array(
					'id_branch' => $this->input->post('id_branch'),
					'name' 	   => strtoupper($this->input->post('name')),
					'date_add' => date('y-m-d H:i:s'),
					'created_by' => $this->session->userdata('uid'),
				);
				$this->db->trans_begin();
				$this->$model->insertData($insertData, 'village_zone');
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Zone Added Successfully.', 'class' => 'success', 'title' => 'Zone'));
					$result = array('status' => true, 'message' => 'Zone added successfully!..', 'class' => 'success', 'title' => 'Zone: ');
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to Proceed.', 'class' => 'danger', 'title' => 'Zone'));
					$result = array('status' => false, 'message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Zone : ');
				}
				echo json_encode($result);
				//redirect('admin_ret_catalog/metal_type/list');	
				break;
			case "edit":
				$data = $this->$model->getVillageZone($id);
				echo json_encode($data);
				break;
			case 'delete':
				$this->db->trans_begin();
				$this->$model->deleteData('id_zone', $id, 'village_zone');
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Zone deleted successfully', 'class' => 'success', 'title' => 'Delete Zone'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Delete Zone'));
				}
				redirect('customer/zone/list');
				break;
			case "update":
				$updData = array(
					'id_branch' => $this->input->post('ed_id_branch'),
					'name' 	   => strtoupper($this->input->post('ed_name')),
					'date_upd' => date('y-m-d H:i:s'),
					'updated_by' => $this->session->userdata('uid'),
				);
				$this->db->trans_begin();
				$this->$model->updateData($updData, 'id_zone', $this->input->post('id_zone'), 'village_zone');
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Rate successfully', 'class' => 'success', 'title' => 'Zone'));
					$result = array('status' => true, 'message' => 'Zone Updated successfully!..', 'class' => 'success', 'title' => 'Zone: ');
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Zone'));
					$result = array('status' => false, 'message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Zone : ');
				}
				echo json_encode($result);
				break;
			case 'list':
				$data['main_content'] = "master/village/zone/list";
				$data['access']= $this->$set_model->get_access('customer/zone/list');
				$this->load->view('layout/template', $data);
				break;
			default:
				$list = $this->$model->get_ajaxzone_list();
				$access = $this->$set_model->get_access('customer/zone/list');
				$data = array(
					'list' => $list,
					'access' => $access
				);
				echo json_encode($data);
		}
	}
	//Zone Master

	function ajax_getAllActiveEmployee(){
	    $model_name=self::CUS_MODEL;
		$employee_select=$this->$model_name->getAllActiveEmployee();
		echo json_encode($employee_select);
	}

	function allocate_employee_toCuctomers(){
	    $model=self::CUS_MODEL;
     	$cus=$this->input->post('id_customer');
     	$id_employee = $this->input->post('id_employee');
     	$total= count($cus);
     	$total=array();
     	

     	if($total>0)
     	{
     		$this->load->model($model); 
     		
     		$i = 0;
    		foreach($cus as $cusid)
    		{
    			$upd_data = array('allocated_employee' => $id_employee,
    			                  'allocated_on' => date('Y-m-d H:i:s'));
            	$cusData=$this->$model->allocate_employee($upd_data,$cusid);
            	
            	if($this->db->trans_status()===TRUE){
            	    $i++;
            	}else{
            	    $not_allocated[] = $cusid;
            	}
    		}
    		
    		$result = $i;
    	
    	}     
	
    	if(count($cus) == $i){
    	    echo json_encode(array('status' => 1, 'total' =>count($cus))); 
    	}elseif($i == 0){
    	    echo json_encode(array('status' => 0, 'total' => count($cus),'not_allocated' =>$not_allocated)); 
    	}else{
    	   echo json_encode(array('status' => 2, 'total' => count($cus),'not_allocated' =>$not_allocated));  
    	}
    	
         
	}


	function wallet_account_cus()
	{
		$model = self::CUS_MODEL;
		$customer = $this->$model->get_cus();
		if (sizeof($customer) > 0) {
			foreach ($customer as $cus) {
				$wallet = $this->wallet_account_create($cus['id_customer'], $cus['mobile']);
				echo '<br/>';
				echo 'Mobile- ' . $cus['mobile'] . '| Created sucessfully';
			}
		} else {
			echo '<br/>';
			echo 'Mobile- ' . $cus['mobile'] . '| Unable to proceed';
		}
	}

	public function ajax_get_kyc_list()
	{
		$from_date  = $this->input->post('from_date');
		$to_date    = $this->input->post('to_date');
		$status     = $this->input->post('status');
		$type       = $this->input->post('type');
		$list_type  = $this->input->post('list_type') ?: 1;
		$mobile     = $this->input->post('mobile');
		$id_customer = $this->input->post('id_customer') ?: 0;

		$model = self::CUS_MODEL;
		$data = $this->$model->get_kyc_report_list($from_date, $to_date, $status, $type, $list_type, $mobile, $id_customer);
		echo json_encode($data);
	}

	public function ajax_update_kyc_status()
	{
		$kyc_data = $this->input->post('kyc_data');
		$model = self::CUS_MODEL;
		if (is_array($kyc_data) && count($kyc_data) > 0) {
			$emp_id = $this->session->userdata('uid');
			foreach ($kyc_data as $kyc) {
				$id_kyc  = isset($kyc['id_kyc']) ? $kyc['id_kyc'] : 0;
				$status  = isset($kyc['status']) ? $kyc['status'] : 0;
				$cus     = isset($kyc['cus']) ? $kyc['cus'] : 0;
				if ($id_kyc > 0) {
					$update_data = array(
						'status'          => $status,
						'emp_verified_by' => $emp_id,
					);
					$this->db->where('id_kyc', $id_kyc)->update('customer_kyc', $update_data);
				}
			}
			echo json_encode(array('status' => 1, 'msg' => 'KYC status updated successfully'));
		} else {
			echo json_encode(array('status' => 0, 'msg' => 'No data provided'));
		}
	}

	private function save_dynamic_kyc($cus_id) {
		$kyc_dynamic = $this->input->post('kyc_dynamic');
		file_put_contents(FCPATH . 'assets/kyc_debug.log', "ENTERING save_dynamic_kyc [ID: $cus_id] - DATA: " . (is_array($kyc_dynamic) ? "count=".count($kyc_dynamic) : "empty") . "\n", FILE_APPEND);
		if ($kyc_dynamic && !empty($kyc_dynamic)) {
		    $this->load->model('kyc_model');
		    $this->kyc_model->save_customer_kyc($cus_id, $kyc_dynamic);
		} else {
		    file_put_contents(FCPATH . 'assets/kyc_debug.log', "SKIPPING save_dynamic_kyc - NO DATA\n", FILE_APPEND);
		}
	}
}

