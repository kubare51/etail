<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH.'libraries/dompdf/autoload.inc.php');

use Dompdf\Dompdf;

class Admin_ret_purchase extends CI_Controller {

	const VIEW_FOLDER = 'ret_purchase/';

	const RET_PUR_ORDER_MODEL = 'ret_purchase_order_model';

	const SETT_MOD	= "admin_settings_model";

	const IMG_PATH  = 'assets/img/';

	const SINGLE_PCS_PARTIAL_WT_MSG = 'Partial weight processing is not allowed for a single-piece item. The complete piece and weight must be processed.';

	const PARTIAL_WT_BOUNDARY_MSG = 'Pieces and weight must match at the boundary: processing all pieces requires the full weight, and processing zero pieces requires zero weight. Partial weight is allowed only for a partial piece count.';

	const QC_RECEIPT_LOCK_MSG = 'QC Receipt cannot be modified because a Purchase Return has already been completed for this transaction.';

	/**
	 * Float-safe equality for decimal(10,3) weight columns — never compare with == on floats.
	 */
	private function _qc_wt_equals($a, $b)
	{
		return abs(round((float)$a, 3) - round((float)$b, 3)) < 0.0005;
	}

	/**
	 * CPO-05: float-safe equality for decimal(_,2) amount columns.
	 *
	 * The browser produces amounts with Number.toFixed(2) and PHP with round(x, 2).
	 * They disagree on binary half-way values — (1.005).toFixed(2) is "1.00" while
	 * round(1.005, 2) is 1.01 — so an == comparison rejects perfectly legitimate
	 * lines. The tolerance is deliberately wider than one paisa because a line
	 * total accumulates three such roundings (taxable, tax, total).
	 */
	private function _cpo_amt_equals($a, $b)
	{
		return abs(round((float)$a, 2) - round((float)$b, 2)) < 0.05;
	}

	/**
	 * Picks the wording that actually describes the row that failed: the original
	 * "single-piece item" message when the line's total pcs really is 1 (unchanged
	 * from what shipped), or the general boundary message for any other pcs count.
	 */
	private function _qc_partial_wt_msg($total_pcs)
	{
		return ((int)$total_pcs === 1) ? self::SINGLE_PCS_PARTIAL_WT_MSG : self::PARTIAL_WT_BOUNDARY_MSG;
	}

	function __construct()

	{

		parent::__construct();

		ini_set('date.timezone', 'Asia/Calcutta');

		$this->load->model(self::RET_PUR_ORDER_MODEL);

		$this->load->model("admin_settings_model");

		$this->load->model("log_model");

		$this->load->model("admin_usersms_model");

		$this->load->model('email_model');

		$this->load->model("sms_model");

		$this->load->model("ret_reports_model");

		$this->load->model("ret_catalog_model");

		$this->load->model(self::SETT_MOD);

		if(!$this->session->userdata('is_logged'))

		{

			redirect('admin/login');

		}

		elseif($this->session->userdata('access_time_from') != NULL && $this->session->userdata('access_time_from') != "")

		{

			$now = time();

			$from = $this->session->userdata('access_time_from');

			$to = $this->session->userdata('access_time_to');

			$allowedAccess = ($now > $from && $now < $to) ? TRUE : FALSE ;

			if($allowedAccess == FALSE){

				$this->session->set_flashdata('login_errMsg','Exceeded allowed access time!!');

				redirect('chit_admin/logout');

			}

		}

	}



	function index(){



	}

    function check_cheque_number_exist()
    {
        // Clear any previous output
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/json');

        try {
            $cheque_no = $this->input->post('cheque_no');
            
            if(!empty($cheque_no)) {
                // Ensure model is loaded
                if (!isset($this->ret_purchase_order_model)) {
                     $this->load->model(self::RET_PUR_ORDER_MODEL);
                }

                $exists = $this->ret_purchase_order_model->check_cheque_number_exist($cheque_no);
                
                if ($exists) {
                    echo json_encode(array('status' => false, 'message' => 'Cheque number already exists!'));
                } else {
                    echo json_encode(array('status' => true));
                }
            } else {
                // No cheque number provided, treat as valid (or handle error)
                echo json_encode(array('status' => true)); 
            }
        } catch (Exception $e) {
            log_message('error', 'check_cheque_number_exist Error: '.$e->getMessage());
            // Return failure response in case of exception
            echo json_encode(array('status' => true)); 
        }
        exit;
    }





	function set_image($id,$img_path,$file)

	{

	 	if($_FILES[$file]['name'])

	   	 {

	        $img = $_FILES[$file]['tmp_name'];

			$status = $this->upload_img($file,$img_path,$img);

			return $status;

		 }

	}

    function upload_img( $outputImage,$dst, $img)

	{

		if (($img_info = getimagesize($img)) === FALSE)

		{

			// die("Image not found or not an image");

			return false;

		}

		$width = $img_info[0];

		$height = $img_info[1];

		switch ($img_info[2]) {

		  case IMAGETYPE_GIF  : $src = imagecreatefromgif($img);

		  						$tmp = imagecreatetruecolor($width, $height);

		  						$kek=imagecolorallocate($tmp, 255, 255, 255);

					      		imagefill($tmp,0,0,$kek);

		  						break;

		  case IMAGETYPE_JPEG : $src = imagecreatefromjpeg($img);

		  						$tmp = imagecreatetruecolor($width, $height);

		 						break;

		  case IMAGETYPE_PNG  : $src = imagecreatefrompng($img);

							    $tmp = imagecreatetruecolor($width, $height);

		  						$kek=imagecolorallocate($tmp, 255, 255, 255);

					     		imagefill($tmp,0,0,$kek);

					     		break;

		  default : //die("Unknown filetype");

		  return false;

		}

		imagecopyresampled($tmp, $src, 0, 0, 0, 0, $width, $height, $width, $height);

		$res = imagejpeg($tmp, $dst);

		return $res;

	}



	public function base64ToFile($imgBase64){

		$data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imgBase64)); // might not work on some systems, specify your temp path if system temp dir is not writeable

		$temp_file_path = tempnam(sys_get_temp_dir(), 'tempimg');

		file_put_contents($temp_file_path, $data);

		$image_info = getimagesize($temp_file_path);

		$imgFile = array(

		     'name' => uniqid().'.'.preg_replace('!\w+/!', '', $image_info['mime']),

		     'tmp_name' => $temp_file_path,

		     'size'  => filesize($temp_file_path),

		     'error' => UPLOAD_ERR_OK,

		     'type'  => $image_info['mime'],

		);

		return $imgFile;

	}

	public function imgTobase64($path)

	{

		$type = pathinfo($path, PATHINFO_EXTENSION);

		$data = file_get_contents($path);

		$base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

		return $base64;

	}



    //Order Description

	function order_description($type="",$id="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;



		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'order_description/list';
					
					$data['access'] = $this->$SETT_MOD->get_access('admin_ret_purchase/order_description/list');

        			$this->load->view('layout/template', $data);

			break;

			case 'save':

			    $insData=array(

			                  'description'=>$_POST['order_des'],

			                  'created_on' => date("Y-m-d H:i:s"),

				              'created_by' => $this->session->userdata('uid')

			                  );

			     $this->db->trans_begin();

			     $insOrder = $this->$model->insertData($insData,'ret_purchase_order_description');

			    if($this->db->trans_status()===TRUE)

    			{

    				$this->db->trans_commit();

					  $log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'ORDER DESCRIPTION',

						'operation'     => 'Add',

						'record'        => $insOrder,

						'remark'        => 'Order Description added successfully'

						);

					$this->log_model->log_detail('insert','',$log_data);

    				$this->session->set_flashdata('chit_alert',array('message'=>'Order Instructions Added Successfully','class'=>'success','title'=>'Order Instructions'));

    				$return_data=array('status'=>TRUE,'message'=>'Order Instructions Added Successfully..');

    			}

    			else

    			{

    			    echo $this->db->last_query();exit;

    				$this->db->trans_rollback();

    				$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Order Instructions'));

    				$return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

    			}

    			echo json_encode($return_data);

			break;



			case 'delete':

    			   $this->$model->deleteData('id_order_des',$id,'ret_purchase_order_description');

    	           if($this->db->trans_status()===TRUE)

    			    {

    			    	  $this->db->trans_commit();

						  $log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'ORDER DESCRIPTION',

							'operation'     => 'Delete',

							'record'        =>  $id,

							'remark'        => 'Order Description deleted successfully'

							);

	                         $this->log_model->log_detail('insert','',$log_data);

    					  $this->session->set_flashdata('chit_alert', array('message' => 'Order Instructions deleted successfully','class' => 'success','title'=>'Delete Order Instructions'));

    				}

    			   else

    			    {

    				 $this->db->trans_rollback();

    				 $this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request','class' => 'danger','title'=>'Delete Order Instructions'));

    			    }

    			 redirect('admin_ret_purchase/order_description/list');

			 break;



			 case 'edit':

			    $model  =	self::RET_PUR_ORDER_MODEL;

                $data   =  $this->$model->get_orderdescription($id);

                echo json_encode($data);

			 break;



			 case 'update':

			     $updData=array(

			                   'description'=>$_POST['order_des'],

			                   'updated_on' => date("Y-m-d H:i:s"),

				               'updated_by' => $this->session->userdata('uid')

			                   );

			     $this->db->trans_begin();

			     $id_order_des =$this->$model->updateData($updData,'id_order_des',$_POST['id_order_des'],'ret_purchase_order_description');

			    if($this->db->trans_status()===TRUE)

    			{

    				$this->db->trans_commit();


				    $log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'ORDER DESCRIPTION',

						'operation'     => 'Update',

						'record'        =>  $id_order_des,

						'remark'        => 'Order Description Updated successfully'

						);

			          $this->log_model->log_detail('insert','',$log_data);


    				$this->session->set_flashdata('chit_alert',array('message'=>'Order Instructions Updated Successfully','class'=>'success','title'=>'Order Instructions'));

    				$return_data=array('status'=>TRUE,'message'=>'Order Instructions Added Successfully..');

    			}

    			else

    			{



    			    echo $this->db->last_query();exit;

    				$this->db->trans_rollback();

    				$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Order Instructions'));

    				$return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

    			}

    			echo json_encode($return_data);

			 break;



		    default:

			        $list=$this->$model->get_order_description();

				  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/order_description/list');

			        $data = array(

			        					'list'  => $list,

										'access'=> $access

			        				);

					echo json_encode($data);

		}

	}

	//Order Description



    //Purchase Order





    function get_customer_order_pending_details()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_customer_order_pending_details($_POST);

        echo json_encode($data);

	}





	function get_stock_repair_order_details()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_stock_repair_order($_POST);

        echo json_encode($data);

	}



    function purchase($type="",$id="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$set_model ="admin_settings_model";

		switch($type)

		{
			case 'list':

			        $this->session->unset_userdata('pur_entry_secret_key');

					$data['main_content'] = self::VIEW_FOLDER.'purchase_entry_list';

					$data['access'] = $this->$set_model->get_access('admin_ret_purchase/purchase/list');

					


        			$this->load->view('layout/template', $data);

			break;

			case 'pur_order':

					$data['main_content'] = self::VIEW_FOLDER.'pur_order_list';

					$data['access'] = $this->$set_model->get_access('admin_ret_purchase/purchase/pur_order');

					
					// print_r($data['access']);exit;

        			$this->load->view('layout/template', $data);

			break;

			case 'add':

			        $data['po_item']=$this->$model->get_empty_record();

                    $data['gold_rate'] = $this->$model->get_gold_rate();

                    // CPO-03: Common PO (bullion advance rate fixing).
                    // Feature is config-gated and defaults off, so existing clients
                    // see no change until common_po_enabled is switched on.
                    $data['common_po_enabled']       = $this->$model->get_common_po_setting('common_po_enabled', '0');
                    $data['common_po_categories']    = ($data['common_po_enabled'] == 1) ? $this->$model->getCommonPoCategories() : array();
                    $data['common_po_unfixed_allow'] = $this->$model->get_common_po_setting('common_po_advance_on_unfixed', '1');

					$data['main_content'] = self::VIEW_FOLDER.'order_form';

        			$this->load->view('layout/template', $data);

			break;

			/* ============================================================
			 * PO-EDIT-3: open an existing Purchase Order for amendment.
			 *
			 * The PO screen was create-only — to change a PO you cancelled it and
			 * raised a new one, losing the PO number and the audit thread. This
			 * serves every PO type; the Common PO block hydrates from the same
			 * payload because its lines live in the same two tables.
			 * ============================================================ */
			case 'edit':

			        $id = (int)$id;

			        $head = $this->$model->get_po_for_edit($id);

			        if (empty($head)) { show_error('Purchase Order not found.', 404); return; }

			        /* Refuse rather than render a form that cannot be saved. The same
			           rules run again on update — this is the courtesy, not the control. */
			        $blockers = $this->$model->get_po_edit_blockers($id);

			        if (!empty($blockers)) {
			            $data['po_head']      = $head;
			            $data['blockers']     = $blockers;
			            $data['main_content'] = self::VIEW_FOLDER.'po_edit_blocked';
			            $this->load->view('layout/template', $data);
			            return;
			        }

			        $data['po_item']   = $this->$model->get_empty_record();

			        $data['gold_rate'] = $this->$model->get_gold_rate();

			        $data['common_po_enabled']       = $this->$model->get_common_po_setting('common_po_enabled', '0');
			        $data['common_po_categories']    = ($data['common_po_enabled'] == 1) ? $this->$model->getCommonPoCategories() : array();
			        $data['common_po_unfixed_allow'] = $this->$model->get_common_po_setting('common_po_advance_on_unfixed', '1');

			        /* order_form.php:253 already reads $purchase_details[0] for the hidden
			           id_customerorder — it was wired for an edit mode that was never built. */
			        $data['purchase_details'] = array($head);
			        $data['po_edit_head']     = $head;
			        $data['po_edit_lines']    = $this->$model->get_po_lines_for_edit($id);

			        $data['main_content'] = self::VIEW_FOLDER.'order_form';

        			$this->load->view('layout/template', $data);

			break;

			/* ============================================================
			 * PO-EDIT-4: save an amendment.
			 *
			 * A separate case from "save" so the create path is untouched; the two
			 * share _po_line_payload() so a PO line cannot be built two different ways.
			 *
			 * Lines are updated IN PLACE by id_orderdetails rather than deleted and
			 * re-inserted. Re-inserting would mint new line ids and orphan everything
			 * hanging off the old ones — customer_order_image rows, the cus_orderdet_ref
			 * back-link to the customer order, and order_cart.id_orderdetails.
			 * ============================================================ */
			case 'po_update':

			        $addData       = $_POST['order'];
			        $order_details = isset($_POST['order_details']) ? $_POST['order_details'] : array();

			        $id = isset($addData['id_customerorder']) ? (int)$addData['id_customerorder'] : 0;

			        if ($id <= 0) {
			            echo json_encode(array('status'=>FALSE,'msg'=>'No Purchase Order specified.'));
			            return;
			        }

			        /* The screen hides the button and refuses to render, but this endpoint
			           is reachable directly. A received or billed PO must never be rewritten. */
			        $block = $this->_block_po_edit($id);

			        if ($block !== NULL) {
			            echo json_encode(array('status'=>FALSE,'msg'=>$block));
			            return;
			        }

			        /* A Common PO posts cpo_items, not order_details, and its lines are
			           priced by a server-side recompute. It takes the same save path it
			           was created by, with the id threaded through — see _save_common_po().
			           Checked against the stored flag, not the posted one, so the shape of
			           the payload cannot decide which rules apply. */
			        $po_kind = $this->$model->get_po_for_edit($id);

			        if (!empty($po_kind) && (int)$po_kind['is_common_po'] === 1) {
			            echo json_encode($this->_save_common_po($id));
			            return;
			        }

			        /* PO-EDIT-5: server-side validation. The screen checks the same things
			           before it posts, but this endpoint is reachable directly and the
			           browser's copy of the rules is a convenience, not a control. */
			        $errors = array();

			        if (empty($addData['id_karigar'])) {
			            $errors[] = 'Select a supplier.';
			        }

			        if (empty($addData['smith_due_dt'])) {
			            $errors[] = 'Select a due date.';
			        }

			        if (empty($order_details) || empty($order_details['product'])) {
			            $errors[] = 'A Purchase Order must have at least one item.';
			        }

			        if (empty($errors)) {

			            /* Every parallel array must be the same length. A short array means
			               a row was posted half-formed, and PHP would quietly read NULL for
			               the missing key and write a line with holes in it. */
			            $n    = count($order_details['product']);
			            $rows = array('design','sub_design','weight_range','order_wt','size','piece');

			            foreach ($rows as $rk) {
			                if (isset($order_details[$rk]) && count($order_details[$rk]) !== $n) {
			                    $errors[] = 'The item rows did not post correctly. Reload the screen and try again.';
			                    break;
			                }
			            }

			            foreach ($order_details['product'] as $k => $prod) {

			                $rowno = $k + 1;

			                if (empty($prod)) {
			                    $errors[] = 'Row '.$rowno.': product is missing.';
			                }

			                $pcs = isset($order_details['piece'][$k]) ? (float)$order_details['piece'][$k] : 0;
			                if ($pcs <= 0) {
			                    $errors[] = 'Row '.$rowno.': pieces must be more than zero.';
			                }

			                $wt = isset($order_details['order_wt'][$k]) ? (float)$order_details['order_wt'][$k] : 0;
			                if ($wt < 0) {
			                    $errors[] = 'Row '.$rowno.': weight cannot be negative.';
			                }

			                /* A line id that is not on THIS purchase order would move
			                   somebody else's line onto it. Treated as tampering, not as a
			                   new row, so it cannot pass silently. */
			                $line_id = isset($order_details['po_line_id'][$k]) ? (int)$order_details['po_line_id'][$k] : 0;

			                if ($line_id > 0) {
			                    $owns = $this->db->query("SELECT COUNT(*) n FROM customerorderdetails
			                        WHERE id_orderdetails = ? AND id_customerorder = ?",
			                        array($line_id, $id))->row_array();
			                    if (empty($owns['n'])) {
			                        $errors[] = 'Row '.$rowno.' does not belong to this Purchase Order.';
			                    }
			                }
			            }
			        }

			        if (!empty($errors)) {
			            echo json_encode(array('status'=>FALSE,'msg'=>implode(' ', $errors),'errors'=>$errors));
			            return;
			        }

			        $before = $this->$model->get_po_for_edit($id);

			        $smith_due_date = (!empty($addData['smith_due_dt'])
			            ? date('Y-m-d', strtotime(str_replace("/","-",$addData['smith_due_dt'])))
			            : NULL);

			        $this->db->trans_begin();

			        /* Header. pur_no, fin_year_code, order_date and createdon are NOT
			           rewritten — an amendment keeps its identity and its original raising
			           date, otherwise the audit thread breaks.

			           order_status goes back to 10 (Waiting for Approval): an approved PO
			           that is then amended is no longer the thing that was approved. */
			        $this->$model->updateData(array(
			            'order_pcs'       => $addData['order_pcs'],
			            'order_approx_wt' => $addData['order_wt'],
			            'order_for'       => $addData['order_for'],
			            'id_karigar'      => $addData['id_karigar'],
			            'rate_type'       => (isset($addData['rate_type']) ? $addData['rate_type'] : 2),
			            'order_status'    => 10,
			        ), 'id_customerorder', $id, 'customerorder');

			        /* A Common PO carries its own approval flag, checked by the advance and
			           billing screens. Clearing order_status alone would leave it approved. */
			        if ((int)$before['is_common_po'] === 1) {
			            $this->$model->updateData(array(
			                'common_po_approved'    => 0,
			                'common_po_approved_on' => NULL,
			                'common_po_status'      => 0,
			            ), 'id_customerorder', $id, 'customerorder');
			        }

			        $existing = $this->$model->get_po_lines_for_edit($id);
			        $known    = array();
			        foreach ($existing as $e) { $known[(int)$e['id_orderdetails']] = TRUE; }

			        $kept = array();

			        foreach ($order_details['product'] as $key => $val) {

			            $line = $this->_po_line_payload($order_details, $key, $addData, $id, $smith_due_date);

			            $line_id = isset($order_details['po_line_id'][$key])
			                     ? (int)$order_details['po_line_id'][$key] : 0;

			            if ($line_id > 0 && isset($known[$line_id])) {
			                /* id_customerorder never changes on an update, and writing it
			                   would let a tampered post move a line to another PO. */
			                unset($line['id_customerorder']);
			                $this->$model->updateData($line, 'id_orderdetails', $line_id, 'customerorderdetails');
			                $kept[$line_id] = TRUE;
			            } else {
			                $new_id = $this->$model->insertData($line, 'customerorderdetails');
			                if ($new_id) { $kept[(int)$new_id] = TRUE; }
			            }
			        }

			        /* Lines the user removed. Released the same way cancel releases them,
			           so a customer-order line does not stay pinned to a PO it is no
			           longer on. */
			        foreach ($existing as $e) {

			            $eid = (int)$e['id_orderdetails'];
			            if (isset($kept[$eid])) { continue; }

			            if (!empty($e['cus_orderdet_ref'])) {
			                $this->$model->updateData(array('orderstatus'=>0),
			                    'id_orderdetails', $e['cus_orderdet_ref'], 'customerorderdetails');
			            }

			            $this->db->where('id_orderdetails', $eid);
			            $this->db->update('order_cart', array('orderstatus'=>0, 'id_orderdetails'=>NULL));

			            $this->db->where('id_orderdetails', $eid);
			            $this->db->delete('customer_order_image');

			            $this->db->where('id_orderdetails', $eid);
			            $this->db->delete('customerorderdetails');
			        }

			        if ($this->db->trans_status() === TRUE) {

			            $this->db->trans_commit();

			            $this->log_model->log_detail('insert', '', array(
			                'id_log'     => $this->session->userdata('id_log'),
			                'event_date' => date("Y-m-d H:i:s"),
			                'module'     => 'Purchase Order',
			                'operation'  => 'Update',
			                'record'     => NULL,
			                'remark'     => 'Purchase Order '.$before['pur_no'].' amended; sent back for approval.',
			            ));

			            echo json_encode(array(
			                'status'  => TRUE,
			                'id'      => $id,
			                'pur_no'  => $before['pur_no'],
			                'msg'     => 'Purchase Order '.$before['pur_no'].' updated. It has been sent back for approval.',
			            ));

			        } else {

			            $this->db->trans_rollback();

			            echo json_encode(array('status'=>FALSE,'msg'=>'Could not update the Purchase Order. No change has been saved.'));
			        }

			break;

			case 'cancel_po_entry':

                    $updData=array(

                    'bill_status'       => 2,

                    'cancel_reason'     => $_POST['cancel_reason'],

                    'cancelled_date'        => date("Y-m-d H:i:s"),

                    'cancelled_by'        => $this->session->userdata('uid')

                    );

                    $this->db->trans_begin();

                    /* CPO-15: release any Common PO advance this bill consumed and roll
                       its receipt back off the PO. Runs BEFORE the status flip so the
                       reversal still sees the bill as live, and inside the same
                       transaction so a failed cancel cannot half-release the advance. */
                    $this->_reverse_common_po_bill($_POST['po_id']);


                    $this->$model->updateData($updData,'po_id',$_POST['po_id'],'ret_purchase_order');

                    if($this->db->trans_status()===TRUE)

                    {

                        $this->db->trans_commit();

						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'Supplier Bill Entry',

							'operation'     => 'Cancel',

							'record'        =>  $_POST['po_id'],

							'remark'        => 'Supplier Bill Entry Cancelled successfully'

							);

	                    $this->log_model->log_detail('insert','',$log_data);

                        $this->session->set_flashdata('chit_alert',array('message'=>'Supplier Bill Entry Cancelled Successfully','class'=>'success','title'=>'Supplier Bill Entry'));

                        $return_data=array('status'=>TRUE,'message'=>'Supplier Bill Entry Cancelled Successfully');

                    }

                    else

                    {

                        echo $this->db->last_query();exit;

                        $this->db->trans_rollback();

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Supplier Bill Entry'));

                        $return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

                    }

                    echo json_encode($return_data);

			 break;



			case 'purchase_add':

			        if($id!='')

			        {

			            $data['po_item']=$this->$model->getPurchaseOrderDet($id);

			            $data['po_item_details']=$this->$model->getPurchaseOrderItemDet($id);

			        }

			        else

			        {

			            $data['po_item']=$this->$model->get_empty_record();

			            $data['po_item_details']=[];

			        }

			        //echo "<pre>";print_r($data);exit;

			        $data['comp_details']   = $this->$set_model->get_company();

			        // CPO-13: lets the screen keep "Against Order" available for a bullion
			        // bill, which is exactly how a Common PO gets billed.
			        $data['common_po_enabled'] = $this->$model->get_common_po_setting('common_po_enabled', '0');

			        // CPO-12: BRS criterion 17 — rate override needs an explicit grant.
			        $data['common_po_rate_override'] = $this->_can_override_common_po_rate() ? '1' : '0';

					$data['main_content'] = self::VIEW_FOLDER.'pur_entry_form';

        			$this->load->view('layout/template', $data);

			break;



			case 'order_status':

					$data['main_content'] = self::VIEW_FOLDER.'order_status';

        			$this->load->view('layout/template', $data);

			break;



			case 'qc_status':

					$data['main_content'] = self::VIEW_FOLDER.'qc_status';

        			$this->load->view('layout/template', $data);

			break;



			case 'order_delivery':

					$data['main_content'] = self::VIEW_FOLDER.'order_delivery';

        			$this->load->view('layout/template', $data);

			break;


		case "save":

		    // CPO-05: a Common PO (bullion advance rate fixing) posts a different
		    // item set (cpo_items[...]) and different header columns, so it takes
		    // its own save path. Branching here rather than threading conditionals
		    // through the block below keeps the established Stock / Customer /
		    // Repair order save completely untouched.
		    if (isset($_POST['order']['is_common_po']) && $_POST['order']['is_common_po'] == 1) {
		        echo json_encode($this->_save_common_po());
		        return;
		    }

		    // print_r($_POST);exit;

            $addData   = $_POST['order'];

            $order_details   = $_POST['order_details'];



			$fin_year       = $this->$model->get_FinancialYear();

			$order_no       = $this->$model->generatePurNo();

 			$order = array(

 			    'fin_year_code'     => $fin_year['fin_year_code'],

 				'pur_no'            => ($fin_year['fin_year_code'].'-'.$order_no),

 				'order_status'		=> 10,

 				'order_type'		=> 1,

 				'order_pcs'			=> $addData['order_pcs'],

 				'order_approx_wt'	=> $addData['order_wt'],

 				'order_for'			=> $addData['order_for'],

 				'id_karigar'		=> $addData['id_karigar'],

 				'cus_ord_ref'		=> ($addData['id_customer_order']!='' && $addData['id_customer_order']!='null' ? $addData['id_customer_order'] :NULL),

 				'order_date'		=> date("Y-m-d H:i:s"),

				'createdon'         => date("Y-m-d H:i:s"),

				'order_taken_by'    => $this->session->userdata('uid'),

                'rate_type'         => (isset($addData['rate_type']) ? $addData['rate_type'] : 2)

			);

			$this->db->trans_begin();

			$insOrder = $this->$model->insertData($order,'customerorder');

		    //print_r($this->db->last_query());exit;

		    if($insOrder)

		    {





		        if(!empty($order_details))

		        {

		            foreach($order_details['product'] as $key => $val)

		            {



		                $smith_due_date = (!empty($addData['smith_due_dt']) ?date('Y-m-d',strtotime(str_replace("/","-",$addData['smith_due_dt']))):NULL );



		                /* PO-EDIT-4: extracted so the update path builds a line the same
		                   way create does. Two copies of this array would drift the first
		                   time a column was added to one of them. */
		                $orderDetail = $this->_po_line_payload($order_details, $key, $addData, $insOrder, $smith_due_date);

		                  $id_order_Details=$this->$model->insertData($orderDetail,'customerorderdetails');

		                //   print_r($this->db->last_query());exit;

		                  if($id_order_Details)

		                  {



		                    if($order_details['id_orderdetails'][$key]!='')

            		        {

            				    $this->$model->updateData(array('orderstatus'=>3,'assigned_by'=>$this->config->item('uid'),
								'assigned_on'=> date("Y-m-d H:i:s")),'id_orderdetails',$order_details['id_orderdetails'][$key],'customerorderdetails'); // For Customer Order Status

            		        }



		                    $img_details=json_decode(rawurldecode($order_details['order_images'][$key]));

		                    //$img = json_decode(($img_details));

		                    $_FILES['order_image']    =  array();

		                    foreach($img_details as $image)

		                    {

		                       $imgFile = $this->base64ToFile($image->src);

    						   $_FILES['order_image'][] = $imgFile;

		                    }



    	                    if(!empty($_FILES)) {

        						$folder =  self::IMG_PATH."order/purchase_order/";

        						if (!is_dir($folder)) {

        							mkdir($folder, 0777, TRUE);

        						}

        						if(isset($_FILES['order_image'])){

        							$precious_imgs = "";

        							// Image Table Update

        							$arrayimg_tag   = array();

        							foreach($_FILES['order_image'] as $file_key => $file_val){



        								if($file_val['tmp_name'])

        								{

        									$img_name =  $id_order_Details."_". mt_rand(100001,999999).".jpg";

        									$path = $folder."/".$img_name;

        									$result = $this->upload_img('image',$path,$file_val['tmp_name']);

        									if($result){

        										$precious_imgs = $img_name;

        										$arrayimg_tag = array(

        										 'id_orderdetails' => $id_order_Details,

        										 'image'           => $img_name,

        										);

        									$img_arr[]   = $arrayimg_tag;

        									  $insImageId = $this->$model->insertData($arrayimg_tag,'customer_order_image');

        									}

        								}

        							}

        						}

        					}



		                      $jobOrder=array(

		                                     'id_order'=>$id_order_Details,

		                                     'orderstatus'=>3,

		                                     'assigndate'=>date("Y-m-d H:i:s"),

		                                     'id_vendor'=>$addData['id_karigar'],

		                                     );

		                      $this->$model->insertData($jobOrder,'joborder');

		                  }



		                  if(isset($order_details['id_orderdetails'][$key]))

		                  {

		                        if($order_details['id_orderdetails'][$key]!='' && $order_details['id_orderdetails'][$key]!="undefined")

                		        {

                				    $this->$model->updateData(array('orderstatus'=>3),'id_orderdetails',$order_details['id_orderdetails'][$key],'customerorderdetails'); // For Customer Order Status

                		        }

		                  }

		            }

		        }



		        if($this->db->trans_status()===TRUE)

    			{

    				$this->db->trans_commit();


					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Order',

						'operation'     => 'Add',

						'record'        =>  $insOrder,

						'remark'        => 'Order added successfully'

						);

					$this->log_model->log_detail('insert','',$log_data);

                    // --- Email to Vendor deferred to MD Approval ---

    				$this->session->set_flashdata('chit_alert',array('message'=>'New Order added successfully','class'=>'success','title'=>'Add Order'));

    				$return_data=array('status'=>TRUE,'msg'=>'Order Created successfully..','id_customerorder'=>$insOrder,'order_for');

    			}

    			else

    			{



    			    echo $this->db->last_query();exit;

    				$this->db->trans_rollback();

    				$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Add Order'));

    				$return_data=array('status'=>FALSE,'msg'=>'');

    			}

    			echo json_encode($return_data);



		    }

			break;

			case 'po_entry_save':

			    $responsData=array();

			   // echo "<pre>";print_r($_POST);echo "</pre>";exit;

			    $orderData=$_POST['order'];

			    $orderDetails=$_POST['order_item'];



                $ho              = $this->$model->get_headOffice();

                $fin_year       = $this->$model->get_FinancialYear();

                $dCData          = $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

				$bill_date       = ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);



				$karigar_details=$this->$model->get_karigar_details($orderData['id_karigar']);



				/*1.IF APPROVAL STOCK -  PA

				2.AGANINST ORDER - IF REGISTERED KARIGAR - P , NON REGISTERED PM*/



				/*if(!empty($orderData['purchase_type'])){

    				if($orderData['purchase_type']==1) // Aganist Order

    				{

    				    if($karigar_details['karigar_type']==0)

    				    {

    				        $gst_bill_type=$karigar_details['karigar_type'];

    				        $last_no       = $this->$model->generatePurRefOrderNo(0,$gst_bill_type);

    				        $po_ref_no='PM-'.$last_no;

    				    }

    				    else

    				    {

    				        $last_no       = $this->$model->generatePurRefOrderNo(0,'');

    				        $po_ref_no='P-'.$last_no;

    				    }

    				}

    				else

    				{

    				    $last_no       = $this->$model->generatePurRefOrderNo($orderData['is_suspense_stock'],'');

    				    if($orderData['is_suspense_stock']==0)

        				{

        				    $po_ref_no='P-'.$last_no;

        				}

        				else

        				{

        				    $po_ref_no='PA-'.$last_no;

        				}

    				}

				}else{

				    $last_no       = $this->$model->generatePurRefOrderNo($orderData['is_suspense_stock'],'');

    				    if($orderData['is_suspense_stock']==0)

        				{

        				    $po_ref_no='P-'.$last_no;

        				}

        				else

        				{

        				    $po_ref_no='PA-'.$last_no;

        				}

				}*/

				$is_suspense_stock = (isset($orderData['is_suspense_stock']) ? $orderData['is_suspense_stock'] :0);

				$last_no       = $this->$model->generatePurRefOrderNo($is_suspense_stock,'');

			    if($is_suspense_stock==0)

				{

				    $po_ref_no='P-'.$last_no;

				}

				else

				{

				    $po_ref_no='PA-'.$last_no;

				}





     			$order = array(

     				'po_ref_no'         => $po_ref_no,

     				'fin_year_code'		=> $fin_year['fin_year_code'],

     				'po_grn_id'         => $orderData['id_grn'],

     				'purchase_order_no' => ($orderData['stock_type']==1 ? $orderData['purchase_order_no'] :NULL), //purchase_type=>1-Aganist Order

     				'is_suspense_stock' => $orderData['is_suspense_stock'],

					'is_po_halmarked'   => ($orderData['is_halmerked']),

     				'isratefixed'       => $_POST['is_rate_fixed'],

     				'gst_bill_type'     => $karigar_details['karigar_type'],

     				'purchase_type'     => !empty($orderData['stock_type']) ? $orderData['stock_type'] : $orderData['po_type'],

     				'po_type'           => $orderData['po_type'],

     				'po_karigar_id'     => $orderData['id_karigar'],

     				'id_category'       => $orderData['id_category'],

     				'id_purity'         =>!empty($orderData['id_purity']) ? $orderData['id_purity'] : 0,

     				'ewaybillno'        => ($orderData['ewaybillno']!='' ? $orderData['ewaybillno']:NULL),

     				'po_irnno'          => ($orderData['po_irnno']!='' ? $orderData['po_irnno']:NULL),

     				'po_supplier_ref_no'=> !empty($orderData['po_supplier_ref_no']) ? $orderData['po_supplier_ref_no']:NULL,

     				'po_ref_date'       => !empty($orderData['po_ref_date']) ? date('Y-m-d', strtotime($orderData['po_ref_date'])):NULL,

     				'despatch_through'  => $orderData['despatch_through'],

     				'tot_purchase_amt'  => $orderData['total_cost'],

     				'tot_purchase_wt'   => $orderData['tot_purchase_wt'],

     				'po_date'		    => $bill_date,

    				'created_on'        => date("Y-m-d H:i:s"),

    				'po_othere_charges' => !empty($orderData['other_charges_amount']) ? $orderData['other_charges_amount'] : 0,

    				'po_discount'       => !empty($orderData['discount']) ? $orderData['discount']:0,

    				'created_by'        => $this->session->userdata('uid'),

    				'tcs_percent'       => $orderData['tcs_percent'],

    				'tcs_tax_value'     => $orderData['tcs_tax_value'],

    				'tds_percent'       => $orderData['tds_percent'],

    				'tds_tax_value'     => $orderData['tds_tax_value'],

    				'form_secret'       => $orderData['pur_entry_form_secret'],

					'po_delivery_date'  => $orderData['due_date'],

    			);

    			//echo"<pre>";print_r($orderDetails);exit;

    			$this->db->trans_begin();

    			$insOrder = $this->$model->insertData($order,'ret_purchase_order');

    			if($insOrder)

    			{

    			     //Update Order Status

    			    if($orderData['stock_type']==1)

    			    {

    			        foreach($orderDetails['id_category'] as $okey => $oval){

        			        $PurorderDetails = $this->$model->get_pur_order_det($orderDetails['po_order_no'][$okey]);

        			        if($PurorderDetails['order_pcs'] > 0)

        			        {

        			            $order_details = array('delivered_qty'=>$orderDetails['tot_pcs'][$okey],'delivered_wt'=> $orderDetails['tot_gwt'][$okey],'id_customerorder'=>$orderDetails['po_order_no'][$okey]);

                                $this->$model->updatePurOrderStatus($order_details,'+');

                                $orderdetails = $this->$model->get_cus_order_details($orderDetails['po_order_no'][$okey], $orderDetails['id_product'][$key], $orderDetails['id_design'][$key], $orderDetails['id_sub_design'][$key]);

                        		$updateorderDetail = array('orderstatus'=> 5,'delivered_qty'=> $orderDetails['tot_pcs'][$key]);

                        		$this->$model->updateData($updateorderDetail,'id_orderdetails',$orderdetails['id_orderdetails'],'customerorderdetails');

                                if($PurorderDetails['order_pcs']<=($orderDetails['tot_pcs'][$okey]+$PurorderDetails['delivered_qty']))

                                {

                                    $this->$model->updateData(array('order_status'=> 5),'id_customerorder',$orderDetails['po_order_no'][$okey],'customerorder');

                                }

        			        }

    			        }



    			    }

    			    //Update Order Status





    			    if(!empty($orderDetails))

    			    {

    			        	foreach($orderDetails['id_category'] as $key => $val){



    			        	    $arrayItems=array(

    			        	                      'po_item_po_id'       =>$insOrder,

    			        	                      'po_item_cat_id'      =>$orderDetails['id_category'][$key],

    			        	                      'po_order_no'         =>( $orderDetails['po_order_no'][$key]!='null' ?  $orderDetails['po_order_no'][$key]: NULL),

    			        	                      'po_purchase_mode'    => $orderDetails['po_purchase_mode'][$key],

    			        	                      'po_item_pro_id'      =>($orderDetails['id_product'][$key] !='' && $orderDetails['id_product'][$key]!='null' ? $orderDetails['id_product'][$key]:NULL),

    			        	                      'po_item_des_id'      =>($orderDetails['id_design'][$key]!='' && $orderDetails['id_design'][$key]!='null' ? $orderDetails['id_design'][$key]:NULL),

    			        	                      'po_item_sub_des_id'  =>($orderDetails['id_sub_design'][$key]!='' && $orderDetails['id_sub_design'][$key]!='null' ? $orderDetails['id_sub_design'][$key]:NULL),

    			        	                      'id_purity'           =>($orderDetails['id_purity'][$key]!='' ? $orderDetails['id_purity'][$key]:NULL),

    			        	                      'gross_wt'            =>$orderDetails['tot_gwt'][$key],

    			        	                      'less_wt'             =>($orderDetails['tot_lwt'][$key]!='' ? $orderDetails['tot_lwt'][$key] :0),

    			        	                      'net_wt'              =>$orderDetails['tot_nwt'][$key],

    			        	                      'calculation_based_on'=>$orderDetails['calculation_based_on'][$key],

    			        	                      'item_wastage'        =>($orderDetails['tot_wastage_perc'][$key]!='' ?$orderDetails['tot_wastage_perc'][$key] :NULL),

    			        	                      'no_of_pcs'           =>($orderDetails['tot_pcs'][$key]!='' ? $orderDetails['tot_pcs'][$key] :0),

    			        	                      'is_suspense_stock'   =>$orderDetails['is_suspense_stock'][$key],

    			        	                      'is_halmarked'        =>($orderDetails['is_halmerked'][$key]!='undefined' ? $orderDetails['is_halmerked'][$key]: ''),

    			        	                      'is_halmark_from'     =>($orderDetails['is_halmerked'][$key]==1 ? $orderDetails['is_halmerked'][$key]:NULL),

    			        	                      'is_rate_fixed'       =>$orderDetails['is_rate_fixed'][$key],

    			        	                      'fix_rate_per_grm'    =>($orderDetails['rate_per_gram'][$key]!='' ? $orderDetails['rate_per_gram'][$key] :0),

    			        	                      'mc_value'            =>($orderDetails['mc_value'][$key]!='' ? $orderDetails['mc_value'][$key] :0),

    			        	                      'purchase_touch'      =>($orderDetails['purchase_touch'][$key]!='' ? $orderDetails['purchase_touch'][$key] :0),

    			        	                      'mc_type'             =>$orderDetails['mc_type'][$key],

    			        	                      'item_cost'           =>($orderDetails['item_cost'][$key]!='' ?$orderDetails['item_cost'][$key] :0),

    			        	                      'item_pure_wt'        =>($orderDetails['tot_purewt'][$key]!='' ? $orderDetails['tot_purewt'][$key]:0),

    			        	                      'uom'                 =>($orderDetails['gwt_uom'][$key]!='' ? $orderDetails['gwt_uom'][$key]:0),

    			        	                      'cal_type'            =>($orderDetails['cal_type'][$key]!='' ? $orderDetails['cal_type'][$key]:0),

    			        	                      'total_tax'           =>($orderDetails['total_tax_rate'][$key]!='' && $orderDetails['total_tax_rate'][$key]!=null ? $orderDetails['total_tax_rate'][$key]:0),

    			        	                      'total_cgst'          =>($orderDetails['cgst_cost'][$key]!='' ? $orderDetails['cgst_cost'][$key]:0),

    			        	                      'total_sgst'          =>($orderDetails['sgst_cost'][$key]!='' ? $orderDetails['sgst_cost'][$key]:0),

    			        	                      'total_igst'          =>($orderDetails['igst_cost'][$key]!='' ? $orderDetails['igst_cost'][$key]:0),

    			        	                      'tax_percentage'      =>($orderDetails['tax_percentage'][$key]!='' ? $orderDetails['tax_percentage'][$key]:0),

    			        	                      'tax_group'           =>($orderDetails['tax_group'][$key]!='' ? $orderDetails['tax_group'][$key]:0),

    			        	                      'tax_type'            =>($orderDetails['tax_type'][$key]!='' ? $orderDetails['tax_type'][$key]:0),

    			        	                      'pure_wt_calc_type'   =>($orderDetails['pure_wt_calc_type'][$key]!='' ? $orderDetails['pure_wt_calc_type'][$key]:0),

    			        	                      'remark'              =>($orderDetails['item_remarks'][$key]!='' && $orderDetails['item_remarks'][$key]!= 'undefined' ? $orderDetails['item_remarks'][$key]:NULL),

												  'issue_met_id'        =>($orderDetails['issue_met_id'][$key]!='' ? $orderDetails['issue_met_id'][$key]:NULL),

												  'quality_id'          =>$this->isEmptySetDefault($orderDetails['quality_id'][$key],NULL),

												  'rate_calc_type'      =>$orderDetails['rate_calc_type'][$key],

					  'purity_type'         =>(isset($orderDetails['purity_type'][$key]) && $orderDetails['purity_type'][$key]!='' ? $orderDetails['purity_type'][$key]:999),

                              // PUR-2607021534: GST Bill Rate Basis and Entry Mode
                              'gst_bill_rate_basis'  => (isset($orderDetails['gst_bill_rate_basis'][$key]) && $orderDetails['gst_bill_rate_basis'][$key]!=='' ? (int)$orderDetails['gst_bill_rate_basis'][$key] : 0),
                              'purchase_entry_mode'  => (isset($orderDetails['purchase_entry_mode'][$key]) && $orderDetails['purchase_entry_mode'][$key]!=='' ? (int)$orderDetails['purchase_entry_mode'][$key] : 1),
                             );

    			        	    //echo "<pre>";print_r($arrayItems);exit;

    			        	    $itemStatus=$this->$model->insertData($arrayItems,'ret_purchase_order_items');



    			        	    if($itemStatus)

    			        	    {

    			        	        //Supplier Ledger Log

    			        	        if($orderDetails['tot_purewt'][$key]!=''){

    			        	            $ledger_data = array(

    			        	                                'trans_date'        =>$bill_date,

    			        	                                'id_karigar'        =>$orderData['id_karigar'],

    			        	                                'cat_id'            =>$orderDetails['id_category'][$key],

    			        	                                'trans_type'        =>2,//Debit

    			        	                                'trans_rec_type'    =>1,//Weight

    			        	                                'trans_pcs'         =>($orderDetails['tot_pcs'][$key]!='' ? $orderDetails['tot_pcs'][$key] :0),

    			        	                                'trans_grswt'       =>$orderDetails['tot_gwt'][$key],

    			        	                                'trans_netwt'       =>$orderDetails['tot_nwt'][$key],

    			        	                                'trans_purewt'      =>$orderDetails['tot_purewt'][$key],

    			        	                                'trans_screen_id'   =>1,//Supplier Bill entry

    			        	                                'ref_id'            =>$itemStatus,

    			        	                                'remarks'           =>'From Suuplier Bill Entry',

    			        	                                'created_by'        =>$this->session->userdata('uid'),

    			        	                                'created_on'        =>date("Y-m-d H:i:s"),

    			        	                                );

    			        	            $this->$model->insertData($ledger_data,'ret_supplier_ledger_log');

    			        	        }

    			        	        //Supplier Ledger Log



    			        	        $charge_details = json_decode($orderDetails['other_chrg_details'][$key],true);

    			        	        if(sizeof($charge_details)>0)

    			        	        {

                                    	foreach($charge_details as $charges)

                                    	{

                                        	$charge_data = array(

                                            	'pur_othr_po_id'            => $insOrder,

                                            	'pur_po_item_id'            => $itemStatus,

                                            	'pur_othr_charge_id'        => $charges['charge_id'],

                                            	'calc_type'                 => $charges['calc_type'],

                                            	'pur_othr_charge_value'     => $charges['charge_value'],

                                            	'tax_percentage'            => $charges['tax_percentage'],

                                            	'item_total_tax'            => $charges['charge_tax_value'],

                                            	'total_charge_value'        => $charges['total_charge_value'],

                                        	);

                                        	//print_r($charge_data);exit;

                                        	$chrg_status = $this->$model->insertData($charge_data, 'ret_purchase_other_charges');

                                        	if($chrg_status)

                                        	{

                                        	    $ledger_data = array(

    			        	                                'trans_date'        =>$bill_date,

    			        	                                'id_karigar'        =>$orderData['id_karigar'],

    			        	                                'id_charge'         =>$charges['charge_id'],

    			        	                                'trans_type'        =>2,//Debit

    			        	                                'trans_rec_type'    =>2,//Amount

    			        	                                'trans_amount'      =>number_format($total_charge,2,'.',''),

    			        	                                'trans_screen_id'   =>1,//Supplier Bill entry

    			        	                                'ref_id'            =>$itemStatus,

    			        	                                'remarks'           =>'From Suuplier Bill Entry Charges',

    			        	                                'created_by'        =>$this->session->userdata('uid'),

    			        	                                'created_on'        =>date("Y-m-d H:i:s"),

    			        	                                );

    			        	                        $this->$model->insertData($ledger_data,'ret_supplier_ledger_log');

                                        	}

                                    	}

                    			    }

			        	        	if($orderDetails['stone_details'][$key])

                                	{

                                    	$stone_details=json_decode($orderDetails['stone_details'][$key],true);

                                    	foreach($stone_details as $stone)

                                    	{

                                        	$stone_data=array(

                                        	'po_item_id'    =>$itemStatus,

                                        	'po_stone_id'   =>$stone['stone_id'],

                                        	'po_stone_pcs'  =>$stone['stone_pcs'],

                                        	'po_stone_wt'   =>$stone['stone_wt'],

                                        	'po_stone_uom'  =>$stone['stone_uom_id'],

                                        	'po_stone_rate' =>$stone['stone_rate'],

                                        	'po_stone_amount'=>$stone['stone_price'],

                                        	'is_apply_in_lwt'=> $stone['show_in_lwt'],

                                        	'po_stone_calc_based_on'=> $stone['stone_cal_type'],

                                        	'po_quality_id'=>($stone['stone_quality_id']!='' ?  $stone['stone_quality_id'] : NULL),

											'issue_met_id' => ($stone['issue_met_id']!='' ?  $stone['issue_met_id'] : NULL)

                                        	);

                                        	$stoneInsert = $this->$model->insertData($stone_data,'ret_po_stone_items');

                                        	//echo $this->db->last_query();exit;

                                    	}

                                	}



                                	if($orderDetails['other_metal_details'][$key])

                                	{

                                    	$othermetal_details=json_decode($orderDetails['other_metal_details'][$key],true);

                                    	foreach($othermetal_details as $othermetal)

                                    	{

                                        	$other_metal_data = array(

                                    	    'po_item_id'                    =>$itemStatus,

                                        	'po_item_metal'             =>$othermetal['id_metal'],

                                        	'po_other_item_gross_weight'=>$othermetal['gwt'],

                                        	'po_other_item_purity'   =>$othermetal['id_purity'],

                                        	'po_other_item_rate'  =>$othermetal['rate_per_gram'],

                                        	'po_other_item_pcs' =>$othermetal['pcs'],

                                        	'po_other_item_amount'=>$othermetal['amount'],

                                        	);

                                        	$othermetalInsert = $this->$model->insertData($other_metal_data,'ret_po_other_item');

                                        	//echo $this->db->last_query();exit;

                                    	}

                                	}

    			        	    }

    			        	}

    			    }

    			    if($this->db->trans_status()===TRUE)

        			{

        			    $log_data = array(

                        	'id_log'        => $this->session->userdata('id_log'),

                        	'event_date'	=> date("Y-m-d H:i:s"),

                        	'module'      	=> 'SUPPLIER BILL ENTRY',

                        	'operation'   	=> 'Add',

                        	'record'        =>  $insOrder,

                        	'remark'       	=> 'Supplier Entry Added Successfully..'

                        );

                        $this->log_model->log_detail('insert','',$log_data);




        				/* CPO-13: settle any Common PO advance against this bill before committing, so
        				   the adjustment shares the bill's transaction — a bill that rolls back must not
        				   leave the advance marked as consumed. Returns 0 and does nothing when the bill
        				   is not against a Common PO. */
        				/* CPO-12: an unpermitted rate override is rejected before anything is adjusted,
        				   so a refused bill leaves no advance consumed. */
        				$cpo_rate_block = $this->_block_common_po_rate_override($insOrder);
        				if ($cpo_rate_block !== NULL) {
        				    $this->db->trans_rollback();
        				    echo json_encode(array('status'=>FALSE,'msg'=>$cpo_rate_block,'message'=>$cpo_rate_block));
        				    return;
        				}
        				$this->_normalise_common_po_bill($insOrder);
        				$this->_record_common_po_receipt($insOrder);
        				$this->_adjust_common_po_advance($insOrder);

        				$this->db->trans_commit();

        				$this->session->set_flashdata('chit_alert',array('message'=>'Purchase Entry added successfully','class'=>'success','title'=>'Purchase Entry'));

        				$responsData=array('status'=>TRUE,'msg'=>'Purchase Entry added successfully..','Insid'=>$insOrder);

        			}

        			else

        			{

        				$this->db->trans_rollback();

        				$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Add Order'));

        				$responsData=array('status'=>FALSE,'msg'=>'');

        			}

        			echo json_encode($responsData);

    			}



			break;



			case 'po_entry_update':

            	$updateorderData = $_POST['order'];



            	$updateorderDetails = $_POST['order_item'];



            	$id = $updateorderData['po_id'];



            	//echo"<pre>";print_r($_POST);exit;

				// === SUPPLIER CHANGE GUARD (Task: 9ed390a1a031) ===
				$current_po = $this->db->query(
					"SELECT po_karigar_id, po_grn_id, purchase_type, po_date, po_ref_no FROM ret_purchase_order WHERE po_id = " . (int)$id
				)->row_array();
				$supplier_changed = ($current_po && $current_po['po_karigar_id'] != $updateorderData['id_karigar']);

				if($supplier_changed)
				{
					// Block: Current month only — past month entries cannot be changed
					if(!empty($current_po['po_date']) && date('Y-m', strtotime($current_po['po_date'])) !== date('Y-m'))
					{
						$responsData = array(
							'status'  => FALSE,
							'msg' => 'Cannot change supplier for past month entries. Only current month bills can be modified.'
						);
						echo json_encode($responsData);
						return;
					}

					// Block: If GRN exists upstream, supplier must be changed at GRN level
					if(!empty($current_po['po_grn_id']))
					{
						$responsData = array(
							'status'  => FALSE,
							'msg' => 'Cannot change supplier from Bill Entry. Please change at GRN level — it will cascade to all linked entries.'
						);
						echo json_encode($responsData);
						return;
					}

					// Block: Financial chain / upstream PO checks (for direct bill entries without GRN)
					$check = $this->$model->check_supplier_change_allowed($id);
					if(!$check['allowed'])
					{
						$responsData = array(
							'status'  => FALSE,
							'msg' => 'Cannot change supplier. ' . implode(', ', $check['reasons'])
						);
						echo json_encode($responsData);
						return;
					}
				}
				// === END GUARD ===

            	$pur_order = array(

            		'po_grn_id'         => $updateorderData['id_grn'],

            		'is_suspense_stock' => ($updateorderData['stock_type']==1 ? 0 :$updateorderData['is_suspense_stock']),

            		'isratefixed'       => $_POST['is_rate_fixed'],

            		//'gst_bill_type'     => $karigar_details['karigar_type'],

            		'purchase_type'     => !empty($updateorderData['stock_type']) ? $updateorderData['stock_type'] : $updateorderData['po_type'],

					'purchase_order_no' => ($updateorderData['stock_type']==1 ? $updateorderData['purchase_order_no'] :NULL), //purchase_type=>1-Aganist Order

            		'po_type'           => $updateorderData['po_type'],

            		'po_karigar_id'     => $updateorderData['id_karigar'],

            		'id_category'       => $updateorderData['id_category'],

            		'id_purity'         =>!empty($updateorderData['id_purity']) ? $updateorderData['id_purity'] : 0,

            		'ewaybillno'        => ($updateorderData['ewaybillno']!='' ? $updateorderData['ewaybillno']:NULL),

            		'po_irnno'          => ($updateorderData['po_irnno']!='' ? $updateorderData['po_irnno']:NULL),

            		'po_supplier_ref_no'=> !empty($updateorderData['po_supplier_ref_no']) ? $updateorderData['po_supplier_ref_no']:NULL,

            		'po_ref_date'       => !empty($updateorderData['po_ref_date']) ? date('Y-m-d', strtotime($updateorderData['po_ref_date'])):NULL,

            		'despatch_through'  => $updateorderData['despatch_through'],

            		'tot_purchase_amt'  => $updateorderData['total_cost'],

            		'tot_purchase_wt'   => $updateorderData['tot_purchase_wt'],

            		// 'po_date'		    => $bill_date,

            		'updated_on'        => date("Y-m-d H:i:s"),

            		'po_othere_charges' => !empty($updateorderData['other_charges_amount']) ? $updateorderData['other_charges_amount'] : 0,

            		'po_discount'       => !empty($updateorderData['discount']) ? $updateorderData['discount']:0,

            		'updated_by'        => $this->session->userdata('uid'),

            		'tcs_percent'       => $updateorderData['tcs_percent'],

            		'tcs_tax_value'     => $updateorderData['tcs_tax_value'],

            		'tds_percent'       => $updateorderData['tds_percent'],

            		'tds_tax_value'     => $updateorderData['tds_tax_value']

            	);



            	$this->db->trans_begin();

                /* CPO-16: an edit can change the bill value, so the existing advance
                   adjustment is undone first at its ORIGINAL figure and re-applied
                   afterwards against the new one. Adjusting the difference in place
                   would drift: the stored adjustment and the bill it refers to would
                   no longer describe the same transaction. Reversed here, before the
                   header changes, so the reversal still sees the old amounts. */
                $this->_reverse_common_po_bill($id, 'Supplier bill edited');




            	$update_status = $this->$model->updateData($pur_order,'po_id',$id, 'ret_purchase_order');





            	if($update_status)

            	{



            		foreach($updateorderDetails['id_category'] as $key => $val)
            		{
            			$arrayItems=array(
            				'po_item_cat_id' =>$updateorderDetails['id_category'][$key],
            				//'po_order_no'         => $updateorderDetails['po_order_no'][$key],
            				'po_purchase_mode'    => $updateorderDetails['po_purchase_mode'][$key],
            				'po_item_pro_id'      =>($updateorderDetails['id_product'][$key] !='' && $updateorderDetails['id_product'][$key]!='null' ? $updateorderDetails['id_product'][$key]:NULL),
            				'po_item_des_id'      =>($updateorderDetails['id_design'][$key]!='' && $updateorderDetails['id_design'][$key]!='null' ? $updateorderDetails['id_design'][$key]:NULL),
            				'po_item_sub_des_id'  =>($updateorderDetails['id_sub_design'][$key]!='' && $updateorderDetails['id_sub_design'][$key]!='null' ? $updateorderDetails['id_sub_design'][$key]:NULL),
            				'id_purity'           =>($updateorderDetails['id_purity'][$key]!='' ? $updateorderDetails['id_purity'][$key]:NULL),
            				'gross_wt'            =>$updateorderDetails['tot_gwt'][$key],
            				'less_wt'             =>($updateorderDetails['tot_lwt'][$key]!='' ? $updateorderDetails['tot_lwt'][$key] :0),
            				'net_wt'              =>$updateorderDetails['tot_nwt'][$key],
            				'item_wastage'        =>($updateorderDetails['tot_wastage_perc'][$key]!='' ?$updateorderDetails['tot_wastage_perc'][$key] :NULL),
            				'no_of_pcs'           =>($updateorderDetails['tot_pcs'][$key]!='' ? $updateorderDetails['tot_pcs'][$key] :0),
            				'is_suspense_stock'   =>$updateorderDetails['is_suspense_stock'][$key],
            				'is_halmarked'        =>isset($updateorderDetails['is_halmerked'][$key]) && $updateorderDetails['is_halmerked'][$key] != 'undefined' ? $updateorderDetails['is_halmerked'][$key] :null,
            				'is_halmark_from'     =>($updateorderDetails['is_halmerked'][$key]==1 ? $updateorderDetails['is_halmerked'][$key]:NULL),
            				'is_rate_fixed'       =>$updateorderDetails['is_rate_fixed'][$key],
            				'fix_rate_per_grm'    =>($updateorderDetails['rate_per_gram'][$key]!='' ? $updateorderDetails['rate_per_gram'][$key] :NULL),
            				'mc_value'            =>($updateorderDetails['mc_value'][$key]!='' ? $updateorderDetails['mc_value'][$key] :NULL),
            				'purchase_touch'      =>($updateorderDetails['purchase_touch'][$key]!='' ? $updateorderDetails['purchase_touch'][$key] :NULL),
            				'mc_type'             =>$updateorderDetails['mc_type'][$key],
            				'item_cost'           =>($updateorderDetails['item_cost'][$key]!='' ?$updateorderDetails['item_cost'][$key] :0),
            				'item_pure_wt'        =>($updateorderDetails['tot_purewt'][$key]!='' ? $updateorderDetails['tot_purewt'][$key]:0),
            				'uom'                 => ($updateorderDetails['gwt_uom'][$key]!='' ? $updateorderDetails['gwt_uom'][$key]:0),
            				'cal_type'            => ($updateorderDetails['cal_type'][$key]!='' ? $updateorderDetails['cal_type'][$key]:0),
            				'remark'              => ($updateorderDetails['remark'][$key]!='' ? $updateorderDetails['remark'][$key]:NULL),
							'quality_id'          =>($updateorderDetails['quality_id'][$key]!='' ? $updateorderDetails['quality_id'][$key]:NULL),
							'rate_calc_type'      => ($updateorderDetails['rate_calc_type'][$key]!='' ? $updateorderDetails['rate_calc_type'][$key]:1),
							'total_tax'           =>($updateorderDetails['total_tax_rate'][$key]!='' && $updateorderDetails['total_tax_rate'][$key]!=null ? $updateorderDetails['total_tax_rate'][$key]:0),
							'total_cgst'          =>($updateorderDetails['cgst_cost'][$key]!='' ? $updateorderDetails['cgst_cost'][$key]:0),
							'total_sgst'          =>($updateorderDetails['sgst_cost'][$key]!='' ? $updateorderDetails['sgst_cost'][$key]:0),
							'total_igst'          =>($updateorderDetails['igst_cost'][$key]!='' ? $updateorderDetails['igst_cost'][$key]:0),
							'tax_percentage'      =>($updateorderDetails['tax_percentage'][$key]!='' ? $updateorderDetails['tax_percentage'][$key]:0),
							'tax_group'           =>($updateorderDetails['tax_group'][$key]!='' ? $updateorderDetails['tax_group'][$key]:0),
							'tax_type'            =>($updateorderDetails['tax_type'][$key]!='' ? $updateorderDetails['tax_type'][$key]:0),
							'purity_type'         =>(isset($updateorderDetails['purity_type'][$key]) && $updateorderDetails['purity_type'][$key]!='' ? $updateorderDetails['purity_type'][$key]:999),

						// PUR-2607021534: GST Bill Rate Basis and Entry Mode
						'gst_bill_rate_basis' => (isset($updateorderDetails['gst_bill_rate_basis'][$key]) && $updateorderDetails['gst_bill_rate_basis'][$key]!=='' ? (int)$updateorderDetails['gst_bill_rate_basis'][$key] : 0),
						'purchase_entry_mode' => (isset($updateorderDetails['purchase_entry_mode'][$key]) && $updateorderDetails['purchase_entry_mode'][$key]!=='' ? (int)$updateorderDetails['purchase_entry_mode'][$key] : 1),
            			);



            			// echo"<pre>";print_r($arrayItems);exit;





            			if(!empty($updateorderDetails['po_item_id'][$key]))

            			{



            				$this->db->trans_begin();



            				$this->$model->updateData($arrayItems,'po_item_id',$updateorderDetails['po_item_id'][$key],'ret_purchase_order_items');

            				//print_r($this->db->last_query());exit;



            				if($updateorderDetails['stone_details'][$key])

            				{

            					$stone_details=json_decode($updateorderDetails['stone_details'][$key],true);



            					$po_stone_delete_id = $this->$model->deleteData("po_item_id",$updateorderDetails['po_item_id'][$key],'ret_po_stone_items');



            					foreach($stone_details as $stone)

            					{

            						$stone_data=array(

            						'po_item_id'    =>$updateorderDetails['po_item_id'][$key],

            						'po_stone_id'   =>$stone['stone_id'],

            						'po_stone_pcs'  =>$stone['stone_pcs'],

            						'po_stone_wt'   =>$stone['stone_wt'],

            						'po_stone_uom'  =>$stone['stone_uom_id'],

            						'po_stone_rate' =>$stone['stone_rate'],

            						'po_stone_amount'=>$stone['stone_price'],

            						'is_apply_in_lwt'=> $stone['show_in_lwt'],

            						'po_quality_id'=> $stone['stone_quality_id']

            						);

            						$stoneInsert = $this->$model->insertData($stone_data,'ret_po_stone_items');

            						//echo $this->db->last_query();exit;

            					}

            				}



            				if($orderDetails['other_metal_details'][$key])

            				{

            					$othermetal_details=json_decode($orderDetails['other_metal_details'][$key],true);



            					$po_othermetal_id = $this->$model->deleteData("po_item_id",$updateorderDetails['po_item_id'][$key],'ret_po_other_item');



            					foreach($othermetal_details as $othermetal)

            					{

            						$other_metal_data = array(

            						'po_item_id'                    =>$itemStatus,

            						'po_item_metal'             =>$othermetal['id_metal'],

            						'po_other_item_gross_weight'=>$othermetal['gwt'],

            						'po_other_item_purity'   =>$othermetal['id_purity'],

            						'po_other_item_rate'  =>$othermetal['rate_per_gram'],

            						'po_other_item_pcs' =>$othermetal['pcs'],

            						'po_other_item_amount'=>$othermetal['amount'],

            						);

            						$othermetalInsert = $this->$model->insertData($other_metal_data,'ret_po_other_item');

            						//echo $this->db->last_query();exit;

            					}

            				}





            			}

            			else

            			{

            				if($id > 0)

            				{

            					$arrayItems['po_item_po_id'] = $id;



            					$this->db->trans_begin();



            					$insPurorderDet = $this->$model->insertData($arrayItems,'ret_purchase_order_items');

            				}



            				$stone_details=json_decode($updateorderDetails['stone_details'][$key],true);



            				if(sizeof($stone_details)>0)

            				{

            					foreach($stone_details as $stone)

            					{

            						$stone_data=array(

            						'po_item_id'    =>$insPurorderDet,

            						'po_stone_id'   =>$stone['stone_id'],

            						'po_stone_pcs'  =>$stone['stone_pcs'],

            						'po_stone_wt'   =>$stone['stone_wt'],

            						'po_stone_uom'  =>$stone['stone_uom_id'],

            						'po_stone_rate' =>$stone['stone_rate'],

            						'po_stone_amount'=>$stone['stone_price'],

            						'is_apply_in_lwt'=> $stone['show_in_lwt']

            						);

            						$stoneInsert = $this->$model->insertData($stone_data,'ret_po_stone_items');

            						//echo $this->db->last_query();exit;

            					}



            				}



            				$othermetal_details=json_decode($orderDetails['other_metal_details'][$key],true);



            				if(sizeof($othermetal_details)>0)

            				{

            					foreach($othermetal_details as $othermetal)

            					{

            						$other_metal_data = array(

            						'po_item_id'                    =>$insPurorderDet,

            						'po_item_metal'             =>$othermetal['id_metal'],

            						'po_other_item_gross_weight'=>$othermetal['gwt'],

            						'po_other_item_purity'   =>$othermetal['id_purity'],

            						'po_other_item_rate'  =>$othermetal['rate_per_gram'],

            						'po_other_item_pcs' =>$othermetal['pcs'],

            						'po_other_item_amount'=>$othermetal['amount'],

            						);

            						$othermetalInsert = $this->$model->insertData($other_metal_data,'ret_po_other_item');

            						//echo $this->db->last_query();exit;

            					}



            				}





            			}



            		}



            	}



            	if($this->db->trans_status()===TRUE)

            	{




             		/* CPO-16: re-apply against the edited bill. Same routines the save path
             		   uses, so an edited bill reconciles exactly as a newly raised one. */
             		// CPO-12: same check on edit — an edit is another chance to change the rate.
             		$cpo_rate_block = $this->_block_common_po_rate_override($id);
             		if ($cpo_rate_block !== NULL) {
             		    $this->db->trans_rollback();
             		    echo json_encode(array('status'=>FALSE,'msg'=>$cpo_rate_block,'message'=>$cpo_rate_block));
             		    return;
             		}
             		$this->_normalise_common_po_bill($id);
             		$this->_record_common_po_receipt($id);
             		$this->_adjust_common_po_advance($id);

            		$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Supplier Entry',

						'operation'     => 'Update',

						'record'        =>  $update_status,

						'remark'        => 'Supplier Entry Updated successfully'

						);

			        $this->log_model->log_detail('insert','',$log_data);

					// === CASCADE SUPPLIER CHANGE (Task: 9ed390a1a031) ===
					if($supplier_changed)
					{
						$cascade_result = $this->$model->cascade_supplier_change(
							$id,
							$updateorderData['id_karigar'],
							$current_po['po_karigar_id'],
							$current_po['po_grn_id'],
							$this->session->userdata('uid'),
							'Bill Entry'
						);

						if(!$cascade_result['success'])
						{
							// Rollback PO update — race condition detected
							$this->$model->updateData(
								array('po_karigar_id' => $current_po['po_karigar_id']),
								'po_id', $id, 'ret_purchase_order'
							);
							$responsData = array('status' => FALSE, 'msg' => $cascade_result['message']);
							echo json_encode($responsData);
							return;
						}

						// Audit log for supplier change — include names + bill ref
						$old_supplier = $this->db->query(
							"SELECT CONCAT(firstname,' ',lastname) as name FROM ret_karigar WHERE id_karigar = " . (int)$current_po['po_karigar_id']
						)->row();
						$new_supplier = $this->db->query(
							"SELECT CONCAT(firstname,' ',lastname) as name FROM ret_karigar WHERE id_karigar = " . (int)$updateorderData['id_karigar']
						)->row();
						$old_name = $old_supplier ? trim($old_supplier->name) : 'ID:' . $current_po['po_karigar_id'];
						$new_name = $new_supplier ? trim($new_supplier->name) : 'ID:' . $updateorderData['id_karigar'];
						$bill_ref = !empty($current_po['po_ref_no']) ? $current_po['po_ref_no'] : 'PO#' . $id;

						$this->log_model->log_detail('insert', '', array(
							'id_log'     => $this->session->userdata('id_log'),
							'event_date' => date("Y-m-d H:i:s"),
							'module'     => 'Supplier Bill Entry',
							'operation'  => 'Supplier Change',
							'record'     => $id,
							'remark'     => 'Supplier changed from ' . $old_name . ' (ID:' . $current_po['po_karigar_id'] . ')'
							              . ' to ' . $new_name . ' (ID:' . $updateorderData['id_karigar'] . ')'
							              . ' | ' . $bill_ref
							              . ' | Cascaded to GRN, Lots, Metal Issue, Ledger'
						));
					}
					// === END CASCADE ===


            		$this->session->set_flashdata('chit_alert',array('message'=>'Purchase Entry updated successfully','class'=>'success','title'=>'Purchase Entry'));

            		$responsData=array('status'=>TRUE,'msg'=>'Purchase Entry Updated successfully..','Insid'=>$id);



            	}

            	else



            	{



            		//echo $this->db->last_query();exit;



            		$this->db->trans_rollback();



            		$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Purchase Entry'));



            		$responsData=array('status'=>FALSE,'msg'=>'');



            	}



            	echo json_encode($responsData);





            break;



			case 'purchase_order':

			        $list=$this->$model->get_purchase_order_Details($_POST);

				  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/purchase/pur_order');

			        $data = array(

			        					'list'  => $list,

										'access'=> $access

			        				);

					echo json_encode($data);

			break;



			case 'purchase_entry':

			        $from_date	= $this->input->post('from_date');

			        $to_date	= $this->input->post('to_date');

					$profile=$this->$model->get_profile_settings($this->session->userdata('profile'));

			        $list=$this->$model->get_purchase_entry_details($from_date,$to_date);

				  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/purchase/list');

			        $data = array(

			        					'list'  => $list,

										'profile' =>$profile,

										'access'=> $access

			        				);

					echo json_encode($data);

			break;



			case 'active_grns':

		            $list = $this->$model->getActiveGRNsDetails($_POST);

					echo json_encode($list);

			break;



			case 'job_receipt':

				$data['po_item']=$this->$model->getPurchaseOrderDet($id);

				$data['po_item_details']=$this->$model->get_purchase_order_item_det($id);

				$data['comp_details']=$this->admin_settings_model->getCompanyDetails("");

				// echo "<pre>";print_r($data);exit;

				$html = $this->load->view(self::VIEW_FOLDER.'job_receipt', $data,true);

				echo $html;exit;



			break;



			case 'grncatdetailsbygrnid':

			    $catitems = $this->$model->getGRNsCatDetailsbyGRNId($_POST);

				echo json_encode($catitems);

				break;



			case 'ajax':

					$list=$this->$model->getStockAgeDetails($_POST);

				  	$access = $this->admin_settings_model->get_access('admin_ret_reports/stock_age/list');

			        $data = array(

			        					'list'  => $list,

										'access'=> $access

			        				);

					echo json_encode($data);

				break;

		}



    }



    function approvalstock($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;

		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'approvalstock/purchase_entry_list';

					$data['access'] = $this->$SETT_MOD->get_access('admin_ret_purchase/approvalstock/list');

        			$this->load->view('layout/template', $data);

			break;



			case 'pur_order':

					$data['main_content'] = self::VIEW_FOLDER.'pur_order_list';

        			$this->load->view('layout/template', $data);

			break;



			case 'add':

					$data['main_content'] = self::VIEW_FOLDER.'order_form';

        			$this->load->view('layout/template', $data);

			break;



			case 'ajax_purchase_list':

			        $list=$this->$model->get_approval_purchase_entry_details();

				  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/approvalstock/list');

			        $data = array(

			        					'list'  => $list,

										'access'=> $access

			        				);

					echo json_encode($data);

			break;

		}



    }





   function send_karigar_sms()

    {



        $responseData=array();

        $model              =	self::RET_PUR_ORDER_MODEL;

        $set_model          =   "admin_settings_model";

        $id_customer_order  =   $_POST['id_customer_order'];

        $mobile             =   $_POST['mobile'];



        $service = $this->$set_model->get_service_by_code('KAR_ALOC');



        $orders=$this->$model->get_karigar_order_details($id_customer_order);







       /* $data['order']['pur_no']=$orders[0]['pur_no'];

        $data['order']['emp_name']=$orders[0]['emp_name'];

        $data['order']['order_date']=$orders[0]['order_date'];

        $data['order']['smith_due_date']=$orders[0]['smith_due_date'];

        $data['order_details']=$this->$model->get_karigar_order_products($id_customer_order);

        $data['orders']=$orders;

        //echo "<pre>";print_r($orders);exit;



        $data['karigar']=$this->$model->get_karigar_details($orders[0]['id_vendor']);

        $data['description']=$this->$model->get_order_description();*/





        $data['order_items']=$orders;

        $data['order']['pur_no']=$orders[0]['pur_no'];

        $data['order']['emp_name']=$orders[0]['emp_name'];

        $data['order']['order_date']=$orders[0]['order_date'];

        $data['order']['order_for']=$orders[0]['order_for'];

        $data['order']['smith_due_date']=$orders[0]['smith_due_date'];



        $data['comp_details'] = $this->$set_model->get_company(1);

        $data['order_details']=$this->$model->get_karigar_order_products($id_customer_order);

        $data['karigar']=$this->$model->get_karigar_details($orders[0]['id_karigar']);

        $data['description']=$this->$model->get_order_description();





		$data['comp_details'] = $this->$set_model->get_company(1);

        $this->load->helper(array('dompdf', 'file'));

        $dompdf = new DOMPDF();

        $html = $this->load->view('ret_purchase/vendor_ack', $data,true);



        $dompdf->load_html($html);

        $dompdf->set_paper('A4', "portriat" );

        $dompdf->render();

        $file = $dompdf->output();





		$folderPath='assets/vendor_ack/'.$data['order']['pur_no'];

		$file_name =$folderPath.'/'.time().'_'.$data['order']['pur_no'].'.pdf';



		$attachementUrl=$file_name;



		if (!is_dir($folderPath)) {

    	mkdir($folderPath, 0777, TRUE);

    	}

		file_put_contents($file_name, $file);





	    //print_r($service);exit;



	    /*if($service['serv_whatsapp'] == 1)

		{*/

			if($mobile!='')

			{

			    $sms_data=$this->admin_usersms_model->Get_service_code_sms('KAR_ALOC',$id_customer_order,$attachementUrl);

			    $message='New Order Received From '.$data['comp_details']['company_name'].'.For More Details Please Find The Below Attachement.';

			    $status=$this->sms_model->sendSMS_MSG91($mobile,$message,'',$dlt_te_id,'');

			    if($attachementUrl!='')

			    {

			        $this->sms_model->sendSMS_MSG91($mobile,$message,'',$dlt_te_id,$this->config->item('base_url').'/'.$attachementUrl,$data['order']['pur_no'].'.pdf');



			    }

			    //$whatsapp=$this->admin_usersms_model->send_whatsApp_message($mobile,"New Order From -".$data['comp_details']['company_name'],$this->config->item('base_url').'/'.$attachementUrl,$data['order']['pur_no'].'.pdf');

			    if($status)

			    {

			        $responseData=array('status'=>true,'message'=>'Order Details Sent Successfully');

			    }

			}

	//	}



		if($service['serv_email'] == 1)

		{

			if($orders[0]['email']!='')

			{

			    $sms_data=$this->admin_usersms_model->Get_service_code_sms('KAR_ALOC',$id_customer_order,$attachement_url);



			    $send_mail_from =$this->$model->get_ret_settings('pur_order_email');



			    if($send_mail_from!='')

			    {

			        $send_mail_to =$orders[0]['email'];



                    $email_subject="New Order -".$data['comp_details']['company_name'];



                    $this->company = $this->admin_settings_model->get_company(1);



                    $email_model = "email_model";





                    $message = $this->load->view('ret_purchase/vendor_ack_email',$orders, true);



                    $sendEmail = $this->$model->send_email($send_mail_from,$send_mail_to,$email_subject,$message,'',$send_mail_from,$attachementUrl);



                    if($sendEmail)

    			    {

    			        $responseData=array('status'=>true,'message'=>'Order Details Sent Successfully');

    			    }

			    }







			}

		}



		echo json_encode($responseData);

    }



    function get_karigar_acknowladgement($id_customer_order)

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $set_model = "admin_settings_model";

        /* CPO-06: a Common PO uses a bullion item table, but everything else — the
           letterhead, the DOMPDF render and the PDF stream — follows the existing
           vendor acknowledgement exactly, so both print as the same document type.
           The standard template derives weight as weight_range.value x tot_items,
           which is meaningless for bullion: a Common PO has no weight range and
           stores actual weights, so it would print blank weights and a zero total. */
        if ($this->$model->isCommonPo($id_customer_order)) {

            $cpo_items = $this->$model->getCommonPoPrintDetails($id_customer_order);

            if (empty($cpo_items)) {
                show_error('No items found for this Purchase Order.', 404);
                return;
            }

            $first = $cpo_items[0];

            $data = array();
            $data['items']        = $cpo_items;
            $data['order']        = array(
                'pur_no'           => $first['pur_no'],
                'emp_name'         => $first['emp_name'],
                'order_date'       => ($first['order_date'] != '' ? date('d-m-Y', strtotime($first['order_date'])) : ''),
                'order_for'        => 1,
                'smith_due_date'   => $first['smith_due_date'],
                'rate_type_fix'    => $first['rate_type_fix'],
                'common_po_status' => $first['common_po_status'],
            );
            // Same helpers and the same get_company(1) argument the standard
            // acknowledgement uses, so the letterhead is identical.
            $data['karigar']      = array(
                'karigar_name' => $first['karigar_name'],
                'mobile'       => $first['mobile'],
            );
            $data['comp_details'] = $this->$set_model->get_company(1);

            $this->load->helper(array('dompdf', 'file'));
            $dompdf = new DOMPDF();
            $html   = $this->load->view(self::VIEW_FOLDER.'common_po_ack', $data, TRUE);
            $dompdf->load_html($html);
            $dompdf->set_paper('A4', 'portrait');
            $dompdf->render();
            $dompdf->output();
            while (ob_get_level()) { ob_end_clean(); }
            $dompdf->stream('CommonPO_'.$first['pur_no'].'.pdf', array('Attachment' => 0));
            return;
        }

        $orders=$this->$model->get_karigar_order_details($id_customer_order);

       // echo "<pre>";print_r($orders);exit;

        $data['order_items']=$orders;

        $data['order']['pur_no']=$orders[0]['pur_no'];

        $data['order']['emp_name']=$orders[0]['emp_name'];

        $data['order']['order_date']=$orders[0]['order_date'];

        $data['order']['order_for']=$orders[0]['order_for'];

        $data['order']['smith_due_date']=$orders[0]['smith_due_date'];



        $data['comp_details'] = $this->$set_model->get_company(1);

        $data['order_details']=$this->$model->get_karigar_order_products($id_customer_order);

        $data['karigar']=$this->$model->get_karigar_details($orders[0]['id_karigar']);

        $data['description']=$this->$model->get_order_description();



        //echo "<pre>";print_r($data);exit;



    	$this->load->helper(array('dompdf', 'file'));

    	$dompdf = new DOMPDF();

        $html = $this->load->view('ret_purchase/vendor_ack', $data,true);

        $dompdf->load_html($html);

        $dompdf->set_paper('A4', "portriat" );



		$dompdf->render();

		 $file = $dompdf->output();

		while(ob_get_level()) ob_end_clean();
		$dompdf->stream("VendorAck.pdf",array('Attachment'=>0));





		$file_name =$order[0]['order_no'].'.pdf';

		if (!is_dir('vendor_ack')) {

    	mkdir('vendor_ack', 0777, TRUE);

    	}

		file_put_contents('vendor_ack/'.$file_name, $file);

	    $attachement_url=$this->shortenurl($this->config->item('base_url').'/vendor_ack/'.$file_name);

    }

    //Purchase Order





    function get_KarigarOrders()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_KarigarOrders($_POST);

        echo json_encode($data);

    }





    function get_ActiveWeightRange()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_ActiveWeightRange($_POST);

        echo json_encode($data);

    }





    function get_pur_order_Details()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_purchase_order_status($_POST);

        echo json_encode($data);

    }





    function get_purchase_issue_entry_items()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_qc_receipt_items($_POST);

        echo json_encode($data);

    }





    function update_qc_status()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $itemDetails=$_POST['order_items'];

        $po_id=$_POST['order']['po_ref_no'];

        //echo "<pre>";print_r($_POST);exit;

        $responseData=array();

        $restrict_single_pcs = $this->$model->isSinglePcsRestrictionEnabled();

        $qcIssueRows = array();

        $qc_process_ids = array();

        foreach($itemDetails['id_qc_issue_details'] as $key => $val)

        {

            $qcIssueRows[$key] = $this->$model->getQcIssueDetailRow($val);

            if(!empty($qcIssueRows[$key]['qc_process_id']))
            {
                $qc_process_ids[$qcIssueRows[$key]['qc_process_id']] = $qcIssueRows[$key]['qc_process_id'];
            }

            // The pcs/weight boundary restriction only applies to Ornament items (ret_category.cat_type == 1).
            if($restrict_single_pcs && !empty($qcIssueRows[$key]) && (int)$qcIssueRows[$key]['cat_type'] === 1)

            {
                $issue_pcs  = (int)$qcIssueRows[$key]['issue_pcs'];

                $issue_gwt  = $qcIssueRows[$key]['issue_gwt'];

                $failed_pcs = (int)$itemDetails['failed_pcs'][$key];

                $failed_gwt = $itemDetails['failed_gwt'][$key];

                $violation = FALSE;

                if($failed_pcs < 0 || ($issue_pcs > 0 && $failed_pcs > $issue_pcs))
                {
                    // out of range - rejecting more (or fewer than zero) pieces than were issued
                    $violation = TRUE;
                }
                elseif($failed_pcs === 0)
                {
                    // nothing rejected -> nothing may be carried as rejected weight
                    $violation = !$this->_qc_wt_equals($failed_gwt, 0);
                }
                elseif($issue_pcs > 0 && $failed_pcs === $issue_pcs)
                {
                    // every piece rejected -> the whole issued weight must be rejected too
                    $violation = !$this->_qc_wt_equals($failed_gwt, $issue_gwt);
                }
                // else: 0 < failed_pcs < issue_pcs -> genuine partial split, unrestricted

                if($violation)
                {
                    echo json_encode(array('status'=>FALSE,'message'=>$this->_qc_partial_wt_msg($issue_pcs)));

                    return;
                }
            }

        }

        //Block the update when a Purchase Return already exists for this QC receipt
        foreach($qc_process_ids as $qc_process_id)
        {
            if($this->$model->is_qc_purchase_return_exist($qc_process_id))
            {
                echo json_encode(array('status'=>FALSE,'message'=>self::QC_RECEIPT_LOCK_MSG));

                return;
            }
        }

        $this->db->trans_begin();

        foreach($itemDetails['id_qc_issue_details'] as $key => $val)

        {

                $issueRow = $qcIssueRows[$key];

                // Recompute passed pcs/gwt server-side from issued - failed instead of trusting the
                // client's read-only qc_acc_pcs/qc_acc_gwt fields, which can be tampered with.
                // passed_lwt/passed_nwt stay client-driven — they depend on the independent stone-modal workflow.
                $passed_pcs = !empty($issueRow) ? ((int)$issueRow['issue_pcs'] - (int)$itemDetails['failed_pcs'][$key]) : $itemDetails['qc_acc_pcs'][$key];

                $passed_gwt = !empty($issueRow) ? round((float)$issueRow['issue_gwt'] - (float)$itemDetails['failed_gwt'][$key], 3) : $itemDetails['qc_acc_gwt'][$key];

                $updData=array(

                                'status'        => 1, // QC Completed

                                'failed_pcs'    => $itemDetails['failed_pcs'][$key],

                                'failed_gwt'    => $itemDetails['failed_gwt'][$key],

                                 'failed_lwt'    => ($itemDetails['failed_lwt'][$key]!='' ? $itemDetails['failed_lwt'][$key]: 0.000),

                                'failed_nwt'    => $itemDetails['failed_nwt'][$key],

                                'passed_pcs'    => $passed_pcs,

                                'passed_gwt'    => $passed_gwt,

                                'passed_lwt'    => $itemDetails['qc_acc_lwt'][$key],

                                'passed_nwt'    => $itemDetails['qc_acc_nwt'][$key],

                              );

                $status = $this->$model->updateData($updData,'id_qc_issue_details',$itemDetails['id_qc_issue_details'][$key],'ret_po_qc_issue_details');



               if($status)

    	       {

    	            //echo "<pre>"; print_r($itemDetails['stone_details'][$key]);exit;

    	          	if(!empty($itemDetails['stone_details'][$key]))

    				{

    					$stone_details = json_decode($itemDetails['stone_details'][$key],true);

    					//echo "<pre>"; print_r($stone_details);exit;

    					foreach($stone_details as $val)

    					{

    						$stone_data = array(

    							'qc_rejected_pcs'     => $val['po_stone_rejected_pcs'],

    							'qc_rejected_wt'      => ($val['po_stone_rejected_wt']!='' ? $val['po_stone_rejected_wt']:0),

    							'qc_passed_pcs'       => $val['po_stone_accepted_pcs'],

    							'qc_passed_wt'        => $val['po_stone_accepted_wt'],

    						);

    						$this->$model->updateData($stone_data,'ret_qc_issue_stn_id',$val['ret_qc_issue_stn_id'],'ret_po_qc_issue_stone_details');

    					}

    				}

    	       }

        }

        if($this->db->trans_status()===TRUE)

        {

            $this->db->trans_commit();

            //Generate LOT

            /*if($po_id!='')

            {

                $LotStatus=TRUE;

                $pur_ord_det=$this->$model->get_purchase_order_qc_details($po_id); // CHECKING QC STATUS AD H.M STATUS FOR LOT GENERATE

                foreach($pur_ord_det as $po_items)

                {

                    if($po_items['status']==0)

                    {

                        $LotStatus=FALSE;

                    }

                }



                if($LotStatus)

                {

                    $this->generate_lot($po_id);

                }

            }*/

            //Generate LOT

            $log_data = array(

                        	'id_log'        => $this->session->userdata('id_log'),

                        	'event_date'	=> date("Y-m-d H:i:s"),

                        	'module'      	=> 'QUALITY CONTROL',

                        	'operation'   	=> 'Update',

                        	'record'        =>  $po_id,

                        	'remark'       	=> 'QC Receipt Updated Successfully..'

                        );

            $this->log_model->log_detail('insert','',$log_data);





            $responseData=array('status'=>true,'message'=>'QC Updated successfully.');

            $this->session->set_flashdata('chit_alert',array('message'=>'QC Updated successfully.','class'=>'success','title'=>'QC Entry'));

        }

        else

        {

            $this->db->trans_rollback();

            $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

            $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'QC Entry'));

        }

        echo json_encode($responseData);

    }





    function generate_lot($po_id)

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $itemDetails = $this->$model->get_purchase_by_category($po_id);

        $ho              =  $this->$model->get_headOffice();

		$dCData          =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

		$bill_date       =  ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

        foreach($itemDetails as $addData)

        {

            $data = array(

            'lot_date'				=> date("Y-m-d H:i:s"),

            'created_branch'		=> (isset($ho['id_branch']) ? $ho['id_branch']:NULL ),

            'lot_received_at'       => $ho['id_branch'],

            'stock_type'            => $addData['stock_type'],

            'lot_from'              => 2,

            'po_id'                 => $po_id,

            'gold_smith'			=> (isset($addData['po_karigar_id']) ? $addData['po_karigar_id'] :NULL ),

            'id_category'			=> (isset($addData['id_category']) ? $addData['id_category'] :NULL ),

            'id_purity'				=> (isset($addData['id_purity']) ? $addData['id_purity'] :NULL ),

            'created_on'	  		=> date("Y-m-d H:i:s"),

            'created_by'      		=> $this->session->userdata('uid')

            );

            $this->db->trans_begin();

    	 	$insId = $this->$model->insertData($data,'ret_lot_inwards');

    	 	if($insId)

    	 	{

    	 	    $po_order_details=$this->$model->get_purchase_orders_by_product($po_id,$addData['id_category'],$addData['id_purity'],$addData['stock_type']);

    	 	    //print_r($po_order_details);exit;

    	 	    foreach($po_order_details as $items)

    	 	    {

    	 	        $item_details = array(

                        'lot_no'	    =>$insId,

                        'lot_product'   =>($items['id_product']!='' ? $items['id_product']:NULL),

                        'no_of_piece'   =>($items['total_pcs']!='' ? $items['total_pcs']:0),

                        'gross_wt'      =>($items['total_gwt']!='' ? $items['total_gwt']:0),

                        'less_wt'       =>($items['total_lwt']!='' ? $items['total_lwt']:0),

                        'net_wt'        =>($items['total_nwt']!='' ? $items['total_nwt']:0),

                        'pur_wt'        =>(isset($items['total_pur_wt']) && $items['total_pur_wt']!='' ? $items['total_pur_wt']:0),

                    );

                    $detail_insId = $this->$model->insertData($item_details,'ret_lot_inwards_detail');

                    //echo "<pre>"; print_r($this->db->last_query());exit;

                    if($detail_insId)

                    {

                        $this->$model->updateMultipleWhereData(array('is_lot_created'=>1,'lot_no'=>$insId),array('po_item_po_id'=>$po_id,'po_item_pro_id'=>$items['id_product'],'id_purity'=>$items['id_purity']),'ret_purchase_order_items');

                        //echo "<pre>"; print_r($this->db->last_query());exit;

                        if($addData['stock_type']==2)

                        {

                            $purchase_order_details = $this->$model->get_purchase_order_items($po_id,$items['id_product']);

                            foreach($purchase_order_details as $val)

                            {

                                $existData=array('id_product'=>$val['po_item_pro_id'],'design'=>$val['po_item_des_id'],'id_sub_design'=>$val['po_item_sub_des_id'],'id_branch'=>$ho['id_branch']);



						        $isExist = $this->$model->checkNonTagItemExist($existData);



						        if($isExist['status'] == TRUE)

						        {

						            $nt_data = array(

                                    'id_nontag_item'=>$isExist['id_nontag_item'],

                                    'gross_wt'		=> $val['total_gwt'],

                                    'net_wt'		=> $val['total_nwt'],

                                    'no_of_piece'   => $val['total_pcs'],

                                    'updated_by'	=> $this->session->userdata('uid'),

                                    'updated_on'	=> date('Y-m-d H:i:s'),

                                    );

                                    $this->$model->updateNTData($nt_data,'+');



                                    $non_tag_data=array(

                                    'product'	    => $val['po_item_pro_id'],

                                    'design'	    => $val['po_item_des_id'],

                                    'id_sub_design'	=> $val['po_item_sub_des_id'],

                                    'no_of_piece'   => $val['total_pcs'],

                                    'gross_wt'		=> $val['total_gwt'],

                                    'net_wt'		=> $val['total_nwt'],

                                    'to_branch'	    => $ho['id_branch'],

                                    'from_branch'	=> NULL,

                                    'ref_no'	    => $insId,

                                    'status'	    => 0,

                                    'date'          => $bill_date,

                                    'created_on'    => date("Y-m-d H:i:s"),

                                    'created_by'    =>  $this->session->userdata('uid')

                                    );

                                    $this->$model->insertData($non_tag_data,'ret_nontag_item_log');

						        }else

						        {

                                        $nt_data=array(

                                        'branch'	    => $ho['id_branch'],

                                        'product'	    => $val['po_item_pro_id'],

                                        'design'	    => $val['po_item_des_id'],

                                        'id_sub_design'	=> $val['po_item_sub_des_id'],

                                        'no_of_piece'   => $val['total_pcs'],

                                        'gross_wt'		=> $val['total_gwt'],

                                        'net_wt'		=> $val['total_nwt'],

                                        'created_on'    => date("Y-m-d H:i:s"),

                                        'created_by'    => $this->session->userdata('uid')

                                        );

                                        $this->$model->insertData($nt_data,'ret_nontag_item');



                                        $non_tag_data=array(

                                        'product'	    => $val['po_item_pro_id'],

                                        'design'	    => $val['po_item_des_id'],

                                        'id_sub_design'	=> $val['po_item_sub_des_id'],

                                        'no_of_piece'   => $val['total_pcs'],

                                        'gross_wt'		=> $val['total_gwt'],

                                        'net_wt'		=> $val['total_nwt'],

                                        'from_branch'	=> NULL,

                                        'to_branch'	    => $ho['id_branch'],

                                        'ref_no'	    => $insId,

                                        'status'	    => 0,

                                        'date'          => $bill_date,

                                        'created_on'    => date("Y-m-d H:i:s"),

                                        'created_by'    =>  $this->session->userdata('uid')

                                        );

                                        $this->$model->insertData($non_tag_data,'ret_nontag_item_log');

						        }

                            }



                        }



                    }



    	 	    }



    	 	    if($detail_insId)

    	 	    {

    	 	        $order_items        =$this->$model->get_po_purchase_items($po_id,$addData['id_category']);

    	 	        $total_payable_wt   =0;

    	 	        $total_mc_value     =0;

    	 	        $total_weight_amt   =0;

    	 	        $total_making_charge   =0;

    	 	        foreach($order_items as $order)

    	 	        {

    	 	            $total_weight       =($order['item_wastage']>0 ? ((($order['total_nwt']*$order['item_wastage'])/100)+$order['total_nwt']) : $order['total_nwt'] );



    	 	            //$pure_wt          =(($total_weight*22)/24); // For calculating PURE WEIGHT (22CT to 24 CT)



    	 	            $pure_wt            = $order['item_pure_wt'];



    	 	            $total_mc_value+=($order['mc_type']==1 ? ($order['total_gwt']*$order['mc_value']):($order['total_pcs']*$order['mc_value']));



    	 	            $item_mc_value  =($order['mc_type']==1 ? ($order['total_gwt']*$order['mc_value']):($order['total_pcs']*$order['mc_value']));





    	 	            $total_payable_wt+=$pure_wt;







    	 	            /*//Update Rate Fixing



    	 	            if($order['fix_rate_per_grm']>0 && $order['is_rate_fixed']==1)

    	 	            {

    	 	                $total_amount =($order['item_pure_wt']*$order['fix_rate_per_grm']);



    	 	                $tax_amt      = (($total_amount*$order['tax_percentage'])/100);



    	 	                $total_weight_amt+=$tax_amt+$total_amount;



    	 	                $rateFixData=array(

                               'rate_fix_po_item_id'    =>$order['po_item_id'],

                               'rate_fix_type'          =>1,

                               'rate_fix_wt'            =>$order['item_pure_wt'],

                               'tax_group_id'           =>$order['tgrp_id'],

                               'rate_fix_rate'          =>$order['fix_rate_per_grm'],

                               'rate_fix_created_from'  =>2,

                               'total_tax_amount'       =>$tax_amt,

                               'total_amount'           =>($tax_amt+$total_amount),

                               'rate_fix_create_by'     =>$this->session->userdata('uid'),

                               'rate_fix_created_on'    =>date("Y-m-d H:i:s"),

                               );

                               $insertId=$this->$model->insertData($rateFixData,'ret_po_rate_fix');

                              if($insertId)

        			          {

        			               $log_data = array(

                                    	'id_log'        =>  $this->session->userdata('id_log'),

                                    	'event_date'	=>  date("Y-m-d H:i:s"),

                                    	'module'      	=> 'RATE FIXING',

                                    	'operation'   	=> 'insert',

                                    	'record'        =>  $order['po_item_id'],

                                    	'remark'       	=> 'Rate Fixed Successfully..'

                                    );

                                    $this->log_model->log_detail('insert','',$log_data);

        			          }

    	 	            }



    	 	            if($order['mc_value']>0) // Update tax and insert into rate fix table for mc and other charges

    	 	            {

    	 	                $tax_amount=(($item_mc_value*$order['tax_percentage'])/100);



    	 	                $total_making_charge+=$tax_amount+$item_mc_value;



    	 	                $mc_payable_data=array(

                               'rate_fix_po_item_id'    =>$order['po_item_id'],

                               'rate_fix_type'          =>2,

                               'rate_fix_amt'           =>$item_mc_value,

                               'tax_group_id'           =>$order['tgrp_id'],

                               'rate_fix_rate'          =>0,

                               'rate_fix_created_from'  =>2,

                               'total_tax_amount'       =>$tax_amount,

                               'total_amount'           =>($tax_amount+$item_mc_value),

                               'rate_fix_create_by'     =>$this->session->userdata('uid'),

                               'rate_fix_created_on'    =>date("Y-m-d H:i:s"),

                               );

                               $insertId=$this->$model->insertData($mc_payable_data,'ret_po_rate_fix');

                               //print_r($this->db->last_query());exit;

    	 	            }

    	 	            */

    	 	            //Update Rate Fixing



    	 	        }

    	 	        //Update Payable weight and amount

    	 	        $payInsData=array(

    	 	                        'total_payable_wt'=>number_format($total_payable_wt,3,'.',''),

    	 	                        'total_payable_amt'=>number_format($total_weight_amt+$total_making_charge,2,'.',''),

    	 	                        'po_id'=>$po_id,

    	 	                        );



    	 	        $paymentData=$this->$model->update_po_paymentData($payInsData,'+');

    	 	        //Update Payable weight and amount



    	 	    }

                if($this->db->trans_status()===TRUE)

                {



                    $log_data = array(

                    	'id_log'        => $this->session->userdata('id_log'),

                    	'event_date'	=> date("Y-m-d H:i:s"),

                    	'module'      	=> 'Lot',

                    	'operation'   	=> 'Add',

                    	'record'        => $insId,

                    	'remark'       	=> 'Lot added successfully From QC Receipt'

                    );

                    $this->log_model->log_detail('insert','',$log_data);



                    $this->db->trans_commit();

                    $responseData=array('status'=>true,'message'=>'Lot Created successfully.');

                }

                else

                {

                    $this->db->trans_rollback();

                    $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                }



    	 	}

        }

    }





    function generate_lot_from_halmarking($hm_process_id)

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $addData=$this->$model->get_halmarking_purchase_order($hm_process_id);



        $settings = $this->$model->get_ret_settings('lot_recv_branch');

    	if($settings == 1){ // HO Only

			$ho = $this->$model->get_headOffice();

		}



        $data = array(

        'lot_date'				=> date("Y-m-d H:i:s"),

        'created_branch'		=> (isset($ho['id_branch']) ? $ho['id_branch']:NULL ),

        'lot_received_at'       => $ho['id_branch'],

        'stock_type'            => 1,

        'lot_from'              => 2,

        'po_id'                 => $addData['po_id'],

        'gold_smith'			=> (isset($addData['po_karigar_id']) ? $addData['po_karigar_id'] :NULL ),

        'id_category'			=> (isset($addData['id_category']) ? $addData['id_category'] :NULL ),

        'id_purity'				=> (isset($addData['id_purity']) ? $addData['id_purity'] :NULL ),

        'created_on'	  		=> date("Y-m-d H:i:s"),

        'created_by'      		=> $this->session->userdata('uid')

        );

        $this->db->trans_begin();

	 	$insId = $this->$model->insertData($data,'ret_lot_inwards');

	 	//echo "<pre>"; print_r($this->db->last_query());exit;

	 	if($insId)

	 	{

	 	    $po_order_details=$this->$model->get_purchase_orders_by_product($addData['po_id']);

	 	    //echo "<pre>"; print_r($po_order_details);exit;

	 	    foreach($po_order_details as $items)

	 	    {

	 	        $item_details = array(

                    'lot_no'	    =>$insId,

                    'lot_product'   =>($items['id_product']!='' ? $items['id_product']:NULL),

                    'no_of_piece'   =>($items['total_pcs']!='' ? $items['total_pcs']:0),

                    'gross_wt'      =>($items['total_gwt']!='' ? $items['total_gwt']:0),

                    'less_wt'       =>($items['total_lwt']!='' ? $items['total_lwt']:0),

                    'net_wt'        =>($items['total_nwt']!='' ? $items['total_nwt']:0),

                    'pur_wt'        =>(isset($items['total_pur_wt']) && $items['total_pur_wt']!='' ? $items['total_pur_wt']:0),

                );

                $detail_insId = $this->$model->insertData($item_details,'ret_lot_inwards_detail');

                //echo "<pre>"; print_r($this->db->last_query());exit;

	 	    }



	 	    if($detail_insId)

	 	    {

	 	        $order_items=$this->$model->check_purchase_halmarking_details($addData['po_id']);

	 	        $total_payable_wt=0;

	 	        $total_mc_value=0;

	 	        $total_making_charge   =0;

	 	        foreach($order_items as $order)

	 	        {

	 	            $total_weight=($order['item_wastage']>0 ? ((($order['total_nwt']*$order['item_wastage'])/100)+$order['total_nwt']) : $order['total_nwt'] );



	 	           // $pure_wt=(($total_weight*22)/24); // For calculating PURE WEIGHT (22CT to 24 CT)

	 	            $pure_wt = $order['item_pure_wt'];



	 	            $total_mc_value+=($order['mc_type']==1 ? ($order['total_gwt']*$order['mc_value']):($order['total_pcs']*$order['mc_value']));



	 	            $this->$model->updateData(array('is_lot_created'=>1,'lot_no'=>$insId,'item_pure_wt'=>$pure_wt),'po_item_id',$order['po_item_id'],'ret_purchase_order_items');



	 	            $item_mc_value  =($order['mc_type']==1 ? ($order['total_gwt']*$order['mc_value']):($order['total_pcs']*$order['mc_value']));



	 	            $total_payable_wt+=$pure_wt;





	 	             //Update Rate Fixing



	 	            /*if($order['fix_rate_per_grm']>0 && $order['is_rate_fixed']==1)

	 	            {

	 	                $total_amount =($order['item_pure_wt']*$order['fix_rate_per_grm']);



	 	                $tax_amt      = (($total_amount*$order['tax_percentage'])/100);



	 	                $total_weight_amt+=$tax_amt+$total_amount;



	 	                $rateFixData=array(

                           'rate_fix_po_item_id'    =>$order['po_item_id'],

                           'rate_fix_type'          =>1,

                           'rate_fix_wt'            =>$order['item_pure_wt'],

                           'tax_group_id'           =>$order['tgrp_id'],

                           'rate_fix_rate'          =>$order['fix_rate_per_grm'],

                           'rate_fix_created_from'  =>2,

                           'total_tax_amount'       =>$tax_amt,

                           'total_amount'           =>($tax_amt+$total_amount),

                           'rate_fix_create_by'     =>$this->session->userdata('uid'),

                           'rate_fix_created_on'    =>date("Y-m-d H:i:s"),

                           );

                           $insertId=$this->$model->insertData($rateFixData,'ret_po_rate_fix');

                          if($insertId)

    			          {

    			               $log_data = array(

                                	'id_log'        =>  $this->session->userdata('id_log'),

                                	'event_date'	=>  date("Y-m-d H:i:s"),

                                	'module'      	=> 'RATE FIXING',

                                	'operation'   	=> 'insert',

                                	'record'        =>  $order['po_item_id'],

                                	'remark'       	=> 'Rate Fixed Successfully..'

                                );

                                $this->log_model->log_detail('insert','',$log_data);

    			          }

	 	            }



	 	            if($order['mc_value']>0) // Update tax and insert into rate fix table for mc and other charges

	 	            {

	 	                $tax_amount=(($item_mc_value*$order['tax_percentage'])/100);



	 	                $total_making_charge+=$tax_amount+$item_mc_value;



	 	                $mc_payable_data=array(

                           'rate_fix_po_item_id'    =>$order['po_item_id'],

                           'rate_fix_type'          =>2,

                           'rate_fix_amt'           =>$item_mc_value,

                           'tax_group_id'           =>$order['tgrp_id'],

                           'rate_fix_rate'          =>0,

                           'rate_fix_created_from'  =>2,

                           'total_tax_amount'       =>$tax_amount,

                           'total_amount'           =>($tax_amount+$item_mc_value),

                           'rate_fix_create_by'     =>$this->session->userdata('uid'),

                           'rate_fix_created_on'    =>date("Y-m-d H:i:s"),

                           );

                           $insertId=$this->$model->insertData($mc_payable_data,'ret_po_rate_fix');

                          // print_r($this->db->last_query());exit;

	 	            }

	 	            */

	 	            //Update Rate Fixing



	 	        }



	 	        //Update Payable weight and amount

	 	        $payInsData=array(

	 	                        'total_payable_wt'=>number_format($total_payable_wt,3,'.',''),

	 	                       'total_payable_amt'=>number_format($total_weight_amt+$total_making_charge,2,'.',''),

	 	                        'po_id'=>$addData['po_id'],

	 	                        );

	 	        $paymentData=$this->$model->update_po_paymentData($payInsData,'+');

	 	        //print_r($this->db->last_query());exit;

	 	        //Update Payable weight and amount



	 	    }



            if($this->db->trans_status()===TRUE)

            {



                $log_data = array(

                	'id_log'        => $this->session->userdata('id_log'),

                	'event_date'	=> date("Y-m-d H:i:s"),

                	'module'      	=> 'Lot',

                	'operation'   	=> 'Add',

                	'record'        => $insId,

                	'remark'       	=> 'Lot added successfully From QC Receipt'

                );

                $this->log_model->log_detail('insert','',$log_data);



                $this->db->trans_commit();

                $responseData=array('status'=>true,'message'=>'Lot Created successfully.');

            }

            else

            {

                $this->db->trans_rollback();

                //echo $this->db->last_query();exit;

                $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

            }



	 	}

    }





    function get_qc_status_details()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_qc_status_details();

        echo json_encode($data);

    }


    function get_po_balance()
    {
        $model=	self::RET_PUR_ORDER_MODEL;
        $data= $this->$model->get_po_balance($_POST);
        echo json_encode($data);
    }


    function get_po_balance_details()
    {
        $model=	self::RET_PUR_ORDER_MODEL;
        $data= $this->$model->get_po_balance_details($_POST);
        echo json_encode($data);
    }

    function get_po_ratecut_balance_details()
    {
        $po_id = $this->input->post('po_id');
        $model = self::RET_PUR_ORDER_MODEL;
        $data = $this->$model->get_po_ratecut_balance_details($po_id);
        echo json_encode($data);
    }



    //Lot generate

    function lot_generate($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;


		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'generate_lot/list';

					$data['access'] = $this->$SETT_MOD ->get_access('admin_ret_purchase/lot_generate/list');

        			$this->load->view('layout/template', $data);

			break;

			case 'add':

					$data['financial_year']  = $this->$model->GetFinancialYear();

					$data['main_content'] = self::VIEW_FOLDER.'generate_lot/form';

					$data['access'] = $this->$SETT_MOD ->get_access('admin_ret_purchase/lot_generate/add');
        			$this->load->view('layout/template', $data);

			break;



			case 'po_details':

			    $data= $this->$model->get_qc_receipt_lot();

                echo json_encode($data);

			break;



			case 'qc_item_details':

			    $data= $this->$model->get_lot_generate_item_details($_POST);

                echo json_encode($data);

			break;



			case 'purchase_receipt':

			    $data= $this->$model->get_qc_issue_details();

                echo json_encode($data);

			break;



			case 'qc_entry':

					$data['main_content'] = self::VIEW_FOLDER.'qc_process/qc_entry';

        			$this->load->view('layout/template', $data);

			break;



			case 'ajax':

				$list=$this->$model->get_purchase_qc_details($_POST);

			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/qc_issue_receipt/list');

		        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;

		}

	}

    //Lot generate





    //QC Issue / Receipt

    function qc_issue_receipt($type="",$id="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;
		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'qc_process/list';

					$data['access'] = $this->$SETT_MOD->get_access('admin_ret_purchase/qc_issue_receipt/list');

        			$this->load->view('layout/template', $data);

			break;


			case 'add':

					$data['financial_year']  = $this->$model->GetFinancialYear();

					$data['restrict_partial_wt_single_pcs'] = $this->$model->isSinglePcsRestrictionEnabled() ? 1 : 0;

					$data['main_content'] = self::VIEW_FOLDER.'qc_process/form';

        			$this->load->view('layout/template', $data);

			break;

			case 'qc_issue_edit':

				$data['restrict_partial_wt_single_pcs'] = $this->$model->isSinglePcsRestrictionEnabled() ? 1 : 0;

				$data['main_content'] = self::VIEW_FOLDER.'qc_process/form';

        		$this->load->view('layout/template', $data);

			break;

			case 'qcIssuedEdit':

				$data = $this->$model->get_qcIssuedDetails($_POST);

				echo json_encode($data);

			break;



			case 'qc_receipt_edit':

				//QC Receipt is locked once the rejected items are returned to the vendor
				if($this->$model->is_qc_purchase_return_exist($id))
				{
					$this->session->set_flashdata('chit_alert',array('message'=>self::QC_RECEIPT_LOCK_MSG,'class'=>'danger','title'=>'QC Issue/ Receipt '));

					redirect('admin_ret_purchase/qc_issue_receipt/list');
				}

				$data['restrict_partial_wt_single_pcs'] = $this->$model->isSinglePcsRestrictionEnabled() ? 1 : 0;

				$data['main_content'] = self::VIEW_FOLDER.'qc_process/qc_entry';

				$this->load->view('layout/template', $data);

			break;

			case 'qcReceiptEdit':

				if($this->$model->is_qc_purchase_return_exist($_POST['id_qc_issue']))
				{
					echo json_encode(array('status'=>false,'message'=>self::QC_RECEIPT_LOCK_MSG));

					return;
				}

				$data = $this->$model->get_qcReceiptDetails($_POST);

				echo json_encode($data);

			break;



			case 'purchase_issue':

			    // $data= $this->$model->purchase_issue();
				$data= $this->$model->purchase_issue($_POST['fin_year']);

                echo json_encode($data);

			break;



			case 'qc_item_details':

			    $data= $this->$model->get_purchase_item_details($_POST['po_id'],$_POST['fin_year']);

                echo json_encode($data);

			break;



			case 'purchase_receipt':

			    $data= $this->$model->get_qc_issue_details();

                echo json_encode($data);

			break;



			case 'qc_entry':

					$data['restrict_partial_wt_single_pcs'] = $this->$model->isSinglePcsRestrictionEnabled() ? 1 : 0;

					$data['main_content'] = self::VIEW_FOLDER.'qc_process/qc_entry';

        			$this->load->view('layout/template', $data);

			break;

		 case 'cancel_qc_issue':

				$updData=array(

				'qc_status'          => 2,

				'cancel_reason'      => $_POST['cancel_reason'],

				'cancelled_on'        => date("Y-m-d H:i:s"),

				'cancelled_by'        => !empty($this->session->userdata('uid')) ? $this->session->userdata('uid') : NULL

				);

				$this->db->trans_begin();



				$this->$model->updateData($updData,'qc_process_id',$_POST['qc_process_id'],'ret_po_qc_issue_process');

				if($this->db->trans_status()===TRUE)

				{

					$this->db->trans_commit();

					 $log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'QC Issue/ Receipt',

						'operation'     => 'Cancel',

						'record'        =>  $id,

						'remark'        => 'QC Issue/ Receipt Cancelled successfully'

						);

				      $this->log_model->log_detail('insert','',$log_data);

					$this->session->set_flashdata('chit_alert',array('message'=>'QC Issue/ Receipt  Cancelled Successfully','class'=>'success','title'=>'QC Issue/ Receipt '));

					$return_data=array('status'=>TRUE,'message'=>'Order Instructions Added Successfully..');

				}

				else

				{

					echo $this->db->last_query();exit;

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'QC Issue/ Receipt '));

					$return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

				}

				echo json_encode($return_data);

		   break;


			case 'ajax':

				$list=$this->$model->get_purchase_qc_details($_POST);

			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/qc_issue_receipt/list');

		        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;

		}

	}



	function update_qc_issue()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $id_employee = $_POST['id_employee'];

	    $total_pcs   = $_POST['total_pcs'];

	    $total_gwt   = $_POST['total_gwt'];

	    $total_lwt   = $_POST['total_lwt'];

	    $total_nwt   = $_POST['total_nwt'];

	    $req_data    = $_POST['req_data'];

	    if($this->$model->isSinglePcsRestrictionEnabled())
	    {
	        foreach($req_data as $items)
	        {
	            $balance = $this->$model->getQcIssueItemBalance($items['po_item_id'], 0);

	            // The pcs/weight boundary restriction only applies to Ornament items (ret_category.cat_type == 1).
	            if(!empty($balance) && (int)$balance['cat_type'] === 1)
	            {
	                $blc_pcs = (int)$balance['blc_pcs'];

	                $req_pcs = (int)$items['qc_issue_pcs'];

	                $req_gwt = $items['qc_issue_gross_wt'];

	                $violation = FALSE;

	                if($blc_pcs > 0)
	                {
	                    if($req_pcs < 0 || $req_pcs > $blc_pcs)
	                    {
	                        // out of range - issuing more (or fewer than zero) pieces than remain
	                        $violation = TRUE;
	                    }
	                    elseif($req_pcs === 0)
	                    {
	                        // a row selected for issue must issue at least one piece
	                        $violation = TRUE;
	                    }
	                    elseif($req_pcs === $blc_pcs)
	                    {
	                        // taking every remaining piece -> must take all the remaining weight too
	                        $violation = !$this->_qc_wt_equals($req_gwt, $balance['blc_gwt']);
	                    }
	                    // else: 0 < req_pcs < blc_pcs -> genuine partial split, unrestricted
	                }

	                if($violation)
	                {
	                    echo json_encode(array('status'=>FALSE,'message'=>$this->_qc_partial_wt_msg($blc_pcs)));

	                    return;
	                }
	            }
	        }
	    }

	    $ref_no      = $this->$model->get_qc_ref_no();

		$dCData          =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

        $bill_date       =  ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

	    $insData=array(

           'ref_no'         =>$ref_no,

		   'date_add'		=>$bill_date,

           'qc_id_vendor'   =>$id_employee,

           'issue_gross_wt' =>$total_gwt,

           'issue_less_wt'  =>$total_lwt,

           'issue_net_wt'   =>$total_nwt,

           'issue_pcs'      =>($total_pcs !== '' && $total_pcs !== null ? $total_pcs : NULL),

           'created_at'     =>date("Y-m-d H:i:s"),

	       'created_by'     =>$this->session->userdata('uid')

          );

        $this->db->trans_begin();

        $insId=$this->$model->insertData($insData,'ret_po_qc_issue_process');

        if($insId)

        {

            foreach($req_data as $items)

    	    {

    	        $itemDetails=array(

    	                       'qc_process_id'  =>$insId,

    	                       'po_item_id'     =>$items['po_item_id'],

    	                       'issue_pcs'      =>(isset($items['qc_issue_pcs']) && $items['qc_issue_pcs'] !== '' ? $items['qc_issue_pcs'] : 0),

    	                       'issue_gwt'      =>$items['qc_issue_gross_wt'],

    	                       'issue_lwt'      =>$items['qc_issue_less_wt'],

    	                       'issue_nwt'      =>$items['qc_issue_net_wt'],

    	                      );

    	       $status=$this->$model->insertData($itemDetails,'ret_po_qc_issue_details');

    	       if($status)

    	       {

    	          	if(!empty($items['stone_details']))

    				{

    					$stone_details = json_decode($items['stone_details'],true);

    					foreach($stone_details as $val)

    					{

    						$stone_data = array(

    							'id_qc_issue_details'       => $status,

    							'po_st_id'                  => $val['po_st_id'],

    							'stone_pcs'                 => $val['stone_pcs'],

    							'stone_wt'                  => $val['stone_wt'],

    						);

    						$this->$model->insertData($stone_data, 'ret_po_qc_issue_stone_details');

    						//echo $this->db->last_query();exit;

    					}

    				}

    	       }

    	    }

    	    if($this->db->trans_status()===TRUE)

            {



                $log_data = array(

                        	'id_log'        => $this->session->userdata('id_log'),

                        	'event_date'	=> date("Y-m-d H:i:s"),

                        	'module'      	=> 'QUALITY CONTROL',

                        	'operation'   	=> 'Update',

                        	'record'        =>  NULL,

                        	'remark'       	=> 'QC Issued Successfully..'

                        );

                $this->log_model->log_detail('insert','',$log_data);



                $this->db->trans_commit();

                $responseData=array('status'=>true,'message'=>'QC Issued successfully.');

                $this->session->set_flashdata('chit_alert',array('message'=>'QC Issued successfully.','class'=>'success','title'=>'QC Issue'));

            }

            else

            {

                $this->db->trans_rollback();

                $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'QC Issue'));

            }

            echo json_encode($responseData);

        }

	}

	function qc_issue_update()

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		//echo"<pre>";print_r($_POST);exit;

		$qc_process_id = $_POST['qc_process_id'];

		$id_employee = $_POST['id_employee'];

	    $total_pcs   = $_POST['total_pcs'];

	    $total_gwt   = $_POST['total_gwt'];

	    $total_lwt   = $_POST['total_lwt'];

	    $total_nwt   = $_POST['total_nwt'];

	    $req_data    = $_POST['req_data'];

		if($this->$model->isSinglePcsRestrictionEnabled())
		{
			foreach($req_data as $items)
			{
				$balance = $this->$model->getQcIssueItemBalance($items['po_item_id'], $qc_process_id);

				// The pcs/weight boundary restriction only applies to Ornament items (ret_category.cat_type == 1).
				if(!empty($balance) && (int)$balance['cat_type'] === 1)
				{
					$blc_pcs = (int)$balance['blc_pcs'];

					$req_pcs = (int)$items['qc_issue_pcs'];

					$req_gwt = $items['qc_issue_gross_wt'];

					$violation = FALSE;

					if($blc_pcs > 0)
					{
						if($req_pcs < 0 || $req_pcs > $blc_pcs)
						{
							// out of range - issuing more (or fewer than zero) pieces than remain
							$violation = TRUE;
						}
						elseif($req_pcs === 0)
						{
							// a row selected for issue must issue at least one piece
							$violation = TRUE;
						}
						elseif($req_pcs === $blc_pcs)
						{
							// taking every remaining piece -> must take all the remaining weight too
							$violation = !$this->_qc_wt_equals($req_gwt, $balance['blc_gwt']);
						}
						// else: 0 < req_pcs < blc_pcs -> genuine partial split, unrestricted
					}

					if($violation)
					{
						echo json_encode(array('status'=>FALSE,'message'=>$this->_qc_partial_wt_msg($blc_pcs)));

						return;
					}
				}
			}
		}

		$updData=array(

			'qc_id_vendor'   =>$id_employee,

			'issue_gross_wt' =>$total_gwt,

			'issue_less_wt'  =>$total_lwt,

			'issue_net_wt'   =>$total_nwt,

			'issue_pcs'      =>$total_pcs,

			'updated_on'     =>date("Y-m-d H:i:s"),

			'updated_by'     =>$this->session->userdata('uid')

		   );

		$this->db->trans_begin();

		$updstatus=$this->$model->updateData($updData,'qc_process_id',$qc_process_id,'ret_po_qc_issue_process');

		//print_r($this->db->last_query());exit;

		if($updstatus)

		{

			$qcIssueDelete = $this->$model->deleteData("qc_process_id",$qc_process_id,'ret_po_qc_issue_details');

			foreach($req_data as $items)

    	    {

				$itemDetails=array(

					'qc_process_id'  =>$qc_process_id,

					'po_item_id'     =>$items['po_item_id'],

					'issue_pcs'      =>$items['qc_issue_pcs'],

					'issue_gwt'      =>$items['qc_issue_gross_wt'],

					'issue_lwt'      =>$items['qc_issue_less_wt'],

					'issue_nwt'      =>$items['qc_issue_net_wt'],

				);

				$status=$this->$model->insertData($itemDetails,'ret_po_qc_issue_details');

				if($status)

				{

					if(!empty($items['stone_details']))

					{

						$stone_details = json_decode($items['stone_details'],true);

						foreach($stone_details as $val)

						{

							$stone_data = array(

								'id_qc_issue_details'       => $status,

								'po_st_id'                  => $val['po_st_id'],

								'stone_pcs'                 => $val['stone_pcs'],

								'stone_wt'                  => $val['stone_wt'],

							);

							$this->$model->insertData($stone_data, 'ret_po_qc_issue_stone_details');

							//echo $this->db->last_query();exit;

						}

					}

				}

			}

			if($this->db->trans_status()===TRUE)

            {



                $log_data = array(

                        	'id_log'        => $this->session->userdata('id_log'),

                        	'event_date'	=> date("Y-m-d H:i:s"),

                        	'module'      	=> 'QUALITY CONTROL',

                        	'operation'   	=> 'Update',

                        	'record'        =>  NULL,

                        	'remark'       	=> 'QC Updated Successfully..'

                        );

                $this->log_model->log_detail('insert','',$log_data);



                $this->db->trans_commit();

                $responseData=array('status'=>true,'message'=>'QC Updated successfully.');

                $this->session->set_flashdata('chit_alert',array('message'=>'QC Updated successfully.','class'=>'success','title'=>'QC Issue'));

            }

            else

            {

                $this->db->trans_rollback();

                $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'QC Issue'));

            }

            echo json_encode($responseData);

		}

	}







    //QC Issue / Receipt





    //Halmarking issue/receipt

    function halmarking_issue_receipt($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;

		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'halmarking_process/list';

					$data['access'] = $this->$SETT_MOD->get_access('admin_ret_purchase/halmarking_issue_receipt/list');
        			$this->load->view('layout/template', $data);

			break;

			case 'add':

					$data['main_content'] = self::VIEW_FOLDER.'halmarking_process/form';

        			$this->load->view('layout/template', $data);

			break;



			case 'get_pending_halmarking_items':

			    $data= $this->$model->get_pending_halmarking_items();

                echo json_encode($data);

			break;



			case 'purchase_receipt':

			    $data= $this->$model->purchase_receipt_orders();

                echo json_encode($data);

			break;



			case 'hm_receipt':

					$data['main_content'] = self::VIEW_FOLDER.'halmarking_process/hm_receipt';

        			$this->load->view('layout/template', $data);

			break;



			case 'get_halmarking_issue_orders':

			    $data= $this->$model->get_halmarking_issue_orders();

                echo json_encode($data);

			break;



			case 'ajax':

				$list=$this->$model->get_halmarking_details($_POST);

			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/halmarking_issue_receipt/list');

		        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;

		}

	}





	function update_halmarking_issue()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $id_karigar  = $_POST['id_karigar'];

	    $total_pcs   = $_POST['total_pcs'];

	    $total_gwt   = $_POST['total_gwt'];

	    $total_lwt   = $_POST['total_lwt'];

	    $total_nwt   = $_POST['total_nwt'];

	    $req_data   =$_POST['req_data'];

	    $insData=array(

	       'hm_ref_no'              =>$this->$model->generate_HalmarkingRefNo(),

           'hm_vendor_id'           =>$id_karigar,

           'hm_process_gwt'         =>$total_gwt,

           'hm_process_lwt'         =>$total_lwt,

           'hm_process_nwt'         =>$total_nwt,

           'hm_process_pcs'         =>$total_pcs,

           'hm_process_created_at'  =>date("Y-m-d H:i:s"),

	       'hm_process_created_by'  =>$this->session->userdata('uid')

          );

        $this->db->trans_begin();

        $insId=$this->$model->insertData($insData,'ret_po_halmark_process');

        //print_r($this->db->last_query());exit;

        if($insId)

        {

            foreach($req_data as $items)

    	    {

    	        $itemDetails=array(

    	                       'hm_issue_id'    =>$insId,

    	                       'hm_po_item_id'  =>$items['po_item_id'],

							   'hm_po_qc_issue_id' => $items['id_qc_issue_details'],

							   	'hm_issue_pcs'  =>$items['hm_issue_pcs'],

								'hm_issue_gwt'  =>$items['hm_issue_gwt'],

								'hm_issue_nwt'  =>$items['hm_issue_nwt'],

								'hm_issue_lwt'  =>$items['hm_issue_lwt'],

    	                      );

    	       $status=$this->$model->insertData($itemDetails,'ret_po_hm_process_details');

    	       if($status)

    	       {

    	           $this->$model->updateData(array('status'=>3),'po_item_id',$items['po_item_id'],'ret_purchase_order_items');

				   
				 
				    if(!empty($items['stone_details']))
				    {
					   $stone_details = json_decode($items['stone_details'],true);

					   foreach($stone_details as $val)
					   {
						   $stone_data = array(
							   'ret_hm_receipt_id'       => $status,

							   'po_st_id'                  => $val['po_st_id'],

							   'stone_pcs'                 => $val['stone_pcs'],

							   'stone_wt'                  => $val['stone_wt'],

						    );

						   $this->$model->insertData($stone_data, 'ret_po_hm_issue_stone_details');

						   //echo $this->db->last_query();exit;

					   }

				   }

    	       }

    	    }

    	    if($this->db->trans_status()===TRUE)

            {



                $log_data = array(

                        	'id_log'        => $this->session->userdata('id_log'),

                        	'event_date'	=> date("Y-m-d H:i:s"),

                        	'module'      	=> 'HALMARKING',

                        	'operation'   	=> 'Update',

                        	'record'        =>  NULL,

                        	'remark'       	=> 'hm Issued Successfully..'

                        );

                $this->log_model->log_detail('insert','',$log_data);



                $this->db->trans_commit();

                $responseData=array('status'=>true,'message'=>'Halmarking Issued successfully.');

                $this->session->set_flashdata('chit_alert',array('message'=>'QC Issued successfully.','class'=>'success','title'=>'Halmarking Issue'));

            }

            else

            {

                $this->db->trans_rollback();

                $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'Halmarking Issue'));

            }

            echo json_encode($responseData);

        }

	}



	function update_halmarking_receipt()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $req_data   =$_POST['req_data'];

	    $hm_process_id   =$_POST['hm_process_id'];

	    $hm_vendor_ref_id = !empty($_POST['hm_vendor_ref_id']) ? $_POST['hm_vendor_ref_id'] : NULL;

        $this->db->trans_begin();

        $halmarking_status=$this->$model->updateData(array('status'=>2,'hm_received_at'=>date("Y-m-d H:i:s")),'hm_process_id',$hm_process_id,'ret_po_halmark_process');

        if($halmarking_status)

        {

            $total_hm_charges=0;

            foreach($req_data as $items)

    	    {

    	        $total_hm_charges+=$items['halmarking_charges'];

    	        $updData=array(

    	                      'status'              =>4,

    	                      'hm_rejected_pcs'     =>$items['hm_rejected_pcs'],

    	                      'hm_rejected_gwt'     =>$items['hm_rejected_gwt'],

    	                      'hm_rejected_lwt'     =>$items['hm_rejected_lwt'],

    	                      'hm_rejected_nwt'     =>$items['hm_rejected_nwt'],

    	                      'halmarking_charges'  =>$items['halmarking_charges'],

    	                      'is_halmark_from'     =>2,

    	                      'is_halmarked'        =>1

    	                      );



    	       $status=$this->$model->updateData($updData,'po_item_id',$items['po_item_id'],'ret_purchase_order_items');
    	       //print_r($this->db->last_query());exit;

			   $hm_data = array(

					'hm_status'         => 1,  // Completed
					
					'hm_failed_pcs'     =>$items['hm_rejected_pcs'],

					'hm_failed_gwt'     =>$items['hm_rejected_gwt'],

					'hm_failed_lwt'     =>$items['hm_rejected_lwt'],

					'hm_failed_nwt'     =>$items['hm_rejected_nwt'],

					'hm_passed_pcs'     =>$items['hm_received_pcs'],

					'hm_passed_gwt'     =>$items['hm_received_gwt'],

					'hm_passed_nwt'     =>$items['hm_received_nwt'],

					'hm_passed_lwt'     =>$items['hm_received_lwt'],
			   );

			   $hm_status = $this->$model->updateData($hm_data,'hm_receipt_id',$items['hm_receipt_id'],'ret_po_hm_process_details');

			   if($hm_status)
			   {
					if(!empty($items['stone_details'][$key]))
					{

						$stone_details = json_decode($items['stone_details'][$key],true);

						//echo "<pre>"; print_r($stone_details);exit;

						foreach($stone_details as $val)

						{

							$stone_data = array(

								'hm_rejected_pcs'     => $val['po_stone_rejected_pcs'],

								'hm_rejected_wt'      => ($val['po_stone_rejected_wt']!='' ? $val['po_stone_rejected_wt']:0),

								'hm_passed_pcs'       => $val['po_stone_accepted_pcs'],

								'hm_passed_wt'        => $val['po_stone_accepted_wt'],

							);

							$this->$model->updateData($stone_data,'ret_hm_issue_stn_id',$val['ret_hm_issue_stn_id'],'ret_po_hm_issue_stone_details');

						}

					}
			   }

    	    }

    	    if($this->db->trans_status()===TRUE)

            {

                $this->$model->updateData(array('total_hm_charges'=>$total_hm_charges, 'hm_vendor_ref_id' => $hm_vendor_ref_id),'hm_process_id',$hm_process_id,'ret_po_halmark_process');



                //Generate LOT

                /*if($hm_process_id!='')

                {

                    $LotStatus=TRUE;

                    $pur_ord_det=$this->$model->get_purchase_order_hm_details($hm_process_id); // CHECKING QC STATUS AD H.M STATUS

                    foreach($pur_ord_det as $po_items)

                    {

                        if($po_items['status']==0)

                        {

                            $LotStatus=FALSE;

                        }

                    }

                    if($LotStatus)

                    {

                        $this->generate_lot_from_halmarking($hm_process_id);

                    }

                }*/

                //Generate LOT



                $log_data = array(

                        	'id_log'        => $this->session->userdata('id_log'),

                        	'event_date'	=> date("Y-m-d H:i:s"),

                        	'module'      	=> 'HALMARKING',

                        	'operation'   	=> 'Update',

                        	'record'        =>  $hm_process_id,

                        	'remark'       	=> 'HM Updated Successfully..'

                        );

                $this->log_model->log_detail('insert','',$log_data);

                $this->db->trans_commit();

                $responseData=array('status'=>true,'message'=>'Halmarking Receipt added successfully.');

                $this->session->set_flashdata('chit_alert',array('message'=>'HM Receipt successfully.','class'=>'success','title'=>'Halmarking Receipt'));

            }

            else

            {

                $this->db->trans_rollback();

                $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'Halmarking Issue'));

            }

	    }

        echo json_encode($responseData);



	}



    //Halmarking issue/receipt





    //Purchase Payment

    /*function supplier_po_payment($type="", $id="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'popayment/list';

        			$this->load->view('layout/template', $data);

			break;

			case 'add':


					$data['main_content'] = self::VIEW_FOLDER.'popayment/form';

        			$this->load->view('layout/template', $data);

			break;



			case 'purchase_payment_details':

			    $data= $this->$model->purchase_payment_details($_POST);

                echo json_encode($data);

			break;



			case 'save':

			    //echo "<pre>";print_r($_POST);echo "</pre>";exit;

			    $billing        = $_POST;



			    if($billing['bill_type']==1)

			    {

			        $orderData  = $this->$model->get_purOrders($billing['po_ref_no']);

			        $pay_po_id  = $orderData['po_id'];

			    }else

			    {

			        $pay_po_id=NULL;

			    }





			    $pay_refno       = $this->$model->generatePaymentNo();



		        $payData=array(

		                  'pay_sup_id'      => $billing['suppay']['pay_sup_id'],

		                  'bill_type'       => $billing['suppay']['bill_type'],

		                  'type'            => 1,

		                  'pay_refno'       => $pay_refno,

		                  'pay_amt'         => $billing['suppay']['received_amount'],

		                  // 'pay_create_on'   => date("Y-m-d H:i:s"),

		                  'pay_created_by'  => $this->session->userdata('uid'),

		                  );



			    $this->db->trans_begin();

			    $insId = $this->$model->insertData($payData,'ret_po_payment');



                if($insId)

                {



                    //insert into po reference table

                    if($billing['suppay']['bill_type'] ==1) // If againist PO

                    {

                        //ret_supplier_pay_po_details

                        foreach( $billing['popay']['po_item_pay'] as $key => $val)

        		        {



        		            $payDetailData = array(

        		                  'po_pay_id'           => $insId,

        		                  'pay_po_ref_id'       => $billing['popay']['po_item_pay'][$key],

        		                  'pay_po_adj_amount'   => $billing['popay']['curpayable'][$key]

        		              );



        		              	$this->$model->insertData($payDetailData,'ret_supplier_pay_po_details');



        		        }





                    }





                    $net_banking_details = json_decode($billing['suppay']['net_banking'],true);

                    $sales_details		 = json_decode($billing['suppay']['sales_details'],true);



                    if($billing['suppay']['bill_type']==2) // If Advance

                    {

                        $wallet = $this->$model->get_retWallet_details($billing['suppay']['pay_sup_id']);

    	 				if($wallet['status'])

    	 				{

    						$insWallet=array(

    						'id_wallet'	=>$wallet['id_wallet'],

    						'po_pay_id'	=>$insId,

    						'amount'	=>$billing['suppay']['received_amount'],

    						'type'	    =>1,

    						'remarks'   =>'Karigar Advace Amount'

    						 );

    						$this->$model->insertData($insWallet,'ret_karigar_wallet_transcation');

    						//echo "<pre>"; print_r($metal_rate);exit;

    	 				}

    	 				else

    	 				{

    	 				   $wallet_acc=array(

        	 				        'id_karigar'    =>$billing['suppay']['pay_sup_id'],

        	 				        'amount'        =>$billing['suppay']['received_amount'],

        	 				   	    'created_by'    =>$this->session->userdata('uid'),

        						    'created_on'    =>date("Y-m-d H:i:s")

    						    );

     					   $insWalletAcc= $this->$model->insertData($wallet_acc,'ret_karigar_wallet');

     					   //print_r($this->db->last_query());exit;

     					   if($insWalletAcc)

     					   {

    	 					   	$insWallet=array(

        						'id_wallet'	=>$insWalletAcc,

        						'po_pay_id'	=>$insId,

        						'amount'	=>$billing['suppay']['received_amount'],

        						'type'	    =>1,

        						'remarks'   =>'Karigar Advace Amount'

        						 );

        						$this->$model->insertData($insWallet,'ret_karigar_wallet_transcation');

        						//print_r($this->db->last_query());exit;

     					   }



    	 				}

                    }



                    if(sizeof($net_banking_details)>0)

                    {

                        foreach($net_banking_details as $pay)

                        {

                            $netBankingPaymentData[]=array(

                                             'pay_id'           =>$insId,

                                             'type'             =>1,

                                             'pay_mode'         =>$pay['nb_type'],

                                             'payment_amount'   =>$pay['pay_amount'],

                                             'ref_no'           =>($pay['ref_no']!='' ? $pay['ref_no']:NULL),

                                            );

                            $this->$model->insertBatchData($netBankingPaymentData,'ret_po_payment_detail');

                        }

                    }



                    if(sizeof($sales_details)>0)

                    {

                        foreach($sales_details as $pay)

                        {

                            $SalesPaymentData[]=array(

                                             'pay_id'         =>$insId,

                                             'type'           =>2,

                                             'payment_amount' =>$pay['pay_amount'],

                                             'bill_id'        =>$pay['bill_id'],

                                            );

                            $this->$model->insertBatchData($SalesPaymentData,'ret_po_payment_detail');

                        }

                    }







                    if($this->db->trans_status()===TRUE)

                    {

                        $log_data = array(

                                	'id_log'        => $this->session->userdata('id_log'),

                                	'event_date'	=> date("Y-m-d H:i:s"),

                                	'module'      	=> 'PURCHASE PAYMENT',

                                	'operation'   	=> 'insert',

                                	'record'        =>  $orderData['po_id'],

                                	'remark'       	=> 'Purchase Payment Successfully..'

                                );

                        $this->log_model->log_detail('insert','',$log_data);







                        $this->db->trans_commit();

                        $responseData=array('status'=>true,'message'=>'Receipt added successfully.');

                        $this->session->set_flashdata('chit_alert',array('message'=>'Receipt successfully.','class'=>'success','title'=>'Purchase Payment'));

                    }

                    else

                    {

                        echo $this->db->last_query();exit;

                        $this->db->trans_rollback();

                        $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'Purchase Payment'));

                    }

                }else

                {

                        echo $this->db->last_query();exit;

                        $this->db->trans_rollback();

                        $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'Purchase Payment'));



                }



                echo json_encode($responseData);

			 break;



			case 'pendingpos':

			    $pendingpos = $this->$model->get_karigarPendingPos($this->input->post('id_karigar'));

			    echo json_encode(array('status' => TRUE, 'pendingpos' => $pendingpos));

			break;



			case 'paymenthistory':

			    $paymenthistory = $this->$model->get_karigarPosPaidHistory($this->input->post('id_karigar'));

			    echo json_encode(array('status' => TRUE, 'payhistory' => $paymenthistory));

			break;



			case 'paymentacknolodgement':

			    $set_model = "admin_settings_model";

        		$data['comp_details']   = $this->$set_model->get_company();

        		$data['paymentdetails'] = $this->$model->get_po_paid_detail($id);



        		//echo "<pre>"; print_r($data);exit;

        		$this->load->helper(array('dompdf', 'file'));

        	        $dompdf = new DOMPDF();

        			$html = $this->load->view(self::VIEW_FOLDER.'popayment/payment_ack', $data,true);

        			$dompdf->load_html($html);

        			$dompdf->set_paper('A4', "portriat" );

        			$dompdf->render();

        			while(ob_get_level()) ob_end_clean();
        			$dompdf->stream("VendorAck.pdf",array('Attachment'=>0));

			break;



			case 'ajax':

				$list=$this->$model->get_PurchaseSupplierPaymentList($_POST);

			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/halmarking_issue_receipt/list');

		        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;

		}

	}*/





	function supplier_po_payment($type="", $id="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;


		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'popayment/list';

					$data['access'] = $this->$SETT_MOD->get_access('admin_ret_purchase/supplier_po_payment/list');
        			$this->load->view('layout/template', $data);

			break;

			case 'add':

					// CPO-08: gates the ADVANCE bill type and its Common PO panel. Default
					// off, so existing clients keep the BILL / RECEIPT pair they have today.
					$data['common_po_enabled'] = $this->$model->get_common_po_setting('common_po_enabled', '0');

					$data['main_content'] = self::VIEW_FOLDER.'popayment/form';

        			$this->load->view('layout/template', $data);

			break;



			case 'purchase_payment_details':

			    $data= $this->$model->purchase_payment_details($_POST);

                echo json_encode($data);

			break;



			case 'get_supplier_pay_details':

			    $data= $this->$model->get_supplier_pay_details($_POST);

                echo json_encode($data);

			break;



			case 'supplier_advance_details':

			    $data= $this->$model->get_supplier_advance_details($_POST);

                echo json_encode($data);

			break;



			case 'get_purchase_return_entries':

			    $data= $this->$model->get_purchase_return_entries_for_payment($_POST);

                echo json_encode($data);

			break;



			case 'edit':

				/* SPS-10: edit renders the SAME template as add, so it needs the same
				   view data, or the Common PO branches in the template evaluate against
				   an undefined variable. */
				$data['common_po_enabled'] = $this->$model->get_common_po_setting('common_po_enabled', '0');

            	$data['main_content'] = self::VIEW_FOLDER.'popayment/form';



            	$this->load->view('layout/template', $data);



            break;









            case 'po_pay_details':



            	$data = $this->$model->get_po_payment($id);



            	echo json_encode($data);



            break;



			case 'cancel_pay_entry':

			    $ho              =  $this->$model->get_headOffice();

        		$dCData          =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

        		$bill_date       =  ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

        		$pay_id      = $_POST['pay_id'];

			    /* CPO-15 / D4: an advance still consumed by a live supplier bill cannot be
			       cancelled — the bill has to go first. Cascading the reversal upward would
			       let a payment cancellation silently rewrite a posted supplier bill. */
			    $cpo_block = $this->_block_common_po_payment_cancel($pay_id);
			    if ($cpo_block !== NULL) {
			        echo json_encode(array('status'=>FALSE,'message'=>$cpo_block,'msg'=>$cpo_block));
			        return;
			    }

			    /* SPS-13: what a cancellation has to undo.

			       Flipping pay_status to 2 already removes this payment from every
			       balance that joins ret_po_payment and filters on pay_status = 1 - the
			       bill allocations in ret_po_bill_payment_details, and (after SPS-13) the
			       Dr/Cr-note and purchase-return adjustments. Those rows are deliberately
			       LEFT in place rather than deleted, so the reversal stays auditable
			       (AC #8) and a cancelled payment can still be shown in the settlement
			       history with its allocations.

			       The karigar wallet is the exception: a Common PO advance credits it
			       with an UPDATE (updateWalletData '+') rather than through a status-
			       filtered join, so nothing about pay_status can undo it. It has to be
			       debited back explicitly, or cancelling an advance leaves the supplier
			       wallet permanently overstated by the advance amount. */
			    $sps_cancel_pay = $this->$model->get_po_payment_row($pay_id);

			    if (!$sps_cancel_pay) {
			        echo json_encode(array('status'=>FALSE,'message'=>'Payment not found','msg'=>'Payment not found'));
			        return;
			    }

			    // Cancelling a cancelled payment would debit the wallet a second time.
			    if ((int)$sps_cancel_pay['pay_status'] !== 1) {
			        echo json_encode(array('status'=>FALSE,'message'=>'This payment is already cancelled','msg'=>'This payment is already cancelled'));
			        return;
			    }

			    $updData=array(

                    'pay_status'        => 2,

                    'cancel_reason'     => $_POST['cancel_reason'],

                    'cancelled_date'    => $bill_date,

                    'cancelled_by'      => $this->session->userdata('uid')

                    );

                    $this->db->trans_begin();

                    $ret_status = $this->$model->updateData($updData,'pay_id',$pay_id,'ret_po_payment');

                    /* SPS-13: debit back the wallet credit a Common PO advance made.
                       Inside the same transaction as the status flip - a cancellation that
                       marked the payment dead but left the wallet credited would overstate
                       the supplier's advance balance with no trace of why. */
                    if ((int)$sps_cancel_pay['bill_type'] === 4 && (float)$sps_cancel_pay['pay_amt'] > 0)
                    {
                        $sps_wallet = $this->$model->get_retWallet_details($sps_cancel_pay['pay_sup_id']);

                        if (!empty($sps_wallet['status']))
                        {
                            $this->$model->updateWalletData(array(
                                'amount'    => (float)$sps_cancel_pay['pay_amt'],
                                'id_wallet' => $sps_wallet['id_wallet'],
                            ), '-');

                            /* type 2 mirrors the type 1 credit written on save, so the
                               wallet transaction log shows the advance and its reversal
                               as a matched pair. */
                            $this->$model->insertData(array(
                                'id_wallet' => $sps_wallet['id_wallet'],
                                'po_pay_id' => $pay_id,
                                'amount'    => number_format((float)$sps_cancel_pay['pay_amt'], 2, '.', ''),
                                'type'      => 2,
                                'remarks'   => 'Reversal - advance payment '.$sps_cancel_pay['pay_refno'].' cancelled',
                            ), 'ret_karigar_wallet_transcation');
                        }
                    }

                    if($this->db->trans_status()===TRUE)

                    {

                        $this->db->trans_commit();

						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'SUPPLIER PAYMENT',

							'operation'     => 'Cancel',

							'record'        =>  $pay_id,

							'remark'        => 'Supplier payment Cancelled successfully'

							);

	                      $this->log_model->log_detail('insert','',$log_data);

                        $this->session->set_flashdata('chit_alert',array('message'=>'Payment Cancelled Successfully','class'=>'success','title'=>'Purchase Payment'));

                        $return_data=array('status'=>TRUE,'message'=>'Payment cancelled successfully');

                    }

                    else

                    {

                        /* SPS-13: this branch used to `echo $this->db->last_query(); exit;`
                           BEFORE the rollback - so a failed cancellation printed raw SQL to
                           the operator and returned without rolling back, leaving the
                           transaction to be discarded by the connection teardown instead.
                           That is now actively dangerous: the wallet reversal above runs in
                           this transaction, and a half-applied cancellation would leave the
                           payment live but the wallet already debited. Roll back first. */
                        $this->db->trans_rollback();

                        log_message('error', 'SPS: supplier payment cancel failed for pay_id '
                            . $pay_id . ' - ' . $this->db->last_query());

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Purchase Payment'));

                        $return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

                    }

                echo json_encode($return_data);

			break;



			case 'save':

			    $billing        = $_POST['billing'];

				$net_banking_details = json_decode($billing['net_banking'], true);
				if (!is_array($net_banking_details)) $net_banking_details = [];

				$cheque_details = json_decode($billing['chq_pay'], true);
				if (!is_array($cheque_details)) $cheque_details = [];

				$checked_count = 0;
				$total_entries = isset($billing['total_entries']) ? (int)$billing['total_entries'] : 0;
				for ($i = 0; $i <= $total_entries; $i++)
				{
					if (isset($billing['bill_checked_' . $i]) && (int)$billing['bill_checked_' . $i] == 1)
					{
						$checked_count++;
					}
				}

				/* This guard rejects a payment that settles more than one bill using more
				   than one payment mode. It was added by 6996bd93 among a batch of
				   defensive input fixes, with no business rationale recorded.

				   Its likely cause is now gone: the PAYMENT branch of the supplier and
				   combined ledger views multiplied a payment by the number of allocations
				   (or of modes), which made exactly this combination report wrong figures.
				   That cartesian is fixed in database/views (SPS-04), so the guard is
				   probably obsolete. It is LEFT IN PLACE rather than removed, because
				   "probably obsolete" is not a reason to lift a restriction on how supplier
				   money is recorded across 70+ deployments - lifting it should be its own
				   change, confirmed against a client. It does not block any acceptance
				   criterion: a partial payment against a single bill is unaffected however
				   many modes it uses, since $checked_count is then 1. */
				if ($checked_count > 1
					&& (($billing['cash_amount'] && !empty($net_banking_details)) || ($billing['cash_amount'] && !empty($cheque_details)) || (!empty($net_banking_details) && !empty($cheque_details))))
				{
					$responseData = array('status' => false, 'message' => 'Unable to proceed Multiple bills with multiple payment modes');

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed Multiple bills with multiple payment modes', 'class' => 'danger', 'title' => 'Purchase Payment'));

					echo json_encode($responseData);
					return;
				}

				$pay_refno       = $this->$model->generatePaymentNo();

				// Calculate actual payment amount (only cash + net banking + cheque)
				$cash_amt = floatval($billing['cash_amount']);
				$nb_amt = 0;
				$chq_amt = 0;
				$nb_details = json_decode($billing['net_banking'], true);
				if(!empty($nb_details)) {
					foreach($nb_details as $nb) {
						$nb_amt += floatval($nb['pay_amount']);
					}
				}
				$chq_details = json_decode($billing['chq_pay'], true);
				if(!empty($chq_details)) {
					foreach($chq_details as $chq) {
						$chq_amt += floatval($chq['payment_amount']);
					}
				}
				$actual_pay_amt = $cash_amt + $nb_amt + $chq_amt;

				/* =============================================================
				   SPS-09: settlement resolution and allocation validation.
				   =============================================================
				   Everything below runs BEFORE trans_begin(), so a rejected payment
				   leaves no partial write and no open transaction behind.

				   Why this exists: the allocation loop further down used to take
				   $billing['amount_paid_'.$i] straight from $_POST and write it to
				   ret_po_bill_payment_details with no check at all. That was tolerable
				   only while the screen refused part-payment and always posted the full
				   bill value. Once an operator can type the amount, an unchecked POST
				   field decides how much of a supplier bill is marked settled - so it is
				   re-derived from the database here, never trusted from the form.
				   =============================================================  */
				$sps_tolerance    = Ret_purchase_order_model::SETTLEMENT_TOLERANCE;
				$sps_bill_type    = (int)$billing['bill_type'];
				$sps_allocations  = array();
				$sps_error        = '';

				/* A Common PO advance (bill_type 4) is paid against a purchase commitment,
				   not against pending bills. It has no allocation grid and is validated by
				   the $cpo_check block below instead. */
				$sps_applies = ($sps_bill_type !== 4);

				if ($sps_applies)
				{
					/* Adjustments settle bill value without moving cash, so they count
					   towards what the allocations are allowed to add up to. $actual_pay_amt
					   is deliberately cash + net banking + cheque ONLY, so it cannot be
					   compared against the allocation total on its own. */
					$sps_debit_adj  = 0;
					$sps_return_adj = 0;

					$sps_dbg_adj = json_decode($billing['debit_adjustments'], true);
					if (is_array($sps_dbg_adj)) {
						foreach ($sps_dbg_adj as $adj) {
							if (!empty($adj['crdrid'])) {
								$sps_debit_adj += floatval($adj['amount']);
							}
						}
					}

					$sps_ret_adj = json_decode($billing['purchase_return_adjustments'], true);
					if (is_array($sps_ret_adj)) {
						foreach ($sps_ret_adj as $adj) {
							if (!empty($adj['pur_return_id'])) {
								$sps_return_adj += floatval($adj['amount']);
							}
						}
					}

					$sps_settle_total = round($actual_pay_amt + $sps_debit_adj + $sps_return_adj, 2);

					if ($sps_settle_total <= 0) {
						echo json_encode(array('status'=>FALSE,'msg'=>'Enter the payment amount','message'=>'Enter the payment amount'));
						return;
					}

					/* The same parameters the screen used to build the grid. The outstanding
					   figures MUST be re-derived under identical filters, or the ceiling
					   validated against would not be the balance the operator was shown. */
					$sps_ctx = array(
						'id_karigar'      => $billing['id_karigar'],
						'bill_type'       => $billing['bill_type'],
						'grn_type'        => ($sps_bill_type === 2 ? '4' : '1'),
						'opening_checked' => isset($_POST['opening_checked']) ? (string)$_POST['opening_checked'] : '0',
					);

					{
						/* Validate every row the operator ticked against the balance re-read
						   from the database. */
						$sps_outstanding = $this->$model->get_payment_outstanding_map($sps_ctx);
						$sps_is_opening  = ($sps_ctx['opening_checked'] === '1');
						$sps_checked     = array();

						for ($i = 0; $i <= $total_entries; $i++)
						{
							if (!isset($billing['bill_checked_'.$i]) || (int)$billing['bill_checked_'.$i] === 0) {
								continue;
							}
							$sps_checked[] = $i;
						}

						if (empty($sps_checked)) {
							echo json_encode(array('status'=>FALSE,'msg'=>'Select at least one bill to settle','message'=>'Select at least one bill to settle'));
							return;
						}

						foreach ($sps_checked as $i)
						{
							$row_type    = isset($billing['type_'.$i]) ? $billing['type_'.$i] : 'bill';
							$row_ref     = isset($billing['bill_ref_no_'.$i])  ? $billing['bill_ref_no_'.$i]  : 0;
							$row_ratecut = isset($billing['bill_rate_no_'.$i]) ? $billing['bill_rate_no_'.$i] : '';
							$row_amount  = isset($billing['amount_paid_'.$i])  ? round(floatval($billing['amount_paid_'.$i]), 2) : 0;

							/* Opening balance, single row: the screen lets the operator type a
							   free amount against an opening balance rather than settling it in
							   full, and posts no meaningful per-row figure. Preserved only for
							   the single-row case - the previous code applied the FULL payment
							   to EVERY checked opening row, so two ticked rows each recorded the
							   whole payment and the opening balance was written down twice. */
							if ($sps_is_opening && count($sps_checked) === 1 && $row_amount <= 0) {
								$row_amount = $sps_settle_total;
							}

							if ($row_amount <= 0) {
								$sps_error = 'Enter a payment amount for every selected bill';
								break;
							}

							$key         = $this->$model->settlement_row_key($row_type, $row_ref, $row_ratecut);
							$outstanding = isset($sps_outstanding[$key]) ? $sps_outstanding[$key]['outstanding'] : NULL;

							if ($outstanding === NULL) {
								/* Either the reference does not belong to this supplier, or it was
								   settled by somebody else while this screen sat open. Both are a
								   refusal - this is the check that stops a crafted po_id being
								   written against an unrelated supplier's bill. */
								$sps_error = 'Bill reference '.htmlspecialchars((string)$row_ref)
								           . ' is no longer outstanding for this supplier. Reload the screen and try again.';
								break;
							}

							/* Paying MORE than the bill still owes is refused. Paying LESS is
							   the point of the feature - a part-payment leaves the remainder
							   outstanding for a later settlement, so there is no lower bound
							   to check here. */
							if ($row_amount > $outstanding + $sps_tolerance) {
								$sps_error = 'Amount '.number_format($row_amount, 2, '.', '')
								           . ' exceeds the outstanding balance of '.number_format($outstanding, 2, '.', '')
								           . ' on '.$sps_outstanding[$key]['referenceno'];
								break;
							}

							$sps_allocations[] = array(
								'type'    => $row_type,
								'ref_id'  => $row_ref,
								'ratecut' => $row_ratecut,
								'amount'  => $row_amount,
							);
						}
					}

					/* The allocations must account for the settlement exactly. Under-allocating
					   loses money into a payment that settles nothing; over-allocating writes
					   off more supplier debt than was actually paid. Either way the supplier
					   ledger and the outstanding report stop reconciling (AC #6). */
					if ($sps_error === '')
					{
						$sps_alloc_total = 0;
						foreach ($sps_allocations as $alloc) {
							$sps_alloc_total += $alloc['amount'];
						}
						$sps_alloc_total = round($sps_alloc_total, 2);

						if (empty($sps_allocations)) {
							$sps_error = 'This supplier has no outstanding bill to settle.';
						} elseif (abs($sps_alloc_total - $sps_settle_total) > $sps_tolerance) {
							$sps_error = 'Allocated total '.number_format($sps_alloc_total, 2, '.', '')
							           . ' does not match the payment of '.number_format($sps_settle_total, 2, '.', '')
							           . '. Reload the screen and try again.';
						}
					}

					if ($sps_error !== '') {
						$this->session->set_flashdata('chit_alert', array('message'=>$sps_error,'class'=>'danger','title'=>'Purchase Payment'));
						echo json_encode(array('status'=>FALSE,'msg'=>$sps_error,'message'=>$sps_error));
						return;
					}
				}

				$payData = array(

							'pay_sup_id'      =>$billing['id_karigar'],

							'bill_type'       =>$billing['bill_type'],

							'pay_refno'       =>$pay_refno,

							/* number_format for the same reason as the allocation rows below:
							   insertData() swaps a float 0.0 for the column default, and pay_amt is
							   a NOT NULL decimal with no default, so the substitute is '' and STRICT
							   mode rejects the insert. Reachable whenever a settlement is funded
							   entirely by debit-note or purchase-return adjustments and no cash,
							   net banking or cheque moves at all. */
							'pay_amt'         =>number_format((float)$actual_pay_amt, 2, '.', ''),

							'pay_narration'   =>($billing['remark']!='' ? $billing['remark']:NULL),

							'pay_create_on'   =>date("Y-m-d H:i:s"),

							'pay_created_by'=>$this->session->userdata('uid'),

							);



					$this->db->trans_begin();

					/* CPO-08: an ADVANCE (bill_type 4) is paid against a purchase
					   commitment, not an invoice. The PO link and the amount are both
					   re-validated against the database here — the screen caps the amount
					   too, but a readonly balance field is a display convention, not a
					   control. $cpo_check is reused after the insert to post the ledger. */
					$cpo_check = NULL;
					if (isset($billing['bill_type']) && (int)$billing['bill_type'] === 4) {

						$cpo_ref = isset($billing['common_po_ref']) ? (int)$billing['common_po_ref'] : 0;
						$cpo_check = ($cpo_ref > 0) ? $this->$model->getCommonPoAdvancePosition($cpo_ref) : NULL;

						if (!$cpo_check) {
							echo json_encode(array('status'=>FALSE,'msg'=>'Select a valid Common Purchase Order'));
							return;
						}
							if ((int)$cpo_check['common_po_approved'] !== 1) {
							echo json_encode(array('status'=>FALSE,'msg'=>'An advance cannot be paid against an unapproved Purchase Order'));
							return;
						}
						if ((int)$cpo_check['id_karigar'] !== (int)$billing['id_karigar']) {
							echo json_encode(array('status'=>FALSE,'msg'=>'This Purchase Order belongs to a different supplier'));
							return;
						}
						if ((int)$cpo_check['common_po_status'] >= 3) {
							echo json_encode(array('status'=>FALSE,'msg'=>'This Purchase Order is already billed or closed'));
							return;
						}
						if ($actual_pay_amt <= 0) {
							echo json_encode(array('status'=>FALSE,'msg'=>'Enter the advance amount'));
							return;
						}

						// D1: an Unfixed PO carries no agreed value, so no ceiling applies.
						$cpo_unfixed = ((int)$cpo_check['rate_type_fix'] === 2);
						$cpo_block   = $this->$model->get_common_po_setting('common_po_advance_exceed_block', '1');

						if (!$cpo_unfixed && $cpo_block == 1) {
							$cpo_balance = round((float)$cpo_check['po_value'] - (float)$cpo_check['advance_paid'], 2);
							if ($actual_pay_amt > $cpo_balance + 0.05) {
								echo json_encode(array('status'=>FALSE,'msg'=>'Advance exceeds the outstanding balance of '.number_format($cpo_balance,2,'.','').' on this Purchase Order'));
								return;
							}
						}

						$payData['common_po_ref'] = $cpo_ref;
					}

					$insId=$this->$model->insertData($payData,'ret_po_payment');


					if($insId)

					{

						/* CPO-09: post the advance to the supplier ledger.
						   Only the Common PO path is posted here. The generic wallet block
						   further down is commented out for every supplier and is left exactly
						   as found — reviving it would change behaviour for all existing
						   payments across every deployment, with no record of why it was
						   disabled. That belongs in its own ticket. */
						if ($cpo_check) {

							$cpo_wallet_id = 0;
							$cpo_wallet    = $this->$model->get_retWallet_details($billing['id_karigar']);

							if (!empty($cpo_wallet['status'])) {
								$this->$model->updateWalletData(array(
									'amount'    => $actual_pay_amt,
									'id_wallet' => $cpo_wallet['id_wallet'],
								), '+');
								$cpo_wallet_id = $cpo_wallet['id_wallet'];
							} else {
								// number_format, not a float: insertData() replaces any value PHP
								// considers empty() with the column default, and its guard that
								// preserves a legitimate zero tests === 0 / === '0', which a
								// float 0.0 fails.
								$cpo_wallet_id = $this->$model->insertData(array(
									'id_karigar' => (int)$billing['id_karigar'],
									'amount'     => number_format($actual_pay_amt, 2, '.', ''),
									'created_by' => $this->session->userdata('uid'),
									'created_on' => date('Y-m-d H:i:s'),
								), 'ret_karigar_wallet');
							}

							if ($cpo_wallet_id) {
								$this->$model->insertData(array(
									'id_wallet' => $cpo_wallet_id,
									'po_pay_id' => $insId,
									'amount'    => number_format($actual_pay_amt, 2, '.', ''),
									'type'      => 1,
									'remarks'   => 'Common PO advance - ' . $cpo_check['pur_no'],
								), 'ret_karigar_wallet_transcation');
							}

							// Open -> Advance Paid. Stays 1 for a part advance too; the
							// remaining balance is derived from pay_amt, never from the status.
							$this->$model->updateData(
								array('common_po_status' => 1),
								'id_customerorder', (int)$cpo_check['id_customerorder'], 'customerorder'
							);
						}



						//$sales_details		 = json_decode($billing['sales_details'],true);

						//$adv_details		 = json_decode($billing['adv_details'],true);



						// if($billing['bill_type']==2)

						// {

						//     $wallet=$this->$model->get_retWallet_details($billing['id_karigar']);

							// if($wallet['status'])

							// {

							//     $updStatus=$this->$model->updateWalletData(array('amount'=>$billing['tot_amt_received'],'id_wallet'=>$wallet['id_wallet']),'+');



							// 	$insWallet=array(

							// 	'id_wallet'	=>$wallet['id_wallet'],

							// 	'po_pay_id'	=>$insId,

							// 	'amount'	=>$billing['tot_amt_received'],

							// 	'type'	    =>1,

							// 	'remarks'   =>($billing['remark']!='' ? $billing['remark']:NULL)

							// 	 );

							// 	$this->$model->insertData($insWallet,'ret_karigar_wallet_transcation');

							// 	//echo "<pre>"; print_r($metal_rate);exit;

							// }

							// else

							// {

							//    $wallet_acc=array(

							// 	        'id_karigar'    =>$billing['id_karigar'],

							// 	        'amount'        =>$billing['tot_amt_received'],

							// 	   	    'created_by'    =>$this->session->userdata('uid'),

							// 		    'created_on'    =>date("Y-m-d H:i:s")

							// 	    );

							//    $insWalletAcc= $this->$model->insertData($wallet_acc,'ret_karigar_wallet');

							//    //print_r($this->db->last_query());exit;

							//    if($insWalletAcc)

							//    {

							// 	   	$insWallet=array(

							// 		'id_wallet'	=>$insWalletAcc,

							// 		'po_pay_id'	=>$insId,

							// 		'amount'	=>$billing['tot_amt_received'],

							// 		'type'	    =>1,

							// 		'remarks'   =>($billing['remark']!='' ? $billing['remark']:NULL)

							// 		 );

							// 		$this->$model->insertData($insWallet,'ret_karigar_wallet_transcation');

							// 		//print_r($this->db->last_query());exit;

							//    }



							// }

						// }

						if($billing['cash_amount']!=0)
						{

							$cash_payment_data = array(

												'pay_id'           =>$insId,

												'type'             =>4,

												'pay_mode'         =>'CSH',

												'payment_amount'   =>$billing['cash_amount'],

												);

							$this->$model->insertData($cash_payment_data,'ret_po_payment_detail');

						}



						if(!empty($net_banking_details))

						{

							foreach($net_banking_details as $pay)

							{

								$netBankingPaymentData[]=array(

												'pay_id'           =>$insId,

												'type'             =>1,

												'pay_mode'         =>$pay['nb_type'],

												'payment_amount'   =>$pay['pay_amount'],

												'ref_no'           =>($pay['ref_no']!='' ? $pay['ref_no']:NULL),

												'id_bank'          =>($pay['id_bank']!='' ? $pay['id_bank']:NULL),

												'ref_date'          =>($pay['nb_date']!='' ? $pay['nb_date']:date("Y-m-d")),

												);

							}

							$this->$model->insertBatchData($netBankingPaymentData,'ret_po_payment_detail');

						}


						if(!empty($cheque_details))

						{
							// flag for update payee name if not exist in karigar table
							$i = 0;

							foreach($cheque_details as $chq_pay)

							{
								$i++;
								$cheque_deposit_date = ($chq_pay['cheque_date']!='' ?date_create($chq_pay['cheque_date']) :NULL);

								$cheque_date = ($chq_pay['cheque_date']!='' ? date_format($cheque_deposit_date,"Y-m-d"):NULL);

								$arraychqPay[]=array(

									'pay_id'           =>$insId,

									'payment_amount'=>$chq_pay['payment_amount'],

									'type'			=>5,

									'cheque_date'		=>($chq_pay['cheque_date']!='' ? $cheque_date:NULL),

									'pay_mode'	=>'CHQ',

									'cheque_no'		=>($chq_pay['cheque_no']!='' ? $chq_pay['cheque_no']:NULL),

									'id_bank'		=>($chq_pay['bank_name']!='' ? $chq_pay['bank_name']:NULL),

									'bank_branch'	=>($chq_pay['bank_branch']!='' ? $chq_pay['bank_branch']:NULL),

									'payee_name'	=>($chq_pay['payee_name']!='' ? $chq_pay['payee_name']:NULL),


								);
								if($chq_pay['payee_name'])
								{
									$data=array(
										'acc_holder_name'         =>$chq_pay['payee_name'],
									);
									$update_status = $this->$model->updateData($data,'id_karigar',$billing['id_karigar'], 'ret_karigar');
								}

								//  print_r($arraychqPay);exit;

							}

							if(!empty($arraychqPay))
							{

								$chqPayInsert = $this->$model->insertBatchData($arraychqPay,'ret_po_payment_detail');



							}

						}

						/* SPS-09: write the allocations validated before trans_begin().
						   The loop no longer reads $_POST - $sps_allocations is either the
						   operator's selection after every row was checked against the balance
						   re-read from the database, or the server's own FIFO split in
						   Cumulative mode. The reference column a row lands in still depends on
						   what kind of item it settles, exactly as before. */
						foreach($sps_allocations as $sps_alloc)
						{
							$alloc_type   = $sps_alloc['type'];
							$alloc_ref    = $sps_alloc['ref_id'];
							$alloc_amount = $sps_alloc['amount'];

							$po_ids             = $alloc_ref;
							$opening_payment_id = 0;
							$crdrid_val         = NULL;
							$rate_fix_id_val    = NULL;

							if($alloc_type == 'opening')
							{
								$po_ids             = 0;
								$opening_payment_id = $alloc_ref;
							}
							elseif($alloc_type == 'crdr_credit')
							{
								$po_ids     = 0;
								$crdrid_val = $alloc_ref;
							}
							elseif($alloc_type == 'rate_fix')
							{
								$po_ids          = 0;
								$rate_fix_id_val = $alloc_ref;
							}

							/* Every reference column here is an INT in the table, but the pending
							   -bills union does not emit integers for all of them: the bill and
							   Dr/Cr-credit branches select '' as ratecut, and the opening-balance
							   branch selects the literal 'OPENING'. Writing those straight through
							   hits STRICT_TRANS_TABLES with

							       Incorrect integer value: 'OPENING' for column 'id_supplier_rate_cut'

							   which CodeIgniter turns into a database-error page, so the whole save
							   fails with "Unable to proceed the requested process". Non-numeric
							   references are therefore normalised to 0 for the NOT NULL columns and
							   to NULL for the nullable ones. */
							$alloc_ratecut = (isset($sps_alloc['ratecut']) && is_numeric($sps_alloc['ratecut']))
							                 ? (int)$sps_alloc['ratecut'] : 0;

							$individual_payment=array(
								'pay_id'         				 => (int)$insId,
								'po_id'				 			 => (is_numeric($po_ids) ? (int)$po_ids : 0),
								'id_supplier_rate_cut'			 => $alloc_ratecut,
								'rate_fix_id'					 => (is_numeric($rate_fix_id_val) ? (int)$rate_fix_id_val : NULL),
								'crdrid'						 => (is_numeric($crdrid_val) ? (int)$crdrid_val : NULL),
								/* number_format, not a raw float: insertData() replaces any value
								   PHP considers empty() with the column default, and its guard that
								   preserves a legitimate zero tests === 0 / === '0', which a float
								   0.0 fails. bill_amount and total_amount are NOT NULL doubles with
								   no default, so the substituted default is '' - which STRICT mode
								   rejects outright. A settlement funded entirely by adjustments has
								   $actual_pay_amt == 0.0 and would hit exactly that. */
								'bill_amount' 					 => number_format($alloc_amount, 2, '.', ''),
								'opening_id' 					 => (is_numeric($opening_payment_id) ? (int)$opening_payment_id : 0),
								'total_amount'   				 => number_format($actual_pay_amt, 2, '.', ''),
								'created_on'					 => date("Y-m-d H:i:s"),
								'created_by'					 => $this->session->userdata('uid')
							);

							$this->$model->insertData($individual_payment,'ret_po_bill_payment_details');

						}

						// Process debit note adjustments
					if(!empty($billing['debit_adjustments']))
					{
						$debit_adjustments = json_decode($billing['debit_adjustments'], true);
						if(!empty($debit_adjustments))
						{
							foreach($debit_adjustments as $adj)
							{
								// Insert partial adjustment into tracking table
								if(!empty($adj['crdrid']) && floatval($adj['amount']) > 0)
								{
									$adj_data = array(
										'crdrid'          => $adj['crdrid'],
										'po_id'           => NULL,
										'pay_id'          => $insId,
										'adjusted_amount' => floatval($adj['amount']),
										'created_by'      => $this->session->userdata('uid'),
										'created_on'      => date("Y-m-d H:i:s")
									);
									$this->$model->insertData($adj_data, 'ret_crdr_note_po_adj');
								}
							}
						}
							
					}

					// Process purchase return adjustments (NO crdr entry created)
					if(!empty($billing['purchase_return_adjustments']))
					{
						$return_adjustments = json_decode($billing['purchase_return_adjustments'], true);
						if(!empty($return_adjustments))
						{
							foreach($return_adjustments as $ret_adj)
							{
								if(!empty($ret_adj['pur_return_id']) && floatval($ret_adj['amount']) > 0)
								{
									$adj_data = array(
										'pur_return_id'   => $ret_adj['pur_return_id'],
										'po_id'           => NULL,
										'pay_id'          => $insId,
										'adjusted_amount'  => floatval($ret_adj['amount']),
										'created_by'      => $this->session->userdata('uid'),
										'created_on'      => date("Y-m-d H:i:s")
									);
									$this->$model->insertData($adj_data, 'ret_purchase_return_po_adj');
								}
							}
						}
					}
					}
					else
					{
							// echo $this->db->last_query();exit;

							// $this->db->trans_rollback();

							$responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

							$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'Purchase Payment'));

					}





                    if($this->db->trans_status()===TRUE)
                    {

                        $log_data = array(

                                	'id_log'        => $this->session->userdata('id_log'),

                                	'event_date'	=> date("Y-m-d H:i:s"),

                                	'module'      	=> 'SUPPLIER  PAYMENT',

                                	'operation'   	=> 'insert',

                                	'record'        =>  $insId,

                                	'remark'       	=> 'Supplier Payment Successfully..'

                                );

                        $this->log_model->log_detail('insert','',$log_data);







                        $this->db->trans_commit();

                        $responseData=array('status'=>true,'message'=>'Receipt added successfully.','id'=>$insId);

                        $this->session->set_flashdata('chit_alert',array('message'=>'Receipt successfully.','class'=>'success','title'=>'Purchase Payment'));

                    }

                    else

                    {

                        $this->db->trans_rollback();

                        $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'Purchase Payment'));

                    }

                echo json_encode($responseData);

			 break;



			 case 'update':



            	$updData = $_POST;



            	$id=$updData['billing']['pay_id'];

				/* SPS-14: an edit may not silently change how much of a bill is settled.

				   This path updates pay_amt and rewrites ret_po_payment_detail, but it has
				   never touched ret_po_bill_payment_details - the per-bill allocations. So
				   changing the amount here left the allocations at their original figures:
				   the payment said one thing, the bills said another, and the supplier
				   outstanding silently stopped reconciling with the ledger.

				   Rewriting the allocations is not possible from this screen - it posts no
				   bill grid, so there is nothing to say which bill absorbs the difference,
				   and guessing would be worse than refusing. The amount is therefore frozen
				   once the payment has allocations; correcting it means cancelling the
				   payment and re-entering it, which leaves a proper audit trail either way.

				   Narration and the payment-mode breakdown remain editable, because neither
				   changes what was settled against which bill. */
				$sps_edit_existing = $this->$model->get_po_payment_row($id);
				$sps_edit_allocs   = $this->$model->get_payment_allocation_total($id);
				$sps_edit_newamt   = round(floatval($updData['billing']['tot_amt_received']), 2);
				$sps_edit_oldamt   = $sps_edit_existing ? round((float)$sps_edit_existing['pay_amt'], 2) : 0;
				$sps_edit_tol      = Ret_purchase_order_model::SETTLEMENT_TOLERANCE;

				if ($sps_edit_allocs > 0 && abs($sps_edit_newamt - $sps_edit_oldamt) > $sps_edit_tol)
				{
					$sps_edit_msg = 'This payment is already allocated against '
					              . number_format($sps_edit_allocs, 2, '.', '')
					              . ' of supplier bills, so its amount cannot be changed here. '
					              . 'Cancel the payment and enter it again with the correct amount.';

					$this->session->set_flashdata('chit_alert',array('message'=>$sps_edit_msg,'class'=>'danger','title'=>'Purchase Payment'));
					echo json_encode(array('status'=>FALSE,'msg'=>$sps_edit_msg,'message'=>$sps_edit_msg));
					return;
				}

            	$data=array(



            		'pay_amt'         =>$updData['billing']['tot_amt_received'],



            		'pay_narration'   =>($updData['billing']['remark']!='' ? $updData['billing']['remark']:NULL),



            	);



            	$this->db->trans_begin();





            	$update_status = $this->$model->updateData($data,'pay_id',$id, 'ret_po_payment');





            	//print_r($this->db->last_query());exit;











            	if($update_status)

            	{



            		$net_banking_details = json_decode($updData['billing']['net_banking'],true);



            		$nb_delete_id = $this->$model->deleteData("pay_id",$id,'ret_po_payment_detail');



            		if(sizeof($net_banking_details)>0)

            		{



            			foreach($net_banking_details as $pay)

            			{

            				$netBankingPaymentData[]=array(

            					'pay_id'           =>$id,

            					'type'             =>1,

            					'pay_mode'         =>$pay['nb_type'],

            					'payment_amount'   =>$pay['pay_amount'],

            					'ref_no'           =>($pay['ref_no']!='' ? $pay['ref_no']:NULL),

            					'id_bank'          =>($pay['id_bank']!='' ? $pay['id_bank']:NULL),

            					'ref_date'          =>($pay['nb_date']!='' ? $pay['nb_date']:date("Y-m-d")),

            				);



            			}



            			$this->$model->insertBatchData($netBankingPaymentData,'ret_po_payment_detail');



            		}



            	}





            		if($this->db->trans_status()===TRUE)

                                {



                                    $this->db->trans_commit();

									$log_data = array(

										'id_log'        => $this->session->userdata('id_log'),

										'event_date'    => date("Y-m-d H:i:s"),

										'module'        => 'SUPPLIER PAYMENT',

										'operation'     => 'Update',

										'record'        =>  $update_status,

										'remark'        => 'Supplier payment Updated successfully'

										);

							         $this->log_model->log_detail('insert','',$log_data);

                                    $responseData=array('status'=>true,'message'=>'Receipt updated successfully.');

                                    $this->session->set_flashdata('chit_alert',array('message'=>'Receipt updated successfully.','class'=>'success','title'=>'Purchase Payment'));

                                }

                                else

                                {

                                    echo $this->db->last_query();exit;

                                    $this->db->trans_rollback();

                                    $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                                    $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'Purchase Payment'));

                                }





                            echo json_encode($responseData);



            break;



			case 'pendingpos':

			    $pendingpos = $this->$model->get_karigarPendingPos($this->input->post('id_karigar'));

			    echo json_encode(array('status' => TRUE, 'pendingpos' => $pendingpos));

			break;



			case 'paymenthistory':

			    $paymenthistory = $this->$model->get_karigarPosPaidHistory($this->input->post('id_karigar'));

			    echo json_encode(array('status' => TRUE, 'payhistory' => $paymenthistory));

			break;



			case 'paymentacknolodgement':

			    $set_model = "admin_settings_model";

        		$data['comp_details']   = $this->$set_model->get_company();

        		$data['paymentdetails'] = $this->$model->get_po_paid_detail($id);

				$data['payment'] = $this->$model->get_po_paid_payment($id);

				/* SPS-17: which bills this payment settled, and what is left on each. The
				   acknowledgement showed only the total and the payment-mode breakdown, so
				   a supplier receiving it could not tell which of their invoices had been
				   paid - the question bill-to-bill settlement exists to answer. */
				$data['sps_allocations'] = $this->$model->get_payment_allocations($id);



        		//echo "<pre>"; print_r($data);exit;

        		$this->load->helper(array('dompdf', 'file'));

        	        $dompdf = new DOMPDF();

        			$html = $this->load->view(self::VIEW_FOLDER.'popayment/payment_ack', $data,true);

        			$dompdf->load_html($html);

        			$dompdf->set_paper('A4', "portriat" );

        			$dompdf->render();

        			while(ob_get_level()) ob_end_clean();
        			$dompdf->stream("VendorAck.pdf",array('Attachment'=>0));

			break;




			case 'verify':
				echo json_encode(array('status' => $this->$model->verify_po_payment($this->input->post('id'))));
			break;

			case 'ajax':

			    $from_date	= $this->input->post('from_date');

			    $to_date	= $this->input->post('to_date');

				$list       = $this->$model->get_PurchaseSupplierPaymentList($from_date, $to_date);

			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/supplier_po_payment/list');

		        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;

			case 'print_cheque':

			    $set_model = "admin_settings_model";

        		$data['comp_details']   = $this->$set_model->get_company();

				$this->load->model('ret_billing_model');

        		$data['paymentdetails'] = $this->$model->get_po_paid_detail($id);

				$data['payment'] = $this->$model->get_po_paid_payment($id);

        		$this->load->helper(array('dompdf', 'file'));

        	        $dompdf = new DOMPDF();

        			$html = $this->load->view(self::VIEW_FOLDER.'print/cheque', $data,true);

        			$dompdf->load_html($html);

        			// Custom paper size for cheque: approx 8 x 3.5 inches
					// 8 inches = 576 points. 3.5 inches = 252 points.
					$customPaper = array(0,0,576,252); 

        			$dompdf->set_paper($customPaper, "portrait" );

        			$dompdf->render();

        			while(ob_get_level()) ob_end_clean();
        			$dompdf->stream("Cheque.pdf",array('Attachment'=>0));

			break;

		}

	}



    //Purchase Payment









    //Purchase Payment

    function purchase_payment($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'payment/list';

        			$this->load->view('layout/template', $data);

			break;

			case 'add':

					$data['main_content'] = self::VIEW_FOLDER.'payment/form';

        			$this->load->view('layout/template', $data);

			break;



			case 'purchase_payment_details':

			    $data= $this->$model->purchase_payment_details($_POST);

                echo json_encode($data);

			break;



			case 'save':

			    //echo "<pre>";print_r($_POST);exit;

			    $billing        =$_POST['billing'];



			    $pay_refno       = $this->$model->generatePaymentNo();



		        $payData[]=array(

		                  'pay_sup_id'      =>$billing['id_karigar'],

		                  'bill_type'       =>$billing['bill_type'],

		                  'pay_refno'       =>$pay_refno,

		                  'pay_amt'         =>$billing['tot_amt_received'],

		                  'pay_narration'   =>($billing['remark']!='' ? $billing['remark']:NULL),

		                  'pay_create_on'   =>date("Y-m-d H:i:s"),

		                  'pay_created_by'=>$this->session->userdata('uid'),

		                  );



			    $this->db->trans_begin();

			    $insId=$this->$model->insertBatchData($payData,'ret_po_payment');



                if($insId)

                {

                    $net_banking_details = json_decode($billing['net_banking'],true);

                    $sales_details		 = json_decode($billing['sales_details'],true);



                    if($billing['bill_type']==2)

                    {

                        $wallet=$this->$model->get_retWallet_details($billing['id_karigar']);

    	 				if($wallet['status'])

    	 				{

    	 				    $updStatus=$this->$model->updateWalletData(array('amount'=>$billing['tot_amt_received'],'id_wallet'=>$wallet['id_wallet']),'+');



    						$insWallet=array(

    						'id_wallet'	=>$wallet['id_wallet'],

    						'po_pay_id'	=>$insId,

    						'amount'	=>$billing['tot_amt_received'],

    						'type'	    =>1,

    						'remarks'   =>($billing['remark']!='' ? $billing['remark']:NULL)

    						 );

    						$this->$model->insertData($insWallet,'ret_karigar_wallet_transcation');

    						//echo "<pre>"; print_r($metal_rate);exit;

    	 				}

    	 				else

    	 				{

    	 				   $wallet_acc=array(

        	 				        'id_karigar'    =>$billing['id_karigar'],

        	 				        'amount'        =>$billing['tot_amt_received'],

        	 				   	    'created_by'    =>$this->session->userdata('uid'),

        						    'created_on'    =>date("Y-m-d H:i:s")

    						    );

     					   $insWalletAcc= $this->$model->insertData($wallet_acc,'ret_karigar_wallet');

     					   //print_r($this->db->last_query());exit;

     					   if($insWalletAcc)

     					   {

    	 					   	$insWallet=array(

        						'id_wallet'	=>$insWalletAcc,

        						'po_pay_id'	=>$insId,

        						'amount'	=>$billing['tot_amt_received'],

        						'type'	    =>1,

        						'remarks'   =>($billing['remark']!='' ? $billing['remark']:NULL)

        						 );

        						$this->$model->insertData($insWallet,'ret_karigar_wallet_transcation');

        						//print_r($this->db->last_query());exit;

     					   }



    	 				}

                    }



                    if(sizeof($net_banking_details)>0)

                    {

                        foreach($net_banking_details as $pay)

                        {

                            $netBankingPaymentData[]=array(

                                             'pay_id'           =>$insId,

                                             'type'             =>1,

                                             'pay_mode'         =>$pay['nb_type'],

                                             'payment_amount'   =>$pay['pay_amount'],

                                             'ref_no'           =>($pay['ref_no']!='' ? $pay['ref_no']:NULL),

                                            );

                            $this->$model->insertBatchData($netBankingPaymentData,'ret_po_payment_detail');

                        }

                    }



                    if(sizeof($sales_details)>0)

                    {

                        foreach($sales_details as $pay)

                        {

                            $SalesPaymentData[]=array(

                                             'pay_id'         =>$insId,

                                             'type'           =>2,

                                             'payment_amount' =>$pay['pay_amount'],

                                             'bill_id'        =>$pay['bill_id'],

                                            );

                            $this->$model->insertBatchData($SalesPaymentData,'ret_po_payment_detail');

                        }

                    }



                    if($this->db->trans_status()===TRUE)

                    {

                        $log_data = array(

                                	'id_log'        => $this->session->userdata('id_log'),

                                	'event_date'	=> date("Y-m-d H:i:s"),

                                	'module'      	=> 'PURCHASE PAYMENT',

                                	'operation'   	=> 'insert',

                                	'record'        =>  $insId,

                                	'remark'       	=> 'Purchase Payment Successfully..'

                                );

                        $this->log_model->log_detail('insert','',$log_data);







                        $this->db->trans_commit();

                        $responseData=array('status'=>true,'message'=>'Receipt added successfully.');

                        $this->session->set_flashdata('chit_alert',array('message'=>'Receipt successfully.','class'=>'success','title'=>'Purchase Payment'));

                    }

                    else

                    {

                        echo $this->db->last_query();exit;

                        $this->db->trans_rollback();

                        $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'Purchase Payment'));

                    }

                }else

                {

                        echo $this->db->last_query();exit;

                        $this->db->trans_rollback();

                        $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'Purchase Payment'));



                }



                echo json_encode($responseData);

			 break;





			case 'ajax':

				$list=$this->$model->get_PurchasePaymentList($_POST);

			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/halmarking_issue_receipt/list');

		        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;

		}

	}

    //Purchase Payment



    //rate fixing



    function update_ratefix_approval()

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$response_data=array();

		$insert_data=$this->input->post('req_data');

		foreach ($insert_data as $value)

		{

            $Data=array(

            'rate_fix_id' => $value['rate_fix_id'],

            'is_approved' 	 => 1,

			'approved_by' 	 =>  $this->session->userdata('uid'),

            'approved_date'  => date("Y-m-d H:i:s"),

            );

            $this->db->trans_begin();

			$rate_fix_id = $this->$model->updateData($Data,'rate_fix_id',$Data['rate_fix_id'],'ret_po_rate_fix');

		}

		if($this->db->trans_status()===TRUE)

        {

		 	$this->db->trans_commit();

			 $log_data = array(

				'id_log'        => $this->session->userdata('id_log'),

				'event_date'    => date("Y-m-d H:i:s"),

				'module'        => 'Rate Fixing',

				'operation'     => 'Update',

				'record'        =>  $rate_fix_id,

				'remark'        => 'Rate Fixing Updated successfully'

				);

			$this->log_model->log_detail('insert','',$log_data);

		 	$response_data=array('status'=>TRUE,'message'=>'Approved Successfully.');

		}

		else

		{

			$this->db->trans_rollback();

			$response_data=array('status'=>FALSE,'message'=>'Unable to proceed Your Request.');

		}

		echo json_encode($response_data);

	}



    function get_rate_fixing_po_no()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_rate_fixing_po_no($_POST);

        echo json_encode($data);

    }



    function get_approvl_ratefix_po()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_approvl_ratefix_po($_POST);

        echo json_encode($data);

    }





    function rate_fixing($type="",$id="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;

		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'rate_fixing/list';

					$data['access'] = $this->$SETT_MOD->get_access('admin_ret_purchase/rate_fixing/list');

        			$this->load->view('layout/template', $data);

			break;



			case 'approval_rate_fixing':

					$data['main_content'] = self::VIEW_FOLDER.'rate_fixing/approval_rate_fixing/list';

					$data['access'] = $this->$SETT_MOD->get_access('admin_ret_purchase/rate_fixing/approval_rate_fixing');

        			$this->load->view('layout/template', $data);

			break;



			case 'approval_rate_fixing_add':

					$data['main_content'] = self::VIEW_FOLDER.'rate_fixing/approval_rate_fixing/form';

        			$this->load->view('layout/template', $data);

			break;



			case 'add':

					$ho =  $this->$model->get_headOffice();

					$data['financial_year']  = $this->$model->GetFinancialYear();

					$data['comp_details']=$this->admin_settings_model->getCompanyDetails($ho['id_branch']);

					$data['main_content'] = self::VIEW_FOLDER.'rate_fixing/form';

        			$this->load->view('layout/template', $data);

			break;

			case 'job_receipt':

				$data['rate_fix']=$this->$model->get_rate_fix_details($id);

				$data['comp_details']=$this->admin_settings_model->getCompanyDetails("");

				//echo "<pre>";print_r($data);exit;

				$html = $this->load->view(self::VIEW_FOLDER.'rate_fixing/print', $data,true);

				echo $html;exit;

			break;



			case 'rate_fixing_items':

			    $data= $this->$model->get_rate_fixing_items($_POST);

                echo json_encode($data);

			break;



		    case 'cancel':

        		$ratefix_id      = $_POST['ratefix_id'];

			    $updData=array(

                    'bill_status'       => 2,

                    'cancel_reason'     => $_POST['cancel_reason'],

                    'cancelled_on'      => date("Y-m-d H:i:s"),

                    'cancelled_by'      => $this->session->userdata('uid')

                    );

                    $this->db->trans_begin();

                    $ret_status = $this->$model->updateData($updData,'rate_fix_id',$ratefix_id,'ret_po_rate_fix');

                    if($this->db->trans_status()===TRUE)

                    {

                        $this->db->trans_commit();


						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'RATE FIXING',

							'operation'     => 'Cancel',

							'record'        =>  $ret_status,

							'remark'        => 'Rate Fixed Cancelled successfully'

							);

	                    $this->log_model->log_detail('insert','',$log_data);

                        $this->session->set_flashdata('chit_alert',array('message'=>'Ratefix Cancelled Successfully','class'=>'success','title'=>'Ratefix'));

                        $return_data=array('status'=>TRUE,'message'=>'Ratefix Cancelled Successfully..');

                    }

                    else

                    {

                        echo $this->db->last_query();exit;

                        $this->db->trans_rollback();

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Ratefix'));

                        $return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

                    }

                echo json_encode($return_data);

			break;



			case 'save':

		        $item_details=$_POST['rate_fixing_items'];



		        if(!empty($item_details))

		        {

    		        foreach($item_details['po_item_id'] as $key => $val)

    		        {

    		            if($item_details['fix_weight'][$key]!='' && $item_details['fix_weight'][$key]>0)

    		            {



    		                //$order              = $this->$model->get_purchase_order_item_search($item_details['po_item_id'][$key]);



    		                $total_amount       = ($item_details['fix_weight'][$key]*$item_details['rate_per_gram'][$key]);

    	 	                $rateFixData=array(

                               'rate_fix_po_item_id'    =>$item_details['po_item_id'][$key],

                               'id_approval_ratecut'    =>(isset($item_details['id_supplier_rate_cut'][$key]) ? ($item_details['id_supplier_rate_cut'][$key]!='' ? $item_details['id_supplier_rate_cut'][$key] :NULL) :NULL),

                               'rate_fix_type'          =>$_POST['rate_fix']['rate_fix_type'],

                               'rate_fix_wt'            =>$item_details['fix_weight'][$key],

                               'rate_fix_rate'          =>$item_details['rate_per_gram'][$key],

                               'rate_fix_created_from'  =>2,

							   'remark'                  =>($_POST['rate_fix']['remark']!='' ? $_POST['rate_fix']['remark']:NULL),

							   'tax_amount'           =>($item_details['taxable_amount'][$key]!='' ? $item_details['taxable_amount'][$key]:0),

							   'tax_percentage'          =>($item_details['tax_percentage'][$key]!='' ? $item_details['tax_percentage'][$key]:0),

							   'cgst_cost'              =>($item_details['cgst'][$key]!='' ? $item_details['cgst'][$key]:0),

							   'sgst_cost'              =>($item_details['sgst'][$key]!='' ? $item_details['sgst'][$key]:0),

							   'igst_cost'               =>($item_details['igst'][$key]!='' ? $item_details['igst'][$key]:0),

                               'total_amount'           =>$item_details['payable_amt'][$key],

                               'rate_fix_create_by'     =>$this->session->userdata('uid'),

                               'rate_fix_created_on'    =>date("Y-m-d H:i:s"),

                               );

                              //echo"<pre>"; print_r($rateFixData);exit;

                              $this->db->trans_begin();

                              $insId=$this->$model->insertData($rateFixData,'ret_po_rate_fix');

    			          if($insId)

    			          {

    			             $log_data = array(

                                	'id_log'        => $this->session->userdata('id_log'),

                                	'event_date'	=> date("Y-m-d H:i:s"),

                                	'module'      	=> 'RATE FIXING',

                                	'operation'   	=> 'insert',

                                	'record'        =>  $insId,

                                	'remark'       	=> 'Rate Fixed added Successfully..'

                                );

                                $this->log_model->log_detail('insert','',$log_data);

    			          }

    		            }

    		        }

    		        if($this->db->trans_status()===TRUE)

                    {

                        $this->db->trans_commit();

                        $responseData=array('status'=>true,'message'=>'Rate Fixed successfully.');

                        $this->session->set_flashdata('chit_alert',array('message'=>'Rate Fixed successfully.','class'=>'success','title'=>'Rate Fixing'));

                    }

                    else

                    {

                        echo $this->db->last_query();exit;

                        $this->db->trans_rollback();

                        $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested operation','class'=>'danger','title'=>'Rate Fixing'));

                    }

		        }else

		        {

		            $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

		        }

                echo json_encode($responseData);

			break;



			case 'approval_rate_fix_list':

			    $from_date	= $this->input->post('from_date');

			    $to_date	= $this->input->post('to_date');

				$list       = $this->$model->get_approval_rate_fix_list($from_date, $to_date);

		  	    $access     = $this->admin_settings_model->get_access('admin_ret_purchase/rate_fixing/approval_rate_fixing');

		        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;



			default:

			   	$from_date	= $this->input->post('from_date');

			    $to_date	= $this->input->post('to_date');

				$list       = $this->$model->get_PO_Ratefix_List($from_date, $to_date);

		  	    $access     = $this->admin_settings_model->get_access('admin_ret_purchase/rate_fixing/list');

		        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;

		}

	}

    //rate fixing



	public function getOrderNosBySearch(){

		$model=	self::RET_PUR_ORDER_MODEL;

		$data = $this->$model->getAvailableOrders($_POST['searchTxt'], $_POST['supplierId']);

		echo json_encode($data);

	}





	public function get_bill_details()

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$searchTxt=$this->input->post('searchTxt');

		$data=$this->$model->get_bill_details($searchTxt,$bill_cus_id);

		echo json_encode($data);

	}



	//Purchase return

    function get_supplier_sale(){
        $model =	self::RET_PUR_ORDER_MODEL;
        $data  = $this->$model->get_supplier_sale($_POST);
        echo json_encode($data);
    }


	public function returnpoitems()

	{

		$model =	self::RET_PUR_ORDER_MODEL;

		$item_details = $_POST['returnitems'];



		$item_category_details = $_POST['returnpuritems'];

		$pur_ret_ref_no = $this->$model->pur_ret_refno($_POST['purchase_type']);

		$ho              =  $this->$model->get_headOffice();

		$dCData          =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

		$bill_date       =  ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

		$fin_year       = $this->$model->get_FinancialYear();

		//echo"<pre>";print_r($_POST);exit;

		// Extract po_id from the first item's source PO (if available)
		$return_po_id = NULL;
		if(!empty($item_category_details)) {
			$first_item = reset($item_category_details);
			if(!empty($first_item['po_item_id'])) {
				$po_item = $this->db->query("SELECT po_item_po_id FROM ret_purchase_order_items WHERE po_item_id = ".(int)$first_item['po_item_id']." LIMIT 1")->row_array();
				if(!empty($po_item['po_item_po_id'])) {
					$return_po_id = $po_item['po_item_po_id'];
				}
			}
		}

		$purchasereturn = array(

			"pur_ret_supplier_id"                   => $_POST['id_karigar'],

			"bill_date"                             => $bill_date,

			"id_branch"                             =>$ho['id_branch'],

			"pur_ret_ref_no"                        => ($fin_year['fin_year_code'].'-'.$pur_ret_ref_no),

			"pur_ret_convert_to"                    => $_POST['pur_ret_convert_to'],

			"tag_issue_from"                        => $_POST['tag_issue_from'],

			"nontag_issue_from"                     => $_POST['nontag_issue_from'],

			"pur_ret_reason"                        => $_POST['returnreason'],

			"pur_ret_remark"                        => $_POST['narration'],

			"stock_type"                            => $_POST['stock_type'],

			"purchase_type"                         => $_POST['purchase_type'],

			"return_total_cost"                     => ($_POST['returnamount']!='' ? $_POST['returnamount']:0),

			"pur_ret_round_off"                     => ($_POST['returnroundoff']!='' ? ($_POST['round_off_type']==1 ? '+'.$_POST['returnroundoff'] :'-'.$_POST['returnroundoff']):0),

			"pur_ret_discount"                      => ($_POST['return_discount']!='' ? $_POST['return_discount']:0),

			"pur_ret_other_charges"                 => ($_POST['other_charges']!='' && $_POST['other_charges']!='NaN' ? $_POST['other_charges']:0),

			"pur_ret_tds_percent"                   => ($_POST['tds_percent']!='' ? $_POST['tds_percent']:0),

			"pur_ret_tds_value"                     => ($_POST['tds_tax_value']!='' ? $_POST['tds_tax_value']:0),

			"pur_ret_other_charges_tds_percent"     => ($_POST['charges_tds_percent']!='' ? $_POST['charges_tds_percent']:0),

			"pur_ret_other_charges_tds_value"       => ($_POST['other_charges_tds_tax_value']!='' ? $_POST['other_charges_tds_tax_value']:0),

			"pur_ret_tcs_percent"                   => ($_POST['tcs_percent']!='' ? $_POST['tcs_percent']:0),

			"pur_ret_tcs_value"                     => ($_POST['tcs_tax_value']!='' ? $_POST['tcs_tax_value']:0),

			"pur_ret_created_by"                    => $this->session->userdata('uid'),

			"po_id"                                 => $return_po_id

		);

		$this->db->trans_begin();

		$insId = $this->$model->insertData($purchasereturn,'ret_purchase_return');

		foreach($item_category_details as $key => $val)

		{

			//echo "<pre>"; print_r($val);exit;

			$purchasereturn_items = array(

				"pur_ret_id"            => $insId,

				"return_item_type"      => $val['ret_item_type'],

				"pur_ret_po_item_id"    => (is_numeric($val['po_item_id']) ? $val['po_item_id'] : NULL),

				"id_qc_issue_details"   => (is_numeric($val['id_qc_issue_details']) ? $val['id_qc_issue_details'] : NULL),

				"tag_id"                => ($val['tag_id']!='' ? $val['tag_id']:NULL),

				"ref_pur_ret_itm_id"    => ($val['ref_pur_ret_itm_id']!='' ? $val['ref_pur_ret_itm_id']:NULL),

				"bill_det_id"           => ($val['bill_det_id']!='' ? $val['bill_det_id']:NULL),

				"branch_trans_id"       => ($val['branch_trans_id']!='' && $val['branch_trans_id']!='undefined' ? $val['branch_trans_id']:NULL),

				'id_section'            => ($val['id_section']!='' && $val['id_section']!='undefined' ? $val['id_section']: NULL ),

				'id_product'            => (is_numeric($val['pro_id']) ? $val['pro_id'] : NULL),

				'id_design'             => (is_numeric($val['des_id']) ? $val['des_id'] : NULL),

				'id_sub_design'         => (is_numeric($val['id_sub_design']) ? $val['id_sub_design'] : NULL),

				// pur_ret_pcs/gwt/lwt/nwt are NOT NULL columns — a blank/non-numeric value
				// (e.g. blank PCS for a Bullion/Non-Tag item) must fall back to 0, not be
				// passed through as '' or 'undefined', which MySQL rejects outright.
				"pur_ret_pcs"           => (is_numeric($val['purretpcs']) ? $val['purretpcs'] : 0),

				"pur_ret_gwt"           => (is_numeric($val['purretgwt']) ? $val['purretgwt'] : 0),

				"pur_ret_lwt"           => (is_numeric($val['purretlwt']) ? $val['purretlwt'] : 0),

				"pur_ret_nwt"           => (is_numeric($val['purretnwt']) ? $val['purretnwt'] : 0),

				"pur_ret_pur_wt"        => (is_numeric($val['purreturnpure']) ? $val['purreturnpure'] : NULL),

				"pur_ret_purchase_touch"=> (is_numeric($val['purreturntouch']) ? $val['purreturntouch'] : NULL),

				"pur_ret_wastage"       => ($val['purreturnwastper']!='' ? $val['purreturnwastper']: NULL),

				"pur_ret_wastage_wt"    => ($val['purreturnwastwgt']!='' ? $val['purreturnwastwgt'] : NULL),

				"pur_ret_mc_type"       => (is_numeric($val['purreturnmctype']) ? $val['purreturnmctype'] : NULL),

				"pur_ret_mc_value"      => (is_numeric($val['purreturnmc']) ? $val['purreturnmc'] : NULL),

				"pur_ret_debit_note_amt" => (is_numeric($val['ret_item_cost']) ? $val['ret_item_cost'] : NULL),

				"pur_ret_tax_id"         => ($val['return_item_tax_id']!='' && $val['return_item_tax_id']!='undefined' ? $val['return_item_tax_id'] :NULL),

                "pur_ret_tax_percent"    => (is_numeric($val['return_item_tax_percent']) ? $val['return_item_tax_percent'] : 0),

                "pur_ret_tax_type"       => ($val['return_item_tax_type']!='' && $val['return_item_tax_type']!='undefined' ?  $val['return_item_tax_type'] : NULL),

                "total_cgst_cost"        => (is_numeric($val['total_cgst_cost']) ? $val['total_cgst_cost'] : 0),

                "total_sgst_cost"        => (is_numeric($val['total_sgst_cost']) ? $val['total_sgst_cost'] : 0),

                "total_igst_cost"        => (is_numeric($val['total_igst_cost']) ? $val['total_igst_cost'] : 0),

                "total_total_tax"        => (is_numeric($val['total_total_tax']) ? $val['total_total_tax'] : 0),

                "calculation_based_on"   => (is_numeric($val['calculation_based_on']) ? $val['calculation_based_on'] : NULL),

                "pure_wt_calc_type"      => (is_numeric($val['pure_wt_calc_type']) ? $val['pure_wt_calc_type'] : NULL),

                "pur_ret_rate"           => (is_numeric($val['pur_ret_rate']) ? $val['pur_ret_rate'] : NULL),

			);

			$insert_item_id = $this->$model->insertData($purchasereturn_items,'ret_purchase_return_items');



			if($insert_item_id)

			{

				$ledger_data = array(

					'trans_date'        =>$bill_date,

					'id_karigar'        =>$_POST['id_karigar'],

					// 'cat_id'         =>$orderDetails['id_category'][$key],

					'trans_type'        =>1,//Credit

					'trans_rec_type'    =>1,//Weight

					'trans_pcs'         =>$val['purretpcs'],

					'trans_grswt'       =>$val['purretgwt'],

					'trans_netwt'       =>$val['purretnwt'],

					'trans_purewt'      =>$val['purreturnpure'],

					'trans_screen_id'   =>4,//Supplier Bill entry

					'ref_id'            =>$insert_item_id,

					'remarks'           =>'From Purchase Return',

					'created_by'        =>$this->session->userdata('uid'),

					'created_on'        =>date("Y-m-d H:i:s"),

				);

				$this->$model->insertData($ledger_data,'ret_supplier_ledger_log');

				if($val['tag_id']!='' && $_POST['tag_issue_from']==1)

				{

					$tagDetails = $this->$model->get_tag_details($val['tag_id']);

		            $dCData = $this->admin_settings_model->getBranchDayClosingData($tagDetails['current_branch']);

		            $bill_date = ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

                    $tagStatus=$this->$model->updateData(array('tag_status'=>9,'updated_by'=>$this->session->userdata('uid'),'updated_time'=>date("Y-m-d H:i:s")),'tag_id',$val['tag_id'],'ret_taging');

					if($tagStatus)

                    {

                        //Update Tag Log status

                            $tag_log=array(

                            'tag_id'	            =>$val['tag_id'],

                            'date'		            =>$bill_date,

                            'status'	            =>14,

                            'from_branch'           =>$tagDetails['current_branch'],

                            'to_branch'	            =>NULL,

                            'issuspensestock'	    =>$_POST['stock_type'],

                            'created_on'            =>date("Y-m-d H:i:s"),

                            'created_by'            =>$this->session->userdata('uid'),

                            );

                            $this->$model->insertData($tag_log,'ret_taging_status_log');

							// update in section log

							$sectag_data =array(

								'tag_id'	        => $val['tag_id'],

                        		'date'		        => $bill_date,

                        		'status'	        => 14,

                        		'from_branch'       => $tagDetails['current_branch'],

                        		'to_branch'	        => NULL,

                        		'created_on'        => date("Y-m-d H:i:s"),

                        		'created_by'        => $this->session->userdata('uid'),

                        		'issuspensestock'   => $_POST['stock_type'],

                        		'from_section'      => $tagDetails['id_section'],

                        		'to_section'        => NULL,
							);

							$this->$model->insertData($sectag_data,'ret_section_tag_status_log');


                    }

				}

				else

				{
					if($_POST['nontag_issue_from']==1)  // Nontag Available Stock .
					{

						$existData=array('id_section'=>$val['id_section'],'id_product'=>$val['pro_id'],'design'=>$val['des_id'],'id_sub_design'=>$val['id_sub_design'],'id_branch'=>$ho['id_branch']);

						//print_r($existData);exit;

						$isExist = $this->$model->checkNonTagItemExist($existData);

						if($isExist['status'] == TRUE)

						{

							$nt_data = array(

								'id_nontag_item'=>$isExist['id_nontag_item'],

								'gross_wt'		=> $val['purretgwt'],

								'net_wt'		=> $val['purretnwt'],

								'no_of_piece'   => $val['purretpcs'],

								'updated_by'	=> $this->session->userdata('uid'),

								'updated_on'	=> date('Y-m-d H:i:s'),

							);

							$this->$model->updateNTData($nt_data,'-');



							$non_tag_data=array(

								'product'	    => $val['pro_id'],

								'design'	    => $val['des_id'],

								'id_sub_design'	=> $val['id_sub_design'],

								'no_of_piece'   => $val['purretpcs'],

								'gross_wt'		=> $val['purretgwt'],

								'net_wt'		=> $val['purretnwt'],

								'from_branch'	=> $ho['id_branch'],

								'to_branch'	    => NULL,

								'ref_no'	    => $insId,

								'status'	    => 8,//Purchase Return

								'date'          => $bill_date,

								'created_on'    => date("Y-m-d H:i:s"),

								'created_by'    =>  $this->session->userdata('uid')

							);

							$this->$model->insertData($non_tag_data,'ret_nontag_item_log');


							$section_nontag_log = array(

								'product'		=> $val['pro_id'],

								'design'		=> $val['des_id'],

								'id_sub_design' => $val['id_sub_design'],

								'no_of_piece'	=> $val['purretpcs'],

								'gross_wt'		=> $val['purretgwt'],

								'net_wt'		=> $val['purretnwt'],

								"status"		=> 8,

								"from_branch"	=> $ho['id_branch'],

								"to_branch"		=> NULL,

								"from_section"	=> $val['id_section'],

								"to_section"    => NULL,

								"created_by"	=> $this->session->userdata('uid'),

								"created_on"	=> date('Y-m-d H:i:s'),

								"date"			=> $bill_date,

								);

							$this->$model->insertData($section_nontag_log,'ret_section_nontag_item_log');

						}

					}

				}



				if(!empty($val['other_metal_details']))

				{

					$othermetal_details = json_decode($val['other_metal_details'],true);

					foreach($othermetal_details as $om)

					{

						$om_data = array(

							'pur_ret_return_id'         => $insert_item_id,

							'ret_other_itm_metal_id'    => $om['id_metal'],

							'ret_other_itm_pur_id'      => $om['id_purity'],

							'ret_other_itm_grs_weight'  => $om['gwt'],

							//'ret_other_itm_cal_type'    => $om['calc_type'],

							'ret_other_itm_pcs'         => $om['pcs'],

							'ret_other_itm_rate'        => $om['rate_per_gram'],

							'ret_other_itm_amount'      => $om['amount'],



						);

						$returnomInsert = $this->$model->insertData($om_data, 'ret_purchase_return_other_metal');

						//echo $this->db->last_query();exit;

					}

				}

				if(!empty($val['stone_details']))

				{

					$stone_details = json_decode($val['stone_details'],true);

					foreach($stone_details as $om)

					{

						$stone_data = array(

							'pur_ret_return_id'         => $insert_item_id,

							'ret_stone_id'              => $om['stone_id'],

							'ret_stone_pcs'             => $om['stone_pcs'],

							'ret_stone_wt'              => $om['stone_wt'],

							'ret_stone_uom'             => $om['stone_uom_id'],

							'ret_stone_calc_based_on'   => $om['stone_calc_typ'],

							'ret_stone_rate'            => $om['stone_rate'],

							'ret_stone_amount'          => $om['stone_price'],

							'po_st_id'                  => $om['po_st_id'],




						);

						$returnstoneInsert = $this->$model->insertData($stone_data, 'ret_purchase_return_stone_items');

						//echo $this->db->last_query();exit;

					}

				}



				if(!empty($val['other_charges_details']))

				{

					$othercharge_details = json_decode($val['other_charges_details'],true);

					foreach($othercharge_details as $ch)

					{

						$charge_data = array(

							'pur_ret_itm_id'        => $insert_item_id,

							'pur_ret_charge_id'     => $ch['charge_id'],

							'pur_ret_charge_value'  => $ch['charge_value'],

							'tax_percentage'        => $ch['tax_percentage'],

							'item_total_tax'        => $ch['item_total_tax'],

							'cgst_cost'             => $ch['cgst_cost'],

							'sgst_cost'             => $ch['sgst_cost'],

							'igst_cost'             => $ch['igst_cost'],

							'total_charge_value'    => $ch['total_charge_value'],

						);

						$returnchargegsInsert = $this->$model->insertData($charge_data, 'ret_purchase_return_other_charges');

					}

				}



			}

		}



		if($this->db->trans_status()===TRUE)

		{

			$this->db->trans_commit();

			$log_data = array(

				'id_log'        => $this->session->userdata('id_log'),

				'event_date'    => date("Y-m-d H:i:s"),

				'module'        => 'Purchase Return',

				'operation'     => 'Add',

				'record'        =>  $insId,

				'remark'        => 'Purchase Return Added successfully'

				);

	        $this->log_model->log_detail('insert','',$log_data);

			$responseData=array('success'=>true,'message'=>'Return data updated successfully.','return_id'=>$insId);



		}

		else

		{

			echo $this->db->last_query();exit;

			$this->db->trans_rollback();

			$responseData=array('success' => false,'message'=>'Unable to proceed the requested operation.');

		}

		echo json_encode($responseData);

	}



    function purchasereturn($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$set_model = "admin_settings_model";

		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'purchasereturn/list';
					$data['access'] = $this->$set_model->get_access('admin_ret_purchase/purchasereturn/list');        			
					$this->load->view('layout/template', $data);

			break;

			case 'add':

					$data['financial_year']  = $this->$model->GetFinancialYear();

			        $data['comp_details']   = $this->$set_model->get_company();

					$data['enable_pur_return_sales_return'] = $this->$model->get_ret_settings('enable_pur_return_sales_return');

					$data['main_content'] = self::VIEW_FOLDER.'purchasereturn/form';

        			$this->load->view('layout/template', $data);

			break;



			case 'cancel_ret_entry':

			    $ho              =  $this->$model->get_headOffice();

        		$dCData          =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

        		$bill_date       =  ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

				//echo"<pre>";print_r($_POST);exit;

			    $pur_return_id = $_POST['pur_return_id'];

			    $cancel_reason = $_POST['cancel_reason'];

				$tag_issue_from = $_POST['tag_issue_from'];

				$nontag_issue_from = $_POST['nontag_issue_from'];

			    $updData=array(

                    'bill_status'       => 2,

                    'cancel_reason'     => $_POST['cancel_reason'],

                    'cancelled_date'    => $bill_date,

                    'cancelled_by'      => $this->session->userdata('uid')

                    );

                    $this->db->trans_begin();

                    $ret_status = $this->$model->updateData($updData,'pur_return_id',$pur_return_id,'ret_purchase_return');

                    if($ret_status)

                    {

                        $non_tag_details = $this->$model->get_return_non_tag_details($pur_return_id);

                        $ret_tag_details = $this->$model->get_return_tag_details($pur_return_id);

                        if(sizeof(($non_tag_details) > 0) && $nontag_issue_from==1)

                        {

                            foreach($non_tag_details as $non_tag)

                            {

                                $existData=array('id_product'=>$non_tag['id_product'],'design'=>$non_tag['id_design'],'id_sub_design'=>$non_tag['id_sub_design'],'id_branch'=>$ho['id_branch'],'id_section'=>$non_tag['id_section']);



                                	$isExist = $this->$model->checkNonTagItemExist($existData);



                    				if($isExist['status'] == TRUE)

                			        {

                			            $nt_data = array(

                                        'id_nontag_item'=>$isExist['id_nontag_item'],

                                        'gross_wt'		=> $non_tag['pur_ret_gwt'],

                                        'net_wt'		=> $non_tag['pur_ret_gwt'],

                                        'no_of_piece'   => $non_tag['pur_ret_pcs'],

                                        'updated_by'	=> $this->session->userdata('uid'),

                                        'updated_on'	=> date('Y-m-d H:i:s'),

                                        );

                                        $this->$model->updateNTData($nt_data,'+');



                                        $non_tag_data=array(

                                        'product'	    => $non_tag['id_product'],

                                        'design'	    => $non_tag['id_design'],

                                        'id_sub_design'	=> $non_tag['id_sub_design'],

                                        'no_of_piece'   => $non_tag['pur_ret_pcs'],

                                        'gross_wt'		=> $non_tag['pur_ret_gwt'],

                                        'net_wt'		=> $non_tag['pur_ret_gwt'],

                                        'to_branch'	    => $ho['id_branch'],

                                        'from_branch'	=> NULL,

                                        'ref_no'	    => $insId,

                                        'status'	    => 9,//Purchase Return Cancel

                                        'date'          => $bill_date,

                                        'created_on'    => date("Y-m-d H:i:s"),

                                        'created_by'    =>  $this->session->userdata('uid')

                                        );

                                        $this->$model->insertData($non_tag_data,'ret_nontag_item_log');

                			        }

                            }

                        }

                        if(sizeof(($ret_tag_details) > 0) && $tag_issue_from==1)

                        {

                            foreach($ret_tag_details as $val)

                            {

                                $tagStatus=$this->$model->updateData(array('tag_status'=>0,'updated_by'=>$this->session->userdata('uid'),'updated_time'=>date("Y-m-d H:i:s")),'tag_id',$val['tag_id'],'ret_taging');

                                $tag_log=array(

                                'tag_id'	  =>$val['tag_id'],

                                'date'		  =>$bill_date,

                                'status'	  =>0,

                                'from_branch' =>NULL,

                                'to_branch'	  =>$ho['id_branch'],

                                'created_on'  =>date("Y-m-d H:i:s"),

                                'created_by'  =>$this->session->userdata('uid'),

                                );

                                $this->$model->insertData($tag_log,'ret_taging_status_log');

                            }

                        }

                    }

                    if($this->db->trans_status()===TRUE)

                    {

                        $this->db->trans_commit();

						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'Purchase Return Entry',

							'operation'     => 'Cancel',

							'record'        => $ret_status,

							'remark'        => 'Purchase Return Entry Cancelled successfully'

							);

	                    $this->log_model->log_detail('insert','',$log_data);

                        $this->session->set_flashdata('chit_alert',array('message'=>'Purchase Return Entry Cancelled Successfully','class'=>'success','title'=>'Purchase Return'));

                        $return_data=array('status'=>TRUE,'message'=>'Order Instructions Added Successfully..');

                    }

                    else

                    {

                        echo $this->db->last_query();exit;

                        $this->db->trans_rollback();

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Purchase Return'));

                        $return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

                    }

                echo json_encode($return_data);

			break;



			case 'ajax':

				$profile=$this->$model->get_profile_settings($this->session->userdata('profile'));

				$list=$this->$model->getReturnedRequestList($_POST);

				$access = $this->$set_model->get_access('admin_ret_purchase/purchasereturn/list');
		        $data = array(

		        					'list'  => $list,

									'profile' =>$profile,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;

		}

	}

	public function getreturn_po_list(){

		$model=	self::RET_PUR_ORDER_MODEL;

		$data = $this->$model->getRejectedPos($_POST);

		echo json_encode($data);

	}

	public function get_qc_faild_items_by_poid(){

		$model =	self::RET_PUR_ORDER_MODEL;

		$data  = $this->$model->getRejectedItemsByPoId($_POST['porefid'],$_POST['purchase_type'],$_POST['fin_year']);

		echo json_encode($data);

	}

	public function get_qc_faild_items_by_supid(){

		$model =	self::RET_PUR_ORDER_MODEL;

		$data  = $this->$model->getRejectedItemsBySupId($_POST['supid']);

		echo json_encode($data);

	}



	public function updateporeturnitems(){



		$model =	self::RET_PUR_ORDER_MODEL;

		$item_details = $_POST['returnitems'];

		$item_category_details = $_POST['returncatitems'];

		$nontagreturnitemlist = $_POST['nontagreturnitemlist'];

		$returntaggeditemlist = $_POST['returntaggeditemlist'];

		$purchasereturnitemlist = $_POST['purchasereturnitemlist'];



		$pur_ret_ref_no = $this->$model->pur_ret_refno();

		$ho              =  $this->$model->get_headOffice();

		$dCData          =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

		$bill_date       =  ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

		$fin_year       = $this->$model->get_FinancialYear();

		$purchasereturn = array(

		                    "pur_ret_supplier_id"                   => $_POST['id_karigar'],

		                    "bill_date"                             => $bill_date,

		                    "pur_ret_ref_no"                        => ($fin_year['fin_year_code'].'-'.$pur_ret_ref_no),

							"pur_ret_reason"                        => $_POST['returnreason'],

							"pur_ret_remark"                        => $_POST['narration'],

							"stock_type"                            => $_POST['stock_type'],

							"return_total_cost"                     => ($_POST['returnamount']!='' ? $_POST['returnamount']:0),

							"pur_ret_round_off"                     => ($_POST['returnroundoff']!='' ? ($_POST['round_off_type']==1 ? '+'.$_POST['returnroundoff'] :'-'.$_POST['returnroundoff']):0),

							"pur_ret_discount"                      => ($_POST['return_discount']!='' ? $_POST['return_discount']:0),

							"pur_ret_other_charges"                 => ($_POST['other_charges']!='' ? $_POST['other_charges']:0),

							"pur_ret_tds_percent"                   => ($_POST['tds_percent']!='' ? $_POST['tds_percent']:0),

							"pur_ret_tds_value"                     => ($_POST['tds_tax_value']!='' ? $_POST['tds_tax_value']:0),

							"pur_ret_other_charges_tds_percent"     => ($_POST['charges_tds_percent']!='' ? $_POST['charges_tds_percent']:0),

							"pur_ret_other_charges_tds_value"       => ($_POST['other_charges_tds_tax_value']!='' ? $_POST['other_charges_tds_tax_value']:0),

							"pur_ret_tcs_percent"                   => ($_POST['tcs_percent']!='' ? $_POST['tcs_percent']:0),

							"pur_ret_tcs_value"                     => ($_POST['tcs_tax_value']!='' ? $_POST['tcs_tax_value']:0),

							"pur_ret_created_by"                    => $this->session->userdata('uid')

						);

		$this->db->trans_begin();

        $insId = $this->$model->insertData($purchasereturn,'ret_purchase_return');



		foreach($item_category_details as $key => $val)

		{

    			$purchasereturn_items = array(

    							"pur_ret_id"         => $insId,

    							"pur_ret_cat_id"     => $val['cat_id'],

    							"pur_ret_cat_pcs"    => $val['cat_pcs'],

    							"pur_ret_cat_gwt"    => $val['cat_gwt'],

    							"pur_ret_cat_leswt"    => $val['cat_lwt'],

    							"pur_ret_cat_netwt"  => $val['cat_nwt'],

    							"pur_ret_rate"       => $val['pur_ret_rate'],

    							"purreturncaltype"   => $val['purreturncaltype'],

    							"pur_ret_item_cost"  => $val['ret_item_cost'],

    							"pur_ret_tax_rate"   => $val['ret_tax_rate'],

    							"pur_ret_tax_value"  => $val['ret_tax_value'],

    							"pur_ret_cgst"       => $val['ret_cgst_value'],

    							"pur_ret_sgst"       => $val['ret_sgst_value'],

    							"pur_ret_igst"       => $val['ret_igst_value'],

    						);

    		    $insert_item_id = $this->$model->insertData($purchasereturn_items,'ret_purchase_return_cat_items');

    		    if($insert_item_id)

    		    {



        		    if(!empty($val['other_metal_details'])){

                    	$othermetal_details = json_decode($val['other_metal_details'],true);

                    	foreach($othermetal_details as $om)

                    	{

                    		$om_data = array(

                    			'pur_ret_return_id'         => $insert_item_id,

                    			'ret_other_itm_metal_id'    => $om['id_metal'],

                    			'ret_other_itm_pur_id'      => $om['id_purity'],

                    			'ret_other_itm_grs_weight'  => $om['gwt'],

                    			//'ret_other_itm_cal_type'    => $om['calc_type'],

                    			'ret_other_itm_pcs'         => $om['pcs'],

                    			'ret_other_itm_rate'        => $om['rate_per_gram'],

                    			'ret_other_itm_amount'      => $om['amount'],



                    		);

                    		$returnomInsert = $this->$model->insertData($om_data, 'ret_purchase_return_other_metal');

                    		//echo $this->db->last_query();exit;

                    	}

                    }

                    if(!empty($val['stone_details'])){

                    	$stone_details = json_decode($val['stone_details'],true);

                    	foreach($stone_details as $om)

                    	{

                    		$stone_data = array(

                    			'pur_ret_return_id'         => $insert_item_id,

                    			'ret_stone_id'              => $om['stone_id'],

                    			'ret_stone_pcs'             => $om['stone_pcs'],

                    			'ret_stone_wt'              => $om['stone_wt'],

                    			'ret_stone_uom'             => $om['stone_uom_id'],

                    			'ret_stone_calc_based_on'   => $om['stone_cal_type'],

                    			'ret_stone_rate'            => $om['stone_rate'],

                    			'ret_stone_amount'          => $om['stone_price'],



                    		);

                    		$returnstoneInsert = $this->$model->insertData($stone_data, 'ret_purchase_return_stone_items');

                    		//echo $this->db->last_query();exit;

                    	}

                    }

		        }

    	}



    	foreach($returntaggeditemlist as $key => $val)

	    {

	        $purchasereturn_tag_items = array(

				"pur_ret_id"            => $insId,

				"tag_id"                => $val['tag_id'],

				"return_item_type"      => 2,

				"pur_ret_pcs"           => $val['piece'],

				"pur_ret_gwt"           => $val['gross_wt'],

				"pur_ret_nwt"           => $val['net_wt'],

			);

			$this->$model->insertData($purchasereturn_tag_items,'ret_purchase_return_items');

			if($val['tag_id']!='')

		    {

		            $tagDetails = $this->$model->get_tag_details($val['tag_id']);

		            $dCData = $this->admin_settings_model->getBranchDayClosingData($tagDetails['current_branch']);

		            $bill_date = ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

                    $tagStatus=$this->$model->updateData(array('tag_status'=>9,'updated_by'=>$this->session->userdata('uid'),'updated_time'=>date("Y-m-d H:i:s")),'tag_id',$val['tag_id'],'ret_taging');

                    if($tagStatus)

                    {

                        //Update Tag Log status

                            $tag_log=array(

                            'tag_id'	            =>$val['tag_id'],

                            'date'		            =>$bill_date,

                            'status'	            =>14,

                            'from_branch'           =>$tagDetails['current_branch'],

                            'to_branch'	            =>NULL,

                            'issuspensestock'	    =>$_POST['stock_type'],

                            'created_on'            =>date("Y-m-d H:i:s"),

                            'created_by'            =>$this->session->userdata('uid'),

                            );

                            $this->$model->insertData($tag_log,'ret_taging_status_log');




                    }

		    }

	    }

	    foreach($nontagreturnitemlist as $key => $val)

	    {

	        $purchasereturn_nontag_items = array(

				"pur_ret_id"            => $insId,

				"return_item_type"      => 3,

				'id_product'            => $val['id_product'],

				'id_design'             => $val['id_design'],

				'id_sub_design'         => $val['id_sub_design'],

				"pur_ret_pcs"           => $val['piece'],

				"pur_ret_gwt"           => $val['gross_wt'],

				"pur_ret_lwt"           => 0,

				"pur_ret_nwt"           => $val['gross_wt'],

			);

			$this->$model->insertData($purchasereturn_nontag_items,'ret_purchase_return_items');



            $existData=array('id_product'=>$val['id_product'],'design'=>$val['id_design'],'id_sub_design'=>$val['id_sub_design'],'id_branch'=>$ho['id_branch']);



			$isExist = $this->$model->checkNonTagItemExist($existData);



			if($isExist['status'] == TRUE)

	        {

	            $nt_data = array(

                'id_nontag_item'=>$isExist['id_nontag_item'],

                'gross_wt'		=> $val['gross_wt'],

                'net_wt'		=> $val['gross_wt'],

                'no_of_piece'   => $val['piece'],

                'updated_by'	=> $this->session->userdata('uid'),

                'updated_on'	=> date('Y-m-d H:i:s'),

                );

                $this->$model->updateNTData($nt_data,'-');



                $non_tag_data=array(

                'product'	    => $val['id_product'],

                'design'	    => $val['id_design'],

                'id_sub_design'	=> $val['id_sub_design'],

                'no_of_piece'   => $val['piece'],

                'gross_wt'		=> $val['gross_wt'],

                'net_wt'		=> $val['gross_wt'],

                'from_branch'	=> $ho['id_branch'],

                'to_branch'	    => NULL,

                'ref_no'	    => $insId,

                'status'	    => 8,//Purchase Return

                'date'          => $bill_date,

                'created_on'    => date("Y-m-d H:i:s"),

                'created_by'    =>  $this->session->userdata('uid')

                );

                $this->$model->insertData($non_tag_data,'ret_nontag_item_log');

	        }

	    }

	    foreach($purchasereturnitemlist as $val)

	    {

	        $purchasereturn_po_items = array(

				"pur_ret_id"            => $insId,

				"pur_ret_po_item_id"    => $val['po_item_id'],

				"return_item_type"      => 1,

				"pur_ret_pcs"           => $val['piece'],

				"pur_ret_gwt"           => $val['gross_wt'],

				"pur_ret_pur_wt"        => $val['gross_wt'],

			);

			$this->$model->insertData($purchasereturn_po_items,'ret_purchase_return_items');

	    }



		if(!empty($_POST['other_charges_details'])){

        	$charge_details = json_decode($_POST['other_charges_details'],true);

        	foreach($charge_details as $charges)

        	{



        	    $cgst_cost = 0;

        	    $sgst_cost = 0;

        	    $igst_cost = 0;

        	    $total_tax = number_format(($charges['charge_tax_value']),2,'.','');

        	    if($addData['cmp_country']==$addData['supplier_country'])

        	    {

        	        if($addData['cmp_state']==$addData['supplier_state'])

        	        {

        	            $cgst_cost = ($total_tax/2);

        	            $sgst_cost = ($total_tax/2);

        	        }

        	        else

        	        {

        	            $igst_cost = $total_tax;

        	        }

        	    }

        	    else

        	    {

        	        $cgst_cost = ($total_tax/2);

        	        $sgst_cost = ($total_tax/2);

        	    }



        		$charge_data = array(

        			'pur_ret_id'                => $insId,

        			'pur_ret_charge_id'         => $charges['charge_id'],

        			'pur_ret_charge_value'      => $charges['charge_value'],

        			'pur_ret_charge_tax'        => $charges['charge_tax'],

        			'pur_ret_charge_tax_value'  => $charges['charge_tax_value'],

        			'cgst_cost'                 => $cgst_cost,

        			'sgst_cost'                 => $sgst_cost,

        			'igst_cost'                 => $igst_cost,

        		);

        		$returnchargegsInsert = $this->$model->insertData($charge_data, 'ret_purchase_return_other_charges');

        		//echo $this->db->last_query();exit;

        	}

        }







		if($this->db->trans_status()===TRUE)

		{

			$this->db->trans_commit();

			$responseData=array('success'=>true,'message'=>'Return data updated successfully.','return_id'=>$insId);



		}

		else

		{

			//echo $this->db->last_query();exit;

			$this->db->trans_rollback();

			$responseData=array('success' => false,'message'=>'Unable to proceed the requested operation.');

		}

		echo json_encode($responseData);



	}



	public function return_receipt_acknowladgement($id, $Print_Type = "")

    {

        $model =    self::RET_PUR_ORDER_MODEL;

        $set_model                      = "admin_settings_model";

        $data['comp_details']           = $this->$set_model->get_company(1);

        $data['issue']                  = $this->$model->getReturnReceipt($id);

        $data['receipt_details']        = $this->$model->getReturnReceiptDetails($id);

        $data['gst_details']            = $this->$model->getReturnReceiptGSTDetails($id);

        $data['item_details']          = $this->$model->get_purchaseReturn_item_details($id);

        $data['type'] = $Print_Type; // 1-summary , 2-Detailed

        //echo "<pre>"; print_r($data);exit;

        $this->load->helper(array('dompdf', 'file'));

        $dompdf = new DOMPDF();

        /*if ($data['type'] == 1) {

            // $html = $this->load->view('ret_purchase/purchasereturn/vendor_ack', $data, true);

             $html = $this->load->view(self::VIEW_FOLDER . 'purchasereturn/vendor_ack', $data, true);

        } else {

            $html = $this->load->view('ret_purchase/purchasereturn/vendor_ack_detailed', $data, true);

        }*/



        $html = $this->load->view(self::VIEW_FOLDER . 'purchasereturn/vendor_ack', $data, true);



        echo $html;

        // $dompdf->load_html($html);

        // $dompdf->set_paper('A4', "portriat");

        // $dompdf->render();

        // $dompdf->stream("VendorAck.pdf", array('Attachment' => 0));

    }

    //Purchase return





    //Order cancel and close

    function update_order_close()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $req_data   =$_POST['req_data'];



        foreach($req_data as $items)

	    {

	       $this->db->trans_begin();



	       $this->$model->updateData(array('order_status'=>7,'closed_date'=>date("Y-m-d H:i:s"),'closed_by'=>$this->session->userdata('uid')),'id_customerorder',$items['id_customerorder'],'customerorder');

	       /* CPO-15b: carry the close through to common_po_status (4 = Closed).
	          Eligibility for advance payment and bill recall filters on
	          common_po_status IN (0,1,2), NOT on order_status — deliberately, because
	          order_status is shared and rewritten by the delivery flow (see CPO-08a).
	          Leaving it unstamped keeps a closed PO in both dropdowns. */
	       $this->_set_common_po_status($items['id_customerorder'], 4);

	    }

    	    if($this->db->trans_status()===TRUE)

            {

                $log_data = array(

                        	'id_log'        => $this->session->userdata('id_log'),

                        	'event_date'	=> date("Y-m-d H:i:s"),

                        	'module'      	=> 'Order Closed',

                        	'operation'   	=> 'Update',

                        	'record'        =>  NULL,

                        	'remark'       	=> 'Order Closed.'

                        );

                $this->log_model->log_detail('insert','',$log_data);



                $this->db->trans_commit();

                $responseData=array('status'=>true,'message'=>'Order Closed successfully.');

            }

            else

            {

                $this->db->trans_rollback();

                $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

            }

            echo json_encode($responseData);

	}

	function update_order_cancel()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $req_data = $_POST['req_data'];

	    /* CPO-15 / D4: a Common PO carrying a live advance or a live supplier bill
	       cannot be cancelled — those have to be cancelled first, innermost outward.
	       Checked for the whole batch up front so a bulk cancel is all-or-nothing
	       rather than stopping halfway with some POs cancelled and others not. */
	    foreach ($req_data as $chk) {

	        $cpo_id = isset($chk['id_customerorder']) ? (int)$chk['id_customerorder'] : 0;
	        if ($cpo_id <= 0) { continue; }

	        $cpo_bills = $this->$model->getCommonPoLiveBills($cpo_id);
	        if (!empty($cpo_bills)) {
	            $refs = array();
	            foreach ($cpo_bills as $b) { $refs[] = $b['po_ref_no']; }
	            $msg = 'Purchase Order has a live supplier bill (' . implode(', ', $refs)
	                 . '). Cancel the supplier bill first.';
	            echo json_encode(array('status'=>FALSE,'msg'=>$msg,'message'=>$msg));
	            return;
	        }

	        $cpo_adv = $this->$model->getCommonPoLiveAdvances($cpo_id);
	        if (!empty($cpo_adv)) {
	            $refs = array();
	            foreach ($cpo_adv as $a) { $refs[] = $a['pay_refno']; }
	            $msg = 'Purchase Order has an advance payment against it (' . implode(', ', $refs)
	                 . '). Cancel the payment first.';
	            echo json_encode(array('status'=>FALSE,'msg'=>$msg,'message'=>$msg));
	            return;
	        }
	    }



        foreach($req_data as $items)

	    {

	       	$this->db->trans_begin();


	       	$this->$model->updateData(array('order_status'=>6,'cancelled_date'=>date("Y-m-d H:i:s"),'cancelled_by'=>$this->session->userdata('uid')),'id_customerorder',$items['id_customerorder'],'customerorder');

	       	/* CPO-15b: carry the cancel through to common_po_status (5 = Cancelled).
	       	   Without this a cancelled Common PO stayed at common_po_status 0 and kept
	       	   appearing in the advance payment and supplier bill dropdowns, which filter
	       	   on common_po_status IN (0,1,2) rather than order_status — an advance could
	       	   be paid against a purchase order that no longer exists. */
	       	$this->_set_common_po_status($items['id_customerorder'], 5);

		  	$this->$model->updateData(array('orderstatus'=> 0),'id_customerorder',$items['cus_ord_ref'],'customerorderdetails');

		 	$this->$model->updateData(array('orderstatus'=>6,'id_karigar'=>NULL),'id_customerorder',$items['id_customerorder'],'customerorderdetails');

			// Reset order_cart items linked to this PO back to pending (orderstatus=0) for reassignment
		 	$po_details = $this->db->query("SELECT id_orderdetails FROM customerorderdetails WHERE id_customerorder = ".$items['id_customerorder'])->result_array();
		 	foreach($po_details as $det) {
		 		if(!empty($det['id_orderdetails'])) {
		 			$this->db->where('id_orderdetails', $det['id_orderdetails']);
		 			$this->db->update('order_cart', array('orderstatus' => 0, 'id_orderdetails' => NULL));
		 		}
		 	}

	    }


    	    if($this->db->trans_status()===TRUE)

            {

                $log_data = array(

                        	'id_log'        => $this->session->userdata('id_log'),

                        	'event_date'	=> date("Y-m-d H:i:s"),

                        	'module'      	=> 'Order Closed',

                        	'operation'   	=> 'Update',

                        	'record'        =>  NULL,

                        	'remark'       	=> 'Order Cancelled.'

                        );

                $this->log_model->log_detail('insert','',$log_data);



                $this->db->trans_commit();

                $responseData=array('status'=>true,'message'=>'Order Cancelled successfully.');

            }

            else

            {

                $this->db->trans_rollback();

                $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

            }

            echo json_encode($responseData);

	}



    //Order cancel and close





    //Order Delivery

    function get_karigar_pending_orders()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_karigar_pending_orders($_POST);

        echo json_encode($data);

    }



    function get_karigar_pending_order_details()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_purchase_order_pending_details($_POST);

        echo json_encode($data);

    }



    function update_order_delivery()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $req_data   =$_POST['req_data'];

        foreach($req_data as $items)

	    {

	       $this->db->trans_begin();

	       if($items['order_pcs']>($items['delivered_pcs']+$items['tot_delivered_pcs']))

	       {

                $orderData=array(

                    'delivered_qty'     =>$items['delivered_pcs'],

                    'delivered_wt'      =>($items['delivered_wt']!='' ? $items['delivered_wt']:0),

                    'id_orderdetails'   =>$items['id_orderdetails'],

                    'is_partial_delivery'=>1

                    );

                $status=$this->$model->update_partial_order_delivery($orderData,'+');

                //print_r($this->db->last_query());exit;

                if($status)

                {

                    $insData=array(

                                  'id_joborder' =>$items['id_joborder'],

                                  'delivered_qty'=>$items['delivered_pcs'],

                                  'delivered_date'=>date("Y-m-d H:i:s")

                                  );

                    $insOrder = $this->$model->insertData($insData,'joborder_partial_delivery');



                }

	       }

	       else if($items['order_pcs']<=$items['delivered_pcs']+$items['tot_delivered_pcs'])

	       {

	           $updData=array(

	                          'delivered_date'=>date("Y-m-d H:i:s"),

	                          'orderstatus'   =>5,

	                          'delivered_by'  =>$this->session->userdata('uid'),

	                         );



	           $this->$model->updateData($updData,'id_orderdetails',$items['id_orderdetails'],'customerorderdetails');



	            $orderData=array(

                    'delivered_qty'     =>$items['delivered_pcs'],

                    'delivered_wt'      =>$items['delivered_wt'],

                    'id_orderdetails'   =>$items['id_orderdetails'],

                    );

                $status=$this->$model->update_order_delivery($orderData,'+');



	       }

	    }

    	    if($this->db->trans_status()===TRUE)

            {



                $log_data = array(

                        	'id_log'        => $this->session->userdata('id_log'),

                        	'event_date'	=> date("Y-m-d H:i:s"),

                        	'module'      	=> 'Order Delivery',

                        	'operation'   	=> 'Update',

                        	'record'        =>  NULL,

                        	'remark'       	=> 'Order Delivered.'

                        );

                $this->log_model->log_detail('insert','',$log_data);



                $this->db->trans_commit();

                $responseData=array('status'=>true,'message'=>'Order Delivered successfully.');

            }

            else

            {

                echo $this->db->last_query();exit;

                $this->db->trans_rollback();

                $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

            }

            echo json_encode($responseData);

	}



    //Order Delivery



    //Metal Issue



    public function karigarmetalissue_acknowladgement($id)

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$set_model = "admin_settings_model";

		$data['comp_details'] = $this->$set_model->get_company(1);

		$data['issue']        = $this->$model->getMetalIssue($id);

		$data['issue_details']   = $this->$model->getMetalIssueDetails($id);

		$data['type']=$type;

		// echo "<pre>"; print_r($data);exit;

		$this->load->helper(array('dompdf', 'file'));

	        $dompdf = new DOMPDF();

			$html = $this->load->view('ret_purchase/metalissue/vendor_ack', $data,true);

			$dompdf->load_html($html);

			$dompdf->set_paper('A4', "portriat" );

			$dompdf->render();

			while(ob_get_level()) ob_end_clean();
			$dompdf->stream("VendorAck.pdf",array('Attachment'=>0));

	}



  function karigarmetalissue($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;

		switch($type)

		{
			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'metalissue/list';

					$data['access'] = $this->$SETT_MOD->get_access('admin_ret_purchase/karigarmetalissue/list');

        			$this->load->view('layout/template', $data);

			break;

			case 'add':
					$data['financial_year']  = $this->$model->GetFinancialYear();

                    $data['is_supplierbill_entry_req']      = $this->$model->get_ret_settings('is_supplierbill_entry_req');

					$data['is_against_po_bills']      = $this->$model->get_ret_settings('issue_against_po_bills');

					$data['main_content'] = self::VIEW_FOLDER.'metalissue/form';

        			$this->load->view('layout/template', $data);

			break;


			case 'available_stock_details':

				    $data=$this->$model->get_available_stock_details($_POST);

				    echo json_encode($data);

			break;

			case 'available_metal_opening_stock_details':
				$data=$this->$model->get_opening_metal_stock_list($_POST);
				echo json_encode($data);
		break;

			case 'get_approval_po_bills':
					$data = $this->$model->get_approval_po_bills($_POST);
					echo json_encode($data);
			break;

			case 'search_po':
					$data = $this->$model->get_po_details_by_ref_no($_POST);
					echo json_encode($data);
			break;


			case'save':

			        $responseData = array();

			        // Server-side mutual exclusion: Against Opening cannot be combined with Against Order or Supplier Bill Entry
		        if ($this->input->post('is_against_opening') == 1 && $this->input->post('is_against_order') == 1) {
		            echo json_encode(array('status' => false, 'message' => 'Against Opening and Against Order cannot both be Yes.'));
		            break;
		        }
		        if ($this->input->post('is_against_opening') == 1 && $this->input->post('issue_against_po') == 1) {
		            echo json_encode(array('status' => false, 'message' => 'Against Opening and Against Supplier Bill Entry cannot both be Yes.'));
		            break;
		        }

			       // echo "<pre>";print_r($_POST);exit;

			        $addData         =  $_POST['issue'];

			        $ref_no          =  $this->$model->get_metal_issue_ref_no();

			        $ho              =  $this->$model->get_headOffice();

                    $dCData          =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

				    $bill_date       =  ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

					$fin_year       = $this->$model->get_FinancialYear();



					$insData=array(

						'met_issue_karid'     =>$_POST['id_karigar'],

						'id_branch'           =>$ho['id_branch'],

						'issue_aganist'       =>$_POST['is_against_order'],

						'is_against_opening'  =>$_POST['is_against_opening'],

						'issue_from'          =>  ( $_POST['is_stock_repair_order'] == 1 ?  1 : $_POST['issue_from'] ),

						'tag_issue_from'      =>$_POST['tag_issue_from'],

						'nontag_issue_from'   =>$_POST['nontag_issue_from'],

						'issue_against_po'    =>($_POST['issue_against_po']!=''  &&  $_POST['is_stock_repair_order'] !=1 ? $_POST['issue_against_po']:0 ),

						'metalissue_type'    =>($_POST['metal_issue_type']!='' ? $_POST['metal_issue_type'] :0),//1-Normal Issue,2-Approval Issue

						'id_order'            =>(!empty($_POST['id_order']) ? $_POST['id_order']:NULL),

						'met_issue_ref_id'    =>($fin_year['fin_year_code'].'-'.$ref_no),

						'met_issue_date'      =>$bill_date,

						'remark'              => $_POST['remark'],

						'met_issue_created_on'=>date("Y-m-d H:i:s"),

						'met_issue_created_by'=>$this->session->userdata('uid'),

						'po_id'				  =>$_POST['against_po'],

						);

			        $this->db->trans_begin();

			        $insId = $this->$model->insertData($insData,'ret_karigar_metal_issue');

			        if($insId)

			        {

						$metal_details = $_POST['issued_items'];

						foreach($metal_details as $key => $val)

			            {

			                //$purity=$this->$model->get_metal_issue_purity($metal_details['purity'][$key]);

			                $metal_data=array(

								'issue_met_parent_id'      =>$insId,

								'issue_metal'              =>$val['metal'],

								'issue_cat_id'             =>$val['category'],

								'issue_pur_id'             =>$val['purity'],

								'issu_met_pro_id'          =>$val['id_product'],

								'issu_met_id_design'       =>$val['id_design'],

								'issu_met_id_sub_design'   =>$val['id_sub_design'],

								'issue_pcs'                =>(isset($val['pcs']) && $val['pcs'] !== '' ? $val['pcs'] : NULL),

								'issue_metal_wt'           =>$val['weight'],

								'issue_uom_id'             =>$val['uom_id'],

								'issue_metal_pur_wt'       =>$val['pur_weight'],

								'issu_met_id_section'      =>$val['id_section'],

								'po_item_id'               =>($val['po_item_id']!='' ? $val['po_item_id'] : NULL),

								'id_smith_company_op_balance' =>($val['id_smith_company_op_balance']!='' ? $val['id_smith_company_op_balance'] : NULL),

								'tag_id'                   =>($val['tag_id']!='' ? $val['tag_id'] : NULL),

								'bill_det_id'              =>($val['bill_det_id']!='' ? $val['bill_det_id'] : NULL),

								'branch_trans_id'          =>($val['branch_trans_id']!='' ? $val['branch_trans_id'] : NULL),
												
								'touch'             		=>$val['touch'],

							);

							if($val['is_repair_item']==1){

								$metal_data['mc']=$val['mc'];
								$metal_data['mc_type']=$val['mc_type'];
								$metal_data['wastage']=$val['issue_va'];
								$metal_data['touch']=$val['issue_touch'];
								$metal_data['calc_type']=$val['calc_type'];
								$metal_data['net_wt']=$val['issue_net_wt'];
								$metal_data['less_wt']=$val['issue_less_wt'];
								$metal_data['is_repair_item']=$val['is_repair_item'];
								

							}

			               $status=$this->$model->insertData($metal_data,'ret_karigar_metal_issue_details');

						  // $stones=[];

						   $stone_details = json_decode($val['stone_details']);
   
						  if(sizeof($stone_details)>0)
   
						  {
   
							  
   
							  foreach($stone_details as $stone)
   
							  {
   
								  $stones=array(
   
									  'issue_met_id'=>$status,
   
									  'stone_pcs'	     => $stone->stone_pcs,
   
									  'stone_wt'		=> $stone->stone_wt,
   
									  'uom_id'		=> $stone->stone_uom_id,
   
									  'stone_id'		=> $stone->stone_id,
   
									  'stone_quality_id'=> $stone->stone_quality_id,
   
									  'is_apply_in_lwt '=> $stone->show_in_lwt,
   
									  'stone_cal_type  '=> $stone->stone_cal_type,
   
									  'rate_per_gram'=> $stone->stone_rate,
   
									  'price'=> $stone->stone_price,
   
								  );
   
								//   echo "<pre>";print_r($stones);exit;
   
								 $this->$model->insertData($stones,'ret_karigar_metal_issue_stone_details');
   
							  }
   
   
   
						  }


						   	if(($status && $_POST['issue_from'] == 1) && ($_POST['tag_issue_from']==1 && $_POST['issue_against_po']!=1) || ($status && $_POST['is_stock_repair_order']==1))  // Issue against Tag
							{
								$tagDetails = $this->$model->get_tag_details($val['tag_id']);

								$dCData = $this->admin_settings_model->getBranchDayClosingData($tagDetails['current_branch']);

								$bill_date = ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

								$tagStatus=$this->$model->updateData(array('tag_status'=>17,'updated_by'=>$this->session->userdata('uid'),	'updated_time'=>date("Y-m-d H:i:s")),'tag_id',$val['tag_id'],'ret_taging');

								if($tagStatus)
								{
									//Update Tag Log status
									$tag_log=array(
										'tag_id'	            =>$val['tag_id'],
										'date'		            =>$bill_date,
										'status'	            =>17,
										'from_branch'           =>$tagDetails['current_branch'],
										'to_branch'	            =>NULL,
										'issuspensestock'	    =>0,
										'created_on'            =>date("Y-m-d H:i:s"),
										'created_by'            =>$this->session->userdata('uid'),
									);
									$this->$model->insertData($tag_log,'ret_taging_status_log');
									// update in section log

									$sectag_data =array(
										'tag_id'	        => $val['tag_id'],
										'date'		        => $bill_date,
										'status'	        => 17,
										'from_branch'       => $tagDetails['current_branch'],
										'to_branch'	        => NULL,
										'created_on'        => date("Y-m-d H:i:s"),
										'created_by'        => $this->session->userdata('uid'),
										'issuspensestock'   => 0,
										'from_section'      => $tagDetails['id_section'],
										'to_section'        => NULL,
									);

									$this->$model->insertData($sectag_data,'ret_section_tag_status_log');
								}
							}


			               if(($status && $_POST['issue_from']==2) && ($_POST['nontag_issue_from']==1 && $_POST['issue_against_po']!=1))  // Issue against Non Tag

			               {
								$existData=array('id_section'=>$val['id_section'],'id_product'=>$val['id_product'],'design'=>$val['id_design'],'id_sub_design'=>$val['id_sub_design'],'id_branch'=>$ho['id_branch']);

    						    $isExist = $this->$model->checkNonTagItemExist($existData);

    						        if($isExist['status'] == TRUE)

    						        {

    						            $nt_data = array(

											'id_nontag_item'=>$isExist['id_nontag_item'],

											'no_of_piece'	=> $val['pcs'],

											'gross_wt'		=> $val['weight'],

											'net_wt'		=> $val['weight'],

											'updated_by'	=> $this->session->userdata('uid'),

											'updated_on'	=> date('Y-m-d H:i:s'),

                                        );

                                        $this->$model->updateNTData($nt_data,'-');



                                        $non_tag_data=array(

											'product'	    => $val['id_product'],

											'design'	    => $val['id_design'],

											'id_sub_design'	=> $val['id_sub_design'],

											'no_of_piece'	=> $val['pcs'],

											'gross_wt'		=> $val['weight'],

											'net_wt'		=> $val['weight'],

											'from_branch'	=> $ho['id_branch'],

											'to_branch'	    => NULL,

											'ref_no'	    => $insId,

											'status'	    => 7,

											'date'          => $bill_date,

											'created_on'    => date("Y-m-d H:i:s"),

											'created_by'    =>  $this->session->userdata('uid')

                                        );

                                        $this->$model->insertData($non_tag_data,'ret_nontag_item_log');



                                        $section_nontag_log = array(

											'product'		=> $val['id_product'],

                                            'design'		=> $val['id_design'],

                                            'id_sub_design' => $val['id_sub_design'],

                                            'no_of_piece'	=> $val['pcs'],

                                            'gross_wt'		=> $val['weight'],

                                            'net_wt'		=> $val['weight'],

                                            "status"		=> 7,

                                            "from_branch"	=> $ho['id_branch'],

                                            "to_branch"		=> NULL,

                                            "from_section"	=> $val['id_section'],

                                            "to_section"    => NULL,

                                            "created_by"	=> $this->session->userdata('uid'),

                                            "created_on"	=> date('Y-m-d H:i:s'),

                                            "date"			=> $bill_date,

                                            );

                                        $this->$model->insertData($section_nontag_log,'ret_section_nontag_item_log');



    						        }

			               }

			            }



			        }



			            if($this->db->trans_status()===TRUE)

                        {

                            $log_data = array(

                                    	'id_log'        => $this->session->userdata('id_log'),

                                    	'event_date'	=> date("Y-m-d H:i:s"),

                                    	'module'      	=> 'Karigar Metal Issue',

                                    	'operation'   	=> 'Add',

                                    	'record'        =>  $insId,

                                    	'remark'       	=> 'Karigar Metal Issue Added Successfully.'

                                    );

                            $this->log_model->log_detail('insert','',$log_data);



                            $this->db->trans_commit();

                            $responseData=array('status'=>true,'message'=>'Metal Issued successfully.','insId'=>$insId);

                        }

                        else

                        {

                            //echo $this->db->last_query();exit;

                            $this->db->trans_rollback();

                            $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

                        }

                        echo json_encode($responseData);



			break;



			case 'metalissue_cancel':

				$this->db->trans_begin();

				$ho   =  $this->$model->get_headOffice();

				$metal_issue_id = $_POST['metal_issue_id'];

				$this->$model->updateData(array('bill_status' => 2),'met_issue_id',$metal_issue_id,'ret_karigar_metal_issue');

				$issuedet = $this->$model->get_KarigarMetal_issue($metal_issue_id);

				foreach($issuedet as $val)

				{

					$existData=array('id_product'=>$val['pro_id'],'id_branch'=>$ho['id_branch']);

					$isExist = $this->$model->checkNonTagItemExist($existData);

					if($isExist['status'] == TRUE)

					{

						$nt_data = array(

							'id_nontag_item'=> $isExist['id_nontag_item'],

							'gross_wt'		=> $val['weight'],

							'net_wt'		=> $val['weight'],

							'no_of_piece'   =>0,

							'updated_by'	=> $this->session->userdata('uid'),

							'updated_on'	=> date('Y-m-d H:i:s'),

							);

							$id = $this->$model->updateNTData($nt_data,'+');

					}

					if($val['tag_id']!='' )

					{

					   $this->$model->updateData(array('tag_status'=>0,'updated_time'=>date("Y-m-d H:i:s"),'updated_by'=>$this->session->userdata('uid')),'tag_id',$val['tag_id'], 'ret_taging');

					   $log_data=array(

						'tag_id'	  =>$val['tag_id'],

						'date'		  =>date('Y-m-d H:i:s'),

						'status'	  =>6,

						'from_branch' =>$val['id_branch'],

						'to_branch'   =>$val['id_branch'],

						'created_on'  =>date("Y-m-d H:i:s"),

						'created_by'  =>$this->session->userdata('uid'),

						);

						$this->$model->insertData($log_data,'ret_taging_status_log'); //Update Tag lot status

						$tag_status=$this->$model->get_tag_status($val['tag_id']);

						if($tag_status['id_section']!=null && $tag_status['id_section']!='')

						{

							$secttag_log = array(

								'tag_id'	          =>$val['tag_id'],

								'date'		          =>date("Y-m-d H:i:s"),

								'status'	          =>6,

								'from_branch'         =>$val['id_branch'],

								'to_branch'           =>$val['id_branch'],

								'from_section'        =>$tag_status['id_section'],

								'to_section'          =>$tag_status['id_section'],

								'created_on'          =>date("Y-m-d H:i:s"),

								'created_by'          =>$this->session->userdata('uid'),

							);

							$this->$model->insertData($secttag_log,'ret_section_tag_status_log');

						}

					}

				}

				if( $this->db->trans_status()===TRUE)

			    {

			    	 $this->db->trans_commit();

					 $log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Karigar Metal Issue',

						'operation'     => 'Cancel',

						'record'        => $metal_issue_id,

						'remark'        => 'Karigar Metal Issue cancelled successfully'

						);

				        $this->log_model->log_detail('insert','',$log_data);

			    	    echo json_encode(array("stattus" => true, "message" => "ISSUE cancel successfully"));

			    }else{

			         $this->db->trans_rollback();

			         echo json_encode(array("stattus" => false, "message" => "Could not cancel this issue"));

			    }

            break;



			case 'ajax':

				$list=$this->$model->getKarigarMetalIssueList();

				$profile=$this->$model->get_profile_settings($this->session->userdata('profile'));

			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/karigarmetalissue/list');

		        $data = array(

		        					'list'  => $list,

									'profile' =>$profile,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;

		}

	}





	function get_karigar_pending_ordes()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $data=$this->$model->get_karigar_pending_ordes($_POST);

	    echo json_encode($data);

	}



	function get_karigar_details(){

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $data = $this->$model->get_karigar_details($_POST['karId']);

	    echo json_encode($data);

	}



	function get_OrderProducts()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $data=$this->$model->get_OrderProducts($_POST);

	    echo json_encode($data);

	}



	function get_OrderProductsDesign()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $data=$this->$model->get_OrderProductsDesign($_POST);

	    echo json_encode($data);

	}



	function get_OrderSubDesigns()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $data=$this->$model->get_OrderSubDesigns($_POST);

	    echo json_encode($data);

	}





	/**
	 * CPO-15: reverse a Common PO advance adjustment when its supplier bill is cancelled.
	 *
	 * Decision D4 is block-first, strict LIFO: a bill can always be cancelled, and doing
	 * so hands the advance back. It is cancelling further UP the chain that is blocked
	 * while something downstream still depends on it.
	 *
	 * Reverses everything _adjust_common_po_advance() wrote:
	 *   ret_common_po_adjustment      soft-reversed (status 2) — never deleted, so the
	 *                                 audit trail survives the cancellation
	 *   ret_po_bill_payment_details   deleted — these rows are what the pending-bill
	 *                                 list nets off, so leaving them would keep
	 *                                 suppressing value for a bill that no longer exists
	 *   ret_po_payment.advance_adjusted   reduced by exactly what this bill consumed
	 *   ret_purchase_order.advance_adjusted_amt  cleared
	 *   customerorder                 receipt figures rolled back, status back to
	 *                                 Advance Paid (1) or Open (0)
	 *
	 * Must be called INSIDE the caller's transaction. Returns the amount released.
	 */
	private function _reverse_common_po_bill($po_id, $reason = 'Supplier bill cancelled')
	{
	    $model = self::RET_PUR_ORDER_MODEL;

	    $adjustments = $this->$model->getCommonPoAdjustmentsForBill($po_id);

	    if (empty($adjustments)) {
	        // Still roll the receipt back if this was a Common PO bill without an advance.
	        $this->_rollback_common_po_receipt($po_id);
	        return 0;
	    }

	    $released = 0;
	    $now      = date('Y-m-d H:i:s');
	    $uid      = $this->session->userdata('uid');
	    $ref      = 0;

	    foreach ($adjustments as $adj) {

	        $amt = round((float)$adj['adjusted_amt'], 2);
	        $ref = (int)$adj['common_po_ref'];

	        // Soft reverse — the row stays as evidence that it happened and was undone.
	        $this->$model->updateData(array(
	            'adjustment_status' => 2,
	            'reversed_on'       => $now,
	            'reversed_by'       => $uid,
	            'reverse_reason'    => $reason,
	        ), 'id_common_po_adjustment', (int)$adj['id_common_po_adjustment'], 'ret_common_po_adjustment');

	        $this->$model->deleteCommonPoBillPaymentLinks($po_id, (int)$adj['pay_id']);

	        $pay = $this->$model->getCommonPoAdvancePositionByPayment((int)$adj['pay_id']);
	        $new_adjusted = round((float)$pay['advance_adjusted'] - $amt, 2);
	        if ($new_adjusted < 0) { $new_adjusted = 0; }

	        $this->$model->updateData(
	            array('advance_adjusted' => number_format($new_adjusted, 2, '.', '')),
	            'pay_id', (int)$adj['pay_id'], 'ret_po_payment'
	        );

	        $released += $amt;
	    }

	    $this->$model->updateData(
	        array('advance_adjusted_amt' => '0.00'),
	        'po_id', (int)$po_id, 'ret_purchase_order'
	    );

	    $this->_rollback_common_po_receipt($po_id);

	    if ($ref > 0) {
	        // Back to Advance Paid — the money is still held, just no longer consumed.
	        $this->$model->updateData(
	            array('common_po_status' => 1),
	            'id_customerorder', $ref, 'customerorder'
	        );
	    }

	    $this->log_model->log_detail('insert', '', array(
	        'id_log'     => $this->session->userdata('id_log'),
	        'event_date' => $now,
	        'module'     => 'Common Purchase Order',
	        'operation'  => 'Advance Adjustment Reversed',
	        'record'     => $po_id,
	        'remark'     => $reason . '; advance of '
	                        . number_format($released, 2, '.', '') . ' released back to the Common PO',
	    ));

	    return $released;
	}

	/**
	 * CPO-15: roll the receipt figures back off the Common PO when its bill is
	 * cancelled, so a cancelled bill stops counting as delivered metal.
	 */
	private function _rollback_common_po_receipt($po_id)
	{
	    $model = self::RET_PUR_ORDER_MODEL;
	    $bill  = $this->$model->getCommonPoForBill($po_id);

	    if (!$bill || empty($bill['common_po_ref'])) { return; }

	    $received = $this->$model->getCommonPoBillReceipt($po_id);
	    $po       = $this->$model->getCommonPoCommitment($bill['common_po_ref']);

	    if (!$po) { return; }

	    $wt  = round((float)$po['delivered_wt'] - (float)$received['net_wt'], 3);
	    $pcs = (int)$po['delivered_qty'] - (int)$received['no_of_pcs'];

	    if ($wt  < 0) { $wt  = 0; }
	    if ($pcs < 0) { $pcs = 0; }

	    $this->$model->updateData(array(
	        'delivered_wt'  => number_format($wt, 3, '.', ''),
	        'delivered_qty' => $pcs,
	    ), 'id_customerorder', (int)$bill['common_po_ref'], 'customerorder');
	}

	/**
	 * CPO-15: may this Common PO advance be cancelled?
	 *
	 * D4: no, while any of it is still adjusted against a live bill — the bill must be
	 * cancelled first. Blocking is deliberate; cascading the reversal upward would mean
	 * a payment cancellation silently rewriting a supplier bill.
	 *
	 * Returns an error string, or NULL when the cancellation may proceed.
	 */
	private function _block_common_po_payment_cancel($pay_id)
	{
	    $model    = self::RET_PUR_ORDER_MODEL;
	    $adjusted = $this->$model->getCommonPoAdvanceAdjusted($pay_id);

	    if ($adjusted > 0.005) {
	        return 'This advance has been adjusted against a supplier bill ('
	               . number_format($adjusted, 2, '.', '')
	               . '). Cancel the supplier bill first, then cancel this payment.';
	    }

	    return NULL;
	}

	/**
	 * CPO-12: may the current user override the rate on a Common PO bill?
	 *
	 * BRS acceptance criterion 17 and section 10 — configurable at role level, default
	 * restricted. Granted through the existing Access screen against the menu link
	 * admin_ret_purchase/common_po_rate_override, so there is no second permission
	 * mechanism to keep in step with the first.
	 *
	 * No access row at all means no override, which is what "default is restricted"
	 * requires: the absence of a grant is a denial, not an unknown.
	 */
	/**
	 * CPO-15b: stamp common_po_status on a Common PO, and only on a Common PO.
	 *
	 * The lifecycle status lives in its own column because order_status is shared with
	 * the customer-order flow and gets rewritten by delivery and cancel (see CPO-08a).
	 * That separation is what makes eligibility reliable — but it also means every
	 * status transition has to be mirrored here explicitly, or the two drift apart.
	 *
	 * Scoped with is_common_po = 1 so an ordinary purchase order is never touched.
	 *
	 * @param int $id_customerorder
	 * @param int $status  0 Open, 1 Advance Paid, 2 Received, 3 Billed, 4 Closed, 5 Cancelled
	 */
	/**
	 * PO-EDIT-4: build one customerorderdetails row from the posted order_details.
	 *
	 * Lifted verbatim out of case "save" so the create and update paths cannot
	 * disagree about what a PO line contains. Behaviour is unchanged — the same
	 * empty/'null' string tests, the same defaults.
	 *
	 * @param array  $order_details  $_POST['order_details']
	 * @param mixed  $key            index into each parallel array
	 * @param array  $addData        $_POST['order']
	 * @param int    $id_customerorder  owning header row
	 * @param string $smith_due_date    already normalised to Y-m-d or NULL
	 */
	private function _po_line_payload($order_details, $key, $addData, $id_customerorder, $smith_due_date)
	{
	    return array(
	        'id_customerorder'=> $id_customerorder,
	        'id_product'      => $order_details['product'][$key],
	        'cus_orderdet_ref'=> ($order_details['id_orderdetails'][$key]!='' ? $order_details['id_orderdetails'][$key]:NULL),
	        'design_no'       => ($order_details['design'][$key]!='' ? $order_details['design'][$key]:NULL),
	        'id_sub_design'   => ($order_details['sub_design'][$key]!='' ? $order_details['sub_design'][$key]:NULL),
	        'id_weight_range' => ($order_details['weight_range'][$key]!='' && $order_details['weight_range'][$key]!='null' ? $order_details['weight_range'][$key] :NULL),
	        'weight'          => ($order_details['order_wt'][$key]!='' ? $order_details['order_wt'][$key]:NULL),
	        'size'            => ($order_details['size'][$key]!='' && $order_details['size'][$key]!='null' ? $order_details['size'][$key] :NULL),
	        'less_wt'         => ($order_details['less_wt'][$key]!='' && $order_details['less_wt'][$key]!='null' ? $order_details['less_wt'][$key] :NULL),
	        'net_wt'          => ($order_details['net_wt'][$key]!='' && $order_details['net_wt'][$key]!='null' ? $order_details['net_wt'][$key] :NULL),
	        'stn_amt'         => ($order_details['stn_amt'][$key]!='' && $order_details['stn_amt'][$key]!='null' ? $order_details['stn_amt'][$key] :0),
	        'mc'              => ($order_details['mc_value'][$key]!='' && $order_details['mc_value'][$key]!='null' ? $order_details['mc_value'][$key] :0),
	        'wast_percent'    => ($order_details['wast_percent'][$key]!='' && $order_details['wast_percent'][$key]!='null' ? $order_details['wast_percent'][$key] :0),
	        'description'     => ($order_details['description'][$key]!='' ? $order_details['description'][$key] :NULL),
	        'totalitems'      => $order_details['piece'][$key],
	        'smith_due_date'  => $smith_due_date,
	        'orderstatus'     => 3,
	        'id_karigar'      => $addData['id_karigar'],
	        'rate_per_gram'   => ($this->input->post('rate_value')!='' ? $this->input->post('rate_value') : 0),
	    );
	}

	/**
	 * PO-EDIT-2: server-side re-check of edit eligibility.
	 *
	 * The list hides the Edit button and the edit screen refuses to render when a PO
	 * is not editable, but both are display conventions — the update endpoint is
	 * reachable directly. A PO that has been received or billed must never be
	 * rewritten, so the same rules are enforced here on save.
	 *
	 * Returns an error string, or NULL when the edit may proceed.
	 */
	private function _block_po_edit($id_customerorder)
	{
	    $model    = self::RET_PUR_ORDER_MODEL;
	    $blockers = $this->$model->get_po_edit_blockers($id_customerorder);

	    return empty($blockers) ? NULL : implode(' ', $blockers);
	}

	private function _set_common_po_status($id_customerorder, $status)
	{
	    $id_customerorder = (int)$id_customerorder;
	    if ($id_customerorder <= 0) { return; }

	    $this->db->where('id_customerorder', $id_customerorder);
	    $this->db->where('is_common_po', 1);
	    $this->db->update('customerorder', array('common_po_status' => (int)$status));
	}

	private function _can_override_common_po_rate()
	{
	    $acc = $this->admin_settings_model->get_access('admin_ret_purchase/common_po_rate_override');
	    return (!empty($acc) && (!empty($acc['edit']) || !empty($acc['add'])));
	}

	/**
	 * CPO-12: reject a bill whose rate departs from the Common PO without permission.
	 *
	 * The screen renders the rate readonly for users without the right, but readonly is
	 * a display convention — the value still posts and can be edited in the browser.
	 * The agreed rate is the whole point of a Common PO, so the check belongs here.
	 *
	 * Compared per product against the PO's stored rate using the same tolerance as
	 * elsewhere: browser and PHP disagree on half-way rounding, and rejecting over a
	 * paisa would be indistinguishable from a genuine override.
	 *
	 * Returns an error string, or NULL when the bill may proceed.
	 */
	private function _block_common_po_rate_override($po_id)
	{
	    $model = self::RET_PUR_ORDER_MODEL;
	    $bill  = $this->$model->getCommonPoForBill($po_id);

	    if (!$bill || empty($bill['common_po_ref'])) { return NULL; }
	    if ($this->_can_override_common_po_rate())   { return NULL; }

	    $po_lines   = $this->$model->getCommonPoBillLines($bill['common_po_ref']);
	    $bill_lines = $this->$model->getCommonPoBillItemRates($po_id);

	    if (empty($po_lines) || empty($bill_lines)) { return NULL; }

	    /* Fixed-rate lines only. An Unfixed PO carries no agreed rate — BRS 4.1 puts it
	       at Supplier Bill Entry — so there is nothing to override. */
	    $agreed = array();
	    foreach ($po_lines as $l) {
	        if ((int)$l['rate_type_fix'] === 1 && (float)$l['rate_per_gram'] > 0) {
	            $agreed[(int)$l['id_product']] = round((float)$l['rate_per_gram'], 2);
	        }
	    }

	    if (empty($agreed)) { return NULL; }

	    foreach ($bill_lines as $b) {

	        $pro = (int)$b['po_item_pro_id'];
	        if (!isset($agreed[$pro])) { continue; }

	        $billed = round((float)$b['fix_rate_per_grm'], 2);

	        if (!$this->_cpo_amt_equals($billed, $agreed[$pro])) {
	            return 'The rate on this bill (' . number_format($billed, 2, '.', '')
	                 . ') differs from the rate fixed on Common PO ' . $bill['pur_no']
	                 . ' (' . number_format($agreed[$pro], 2, '.', '')
	                 . '). Overriding an agreed rate requires the Common PO Rate Override permission.';
	        }
	    }

	    return NULL;
	}

	/**
	 * CPO-12a: normalise a Common PO supplier bill to the BRS bullion field set.
	 *
	 * BRS section 4.1 defines a bullion line as Category, Purity, Product, Design,
	 * Sub Design, Size, Pcs, Gross Wt, Stone Wt, Net Wt, Rate Type, Rate per Gram,
	 * Taxable, Tax and Total. There is no wastage and no making charge — bullion is
	 * bought as metal at an agreed rate per gram.
	 *
	 * Those fields belong to the ORNAMENT entry set, which is hidden when Purchase
	 * Type is Bullion but still submits whatever it last held. A bill raised while
	 * Purchase Type was Ornaments, or switched afterwards, therefore carries a
	 * wastage percentage against pure metal — silently inflating pure weight through
	 *     purewt = net_wt * (purity + wastage) / 100
	 * and with it the whole line value.
	 *
	 * Forced to zero server-side rather than trusted from the form, and only for
	 * bills against a Common PO. Ordinary purchases are untouched.
	 *
	 * Must be called INSIDE the caller's transaction.
	 */
	private function _normalise_common_po_bill($po_id)
	{
	    $model = self::RET_PUR_ORDER_MODEL;
	    $bill  = $this->$model->getCommonPoForBill($po_id);

	    if (!$bill || empty($bill['common_po_ref'])) { return; }

	    $cleared = $this->$model->clearCommonPoBillOrnamentFields($po_id);

	    if ($cleared) {
	        $this->log_model->log_detail('insert', '', array(
	            'id_log'     => $this->session->userdata('id_log'),
	            'event_date' => date("Y-m-d H:i:s"),
	            'module'     => 'Common Purchase Order',
	            'operation'  => 'Bullion Normalisation',
	            'record'     => $po_id,
	            'remark'     => 'Wastage and making charge cleared on ' . $cleared
	                            . ' line(s): BRS 4.1 defines no wastage or making charge for bullion.',
	        ));
	    }
	}

	/**
	 * CPO-10: record the receipt against the Common PO from the supplier bill.
	 *
	 * The GRN screen is deliberately NOT changed. A Common PO and a GRN already meet
	 * on the supplier bill, which carries both links:
	 *
	 *     customerorder <-- purchase_order_no -- ret_purchase_order -- po_grn_id --> ret_grn_entry
	 *
	 * so the bill is the join point and the received quantity is taken from the bill
	 * lines rather than from a second selector on the GRN.
	 *
	 * Writes the received weight and pieces onto customerorder.delivered_wt /
	 * delivered_qty (both columns already exist and already mean this), and applies
	 * the D2 tolerance so a short or excess delivery is visible instead of silent.
	 *
	 * Must be called INSIDE the caller's transaction. Returns the variance in grams
	 * (positive = excess, negative = short), or NULL when not a Common PO bill.
	 */
	private function _record_common_po_receipt($po_id)
	{
	    $model = self::RET_PUR_ORDER_MODEL;

	    $bill = $this->$model->getCommonPoForBill($po_id);

	    if (!$bill || empty($bill['common_po_ref'])) {
	        return NULL;
	    }

	    $received = $this->$model->getCommonPoBillReceipt($po_id);
	    $po       = $this->$model->getCommonPoCommitment($bill['common_po_ref']);

	    if (!$po) { return NULL; }

	    $committed_wt = round((float)$po['order_approx_wt'], 3);
	    $received_wt  = round((float)$received['net_wt'], 3);
	    $received_pcs = (int)$received['no_of_pcs'];
	    $variance     = round($received_wt - $committed_wt, 3);

	    $this->$model->updateData(array(
	        'delivered_wt'  => number_format($received_wt, 3, '.', ''),
	        'delivered_qty' => $received_pcs,
	    ), 'id_customerorder', (int)$bill['common_po_ref'], 'customerorder');

	    /* D2: a variance inside tolerance passes silently; outside it is logged so the
	       difference is on the record rather than absorbed. The BRS puts staggered
	       delivery out of scope, so one bill closes the commitment either way — but a
	       short delivery that nobody notices is how a supplier ends up paid for metal
	       that never arrived. */
	    $tolerance_pct = (float)$this->$model->get_common_po_setting('common_po_grn_tolerance_percent', '0.00');
	    $allowed       = round(($committed_wt * $tolerance_pct) / 100, 3);

	    if ($committed_wt > 0 && abs($variance) > $allowed + 0.0005) {
	        $this->log_model->log_detail('insert', '', array(
	            'id_log'     => $this->session->userdata('id_log'),
	            'event_date' => date("Y-m-d H:i:s"),
	            'module'     => 'Common Purchase Order',
	            'operation'  => ($variance < 0 ? 'Short Delivery' : 'Excess Delivery'),
	            'record'     => $po_id,
	            'remark'     => 'Common PO ' . $bill['pur_no'] . ': committed '
	                            . number_format($committed_wt, 3, '.', '') . ' g, received '
	                            . number_format($received_wt, 3, '.', '') . ' g, variance '
	                            . number_format($variance, 3, '.', '') . ' g (tolerance '
	                            . number_format($tolerance_pct, 2, '.', '') . '%)',
	        ));
	    }

	    // Received. The adjustment that follows moves it on to Billed (3).
	    $this->$model->updateData(
	        array('common_po_status' => 2),
	        'id_customerorder', (int)$bill['common_po_ref'], 'customerorder'
	    );

	    return $variance;
	}

	/**
	 * CPO-13: adjust Common PO advances against a freshly posted supplier bill.
	 *
	 * BRS section 5 steps 9-10: once the bill is posted the advance is set against the
	 * invoice automatically, so the supplier ledger reflects a nil balance when the
	 * two match (step 11), or the remaining payable/receivable when they do not.
	 *
	 * Writes to TWO tables, and both are required:
	 *
	 *   ret_common_po_adjustment      the audit trail. One row per (payment, bill)
	 *                                 pair, soft-reversible, so CPO-15 can undo an
	 *                                 adjustment exactly.
	 *   ret_po_bill_payment_details   what the pending-bill list actually nets off
	 *                                 (getPending_payment_po_bills_for_payment
	 *                                 subtracts SUM(bill_amount) from this table).
	 *                                 Omitting it would settle the advance in the
	 *                                 audit table while the payment screen carried on
	 *                                 showing the bill at full value.
	 *
	 * Must be called INSIDE the caller's transaction — it does not begin or commit one.
	 *
	 * Returns the total adjusted.
	 */
	private function _adjust_common_po_advance($po_id)
	{
	    $model = self::RET_PUR_ORDER_MODEL;

	    $bill = $this->$model->getCommonPoForBill($po_id);

	    // Not a Common PO bill — nothing to do, and no error either.
	    if (!$bill || empty($bill['common_po_ref'])) {
	        return 0;
	    }

	    $bill_amount  = round((float)$bill['bill_amount'], 2);
	    $already      = $this->$model->getCommonPoAdjustedForBill($po_id);
	    $bill_balance = round($bill_amount - $already, 2);

	    if ($bill_balance <= 0) {
	        return 0;
	    }

	    $advances = $this->$model->getUnadjustedCommonPoAdvances($bill['common_po_ref']);

	    if (empty($advances)) {
	        return 0;
	    }

	    $total_adjusted = 0;
	    $now            = date('Y-m-d H:i:s');
	    $uid            = $this->session->userdata('uid');

	    foreach ($advances as $adv) {

	        if ($bill_balance <= 0) { break; }

	        // D3: take the lesser of what this advance still has and what the bill
	        // still owes, so neither is ever over-applied.
	        $take = min(round((float)$adv['unadjusted'], 2), $bill_balance);

	        if ($take <= 0) { continue; }

	        $this->$model->insertData(array(
	            'common_po_ref'     => (int)$bill['common_po_ref'],
	            'pay_id'            => (int)$adv['pay_id'],
	            'po_id'             => (int)$po_id,
	            'adjusted_amt'      => number_format($take, 2, '.', ''),
	            'adjustment_status' => 1,
	            'created_on'        => $now,
	            'created_by'        => $uid,
	        ), 'ret_common_po_adjustment');

	        // What the pending-bill list nets off.
	        $this->$model->insertData(array(
	            'pay_id'                => (int)$adv['pay_id'],
	            'po_id'                 => (int)$po_id,
	            'opening_id'            => 0,
	            'id_supplier_rate_cut'  => 0,
	            'bill_amount'           => number_format($take, 2, '.', ''),
	            'total_amount'          => number_format($take, 2, '.', ''),
	        ), 'ret_po_bill_payment_details');

	        $this->$model->updateData(
	            array('advance_adjusted' => number_format(round((float)$adv['advance_adjusted'] + $take, 2), 2, '.', '')),
	            'pay_id', (int)$adv['pay_id'], 'ret_po_payment'
	        );

	        $total_adjusted += $take;
	        $bill_balance    = round($bill_balance - $take, 2);
	    }

	    if ($total_adjusted > 0) {

	        $this->$model->updateData(
	            array('advance_adjusted_amt' => number_format(round($already + $total_adjusted, 2), 2, '.', '')),
	            'po_id', (int)$po_id, 'ret_purchase_order'
	        );

	        // Billed. The remaining payable/receivable is derived from the amounts,
	        // never inferred from the status (CPO-14).
	        $this->$model->updateData(
	            array('common_po_status' => 3),
	            'id_customerorder', (int)$bill['common_po_ref'], 'customerorder'
	        );

	        $this->log_model->log_detail('insert', '', array(
	            'id_log'     => $this->session->userdata('id_log'),
	            'event_date' => $now,
	            'module'     => 'Common Purchase Order',
	            'operation'  => 'Advance Adjustment',
	            'record'     => $po_id,
	            'remark'     => 'Advance of ' . number_format($total_adjusted, 2, '.', '')
	                            . ' adjusted against supplier bill for Common PO ' . $bill['pur_no'],
	        ));
	    }

	    return $total_adjusted;
	}

	/**
	 * CPO-05: save a Common Purchase Order (bullion advance rate fixing).
	 *
	 * Every derived value is RECOMPUTED here from the two inputs the user actually
	 * types — weights and rate per gram. The posted taxable / tax / total are used
	 * only to detect tampering; what gets stored is always the server's own figure.
	 * Per CLAUDE.md: never trust $_POST for financial values.
	 *
	 * Returns an array suitable for json_encode — the caller echoes it.
	 */
	/**
	 * @param int $update_id  0 to create; a PO id to amend that PO in place (PO-EDIT-4).
	 */
	private function _save_common_po($update_id = 0)
	{
	    $model   = self::RET_PUR_ORDER_MODEL;
	    $addData = $this->input->post('order');
	    $items   = $this->input->post('cpo_items');

	    /* -------- header validation -------- */

	    if (empty($addData['id_karigar'])) {
	        return array('status' => FALSE, 'msg' => 'Select Karigar');
	    }

	    if (empty($items) || empty($items['id_product'])) {
	        return array('status' => FALSE, 'msg' => 'Add at least one bullion item');
	    }

	    /* -------- parallel-array integrity --------
	       cpo_items arrives as parallel arrays, one per field. A short array would
	       silently pair the wrong weight with the wrong rate, so every field is
	       length-checked before a single row is read. */

	    $line_count = count($items['id_product']);
	    $required   = array('id_category', 'id_purity', 'id_product', 'no_of_pcs',
	                        'gross_wt', 'stone_wt', 'net_wt', 'rate_type', 'rate_per_gram',
	                        'tax_percent', 'taxable_amount', 'tax_amount', 'total_amount');

	    foreach ($required as $field) {
	        if (!isset($items[$field]) || count($items[$field]) != $line_count) {
	            return array('status' => FALSE, 'msg' => 'Bullion item data is incomplete. Please re-add the lines and try again.');
	        }
	    }

	    /* -------- per-line validation and recompute -------- */

	    $lines          = array();
	    $total_pcs      = 0;
	    $total_net_wt   = 0;
	    $total_gross_wt = 0;
	    $grand_total    = 0;
	    $has_fixed      = FALSE;
	    $has_unfixed    = FALSE;

	    for ($i = 0; $i < $line_count; $i++) {

	        $row_no      = $i + 1;
	        $id_category = (int)$items['id_category'][$i];
	        $id_purity   = (int)$items['id_purity'][$i];
	        $id_product  = (int)$items['id_product'][$i];
	        $rate_type   = ((int)$items['rate_type'][$i] === 2) ? 2 : 1;

	        if ($id_category <= 0) { return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Category is required'); }
	        if ($id_purity   <= 0) { return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Purity is required'); }
	        if ($id_product  <= 0) { return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Product is required'); }

	        $no_of_pcs = (int)$items['no_of_pcs'][$i];
	        $gross_wt  = round((float)$items['gross_wt'][$i], 3);
	        $stone_wt  = round((float)$items['stone_wt'][$i], 3);

	        if ($no_of_pcs <= 0)      { return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Pcs must be greater than zero'); }
	        if ($gross_wt  <= 0)      { return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Gross Weight must be greater than zero'); }
	        if ($stone_wt  <  0)      { return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Stone Weight cannot be negative'); }
	        if ($stone_wt >= $gross_wt) { return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Stone Weight must be less than Gross Weight'); }

	        // Authoritative net weight — the posted value is only cross-checked.
	        $net_wt = round($gross_wt - $stone_wt, 3);

	        /* CPO-05b: fine weight, read from ret_purity rather than $_POST. Bullion is
	           valued on pure weight worldwide, and Supplier Bill Entry already computes
	           item_cost = pure_wt * rate (ret_purchase_order.js:20239) using
	           pure_wt = net_wt * (purity + wastage) / 100. Bullion carries no wastage,
	           so the term reduces to purity. Billing the PO on net weight instead left a
	           permanent residue at any purity < 100% and BRS 5 step 11 could never
	           nullify the ledger. */
	        $purity_row   = $this->$model->get_purity_details($id_purity);
	        $purity_value = ($purity_row && isset($purity_row['purity'])) ? round((float)$purity_row['purity'], 4) : 0;

	        if ($purity_value <= 0) {
	            return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Selected purity has no value configured in the Purity master');
	        }

	        $pure_wt = round(($net_wt * $purity_value) / 100, 3);

	        // 0 = PureWt (default, matches gst_bill_rate_basis), 1 = GrossWt, 2 = NetWt
	        $rate_basis = isset($items['rate_basis'][$i]) ? (int)$items['rate_basis'][$i] : 0;
	        if (!in_array($rate_basis, array(0, 1, 2), TRUE)) { $rate_basis = 0; }

	        if ($rate_basis === 1)      { $rated_wt = $gross_wt; }
	        elseif ($rate_basis === 2)  { $rated_wt = $net_wt; }
	        else                        { $rated_wt = $pure_wt; }

	        if (!$this->_qc_wt_equals($net_wt, $items['net_wt'][$i])) {
	            return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Net Weight does not match Gross minus Stone. Please re-add the line.');
	        }

	        // CPO-05a: tax percentage and inclusive/exclusive basis are read from the
	        // product master, never from $_POST. The screen renders them readonly, but
	        // a readonly field is a display convention, not a control.
	        $tax_master    = $this->$model->getCommonPoProductTax($id_product);
	        $tax_percent   = $tax_master ? round((float)$tax_master['tax_percent'], 2) : 0;
	        $tax_group_id  = ($tax_master && $tax_master['tax_group_id'] !== NULL) ? (int)$tax_master['tax_group_id'] : NULL;
	        $tax_calc_type = ($tax_master && (int)$tax_master['purchase_tax_type'] === 1) ? 1 : 2;

	        if ($tax_percent < 0 || $tax_percent > 100) {
	            return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Product tax master is misconfigured (' . $tax_percent . '%). Correct the tax group before creating the PO.');
	        }

	        if ($rate_type == 1) {

	            $has_fixed     = TRUE;
	            $rate_per_gram = round((float)$items['rate_per_gram'][$i], 2);

	            if ($rate_per_gram <= 0) {
	                return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Rate per Gram is required for a Fixed rate line');
	            }

	            $value = $rated_wt * $rate_per_gram;

	            if ($tax_calc_type == 1) {
	                // Inclusive — the agreed rate already contains the tax, so the
	                // taxable base is back-derived and the line total IS the rate
	                // value. Adding tax on top would overstate the PO, and that
	                // inflated figure is what the advance payment is raised against.
	                $total_amount   = round($value, 2);
	                $taxable_amount = round(($value * 100) / (100 + $tax_percent), 2);
	                $tax_amount     = round($total_amount - $taxable_amount, 2);
	            } else {
	                // Exclusive — tax is added on top of the rate value.
	                $taxable_amount = round($value, 2);
	                $tax_amount     = round(($taxable_amount * $tax_percent) / 100, 2);
	                $total_amount   = round($taxable_amount + $tax_amount, 2);
	            }

	            // Tamper check — the browser and PHP round half-way values
	            // differently, so compare with tolerance, never with ==.
	            if (!$this->_cpo_amt_equals($taxable_amount, $items['taxable_amount'][$i])
	                || !$this->_cpo_amt_equals($tax_amount, $items['tax_amount'][$i])
	                || !$this->_cpo_amt_equals($total_amount, $items['total_amount'][$i])) {
	                return array('status' => FALSE, 'msg' => 'Row ' . $row_no . ': Submitted amounts do not match the calculated values. Please re-add the line.');
	            }

	        } else {

	            // BRS 4.1 — an Unfixed line records a quantity commitment only.
	            // Rate and amounts are forced to zero regardless of what was posted.
	            // tax_group_id / tax_calc_type are still recorded so the rate can be
	            // fixed later at Supplier Bill Entry on the same basis.
	            $has_unfixed    = TRUE;
	            $rate_per_gram  = 0;
	            $tax_percent    = 0;
	            $taxable_amount = 0;
	            $tax_amount     = 0;
	            $total_amount   = 0;
	        }

	        /* insertData() replaces any value PHP considers empty() with the column
	           default — and its guard that preserves a legitimate zero tests
	           `$value === 0 || $value === '0'`, which a FLOAT 0.0 fails. A zero stone
	           weight or a zero rate on an Unfixed line would therefore be written as
	           NULL on these nullable columns, and `gross - NULL` is NULL in every
	           downstream query. Numeric values are formatted to fixed-decimal STRINGS
	           ('0.000'), which empty() does not treat as empty, so zeros survive.
	           Weights carry 3 decimals and amounts 2, matching the column definitions. */
	        $lines[] = array(
	            'id_category'    => $id_category,
	            'id_purity'      => $id_purity,
	            'id_product'     => $id_product,
	            'design_no'      => (isset($items['design_no'][$i])     && $items['design_no'][$i]     !== '' ? (int)$items['design_no'][$i]     : NULL),
	            'id_sub_design'  => (isset($items['id_sub_design'][$i]) && $items['id_sub_design'][$i] !== '' ? (int)$items['id_sub_design'][$i] : NULL),
	            'size'           => (isset($items['size'][$i]) && $items['size'][$i] !== '' ? $items['size'][$i] : NULL),
	            'no_of_pcs'      => $no_of_pcs,
	            'totalitems'     => $no_of_pcs,
	            'weight'         => number_format($gross_wt, 3, '.', ''),
	            'less_wt'        => number_format($stone_wt, 3, '.', ''),
	            'pure_wt'        => number_format($pure_wt, 3, '.', ''),
	            'purity_value'   => number_format($purity_value, 4, '.', ''),
	            'rate_basis'     => $rate_basis,
	            'net_wt'         => number_format($net_wt, 3, '.', ''),
	            'rate_type_fix'  => $rate_type,
	            'rate_per_gram'  => number_format($rate_per_gram, 2, '.', ''),
	            'tax_percent'    => number_format($tax_percent, 2, '.', ''),
	            'tax_group_id'   => $tax_group_id,
	            'tax_calc_type'  => $tax_calc_type,
	            'taxable_amount' => number_format($taxable_amount, 2, '.', ''),
	            'tax_amount'     => number_format($tax_amount, 2, '.', ''),
	            'total_amount'   => number_format($total_amount, 2, '.', ''),
	        );

	        $total_pcs      += $no_of_pcs;
	        $total_gross_wt += $gross_wt;
	        $total_net_wt   += $net_wt;
	        $grand_total    += $total_amount;
	    }

	    $total_net_wt = round($total_net_wt, 3);
	    $grand_total  = round($grand_total, 2);

	    // Header roll-up. A single Common PO may legitimately mix Fixed and
	    // Unfixed lines, so the header records which of the three it is.
	    if ($has_fixed && $has_unfixed) {
	        $header_rate_type = 3;                       // Mixed
	    } elseif ($has_unfixed) {
	        $header_rate_type = 2;                       // All Unfixed
	    } else {
	        $header_rate_type = 1;                       // All Fixed
	    }

	    /* -------- persist -------- */

	    /* PO-EDIT-4: an amendment reuses everything above — the same validation and
	       the same server-side recompute — and only differs in where the result
	       lands. Threading the id through here rather than writing a second save
	       means an amended Common PO can never be priced by different rules from a
	       new one. */
	    $update_id = (int)$update_id;

	    if ($update_id > 0) {

	        $before = $this->$model->get_po_for_edit($update_id);

	        if (empty($before) || (int)$before['is_common_po'] !== 1) {
	            return array('status' => FALSE, 'msg' => 'This is not a Common Purchase Order.');
	        }

	        $smith_due_date = (!empty($addData['smith_due_dt'])
	            ? date('Y-m-d', strtotime(str_replace("/", "-", $addData['smith_due_dt'])))
	            : NULL);

	        $this->db->trans_begin();

	        /* pur_no, fin_year_code and order_date are not rewritten — an amendment
	           keeps its identity. Approval is revoked: the PO is no longer the thing
	           that was approved, and common_po_approved is what the advance payment
	           and bill recall screens actually test. */
	        $this->$model->updateData(array(
	            'rate_type_fix'         => $header_rate_type,
	            'order_pcs'             => $total_pcs,
	            'order_approx_wt'       => number_format($total_net_wt, 3, '.', ''),
	            'id_karigar'            => (int)$addData['id_karigar'],
	            'order_status'          => 10,
	            'common_po_status'      => 0,
	            'common_po_approved'    => 0,
	            'common_po_approved_on' => NULL,
	        ), 'id_customerorder', $update_id, 'customerorder');

	        /* Bullion lines are replaced wholesale rather than matched up. Unlike an
	           ornament line they carry no images and no cus_orderdet_ref back to a
	           customer order, so there is nothing hanging off the old ids — and edit
	           is blocked once an advance or a bill exists, so nothing downstream
	           refers to them either. */
	        $this->db->where('id_customerorder', $update_id);
	        $this->db->delete('customerorderdetails');

	        foreach ($lines as $line) {
	            $line['id_customerorder'] = $update_id;
	            $line['orderstatus']      = 3;
	            $line['id_karigar']       = (int)$addData['id_karigar'];
	            $line['smith_due_date']   = $smith_due_date;
	            $line['order_date']       = date("Y-m-d H:i:s");
	            $line['id_employee']      = $this->session->userdata('uid');

	            $this->$model->insertData($line, 'customerorderdetails');
	        }

	        if ($this->db->trans_status() === TRUE) {
	            $this->db->trans_commit();
	            return array(
	                'status' => TRUE,
	                'id'     => $update_id,
	                'pur_no' => $before['pur_no'],
	                'msg'    => 'Common Purchase Order '.$before['pur_no']
	                          . ' updated. It has been sent back for approval.',
	            );
	        }

	        $this->db->trans_rollback();
	        return array('status' => FALSE, 'msg' => 'Could not update the Common Purchase Order. No change has been saved.');
	    }

	    $fin_year = $this->$model->get_FinancialYear();
	    $order_no = $this->$model->generatePurNo();

	    $order = array(
	        'fin_year_code'    => $fin_year['fin_year_code'],
	        'pur_no'           => ($fin_year['fin_year_code'] . '-' . $order_no),
	        'order_status'     => 10,
	        'order_type'       => 1,
	        'order_for'        => 1,
	        'is_common_po'     => 1,
	        'rate_type_fix'    => $header_rate_type,
	        'common_po_status' => 0,                     // Open
	        'order_pcs'        => $total_pcs,
	        'order_approx_wt'  => number_format($total_net_wt, 3, '.', ''),   // see note on insertData zero handling
	        'id_karigar'       => (int)$addData['id_karigar'],
	        'order_date'       => date("Y-m-d H:i:s"),
	        'createdon'        => date("Y-m-d H:i:s"),
	        'order_taken_by'   => $this->session->userdata('uid'),
	    );

	    $this->db->trans_begin();

	    $insOrder = $this->$model->insertData($order, 'customerorder');

	    if (!$insOrder) {
	        $this->db->trans_rollback();
	        return array('status' => FALSE, 'msg' => 'Unable to create the Common Purchase Order');
	    }

	    $smith_due_date = (!empty($addData['smith_due_dt'])
	        ? date('Y-m-d', strtotime(str_replace("/", "-", $addData['smith_due_dt'])))
	        : NULL);

	    foreach ($lines as $line) {
	        $line['id_customerorder'] = $insOrder;
	        $line['orderstatus']      = 3;
	        $line['id_karigar']       = (int)$addData['id_karigar'];
	        $line['smith_due_date']   = $smith_due_date;
	        $line['order_date']       = date("Y-m-d H:i:s");
	        $line['id_employee']      = $this->session->userdata('uid');

	        $this->$model->insertData($line, 'customerorderdetails');
	    }

	    if ($this->db->trans_status() === TRUE) {

	        $this->db->trans_commit();

	        $this->log_model->log_detail('insert', '', array(
	            'id_log'     => $this->session->userdata('id_log'),
	            'event_date' => date("Y-m-d H:i:s"),
	            'module'     => 'Common Purchase Order',
	            'operation'  => 'Add',
	            'record'     => $insOrder,
	            'remark'     => 'Common PO ' . $order['pur_no'] . ' created. Pcs ' . $total_pcs
	                            . ', Net Wt ' . $total_net_wt . ', Value ' . $grand_total,
	        ));

	        $this->session->set_flashdata('chit_alert', array(
	            'message' => 'Common Purchase Order Created Successfully',
	            'class'   => 'success',
	            'title'   => 'Purchase Order',
	        ));

	        return array('status' => TRUE, 'msg' => 'Common Purchase Order Created Successfully', 'id' => $insOrder);
	    }

	    $this->db->trans_rollback();
	    return array('status' => FALSE, 'msg' => 'Unable to Proceed Your Request..');
	}

	/* ---------------- CPO-03: Common PO cascade endpoints ----------------
	   get_OrderProducts / get_OrderProductsDesign / get_OrderSubDesigns above read
	   back what is already on an EXISTING customerorder. A new Common PO has no
	   order to read from, so these read the masters by category/product instead.
	   All inputs are cast to int before reaching the model — never interpolated
	   raw from $_POST.
	   -------------------------------------------------------------------- */

	function get_common_po_purities()
	{
	    $model = self::RET_PUR_ORDER_MODEL;
	    $id_category = isset($_POST['id_category']) ? (int)$_POST['id_category'] : 0;
	    echo json_encode($id_category > 0 ? $this->$model->getCommonPoPurities($id_category) : array());
	}

	function get_common_po_products()
	{
	    $model = self::RET_PUR_ORDER_MODEL;
	    $id_category = isset($_POST['id_category']) ? (int)$_POST['id_category'] : 0;
	    echo json_encode($id_category > 0 ? $this->$model->getCommonPoProducts($id_category) : array());
	}

	function get_common_po_designs()
	{
	    $model = self::RET_PUR_ORDER_MODEL;
	    $id_product = isset($_POST['id_product']) ? (int)$_POST['id_product'] : 0;
	    echo json_encode($id_product > 0 ? $this->$model->getCommonPoDesigns($id_product) : array());
	}

	function get_common_po_sub_designs()
	{
	    $model = self::RET_PUR_ORDER_MODEL;
	    $id_product = isset($_POST['id_product']) ? (int)$_POST['id_product'] : 0;
	    $id_design  = isset($_POST['id_design'])  ? (int)$_POST['id_design']  : '';
	    echo json_encode($id_product > 0 ? $this->$model->getCommonPoSubDesigns($id_product, $id_design) : array());
	}

	/**
	 * CPO-18: Common PO status report — every PO with its commitment, receipt,
	 * advance and settled position in one place.
	 */
	/**
	 * Supplier Settlement Report (SPS-15).
	 *
	 * One row per purchase bill: value, settled, balance, how many payments it took and
	 * its settlement status - AC #6 and #7. Drilling into a row returns every payment
	 * ever allocated to that bill, cancelled ones included, which is AC #7 (payment
	 * history) and AC #8 (audit and reconciliation).
	 *
	 * Every figure here is produced by the same balance expression the Supplier Payment
	 * screen uses to decide what may be settled, so the report and the payment screen
	 * cannot quote different outstanding amounts for the same bill.
	 */
	function supplier_settlement_report($type = "")
	{
	    $model     = self::RET_PUR_ORDER_MODEL;
	    $set_model = self::SETT_MOD;

	    switch ($type) {

	        case 'ajax':

	            $filters = array(
	                'id_karigar'    => isset($_POST['id_karigar'])    ? (int)$_POST['id_karigar'] : 0,
	                'from_date'     => isset($_POST['from_date'])     ? $_POST['from_date'] : '',
	                'to_date'       => isset($_POST['to_date'])       ? $_POST['to_date'] : '',
	                'settle_status' => isset($_POST['settle_status']) ? $_POST['settle_status'] : '',
	            );

	            // 'all' is the screen's way of saying no filter; the model treats an
	            // empty string as no filter, so normalise here rather than there.
	            if ($filters['settle_status'] === 'all') {
	                $filters['settle_status'] = '';
	            }

	            $rows = $this->$model->get_supplier_settlement_summary($filters);

	            /* Totals summed from the rows the screen is about to show, so the footer
	               agrees with the body even when a status filter has removed rows. */
	            $tot = array('bill_amount'=>0,'settled_amt'=>0,'balance_amt'=>0,
	                         'return_amt'=>0,'debit_note_amt'=>0);

	            foreach ($rows as $r) {
	                foreach ($tot as $k => $v) { $tot[$k] += (float)$r[$k]; }
	            }
	            foreach ($tot as $k => $v) { $tot[$k] = round($v, 2); }

	            echo json_encode(array('list' => $rows, 'totals' => $tot, 'count' => count($rows)));
	            return;

	        case 'history':

	            $po_id = (int)$this->input->post('po_id');

	            echo json_encode(array(
	                'status'  => $this->$model->get_po_settlement_status($po_id),
	                'history' => $this->$model->get_po_settlement_history($po_id),
	            ));
	            return;

	        default:

	            $data['suppliers']    = $this->$model->get_purchase_suppliers();
	            $data['access']       = $this->$set_model->get_access('admin_ret_purchase/supplier_settlement_report/list');
	            $data['main_content'] = self::VIEW_FOLDER.'supplier_settlement_report';

	            $this->load->view('layout/template', $data);
	            return;
	    }
	}

	function common_po_report($type = "")
	{
	    $model     = self::RET_PUR_ORDER_MODEL;
	    $set_model = self::SETT_MOD;

	    switch ($type) {

	        case 'ajax':

	            $filters = array(
	                'id_karigar'       => isset($_POST['id_karigar'])       ? (int)$_POST['id_karigar'] : 0,
	                'from_date'        => isset($_POST['from_date'])        ? $_POST['from_date'] : '',
	                'to_date'          => isset($_POST['to_date'])          ? $_POST['to_date'] : '',
	                'common_po_status' => isset($_POST['common_po_status']) ? $_POST['common_po_status'] : 'all',
	                'position'         => isset($_POST['position'])         ? $_POST['position'] : 'all',
	            );

	            $rows = $this->$model->getCommonPoReport($filters);

	            /* Totals are summed here, from the same rows the screen shows, so the
	               footer can never disagree with the body — including when a position
	               filter is applied. */
	            $tot = array('po_value'=>0,'billed'=>0,'advance'=>0,'adjusted'=>0,
	                         'payable'=>0,'receivable'=>0,'committed_wt'=>0,'received_wt'=>0);

	            foreach ($rows as $r) {
	                foreach ($tot as $k => $v) { $tot[$k] += (float)$r[$k]; }
	            }
	            foreach ($tot as $k => $v) {
	                $tot[$k] = (strpos($k, '_wt') !== FALSE) ? round($v, 3) : round($v, 2);
	            }

	            echo json_encode(array('list' => $rows, 'totals' => $tot, 'count' => count($rows)));
	            return;

	        default:

	            $data['suppliers']    = $this->$model->getCommonPoSuppliers();
	            $data['access']       = $this->$set_model->get_access('admin_ret_purchase/common_po_report/list');
	            $data['main_content'] = self::VIEW_FOLDER.'common_po_report';

	            $this->load->view('layout/template', $data);
	            return;
	    }
	}

	/**
	 * CPO-17: full document trace for a Common PO (BRS section 7 / criterion 19) —
	 * PO -> Advance Payment -> GRN -> Supplier Bill -> Vendor Ledger.
	 */
	function common_po_trace($id = "")
	{
	    $model     = self::RET_PUR_ORDER_MODEL;
	    $set_model = self::SETT_MOD;

	    $id = (int)$id;

	    if ($id <= 0) {
	        show_error('No Purchase Order specified.', 404);
	        return;
	    }

	    $trace = $this->$model->getCommonPoTraceability($id);

	    if (!$trace) {
	        show_error('Common Purchase Order not found.', 404);
	        return;
	    }

	    $data['trace']        = $trace;
	    /* The trace has no menu entry of its own — it is a per-record drill-down opened
	       from the PO list and the status report, never from navigation — so it shares
	       the report's access entry. */
	    $data['access']       = $this->$set_model->get_access('admin_ret_purchase/common_po_report/list');
	    $data['main_content'] = self::VIEW_FOLDER.'common_po_trace';

	    $this->load->view('layout/template', $data);
	}

	/**
	 * CPO-19: the page behind the Common PO Rate Override menu entry.
	 *
	 * The entry exists so the capability can be granted in Settings > Profile Access —
	 * see _can_override_common_po_rate(). It has to be `active = 1` to reach that grid,
	 * which also puts it in the navigation of whoever holds it, so it needs somewhere
	 * to land. This page says what the capability does and who currently has it; there
	 * is nothing to operate here.
	 */
	function common_po_rate_override()
	{
	    $model     = self::RET_PUR_ORDER_MODEL;
	    $set_model = self::SETT_MOD;

	    $data['holders']      = $this->$model->getCommonPoRateOverrideProfiles();
	    $data['has_override'] = $this->_can_override_common_po_rate();
	    $data['access']       = $this->$set_model->get_access('admin_ret_purchase/common_po_rate_override');
	    $data['main_content'] = self::VIEW_FOLDER.'common_po_rate_override';

	    $this->load->view('layout/template', $data);
	}

	/**
	 * CPO-14: reconciled ledger position for a Common PO — nil, payable or receivable
	 * (BRS section 5 steps 11-12).
	 */
	function get_common_po_reconciliation()
	{
	    $model = self::RET_PUR_ORDER_MODEL;
	    $id_po = isset($_POST['id_customerorder']) ? (int)$_POST['id_customerorder'] : 0;

	    if ($id_po > 0) {
	        $row = $this->$model->getCommonPoReconciliation($id_po);
	        echo json_encode($row ? array_merge(array('status' => TRUE), $row)
	                              : array('status' => FALSE, 'msg' => 'Common Purchase Order not found'));
	        return;
	    }

	    $id_karigar = isset($_POST['id_karigar']) ? (int)$_POST['id_karigar'] : 0;
	    echo json_encode($this->$model->getCommonPoReconciliationList($id_karigar));
	}

	/**
	 * CPO-11: Common POs this supplier can be billed against, for the PO recall on
	 * Supplier Bill Entry. Approved and not yet billed.
	 */
	function get_common_po_for_billing()
	{
	    $model      = self::RET_PUR_ORDER_MODEL;
	    $id_karigar = isset($_POST['supplierId']) ? (int)$_POST['supplierId'] : 0;
	    $searchTxt  = isset($_POST['searchTxt'])  ? trim($_POST['searchTxt']) : '';

	    if ($id_karigar <= 0) {
	        // Without a supplier the list would span every vendor's commitments.
	        echo json_encode(array());
	        return;
	    }

	    echo json_encode($this->$model->getCommonPoForBilling($id_karigar, $searchTxt));
	}

	/**
	 * CPO-11: the stored PO lines used to auto-populate Supplier Bill Entry
	 * (BRS step 3.7 — product, purity, quantity, fixed rate, tax, total).
	 *
	 * Also reports the advance position so the screen can show what will be adjusted
	 * (CPO-13), and flags any line whose tax group has been re-rated since the PO was
	 * raised so the difference is visible rather than silently applied.
	 */
	function get_common_po_bill_lines()
	{
	    $model = self::RET_PUR_ORDER_MODEL;
	    $id_po = isset($_POST['id_customerorder']) ? (int)$_POST['id_customerorder'] : 0;

	    $position = ($id_po > 0) ? $this->$model->getCommonPoAdvancePosition($id_po) : NULL;

	    if (!$position) {
	        echo json_encode(array('status' => FALSE, 'msg' => 'Common Purchase Order not found'));
	        return;
	    }

	    if ((int)$position['common_po_approved'] !== 1) {
	        echo json_encode(array('status' => FALSE, 'msg' => 'This Purchase Order is not approved yet'));
	        return;
	    }

	    if ((int)$position['common_po_status'] >= 3) {
	        echo json_encode(array('status' => FALSE, 'msg' => 'This Purchase Order has already been billed or closed'));
	        return;
	    }

	    $lines       = $this->$model->getCommonPoBillLines($id_po);
	    $has_unfixed = FALSE;
	    $tax_changed = FALSE;

	    foreach ($lines as $k => $line) {
	        if ((int)$line['rate_type_fix'] === 2) { $has_unfixed = TRUE; }

	        // Float-safe: never compare stored vs master percentages with ==.
	        $drift = abs((float)$line['tax_percent'] - (float)$line['tax_percent_current']);
	        $lines[$k]['tax_rate_changed'] = ((int)$line['rate_type_fix'] === 1 && $drift > 0.005) ? 1 : 0;
	        if ($lines[$k]['tax_rate_changed']) { $tax_changed = TRUE; }
	    }

	    $balance = (float)$position['po_value'] - (float)$position['advance_paid'];

	    echo json_encode(array(
	        'status'          => TRUE,
	        'pur_no'          => $position['pur_no'],
	        'id_karigar'      => $position['id_karigar'],
	        'rate_type_fix'   => $position['rate_type_fix'],
	        'po_value'        => round((float)$position['po_value'], 2),
	        'advance_paid'    => round((float)$position['advance_paid'], 2),
	        'advance_balance' => round($balance, 2),
	        'has_unfixed'     => $has_unfixed ? 1 : 0,
	        'tax_rate_changed'=> $tax_changed ? 1 : 0,
	        'lines'           => $lines,
	    ));
	}

	/**
	 * CPO-07: Common POs available for an advance payment, for the supplier's
	 * selection in Smith & Supplier Payment. Returns the PO value, what has already
	 * been advanced and the remaining balance, so the screen can pre-fill and cap.
	 */
	function get_common_po_for_payment()
	{
	    $model = self::RET_PUR_ORDER_MODEL;
	    $id_karigar = isset($_POST['id_karigar']) ? (int)$_POST['id_karigar'] : 0;
	    echo json_encode($id_karigar > 0 ? $this->$model->getCommonPoForPayment($id_karigar) : array());
	}

	/**
	 * CPO-07: advance position for one Common PO — used when the user picks a PO so
	 * the amount, weight and remaining balance populate without a page reload.
	 */
	function get_common_po_advance_position()
	{
	    $model  = self::RET_PUR_ORDER_MODEL;
	    $id_po  = isset($_POST['id_customerorder']) ? (int)$_POST['id_customerorder'] : 0;
	    $row    = ($id_po > 0) ? $this->$model->getCommonPoAdvancePosition($id_po) : NULL;

	    if (!$row) {
	        echo json_encode(array('status' => FALSE, 'msg' => 'Purchase Order not found'));
	        return;
	    }

	    $balance = (float)$row['po_value'] - (float)$row['advance_paid'];
	    $row['advance_balance'] = ($balance > 0 ? round($balance, 2) : 0);
	    $row['is_unfixed']      = ((int)$row['rate_type_fix'] === 2) ? 1 : 0;
	    // D1: an Unfixed PO has no agreed value, so no ceiling can be enforced.
	    $row['exceed_block']    = ($row['is_unfixed'] ? '0' : $this->$model->get_common_po_setting('common_po_advance_exceed_block', '1'));
	    $row['status']          = TRUE;

	    echo json_encode($row);
	}

		//GRN ENTRY

	function grnentry($type="",$id="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$ret_catalog_model = "ret_catalog_model";

		$set_model = "admin_settings_model";

		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'grn_entry/list';

					$data['access'] = $this->$set_model->get_access('admin_ret_purchase/grnentry/list');


				//    print_r($data['access']);exit;


        			$this->load->view('layout/template', $data);

			break;

			case 'add':

			        $data['grn_details']['grn_type'] = 1;

			        $data['grn_details']['grn_karigar_id'] = '';

			        $data['grn_details']['grn_supplier_ref_no'] = '';

			        $data['grn_details']['grn_ewaybillno'] = '';

			        $data['grn_details']['grn_irnno'] = '';

			        $data['grn_details']['grn_ref_date'] = '';

			        $data['comp_details']   = $this->$set_model->get_company();

					$data['main_content'] = self::VIEW_FOLDER.'grn_entry/form';

        			$this->load->view('layout/template', $data);

			break;



			case 'edit':

            	$data['grn_details']    = $this->$model->get_grn_details($id);

            	$data['categories']   = $this->$ret_catalog_model->getActiveCategorymtr($_POST);

            	$data['comp_details']   = $this->$set_model->get_company();

            	//echo "<pre>";print_r($data);exit;

            	$data['main_content'] = self::VIEW_FOLDER.'grn_entry/form';

            	$this->load->view('layout/template', $data);

            break;



			case 'save':


    			$addData   = $_POST['order'];

                $order_details   = $_POST['order_details'];

				//echo "<pre>";print_r($_POST);exit;

				$p_ImgData   = json_decode(rawurldecode($addData['tag_img']));

				//print_r($p_ImgData);exit;

    			$fin_year       = $this->$model->get_FinancialYear();

    			$grn_refno      = $this->$model->generate_grn_refno($addData['grn_type']);



     			$insData = array(

     			    'grn_fin_year_code'     => $fin_year['fin_year_code'],

     			    'grn_karigar_id'        => $addData['id_karigar'],

     			    'grn_date'              => date("Y-m-d H:i:s"),

     				'grn_ref_no'            => $grn_refno,

     				'grn_supplier_ref_no'   => ($addData['po_supplier_ref_no']!='' ? $addData['po_supplier_ref_no']:NULL),

     				'grn_ref_date'          => !empty($addData['po_ref_date']) ? date('Y-m-d', strtotime($addData['po_ref_date'])):NULL,

     				'grn_type'              => $addData['grn_type'],

					'purchase_type' 		=> $this->input->post('purchase_type'),

     				'grn_ewaybillno'        => ($addData['ewaybillno']!='' ? $addData['ewaybillno']:NULL),

     				'grn_despatch_through'  => ($addData['despatch_through']!='' ? $addData['despatch_through']:NULL),

     				'grn_irnno'             => ($addData['invoice_ref_no']!='' ? $addData['invoice_ref_no']:NULL),

     				'grn_other_charges'     => ($addData['other_charges_amount']!='' ? ($addData['other_charges_amount']+$addData['other_charges_tax']):0),

     				'grn_discount'          => ($addData['discount']!='' ? $addData['discount']:0),

     				'grn_purchase_amt'      => ($addData['total_cost']!='' ? $addData['total_cost']:0),

     				'grn_round_off'         => ($addData['round_off']!='' ? ($addData['round_off_type']==1 ? '+'.$addData['round_off'] :'-'.$addData['round_off']):0),

     				'grn_tcs_percent'       => ($addData['tcs_percent']!='' ? $addData['tcs_percent']:0),

     				'grn_tcs_value'         => ($addData['tcs_tax_value']!='' ? $addData['tcs_tax_value']:0),

     				'grn_pay_tds_percent'           => ($addData['tds_percent']!='' ? $addData['tds_percent']:0),

     				'grn_pay_tds_value'             => ($addData['tds_tax_value']!='' ? $addData['tds_tax_value']:0),

     				'grn_other_charges_tds_value'   => ($addData['other_charges_tds_tax_value']!='' ? $addData['other_charges_tds_tax_value']:0),

     				'grn_other_charges_tds_percent' => ($addData['charges_tds_percent']!='' ? $addData['charges_tds_percent']:0),

     				'form_secret'                   => ($addData['form_secret']!='' ? $addData['form_secret']:NULL),

     				'remarks'                       => ($addData['remarks']!='' ? $addData['remarks']:NULL),

    				'created_on'                    => date("Y-m-d H:i:s"),

    				'created_by'                    => $this->session->userdata('uid')

    			);

    			$this->db->trans_begin();

    			$insId = $this->$model->insertData($insData,'ret_grn_entry');

			    if($insId)

			    {

					if (sizeof($p_ImgData) > 0) {
						$_FILES['precious'] = array();

						foreach ($p_ImgData as $precious) {
							// print_r($precious); // Uncomment for debugging
							$imgFile = $this->base64ToFile($precious->src);
							$_FILES['precious'][] = $imgFile;
						}

						$img_arr = array();
                          // print_r($precious);

						if (!empty($_FILES['precious'])) {
							$folder = self::IMG_PATH . "grn_entry/";

							if (!is_dir($folder)) {
								mkdir($folder, 0777, TRUE);
							}


							foreach ($_FILES['precious'] as $file_key => $file_val) {
								if ($file_val['tmp_name']) {
									$img_name = $insId . "_" . mt_rand(100001, 999999) . ".jpg";
									$path = $folder . "/" . $img_name;

									$result = $this->upload_img('image', $path, $file_val['tmp_name']);

									if ($result) {
										$arrayimg_tag = array(
											'grn_id'   => $insId,
											'image'    => $img_name,
											'date_add' => date("Y-m-d H:i:s"),
										);

										$img_arr[] = $arrayimg_tag;

										$insImageId = $this->$model->insertData($arrayimg_tag, 'ret_grn_images');

										// print_r($this->db->last_query());exit;

									}
								}
							}
						}
					}

			        if($addData['grn_type']==1 || $addData['grn_type']==2 || $addData['grn_type']==4)

			        {

			            $billSale = $_POST['item'];

    			        if(!empty($billSale))

    			        {

    			            foreach($billSale['category'] as $key => $val){



                                $array_item_details = array(

                                'grn_item_grn_id'       =>$insId,

                                'grn_item_cat_id'       =>(($billSale['category'][$key]!='') ? $billSale['category'][$key]:NULL),

                                'grn_gross_wt'          =>(($billSale['gross_wt'][$key]!='') ? $billSale['gross_wt'][$key]:NULL),

                                'grn_less_wt'           =>(($billSale['less_wt'][$key]!='') ? $billSale['less_wt'][$key]:NULL),

                                'grn_net_wt'            =>(($billSale['net_wt'][$key]!='') ? $billSale['net_wt'][$key]:NULL),

                                'grn_wastage'           =>(($billSale['wastage'][$key]!='') ? $billSale['wastage'][$key]:0),

                                'grn_no_of_pcs'         =>((isset($billSale['pcs'][$key]) && $billSale['pcs'][$key] !== '') ? $billSale['pcs'][$key]:NULL),

                                'grn_rate_per_grm'      =>(isset($billSale['rate_per_gram'][$key]) ? $billSale['rate_per_gram'][$key]:NULL),

                                'grn_item_cost'         =>(isset($billSale['item_cost'][$key]) ? $billSale['item_cost'][$key]:NULL),

                                'itemratecaltype'       =>(isset($billSale['rate_type'][$key]) ? $billSale['rate_type'][$key]:NULL),

                                'grn_item_gst_rate'     =>(isset($billSale['item_total_tax'][$key]) ? $billSale['item_total_tax'][$key]:NULL),

                                'grn_item_gst_value'    =>number_format(($billSale['tax_percentage'][$key]),2,'.',''),

                                'grn_item_cgst'         =>number_format(($billSale['item_cgst'][$key]),2,'.',''),

                                'grn_item_sgst'         =>number_format(($billSale['item_sgst'][$key]),2,'.',''),

                                'grn_item_igst'         =>number_format(($billSale['item_igst'][$key]),2,'.',''),

                                );

                                 $grn_item_id = $this->$model->insertData($array_item_details,'ret_grn_items');

                                 if($grn_item_id)

                                 {

                                     if($billSale['stone_details'][$key])

                                     {

                                            $stone_details=json_decode($billSale['stone_details'][$key],true);

                                            //echo "<pre>";print_r($stone_details);exit;

            								foreach($stone_details as $stone)

            								{

            									$stone_data=array(

            									'grn_item_id'    =>$grn_item_id,

            									'pieces'         =>$stone['stone_pcs'],

            									'wt'             =>$stone['stone_wt'],

            									'stone_id'       =>$stone['stone_id'],

            									'amount'          =>$stone['stone_price'],

												'uom_id'         => $stone['stone_uom_id'],

            									'is_apply_in_lwt'=>$stone['show_in_lwt'],

                                            	'stone_cal_type' =>$stone['stone_cal_type'],

                                            	'rate_per_gram'  =>$stone['stone_rate'],

            									);

            									$stoneInsert = $this->$model->insertData($stone_data,'ret_grn_item_stone');

                                                //print_r($this->db->last_query());exit;

            								}

                                     }

                                     if($billSale['other_metal_details'][$key])

                                     {

                                        $othermetal_details=json_decode($billSale['other_metal_details'][$key],true);

                                        foreach($othermetal_details as $othermetal)

                                    	{

                                        	$other_metal_data = array(

                                    	    'grn_itms_id'               =>$grn_item_id,

                                        	'grn_other_itm_metal_id'    =>$othermetal['id_metal'],

                                        	'grn_other_itm_grs_weight'  =>$othermetal['gwt'],

                                        	'grn_other_itm_pur_id'      =>$othermetal['id_purity'],

                                        	'grn_other_itm_wastage'     =>$othermetal['wastage_perc'],

                                        	'grn_other_itm_mc'          =>$othermetal['making_charge'],

                                        	'grn_other_itm_cal_type'    =>$othermetal['calc_type'],

                                        	'grn_other_itm_rate'        =>$othermetal['rate_per_gram'],

                                        	'grn_other_itm_pcs'         =>$othermetal['pcs'],

                                        	'grn_other_itm_amount'      =>$othermetal['amount'],

                                        	);

                                        	$othermetalInsert = $this->$model->insertData($other_metal_data,'ret_grn_other_metals');

                                        	//echo $this->db->last_query();exit;

                                    	}

                                     }

                                 }



    			            }

    			        }

			        }



			        if(!empty($addData['other_charges_details'])){

    			        $charge_details = json_decode($addData['other_charges_details'],true);

                    	foreach($charge_details as $charges)

                    	{

                    	    $cgst_cost = 0;

                    	    $sgst_cost = 0;

                    	    $igst_cost = 0;

                    	    $total_tax = number_format(($charges['char_with_tax'] - $charges['charge_value']),2,'.','');

                    	    if($addData['cmp_country']==$addData['supplier_country'])

                    	    {

                    	        if($addData['cmp_state']==$addData['supplier_state'])

                    	        {

                    	            $cgst_cost = ($total_tax/2);

                    	            $sgst_cost = ($total_tax/2);

                    	        }

                    	        else

                    	        {

                    	            $igst_cost = $total_tax;

                    	        }

                    	    }

                    	    else

                    	    {

                    	        $cgst_cost = ($total_tax/2);

                    	        $sgst_cost = ($total_tax/2);

                    	    }

                        	$charge_data = array(

                            	'grn_id'           => $insId,

                            	'grn_charge_id'    => $charges['charge_id'],

                            	'grn_charge_value' => $charges['charge_value'],

                            	'char_with_tax'    => $charges['char_with_tax'],

                            	'char_tax'         => $charges['charge_tax'],

                            	'total_tax'        => number_format(($charges['char_with_tax'] - $charges['charge_value']),2,'.',''),

                            	'igst_cost'        => $igst_cost,

                            	'cgst_cost'        => $cgst_cost,

                            	'sgst_cost'        => $sgst_cost,

                        	);

                        	$stoneInsert = $this->$model->insertData($charge_data, 'ret_grn_other_charges');

                        	//echo $this->db->last_query();exit;

                    	}



    			    }



			        if($this->db->trans_status()===TRUE)

        			{

        				$this->db->trans_commit();


						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'GRN Entry',

							'operation'     => 'Add',

							'record'        =>  $insId,

							'remark'        => 'GRN Entry added successfully'

							);

						$this->log_model->log_detail('insert','',$log_data);

        				$this->session->set_flashdata('chit_alert',array('message'=>'GRN Entry Added Successfully','class'=>'success','title'=>'GRN Entry'));

        				$return_data=array('status'=>TRUE,'message'=>'GRN Entry Added Successfully..');

        			}

        			else

        			{



        			    // echo $this->db->last_query();exit;

        				$this->db->trans_rollback();

        				$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'GRN Entry'));

        				$return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

        			}

			    }

			    else

			    {

			            // echo $this->db->last_query();exit;

        				$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'GRN Entry'));

        				$return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

			    }



    			echo json_encode($return_data);

			break;



			case 'delete':

    			   $this->$model->deleteData('id_order_des',$id,'ret_purchase_order_description');

    	           if($this->db->trans_status()===TRUE)

    			    {

    			    	  $this->db->trans_commit();

						  $log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'GRN Entry',

							'operation'     => 'Delete',

							'record'        =>  $id,

							'remark'        => 'GRN Entry deleted successfully'

							);

	                      $this->log_model->log_detail('insert','',$log_data);

    					  $this->session->set_flashdata('chit_alert', array('message' => 'Order Instructions deleted successfully','class' => 'success','title'=>'Delete Order Instructions'));

    				}

    			   else

    			    {

    				 $this->db->trans_rollback();

    				 $this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed your request','class' => 'danger','title'=>'Delete Order Instructions'));

    			    }

    			 redirect('admin_ret_purchase/order_description/list');

			 break;



			 case 'edit':

			    $model  =	self::RET_PUR_ORDER_MODEL;

                $data   =  $this->$model->get_orderdescription($id);

                echo json_encode($data);

			 break;



			 case 'update':

				$updData = $_POST['order'];

				$id = $updData['grn_id'];

				$p_ImgData   = json_decode(rawurldecode($this->input->post('tag_img')));

				//echo "<pre>";print_r($updData);exit;

				// === SUPPLIER CHANGE GUARD (Task: 9ed390a1a031) ===
				$current_grn = $this->db->query(
					"SELECT grn_karigar_id, grn_date, grn_ref_no FROM ret_grn_entry WHERE grn_id = " . (int)$id
				)->row_array();
				$supplier_changed = ($current_grn && $current_grn['grn_karigar_id'] != $updData['id_karigar']);

				if($supplier_changed)
				{
					// Block: Current month only — past month entries cannot be changed
					if(!empty($current_grn['grn_date']) && date('Y-m', strtotime($current_grn['grn_date'])) !== date('Y-m'))
					{
						$return_data = array(
							'status'  => FALSE,
							'message' => 'Cannot change supplier for past month entries. Only current month GRNs can be modified.'
						);
						echo json_encode($return_data);
						return;
					}

					$linked_po = $this->$model->get_po_by_grn($id);
					if($linked_po)
					{
						$check = $this->$model->check_supplier_change_allowed($linked_po['po_id']);
						if(!$check['allowed'])
						{
							$return_data = array(
								'status'  => FALSE,
								'message' => 'Cannot change supplier. ' . implode(', ', $check['reasons'])
							);
							echo json_encode($return_data);
							return;
						}
					}
				}
				// === END GUARD ===

				$fin_year       = $this->$model->get_FinancialYear();

				$order = array(

					'grn_fin_year_code'     => $fin_year['fin_year_code'],

					'grn_karigar_id'        => $updData['id_karigar'],

					'grn_supplier_ref_no'   => ($updData['po_supplier_ref_no']!='' ? $updData['po_supplier_ref_no']:NULL),

					'grn_ref_date'          => !empty($updData['po_ref_date']) ? date('Y-m-d', strtotime($updData['po_ref_date'])):NULL,

					'grn_type'              => $updData['grn_type'],

					'grn_ewaybillno'        => ($updData['ewaybillno']!='' ? $updData['ewaybillno']:NULL),

					'grn_despatch_through'  => ($updData['despatch_through']!='' ? $updData['despatch_through']:NULL),

					'grn_irnno'             => ($updData['invoice_ref_no']!='' ? $updData['invoice_ref_no']:NULL),

					'grn_other_charges'     => ($updData['other_charges_amount']!='' ? ($updData['other_charges_amount']+$updData['other_charges_tax']):0),

					'grn_discount'          => ($updData['discount']!='' ? $updData['discount']:0),

					'grn_purchase_amt'      => ($updData['total_cost']!='' ? $updData['total_cost']:0),

					'grn_round_off'         => ($updData['round_off']!='' ? ($updData['round_off_type']==1 ? '+'.$updData['round_off'] :'-'.$updData['round_off']):0),

					'grn_tcs_percent'       => ($updData['tcs_percent']!='' ? $updData['tcs_percent']:0),

					'grn_tcs_value'         => ($updData['tcs_tax_value']!='' ? $updData['tcs_tax_value']:0),

					'grn_pay_tds_percent'           => ($updData['tds_percent']!='' ? $updData['tds_percent']:0),

					'grn_pay_tds_value'             => ($updData['tds_tax_value']!='' ? $updData['tds_tax_value']:0),

					'grn_other_charges_tds_value'   => ($updData['other_charges_tds_tax_value']!='' ? $updData['other_charges_tds_tax_value']:0),

					'grn_other_charges_tds_percent' => ($updData['charges_tds_percent']!='' ? $updData['charges_tds_percent']:0),

					'form_secret'                   => ($updData['form_secret']!='' ? $updData['form_secret']:NULL),

					'remarks'                       => ($updData['remarks']!='' ? $updData['remarks']:NULL),

				   	'updated_on'                    => date("Y-m-d H:i:s"),

				   	'updated_by'                    => $this->session->userdata('uid')

			   );



			   $this->db->trans_begin();



			   $update_status = $this->$model->updateData($order,'grn_id',$id,'ret_grn_entry');

    			if($update_status)

    			{

    				$this->$model->deleteData('grn_item_grn_id', $id, 'ret_grn_items');

					$this->$model->deleteData('grn_id', $id, 'ret_grn_images');

					if (sizeof($p_ImgData) > 0) {



						$_FILES['precious'] = array();

						foreach ($p_ImgData as $precious) {
							// print_r($precious); // Uncomment for debugging
							$imgFile = $this->base64ToFile($precious->src);
							$_FILES['precious'][] = $imgFile;
						}

						$img_arr = array();
                        //   print_r($precious);

						if (!empty($_FILES['precious'])) {
							$folder = self::IMG_PATH . "grn_entry/";

							if (!is_dir($folder)) {
								mkdir($folder, 0777, TRUE);
							}


							foreach ($_FILES['precious'] as $file_key => $file_val) {
								if ($file_val['tmp_name']) {
									$img_name = $id . "_" . mt_rand(100001, 999999) . ".jpg";
									$path = $folder . "/" . $img_name;

									$result = $this->upload_img('image', $path, $file_val['tmp_name']);

									if ($result) {
										$arrayimg_tag = array(
											'grn_id'   => $id,
											'image'    => $img_name,
											'date_add' => date("Y-m-d H:i:s"),

										);

										// print_r($arrayimg_tag); // Uncomment for debugging

										$img_arr[] = $arrayimg_tag;

										$insImageId = $this->$model->insertData($arrayimg_tag, 'ret_grn_images');

									}
								}
							}
						}
					}



    			   	if($updData['grn_type']==1 || $updData['grn_type']==2 || $updData['grn_type']==4)

    				{



    					$updItems = $_POST['item'];



    					foreach($updItems['category'] as $key => $val)

    					{



    						$this->$model->deleteData('grn_item_id', $updItems['grn_item_id'][$key], 'ret_grn_item_stone');



    						$this->$model->deleteData('grn_itms_id', $updItems['grn_item_id'][$key], 'ret_grn_other_metals');





    						$ItemDetails = array(



    							'grn_item_grn_id'       =>$id,

    							'grn_item_cat_id'       =>(isset($updItems['category'][$key]) ? $updItems['category'][$key]:NULL),

    							'grn_gross_wt'          =>(isset($updItems['gross_wt'][$key]) ? $updItems['gross_wt'][$key]:NULL),

    							'grn_less_wt'           =>(isset($updItems['less_wt'][$key]) ? $updItems['less_wt'][$key]:NULL),

    							'grn_net_wt'            =>(isset($updItems['net_wt'][$key]) ? $updItems['net_wt'][$key]:NULL),

    							'grn_wastage'           =>(isset($updItems['wastage'][$key]) ? $updItems['wastage'][$key]:0),

    							'grn_no_of_pcs'         =>((isset($updItems['pcs'][$key]) && $updItems['pcs'][$key] !== '') ? $updItems['pcs'][$key]:NULL),

    							'grn_rate_per_grm'      =>(isset($updItems['rate_per_gram'][$key]) ? $updItems['rate_per_gram'][$key]:NULL),

    							'grn_item_cost'         =>(isset($updItems['item_cost'][$key]) ? $updItems['item_cost'][$key]:NULL),

    							'itemratecaltype'       =>(isset($updItems['rate_type'][$key]) ? $updItems['rate_type'][$key]:NULL),

    							'grn_item_gst_rate'     =>(isset($updItems['item_total_tax'][$key]) ? $updItems['item_total_tax'][$key]:NULL),

    							'grn_item_gst_value'    =>number_format(($updItems['tax_percentage'][$key]),2,'.',''),

    							'grn_item_cgst'         =>number_format(($updItems['item_cgst'][$key]),2,'.',''),

    							'grn_item_sgst'         =>number_format(($updItems['item_sgst'][$key]),2,'.',''),

    							'grn_item_igst'         =>number_format(($updItems['item_igst'][$key]),2,'.',''),

    						);



    						$grn_item_id = $this->$model->insertData($ItemDetails,'ret_grn_items');



    						if($grn_item_id)

    						{



    							if($updItems['stone_details'][$key])

    							{

    									$stone_details=json_decode($updItems['stone_details'][$key],true);

    									//echo "<pre>";print_r($stone_details);exit;

    									foreach($stone_details as $stone)

    									{

    										$stone_data=array(

    										'grn_item_id'    =>$grn_item_id,

    										'pieces'         =>$stone['stone_pcs'],

    										'wt'             =>$stone['stone_wt'],

    										'stone_id'       =>$stone['stone_id'],

    										'amount'          =>$stone['stone_price'],

    										'is_apply_in_lwt'=>$stone['show_in_lwt'],

											'uom_id'         => $stone['stone_uom_id'],

    										'stone_cal_type' =>$stone['stone_cal_type'],

    										'rate_per_gram'  =>$stone['stone_rate'],

    										);

    										$stoneInsert = $this->$model->insertData($stone_data,'ret_grn_item_stone');

    										//print_r($this->db->last_query());exit;

    									}

    							}

    							if($updItems['other_metal_details'][$key])

    							{

    								$othermetal_details=json_decode($updItems['other_metal_details'][$key],true);

    								foreach($othermetal_details as $othermetal)

    								{

    									$other_metal_data = array(

    									'grn_itms_id'               =>$grn_item_id,

    									'grn_other_itm_metal_id'    =>$othermetal['id_metal'],

    									'grn_other_itm_grs_weight'  =>$othermetal['gwt'],

    									'grn_other_itm_pur_id'      =>$othermetal['id_purity'],

    									'grn_other_itm_wastage'     =>$othermetal['wastage_perc'],

    									'grn_other_itm_mc'          =>$othermetal['mc_value'],

    									'grn_other_itm_cal_type'    =>$othermetal['calc_type'],

    									'grn_other_itm_rate'        =>$othermetal['rate_per_gram'],

    									'grn_other_itm_pcs'         =>$othermetal['pcs'],

    									'grn_other_itm_amount'      =>$othermetal['amount'],

    									);

    									$othermetalInsert = $this->$model->insertData($other_metal_data,'ret_grn_other_metals');

    									//echo $this->db->last_query();exit;

    								}

    							}







    						}





    					}

    				}



    					if(!empty($updData['other_charges_details']))

    					{

    						$this->$model->deleteData('grn_id', $id, 'ret_grn_other_charges');



        			        $charge_details = json_decode($updData['other_charges_details'],true);



    						//echo "<pre>";print_r($charge_details);exit;



                        	foreach($charge_details as $charges)

                        	{

                        	    $cgst_cost = 0;

                        	    $sgst_cost = 0;

                        	    $igst_cost = 0;

                        	    $total_tax = number_format(($charges['char_with_tax'] - $charges['charge_value']),2,'.','');

                        	    if($updData['cmp_country']==$updData['supplier_country'])

                        	    {

                        	        if($updData['cmp_state']==$updData['supplier_state'])

                        	        {

                        	            $cgst_cost = ($total_tax/2);

                        	            $sgst_cost = ($total_tax/2);

                        	        }

                        	        else

                        	        {

                        	            $igst_cost = $total_tax;

                        	        }

                        	    }

                        	    else

                        	    {

                        	        $cgst_cost = ($total_tax/2);

                        	        $sgst_cost = ($total_tax/2);

                        	    }

                            	$charge_data = array(

                                	'grn_id'           => $id,

                                	'grn_charge_id'    => $charges['charge_id'],

                                	'grn_charge_value' => $charges['charge_value'],

                                	'char_with_tax'    => $charges['char_with_tax'],

                                	'char_tax'         => $charges['charge_tax'],

                                	'total_tax'        => number_format(($charges['char_with_tax'] - $charges['charge_value']),2,'.',''),

                                	'igst_cost'        => $igst_cost,

                                	'cgst_cost'        => $cgst_cost,

                                	'sgst_cost'        => $sgst_cost,

                            	);

                            	$stoneInsert = $this->$model->insertData($charge_data, 'ret_grn_other_charges');

                            	//echo $this->db->last_query();exit;

                        	}



        			    }





    			}



			    if($this->db->trans_status()===TRUE)

    			{

    				$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'GRN Entry',

						'operation'     => 'Update',

						'record'        => $update_status,

						'remark'        => 'GRN Entry Updated successfully'

						);

			        $this->log_model->log_detail('insert','',$log_data);

					// === CASCADE SUPPLIER CHANGE (Task: 9ed390a1a031) ===
					if($supplier_changed)
					{
						$linked_po = $this->$model->get_po_by_grn($id);
						if($linked_po)
						{
							$cascade_result = $this->$model->cascade_supplier_change(
								$linked_po['po_id'],
								$updData['id_karigar'],
								$current_grn['grn_karigar_id'],
								null,
								$this->session->userdata('uid'),
								'GRN'
							);

							if(!$cascade_result['success'])
							{
								// Rollback GRN update — race condition detected
								$this->$model->updateData(
									array('grn_karigar_id' => $current_grn['grn_karigar_id']),
									'grn_id', $id, 'ret_grn_entry'
								);
								$return_data = array('status' => FALSE, 'message' => $cascade_result['message']);
								echo json_encode($return_data);
								return;
							}

							// Audit log for supplier change — include names + GRN number
							$old_supplier = $this->db->query(
								"SELECT CONCAT(firstname,' ',lastname) as name FROM ret_karigar WHERE id_karigar = " . (int)$current_grn['grn_karigar_id']
							)->row();
							$new_supplier = $this->db->query(
								"SELECT CONCAT(firstname,' ',lastname) as name FROM ret_karigar WHERE id_karigar = " . (int)$updData['id_karigar']
							)->row();
							$old_name = $old_supplier ? trim($old_supplier->name) : 'ID:' . $current_grn['grn_karigar_id'];
							$new_name = $new_supplier ? trim($new_supplier->name) : 'ID:' . $updData['id_karigar'];
							$grn_number = !empty($current_grn['grn_ref_no']) ? $current_grn['grn_ref_no'] : 'GRN#' . $id;

							$this->log_model->log_detail('insert', '', array(
								'id_log'     => $this->session->userdata('id_log'),
								'event_date' => date("Y-m-d H:i:s"),
								'module'     => 'GRN Entry',
								'operation'  => 'Supplier Change',
								'record'     => $id,
								'remark'     => 'Supplier changed from ' . $old_name . ' (ID:' . $current_grn['grn_karigar_id'] . ')'
								              . ' to ' . $new_name . ' (ID:' . $updData['id_karigar'] . ')'
								              . ' | ' . $grn_number
								              . ' | Cascaded to PO, Lots, Metal Issue, Ledger'
							));
						}
					}
					// === END CASCADE ===

    				$this->session->set_flashdata('chit_alert',array('message'=>'GRN Entry Updated Successfully','class'=>'success','title'=>'GRN ENTRY'));

    				$return_data=array('status'=>TRUE,'message'=>'GRN Entry Updated Successfully');

    			}

    			else

    			{



    			    // echo $this->db->last_query();exit;

    				$this->db->trans_rollback();

    				$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'GRN ENTRY'));

    				$return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

    			}

    			echo json_encode($return_data);

            break;



			 case 'cancel_grn_entry':

                    $updData=array(

                    'grn_bill_status'   => 2,

                    'cancel_reason'     => $_POST['cancel_reason'],

                    'updated_on'        => date("Y-m-d H:i:s"),

                    'updated_by'        => $this->session->userdata('uid')

                    );

                    $this->db->trans_begin();



                    $this->$model->updateData($updData,'grn_id',$_POST['grn_id'],'ret_grn_entry');

                    if($this->db->trans_status()===TRUE)

                    {

                        $this->db->trans_commit();

						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'GRN Entry',

							'operation'     => 'Cancel',

							'record'        => $_POST['grn_id'],

							'remark'        => 'GRN Entry Cancelled successfully'

							);

						 $this->log_model->log_detail('insert','',$log_data);

                         $this->session->set_flashdata('chit_alert',array('message'=>'GRN Entry Cancelled Successfully','class'=>'success','title'=>'GRN Entry'));

                         $return_data=array('status'=>TRUE,'message'=>'Order Instructions Added Successfully..');

                    }

                    else

                    {

                        // echo $this->db->last_query();exit;

                        $this->db->trans_rollback();

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'GRN Entry'));

                        $return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

                    }

                    echo json_encode($return_data);

			 break;



		    default:

		            $from_date	= $this->input->post('from_date');

			        $to_date	= $this->input->post('to_date');


					$profile=$this->$model->get_profile_settings($this->session->userdata('profile'));

			        $list=$this->$model->ajax_getGrnentrydetails($from_date, $to_date);

				  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/grnentry/list');

			        $data = array(

			        					'list'  => $list,

										'profile' =>$profile,

										'access'=> $access

			        				);

					echo json_encode($data);

		}

	}

	function grn_invoice($id)

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $set_model = "admin_settings_model";

        $data['comp_details']   = $this->$set_model->get_company();

        $data['grn_details']    = $this->$model->get_grn_details($id);

        //echo "<pre>";print_r($data);exit;

        $this->load->helper(array('dompdf', 'file'));

        $dompdf = new DOMPDF();

        $html = $this->load->view(self::VIEW_FOLDER.'grn_entry/invoice', $data,true);

        $dompdf->load_html($html);

        $dompdf->set_paper('A4', "portriat" );

		$dompdf->render();

		while(ob_get_level()) ob_end_clean();
		$dompdf->stream("VendorAck.pdf",array('Attachment'=>0));

	}





	//GRN ENTRY



	//Supplier Update

	function update_karigar(){

		$model=	self::RET_PUR_ORDER_MODEL;

		$updData=array(

			'firstname'=>$_POST['first_name'],

			'pan_no'=>$_POST['pan_no'],

			'gst_number'=>$_POST['gst_no'],

			'address1'=>$_POST['address1'],

			'address2'=>$_POST['address2'],

			'address3'=>$_POST['address3'],

			'pincode'=>$_POST['pin_code_add'],

			'id_city'=>$_POST['id_city'],

			'id_state'=>$_POST['id_state'],

			'id_country'=>$_POST['id_country'],

			);

		  $this->db->trans_begin();

		  $status = $this->$model->updateData($updData,'id_karigar',$_POST['id_karigar'],'ret_karigar');

		  if($this->db->trans_status()===TRUE)

		  {

			  $this->db->trans_commit();

			  $return_data=array('status'=>TRUE,'title'=>'Success','priority'=>'success','msg'=>'Karigar Updated successfully..');

		  }

		  else

		  {



			  $this->db->trans_rollback();

			  $return_data=array('status'=>FALSE,'title'=>'Warning','priority'=>'danger','msg'=>'Unable to proceed your request');

		  }

			  echo json_encode($return_data);

	}

	//Supplier Update







	//approval stock tag list

	function approvalstock_tag($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$set_model = "admin_settings_model";

		switch($type)

		{

			case 'list':

					$data['access'] = $this->$set_model ->get_access('admin_ret_purchase/approvalstock_tag/list');

					$data['main_content'] = self::VIEW_FOLDER.'approval_tag';




        			$this->load->view('layout/template', $data);

			break;



			case 'ajax':

				$list=$this->$model->get_approval_stock_tags($_POST);

			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/approvalstock_tag/list');

		        $data = array(

		        					'list'  => $list,

									'access'=> $access


		        				);

				echo json_encode($data);

			break;

		}

	}



	function order_place()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $fin_year       = $this->$model->get_FinancialYear();

	    $req_data       = $_POST['req_data'];

	    $karigar_details    =[];



        foreach($req_data as $r){

			$karigar_details[$r['id_karigar']][] = $r;

		}

	    foreach($karigar_details as $id_karigar => $tag_details)

	    {

	        $pur_no = $this->$model->generatePurNo();

	        $total_pcs = 0;

	        $total_weight = 0;

	        $order = array(

 			    'fin_year_code'     => $fin_year['fin_year_code'],

 				'pur_no'            => $pur_no,

 				'order_status'		=> 3,

 				'order_type'		=> 1,

 				'order_pcs'			=> 0,

 				'order_approx_wt'	=> 0,

 				'order_for'			=> 1,

 				'is_against_approval_stock'			=> 1,

 				'id_karigar'		=> $id_karigar,

 				'order_date'		=> date("Y-m-d H:i:s"),

				'createdon'         => date("Y-m-d H:i:s"),

				'order_taken_by'    => $this->session->userdata('uid')

			);

			$this->db->trans_begin();

            $insOrder = $this->$model->insertData($order,'customerorder');



	        foreach($tag_details as $val)

	        {







	            $tag_det = $this->$model->get_approval_tag_details($val['tag_id']);



	            $total_pcs+=$tag_det['piece'];

	            $total_weight+=$tag_det['gross_wt'];



	            $orderDetails = array(

				'id_customerorder'	=>$insOrder,

				'approval_tagid'	=>$val['tag_id'],

				'orderstatus'		=>3,

				'id_weight_range'	=> NULL,

				'id_product'		=> (!empty($tag_det['product_id']) ? $tag_det['product_id'] :NULL ),

				'design_no'			=> (!empty($tag_det['design_id']) ? $tag_det['design_id'] :NULL ),

				'id_sub_design'		=> (!empty($tag_det['id_sub_design']) ? $tag_det['id_sub_design'] :NULL ),

				'totalitems'		=> (!empty($tag_det['piece']) ? $tag_det['piece'] :NULL ),

				'weight'		    => (!empty($tag_det['gross_wt']) ? $tag_det['gross_wt'] :NULL ),

				'size'				=> (!empty($tag_det['size']) ? $tag_det['size'] :NULL ),

				'smith_due_date'	=> NULL,

				'order_date'		=> date("Y-m-d H:i:s"),

				'id_employee'       => $this->session->userdata('uid'),

				);

				$insOrderdet = $this->$model->insertData($orderDetails,'customerorderdetails');



				if($insOrderdet)

				{

				    $dCData = $this->admin_settings_model->getBranchDayClosingData($val['current_branch']);

    	            $bill_date = ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);



				    $tag_log=array(

                            'tag_id'	  =>$val['tag_id'],

                            'date'		  =>$bill_date,

                            'status'	  =>13,

                            'from_branch' =>$tag_det['current_branch'],

                            'to_branch'	  =>NULL,

                            'issuspensestock'	  =>1,

                            'created_on'  =>date("Y-m-d H:i:s"),

                            'created_by'  =>$this->session->userdata('uid'),

                            );

                            $this->$model->insertData($tag_log,'ret_taging_status_log');

				}

	        }

	        $this->$model->updateData(array('order_pcs'=>$total_pcs,'order_approx_wt'=>$total_weight),'id_customerorder',$insOrder,'customerorder');

	    }

    	if($this->db->trans_status()===TRUE)

		{

			$this->db->trans_commit();

			$log_data = array(

				'id_log'        => $this->session->userdata('id_log'),

				'event_date'    => date("Y-m-d H:i:s"),

				'module'        => 'Approval Stock',

				'operation'     => 'Add',

				'record'        =>  $insOrder,

				'remark'        => 'Order Placed added successfully'

				);

			$this->log_model->log_detail('insert','',$log_data);

			$this->session->set_flashdata('chit_alert', array('message' => 'Order Placed Successfully','class' => 'success','title'=>'Order'));

			$response_data=array('status'=>TRUE,'msg'=>'Order Placed Successfully..');

		}

		else

		{

		    //echo $this->db->_error_message();

		    echo $this->db->last_query();exit;

			$this->db->trans_rollback();

			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to Proceed Your Request..','class' => 'danger','title'=>'Order'));

			$response_data=array('status'=>FALSE,'msg'=>'Unable to Proceed Your Request..');

		}

        echo json_encode($response_data);

	}





	function convert_to_normal_stock()

	{

	    $model=	self::RET_PUR_ORDER_MODEL;

	    $req_data       = $_POST['req_data'];

	    $karigar_details    =[];

	    $ho              =  $this->$model->get_headOffice();

	    foreach($req_data as $val)

	    {

	        $tag_det = $this->$model->get_approval_tag_details($val['tag_id']);



	        if($tag_det['tag_status']==0)

	        {

    	        $dCData = $this->admin_settings_model->getBranchDayClosingData($tag_det['current_branch']);



    	        $bill_date = ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);



                $this->$model->updateData(array('is_approval_stock_converted'=>1,'app_stk_converted_date'=>date("Y-m-d H:i:s"),'app_stk_converted_by'=>$this->session->userdata('uid')),'tag_id',$val['tag_id'], 'ret_taging');



                $ho_log_data=array(

                'tag_id'	  =>$val['tag_id'],

                'date'		  =>$bill_date,

                'status'	  =>0,

                'from_branch' =>NULL,

                'to_branch'   =>$ho['id_branch'],

                'created_on'  =>date("Y-m-d H:i:s"),

                'created_by'  =>$this->session->userdata('uid'),

                );

                $this->$model->insertData($ho_log_data,'ret_taging_status_log'); //Update Tag lot status



                if($ho['id_branch']!=$tag_det['current_branch'])

                {

                    $ho_log_data=array(

                    'tag_id'	  =>$val['tag_id'],

                    'date'		  =>$bill_date,

                    'status'	  =>4,

                    'from_branch' =>1,

                    'to_branch'   =>$tag_det['current_branch'],

                    'created_on'  =>date("Y-m-d H:i:s"),

                    'created_by'  =>$this->session->userdata('uid'),

                    );

                    $this->$model->insertData($ho_log_data,'ret_taging_status_log'); //Update Tag lot status



                    $branch_log_data=array(

                    'tag_id'	  =>$val['tag_id'],

                    'date'		  =>$bill_date,

                    'status'	  =>0,

                    'from_branch' =>1,

                    'to_branch'   =>$tag_det['current_branch'],

                    'created_on'  =>date("Y-m-d H:i:s"),

                    'created_by'  =>$this->session->userdata('uid'),

                    );

                    $this->$model->insertData($branch_log_data,'ret_taging_status_log'); //Update Tag lot status

                }

	        }

	    }

    	if($this->db->trans_status()===TRUE)

		{

			$this->db->trans_commit();

			$this->session->set_flashdata('chit_alert', array('message' => 'Tag Status Changed Successfully','class' => 'success','title'=>'Order'));

			$response_data=array('status'=>TRUE,'msg'=>'Tag Status Changed Successfull..');

		}

		else

		{

		    //echo $this->db->_error_message();

		    echo $this->db->last_query();exit;

			$this->db->trans_rollback();

			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to Proceed Your Request..','class' => 'danger','title'=>'Order'));

			$response_data=array('status'=>FALSE,'msg'=>'Unable to Proceed Your Request..');

		}

        echo json_encode($response_data);

	}





	//approval stock tag list





	//Lot generate

	function generateLot()

	{

	    $model           =	self::RET_PUR_ORDER_MODEL;

	    $po_id           =  $_POST['po_id'];

	    $item_details    =  $_POST['req_data'];

	    $remark          =  $_POST['remark'];

	    $lotData = [];

        foreach($item_details as $r){

			$lotData[$r['stock_type']][$r['cat_id']][$r['id_purity']][] = $r;

		}



        $ho              =  $this->$model->get_headOffice();

		$dCData          =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

		$bill_date       =  ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

		$po_details      = $this->$model->get_po_details($po_id);

    	foreach($lotData as $stk_type =>$stock_type)

		{

		    foreach($stock_type as $cat_id =>$category)

		    {

		        foreach($category as $id_purity =>$product_details)

		        {



		            $data = array(

		                'narration'				=> $remark,

                        'lot_date'				=> $bill_date,

                        'created_branch'		=> (isset($ho['id_branch']) ? $ho['id_branch']:NULL ),

                        'lot_received_at'       => $ho['id_branch'],

                        'stock_type'            => $stk_type,

                        'lot_from'              => 2,

                        'po_id'                 => $po_id,

                        'gold_smith'			=> $po_details['po_karigar_id'],

                        'id_category'			=> $cat_id,

                        'id_purity'				=> $id_purity,

                        'created_on'	  		=> date("Y-m-d H:i:s"),

                        'created_by'      		=> $this->session->userdata('uid')

                        );

                        $this->db->trans_begin();

    	 	            $insId = $this->$model->insertData($data,'ret_lot_inwards');

    	 	            if($insId)

    	 	            {


    	 	                foreach($product_details as $items)

    	 	                {

								$item_details      = $this->$model->get_po_item_details($items['po_item_id']);


    	 	                        $lotDetails = array(

                                        'lot_no'	    =>$insId,

                                        'po_item_id'   =>($items['po_item_id']!='' ? $items['po_item_id']:NULL),

                                        'lot_product'   =>($items['pro_id']!='' ? $items['pro_id']:NULL),

                                        'lot_id_design'   =>($items['id_design']!='' ? $items['id_design']:NULL),

                                        'id_sub_design'   =>($items['id_sub_design']!='' ? $items['id_sub_design']:NULL),

                                        'id_section'   =>($items['id_section']!='' ? $items['id_section']:NULL),

                                        'no_of_piece'   =>($items['pcs']!='' ? $items['pcs']:0),

                                        'gross_wt'      =>($items['gross_wt']!='' ? $items['gross_wt']:0),

                                        'less_wt'       =>($items['less_wt']!='' ? $items['less_wt']:0),

                                        'net_wt'        =>($items['net_wt']!='' ? $items['net_wt']:0),

										'wastage_percentage'        =>($item_details['item_wastage']!='' ? number_format((float)$item_details['item_wastage'],3,'.',''):0),

										'mc_type'        =>($item_details['mc_type']!='' ? $item_details['mc_type']:0),

										'making_charge'        =>($item_details['mc_value']!='' ? number_format((float)$item_details['mc_value'],2,'.',''):0),

										'rate'        =>($item_details['fix_rate_per_grm']!='' ? $item_details['fix_rate_per_grm']:0),

										'rate_calc_type'        =>($item_details['rate_calc_type']!='' ? $item_details['rate_calc_type']:0),

										'calc_type'        =>($item_details['pure_wt_calc_type']!='' ? $item_details['pure_wt_calc_type']:0),

										'purchase_touch'        =>($item_details['purchase_touch']!='' ? $item_details['purchase_touch']:0),

										'lot_id_category'=> $cat_id,

										'lot_id_purity'  => $id_purity,

                                        'pur_wt' => ($item_details['item_pure_wt']!='' ? $item_details['item_pure_wt'] : 0),

                                    );




                                    $detail_insId = $this->$model->insertData($lotDetails,'ret_lot_inwards_detail');

                                    if($detail_insId)

                                    {

                                        $stone_details = json_decode($items['stone_details'],true);

                                        //echo "<pre>";print_r($items['stone_details']);exit;

                                        if(sizeof($stone_details)>0){

                                            foreach($stone_details as $val)

                                            {

                                                $stnData = array(
                                                        'id_lot_inward_detail'  =>$detail_insId,
                                                        'stone_id'              =>$val['po_stone_id'],
                                                        'uom_id'                =>$val['uom_id'],
                                                        'stone_pcs'             =>$val['qc_passed_pcs'],
                                                        'stone_wt'              =>$val['qc_passed_wt'],
                                                        'is_apply_in_lwt'       =>$val['is_apply_in_lwt'],
                                                        'rate_per_gram'         =>$val['po_stone_rate'],
                                                        'stone_cal_type'        => $val['po_stone_calc_based_on'] != NULL ? $val['po_stone_calc_based_on'] : 1,
                                                        'stone_quality_id'      =>$val['po_quality_id'],
                                                        'price'                 =>($val['po_stone_calc_based_on'] ==2 ? number_format(($val['po_stone_rate']*$val['qc_passed_pcs']),2,'.','')  : number_format(($val['po_stone_rate']*$val['qc_passed_wt']),2,'.','')),
                                                        );

                                                $this->$model->insertData($stnData,'ret_lot_inwards_stone_detail');

                                            }

                                        }



                                    }


    	 	                    //$this->$model->updateData(array('is_lot_created'=>1,'lot_no'=>$insId),'id_qc_issue_details',$items['id_qc_issue_details'],'ret_po_qc_issue_details');

    	 	                }

    	 	            }

		        }

		    }

		}

        if($this->db->trans_status()===TRUE)

        {

            $log_data = array(

            	'id_log'        => $this->session->userdata('id_log'),

            	'event_date'	=> date("Y-m-d H:i:s"),

            	'module'      	=> 'Lot',

            	'operation'   	=> 'Add',

            	'record'        => $insId,

            	'remark'       	=> 'Lot added successfully From QC Receipt'

            );

            $this->log_model->log_detail('insert','',$log_data);

            $this->db->trans_commit();

            $responseData=array('status'=>true,'message'=>'Lot Created successfully.');

        }

        else

        {

            $this->db->trans_rollback();

            $responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

        }

        echo json_encode($responseData);

	}

	//Lot generate







	function update_po_approval()

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$response_data=array();

		$insert_data=$this->input->post('req_data');

		foreach ($insert_data as $value)

		{

            $Data=array(

            'po_id' => $value['id_po'],

            'is_approved' 	 => 1,

			'approved_by' 	 =>  $this->session->userdata('uid'),

			'po_delivery_date'=> $value['due_date'],

            'approved_date'  => date("Y-m-d H:i:s"),

            );

            $this->db->trans_begin();

			$po_id = $this->$model->updateData($Data,'po_id',$value['id_po'],'ret_purchase_order');

		}

		if($this->db->trans_status()===TRUE)

        {

		 	$this->db->trans_commit();

			 $log_data = array(

				'id_log'        => $this->session->userdata('id_log'),

				'event_date'    => date("Y-m-d H:i:s"),

				'module'        => 'Rate Fixing',

				'operation'     => 'Update',

				'record'        =>  $po_id,

				'remark'        => 'Rate Fixing Updated successfully'

				);

			$this->log_model->log_detail('insert','',$log_data);

		 	$response_data=array('status'=>TRUE,'message'=>'Approved Successfully.');

		}

		else

		{

			$this->db->trans_rollback();

			$response_data=array('status'=>FALSE,'message'=>'Unable to proceed Your Request.');

		}

		echo json_encode($response_data);

	}





    //Weight and Amount Conversation

    function get_approval_rate_fixing_po_no()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_approval_rate_fixing_po_no($_POST);

        echo json_encode($data);

    }



    function opening_balance_ratefixing()

    {

        $model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->opening_balance_ratefixing($_POST);

        echo json_encode($data);

    }



    function supplier_rate_cut($type = "",$id="",$status="")

	{

		$model =	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;


		switch ($type) {

			case 'list':

				$data['main_content'] = self::VIEW_FOLDER . 'amt_weight_conversation/list';

				$data['access'] = $this->$SETT_MOD ->get_access('admin_ret_purchase/supplier_rate_cut/list');

				$this->load->view('layout/template', $data);

				break;

			case 'add':

				$ho                  =  $this->$model->get_headOffice();

				$data['financial_year']  = $this->$model->GetFinancialYear();

				$data['comp_details']=$this->admin_settings_model->getCompanyDetails($ho['id_branch']);

				$data['is_against_po_bills']      = $this->$model->get_ret_settings('issue_against_po_bills');

				$data['main_content'] = self::VIEW_FOLDER . 'amt_weight_conversation/form';

				$this->load->view('layout/template', $data);

				break;

			case 'save':

				$addData                =  $_POST['supplier_rate_cut'];

				$billing                =  $_POST['billing'];

				$ho                     =  $this->$model->get_headOffice();

				$entry_date             =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

				$date_add               =  ($entry_date['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $entry_date['entry_date']);

				$ref_no                 = $this->$model->generate_approval_refno();

				$fin_year              = $this->$model->get_FinancialYear();




				$data = array(

				    'ref_no'            => ($fin_year['fin_year_code'].'-'.$ref_no),

					'is_opening_blc'	=> $addData['is_opening_blc'],

					'op_blc_id'	        => $addData['id_smith_company_op_balance'],

					'convert_to'		=> $addData['convert_to'],

					'conversion_type'	=> $addData['conversion_type'],

					'id_branch'         => ($addData['id_branch'] != '' ? $addData['id_branch'] : 1),

					'id_karigar'        => ($addData['id_karigar'] != '' ? $addData['id_karigar'] : NULL),

					'rate_cut_type'     => ($addData['rate_cut_type'] != '' ? $addData['rate_cut_type'] : NULL),

					'id_metal'          => ($addData['id_metal'] != '' ? $addData['id_metal'] : NULL),

					'id_product'        => ($addData['id_product'] != '' ? $addData['id_product'] : NULL),

					'po_id'          	=> ($addData['po_id'] != '' ? $addData['po_id'] : NULL),

					'date_add'          => ($date_add != '' ? $date_add : NULL),

					'charges_amount'    => ($addData['charges_amount'] != '' ? $addData['charges_amount'] : 0),

					'amount'            => ($addData['total_bill_amount'] != '' ? $addData['total_bill_amount'] : 0),

					'weight'            => ($addData['type1_wt'] != '' ? $addData['type1_wt'] : ($addData['type2_wt'] != '' ? $addData['type2_wt'] : 0)),

					'rate_per_gram'     => ($addData['src_rate'] != '' ? $addData['src_rate'] : 0),

					'round_off'     	=> ($addData['round_off'] != '' ? $addData['round_off'] : 0),

					'status'            => 1,

					'narration'         => ($addData['src_remark'] != '' ? $addData['src_remark'] : NULL),
					'is_tds'            => (isset($addData['is_tds']) ? $addData['is_tds'] : 0),
					'tds_percent'       => ($addData['tds_percent'] != '' ? $addData['tds_percent'] : 0),
					'tds_tax_value'     => ($addData['tds_tax_value'] != '' ? $addData['tds_tax_value'] : 0),

					'tax_amount'		=> $this->isEmptySetDefault($addData['tax_amount'],0),
					'tax_percentage'	=> $this->isEmptySetDefault($addData['tax_percentage'],0),
					'sgst_cost'	        => $this->isEmptySetDefault($addData['sgst_cost'],0),
					'cgst_cost'	        => $this->isEmptySetDefault($addData['cgst_cost'],0),
					'igst_cost'	        => $this->isEmptySetDefault($addData['igst_cost'],0),

					'amount_type'       => ($addData['amount_type'] != '' ? $addData['amount_type'] : 1), // 1 = CR, 2 = DR

					'weight_type'       => ($addData['weight_type'] != '' ? $addData['weight_type'] : 1), // 1 = CR, 2 = DR

					'created_on'        => date("Y-m-d H:i:s"),

					'created_by'        => $this->session->userdata('uid'),

				);

				$this->db->trans_begin();

				$insOrder = $this->$model->insertData($data, 'ret_supplier_rate_cut');

				if($insOrder && $addData['rate_cut_type']==1 && $data['amount'] > 0 )

				{

				    $net_banking_details = json_decode($billing['net_banking'],true);

				    $pay_refno       = $this->$model->generatePaymentNo();

				    $payData=array(

				          'id_supplier_rate_cut'=>$insOrder,

		                  'pay_sup_id'      =>$addData['id_karigar'],

		                  'bill_type'       =>3,

		                  'pay_refno'       =>$pay_refno,

		                  'pay_amt'         =>$data['amount'],

		                  'pay_narration'   =>($addData['src_remark'] != '' ? $addData['src_remark'] : NULL),

		                  'pay_create_on'   =>date("Y-m-d H:i:s"),

		                  'pay_created_by'=>$this->session->userdata('uid'),

		                  );



			        $insId=$this->$model->insertData($payData,'ret_po_payment');



				    if($billing['cash_amount']!=0)

                    {

                        $cash_payment_data = array(

                                             'pay_id'           =>$insId,

                                             'type'             =>4,

                                             'pay_mode'         =>'CSH',

                                             'payment_amount'   =>$billing['cash_amount'],

                                            );

                         $this->$model->insertData($cash_payment_data,'ret_po_payment_detail');

                    }



                    if(sizeof($net_banking_details)>0)

                    {

                        foreach($net_banking_details as $pay)

                        {

                            $netBankingPaymentData[]=array(

                                             'pay_id'           =>$insId,

                                             'type'             =>1,

                                             'pay_mode'         =>$pay['nb_type'],

                                             'payment_amount'   =>$pay['pay_amount'],

                                             'ref_no'           =>($pay['ref_no']!='' ? $pay['ref_no']:NULL),

                                             'id_bank'          =>($pay['id_bank']!='' ? $pay['id_bank']:NULL),

                                             'ref_date'          =>($pay['nb_date']!='' ? $pay['nb_date']:date("Y-m-d")),

                                            );

                        }

                        $this->$model->insertBatchData($netBankingPaymentData,'ret_po_payment_detail');

                    }

                    // Insert into ret_po_bill_payment_details so the payment listing can resolve PO ref no
                    $bill_payment_data = array(
                        'pay_id'                => $insId,
                        'po_id'                 => ($addData['po_id'] != '' ? $addData['po_id'] : 0),
                        'id_supplier_rate_cut'  => $insOrder,
                        'bill_amount'           => $data['amount'],
                        'total_amount'          => $data['amount'],
                    );
                    $this->$model->insertData($bill_payment_data, 'ret_po_bill_payment_details');

				}

				if ($this->db->trans_status() === TRUE) {

					$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Approval to Invoice Conversion',

						'operation'     => 'Add',

						'record'        =>  $insOrder,

						'remark'        => 'Approval to Invoice Conversion added successfully'

						);

					$this->log_model->log_detail('insert','',$log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Added Successfully', 'class' => 'success', 'title' => 'Rate Cut'));

					$return_data = array('status' => TRUE, 'message' => 'Order Instructions Added Successfully..');

				} else {

					echo $this->db->last_query();

					exit;

					$this->db->trans_rollback();

					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Rate Cut'));

					$return_data = array('status' => FALSE, 'message' => 'Unable to proceed the requested process');

				}

				echo json_encode($return_data);

				break;



			case 'job_receipt':

				$data['rate_cut_details']=$this->$model->get_approval_conversion_details($id);

				$data['comp_details']=$this->admin_settings_model->getCompanyDetails("");

				//echo "<pre>";print_r($data);exit;

				$html = $this->load->view(self::VIEW_FOLDER.'amt_weight_conversation/print', $data,true);

				echo $html;exit;



			break;

			case 'ajax':

				$list = $this->$model->get_supplier_rate_cut_details($_POST);

				$access = $this->admin_settings_model->get_access('admin_ret_purchase/supplier_rate_cut/list');

				$data = array(

					'list'  => $list,

					'access' => $access

				);

				echo json_encode($data);

				break;

				case 'cancel':

				    $ho                     =  $this->$model->get_headOffice();

				    $entry_date             =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

				    $date_add               =  ($entry_date['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $entry_date['entry_date']);



					$data = array(

				            'status'            => 2,

					        'cancelled_on'	    => $date_add,

					        'updated_on'	    => date("Y-m-d H:i:s"),

					        'cancelled_by'      => $this->session->userdata('uid'),

					        'updated_by'        => $this->session->userdata('uid'),

					        'cancelled_reason'  => ($_POST['cancel_reason']!='' ? $_POST['cancel_reason'] :NULL),

					        );

					$this->db->trans_begin();

					$updstatus = $this->$model->updateData($data,'id_supplier_rate_cut',$_POST['id_supplier_rate_cut'],'ret_supplier_rate_cut');

    				if ($this->db->trans_status() === TRUE)

    				{

    					$this->db->trans_commit();

						$log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'Approval to Invoice Conversion',

							'operation'     => 'Cancel',

							'record'        =>  $_POST['id_supplier_rate_cut'],

							'remark'        => 'Approval to Invoice Conversion Cancelled successfully'

							);

	                     $this->log_model->log_detail('insert','',$log_data);

    					$this->session->set_flashdata('chit_alert', array('message' => 'Cancelled Successfully', 'class' => 'success', 'title' => 'Weight Conversion'));

    					$return_data = array('status' => TRUE, 'message' => 'Cancelled Successfully..');

    				}else{

    					$this->db->trans_rollback();

    					$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Weight Conversion'));

    					$return_data = array('status' => FALSE, 'message' => 'Unable to proceed the requested process');

    				}

    				echo json_encode($return_data);

				break;

				echo json_encode($data);

		}

	}

    //Weight and Amount Conversation

    function lot_and_tag_wise_report($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$set_model = "admin_settings_model";

		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'lot_and_tag_wise_report';

        			$this->load->view('layout/template', $data);

			break;



			case 'ajax':

				$list=$this->$model->get_lot_and_tag_wise_report($_POST);

			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/lot_and_tag_wise_report/list');

		        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;

		}

	}



	function headoffice_valut_report($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$set_model = "admin_settings_model";

		switch($type)

		{

			case 'list':
                     $data['is_supplierbill_entry_req']      = $this->$model->get_ret_settings('is_supplierbill_entry_req');
					$data['main_content'] = self::VIEW_FOLDER.'reports/headoffice_valut_report';

        			$this->load->view('layout/template', $data);

			break;



			case 'ajax':

			    $summary['blc_pcs'] = 0;

			    $summary['blc_gwt'] = 0;

			    $summary['blc_nwt'] = 0;

			    $summary['blc_diawt'] = 0;

			    $summary['inw_pcs']    = 0;

			    $summary['inw_gwt']    = 0;

			    $summary['inw_nwt']    = 0;

			    $summary['inw_diawt']    = 0;

			    $summary['issue_pcs']    = 0;

			    $summary['issue_gwt']    = 0;

			    $summary['issue_nwt']    = 0;

			    $summary['issue_diawt']  = 0;

			    $summary['lot_pcs']  = 0;

			    $summary['lot_gwt']  = 0;

			    $summary['lot_nwt']    = 0;

			    $summary['lot_dia_wt']    = 0;

			    $summary['tag_pcs']    = 0;

			    $summary['tag_gwt']    = 0;

			    $summary['tag_nwt']    = 0;

			    $summary['tag_dia_wt']    = 0;

			    $summary['pur_ret_pcs']    = 0;

			    $summary['pur_ret_gwt']    = 0;

			    $summary['pur_ret_nwt']    = 0;

			    $summary['ret_diawt']    = 0;

			    $summary['closing_pcs']    = 0;

			    $summary['closing_gwt']    = 0;

			    $summary['closing_nwt']    = 0;

			    $summary['closing_diawt']    = 0;

				$po_details=$this->$model->get_headoffice_valut_report($_POST);

				foreach($po_details as $val)

				{

				    $summary['blc_pcs']=$summary['blc_pcs']+$val['blc_pcs'];

				    $summary['blc_gwt']=$summary['blc_gwt']+$val['blc_gwt'];

				    $summary['blc_nwt']=$summary['blc_nwt']+$val['blc_nwt'];

				    $summary['blc_diawt']=$summary['blc_diawt']+$val['blc_diawt'];

				    $summary['inw_pcs']=$summary['inw_pcs']+$val['inw_pcs'];

				    $summary['inw_gwt']=$summary['inw_gwt']+$val['inw_gwt'];

				    $summary['inw_nwt']=$summary['inw_nwt']+$val['inw_nwt'];

				    $summary['inw_diawt']=$summary['inw_diawt']+$val['inw_diawt'];

				    $summary['pur_ret_pcs']=$summary['pur_ret_pcs']+$val['pur_ret_pcs'];

				    $summary['pur_ret_gwt']=$summary['pur_ret_gwt']+$val['pur_ret_gwt'];

				    $summary['pur_ret_nwt']=$summary['pur_ret_nwt']+$val['pur_ret_nwt'];

				    $summary['ret_diawt']=$summary['ret_diawt']+$val['ret_diawt'];

				    $summary['issue_pcs']=$summary['issue_pcs']+$val['issue_pcs'];

				    $summary['issue_gwt']=$summary['issue_gwt']+$val['issue_wt'];

				    $summary['issue_nwt']=$summary['issue_nwt']+$val['issue_wt'];

				    $summary['issue_diawt']= $summary['issue_diawt']+$val['issue_diawt'];

				    $summary['lot_pcs']=$summary['lot_pcs']+$val['lot_pcs'];

				    $summary['lot_gwt']=$summary['lot_gwt']+$val['lot_gwt'];

				    $summary['lot_nwt']=$summary['lot_nwt']+$val['lot_nwt'];

				    $summary['lot_dia_wt']=$summary['lot_dia_wt']+$val['lot_dia_wt'];

				    $summary['tag_pcs']=$summary['tag_pcs']+$val['tag_pcs'];

				    $summary['tag_gwt']=$summary['tag_gwt']+$val['tag_gwt'];

				    $summary['tag_nwt']=$summary['tag_nwt']+$val['tag_nwt'];

				    $summary['tag_dia_wt']=$summary['tag_dia_wt']+$val['tag_dia_wt'];

				    $summary['closing_pcs']=$summary['closing_pcs']+$val['closing_pcs'];

				    $summary['closing_gwt']=$summary['closing_gwt']+$val['closing_gwt'];

				    $summary['closing_nwt']=$summary['closing_nwt']+$val['closing_nwt'];

				    $summary['closing_diawt']=$summary['closing_diawt']+$val['closing_diawt'];

				}

			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/headoffice_valut_report/list');

		        $data = array(

		        					'list'  => $po_details,

									'access'=> $access,

									'summary'=> $summary

		        				);

				echo json_encode($data);

			break;

		}

	}

	function weight_gain_loss($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$set_model = "admin_settings_model";

		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'reports/weight_gain_loss';

        			$this->load->view('layout/template', $data);

			break;



			case 'ajax':

			    $summary['blc_pcs'] = 0;

			    $summary['blc_gwt'] = 0;

			    $summary['blc_nwt'] = 0;

			    $summary['blc_diawt'] = 0;

			    $summary['lot_pcs']  = 0;

			    $summary['lot_gwt']  = 0;

			    $summary['lot_nwt']    = 0;

			    $summary['lot_dia_wt']    = 0;

			    $summary['tag_pcs']    = 0;

			    $summary['tag_gwt']    = 0;

			    $summary['tag_nwt']    = 0;

			    $summary['tag_dia_wt']    = 0;

			    $summary['gl_pcs']    = 0;

			    $summary['gl_gwt']    = 0;

			    $summary['gl_nwt']    = 0;

			    $summary['gl_diawt']    = 0;

				$po_details=$this->$model->get_weight_gain_loss_report($_POST);



				foreach($po_details as $val)

				{

				    $summary['blc_pcs']=$summary['blc_pcs']+$val['blc_pcs'];

				    $summary['blc_gwt']=$summary['blc_gwt']+$val['blc_gwt'];

				    $summary['blc_nwt']=$summary['blc_nwt']+$val['blc_nwt'];

				    $summary['blc_diawt']=$summary['blc_diawt']+$val['blc_diawt'];

				    $summary['lot_pcs']=$summary['lot_pcs']+$val['lot_pcs'];

				    $summary['lot_gwt']=$summary['lot_gwt']+$val['lot_gwt'];

				    $summary['lot_nwt']=$summary['lot_nwt']+$val['lot_nwt'];

				    $summary['lot_dia_wt']=$summary['lot_dia_wt']+$val['lot_diawt'];

				    $summary['tag_pcs']=$summary['tag_pcs']+$val['tag_pcs'];

				    $summary['tag_gwt']=$summary['tag_gwt']+$val['tag_gwt'];

				    $summary['tag_nwt']=$summary['tag_nwt']+$val['tag_nwt'];

				    $summary['tag_dia_wt']=$summary['tag_dia_wt']+$val['tag_diawt'];

				    $summary['gl_pcs']=$summary['gl_pcs']+$val['gl_pcs'];

				    $summary['gl_gwt']=$summary['gl_gwt']+$val['gl_gwt'];

				    $summary['gl_nwt']=$summary['gl_nwt']+$val['gl_nwt'];

				    $summary['gl_diawt']=$summary['gl_diawt']+$val['gl_diawt'];

				}



			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/weight_gain_loss/list');

		        $data = array(

		        					'list'  => $po_details,

									'access'=> $access,

									'summary'=> $summary

		        				);

				echo json_encode($data);

			break;

		}

	}



	public function getpurchase_po_list(){

		$model=	self::RET_PUR_ORDER_MODEL;

		$data = $this->$model->getPurchasePos($_POST);

		echo json_encode($data);

	}

	function get_karigar_SupplierEntrys()

	{

		$model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_karigar_SupplierEntrys($_POST);

        echo json_encode($data);

	}

	function get_Available_SupplierPo()

	{

		$model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->get_Available_SupplierPo($_POST);

        echo json_encode($data);

	}

	function getKarigarIssueRefNo()

	{

		$model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->getKarigarIssueRefNo($_POST);

        echo json_encode($data);

	}

	function getKarigarMetalIssueLooseStones()

	{

		$model=	self::RET_PUR_ORDER_MODEL;

        $data= $this->$model->getKarigarMetalIssueLooseStones($_POST);

        echo json_encode($data);

	}



	 //Unfixing report

    function approval_unfixing_report($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'reports/approval_unfixing_report';

        			$this->load->view('layout/template', $data);

				break;



			case 'ajax':

					$list=$this->$model->get_approval_rate_unfixed_details($_POST);

				  	$access = $this->admin_settings_model->get_access('admin_ret_reports/approval_unfixing_report/list');

			        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				 );

					echo json_encode($data);

				break;

		}



    }

    //Unfixing report



    //Approval rate fixing

    function approval_rate_fixed($type="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		switch($type)

		{

			case 'list':

					$data['main_content'] = self::VIEW_FOLDER.'reports/approval_rate_fixed';

        			$this->load->view('layout/template', $data);

				break;



			case 'ajax':

					$list=$this->$model->get_approval_rate_fixed_details($_POST);

				  	$access = $this->admin_settings_model->get_access('admin_ret_reports/approval_rate_fixed/list');

			        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				 );

					echo json_encode($data);

				break;

		}



    }

    //Approval rate fixing

	//Smith / Company Opening Balance

	function smith_cmpy_op_bal($type = "",$id="",$status="")

	{

		$model =	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;


		switch ($type)

		{

			case 'list':

				$data['main_content'] = self::VIEW_FOLDER . 'smith_cmpy_op_bal/list';
				$data['access'] = $this->$SETT_MOD ->get_access('admin_ret_purchase/smith_cmpy_op_bal/list');
				$this->load->view('layout/template', $data);

			break;

			case 'add':

				$data['main_content'] = self::VIEW_FOLDER . 'smith_cmpy_op_bal/form';

				$this->load->view('layout/template', $data);

			break;

			case 'save':

				//echo"<pre>";print_r($_POST);exit;

				$response_data=array();

				$addData   =  $_POST['smth_cmpy_stk'];

				$ref_no    = $this->$model->generate_opblc_refno();

				$op_date = ($addData['date'] != '' ? date_format(date_create($addData['date']),'Y-m-d') : date("Y-m-d H:i:s"));

				$data = array(

					'ref_no'		    => $ref_no,

					'stock_type'		=> $addData['stock_type'],

					'smith_type'        => $addData['smith_type'],

					'balance_type'	    => $addData['bal_type'],

					'metal_type'	    => $addData['metal_type'],

					'id_metal'          => ($addData['id_metal'] != '' ? $addData['id_metal'] : NULL),

					//'id_category'       => ($addData['id_ret_category'] != '' ? $addData['id_ret_category'] : NULL),

					'id_category'       => ($addData['id_ret_category'] != '' ? $addData['id_ret_category'] : ($addData['metal_type']==2 ? ($addData['old_metal_type'] != '' ? $addData['old_metal_type'] : NULL) : NULL)),

					'id_product'        => ($addData['id_product'] != '' ? $addData['id_product'] : NULL),

					'id_karigar'        => ($addData['id_karigar'] != '' ? $addData['id_karigar'] : NULL),

					'pieces'            => ($addData['op_pcs'] != '' ? $addData['op_pcs'] : NULL),

					'weight'            => ($addData['op_wgt'] != '' ? $addData['op_wgt'] : 0),

					'net_wt'            => ($addData['op_net_wgt'] != '' ? $addData['op_net_wgt'] : 0),

					'dia_wt'            => ($addData['op_dia_wgt'] != '' ? $addData['op_dia_wgt'] : 0),

					'pure_wt'            => ($addData['op_pure_wgt'] != '' ? $addData['op_pure_wgt'] : 0),

					'id_old_metal_catid' => ($addData['old_metal_category'] != '' ? $addData['old_metal_category'] : NULL),

					'uom_id'            => ($addData['op_uom'] !='' ? $addData['op_uom'] : NULL),

					'weight_type'       => ($addData['wt_receipt_type']!='' && $addData['stock_type']==2 && $addData['op_wgt']>0  ? $addData['wt_receipt_type'] : NULL),

					'amount'		    => ($addData['op_amt'] != '' ? $addData['op_amt'] : 0),

					'amount_type'       => ($addData['amt_receipt_type']!='' && $addData['stock_type']==2 && $addData['op_amt']> 0 ? $addData['amt_receipt_type'] : NULL),

					'remarks'           => ($addData['remark'] != '' ? $addData['remark'] : NULL),

					// 'createdon'        => date("Y-m-d H:i:s"),

					'createdon'        => ($op_date != '' ? $op_date : date("Y-m-d H:i:s")),

					'createdby'        => $this->session->userdata('uid'),

				);

				$this->db->trans_begin();

			    $insOrder = $this->$model->insertData($data,'smith_company_op_balance');

				//print_r($this->db->last_query());exit;

			    if($this->db->trans_status()===TRUE)

    			{

    				$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Smith Company Stock',

						'operation'     => 'Add',

						'record'        => $insOrder,

						'remark'        => 'Smith Company Stock added successfully'

						);

						$this->log_model->log_detail('insert','',$log_data);


    				$this->session->set_flashdata('chit_alert',array('message'=>'Smith Company Stock Added Successfully','class'=>'success','title'=>'Smith/company Op Balance'));

    				$return_data=array('status'=>TRUE,'message'=>'Smith Company Stock Added Successfully..');

    			}

    			else

    			{



    			    echo $this->db->last_query();exit;

    				$this->db->trans_rollback();

    				$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Order Instructions'));

    				$return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

    			}

    			echo json_encode($return_data);

			break;

			case 'ajax':

				$list=$this->$model->get_smith_cmpy_op_bal_details($_POST);

			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/smith_cmpy_op_bal/list');

		        $data = array(

		        					'list'  => $list,

									'access'=> $access

		        				);

				echo json_encode($data);

			break;

			case 'edit':

				$data['edit_data'] = $this->$model->get_op_bal_by_id($id);
				if (empty($data['edit_data'])) {
					$this->session->set_flashdata('chit_alert', array('message' => 'Record not found', 'class' => 'danger', 'title' => 'Smith/Company Op Balance'));
					redirect('admin_ret_purchase/smith_cmpy_op_bal/list');
				}
				$data['main_content'] = self::VIEW_FOLDER . 'smith_cmpy_op_bal/form';
				$this->load->view('layout/template', $data);

			break;

			case 'update':

				$response_data = array();
				$addData = $this->input->post('smth_cmpy_stk');
				$op_date = ($addData['date'] != '' ? date_format(date_create($addData['date']), 'Y-m-d') : date('Y-m-d H:i:s'));

				$data = array(
					'stock_type'        => $addData['stock_type'],
					'smith_type'        => $addData['smith_type'],
					'balance_type'      => $addData['bal_type'],
					'metal_type'        => $addData['metal_type'],
					'id_metal'          => ($addData['id_metal'] != '' ? $addData['id_metal'] : NULL),
					'id_category'       => ($addData['id_ret_category'] != '' ? $addData['id_ret_category'] : ($addData['metal_type'] == 2 ? ($addData['old_metal_type'] != '' ? $addData['old_metal_type'] : NULL) : NULL)),
					'id_product'        => ($addData['id_product'] != '' ? $addData['id_product'] : NULL),
					'id_karigar'        => ($addData['id_karigar'] != '' ? $addData['id_karigar'] : NULL),
					'pieces'            => ($addData['op_pcs'] != '' ? $addData['op_pcs'] : NULL),
					'weight'            => ($addData['op_wgt'] != '' ? $addData['op_wgt'] : 0),
					'net_wt'            => ($addData['op_net_wgt'] != '' ? $addData['op_net_wgt'] : 0),
					'dia_wt'            => ($addData['op_dia_wgt'] != '' ? $addData['op_dia_wgt'] : 0),
					'pure_wt'           => ($addData['op_pure_wgt'] != '' ? $addData['op_pure_wgt'] : 0),
					'id_old_metal_catid' => ($addData['old_metal_category'] != '' ? $addData['old_metal_category'] : NULL),
					'uom_id'            => ($addData['op_uom'] != '' ? $addData['op_uom'] : NULL),
					'weight_type'       => ($addData['wt_receipt_type'] != '' && $addData['stock_type'] == 2 && $addData['op_wgt'] > 0 ? $addData['wt_receipt_type'] : NULL),
					'amount'            => ($addData['op_amt'] != '' ? $addData['op_amt'] : 0),
					'amount_type'       => ($addData['amt_receipt_type'] != '' && $addData['stock_type'] == 2 && $addData['op_amt'] > 0 ? $addData['amt_receipt_type'] : NULL),
					'remarks'           => ($addData['remark'] != '' ? $addData['remark'] : NULL),
					'createdon'         => ($op_date != '' ? $op_date : date('Y-m-d H:i:s')),
					'updatedby'         => $this->session->userdata('uid'),
				);

				$this->db->trans_begin();
				$this->$model->update_op_bal($id, $data);

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();

					$log_data = array(
						'id_log'     => $this->session->userdata('id_log'),
						'event_date' => date('Y-m-d H:i:s'),
						'module'     => 'Smith Company Stock',
						'operation'  => 'Edit',
						'record'     => $id,
						'remark'     => 'Smith Company Stock updated successfully'
					);
					$this->log_model->log_detail('insert', '', $log_data);

					$this->session->set_flashdata('chit_alert', array('message' => 'Opening Balance Updated Successfully', 'class' => 'success', 'title' => 'Smith/Company Op Balance'));
					$return_data = array('status' => TRUE, 'message' => 'Opening Balance Updated Successfully.');
				} else {
					$this->db->trans_rollback();
					$return_data = array('status' => FALSE, 'message' => 'Unable to proceed the requested process');
				}

				echo json_encode($return_data);

			break;

			case 'delete':

				$cancel_remark = trim($this->input->post('cancel_remark'));
				if (empty($cancel_remark)) {
					echo json_encode(array('status' => FALSE, 'message' => 'Cancel remark is required.'));
					return;
				}

				$this->db->trans_begin();
				$this->$model->delete_op_bal($id, $cancel_remark);

				if ($this->db->trans_status() === TRUE) {
					$this->db->trans_commit();

					$log_data = array(
						'id_log'     => $this->session->userdata('id_log'),
						'event_date' => date('Y-m-d H:i:s'),
						'module'     => 'Smith Company Stock',
						'operation'  => 'Cancel',
						'record'     => $id,
						'remark'     => 'Smith Company Stock cancelled - ' . $cancel_remark
					);
					$this->log_model->log_detail('insert', '', $log_data);

					$return_data = array('status' => TRUE, 'message' => 'Opening Balance Cancelled Successfully.');
				} else {
					$this->db->trans_rollback();
					$return_data = array('status' => FALSE, 'message' => 'Unable to cancel the record');
				}

				echo json_encode($return_data);

			break;

		}

	}

	//Smith / Company Opening Balance

	function CrDr_noteinv($id)

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$set_model = "admin_settings_model";

        $data['comp_details']   = $this->$set_model->get_company();

		$data['crdrdetails'] = $this->$model->get_crdr_detail($id);

		//echo "<pre>"; print_r($data);exit;

		$this->load->helper(array('dompdf', 'file'));

		$dompdf = new DOMPDF();

		$html = $this->load->view(self::VIEW_FOLDER.'crdr_note/inv_ack', $data,true);

		$dompdf->load_html($html);

		$dompdf->set_paper('A4', "portriat" );

		$dompdf->render();

		while(ob_get_level()) ob_end_clean();
		$dompdf->stream("VendorAck.pdf",array('Attachment'=>0));



	}

	// NonTag Lot Generate

	function nontag_lot_generate($type = "",$id="",$status="")

	{

		$model=	self::RET_PUR_ORDER_MODEL;

		switch($type)

		{

			case 'list':

				$data['main_content'] = self::VIEW_FOLDER . 'nontag_lot_generate/list';

				$this->load->view('layout/template', $data);

			break;



			case 'add':

				$data['main_content'] = self::VIEW_FOLDER . 'nontag_lot_generate/form';

				$this->load->view('layout/template', $data);

			break;

			case 'save':



				$nontag_item_details    =  $_POST['nontag_items'];

				$id_karigar = $_POST['id_karigar'];

				$lotData = [];

				foreach($nontag_item_details as $r)

				{

					$lotData[$r['id_category']][$r['id_purity']][] = $r;

				}

				$ho              =  $this->$model->get_headOffice();

				$dCData          =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

				$bill_date       =  ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

				foreach($lotData as $cat_id =>$category)

				{

					foreach($category as $id_purity =>$product_details)

					{

						$data = array(

							'lot_date'				=> $bill_date,

							'created_branch'		=> (isset($ho['id_branch']) ? $ho['id_branch']:NULL ),

							'lot_received_at'       => $ho['id_branch'],

							'stock_type'            => 2,

							'lot_from'              => 8,

							'gold_smith'			=> $id_karigar,

							'id_category'			=> $cat_id,

							'id_purity'				=> $id_purity,

							'created_on'	  		=> date("Y-m-d H:i:s"),

							'created_by'      		=> $this->session->userdata('uid')

						);

						$this->db->trans_begin();

						$insId = $this->$model->insertData($data,'ret_lot_inwards');

						if($insId)

						{

							foreach($product_details as $items)

							{

								//echo"<pre>";print_r($items);exit;



								$non_tag_data= array(

									'lot_no'	    =>$insId,

									'lot_product'   =>($items['id_product']!='' ? $items['id_product']:NULL),

									'no_of_piece'   =>($items['pcs']!='' ? $items['pcs']:0),

									'gross_wt'      =>($items['grswt']!='' ? $items['grswt']:0),

									'less_wt'       =>($items['less_wt']!='' ? $items['less_wt']:0),

									'net_wt'        =>($items['netwt']!='' ? $items['netwt']:0),

									'lot_id_category'=> $cat_id,

									'lot_id_purity'  => $id_purity,

                                    'pur_wt' => (isset($items['pure_wt']) && $items['pure_wt']!='' ? $items['pure_wt'] : 0),

								);

								$detail_insId = $this->$model->insertData($non_tag_data,'ret_lot_inwards_detail');

								if($detail_insId)

								{

									$existData=array('id_section'=>$items['id_section'],'id_product'=>$items['id_product'],'design'=>$items['id_design'],'id_sub_design'=>$items['id_sub_design'],'id_branch'=>$ho['id_branch']);

									$isExist = $this->$model->checkNonTagItemExist($existData);

									if($isExist['status'] == TRUE)

									{

										$nt_data = array(

											'id_nontag_item'=> $isExist['id_nontag_item'],

											'no_of_piece'	=> $items['pcs'],

											'gross_wt'		=> $items['grswt'],

											'net_wt'		=> $items['netwt'],

											'updated_by'	=> $this->session->userdata('uid'),

											'updated_on'	=> date('Y-m-d H:i:s'),

										);

										$this->$model->updateNTData($nt_data,'-');



										$non_tag_data=array(

											'product'	    => $items['id_product'],

											'design'	    => $items['id_design'],

											'id_sub_design'	=> $items['id_sub_design'],

											'no_of_piece'	=> $items['pcs'],

											'gross_wt'		=> $items['grswt'],

											'net_wt'		=> $items['netwt'],

											'from_branch'	=> $ho['id_branch'],

											'to_branch'	    => NULL,

											'ref_no'	    => $insId,

											'status'	    => 4,

											'date'          => $bill_date,

											'created_on'    => date("Y-m-d H:i:s"),

											'created_by'    =>  $this->session->userdata('uid')

										);

										$this->$model->insertData($non_tag_data,'ret_nontag_item_log');

										$section_nontag_log = array(

											'product'		=> $items['id_product'],

											'design'		=> $items['id_design'],

											'id_sub_design' => $items['id_sub_design'],

											'no_of_piece'	=> $items['pcs'],

											'gross_wt'		=> $items['grswt'],

											'net_wt'		=> $items['netwt'],

											"status"		=> 4,

											"from_branch"	=> $ho['id_branch'],

											"to_branch"		=> NULL,

											"from_section"	=> $items['id_section'],

											"to_section"    => NULL,

											"created_by"	=> $this->session->userdata('uid'),

											"created_on"	=> date('Y-m-d H:i:s'),

											"date"			=> $bill_date,

											);

										$this->$model->insertData($section_nontag_log,'ret_section_nontag_item_log');

									}

								}

							}

						}

					}

				}

				if($this->db->trans_status()===TRUE)

				{

					$log_data = array(

								'id_log'        => $this->session->userdata('id_log'),

								'event_date'	=> date("Y-m-d H:i:s"),

								'module'      	=> 'Non Tag Lot Generate',

								'operation'   	=> 'Add',

								'record'        =>  NULL,

								'remark'       	=> 'Non Tag Lot Generate.'

							);

					$this->log_model->log_detail('insert','',$log_data);



					$this->db->trans_commit();

					$responseData=array('status'=>true,'message'=>'Non Tag Lot Generated successfully.','insId'=>$insId);

				}

				else

				{

					//echo $this->db->last_query();exit;

					$this->db->trans_rollback();

					$responseData=array('status'=>false,'message'=>'Unable to proceed the requested operation.');

				}

				echo json_encode($responseData);

			break;

		}

	}

	// NonTag Lot Generate

	// Nontag Lot Receipt
	function nontag_receipt($type = "",$id="",$status="")
	{
		$model=	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;
		switch($type)
		{
			case 'list':
				$data['main_content'] = self::VIEW_FOLDER . 'nontag_receipt/list';
				$data['access'] = $this->$SETT_MOD ->get_access('admin_ret_purchase/nontag_receipt/list');
				$this->load->view('layout/template', $data);
			break;

			case 'add':
				$data['nontag_weight_per'] = $this->$model->get_ret_settings('nontag_weight_per');
				$data['main_content'] = self::VIEW_FOLDER . 'nontag_receipt/form';
				$this->load->view('layout/template', $data);
				//echo"<pre>";print_r($data);exit;
			break;

			case 'get_NontagLots':
				$data= $this->$model->get_NontagLots();
        		echo json_encode($data);
			break;

			case 'save':
				$response_data=array();
				$dCData          = $this->admin_settings_model->getBranchDayClosingData(1);
			    $bill_date       = ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);
				$addData   =  $_POST['nt_receipt'];
				$nt_receipt_no = $this->$model->getNonTagReceiptNum();
				$lottedWt = $this->$model->getLottedNotTagWt($addData['id_lot'],$addData['id_product'],$addData['id_design'],$addData['id_sub_design']);
				$receiptedNontagWt = $this->$model->getReceiptedNontagWt($addData['id_lot'],$addData['id_product'],$addData['id_design'],$addData['id_sub_design']);
				if(($receiptedNontagWt + $addData['nt_grswt']) >= $lottedWt['gross_wt'])
				{
					$lot['tag_status']=1;
					$tagInsert= $this->$model->updateData($lot,'id_lot_inward_detail',$lottedWt['id_lot_inward_detail'],'ret_lot_inwards_detail');
				}
				$data = array(
					'lot_id'		=> $addData['id_lot'],
					'id_lot_inward_detail'		=> $addData['id_lot_inward_detail'],
					'nt_receipt_no' => $nt_receipt_no,
					'id_branch'     => $addData['id_branch'],
					'id_section'	=> $addData['id_section'],
					'id_product'    => $addData['id_product'],
					'id_design'     => $addData['id_design'],
					'id_sub_design' => $addData['id_sub_design'],
					'pcs'        	=> ($addData['nt_pcs'] != '' ? $addData['nt_pcs'] : NULL),
					'grs_wt'        => ($addData['nt_grswt'] != '' ? $addData['nt_grswt'] : 0),
					'less_wt'       => ($addData['nt_lesswt'] != '' ? $addData['nt_lesswt'] : 0),
					'net_wt'        => ($addData['nt_netwt'] != '' ? $addData['nt_netwt'] : 0),
					'remark'        => ($addData['remark']!='' ? $addData['remark'] : NULL),
					'created_on'    => date("Y-m-d H:i:s"),
					'created_by'    => $this->session->userdata('uid'),
				);
				$this->db->trans_begin();
			    $insOrder = $this->$model->insertData($data,'ret_nontag_receipt');
				if($insOrder)
				{
					$existData=array('id_section'=>$addData['id_section'],'id_product'=>$addData['id_product'],'id_design'=>$addData['id_design'],'id_branch'=>1,'id_sub_design'=>$addData['id_sub_design']);

					$isExist = $this->$model->checkNonTagItemExist($existData);
					if($isExist['status'] == TRUE)
					{
						$nt_data = array(
							'id_nontag_item' => $isExist['id_nontag_item'],
							'no_of_piece'	=> ($addData['nt_pcs']!='' ? $addData['nt_pcs']:0),
							'gross_wt'		=> $addData['nt_grswt'],
							'net_wt'		=> $addData['nt_netwt'],
							'less_wt'		=> 0,
							'updated_by'	=> $this->session->userdata('uid'),
							'updated_on'	=> date('Y-m-d H:i:s'),
						);
						$update_nt = $this->$model->updateNTData($nt_data,'+');
					}
					else
					{
						$non_tag_data_insert=array(
							'branch'        => 1,
							'id_section'    => $addData['id_section'],
							'product'	    => ($addData['id_product']!='' ? $addData['id_product']:NULL),
							'design'	    => ($addData['id_design']!='' ? $addData['id_design']:NULL),
							'id_sub_design'	=> ($addData['id_sub_design']!='' ? $addData['id_sub_design']:NULL),
							'no_of_piece'	=> ($addData['nt_pcs']!='' ? $addData['nt_pcs']:0),
							'gross_wt'		=> ($addData['nt_grswt']!='' ? $addData['nt_grswt']:NULL),
							'net_wt'		=> ($addData['nt_netwt']!=''? $addData['nt_netwt']:NULL),
							'created_on'    => date("Y-m-d H:i:s"),
						'created_by'    => $this->session->userdata('uid')
						);
						$ins_id = $this->$model->insertData($non_tag_data_insert,'ret_nontag_item');
					}

					$non_tag_data=array(
						'product'	    => ($addData['id_product']!='' ? $addData['id_product']:NULL),
						'design'	    => ($addData['id_design']!='' ? $addData['id_design']:NULL),
						'id_sub_design'	=> ($addData['id_sub_design']!='' ? $addData['id_sub_design']:NULL),
						'no_of_piece'	=> ($addData['nt_pcs']!='' ? $addData['nt_pcs']:0),
						'gross_wt'		=> ($addData['nt_grswt']!='' ? $addData['nt_grswt']:NULL),
						'net_wt'		=> ($addData['nt_netwt']!=''? $addData['nt_netwt']:NULL),
						'from_branch'	=> NULL,
						'to_branch'	    => 1,
						'status'	    => 0,
						'date'          => $bill_date,
						'created_on'    => date("Y-m-d H:i:s"),
						'created_by'    => $this->session->userdata('uid')
					);
					$this->$model->insertData($non_tag_data,'ret_nontag_item_log');

					$item_non_tag_data=array(
						'to_section'	=> $addData['id_section'],
						'from_section'	=> NULL,
						'product'	    => ($addData['id_product']!='' ? $addData['id_product']:NULL),
						'design'	    => ($addData['id_design']!='' ? $addData['id_design']:NULL),
						'id_sub_design'	=> ($addData['id_sub_design']!='' ? $addData['id_sub_design']:NULL),
						'no_of_piece'	=> ($addData['nt_pcs']!='' ? $addData['nt_pcs']:0),
						'gross_wt'		=> ($addData['nt_grswt']!='' ? $addData['nt_grswt']:NULL),
						'net_wt'		=> ($addData['nt_netwt']!=''? $addData['nt_netwt']:NULL),
						'from_branch'	=> NULL,
						'to_branch'	    => 1,
						'status'	    => 0,
						'date'          => $bill_date,
						'created_on'    => date("Y-m-d H:i:s"),
						'created_by'    => $this->session->userdata('uid')
						);
					$this->$model->insertData($item_non_tag_data,'ret_section_nontag_item_log');
				}
			    if($this->db->trans_status()===TRUE)
    			{
    				$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'NonTag Receipt',

						'operation'     => 'Add',

						'record'        =>  $insOrder,

						'remark'        => 'NonTag Receipt added successfully'

						);

						$this->log_model->log_detail('insert','',$log_data);

    				$this->session->set_flashdata('chit_alert',array('message'=>'NonTag Receipt Added Successfully','class'=>'success','title'=>'NonTag Receipt'));
    				$return_data=array('status'=>TRUE,'message'=>'NonTag Receipt Added Successfully..');
    			}
    			else
    			{

    			    echo $this->db->last_query();exit;
    				$this->db->trans_rollback();
    				$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'NonTag Receipt'));
    				$return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');
    			}
    			echo json_encode($return_data);
			break;
			case 'ajax':
				$list=$this->$model->get_nontag_receiptedList($_POST);
			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/nontag_receipt/list');
		        $data = array(
								'list'  => $list,
								'access'=> $access
							);
				echo json_encode($data);
			break;
		}
	}
	function get_lot_nontag_details()
	{
		$model=	self::RET_PUR_ORDER_MODEL;
        $data= $this->$model->get_lot_nontag_details($_POST);
        echo json_encode($data);
	}
	function getNonTagLotItemDetails()
	{
		$model=	self::RET_PUR_ORDER_MODEL;
        $data= $this->$model->getNonTagLotItemDetails($_POST);
        echo json_encode($data);
	}
	// Nontag Lot Receipt

	function isEmptySetDefault($value,$default){

	    if($value != '' &&  $value != NULL &&  $value != 'null' && $value != 'undefined'  ){
	        return $value;
	    }else{
	        return $default;
	    }

	}

	function get_tds_percent()
	{

		$model=	self::RET_PUR_ORDER_MODEL;

		$id_kaigar = $_POST['id_karigar'];

		$fin_year = $this->$model->get_FinancialYear();

		// print_r($fin_year);exit;

		$data['settings'] = $this->$model->get_ret_settings('tds_purchase_limit');

        $data['tds_details']=$this->$model->get_karigar_wise_tds_percent($id_kaigar,$fin_year['fin_year_code']);

		// print_r($this->db->last_query());exit;

		echo json_encode($data);

	}


    function credit_debit_entry($type = "",$id="",$status="")
	{
		$model=	self::RET_PUR_ORDER_MODEL;

		$SETT_MOD = self::SETT_MOD;

		switch($type)
		{

			case 'list':
				$data['main_content'] = self::VIEW_FOLDER .'credit_entry/list';
				$data['access'] = $this->$SETT_MOD ->get_access('admin_ret_purchase/credit_debit_entry/list');
				$this->db->where('status',1);
				$data['cr_dr_ledger'] = $this->db->get('ret_crdr_ledger')->result_array();
				$data['karigar_list'] = $this->db->select('id_karigar, firstname, code_karigar')->get('ret_karigar')->result_array();
				$this->load->view('layout/template', $data);

			break;

			case 'add':
				$this->db->where('status',1);
				$data['cr_dr_ledger'] = $this->db->get('ret_crdr_ledger')->result_array();
				$data['main_content'] = self::VIEW_FOLDER . 'credit_entry/form';
				$this->load->view('layout/template', $data);
			break;

			case 'edit':
				$this->db->where('status',1);
				$data['cr_dr_ledger'] = $this->db->get('ret_crdr_ledger')->result_array();
				$data['credit'] = $this->$model->get_credit_debit($id);
				$data['main_content'] = self::VIEW_FOLDER . 'credit_entry/form';
				$this->load->view('layout/template', $data);
			break;

			case 'cd_edit':
				$data['credit'] = $this->$model->get_credit_debit($id);
				echo json_encode($data);
			break;
			case 'save':
				$response_data=array();
				if($_POST['transactionin'] == '1')
					$transtype = $_POST['transtype'];
				else
					$transtype = $_POST['wtranstype'];

				$bill_no = $this->$model->code_number_generator();   //Bill Number Generate
				$data = array(
					'transbillno'     => $bill_no,
					'po_id'			=> (!empty($_POST['po_id'])? $_POST['po_id']:NULL),
					'id_supplier_rate_cut' => (!empty($_POST['ratecut'])? $_POST['ratecut']:NULL),
					'supid'          => ($_POST['karigar']!=''? $_POST['karigar']:NULL),
					'accountto'		 => ($_POST['accountto']!=''? $_POST['accountto']:NULL),
					'transtype'     => $transtype,
					'transamount'	 => ($_POST['transamount']!=''? $_POST['transamount']:NULL),
					'weight'	 => (!empty($_POST['transweight'])? $_POST['transweight']:NULL),
					'id_cr_dr_ledger' => (isset($_POST['id_cr_dr_ledger']) && $_POST['id_cr_dr_ledger']!='' ? $_POST['id_cr_dr_ledger']:NULL),
					'naration'       => ($_POST['naration']!=''? $_POST['naration']:NULL),
					'createdon'     => date("Y-m-d H:i:s"),
					'createdby'     => $this->session->userdata('uid'),
				);
				$this->db->trans_begin();
			    $insOrder = $this->$model->insertData($data,'ret_crdr_note');

			    if($this->db->trans_status()===TRUE)
    			{
    				$this->db->trans_commit();

					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Credit/Debit Entry',

						'operation'     => 'Add',

						'record'        =>  $insOrder,

						'remark'        => 'Credit/Debit Entry added successfully'

						);

					$this->log_model->log_detail('insert','',$log_data);
    				$this->session->set_flashdata('chit_alert',array('message'=>'Credit/Debit Entry Added Successfully','class'=>'success','title'=>'Credit/Debit Entry'));
    				$return_data=array('status'=>TRUE,'message'=>'Credit/Debit Entry Added Successfully..');
    			}
    			else
    			{

    			    echo $this->db->last_query();exit;
    				$this->db->trans_rollback();
    				$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Credit/Debit Entry'));
    				$return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');
    			}
    			echo json_encode($return_data);
			break;

			case 'credit_debit_acknolodgement':

			    $set_model = "admin_settings_model";

        		$data['comp_details']   = $this->$set_model->get_company();

        		$data['paymentdetails'] = $this->$model->get_credit_debit_detail($id);

				// $data['payment'] = $this->$model->get_po_paid_payment($id);
        		// echo "<pre>"; print_r($data);exit;

        		$this->load->helper(array('dompdf', 'file'));

        	        $dompdf = new DOMPDF();

        			$html = $this->load->view(self::VIEW_FOLDER.'credit_entry/print', $data,true);

        			$dompdf->load_html($html);

        			$dompdf->set_paper('A4', "portriat" );

        			$dompdf->render();

        			while(ob_get_level()) ob_end_clean();
        			$dompdf->stream("VendorAck.pdf",array('Attachment'=>0));

			break;

			case 'cancel_crdr_entry':

			    $ho              =  $this->$model->get_headOffice();

        		$dCData          =  $this->admin_settings_model->getBranchDayClosingData($ho['id_branch']);

        		$bill_date       =  ($dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : $dCData['entry_date']);

        		$crdrid          = $_POST['crdrid'];

			    $updData=array(

                    'crdr_status'        => 2,

                    'cancel_reason'     => $_POST['cancel_reason'],

                    'cancelled_date'    => $bill_date,

                    'cancelled_by'      => $this->session->userdata('uid')

                    );

                    $this->db->trans_begin();

                    $ret_status = $this->$model->updateData($updData,'crdrid',$crdrid,'ret_crdr_note');

					// print_r($this->db->last_query());exit;

                    if($this->db->trans_status()===TRUE)

                    {

                        $this->db->trans_commit();

                        $this->session->set_flashdata('chit_alert',array('message'=>'Credit/Debit Entry Cancelled Successfully','class'=>'success','title'=>'Credit/Debit Entry'));

                        $return_data=array('status'=>TRUE,'message'=>'Order Instructions Added Successfully..');

                    }

                    else

                    {

                        echo $this->db->last_query();exit;

                        $this->db->trans_rollback();

                        $this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Credit/Debit Entry'));

                        $return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

                    }

                echo json_encode($return_data);

			break;

			case 'update':
				$response_data=array();
				$data = array(
					'supid'          => ($_POST['karigar']!=''? $_POST['karigar']:NULL),
					'accountto'		 => ($_POST['accountto']!=''? $_POST['accountto']:NULL),
					'transtype'     => ($_POST['transtype']!=''? $_POST['transtype']:NULL),
					'transamount'	 => ($_POST['transamount']!=''? $_POST['transamount']:NULL),
					'weight'	 => ($_POST['transweight']!=''? $_POST['transweight']:NULL),
					'id_cr_dr_ledger' => (isset($_POST['id_cr_dr_ledger']) && $_POST['id_cr_dr_ledger']!='' ? $_POST['id_cr_dr_ledger']:NULL),
					'naration'       => ($_POST['naration']!=''? $_POST['naration']:NULL),
					'updatedon'     => date("Y-m-d H:i:s"),
					'updatedby'     => $this->session->userdata('uid'),
				);
				$this->db->trans_begin();
            	$update_status = $this->$model->updateData($data,'crdrid',$_POST['crdrid'],'ret_crdr_note');
				// print_r($this->db->last_query());exit;
			    if($this->db->trans_status()===TRUE)
    			{
    				$this->db->trans_commit();
					$log_data = array(

						'id_log'        => $this->session->userdata('id_log'),

						'event_date'    => date("Y-m-d H:i:s"),

						'module'        => 'Credit/Debit Entry',

						'operation'     => 'Update',

						'record'        => $update_status ,

						'remark'        => 'Credit/Debit Entry Updated successfully'

						);

			        $this->log_model->log_detail('insert','',$log_data);
    				$this->session->set_flashdata('chit_alert',array('message'=>'Credit/Debit Entry Added Successfully','class'=>'success','title'=>'Credit/Debit Entry'));
    				$return_data=array('status'=>TRUE,'message'=>'Credit/Debit Entry Updated Successfully..');
    			}
    			else
    			{

    			    echo $this->db->last_query();exit;
    				$this->db->trans_rollback();
    				$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Credit/Debit Entry'));
    				$return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');
    			}
    			echo json_encode($return_data);
			break;

			case 'ajax':
				$list=$this->$model->get_creditdebit_entry($_POST);
			  	$access = $this->admin_settings_model->get_access('admin_ret_purchase/credit_debit_entry/list');
		        $data = array(
								'list'  => $list,
								'access'=> $access
							);
				echo json_encode($data);
			break;
		}

	}


	function get_img_by_grn_id()

    {

	    $model=	self::RET_PUR_ORDER_MODEL;

        $data = $this->$model->get_grn_images($_POST['grn_id']);

        echo json_encode($data);

    }

	// Retagging report
	function retagging_report($type="")
	{
		$model=	self::RET_PUR_ORDER_MODEL;
		switch($type)
		{
			case 'list':
					$data['main_content'] = self::VIEW_FOLDER.'reports/retagging';
        			$this->load->view('layout/template', $data);
				break;
			case 'ajax':
					$list=$this->$model->get_retagging_details($_POST);
				  	$access = $this->admin_settings_model->get_access('admin_ret_reports/retagging/list');
			        $data = array(
		        					'list'  => $list,
									'access'=> $access
		        				 );
					echo json_encode($data);
				break;
		}
    }

		// Retagging report
		function metal_issue_receipt($type="")
		{
			$model=	self::RET_PUR_ORDER_MODEL;
			switch($type)
			{

				case 'add':
						$data['main_content'] = self::VIEW_FOLDER.'metalissue/receipt_form';
						$this->load->view('layout/template', $data);
					break;
				case 'get_metal_issue_details':
					$list=$this->$model->get_metal_issue_details($_POST);
						$access = $this->admin_settings_model->get_access('admin_ret_reports/retagging/list');
					$data = array(
									'list'  => $list,
									'access'=> $access
									);
					echo json_encode($data);
				break;
				case 'getKarigarIssueRefNo':
					$model=	self::RET_PUR_ORDER_MODEL;

					$data= $this->$model->getKarigarIssueRef_No($_POST);

					echo json_encode($data);
				break;
				case 'save':

					$receipt_date = ($_POST['receipt_date']!='' ? date("Y-m-d", strtotime($_POST['receipt_date']))." ".date("H:i:s") :  date("Y-m-d H:i:s"));

					$insData=array(

								  'id_metal_issue'=>$_POST['id_metal_issue'],

								  'receipt_date' => $receipt_date,

								  'created_by' => $this->session->userdata('uid')

								  );

					 $this->db->trans_begin();

					 $insId = $this->$model->insertData($insData,'ret_metai_issue_receipt');

					 foreach($_POST['issue'] as $key => $val)

					 {

						 $order_details=$this->$model->get_order_details($val['issue_met_id']);

						 

						 $metal_data=array(

							 'id_issue_receipt'      =>$insId,

							 'issue_met_id'          =>$val['issue_met_id'],

							 'product_id'          =>$val['id_product'],

							 'design_id'       =>$val['id_design'],

							 'sub_design_id'   =>$val['id_sub_design'],

							 'weight'           =>$val['receipt_gwt'],

							 'net_wt'           =>$val['receipt_nwt'],

							 'less_wt'           =>$val['receipt_lwt'],

							 'piece'           =>$val['receipt_pcs'],


						 );

						$status=$this->$model->insertData($metal_data,'ret_metai_issue_receipt_details');

						
						
						$stones=[];

						$stone_details = json_decode($val['stone_details']);

					   if(sizeof($stone_details)>0)

					   {

						   

						   foreach($stone_details as $stone)

						   {

							   $stones=array(

								   'id_receipt_details'=>$status,

								   'stone_pcs'	     => $stone->stone_pcs,

								   'stone_wt'		=> $stone->stone_wt,

								   'uom_id'		=> $stone->stone_uom_id,

								   'stone_id'		=> $stone->stone_id,

								   'stone_quality_id'=> $stone->stone_quality_id,

								   'is_apply_in_lwt '=> $stone->show_in_lwt,

								   'stone_cal_type  '=> $stone->stone_cal_type,

								   'rate_per_gram'=> $stone->stone_rate,

								   'price'=> $stone->stone_price,

							   );

							  // echo "<pre>";print_r($stones);exit;

							  $this->$model->insertData($stones,'ret_metai_issue_receipt_stone_details');

						   }



					   }


						// $received=$this->$model->get_issue_received_wt($val['issue_met_id']);

						// echo $received;

						// print_r($order_details);exit;


						//  if($order_details['weight']<=$received){
 
							$updData=array(
								"completed_weight"=> $val['receipt_gwt'],
								"orderstatus" 	 =>  4,
								);
								$this->db->trans_begin();
								$status=$this->$model->updateData($updData,'id_orderdetails',$order_details['id_orderdetails'],'customerorderdetails');


						//  }


					 }




					if($this->db->trans_status()===TRUE)

					{

						$this->db->trans_commit();

						  $log_data = array(

							'id_log'        => $this->session->userdata('id_log'),

							'event_date'    => date("Y-m-d H:i:s"),

							'module'        => 'ORDER DESCRIPTION',

							'operation'     => 'Add',

							'record'        => $insOrder,

							'remark'        => 'Order Description added successfully'

							);

						$this->log_model->log_detail('insert','',$log_data);

						$this->session->set_flashdata('chit_alert',array('message'=>'Order Instructions Added Successfully','class'=>'success','title'=>'Order Instructions'));

						$return_data=array('status'=>TRUE,'message'=>'Receipt Added Successfully..');

					}

					else

					{

						echo $this->db->last_query();exit;

						$this->db->trans_rollback();

						$this->session->set_flashdata('chit_alert',array('message'=>'Unable to proceed the requested process','class'=>'danger','title'=>'Order Instructions'));

						$return_data=array('status'=>FALSE,'message'=>'Unable to proceed the requested process');

					}

					echo json_encode($return_data);

				break;

			}
		}


	function delete_supplier_entry_item()
	{

		$model=	self::RET_PUR_ORDER_MODEL;
		$po_item_id = $this->input->post('po_item_id');

		$this->db->trans_begin();

		$this->$model->deleteData('po_item_id',$po_item_id,'ret_purchase_order_items');
		$this->$model->deleteData('pur_po_item_id',$po_item_id,'ret_purchase_other_charges');
		$this->$model->deleteData('po_item_id',$po_item_id,'ret_po_stone_items');

		

		if($this->db->trans_status()===TRUE)
		{
			$this->db->trans_commit();

			$return_data =  array('status'=>true,'message' => 'Supplier entry deleted successfully','class' => 'success','title'=>'Delete Supplier Entry');

			echo json_encode($return_data);
		}
		else
		{
			$this->db->trans_rollback();

			$return_data = array('status'=>false,'message' => 'Unable to proceed your request','class' => 'danger','title'=>'Delete Supplier Entry');
		
			echo json_encode($return_data);
		}
	}


	function qc_issue_receipt_cancel()
	{
		$model =	self::RET_PUR_ORDER_MODEL;
		$qc_issue_id = $this->input->post('qc_issue_id');
		$id_reason = $this->input->post('id_reason');


		$updData = array(

			'qc_status'    => 2, // 2 = Cancelled — excludes from stock calculations

			'id_reason' => $id_reason,

			'cancelled_on' => date("Y-m-d H:i:s"),

			'cancelled_by' => $this->session->userdata('uid')

		);

		$this->db->trans_begin();

		$id_order_des = $this->$model->updateData($updData, 'qc_process_id', $qc_issue_id, 'ret_po_qc_issue_process');

		if ($this->db->trans_status() === TRUE) {

			$this->db->trans_commit();

			$this->session->set_flashdata('chit_alert', array('message' => 'Cancelled Successfully', 'class' => 'success', 'title' => 'Cancel QC Issue/Receipt'));

			$return_data = array('status' => TRUE, 'message' => 'Cancelled Successfully..');
		} else {



			echo $this->db->last_query();
			exit;

			$this->db->trans_rollback();

			$this->session->set_flashdata('chit_alert', array('message' => 'Unable to proceed the requested process', 'class' => 'danger', 'title' => 'Cancel QC Issue/Receipt'));

			$return_data = array('status' => FALSE, 'message' => 'Unable to proceed the requested process');
		}

		echo json_encode($return_data);
	}

	function karigar_pan_available()
	{

		$model =	self::RET_PUR_ORDER_MODEL;

		$data = $this->$model->karigar_pan_available($_POST['pan_number'], $_POST['id_karigar']);

		echo json_encode($data);
	}

	function karigar_gst_available()
	{

		$model =	self::RET_PUR_ORDER_MODEL;

		$data = $this->$model->karigar_gst_available($_POST['gst_number'], $_POST['id_karigar']);

		echo json_encode($data);
	}

	function karigar_aadhar_available()
	{

		$model =	self::RET_PUR_ORDER_MODEL;

		$data = $this->$model->karigar_aadhar_available($_POST['aadhar_no'], $_POST['id_karigar']);

		echo json_encode($data);
	}
	// Pending payment PO Bills Report
	function getPending_payment_po_bills()
	{
		$model=	self::RET_PUR_ORDER_MODEL;
		// echo "<pre>";print_r($_POST);exit;
		$list = $this->$model->getPending_payment_po_bills($_POST);
		echo json_encode($list);
	}

	function getPending_payment_po_bills_for_payment()
	{
		$model=	self::RET_PUR_ORDER_MODEL;

		/* The payment screen fires this whenever Bill Type changes, which can happen
		   before a supplier has been picked. get_karigar_details() interpolates the id
		   straight into the WHERE clause, so an empty value produced
		   "WHERE k.id_karigar=" — a SQL syntax error surfacing as a 500 with
		   "Unable to proceed the requested process". Return empty structures instead;
		   the caller already renders an empty list for them. */
		$id_karigar = isset($_POST['id_karigar']) ? (int)$_POST['id_karigar'] : 0;

		if ($id_karigar <= 0) {
			echo(json_encode(array())."[~s~]".json_encode(new stdClass()));
			return;
		}

		$_POST['id_karigar'] = $id_karigar;

		$list = $this->$model->getPending_payment_po_bills_for_payment($_POST);
		$kar_det = $this->$model->get_karigar_details($id_karigar);
		echo(json_encode($list)."[~s~]".json_encode($kar_det));
	}

	function get_crdr_debit_entries_for_payment()
	{
		$model=	self::RET_PUR_ORDER_MODEL;
		$list = $this->$model->get_crdr_debit_entries_without_po($_POST);
		echo json_encode($list);
	}
	
	function get_pending_po_bills_after_tagging_without_pcs_with_weight()
	{
		$model=	self::RET_PUR_ORDER_MODEL;
		// echo "<pre>";print_r($_POST);exit;
		$list = $this->$model->get_pending_po_bills_after_tagging_without_pcs_with_weight($_POST);
		echo json_encode($list);
	}
}


?>