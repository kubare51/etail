<?php
if( ! defined('BASEPATH')) exit('No direct script access allowed');

class Digigold_modal extends CI_Model
{
    function __construct()
    {      
        parent::__construct();
        $this->load->model('payment_model');
	}
	
	function digiGold_settings(){
	    $sql = $this->db->query("SELECT s.id_scheme,s.scheme_name,s.code,s.sync_scheme_code,s.id_metal,s.id_classification,s.scheme_type,s.total_installments,s.min_chance,
	                            s.max_chance,s.payment_chances,s.interest,s.min_amount,s.max_amount,s.pay_duration,s.wgt_convert,s.maturity_days,s.flx_denomintion,
	                            s.flexible_sch_type,s.is_digi,s.total_days_to_pay,cls.classification_name,cls.description,cs.is_branchwise_rate,s.digi_target,s.digi_target_split_unit,
	                            if(s.digi_target_split_unit = 1,'1',if(s.digi_target_split_unit = 2, '7',if(s.digi_target_split_unit = 3, '30','0'))) as digi_target_split_value,
	                            if(s.digi_target_split_unit = 1,'Every Day',if(s.digi_target_split_unit = 2, 'Every Week',if(s.digi_target_split_unit = 3, 'EVery Month',''))) as digi_target_split_term,
	                            s.flx_denomintion,s.description as know_more_description
	                            FROM scheme s
	                            LEFT JOIN sch_classify cls ON cls.id_classification = s.id_classification
	                            JOIN chit_settings cs
	                            WHERE s.is_digi = 1 and s.active = 1");
        $data = $sql->row_array();
        return $data;
	}
	
	function digiGold_account($id_customer, $id_scheme_account = '', $payment_date = ''){
        $scheme_account_cond = ($id_scheme_account != '') ? " AND sa.id_scheme_account = ".$id_scheme_account : "";
        $target_date = ($payment_date != '') ? "date('".date('Y-m-d', strtotime($payment_date))."')" : "CURDATE()";

  	    $sql = $this->db->query("SELECT s.is_digi,sa.id_scheme_account,sa.id_scheme,date_format(sa.start_date, '%d-%b-%Y') as start_date,
  	                            IF(".$target_date." = date(sa.start_date), 1, DATEDIFF(".$target_date.",date(sa.start_date))) as date_difference,".$target_date." as cur_date, 
  		                        DATE_ADD(date(sa.start_date), INTERVAL s.total_days_to_pay DAY) as allow_pay_till,IFNULL(sa.account_name,c.firstname) as account_name,
  		                        IFNULL(sa.scheme_acc_number,'Not Allocated') as scheme_acc_number,
  		                        COUNT(p.id_payment) as pay_count,sa.id_branch as joined_branch,sa.active, sa.is_closed,
  		                        date_format((DATE_ADD(date(sa.start_date), INTERVAL s.total_days_to_pay DAY)),'%d-%b-%Y') as maturity_date,
  		                        
  		                        IFNULL(SUM(p.payment_amount),0) as total_paid_amount,
  		                        IFNULL(SUM(p.metal_weight),0) as total_paid_weight,
  		                        IFNULL(SUM(p.saved_benefits),0) as total_saved_benefits,
  		                        IFNULL((SUM(p.metal_weight) + SUM(p.saved_benefits)),0) as total_saved, 
  		                        if(sa.dg_target_value_wgt > 0,sa.dg_target_value_wgt,'Target not set') entered_target,sa.dg_target_value_wgt
  		                        
                                 FROM scheme_account sa
                                  LEFT JOIN customer c ON (c.id_customer = sa.id_customer)
                                  LEFT JOIN scheme s ON (s.id_scheme = sa.id_scheme)
                                  LEFT JOIN payment p ON (p.id_scheme_account = sa.id_scheme_account and p.payment_status = 1)
                                 WHERE sa.id_customer =".$id_customer."  AND s.is_digi = 1 AND sa.is_closed = 0 AND sa.active = 1 ".$scheme_account_cond."
                                  GROUP BY p.id_scheme_account
  	                            ");
          $data = $sql->row_array();
          return $data;
  	}
	
	
	function digi_payDues($acc){
	    $result = [];
	    $date = date('Y-m-d');
	    $digi = $this->digiGold_settings();
	    $metal_rates=$this->payment_model->get_metalrate_by_branch($acc['joined_branch'], $digi['id_metal'], $digi['id_purity']);
	        
	    
	    //$digi_payments = $this->db->query('SELECT ')->row_array();
	    
	    if($date <= $acc['allow_pay_till']){
	        $result = array('digi_allow_pay' => 'Y',
	                        'payable' => $digi['min_amount'],
	                        'min_amount' => $digi['min_amount'],
	                        'max_amount' => $digi['max_amount'],
	                        'min_weight' => number_format(($digi['min_amount'] / $metal_rates['goldrate_22ct']),3),
	                        'max_weight' => number_format(($digi['max_amount'] / $metal_rates['goldrate_22ct']),3),
	                        'flx_denomination' => $digi['flx_denomintion'],
	                        'wgt_convert'      => $digi['wgt_convert'],
	                        /*'total_paid_amount' => $acc['total_paid_amount'],
	                        'total_paid_weight' => $acc['total_paid_weight'],
	                        'total_benefits_earned' => $acc['total_saved_benefits'],
	                        'total_saved'   => number_format(($acc['total_paid_weight'] + $acc['total_saved_benefits']),3),*/
	                        'metal_rate'                => $metal_rates['goldrate_22ct'],
	                        'allow_benefit_calc' => $digi['interest']
	                        );
	        
	    }else{
	        $result = array('digi_allow_pay' => 'N',
	                        'payable' => $digi['min_amount'],
	                        'min_amount' => $digi['min_amount'],
	                        'max_amount' => $digi['max_amount'],
	                        'min_weight' => number_format(($digi['min_amount'] / $metal_rates['goldrate_22ct']),3),
	                        'max_weight' => number_format(($digi['max_amount'] / $metal_rates['goldrate_22ct']),3),
	                        'flx_denomination' => $digi['flx_denomintion'],
	                        'wgt_convert'      => $digi['wgt_convert'],
	                        /*'total_paid_amount' => $acc['total_paid_amount'],
	                        'total_paid_weight' => $acc['total_paid_weight'],
	                        'total_benefits_earned' => $acc['total_saved_benefits'],
	                        'total_saved'   => number_format(($acc['total_paid_weight'] + $acc['total_saved_benefits']),3),*/
	                        'metal_rate'                => $metal_rates['goldrate_22ct'],
	                        'allow_benefit_calc' => $digi['interest']
	                        );
	        //$result = array('digi_allow_pay' => 'N');
	    }
	    return $result;
	}
	
	function get_digi_benefit($res){

	   /*  $sql_int = $this->db->query("SELECT interest_type,interest_value, IF(interest_type = 0,'%','INR') as int_symbol 
				FROM `scheme_benefit_deduct_settings` 
				WHERE ('".$res['date_difference']."' BETWEEN installment_from AND installment_to) AND id_scheme=".$res['id_scheme']."
    			");
    	return $sql_int->row_array(); */	
		
		$sql_int = $this->db->query("SELECT sbd.interest_type,sbd.interest_value, IF(sbd.interest_type = 0,'%','INR') as int_symbol,sbd.commodity,m.metal   
				FROM `scheme_benefit_deduct_settings` sbd
				left join metal m on m.id_metal = sbd.commodity 
				WHERE ('" . $res['date_difference'] . "' BETWEEN sbd.installment_from AND sbd.installment_to) AND sbd.id_scheme=" . $res['id_scheme'] . "
    			");
		return $sql_int->result_array();
    			
	}
	
	function getCusDigiData($data){
	    $result = [];
	    $digi = [];
	    $digi_account = [];
	    $digi_account['paydues'] = [];
	    $digi_account['benefit_calc'] = [];
	    
	    
	    
	    //check digi gold scheme available...
	    $digi = $this->digiGold_settings();
	    
	    if(sizeof($digi) > 0){
	        $digi['benefit_chart_data'] = [];
	        $digi['benefit_day_start'] = '';
	        $digi['benefit_day_end'] = '';
	        $digi['benefit_day_current'] = '';
	        $digi['benefit_slab_count'] = '';
	        $digi['benefit_progress_percent'] = '';
	        
	        
	        //check customer has active digi gold account...
	        $digi_account = $this->digiGold_account($data['id_customer']);
	        //$metal_rates=$this->mobileapi_model->get_metalrate($digi_account['joined_branch'],$digi['is_branchwise_rate']);
			$metal_rates=$this->payment_model->get_metalrate_by_branch($digi_account['joined_branch'], $digi['id_metal'], $digi['id_purity']);
	        
	        //Benefit chart...
	        $reached_day = (!empty($digi_account['date_difference']) ? $digi_account['date_difference'] : 1 );
            $digi['benefit_chart_data'] = $this->digi_benefit_chart_data($reached_day);  //echo '<pre>';print_r($currentInterest);exit;
            if($digi['interest'] == 1 && sizeof($digi['benefit_chart_data']) > 0){
                $digi['benefit_day_start'] = min(array_column($digi['benefit_chart_data'], 'days_from'));
    	        $digi['benefit_day_end'] = max(array_column($digi['benefit_chart_data'], 'days_to'));
    	        $digi['benefit_day_current'] = $reached_day;
    	        $digi['benefit_slab_count'] = sizeof($digi['benefit_chart_data']);
    	        $benefit_progress_percent = (($digi['benefit_day_current']/$digi['benefit_day_end']) * 100) ; 
    	        $digi['benefit_progress_percent'] = number_format(($benefit_progress_percent > 100 ? 100 : $benefit_progress_percent),2) ;
    	        foreach ($digi['benefit_chart_data'] as $item) {
                    if ($item['is_current'] == 1) {
                        $benefit_percent_current = $item['interest_value'];
                        break; 
                    }
                }
                   //echo '<pre>';print_r($currentInterest);exit;                         
    	        $digi['benefit_percent_current'] = (!empty($benefit_percent_current) ? $benefit_percent_current : 0);
            }

	        
	        if(sizeof($digi_account) > 0){
	            //set target..
	            if($digi['digi_target'] == 1){
	                
	                $max_weight = number_format(($digi['max_amount'] / $metal_rates['goldrate_22ct']),3);
	                $entered_target = ($digi_account['dg_target_value_wgt'] > 0 ? $digi_account['dg_target_value_wgt'] : $max_weight );
	                $dg_target_achieved_percent = number_format((($digi_account['total_paid_weight'] / $entered_target) * 100 ),3);
	                $digi_account['dg_target_achieved_percent'] = number_format(($digi_account['total_paid_weight'] > $entered_target ? 100 : $dg_target_achieved_percent),1);
	                
	                $min_target_wgt = number_format(($digi['min_amount'] / $metal_rates['goldrate_22ct']),3);
	                $digi['min_target_wgt'] = $digi_account['total_paid_weight'] > $min_target_wgt ? $digi_account['total_paid_weight'] : $min_target_wgt;
	                $digi['max_target_wgt'] = $max_weight;
	                $digi['digi_target_split_wgt'] = number_format(($entered_target / ($digi['total_days_to_pay']/$digi['digi_target_split_value'])),3);
	                $digi['digi_target_split_amt'] = number_format(($digi['digi_target_split_wgt'] * $metal_rates['goldrate_22ct']),2);
	                
	            }
	            
	            if($digi_account['active'] == 1 && $date <= $digi_account['allow_pay_till']){
	               $digi_account['ac_status'] =  'Active';
	               $digi_account['ac_status_type'] =  '1';
	            }else if($digi_account['active'] == 1 && $date > $digi_account['allow_pay_till']){
	               $digi_account['ac_status'] =  'Matured';
	               $digi_account['ac_status_type'] =  '2';
	            }else if($digi_account['active'] != 1 && $digi_account['is_closed'] != 1 ){
	               $digi_account['ac_status'] =  'Inactive';
	               $digi_account['ac_status_type'] =  '0';
	            }else if($digi_account['active'] != 1 && $digi_account['is_closed'] == 1 ){
	               $digi_account['ac_status'] =  'Closed';
	               $digi_account['ac_status_type'] =  '3';
	            }else{
	               $digi_account['ac_status'] =  '';
	               $digi_account['ac_status_type'] =  '';
	            }
	            
	            //get payduesdata
	            $digi_account['paydues'] = $this->digi_payDues($digi_account);
	            if($digi['interest'] == 1){
	                $digi_account['benefit_calc'] = $this->get_digi_benefit($digi_account);
	            }
	            $result = array('status' => TRUE , 
	                        'show_digi' => TRUE,
	                        'allow_join' => FALSE,
	                        'msg' => 'Digi Gold Account successfully retrived...',
	                        'sch_data' => $digi,
	                        'acc_data' => $digi_account,
	                        );
	        }else{
	            $result = array('status' => TRUE , 
	                        'show_digi' => TRUE,
	                        'allow_join' => TRUE, 
	                        'msg' => 'You are allowed to join Digi Gold Savings Plan... ',
	                        'sch_data' => $digi,
	                        'acc_data' => []
	                        );
	        }
	        
	    }else{
	        $result = array('status' => FALSE , 
	                        'show_digi' => FALSE,
	                        'allow_join' => FALSE, 
	                        'msg' => 'Digi Gold Savings plan is currently unavailable...Kindly contact admin...',
	                        'sch_data' => [],
	                        'acc_data' => []
	                        );
	    }
	    
	    return $result;
	}
	    
	
    function getdigidata($data)
	{
	    $sql = $this->db->query("SELECT s.is_digi,sa.id_scheme_account,sa.id_scheme,DATEDIFF(CURDATE(),date(sa.start_date)) as date_difference,CURDATE() as cur_date, 
		                        DATE_ADD(date(sa.start_date), INTERVAL s.total_days_to_pay DAY) as allow_pay_till,
		                        COUNT(p.id_payment) as pay_count,sa.id_branch as joined_branch, sa.active
                               FROM scheme_account sa
                                LEFT JOIN customer c ON (c.id_customer = sa.id_customer)
                                LEFT JOIN scheme s ON (s.id_scheme = sa.id_scheme)
                                 LEFT JOIN payment p ON (p.id_scheme_account = sa.id_scheme_account)
                               WHERE sa.id_customer =".$data['id_customer']."  AND s.is_digi = 1 AND sa.is_closed = 0 AND sa.active = 1
                                GROUP BY p.id_scheme_account");
                                
        $res = $sql->row_array();

        
        $sql1 = $this->db->query("SELECT s.id_scheme 
                                FROM scheme s
                                WHERE is_digi = 1");
        $is_digi = $sql1->row_array();
        
        
        
         if($sql1->num_rows() > 0){
            $sch = $this->get_scheme($is_digi['id_scheme'],$data['id_customer']);
         }

        
        
         if($sql->num_rows() == 0){
            //get scheme details
            return array('status' => FALSE,'scheme' => $sch, 'chit' => [],'digiwallet' => []);
        }else{
           //get scheme account details
            $sch_acc = $this->chit_scheme_detail($res['id_scheme_account']);

            
            if($sch_acc['chit']['show_chit_wallet'] == 1){
                
                $sql_int = $this->db->query("SELECT interest_type,interest_value, IF(interest_type = 0,'%','INR') as int_symbol 
				FROM `scheme_benefit_deduct_settings` 
				".($res['id_scheme'] != '' && $res['id_scheme'] != null ? 
				    ($res['restrict_payment'] = 1 ? 'WHERE ('.$res['date_difference'].' BETWEEN installment_from AND installment_to) AND id_scheme='.$res['id_scheme'] : 'WHERE id_scheme='.$res['id_scheme']) 
				    : ('WHERE id_scheme='.$res['id_scheme']))."
    			");
    
                $int = $sql_int->row_array();
                
                $sql_debit = $this->db->query("SELECT deduction_type ,deduction_value,installment_to FROM `scheme_debit_settings` 
	            where ".($res['is_digi']==1 ? "(".$res['date_difference']." BETWEEN installment_from AND installment_to)" :"(installment_from =".$res['paid_installments']." or installment_to =".$res['paid_installments'].")")."
				and id_scheme=".$res['id_scheme']);
				
				$debit = $sql_debit->row_array();
				
                //print_r($this->db->last_query());exit;
                
                if($sql_int->num_rows > 0 ){
                    $sql_tot = $this->db->query("SELECT SUM(p.payment_amount) as total_paid,SUM(p.metal_weight) as saved_wgt,
                    SUM(ROUND((p.metal_weight)*(".$int['interest_value']."/100)*(DATEDIFF(CURDATE(),date(p.date_payment))/365),3)) as total_benefit,
                    CURDATE() as cur_date, CONCAT(".$int['interest_value'].",' %') as interest,COUNT(id_payment) as pay_count,date(sa.start_date) as join_date,
                    DATE_ADD(date(sa.start_date), INTERVAL s.total_days_to_pay DAY) as allow_pay_till,DATEDIFF(DATE_ADD(date(sa.start_date), INTERVAL s.total_days_to_pay DAY),date(p.date_payment)) as date_difference,
                    '' as wallet_text
        			FROM `payment` p  
        			LEFT JOIN scheme_account sa ON (sa.id_scheme_account = p.id_scheme_account)
        			LEFT JOIN scheme s ON (s.id_scheme = sa.id_scheme)			
        			WHERE sa.id_scheme_account = ".$res['id_scheme_account']." and p.payment_status = 1");
                    $digiwallet = $sql_tot->row_array();
                }else{
                    $sql_tot = $this->db->query("SELECT SUM(p.payment_amount) as total_paid,SUM(p.metal_weight) as saved_wgt,
                    '' as total_benefit,CURDATE() as cur_date, '' as interest,COUNT(id_payment) as pay_count,date(sa.start_date) as join_date,
                    DATE_ADD(date(sa.start_date), INTERVAL s.total_days_to_pay DAY) as allow_pay_till,DATEDIFF(CURDATE(),date(p.date_payment)) as date_difference,
                    '' as wallet_text
                   
        			FROM `payment` p  
        			LEFT JOIN scheme_account sa ON (sa.id_scheme_account = p.id_scheme_account)
        			LEFT JOIN scheme s ON (s.id_scheme = sa.id_scheme)			
        			WHERE sa.id_scheme_account = ".$res['id_scheme_account']." and p.payment_status = 1");
                    $digiwallet = $sql_tot->row_array();
                }
                
                if($sql_debit->num_rows > 0 ){
                    $sql_tot = $this->db->query("SELECT SUM(ROUND((p.metal_weight)*(".$debit['deduction_value']."/100)*(DATEDIFF(CURDATE(),date(p.date_payment))/365),3)) as preclose_benefit,
                     CONCAT(".$debit['deduction_value'].",' %') as preclose_interest,
                     CONCAT(date(sa.start_date),' to ',(DATE_ADD(date(sa.start_date), INTERVAL ".$debit['installment_to']." DAY))) AS preclose_date
                     
        			FROM `payment` p  
        			LEFT JOIN scheme_account sa ON (sa.id_scheme_account = p.id_scheme_account)
        			LEFT JOIN scheme s ON (s.id_scheme = sa.id_scheme)			
        			WHERE sa.id_scheme_account = ".$res['id_scheme_account']." and p.payment_status = 1");
                    $digiwallet['preclose_interest'] = $sql_tot->row()->preclose_interest;
                    $digiwallet['preclose_benefit'] = $sql_tot->row()->preclose_benefit;
                    $digiwallet['preclose_date'] = $sql_tot->row()->preclose_date;

                }else{
                    $sql_tot = $this->db->query("SELECT '' as preclose_interest, '' as preclose_benefit, '' as preclose_date
        			FROM `payment` p  
        			LEFT JOIN scheme_account sa ON (sa.id_scheme_account = p.id_scheme_account)
        			LEFT JOIN scheme s ON (s.id_scheme = sa.id_scheme)			
        			WHERE sa.id_scheme_account = ".$res['id_scheme_account']." and p.payment_status = 1");
                     $digiwallet['preclose_interest'] = $sql_tot->row()->preclose_interest;
                    $digiwallet['preclose_benefit'] = $sql_tot->row()->preclose_benefit;
                    $digiwallet['preclose_date'] = $sql_tot->row()->preclose_date;
                }
                
                
                
            }else{
                $digiwallet = [];
            }

           return array('status' => TRUE, 'scheme' => $sch, 'chit' => $sch_acc, 'digiwallet' => $digiwallet);
           
        } 
	   
	}
	
	public function updData($data, $id_field, $id_value, $table)
    {    
	    $edit_flag = 0;
	    $this->db->where($id_field, $id_value);
		$edit_flag = $this->db->update($table,$data);
		return ($edit_flag==1?$id_value:0);
	}
	
	public function digi_benefit_chart_data($reached_day){
	    $response = [];
	    
	    $sql = $this->db->query("SELECT sb.installment_from as days_from ,sb.installment_to as days_to, if(sb.interest_type = 0,'%','INR') as type, sb.interest_value, 
	                                if(".$reached_day." BETWEEN sb.installment_from and sb.installment_to , '1','0') as is_current, 
	                                if(".$reached_day." > sb.installment_to,'Fully Crosed Slab',if(".$reached_day." > sb.installment_from and ".$reached_day." < sb.installment_to, 'Current Slab' ,'Upcoming Slab')) as slab_status
                                FROM `scheme_benefit_deduct_settings` as sb
                                WHERE id_scheme = 13");
        if($sql->num_rows() > 1){
            $response = $sql->result_array();
        }

        return $response;
	}

	/**
	 * Branch-wise Closing report for Digi Gold / Digi Silver schemes.
	 *
	 * For a given report date the report shows, per branch, the PAID (customer's
	 * actual payment) and BENEFIT (slab bonus) movement in Weight (g) and Amount (Rs):
	 *   Opening (= previous day's Closing) + Today + Inward - Outward = Closing
	 *
	 * Inward/Outward come from cross-branch closures: an account whose home branch
	 * (scheme_account.id_branch) differs from the branch it was closed at
	 * (scheme_account.Closing_id_branch). The accumulated balance leaves the home
	 * branch (Outward) and arrives at the closing branch (Inward). Benefit only
	 * transfers when the account has MATURED (closing_date >= maturity_date);
	 * on pre-close the benefit is forfeited operationally (not modelled here).
	 *
	 * @param string $metal_family 'gold' | 'silver'
	 * @param string $report_date  Y-m-d (defaults to today)
	 * @return array
	 */
	function digi_branch_wise_closing($metal_family = 'gold', $report_date = '')
	{
		$date = ($report_date != '') ? date('Y-m-d', strtotime($report_date)) : date('Y-m-d');
		$like = ($metal_family == 'silver') ? 'silver' : 'gold';

		$result = array(
			'report_date' => $date,
			'metal'       => ucfirst($like),
			'branches'    => array(),
			'totals'      => $this->_empty_dg_row(0, 'Total'),
			'has_scheme'  => FALSE,
			'balanced'    => TRUE,
		);

		// Resolve the digi scheme id(s) for this metal family
		$schRows = $this->db->query(
			"SELECT s.id_scheme
			   FROM scheme s
			   JOIN metal m ON m.id_metal = s.id_metal
			  WHERE s.is_digi = 1 AND s.active = 1
			    AND LOWER(m.metal) LIKE '%" . $this->db->escape_like_str($like) . "%'"
		)->result_array();
		$scheme_ids = array_map('intval', array_column($schRows, 'id_scheme'));
		if (empty($scheme_ids)) {
			return $result;
		}
		$result['has_scheme'] = TRUE;
		$in = implode(',', $scheme_ids);

		// Branch name map (home + closing branches may include any active branch)
		$brRows = $this->db->query("SELECT id_branch, name FROM branch ORDER BY id_branch")->result_array();
		$branch_names = array();
		foreach ($brRows as $b) {
			$branch_names[(int) $b['id_branch']] = (trim($b['name']) != '') ? trim($b['name']) : ('BRANCH ' . $b['id_branch']);
		}

		$branches = array();

		// 1) Collection aggregates grouped by HOME branch (scheme_account.id_branch)
		$collect = $this->db->query(
			"SELECT sa.id_branch AS id_branch,
			     SUM(CASE WHEN DATE(p.date_payment) <  '" . $date . "' THEN p.metal_weight     ELSE 0 END) AS o_paid_wt,
			     SUM(CASE WHEN DATE(p.date_payment) <  '" . $date . "' THEN p.payment_amount    ELSE 0 END) AS o_paid_amt,
			     SUM(CASE WHEN DATE(p.date_payment) <  '" . $date . "' THEN p.saved_benefits    ELSE 0 END) AS o_ben_wt,
			     SUM(CASE WHEN DATE(p.date_payment) <  '" . $date . "' THEN p.saved_benefit_amt ELSE 0 END) AS o_ben_amt,
			     SUM(CASE WHEN DATE(p.date_payment) =  '" . $date . "' THEN p.metal_weight     ELSE 0 END) AS t_paid_wt,
			     SUM(CASE WHEN DATE(p.date_payment) =  '" . $date . "' THEN p.payment_amount    ELSE 0 END) AS t_paid_amt,
			     SUM(CASE WHEN DATE(p.date_payment) =  '" . $date . "' THEN p.saved_benefits    ELSE 0 END) AS t_ben_wt,
			     SUM(CASE WHEN DATE(p.date_payment) =  '" . $date . "' THEN p.saved_benefit_amt ELSE 0 END) AS t_ben_amt
			   FROM payment p
			   JOIN scheme_account sa ON sa.id_scheme_account = p.id_scheme_account
			  WHERE p.payment_status = 1
			    AND sa.id_scheme IN (" . $in . ")
			    AND DATE(p.date_payment) <= '" . $date . "'
			  GROUP BY sa.id_branch"
		)->result_array();

		foreach ($collect as $c) {
			$id = (int) $c['id_branch'];
			$row =& $this->_dg_branch($branches, $id, $branch_names);
			$row['paid']['o_wt']  += (float) $c['o_paid_wt'];
			$row['paid']['o_amt'] += (float) $c['o_paid_amt'];
			$row['paid']['t_wt']  += (float) $c['t_paid_wt'];
			$row['paid']['t_amt'] += (float) $c['t_paid_amt'];
			$row['ben']['o_wt']   += (float) $c['o_ben_wt'];
			$row['ben']['o_amt']  += (float) $c['o_ben_amt'];
			$row['ben']['t_wt']   += (float) $c['t_ben_wt'];
			$row['ben']['t_amt']  += (float) $c['t_ben_amt'];
			unset($row);
		}

		// 2) Cross-branch closures up to the report date (accumulated paid + benefit)
		$transfers = $this->db->query(
			"SELECT sa.id_branch AS home_branch, sa.Closing_id_branch AS close_branch,
			     DATE(sa.closing_date) AS cdate,
			     (sa.maturity_date IS NOT NULL AND DATE(sa.closing_date) >= DATE(sa.maturity_date)) AS matured,
			     IFNULL(SUM(p.metal_weight),0)     AS paid_wt,
			     IFNULL(SUM(p.payment_amount),0)    AS paid_amt,
			     IFNULL(SUM(p.saved_benefits),0)    AS ben_wt,
			     IFNULL(SUM(p.saved_benefit_amt),0) AS ben_amt
			   FROM scheme_account sa
			   LEFT JOIN payment p ON (p.id_scheme_account = sa.id_scheme_account AND p.payment_status = 1)
			  WHERE sa.id_scheme IN (" . $in . ")
			    AND sa.is_closed = 1
			    AND sa.Closing_id_branch IS NOT NULL
			    AND sa.Closing_id_branch <> sa.id_branch
			    AND sa.closing_date IS NOT NULL
			    AND DATE(sa.closing_date) <= '" . $date . "'
			  GROUP BY sa.id_scheme_account, sa.id_branch, sa.Closing_id_branch, cdate, matured"
		)->result_array();

		foreach ($transfers as $t) {
			$home    = (int) $t['home_branch'];
			$close   = (int) $t['close_branch'];
			$onDate  = ($t['cdate'] === $date);          // happened on the report date
			$matured = ((int) $t['matured'] === 1);
			$pw = (float) $t['paid_wt'];  $pa = (float) $t['paid_amt'];
			$bw = (float) $t['ben_wt'];   $ba = (float) $t['ben_amt'];

			$hrow =& $this->_dg_branch($branches, $home, $branch_names);
			$crow =& $this->_dg_branch($branches, $close, $branch_names);

			if ($onDate) {
				// Today's movement: Outward at home, Inward at closing branch
				$hrow['paid']['out_wt'] += $pw;  $hrow['paid']['out_amt'] += $pa;
				$crow['paid']['in_wt']  += $pw;  $crow['paid']['in_amt']  += $pa;
				if ($matured) {
					$hrow['ben']['out_wt'] += $bw;  $hrow['ben']['out_amt'] += $ba;
					$crow['ben']['in_wt']  += $bw;  $crow['ben']['in_amt']  += $ba;
				}
			} else {
				// Transfer happened before the report date: already reflected in Opening
				$hrow['paid']['o_wt'] -= $pw;  $hrow['paid']['o_amt'] -= $pa;
				$crow['paid']['o_wt'] += $pw;  $crow['paid']['o_amt'] += $pa;
				if ($matured) {
					$hrow['ben']['o_wt'] -= $bw;  $hrow['ben']['o_amt'] -= $ba;
					$crow['ben']['o_wt'] += $bw;  $crow['ben']['o_amt'] += $ba;
				}
			}
			unset($hrow, $crow);
		}

		// Finalise: Closing = Opening + Today + Inward - Outward; build totals
		$totals = $this->_empty_dg_row(0, 'Total');
		ksort($branches);
		foreach ($branches as $id => $row) {
			foreach (array('paid', 'ben') as $sec) {
				$row[$sec]['c_wt']  = $row[$sec]['o_wt'] + $row[$sec]['t_wt'] + $row[$sec]['in_wt'] - $row[$sec]['out_wt'];
				$row[$sec]['c_amt'] = $row[$sec]['o_amt'] + $row[$sec]['t_amt'] + $row[$sec]['in_amt'] - $row[$sec]['out_amt'];
				foreach ($row[$sec] as $k => $v) {
					if (is_numeric($v)) {
						$totals[$sec][$k] += $v;
					}
				}
			}
			$result['branches'][] = $row;
		}
		$result['totals'] = $totals;

		// Inward must tie to Outward (paid and benefit) - flag if not
		$eps = 0.0005;
		$result['balanced'] =
			abs($totals['paid']['in_wt'] - $totals['paid']['out_wt']) < $eps &&
			abs($totals['paid']['in_amt'] - $totals['paid']['out_amt']) < 0.005 &&
			abs($totals['ben']['in_wt'] - $totals['ben']['out_wt']) < $eps &&
			abs($totals['ben']['in_amt'] - $totals['ben']['out_amt']) < 0.005;

		return $result;
	}

	/** Empty branch row for the digi branch-wise closing report */
	private function _empty_dg_row($id_branch, $name)
	{
		$section = array(
			'o_wt' => 0, 'o_amt' => 0, 't_wt' => 0, 't_amt' => 0,
			'in_wt' => 0, 'in_amt' => 0, 'out_wt' => 0, 'out_amt' => 0,
			'c_wt' => 0, 'c_amt' => 0,
		);
		return array(
			'id_branch' => $id_branch,
			'name'      => $name,
			'paid'      => $section,
			'ben'       => $section,
		);
	}

	/** Get a reference to a branch bucket, creating it on first use */
	private function &_dg_branch(&$branches, $id_branch, $branch_names)
	{
		if (!isset($branches[$id_branch])) {
			$name = isset($branch_names[$id_branch]) ? $branch_names[$id_branch] : ('BRANCH ' . $id_branch);
			$branches[$id_branch] = $this->_empty_dg_row($id_branch, $name);
		}
		return $branches[$id_branch];
	}
	
	
	
	
	
	
	
	
}











