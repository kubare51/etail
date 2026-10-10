<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

class Admin_ret_catalog extends CI_Controller

{
	const CAT_MODEL	= "ret_catalog_model";

	const SETT_MOD	= "admin_settings_model";

	const CATE_VIEW	= "master/category/";

	const SUBCATE_VIEW	= "master/sub_category/";

	const IMG_PATH  = 'assets/img/';

	const PROD_PATH  = 'assets/img/products/';

	const DESIGN_PATH  = 'assets/img/designs/';

	const MAS_VIEW 		= 'master/';

	function __construct()

	{

		parent::__construct();

		ini_set('date.timezone', 'Asia/Calcutta');

		$this->load->model(self::CAT_MODEL);

		$this->load->model(self::SETT_MOD);

		$this->load->model("sms_model");

		$this->load->model("log_model");



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



	/**
	 * Generic Duplicate Check Endpoint
	 * Used by Validation.checkDuplicate() in validation.js
	 * POST params: table, column, value, exclude_id (optional), id_column (optional)
	 */
	public function check_duplicate()
	{
		$table     = $this->input->post('table');
		$column    = $this->input->post('column');
		$value     = trim($this->input->post('value'));
		$excludeId = $this->input->post('exclude_id');
		$idColumn  = $this->input->post('id_column') ?: 'id';

		// Security: Only allow known tables
		$allowed_tables = array(
			'ret_purity', 'ret_color', 'ret_cut', 'ret_clarity', 'ret_shape',
			'metal', 'ret_old_metal_type', 'ret_stone', 'ret_material',
			'ret_uom', 'ret_screw_type', 'ret_hook_type',
			'ret_making_type', 'ret_theme', 'ret_tag_type_master', 'ret_taxmaster', 'ret_section',
			'ret_sub_design_master', 'ret_branch_floor', 'ret_branch_floor_counter',
			'ret_collection_master', 'ret_product_division', 'ret_karigar',
			'ret_category', 'ret_product', 'ret_product_master', 'ret_sub_product',
			'ret_weight', 'ret_charges', 'ret_qc_cancel_reason',
			'ret_old_metal_category', 'ret_repair_master',
			'ret_stock_issue_types', 'ret_attribute',
			'ret_bill_pay_device', 'ret_product_grouping', 'ret_delivery_type', 'ret_sale_delivery',
			'ret_account', 'ret_account_head', 'ret_crdr_ledger', 'ret_profession',
			'ret_ledger', 'ret_paymode', 'ret_bank', 'ret_wallet',
			'ret_wallet_category', 'ret_size', 'ret_design_master',
			'ret_taxgroupmaster'
		);

		if (empty($table) || empty($column) || $value === '' || !in_array($table, $allowed_tables)) {
			echo json_encode(array('exists' => false));
			return;
		}

		// Map: table → correct status column name (each table is different!)
		$status_columns = array(
			'ret_purity'           => 'status',
			'ret_color'            => 'status',
			'ret_cut'              => 'status',
			'ret_clarity'          => 'status',
			'ret_shape'            => 'status',
			'metal'                => 'metal_status',
			'ret_stone'            => 'stone_status',
			'ret_material'         => 'material_status',
			'ret_uom'              => 'uom_status',
			'ret_screw_type'       => 'screw_status',
			'ret_hook_type'        => 'hook_status',
			'ret_making_type'      => 'mak_status',
			'ret_theme'            => 'theme_status',
			'ret_tag_type_master'  => 'tag_status',
			'ret_taxmaster'        => 'tax_status',
			'ret_section'          => 'status',
			'ret_category'         => 'status',
			'ret_branch_floor'     => 'floor_status',
			'ret_branch_floor_counter' => 'counter_status',
			'ret_collection_master' => 'status',
			'ret_product_division' => 'status',
			// ret_sub_design_master: no status filter — DB UNIQUE key is status-agnostic
			'ret_qc_cancel_reason' => 'status',
			'ret_repair_master'    => 'status',
			'ret_stock_issue_types' => 'status',
			'ret_bill_pay_device'  => 'status',
			'ret_product_grouping' => 'status',
			'ret_old_metal_category' => 'is_active',
			'ret_profession'       => 'status',
			'ret_wallet'           => 'status',
			'ret_size'             => 'status',
			'ret_account_head'     => 'status',
			'ret_design_master'    => 'design_status',
			'ret_product_master'   => 'product_status',
			'ret_attribute'        => 'attr_status',
			'ret_taxgroupmaster'   => 'tgrp_status'
		);

		// Sanitize column name — only allow alphanumeric and underscore
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $column) || !preg_match('/^[a-zA-Z0-9_]+$/', $idColumn)) {
			echo json_encode(array('exists' => false));
			return;
		}

		// Temporarily disable db_debug to prevent 500 errors on bad columns
		$old_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;

		// Use raw WHERE to avoid CI3 backtick-wrapping LOWER()
		$this->db->where('LOWER(`' . $column . '`) = ' . $this->db->escape(strtolower($value)), NULL, FALSE);

		// Use the correct status column for this table
		if (isset($status_columns[$table])) {
			$status_col = $status_columns[$table];
			if ($status_col === 'is_active') {
				$this->db->where("($status_col = 1 OR $status_col IS NULL)", NULL, FALSE);
			} else {
				$this->db->where($status_col, 1);
			}
		}

		if (!empty($excludeId)) {
			$this->db->where($idColumn . ' !=', $excludeId);
		}

		$count = $this->db->count_all_results($table);

		// Restore db_debug
		$this->db->db_debug = $old_debug;

		// If query failed, count will be 0
		if ($count > 0) {
			echo json_encode(array(
				'exists' => true,
				'msg'    => '"' . $value . '" already exists. Please enter a different value.'
			));
		} else {
			echo json_encode(array('exists' => false));
		}
	}

	public function index()

	{

		//$this->cus_list();

	}



	public function ajax_active_subCtg()
	{

		$model = self::CAT_MODEL;

		$subctg = $this->$model->getActiveSubctg();

		$data = array(

			'sub_category' => $subctg

		);

		echo json_encode($data);
	}



	public function active_color()
	{

		$model = self::CAT_MODEL;

		$color = $this->$model->getActivecolor();

		$data = array(

			'color' => $color

		);

		echo json_encode($data);
	}





	public function active_metals()
	{

		$model = self::CAT_MODEL;

		$data = $this->$model->getActiveMetals();

		echo json_encode($data);
	}



	public function active_masters()
	{

		$model = self::CAT_MODEL;

		$cut = $this->$model->getActiveCut();

		$clarity = $this->$model->getActiveClarity();

		//$carat = $this->$model->getActiveCarat();

		$data = array(

			'cut'	 => $cut,

			'clarity' => $clarity,

			'carat'  => $carat

		);

		echo json_encode($data);
	}

	/*public function active_carat(){

		$model = self::CAT_MODEL;

		$data = $this->$model->getActiveCarat();

	echo json_encode($data);

	}*/

	public function active_clarity()
	{

		$model = self::CAT_MODEL;

		$data = $this->$model->getActiveClarity();

		echo json_encode($data);
	}

	public function active_cut()
	{

		$model = self::CAT_MODEL;

		$data = $this->$model->getActiveCut();

		echo json_encode($data);
	}



	public function metal_info_list($prod_id)
	{

		$model = self::CAT_MODEL;

		$prod_mInfo = $this->$model->getmetalInfo($prod_id);

		$prod_dInfo = $this->$model->getdiamondInfo($prod_id);

		$cut = $this->$model->getActiveCut();

		$clarity = $this->$model->getActiveClarity();

		//		$carat = $this->$model->getActiveCarat();

		$color = $this->$model->getActivecolor();

		$purity = $this->$model->getActivePurity();

		$data = array(

			'prod_mInfo'	 => $prod_mInfo,

			'prod_dInfo'	 => $prod_dInfo,

			'cut'			 => $cut,

			'clarity'		 => $clarity,

			//    					'carat'  		 => $carat,

			'color'  		 => $color,

			'purity'  		 => $purity

		);

		echo json_encode($data);
	}





	public function sub_product($type = "", $id = "")
	{

		$model = self::CAT_MODEL;

		switch ($type) {

			case 'active_subprodBySearch':

				$data = $this->$model->getActiveSearchSubProd($_POST['searchTxt'], $_POST['prodId']);

				echo json_encode($data);

				break;

			case 'active_subprods':

				$data = $this->$model->getActiveSubProducts();

				echo json_encode($data);

				break;
		}
	}



	public function product($type = "", $id = "")
	{

		$model = self::CAT_MODEL;

		switch ($type) {

			case 'active_prods':

				$data = $this->$model->getActiveProducts();

				echo json_encode($data);

				break;

			case 'active_prodBySearch':

				$data = $this->$model->getActiveSearchProd($_POST['searchTxt']);

				//echo $this->db->last_query();

				echo json_encode($data);

				break;
		}
	}



	public function purity($type = "", $id = "", $status = "")
	{
		// I-GMSJ-005: Category's Purity multi-select (get_purities()/get_ActivePurity()
		// in catalog_master.js) calls this action expecting a flat array of
		// {id_purity, purity} rows. Migrating purity() to master_crud() dropped this
		// case, so it fell through to master_crud()'s default 'list' handler, which
		// returns {responseData, access} instead — the JS's $.each() then iterated
		// those 2 wrapper keys and appended two blank <option> elements.
		if ($type === 'active_purities') {
			$model = self::CAT_MODEL;
			echo json_encode($this->$model->getActivePurity());
			return;
		}
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'purity', $type, $id, $status);
	}



	function ajax_getPurity()

	{

		$model = self::CAT_MODEL;

		$purity = $this->$model->ajax_getPurity();

		echo json_encode($purity);
	}



	function purity_status($status, $id)

	{

		$data = array('status' => $status);

		$model = self::CAT_MODEL;

		$this->db->trans_begin();
		$updstatus = $this->$model->update_purity($data, $id);

		if ($this->db->trans_status() === TRUE) {
			$this->db->trans_commit();
			$this->session->set_flashdata('chit_alert', array('message' => 'Purity status updated as ' . ($status == 1 ? 'Active' : 'In Active') . ' successfully.', 'class' => 'success', 'title' => 'Purity Status'));
		} else {
			$this->db->trans_rollback();
			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Purity  Status'));
		}

		redirect('purity/list');
	}



	/*public function carat($type="",$id=""){

		$model=self::CAT_MODEL;

		switch($type)

		{

			case "Add":

	 				$carat= $this->input->post("carat");

	 				$description= $this->input->post("description");

	 				$data=array('carat'=>$carat , 'description'=>$description , 'id_employee'=>$this->session->userdata('uid'));

	 				$this->db->trans_begin();

	 				$this->$model->insert_carat($data);

	 				print_r($data);

	 			if($this->db->trans_status()===TRUE)

	             {

				 	$this->db->trans_commit();

				 	$this->session->set_flashdata('chit_alert',array('message'=>'New carat added successfully','class'=>'success','title'=>'Add carat'));



				 }

				 else

				 {

				 	 $this->db->trans_rollback();

				 	$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Add carat'));



				 }

				redirect('carat/list');



	 		break;

	 	case "Edit":

	 			$data['carat'] = $this->$model->get_carat($id);

	 			echo json_encode($data['carat']);

	 		break;



	 	case 'Delete':

						 $this->db->trans_begin();

						 $this->$model->delete_carat($id);

				           if( $this->db->trans_status()===TRUE)

						    {

						    	  $this->db->trans_commit();

								  $this->session->set_flashdata('chit_alert', array('message' => 'carat deleted successfully','class' => 'success','title'=>'Delete carat'));

							}

						   else

						   {

							 $this->db->trans_rollback();

							 $this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request','class' => 'danger','title'=>'Delete carat'));

						   }

						 redirect('carat/list');

				break;



	 	case "Update":

	 			$data['carat']=$this->$model->get_carat($id);

	 			$carat=$this->input->post('carat');

	 			$description=$this->input->post('description');

	 			$data=array("carat"=>$carat , "description"=>$description, 'id_employee'=>$this->session->userdata('uid'));



	 			 $this->db->trans_begin();



			          $carat_id = $this->$model->update_carat($data,$id);



			            if($this->db->trans_status()===TRUE)

			             {

						 	$this->db->trans_commit();



						 	$this->session->set_flashdata('chit_alert',array('message'=>'carat record modified successfully','class'=>'success','title'=>'Edit carat'));

						 	redirect('carat/list');

						 }

						 else

						 {

						 	 $this->db->trans_rollback();

						 	$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Edit carat'));

						 	redirect('carat/list');

						 }

	 		break;



			case 'List':



						$data['main_content'] = "master/carat/list" ;

						$this->load->view('layout/template', $data);

						break;



			default:

						$SETT_MOD = self::SETT_MOD;

					  	$carat = $this->$model->ajax_getcarat();

					  	$access = $this->$SETT_MOD->get_access('carat/list');

				        $data = array(

				        					'carat' =>$carat,

											'access'=>$access

				        				);

						echo json_encode($data);

		}

	}



	function carat_status($status,$id)

	{

		$data = array('status' => $status);

		$model=self::CAT_MODEL;

		$status = $this->$model->update_carat($data,$id);

		if($status)

		{

			$this->session->set_flashdata('chit_alert',array('message'=>'carat status updated as '.($status ? 'active' : 'inactive').' successfully.','class'=>'success','title'=>'carat  Status'));

		}

		else

		{

			$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'carat  Status'));

		}

		redirect('carat/list');

	}*/



	public function color($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'color', $type, $id, $status);
	}



	function color_status($status, $id)

	{

		$data = array('status' => $status);

		$model = self::CAT_MODEL;

		$this->db->trans_begin();
		$updstatus = $this->$model->update_color($data, $id);

		if ($this->db->trans_status() === TRUE) {
			$this->db->trans_commit();
			$this->session->set_flashdata('chit_alert', array('message' => 'color status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'color  Status'));
		} else {
			$this->db->trans_rollback();
			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'color  Status'));
		}

		redirect('color/list');
	}

	public function cut($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'cut', $type, $id, $status);
	}



	function cut_status($status, $id)

	{

		$data = array('status' => $status);

		$model = self::CAT_MODEL;

		$this->db->trans_begin();
		$updstatus = $this->$model->update_cut($data, $id);

		if ($this->db->trans_status() === TRUE) {
			$this->db->trans_commit();
			$this->session->set_flashdata('chit_alert', array('message' => 'cut status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'cut  Status'));
		} else {
			$this->db->trans_rollback();
			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'cut  Status'));
		}

		redirect('cut/list');
	}



	public function clarity($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'clarity', $type, $id, $status);
	}



	function clarity_status($status, $id)

	{

		$data = array('status' => $status);

		$model = self::CAT_MODEL;

		$this->db->trans_begin();
		$updstatus = $this->$model->update_clarity($data, $id);

		if ($this->db->trans_status() === TRUE) {
			$this->db->trans_commit();
			$this->session->set_flashdata('chit_alert', array('message' => 'clarity status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'clarity  Status'));
		} else {
			$this->db->trans_rollback();
			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'clarity  Status'));
		}

		redirect('clarity/list');
	}





	function set_image($id, $img_path, $file)

	{

		if ($_FILES[$file]['name']) {

			$img = $_FILES[$file]['tmp_name'];

			$status = $this->upload_img($file, $img_path, $img);

			return $status;
		}
	}



	function set_image_ret($id)

	{

		//$data=array();

		//print_r($data);exit;

		$model = self::CAT_MODEL;

		if ($_FILES['product']['name']['ret_product_img']) {

			$path = 'assets/img/ret_product/';



			if (!is_dir($path)) {



				mkdir($path, 0777, TRUE);
			} else {

				$file = $path . $id['ID'] . ".jpg";

				chmod($path, 0777);

				unlink($file);
			}



			$img = $_FILES['product']['tmp_name']['ret_product_img'];



			$filename = mt_rand(100001, 999999) . ".jpg";







			$imgpath = 'assets/img/ret_product/' . $filename;







			$upload = $this->upload_img('image', $imgpath, $img);







			$data['image'] = base_url() . $imgpath;

			//print_r($data['image']);exit;

			//$this->$model->updateData("update",$id['ID'],$data);

			$this->db->trans_begin();
			$status = $this->$model->updateData($data, 'pro_id', $id, 'ret_product_master');

			if ($this->db->trans_status() === TRUE) {
				$this->db->trans_commit();
			} else {
				$this->db->trans_rollback();
			}
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

		return true;
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



	function remove_img($file, $id)
	{

		$path = self::PROD_PATH . $id . "/" . $file;

		chmod(self::PROD_PATH . $id, 0777);

		unlink($path);

		$model = self::CAT_MODEL;

		$status = $this->$model->delete_prodimage($file);

		if ($status) {

			echo "Picture removed successfully";
		}
	}



	public function uom($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'uom', $type, $id, $status);
	}



	public function branch_floor($type = "", $id = "", $status = "")
	{
		// active_floors is consumed by catalog_master.js (Counter add/edit form's
		// branch->floor cascading dropdown) outside this master's own page —
		// preserved as-is, everything else routes through the generic engine.
		if ($type === 'active_floors') {
			$model = self::CAT_MODEL;
			echo json_encode($this->$model->getActiveFloors());
			return;
		}
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'branch_floor', $type, $id, $status);
	}



	public function floor_counter($type = "", $id = "", $status = "")
	{
		// active_counters has no other caller found in the codebase, preserved
		// defensively anyway — everything else routes through the generic engine.
		if ($type === 'active_counters') {
			$model = self::CAT_MODEL;
			echo json_encode($this->$model->getActiveCounters());
			return;
		}
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'floor_counter', $type, $id, $status);
	}



	public function making_type($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'making_type', $type, $id, $status);
	}



	public function theme($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'theme', $type, $id, $status);
	}



	public function material($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'material', $type, $id, $status);
	}



	public function stone($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'stone', $type, $id, $status);
	}



	function get_catByMetal($id_cat)

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_catByMetal($id_cat);

		echo json_encode($data);
	}

	public function category($type = "", $id = "", $status = "")
	{

		$model = self::CAT_MODEL;

		$SETT_MOD 	= self::SETT_MOD;

		// I-GMSJ-005: the delete/update_status cases below call lmx_can(), but
		// this helper was never loaded anywhere in category()'s request path
		// (not autoloaded, not loaded in the constructor) — every deactivate/
		// reactivate click fatal-errored with "Call to undefined function
		// lmx_can()" before ever reaching the actual status update, silently
		// (production mode hides the error) leaving the category untouched.
		$this->load->helper('lmx/core/access_helper');

		switch ($type) {

			case "add":

				// SEC: Validate category name, cat_code, hsn_code
				$this->load->helper('customer_validation');
				$validation = validate_category_data($this, 'add');
				if (!$validation['valid']) {
					echo json_encode(array('status' => false, 'message' => implode(' ', $validation['errors']), 'class' => 'danger', 'title' => 'Add Category'));
					return;
				}

				// I-GMSJ-005: Purity was never actually required server-side — the
				// Add form's client-side check already required it, but nothing
				// stopped a direct/API submission with none selected, and Update
				// had no check at all (client or server).
				if (trim($this->input->post('id_purity')) === '') {
					echo json_encode(array('status' => false, 'message' => 'Purity is required', 'class' => 'danger', 'title' => 'Add Category'));
					return;
				}

				// Known gap fix: id_metal/tgrp_id/id_purity are all chosen from
				// active-list dropdowns but were never re-checked for staleness
				// before save (this branch's validate_category_data() only checks
				// name/code format, not FK staleness — same class of gap already
				// fixed on other masters this session).
				$post_id_metal = $this->input->post('id_metal');
				if (!empty($post_id_metal) && !$this->$model->check_metal_active($post_id_metal)) {
					echo json_encode(array('status' => false, 'message' => 'Selected Metal is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Add Category'));
					return;
				}
				$post_tgrp_id = $this->input->post('tgrp_id');
				if (!empty($post_tgrp_id) && !$this->$model->check_tax_group_active($post_tgrp_id)) {
					echo json_encode(array('status' => false, 'message' => 'Selected Tax Group is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Add Category'));
					return;
				}
				foreach (explode(',', $this->input->post('id_purity')) as $post_purity_id) {
					if (!$this->$model->check_purity_active($post_purity_id)) {
						echo json_encode(array('status' => false, 'message' => 'One or more selected Purities are no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Add Category'));
						return;
					}
				}

				$purity1      = explode(',', $this->input->post('id_purity'));

				$data = array(

					'name'	        => strtoupper($this->input->post("name")),

					'cat_code'      => sanitize_by_rule($this->input->post("cat_code"), 'codeField'),

					'hsn_code'      => sanitize_by_rule($this->input->post("hsn_code"), 'hsnCode'), // Note: HSN code duplicates are intentionally allowed

					'description'   => ($this->input->post("description") != '' ? $this->input->post("description") : NULL),

					'id_metal'      => $this->input->post("id_metal"),

					'cat_type' 		=> $this->input->post("cat_type"),

					'tgrp_id'       => $this->input->post("tgrp_id"),

					'is_multimetal' => $this->input->post("is_multimetal"),

					'image'         => ($this->input->post("image") != '') ? $this->input->post("image") : NULL,

					'created_on'	=> date("Y-m-d H:i:s"),

					'created_by'    => $this->session->userdata('uid')

				);

				$this->db->trans_begin();

				$result = $this->$model->insertData($data, 'ret_category');

				//print_r($this->db->last_query);exit;

				if (isset($_FILES['file']['name'])) {

					$path = 'assets/img/ret_category/';

					$file_name = $_FILES['file']['name'];

					if (!is_dir($path)) {

						mkdir($path, 0777, TRUE);
					} else {

						$file = $path . $file_name;

						chmod($path, 0777);

						// unlink($file);

					}

					$img = $_FILES['file']['tmp_name'];

					$filename = $result . '_CAT_' . mt_rand(120, 1230) . ".jpg";

					$imgpath  = 'assets/img/ret_category/' . $filename;

					$upload   = $this->upload_img('image', $imgpath, $img);

					$data['image'] = $filename;

					$this->$model->updateData($data, 'id_ret_category ', $result, 'ret_category');
				}



				if ($result > 0) {



					foreach ($purity1 as $k) {



						$data['purity'] = array(

							'id_category' => $result,

							'id_purity'			=> $k,

							'created_on'		=> date("Y-m-d H:i:s"),

							'created_by'    	=> $this->session->userdata('uid')

						);

						$this->$model->insertData($data['purity'], 'ret_metal_cat_purity');
					}
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Category',

						'operation'     => 'Add',

						'record'        =>  $result,

						'remark'        => 'Category added successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$result = array('message' => 'New Category added successfully!..', 'class' => 'success', 'title' => 'Add Category : ');
				} else {

					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Add Category : ');

					//echo"<pre>";print_r($this->db->last_query());

				}

				echo json_encode($result);

				break;

			case "edit":

				$data['categorymtr'] = $this->$model->get_ret_category($id);

				$purity = $this->$model->get_category_purity($id);

				$data['purity']   = array('id_purity'  => $purity);

				$data['categorymtr']['purity'] = $purity;

				echo json_encode($data['categorymtr']);

				break;

			case 'delete':

				if (!lmx_can($this, 'admin_ret_catalog/category/list', 'delete')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Delete category'));
					redirect('admin_ret_catalog/category/list');
					return;
				}

				$isCategoryExists = $this->$model->getItemsinTagDetails('', $id, '', '', '', '');

				$this->db->trans_begin();

				if ($isCategoryExists == 0) {
					// Masters never hard-delete — deactivate instead, same as
					// every other master.
					$this->$model->updateData(
						array(
							'status'     => 0,
							'updated_on' => date("Y-m-d H:i:s"),
							'updated_by' => $this->session->userdata('uid')
						),
						'id_ret_category ',
						$id,
						'ret_category'
					);

					if ($this->db->trans_status() === TRUE) {

						$this->db->trans_commit();

						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'Category',

							'operation'     => 'Deactivate',

							'record'        =>  $id,

							'remark'        => 'Category deactivated successfully'

						);

						$this->log_model->log_detail('insert', '', $log_data);

						$this->session->set_flashdata('chit_alert', array('message' => 'category deactivated successfully', 'class' => 'success', 'title' => 'Deactivate category'));

						echo 1;
					} else {

						$this->db->trans_rollback();

						$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Deactivate category'));

						echo 0;
					}
				} else {
					$this->session->set_flashdata('chit_alert', array('message' => 'Categroy Exists in Stock..', 'class' => 'danger', 'title' => 'Deactivate category'));
				}



				redirect('admin_ret_catalog/category/list');

				break;



			case "update":

				// SEC: Validate category name, cat_code, hsn_code
				$this->load->helper('customer_validation');
				$validation = validate_category_data($this, 'edit', $id);
				if (!$validation['valid']) {
					echo json_encode(array('status' => false, 'message' => implode(' ', $validation['errors']), 'class' => 'danger', 'title' => 'Edit Category'));
					return;
				}

				// I-GMSJ-005: Purity required on Update too — see Add above.
				if (trim($this->input->post('id_purity')) === '') {
					echo json_encode(array('status' => false, 'message' => 'Purity is required', 'class' => 'danger', 'title' => 'Edit Category'));
					return;
				}

				// Known gap fix: same staleness checks as Add above.
				$post_id_metal = $this->input->post('id_metal');
				if (!empty($post_id_metal) && !$this->$model->check_metal_active($post_id_metal)) {
					echo json_encode(array('status' => false, 'message' => 'Selected Metal is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Edit Category'));
					return;
				}
				$post_tgrp_id = $this->input->post('tgrp_id');
				if (!empty($post_tgrp_id) && !$this->$model->check_tax_group_active($post_tgrp_id)) {
					echo json_encode(array('status' => false, 'message' => 'Selected Tax Group is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Edit Category'));
					return;
				}
				foreach (explode(',', $this->input->post('id_purity')) as $post_purity_id) {
					if (!$this->$model->check_purity_active($post_purity_id)) {
						echo json_encode(array('status' => false, 'message' => 'One or more selected Purities are no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Edit Category'));
						return;
					}
				}

				$data = array(

					'name'	        => strtoupper($this->input->post("name")),

					'description'   => ($this->input->post("description") != '' ? $this->input->post("description") : NULL),

					'id_metal'      => $this->input->post("id_metal"),

					'is_multimetal' => $this->input->post("is_multimetal"),

					'cat_code'      => sanitize_by_rule($this->input->post("cat_code"), 'codeField'),

					'cat_type' 		=> $this->input->post("cat_type"),

					'tgrp_id'       => $this->input->post("tgrp_id"),

					'hsn_code'      => sanitize_by_rule($this->input->post("hsn_code"), 'hsnCode'), // Note: HSN code duplicates are intentionally allowed

					'updated_on'	=> date("Y-m-d H:i:s"),

					'updated_by'    => $this->session->userdata('uid')

				);

				$this->db->trans_begin();

				if ($_FILES['file']['name']) {

					$path = 'assets/img/ret_category/';

					$file_name = $_FILES['file']['name'];

					if (!is_dir($path)) {

						mkdir($path, 0777, TRUE);
					} else {

						$file = $path . $file_name;

						chmod($path, 0777);

						// unlink($file);

					}

					$img = $_FILES['file']['tmp_name'];

					$filename = $id . '_CAT_' . mt_rand(120, 1230) . ".jpg";

					$imgpath  = 'assets/img/ret_category/' . $filename;

					$upload   = $this->upload_img('image', $imgpath, $img);

					$data['image'] = $filename;
				}

				$id = $this->$model->updateData($data, 'id_ret_category ', $id, 'ret_category');

				$this->$model->deleteData('id_category ', $id, 'ret_metal_cat_purity');

				$purity1  = explode(',', $this->input->post('id_purity'));

				if ($id > 0) {

					foreach ($purity1 as $k) {

						$data['purity'] = array(

							'id_category' 	    => $id,

							'id_purity'			=> $k,

							'created_on'		=> date("Y-m-d H:i:s"),

							'created_by'    	=> $this->session->userdata('uid')

						);

						$this->$model->insertData($data['purity'], 'ret_metal_cat_purity');
					}
				}



				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Category',

						'operation'     => 'Update',

						'record'        => $id,

						'remark'        => 'Category Updated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);



					$this->session->set_flashdata('chit_alert', array('message' => 'Category record modified successfully', 'class' => 'success', 'title' => 'Edit Category'));

					echo 1;
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Edit Category'));

					echo 0;
				}



				break;

			case 'list':

				$data['main_content'] = "master/ret_category/list";

				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/category/list');

				$this->load->view('layout/template', $data);

				break;

			case 'update_status':

				if (!lmx_can($this, 'admin_ret_catalog/category/list', 'edit')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Category  Status'));
					redirect('admin_ret_catalog/category/list');
					return;
				}

				// Same dependency gate as Delete — deactivating a category
				// still in stock is exactly as unsafe as deleting it.
				if ($status == 0) {
					$isCategoryExists = $this->$model->getItemsinTagDetails('', $id, '', '', '', '');
					if ($isCategoryExists != 0) {
						$this->session->set_flashdata('chit_alert', array('message' => 'Category Exists in Stock..', 'class' => 'danger', 'title' => 'Category  Status'));
						redirect('admin_ret_catalog/category/list');
						return;
					}
				}

				$data = array('status' => $status, 'updated_on' => date("Y-m-d H:i:s"), 'updated_by' => $this->session->userdata('uid'));

				$this->db->trans_begin();
				$updstatus = $this->$model->updateData($data, 'id_ret_category', $id, 'ret_category');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Category status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'Category  Status'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Category  Status'));
				}

				redirect('admin_ret_catalog/category/list');

				break;

			case 'active_category':

				$data = $this->$model->getActiveCategorymtr($_POST);

				echo json_encode($data);

				break;

			case 'cat_purity':

				$data = $this->$model->getCatPurity($_POST['id_category']);

				echo json_encode($data);

				break;

			case 'hsn_code':

				$data = $this->$model->getHsnCode($_POST);

				echo json_encode($data);

				break;

			default:

				$SETT_MOD 	= self::SETT_MOD;

				$range['from_date']	= $this->input->post('from_date');

				$range['to_date']	= $this->input->post('to_date');

				$categorymtr 		= $this->$model->ajax_getcategory($range['from_date'], $range['to_date']);

				$access 	= $this->$SETT_MOD->get_access('admin_ret_catalog/category/list');

				$data 		= array(

					'categorymtr' => $categorymtr,

					'access'   => $access

				);

				echo json_encode($data);
		}
	}



	function get_category($id)

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_catByMetal($id);

		echo json_encode($data);
	}



	public function karigar($type = "", $id = "", $status = "")

	{

		$model = self::CAT_MODEL;

		$set_model = self::SETT_MOD;

		// I-GMSJ-005: the save/update cases below call lmx_can(), which needs
		// this helper loaded — same class of gap as category()/ret_product().
		$this->load->helper('lmx/core/access_helper');

		switch ($type) {

			case 'add':

				$data['financial_year'] = $this->$model->GetFinancialYear();

				$data['main_content'] = "master/karigar/form";

				$this->load->view('layout/template', $data);

				break;

			case 'print':

				$data['comp_details'] = $this->$model->getCompanyDetails();

				$data['karigar'] = $this->$model->get_print_karigar($id);

				$data['wastage'] = $this->$model->get_karigar_wastage_details($id);

				$data['stone'] = $this->$model->get_karigar_gem_stones($id);

				$data['bank'] = $this->$model->get_bank_details($id);

				$data['kyc']  = $this->$model->get_kyc_details($id);



				// echo"<pre>"; print_r($data);exit;

				$html = $this->load->view('master/karigar/karigar_print', $data, true);

				echo $html;
				exit;

				break;

			case 'product_details':

				$data['categories'] = $this->$model->getActiveCategorymtr(array("id_metal" => ''));

				$data['purities'] = $this->$model->getCatPurity('');

				$data['uom'] = $this->$model->getActiveUOM();

				$data['quality_code'] = $this->$model->get_quality_code();

				$karigar_wastages = $this->$model->get_karigar_wastages($id);

				foreach ($karigar_wastages as $val) {

					$val['karigar_charges'] = $this->$model->get_karigar_charges($val['id_karikar_wast']);

					$val['karigar_wastage_images'] = $this->$model->get_karigar_images($val['id_karikar_wast']);

					$data['karigar_wastages'][] = $val;
				}

				// Use same method as ret_product/active_list endpoint — returns all columns including cat_id, stone_type
				$_POST['id_category'] = ''; // getActiveProduct reads from $_POST
				$data['products'] = $this->$model->getActiveProduct();

				$data['designs'] = $this->$model->get_active_design_products(array("id_product" => ''));

				$data['sub_designs'] = $this->$model->get_ActiveSubDesigns(array("id_product" => '', "design_no" => ''));

				$data['purities'] = $this->$model->getCatPurity('');


				//echo"<pre>"; print_r($data['karigar_wastages']);exit;

				echo json_encode($data);

				break;



			case 'kyc_details':

				$data['karigar_kyc_det'] = $this->$model->get_karigar_kyc_det($id);

				$data['kyc'] = $this->$model->get_ActiveKYC();

				echo json_encode($data);

				break;



			case 'stone_details':

				$data['karigar_stones'] = $this->$model->get_karigar_stones($id);

				$data['stone_type'] = $this->$model->getActiveStoneTypes();

				$data['uom'] = $this->$model->getActiveUOM();

				$data['stones']  = $this->$model->getActiveStone();

				$data['quality_code'] = $this->$model->get_quality_code();

				//echo"<pre>"; print_r($data);exit;

				echo json_encode($data);

				break;



			case 'edit':

				$data['karigar'] = $this->$model->get_karigar($id);

				$data['financial_year'] = $this->$model->GetFinancialYear();

				$karigar_wastages = $this->$model->get_karigar_wastages($id);

				$data['bank'] = $this->$model->get_bank_details($id);

				foreach ($karigar_wastages as $val) {

					$val['karigar_charges'] = $this->$model->get_karigar_charges($val['id_karikar_wast']);

					$val['karigar_wastage_images'] = $this->$model->get_karigar_images($val['id_karikar_wast']);

					$data['karigar_wastages'][] = $val;
				}

				$data['karigar_stones'] = $this->$model->get_karigar_stones($id);

				$prod_args = array("id_category" => '');

				$data['wastages']['product'] = $this->$model->getActiveProduct($prod_args);

				$data['stones']['stone_type'] = $this->$model->getActiveStoneTypes();

				$data['stones']['uom'] = $this->$model->getActiveUOM();

				$data['charge']['charge_name'] = $this->$model->getActiveCharges();

				foreach ($data['karigar_stones'] as $key => $stones) {

					$stn_type = array(

						"stone_type" => $stones['stone_type']



					);

					$data['karigar_stones'][$key]['stones']  = $this->$model->get_ActiveStones($stn_type);
				}

				if ($data['karigar']['id_country'] != '') {

					$data['country'] = json_decode($this->$set_model->get_country(), TRUE);

					$data['state'] 	 = json_decode($this->$set_model->get_state($data['karigar']['id_country']), TRUE);

					$data['city'] 	 = json_decode($this->$set_model->get_city($data['karigar']['id_state']), TRUE);
				}



				//echo"<pre>"; print_r($data);exit;

				$data['main_content'] = "master/karigar/form";

				$this->load->view('layout/template', $data);

				break;

			case "save":

				if (!lmx_can($this, 'admin_ret_catalog/karigar/list', 'add')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Add User'));
					redirect('admin_ret_catalog/karigar/list');
					return;
				}

				$addData = $_POST['karigar'];

				// I-GMSJ-005: karigar had no required-field or duplicate check at
				// all — an empty name, or a username/GST/PAN collision with an
				// existing karigar, all saved silently or (for GST/PAN, which have
				// real DB UNIQUE constraints) hit the constraint raw and surfaced a
				// "Database Error Occurred" page.
				if (!isset($addData['first_name']) || trim($addData['first_name']) === '') {
					$this->session->set_flashdata('chit_alert', array('message' => 'First Name is required', 'class' => 'danger', 'title' => 'Add User'));
					redirect('admin_ret_catalog/karigar/list');
					return;
				}
				if (!empty($addData['user_name']) && $this->$model->check_karigar_username_exists($addData['user_name'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Username "' . $addData['user_name'] . '" already exists', 'class' => 'danger', 'title' => 'Add User'));
					redirect('admin_ret_catalog/karigar/list');
					return;
				}
				if (!empty($addData['gst_number']) && $this->$model->check_karigar_gst_exists($addData['gst_number'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'GST Number "' . $addData['gst_number'] . '" already exists', 'class' => 'danger', 'title' => 'Add User'));
					redirect('admin_ret_catalog/karigar/list');
					return;
				}
				if (!empty($addData['pan_number']) && $this->$model->check_karigar_pan_exists($addData['pan_number'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'PAN Number "' . $addData['pan_number'] . '" already exists', 'class' => 'danger', 'title' => 'Add User'));
					redirect('admin_ret_catalog/karigar/list');
					return;
				}

				$product_details = $_POST['o_item'];

				$kyc_details = $_POST['kyc_det'];

				$stone_details = $_POST['stn'];

				$bank_details = $_POST['bank'];



				//echo "<pre>";print_r($stone_details);exit;



				$data = array(

					'firstname'	           => strtoupper($addData['first_name']),

					'lastname'             => $addData['last_name_karigar'],

					//'code_karigar'         => $addData['karigar_code'],

					'fin_year_code'         => $addData['fin_year_code'],

					'opening_balance_amount' => $addData['opening_balance_amount'],

					'karigar_type'         => $addData['user_type'],

					'is_tcs'               => $addData['is_tcs'],

					'is_tds'               => $addData['is_tds'],

					'tcs_tax'              => $addData['tcs_tax'],

					'tds_tax'              => $addData['tds_tax'],

					'karigar_for'          => $addData['karigar_for'],

					'address1'   		   => (!empty($addData['address1']) ? $addData['address1'] : NULL),

					'address2'   		   => (!empty($addData['address2']) ? $addData['address2'] : NULL),

					'address3'             => (!empty($addData['address3']) ? $addData['address3'] : NULL),

					'id_country'		   => (!empty($addData['country']) ? $addData['country'] : NULL),

					'id_state'		       => (!empty($addData['stateval']) ? $addData['stateval'] : NULL),

					'id_city'		       => (!empty($addData['cityval']) ? $addData['cityval'] : NULL),

					'pincode'		       => (!empty($addData['pincode']) ? $addData['pincode'] : NULL),

					'contactno2'		   => (!empty($addData['phone']) ? $addData['phone'] : NULL),

					'status_karigar'	   => 1,

					'email'		           => (!empty($addData['email']) ? $addData['email'] : NULL),

					'contactno1'		   => (!empty($addData['mobile']) ? $addData['mobile'] : NULL),

					'urname'		       => (!empty($addData['user_name']) ? $addData['user_name'] : NULL),

					'psword'		       => (!empty($addData['password']) ? $addData['password'] : NULL),

					'company'		       => (!empty($addData['company_karigar']) ? $addData['company_karigar'] : NULL),

					'gst_number'		   => (!empty($addData['gst_number']) ? $addData['gst_number'] : NULL),

					'ifsc_code'		       => (!empty($addData['ifsc_code']) ? $addData['ifsc_code'] : NULL),

					'acc_number'		   => (!empty($addData['acc_number']) ? $addData['acc_number'] : NULL),

					'pan_no'		   	   => (!empty($addData['pan_number']) ? $addData['pan_number'] : NULL),

					//'karigar_calc_type'	   =>(!empty($addData['karigar_calc_type']) ? $addData['karigar_calc_type']:NULL),

					'remarks'              => (!empty($addData['remarks']) ? $addData['remarks'] : NULL),

					'bank_name'            => (!empty($addData['bank_name']) ? $addData['bank_name'] : NULL),

					'acc_holder_name'      => (!empty($addData['acc_holder']) ? $addData['acc_holder'] : NULL),

					'createdon'            => date("Y-m-d H:i:s"),

					'createdby'            => $this->session->userdata('uid')

				);

				//echo"<pre>"; print_r($data);exit;

				$this->db->trans_begin();

				if ($_FILES['image']['name']) {

					$path = 'assets/img/karigar/';

					$file_name = $_FILES['image']['name'];

					if (!is_dir($path)) {

						mkdir($path, 0777, TRUE);
					} else {

						$file = $path . $file_name;

						chmod($path, 0777);

						// unlink($file);

					}

					$img = $_FILES['image']['tmp_name'];

					$filename = $id . '_' . mt_rand(120, 1230) . ".jpg";

					$imgpath  = 'assets/img/karigar/' . $filename;

					$upload   = $this->upload_img('image', $imgpath, $img);

					$data['image'] = $filename;

					$status = $this->$model->updateData($data, 'id_karigar', $id, 'ret_karigar');
				}

				$id_karigar = $this->$model->insertData($data, 'ret_karigar');

				//print_r($this->db->last_query());exit;

				if ($id_karigar != '') {

					$data = array(

						'code_karigar'         => $id_karigar,

					);

					$this->$model->updateData($data, 'id_karigar', $id_karigar, 'ret_karigar');
				}

				//echo "<pre>"; print_r($product_details);exit;

				foreach ($product_details as $val) {

					if ($val['is_item_select'] == 1) {

						$wast = array(



							'id_karikar'	=>	$id_karigar,

							'id_category'   =>  $val['category'],

							'id_purity'     =>  $val['purity'],

							'karigar_calc_type' => $val['kar_calc_type'],

							'id_product' 	=>	$val['product'],

							'id_design' 	=>	$val['design'],

							'id_sub_design' =>	$val['sub_design'],

							'wastage_type'  => ($val['va_type'] != '' ? $val['va_type'] : 1),

							'wastage_per' 	=> ($val['va_type'] == 1 ? ($val['wast_percent'] != '' ? $val['wast_percent'] : 0) : 0),

							'wastage_wt' 	=> ($val['va_type'] == 2 ? ($val['wast_wgt'] != '' ? $val['wast_wgt'] : 0) : 0),

							'mc_type'       =>  $val['id_mc_type'],

							'mc_value'      => ($val['mc'] != '' ? $val['mc'] : 0),

							'pur_touch'     => ($val['pur_touch'] != '' ? $val['pur_touch'] : 0),

							'calc_type'	    =>	$val['calc_type'],

							'active'        =>  1,

							'status'        =>  0,

							'created_on'	=>  date("Y-m-d H:i:s"),

							'created_by'    =>  $this->session->userdata('uid')

						);



						$id_karikar_wast = $this->$model->insertData($wast, 'ret_karikar_items_wastage');

						if ($id_karikar_wast) {



							/*Karigar Wastage Product Image*/

							$WastProImg = $val['order_img'];



							$p_ImgData = json_decode($WastProImg);





							$is_defalut_img = '';



							$is_default = 0;



							if (sizeof($p_ImgData) > 0) {

								$_FILES['precious']    =  array();

								foreach ($p_ImgData as $key => $precious) {

									if ($precious->is_default == 1) {

										$is_defalut_img = $key;
									}

									$imgFile = $this->base64ToFile($precious->src);

									$_FILES['precious'][] = $imgFile;
								}
							}



							if (!empty($p_ImgData)) {

								$folder =  self::IMG_PATH . "karigar/product_wastage/" . $id_karikar_wast;



								if (!is_dir($folder)) {

									mkdir($folder, 0777, TRUE);
								}



								if (isset($_FILES['precious'])) {

									//echo "<pre>";print_r($_FILES['wast_pro_images']);exit;



									foreach ($_FILES['precious'] as $file_key => $file_val) {

										if ($file_val['name']) {

											$img_name =  "$id_karikar_wast" . mt_rand(100001, 999999) . ".jpg";

											$path = $folder . "/" . $img_name;

											$result = $this->upload_img('image', $path, $file_val['tmp_name']);

											if ($result) {

												if ($is_defalut_img == $file_key) {

													$is_default = 1;
												} else {

													$is_default = 0;
												}

												$imgData = array('is_default' => $is_default, 'id_karikar_wast' => $id_karikar_wast, 'image_name' => $img_name);



												$this->$model->insertData($imgData, 'ret_karigar_item_wast_pro_images');
											}
										}
									}
								}
							}

							//echo "<pre>";print_r($imgData);exit;

							/*Karigar Wastage Product Image*/



							$charges_details = json_decode($val['charges_details'], true);

							if (sizeof($charges_details) > 0) {

								foreach ($charges_details as $charge) {

									$charge_data = array(

										'id_karigar'         => $id,

										'id_karikar_wast'    => $id_karikar_wast,

										'charge_id'          => $charge['charge_id'],

										'calc_type'          => $charge['calc_type'],

										'charge_value'       => $charge['charge_value'],

										'active'             => 0,

										'updated_on'	     => date("Y-m-d H:i:s"),

										'updated_by'         => $this->session->userdata('uid')

									);

									$this->$model->insertData($charge_data, 'ret_karigar_charges');
								}
							}
						}
					}
				}



				foreach ($stone_details as  $s) {

					//echo"<pre>"; print_r($s);exit;

					if ($s['is_stn_select'] == 1) {

						$stn = array(



							'id_karigar'         =>  $id_karigar,

							'stone_type'         =>  $s['stn_type'],

							'stone_id'           =>  $s['stn_name'],

							'stone_cal_type'     =>  $s['stn_calc_type'],

							'uom_id'             =>  $s['uom_id'],

							'rate_per_gram'      =>  $s['stone_rate'],

							/*'quality_id'         =>  $s['quality_id'],

                        		'from_wt'            =>  $s['from_wt'],

                        		'to_wt'              =>  $s['to_wt'],*/

							'active'             =>  0,

							'status'             =>  0,

							'created_on'	     =>  date("Y-m-d H:i:s"),

							'created_by'         =>  $this->session->userdata('uid')



						);



						$this->$model->insertData($stn, 'ret_karigar_stones');

						//print_r($this->db->last_query());exit;

					}
				}



				foreach ($kyc_details as $val) {

					$kyc = array(

						'id_karigar'	=>	$id_karigar,

						'id_kyc' 	    =>	$val['proof_name'],

						'kyc_number'    =>  $val['id_reg_exp'],

						'document'      =>  $val['document_file'],

						'created_on'	=>  date("Y-m-d H:i:s"),

						'created_by'    =>  $this->session->userdata('uid')

					);



					if ($val['img_type'] == 1) {

						$img_type = 'jpg';
					} else if ($val['img_type'] == 2) {

						$img_type = 'jpeg';
					} else {

						$img_type = 'png';
					}



					if ($val['order_img_fr'] != '') {





						$p_ImgData = [];

						$p_ImgData = json_decode($val['order_img_fr']);



						if (sizeof($p_ImgData) > 0) {

							foreach ($p_ImgData as $precious) {

								$imgFile = $this->base64ToFile($precious->src);

								$_FILES['order_img_fr'][] = $imgFile;
							}
						}



						if (!empty($_FILES)) {

							$img_arr = array();

							$folder =  self::IMG_PATH . "karigar/kyc/";

							if (!is_dir($folder)) {

								mkdir($folder, 0777, TRUE);
							}

							if (isset($_FILES['order_img_fr'])) {

								$order_images = "";



								foreach ($_FILES['order_img_fr'] as $file_key => $file_val) {



									if ($file_val['name']) {

										// unlink($folder."/".$product['image']);

										$img_name =  $insOrderDet . "_" . mt_rand(100001, 999999) . '.' . $img_type;

										$path = $folder . "/" . $img_name;

										$result = $this->upload_img('image', $path, $file_val['tmp_name']);

										if ($result) {

											$kyc['front_images'] = $img_name;
										}
									}
								}
							}
						}
					}



					if ($val['order_img_bk'] != '') {



						$p_ImgData = [];

						$p_ImgData = json_decode($val['order_img_bk']);



						if (sizeof($p_ImgData) > 0) {

							foreach ($p_ImgData as $precious) {

								$imgFile = $this->base64ToFile($precious->src);

								$_FILES['order_img_bk'][] = $imgFile;
							}
						}



						if (!empty($_FILES)) {

							$img_arr = array();

							$folder =  self::IMG_PATH . "karigar/kyc/";

							if (!is_dir($folder)) {

								mkdir($folder, 0777, TRUE);
							}

							if (isset($_FILES['order_img_bk'])) {

								$order_images = "";



								foreach ($_FILES['order_img_bk'] as $file_key => $file_val) {



									if ($file_val['name']) {

										// unlink($folder."/".$product['image']);

										$img_name =  $insOrderDet . "_" . mt_rand(100001, 999999) . '.' . $img_type;

										$path = $folder . "/" . $img_name;

										$result = $this->upload_img('image', $path, $file_val['tmp_name']);

										if ($result) {

											$kyc['back_images'] = $img_name;
										}
									}
								}
							}
						}
					}



					$id_karigar_kyc = $this->$model->insertData($kyc, 'ret_karigar_kyc');
				}



				if (!empty($bank_details)) {

					$arraybank = array();

					foreach ($bank_details['bank_name'] as $key => $val) {

						$arraybank = array(

							'id_karigar'     =>	 $id,

							'account_name'   =>	 $bank_details['acc_holder'][$key],

							'account_number' =>	 $bank_details['acc_number'][$key],

							'bank_name'      =>	 $bank_details['bank_name'][$key],

							'ifsc_code '     =>	 $bank_details['ifsc_code'][$key],

						);

						$this->$model->insertData($arraybank, 'ret_karigar_bank_acc_details');
					}
				}



				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Karigar',

						'operation'     => 'Add',

						'record'        => $id_karigar,

						'remark'        => 'Karigar added successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$result = array('message' => 'New User added successfully!..', 'class' => 'success', 'title' => 'Add User : ');

					$this->session->set_flashdata('chit_alert', array('message' => 'New User added successfully!..', 'class' => 'success', 'title' => 'Add User'));
				} else {

					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Add User : ');

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Add User'));
				}

				redirect('admin_ret_catalog/karigar/list');

				break;





			case 'delete':

				$this->db->trans_begin();

				$this->$model->deleteData('id_karigar', $id, 'ret_karigar');

				$this->$model->deleteData('id_karikar', $id, 'ret_karikar_items_wastage');

				$this->$model->deleteData('id_karigar', $id, 'ret_karigar_kyc');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Karigar',

						'operation'     => 'Delete',

						'record'        =>  $id,

						'remark'        => 'Karigar deleted successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'User deleted successfully', 'class' => 'success', 'title' => 'Delete User'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Delete User'));
				}

				redirect('admin_ret_catalog/karigar/list');

				break;



			case "update":

				if (!lmx_can($this, 'admin_ret_catalog/karigar/list', 'edit')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Edit User'));
					redirect('admin_ret_catalog/karigar/list');
					return;
				}

				$addData = $_POST['karigar'];

				// I-GMSJ-005: same gap as the "save" case above.
				if (!isset($addData['first_name']) || trim($addData['first_name']) === '') {
					$this->session->set_flashdata('chit_alert', array('message' => 'First Name is required', 'class' => 'danger', 'title' => 'Edit User'));
					redirect('admin_ret_catalog/karigar/list');
					return;
				}
				if (!empty($addData['user_name']) && $this->$model->check_karigar_username_exists($addData['user_name'], $id)) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Username "' . $addData['user_name'] . '" already exists', 'class' => 'danger', 'title' => 'Edit User'));
					redirect('admin_ret_catalog/karigar/list');
					return;
				}
				if (!empty($addData['gst_number']) && $this->$model->check_karigar_gst_exists($addData['gst_number'], $id)) {
					$this->session->set_flashdata('chit_alert', array('message' => 'GST Number "' . $addData['gst_number'] . '" already exists', 'class' => 'danger', 'title' => 'Edit User'));
					redirect('admin_ret_catalog/karigar/list');
					return;
				}
				if (!empty($addData['pan_number']) && $this->$model->check_karigar_pan_exists($addData['pan_number'], $id)) {
					$this->session->set_flashdata('chit_alert', array('message' => 'PAN Number "' . $addData['pan_number'] . '" already exists', 'class' => 'danger', 'title' => 'Edit User'));
					redirect('admin_ret_catalog/karigar/list');
					return;
				}

				$product_details = $_POST['o_item'];

				$stone_details = $_POST['stn'];

				$kyc_details = $_POST['kyc_det'];

				$bank_details = (isset($_POST['bank']) ? $_POST['bank'] : '');

				//echo"<pre>"; print_r($addData);exit;

				$data = array(

					'firstname'	           => strtoupper($addData['first_name']),

					'lastname'             => $addData['last_name_karigar'],

					'code_karigar'         => $addData['karigar_code'],

					'fin_year_code'         => $addData['fin_year_code'],

					'opening_balance_amount' => $addData['opening_balance_amount'],

					'karigar_type'         => $addData['user_type'],

					'is_tcs'               => $addData['is_tcs'],

					'is_tds'               => $addData['is_tds'],

					'tcs_tax'              => $addData['tcs_tax'],

					'tds_tax'              => $addData['tds_tax'],

					'karigar_for'          => $addData['karigar_for'],

					'address1'   		   => (!empty($addData['address1']) ? $addData['address1'] : NULL),

					'address2'   		   => (!empty($addData['address2']) ? $addData['address2'] : NULL),

					'address3'             => (!empty($addData['address3']) ? $addData['address3'] : NULL),

					'id_country'		   => (!empty($addData['country']) ? $addData['country'] : NULL),

					'id_state'		       => (!empty($addData['stateval']) ? $addData['stateval'] : NULL),

					'id_city'		       => (!empty($addData['cityval']) ? $addData['cityval'] : NULL),

					'pincode'		       => (!empty($addData['pincode']) ? $addData['pincode'] : NULL),

					'contactno2'		   => (!empty($addData['phone']) ? $addData['phone'] : NULL),

					'status_karigar'	   => 1,

					'email'		           => (!empty($addData['email']) ? $addData['email'] : NULL),

					'contactno1'		   => (!empty($addData['mobile']) ? $addData['mobile'] : NULL),

					'urname'		       => (!empty($addData['user_name']) ? $addData['user_name'] : NULL),

					'psword'		       => (!empty($addData['password']) ? $addData['password'] : NULL),

					'company'		       => (!empty($addData['company_karigar']) ? $addData['company_karigar'] : NULL),

					'gst_number'		   => (!empty($addData['gst_number']) ? $addData['gst_number'] : NULL),

					'ifsc_code'		       => (!empty($addData['ifsc_code']) ? $addData['ifsc_code'] : NULL),

					'acc_number'		   => (!empty($addData['acc_number']) ? $addData['acc_number'] : NULL),

					'bank_name'            => (!empty($addData['bank_name']) ? $addData['bank_name'] : NULL),

					'acc_holder_name'      => (!empty($addData['acc_holder']) ? $addData['acc_holder'] : NULL),

					'pan_no'		   	   => (!empty($addData['pan_number']) ? $addData['pan_number'] : NULL),

					//'karigar_calc_type'	   =>(!empty($addData['karigar_calc_type']) ? $addData['karigar_calc_type']:NULL),

					'remarks'	           => (!empty($addData['remarks']) ? $addData['remarks'] : NULL),

					'updateon'            => date("Y-m-d H:i:s"),

					'updatedby'            => $this->session->userdata('uid')

				);



				$this->db->trans_begin();

				// echo "<pre>"; print_r($_FILES);exit;

				if ($_FILES['karigar']['name']['user_img']) {

					$path = 'assets/img/karigar/';

					if (!is_dir($path)) {

						mkdir($path, 0777, TRUE);
					} else {

						$file = $path;

						chmod($path, 0777);
					}

					$img = $_FILES['karigar']['tmp_name']['user_img'];



					$filename = $id . '_' . mt_rand(00001, 99999) . ".jpg";

					$imgpath  = 'assets/img/karigar/' . $filename;

					$upload   = $this->upload_img('image', $imgpath, $img);

					$data['image'] = $filename;
				}



				$id_karigar = $this->$model->updateData($data, 'id_karigar', $id, 'ret_karigar');

				//print_r($this->db->last_query());exit;



				foreach ($product_details as $val) {

					if ($val['is_item_select'] == 1) {

						$this->$model->deleteData('id_karikar_wast', $val['id_karikar_wast'], 'ret_karikar_items_wastage');

						$wast = array(

							'id_karikar'	=>	$id,

							'id_category'   =>  $val['category'],

							'id_product' 	=>	$val['product'],

							'id_design' 	=>	$val['design'],

							'id_sub_design' =>	$val['sub_design'],

							'id_purity'     =>  $val['purity'],

							'wastage_type'  => ($val['va_type'] != '' ? $val['va_type'] : 1),

							'wastage_per' 	=> ($val['wast_percent'] != '' ? $val['wast_percent'] : 0),

							'wastage_wt' 	=> ($val['wast_wgt'] != '' ? $val['wast_wgt'] : 0),

							'mc_type'       =>  $val['id_mc_type'],

							'mc_value'      => ($val['mc'] != '' ? $val['mc'] : 0),

							'pur_touch'     => ($val['pur_touch'] != '' ? $val['pur_touch'] : 0),

							'karigar_calc_type' => $val['kar_calc_type'],

							'calc_type'	    =>	$val['calc_type'],

							'active'        =>  0,

							'status'        =>  0,

							'updated_on'	=>  date("Y-m-d H:i:s"),

							'updated_by'    =>  $this->session->userdata('uid')

						);



						$id_karikar_wast = $this->$model->insertData($wast, 'ret_karikar_items_wastage');

						if ($id_karikar_wast) {

							/*Karigar Wastage Product Image*/

							$WastProImg = $val['order_img'];



							$p_ImgData = json_decode($WastProImg);





							$is_defalut_img = '';



							$is_default = 0;



							if (sizeof($p_ImgData) > 0) {

								$_FILES['precious']    =  array();



								foreach ($p_ImgData as $key => $precious) {

									if ($precious->is_default == 1) {

										$is_defalut_img = $key;
									}

									$imgFile = $this->base64ToFile($precious->src);

									$_FILES['precious'][] = $imgFile;
								}
							}



							if (!empty($p_ImgData)) {

								$folder =  self::IMG_PATH . "karigar/product_wastage/" . $id_karikar_wast;



								if (!is_dir($folder)) {

									mkdir($folder, 0777, TRUE);
								}



								if (isset($_FILES['precious'])) {

									//echo "<pre>";print_r($_FILES['wast_pro_images']);exit;



									foreach ($_FILES['precious'] as $file_key => $file_val) {

										//echo "<pre>";print_r($file_val);exit;

										if ($file_val['name']) {

											$img_name =  "$id_karikar_wast" . mt_rand(100001, 999999) . ".jpg";

											$path = $folder . "/" . $img_name;

											$result = $this->upload_img('image', $path, $file_val['tmp_name']);

											if ($result) {

												if ($is_defalut_img == $file_key) {

													$is_default = 1;
												} else {

													$is_default = 0;
												}



												$imgData = array('is_default' => $is_default, 'id_karikar_wast' => $id_karikar_wast, 'image_name' => $img_name);

												$this->$model->insertData($imgData, 'ret_karigar_item_wast_pro_images');
											}
										}
									}
								}
							}



							/*Karigar Wastage Product Image*/





							$charges_details = json_decode($val['charges_details'], true);

							if (sizeof($charges_details) > 0) {

								foreach ($charges_details as $charge) {

									$charge_data = array(

										'id_karigar'         => $id,

										'id_karikar_wast'    => $id_karikar_wast,

										'charge_id'          => $charge['charge_id'],

										'charge_value'       => $charge['charge_value'],

										'calc_type'          => $charge['calc_type'],

										'active'             => 0,

										'updated_on'	     => date("Y-m-d H:i:s"),

										'updated_by'         => $this->session->userdata('uid')

									);

									$this->$model->insertData($charge_data, 'ret_karigar_charges');
								}
							}
						}
					}
				}



				foreach ($stone_details as  $s) {

					//echo"<pre>"; print_r($s);exit;

					if ($s['is_stn_select'] == 1) {

						$stn = array(

							'id_karigar'         =>   $id,

							'stone_type'         =>   $s['stn_type'],

							'stone_id'           =>   $s['stn_name'],

							'stone_cal_type'     =>   $s['stn_calc_type'],

							'uom_id'             =>   $s['uom_id'],

							'rate_per_gram'      =>   $s['stone_rate'],

							/*'quality_id'         =>   $s['quality_id'],

                                    'from_wt'            =>   $s['from_wt'],

                                    'to_wt'              =>   $s['to_wt'],*/

							'active'             =>  0,

							'status'             =>  0,

							'updated_on'	     =>  date("Y-m-d H:i:s"),

							'updated_by'         =>  $this->session->userdata('uid')

						);



						$this->$model->insertData($stn, 'ret_karigar_stones');

						//print_r($this->db->last_query());exit;

					}
				}



				$this->$model->deleteData('id_karigar', $id, 'ret_karigar_kyc');



				foreach ($kyc_details as $val) {

					$kyc = array(

						'id_karigar'	=>	$id,

						'id_kyc' 	    =>	$val['proof_name'],

						'kyc_number'    =>  $val['id_reg_exp'],

						'document'      =>  $val['document_file'],

						'created_on'	=>  date("Y-m-d H:i:s"),

						'created_by'    =>  $this->session->userdata('uid')

					);



					if ($val['img_type'] == 1) {

						$img_type = 'jpg';
					} else if ($val['img_type'] == 2) {

						$img_type = 'jpeg';
					} else {

						$img_type = 'png';
					}



					if ($val['order_img_fr'] != '') {





						$p_ImgData = [];

						$p_ImgData = json_decode($val['order_img_fr']);



						if (sizeof($p_ImgData) > 0) {

							foreach ($p_ImgData as $precious) {

								$imgFile = $this->base64ToFile($precious->src);

								$_FILES['order_img_fr'][] = $imgFile;
							}
						}



						if (!empty($_FILES)) {

							$img_arr = array();

							$folder =  self::IMG_PATH . "karigar/kyc/";

							if (!is_dir($folder)) {

								mkdir($folder, 0777, TRUE);
							}

							if (isset($_FILES['order_img_fr'])) {

								$order_images = "";



								foreach ($_FILES['order_img_fr'] as $file_key => $file_val) {



									if ($file_val['name']) {

										// unlink($folder."/".$product['image']);

										$img_name =  $insOrderDet . "_" . mt_rand(100001, 999999) . '.' . $img_type;

										$path = $folder . "/" . $img_name;

										$result = $this->upload_img('image', $path, $file_val['tmp_name']);

										if ($result) {

											$kyc['front_images'] = $img_name;
										}
									}
								}
							}
						}
					}



					if ($val['order_img_bk'] != '') {



						$p_ImgData = [];

						$p_ImgData = json_decode($val['order_img_bk']);



						if (sizeof($p_ImgData) > 0) {

							foreach ($p_ImgData as $precious) {

								$imgFile = $this->base64ToFile($precious->src);

								$_FILES['order_img_bk'][] = $imgFile;
							}
						}



						if (!empty($_FILES)) {

							$img_arr = array();

							$folder =  self::IMG_PATH . "karigar/kyc/";

							if (!is_dir($folder)) {

								mkdir($folder, 0777, TRUE);
							}

							if (isset($_FILES['order_img_bk'])) {

								$order_images = "";



								foreach ($_FILES['order_img_bk'] as $file_key => $file_val) {



									if ($file_val['name']) {

										// unlink($folder."/".$product['image']);

										$img_name =  $insOrderDet . "_" . mt_rand(100001, 999999) . '.' . $img_type;

										$path = $folder . "/" . $img_name;

										$result = $this->upload_img('image', $path, $file_val['tmp_name']);

										if ($result) {

											$kyc['back_images'] = $img_name;
										}
									}
								}
							}
						}
					}



					$id_karigar_kyc = $this->$model->insertData($kyc, 'ret_karigar_kyc');
				}



				$this->$model->deleteData('id_karigar', $id, 'ret_karigar_bank_acc_details');

				if (!empty($bank_details)) {

					$arraybank = array();

					foreach ($bank_details['bank_name'] as $key => $val) {

						$arraybank = array(

							'id_karigar'     =>	 $id,

							'account_name'   =>	 $bank_details['acc_holder'][$key],

							'account_number' =>	 $bank_details['acc_number'][$key],

							'bank_name'      =>	 $bank_details['bank_name'][$key],

							'ifsc_code '     =>	 $bank_details['ifsc_code'][$key],

						);

						$this->$model->insertData($arraybank, 'ret_karigar_bank_acc_details');
					}
				}



				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();


					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'karigar',

						'operation'     => 'Update',

						'record'        =>  $id_karigar,

						'remark'        => 'karigar Updated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'User record modified successfully', 'class' => 'success', 'title' => 'Edit User'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Edit User'));
				}

				redirect('admin_ret_catalog/karigar/list');

				break;



			case 'update_status':



				$data = array(
					'status_karigar' => $status,

					'updateon'	  => date("Y-m-d H:i:s"),

					'updatedby'      => $this->session->userdata('uid')
				);

				$model = self::CAT_MODEL;

				$this->db->trans_begin();
				$updstatus = $this->$model->updateData($data, 'id_karigar', $id, 'ret_karigar');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'User status updated as ' . ($status == 1 ? 'Approved' : 'Not Approved') . ' successfully.', 'class' => 'success', 'title' => 'User Status'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'User Status'));
				}

				redirect('admin_ret_catalog/karigar/list');

				break;

			case 'active_list':

				$data = $this->$model->getActiveKarigar($id);

				echo json_encode($data);

				break;

			case 'list':

				$data['main_content'] = "master/karigar/list";

				$data['access'] = $this->$set_model->get_access('admin_ret_catalog/karigar/list');

				$this->load->view('layout/template', $data);

				break;

			case 'mobile_available':

				$data = $this->$model->mobile_available($_POST['mobile']);

				echo json_encode($data);

				break;



			case 'email_available':

				$data = $this->$model->email_available($_POST['email']);

				echo json_encode($data);

				break;



			default:

				$SETT_MOD = self::SETT_MOD;

				$range['from_date']  = $this->input->post('from_date');

				$range['to_date']  = $this->input->post('to_date');

				$range['karigar_for']  = $this->input->post('karigar_for');

				$karigar = $this->$model->ajax_getkarigar($range['from_date'], $range['to_date'], $range['karigar_for'], $_POST['karigar_type']);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/karigar/list');

				$data = array(

					'karigar' => $karigar,

					'access' => $access

				);

				echo json_encode($data);
		}
	}



	public function material_rate($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'material_rate', $type, $id, $status);
	}

	public function tag($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'tag', $type, $id, $status);
	}



	public function design($type = "", $id = "", $status = "")
	{

		$model = self::CAT_MODEL;

		switch ($type) {

			case 'active_designBySearch':

				$data = $this->$model->getSearchDesign($_POST['searchTxt'], $_POST['product_id']);

				echo json_encode($data);

				break;

			case 'd_sizes':

				$data = $this->$model->getDesignSizes($_POST['design_no']);

				echo json_encode($data);

				break;

			case 'd_purities':

				$data = $this->$model->getDesignPurities($_POST['design_no']);

				echo json_encode($data);

				break;
		}
	}



	public function tax($type = "", $id = "", $status = "")
	{
		// active_tax is consumed by catalog_master.js and ret_reports.js (tax
		// dropdowns elsewhere) outside this master's own page — preserved as-is,
		// everything else routes through the generic engine.
		if ($type === 'active_tax') {
			$model = self::CAT_MODEL;
			echo json_encode($this->$model->getActivetax());
			return;
		}
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'tax', $type, $id, $status);
	}


	public function ret_product($type = "", $id = "", $status = "")
	{

		$model = self::CAT_MODEL;

		$SETT_MOD = self::SETT_MOD;

		// I-GMSJ-005: same gap as category() — delete/update_status call
		// lmx_can() but this branch never loaded the helper for it, so both
		// actions fatal-errored silently in production mode with no DB change.
		$this->load->helper('lmx/core/access_helper');

		switch ($type) {

			case "add":

				$data['product'] = $this->$model->getProd_empty_record();

				$data['main_content'] = "master/ret_product/form";

				$this->load->view('layout/template', $data);

				break;

			case "save":

				if (!lmx_can($this, 'admin_ret_catalog/ret_product/list', 'add')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Add Product'));
					redirect('admin_ret_catalog/ret_product/list');
					return;
				}

				$addData = $_POST['product'];

				//echo "<pre>"; print_r($addData); echo "</pre>"; exit;

				//$short_code = $this->$model->genProdShortCode();

				// I-GMSJ-005: Product Name is the master's mandatory/duplicate-checked
				// field (product_short_code below was already checked, product_name
				// never was) — an unchecked duplicate hit the DB's unique constraint
				// raw, surfacing a "Database Error Occurred" page to the user instead
				// of a normal validation message.
				if (empty($addData['product_name']) || trim($addData['product_name']) === '') {
					$this->session->set_flashdata('chit_alert', array('message' => 'Product Name is required', 'class' => 'danger', 'title' => 'Add Product'));
					redirect('admin_ret_catalog/ret_product/list');
					break;
				}

				$name_dup_check = $this->db->query("SELECT pro_id FROM ret_product_master WHERE LOWER(TRIM(product_name)) = " . $this->db->escape(strtolower(trim($addData['product_name']))));
				if ($name_dup_check->num_rows() > 0) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Product Name "' . $addData['product_name'] . '" already exists', 'class' => 'danger', 'title' => 'Add Product'));
					redirect('admin_ret_catalog/ret_product/list');
					break;
				}

				// Check for duplicate product_short_code before insert
				$short_code = !empty($addData['product_short_code']) ? $addData['product_short_code'] : '';
				if ($short_code != '') {
					$dup_check = $this->db->query("SELECT pro_id FROM ret_product_master WHERE product_short_code = " . $this->db->escape($short_code));
					if ($dup_check->num_rows() > 0) {
						$this->session->set_flashdata('chit_alert', array('message' => 'Product Short Code "' . $short_code . '" already exists. Please use a different short code.', 'class' => 'danger', 'title' => 'Add Product'));
						redirect('admin_ret_catalog/ret_product/list');
						break;
					}
				}

				// I-GMSJ-005: Category is required, and must still exist and be
				// active at the moment of save — catches the case where a category
				// was deactivated in another tab after this form's dropdown loaded.
				if (empty($addData['cat_id'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Category is required', 'class' => 'danger', 'title' => 'Add Product'));
					redirect('admin_ret_catalog/ret_product/list');
					break;
				}
				if (!$this->$model->check_category_active($addData['cat_id'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Selected Category is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Add Product'));
					redirect('admin_ret_catalog/ret_product/list');
					break;
				}

				// I-GMSJ-005: tgrp_id (Tax Group) is optional (nullable in the DB,
				// unlike cat_id) but if one IS submitted it must still exist and be
				// active — same stale-reference race as Category.
				if (!empty($addData['tgrp_id']) && !$this->$model->check_tax_group_active($addData['tgrp_id'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Selected Tax Group is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Add Product'));
					redirect('admin_ret_catalog/ret_product/list');
					break;
				}

				$data = array(

					'cat_id'	           => (!empty($addData['cat_id']) ? $addData['cat_id'] : NULL),

					'tgrp_id'	           => (!empty($addData['tgrp_id']) ? $addData['tgrp_id'] : NULL),

					'job_work_receipt_tgrp_id'	 => (!empty($addData['job_work_receipt_tgrp_id']) ? $addData['job_work_receipt_tgrp_id'] : NULL),

					// 'id_section'	       => (!empty($addData['id_section']) ? $addData['id_section'] :NULL ),

					'hsn_code'             => (!empty($addData['hsn_code']) ? $addData['hsn_code'] : NULL),

					'stock_type'           => (!empty($addData['stock_type']) ? $addData['stock_type'] : 1),

					'stone_type'           => (!empty($addData['stone_type']) ? $addData['stone_type'] : 0),

					'sales_mode'   		   => (!empty($addData['sales_mode']) ? $addData['sales_mode'] : 1),

					'purchase_mode'   	   => (!empty($addData['purchase_mode']) ? $addData['purchase_mode'] : 1),

					'wastage_type'   	   => (!empty($addData['wastage_type']) ? $addData['wastage_type'] : 1),

					'min_wastage'   	   => (!empty($addData['min_wastage']) ? $addData['min_wastage'] : 1),

					'max_wastage'   	   => (!empty($addData['max_wastage']) ? $addData['max_wastage'] : 1),

					'other_materials'      => (!empty($addData['other_materials']) ? $addData['other_materials'] : 0),

					'gift_applicable'      => (!empty($addData['gift_applicable']) ? $addData['gift_applicable'] : 0),

					'has_stone'		       => (!empty($addData['has_stone']) ? $addData['has_stone'] : 0),

					'has_hook'		       => (!empty($addData['has_hook']) ? $addData['has_hook'] : 0),

					'has_screw'		       => (!empty($addData['has_screw']) ? $addData['has_screw'] : 0),

					'has_fixed_price'	   => (!empty($addData['has_fixed_price']) ? $addData['has_fixed_price'] : 0),

					'metal_type'		   => (!empty($addData['metal_type']) ? $addData['metal_type'] : NULL),

					'product_short_code'   => (!empty($addData['product_short_code']) ? $addData['product_short_code'] : NULL),

					'product_name'		   => (!empty($addData['product_name']) ? strtoupper($addData['product_name']) : NULL),

					'has_size'		       => (!empty($addData['has_size']) ? $addData['has_size'] : 0),

					'less_stone_wt'		   => (!empty($addData['less_stone_wt']) ? $addData['less_stone_wt'] : 0),

					'tag_split'		       => (!empty($addData['tag_split']) ? $addData['tag_split'] : 0),

					'ag_parts'		       => (!empty($addData['ag_parts']) ? $addData['ag_parts'] : 0),

					'tag_merge'		       => (!empty($addData['tag_merge']) ? $addData['tag_merge'] : 0),

					'tag_type'		       => (!empty($addData['tag_type']) ? $addData['tag_type'] : 0),

					'other_charges'		   => (!empty($addData['other_charges']) ? $addData['other_charges'] : 0),

					'net_wt'		       => (!empty($addData['net_wt']) ? $addData['net_wt'] : 0),

					'stock_report'		   => (!empty($addData['stock_report']) ? $addData['stock_report'] : 0),

					'central_exces_duty'   => (!empty($addData['central_exces_duty']) ? $addData['central_exces_duty'] : 0),

					'no_of_pieces'		   => (!empty($addData['no_of_pieces']) ? $addData['no_of_pieces'] : NULL),

					'rfid_required'		   => (!empty($addData['rfid_required']) ? $addData['rfid_required'] : 0),

					'rfid_in_stock'		   => (!empty($addData['rfid_in_stock']) ? $addData['rfid_in_stock'] : 0),

					'hallmark'		       => (!empty($addData['hallmark']) ? $addData['hallmark'] : 0),

					'counter'		       => (!empty($addData['counter']) ? $addData['counter'] : 1),

					'stone_board_rate_cal' => (!empty($addData['stone_board_rate_cal']) ? $addData['stone_board_rate_cal'] : 0),

					'calculation_based_on' => (!empty($addData['calculation_based_on']) ? $addData['calculation_based_on'] : NULL),

					'sales_markup'		   => (!empty($addData['sales_markup']) ? $addData['sales_markup'] : 0),

					'max_markup_per_for_rateitems' => (!empty($addData['max_markup_per_for_rateitems']) ? $addData['max_markup_per_for_rateitems'] : NULL),

					'no_of_tags_to_print' => (!empty($addData['no_of_tags_to_print']) ? $addData['no_of_tags_to_print'] : NULL),

					'tax_group_id' => (!empty($addData['tax_group_id']) ? $addData['tax_group_id'] : NULL),

					'product_status' => 1,

					'created_time' => date("Y-m-d H:i:s"),

					'create_by'      => $this->session->userdata('uid'),

					'reorder_based_on'	    => (!empty($addData['reorder_based_on']) ? $addData['reorder_based_on'] : 1),

					'image'                 => (!empty($addData['image']) ? $addData['image'] : NULL),

					'tax_type'	           => (!empty($addData['tax_type']) ? $addData['tax_type'] : NULL),

					'uom_id'	           => (!empty($addData['uom']) ? $addData['uom'] : NULL),

					'display_purity'       => (isset($addData['display_purity']) ? $addData['display_purity'] : 1),
					
					'show_va_mc'       => (isset($addData['show_va_mc']) ? $addData['show_va_mc'] : 1),

				);

				//	print_r($data);exit;

				$this->db->trans_begin();

				$id_karigar = $this->$model->insertData($data, 'ret_product_master');



				if (isset($_FILES['product']['name'])) {

					if ($id_karigar > 0) {

						// print_r($addData['id_section']);exit;


						foreach ($addData['id_section'] as $key => $section) {


							$section_data = array(

								'pro_id'   		=> $id_karigar,

								'id_section'	    => (!empty($section) ? $section : NULL),

							);

							// print_r($section_data);exit;

							$this->$model->insertData($section_data, 'ret_product_section');
						}


						$this->set_image_ret($id_karigar);



						foreach ($addData['charges']['id'] as $key => $charge) {

							$charge_data = array(

								'prod_id'   		=>	$id_karigar,

								'charge_id'      	=>	$addData['charges']['id'][$key],

								'charge_value'   	=>	$addData['charges']['value'][$key],

								'created_on'   	 	=> 	date("Y-m-d H:i:s"),

								'created_by'     	=> 	$this->session->userdata('uid')

							);



							$this->$model->insertData($charge_data, 'ret_product_charges');
						}
					}
				}



				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					// Generate QR Code
					$this->load->library('phpqrcode/qrlib');
					$SERVERFILEPATH = 'assets/img/product_qrcode/';
					if (!is_dir($SERVERFILEPATH)) {
						mkdir($SERVERFILEPATH, 0777, TRUE);
					}
					$file_name1 = $id_karigar . ".png";
					$file_name = $SERVERFILEPATH . $file_name1;
					$qr_data = !empty($data['product_short_code']) ? $data['product_short_code'] : $id_karigar;
					QRcode::png($qr_data, $file_name);

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Product',

						'operation'     => 'Add',

						'record'        =>  $id_karigar,

						'remark'        => 'Product added successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'New Product added successfully', 'class' => 'success', 'title' => 'Add Product'));

					//echo 1;

				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Add Product'));

					//echo 0;

				}

				redirect('admin_ret_catalog/ret_product/list');

				break;

			case "edit":

				$data['product'] = $this->$model->get_ret_product($id);

				$data['section'] = $this->$model->get_product_section($id);

				$data['charges_list'] = $this->$model->get_charges_list();

				$data['charges'] = $this->$model->get_product_charges($id);

				//$data['charges']

				//echo "<pre>";print_r($data);exit;

				$data['main_content'] = "master/ret_product/form";

				$this->load->view('layout/template', $data);

				break;

			case 'delete':

				if (!lmx_can($this, 'admin_ret_catalog/ret_product/list', 'delete')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Delete product'));
					redirect('admin_ret_catalog/ret_product/list');
					return;
				}

				$isProductExists = $this->$model->getItemsinTagDetails('', '', $id, '', '', '');

				// Guard against an active (not-yet-converted-to-order)
				// Estimation still referencing it — only stock/tag existence
				// was checked before.
				$isProductInActiveEstimation = $this->db
					->where('ei.product_id', $id)
					->where('e.has_converted_order', 0)
					->join('ret_estimation e', 'e.estimation_id = ei.esti_id')
					->count_all_results('ret_estimation_items ei');

				$this->db->trans_begin();

				if ($isProductExists == 0 && $isProductInActiveEstimation == 0) {
					// Masters never hard-delete — deactivate instead, same as
					// every other master. ret_product_charges is left intact
					// (previously wiped here) so a reactivated product keeps
					// its charges.
					$this->$model->updateData(
						array(
							'product_status' => 0,
							'updated_time'   => date("Y-m-d H:i:s"),
							'updated_by'     => $this->session->userdata('uid')
						),
						'pro_id',
						$id,
						'ret_product_master'
					);

					if ($this->db->trans_status() === TRUE) {
						$this->db->trans_commit();
						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),
							'event_date'    => date("Y-m-d H:i:s"),
							'module'        => 'Product',
							'operation'     => 'Deactivate',
							'record'        =>  $id,
							'remark'        => 'Product deactivated successfully'
						);
						$this->log_model->log_detail('insert', '', $log_data);
						$this->session->set_flashdata('chit_alert', array('message' => 'product deactivated successfully', 'class' => 'success', 'title' => 'Deactivate product'));
					} else {
						$this->db->trans_rollback();
						$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Deactivate product'));
					}
				} else {
					$blockMsg = $isProductExists != 0
						? 'Product Exists in Stock..'
						: 'Product is referenced by an active Estimation and cannot be deactivated.';
					$this->session->set_flashdata('chit_alert', array('message' => $blockMsg, 'class' => 'danger', 'title' => 'Deactivate product'));
				}


				redirect('admin_ret_catalog/ret_product/list');

				break;



			case "update":

				if (!lmx_can($this, 'admin_ret_catalog/ret_product/list', 'edit')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Edit Product'));
					redirect('admin_ret_catalog/ret_product/list');
					return;
				}

				$updData = $_POST['product'];

				//echo "<pre>"; print_r($updData); echo "</pre>"; exit;

				if (empty($updData['product_name']) || trim($updData['product_name']) === '') {
					$this->session->set_flashdata('chit_alert', array('message' => 'Product Name is required', 'class' => 'danger', 'title' => 'Edit Product'));
					redirect('admin_ret_catalog/ret_product/list');
					break;
				}

				$name_dup_check_upd = $this->db->query("SELECT pro_id FROM ret_product_master WHERE LOWER(TRIM(product_name)) = " . $this->db->escape(strtolower(trim($updData['product_name']))) . " AND pro_id != " . intval($id));
				if ($name_dup_check_upd->num_rows() > 0) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Product Name "' . $updData['product_name'] . '" already exists for another product', 'class' => 'danger', 'title' => 'Edit Product'));
					redirect('admin_ret_catalog/ret_product/list');
					break;
				}

				// Check for duplicate product_short_code before update (exclude current product)
				$short_code_upd = !empty($updData['product_short_code']) ? $updData['product_short_code'] : '';
				if ($short_code_upd != '') {
					$dup_check_upd = $this->db->query("SELECT pro_id FROM ret_product_master WHERE product_short_code = " . $this->db->escape($short_code_upd) . " AND pro_id != " . intval($id));
					if ($dup_check_upd->num_rows() > 0) {
						$this->session->set_flashdata('chit_alert', array('message' => 'Product Short Code "' . $short_code_upd . '" already exists for another product. Please use a different short code.', 'class' => 'danger', 'title' => 'Edit Product'));
						redirect('admin_ret_catalog/ret_product/list');
						break;
					}
				}

				// I-GMSJ-005: Category is required, and must still exist and be
				// active at the moment of save — catches the case where a category
				// was deactivated in another tab after this form's dropdown loaded.
				if (empty($updData['cat_id'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Category is required', 'class' => 'danger', 'title' => 'Edit Product'));
					redirect('admin_ret_catalog/ret_product/list');
					break;
				}
				if (!$this->$model->check_category_active($updData['cat_id'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Selected Category is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Edit Product'));
					redirect('admin_ret_catalog/ret_product/list');
					break;
				}

				// I-GMSJ-005: tgrp_id (Tax Group) is optional but if submitted must
				// still exist and be active — same stale-reference race as Category.
				if (!empty($updData['tgrp_id']) && !$this->$model->check_tax_group_active($updData['tgrp_id'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Selected Tax Group is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Edit Product'));
					redirect('admin_ret_catalog/ret_product/list');
					break;
				}

				$data = array(

					'cat_id'	           => (!empty($updData['cat_id']) ? $updData['cat_id'] : NULL),

					'tgrp_id'	           => (!empty($updData['tgrp_id']) ? $updData['tgrp_id'] : NULL),

					'job_work_receipt_tgrp_id' => (!empty($updData['job_work_receipt_tgrp_id']) ? $updData['job_work_receipt_tgrp_id'] : NULL),

					// 'id_section'	       => (!empty($updData['id_section']) ? $updData['id_section'] :NULL ),

					'hsn_code'             => (!empty($updData['hsn_code']) ? $updData['hsn_code'] : NULL),

					'stock_type'           => (!empty($updData['stock_type']) ? $updData['stock_type'] : 1),

					'stone_type'           => (!empty($updData['stone_type']) ? $updData['stone_type'] : 0),

					'sales_mode'   		   => (!empty($updData['sales_mode']) ? $updData['sales_mode'] : 1),

					'purchase_mode'   	   => (!empty($updData['purchase_mode']) ? $updData['purchase_mode'] : 1),

					'wastage_type'   	   => (!empty($updData['wastage_type']) ? $updData['wastage_type'] : 1),

					'min_wastage'   	   => (!empty($updData['min_wastage']) ? $updData['min_wastage'] : 1),

					'max_wastage'   	   => (!empty($updData['max_wastage']) ? $updData['max_wastage'] : 1),

					'other_materials'      => (!empty($updData['other_materials']) ? $updData['other_materials'] : 0),

					'gift_applicable'      => (!empty($updData['gift_applicable']) ? $updData['gift_applicable'] : 0),

					'has_stone'		       => (!empty($updData['has_stone']) ? $updData['has_stone'] : 0),

					'has_hook'		       => (!empty($updData['has_hook']) ? $updData['has_hook'] : 0),

					'has_screw'		       => (!empty($updData['has_screw']) ? $updData['has_screw'] : 0),

					'has_fixed_price'	   => (!empty($updData['has_fixed_price']) ? $updData['has_fixed_price'] : 0),

					'metal_type'		   => (!empty($updData['metal_type']) ? $updData['metal_type'] : NULL),

					'product_short_code'   => (!empty($updData['product_short_code']) ? $updData['product_short_code'] : NULL),

					'product_name'		   => (!empty($updData['product_name']) ? strtoupper($updData['product_name']) : NULL),

					'uom_id'		   		=> (!empty($updData['uom']) ? strtoupper($updData['uom']) : NULL),

					'has_size'		       => (!empty($updData['has_size']) ? $updData['has_size'] : 0),

					'less_stone_wt'		   => (!empty($updData['less_stone_wt']) ? $updData['less_stone_wt'] : 0),

					'tag_split'		       => (!empty($updData['tag_split']) ? $updData['tag_split'] : 0),

					'ag_parts'		       => (!empty($updData['ag_parts']) ? $updData['ag_parts'] : 0),

					'tag_merge'		       => (!empty($updData['tag_merge']) ? $updData['tag_merge'] : 0),

					'tag_type'		       => (!empty($updData['tag_type']) ? $updData['tag_type'] : 0),

					'other_charges'		   => (!empty($updData['other_charges']) ? $updData['other_charges'] : 0),

					'net_wt'		       => (!empty($updData['net_wt']) ? $updData['net_wt'] : 0),

					'stock_report'		   => (!empty($updData['stock_report']) ? $updData['stock_report'] : 0),

					'central_exces_duty'   => (!empty($updData['central_exces_duty']) ? $updData['central_exces_duty'] : 0),

					'no_of_pieces'		   => (!empty($updData['no_of_pieces']) ? $updData['no_of_pieces'] : NULL),

					'rfid_required'		   => (!empty($updData['rfid_required']) ? $updData['rfid_required'] : 0),

					'rfid_in_stock'		   => (!empty($updData['rfid_in_stock']) ? $updData['rfid_in_stock'] : 0),

					'hallmark'		       => (!empty($updData['hallmark']) ? $updData['hallmark'] : 0),

					'counter'		       => (!empty($updData['counter']) ? $updData['counter'] : 1),

					'stone_board_rate_cal' => (!empty($updData['stone_board_rate_cal']) ? $updData['stone_board_rate_cal'] : 0),

					'calculation_based_on' => (!empty($updData['calculation_based_on']) ? $updData['calculation_based_on'] : NULL),

					'sales_markup'		   => (!empty($updData['sales_markup']) ? $updData['sales_markup'] : 0),

					'max_markup_per_for_rateitems'		=> (!empty($updData['max_markup_per_for_rateitems']) ? $updData['max_markup_per_for_rateitems'] : NULL),

					'no_of_tags_to_print'		        => (!empty($updData['no_of_tags_to_print']) ? $updData['no_of_tags_to_print'] : NULL),

					'tax_group_id'		    => (!empty($updData['tax_group_id']) ? $updData['tax_group_id'] : NULL),

					'product_status'		=> (isset($updData['product_status']) ? $updData['product_status'] : 1),

					'tax_type'	           => (!empty($updData['tax_type']) ? $updData['tax_type'] : NULL),

					'display_purity'       => (isset($updData['display_purity']) ? $updData['display_purity'] : 1),

					'show_va_mc'       => (isset($updData['show_va_mc']) ? $updData['show_va_mc'] : 1),

					'reorder_based_on'	  => (!empty($updData['reorder_based_on']) ? $updData['reorder_based_on'] : 1),

					'updated_time'	        => date("Y-m-d H:i:s"),

					'updated_by'            => $this->session->userdata('uid'),

					'image'                 => (!empty($updData['image']) && $updData['image'] != '' ? $updData['image'] : NULL)

				);



				$this->db->trans_begin();

				$prod = $this->$model->updateData($data, 'pro_id', $id, 'ret_product_master');


				if ($prod > 0) {


					if (isset($updData['id_section']) && !empty($updData['id_section'])) {
					$this->$model->deleteData('pro_id', $prod, 'ret_product_section');

					foreach ($updData['id_section'] as $key => $section) {

						$section_data = array(

							'pro_id'   		=>  $prod,

							'id_section'	    => (!empty($section) ? $section : NULL),

						);

						// print_r($section_data);exit;

						$this->$model->insertData($section_data, 'ret_product_section');
					}
				}

					if (isset($_FILES['product']['name']['ret_product_img']) && $_FILES['product']['name']['ret_product_img'] != '') {

						$this->set_image_ret($prod);
					}



					$this->$model->deleteData('prod_id', $prod, 'ret_product_charges');



					foreach ($updData['charges']['id'] as $key => $charge) {

						$charge_data = array(

							'prod_id'   		=>	$prod,

							'charge_id'      	=>	$updData['charges']['id'][$key],

							'charge_value'   	=>	$updData['charges']['value'][$key],

							'created_on'   	 	=> 	date("Y-m-d H:i:s"),

							'created_by'     	=> 	$this->session->userdata('uid')

						);



						$this->$model->insertData($charge_data, 'ret_product_charges');
					}
				}



				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					// Generate QR Code
					$this->load->library('phpqrcode/qrlib');
					$SERVERFILEPATH = 'assets/img/product_qrcode/';
					if (!is_dir($SERVERFILEPATH)) {
						mkdir($SERVERFILEPATH, 0777, TRUE);
					}
					$file_name1 = $id . ".png";
					$file_name = $SERVERFILEPATH . $file_name1;
					$qr_data = !empty($data['product_short_code']) ? $data['product_short_code'] : $id;
					QRcode::png($qr_data, $file_name);

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Product',

						'operation'     => 'Update',

						'record'        =>  $prod,

						'remark'        => 'Product Updated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Product record modified successfully', 'class' => 'success', 'title' => 'Edit Product'));

					//echo 1;

				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Edit Product'));

					//echo 0;

				}

				redirect('admin_ret_catalog/ret_product/list');

				break;

			case 'update_status':

				if (!lmx_can($this, 'admin_ret_catalog/ret_product/list', 'edit')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Product Status'));
					redirect('admin_ret_catalog/ret_product/list');
					return;
				}

				// Known gap fix: the full Edit-form save allows deactivating a
				// product regardless of stock on hand (no such check exists
				// there at all) — this listing-page toggle used to block on
				// stock, which was inconsistent with Edit and, per the reported
				// requirement, incorrect: a product should be deactivatable
				// even with stock available. Only the active-Estimation guard
				// remains, since an unconverted Estimation actively referencing
				// this product is a distinct, real risk not covered by Edit
				// either way and wasn't part of what was reported as wrong.
				if ($status == 0) {
					$isProductInActiveEstimation = $this->db
						->where('ei.product_id', $id)
						->where('e.has_converted_order', 0)
						->join('ret_estimation e', 'e.estimation_id = ei.esti_id')
						->count_all_results('ret_estimation_items ei');
					if ($isProductInActiveEstimation != 0) {
						$this->session->set_flashdata('chit_alert', array('message' => 'Product is referenced by an active Estimation and cannot be deactivated.', 'class' => 'danger', 'title' => 'Product Status'));
						redirect('admin_ret_catalog/ret_product/list');
						return;
					}
				}

				// Known gap fix: reactivating a product whose Category or Tax
				// Group has since been deactivated left it Active again with a
				// stale reference — same validation the full Edit-form save
				// already applies, just re-checked here against the product's
				// current (unchanged) values since this action only submits
				// the new status, not the whole form.
				if ($status == 1) {
					$current_product = $this->$model->get_ret_product($id);
					if (!empty($current_product['cat_id']) && !$this->$model->check_category_active($current_product['cat_id'])) {
						$this->session->set_flashdata('chit_alert', array('message' => 'Selected Category is no longer available. Please update the product before reactivating it.', 'class' => 'danger', 'title' => 'Product Status'));
						redirect('admin_ret_catalog/ret_product/list');
						return;
					}
					if (!empty($current_product['tgrp_id']) && !$this->$model->check_tax_group_active($current_product['tgrp_id'])) {
						$this->session->set_flashdata('chit_alert', array('message' => 'Selected Tax Group is no longer available. Please update the product before reactivating it.', 'class' => 'danger', 'title' => 'Product Status'));
						redirect('admin_ret_catalog/ret_product/list');
						return;
					}
				}

				$data = array(
					'product_status' => $status,

					'updated_time'	  => date("Y-m-d H:i:s"),

					'updated_by'      => $this->session->userdata('uid')
				);

				$model = self::CAT_MODEL;

				$this->db->trans_begin();
				$updstatus = $this->$model->updateData($data, 'pro_id', $id, 'ret_product_master');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Product status updated as ' . ($status == 1 ? 'Active' : 'In Active') . ' successfully.', 'class' => 'success', 'title' => 'Product Status'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Product Status'));
				}

				redirect('admin_ret_catalog/ret_product/list');

				break;

			case 'active_list':

				$data = $this->$model->getActiveProduct();

				echo json_encode($data);

				break;

			case 'list':

				$data['main_content'] = "master/ret_product/list";

				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_product/list');

				//	print_r($data);exit;

				$this->load->view('layout/template', $data);

				break;

			case 'active_metal':

				$data = $this->$model->getActiveMetal();

				echo json_encode($data);

				break;

			default:



				$range['from_date']  = $this->input->post('from_date');

				$range['to_date']  = $this->input->post('to_date');

				$product = $this->$model->ajax_get_retProduct($range['from_date'], $range['to_date']);

				$this->load->library('phpqrcode/qrlib');
				$SERVERFILEPATH = 'assets/img/product_qrcode/';
				if (!is_dir($SERVERFILEPATH)) {
					mkdir($SERVERFILEPATH, 0777, TRUE);
				}
				foreach ($product as &$p) {
					$file_name1 = $p['pro_id'] . ".png";
					$file_name = $SERVERFILEPATH . $file_name1;
					if (!file_exists($file_name)) {
						$qr_data = !empty($p['product_short_code']) ? $p['product_short_code'] : $p['pro_id'];
						QRcode::png($qr_data, $file_name);
					}
					$p['qrcode'] = base_url($file_name);
				}
				unset($p);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_product/list');

				$data = array(

					'product' => $product,

					'access' => $access

				);

				echo json_encode($data);
		}
	}



	public function bulkprodupdated($type = "", $id = "", $status = "", $tax_group_id = "")
	{

		$model = self::CAT_MODEL;

		switch ($type) {

			case "update":

				$this->db->trans_begin();

				if (isset($_POST['product_ids'])) {

					foreach ($_POST['product_ids'] as $prod_id) {

						if (isset($_POST['status'])) {

							$data['product_status'] = $_POST['status'];
						}

						if (!empty($_POST['tax_grp'])) {

							$data['tax_group_id'] = $_POST['tax_grp'];
						}

						$data['updated_time'] = date("Y-m-d H:i:s");

						$data['updated_by'] = $this->session->userdata('uid');



						$this->$model->updateData($data, 'pro_id', $prod_id, 'ret_product_master');
					}
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$result = array('message' => 'Bulk Product record modified successfully', 'class' => 'success', 'title' => 'Edit Bulk Product');
				} else {

					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Edit Bulk Product');
				}

				return $result;

				break;

			case 'list':

				$data['main_content'] = "master/ret_product/bulk_prod_upd";

				$this->load->view('layout/template', $data);

				break;

			default:

				$SETT_MOD = self::SETT_MOD;

				$pro_id             = $this->input->post('pro_id');

				$tax_group_id       = $this->input->post('tax_group_id');

				$product_status     = $this->input->post('product_status');

				$range['from_date'] = $this->input->post('from_date');

				$range['to_date']  	= $this->input->post('to_date');

				$product = $this->$model->ajax_get_TaxProd($pro_id, $tax_group_id, $product_status, $range['from_date'], $range['to_date']);

				$access = $this->$SETT_MOD->get_access('product/list');

				$data = array(

					'product' => $product,

					'access' => $access

				); //print_r($data);exit;

				echo json_encode($data);
		}
	}



	public function ret_sub_product($type = "", $id = "", $status = "")
	{

		$model = self::CAT_MODEL;

		$this->load->helper('lmx/core/access_helper');

		switch ($type) {

			case "add":

				$data['sub_product'] = $this->$model->get_empty_subproduct();

				$data['main_content'] = "master/ret_sub_product/form";

				$this->load->view('layout/template', $data);

				break;

			case "save":

				if (!lmx_can($this, 'admin_ret_catalog/ret_sub_product/list', 'add')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Add Sub Product'));
					redirect('admin_ret_catalog/ret_sub_product/list');
					return;
				}

				$addData = $_POST['sub_product'];

				// Known gap fix: neither required nor duplicate checks existed
				// on this field before.
				if (empty($addData['sub_pro_name']) || trim($addData['sub_pro_name']) === '') {
					$this->session->set_flashdata('chit_alert', array('message' => 'Sub Product Name is required', 'class' => 'danger', 'title' => 'Add Sub Product'));
					redirect('admin_ret_catalog/ret_sub_product/list');
					return;
				}
				if ($this->$model->check_sub_product_name_exists($addData['sub_pro_name'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Sub Product Name already exists', 'class' => 'danger', 'title' => 'Add Sub Product'));
					redirect('admin_ret_catalog/ret_sub_product/list');
					return;
				}
				// Known gap fix: tax_group_id is chosen from a dropdown of active
				// tax groups, but a stale selection (deactivated in another tab
				// after the dropdown loaded) was never re-checked before save.
				if (!empty($addData['tax_group_id']) && !$this->$model->check_tax_group_active($addData['tax_group_id'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Selected Tax Group is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Add Sub Product'));
					redirect('admin_ret_catalog/ret_sub_product/list');
					return;
				}

				$data = array(

					'less_tax'	           => (!empty($addData['less_tax']) ? $addData['less_tax'] : 0),

					'wastage_billing'      => (!empty($addData['wastage_billing']) ? $addData['wastage_billing'] : 0),

					'stock_type'           => (!empty($addData['stock_type']) ? $addData['stock_type'] : 1),

					'sales_mode'   		   => (!empty($addData['sales_mode']) ? $addData['sales_mode'] : 1),

					'wastage_type'   	   => (!empty($addData['wastage_type']) ? $addData['wastage_type'] : 1),

					'other_materials'      => (!empty($addData['other_materials']) ? $addData['other_materials'] : 0),

					'has_stone'		       => (!empty($addData['has_stone']) ? $addData['has_stone'] : 0),

					'metal_type'		   => (!empty($addData['metal_type']) ? $addData['metal_type'] : 0),

					'sub_pro_code'         => (!empty($addData['sub_pro_code']) ? $addData['sub_pro_code'] : NULL),

					'sub_pro_name'		   => (!empty($addData['sub_pro_name']) ? $addData['sub_pro_name'] : NULL),

					'has_size'		       => (!empty($addData['has_size']) ? $addData['has_size'] : 0),

					'less_stone_wt'		   => (!empty($addData['less_stone_wt']) ? $addData['less_stone_wt'] : 0),

					'tag_split'		       => (!empty($addData['tag_split']) ? $addData['tag_split'] : 0),

					'tag_merge'		       => (!empty($addData['tag_merge']) ? $addData['tag_merge'] : 0),

					'tag_type'		       => (!empty($addData['tag_type']) ? $addData['tag_type'] : 0),

					'other_charges'		   => (!empty($addData['other_charges']) ? $addData['other_charges'] : 0),

					'net_wt'		       => (!empty($addData['net_wt']) ? $addData['net_wt'] : 0),

					'stock_report'		   => (!empty($addData['stock_report']) ? $addData['stock_report'] : 0),

					'central_exces_duty'   => (!empty($addData['central_exces_duty']) ? $addData['central_exces_duty'] : 0),

					'no_of_pieces'		   => (!empty($addData['no_of_pieces']) ? $addData['no_of_pieces'] : NULL),

					'rfid_required'		   => (!empty($addData['rfid_required']) ? $addData['rfid_required'] : 0),

					'rfid_in_stock'		   => (!empty($addData['rfid_in_stock']) ? $addData['rfid_in_stock'] : 0),

					'hallmark'		       => (!empty($addData['hallmark']) ? $addData['hallmark'] : 0),

					'counter'		       => (!empty($addData['counter']) ? $addData['counter'] : 1),

					'stone_board_rate_cal' => (!empty($addData['stone_board_rate_cal']) ? $addData['stone_board_rate_cal'] : 0),

					'calculation_based_on' => (!empty($addData['calculation_based_on']) ? $addData['calculation_based_on'] : NULL),

					'tax_group_id'		                       => (!empty($addData['tax_group_id']) ? $addData['tax_group_id'] : NULL),

					'sub_pro_status'		                   => 1,

					'created_time'                             => date("Y-m-d H:i:s"),

					'create_by'                                => $this->session->userdata('uid')

				);

				$this->db->trans_begin();

				$id_karigar = $this->$model->insertData($data, 'ret_sub_product_master');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'New SubProduct added successfully', 'class' => 'success', 'title' => 'Add SubProduct'));

					echo 1;
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Add SubProduct'));

					echo 0;
				}

				redirect('admin_ret_catalog/ret_sub_product/list');

				break;

			case "edit":

				$data['sub_product'] = $this->$model->get_ret_subproduct($id);

				$data['main_content'] = "master/ret_sub_product/form";

				$this->load->view('layout/template', $data);

				break;

			case 'delete':

				if (!lmx_can($this, 'admin_ret_catalog/ret_sub_product/list', 'delete')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Delete Sub Product'));
					redirect('admin_ret_catalog/ret_sub_product/list');
					return;
				}

				$this->db->trans_begin();

				$this->$model->deleteData('sub_pro_id', $id, 'ret_sub_product_master');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'SubProduct deleted successfully', 'class' => 'success', 'title' => 'Delete Subproduct'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Delete Subproduct'));
				}

				redirect('admin_ret_catalog/ret_sub_product/list');

				break;



			case "update":

				if (!lmx_can($this, 'admin_ret_catalog/ret_sub_product/list', 'edit')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Edit Sub Product'));
					redirect('admin_ret_catalog/ret_sub_product/list');
					return;
				}

				$updData = $_POST['sub_product'];

				// Known gap fix: see the matching checks in the "save" case above.
				if (empty($updData['sub_pro_name']) || trim($updData['sub_pro_name']) === '') {
					$this->session->set_flashdata('chit_alert', array('message' => 'Sub Product Name is required', 'class' => 'danger', 'title' => 'Edit Sub Product'));
					redirect('admin_ret_catalog/ret_sub_product/list');
					return;
				}
				if ($this->$model->check_sub_product_name_exists($updData['sub_pro_name'], $id)) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Sub Product Name already exists', 'class' => 'danger', 'title' => 'Edit Sub Product'));
					redirect('admin_ret_catalog/ret_sub_product/list');
					return;
				}
				if (!empty($updData['tax_group_id']) && !$this->$model->check_tax_group_active($updData['tax_group_id'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Selected Tax Group is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Edit Sub Product'));
					redirect('admin_ret_catalog/ret_sub_product/list');
					return;
				}

				$data = array(

					'less_tax'	           => (!empty($updData['less_tax']) ? $updData['less_tax'] : 0),

					'wastage_billing'      => (!empty($updData['wastage_billing']) ? $updData['wastage_billing'] : 0),

					'stock_type'           => (!empty($updData['stock_type']) ? $updData['stock_type'] : 1),

					'sales_mode'   		   => (!empty($updData['sales_mode']) ? $updData['sales_mode'] : 1),

					'wastage_type'   	   => (!empty($updData['wastage_type']) ? $updData['wastage_type'] : 1),

					'other_materials'      => (!empty($updData['other_materials']) ? $updData['other_materials'] : 0),

					'has_stone'		       => (!empty($updData['has_stone']) ? $updData['has_stone'] : 0),

					'metal_type'		   => (!empty($updData['metal_type']) ? $updData['metal_type'] : 0),

					'sub_pro_code'         => (!empty($updData['sub_pro_code']) ? $updData['sub_pro_code'] : NULL),

					'sub_pro_name'		   => (!empty($updData['sub_pro_name']) ? $updData['sub_pro_name'] : NULL),

					'has_size'		       => (!empty($updData['has_size']) ? $updData['has_size'] : 0),

					'less_stone_wt'		   => (!empty($updData['less_stone_wt']) ? $updData['less_stone_wt'] : 0),

					'tag_split'		       => (!empty($updData['tag_split']) ? $updData['tag_split'] : 0),

					'tag_merge'		       => (!empty($updData['tag_merge']) ? $updData['tag_merge'] : 0),

					'tag_type'		       => (!empty($updData['tag_type']) ? $updData['tag_type'] : 0),

					'other_charges'		   => (!empty($updData['other_charges']) ? $updData['other_charges'] : 0),

					'net_wt'		       => (!empty($updData['net_wt']) ? $updData['net_wt'] : 0),

					'stock_report'		   => (!empty($updData['stock_report']) ? $updData['stock_report'] : 0),

					'central_exces_duty'   => (!empty($updData['central_exces_duty']) ? $updData['central_exces_duty'] : 0),

					'no_of_pieces'		   => (!empty($updData['no_of_pieces']) ? $updData['no_of_pieces'] : NULL),

					'rfid_required'		   => (!empty($updData['rfid_required']) ? $updData['rfid_required'] : 0),

					'rfid_in_stock'		   => (!empty($updData['rfid_in_stock']) ? $updData['rfid_in_stock'] : 0),

					'hallmark'		       => (!empty($updData['hallmark']) ? $updData['hallmark'] : 0),

					'counter'		       => (!empty($updData['counter']) ? $updData['counter'] : 1),

					'stone_board_rate_cal' => (!empty($updData['stone_board_rate_cal']) ? $updData['stone_board_rate_cal'] : 0),

					'calculation_based_on' => (!empty($updData['calculation_based_on']) ? $updData['calculation_based_on'] : NULL),

					'tax_group_id'		       => (!empty($updData['tax_group_id']) ? $updData['tax_group_id'] : NULL),

					'sub_pro_status'		       => (!empty($updData['sub_pro_status']) ? $updData['sub_pro_status'] : NULL),

					'updated_time'	           => date("Y-m-d H:i:s"),

					'updated_by'            => $this->session->userdata('uid')

				);



				$this->db->trans_begin();

				$this->$model->updateData($data, 'sub_pro_id', $id, 'ret_sub_product_master');



				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'SubProduct record modified successfully', 'class' => 'success', 'title' => 'Edit SubProduct'));

					echo 1;
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Edit Product'));

					echo 0;
				}

				redirect('admin_ret_catalog/ret_sub_product/list');

				break;

			case 'update_status':



				$data = array(
					'sub_pro_status' => $status,

					'updated_time'	  => date("Y-m-d H:i:s"),

					'updated_by'      => $this->session->userdata('uid')
				);

				$model = self::CAT_MODEL;

				$this->db->trans_begin();
				$updstatus = $this->$model->updateData($data, 'sub_pro_id', $id, 'ret_sub_product_master');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'SubProduct status updated as ' . ($status == 1 ? 'Active' : 'In Active') . ' successfully.', 'class' => 'success', 'title' => 'SubProduct Status'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'SubProduct Status'));
				}

				redirect('admin_ret_catalog/ret_sub_product/list');

				break;

			case 'active_list':

				$data = $this->$model->getActiveSubProducts();

				echo json_encode($data);

				break;

			case 'list':

				$data['main_content'] = "master/ret_sub_product/list";

				$this->load->view('layout/template', $data);

				break;

			default:

				$SETT_MOD = self::SETT_MOD;

				$range['from_date']  = $this->input->post('from_date');

				$range['to_date']  = $this->input->post('to_date');

				$sub_product = $this->$model->ajax_get_retSubProduct($range['from_date'], $range['to_date']);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_sub_product/list');

				$data = array(

					'sub_product' => $sub_product,

					'access' => $access

				);

				echo json_encode($data);
		}
	}



	public function tgrp($type = "", $id = "", $status = "")
	{
		// active_tgrp is consumed by catalog_master.js's get_taxgroup() — a
		// globally-shared helper populating tax-group dropdowns across other
		// masters/forms (#tgrp_sel, #ed_tgrp_sel, #pur_tgrp_sel, etc.), not just
		// this master's own page. active_tgi has no other caller found in the
		// codebase, preserved defensively anyway. Everything else routes through
		// the generic engine.
		if ($type === 'active_tgrp' || $type === 'active_tgi') {
			$model = self::CAT_MODEL;
			echo json_encode($type === 'active_tgrp' ? $this->$model->getActivetgrp() : $this->$model->get_tgi());
			return;
		}
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'tgrp', $type, $id, $status);
	}



	public function metal($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'metal', $type, $id, $status);
	}



	function removeDesign_img($file = "", $id = "")
	{

		$folder =  self::IMG_PATH . "designs" . "/" . $id;

		$img_name =  $id . "_DESIGN.jpg";

		$path = $folder . "/" . $img_name;

		unset($path);

		$model = self::CAT_MODEL;

		$status = $this->$model->delete_designimage($file);

		if ($status) {

			echo "Picture removed successfully";
		}
	}



	public function get_hooktype()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->getHooktype();

		echo json_encode($data);
	}

	public function get_screwtype()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_screwtype();

		echo json_encode($data);
	}

	public function get_productData()

	{

		$model = self::CAT_MODEL;

		$id = $this->input->post('pro_id');

		$data = $this->$model->get_productData($id);

		echo json_encode($data);
	}





	public function ret_design($type = "", $id = "", $status = "")
	{

		$model = self::CAT_MODEL;

		$SETT_MOD = self::SETT_MOD;

		// I-GMSJ-005: same gap as category() — delete/update_status call
		// lmx_can() but this branch never loaded the helper for it, so both
		// actions fatal-errored silently in production mode with no DB change.
		$this->load->helper('lmx/core/access_helper');

		switch ($type) {

			case "add":

				$data['design'] = $this->$model->get_empty_design();

				$data['karigar'] = $this->$model->getkarigar();

				$data['purity'] = $this->$model->getpurity();

				$data['material'] = $this->$model->getmaterial();

				$data['stone'] = $this->$model->getstone();

				$data['size'] = $this->$model->getsize();

				$data['main_content'] = "master/ret_design/form";

				$this->load->view('layout/template', $data);

				break;

			case "save":

				// Known gap fix: this master had zero validation of any kind —
				// no permission gate, no required/duplicate check on
				// design_name (a whitespace-only name saved silently), no
				// staleness check on product_id.
				if (!lmx_can($this, 'admin_ret_catalog/ret_design/list', 'add')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Add Design'));
					redirect('admin_ret_catalog/ret_design/list');
					return;
				}

				$addData = $this->input->post('design');

				if (empty($addData['design_name']) || trim($addData['design_name']) === '') {
					$this->session->set_flashdata('chit_alert', array('message' => 'Design Name is required', 'class' => 'danger', 'title' => 'Add Design'));
					redirect('admin_ret_catalog/ret_design/list');
					return;
				}

				if ($this->$model->check_design_name_exists($addData['design_name'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Design Name already exists', 'class' => 'danger', 'title' => 'Add Design'));
					redirect('admin_ret_catalog/ret_design/list');
					return;
				}

				if (!empty($addData['product_id']) && !$this->$model->check_product_active($addData['product_id'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Selected Product is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Add Design'));
					redirect('admin_ret_catalog/ret_design/list');
					return;
				}

				//echo "<pre>"; print_r($addData);exit;

				$karigar     = [];

				$purity      = [];

				$size = [];

				$other_materials = [];

				$stone = [];

				if ($this->input->post('karigars') != '') {

					$karigar     = explode(',', $this->input->post('karigars'));
				}

				if ($this->input->post('purity') != '') {

					$purity          = explode(',', $this->input->post('purity'));
				}

				if ($this->input->post('materials') != '') {

					$other_materials = explode(',', $this->input->post('materials'));
				}

				if ($this->input->post('size') != '') {

					$size            = $this->input->post('size');
				}

				if ($this->input->post('stone') != '') {

					$stone   = $this->input->post('stone');
				}

				$design_code = $this->$model->genDesignShortCode();

				$data['design'] = array(

					'design_status'        => (isset($addData['design_status']) ? $addData['design_status'] : NULL),

					'design_code'		   => $design_code,

					'design_name'		   => (!empty($addData['design_name']) ? strtoupper($addData['design_name']) : NULL),

					'product_id'		   => (!empty($addData['product_id']) ? $addData['product_id'] : NULL),

					'theme'		           => (!empty($addData['theme']) ? $addData['theme'] : NULL),

					'hook_type'		       => (!empty($addData['hook_type']) ? $addData['hook_type'] : NULL),

					'screw_type'		   => (!empty($addData['screw_type']) ? $addData['screw_type'] : NULL),

					'design_for'		   => (!empty($addData['design_for']) ? $addData['design_for'] : NULL),

					'min_length'		   => (!empty($addData['min_length']) ? $addData['min_length'] : NULL),

					'max_length'		   => (!empty($addData['max_length']) ? $addData['max_length'] : NULL),

					'min_width'		       => (!empty($addData['min_width']) ? $addData['min_width'] : NULL),

					'max_width'            => (!empty($addData['max_width']) ? $addData['max_width'] : NULL),

					'min_dia'		       => (!empty($addData['min_dia']) ? $addData['min_dia'] : NULL),

					'max_dia'		       => (!empty($addData['max_dia']) ? $addData['max_dia'] : NULL),

					'min_weight'		   => (!empty($addData['min_weight']) ? $addData['min_weight'] : NULL),

					'max_weight'		   => (!empty($addData['max_weight']) ? $addData['max_weight'] : NULL),

					'fixed_rate'		   => (!empty($addData['fixed_rate']) ? $addData['fixed_rate'] : NULL),

					'fixed_rate'		   => (!empty($addData['fixed_rate']) ? $addData['fixed_rate'] : NULL),

					'id_size'               => (!empty($addData['id_size']) ? $addData['id_size'] : NULL),

					'created_time'         => date("Y-m-d H:i:s"),

					'create_by'            => $this->session->userdata('uid')

				);

				$design_name_check = $data['design']['design_name'];
				if (!empty($design_name_check)) {
					$check_query = $this->db->get_where('ret_design_master', array('design_name' => $design_name_check));
					if ($check_query->num_rows() > 0) {
						$this->session->set_flashdata('chit_alert', array('message' => 'Design Name "' . $design_name_check . '" already exists. Please enter a different name.', 'class' => 'danger', 'title' => 'Duplicate Entry'));
						redirect('admin_ret_catalog/ret_design/add');
						return;
					}
				}

				$this->db->trans_begin();

				$id = $this->$model->insertData($data['design'], 'ret_design_master');

				//print_r($this->db->last_query());exit;

				if ($id > 0) {

					if (sizeof($karigar) > 0) {

						foreach ($karigar as $k) {

							$data['karigar'] = array(

								'karigar_id'    => (isset($k) ? ($k) : NULL),

								'design_id'    =>   $id

							);

							$result = $this->$model->insertData($data['karigar'], 'ret_design_karigars');
						}
					}

					if (sizeof($purity) > 0) {

						foreach ($purity as $k) {

							$data['purity'] = array(

								'design_id'		    =>   $id,

								'pur_id'			=> (isset($k) ? ($k) : NULL)

							);

							$result = $this->$model->insertData($data['purity'], 'ret_design_purity');
						}
					}

					if (sizeof($other_materials) > 0) {

						foreach ($other_materials as $k) {

							$data['material'] = array(

								'design_id'		    =>   $id,

								'material_id'		=> (isset($k) ? ($k) : NULL)

							);

							$result = $this->$model->insertData($data['material'], 'ret_design_other_materials');
						}
					}

					if (sizeof($size) > 0) {

						foreach ($size as $det) {

							$data['size'] = array(

								'design_id'		    =>   $id,

								'size'			    => (isset($det['size']) ? ($det['size']) : NULL),

								'uom_id'			=> (isset($det['uom_id']) ? ($det['uom_id']) : NULL)

							);

							$result = $this->$model->insertData($data['size'], 'ret_design_sizes');
						}
					}

					if (sizeof($stone) > 0) {

						foreach ($stone as $det) {

							$data['stone'] = array(

								'design_id'		    =>   $id,

								'stone_id'			=> (isset($det['stone_id']) ? $det['stone_id'] : NULL),

								'stone_pcs'			=> (isset($det['stone_pcs']) ? $det['stone_pcs'] : NULL)

							);

							$result = $this->$model->insertData($data['stone'], 'ret_design_stone');
						}
					}
				}

				if (!empty($_FILES)) {

					$img_arr = array();

					if ($id > 0) {

						$folder =  self::IMG_PATH . "designs/" . $id;



						if (!is_dir($folder)) {

							mkdir($folder, 0777, TRUE);
						}

						//upload default image

						if ($_FILES['default_img']) {



							if ($_FILES['default_img']['name']) {



								$img_name =  $id . "_DESIGN" . mt_rand(120, 1230) . ".jpg";

								$path = $folder . "/" . $img_name;

								$img = $_FILES['default_img']['tmp_name'];

								$result = $this->upload_img('image', $path, $img);

								$data = array(

									'image'		 => $img_name,

									'id_design'  =>  $id,

									'date_add'	 => date("Y-m-d H:i:s"),

									'is_default' => 0

								);

								$this->$model->insertData($data, 'ret_design_images');
							}
						}

						//remove default image from array

						unset($_FILES['default_img']);



						if (!empty($_FILES)) {

							foreach ($_FILES as $file) {

								if ($file['name']) {

									// unlink($folder."/".$product['image']);

									$img_name =  $id . "_DESIGN" . mt_rand(120, 1230) . ".jpg";

									$path = $folder . "/" . $img_name;

									$img = $file['tmp_name'];

									$result = $this->upload_img('image', $path, $img);

									if ($result) {

										$img_arr[] = $img_name;
									}
								}
							}
						}
					}

					if (!empty($img_arr)) {

						foreach ($img_arr as $img) {

							$data = array(

								'image'		 => $img,

								'id_design' => $id,

								'date_add'	 => date("Y-m-d H:i:s")

							);

							$this->$model->insertData($data, 'ret_design_images');
						}
					}
				}



				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Design',

						'operation'     => 'Add',

						'record'        =>  $id,

						'remark'        => 'Design added successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'New Design added successfully', 'class' => 'success', 'title' => 'Add Design'));

					//echo 1;

				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Add Design'));

					//echo 0;

				}

				redirect('admin_ret_catalog/ret_design/list');

				break;

			case "edit":

				$edit     = $this->$model->get_ret_design($id);

				$karigar  = json_encode($this->$model->get_design_karigar($id));

				$material = json_encode($this->$model->get_design_material($id));

				$purity   = json_encode($this->$model->get_design_purity($id));

				$size     = $this->$model->get_design_size($id);

				$data['images'] = $this->$model->get_designimage($id);

				$data['design'] = array(

					'design_no' 		   => (!empty($edit['design_no']) ? $edit['design_no'] : NULL),

					'default_img' 		   => (!empty($edit['default_img']) ? $edit['default_img'] : NULL),

					'design_status'        => (!empty($edit['design_status']) ? $edit['design_status'] : NULL),

					'design_code'		   => (!empty($edit['design_code']) ? $edit['design_code'] : NULL),

					'design_name'		   => (!empty($edit['design_name']) ? strtoupper($edit['design_name']) : NULL),

					'product_id'		   => (!empty($edit['product_id']) ? $edit['product_id'] : NULL),

					'theme'		           => (!empty($edit['theme']) ? $edit['theme'] : NULL),

					'hook_type'		       => (!empty($edit['hook_type']) ? $edit['hook_type'] : NULL),

					'screw_type'		   => (!empty($edit['screw_type']) ? $edit['screw_type'] : NULL),

					'design_for'		   => (!empty($edit['design_for']) ? $edit['design_for'] : NULL),

					'min_length'		   => (!empty($edit['min_length']) ? $edit['min_length'] : NULL),

					'max_length'		   => (!empty($edit['max_length']) ? $edit['max_length'] : NULL),

					'min_width'		       => (!empty($edit['min_width']) ? $edit['min_width'] : NULL),

					'max_width'            => (!empty($edit['max_width']) ? $edit['max_width'] : NULL),

					'min_dia'		       => (!empty($edit['min_dia']) ? $edit['min_dia'] : NULL),

					'max_dia'		       => (!empty($edit['max_dia']) ? $edit['max_dia'] : NULL),

					'min_weight'		   => (!empty($edit['min_weight']) ? $edit['min_weight'] : NULL),

					'max_weight'		   => (!empty($edit['max_weight']) ? $edit['max_weight'] : NULL),

					'fixed_rate'		   => (!empty($edit['fixed_rate']) ? $edit['fixed_rate'] : NULL),

					'usage_type'           => (!empty($edit['usage_type']) ? $edit['usage_type'] : NULL),

					'id_size'              => (!empty($edit['id_size']) ? $edit['id_size'] : NULL),

					'mc_cal_type'          => $edit['mc_cal_type'],

					'mc_cal_value'         => !empty($edit['mc_cal_value']) ? $edit['mc_cal_value'] : 0,

					'wastag_value'         => !empty($edit['wastag_value']) ? $edit['wastag_value'] : 0,

				);

				$data['image']    = array('image'        => (!empty($image['image']) ? $image['image'] : NULL));

				$data['karigar']  = array('karigar_id'   => (!empty($karigar) ? $karigar : NULL));

				$data['material'] = array('material_id'  => (!empty($material) ? $material : NULL));

				$data['purity']   = array('pur_id'       => (!empty($purity) ? $purity : NULL));

				$data['size']     = array('uom_id'       => (!empty($size['uom_id']) ? $size['uom_id'] : NULL));

				//echo "<pre>"; print_r($data);exit;

				$data['main_content'] = "master/ret_design/form";

				$this->load->view('layout/template', $data);

				break;

			case 'delete':

				if (!lmx_can($this, 'admin_ret_catalog/ret_design/list', 'delete')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Delete Design'));
					redirect('admin_ret_catalog/ret_design/list');
					return;
				}

				$isDesignsExists = $this->$model->getItemsinTagDetails('', '', '', $id, '', '');
				$this->db->trans_begin();
				if ($isDesignsExists == 0) {
					// Masters never hard-delete — deactivate instead, same as
					// every other master.
					$this->$model->updateData(
						array(
							'design_status' => 0,
							'updated_time'  => date("Y-m-d H:i:s"),
							'updated_by'    => $this->session->userdata('uid')
						),
						'design_no',
						$id,
						'ret_design_master'
					);

					if ($this->db->trans_status() === TRUE) {

						$this->db->trans_commit();

						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'Design',

							'operation'     => 'Deactivate',

							'record'        =>  $id,

							'remark'        => 'Design deactivated successfully'

						);

						$this->log_model->log_detail('insert', '', $log_data);

						$this->session->set_flashdata('chit_alert', array('message' => 'Design deactivated successfully', 'class' => 'success', 'title' => 'Deactivate Design'));

					} else {

						$this->db->trans_rollback();

						$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Deactivate Design'));

					}
				} else {
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to deactivate design. It is already in use.', 'class' => 'danger', 'title' => 'Deactivate Design'));
				}

				redirect('admin_ret_catalog/ret_design/list');

				break;



			case "update":

				if (!lmx_can($this, 'admin_ret_catalog/ret_design/list', 'edit')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Edit Design'));
					redirect('admin_ret_catalog/ret_design/list');
					return;
				}

				$updData = $this->input->post('design');

				if (empty($updData['design_name']) || trim($updData['design_name']) === '') {
					$this->session->set_flashdata('chit_alert', array('message' => 'Design Name is required', 'class' => 'danger', 'title' => 'Edit Design'));
					redirect('admin_ret_catalog/ret_design/list');
					return;
				}

				if ($this->$model->check_design_name_exists($updData['design_name'], $id)) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Design Name already exists', 'class' => 'danger', 'title' => 'Edit Design'));
					redirect('admin_ret_catalog/ret_design/list');
					return;
				}

				if (!empty($updData['product_id']) && !$this->$model->check_product_active($updData['product_id'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Selected Product is no longer available. Please refresh and choose again.', 'class' => 'danger', 'title' => 'Edit Design'));
					redirect('admin_ret_catalog/ret_design/list');
					return;
				}

				/*echo "<pre>";

				    print_r($updData);

				    echo "</pre>";exit;*/

				$karigar_ids       = explode(',', $this->input->post('karigars'));

				$purity            = explode(',', $this->input->post('purity'));

				$other_materials   = explode(',', $this->input->post('materials'));

				$size              = $this->input->post('size');

				$stone             = $this->input->post('stone');

				$data['design'] = array(

					'design_status'          => (isset($updData['design_status']) && $updData['design_status'] !== '' ? $updData['design_status'] : 0),

					//'design_code'		   => (!empty($updData['design_code']) ? $updData['design_code'] :NULL ),

					'design_name'		   => (!empty($updData['design_name']) ? strtoupper($updData['design_name']) : NULL),

					//'product_id'		   => (!empty($updData['product_id']) ? $updData['product_id'] :NULL ),

					'theme'		           => (!empty($updData['theme']) ? ($updData['theme'] != '' ? $updData['theme'] : NULL) : NULL),

					'hook_type'		       => (!empty($updData['hook_type']) ? ($updData['hook_type'] != '' ? $updData['hook_type'] : NULL) : NULL),

					'screw_type'		   => (!empty($updData['screw_type']) ? ($updData['screw_type'] != '' ? $updData['screw_type'] : NULL) : NULL),

					'design_for'		   => (!empty($updData['design_for']) ? $updData['design_for'] : NULL),

					'min_length'		   => (!empty($updData['min_length']) ? $updData['min_length'] : NULL),

					'max_length'		   => (!empty($updData['max_length']) ? $updData['max_length'] : NULL),

					'min_width'		       => (!empty($updData['min_width']) ? $updData['min_width'] : NULL),

					'max_width'            => (!empty($updData['max_width']) ? $updData['max_width'] : NULL),

					'min_dia'		       => (!empty($updData['min_dia']) ? $updData['min_dia'] : NULL),

					'max_dia'		       => (!empty($updData['max_dia']) ? $updData['max_dia'] : NULL),

					'min_weight'		   => (!empty($updData['min_weight']) ? $updData['min_weight'] : NULL),

					'max_weight'		   => (!empty($updData['max_weight']) ? $updData['max_weight'] : NULL),

					'fixed_rate'		   => (!empty($updData['fixed_rate']) ? $updData['fixed_rate'] : NULL),

					'usage_type'           => (!empty($updData['usage_type']) ? $updData['usage_type'] : NULL),

					'id_size'              => (!empty($updData['id_size']) ? $updData['id_size'] : NULL),

					'updated_time'	       => date("Y-m-d H:i:s"),

					'updated_by'           => $this->session->userdata('uid')

				);

				$design_name_check = $data['design']['design_name'];
				if (!empty($design_name_check)) {
					$check_query = $this->db->get_where('ret_design_master', array('design_name' => $design_name_check, 'design_no !=' => $id));
					if ($check_query->num_rows() > 0) {
						$this->session->set_flashdata('chit_alert', array('message' => 'Design Name "' . $design_name_check . '" already exists. Please enter a different name.', 'class' => 'danger', 'title' => 'Duplicate Entry'));
						redirect('admin_ret_catalog/ret_design/edit/' . $id);
						return;
					}
				}

				$this->db->trans_begin();

				$id = $this->$model->updateData($data['design'], 'design_no', $id, 'ret_design_master');



				if ($id > 0) {

					$this->$model->deleteData('design_id', $id, 'ret_design_karigars');

					if ($karigar_ids[0] != '') {

						foreach ($karigar_ids as $k) {

							$karigar = array(

								'karigar_id'    => (isset($k) ? ($k) : NULL),

								'design_id'     =>   $id

							);

							$result = $this->$model->insertData($karigar, 'ret_design_karigars');
						}
					}

					$this->$model->deleteData('design_id', $id, 'ret_design_purity');

					if ($purity[0] != '') {

						foreach ($purity as $k) {

							$purity = array(

								'pur_id'			=> (isset($k) ? ($k) : NULL),

								'design_id'	        => $id

							);

							$result = $this->$model->insertData($purity, 'ret_design_purity');
						}
					}

					$this->$model->deleteData('design_id', $id, 'ret_design_other_materials');

					if ($other_materials[0] != '') {

						foreach ($other_materials as $k) {

							$other_material = array(

								'design_id'		    =>   $id,

								'material_id'		=> (isset($k) ? ($k) : NULL)

							);

							$result = $this->$model->insertData($other_material, 'ret_design_other_materials');
						}
					}

					if ($size[0] != '') {

						foreach ($size as $det) {

							$size = array(

								'design_id'		    =>   $id,

								'size'			    => (isset($det['size']) ? ($det['size']) : NULL),

								'uom_id'			=> (isset($det['uom_id']) ? ($det['uom_id']) : NULL)

							);

							$result = $this->$model->updateData($size, 'design_id', $id, 'ret_design_sizes');
						}
					}

					if ($stone[0] != '') {

						foreach ($stone as $det) {

							$stone = array(

								'design_id'		    =>   $id,

								'stone_id'			=> (isset($det['stone_id']) ? $det['stone_id'] : NULL),

								'stone_pcs'			=> (isset($det['stone_pcs']) ? $det['stone_pcs'] : NULL)

							);

							$result = $this->$model->updateData($stone, 'design_id', $id, 'ret_design_stone');
						}
					}
				}

				if (!empty($_FILES)) {

					$img_arr = array();



					if ($id > 0) {

						$folder =  self::IMG_PATH . "designs/" . $id;

						if (!is_dir($folder)) {

							mkdir($folder, 0777, TRUE);
						}

						//upload default image

						if ($_FILES['default_img']) {



							if ($_FILES['default_img']['name']) {

								$img_name =  $id . "_DESIGN" . mt_rand(120, 1230) . ".jpg";

								$path = $folder . "/" . $img_name;

								$img = $_FILES['default_img']['tmp_name'];

								$result = $this->upload_img('image', $path, $img);

								$data = array(

									'image'		 => $img_name,

									'id_design' => $id,

									'date_add'	 => date("Y-m-d H:i:s"),

									'is_default' => 0

								);

								$this->$model->insertData($data, 'ret_design_images');
							}
						}

						//remove default image from array

						unset($_FILES['default_img']);

						//check array and upload remaining images

						if (!empty($_FILES)) {

							foreach ($_FILES as $file) {

								if ($file['name']) {

									$img_name =  $id . "_DESIGN" . mt_rand(120, 1230) . ".jpg";

									$path = $folder . "/" . $img_name;

									$img = $file['tmp_name'];

									$result = $this->upload_img('image', $path, $img);

									if ($result) {

										$img_arr[] = $img_name;
									}
								}
							}
						}
					}

					if (!empty($img_arr)) {

						foreach ($img_arr as $img) {

							$data = array(

								'image'		 => $img,

								'id_design' => $id,

								'date_add'	 => date("Y-m-d H:i:s")

							);

							$this->$model->insertData($data, 'ret_design_images');
						}
					}
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Design',

						'operation'     => 'Update',

						'record'        =>  $id,

						'remark'        => 'Design Updated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Design record modified successfully', 'class' => 'success', 'title' => 'Edit Design'));

					//echo 1;

				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Edit Design'));

					//echo 0;

				}

				redirect('admin_ret_catalog/ret_design/list');

				break;

			case 'update_status':

				if (!lmx_can($this, 'admin_ret_catalog/ret_design/list', 'edit')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Design Status'));
					redirect('admin_ret_catalog/ret_design/list');
					return;
				}

				// Same dependency gate as Delete — deactivating a design
				// still in stock is exactly as unsafe as deleting it.
				if ($status == 0) {
					$isDesignsExists = $this->$model->getItemsinTagDetails('', '', '', $id, '', '');
					if ($isDesignsExists != 0) {
						$this->session->set_flashdata('chit_alert', array('message' => 'Unable to deactivate design. It is already in use.', 'class' => 'danger', 'title' => 'Design Status'));
						redirect('admin_ret_catalog/ret_design/list');
						return;
					}
				}

				$data = array(
					'design_status' => $status,

					'updated_time'	  => date("Y-m-d H:i:s"),

					'updated_by'      => $this->session->userdata('uid')
				);

				$model = self::CAT_MODEL;

				$this->db->trans_begin();
				$updstatus = $this->$model->updateData($data, 'design_no', $id, 'ret_design_master');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Design status updated as ' . ($status == 1 ? 'Active' : 'In Active') . ' successfully.', 'class' => 'success', 'title' => 'Design Status'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Design Status'));
				}

				redirect('admin_ret_catalog/ret_design/list');

				break;

			case 'active_designBySearch':

				$data = $this->$model->getSearchDesign($_POST['searchTxt'], $_POST['product_id']);
				echo json_encode($data);

				break;

			case 'list':

				$data['main_content'] = "master/ret_design/list";

				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_design/list');

				$this->load->view('layout/template', $data);

				break;

			case 'bulk_edit':

				$data['main_content'] = "master/ret_design/bulk_edit_list";

				$this->load->view('layout/template', $data);

				break;

			case 'ajax_get_retmaster':



				$retmater = $this->$model->ajax_get_retmaster();

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_design/list');

				$data = array(

					'masterdet' => $retmater,

					'access'    => $access

				);

				echo json_encode($retmater);

				break;

			case 'ajax_update_bulk_retdesign':

				$reqdata = $this->input->post('req_data');

				//echo "<pre>"; print_r($reqdata); echo "</pre>"; exit;

				$update_status = false;

				$attr_update_status = false;



				$mc_va_update = "";

				$attr_update = "";



				$this->db->trans_begin();

				foreach ($reqdata as $data) {

					$update_status 			= $data['mc_va_update'] == 1 ? false : true;

					$attr_update_status 	= $data['attribute_update'] == 1 ? false : true;



					$mc_va_update 		= $data['mc_va_update'];

					$attr_update  		= $data['attribute_update'];



					if ($mc_va_update == 1) {

						$update_mc_status = true;

						$employee = $this->session->userdata('uid');



						if ($data['mc_cal_type'] != 0 && $data['mc_cal_type'] != "") {

							$upd_data['mc_cal_type']   = $data['mc_cal_type'];
						}

						if ($data['mc_cal_value'] != "") {

							$upd_data['mc_cal_value']   = $data['mc_cal_value'];
						}



						if (trim($data['mc_min']) != "") {

							$upd_data['mc_min']   = trim($data['mc_min']);
						}

						if (trim($data['mc_max']) != "") {

							$upd_data['mc_max']   = trim($data['mc_max']);
						}



						if ($data['wastageType'] == 1 && $data['wastag_value'] != "") {

							$upd_data['wastag_value']   = $data['wastag_value'];
						}



						if (trim($data['wastag_min']) != "") {

							$upd_data['wastag_min']   = trim($data['wastag_min']);
						}

						if (trim($data['wastag_max']) != "") {

							$upd_data['wastag_max']   = trim($data['wastag_max']);
						}



						if (trim($data['margin_mrp']) != "") {

							$upd_data['margin_mrp']   = trim($data['margin_mrp']);
						} else if ($data['wastageType'] == 2) {

							foreach ($data['wastag_value'] as $wkey => $wval) {

								$mcrg_min 	= trim($wval['mcrg_min']) != "" ? trim($wval['mcrg_min']) : NULL;

								$mcrg_max 	= trim($wval['mcrg_max']) != "" ? trim($wval['mcrg_max']) : NULL;

								$wc_min 	= trim($wval['wc_min']) != "" ? trim($wval['wc_min']) : NULL;

								$wc_max 	= trim($wval['wc_max']) != "" ? trim($wval['wc_max']) : NULL;



								$wcweightranges = array(

									"id_sub_design_mapping" => $data['id_sub_design_mapping'],

									"wc_from_weight"  =>  $wval['from_wt'],

									"wc_to_weight"    =>  $wval['to_wt'],

									"wc_percent"     =>  $wval['wc_percent'],

									"mc"             =>  $wval['mc'],

									"mcrg_min"       =>  $mcrg_min,

									"mcrg_max"       =>  $mcrg_max,

									"wc_min"         =>  $wc_min,

									"wc_max"         =>  $wc_max

								);

								$wastage_id = $this->$model->insertData($wcweightranges, 'ret_design_weight_range_wc');

								$update_mc_status = $wastage_id > 0 ? true : false;

								if (!$update_mc_status)

									break;
							}
						}

						//echo "<pre>";print_r($upd_data);exit;

						$upd_data['wastage_type']   = $data['wastageType'];



						if ($update_mc_status) {

							$des_id = $this->$model->updateData($upd_data, 'id_sub_design_mapping', $data['id_sub_design_mapping'], 'ret_sub_design_mapping');

							$update_status = $des_id > 0 ? true : false;

							if (!$update_status)

								break;
						} else {

							$update_status = false;

							break;
						}
					} else if ($attr_update == 1) {

						if ($data['attribute_type'] == 1) {

							foreach ($data['attributes'] as $attrkey => $attrval) {

								$curr_des_attrs = $this->$model->get_design_attr_values($data['id_sub_design_mapping'], $attrval['attr_name']);



								$total_count_attrs = count($curr_des_attrs);



								if ($total_count_attrs > 0) {

									foreach ($curr_des_attrs as $curr_attrs) {

										$attr_update_status = $this->$model->deleteData('attr_des_id', $curr_attrs['attr_des_id'], 'ret_design_attributes');

										if (!$attr_update_status)

											break 3;
									}
								}



								$des_attrs = array(

									"id_sub_design_mapping"	=>	$data['id_sub_design_mapping'],

									"attr_id"  				=>  $attrval['attr_name'],

									"attr_val_id"    		=>  $attrval['attr_value'],

									'created_on'	  		=> 	date("Y-m-d H:i:s"),

									'created_by'      		=> 	$this->session->userdata('uid')

								);



								$attr_des_id = $this->$model->insertData($des_attrs, 'ret_design_attributes');

								$attr_update_status = $attr_des_id > 0 ? true : false;

								if (!$attr_update_status)

									break 2;
							}
						} else if ($data['attribute_type'] == 2) {

							$attr_update_status = $this->$model->deleteData('id_sub_design_mapping', $data['id_sub_design_mapping'], 'ret_design_attributes');

							if (!$attr_update_status)

								break;
						}
					}
				}



				if ($update_status && $attr_update_status) {

					$this->db->trans_commit();

					$msg = "";

					if ($mc_va_update == 1 && $attr_update == 1)

						$msg = 'MC / Wastage / Attributes updated successfully';

					else if ($mc_va_update == 1)

						$msg = 'MC / Wastage updated successfully';

					else if ($attr_update == 1)

						$msg = 'Attributes updated successfully';



					$status	= array('status' => true,	'msg' => $msg);
				} else {

					$this->db->trans_rollback();

					$status = array('status' => false, 'msg' => 'Unable to proceed your request');
				}

				echo json_encode($status);

				break;

			default:

				$SETT_MOD = self::SETT_MOD;

				$range['from_date']  = $this->input->post('from_date');

				$range['to_date']  = $this->input->post('to_date');

				$id_product  = $this->input->post('id_product');

				$design = $this->$model->ajax_get_design($range['from_date'], $range['to_date'], $id_product);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_design/list');

				$data = array(

					'design' => $design,

					'access' => $access

				);

				echo json_encode($data);
		}
	}



	public function screw($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'screw', $type, $id, $status);
	}



	public function hook($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'hook', $type, $id, $status);
	}

	public function financial_year($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'financial_year', $type, $id, $status);
	}



	function financial_status($status, $id)

	{

		$model = "ret_catalog_model";
		if ($status == 1) {
			$target_fy = $this->$model->get_finance_entry_records($id);
			$target_code = $target_fy['fin_year_code'];
			$newer_fy = $this->db->query(
				"SELECT COUNT(*) as cnt FROM ret_financial_year WHERE fin_year_code > " . (int)$target_code
			);
			if ($newer_fy->row()->cnt > 0) {
				$log_data = array(
					'id_log'        => $this->session->userdata('id_log'),
					'event_date'    => date("Y-m-d H:i:s"),
					'module'        => 'Financial Year',
					'operation'     => 'Activate Blocked',
					'record'        => $id,
					'remark'        => 'Attempted to revert to older FY (code=' . $target_code . ', name=' . $target_fy['fin_year_name'] . ') - BLOCKED'
				);
				$this->log_model->log_detail('insert', '', $log_data);
				$this->session->set_flashdata('chit_alert', array(
					'message' => 'Cannot activate this Financial Year. A newer Financial Year already exists. Reverting to an older Financial Year is not allowed.',
					'class'   => 'danger',
					'title'   => 'Financial Year'
				));
				redirect('admin_ret_catalog/financial_year/list');
				return;
			}
		}
		if ($status == 0) {
			$target_fy = $this->$model->get_finance_entry_records($id);
			$target_code = $target_fy['fin_year_code'];
			$newer_fy = $this->db->query(
				"SELECT COUNT(*) as cnt FROM ret_financial_year WHERE fin_year_code > " . (int)$target_code
			);
			if ($newer_fy->row()->cnt == 0) {
				$log_data = array(
					'id_log'        => $this->session->userdata('id_log'),
					'event_date'    => date("Y-m-d H:i:s"),
					'module'        => 'Financial Year',
					'operation'     => 'Deactivate Blocked',
					'record'        => $id,
					'remark'        => 'Attempted to deactivate latest FY (code=' . $target_code . ', name=' . $target_fy['fin_year_name'] . ') - BLOCKED'
				);
				$this->log_model->log_detail('insert', '', $log_data);
				$this->session->set_flashdata('chit_alert', array(
					'message' => 'Cannot deactivate the current Financial Year. The latest Financial Year must remain active.',
					'class'   => 'danger',
					'title'   => 'Financial Year'
				));
				redirect('admin_ret_catalog/financial_year/list');
				return;
			}
		}
		$data = array('fin_status' => $status);

		$this->db->trans_begin();
		$this->$model->setFinancialYearStatus();

		$updstatus = $this->$model->updateData($data, 'fin_id', $id, 'ret_financial_year');

		if ($this->db->trans_status() === TRUE) {
			$this->db->trans_commit();
			$this->session->set_flashdata('chit_alert', array('message' => 'Financial Year updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'Financial Year'));
		} else {
			$this->db->trans_rollback();
			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Financial Year'));
		}

		redirect('admin_ret_catalog/financial_year/list');
	}




	//Old Metal Rate

	/*	function old_metal_rate()

	{

		$model="ret_catalog_model";

		$id_metal=$this->input->post('id_metal');

		$id_branch=$this->input->post('id_branch');

		$data=array();

		$old_metal_rate=$this->$model->get_old_metal_rate($id_metal,$id_branch);

		$purities=$this->get_all_purity();



		foreach ($old_metal_rate as $rate) {

			$html='';

			foreach($purities as $purity)

			{

				$selected = ($purity['id_purity'] == $rate['id_purity']) ? 'selected="selected"' : '';

				 $html.= '<option value="' . $purity['id_purity'] . '"  ' .$selected. '>' . $purity['purity'] . '</option>';

			}

			$data[]=array(

						'id_old_metal_rate'=>$rate['id_old_metal_rate'],

						'metal'=>$rate['metal'],

						'metal_code'=>$rate['metal_code'],

						'id_metal'=>$rate['id_metal'],

						'rate'=>$rate['rate'],

						'id_purity'=>$rate['id_purity'],

						'html'		=>'<select class="form-control purity" id="' .$rate['id_old_metal_rate']. '">'.$html.'</select><input class="id_purity" type="hidden"  id="' .$rate['id_old_metal_rate']. '_id_purity" value="'.$rate['id_purity'].'">',

					   );

		}

		echo json_encode($data);

	}*/



	function update_rate_data()

	{

		$model     = "ret_catalog_model";

		$id_branch    = $this->input->post('id_branch');

		$id_metal    = $this->input->post('id_metal');

		$reqdata   = $this->input->post('req_data');



		if (!empty($reqdata) && sizeof($reqdata) > 0) {

			foreach ($reqdata as $data) {

				$employee = $this->session->userdata('uid');



				$this->db->trans_begin();

				if ($data['id_old_metal_rate'] == 'undefined' || $data['id_old_metal_rate'] == '') {



					$insdata = array(

						'rate' => $data['rate'],

						'id_metal' => $id_metal,

						'id_branch' => ($id_branch != '' ? $id_branch : NULL),

						'id_purity' => ($data['id_purity'] != '' ? $data['id_purity'] : NULL),

						'created_on' => date("Y-m-d H:i:s"),

						'created_by' => $employee,

						'status' => 1

					);

					$updStat = $this->$model->insertData($insdata, 'ret_old_metal_rate');
				} else {

					$upd_status = array('status' => 0);



					$update_status = $this->$model->updateData($upd_status, 'id_old_metal_rate', $data['id_old_metal_rate'], 'ret_old_metal_rate');



					$updatedata = array(

						'rate' => $data['rate'],

						'id_metal' => $id_metal,

						'id_branch' => ($id_branch != '' ? $id_branch : NULL),

						'id_purity' => ($data['id_purity'] != '' ? $data['id_purity'] : NULL),

						'status' => 1,

						'created_on' => date("Y-m-d H:i:s"),

						'created_by' => $employee,

					);



					$updStat = $this->$model->insertData($updatedata, 'ret_old_metal_rate');
				}
			}
		}



		if ($updStat) {

			$this->db->trans_commit();

			$this->session->set_flashdata('chit_alert', array('message' => 'Old Metal Rate  Added successfully.', 'class' => 'success', 'title' => 'Old Metal rate'));
		} else {

			$this->db->trans_rollback();
		}
	}



	function get_all_purity()

	{

		$model = "ret_catalog_model";

		$data = $this->$model->getActivePurity();

		return $data;
	}

	//Old Metal Rate



	//Material Rate



	function update_mrrate_data()

	{

		$model     = "ret_catalog_model";

		$material_id    = $this->input->post('material_id');

		$reqdata   = $this->input->post('req_data');



		if (!empty($reqdata) && sizeof($reqdata) > 0) {

			foreach ($reqdata as $data) {

				$employee = $this->session->userdata('uid');



				$this->db->trans_begin();

				if ($data['mat_rate_id'] == 'undefined' || $data['mat_rate_id'] == '') {



					$insdata = array(

						'mat_rate' => $data['mat_rate'],

						'material_id' => $material_id,

						'effective_date' => date("Y-m-d H:i:s"),

						'created_on' => date("Y-m-d H:i:s"),

						'created_by' => $employee,

						'status' => 1

					);

					$updStat = $this->$model->insertData($insdata, 'ret_material_rate');
				} else {

					$upd_status = array('status' => 0);



					$update_status = $this->$model->updateData($upd_status, 'mat_rate_id', $data['mat_rate_id'], 'ret_material_rate');



					$updatedata = array(

						'mat_rate' => $data['mat_rate'],

						'material_id' => $material_id,

						'status' => 1,

						'created_on' => date("Y-m-d H:i:s"),

						'effective_date' => date("Y-m-d H:i:s"),

						'created_by' => $employee,

					);



					$updStat = $this->$model->insertData($updatedata, 'ret_material_rate');
				}

				//echo $this->db->last_query();exit;

			}
		}



		if ($updStat) {

			$this->db->trans_commit();

			$log_data = array(

				'id_log'        => $this->session->userdata('id_log'),

				'event_date'    => date("Y-m-d H:i:s"),

				'module'        => 'Material Rate',

				'operation'     => 'Add',

				'record'        =>  $updStat,

				'remark'        => 'Material Rate added successfully'

			);

			$this->log_model->log_detail('insert', '', $log_data);

			$res = array('message' => 'Old Metal Rate  Added successfully.', 'class' => 'success', 'title' => 'Old Metal rate');
		} else {

			$this->db->trans_rollback();

			$res = array('message' => 'Old Metal Rate  Added successfully.', 'class' => 'success', 'title' => 'Old Metal rate');
		}

		echo json_encode($res);
	}



	function weight($type = "", $id = "", $status = "")

	{

		$model     = "ret_catalog_model";

		switch ($type) {

			case 'new':

				$data['main_content'] = "master/ret_weight/form";

				$this->load->view('layout/template', $data);

				break;

			case "Add":

				// Security: xss_clean + SQL/XSS check on text field
				$this->load->helper('customer_validation');
				$raw_desc = $this->input->post('weight_desc');
				if (!empty($raw_desc)) {
					$desc_check = sanitize_and_validate_text($this, $raw_desc, 'Weight Description', 100);
					if (!$desc_check['valid']) {
						echo json_encode(array('status' => false, 'msg' => implode(' ', $desc_check['errors'])));
						return;
					}
					$clean_desc = $desc_check['clean'];
				} else {
					$clean_desc = NULL;
				}

				$wt_data = array(

					'value'          => (float)$this->input->post('name'),

					'from_weight'   => (float)$this->input->post('from_weight'),

					'to_weight'     => (float)$this->input->post('to_weight'),

					'id_product'    => (int)$this->input->post('id_product'),

					/* 'id_design'     =>($this->input->post('id_design')!='' ? $this->input->post('id_design'):NULL),

                'id_sub_design' =>($this->input->post('id_sub_design')!='' ? $this->input->post('id_sub_design'):NULL),*/

					'id_uom'        => (int)$this->input->post('uom'),

					'weight_description'  => $clean_desc,

					'created_on'    => date("Y-m-d H:i:s"),

					'created_by'    => $this->session->userdata('uid')

				);

				$id_product = $this->input->post('id_product') ? (int)$this->input->post('id_product') : '';

				$from_weight = $this->input->post('from_weight') ? (float)$this->input->post('from_weight') : '';

				$to_weight = $this->input->post('to_weight') ? (float)$this->input->post('to_weight') : '';

				$value = $this->input->post('name') ? (float)$this->input->post('name') : '';

				$desc = $clean_desc !== NULL ? $clean_desc : '';

				$chkdn = $this->$model->check_weight_range($from_weight, $to_weight, $value, $id_product, $desc);

				if ($chkdn == 0) {

					$this->db->trans_begin();

					$Insid = $this->$model->insertData($wt_data, 'ret_weight');



					if ($this->db->trans_status() === TRUE) {

						$this->db->trans_commit();

						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'Weight Range',

							'operation'     => 'Add',

							'record'        =>  $Insid,

							'remark'        => 'Weight Range added successfully'

						);

						$this->log_model->log_detail('insert', '', $log_data);

						$data = array('status' => true, 'msg' => 'Weight Range Added Successfully', 'weight_id' => $Insid);

						// $data['weight']=$this->$model->get_weight($Insid);
					} else {

						$this->db->trans_rollback();

						$data = array('status' => false, 'msg' => 'Unable To Proceed Your request');
					}
				} else {

					$data = array('status' => false, 'msg' => 'Weight Range already Existed');

					echo json_encode($data);
					exit;
				}

				echo json_encode($data);

				break;

			case "Edit":

				$data['main_content'] = "master/ret_weight/form";

				$data['weight'] = $this->$model->get_weight($id);

				$this->load->view('layout/template', $data);

				break;

			case "weight_Edit":

				$data['weight'] = $this->$model->get_weight($id);

				echo json_encode($data);

				break;

			case "Delete":

				if (!lmx_can($this, 'admin_ret_catalog/weight/list', 'delete')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Delete Weight'));
					redirect('admin_ret_catalog/weight/list');
					return;
				}

				// I-GMSJ-005: masters never hard-delete — ret_weight already has an
				// 'active' column (previously unused by this action); deactivate
				// instead of removing the row so historical tag/estimation data that
				// references this weight range by id isn't left dangling.
				$this->db->trans_begin();
				$status = $this->$model->updateData(array('active' => 0), 'id_weight', $id, 'ret_weight');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Weight Range',

						'operation'     => 'Deactivate',

						'record'        =>  $id,

						'remark'        => 'Weight Range deactivated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Weight deactivated successfully', 'class' => 'success', 'title' => 'Delete Weight'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Delete Weight'));
				}

				redirect('admin_ret_catalog/weight/list');

				break;

			case "update_status":

				if (!lmx_can($this, 'admin_ret_catalog/weight/list', 'edit')) {
					echo json_encode(array('status' => false, 'msg' => 'Access Denied'));
					return;
				}

				// I-GMSJ-005: no reactivate path existed at all before this — once
				// Delete flips active=0 (see above), an admin needs a way back.
				$new_status = ($status == 1) ? 0 : 1;

				$this->db->trans_begin();
				$this->$model->updateData(array('active' => $new_status), 'id_weight', $id, 'ret_weight');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();

					$log_data = array(
						'id_log'        => $this->session->userdata('id_log'),
						'event_date'    => date("Y-m-d H:i:s"),
						'module'        => 'Weight Range',
						'operation'     => 'Status Change',
						'record'        => $id,
						'remark'        => 'Weight Range ' . ($new_status == 1 ? 'activated' : 'deactivated') . ' successfully',
					);
					$this->log_model->log_detail('insert', '', $log_data);

					echo json_encode(array('status' => true, 'msg' => 'Weight Range ' . ($new_status == 1 ? 'activated' : 'deactivated') . ' successfully'));
				} else {
					$this->db->trans_rollback();
					echo json_encode(array('status' => false, 'msg' => 'Unable to proceed the requested operation'));
				}

				break;

			case "Update":

				$data['weight'] = $this->$model->get_weight($id);

				// Security: xss_clean + SQL/XSS check on text field
				$this->load->helper('customer_validation');
				$raw_desc = $this->input->post('weight_desc');
				if (!empty($raw_desc)) {
					$desc_check = sanitize_and_validate_text($this, $raw_desc, 'Weight Description', 100);
					if (!$desc_check['valid']) {
						echo json_encode(array('status' => false, 'msg' => implode(' ', $desc_check['errors'])));
						return;
					}
					$clean_desc = $desc_check['clean'];
				} else {
					$clean_desc = NULL;
				}

				$wt_data = array(

					'value'         => (float)$this->input->post('name'),

					'from_weight'   => (float)$this->input->post('from_weight'),

					'to_weight'     => (float)$this->input->post('to_weight'),

					'id_product'    => (int)$this->input->post('id_product'),

					'id_uom'        => (int)$this->input->post('uom'),

					/*'id_design'     =>($this->input->post('id_design')!='' && $this->input->post('id_design')!=null ?$this->input->post('id_design'):NULL),

                'id_sub_design' =>($this->input->post('id_sub_design')!='' && $this->input->post('id_sub_design')!=null ?$this->input->post('id_sub_design'):NULL),*/

					'weight_description'  => $clean_desc,

					'updated_on'    => date("Y-m-d H:i:s"),

					'updated_by'    => $this->session->userdata('uid')

				);

				$chkdn = $this->$model->check_weight_range($this->input->post('from_weight'), $this->input->post('to_weight'), $this->input->post('name'), $this->input->post('id_product'), $this->input->post('weight_desc'), $id);

				if ($chkdn > 0) {
					$return_data = array('status' => false, 'msg' => 'Weight Range already Existed');
					echo json_encode($return_data);
					exit;
				}

				$this->db->trans_begin();

				$status = $this->$model->updateData($wt_data, 'id_weight', $id, 'ret_weight');

				//print_r($this->db->last_query());exit;

				if ($status) {

					$return_data = array('status' => true, 'msg' => 'Weight Updated Successfully', 'weight_id' => $id);
				} else {

					$return_data = array('status' => false, 'msg' => 'Unable to Proceed');
				}



				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Weight Range',

						'operation'     => 'Update',

						'record'        => $status,

						'remark'        => 'Weight Range Updated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Weight Updated Successfully', 'class' => 'success', 'title' => 'Weight Range'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to Proceed', 'class' => 'danger', 'title' => 'Weight Range'));
				}

				//redirect('admin_ret_catalog/weight/list');
				echo json_encode($return_data);

				break;

			case "list":

				$data['main_content'] = "master/ret_weight/list";

				$this->load->view('layout/template', $data);

				break;

			case "weight_details":

				$data = $this->$model->weight_details($id);

				echo json_encode($data);

				break;


			default:

				$SETT_MOD = self::SETT_MOD;

				$id_product = $this->input->post('id_product');

				$items = $this->$model->ajax_get_weights($id_product, '', '');

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/weight/list');

				$weights = array(

					'access' => $access,

					'data'   => $items

				);

				echo json_encode($weights);

				break;
		}
	}



	function get_weight_range_details()

	{

		$model = self::CAT_MODEL;

		$id_product = $this->input->post('id_product');

		$id_design = $this->input->post('id_design');

		$id_sub_design = $this->input->post('id_sub_design');

		$data = $this->$model->weight_details($id_product, $id_design, $id_sub_design);

		echo json_encode($data);
	}



	public function reorder_settings($type = "", $id = "", $status = "")
	{

		$model = self::CAT_MODEL;

		$set_model = 'admin_settings_model';

		switch ($type) {

			case 'new':

				$data['main_content'] = "master/ret_reorder_settings/form";

				$this->load->view('layout/template', $data);

				break;



			case "add":

				$addData = $_POST['settings'];

				$pieces = $_POST['pieces'];

				// Known gap fix: id_product/id_design/id_sub_design/id_branch are
				// all chosen from active-list dropdowns but were never re-checked
				// for staleness before save — validate the whole batch up front,
				// before any row is written.
				if (!empty($addData['id_product']) && !$this->$model->check_product_active($addData['id_product'])) {
					echo json_encode(array('status' => false, 'msg' => 'Selected Product is no longer available. Please refresh and choose again.'));
					return;
				}
				if (!empty($addData['id_design']) && !$this->$model->check_design_active($addData['id_design'])) {
					echo json_encode(array('status' => false, 'msg' => 'Selected Design is no longer available. Please refresh and choose again.'));
					return;
				}
				if (sizeof($pieces) > 0 && $addData['branch_settings'] == 1) {
					foreach ($pieces as $set) {
						foreach ($set['id_branch'] as $id_branch) {
							if (!$this->$model->check_branch_active($id_branch)) {
								echo json_encode(array('status' => false, 'msg' => 'One or more selected Branches are no longer available. Please refresh and choose again.'));
								return;
							}
						}
						foreach ($set['id_sub_design'] as $id_sub_design) {
							if (!empty($id_sub_design) && !$this->$model->check_sub_design_active($id_sub_design)) {
								echo json_encode(array('status' => false, 'msg' => 'One or more selected Sub Designs are no longer available. Please refresh and choose again.'));
								return;
							}
						}
					}
				} elseif (!empty($addData['id_sub_design']) && !$this->$model->check_sub_design_active($addData['id_sub_design'])) {
					echo json_encode(array('status' => false, 'msg' => 'Selected Sub Design is no longer available. Please refresh and choose again.'));
					return;
				}

				$this->db->trans_begin();

				//echo "<pre>"; print_r($_POST);exit;

				if (sizeof($pieces) > 0 && $addData['branch_settings'] == 1) {
					foreach ($pieces as $set) {

						foreach ($set['id_branch'] as $branchIndex  => $id_branch) {

							foreach ($set['id_sub_design'] as $sdesignIndex  => $id_sub_design) {

								//  $sub_design = $set['id_sub_design'][$index];
								//  $id_sub_design = (($sub_design!='') ? $sub_design : $set['id_sub_design'][0]);

								$checkItemExist = $this->$model->checkReorderItemExist($id_branch, $addData, $id_sub_design, $set);
								//  echo "<pre>"; print_r($checkItemExist);exit;
								if ((int)$checkItemExist == 0) {

									$insdata = array(
										'id_branch'     => $id_branch,
										'id_sub_design' => $id_sub_design,
										'id_product'    => $addData['id_product'],
										'id_design'     => $addData['id_design'],
										'id_section'    => $set['id_section'],
										'id_wt_range'   => $set['weight_range'],
										'max_pcs'       => $set['max_pcs'],
										'min_pcs'       => $set['min_pcs'],
										'size'          => $set['id_size'],
										'created_on'    => date("Y-m-d H:i:s"),
										'ref_no'        => time(),
										'created_by'    => $this->session->userdata('uid')
									);
									// print_r($insdata);exit;
									$insId = $this->$model->insertData($insdata, 'ret_reorder_settings');
								} else {

									$data = array('status' => false, 'msg' => 'Re-Order Settings Already exists');

									echo json_encode($data);

									return;
								}
							}
						}
					}
				} else {

					$insdata = array(

						'id_wt_range'	        => $addData['weight_range'],

						'id_product'            => $addData['id_product'],

						'id_design'	            => $addData['id_design'],

						'id_sub_design'	        => $addData['id_sub_design'],

						'id_section'	         => $addData['id_section'],

						'max_pcs'	            => $addData['max_pcs'],

						'min_pcs'	            => $addData['min_pcs'],

						'size'		            => $addData['id_size'],

						'ref_no'                 => time(),

						'created_on'	  		=> date("Y-m-d H:i:s"),

						'created_by'      		=> $this->session->userdata('uid')

					);

					$insId = $this->$model->insertData($insdata, 'ret_reorder_settings');

					// print_r($this->db->last_query());exit;


				}





				//print_r($this->db->last_query());exit;

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Re-Order Settings',

						'operation'     => 'Add',

						'record'        =>  $insId,

						'remark'        => 'Re-Order Settings added successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Re-Order Settings Added successfully', 'class' => 'success', 'title' => 'Settings'));

					$data = array('status' => true, 'msg' => 'Re-Order Settings Added Successfully');
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable To Proceed Your request', 'class' => 'success', 'title' => 'Settings'));

					$data = array('status' => false, 'msg' => 'Unable To Proceed Your request');
				}

				echo json_encode($data);

				break;

			case "edit":

				$data['main_content'] = "master/ret_reorder_settings/form";

				$ref_no = $this->$model->get_ref_no($id);

				$data['reorder'] = $this->$model->ajax_getreorder_settings($id, '', '', '', $ref_no);

				// echo json_encode($data['material']);

				$this->load->view('layout/template', $data);


				break;


			case "edit_reorder":
				$ref_no = $this->$model->get_ref_no($id);
				$data['reorder'] = $this->$model->ajax_getreorder_settings($id, '', '', '', $ref_no);
				$data['weight'] = $this->$model->ajax_get_weights('', '', '');
				$data['size'] = $this->$model->get_Activesize('');
				$data['section'] = $this->$model->get_section();


				echo json_encode($data);

				break;



			case 'delete':

				$this->db->trans_begin();

				$this->$model->deleteData('id_reorder_settings', $id, 'ret_reorder_settings');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();
					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Re-Order Settings',

						'operation'     => 'Delete',

						'record'        =>  $id,

						'remark'        => 'Re-Order Settings deleted successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Re-Order Settings deleted successfully', 'class' => 'success', 'title' => 'Settings'));

					redirect('admin_ret_catalog/reorder_settings/list');
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Settings'));

					redirect('admin_ret_catalog/reorder_settings/list');
				}

				redirect('admin_ret_catalog/reorder_settings/list');

				break;



			case "update":


				$addData = $_POST['settings'];

				$pieces = $_POST['pieces'];

				// Known gap fix: same staleness check as the 'add' case above —
				// the update path had none of these at all.
				if (!empty($addData['id_product']) && !$this->$model->check_product_active($addData['id_product'])) {
					echo json_encode(array('status' => false, 'msg' => 'Selected Product is no longer available. Please refresh and choose again.'));
					return;
				}
				if (!empty($addData['id_design']) && !$this->$model->check_design_active($addData['id_design'])) {
					echo json_encode(array('status' => false, 'msg' => 'Selected Design is no longer available. Please refresh and choose again.'));
					return;
				}
				if (sizeof($pieces) > 0 && $addData['branch_settings'] == 1) {
					foreach ($pieces as $set) {
						foreach ($set['id_branch'] as $id_branch) {
							if (!$this->$model->check_branch_active($id_branch)) {
								echo json_encode(array('status' => false, 'msg' => 'One or more selected Branches are no longer available. Please refresh and choose again.'));
								return;
							}
						}
						foreach ($set['id_sub_design'] as $id_sub_design) {
							if (!empty($id_sub_design) && !$this->$model->check_sub_design_active($id_sub_design)) {
								echo json_encode(array('status' => false, 'msg' => 'One or more selected Sub Designs are no longer available. Please refresh and choose again.'));
								return;
							}
						}
					}
				} elseif (!empty($addData['id_sub_design']) && !$this->$model->check_sub_design_active($addData['id_sub_design'])) {
					echo json_encode(array('status' => false, 'msg' => 'Selected Sub Design is no longer available. Please refresh and choose again.'));
					return;
				}

				$this->db->trans_begin();


				//  echo "<pre>"; print_r($_POST);exit;

				if (sizeof($pieces) > 0 && $addData['branch_settings'] == 1) {
					foreach ($pieces as $set) {

						foreach ($set['id_branch'] as $index => $id_branch) {

							foreach ($set['id_sub_design'] as $index => $id_sub_design) {


								//  $sub_design = $set['id_sub_design'][$index];

								//  $id_sub_design = (($sub_design!='') ? $sub_design : $set['id_sub_design'][0]);

								$id_reorder = $set['id_reorder_settings'];

								//  print_r($id_reorder);exit;


								if (!empty($id_reorder)) {

									$updateData = array(
										'id_branch'     => $id_branch,
										'id_sub_design' => $id_sub_design,
										'id_product'    => $addData['id_product'],
										'id_design'     => $addData['id_design'],
										'id_section'    => $set['id_section'],
										'id_wt_range'   => $set['weight_range'],
										'max_pcs'       => $set['max_pcs'],
										'min_pcs'       => $set['min_pcs'],
										'size'          => $set['id_size'],
										'updated_on'    => date("Y-m-d H:i:s"),
										// 'ref_no'        => time(),
										'updated_by'    => $this->session->userdata('uid')
									);
									// print_r($updateData);exit;

									// Known gap fix: checkReorderSettingsUpdateItemExist already
									// existed in the model but was never called from here — the
									// update path had no duplicate-check at all.
									if ($this->$model->checkReorderSettingsUpdateItemExist($id_reorder, $updateData) == 0) {
										echo json_encode(array('status' => false, 'msg' => 'Re-Order Settings Already exists'));
										$this->db->trans_rollback();
										return;
									}

									$id_reorder = $this->$model->updateData($updateData, 'id_reorder_settings', $id_reorder, 'ret_reorder_settings');

									// print_r($this->db->last_query());exit;

								} else {

									$data = array('status' => true, 'msg' => 'Re-Order Settings Alreday exists');
								}
							}
						}
					}
				} else {


					$UpdData = array(

						'id_wt_range'	        => $addData['weight_range'],

						'id_product'            => $addData['id_product'],

						'id_design'	            => $addData['id_design'],

						'id_sub_design'	        => $addData['id_sub_design'],

						'id_section'	         => $addData['id_section'],

						'max_pcs'	            => $addData['max_pcs'],

						'min_pcs'	            => $addData['min_pcs'],

						'size'		            => $addData['id_size'],

						// 'ref_no'                 => time(),

						'updated_on'	  		=> date("Y-m-d H:i:s"),

						'updated_by'      		=> $this->session->userdata('uid')

					);

					$id_reorder = $this->$model->updateData($UpdData, 'id_reorder_settings', $id, 'ret_reorder_settings');
				}


				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Re-Order Settings',

						'operation'     => 'Update',

						'record'        =>  $id_reorder,

						'remark'        => 'Re-Order Settings Updated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);



					$this->session->set_flashdata('chit_alert', array('message' => 'Re-Order Settings Updated successfully', 'class' => 'success', 'title' => 'Settings'));

					$data = array('status' => true, 'msg' => 'Re-Order Settings Updated Successfully');
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable To Proceed Your request', 'class' => 'success', 'title' => 'Settings'));

					$data = array('status' => true, 'msg' => 'Unable To Proceed Your request');
				}



				echo json_encode($data);


				break;

			case 'active_design':

				$data = $this->$model->ajax_get_design('', '', $_POST['id_product']);

				echo json_encode($data);

				break;

			case 'weight_range':

				$data = $this->$model->ajax_get_weights($_POST['id_product'], $_POST['id_design'], $_POST['id_sub_design']);

				echo json_encode($data);

				break;

			case 'list':

				$data['main_content'] = "master/ret_reorder_settings/list";

				$data['access'] = $this->$set_model->get_access('admin_ret_catalog/reorder_settings/list');

				$this->load->view('layout/template', $data);

				break;

			case 'update_status':

				$data = array('material_status' => $status, 'updated_on' => date("Y-m-d H:i:s"), 'updated_by' => $this->session->userdata('uid'));

				$this->db->trans_begin();
				$updstatus = $this->$model->updateData($data, 'material_id', $id, 'ret_material');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Material status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'Material  Status'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Material  Status'));
				}

				redirect('admin_ret_catalog/material/list');

				break;

			default:

				$SETT_MOD 	= self::SETT_MOD;

				$id_product = (isset($_POST['id_product']) ? $_POST['id_product'] : '');

				$id_design = (isset($_POST['id_design']) ? $_POST['id_design'] : '');

				$id_wt_range = (isset($_POST['id_wt_range']) ? $_POST['id_wt_range'] : '');

				$data 		= $this->$model->ajax_getreorderSett($_POST);

				$access 	= $this->$SETT_MOD->get_access('admin_ret_catalog/reorder_settings/list');

				$data 		= array(

					'responseData' => $data,

					'access' => $access

				);

				echo json_encode($data);
		}
	}





	public function ret_delivery($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'ret_delivery', $type, $id, $status);
	}



	function update_location($status, $id)

	{

		$model = self::CAT_MODEL;

		$this->db->trans_begin();

		$data = array(
			'is_default'  => $status,

			'date_upd'   => date("Y-m-d H:i:s"),

			'updated_by' => $this->session->userdata('uid')

		);

		$this->$model->update_location();

		$status = $this->$model->updateData($data, 'id_sale_delivery', $id, 'ret_sale_delivery');

		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$this->session->set_flashdata('chit_alert', array('message' => 'Location updated successfully.', 'class' => 'success', 'title' => 'Location'));
		} else {

			$this->db->trans_rollback();

			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Customer Status'));
		}

		redirect('admin_ret_catalog/ret_delivery/List');
	}



	function set_ret_prod_image($id)

	{

		$data = array();

		$model = self::CAT_MODEL;

		//print_r($this->db->last_query());exit;

		if ($_FILES['product']['name']['ret_product_img']) {

			$path = 'assets/img/ret_product/';



			if (!is_dir($path)) {



				mkdir($path, 0777, TRUE);
			} else {

				$file = $path . $id['ID'] . ".jpg";

				chmod($path, 0777);

				unlink($file);
			}



			$img = str_replace(' ', '_', $_FILES['product']['tmp_name']['ret_product_img']);



			$filename = str_replace(' ', '_', $_FILES['product']['name']['ret_product_img']);







			$imgpath = 'assets/img/ret_product/' . $filename;







			$upload = $this->upload_img('image', $imgpath, $img);







			$data['image'] = base_url() . $imgpath;



			$this->db->trans_begin();
			$this->$model->updateData("update", $id['ID'], $data);

			if ($this->db->trans_status() === TRUE) {
				$this->db->trans_commit();
			} else {
				$this->db->trans_rollback();
			}
		}
	}



	function get_Activesize()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_Activesize($_POST['id_product']);

		echo json_encode($data);
	}



	public function ret_size($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'size', $type, $id, $status);
	}





	function update_size_status($status, $id)

	{

		$model = self::CAT_MODEL;

		$this->db->trans_begin();

		$data = array(
			'active'  => $status,

			'updated_on'   => date("Y-m-d H:i:s"),

			'updated_by' => $this->session->userdata('uid')

		);

		$status = $this->$model->updateData($data, 'id_size', $id, 'ret_size');

		//print_r($this->db->last_query());exit;

		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$this->session->set_flashdata('chit_alert', array('message' => 'Size updated successfully.', 'class' => 'success', 'title' => 'Size'));
		} else {

			$this->db->trans_rollback();

			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Size Status'));
		}

		redirect('admin_ret_catalog/ret_size/list');
	}



	//Old Metal Type

	public function metal_type($type = "", $id = "", $status = "")

	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'metal_type', $type, $id, $status);
	}

	//Old Metal Type



	//Old Metal Category

	public function old_metal_cat($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'old_metal_cat', $type, $id, $status);
	}

	//Old Metal Category



	//Old Metal Rate

	public function old_metal_rate($type = "", $id = "", $status = "")

	{

		$model = self::CAT_MODEL;

		$SETT_MOD = self::SETT_MOD;

		switch ($type) {

			case "add":

				$this->db->trans_begin();



				$this->$model->updateData(array('status' => 0), 'id_metal', 1, 'ret_old_metal_rate');

				$this->$model->updateData(array('status' => 0), 'id_metal', 2, 'ret_old_metal_rate');



				$data = array(

					'id_metal'  => 1,

					'id_purity' => 1,

					'created_on' => date("Y-m-d H:i:s"),

					'rate'      => $this->input->post('gold_rate'),

					'created_by' => $this->session->userdata('uid')

				);







				$this->$model->insertData($data, 'ret_old_metal_rate');



				$data = array(

					'id_metal'  => 2,

					'id_purity' => 1,

					'rate'      => $this->input->post('silver_rate'),

					'created_on' => date("Y-m-d H:i:s"),

					'created_by' => $this->session->userdata('uid')

				);



				$this->$model->insertData($data, 'ret_old_metal_rate');

				$data = array(

					'id_metal'  => 3,

					'id_purity' => 1,

					'rate'      => $this->input->post('platinum_rate'),

					'created_on' => date("Y-m-d H:i:s"),

					'created_by' => $this->session->userdata('uid')

				);



				$this->$model->insertData($data, 'ret_old_metal_rate');



				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'Metal Rate Added Successfully.', 'class' => 'success', 'title' => 'Metal Rate'));

					$result = array('message' => 'Metal Rate added successfully!..', 'class' => 'success', 'title' => 'Metal Rate : ');
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to Proceed.', 'class' => 'danger', 'title' => 'Metal Rate'));

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Metal Rate : ');
				}

				echo json_encode($result);

				//redirect('admin_ret_catalog/metal_type/list');

				break;

			case "edit":

				$data = $this->$model->getOldMetalRate($id);

				echo json_encode($data);

				break;



			case 'delete':

				$this->db->trans_begin();

				$this->$model->deleteData('id_metal_type', $id, 'ret_old_metal_type');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'Metal Type deleted successfully', 'class' => 'success', 'title' => 'Delete Metal Type'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Delete Metal Type'));
				}

				redirect('admin_ret_catalog/metal_type/list');

				break;



			case "update":

				$data = array(

					'updated_on' => date("Y-m-d H:i:s"),

					'updated_by' => $this->session->userdata('uid')

				);

				if ($this->input->post('id_metal') == 1) {

					$data['rate'] = $this->input->post('ed_gold_rate');
				} else if ($this->input->post('id_metal') == 2) {

					$data['rate'] = $this->input->post('ed_silver_rate');
				} else {

					$data['rate'] = $this->input->post('ed_platinum_rate');
				}





				$this->db->trans_begin();



				$this->$model->updateData($data, 'id_old_metal_rate', $this->input->post('id_old_metal_rate'), 'ret_old_metal_rate');

				//print_r($this->db->last_query());exit;

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'Rate successfully', 'class' => 'success', 'title' => 'Metal Rate'));

					echo 1;
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Metal Rate'));

					echo 0;
				}

				break;





			case 'list':

				$data['main_content'] = "master/old_metal_rate/list";

				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/old_metal_rate/list');


				$this->load->view('layout/template', $data);

				break;



			default:

				$SETT_MOD = self::SETT_MOD;

				$list = $this->$model->ajax_GetOldMetalRate();

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/old_metal_rate/list');

				$data = array(

					'list' => $list,

					'access' => $access

				);

				echo json_encode($data);
		}
	}



	//Old Metal Rate







	//Section



	public function ret_section($type = "", $id = "", $status = "")

	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'ret_section', $type, $id, $status);
	}



	function get_sectionBranchwise()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_sectionBranchwise($_POST);


		echo json_encode($data);
	}



	//Section





	//Section Mapping



	function get_section()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_section();

		echo json_encode($data);
	}



	public function update_product_section()

	{

		$model = self::CAT_MODEL;

		$i = 0;

		$this->db->trans_begin();

		foreach ($_POST['req_data'] as $section) {

			$i++;

			$data = array(

				'id_section'    => $section['product_section_select'],

				'updated_time'  => date("Y-m-d H:i:s"),

				'updated_by'    => $this->session->userdata('uid')

			);

			$this->$model->updateData($data, 'pro_id', $section['pro_id'], 'ret_product_master');

			// Also update ret_product_section table for consistency with form edit
			$this->$model->deleteData('pro_id', $section['pro_id'], 'ret_product_section');
			$section_data = array(
				'pro_id'      => $section['pro_id'],
				'id_section'  => $section['product_section_select']
			);
			$this->$model->insertData($section_data, 'ret_product_section');
		}

		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$this->session->set_flashdata('chit_alert', array('message' => 'Section updated successfully No of Updated Records ' . $i, 'class' => 'success', 'title' => 'Section'));

			$return_data = array('status' => true, 'message' => 'Section updated successfully.');
		} else {

			$this->db->trans_rollback();

			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Section'));

			$return_data = array('status' => false, 'message' => 'Section not updated.');
		}

		echo json_encode($return_data);
	}

	//Section Mapping

	//Pay Device Start

	public function ret_pay_device($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'ret_pay_device', $type, $id, $status);
	}

	//Pay Device End

	//Account Head

	public function ret_account($type = "", $id = "", $status = "")

	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'ret_account', $type, $id, $status);
	}


	//Account Head End

	//CR/DR Ledger Master Start
	public function ret_crdr_ledger($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'ret_crdr_ledger', $type, $id, $status);
	}
	//CR/DR Ledger Master End

	public function stock_issue_type($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'stock_issue_type', $type, $id, $status);
	}
	//stock type End

	//Feedback Master

	function feedback($type = "", $id = "")

	{

		$model = self::CAT_MODEL;

		$this->load->helper('lmx/core/access_helper');

		switch ($type) {

			case 'add':

				if (!lmx_can($this, 'admin_ret_catalog/feedback/list', 'add')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Add Feedback'));
					break;
				}

				$name = $this->input->post('name');

				// Known gap fix: neither required nor duplicate checks existed
				// on this field before.
				if (empty($name) || trim($name) === '') {
					$this->session->set_flashdata('chit_alert', array('message' => 'Feedback Name is required', 'class' => 'danger', 'title' => 'Add Feedback'));
					break;
				}
				if ($this->$model->check_feedback_name_exists($name)) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Feedback Name already exists', 'class' => 'danger', 'title' => 'Add Feedback'));
					break;
				}

				$data = array(

					'name'          => strtoupper($name),

					'created_by'    => $this->session->userdata('uid'),

					'created_date'  => date("Y-m-d H:i:s")

				);

				$this->db->trans_begin();

				$this->$model->insertData($data, 'customer_feedback_master');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'New Feedback added successfully', 'class' => 'success', 'title' => 'Add Feedback'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Add Feedback'));
				}

				break;



			case "edit":

				$data['feedback'] = $this->$model->get_feedback($id);

				echo json_encode($data['feedback']);

				break;



			case "update":

				if (!lmx_can($this, 'admin_ret_catalog/feedback/list', 'edit')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Feedback'));
					break;
				}

				$name = $this->input->post('name');

				if (empty($name) || trim($name) === '') {
					$this->session->set_flashdata('chit_alert', array('message' => 'Feedback Name is required', 'class' => 'danger', 'title' => 'Feedback'));
					break;
				}
				if ($this->$model->check_feedback_name_exists($name, $id)) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Feedback Name already exists', 'class' => 'danger', 'title' => 'Feedback'));
					break;
				}

				$data = array(

					"name"          => strtoupper($name),

					'updated_by'    => $this->session->userdata('uid'),

					'updated_date'  => date("Y-m-d H:i:s")

				);



				$this->db->trans_begin();

				$status = $this->$model->updateData($data, 'id_feedback', $id, 'customer_feedback_master');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'Feedback Updated  successfully', 'class' => 'success', 'title' => 'Feedback'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Feedback'));
				}

				break;



			case 'delete':

				$this->db->trans_begin();

				$this->$model->deleteData('id_feedback', $id, 'customer_feedback_master');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'Feedback deleted successfully', 'class' => 'success', 'title' => 'Delete Feedback'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Delete Feedback'));
				}

				redirect('admin_ret_catalog/feedback/list');

				break;



			case 'list':

				$data['main_content'] = "master/customer/feedback/list";

				$this->load->view('layout/template', $data);

				break;



			case 'save':

				// print_r($_POST);exit;

				$feedback = $_POST['id_feedback'];

				$i = 1;

				$insData = array(

					'id_customer'       => $_POST['id_customer'],

					'feedback_date'     => date("Y-m-d H:i:s"),

					'feedback_taken_by' => $this->session->userdata('uid'),

					'comments'          => $_POST['comments'],

				);



				$this->db->trans_begin();

				$insId = $this->$model->insertData($insData, 'customer_feedback');

				foreach ($feedback as $key => $val) {

					$responseData = array(

						'id_cus_feedback'   => $insId,

						'id_feedback'       => $feedback[$key],

						'feedback_response' => $_POST['feedback_option_' . $i],

					);

					$i++;

					$this->$model->insertData($responseData, 'customer_feedback_response');

					if ($this->db->trans_status() === TRUE) {

						$this->db->trans_commit();

						$response_data = array('status' => TRUE, 'message' => 'Feedback added successfully');

						$this->session->set_flashdata('chit_alert', array('message' => 'Feedback added successfully', 'class' => 'success', 'title' => 'Add Feedback'));
					} else {

						$this->db->trans_rollback();

						$response_data = array('status' => TRUE, 'message' => 'Unable to proceed the requested process');

						$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Feedback'));
					}
				}

				echo json_encode($response_data);



				break;



			default:

				$SETT_MOD = self::SETT_MOD;

				$list = $this->$model->ajax_getFeedback();

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/feedback/list');

				$data = array(

					'list' => $list,

					'access' => $access

				);

				echo json_encode($data);
		}
	}

	//Feedback Master



	//Charges

	function charges($type = "", $id = "", $status = "")

	{

		$model = self::CAT_MODEL;

		$set_model = 'admin_settings_model';


		switch ($type) {

			case "list":



				$data['main_content'] = "master/charges/list";

				$data['access'] = $this->$set_model->get_access('admin_ret_catalog/charges/list');

				$this->load->view('layout/template', $data);

				break;



			case "Add":


				$this->load->helper('customer_validation');
				$validation = validate_charges_data($this, 'add');
				if (!$validation['valid']) {
					echo json_encode(array('status' => false, 'message' => implode(' ', $validation['errors'])));
					return;
				}

				$charge_name = sanitize_by_rule($this->input->post('charge_name'), 'nameField');
				$charge_code = sanitize_by_rule($this->input->post('charge_code'), 'chargeCode');
				$value_charge = sanitize_by_rule($this->input->post('value_charge'), 'decimal');
				$charge_description = $this->security->xss_clean($this->input->post('charge_description'));
				$tag_display = $this->input->post('tag_display');
				$charge_tax = sanitize_by_rule($this->input->post('charge_tax'), 'taxPercentage');

				$data = array(
					'name_charge' => $charge_name,
					'code_charge' => $charge_code,
					'description_charge' => $charge_description,
					'value_charge' => $value_charge,
					'tag_display'	=> $tag_display,
					'charge_tax'    => $charge_tax,
					'created_on'	  => date("Y-m-d H:i:s"),
					'created_by'      => $this->session->userdata('uid')
				);

				$this->db->trans_begin();
				$insId = $this->$model->insertData($data, 'ret_charges');

				if ($insId > 0) {
					if ($this->db->trans_status() === TRUE) {
						$this->db->trans_commit();
						$log_data = array(
							'id_log'        => $this->session->userdata('id_log'),
							'event_date'    => date("Y-m-d H:i:s"),
							'module'        => 'Charge',
							'operation'     => 'Add',
							'record'        =>  $insId,
							'remark'        => 'Charge added successfully'
						);
						$this->log_model->log_detail('insert', '', $log_data);
						echo json_encode(array('status' => true, 'message' => 'Charge Added Successfully', 'title' => 'Success!', 'class' => 'success'));
					} else {
						$this->db->trans_rollback();
						echo json_encode(array('status' => false, 'message' => 'Transaction failed'));
					}
				} else {
					$this->db->trans_rollback();
					echo json_encode(array('status' => false, 'message' => 'Failed to save'));
				}



				break;



			case "edit":



				$id_charge = $this->input->post('id_charge');

				$data = $this->$model->get_charges_list_edit($id_charge);

				echo json_encode($data);



				break;



			case "update":


				$this->load->helper('customer_validation');
				$validation = validate_charges_data($this, 'edit');
				if (!$validation['valid']) {
					echo json_encode(array('status' => false, 'message' => implode(' ', $validation['errors'])));
					return;
				}

				$name_charge = sanitize_by_rule($this->input->post('name_charge'), 'nameField');
				$charge_code = sanitize_by_rule($this->input->post('charge_code'), 'chargeCode');
				$description_charge = $this->security->xss_clean($this->input->post('description_charge'));
				$value_charge = sanitize_by_rule($this->input->post('value_charge'), 'decimal');
				$id_charge = $this->input->post('id_charge');
				$tag_display = $this->input->post('tag_display');
				$charge_tax = sanitize_by_rule($this->input->post('charge_tax'), 'taxPercentage');

				$data = array(
					'name_charge' => $name_charge,
					'code_charge' => $charge_code,
					'description_charge' => $description_charge,
					'value_charge' => $value_charge,
					'tag_display'	=> $tag_display,
					'charge_tax'    => $charge_tax,
					'updated_on'	  => date("Y-m-d H:i:s"),
					'updated_by'      => $this->session->userdata('uid')
				);

				$this->db->trans_begin();
				$id_branch_group = $this->$model->updateData($data, 'id_charge', $id_charge, 'ret_charges');

				$log_data = array(
					'id_log'        => $this->session->userdata('id_log'),
					'event_date'    => date("Y-m-d H:i:s"),
					'module'        => 'Charge',
					'operation'     => 'Update',
					'record'        =>  $id_branch_group,
					'remark'        => 'Charge Updated successfully'
				);
				$this->log_model->log_detail('insert', '', $log_data);

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					echo json_encode(array('status' => true, 'message' => 'Charge Updated Successfully'));
				} else {
					$this->db->trans_rollback();
					echo json_encode(array('status' => false, 'message' => 'Transaction failed'));
				}





				break;



			case "delete":



				$id_charge = $this->input->post('id_charge');

				// I-GMSJ-005: masters never hard-delete — Charges is referenced by
				// ret_taging_charges and ret_estimation_other_charges, so a real
				// DELETE would corrupt any historical tag/estimation still applying
				// this charge. Deactivate instead, blocked while still in use.
				if ($this->$model->check_charge_in_use($id_charge)) {
					echo json_encode(array('status' => false, 'msg' => 'This charge is referenced by existing tag/estimation records and cannot be deleted.'));
					return;
				}

				$this->db->trans_begin();
				$data = $this->$model->updateData(array('status' => 0), 'id_charge', $id_charge, 'ret_charges');

				$log_data = array(

					'id_log'        => $this->session->userdata('id_log'),

					'event_date'    => date("Y-m-d H:i:s"),

					'module'        => 'Charge',

					'operation'     => 'Deactivate',

					'record'        =>  $id_charge,

					'remark'        => 'Charge deactivated successfully'

				);

				$this->log_model->log_detail('insert', '', $log_data);

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$data = array('status' => true, 'msg' => 'Charge deactivated successfully');
				} else {
					$this->db->trans_rollback();
					$data = array('status' => false, 'msg' => 'Unable to proceed the requested operation');
				}

				echo json_encode($data);



				break;

			case "update_status":

				// I-GMSJ-005: no reactivate path existed at all before this.
				$new_status = ($status == 1) ? 0 : 1;

				$this->db->trans_begin();
				$this->$model->updateData(array('status' => $new_status), 'id_charge', $id, 'ret_charges');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$log_data = array(
						'id_log'        => $this->session->userdata('id_log'),
						'event_date'    => date("Y-m-d H:i:s"),
						'module'        => 'Charge',
						'operation'     => 'Status Change',
						'record'        => $id,
						'remark'        => 'Charge ' . ($new_status == 1 ? 'activated' : 'deactivated') . ' successfully',
					);
					$this->log_model->log_detail('insert', '', $log_data);
					echo json_encode(array('status' => true, 'msg' => 'Charge ' . ($new_status == 1 ? 'activated' : 'deactivated') . ' successfully'));
				} else {
					$this->db->trans_rollback();
					echo json_encode(array('status' => false, 'msg' => 'Unable to proceed the requested operation'));
				}

				break;



			case "getActiveChargesList":



				$list = $this->$model->get_charges_list();

				echo json_encode($list);



				break;



			default:



				$range['from_date'] = $this->input->post("from_date");

				$range['to_date'] = $this->input->post("to_date");

				if ($range['from_date']) {



					$list = $this->$model->get_charges_list_date($range['to_date'], $range['from_date']);
				} else {

					$list = $this->$model->get_charges_list();
				}



				$SETT_MOD = self::SETT_MOD;

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/charges/list');

				$data = array(

					'charges' => $list,

					'access' => $access

				);

				echo json_encode($data);
		}
	}

	// Charges functions :: ENDS









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





	//ret sub design master

	public function ret_sub_design($type = "", $id = "", $status = "")

	{

		$model = self::CAT_MODEL;

		$SETT_MOD = self::SETT_MOD;

		// I-GMSJ-005: same gap as category() — delete/update_status call
		// lmx_can() but this branch never loaded the helper for it, so both
		// actions fatal-errored silently in production mode with no DB change.
		$this->load->helper('lmx/core/access_helper');

		switch ($type) {

			case "add":

				$data['subdesign'] = $this->$model->get_empty_subproduct();

				$data['main_content'] = "master/ret_sub_design/form";

				$this->load->view('layout/template', $data);

				break;

			case "save":

				$addData = $_POST['subdesign'];

				$sub_design_name = strtoupper(trim($addData['sub_design_name']));

				// Check if sub_design_name already exists to prevent duplicate entry error
				$isDuplicate = $this->$model->check_transaction('ret_sub_design_master', 'sub_design_name', $sub_design_name);

				if ($isDuplicate == 0) {
					// Name already exists — show friendly error
					$this->session->set_flashdata('chit_alert', array('message' => 'Sub Design "' . $sub_design_name . '" already exists. Please use a different name.', 'class' => 'danger', 'title' => 'Add Sub Design'));
					redirect('admin_ret_catalog/ret_sub_design/list');
					break;
				}

				$short_code = $this->$model->genSubDesignShortCode();

				//echo "<pre>";print_r($addData);exit;

				$data = array(

					'sub_design_name'	=> $sub_design_name,

					'sub_design_code'	=> $short_code,

					'status'	        => 1,

					'created_on'        => date("Y-m-d H:i:s"),

					'created_by'        => $this->session->userdata('uid')

				);

				$this->db->trans_begin();

				$design_no = $this->$model->insertData($data, 'ret_sub_design_master');



				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Sub Design',

						'operation'     => 'Add',

						'record'        =>  $design_no,

						'remark'        => 'Sub Design added successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'New Sub Design added successfully', 'class' => 'success', 'title' => 'Add Sub Design'));

					redirect('admin_ret_catalog/ret_sub_design/list');
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Add Sub Design'));

					$return_data = array('message' => 'Unable to proceed the requested process', 'status' => false);

					redirect('admin_ret_catalog/ret_sub_design/list');
				}



				break;

			case "edit":

				$data['subdesign'] = $this->$model->get_sub_design($id);

				$data['main_content'] = "master/ret_sub_design/form";

				$this->load->view('layout/template', $data);

				break;



			case 'delete':

				if (!lmx_can($this, 'admin_ret_catalog/ret_sub_design/list', 'delete')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Delete Sub Design'));
					redirect('admin_ret_catalog/ret_sub_design/list');
					return;
				}

				$isSubdesignExists = $this->$model->getItemsinTagDetails('', '', '', '', $id, '');

				$this->db->trans_begin();

				if ($isSubdesignExists == 0) {
					// Masters never hard-delete — deactivate instead, same as
					// every other master.
					$this->$model->updateData(
						array(
							'status'     => 0,
							'updated_on' => date("Y-m-d H:i:s"),
							'updated_by' => $this->session->userdata('uid')
						),
						'id_sub_design',
						$id,
						'ret_sub_design_master'
					);

					if ($this->db->trans_status() === TRUE) {

						$this->db->trans_commit();

						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'Sub Design',

							'operation'     => 'Deactivate',

							'record'        =>  $id,

							'remark'        => 'Sub Design deactivated successfully'

						);

						$this->log_model->log_detail('insert', '', $log_data);

						$this->session->set_flashdata('chit_alert', array('message' => 'Sub Design deactivated successfully', 'class' => 'success', 'title' => 'Deactivate Sub Design'));
					} else {

						$this->db->trans_rollback();

						$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Deactivate Sub Design'));
					}
				} else {
					$this->session->set_flashdata('chit_alert', array('message' => 'SubDesign Exists in Stock...', 'class' => 'danger', 'title' => 'Deactivate Sub Design'));
				}



				redirect('admin_ret_catalog/ret_sub_design/list');

				break;



			case 'sub_design_images':

				$status = $this->$model->ret_sub_design_mapping_images($_POST['id_sub_design_mapping']);

				echo json_encode($status);

				break;



			case 'get_karigar_products':

				$result = $this->$model->get_karigar_products($_POST['id_sub_design_mapping']);

				$data = array('id_karigar'  => $result);

				$karigars = $data;

				echo json_encode($karigars);

				break;



			case 'sub_design_description':

				$status = $this->$model->get_sub_design_mapping($_POST['id_sub_design_mapping']);

				echo json_encode($status);

				break;





			case "update":

				$addData = $_POST['subdesign'];

				// Server-side duplicate validation (no status filter — DB UNIQUE key is status-agnostic)
				$this->db->where('LOWER(`sub_design_name`) = ' . $this->db->escape(strtolower(trim($addData['sub_design_name']))), NULL, FALSE);
				$this->db->where('id_sub_design !=', $id);
				$duplicateCount = $this->db->count_all_results('ret_sub_design_master');

				if ($duplicateCount > 0) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Sub Design "'.strtoupper(trim($addData['sub_design_name'])).'" already exists!', 'class' => 'danger', 'title' => 'Edit Sub Design'));
					redirect('admin_ret_catalog/ret_sub_design/edit/'.$id);
					return;
				}

				$sub_design_name = strtoupper(trim($addData['sub_design_name']));

				// Check if another sub_design already has this name (exclude current record)
				$dupCheck = $this->db->query("SELECT id_sub_design FROM ret_sub_design_master WHERE sub_design_name = " . $this->db->escape($sub_design_name) . " AND id_sub_design != " . intval($id));
				if ($dupCheck->num_rows() > 0) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Sub Design "' . $sub_design_name . '" already exists. Please use a different name.', 'class' => 'danger', 'title' => 'Update Sub Design'));
					redirect('admin_ret_catalog/ret_sub_design/list');
					break;
				}

				$data = array(

					'sub_design_name'	=> $sub_design_name,

					'status'			=> isset($addData['status']) ? 1 : 0,

					'updated_on'        => date("Y-m-d H:i:s"),

					'updated_by'        => $this->session->userdata('uid')

				);

				$this->db->trans_begin();

				$status = $this->$model->updateData($data, 'id_sub_design', $id, 'ret_sub_design_master');



				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Sub Design',

						'operation'     => 'Update',

						'record'        =>  $status,

						'remark'        => 'Sub Design Updated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);



					$this->session->set_flashdata('chit_alert', array('message' => 'Sub Design Updated  successfully', 'class' => 'success', 'title' => 'Sub design'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Sub design'));
				}

				redirect('admin_ret_catalog/ret_sub_design/list');

				break;



			case 'active_list':

				$data = $this->$model->getActiveSubDesigns();

				echo json_encode($data);

				break;



			case 'list':


				$data['main_content'] = "master/ret_sub_design/list";

				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_sub_design/list');

				$this->load->view('layout/template', $data);

				break;



			case 'update_status':

				if (!lmx_can($this, 'admin_ret_catalog/ret_sub_design/list', 'edit')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Design  Status'));
					redirect('admin_ret_catalog/ret_sub_design/list');
					return;
				}

				// Same dependency gate as Delete — deactivating a sub design
				// still in stock is exactly as unsafe as deleting it.
				if ($status == 0) {
					$isSubdesignExists = $this->$model->getItemsinTagDetails('', '', '', '', $id, '');
					if ($isSubdesignExists != 0) {
						$this->session->set_flashdata('chit_alert', array('message' => 'SubDesign Exists in Stock...', 'class' => 'danger', 'title' => 'Design  Status'));
						redirect('admin_ret_catalog/ret_sub_design/list');
						return;
					}
				}

				$data = array('status' => $status, 'updated_on' => date("Y-m-d H:i:s"), 'updated_by' => $this->session->userdata('uid'));

				$this->db->trans_begin();
				$updstatus = $this->$model->updateData($data, 'id_sub_design', $id, 'ret_sub_design_master');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Sub design status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'Design  Status'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Design  Status'));
				}

				redirect('admin_ret_catalog/ret_sub_design/list');

				break;



			default:



				$list = $this->$model->ajax_getSubDesign($_POST);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_sub_design/list');

				$data = array(

					'list' => $list,

					'access' => $access

				);

				echo json_encode($data);
		}
	}





	function get_ActiveDesign()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_ActiveDesign();

		echo json_encode($data);
	}



	function update_design()

	{

		$model = self::CAT_MODEL;

		$reqdata = $this->input->post('req_data');

		$id_design = $this->input->post('id_design');





		foreach ($reqdata as $data) {

			if ($this->$model->check_subdesign_maping($data['id_sub_design'], $id_design)) {

				$insdata = array(

					'id_sub_design' => $data['id_sub_design'],

					'design_no'     => $id_design,

				);

				$this->db->trans_begin();

				$this->$model->insertData($insdata, 'ret_sub_design_mapping');
			}
		}

		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$status = array('status' => true, 'msg' => 'Design Mapped successfully');
		} else {

			$this->db->trans_rollback();

			$status = array('status' => false, 'msg' => 'Unable to Proceed Your Request');
		}

		echo json_encode($status);
	}



	function update_design_products()

	{

		$model = self::CAT_MODEL;

		$reqdata = $this->input->post('req_data');

		$id_product = $this->input->post('id_product');





		foreach ($reqdata as $data) {

			if ($this->$model->check_design_products_maping($data['design_no'], $id_product)) {

				$insdata = array(

					'design_no' => $data['design_no'],

					'pro_id'    => $id_product,

				);

				$this->db->trans_begin();

				$this->$model->insertData($insdata, 'ret_design_products');
			}
		}

		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$status = array('status' => true, 'msg' => 'Design Mapped successfully');
		} else {

			$this->db->trans_rollback();

			$status = array('status' => false, 'msg' => 'Unable to Proceed Your Request');
		}

		echo json_encode($status);
	}







	//Product Mapping

	public function ret_products_mapping($type = "", $id = "", $status = "")
	{

		$model = self::CAT_MODEL;

		$SETT_MOD 	= self::SETT_MOD;

		switch ($type) {

			case 'list':

				$data['main_content'] = "master/ret_product/product_mapping";


				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_products_mapping/list');

				$this->load->view('layout/template', $data);

				break;



			case 'delete':

				$this->db->trans_begin();

				$this->$model->deleteData('mapping_id', $id, 'ret_product_mapping');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();




					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Design Maping',

						'operation'     => 'Delete',

						'record'        =>  $id,

						'remark'        => 'Design Maping deleted successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);


					$this->session->set_flashdata('chit_alert', array('message' => 'Design Maping Deleted successfully', 'class' => 'success', 'title' => 'Design Mapping'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Design Mapping'));
				}

				redirect('admin_ret_catalog/ret_products_mapping/list');

				break;





			default:

				$SETT_MOD = self::SETT_MOD;

				$product = $this->$model->ajax_ProductMapingDetails($_POST);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_products_mapping/list');

				$data = array(

					'product' => $product,

					'access' => $access

				);

				echo json_encode($data);
		}
	}







	function update_product_design_mapping()

	{

		$model = self::CAT_MODEL;

		$this->load->helper('lmx/core/access_helper');

		// I-GMSJ-005: this is the real add-mapping action for ret_products_mapping
		// (the ret_products_mapping() switch-case only lists/deletes).
		if (!lmx_can($this, 'admin_ret_catalog/ret_products_mapping/list', 'add')) {
			echo json_encode(array('status' => false, 'msg' => 'Access Denied'));
			return;
		}

		$id_product     = $this->input->post('id_product');

		$designs      = $this->input->post('id_design');

		// I-GMSJ-005: neither id_product nor id_design had a required check,
		// an isset/is_array guard, or an FK-existence/active check — same
		// class of gap as Product's Category field, plus the array form
		// needed its own guard against a missing/non-array post.
		if (empty($id_product) || !$this->$model->check_product_active($id_product)) {
			echo json_encode(array('status' => false, 'msg' => 'Selected Product is required and must be active.'));
			return;
		}
		if (empty($designs) || !is_array($designs)) {
			echo json_encode(array('status' => false, 'msg' => 'At least one Design is required.'));
			return;
		}
		foreach ($designs as $id_design) {
			if (!$this->$model->check_design_active($id_design)) {
				echo json_encode(array('status' => false, 'msg' => 'One or more selected Designs are no longer available. Please refresh and choose again.'));
				return;
			}
		}

		foreach ($designs as $id_design) {

			if ($this->$model->check_products_design_maping($id_product, $id_design)) {

				$insdata = array(

					'id_design'         => $id_design,

					'pro_id'            => $id_product,

				);

				$this->db->trans_begin();

				$insId = $this->$model->insertData($insdata, 'ret_product_mapping');

				//print_r($this->db->last_query());exit;

			}
		}



		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$log_data = array(

				'id_log'        => $this->session->userdata('id_log'),

				'event_date'    => date("Y-m-d H:i:s"),

				'module'        => 'Design Mapping',

				'operation'     => 'Add',

				'record'        =>  $insId,

				'remark'        => 'Design Mapping added successfully'

			);

			$this->log_model->log_detail('insert', '', $log_data);

			$status = array('status' => true, 'msg' => 'Product Mapped successfully');
		} else {

			$this->db->trans_rollback();

			$status = array('status' => false, 'msg' => 'Unable to Proceed Your Request');
		}

		echo json_encode($status);
	}





	function delete_product_design_mapping()

	{

		$model = self::CAT_MODEL;

		$reqdata = $this->input->post('req_data');



		foreach ($reqdata as $items) {

			$this->db->trans_begin();

			$this->$model->deleteData('mapping_id', $items['mapping_id'], 'ret_product_mapping');
		}



		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$status = array('status' => true, 'msg' => 'Product Mapped Deleted successfully');
		} else {

			$this->db->trans_rollback();

			$status = array('status' => false, 'msg' => 'Unable to Proceed Your Request');
		}

		echo json_encode($status);
	}







	public function ret_subdesign_mapping($type = "", $id = "", $status = "")
	{

		$model = self::CAT_MODEL;

		$SETT_MOD 	= self::SETT_MOD;



		switch ($type) {

			case 'list':

				$data['main_content'] = "master/ret_product/subdesign_mapping";

				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_subdesign_mapping/list');

				$this->load->view('layout/template', $data);

				break;



			case 'delete':

				if (!lmx_can($this, 'admin_ret_catalog/ret_subdesign_mapping/list', 'delete')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Sub Design Mapping'));
					redirect('admin_ret_catalog/ret_subdesign_mapping/list');
					return;
				}

				// I-GMSJ-005: this delete had no dependency check at all — a mapping
				// whose product+design+sub_design combination is already tagged (and,
				// downstream, billed) could be removed anyway, silently invalidating
				// that historical stock/tag data. Reuses the same getItemsinTagDetails()
				// check every other catalog master's delete already runs.
				$mappingRow = $this->db->get_where('ret_sub_design_mapping', array('id_sub_design_mapping' => $id))->row_array();
				if ($mappingRow) {
					$isMappingInUse = $this->$model->getItemsinTagDetails('', '', $mappingRow['id_product'], $mappingRow['id_design'], $mappingRow['id_sub_design'], '');
					if ($isMappingInUse != 0) {
						$this->session->set_flashdata('chit_alert', array('message' => 'This mapping is referenced by existing stock/tags and cannot be deleted.', 'class' => 'danger', 'title' => 'Sub Design Mapping'));
						redirect('admin_ret_catalog/ret_subdesign_mapping/list');
						return;
					}
				}

				$this->db->trans_begin();

				$this->$model->deleteData('id_sub_design_mapping', $id, 'ret_sub_design_mapping');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Sub Design Maping',

						'operation'     => 'Delete',

						'record'        =>  $id,

						'remark'        => 'Sub Design Maping deleted successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Sub Design Maping Deleted successfully', 'class' => 'success', 'title' => 'Sub Design Mapping'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Sub Design Mapping'));
				}

				redirect('admin_ret_catalog/ret_subdesign_mapping/list');

				break;





			default:

				$SETT_MOD = self::SETT_MOD;

				$product = $this->$model->ajax_subDesignMapingDetails($_POST);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_subdesign_mapping/list');

				$data = array(

					'product' => $product,

					'access' => $access

				);

				echo json_encode($data);
		}
	}





	function update_sub_design_image()

	{

		$model = self::CAT_MODEL;

		$subdesign_images      = $_POST['subdesign']['subdesign_images'];

		$id_sub_design_mapping = $_POST['subdesign']['id_sub_design_mapping'];

		$p_ImgData = json_decode($subdesign_images);

		//echo "<pre>";print_r($p_ImgData);exit;

		$is_defalut_img = '';

		$is_default = 0;

		$this->db->trans_begin();

		$this->$model->deleteData('id_sub_design_mapping', $id_sub_design_mapping, 'ret_sub_design_mapping_images');

		if (sizeof($p_ImgData) > 0) {

			foreach ($p_ImgData as $key => $precious) {



				if ($precious->is_default == 1) {

					$is_defalut_img = $key;
				}

				$imgFile = $this->base64ToFile($precious->src);

				$_FILES['subdesign_images'][] = $imgFile;
			}
		}



		if (!empty($p_ImgData)) {

			$folder =  self::IMG_PATH . "sub_design/" . $id_sub_design_mapping;

			if (!is_dir($folder)) {

				mkdir($folder, 0777, TRUE);
			}

			if (isset($_FILES['subdesign_images'])) {

				foreach ($_FILES['subdesign_images'] as $file_key => $file_val) {

					if ($file_val['name']) {

						$img_name =  "$id_sub_design_mapping_" . mt_rand(100001, 999999) . ".jpg";

						$path = $folder . "/" . $img_name;

						$result = $this->upload_img('image', $path, $file_val['tmp_name']);

						if ($result) {

							if ($is_defalut_img == $file_key) {

								$is_default = 1;
							} else {

								$is_default = 0;
							}

							$imgData = array('is_default' => $is_default, 'id_sub_design_mapping' => $id_sub_design_mapping, 'image_name' => $img_name);

							$this->$model->insertData($imgData, 'ret_sub_design_mapping_images');
						}
					}
				}
			}
		}

		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$status = array('status' => true, 'msg' => 'Image Updated Successfully..');
		} else {

			$this->db->trans_rollback();

			$status = array('status' => false, 'msg' => 'Unable to Proceed Your Request');
		}

		echo json_encode($status);
	}



	function update_subdesign_mapping()

	{

		$model = self::CAT_MODEL;

		$this->load->helper('lmx/core/access_helper');

		// I-GMSJ-005: this is the real add-mapping action for ret_subdesign_mapping
		// (the ret_subdesign_mapping() switch-case only lists/deletes).
		if (!lmx_can($this, 'admin_ret_catalog/ret_subdesign_mapping/list', 'add')) {
			echo json_encode(array('status' => false, 'msg' => 'Access Denied'));
			return;
		}

		$id_product     = $this->input->post('id_product');

		$id_design     = $this->input->post('id_design');

		$sub_designs      = $this->input->post('id_sub_design');

		// I-GMSJ-005: id_product/id_design/id_sub_design had no required
		// check, no isset/is_array guard on id_sub_design, and no
		// FK-existence/active check — same class of gap as Product's
		// Category field, closed the same way for this array field too.
		if (empty($id_product) || !$this->$model->check_product_active($id_product)) {
			echo json_encode(array('status' => false, 'msg' => 'Selected Product is required and must be active.'));
			return;
		}
		if (empty($id_design) || !$this->$model->check_design_active($id_design)) {
			echo json_encode(array('status' => false, 'msg' => 'Selected Design is required and must be active.'));
			return;
		}
		if (empty($sub_designs) || !is_array($sub_designs)) {
			echo json_encode(array('status' => false, 'msg' => 'At least one Sub Design is required.'));
			return;
		}
		foreach ($sub_designs as $id_sub_design) {
			if (!$this->$model->check_sub_design_active($id_sub_design)) {
				echo json_encode(array('status' => false, 'msg' => 'One or more selected Sub Designs are no longer available. Please refresh and choose again.'));
				return;
			}
		}





		foreach ($sub_designs as $id_sub_design) {

			if ($this->$model->check_sub_design_mapping($id_product, $id_design, $id_sub_design)) {

				$insdata = array(

					'id_design'     => $id_design,

					'id_product'    => $id_product,

					'id_sub_design' => $id_sub_design,

				);

				$this->db->trans_begin();

				$insId = $this->$model->insertData($insdata, 'ret_sub_design_mapping');
			}
		}



		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$log_data = array(

				'id_log'        => $this->session->userdata('id_log'),

				'event_date'    => date("Y-m-d H:i:s"),

				'module'        => 'Sub Design Mapping',

				'operation'     => 'Add',

				'record'        =>  $insId,

				'remark'        => 'Sub Design Mapping added successfully'

			);

			$this->log_model->log_detail('insert', '', $log_data);

			$status = array('status' => true, 'msg' => 'Sub Design Mapped successfully');
		} else {

			$this->db->trans_rollback();

			$status = array('status' => false, 'msg' => 'Unable to Proceed Your Request');
		}

		echo json_encode($status);
	}



	public function update_subdesign_des()

	{

		$model = self::CAT_MODEL;



		$id_sub_design_mapping = $this->input->post('id_sub_design_mapping');

		$description = $this->input->post('description');

		$data_des['description'] = $description;



		$this->db->trans_begin();
		$status = $this->$model->updateData($data_des, 'id_sub_design_mapping', $id_sub_design_mapping, 'ret_sub_design_mapping');

		//	print_r($this->db->last_query());exit;

		if ($this->db->trans_status() === TRUE) {
			$this->db->trans_commit();
			$responseData = array('status' => TRUE, 'message' => 'Description Added Successfully..');
		} else {
			$this->db->trans_rollback();
			$responseData = array('status' => FALSE, 'message' => 'Description Added Successfully..');
		}

		echo json_encode($responseData);
	}



	public function update_karigar_products()

	{

		$model = self::CAT_MODEL;



		$id_sub_design_mapping = $this->input->post('id_sub_design_mapping');

		$karigars = $this->input->post('id_karigar');



		$this->$model->deleteData('id_sub_design_mapping', $id_sub_design_mapping, 'ret_karigar_products');

		foreach ($karigars as $id_karigar) {

			$data = array('id_karigar' => $id_karigar, 'id_sub_design_mapping' => $id_sub_design_mapping);

			$this->db->trans_begin();

			$this->$model->insertData($data, 'ret_karigar_products');
		}



		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$responseData = array('status' => true, 'message' => 'Karigar Mapped successfully');
		} else {

			$this->db->trans_rollback();

			$responseData = array('status' => false, 'message' => 'Unable to Proceed Your Request');
		}

		echo json_encode($responseData);
	}



	function delete_sub_design_mapping()

	{

		$model = self::CAT_MODEL;

		$reqdata = $this->input->post('req_data');





		foreach ($reqdata as $items) {

			$this->db->trans_begin();

			$this->$model->deleteData('id_sub_design_mapping', $items['id_sub_design_mapping'], 'ret_sub_design_mapping');
		}



		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$status = array('status' => true, 'msg' => 'Sub Design Mapped Deleted successfully');
		} else {

			$this->db->trans_rollback();

			$status = array('status' => false, 'msg' => 'Unable to Proceed Your Request');
		}

		echo json_encode($status);
	}





	function get_ProductDesign()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_ProductDesign($_POST);

		//  	print_r($this->db->last_query());exit;

		echo json_encode($data);
	}



	public function getSubDesignName()
	{

		$model = self::CAT_MODEL;

		$data = $this->$model->getSubDesignName($_POST['sub_design_name']);

		echo json_encode($data);
	}



	public function getDesignName()
	{

		$model = self::CAT_MODEL;

		$data = $this->$model->getDesignName($_POST['design_name']);

		echo json_encode($data);
	}



	//Product Mapping





	//GET DESIGN PRODUCTS

	function get_active_design_products()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_active_design_products($_POST);

		echo json_encode($data);
	}



	function get_ActiveSubDesigns()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_ActiveSubDesigns($_POST);

		echo json_encode($data);
	}

	//GET DESIGN PRODUCTS







	//V.A / M.C Attribute Settings

	function get_DesignSettingsDetails()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_DesignSettingsDetails($_POST);

		echo json_encode($data);
	}



	function get_wastage_details($id)

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_wastage_details($id);

		echo json_encode($data);
	}



	function get_design_attr_values($id)

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_design_attr_values($id);

		echo json_encode($data);
	}



	function delete_design_attribute($attr_des_id)

	{

		$model = self::CAT_MODEL;

		$this->db->trans_begin();
		$status = $this->$model->deleteData('attr_des_id', $attr_des_id, 'ret_design_attributes');



		if ($this->db->trans_status() === TRUE) {
			$this->db->trans_commit();
			$msg = "Attribute deleted successfully";
		} else {
			$this->db->trans_rollback();
			$msg = "Error occured. Please try again";
		}



		$responseData =	array('status' => $this->db->trans_status() !== FALSE ? TRUE : FALSE, 'msg' => $msg);

		echo json_encode($responseData);
	}



	function delete_design_weight_range($wastage_des_id)

	{

		$model = self::CAT_MODEL;

		$this->db->trans_begin();
		$status = $this->$model->deleteData('id_wc', $wastage_des_id, 'ret_design_weight_range_wc');



		if ($this->db->trans_status() === TRUE) {
			$this->db->trans_commit();
			$msg = "Weight range deleted successfully";
		} else {
			$this->db->trans_rollback();
			$msg = "Error occured. Please try again";
		}



		$responseData =	array('status' => $this->db->trans_status() !== FALSE ? TRUE : FALSE, 'msg' => $msg);

		echo json_encode($responseData);
	}

	//V.A / M.C Attribute Settings



	// Attributes



	public function attribute($type = "", $id = "", $status = "")
	{
		// active_list / activeValues_list / get_attribute_with_values are consumed
		// by catalog_master.js (Design attribute dropdowns) and ret_tagging.js (the
		// Tagging module's attribute+value picker) outside this master's own page —
		// preserved as-is, everything else routes through the generic engine.
		if (in_array($type, ['active_list', 'activeValues_list', 'get_attribute_with_values'], true)) {
			$model = self::CAT_MODEL;
			if ($type === 'active_list') {
				echo json_encode($this->$model->getActiveAttribute());
			} elseif ($type === 'activeValues_list') {
				echo json_encode($this->$model->get_attribute_values($_POST['attr_id']));
			} else {
				$attr_id = isset($_POST['attr_id']) && $_POST['attr_id'] > 0 ? $_POST['attr_id'] : 0;
				echo json_encode($this->$model->get_attribute_with_values($attr_id, 1));
			}
			return;
		}
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'attribute', $type, $id, $status);
	}



	// Attributes





	//Product,Design,Sub Design Filter

	function get_ActiveProducts()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_ActiveProducts($_POST);

		echo json_encode($data);
	}





	function get_MetalCategory()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_MetalCategory($_POST);

		echo json_encode($data);
	}



	function get_NonTagProducts()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_NonTagProducts($_POST);

		echo json_encode($data);
	}





	//Product,Design,Sub Design Filter







	//Karigar V.A Details

	function get_karigar_wise_wastage()

	{

		$model = self::CAT_MODEL;

		$id_kaigar = $_POST['id_karigar'];

		$data['approved_details'] = $this->$model->get_karigar_wise_wastage($id_kaigar);

		$data['yet_approved_details'] = $this->$model->karigar_waiting_for_app_products($id_kaigar);

		$data['hold_details'] = $this->$model->get_karigar_hold_wastages($id_kaigar);

		echo json_encode($data);
	}

	//Karigar V.A Details





	//Karigar stone Details

	function get_karigar_wise_stones()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_karigar_wise_stones();

		echo json_encode($data);
	}

	//Karigar stone Details



	//karigar charges Details

	function get_karigar_wise_charges()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_karigar_wise_charges();

		echo json_encode($data);
	}



	//karigar charges Details



	//Karigar Product Mapping

	public function ret_karigar_product($type = "", $id = "", $status = "")

	{

		$model = self::CAT_MODEL;

		switch ($type) {

			case 'list':

				$data['main_content'] = "master/karigar/ret_product_mapping";

				$this->load->view('layout/template', $data);

				break;



			case 'update_status':



				if ($status == 1) {

					$karigar_pro = $this->$model->get_karigar_products($id);
				}



				$data = array('status' => $status, 'updated_on' => date("Y-m-d H:i:s"), 'updated_by' => $this->session->userdata('uid'));

				$this->db->trans_begin();
				$updstatus = $this->$model->updateData($data, 'id_karigar_product', $id, 'ret_karigar_products');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Sub design status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'Design  Status'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Design  Status'));
				}

				redirect('admin_ret_catalog/ret_karigar_product/list');

				break;



			default:

				$SETT_MOD = self::SETT_MOD;

				$list = $this->$model->get_karigar_product_mapping($_POST);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_karigar_product/list');

				$data = array(

					'list' => $list,

					'access' => $access

				);

				echo json_encode($data);
		}
	}







	function update_product_mapping()

	{

		$model = self::CAT_MODEL;

		$id_product     = $this->input->post('id_product');

		$id_design      = $this->input->post('id_design');

		$karigar      = $this->input->post('id_karigar');



		foreach ($karigar as $kid) {

			if ($this->$model->check_design_products_maping($id_product, $id_design, $kid)) {

				$insdata = array(

					'id_karigar' => $kid,

					'id_product' => $id_product,

					'id_design'  => $id_design,

					'created_by' => $this->session->userdata('uid'),

					'created_on' => date("Y-m-d H:i:s"),

				);

				$this->db->trans_begin();

				$this->$model->insertData($insdata, 'ret_karigar_products');

				//print_r($this->db->last_query());exit;

			}
		}



		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$status = array('status' => true, 'msg' => 'Product Mapped successfully');
		} else {

			$this->db->trans_rollback();

			$status = array('status' => false, 'msg' => 'Unable to Proceed Your Request');
		}

		echo json_encode($status);
	}



	function delete_product_mapping()

	{

		$model = self::CAT_MODEL;

		$reqdata = $this->input->post('req_data');



		foreach ($reqdata as $items) {

			$this->db->trans_begin();

			$this->$model->deleteData('id_karigar_product', $items['id_karigar_product'], 'ret_karigar_products');
		}



		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$status = array('status' => true, 'msg' => 'Product Mapped Deleted successfully');
		} else {

			$this->db->trans_rollback();

			$status = array('status' => false, 'msg' => 'Unable to Proceed Your Request');
		}

		echo json_encode($status);
	}



	//Karigar Product Mapping





	//web registered devices

	function web_devices($type = "", $id = "")

	{

		$model = self::CAT_MODEL;

		switch ($type) {

			case "edit":

				if ($id != NULL) {

					$data['device'] = $this->$model->web_device_settingDB('get', $id);
				} else {

					$data['device'] = $this->$model->web_device_settingDB();
				}

				$data['main_content'] = self::MAS_VIEW . 'web_devices/form';

				$this->load->view('layout/template', $data);

				break;



			case 'add':

				$data['main_content'] = self::MAS_VIEW . 'web_devices/form';

				$this->load->view('layout/template', $data);

				break;



			case "list":

				$data['main_content'] = self::MAS_VIEW . 'web_devices/list';

				$this->load->view('layout/template', $data);

				break;



			case "save":

				$addData = $this->input->post('device');



				$insData = array(

					'device_name'   => strtoupper($addData['device_name']),

					'token_id'      => $addData['token_id'],

					'browser'       => strtoupper($addData['browser']),

					'id_branch'     => $addData['id_branch'],

					'id_counter'   => $addData['id_counter'],

					'id_floor'     => $addData['id_floor'],

				);

				//inserting data

				$this->db->trans_begin();

				$status = $this->$model->web_device_settingDB("insert", "", $insData);

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'Device Added successfully', 'class' => 'success', 'title' => 'Device List'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Device List'));
				}

				redirect('admin_ret_catalog/web_devices/list');

				break;

			case "update":

				$addData = $this->input->post('device');



				$insData = array(

					'device_name'   => strtoupper($addData['device_name']),

					'token_id'      => $addData['token_id'],

					'browser'       => strtoupper($addData['browser']),

					'id_branch'     => $addData['id_branch'],

					'id_counter'    => $addData['id_counter'],

					'id_floor'     => $addData['id_floor'],

				);

				$this->db->trans_begin();

				$status = $this->$model->web_device_settingDB("update", $id, $insData);

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'Device Updated successfully', 'class' => 'success', 'title' => 'Device List'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Device List'));
				}

				redirect('admin_ret_catalog/web_devices/list');

				break;

			case 'delete':

				$status = $this->$model->web_device_settingDB("delete", $id);

				if ($status) {

					$this->session->set_flashdata('chit_alert', array('message' => 'Web Devices deleted successfully', 'class' => 'success', 'title' => 'Web Device'));
				} else {

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Web Device'));
				}

				redirect('admin_ret_catalog/web_devices/list');

				break;

			case 'check_token_duplicates':
				$status = $this->$model->check_token_duplicates($_POST['token_id']);
				echo json_encode($status);
				break;
			default:

				$list = $this->$model->get_DeviceList();

				$access = $this->admin_settings_model->get_access('admin_ret_catalog/web_devices/list');

				$data = array(

					'list'  => $list,

					'access' => $access

				);

				echo json_encode($data);

				break;
		}
	}



	function get_ActiveCounter()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_ActiveCounter($_POST);

		echo json_encode($data);
	}



	function get_ActiveBranchFloor()

	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_ActiveBranchFloor($_POST);

		echo json_encode($data);
	}



	//web registered devices





	//collection



	public function ret_collection($type = "", $id = "", $status = "")

	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'ret_collection', $type, $id, $status);
	}



	//collection





	//repair master



	public function repair_master($type = "", $id = "", $status = "")

	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'repair_master', $type, $id, $status);
	}



	//repair master



	public function product_division($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'product_division', $type, $id, $status);
	}



	function product_division_status($status, $id)

	{

		$data = array('status' => $status);



		$model = self::CAT_MODEL;



		$this->db->trans_begin();
		$updstatus = $this->$model->update_prod_division($data, $id);

		if ($this->db->trans_status() === TRUE) {
			$this->db->trans_commit();
			$this->session->set_flashdata('chit_alert', array('message' => 'Product division status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'Product Division Status'));
		} else {
			$this->db->trans_rollback();
			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Product Division Status'));
		}



		redirect('product_division/list');
	}





	//Metal Rate Purity

	public function MetalSelect()
	{

		$model = self::CAT_MODEL;

		$data = $this->$model->MetalSelectModel();

		echo json_encode($data);
	}

	public function PuritySelect()
	{

		$model = self::CAT_MODEL;

		$data = $this->$model->PuritySelectModel($_POST['id_metal']);

		echo json_encode($data);
	}

	public function MetalRates()
	{

		$model = self::CAT_MODEL;

		$data = $this->$model->MetalRatesModel();

		echo json_encode($data);
	}



	public function ret_metalpurity($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'ret_metalpurity', $type, $id, $status);
	}



	function moneyFormatIndia($num)
	{



		return preg_replace("/(\d+?)(?=(\d\d)+(\d)(?!\d))(\.\d+)?/i", "$1,", $num);
	}







	/*Karigar Approval*/





	function karigar_approval($type = "")

	{



		$model	=	self::CAT_MODEL;

		$SETT_MOD = self::SETT_MOD;



		switch ($type) {

			case 'list':

				//$data['karigar_wastages'] = $this->$model->get_karigar_approvalWastage_list($id);

				// $data['otp_settings'] = $this->$model->get_ret_settings('vendor_approval_otp');

				$data['otp_settings'] = $this->$model->get_profile_settings($this->session->userdata('profile'));

				$data['main_content'] = "master/karigar/approval_list";

				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/karigar_approval/list');

				$this->load->view('layout/template', $data);

				break;


			case 'save':

				//echo"<pre>";print_r($_POST);exit;

				$status = $_POST['status'];



				$approval_for = $_POST['approval_for'];



				$this->db->trans_begin();



				if ($approval_for == 1 && $status == 1) {

					foreach ($_POST['approved_data'] as $key => $val) {

						$this->$model->update_karigar_stones($val['id_karigar'], $val['stone_type_id'], $val['stone_id'], $val['quality_id'], $val['from_wt'], $val['to_wt']);
					}
				}



				foreach ($_POST['approved_data'] as $key => $val) {

					if ($status == 1) // For Status Approved

					{

						if ($approval_for == 0) // Karigar Wastages Update

						{
							//list of approved wastages

							$wastage_list = $this->$model->get_karigar_wastages($val['id_karigar']);



							foreach ($wastage_list as $itm) {
								if ($val['stone_type'] == 0)  // For Ornaments Product
								{
									// if Post category is All
									if ($val['cat_id'] == 0) {
										$this->$model->update_karigar_wastages($val['id_karigar']);
									}

									//if All category in list
									if ($itm['id_category'] == 0) {
										$this->$model->update_karigar_wastages($val['id_karigar'], $itm['id_category']);
									}


									//Same category , // Same Purity

									if (($val['cat_id'] == $itm['id_category']) && ($val['id_purity'] == $itm['id_purity'])) {
										//All product in list
										if ($itm['id_product'] == 0) {
											$this->$model->update_karigar_wastages($val['id_karigar'], $val['cat_id'], $itm['id_purity'], $itm['id_product']);
										}

										// If post product is All
										if ($val['pro_id'] == 0) {
											$this->$model->update_karigar_wastages($val['id_karigar'], $val['cat_id'], $val['id_purity']);
										}
									}



									//Same category,Same purity,Same product
									if (($val['cat_id'] == $itm['id_category']) && ($val['id_purity'] == $itm['id_purity']) && ($val['pro_id'] == $itm['id_product'])) {
										//All Design in List
										if ($itm['id_design'] == 0) {
											$this->$model->update_karigar_wastages($val['id_karigar'], $val['cat_id'], $val['id_purity'], $val['pro_id'], $itm['id_design']);
										}
										//if post Design is All
										if ($val['des_id'] == 0) {
											$this->$model->update_karigar_wastages($val['id_karigar'], $val['cat_id'], $val['id_purity'], $val['pro_id']);
										}
									}



									// Same category , Same purity, Same product , Same Design
									if (($val['cat_id'] == $itm['id_category']) && ($val['id_purity'] == $itm['id_purity']) && ($val['pro_id'] == $itm['id_product']) && ($val['des_id'] == $itm['id_design'])) {
										// All sub design in list
										if ($itm['id_sub_design'] == 0) {
											$this->$model->update_karigar_wastages($val['id_karigar'], $val['cat_id'], $val['id_purity'], $val['pro_id'], $val['des_id'], $itm['id_sub_design']);
										}
										// If post subdesign is All
										if ($val['sub_des_id'] == 0) {
											$this->$model->update_karigar_wastages($val['id_karigar'], $val['cat_id'], $val['id_purity'], $val['pro_id'], $val['des_id']);
										}
									}


									// Same category , Same purity, Same product , Same Design , Same Sub Design

									if (($val['cat_id'] == $itm['id_category']) && ($val['id_purity'] == $itm['id_purity']) && ($val['pro_id'] == $itm['id_product']) && ($val['des_id'] == $itm['id_design']) && ($val['sub_des_id'] == $itm['id_sub_design'])) {
										$this->$model->update_karigar_wastages($val['id_karigar'], $val['cat_id'], $val['id_purity'], $val['pro_id'], $val['des_id'], $val['sub_des_id']);
									}
								} else if ($val['stone_type'] == 2)  // For Diamond Product
								{
									if (($val['cat_id'] == $itm['id_category'])  && ($val['pro_id'] == $itm['id_product']) && ($val['des_id'] == $itm['id_design']) && ($val['sub_des_id'] == $itm['id_sub_design']) && ($val['quality_id'] == $itm['quality_id']) && (($val['from_wt'] >= $itm['from_wt'] && $val['from_wt'] <= $itm['to_wt']) || $itm['from_wt'] >= $val['from_wt'] && $itm['from_wt'] <= $val['to_wt'])) {
										$this->$model->update_karigar_wastages($val['id_karigar'], $val['cat_id'], '', $val['pro_id'], $val['des_id'], $val['sub_des_id'], $val['quality_id'], $val['from_wt'], $val['to_wt']);
									}
								} else  // For Stone Product
								{
									if (($val['cat_id'] == $itm['id_category'])  && ($val['pro_id'] == $itm['id_product']) && ($val['des_id'] == $itm['id_design']) && ($val['sub_des_id'] == $itm['id_sub_design'])) {
										$this->$model->update_karigar_wastages($val['id_karigar'], $val['cat_id'], '', $val['pro_id'], $val['des_id'], $val['sub_des_id']);
									}
								}
							}



							$statusData = array(

								'calc_type'                => $val['calc_type'],

								'wastage_type'             => $val['wastage_type'],

								'wastage_per'              => $val['wastage_per'],

								'wastage_wt'               => $val['wastage_wt'],

								'mc_type'                  => $val['mc_type'],

								'mc_value'                 => $val['mc_value'],

								'pur_touch'                => $val['pur_touch'],

								'karigar_calc_type'        => $val['kar_calc_type'],

								'stone_cal_type'           => $val['stone_cal_type'],

								'uom_id'                   => $val['uom_id'],

								'quality_id'               => $val['quality_id'],

								'from_wt'                  => $val['from_wt'],

								'to_wt'                    => $val['to_wt'],

								'rate_per_gram'            => $val['rate_per_gram'],

								'status'                   => 1,

								'active'                   => 1,

								'approved_by'              => $this->session->userdata('uid'),

								'approved_on'              => date("Y-m-d H:i:s")

							);

							$id_karikar_wast = $this->$model->updateData($statusData, 'id_karikar_wast', $val['id_karikar_wast'], 'ret_karikar_items_wastage');



							$this->$model->deleteData('id_karikar_wast', $val['id_karikar_wast'], 'ret_karigar_charges');

							$charges_details = json_decode($val['charges_details'], true);

							if (sizeof($charges_details) > 0) {

								foreach ($charges_details as $charge) {

									$charge_data = array(

										'id_karigar'         => $val['id_karigar'],

										'id_karikar_wast'    => $val['id_karikar_wast'],

										'charge_id'          => $charge['charge_id'],

										'calc_type'          => $charge['calc_type'],

										'charge_value'       => $charge['charge_value'],

										'active'             => 0,

										'updated_on'	     => date("Y-m-d H:i:s"),

										'updated_by'         => $this->session->userdata('uid')

									);

									$this->$model->insertData($charge_data, 'ret_karigar_charges');
								}
							}
						} else if ($approval_for == 1) // Karigar stones Update

						{

							$this->$model->update_karigar_stones($val['id_karigar'], $val['stone_type_id'], $val['stone_id'], $val['quality_id'], $val['from_wt'], $val['to_wt']);



							$statusData = array('status' => 1, 'active' => 1, 'approved_by' => $this->session->userdata('uid'), 'approved_on'  => date("Y-m-d H:i:s"));



							$this->$model->updateData($statusData, 'id_karigar_stone', $val['id_karigar_stone'], 'ret_karigar_stones');
						}
					} else if ($status == 3)  // For Status Hold

					{

						$statusData = array(

							'status'      => 3,



							'active'      => 1,



							'holded_by'   => $this->session->userdata('uid'),



							'holded_on'   => date("Y-m-d H:i:s"),

						);



						if ($approval_for == 1) // Karigar Stone Update

						{

							$this->$model->updateData($statusData, 'id_karigar_stone', $val['id_karigar_stone'], 'ret_karigar_stones');
						} else {

							$this->$model->updateData($statusData, 'id_karikar_wast', $val['id_karikar_wast'], 'ret_karikar_items_wastage');
						}
					} else  // Status Rejected

					{

						$statusData = array(



							'status' => 2,



							'active' => 0,



							'rejected_by' => $this->session->userdata('uid'),



							'rejected_on'  => date("Y-m-d H:i:s"),



						);



						if ($approval_for == 1) // Karigar stones Update

						{

							$this->$model->updateData($statusData, 'id_karigar_stone', $val['id_karigar_stone'], 'ret_karigar_stones');
						} else {

							$this->$model->updateData($statusData, 'id_karikar_wast', $val['id_karikar_wast'], 'ret_karikar_items_wastage');
						}
					}
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Karigar Approval',

						'operation'     => 'Add',

						'record'        =>  $id_karikar_wast,

						'remark'        => 'Karigar Approval added successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Approved successfully', 'class' => 'success', 'title' => 'Karigar Approval'));

					$return_data = array('message' => 'Aprroved successfully', 'status' => true);

					echo json_encode($return_data);
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Karigar Approval'));

					$return_data = array('message' => 'Unable to proceed the requested process', 'status' => false);

					echo json_encode($return_data);
				}



				break;



			case 'wastages_list':

				$SETT_MOD = self::SETT_MOD;

				$list = $this->$model->get_karigar_approvalWastage_list($_POST);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/karigar/list');

				$data = array(

					'list' => $list,

					'access' => $access

				);

				echo json_encode($data);

				break;



			case 'stones_list':

				$SETT_MOD = self::SETT_MOD;

				$list = $this->$model->get_karigar_approvalStones_list($_POST);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/karigar/list');

				$data = array(

					'list' => $list,

					'access' => $access

				);

				echo json_encode($data);

				break;



			case 'charges_list':

				$SETT_MOD = self::SETT_MOD;

				$list = $this->$model->get_karigar_approvalCharges_list($_POST);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/karigar/list');

				$data = array(

					'list' => $list,

					'access' => $access

				);

				echo json_encode($data);

				break;
		}



		/*Karigar Approval*/
	}



	function get_WastApprvKarigars()

	{

		$model	=	self::CAT_MODEL;

		$approval_for = $_POST['approval_for'];

		$data = $this->$model->get_WastApprvKarigars($approval_for);

		echo json_encode($data);
	}





	function get_KarigarWastPurity()

	{

		$model	=	self::CAT_MODEL;

		$data = $this->$model->getCatPurity($_POST['id_category']);

		echo json_encode($data);
	}



	function get_ActiveKYC()

	{

		$model	=	self::CAT_MODEL;

		$data = $this->$model->get_ActiveKYC();

		echo json_encode($data);
	}



	function getKycDetails()

	{



		$model	=	self::CAT_MODEL;

		$data = $this->$model->get_ActiveKYCDetails($_POST);

		echo json_encode($data);
	}



	function get_kar_img_by_id()

	{

		$model	=	self::CAT_MODEL;

		$data = $this->$model->get_karigar_images($_POST['id_karigar_wast']);

		echo json_encode($data);
	}



	function do_upload()

	{



		//echo"<pre>";print_r($_FILES);exit;



		$config['upload_path']          = self::IMG_PATH . "karigar/kyc/documents";

		$config['allowed_types']        = 'pdf|docx';

		$config['max_size']             = '15000000';



		if (!is_dir($config['upload_path'])) {

			mkdir($config['upload_path'], 0777, TRUE);
		}



		$this->load->library('upload', $config);



		$this->upload->initialize($config);



		if (!$this->upload->do_upload('doc_file')) {

			$error = array('status' => false, 'error' => $this->upload->display_errors());
		} else {

			$data = array('status' => true, 'response' => $this->upload->data());



			//echo"<pre>";print_r($data);exit;



		}



		echo json_encode($data);
	}





	//Diamond quality function Starts

	public function diamond($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'diamond', $type, $id, $status);
	}





	//V.A and M.C Settings

	public function wastage_mc_settings($type, $id = "")
	{

		$model = self::CAT_MODEL;

		$SETT_MOD = self::SETT_MOD;

		switch ($type) {

			case 'add':

				$data['main_content'] = "master/ret_selling_settings/form";

				$data['is_va_mc_based_on_branch'] = $this->$model->get_ret_settings('is_va_mc_based_on_branch');

				$this->load->view('layout/template', $data);

				break;


			case 'list':

				$data['main_content'] = "master/ret_selling_settings/list";

				$data['is_va_mc_based_on_branch'] = $this->$model->get_ret_settings('is_va_mc_based_on_branch');

				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/wastage_mc_settings/list');
				// print_r($data['access']);exit;


				$this->load->view('layout/template', $data);

				break;

			case 'edit':

				$data['main_content'] = "master/ret_selling_settings/form";

				$data['is_va_mc_based_on_branch'] = $this->$model->get_ret_settings('is_va_mc_based_on_branch');

				// echo "<pre>"; print_r($data); echo "</pre>"; exit;

				$this->load->view('layout/template', $data);

				break;


			case 'get_wastage_mc_settings':

				$data = $this->$model->get_wastage_mc_settings($id);

				echo json_encode($data);

				break;

			case 'get_wastage_mc_setting_WeightDetails':

				$data = $this->$model->get_wastage_details_settings($id);

				echo json_encode($data);

				break;

			case 'get_wastage_mc_setting_TouchDetails':

				$data = $this->$model->get_wastage_touch_details_settings($id);

				echo json_encode($data);

				break;

			case 'save':



				$addData = $_POST['settings'];

				/*echo "<pre>";
				print_r($_POST);exit;*/

				$is_va_mc_based_on_branch = $this->$model->get_ret_settings('is_va_mc_based_on_branch');

				if ($addData['id_design'] != 0 && $addData['id_sub_design'] != 0) {

					$responseData = $this->create_va_mc_settings($addData);
				} else {

					if ($addData['id_design'] == 0) {

						$design_array = $this->$model->get_active_design_products(array('id_product' => $addData['id_product']));

						foreach ($design_array as $design) {

							$addData['id_design'] = $design['design_no'];

							if ($addData['id_sub_design'] == 0) {

								$data = array('id_product' => $addData['id_product'], 'design_no' => $addData['id_design']);

								$Sub_design_array = $this->$model->get_ActiveSubDesigns($data);


								foreach ($Sub_design_array as $sub_design) {
									$addData['id_sub_design'] = $sub_design['id_sub_design'];
									$responseData = $this->create_va_mc_settings($addData);
								}
								$addData['id_sub_design'] = 0;
							} else {

								$responseData = $this->create_va_mc_settings($addData);
							}
						}
					} else {

						if ($addData['id_sub_design'] == 0) {

							$data = array('id_product' => $addData['id_product'], 'design_no' => $addData['id_design']);

							$Sub_design_array = $this->$model->get_ActiveSubDesigns($data);


							foreach ($Sub_design_array as $sub_design) {
								$addData['id_sub_design'] = $sub_design['id_sub_design'];
								$responseData = $this->create_va_mc_settings($addData);
							}
						} else {

							$responseData = $this->create_va_mc_settings($addData);
						}
					}
				}



				if ($this->db->trans_status() === TRUE) {



					$this->db->trans_commit();
				} else {

					$this->db->trans_rollback();

					$responseData = array('status' => false, 'message' => 'Unable to Proceed Your Request');
				}

				echo json_encode($responseData);

				break;

			case 'get_product_weight':

				$data = $this->$model->get_product_weight($_POST);

				echo json_encode($data);

				break;

			case 'delete':

				$status = $this->$model->delete_wastage_details_settings($id);

				if ($status) {

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'V.A and MC Settings',

						'operation'     => 'Delete',

						'record'        =>  $id,

						'remark'        => 'V.A and MC Settings deleted successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'V.A and MC Settings deleted successfully', 'class' => 'success', 'title' => 'Delete V.A and MC Settings'));
				} else {

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to delete V.A and MC Settings', 'class' => 'danger', 'title' => 'Delete V.A and MC Settings'));
				}

				redirect('admin_ret_catalog/wastage_mc_settings/list');

				break;

			case 'update':

				$addData = $_POST['settings'];

				// Known gap fix: same staleness check as create_va_mc_settings()
				// (the 'save' path) — 'update' had none of these at all.
				if (!empty($addData['id_product']) && !$this->$model->check_product_active($addData['id_product'])) {
					echo json_encode(array('status' => false, 'message' => 'Selected Product is no longer available. Please refresh and choose again.'));
					return;
				}
				if (!empty($addData['id_design']) && $addData['id_design'] != 0 && !$this->$model->check_design_active($addData['id_design'])) {
					echo json_encode(array('status' => false, 'message' => 'Selected Design is no longer available. Please refresh and choose again.'));
					return;
				}
				if (!empty($addData['id_sub_design']) && $addData['id_sub_design'] != 0 && !$this->$model->check_sub_design_active($addData['id_sub_design'])) {
					echo json_encode(array('status' => false, 'message' => 'Selected Sub Design is no longer available. Please refresh and choose again.'));
					return;
				}
				if (!empty($_POST['id_branch']) && !$this->$model->check_branch_active($_POST['id_branch'])) {
					echo json_encode(array('status' => false, 'message' => 'Selected Branch is no longer available. Please refresh and choose again.'));
					return;
				}

				$is_va_mc_based_on_branch = $this->$model->get_ret_settings('is_va_mc_based_on_branch');

				$id_selling_settings = $_POST['id_selling_settings'];

				$check = $this->$model->check_wastage_details_settings($id_selling_settings);

				$this->db->trans_begin();

				if ($addData['wastage_type'] == 1) {

					$settings = $this->$model->check_selling_rate_settings($addData['id_product'], $addData['id_design'], $addData['id_sub_design'], '', $id_selling_settings);

					if ($settings['status'] || $is_va_mc_based_on_branch == 1) {

						$insData = array(

							'id_product'     => $addData['id_product'],

							'id_design'      => $addData['id_design'],

							'id_sub_design'  => ($addData['id_sub_design'] != '' ? $addData['id_sub_design'] : NULL),

							'type'           => $addData['wastage_type'],

							'wastag_method'  => $addData['wastag_method'],

							'wastage_perc'   => ($addData['wastag_value'] != '' ? $addData['wastag_value'] : NULL),

							'wastage_wt'   => ($addData['wastag_wt'] != '' ? $addData['wastag_wt'] : NULL),

							'wastag_min'     => ($addData['min_wastag_value'] != '' ? $addData['min_wastag_value'] : NULL),

							'wastag_min_wt'   => ($addData['wastag_min_wt'] != '' ? $addData['wastag_min_wt'] : NULL),

							'mc_value'       => ($addData['mc_value'] != '' ? $addData['mc_value'] : NULL),

							'mc_min'         => ($addData['min_mc_value'] != '' ? $addData['min_mc_value'] : NULL),

							'mc_type'        => $addData['mc_type'],

							'margin_mrp'     => $addData['margin_mrp'],

						);





						if ($is_va_mc_based_on_branch == 1) {



							$branch = $_POST['id_branch'];

							$check = $this->$model->check_selling_rate_settings($addData['id_product'], $addData['id_design'], $addData['id_sub_design'], $branch, $id_selling_settings);



							if ($check['status']) {

								$insData['id_branch'] = $branch;



								$status = True;
							} else {



								$responseData = array('status' => $check['status'], 'message' => $check['message'], 'check' => $check);

								$status = FALSE;
							}

							if ($status) {



								$insId = $this->$model->updateData($insData, 'id_selling_settings', $id_selling_settings, 'ret_selling_settings');
							}
						} else {


							$insId = $this->$model->updateData($insData, 'id_selling_settings', $id_selling_settings, 'ret_selling_settings');
						}







						if ($insId) {

							$log_data = array(

								'id_log'        => $this->session->userdata('id_log'),

								'event_date'    => date("Y-m-d H:i:s"),

								'module'        => 'V.A and MC Settings',

								'operation'     => 'Update',

								'record'        =>  $insId,

								'remark'        => 'V.A and MC Settings Updated successfully'

							);

							$this->log_model->log_detail('insert', '', $log_data);

							$responseData = array('status' => true, 'message' => 'V.A and MC Settings Added successfully');

							if ($check) {

								$this->$model->deleteData('id_selling_settings', $id_selling_settings, 'ret_design_weight_range_wc');
							}
						}
					} else {

						$responseData = array('status' => $settings['status'], 'message' => $settings['message']);
					}
				} else {
					if ($is_va_mc_based_on_branch == 1) {

						$id_branch = $_POST['id_branch'];

						$check = $this->$model->check_selling_rate_settings($addData['id_product'], $addData['id_design'], $addData['id_sub_design'], $id_branch, $id_selling_settings);

						if ($check['status']) {

							$insData = array(

								'id_product'     => $addData['id_product'],

								'id_design'      => $addData['id_design'],

								'id_sub_design'  => ($addData['id_sub_design'] != '' ? $addData['id_sub_design'] : NULL),

								'type'           => $addData['wastage_type'],

								'wastag_method'  => (isset($addData['wastag_method']) && $addData['wastag_method'] != '' ? $addData['wastag_method'] : 1),

								'wastage_perc'   => NULL,

								'wastage_wt'     => NULL,

								'wastag_min'     => NULL,

								'wastag_min_wt'  => NULL,

								'mc_value'       => NULL,

								'mc_min'         => NULL,

								'mc_type'        => (isset($addData['mc_type']) && $addData['mc_type'] != '' ? $addData['mc_type'] : 1),

								'id_branch'      => $id_branch,

								'margin_mrp'     => (isset($addData['margin_mrp']) && $addData['margin_mrp'] != '' ? $addData['margin_mrp'] : NULL),

							);

							$this->$model->updateData($insData, 'id_selling_settings', $id_selling_settings, 'ret_selling_settings');
						} else {

							$id_selling_settings = NULL;
						}

						if ($id_selling_settings) {

							$this->$model->deleteData('id_selling_settings', $id_selling_settings, 'ret_design_weight_range_wc');

							if (isset($_POST['wcrange']) && is_array($_POST['wcrange'])) {
								foreach ($_POST['wcrange'] as $weightDetails) {

									if (($weightDetails['wc_method'] == 1 ? $weightDetails['wastage'] != '' : $weightDetails['wc_wt'] != '') && $weightDetails['making_charge'] != '') {
										$weightSettings = $this->$model->check_selling_rate_weight_settings($weightDetails['from_weight'], $weightDetails['to_weight'], $addData['id_product'], $addData['id_design'], $addData['id_sub_design'], $id_branch);

										if ($weightSettings['status']) {

											$itemDetails = array(

												'id_selling_settings' => $id_selling_settings,

												'wc_from_weight'      => $weightDetails['from_weight'],

												'wc_to_weight'        => $weightDetails['to_weight'],

												'wc_method'			  => $weightDetails['wc_method'],

												'wc_percent'          => ($weightDetails['wastage'] != '' ? $weightDetails['wastage'] : NULL),

												'wc_wt'				  => ($weightDetails['wc_wt'] != '' ? $weightDetails['wc_wt'] : NULL),

												'mc_type'             => ($weightDetails['mc_type'] != '' ? $weightDetails['mc_type'] : 1),

												'mc'                  => ($weightDetails['making_charge'] != '' ? $weightDetails['making_charge'] : NULL),

												'wc_min'              => ($weightDetails['wc_min'] != '' ? $weightDetails['wc_min'] : NULL),

												'wc_min_wt'           => ($weightDetails['wc_min_wt'] != '' ? $weightDetails['wc_min_wt'] : NULL),

												'mcrg_min'            => ($weightDetails['mcrg_min'] != '' ? $weightDetails['mcrg_min'] : NULL),

											);

											$this->$model->insertData($itemDetails, 'ret_design_weight_range_wc');
										}
									}
								}
							}

							$log_data = array(

								'id_log'        => $this->session->userdata('id_log'),

								'event_date'    => date("Y-m-d H:i:s"),

								'module'        => 'V.A and MC Settings',

								'operation'     => 'Update',

								'record'        =>  $id_selling_settings,

								'remark'        => 'V.A and MC Settings Updated successfully'

							);

							$this->log_model->log_detail('insert', '', $log_data);

							$responseData = array('status' => true, 'message' => 'V.A and MC Settings Updated successfully');
						} else {

							$responseData = array('status' => false, 'message' => $check['message']);
						}
					} else {

						$settings = $this->$model->check_selling_rate_settings($addData['id_product'], $addData['id_design'], $addData['id_sub_design'], '', $id_selling_settings);

						if ($settings['status']) {

							$insData = array(

								'id_product'     => $addData['id_product'],

								'id_design'      => $addData['id_design'],

								'id_sub_design'  => ($addData['id_sub_design'] != '' ? $addData['id_sub_design'] : NULL),

								'type'           => $addData['wastage_type'],

								'wastag_method'  => (isset($addData['wastag_method']) && $addData['wastag_method'] != '' ? $addData['wastag_method'] : 1),

								'wastage_perc'   => NULL,

								'wastage_wt'     => NULL,

								'wastag_min'     => NULL,

								'wastag_min_wt'  => NULL,

								'mc_value'       => NULL,

								'mc_min'         => NULL,

								'mc_type'        => (isset($addData['mc_type']) && $addData['mc_type'] != '' ? $addData['mc_type'] : 1),

								'margin_mrp'     => (isset($addData['margin_mrp']) && $addData['margin_mrp'] != '' ? $addData['margin_mrp'] : NULL),

							);

							$this->$model->updateData($insData, 'id_selling_settings', $id_selling_settings, 'ret_selling_settings');

							$this->$model->deleteData('id_selling_settings', $id_selling_settings, 'ret_design_weight_range_wc');

							if (isset($_POST['wcrange']) && is_array($_POST['wcrange'])) {

								foreach ($_POST['wcrange'] as $weightDetails) {

									if (($weightDetails['wc_method'] == 1 ? $weightDetails['wastage'] != '' : $weightDetails['wc_wt'] != '') && $weightDetails['making_charge'] != '') {

										$weightSettings = $this->$model->check_selling_rate_weight_settings($weightDetails['from_weight'], $weightDetails['to_weight'], $addData['id_product'], $addData['id_design'], $addData['id_sub_design']);

										if ($weightSettings['status']) {

											$itemDetails = array(

												'id_selling_settings' => $id_selling_settings,

												'wc_from_weight'      => $weightDetails['from_weight'],

												'wc_to_weight'        => $weightDetails['to_weight'],

												'wc_method'			  => $weightDetails['wc_method'],

												'wc_percent'          => ($weightDetails['wastage'] != '' ? $weightDetails['wastage'] : NULL),

												'wc_wt'				  => ($weightDetails['wc_wt'] != '' ? $weightDetails['wc_wt'] : NULL),

												'mc'                  => ($weightDetails['making_charge'] != '' ? $weightDetails['making_charge'] : NULL),

												'mc_type'             => ($weightDetails['mc_type'] != '' ? $weightDetails['mc_type'] : 1),

												'wc_min'              => ($weightDetails['wc_min'] != '' ? $weightDetails['wc_min'] : NULL),

												'wc_min_wt'           => ($weightDetails['wc_min_wt'] != '' ? $weightDetails['wc_min_wt'] : NULL),

												'mcrg_min'            => ($weightDetails['mcrg_min'] != '' ? $weightDetails['mcrg_min'] : NULL),

											);

											$this->$model->insertData($itemDetails, 'ret_design_weight_range_wc');
										}
									}
								}
							}

							$log_data = array(

								'id_log'        => $this->session->userdata('id_log'),

								'event_date'    => date("Y-m-d H:i:s"),

								'module'        => 'V.A and MC Settings',

								'operation'     => 'Update',

								'record'        =>  $id_selling_settings,

								'remark'        => 'V.A and MC Settings Updated successfully'

							);

							$this->log_model->log_detail('insert', '', $log_data);

							$responseData = array('status' => true, 'message' => 'V.A and MC Settings Updated successfully');
						} else {

							$responseData = array('status' => false, 'message' => $settings['message']);
						}
					}
				}



				if ($this->db->trans_status() === TRUE) {



					$this->db->trans_commit();
				} else {

					$this->db->trans_rollback();

					$responseData = array('status' => false, 'message' => 'Unable to Proceed Your Request');
				}

				echo json_encode($responseData);

				break;

			case 'get_wastage_details':

				$data = $this->$model->get_wastage_details_settings($id);

				echo json_encode($data);

				break;

			default:



				$list = $this->$model->ajax_get_wastage_settings_details($_POST);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/wastage_mc_settings/list');

				//$access = $this->$SETT_MOD->get_access('admin_ret_catalog/selling_price/list');

				$data = array(

					'list' => $list,

					'access' => $access

				);

				echo json_encode($data);
		}
	}

	//V.A and M.C Settings



	function karigar_general($type = "", $id = "")

	{



		$model = self::CAT_MODEL;

		$this->load->helper('lmx/core/access_helper');

		switch ($type) {

			case 'save':

				//echo"<pre>";print_r($_POST);exit;

				$addData = $_POST['karigar'];

				// Same gap class already fixed in karigar()'s 'save' case: this is a
				// second, unhardened entry point into ret_karigar (tabbed-wizard flow)
				// that had no permission gate and no required/duplicate checks at all.
				if (!lmx_can($this, 'admin_ret_catalog/karigar/list', 'add')) {
					echo json_encode(array('status' => false, 'message' => 'Access Denied', 'class' => 'danger', 'title' => 'Add User'));
					return;
				}
				if (!isset($addData['first_name']) || trim($addData['first_name']) === '') {
					echo json_encode(array('status' => false, 'message' => 'First Name is required', 'class' => 'danger', 'title' => 'Add User'));
					return;
				}
				if (!empty($addData['user_name']) && $this->$model->check_karigar_username_exists($addData['user_name'])) {
					echo json_encode(array('status' => false, 'message' => 'Username "' . $addData['user_name'] . '" already exists', 'class' => 'danger', 'title' => 'Add User'));
					return;
				}
				if (!empty($addData['gst_number']) && $this->$model->check_karigar_gst_exists($addData['gst_number'])) {
					echo json_encode(array('status' => false, 'message' => 'GST Number "' . $addData['gst_number'] . '" already exists', 'class' => 'danger', 'title' => 'Add User'));
					return;
				}
				if (!empty($addData['pan_number']) && $this->$model->check_karigar_pan_exists($addData['pan_number'])) {
					echo json_encode(array('status' => false, 'message' => 'PAN Number "' . $addData['pan_number'] . '" already exists', 'class' => 'danger', 'title' => 'Add User'));
					return;
				}

				$data = array(

					'firstname'	           => strtoupper($addData['first_name']),

					'lastname'             => $addData['last_name_karigar'],

					//'code_karigar'         => $addData['karigar_code'],

					'fin_year_code'         => $addData['fin_year_code'],

					'opening_balance_amount' => $addData['opening_balance_amount'],

					'karigar_type'         => $addData['user_type'],

					'is_tcs'               => $addData['is_tcs'],

					'is_tds'               => $addData['is_tds'],

					'tcs_tax'              => ($addData['tcs_tax'] != '' && $addData['tcs_tax'] != NULL ? $addData['tcs_tax'] : 0),

					'tds_tax'              => ($addData['tds_tax'] != '' && $addData['tds_tax'] != NULL ? $addData['tds_tax'] : 0),

					'karigar_for'          => $addData['karigar_for'],

					'address1'   		   => (!empty($addData['address1']) ? $addData['address1'] : NULL),

					'address2'   		   => (!empty($addData['address2']) ? $addData['address2'] : NULL),

					'address3'             => (!empty($addData['address3']) ? $addData['address3'] : NULL),

					'id_country'		   => (!empty($addData['country']) ? $addData['country'] : NULL),

					'id_state'		       => (!empty($addData['stateval']) ? $addData['stateval'] : NULL),

					'id_city'		       => (!empty($addData['cityval']) ? $addData['cityval'] : NULL),

					'pincode'		       => (!empty($addData['pincode']) ? $addData['pincode'] : NULL),

					'contactno2'		   => (!empty($addData['phone']) ? $addData['phone'] : NULL),

					'status_karigar'	   => 1,

					'email'		           => (!empty($addData['email']) ? $addData['email'] : NULL),

					'contactno1'		   => (!empty($addData['mobile']) ? $addData['mobile'] : NULL),

					'urname'		       => (!empty($addData['user_name']) ? $addData['user_name'] : NULL),

					'psword'		       => (!empty($addData['password']) ? $addData['password'] : NULL),

					'company'		       => (!empty($addData['company_karigar']) ? $addData['company_karigar'] : NULL),

					'gst_number'		   => (!empty($addData['gst_number']) ? $addData['gst_number'] : NULL),

					'ifsc_code'		       => (!empty($addData['ifsc_code']) ? $addData['ifsc_code'] : NULL),

					'acc_number'		   => (!empty($addData['acc_number']) ? $addData['acc_number'] : NULL),

					'pan_no'		   	   => (!empty($addData['pan_number']) ? $addData['pan_number'] : NULL),

					//'karigar_calc_type'	   =>(!empty($addData['karigar_calc_type']) ? $addData['karigar_calc_type']:NULL),

					'remarks'              => (!empty($addData['remarks']) ? $addData['remarks'] : NULL),

					'bank_name'            => (!empty($addData['bank_name']) ? $addData['bank_name'] : NULL),

					'acc_holder_name'      => (!empty($addData['acc_holder']) ? $addData['acc_holder'] : NULL),

					'createdon'            => date("Y-m-d H:i:s"),

					'createdby'            => $this->session->userdata('uid')

				);

				//print_r($data);exit;

				$this->db->trans_begin();

				if ($_FILES['image']['name']) {

					$path = 'assets/img/karigar/';

					$file_name = $_FILES['image']['name'];

					if (!is_dir($path)) {

						mkdir($path, 0777, TRUE);
					} else {

						$file = $path . $file_name;

						chmod($path, 0777);

						// unlink($file);

					}

					$img = $_FILES['image']['tmp_name'];

					$filename = $id . '_' . mt_rand(120, 1230) . ".jpg";

					$imgpath  = 'assets/img/karigar/' . $filename;

					$upload   = $this->upload_img('image', $imgpath, $img);

					$data['image'] = $filename;

					$status = $this->$model->updateData($data, 'id_karigar', $id, 'ret_karigar');
				}

				$id_karigar = $this->$model->insertData($data, 'ret_karigar');

				//print_r($this->db->last_query());exit;

				if ($id_karigar != '') {

					$data = array(

						'code_karigar'         => $id_karigar,

					);

					$this->$model->updateData($data, 'id_karigar', $id_karigar, 'ret_karigar');
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Karigar',

						'operation'     => 'Add',

						'record'        => $id_karigar,

						'remark'        => 'Karigar added successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$result = array('message' => 'New User added successfully!..', 'class' => 'success', 'title' => 'Add User : ', 'status' => True, 'value' => $id_karigar);

					$this->session->set_flashdata('chit_alert', array('message' => 'New User added successfully!..', 'class' => 'success', 'title' => 'Add User',));
				} else {
					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Add User : ');

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Add User'));
				}

				//redirect('admin_ret_catalog/karigar/list');

				echo json_encode($result);

				break;

			case 'update':

				//echo"<pre>";print_r($_POST);exit;

				$addData = $_POST['karigar'];

				if (!lmx_can($this, 'admin_ret_catalog/karigar/list', 'edit')) {
					echo json_encode(array('status' => false, 'message' => 'Access Denied', 'class' => 'danger', 'title' => 'Update User'));
					return;
				}
				if (!isset($addData['first_name']) || trim($addData['first_name']) === '') {
					echo json_encode(array('status' => false, 'message' => 'First Name is required', 'class' => 'danger', 'title' => 'Update User'));
					return;
				}
				if (!empty($addData['user_name']) && $this->$model->check_karigar_username_exists($addData['user_name'], $id)) {
					echo json_encode(array('status' => false, 'message' => 'Username "' . $addData['user_name'] . '" already exists', 'class' => 'danger', 'title' => 'Update User'));
					return;
				}
				if (!empty($addData['gst_number']) && $this->$model->check_karigar_gst_exists($addData['gst_number'], $id)) {
					echo json_encode(array('status' => false, 'message' => 'GST Number "' . $addData['gst_number'] . '" already exists', 'class' => 'danger', 'title' => 'Update User'));
					return;
				}
				if (!empty($addData['pan_number']) && $this->$model->check_karigar_pan_exists($addData['pan_number'], $id)) {
					echo json_encode(array('status' => false, 'message' => 'PAN Number "' . $addData['pan_number'] . '" already exists', 'class' => 'danger', 'title' => 'Update User'));
					return;
				}

				$data = array(

					'firstname'	           => strtoupper($addData['first_name']),

					'lastname'             => $addData['last_name_karigar'],

					'code_karigar'         => $addData['karigar_code'],

					'fin_year_code'         => $addData['fin_year_code'],

					'opening_balance_amount' => $addData['opening_balance_amount'],

					'karigar_type'         => $addData['user_type'],

					'is_tcs'               => $addData['is_tcs'],

					'is_tds'               => $addData['is_tds'],

					'tcs_tax'              => $addData['tcs_tax'],

					'tds_tax'              => $addData['tds_tax'],

					'karigar_for'          => $addData['karigar_for'],

					'address1'   		   => (!empty($addData['address1']) ? $addData['address1'] : NULL),

					'address2'   		   => (!empty($addData['address2']) ? $addData['address2'] : NULL),

					'address3'             => (!empty($addData['address3']) ? $addData['address3'] : NULL),

					'id_country'		   => (!empty($addData['country']) ? $addData['country'] : NULL),

					'id_state'		       => (!empty($addData['stateval']) ? $addData['stateval'] : NULL),

					'id_city'		       => (!empty($addData['cityval']) ? $addData['cityval'] : NULL),

					'pincode'		       => (!empty($addData['pincode']) ? $addData['pincode'] : NULL),

					'contactno2'		   => (!empty($addData['phone']) ? $addData['phone'] : NULL),

					'status_karigar'	   => 1,

					'email'		           => (!empty($addData['email']) ? $addData['email'] : NULL),

					'contactno1'		   => (!empty($addData['mobile']) ? $addData['mobile'] : NULL),

					'urname'		       => (!empty($addData['user_name']) ? $addData['user_name'] : NULL),

					'psword'		       => (!empty($addData['password']) ? $addData['password'] : NULL),

					'company'		       => (!empty($addData['company_karigar']) ? $addData['company_karigar'] : NULL),

					'gst_number'		   => (!empty($addData['gst_number']) ? $addData['gst_number'] : NULL),

					'ifsc_code'		       => (!empty($addData['ifsc_code']) ? $addData['ifsc_code'] : NULL),

					'acc_number'		   => (!empty($addData['acc_number']) ? $addData['acc_number'] : NULL),

					'bank_name'            => (!empty($addData['bank_name']) ? $addData['bank_name'] : NULL),

					'acc_holder_name'      => (!empty($addData['acc_holder']) ? $addData['acc_holder'] : NULL),

					'pan_no'		   	   => (!empty($addData['pan_number']) ? $addData['pan_number'] : NULL),

					//'karigar_calc_type'	   =>(!empty($addData['karigar_calc_type']) ? $addData['karigar_calc_type']:NULL),

					'remarks'	           => (!empty($addData['remarks']) ? $addData['remarks'] : NULL),

					'createdon'            => date("Y-m-d H:i:s"),

					'createdby'            => $this->session->userdata('uid')

				);

				$this->db->trans_begin();

				if ($_FILES['karigar']['name']['user_img']) {

					$path = 'assets/img/karigar/';

					if (!is_dir($path)) {

						mkdir($path, 0777, TRUE);
					} else {

						$file = $path;

						chmod($path, 0777);
					}

					$img = $_FILES['karigar']['tmp_name']['user_img'];



					$filename = $id . '_' . mt_rand(00001, 99999) . ".jpg";

					$imgpath  = 'assets/img/karigar/' . $filename;

					$upload   = $this->upload_img('image', $imgpath, $img);

					$data['image'] = $filename;
				}



				$id_karigar = $this->$model->updateData($data, 'id_karigar', $id, 'ret_karigar');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Karigar',

						'operation'     => 'Update',

						'record'        => $id_karigar,

						'remark'        => 'Karigar Updated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$result = array('message' => 'User Updated successfully!..', 'class' => 'success', 'title' => 'Update User : ', 'status' => True, 'value' => $id_karigar);

					$this->session->set_flashdata('chit_alert', array('message' => 'User Updated successfully!..', 'class' => 'success', 'title' => 'Update User',));
				} else {

					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Update User : ');

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Update User'));
				}

				//redirect('admin_ret_catalog/karigar/list');

				echo json_encode($result);

				break;
		}
	}





	function karigar_wastage($type = "", $id = "")

	{

		$model = self::CAT_MODEL;

		$this->load->helper('lmx/core/access_helper');

		switch ($type) {



			case 'save':

				//echo"<pre>";print_r($_POST);exit;

				if (!lmx_can($this, 'admin_ret_catalog/karigar/list', 'add')) {
					echo json_encode(array('status' => false, 'message' => 'Access Denied', 'class' => 'danger', 'title' => 'Add Contract Pricing'));
					return;
				}

				$product_details = $_POST['o_item'];

				$id_karigar = $_POST['karigar_id'];

				foreach ($product_details as $val) {

					if ($val['is_item_select'] == 1) {

						$wast = array(



							'id_karikar'	=>	$id_karigar,

							'id_category'   =>  $val['category'],

							'id_purity'     =>  $val['purity'],

							'karigar_calc_type' => $val['kar_calc_type'],

							'id_product' 	=>	$val['product'],

							'id_design' 	=>	$val['design'],

							'id_sub_design' =>	$val['sub_design'],

							'wastage_type'  => ($val['va_type'] != '' ? $val['va_type'] : 1),

							'wastage_per' 	=> ($val['va_type'] == 1 ? ($val['wast_percent'] != '' ? $val['wast_percent'] : 0) : 0),

							'wastage_wt' 	=> ($val['va_type'] == 2 ? ($val['wast_wgt'] != '' ? $val['wast_wgt'] : 0) : 0),

							'mc_type'       =>  $val['id_mc_type'],

							'mc_value'      => ($val['mc'] != '' ? $val['mc'] : 0),

							'pur_touch'     => ($val['pur_touch'] != '' ? $val['pur_touch'] : 0),

							'calc_type'	    =>	$val['calc_type'],

							'stone_cal_type'  => ($val['stn_calc_type'] != '' ? $val['stn_calc_type'] : 1),

							'uom_id'             =>   $val['uom_id'],

							'rate_per_gram'      =>   $val['stone_rate'],

							'quality_id'         =>  $val['quality_id'],

							'from_wt'            =>  $val['from_wt'],

							'to_wt'              =>  $val['to_wt'],

							'active'        =>  0,

							'status'        =>  0,

							'created_on'	=>  date("Y-m-d H:i:s"),

							'created_by'    =>  $this->session->userdata('uid')

						);

						//print_r($wast);exit;

						$this->db->trans_begin();

						$id_karikar_wast = $this->$model->insertData($wast, 'ret_karikar_items_wastage');

						if ($id_karikar_wast) {

							/*Karigar Wastage Product Image*/

							$WastProImg = $val['order_img'];

							$p_ImgData = json_decode($WastProImg);

							$is_defalut_img = '';

							$is_default = 0;

							if (sizeof($p_ImgData) > 0) {

								$_FILES['precious']    =  array();

								foreach ($p_ImgData as $key => $precious) {

									if ($precious->is_default == 1) {

										$is_defalut_img = $key;
									}

									$imgFile = $this->base64ToFile($precious->src);

									$_FILES['precious'][] = $imgFile;
								}
							}

							if (!empty($p_ImgData)) {

								$folder =  self::IMG_PATH . "karigar/product_wastage/" . $id_karikar_wast;

								if (!is_dir($folder)) {

									mkdir($folder, 0777, TRUE);
								}

								if (isset($_FILES['precious'])) {

									//echo "<pre>";print_r($_FILES['wast_pro_images']);exit;



									foreach ($_FILES['precious'] as $file_key => $file_val) {

										if ($file_val['name']) {

											$img_name =  "$id_karikar_wast" . mt_rand(100001, 999999) . ".jpg";

											$path = $folder . "/" . $img_name;

											$result = $this->upload_img('image', $path, $file_val['tmp_name']);

											if ($result) {

												if ($is_defalut_img == $file_key) {

													$is_default = 1;
												} else {

													$is_default = 0;
												}

												$imgData = array('is_default' => $is_default, 'id_karikar_wast' => $id_karikar_wast, 'image_name' => $img_name);

												$this->$model->insertData($imgData, 'ret_karigar_item_wast_pro_images');
											}
										}
									}
								}
							}

							/*Karigar Wastage Product Image*/

							$charges_details = json_decode($val['charges_details'], true);

							if (sizeof($charges_details) > 0) {

								foreach ($charges_details as $charge) {

									$charge_data = array(

										'id_karigar'         => $id,

										'id_karikar_wast'    => $id_karikar_wast,

										'charge_id'          => $charge['charge_id'],

										'calc_type'          => $charge['calc_type'],

										'charge_value'       => $charge['charge_value'],

										'active'             => 0,

										'updated_on'	     => date("Y-m-d H:i:s"),

										'updated_by'         => $this->session->userdata('uid')

									);

									$this->$model->insertData($charge_data, 'ret_karigar_charges');
								}
							}
						}
					}
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$result = array('message' => 'Contract Pricing added successfully!..', 'class' => 'success', 'title' => 'Add Contract Pricing : ', 'status' => True, 'value' => $id_karigar);

					$this->session->set_flashdata('chit_alert', array('message' => 'Contract Price added successfully!..', 'class' => 'success', 'title' => 'Add User',));
				} else {

					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Add Contract Pricing : ');

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Add User'));
				}

				echo json_encode($result);

				break;

			case 'update':

				//echo"<pre>";print_r($_POST);exit;

				if (!lmx_can($this, 'admin_ret_catalog/karigar/list', 'edit')) {
					echo json_encode(array('status' => false, 'message' => 'Access Denied', 'class' => 'danger', 'title' => 'Update Contract Pricing'));
					return;
				}

				$product_details = $_POST['o_item'];

				foreach ($product_details as $val) {

					if ($val['is_item_select'] == 1) {

						$wast = array(

							'id_karikar'	=>	$id,

							'id_category'   =>  $val['category'],

							'id_product' 	=>	$val['product'],

							'id_design' 	=>	$val['design'],

							'id_sub_design' =>	$val['sub_design'],

							'id_purity'     =>  $val['purity'],

							'wastage_type'  => ($val['va_type'] != '' ? $val['va_type'] : 1),

							'wastage_per' 	=> ($val['wast_percent'] != '' ? $val['wast_percent'] : 0),

							'wastage_wt' 	=> ($val['wast_wgt'] != '' ? $val['wast_wgt'] : 0),

							'mc_type'       =>  $val['id_mc_type'],

							'mc_value'      => ($val['mc'] != '' ? $val['mc'] : 0),

							'pur_touch'     => ($val['pur_touch'] != '' ? $val['pur_touch'] : 0),

							'karigar_calc_type' => $val['kar_calc_type'],

							'calc_type'	    =>	$val['calc_type'],

							'stone_cal_type'  => ($val['stn_calc_type'] != '' ? $val['stn_calc_type'] : 1),

							'uom_id'             =>   $val['uom_id'],

							'rate_per_gram'      =>   $val['stone_rate'],

							'quality_id'         =>  $val['quality_id'],

							'from_wt'            =>  $val['from_wt'],

							'to_wt'              =>  $val['to_wt'],

							'active'        =>  0,

							'status'        =>  0,

							'created_on'	=>  date("Y-m-d H:i:s"),

							'created_by'    =>  $this->session->userdata('uid')

						);



												$id_karikar_wast = '';
						// If existing row (id_karikar_wast sent from form), UPDATE; otherwise INSERT new row
						if(!empty($val['id_karikar_wast']) && $val['id_karikar_wast'] > 0)
						{
							$wast['status'] = 0;  // Reset for re-approval
							$wast['active'] = 0;
							$this->$model->updateData($wast, 'id_karikar_wast', $val['id_karikar_wast'], 'ret_karikar_items_wastage');
							$id_karikar_wast = $val['id_karikar_wast'];
							// Delete old charges for this wastage row before re-inserting
							$this->$model->deleteData('id_karikar_wast', $id_karikar_wast, 'ret_karigar_charges');
						}
						else
						{
							$id_karikar_wast = $this->$model->insertData($wast, 'ret_karikar_items_wastage');
						}

						if ($id_karikar_wast) {

							/*Karigar Wastage Product Image*/

							$WastProImg = $val['order_img'];

							$p_ImgData = json_decode($WastProImg);

							$is_defalut_img = '';

							$is_default = 0;

							if (sizeof($p_ImgData) > 0) {

								$_FILES['precious']    =  array();



								foreach ($p_ImgData as $key => $precious) {

									if ($precious->is_default == 1) {

										$is_defalut_img = $key;
									}

									$imgFile = $this->base64ToFile($precious->src);

									$_FILES['precious'][] = $imgFile;
								}
							}



							if (!empty($p_ImgData)) {

								$folder =  self::IMG_PATH . "karigar/product_wastage/" . $id_karikar_wast;



								if (!is_dir($folder)) {

									mkdir($folder, 0777, TRUE);
								}



								if (isset($_FILES['precious'])) {

									//echo "<pre>";print_r($_FILES['wast_pro_images']);exit;



									foreach ($_FILES['precious'] as $file_key => $file_val) {

										//echo "<pre>";print_r($file_val);exit;

										if ($file_val['name']) {

											$img_name =  "$id_karikar_wast" . mt_rand(100001, 999999) . ".jpg";

											$path = $folder . "/" . $img_name;

											$result = $this->upload_img('image', $path, $file_val['tmp_name']);

											if ($result) {

												if ($is_defalut_img == $file_key) {

													$is_default = 1;
												} else {

													$is_default = 0;
												}



												$imgData = array('is_default' => $is_default, 'id_karikar_wast' => $id_karikar_wast, 'image_name' => $img_name);

												$this->$model->insertData($imgData, 'ret_karigar_item_wast_pro_images');
											}
										}
									}
								}
							}



							/*Karigar Wastage Product Image*/

							$charges_details = json_decode($val['charges_details'], true);

							if (sizeof($charges_details) > 0) {

								foreach ($charges_details as $charge) {

									$charge_data = array(

										'id_karigar'         => $id,

										'id_karikar_wast'    => $id_karikar_wast,

										'charge_id'          => $charge['charge_id'],

										'charge_value'       => $charge['charge_value'],

										'calc_type'          => $charge['calc_type'],

										'active'             => 0,

										'updated_on'	     => date("Y-m-d H:i:s"),

										'updated_by'         => $this->session->userdata('uid')

									);

									$this->$model->insertData($charge_data, 'ret_karigar_charges');
								}
							}
						}
					}
				}

				// Delete rows that were removed from the form by the user
				// Collect all id_karikar_wast values still present in POST
				$posted_wast_ids = array();
				foreach($product_details as $val)
				{
					if(!empty($val['id_karikar_wast']) && $val['id_karikar_wast'] > 0)
					{
						$posted_wast_ids[] = $val['id_karikar_wast'];
					}
				}

				// Get all active DB rows for this karigar
				$existing_rows = $this->$model->getWastageDetails($id);
				foreach($existing_rows as $row)
				{
					// If DB row is active and NOT in POST → user deleted it from form
					if($row['active'] == 1 && !in_array($row['id_karikar_wast'], $posted_wast_ids))
					{
						// Delete the wastage row and its charges
						$this->$model->deleteData('id_karikar_wast', $row['id_karikar_wast'], 'ret_karigar_charges');
						$this->$model->deleteData('id_karikar_wast', $row['id_karikar_wast'], 'ret_karikar_items_wastage');
					}
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$result = array('message' => 'Contract Pricing Updated successfully!..', 'class' => 'success', 'title' => 'Update Contract Pricing : ', 'status' => True, 'value' => $id);

					$this->session->set_flashdata('chit_alert', array('message' => 'Contract Price updated successfully!..', 'class' => 'success', 'title' => 'Update User',));
				} else {

					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Update Contract Pricing : ');

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Update User'));
				}

				echo json_encode($result);



				break;
		}
	}



	function karigar_stone($type = "", $id = "")

	{

		$model = self::CAT_MODEL;

		$this->load->helper('lmx/core/access_helper');

		switch ($type) {

			case 'save':

				if (!lmx_can($this, 'admin_ret_catalog/karigar/list', 'add')) {
					echo json_encode(array('status' => false, 'message' => 'Access Denied', 'class' => 'danger', 'title' => 'Add Stones'));
					return;
				}

				$stone_details = $_POST['stn'];

				//echo "<pre>";print_r($stone_details);exit;

				$id_karigar = $_POST['karigar_id'];

				foreach ($stone_details as  $s) {

					if ($s['is_stn_select'] == 1) {

						$stn = array(

							'id_karigar'         =>   $id_karigar,

							'stone_type'         =>   $s['stn_type'],

							'stone_id'           =>   $s['stn_name'],

							'stone_cal_type'     =>   $s['stn_calc_type'],

							'uom_id'             =>   $s['uom_id'],

							'rate_per_gram'      =>   $s['stone_rate'],

							'quality_id'         =>  $s['quality_id'],

							'from_wt'            =>  $s['from_wt'],

							'to_wt'              =>  $s['to_wt'],

							'active'        =>  0,

							'status'        =>  0,

							'created_on'	=>  date("Y-m-d H:i:s"),

							'created_by'    =>  $this->session->userdata('uid')



						);

						$this->db->trans_begin();

						$this->$model->insertData($stn, 'ret_karigar_stones');
					}
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$result = array('message' => 'Stone added successfully!..', 'class' => 'success', 'title' => 'Add Stones : ', 'status' => True, 'value' => $id_karigar);

					$this->session->set_flashdata('chit_alert', array('message' => 'Stones added successfully!..', 'class' => 'success', 'title' => 'Add User',));
				} else {

					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Add Contract Pricing : ');

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Add User'));
				}

				echo json_encode($result);



				break;

			case 'update':

				if (!lmx_can($this, 'admin_ret_catalog/karigar/list', 'edit')) {
					echo json_encode(array('status' => false, 'message' => 'Access Denied', 'class' => 'danger', 'title' => 'Update Stones'));
					return;
				}

				$stone_details = $_POST['stn'];

				//echo"<pre>";print_r($_POST);exit;

				foreach ($stone_details as  $s) {

					if ($s['is_stn_select'] == 1) {

						$stn = array(

							'id_karigar'         =>   $id,

							'stone_type'         =>   $s['stn_type'],

							'stone_id'           =>   $s['stn_name'],

							'stone_cal_type'     =>   $s['stn_calc_type'],

							'uom_id'             =>   $s['uom_id'],

							'rate_per_gram'      =>   $s['stone_rate'],

							'quality_id'         =>  $s['quality_id'],

							'from_wt'            =>  $s['from_wt'],

							'to_wt'              =>  $s['to_wt'],

							'active'             =>  0,

							'status'             =>  0,

							'updated_on'	     =>  date("Y-m-d H:i:s"),

							'updated_by'         =>  $this->session->userdata('uid')



						);

						$this->db->trans_begin();

						$this->$model->insertData($stn, 'ret_karigar_stones');
					}
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$result = array('message' => 'Stone updated successfully!..', 'class' => 'success', 'title' => 'Update Stones : ', 'status' => True, 'value' => $id);

					$this->session->set_flashdata('chit_alert', array('message' => 'Stones updated successfully!..', 'class' => 'success', 'title' => 'Updated User',));
				} else {

					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Update Contract Pricing : ');

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Update User'));
				}

				echo json_encode($result);

				break;
		}
	}



	function karigar_kyc($type = "", $id = "")

	{

		$model = self::CAT_MODEL;

		$this->load->helper('lmx/core/access_helper');

		switch ($type) {

			case 'save':

				if (!lmx_can($this, 'admin_ret_catalog/karigar/list', 'add')) {
					echo json_encode(array('status' => false, 'message' => 'Access Denied', 'class' => 'danger', 'title' => 'Add KYC'));
					return;
				}

				$kyc_details = $_POST['kyc_det'];

				$bank_details = $_POST['bank'];

				$id_karigar = $_POST['karigar_id'];





				foreach ($kyc_details as $val) {

					$kyc = array(

						'id_karigar'	=>	$id_karigar,

						'id_kyc' 	    =>	$val['proof_name'],

						'kyc_number'    =>  $val['id_reg_exp'],

						'document'      =>  $val['document_file'],

						'created_on'	=>  date("Y-m-d H:i:s"),

						'created_by'    =>  $this->session->userdata('uid')

					);



					if ($val['img_type'] == 1) {

						$img_type = 'jpg';
					} else if ($val['img_type'] == 2) {

						$img_type = 'jpeg';
					} else {

						$img_type = 'png';
					}



					if ($val['order_img_fr'] != '') {

						$p_ImgData = [];

						$p_ImgData = json_decode($val['order_img_fr']);

						if (sizeof($p_ImgData) > 0) {

							foreach ($p_ImgData as $precious) {

								$imgFile = $this->base64ToFile($precious->src);

								$_FILES['order_img_fr'][] = $imgFile;
							}
						}

						if (!empty($_FILES)) {

							$img_arr = array();

							$folder =  self::IMG_PATH . "karigar/kyc/";

							if (!is_dir($folder)) {

								mkdir($folder, 0777, TRUE);
							}

							if (isset($_FILES['order_img_fr'])) {

								$order_images = "";



								foreach ($_FILES['order_img_fr'] as $file_key => $file_val) {



									if ($file_val['name']) {

										// unlink($folder."/".$product['image']);

										$img_name =  $insOrderDet . "_" . mt_rand(100001, 999999) . '.' . $img_type;

										$path = $folder . "/" . $img_name;

										$result = $this->upload_img('image', $path, $file_val['tmp_name']);

										if ($result) {

											$kyc['front_images'] = $img_name;
										}
									}
								}
							}
						}
					}

					if ($val['order_img_bk'] != '') {



						$p_ImgData = [];

						$p_ImgData = json_decode($val['order_img_bk']);

						if (sizeof($p_ImgData) > 0) {

							foreach ($p_ImgData as $precious) {

								$imgFile = $this->base64ToFile($precious->src);

								$_FILES['order_img_bk'][] = $imgFile;
							}
						}

						if (!empty($_FILES)) {

							$img_arr = array();

							$folder =  self::IMG_PATH . "karigar/kyc/";

							if (!is_dir($folder)) {

								mkdir($folder, 0777, TRUE);
							}

							if (isset($_FILES['order_img_bk'])) {

								$order_images = "";



								foreach ($_FILES['order_img_bk'] as $file_key => $file_val) {



									if ($file_val['name']) {

										// unlink($folder."/".$product['image']);

										$img_name =  $insOrderDet . "_" . mt_rand(100001, 999999) . '.' . $img_type;

										$path = $folder . "/" . $img_name;

										$result = $this->upload_img('image', $path, $file_val['tmp_name']);

										if ($result) {

											$kyc['back_images'] = $img_name;
										}
									}
								}
							}
						}
					}



					$id_karigar_kyc = $this->$model->insertData($kyc, 'ret_karigar_kyc');
				}



				if (!empty($bank_details)) {

					$arraybank = array();

					foreach ($bank_details['bank_name'] as $key => $val) {

						$arraybank = array(

							'id_karigar'     =>	 $id_karigar,

							'account_name'   =>	 $bank_details['acc_holder'][$key],

							'account_number' =>	 $bank_details['acc_number'][$key],

							'bank_name'      =>	 $bank_details['bank_name'][$key],

							'ifsc_code '     =>	 $bank_details['ifsc_code'][$key],

						);

						$this->$model->insertData($arraybank, 'ret_karigar_bank_acc_details');
					}
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$result = array('message' => 'KYC added successfully!..', 'class' => 'success', 'title' => 'Add KYC : ', 'status' => True, 'value' => $id_karigar);

					$this->session->set_flashdata('chit_alert', array('message' => 'KYC added successfully!..', 'class' => 'success', 'title' => 'Add User',));
				} else {

					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Add KYC : ');

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Update User'));
				}

				echo json_encode($result);



				break;

			case 'update':

				//echo"<pre>";print_r($id);exit;

				if (!lmx_can($this, 'admin_ret_catalog/karigar/list', 'edit')) {
					echo json_encode(array('status' => false, 'message' => 'Access Denied', 'class' => 'danger', 'title' => 'Update KYC'));
					return;
				}

				$kyc_details = $_POST['kyc_det'];

				$bank_details = $_POST['bank'];

				$this->db->trans_begin();

				$this->$model->deleteData('id_karigar', $id, 'ret_karigar_kyc');

				foreach ($kyc_details as $val) {

					$kyc = array(

						'id_karigar'	=>	$id,

						'id_kyc' 	    =>	$val['proof_name'],

						'kyc_number'    =>  $val['id_reg_exp'],

						'document'      =>  $val['document_file'],

						'created_on'	=>  date("Y-m-d H:i:s"),

						'created_by'    =>  $this->session->userdata('uid')

					);



					if ($val['img_type'] == 1) {

						$img_type = 'jpg';
					} else if ($val['img_type'] == 2) {

						$img_type = 'jpeg';
					} else {

						$img_type = 'png';
					}



					if ($val['order_img_fr'] != '') {





						$p_ImgData = [];

						$p_ImgData = json_decode($val['order_img_fr']);



						if (sizeof($p_ImgData) > 0) {

							foreach ($p_ImgData as $precious) {

								$imgFile = $this->base64ToFile($precious->src);

								$_FILES['order_img_fr'][] = $imgFile;
							}
						}



						if (!empty($_FILES)) {

							$img_arr = array();

							$folder =  self::IMG_PATH . "karigar/kyc/";

							if (!is_dir($folder)) {

								mkdir($folder, 0777, TRUE);
							}

							if (isset($_FILES['order_img_fr'])) {

								$order_images = "";



								foreach ($_FILES['order_img_fr'] as $file_key => $file_val) {



									if ($file_val['name']) {

										// unlink($folder."/".$product['image']);

										$img_name =  $insOrderDet . "_" . mt_rand(100001, 999999) . '.' . $img_type;

										$path = $folder . "/" . $img_name;

										$result = $this->upload_img('image', $path, $file_val['tmp_name']);

										if ($result) {

											$kyc['front_images'] = $img_name;
										}
									}
								}
							}
						}
					}



					if ($val['order_img_bk'] != '') {



						$p_ImgData = [];

						$p_ImgData = json_decode($val['order_img_bk']);



						if (sizeof($p_ImgData) > 0) {

							foreach ($p_ImgData as $precious) {

								$imgFile = $this->base64ToFile($precious->src);

								$_FILES['order_img_bk'][] = $imgFile;
							}
						}



						if (!empty($_FILES)) {

							$img_arr = array();

							$folder =  self::IMG_PATH . "karigar/kyc/";

							if (!is_dir($folder)) {

								mkdir($folder, 0777, TRUE);
							}

							if (isset($_FILES['order_img_bk'])) {

								$order_images = "";



								foreach ($_FILES['order_img_bk'] as $file_key => $file_val) {



									if ($file_val['name']) {

										// unlink($folder."/".$product['image']);

										$img_name =  $insOrderDet . "_" . mt_rand(100001, 999999) . '.' . $img_type;

										$path = $folder . "/" . $img_name;

										$result = $this->upload_img('image', $path, $file_val['tmp_name']);

										if ($result) {

											$kyc['back_images'] = $img_name;
										}
									}
								}
							}
						}
					}



					$id_karigar_kyc = $this->$model->insertData($kyc, 'ret_karigar_kyc');
				}



				$this->$model->deleteData('id_karigar', $id, 'ret_karigar_bank_acc_details');

				if (!empty($bank_details)) {

					$arraybank = array();

					foreach ($bank_details['bank_name'] as $key => $val) {

						$arraybank = array(

							'id_karigar'     =>	 $id,

							'account_name'   =>	 $bank_details['acc_holder'][$key],

							'account_number' =>	 $bank_details['acc_number'][$key],

							'bank_name'      =>	 $bank_details['bank_name'][$key],

							'ifsc_code '     =>	 $bank_details['ifsc_code'][$key],

						);

						$this->$model->insertData($arraybank, 'ret_karigar_bank_acc_details');
					}
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$result = array('message' => 'KYC Updated successfully!..', 'class' => 'success', 'title' => 'Update KYC : ', 'status' => True, 'value' => $id_karigar);

					$this->session->set_flashdata('chit_alert', array('message' => 'KYC added successfully!..', 'class' => 'success', 'title' => 'Update User',));
				} else {

					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Update KYC : ');

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Update User'));
				}

				echo json_encode($result);

				break;
		}
	}



	function get_quality_code()

	{

		$model	=	self::CAT_MODEL;

		$data = $this->$model->get_quality_code();

		echo json_encode($data);
	}







	public function diamond_rate($type = "", $id = "", $status = "", $quality_code_id = "")
	{
		// active_diamondquality is consumed by catalog_master.js — preserved as-is,
		// everything else routes through the generic engine.
		if ($type === 'active_diamondquality') {
			$model = self::CAT_MODEL;
			echo json_encode($this->$model->getquality());
			return;
		}
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'diamond_rate', $type, $id, $status);
	}







	public function shape($type = "", $id = "", $status = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'shape', $type, $id, $status);
	}

	function getExistingKarigars()
	{
		$model = self::CAT_MODEL;
		$data = $this->$model->getExistingKarigars($_POST['searchTxt']);
		echo json_encode($data);
	}

	public function selling_diamond_rate($type = "", $id = "", $status = "", $quality_code_id = "")
	{
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'selling_diamond_rate', $type, $id, $status);
	}

	function getQualityDiamondRates()
	{
		$model = self::CAT_MODEL;
		$data = $this->$model->getQualityDiamondRates();
		echo json_encode($data);
	}



	function check_quality_code($code)
	{
		$model = self::CAT_MODEL;
		$data = $this->$model->check_quality_code($code);
		echo json_encode($data);
	}



	function create_va_mc_settings($addData)
	{

		$model = self::CAT_MODEL;
		$this->load->helper('lmx/core/access_helper');
		// FR-CAT-015: reachable directly via URL (public method), not just through
		// wastage_mc_settings()'s 'save' case — gate it independently too.
		if (!lmx_can($this, 'admin_ret_catalog/wastage_mc_settings/list', 'add')) {
			return array('status' => false, 'message' => 'Access Denied');
		}
		// Known gap fix: id_product/id_design/id_sub_design are chosen from
		// active-list dropdowns but were never re-checked for staleness before
		// save (id_design/id_sub_design of 0 mean "all" and are handled via
		// already-active-filtered auto-expansion in wastage_mc_settings(),
		// so only non-zero explicit selections need checking here).
		if (!empty($addData['id_product']) && !$this->$model->check_product_active($addData['id_product'])) {
			return array('status' => false, 'message' => 'Selected Product is no longer available. Please refresh and choose again.');
		}
		if (!empty($addData['id_design']) && $addData['id_design'] != 0 && !$this->$model->check_design_active($addData['id_design'])) {
			return array('status' => false, 'message' => 'Selected Design is no longer available. Please refresh and choose again.');
		}
		if (!empty($addData['id_sub_design']) && $addData['id_sub_design'] != 0 && !$this->$model->check_sub_design_active($addData['id_sub_design'])) {
			return array('status' => false, 'message' => 'Selected Sub Design is no longer available. Please refresh and choose again.');
		}
		$responseData = [];
		$is_va_mc_based_on_branch = $this->$model->get_ret_settings('is_va_mc_based_on_branch');

		$this->db->trans_begin();

		if ($addData['wastage_type'] == 1) {

			$settings = $this->$model->check_selling_rate_settings($addData['id_product'], $addData['id_design'], $addData['id_sub_design']);

			if ($settings['status'] || $is_va_mc_based_on_branch == 1) {

				$insData = array(

					'id_product'     => $addData['id_product'],

					'id_design'      => $addData['id_design'],

					'id_sub_design'  => ($addData['id_sub_design'] != '' ? $addData['id_sub_design'] : NULL),

					'type'           => $addData['wastage_type'],

					'wastag_method'  => $addData['wastag_method'],

					'wastage_perc'   => ($addData['wastag_value'] != '' ? $addData['wastag_value'] : NULL),

					'wastage_wt'   => ($addData['wastag_wt'] != '' ? $addData['wastag_wt'] : NULL),

					'wastag_min'   => ($addData['min_wastag_value'] != '' ? $addData['min_wastag_value'] : NULL),

					'wastag_min_wt'   => ($addData['wastag_min_wt'] != '' ? $addData['wastag_min_wt'] : NULL),

					'mc_value'       => ($addData['mc_value'] != '' ? $addData['mc_value'] : NULL),

					'mc_min'       => ($addData['min_mc_value'] != '' ? $addData['min_mc_value'] : NULL),

					'mc_type'        => $addData['mc_type'],

					'margin_mrp'        => $addData['margin_mrp'],

				);



				$this->db->trans_begin();

				if ($is_va_mc_based_on_branch == 1) {



					$branch = $_POST['settings']['branch'];

					$Data = [];

					foreach ($branch as $id_branch) {

						if (!$this->$model->check_branch_active($id_branch)) {
							$responseData = array('status' => false, 'message' => 'One or more selected Branches are no longer available. Please refresh and choose again.');
							$status = FALSE;
							break;
						}

						$check = $this->$model->check_selling_rate_settings($addData['id_product'], $addData['id_design'], $addData['id_sub_design'], $id_branch);

						if ($check['status']) {

							$insData['id_branch'] = $id_branch;

							$Data[] = $insData;

							$status = True;
						} else {



							$responseData = array('status' => $settings['status'], 'message' => $settings['message']);

							$status = FALSE;

							break;
						}
					}

					if ($status) {



						$insId = $this->$model->insertBatchData($Data, 'ret_selling_settings');
					}
				} else {

					$insId = $this->$model->insertData($insData, 'ret_selling_settings');
				}







				if ($insId) {

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'V.A and MC Settings',

						'operation'     => 'Add',

						'record'        =>  $insId,

						'remark'        => 'V.A and MC Settings added successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$responseData = array('status' => true, 'message' => 'V.A and MC Settings Added successfully');
				}
			} else {

				$responseData = array('status' => $settings['status'], 'message' => $settings['message']);
			}
		} else {

			if ($is_va_mc_based_on_branch == 1) {



				$branch = $_POST['settings']['branch'];



				foreach ($branch as $id_branch) {



					$check = $this->$model->check_selling_rate_settings($addData['id_product'], $addData['id_design'], $addData['id_sub_design'], $id_branch);



					if ($check['status']) {

						if ($addData['wastage_type'] == 3) {
							$insData = array(
								'id_product'     => $addData['id_product'],
								'id_design'      => $addData['id_design'],
								'id_sub_design'  => ($addData['id_sub_design'] != '' ? $addData['id_sub_design'] : NULL),
								'type'           => 3,
								'id_branch'      => $id_branch,
								'margin_mrp'     => (isset($addData['margin_mrp']) ? $addData['margin_mrp'] : NULL),
							);
						} else {
							$insData = array(

								'id_product'     => $addData['id_product'],

								'id_design'      => $addData['id_design'],

								'id_sub_design'  => ($addData['id_sub_design'] != '' ? $addData['id_sub_design'] : NULL),

								'type'           => $addData['wastage_type'],

								'wastag_method'  => $addData['wastag_method'],

								'wastage_perc'   => ($addData['wastag_value'] != '' ? $addData['wastag_value'] : NULL),

								'wastage_wt'   => ($addData['wastag_wt'] != '' ? $addData['wastag_wt'] : NULL),

								'mc_value'       => ($addData['mc_value'] != '' ? $addData['mc_value'] : NULL),

								'mc_type'        => $addData['mc_type'],

								'id_branch'        => $id_branch,

								'margin_mrp'        => $addData['margin_mrp'],

							);
						}

						$this->db->trans_begin();

						$id_selling_settings = $this->$model->insertData($insData, 'ret_selling_settings');
					} else if ($check['type'] == 2) {

						$id_selling_settings = $check['id_selling_settings'];
					} else {



						$id_selling_settings = NUll;
					}



					if ($id_selling_settings) {

						$i = 1;

						if ($addData['wastage_type'] == 3) {

							if (isset($_POST['touchrange'])) {
								foreach ($_POST['touchrange'] as $touchDetails) {

									if (($touchDetails['wc_method'] == 1 ? $touchDetails['wastage'] != '' : $touchDetails['wc_wt'] != '') && $touchDetails['making_charge'] != '') {

										$touchSettings = $this->$model->check_selling_rate_touch_settings($touchDetails['from_touch'], $touchDetails['to_touch'], $addData['id_product'], $addData['id_design'], $addData['id_sub_design'], $id_branch);

										if ($touchSettings['status']) {

											$itemDetails = array(

												'id_selling_settings' => $id_selling_settings,

												'touch_from'          => $touchDetails['from_touch'],

												'touch_to'            => $touchDetails['to_touch'],

												'wc_method'			  => $touchDetails['wc_method'],

												'wc_percent'          => ($touchDetails['wastage'] != '' ? $touchDetails['wastage'] : NULL),

												'wc_wt'				  => ($touchDetails['wc_wt'] != '' ? $touchDetails['wc_wt'] : NULL),

												'mc'                  => ($touchDetails['making_charge'] != '' ? $touchDetails['making_charge'] : NULL),

												'mc_type'             => ($touchDetails['mc_type'] != '' ? $touchDetails['mc_type'] : 1),

												'wc_min'              => ($touchDetails['wc_min'] != '' ? $touchDetails['wc_min'] : NULL),

												'wc_min_wt'           => ($touchDetails['wc_min_wt'] != '' ? $touchDetails['wc_min_wt'] : NULL),

												'mcrg_min'            => ($touchDetails['mcrg_min'] != '' ? $touchDetails['mcrg_min'] : NULL),

											);

											$this->$model->insertData($itemDetails, 'ret_design_weight_range_wc');
										}
									}

									$i++;
								}
							}
						} else {

						foreach ($_POST['wcrange'] as $weightDetails) {

							if (($weightDetails['wc_method'] == 1 ? $weightDetails['wastage'] != '' : $weightDetails['wc_wt'] != '') && $weightDetails['making_charge'] != '') {

								$weightSettings = $this->$model->check_selling_rate_weight_settings($weightDetails['from_weight'], $weightDetails['to_weight'], $addData['id_product'], $addData['id_design'], $addData['id_sub_design'], $id_branch);

								if ($weightSettings['status']) {

									$itemDetails = array(

										'id_selling_settings' => $id_selling_settings,

										'wc_from_weight'      => $weightDetails['from_weight'],

										'wc_to_weight'        => $weightDetails['to_weight'],

										'wc_method'			  => $weightDetails['wc_method'],

										'wc_percent'          => ($weightDetails['wastage'] != '' ? $weightDetails['wastage'] : NULL),

										'wc_wt'				  => ($weightDetails['wc_wt'] != '' ? $weightDetails['wc_wt'] : NULL),

										'mc'                  => ($weightDetails['making_charge'] != '' ? $weightDetails['making_charge'] : NULL),

										'mc_type'             => ($weightDetails['mc_type'] != '' ? $weightDetails['mc_type'] : 1),

										'wc_min'              => ($weightDetails['wc_min'] != '' ? $weightDetails['wc_min'] : NULL),

										'wc_min_wt'           => ($weightDetails['wc_min_wt'] != '' ? $weightDetails['wc_min_wt'] : NULL),

										'mcrg_min'            => ($weightDetails['mcrg_min'] != '' ? $weightDetails['mcrg_min'] : NULL),

									);



									$this->$model->insertData($itemDetails, 'ret_design_weight_range_wc');
								}
							}

							$i++;
						}
						}

						$responseData = array('status' => true, 'message' => 'V.A and MC Settings Added successfully');
					} else {



						$responseData = array('status' => true, 'message' => 'Already Added For This Product as Fixed');
					}
				}
			} else {



				$settings = $this->$model->check_selling_rate_settings($addData['id_product'], $addData['id_design'], $addData['id_sub_design']);



				if ($settings['status']) {

					if ($addData['wastage_type'] == 3) {
						$insData = array(
							'id_product'     => $addData['id_product'],
							'id_design'      => $addData['id_design'],
							'id_sub_design'  => ($addData['id_sub_design'] != '' ? $addData['id_sub_design'] : NULL),
							'type'           => 3,
							'margin_mrp'     => (isset($addData['margin_mrp']) ? $addData['margin_mrp'] : NULL),
						);
					} else {
						$insData = array(
							'id_product'     => $addData['id_product'],
							'id_design'      => $addData['id_design'],
							'id_sub_design'  => ($addData['id_sub_design'] != '' ? $addData['id_sub_design'] : NULL),
							'type'           => $addData['wastage_type'],
							'wastag_method'  => $addData['wastag_method'],
							'wastage_perc'   => ($addData['wastag_value'] != '' ? $addData['wastag_value'] : NULL),
							'wastage_wt'   => ($addData['wastag_wt'] != '' ? $addData['wastag_wt'] : NULL),
							'mc_value'       => ($addData['mc_value'] != '' ? $addData['mc_value'] : NULL),
							'mc_type'        => $addData['mc_type'],
							'margin_mrp'     => $addData['margin_mrp'],
						);
					}

					$this->db->trans_begin();

					$id_selling_settings = $this->$model->insertData($insData, 'ret_selling_settings');
				} else {

					$id_selling_settings = $settings['id_selling_settings'];

					// Update master record type if switching to a different type
					if ($addData['wastage_type'] != $settings['type']) {
						$updateData = array('type' => $addData['wastage_type']);
						if ($addData['wastage_type'] == 3) {
							$updateData['wastag_method'] = NULL;
							$updateData['wastage_perc'] = NULL;
							$updateData['wastage_wt'] = NULL;
							$updateData['mc_value'] = NULL;
						}
						$this->db->where('id_selling_settings', $id_selling_settings);
						$this->db->update('ret_selling_settings', $updateData);

						// Delete old weight/touch detail rows when switching type
						$this->$model->deleteData('id_selling_settings', $id_selling_settings, 'ret_design_weight_range_wc');
					}

					$this->db->trans_begin();
				}


				if ($id_selling_settings) {

					if ($addData['wastage_type'] == 2) {

						foreach ($_POST['wcrange'] as $weightDetails) {

							if (($weightDetails['wc_method'] == 1 ? $weightDetails['wastage'] != '' : $weightDetails['wc_wt'] != '') && $weightDetails['making_charge'] != '') {

								$weightSettings = $this->$model->check_selling_rate_weight_settings($weightDetails['from_weight'], $weightDetails['to_weight'], $addData['id_product'], $addData['id_design'], $addData['id_sub_design']);

								if ($weightSettings['status']) {

									$itemDetails = array(

										'id_selling_settings' => $id_selling_settings,

										'wc_from_weight'      => $weightDetails['from_weight'],

										'wc_to_weight'        => $weightDetails['to_weight'],

										'wc_method'			  => $weightDetails['wc_method'],

										'wc_percent'          => ($weightDetails['wastage'] != '' ? $weightDetails['wastage'] : NULL),

										'wc_wt'				  => ($weightDetails['wc_wt'] != '' ? $weightDetails['wc_wt'] : NULL),

										'mc'                  => ($weightDetails['making_charge'] != '' ? $weightDetails['making_charge'] : NULL),

										'mc_type'             => ($weightDetails['mc_type'] != '' ? $weightDetails['mc_type'] : 1),

										'wc_min'              => ($weightDetails['wc_min'] != '' ? $weightDetails['wc_min'] : NULL),

										'wc_min_wt'           => ($weightDetails['wc_min_wt'] != '' ? $weightDetails['wc_min_wt'] : NULL),

										'mcrg_min'            => ($weightDetails['mcrg_min'] != '' ? $weightDetails['mcrg_min'] : NULL),

									);

									$this->$model->insertData($itemDetails, 'ret_design_weight_range_wc');
								}
							}
						}
					}
 else if ($addData['wastage_type'] == 3) {

						if (isset($_POST['touchrange'])) {
							foreach ($_POST['touchrange'] as $touchDetails) {

								$touchSettings = $this->$model->check_selling_rate_touch_settings($touchDetails['from_touch'], $touchDetails['to_touch'], $addData['id_product'], $addData['id_design'], $addData['id_sub_design']);

								if ($touchSettings['status']) {

									$itemDetails = array(

										'id_selling_settings' => $id_selling_settings,

										'touch_from'          => $touchDetails['from_touch'],

										'touch_to'            => $touchDetails['to_touch'],

										'wc_method'			  => $touchDetails['wc_method'],

										'wc_percent'          => ($touchDetails['wastage'] != '' ? $touchDetails['wastage'] : NULL),

										'wc_wt'				  => ($touchDetails['wc_wt'] != '' ? $touchDetails['wc_wt'] : NULL),

										'mc'                  => ($touchDetails['making_charge'] != '' ? $touchDetails['making_charge'] : NULL),

										'mc_type'             => ($touchDetails['mc_type'] != '' ? $touchDetails['mc_type'] : 1),

										'wc_min'              => ($touchDetails['wc_min'] != '' ? $touchDetails['wc_min'] : NULL),

										'wc_min_wt'           => ($touchDetails['wc_min_wt'] != '' ? $touchDetails['wc_min_wt'] : NULL),

										'mcrg_min'            => ($touchDetails['mcrg_min'] != '' ? $touchDetails['mcrg_min'] : NULL),

									);

									$this->$model->insertData($itemDetails, 'ret_design_weight_range_wc');
								}
							}
						}
					}

					if ($this->db->trans_status() === TRUE) {

						$this->db->trans_commit();

						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'V.A and MC Settings',

							'operation'     => 'Add',

							'record'        =>  $id_selling_settings,

							'remark'        => 'V.A and MC Settings added successfully'

						);

						$this->log_model->log_detail('insert', '', $log_data);

						$responseData = array('status' => true, 'message' => 'V.A and MC Settings Added successfully');
					} else {

						$this->db->trans_rollback();

						$this->session->set_flashdata('chit_alert', array('message' => 'Unable To Proceed Your request', 'class' => 'Danger', 'title' => 'Settings'));

						$responseData = array('status' => true, 'msg' => 'Unable To Proceed Your request');
					}
				}
			}
		}
		return $responseData;
	}


	public function ret_stone_rate_settings($type = "", $id = "")
	{
		$model = self::CAT_MODEL;
		$SETT_MOD = self::SETT_MOD;

		switch ($type) {
			case 'list':
				$data['main_content'] = "master/ret_stone_rate_settings/list";
				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_stone_rate_settings/list');
				$this->load->view('layout/template', $data);

				break;
			case 'add':
				$data['main_content'] = "master/ret_stone_rate_settings/form";
				$this->load->view('layout/template', $data);
				break;
			case 'save':
				//echo"<pre>";print_r($_POST);exit;
				$stone_rate = $_POST['stn_rate'];
				$branch = $this->$model->GetBranches();

				// Known gap fix: stone_id/uom_id/id_branch are chosen from
				// active-list dropdowns but were never re-checked for staleness
				// before save (id_branch==0 means "all active branches" and is
				// already sourced from GetBranches(), which filters active=1).
				if (sizeof($stone_rate) > 0) {
					foreach ($stone_rate as $stn) {
						if (empty($stn['stn_name']) || !$this->$model->check_stone_active($stn['stn_name'])) {
							echo json_encode(array('status' => false, 'msg' => 'One or more selected Stones are no longer available. Please refresh and choose again.'));
							return;
						}
						if (!empty($stn['uom_id']) && !$this->$model->check_uom_active($stn['uom_id'])) {
							echo json_encode(array('status' => false, 'msg' => 'One or more selected UOMs are no longer available. Please refresh and choose again.'));
							return;
						}
						if (!empty($stn['id_branch']) && $stn['id_branch'] != 0 && !$this->$model->check_branch_active($stn['id_branch'])) {
							echo json_encode(array('status' => false, 'msg' => 'One or more selected Branches are no longer available. Please refresh and choose again.'));
							return;
						}
					}
				}

				if (sizeof($stone_rate) > 0) {
					foreach ($stone_rate as $stn) {
						if ($stn['id_branch'] == 0) // ALL BRANCH
						{
							foreach ($branch as $br) {
								$checkRateExist = $this->$model->checkStoneRateExist($br['id_branch'], $stn['stn_type'], $stn['stn_name'], $stn['stn_calc_type'], $stn['uom_id'], $stn['stn_quality'], $stn['from_wt'], $stn['to_wt']);

								if (sizeof($checkRateExist) > 0) {
									foreach ($checkRateExist as $r) {
										$this->$model->updateData(array('active' => 0), 'id_stone_rate', $r['id_stone_rate'], 'ret_stone_rate_settings');
									}
								}

								$insdata = array(
									'id_branch'  => $br['id_branch'],
									'stone_type' => $stn['stn_type'],
									'stone_id'   => $stn['stn_name'],
									'quality_id' => (isset($stn['stn_quality']) ? $stn['stn_quality'] : NULL),
									'stone_calc_type' => (isset($stn['stn_calc_type']) ? $stn['stn_calc_type'] : 1),
									'uom_id' => (isset($stn['uom_id']) ? $stn['uom_id'] : NULL),
									'from_wt' => (($stn['from_wt'] != '') ? $stn['from_wt'] : 0),
									'to_wt' => (($stn['to_wt'] != '') ? $stn['to_wt'] : 0),
									'min_rate' => (($stn['min_rate'] != '') ? $stn['min_rate'] : 0),
									'max_rate' => (($stn['max_rate'] != '') ? $stn['max_rate'] : 0),
									'active' => 1,
									'created_on'	 => date("Y-m-d H:i:s"),
									'created_by' => $this->session->userdata('uid'),
								);
								$this->db->trans_begin();
								$this->$model->insertData($insdata, 'ret_stone_rate_settings');
							}
						} else {
							$checkRateExist = $this->$model->checkStoneRateExist($stn['id_branch'], $stn['stn_type'], $stn['stn_name'], $stn['stn_calc_type'], $stn['uom_id'], $stn['stn_quality'], $stn['from_wt'], $stn['to_wt']);

							if (sizeof($checkRateExist) > 0) {
								foreach ($checkRateExist as $r) {
									$this->$model->updateData(array('active' => 0), 'id_stone_rate', $r['id_stone_rate'], 'ret_stone_rate_settings');
								}
							}

							$insdata = array(
								'id_branch'  => $stn['id_branch'],
								'stone_type' => $stn['stn_type'],
								'stone_id'   => $stn['stn_name'],
								'quality_id' => (isset($stn['stn_quality']) ? $stn['stn_quality'] : NULL),
								'stone_calc_type' => (isset($stn['stn_calc_type']) ? $stn['stn_calc_type'] : 1),
								'uom_id' => (isset($stn['uom_id']) ? $stn['uom_id'] : NULL),
								'from_wt' => (($stn['from_wt'] != '') ? $stn['from_wt'] : 0),
								'to_wt' => (($stn['to_wt'] != '') ? $stn['to_wt'] : 0),
								'min_rate' => (($stn['min_rate'] != '') ? $stn['min_rate'] : 0),
								'max_rate' => (($stn['max_rate'] != '') ? $stn['max_rate'] : 0),
								'active' => 1,
								'created_on'	 => date("Y-m-d H:i:s"),
								'created_by' => $this->session->userdata('uid'),
							);
							$this->db->trans_begin();
							$this->$model->insertData($insdata, 'ret_stone_rate_settings');
						}
					}
				}

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Stone Rate Settings Added successfully', 'class' => 'success', 'title' => 'Settings'));
					$data = array('status' => true, 'msg' => 'Stone Rate Settings Added Successfully');
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable To Proceed Your request', 'class' => 'Danger', 'title' => 'Settings'));
					$data = array('status' => true, 'msg' => 'Unable To Proceed Your request');
				}
				echo json_encode($data);

				break;
			case 'edit':
				$data['rate_sett'] = $this->$model->get_Stone_min_max_rate($id);
				$data['main_content'] = "master/ret_stone_rate_settings/form";
				$this->load->view('layout/template', $data);
				break;
			case 'stone_rate_details':
				$data['rate_sett'] = $this->$model->get_Stone_min_max_rate($id);
				$stone_type = "";
				foreach ($data['rate_sett'] as $val) {
					$stone_type = $val['stone_type'];
				}
				$data['stone_type'] = $this->$model->getActiveStoneTypes();
				$data['uom'] = $this->$model->getActiveUOM();
				$data['stones']  = $this->$model->get_ActiveStones(array("stone_type" => $stone_type));
				$data['quality_code'] = $this->$model->get_quality_code();
				//echo"<pre>";print_r($data);exit;
				echo json_encode($data);
				break;
			case 'product_rate_details':
				$data['prod_rate_sett'] = $this->$model->get_product_min_max_rate($id);
				$data['products'] = $this->$model->getLooseStonesProduct();
				$id_product = "";
				$id_design = "";
				foreach ($data['prod_rate_sett']  as $value) {
					$id_product = $value['id_product'];
					$id_design = $value['id_design'];
				}
				$data['designs'] = $this->$model->get_ProductDesign(array("id_product" => $id_product));
				$data['sub_designs'] = $this->$model->get_ActiveSubDesigns(array("id_product" => $id_product, "design_no" => $id_design));
				$data['uom'] = $this->$model->getActiveUOM();
				$data['quality_code'] = $this->$model->get_quality_code();
				echo json_encode($data);
				break;
			case 'update':
				//echo "<pre>";print_r($_POST);exit;
				$stone_rate = $_POST['stn_rate'];
				// Known gap fix: same staleness check as the 'save' case above —
				// the update path had none of these at all.
				if (sizeof($stone_rate) > 0) {
					foreach ($stone_rate as $stn) {
						if (empty($stn['stn_name']) || !$this->$model->check_stone_active($stn['stn_name'])) {
							echo json_encode(array('status' => false, 'msg' => 'One or more selected Stones are no longer available. Please refresh and choose again.'));
							return;
						}
						if (!empty($stn['uom_id']) && !$this->$model->check_uom_active($stn['uom_id'])) {
							echo json_encode(array('status' => false, 'msg' => 'One or more selected UOMs are no longer available. Please refresh and choose again.'));
							return;
						}
						if (!empty($stn['id_branch']) && !$this->$model->check_branch_active($stn['id_branch'])) {
							echo json_encode(array('status' => false, 'msg' => 'One or more selected Branches are no longer available. Please refresh and choose again.'));
							return;
						}
					}
				}
				if (sizeof($stone_rate) > 0) {
					foreach ($stone_rate as $stn) {
						$checkRateExist = $this->$model->checkStoneRateExist($stn['id_branch'], $stn['stn_type'], $stn['stn_name'], $stn['stn_calc_type'], $stn['uom_id'], $stn['stn_quality'], $stn['from_wt'], $stn['to_wt']);

						if (sizeof($checkRateExist) > 0) {
							foreach ($checkRateExist as $r) {
								$this->$model->updateData(array('active' => 0), 'id_stone_rate', $r['id_stone_rate'], 'ret_stone_rate_settings');
							}
						}

						$updData = array(
							'id_branch'  => $stn['id_branch'],
							'stone_type' => $stn['stn_type'],
							'stone_id'   => $stn['stn_name'],
							'quality_id' => (isset($stn['stn_quality']) ? $stn['stn_quality'] : NULL),
							'stone_calc_type' => (isset($stn['stn_calc_type']) ? $stn['stn_calc_type'] : 1),
							'uom_id' => (isset($stn['uom_id']) ? $stn['uom_id'] : NULL),
							'from_wt' => (($stn['from_wt'] != '') ? $stn['from_wt'] : 0),
							'to_wt' => (($stn['to_wt'] != '') ? $stn['to_wt'] : 0),
							'min_rate' => (($stn['min_rate'] != '') ? $stn['min_rate'] : 0),
							'max_rate' => (($stn['max_rate'] != '') ? $stn['max_rate'] : 0),
							'active' => 1,
							'created_on'	 => date("Y-m-d H:i:s"),
							'created_by' => $this->session->userdata('uid'),
						);
						$this->db->trans_begin();
						$id_stone_rate = $this->$model->updateData($updData, 'id_stone_rate', $id, 'ret_stone_rate_settings');
					}
				}

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Stone Rate Settings',

						'operation'     => 'Update',

						'record'        =>  $id_stone_rate,

						'remark'        => 'Stone Rate Settings Updated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);
					$this->session->set_flashdata('chit_alert', array('message' => 'Stone Rate Settings Updated successfully', 'class' => 'success', 'title' => 'Settings'));
					$data = array('status' => true, 'msg' => 'Stone Rate Settings Updated Successfully');
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable To Proceed Your request', 'class' => 'Danger', 'title' => 'Settings'));
					$data = array('status' => true, 'msg' => 'Unable To Proceed Your request');
				}
				echo json_encode($data);
				break;
			case 'delete':
				$this->db->trans_begin();
				$this->$model->deleteData('id_stone_rate', $id, 'ret_stone_rate_settings');
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Stone Rate Settings',

						'operation'     => 'Delete',

						'record'        =>  $id,

						'remark'        => 'Stone Rate Settings  deleted successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);
					$this->session->set_flashdata('chit_alert', array('message' => 'Min & Max rate deleted successfully', 'class' => 'success', 'title' => 'Delete Min & Max rate'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Delete Min & Max rate'));
				}
				redirect('admin_ret_catalog/ret_stone_rate_settings/list');
				break;
			case 'product_save':
				//echo"<pre>";print_r($_POST);exit;
				$stn_product_rate = $_POST['stn_prod_rate'];
				if (sizeof($stn_product_rate) > 0) {
					foreach ($stn_product_rate as $r) {
						$checkProductRateExist = $this->$model->checkProductStoneRateExist($r['id_branch'], $r['id_product'], $r['id_design'], $r['id_sub_design'], $r['stn_calc_type'], $r['uom_id'], $r['stn_quality'], $r['from_wt'], $r['to_wt']);

						if (sizeof($checkProductRateExist) > 0) {
							foreach ($checkProductRateExist as $itm) {
								$this->$model->updateData(array('active' => 0), 'id_stone_rate', $itm['id_stone_rate'], 'ret_stone_rate_settings');
							}
						}

						$insdata = array(
							'id_branch'     => $r['id_branch'],
							'id_product'    => $r['id_product'],
							'id_design'     => $r['id_design'],
							'id_sub_design' => (isset($r['id_sub_design']) ? $r['id_sub_design'] : NULL),
							'quality_id' => (isset($r['stn_quality']) ? $r['stn_quality'] : NULL),
							'stone_calc_type' => (isset($r['stn_calc_type']) ? $r['stn_calc_type'] : 1),
							'uom_id' => (isset($r['uom_id']) ? $r['uom_id'] : NULL),
							'from_wt' => (($r['from_wt'] != '') ? $r['from_wt'] : 0),
							'to_wt' => (($r['to_wt'] != '') ? $r['to_wt'] : 0),
							'min_rate' => (($r['min_rate'] != '') ? $r['min_rate'] : 0),
							'max_rate' => (($r['max_rate'] != '') ? $r['max_rate'] : 0),
							'type' => 2,
							'active' => 1,
							'created_on'	 => date("Y-m-d H:i:s"),
							'created_by' => $this->session->userdata('uid'),
						);
						$this->db->trans_begin();
						$this->$model->insertData($insdata, 'ret_stone_rate_settings');
						//print_r($this->db->last_query());exit;
					}
				}
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Stone Rate Settings Added successfully', 'class' => 'success', 'title' => 'Settings'));
					$data = array('status' => true, 'msg' => 'Stone Rate Settings Added Successfully');
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable To Proceed Your request', 'class' => 'Danger', 'title' => 'Settings'));
					$data = array('status' => true, 'msg' => 'Unable To Proceed Your request');
				}
				echo json_encode($data);
				break;
			case 'product_update':
				//echo"<pre>";print_r($_POST);exit;
				$stn_product_rate = $_POST['stn_prod_rate'];
				if (sizeof($stn_product_rate) > 0) {
					foreach ($stn_product_rate as $r) {
						$checkProductRateExist = $this->$model->checkProductStoneRateExist($r['id_branch'], $r['id_product'], $r['id_design'], $r['id_sub_design'], $r['stn_calc_type'], $r['uom_id'], $r['stn_quality'], $r['from_wt'], $r['to_wt']);

						if (sizeof($checkProductRateExist) > 0) {
							foreach ($checkProductRateExist as $itm) {
								$this->$model->updateData(array('active' => 0), 'id_stone_rate', $itm['id_stone_rate'], 'ret_stone_rate_settings');
							}
						}

						$updData = array(
							'id_branch'     => $r['id_branch'],
							'id_product'    => $r['id_product'],
							'id_design'     => $r['id_design'],
							'id_sub_design' => (isset($r['id_sub_design']) ? $r['id_sub_design'] : NULL),
							'quality_id' => (isset($r['stn_quality']) ? $r['stn_quality'] : NULL),
							'stone_calc_type' => (isset($r['stn_calc_type']) ? $r['stn_calc_type'] : 1),
							'uom_id' => (isset($r['uom_id']) ? $r['uom_id'] : NULL),
							'from_wt' => (($r['from_wt'] != '') ? $r['from_wt'] : 0),
							'to_wt' => (($r['to_wt'] != '') ? $r['to_wt'] : 0),
							'min_rate' => (($r['min_rate'] != '') ? $r['min_rate'] : 0),
							'max_rate' => (($r['max_rate'] != '') ? $r['max_rate'] : 0),
							'active' => 1,
							'created_on'	 => date("Y-m-d H:i:s"),
							'created_by' => $this->session->userdata('uid'),
						);
						$this->db->trans_begin();
						$this->$model->updateData($updData, 'id_stone_rate', $id, 'ret_stone_rate_settings');
						//print_r($this->db->last_query());exit;
					}
				}
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Stone Rate Settings Updated successfully', 'class' => 'success', 'title' => 'Settings'));
					$data = array('status' => true, 'msg' => 'Stone Rate Settings Updated Successfully');
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable To Proceed Your request', 'class' => 'success', 'title' => 'Settings'));
					$data = array('status' => true, 'msg' => 'Unable To Proceed Your request');
				}
				echo json_encode($data);
				break;
			default:
				$SETT_MOD = self::SETT_MOD;
				$list = $this->$model->ajax_get_stone_rates_settings($_POST);
				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_stone_rate_settings/list');
				$data = array(
					'list' => $list,
					'access' => $access
				);
				echo json_encode($data);
		}
	}

	function getStoneRateSettings()
	{
		$model = self::CAT_MODEL;
		$data = $this->$model->getStoneRateSettings();
		echo json_encode($data);
	}

	function getLooseStonesProduct()
	{
		$model = self::CAT_MODEL;
		$data = $this->$model->getLooseStonesProduct();
		echo json_encode($data);
	}


	function getLooseStoneProductRateSettings()
	{
		$model = self::CAT_MODEL;
		$data = $this->$model->getLooseStoneProductRateSettings();
		echo json_encode($data);
	}

	function vendor_trans_send_sms($mobile, $message, $dlt_te_id = '')

	{

		if ($this->config->item('sms_gateway') == '1') {

			$this->sms_model->sendSMS_MSG91($mobile, $message, '', $dlt_te_id);
		} elseif ($this->config->item('sms_gateway') == '2') {

			$this->sms_model->sendSMS_Nettyfish($mobile, $message, 'trans');
		}
	}

	function vendor_sendotp()

	{

		$model = "ret_catalog_model";

		$mobile_num     = $this->input->post('mobile');

		$send_resend     = $this->input->post('send_resend');

		$sent_otp = '';

		if ($mobile_num != '') {

			$this->db->trans_begin();

			$this->session->unset_userdata("vendor_approval_otp");

			$this->session->unset_userdata("vendor_approval_otp_exp");

			$OTP = mt_rand(100001, 999999);

			$this->session->set_userdata('vendor_approval_otp', $OTP);

			$this->session->set_userdata('vendor_approval_otp_exp', time() + 60);

			$message = "Hi Your OTP  For Vendor Approval is :  " . $OTP . " Will expire within 1 minute.";

			$otp_gen_time = date("Y-m-d H:i:s");

			$insData = array(

				'mobile' => $mobile_num,

				'otp_code' => $OTP,

				'otp_gen_time' => date("Y-m-d H:i:s"),

				'module' => 'Vendor Approval',

				'send_resend' => $send_resend,

				'id_emp' => $this->session->userdata('uid')

			);

			$insId = $this->$model->insertData($insData, 'otp');
		}

		if ($insId) {

			$this->db->trans_commit();

			$this->vendor_trans_send_sms($mobile_num, $message);

			$status = array('status' => true, 'OTP' => $OTP, 'msg' => 'OTP sent Successfully');
			//   'OTP' => $OTP,

		} else {

			$this->db->trans_rollback();

			$status = array('status' => false, 'msg' => 'Unabe To Send Try Again');
		}

		echo json_encode($status);
	}


	function vendor_verify_otp()

	{

		$model = "ret_catalog_model";

		$post_otp = $this->input->post('otp');

		$session_otp = $this->session->userdata('vendor_approval_otp');

		$otp = array(explode(',', $session_otp));

		$this->db->trans_begin();

		if ($post_otp != '') {
			foreach ($otp[0] as $OTP) {

				if ($OTP == $post_otp) {

					if (time() >= $this->session->userdata('vendor_approval_otp_exp')) {

						$this->session->unset_userdata('vendor_approval_otp');

						$this->session->unset_userdata('vendor_approval_otp_exp');

						$status = array('status' => false, 'msg' => 'OTP has been expired');
					} else {

						$this->db->trans_commit();

						$updData = array('is_verified' => 1, 'verified_time' => date("Y-m-d H:i:s"));

						$updStatus = $this->$model->updateData($updData, 'otp_code', $post_otp, 'otp');

						$status = array('status' => true, 'msg' => 'OTP Verified Successfully.', 'verified_otp' => $post_otp);
					}

					break;
				} else {

					$status = array('status' => false, 'msg' => 'Please Enter Valid OTP');
				}
			}
		} else {

			$status = array('status' => false, 'msg' => 'Please Enter Valid OTP');
		}

		echo json_encode($status);
	}

	public function get_deviceNames()
	{

		$model = self::CAT_MODEL;

		$data = $this->$model->get_deviceNames();

		echo json_encode($data);
	}


	public function day_close($type = "", $id = "")
	{

		$model = self::CAT_MODEL;

		$SETT_MOD = self::SETT_MOD;

		switch ($type) {



			case 'list':

				$data['main_content'] = "day_close/list";

				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/day_close/list');

				$this->load->view('layout/template', $data);

				break;

			case 'update_dayclose':

				// $branch     = $this->$model->getAllBranchDayClose();

				// print_r($_POST);exit;

				$this->db->trans_begin();

				foreach ($_POST['branch'] as $br) {

					$updData = array(
						'is_day_closed'	=>	1,
						"entry_date"	=>	date('Y-m-d', strtotime("+1 days")),
						"updated_on"	=>	date('Y-m-d H:i:s'),
					);
					$status = $this->$model->updateData($updData, 'id_branch', $br['id_branch'], 'ret_day_closing');

					// print_r($this->db->last_query());exit;

					/* if ($status) {
						$this->stock_balance('autoDC',  $br['id_branch']);
						$this->stock_balance_nontag('autoDC',  $br['id_branch']);
					} */
				}





				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Day Close',

						'operation'     => 'Update',

						'record'        =>  $status,

						'remark'        => 'Day Close Updated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$result = array('status' => TRUE, 'message' => 'Day closed successfully');
				} else {

					$this->db->trans_rollback();

					$result = array('status' => FALSE, 'message' => "Error in Day close..", 'error_msg' => $this->db->_error_message());
				}

				echo json_encode($result);

				break;



			default:

				$SETT_MOD 	= self::SETT_MOD;

				$range['from_date']	= $this->input->post('from_date');

				$range['to_date']	= $this->input->post('to_date');

				$dayclose 		= $this->$model->ajax_dayclose($range['from_date'], $range['to_date']);

				$access 	= $this->$SETT_MOD->get_access('admin_ret_catalog/dayclose/list');

				$data 		= array(

					'dayclose' => $dayclose,

					'access' => $access

				);

				echo json_encode($data);
		}
	}


	//Charges

	function cover_up($type = "", $id = "")

	{

		$model = self::CAT_MODEL;



		switch ($type) {

			case "list":



				$data['main_content'] = "master/cover_up/list";

				$this->load->view('layout/template', $data);

				break;


			case "edit":



				$id_charge = $this->input->post('id_coverup');

				$data = $this->$model->get_coverup_list_edit($id_charge);

				echo json_encode($data);



				break;



			case "update":

				$this->load->library('form_validation');
				$this->form_validation->set_rules('metal', 'Metal', 'required|trim');
				$this->form_validation->set_rules('weight', 'Weight', 'required|trim|decimal');
				$this->form_validation->set_rules('created_by', 'Updated By', 'required');

				if ($this->form_validation->run()) {

					$data = ['id_metal' => $_POST['metal'], 'weight' => $_POST['weight'], 'updated_on' => date("Y-m-d H:i:s"), 'updated_by' => $_POST['created_by']];

					$this->db->trans_begin();
					$status = $this->ret_catalog_model->updateData($data, 'id_coverup', $_POST['id_coverup'], 'ret_cover_up');

					if ($this->db->trans_status() === TRUE) {
						$this->db->trans_commit();
					} else {
						$this->db->trans_rollback();
						$status = FALSE;
					}

					echo json_encode(['status' => $status, 'message' => " Added Successfully"]);
				} else {

					echo json_encode(['status' => FALSE, 'message' => reset($this->form_validation->error_array())]);
				}


				break;



			case "delete":



				$id_coverup = $this->input->post('id_coverup');

				$this->db->trans_begin();
				$data = $this->$model->deleteData('id_coverup', $id_coverup, 'ret_cover_up');

				$log_data = array(

					'id_log'        => $this->session->userdata('id_log'),

					'event_date'    => date("Y-m-d H:i:s"),

					'module'        => 'Cover Up',

					'operation'     => 'Delete',

					'record'        =>  $id_coverup,

					'remark'        => 'Cover Up deleted successfully'

				);

				$this->log_model->log_detail('insert', '', $log_data);

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
				} else {
					$this->db->trans_rollback();
				}

				echo json_encode($data);



				break;

			default:



				$range['from_date'] = $this->input->post("from_date");

				$range['to_date'] = $this->input->post("to_date");

				$list = $this->$model->get_cover_up_list($range['to_date'], $range['from_date']);

				$SETT_MOD = self::SETT_MOD;

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/cover_up/list');

				$data = array(

					'list' => $list,

					'access' => $access

				);

				echo json_encode($data);
		}
	}

	// COver Up functions :: ENDS

	public function ret_breakeven_logs($type = "", $id = "", $status = "")
	{
		$model = self::CAT_MODEL;

		$SETT_MOD = "admin_settings_model";

		switch ($type) {
			case "add":
				$branch_settings = $this->session->userdata('branch_settings');
				$pieces = $_POST['pieces'];
				//echo "<pre>"; print_r($_POST);exit;


				// Known gap fix: id_branch is chosen from an active-list dropdown
				// but was never re-checked for staleness before save.
				if (sizeof($pieces) > 0 && $branch_settings == 1) {
					foreach ($pieces as $items) {
						if (empty($items['id_branch']) || !$this->$model->check_branch_active($items['id_branch'])) {
							echo json_encode(array('message' => 'One or more selected Branches are no longer available. Please refresh and choose again.', 'status' => false));
							return;
						}
					}
				}

				$this->db->trans_begin();
				if (sizeof($pieces) > 0 && $branch_settings == 1) {

					foreach ($pieces as $items) {
						//$checkItemExist=$this->$model->checkReorderItemExist($items['id_branch'],$addData);
						//if($checkItemExist)

						$insdata = array(
							'brevn_log_branchid'	            => $items['id_branch'],
							'brevn_log_gold_val'	            => $items['goldvalue'],
							'brevn_log_silver_val'	            => $items['silvervalue'],
							'brevn_log_dia_val'	            => $items['dimondvalue'],
							'brevn_log_date'	  		=> date("Y-m-d H:i:s"),
						);
						$this->$model->insertData($insdata, 'ret_breakeven_logs');
					}
				}
				//$result=$this->$model->insertData($data,'ret_breakeven_logs');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'Breakeven Logs added successfully', 'class' => 'success', 'title' => 'Add Breakeven Logs'));

					$return_data = array('message' => 'Breakeven Logs added successfully', 'status' => true);

					echo json_encode($return_data);
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Add Breakeven Logs'));

					$return_data = array('message' => 'Unable to proceed the requested process', 'status' => false);

					echo json_encode($return_data);
				}



				break;
			case "Edit":

				$data['name'] = $this->$model->get_rbl($id);

				echo json_encode($data['name']);

				break;

			case 'Update':

				//$brevn_log_branchid=$this->input->post('id_branch');
				$brevn_log_gold_val = $this->input->post('goldvalue');
				$brevn_log_silver_val = $this->input->post('silvervalue');
				$brevn_log_dia_val = $this->input->post('dimondvalue');
				$UpdData = array(
					//'brevn_log_branchid'	    =>(!empty($brevn_log_branchid) ? $brevn_log_branchid: NULL),
					'brevn_log_gold_val'	    => (!empty($brevn_log_gold_val) ? $brevn_log_gold_val : NULL),
					'brevn_log_silver_val'	    => (!empty($brevn_log_silver_val) ? $brevn_log_silver_val : NULL),
					'brevn_log_dia_val'		    => (!empty($brevn_log_dia_val) ? $brevn_log_dia_val : NULL),
				);
				$this->db->trans_begin();



				$dev_status = $this->$model->updateData($UpdData, 'brevn_log_id', $id, 'ret_breakeven_logs');

				//echo $$this->db->last_query();exit;
				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();


					$this->session->set_flashdata('chit_alert', array('message' => 'Breakeven Logs Updated  successfully', 'class' => 'success', 'title' => 'Breakeven Logs'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Breakeven Logs'));
				}

				break;

			case 'update_status':
				/* 	echo "test";exit; */
				$data = array(
					'status' => $status,

					'updated_on'	  => date("Y-m-d H:i:s"),

					'updated_by'      => $this->session->userdata('uid')
				);

				$model = self::CAT_MODEL;

				$this->db->trans_begin();
				$updstatus = $this->$model->updateData($data, 'brevn_log_id', $id, 'ret_breakeven_logs');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Breakeven Logs status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'Breakeven Logs  Status'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Pay Device  Status'));
				}

				redirect('admin_ret_catalog/ret_account/list');

				break;
			case 'list':

				$data['main_content'] = "master/ret_breakeven_logs/list";

				$data['access'] = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_breakeven_logs/list');

				$this->load->view('layout/template', $data);

				break;
			case "delete":

				$this->db->trans_begin();

				$this->$model->deleteData('brevn_log_id', $id, 'ret_breakeven_logs');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$this->session->set_flashdata('chit_alert', array('message' => 'Breakeven Logs deleted successfully', 'class' => 'success', 'title' => 'Delete Breakeven Logs'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Delete Device'));
				}

				redirect('admin_ret_catalog/ret_breakeven_logs/list');
				break;

			default:

				$SETT_MOD = self::SETT_MOD;

				$list = $this->$model->ajax_getrbl($_POST);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_breakeven_logs/list');

				$data = array(

					'list' => $list,

					'access' => $access

				);

				echo json_encode($data);
		}
	}

	public function ret_qc_cancel_reason($type = "", $id = "", $status = "")
	{
		// /ajax is consumed by ret_purchase_order.js (qc_cancel_reason() populates a
		// delete-reason dropdown) outside this master's own page — response shape
		// {list, access} preserved exactly; everything else routes through the
		// generic engine.
		if ($type === 'ajax') {
			$model = self::CAT_MODEL;
			$SETT_MOD = self::SETT_MOD;
			$list = $this->$model->ajax_get_cancel_reason();
			$access = $this->$SETT_MOD->get_access('admin_ret_catalog/ret_qc_cancel_reason/list');
			echo json_encode(array('list' => $list, 'access' => $access));
			return;
		}
		$this->load->helper('lmx/engines/master_engine_helper');
		master_crud($this, 'ret_qc_cancel_reason', $type, $id, $status);
	}

	function product_grouping($type = "", $id = "", $status = "")
	{
		$model = self::CAT_MODEL;
		switch ($type) {
			case "add":
				$data = array(
					'group_name' => $this->input->post("product_grpname"),
					'group_metal' => $this->input->post("prodgrp_metal"),
					'status' => $this->input->post("prodstatus"),
					'created_on'		=> date("Y-m-d H:i:s"),
					'created_by'    	=> $this->session->userdata('uid')
				);
				$this->db->trans_begin();
				$this->$model->insertData($data, 'ret_product_grouping');
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$result = array('message' => 'Product Group Name added successfully!..', 'class' => 'success', 'title' => 'Product Grouping : ');
				} else {
					$this->db->trans_rollback();
					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Product Grouping : ');
				}
				echo json_encode($result);
				break;

			case 'list':
				$data['main_content'] = "master/product_grouping/list";
				$this->load->view('layout/template', $data);
				break;

			case "edit":
				$data['prodgrp'] = $this->$model->get_prodgrp($id);
				echo json_encode($data['prodgrp']);
				break;


			case "update":

				$data = array(
					'group_name' => $this->input->post("ed_product_grpname"),
					'group_metal' => $this->input->post("ed_prodgrp_metal"),
					'status' => $this->input->post("ed_prodstatus"),
					'updated_on' => date("Y-m-d H:i:s"),
					'updated_by' => $this->session->userdata('uid')
				);

				$this->db->trans_begin();

				$id = $this->$model->updateData($data, 'id_prod_group ', $id, 'ret_product_grouping');
				//print_r($this->db->last_query());exit;
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'User record modified successfully', 'class' => 'success', 'title' => 'Product group name'));
					echo 1;
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Product group name'));
					echo 0;
				}
				break;


			case 'delete':
				$this->db->trans_begin();
				$this->$model->deleteData('id_prod_group ', $id, 'ret_product_grouping');
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Product Group Name deleted successfully', 'class' => 'success', 'title' => 'Delete Product Group Name'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Delete Product Group Name'));
				}
				redirect('admin_ret_catalog/product_grouping/list');
				break;

			case 'update_status':
				$data = array('status' => $status, 'updated_on' => date("Y-m-d H:i:s"), 'updated_by' => $this->session->userdata('uid'));
				$this->db->trans_begin();
				$updstatus = $this->$model->updateData($data, 'id_prod_group ', $id, 'ret_product_grouping');
				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Product Grouping status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'Product Grouping  Status'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Product Grouping  Status'));
				}
				redirect('admin_ret_catalog/product_grouping/list');
				break;





			default:
				$SETT_MOD = self::SETT_MOD;
				$prod_grp = $this->$model->ajax_get_productgrp_name();
				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/product_grouping/list');
				$data = array(
					'prod_grp' => $prod_grp,
					'access' => $access
				);
				echo json_encode($data);
		}
	}



	function get_product_group($id)
	{
		$model = self::CAT_MODEL;
		$data = $this->$model->get_product_group($id);
		echo json_encode($data);
	}
	//End of product grouping

	public function stone_discount($type = "", $id= "",$status= ""){

		$model = self::CAT_MODEL;

		switch ($type) {

			case "add":

				$this->load->helper('customer_validation');
				$validation = validate_stone_discount_data($this, 'add');
				if (!$validation['valid']) {
					echo json_encode(array('status' => false, 'message' => implode(' ', $validation['errors'])));
					return;
				}

				// print_r($_POST);exit;
				$data = array(

					'id_stone_type'	      => (int)$this->input->post("stone_type"),

					'min_disc_per'	      => (float)$this->input->post("min_disc"),

					'max_disc_per'  => (float)$this->input->post("max_disc"),

					// 'branch_code'     => NULL, //$this->input->post("branch_code"),

					'status'	  => (int)$this->input->post("disc_status"),

					'created_on'	  => date("Y-m-d H:i:s"),

					'created_by'       => $this->session->userdata('uid')

				);

				$this->db->trans_begin();

				$insId = $this->$model->insertData($data, 'ret_stone_discount_master');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();


					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Stone Discount',

						'operation'     => 'Add',

						'record'        =>  $insId,

						'remark'        => 'Stone Discount added successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$result = array('message' => 'New Stone discount added successfully!..', 'class' => 'success', 'title' => 'Add Discount : ');
				} else {

					$this->db->trans_rollback();

					$result = array('message' => 'Unable to proceed the requested process..', 'class' => 'danger', 'title' => 'Add Discount : ');
				}

				echo json_encode($result);

				break;

			case "edit":

				$data['stn_disc'] = $this->$model->get_stn_disc($id);

				echo json_encode($data['stn_disc']);

				break;



			case 'delete':

				$this->db->trans_begin();

				$this->$model->deleteData('id', $id, 'ret_stone_discount_master');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Stone Discount',

						'operation'     => 'Delete',

						'record'        =>  $id,

						'remark'        => 'Stone Discount deleted successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Stone Discount deleted successfully', 'class' => 'success', 'title' => 'Delete Stone Discount'));

					echo 1;
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request', 'class' => 'danger', 'title' => 'Delete Stone Discount'));

					echo 0;
				}

				redirect('admin_ret_catalog/stone_discount/list');

				break;



			case "update":

				$this->load->helper('customer_validation');
				$validation = validate_stone_discount_data($this, 'edit', $id);
				if (!$validation['valid']) {
					echo json_encode(array('status' => false, 'message' => implode(' ', $validation['errors'])));
					return;
				}

				$data = array(

					'id_stone_type'	      => (int)$this->input->post("stone_type"),

					'min_disc_per'	      => (float)$this->input->post("min_disc"),

					'max_disc_per'  => (float)$this->input->post("max_disc"),

					// 'branch_code'     => NULL, //$this->input->post("branch_code"),

					'status'	  => (int)$this->input->post("disc_status"),

					'modified_on'	  => date("Y-m-d H:i:s"),

					'modified_by'      => $this->session->userdata('uid')

				);

				$this->db->trans_begin();



				$tax_id = $this->$model->updateData($data, 'id', $id, 'ret_stone_discount_master');

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();


					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Stone Discount',

						'operation'     => 'Update',

						'record'        =>  $id,

						'remark'        => 'Stone Discount Updated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Stone Discount record modified successfully', 'class' => 'success', 'title' => 'Edit Stone Discount'));

					echo json_encode(array('status' => true, 'message' => 'Stone Discount record modified successfully'));
				} else {

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Edit Stone Disount'));

					echo json_encode(array('status' => false, 'message' => 'Unable to proceed the requested process'));
				}



				break;

			case 'update_status':
				/* 	echo "test";exit; */
				$data = array(
					'status' => $status,

					'modified_on'	  => date("Y-m-d H:i:s"),

					'modified_by'      => $this->session->userdata('uid')
				);

				$this->db->trans_begin();
				$updstatus = $this->$model->updateData($data, 'id', $id, 'ret_stone_discount_master');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$this->session->set_flashdata('chit_alert', array('message' => 'Stone discount status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'Stone discount Status'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Stone discount Status'));
				}

				redirect('admin_ret_catalog/stone_discount/list');

				break;
			

			case 'list':

				$data['main_content'] = "master/discount/stone_list";

				$this->load->view('layout/template', $data);

				break;

			default:

				$SETT_MOD 	= self::SETT_MOD;

				$stn_disc 		= $this->$model->ajax_get_stn_disc();

				$access 	= $this->$SETT_MOD->get_access('admin_ret_catalog/stone_discount/list');

				$data 		= array(

					'stn_disc'   => $stn_disc,

					'access' => $access

				);

				echo json_encode($data);
		
		}
	}

	function wastage_discount($type = "", $id = "", $status = "")

	{

		$model     = "ret_catalog_model";

		switch ($type) {

			case 'new':

				$data['main_content'] = "master/discount/wastage_form";

				$this->load->view('layout/template', $data);

				break;

			case "Add":
				$this->load->helper('customer_validation');
				$validation = validate_wastage_discount_data($this, 'add');
				if (!$validation['valid']) {
					echo json_encode(array('status' => false, 'msg' => implode(' ', $validation['errors'])));
					return;
				}

				$va_data = array(
					'value'          => (float)$this->input->post('disc_value'),
					'from_range'   => (float)$this->input->post('from_wastage'),
					'to_range'     => (float)$this->input->post('to_wastage'),
					'id_metal'    => (int)$this->input->post('id_metal'),
					'created_on'    => date('Y-m-d H:i:s'),
					'created_by'    => $this->session->userdata('uid')
				);

				$id_metal     = (int)$this->input->post('id_metal');
				$from_wastage = (float)$this->input->post('from_wastage');
				$to_wastage   = (float)$this->input->post('to_wastage');
				$value        = (float)$this->input->post('disc_value');

				$data = $this->$model->check_wastage_range($from_wastage, $to_wastage, $value, $id_metal);

				if ($data == 0) {
					$this->db->trans_begin();
					$Insid = $this->$model->insertData($va_data, 'ret_wastage_discount_master');

					if ($this->db->trans_status() === TRUE) {
						$this->db->trans_commit();
						$log_data = array(
							'id_log'     => $this->session->userdata('id_log'),
							'event_date' => date('Y-m-d H:i:s'),
							'module'     => 'Wastage Discount Range',
							'operation'  => 'Add',
							'record'     => $Insid,
							'remark'     => 'Wastage Range added successfully'
						);
						$this->log_model->log_detail('insert', '', $log_data);
						$data = array('status' => true, 'msg' => 'Wastage Range Added Successfully', 'wastage_id' => $Insid);
					} else {
						$this->db->trans_rollback();
						$data = array('status' => false, 'msg' => 'Unable To Proceed Your request');
					}
				} else {
					$data = array('status' => false, 'msg' => 'Wastage Range already Existed');
				}

				echo json_encode($data);

				break;

			case "Edit":

				$data = $this->$model->get_wastage($id);

				echo json_encode($data);

				break;

			case "Delete":

				if (!lmx_can($this, 'admin_ret_catalog/wastage_discount/list', 'delete')) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Access Denied', 'class' => 'danger', 'title' => 'Delete Wastage'));
					redirect('admin_ret_catalog/wastage_discount/list');
					return;
				}

				// I-GMSJ-005: masters never hard-delete — referenced by
				// ret_estimation_items.wastage_slab_id, so a real DELETE would corrupt any
				// historical estimation still applying this wastage slab.
				if ($this->$model->check_wastage_in_use($id)) {
					$this->session->set_flashdata('chit_alert', array('message' => 'This wastage range is referenced by existing estimation records and cannot be deleted.', 'class' => 'danger', 'title' => 'Delete Wastage'));
					redirect('admin_ret_catalog/wastage_discount/list');
					return;
				}

				$this->db->trans_begin();
				$status = $this->$model->updateData(array('status' => 0), 'id_wastage', $id, 'ret_wastage_discount_master');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Wastage Range',

						'operation'     => 'Deactivate',

						'record'        =>  $id,

						'remark'        => 'Wastage Range deactivated successfully'

					);

					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Wastage deactivated successfully', 'class' => 'success', 'title' => 'Delete Wastage'));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Delete Wastage'));
				}

				redirect('admin_ret_catalog/wastage_discount/list');

				break;

			case "update_status":

				// I-GMSJ-005: no reactivate path existed at all before this.
				$new_status = ($status == 1) ? 0 : 1;

				$this->db->trans_begin();
				$this->$model->updateData(array('status' => $new_status), 'id_wastage', $id, 'ret_wastage_discount_master');

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$log_data = array(
						'id_log'        => $this->session->userdata('id_log'),
						'event_date'    => date("Y-m-d H:i:s"),
						'module'        => 'Wastage Range',
						'operation'     => 'Status Change',
						'record'        => $id,
						'remark'        => 'Wastage Range ' . ($new_status == 1 ? 'activated' : 'deactivated') . ' successfully',
					);
					$this->log_model->log_detail('insert', '', $log_data);
					echo json_encode(array('status' => true, 'msg' => 'Wastage Range ' . ($new_status == 1 ? 'activated' : 'deactivated') . ' successfully'));
				} else {
					$this->db->trans_rollback();
					echo json_encode(array('status' => false, 'msg' => 'Unable to proceed the requested operation'));
				}

				break;

			case "Update":
				$this->load->helper('customer_validation');
				$validation = validate_wastage_discount_data($this, 'edit', $id);
				if (!$validation['valid']) {
					echo json_encode(array('status' => false, 'msg' => implode(' ', $validation['errors'])));
					return;
				}

				$va_data = array(
					'value'         => (float)$this->input->post('value'),
					'from_range'   => (float)$this->input->post('from_range'),
					'to_range'     => (float)$this->input->post('to_range'),
					'id_metal'    => (int)$this->input->post('id_metal'),
					'modified_on'    => date('Y-m-d H:i:s'),
					'modified_by'    => $this->session->userdata('uid')
				);

				$this->db->trans_begin();
				$status = $this->$model->updateData($va_data, 'id_wastage', $id, 'ret_wastage_discount_master');

				if ($status) {
					$return_data = array('status' => true, 'msg' => 'Wastage Updated Successfully', 'wastage_id' => $id);
				} else {
					$return_data = array('status' => false, 'msg' => 'Unable to Proceed');
				}

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();
					$log_data = array(
						'id_log'     => $this->session->userdata('id_log'),
						'event_date' => date('Y-m-d H:i:s'),
						'module'     => 'Wastage Range',
						'operation'  => 'Update',
						'record'     => $id,
						'remark'     => 'Wastage Range Updated successfully'
					);
					$this->log_model->log_detail('insert', '', $log_data);
				} else {
					$this->db->trans_rollback();
				}

				echo json_encode($return_data);

				break;

			case "list":

				$data['main_content'] = "master/discount/wastage_list";

				$this->load->view('layout/template', $data);

				break;

			default:

				$SETT_MOD = self::SETT_MOD;

				$id_metal = $this->input->post('id_metal');

				$items = $this->$model->ajax_get_wastage($id_metal);

				$access = $this->$SETT_MOD->get_access('admin_ret_catalog/wastage_discount/list');

				$wastage = array(

					'access' => $access,

					'data'   => $items

				);

				echo json_encode($wastage);

				break;
		}
	}


	public function employee_stock_product($type = "")
	{

		$model = self::CAT_MODEL;
		switch ($type) {

			case 'add':

				try {
					$data['stock_audit_by'] = $this->$model->get_ret_settings('stock_audit_by');
				} catch (Exception $e) {
					$data['stock_audit_by'] = 1;
				}
				if (empty($data['stock_audit_by'])) $data['stock_audit_by'] = 1;
				$data['main_content'] = "employee_product_pcs/form";
				$this->load->view('layout/template', $data);
				break;

			case 'list':

				$data['main_content'] = "employee_product_pcs/list";
				$this->load->view('layout/template', $data);
				break;

			case 'save':


				$branch = $_POST['id_branch'];
				$employee = $_POST['id_employee'];
				$date = $_POST['product_date'];
				$products = $_POST['products'];


				$FromDt = date('Y-m-d', strtotime(str_replace("/", "-", $date)));

				$emp_data = array(
					'id_branch'    => $branch,
					'id_emp'       => $employee,
					'date'         => $FromDt,
					'created_on'	=> date("Y-m-d H:i:s"),
					'created_by'    => $this->session->userdata('uid'),


				);

				$this->db->trans_begin();

				$stock = $this->$model->get_employee_stock_products($employee, $branch, $FromDt);


				if (!empty($stock)) {

					$stock_date = $stock[0]['date'];

					$stock_emp_id = $stock[0]['id_emp_stock'];

					$stock_product_id = $stock[0]['id_product'];


					if ($stock_date == $FromDt) {

						$update_status = $this->$model->updateData($emp_data, 'id_emp_stock', $stock_emp_id, 'ret_employee_stock');
						$emp = $stock_emp_id;
					} else {
						$emp = $this->$model->insertData($emp_data, 'ret_employee_stock');
					}
				} else {

					$emp = $this->$model->insertData($emp_data, 'ret_employee_stock');
				}

				foreach ($products as $product) {

					$product_id   = $product['product_id'];
					$product_pcs  = isset($product['pieces']) ? $product['pieces'] : 0;
					$product_wt   = isset($product['weight']) ? $product['weight'] : 0;
					$product_type = isset($product['product_type']) ? $product['product_type'] : 1; // 1=tagged, 2=non-tagged
					$id_section   = $product['id_section'];
					$existing_product = $this->$model->get_employee_stock_product_details($emp, $product_id, $id_section);

					$pro_data = array(

						'id_emp_stock'  => $emp,
						'id_product' 	=> $product_id,
						'pcs'		    => $product_pcs,
						'weight'	    => $product_wt,
						'product_type'  => $product_type,
						'id_section' 	=> $id_section,
						'created_on'	=> date("Y-m-d H:i:s"),
						'created_by'    => $this->session->userdata('uid'),
						'is_updated'    => 0,

					);

					if (!empty($existing_product)) {

						if ($product_pcs > 0 || $product_wt > 0) {
							$this->$model->updateWhereData($pro_data,  ['id_product' => $product_id, 'id_section' => $id_section], 'ret_employee_stock_product');
						}
					} else {

						$this->$model->insertData($pro_data, 'ret_employee_stock_product');
					}
				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$response = array('message' => ' Updated Successfully', 'class' => 'success', 'title' => 'Pieces added successfully');
				} else {

					$this->db->trans_rollback();

					$response = array('message' => 'Unable to Proceed', 'class' => 'danger', 'title' => 'Error');
				}

				echo json_encode($response);
				break;

			case 'get_entry_log':

				$product_id = $this->input->post('product_id');
				$id_section = $this->input->post('id_section');
				$id_branch  = $this->input->post('id_branch');
				$date       = $this->input->post('date');
				$log = $this->$model->get_employee_entry_log($product_id, $id_section, $id_branch, $date);
				echo json_encode($log);
				break;

			case 'print':

				$id_branch    = $this->input->get('id_branch')    ? $this->input->get('id_branch')    : '';
				$id_employee  = $this->input->get('id_employee')  ? $this->input->get('id_employee')  : '';
				$date         = $this->input->get('date')         ? $this->input->get('date')         : '';
				$product_type = $this->input->get('product_type') ? $this->input->get('product_type') : '1';
				$audit_by     = $this->input->get('audit_by')     ? $this->input->get('audit_by')     : '1';
				$id_metal     = $this->input->get('id_metal')     ? $this->input->get('id_metal')     : '';
				$branch_name  = $this->input->get('branch_name')  ? $this->input->get('branch_name')  : '';
				$emp_name     = $this->input->get('emp_name')     ? $this->input->get('emp_name')     : '';

				// Model methods read id_metal from POST, so set it
				$_POST['id_metal'] = $id_metal;

				$is_tagged = ($product_type == '1');
				$product_type_label = $is_tagged ? 'Tagged Products' : 'Non-Tagged Products';

				// Fetch products based on audit mode (only entered products)
				$products_raw = [];
				if ($product_type == '2') {
					$raw = $this->$model->getProductBySection_NonTag($id_branch, $id_employee, $date, $product_type);
					$grouped = [];
					foreach ($raw as $p) {
						if ($p['entered_wt'] > 0) {
							$sec = !empty($p['section_name']) ? $p['section_name'] : 'No Section';
							if (!isset($grouped[$sec])) $grouped[$sec] = [];
							$grouped[$sec][] = $p;
						}
					}
					foreach ($grouped as $sec_name => $items) {
						$products_raw[] = ['is_section_header' => true, 'name' => $sec_name];
						foreach ($items as $item) {
							$products_raw[] = ['name' => $item['product_name'], 'value' => number_format($item['entered_wt'], 3)];
						}
					}
				} else if ($audit_by == '1') {
					$raw = $this->$model->getProductFlat($id_branch, $id_employee, $date, $product_type);
					foreach ($raw as $p) {
						if ($p['pcs'] > 0) {
							$products_raw[] = ['name' => $p['product_name'], 'value' => $p['pcs']];
						}
					}
				} else {
					$raw = $this->$model->getProductBySection($id_branch, $id_employee, $date, $product_type);
					$grouped = [];
					foreach ($raw as $p) {
						if ($p['pcs'] > 0) {
							$sec = !empty($p['section_name']) ? $p['section_name'] : 'No Section';
							if (!isset($grouped[$sec])) $grouped[$sec] = [];
							$grouped[$sec][] = $p;
						}
					}
					foreach ($grouped as $sec_name => $items) {
						$products_raw[] = ['is_section_header' => true, 'name' => $sec_name];
						foreach ($items as $item) {
							$products_raw[] = ['name' => $item['product_name'], 'value' => $item['pcs']];
						}
					}
				}

				$comp_details = $this->$model->getCompanyDetails($id_branch);

				// Strip emp code prefix (e.g. "111-Developer" -> "Developer")
				$emp_name_clean = urldecode($emp_name);
				if (strpos($emp_name_clean, '-') !== false) {
					$emp_name_clean = trim(substr($emp_name_clean, strpos($emp_name_clean, '-') + 1));
				}

				$data = [
					'company_name'       => !empty($comp_details) ? $comp_details['company_name'] : '',
					'branch_name'        => urldecode($branch_name),
					'employee_name'      => $emp_name_clean,
					'stock_date'         => $date,
					'product_type_label' => $product_type_label,
					'is_tagged'          => $is_tagged,
					'products'           => $products_raw
				];

				// Render as PDF via DOMPDF (same as estimation print)
				$html = $this->load->view('employee_product_pcs/print', $data, true);

				$this->load->helper(array('dompdf', 'file'));
				$dompdf = new \Dompdf\Dompdf();
				$dompdf->load_html($html);
				$dompdf->set_paper('A4', 'portrait');
				$dompdf->render();
				while(ob_get_level()) ob_end_clean();
				$dompdf->stream("employee_stock_entry.pdf", array('Attachment' => 0));
				break;
		}
	}

	public function get_employee_stock_product()
	{
		$model = self::CAT_MODEL;
		$this->$model->get_employee_stock_product();
	}

	/**
	 * AJAX: Get employees who have stock entries for a given branch + date
	 */
	public function get_employees_for_stock()
	{
		$model = self::CAT_MODEL;
		$id_branch = $this->input->post('id_branch') ? $this->input->post('id_branch') : '';
		$date      = $this->input->post('date')      ? $this->input->post('date')      : '';
		$data = $this->$model->get_employees_by_date($id_branch, $date);
		echo json_encode($data);
	}


	public function getProductBySection()
	{

		$model = self::CAT_MODEL;
		$id_branch    = $this->input->post('id_branch')    ? $this->input->post('id_branch')    : '';
		$id_employee  = $this->input->post('id_employee')  ? $this->input->post('id_employee')  : '';
		$date         = $this->input->post('date')         ? $this->input->post('date')         : '';
		$product_type = $this->input->post('product_type') ? $this->input->post('product_type') : '1';
		$data = $this->$model->getProductBySection($id_branch, $id_employee, $date, $product_type);
		$grouped_data = [];
		foreach ($data as $product) {

			$section_name = !empty($product['section_name']) ? $product['section_name'] : 'No Section';

			if (!isset($grouped_data[$section_name])) {
				$grouped_data[$section_name] = [];
			}

			$grouped_data[$section_name][] = [
				'pro_id'       => $product['pro_id'],
				'product_name' => $product['product_name'],
				'pcs'          => $product['pcs'],
				'is_updated'   => $product['is_updated'],
				'updated_on'   => $product['updated_on'],
				'id_section'   => $product['id_section']
			];
		}
		echo json_encode($grouped_data);
	}

	/**
	 * AJAX: Get flat unique product list for product-wise stock entry (stock_audit_by = 1)
	 * Returns a flat array (not grouped by section)
	 */
	public function getProductFlat()
	{
		$model = self::CAT_MODEL;
		$id_branch    = $this->input->post('id_branch')    ? $this->input->post('id_branch')    : '';
		$id_employee  = $this->input->post('id_employee')  ? $this->input->post('id_employee')  : '';
		$date         = $this->input->post('date')         ? $this->input->post('date')         : '';
		$product_type = $this->input->post('product_type') ? $this->input->post('product_type') : '1';
		$data = $this->$model->getProductFlat($id_branch, $id_employee, $date, $product_type);
		echo json_encode($data);
	}

	public function getProductBySection_NonTag()
	{
		$model = self::CAT_MODEL;
		$id_branch    = $this->input->post('id_branch')    ? $this->input->post('id_branch')    : '';
		$id_employee  = $this->input->post('id_employee')  ? $this->input->post('id_employee')  : '';
		$date         = $this->input->post('date')         ? $this->input->post('date')         : '';
		$product_type = $this->input->post('product_type') ? $this->input->post('product_type') : '2';
		$data = $this->$model->getProductBySection_NonTag($id_branch, $id_employee, $date, $product_type);
		$grouped_data = [];
		foreach ($data as $product) {
			$section_name = !empty($product['section_name']) ? $product['section_name'] : 'No Section';
			if (!isset($grouped_data[$section_name])) {
				$grouped_data[$section_name] = [];
			}
			$grouped_data[$section_name][] = [
				'pro_id'       => $product['pro_id'],
				'product_name' => $product['product_name'],
				'stock_gwt'    => $product['stock_gwt'],
				'stock_pcs'    => $product['stock_pcs'],
				'entered_wt'   => $product['entered_wt'],
				'is_updated'   => $product['is_updated'],
				'updated_on'   => $product['updated_on'],
				'id_section'   => $product['id_section']
			];
		}
		echo json_encode($grouped_data);
	}

	/**
	 * AJAX: Get sections with stock counts for section-based entry mode
	 */
	public function getSectionListForStock()
	{
		$model = self::CAT_MODEL;
		$id_branch   = $this->input->post('id_branch')   ? $this->input->post('id_branch')   : '';
		$id_employee = $this->input->post('id_employee') ? $this->input->post('id_employee') : '';
		$date        = $this->input->post('date')        ? $this->input->post('date')        : '';
		$data = $this->$model->getSectionListForStock($id_branch, $id_employee, $date);
		echo json_encode($data);
	}





	function shape_status($status, $id)
	{
		$model = self::CAT_MODEL;

		$data = array(
			'status' => $status,
			'updated_on' => date("Y-m-d H:i:s")
		);

		$this->db->trans_begin();
		$this->$model->update_shape($data, $id);

		if ($this->db->trans_status() === TRUE) {
			$this->db->trans_commit();

			$log_data = array(
				'id_log' => $this->session->userdata('id_log'),
				'event_date' => date("Y-m-d H:i:s"),
				'module' => 'Diamond Shape',
				'operation' => 'Status Change',
				'record' => $id,
				'remark' => 'Diamond Shape status changed to ' . ($status == 1 ? 'Active' : 'Inactive')
			);

			$this->log_model->log_detail('insert', '', $log_data);

			$this->session->set_flashdata('chit_alert', array('message' => 'Diamond Shape status updated as ' . ($status == 1 ? 'Active' : 'Inactive') . ' successfully.', 'class' => 'success', 'title' => 'Diamond Shape Status'));
		} else {
			$this->db->trans_rollback();
			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested operation', 'class' => 'danger', 'title' => 'Diamond Shape Status'));
		}

		redirect('admin_ret_catalog/shape/list');
	}

}


