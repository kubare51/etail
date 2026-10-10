<?php

if( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ret_sales_transfer_model extends CI_Model

{

	function __construct()

    {

        parent::__construct();

    }

    // General Functions

    public function insertData($data,$table)

    {

    	$insert_flag = 0;

		$insert_flag = $this->db->insert($table, $data);

		return ($insert_flag == 1 ? $this->db->insert_id(): 0);

	}

	public function updateData($data, $id_field, $id_value, $table)

    {    

	    $edit_flag = 0;

	    $this->db->where($id_field, $id_value);

		$edit_flag = $this->db->update($table,$data);

		return ($edit_flag==1?$id_value:0);

	}	 

	public function deleteData($id_field,$id_value,$table)

    {

        $this->db->where($id_field, $id_value);

        $status= $this->db->delete($table); 

		return $status;

	}

	

	function get_FinancialYear()

	{

		$sql=$this->db->query("SELECT fin_year_code,fin_status,fin_year_name From ret_financial_year");

		return $sql->result_array();

	}

	

	function get_category_details($id_ret_category)

	{

	    $sql = $this->db->query("SELECT c.id_ret_category,c.id_metal,c.name as category_name,mt.metal_code

        FROM ret_category c 

        LEFT JOIN metal mt ON mt.id_metal = c.id_metal where c.id_ret_category=".$id_ret_category."");

	    return $sql->row_array();

	}

	

	/**
	 * Get metal details by ID
	 * @param int|string $id_metal Metal ID
	 * @return array Metal details
	 */
	function get_metal_details($id_metal)
	{
		if (empty($id_metal)) {
			return array();
		}
		$sql = $this->db->query("SELECT * FROM metal where id_metal = ?", array($id_metal));
		return $sql->row_array() ?: array();
	}



	function get_sales_transfer_tag_details($data)

	{

			$sql = $this->db->query("SELECT IFNULL(t.gross_wt,0) as gross_wt,IFNULL(t.net_wt,0) as net_wt,IFNULL(t.less_wt,0) as less_wt,cat.name as category_name,p.pro_id,(t.piece) as piece,p.cat_id,

			cat.name as category_name,t.tag_id,t.tag_code,mt.metal_code,cat.id_metal,t.product_id,t.design_id,t.id_sub_design,t.calculation_based_on,t.purity,p.cat_id,

			IFNULL(lid.rate, t.lot_rate) as purchase_rate,

			IFNULL(NULLIF(poi.item_wastage, 0), IFNULL(NULLIF(lid.wastage_percentage, 0), t.lot_wastage_percentage)) as purchase_va,

			IFNULL(poi.mc_value, IFNULL(lid.making_charge, t.lot_making_charge)) as purchase_mc,

			IFNULL(poi.mc_type, IFNULL(lid.mc_type, t.lot_mc_type)) as purchase_mc_type,

			ROUND(
				(
					CAST(ROUND(COALESCE(t.net_wt, 0), 3) AS DECIMAL(10, 3))
					*
					(
						(
							CAST(ROUND(COALESCE(pur.purity, 0), 2) AS DECIMAL(10, 2)) +
							CAST(ROUND(COALESCE(t.lot_wastage_percentage, 0), 2) AS DECIMAL(10, 2))
						) / 100
					)
				),
			3) AS pure_wt

			FROM  ret_taging t

			Left join ret_lot_inwards l on t.tag_lot_id=l.lot_no

			LEFT JOIN ret_product_master p ON p.pro_id=t.product_id

			LEFT JOIN ret_category cat ON cat.id_ret_category=p.cat_id

			LEFT JOIN metal mt ON mt.id_metal = cat.id_metal

			Left join ret_design_master d on d.design_no=t.design_id

			LEFT JOIN ret_lot_inwards_detail lid ON lid.id_lot_inward_detail = t.id_lot_inward_detail

			LEFT JOIN ret_purchase_order_items poi ON poi.po_item_id = lid.po_item_id

			LEFT JOIN ret_purity pur ON pur.id_purity = t.purity

			WHERE t.tag_status=0  AND t.current_branch=".$data['from_brn']."

			".($data['lotno'] != '' ? ' and t.tag_lot_id='.$data['lotno']: '')." 

			".($data['design_id'] != '' ? ' and design_id='.$data['design_id']: '')." 

			".($data['prodId'] != '' ? ' and t.product_id='.$data['prodId']: '')."

			".($data['tag_code']!='' ? " AND t.tag_code='".$data['tag_code']."'" :'')."

			".($data['old_tag_code']!='' ? " AND t.old_tag_id='".$data['old_tag_code']."'" :'')."

			".($data['cat_id']!='' ? " AND p.cat_id='".$data['cat_id']."'" :'')."

			".($data['id_metal']!='' ? " AND cat.id_metal='".$data['id_metal']."'" :'')."

			");

			//print_r($this->db->last_query());exit;

			return $sql->result_array();

	}

	

	function get_category_tag_details($cat_id,$id_branch)

    {

        $sql=$this->db->query("SELECT t.tag_id,t.product_id,t.design_id,(t.gross_wt),t.net_wt,t.less_wt,t.calculation_based_on,t.purity,t.piece

        FROM `ret_taging` t

        LEFT JOIN ret_product_master pro ON pro.pro_id=t.product_id

        LEFT JOIN ret_category cat ON cat.id_ret_category=pro.cat_id      

        WHERE t.tag_status=0  AND cat.id_ret_category IS NOT NULL and t.current_branch=".$id_branch."

        ".($cat_id!='' ? " AND pro.cat_id='".$cat_id."'" :'')."  GROUP by t.tag_id");

        //print_r($this->db->last_query());exit;

        return $sql->result_array();

    }

    

    function get_sales_trans_approval_tag($data)
	{
        $return_data = array();

        $sql = $this->db->query("SELECT b.bill_no AS bill_no, b.bill_id,
            IFNULL(SUM(dt.piece),0) as piece,
            IFNULL(SUM(dt.gross_wt),0) as gross_wt,
            date_format(b.bill_date,'%d-%m-%Y') as bill_date
        FROM ret_billing b
        LEFT JOIN ret_bill_details dt ON dt.bill_id = b.bill_id
        WHERE b.bill_type = 13
        AND b.bill_status = 1
        AND b.download_date IS NULL
        AND b.fin_year_code = '".$data['fin_year_code']."'
        AND b.bill_no = '".$data['bill_no']."'
        AND b.from_branch = ".$data['from_brn']."
        AND b.to_branch = ".$data['to_brn']."
        GROUP BY b.bill_id");

        $result = $sql->result_array();

        foreach($result as $r)
        {
            $return_data[] = array(
                'bill_no'   => $r['bill_no'],
                'bill_id'   => $r['bill_id'],
                'piece'     => $r['piece'],
                'gross_wt'  => $r['gross_wt'],
                'bill_date' => $r['bill_date'],
                'bill_tags' => $this->get_billed_details($r['bill_id'])
            );
        }

        return $return_data;
	}	



    function get_billed_details($bill_id)

    {

        $sql = $this->db->query("SELECT dt.tag_id,t.tag_code,t.tag_status,dt.piece 

        

        FROM ret_bill_details dt 



        LEFT JOIN ret_taging t on t.tag_id=dt.tag_id

        

        where dt.bill_id=".$bill_id."");



        return $sql->result_array();

    }

    

    function get_branch_details($id_branch)

    {

        $sql = $this->db->query("SELECT * FROM branch where id_branch = ".$id_branch."");

        return $sql->row_array();

    }

    

    function getSalesTrans_Tag($bill_id)

	{

	    $sql=$this->db->query("SELECT d.tag_id

        FROM ret_billing b 

        LEFT JOIN ret_bill_details d ON d.bill_id=b.bill_id

        LEFT JOIN ret_taging t ON t.tag_id=d.tag_id

        WHERE t.tag_status=4 AND b.bill_id=".$bill_id."");

        return $sql->result_array();

	}

	

	

	function get_sales_return_trans_req_tag($data)

	{

	    if($data['is_aganist_bill']==1)
	    {
	        // Fetch ALL item types from the ST bill (Tagged + NT + OG/Purchase)
	        // Groups by salesTransType + category so SRT can return mixed-type bills
	        $sql = $this->db->query("SELECT 
	            IFNULL(dt.salesTransType, 1) as salesTransType,
	            SUM(dt.gross_wt) as gross_wt,
	            SUM(dt.item_cost) as item_cost,
	            b.bill_id,
	            b.sales_ref_no as bill_no,
	            SUM(dt.piece) as piece,
	            CASE 
	                WHEN IFNULL(dt.salesTransType,1) IN (1,4,5) THEN IFNULL(cat.name, 'Tagged')
	                WHEN IFNULL(dt.salesTransType,1) = 2 THEN IFNULL(CONCAT(sec.section_name,' - ',pro.product_name), 'Non-Tagged')
	                WHEN IFNULL(dt.salesTransType,1) = 3 THEN IFNULL(omc.old_metal_cat, 'Old Gold')
	            END as category_name,
	            IFNULL(cat.id_ret_category, 0) as cat_id,
	            date_format(b.bill_date,'%d-%m-%Y') as bill_date
	            FROM ret_billing b 
	            LEFT JOIN ret_bill_details dt ON dt.bill_id = b.bill_id
	            LEFT JOIN ret_taging t ON t.tag_id = dt.tag_id
	            LEFT JOIN ret_product_master pro ON pro.pro_id = COALESCE(t.product_id, dt.product_id)
	            LEFT JOIN ret_category cat ON cat.id_ret_category = pro.cat_id
	            LEFT JOIN ret_section sec ON sec.id_section = t.id_section
	            LEFT JOIN ret_bill_old_metal_sale_details omd ON omd.st_bill_id = b.bill_id AND IFNULL(dt.salesTransType,1) = 3
	            LEFT JOIN ret_old_metal_category omc ON omc.id_old_metal_cat = omd.id_old_metal_category
	            WHERE b.bill_status = 1 
	            AND b.bill_type = 13
	            AND b.fin_year_code = '".$data['fin_year_code']."'
	            AND b.id_branch = ".$data['to_brn']."
	            ".($data['bill_no']!='' ? "AND b.sales_ref_no='".$data['bill_no']."'" :'')."
	            AND (
	                (IFNULL(dt.salesTransType,1) IN (1,4,5) AND t.tag_status = 0 AND t.current_branch = ".$data['from_brn'].")
	                OR (IFNULL(dt.salesTransType,1) = 2)
	                OR (IFNULL(dt.salesTransType,1) = 3)
	            )
	            AND NOT EXISTS (
	                SELECT 1 FROM ret_billing srt 
	                WHERE srt.bill_type = 14 
	                AND srt.bill_status = 1 
	                AND srt.sales_ref_no = b.sales_ref_no
	                AND srt.fin_year_code = b.fin_year_code
	            )
	            GROUP BY IFNULL(dt.salesTransType,1), cat.id_ret_category");

	    }else

	    {

	        $sql = $this->db->query("SELECT 

            dt.bill_det_id,b.bill_id,dt.tag_id,t.tag_code,(t.gross_wt) as gross_wt,(dt.item_cost) as item_cost,b.bill_id,b.bill_no,(t.piece) as piece,cat.name as category_name,cat.id_ret_category as cat_id,date_format(b.bill_date,'%d-%m-%Y') as bill_date

            FROM ret_billing b 

            LEFT JOIN ret_bill_details dt ON dt.bill_id=b.bill_id

            LEFT JOIN ret_taging t ON t.tag_id=dt.tag_id

            Left join ret_lot_inwards l on t.tag_lot_id=l.lot_no

            LEFT JOIN ret_product_master p ON p.pro_id=t.product_id

            LEFT JOIN ret_category cat ON cat.id_ret_category=p.cat_id	

            Left join ret_design_master d on d.design_no=t.design_id

            WHERE (t.tag_status=6) and b.bill_type = 13 and b.bill_status=1  and t.current_branch = ".$data['from_brn']."

            group by dt.tag_id");

	    }

        

    //    echo $this->db->last_query();exit;

        return $sql->result_array();

	}

	

	

	function get_sales_return_req_tag_details($cat_id,$id_branch,$bill_id,$from_branch)

	{

	    $sql=$this->db->query("SELECT 

        dt.bill_det_id,dt.bill_id,dt.tag_id,t.tag_status,dt.status,dt.item_cost

        FROM ret_billing b

        LEFT JOIN ret_bill_details dt ON dt.bill_id=b.bill_id 

        LEFT JOIN ret_taging t ON t.tag_id=dt.tag_id       

        LEFT JOIN ret_product_master pro on pro.pro_id=t.product_id      

        LEFT JOIN ret_category cat ON cat.id_ret_category=pro.cat_id

        WHERE (t.tag_status=0) and t.current_branch = ".$from_branch." and b.bill_status=1 and b.id_branch=".$id_branch."

        ".($bill_id!='' ? " AND b.bill_id=".$bill_id."" :'')."

        ".($cat_id!='' ? " AND cat.id_ret_category='".$cat_id."'" :'')."

        ");

        //print_r($this->db->last_query());exit;

        return $sql->result_array();

	}

	

	function getBillId($to_brn,$sales_bill_no,$fin_year_code)

	{

		$sql = $this->db->query("SELECT bill_id from ret_billing 

		WHERE id_branch=".$to_brn." AND sales_ref_no='".$sales_bill_no."' and 

		fin_year_code=".$fin_year_code." " );

	// print_r($this->db->last_query());exit;

	return $sql->row()->bill_id;

	}

	

	

	function get_sales_return_trans_approval_tag($data)	

	{    

        $return_data=array();    

            $sql = $this->db->query("SELECT         

            SUM(t.gross_wt) as gross_wt,SUM(dt.item_cost) as item_cost,b.bill_id,b.bill_no,

            SUM(t.piece) as piece,cat.name as category_name,cat.id_ret_category as cat_id,date_format(b.bill_date,'%d-%m-%Y') as bill_date,

            b.ref_bill_id 

            

            FROM ret_billing b

            LEFT JOIN ret_bill_return_details r ON r.bill_id=b.bill_id

            LEFT JOIN ret_bill_details dt ON dt.bill_det_id=r.ret_bill_det_id 

            LEFT JOIN ret_taging t ON t.tag_id=dt.tag_id        

            LEFT JOIN ret_product_master pro on pro.pro_id=t.product_id     

            LEFT JOIN ret_category cat ON cat.id_ret_category=pro.cat_id  

            WHERE b.bill_id IS NOT NULL AND t.tag_id IS NOT NULL AND (t.tag_status=4 or t.tag_status=6) and b.bill_status=1 AND b.fin_year_code='".$data['fin_year_code']."' 

            AND t.current_branch = ".$data['from_brn']." 

            and b.id_branch=".$data['from_brn']."       

            ".($data['bill_no']!='' ? " AND bill_no='".$data['bill_no']."'" :'')."       

            GROUP BY b.bill_id
            HAVING gross_wt > 0");      

	//echo $this->db->last_query();exit;   

	$result = $sql->result_array();	

    foreach($result as $r)

    {

        $return_data[]=array(

            "bill_id"  => $r['bill_id'],

            "bill_no"  => $r['bill_no'],

            "bill_date" => $r['bill_date'],

            "gross_wt" => $r['gross_wt'],

            "piece"    => $r['piece'],

            "item_cost" => $r['item_cost'],

            "cat_id"   => $r['cat_id'],

            "category_name" => $r['category_name'],

            'ref_bill_id'  => $r['ref_bill_id'],

            "ret_bill_tags" => $this->get_billed_details($r['ref_bill_id'])



        );

    }

    return $return_data;

}



/*function get_ret_billed_tags($bill_id)

{



}*/

	

    

    function get_sales_return_tag_details($cat_id,$id_branch,$bill_id)

	{

	    $sql=$this->db->query("SELECT 

        dt.bill_det_id,dt.bill_id,dt.tag_id,t.tag_status,IFNULL(t.id_section,'') as id_section

        FROM ret_billing b 

        LEFT JOIN ret_bill_return_details r ON r.bill_id=b.bill_id

        LEFT JOIN ret_bill_details dt ON dt.bill_det_id=r.ret_bill_det_id 

        LEFT JOIN ret_taging t ON t.tag_id=dt.tag_id       

        LEFT JOIN ret_product_master pro on pro.pro_id=t.product_id      

        LEFT JOIN ret_category cat ON cat.id_ret_category=pro.cat_id  

        Left join ret_design_master d on d.design_no=t.design_id

        WHERE (t.tag_status=4 OR t.tag_status=6) and t.current_branch = ".$id_branch." and b.bill_status=1  and b.id_branch=".$id_branch."

        ".($bill_id!='' ? " AND b.bill_id=".$bill_id."" :'')."

        ".($cat_id!='' ? " AND pro.cat_id='".$cat_id."'" :'')."

        ");

        //print_r($this->db->last_query());exit;

        return $sql->result_array();

	}

	



    function getSettigsByName($name)

    {		 

		$branch = $this->db->query("SELECT value FROM ret_settings b Where name='".$name."'");

		return $branch->row('value');



	}

	



    function fetchTagsByFilter_scan($data)

    {

        $sql=$this->db->query("SELECT  b.bill_no,b.bill_id,IFNULL(dt.piece,0) as piece,IFNULL(dt.gross_wt,0) as gross_wt,

        

        date_format(b.bill_date,'%d-%m-%Y') as bill_date,dt.tag_id,t.tag_code,pro.product_name

        

        FROM ret_billing b 

        

        LEFT JOIN ret_bill_details dt ON dt.bill_id=b.bill_id

        

        LEFT JOIN ret_taging t ON t.tag_id=dt.tag_id	



        LEFT JOIN ret_product_master pro on pro.pro_id=t.product_id

        

        WHERE t.tag_status=4 

        ".($data['tag_code']!='' ? " and t.tag_code='".$data['tag_code']."'" :'')."

        ".($data['old_tag_code']!='' ? " and t.old_tag_id='".$data['old_tag_code']."'" :'')."

        

        and b.fin_year_code='".$data['fin_year_code']."' and b.bill_no='".$data['bill_no']."' and b.from_branch=".$data['from_brn']." and b.

        

        bill_status=1 and b.to_branch=".$data['to_brn']."");



       //print_r($this->db->last_query());exit;



        return $sql->result_array();

    }





    function get_TagBilledPcs($bill_id)

    {

        $sql = $this->db->query("SELECT IFNULL(SUM(dt.piece),0) as piece

        

        FROM ret_bill_details dt

        

        LEFT JOIN ret_taging t on t.tag_id=dt.tag_id

        

        WHERE tag_status=0 and dt.bill_id=".$bill_id."

        

        GROUP BY dt.bill_id");



        return $sql->row('piece');





    }

    /**
     * Count tagged lines on an ST/SRT bill whose tag has not yet been received
     * at the destination branch (tag_status still != 0 after scan).
     *
     * Unlike get_TagBilledPcs() -- which sums piece counts only for rows
     * joinable to ret_taging with tag_status=0, and is therefore blind to
     * Non-Tagged/Old Gold/Sales Return/Partly Sold lines -- this counts
     * remaining work across ALL tagged lines regardless of salesTransType,
     * so scan-mode download completion is not blocked forever by a mixed bill.
     */
    function count_unscanned_tagged_lines($bill_id)
    {
        $sql = $this->db->query("
            SELECT COUNT(*) as cnt
            FROM ret_bill_details dt
            LEFT JOIN ret_taging t ON t.tag_id = dt.tag_id
            WHERE dt.bill_id = " . $this->db->escape($bill_id) . "
            AND dt.tag_id IS NOT NULL
            AND IFNULL(t.tag_status, -1) <> 0
        ");
        return (int) $sql->row('cnt');
    }





    function fetchReturnTagsByFilter_scan($data)

    {

        $sql=$this->db->query("SELECT dt.bill_det_id,b.bill_id,dt.tag_id,b.ref_bill_id,

        

        t.tag_status,t.tag_code,pro.product_name,IFNULL(t.piece,0) as piece,IFNULL(t.gross_wt,0) as gross_wt

        

        FROM ret_billing b 

        

        LEFT JOIN ret_bill_return_details r ON r.bill_id=b.bill_id

        

        LEFT JOIN ret_bill_details dt ON dt.bill_det_id=r.ret_bill_det_id 

        

        LEFT JOIN ret_taging t ON t.tag_id=dt.tag_id       

        

        LEFT JOIN ret_product_master pro on pro.pro_id=t.product_id      

        

        LEFT JOIN ret_category cat ON cat.id_ret_category=pro.cat_id  

        

        Left join ret_design_master d on d.design_no=t.design_id

        

        WHERE (t.tag_status=4 OR t.tag_status=6) and t.current_branch=".$data['from_brn']."

        

        and b.bill_status=1 and b.id_branch=".$data['from_brn']." and t.tag_code='".$data['tag_code']."'

        

        and b.bill_no='".$data['bill_no']."'");



        //print_r($this->db->last_query());exit;



        return $sql->result_array();

    }


	// ============================================================
	// === Deemed Sales Transfer — New Model Methods (Phase 1) ===
	// ============================================================

	/**
	 * Get available Non-Tagged stock for a branch
	 * Used by salesTransType=2
	 */
	public function get_non_tagged_stock($data)
	{
		$branch_id = $this->db->escape($data['from_brn']);

		// Optional filters from search form
		$product_filter = '';
		if (!empty($data['prodId'])) {
			$product_filter = ' AND nt.product = ' . $this->db->escape($data['prodId']);
		}
		$category_filter = '';
		if (!empty($data['cat_id'])) {
			$category_filter = ' AND p.cat_id = ' . $this->db->escape($data['cat_id']);
		}

		$sql = $this->db->query("
			SELECT nt.id_nontag_item as nt_stock_id,
				   s.section_name,
				   IF(p.product_short_code = '' OR p.product_short_code IS NULL, p.product_name, CONCAT(p.product_short_code,' - ',p.product_name)) as product_name,
				   /* Available = Total stock minus pending-download dispatch */
				   (IFNULL(nt.no_of_piece,0) - IFNULL(pending.pending_pcs, 0)) as piece,
				   (nt.gross_wt - IFNULL(pending.pending_gwt, 0)) as gross_wt,
				   (nt.net_wt - IFNULL(pending.pending_nwt, 0)) as net_wt,
				   0 as pure_wt,
				   nt.product as product_id,
				   nt.design as design_id,
				   nt.id_sub_design,
				   nt.id_section,
				   IFNULL(p.cat_id, 0) as cat_id,
				   lid.rate as purchase_rate,
				   IFNULL(poi.item_wastage, lid.wastage_percentage) as purchase_va,
				   IFNULL(poi.mc_value, lid.making_charge) as purchase_mc
			FROM ret_nontag_item nt
			LEFT JOIN ret_section s ON s.id_section = nt.id_section
			LEFT JOIN ret_product_master p ON p.pro_id = nt.product
			/* Sum quantities already dispatched in ST bills not yet downloaded.
			   bill_status = 1 is essential: without it a CANCELLED, never-downloaded
			   dispatch keeps subtracting its quantity from available stock forever. */
			LEFT JOIN (
				SELECT bd.product_id, bd.design_id, bd.id_sub_design,
					   SUM(bd.piece) as pending_pcs,
					   SUM(bd.gross_wt) as pending_gwt,
					   SUM(bd.net_wt) as pending_nwt
				FROM ret_bill_details bd
				INNER JOIN ret_billing b ON b.bill_id = bd.bill_id
				WHERE b.bill_type = 13
				AND b.bill_status = 1
				AND b.id_branch = {$branch_id}
				AND b.download_date IS NULL
				AND bd.salesTransType = 2
				GROUP BY bd.product_id, bd.design_id, bd.id_sub_design
			) pending ON pending.product_id = nt.product
				AND (pending.design_id = 0 OR pending.design_id = nt.design)
				AND (pending.id_sub_design IS NULL OR pending.id_sub_design = nt.id_sub_design)
			/* NT stock is an aggregated bucket with no PO link — resolve purchase
			   values from the latest lot inward line of the same product */
			LEFT JOIN ret_lot_inwards_detail lid ON lid.id_lot_inward_detail = (
				SELECT MAX(x.id_lot_inward_detail)
				FROM ret_lot_inwards_detail x
				WHERE x.lot_product = nt.product
			)
			LEFT JOIN ret_purchase_order_items poi ON poi.po_item_id = lid.po_item_id
			WHERE nt.branch = {$branch_id}
			AND (nt.no_of_piece IS NULL OR (nt.no_of_piece - IFNULL(pending.pending_pcs, 0)) > 0)
			AND (nt.gross_wt - IFNULL(pending.pending_gwt, 0)) > 0
			AND IFNULL(nt.is_st_locked, 0) = 0  /* Exclude items locked/transferred by Sales Transfer */
			{$product_filter}
			{$category_filter}
		");
		return $sql->result_array();
	}

	/**
	 * Get Old Gold purchase items for a branch
	 * Used by salesTransType=3
	 * OG Rate = Total Purchase Amount / Total Net Weight
	 */
	public function get_old_gold_items($data)
	{
		$branch_id = $this->db->escape($data['from_brn']);
		$sql = $this->db->query("
			SELECT d.old_metal_sale_id as og_purchase_id,
				   IFNULL(omc.old_metal_cat, 'Old Gold') as category_name,
				   d.piece,
				   d.gross_wt,
				   d.net_wt,
				   IFNULL(d.pure_wt, 0) as pure_wt,
				   d.rate as amount,
				   CASE WHEN d.net_wt > 0 THEN ROUND(d.rate / d.net_wt, 2) ELSE 0 END as rate_per_grm
			FROM ret_bill_old_metal_sale_details d
			LEFT JOIN ret_billing b ON b.bill_id = d.bill_id
			LEFT JOIN ret_old_metal_category omc ON omc.id_old_metal_cat = d.id_old_metal_category
			WHERE b.id_branch = {$branch_id}
			AND b.bill_status = 1
			AND d.is_transferred = 0
			AND IFNULL(d.current_branch, b.id_branch) = {$branch_id} /* Only show OG items currently at this branch */
			AND d.st_bill_id IS NULL /* Not locked by any ST dispatch */
		");
		return $sql->result_array();
	}

	/**
	 * Get Sales Return items available for deemed transfer
	 * Used by salesTransType=4
	 */
	public function get_sales_return_items($data)
	{
		$branch_id = $this->db->escape($data['from_brn']);
		$sql = $this->db->query("
			SELECT t.tag_id,
				   t.tag_code,
				   IF(p.product_short_code = '' OR p.product_short_code IS NULL, p.product_name, CONCAT(p.product_short_code,' - ',p.product_name)) as product_name,
				   cat.name as category_name,
				   t.piece,
				   t.gross_wt,
				   t.net_wt,
				   b.bill_no,
				   t.product_id,
				   t.design_id,
				   t.purity,
				   IFNULL(p.cat_id, 0) as cat_id
			FROM ret_taging t
			LEFT JOIN ret_product_master p ON p.pro_id = t.product_id
			LEFT JOIN ret_category cat ON cat.id_ret_category = p.cat_id
			LEFT JOIN ret_bill_details bd ON bd.tag_id = t.tag_id
			LEFT JOIN ret_billing b ON b.bill_id = bd.bill_id AND b.bill_type = 2
			WHERE t.current_branch = {$branch_id}
			AND t.tag_status = 0
			AND t.is_return = 1
			AND t.tag_id NOT IN (
				SELECT dt2.tag_id FROM ret_bill_details dt2
				INNER JOIN ret_billing b2 ON b2.bill_id = dt2.bill_id
				WHERE b2.bill_type IN (13,14) /* ST / SRT bills */
				AND b2.bill_status = 1
				AND b2.download_date IS NULL /* only IN-FLIGHT dispatches lock the tag; completed transfers must not blacklist it forever */
				AND dt2.tag_id IS NOT NULL
			)
		");
		return $sql->result_array();
	}

	/**
	 * Get Partly Sold items available for deemed transfer
	 * Used by salesTransType=5
	 */
	public function get_partly_sold_items($data)
	{
		$branch_id = $this->db->escape($data['from_brn']);
		$sql = $this->db->query("
			SELECT t.tag_id,
				   t.tag_code,
				   IF(p.product_short_code = '' OR p.product_short_code IS NULL, p.product_name, CONCAT(p.product_short_code,' - ',p.product_name)) as product_name,
				   cat.name as category_name,
				   t.piece,
				   t.gross_wt,
				   IFNULL((t.gross_wt - IFNULL(d.gross_wt, 0)), t.gross_wt) as residual_wt,
				   t.net_wt,
				   t.product_id,
				   t.design_id,
				   t.purity,
				   IFNULL(p.cat_id, 0) as cat_id
			FROM ret_taging t
			LEFT JOIN ret_product_master p ON p.pro_id = t.product_id
			LEFT JOIN ret_category cat ON cat.id_ret_category = p.cat_id
			LEFT JOIN ret_bill_details d ON d.tag_id = t.tag_id
			LEFT JOIN ret_billing b ON b.bill_id = d.bill_id AND b.bill_status = 1
			WHERE t.current_branch = {$branch_id}
			AND t.tag_status = 0
			AND t.is_partial = 1
			AND t.trans_to_acc_stock = 0
			AND t.tag_id NOT IN (
				SELECT dt2.tag_id FROM ret_bill_details dt2
				INNER JOIN ret_billing b2 ON b2.bill_id = dt2.bill_id
				WHERE b2.bill_type IN (13,14) /* ST / SRT bills */
				AND b2.bill_status = 1
				AND b2.download_date IS NULL /* only IN-FLIGHT dispatches lock the tag; completed transfers must not blacklist it forever */
				AND dt2.tag_id IS NOT NULL
			)
		");
		return $sql->result_array();
	}

	/**
	 * Record which ST bill is dispatching from this NT stock row.
	 * Does NOT set is_st_locked=1 — partial quantities remain available.
	 * The search query subtracts pending-dispatch quantities instead of hiding rows.
	 */
	public function lock_nt_stock($nt_stock_id, $bill_id)
	{
		$this->db->where('id_nontag_item', $nt_stock_id);
		return $this->db->update('ret_nontag_item', array(
			'st_bill_id' => $bill_id
		));
	}

	/**
	 * Log the Sales Transfer OUTWARD movement for a Non-Tagged line at DISPATCH time.
	 *
	 * Tagged lines log their outward (ret_taging_status_log status=11/12) the moment the
	 * transfer is created, so they show up in the stock reports straight away. Non-Tagged
	 * lines used to log nothing until the receiving branch downloaded the bill, which left
	 * every in-flight transfer invisible in:
	 *   - Stock In & Out v1/v2  — outward set is status IN (2,3,4,5,9,10,11,12)
	 *   - Section-Wise Stock    — same outward set, keyed on from_section
	 * Branch Transfer already logs its outward at transit approval; this matches it.
	 *
	 * Section/design metadata is read from the source ret_nontag_item row so the caller
	 * does not have to extend the POSTed payload.
	 *
	 * @param  int    $nt_stock_id  ret_nontag_item.id_nontag_item being dispatched from
	 * @param  string $bill_date    dispatching branch's bill date (NOT the receiver's)
	 * @return bool
	 */
	public function log_nt_dispatch($nt_stock_id, $bill_id, $piece, $gross_wt, $net_wt, $from_branch, $to_branch, $bill_date)
	{
		$src = $this->db->query("
			SELECT product, design, id_sub_design, id_section
			FROM ret_nontag_item
			WHERE id_nontag_item = " . $this->db->escape($nt_stock_id) . "
		")->row_array();

		if (empty($src)) {
			log_message('error', 'log_nt_dispatch: nt_stock_id ' . $nt_stock_id . ' not found (bill_id=' . $bill_id . ')');
			return FALSE;
		}

		// Both log tables store `date` as DATE; $bill_date may carry a time component.
		$log_date = date('Y-m-d', strtotime($bill_date));

		$log = array(
			'bill_id'       => $bill_id,
			'product'       => $src['product'],
			'design'        => $src['design'],
			'id_sub_design' => $src['id_sub_design'],
			'no_of_piece'   => (int)$piece,
			'gross_wt'      => floatval($gross_wt),
			'net_wt'        => floatval($net_wt),
			'status'        => 11, // Sales Transfer Outward
			'from_branch'   => $from_branch,
			'to_branch'     => $to_branch,
			'created_by'    => $this->session->userdata('uid'),
			'created_on'    => date('Y-m-d H:i:s'),
			'date'          => $log_date,
		);
		$this->db->insert('ret_nontag_item_log', $log);

		$log['from_section'] = $src['id_section'];
		$log['to_section']   = $src['id_section'];
		$this->db->insert('ret_section_nontag_item_log', $log);

		return TRUE;
	}

	/**
	 * Lock Old Gold item during deemed sales transfer dispatch
	 */
	public function lock_og_item($og_purchase_id, $bill_id)
	{
		$this->db->where('old_metal_sale_id', $og_purchase_id);
		return $this->db->update('ret_bill_old_metal_sale_details', array(
			'is_transferred' => 1,
			'st_bill_id' => $bill_id
		));
	}

	// ============================================================
	// === Deemed Sales Transfer — Download & Purchase Invoice  ===
	// ============================================================

	/**
	 * Get bill details WITH salesTransType for type-aware download processing
	 * Returns all line items for a given sales transfer bill_id
	 */
	public function get_transfer_bill_details_with_type($bill_id)
	{
		$sql = $this->db->query("
			SELECT dt.bill_det_id, dt.bill_id, dt.tag_id, dt.product_id, dt.design_id,
				   dt.piece, dt.gross_wt, dt.net_wt, dt.less_wt, dt.purity,
				   dt.item_cost, dt.rate_per_grm, dt.calculation_based_on,
				   dt.total_igst, dt.total_sgst, dt.total_cgst, dt.item_total_tax,
				   IFNULL(dt.salesTransType, 1) as salesTransType,
				   dt.source_bill_det_id,
				   t.tag_code, t.tag_status,
				   IFNULL(om.old_metal_sale_id, 0) as old_metal_sale_id
			FROM ret_bill_details dt
			LEFT JOIN ret_taging t ON t.tag_id = dt.tag_id
			LEFT JOIN ret_bill_old_metal_sale_details om
				ON om.st_bill_id = dt.bill_id AND IFNULL(dt.salesTransType, 1) = 3
			WHERE dt.bill_id = " . $this->db->escape($bill_id) . "
		");
		return $sql->result_array();
	}

	/**
	 * Unlock NT stock and transfer it to the receiving branch
	 * Called during download of salesTransType=2 bills
	 * 
	 * Log Status Reference (matches BT convention for stock-in-out report):
	 *   status=0 → Inward (to_branch receives stock) — written here, at download
	 *   status=4 → Transit (BT approval step — not used in ST)
	 *   status=11 → ST Outward — written at DISPATCH by log_nt_dispatch(), not here.
	 *               Logging the outward here made in-flight transfers invisible to the
	 *               Stock In & Out and Section-Wise reports (VEDA-087).
	 *
	 * Runs inside the CALLER'S transaction. Do not open one here — CI 2.x never
	 * increments _trans_depth, so a nested trans_start() issues a second
	 * START TRANSACTION, which implicitly commits the caller's work, and the matching
	 * trans_complete() restores AUTOCOMMIT=1 for everything that follows.
	 *
	 * @return int number of NT lines moved; 0 means nothing matched — treat as failure
	 */
	public function unlock_nt_stock_for_download($bill_id, $to_branch)
	{
		// Get source branch from the ST bill
		$bill_row = $this->db->query("SELECT id_branch FROM ret_billing WHERE bill_id = " . $this->db->escape($bill_id))->row_array();
		$from_branch = isset($bill_row['id_branch']) ? $bill_row['id_branch'] : 0;

		// Get day-closing entry_date for accurate log dates
		$dc_row = $this->db->query("SELECT entry_date FROM ret_day_closing WHERE id_branch = " . $this->db->escape($to_branch))->row_array();
		$log_date = isset($dc_row['entry_date']) ? $dc_row['entry_date'] : date('Y-m-d');

		// Get dispatched NT quantities (partial, as entered by the user) together with the
		// source stock row's metadata.
		//
		// Primary path: bd.nt_stock_id, written at dispatch, binds each line to exactly one
		// ret_nontag_item row. This is strictly 1:1 — no fan-out, and immune to a later
		// dispatch overwriting ret_nontag_item.st_bill_id.
		//
		// Fallback: bills dispatched before nt_stock_id existed have it NULL, so they still
		// resolve the old way (st_bill_id + product/design/sub_design). Old bills may carry
		// design_id=0 / id_sub_design=NULL, hence those conditions are skipped when 0/NULL.
		$sql = $this->db->query("
			SELECT bd.piece, bd.gross_wt, bd.net_wt, bd.bill_det_id,
				   nt.id_nontag_item, nt.product, nt.design, nt.id_sub_design, nt.id_section
			FROM ret_bill_details bd
			INNER JOIN ret_nontag_item nt ON (
				(bd.nt_stock_id IS NOT NULL AND nt.id_nontag_item = bd.nt_stock_id)
				OR
				(bd.nt_stock_id IS NULL
					AND nt.st_bill_id = " . $this->db->escape($bill_id) . "
					AND nt.product = bd.product_id
					AND (bd.design_id = 0 OR nt.design = bd.design_id)
					AND (bd.id_sub_design IS NULL OR nt.id_sub_design = bd.id_sub_design))
			)
			WHERE bd.bill_id = " . $this->db->escape($bill_id) . "
			AND bd.salesTransType = 2
		");
		$items = $sql->result_array();

		foreach ($items as $item) {
			// Use the dispatched (partial) quantities from bill_details — NOT the full stock row
			$dispatch_pcs = (int)$item['piece'];
			$dispatch_gwt = floatval($item['gross_wt']);
			$dispatch_nwt = floatval($item['net_wt']);

			// Deduct dispatched partial quantities from source row using delta subtraction
			$this->db->query("
				UPDATE ret_nontag_item SET 
					no_of_piece = (no_of_piece - " . $dispatch_pcs . "),
					gross_wt    = (gross_wt - " . $this->db->escape($dispatch_gwt) . "),
					net_wt      = (net_wt - " . $this->db->escape($dispatch_nwt) . "),
					st_bill_id   = NULL
				WHERE id_nontag_item = " . $this->db->escape($item['id_nontag_item']) . "
			");


			// Check if same product+design+sub_design+section exists in receiving branch.
			// Uses <=> (NULL-safe equality): design/id_sub_design are NULL for products
			// like COINS, and CI's escape() renders PHP NULL as the literal NULL, so a
			// plain `col = NULL` never matched and every download inserted a duplicate
			// ret_nontag_item row instead of merging (skewed the Section-Wise report).
			$existing = $this->db->query("
				SELECT id_nontag_item, no_of_piece, gross_wt, net_wt
				FROM ret_nontag_item
				WHERE branch = " . $this->db->escape($to_branch) . "
				AND product = " . $this->db->escape($item['product']) . "
				AND design <=> " . $this->db->escape($item['design']) . "
				AND id_sub_design <=> " . $this->db->escape($item['id_sub_design']) . "
				AND id_section <=> " . $this->db->escape($item['id_section']) . "
				LIMIT 1
			")->row_array();

			if ($existing) {
				// Merge into existing row using dispatched quantities
				$this->db->where('id_nontag_item', $existing['id_nontag_item']);
				$this->db->update('ret_nontag_item', array(
					'no_of_piece' => $existing['no_of_piece'] + $dispatch_pcs,
					'gross_wt'    => $existing['gross_wt'] + $dispatch_gwt,
					'net_wt'      => $existing['net_wt'] + $dispatch_nwt,
				));
			} else {
				// Create new row at receiving branch with dispatched quantities
				$this->db->insert('ret_nontag_item', array(
					'branch'       => $to_branch,
					'product'      => $item['product'],
					'design'       => $item['design'],
					'id_sub_design' => $item['id_sub_design'],
					'id_section'   => $item['id_section'],
					'no_of_piece'  => $dispatch_pcs,
					'gross_wt'     => $dispatch_gwt,
					'net_wt'       => $dispatch_nwt,
					'is_st_locked' => 0,
					'st_bill_id'   => NULL,
				));
			}

			// NOTE: the status=11 OUTWARD rows for both log tables are written at dispatch
			// by log_nt_dispatch(), dated with the SENDING branch's bill date. Writing them
			// here dated them with the RECEIVING branch's day-closing date, so the source
			// branch's outward landed on the wrong business day (VEDA-087).

			// ---- LOG 1: INWARD to receiving branch (status=0 = Stock Download) ----
			// Report query: WHERE to_branch={branch} AND status=0
			$this->db->insert('ret_nontag_item_log', array(
				'bill_id'       => $bill_id,
				'product'       => $item['product'],
				'design'        => $item['design'],
				'id_sub_design' => $item['id_sub_design'],
				'no_of_piece'   => $dispatch_pcs,
				'gross_wt'      => $dispatch_gwt,
				'net_wt'        => $dispatch_nwt,
				'status'        => 0, // Inward — picked up by stock-in-out report inward query
				'from_branch'   => $from_branch,
				'to_branch'     => $to_branch,
				'created_by'    => $this->session->userdata('uid'),
				'created_on'    => date('Y-m-d H:i:s'),
				'date'          => $log_date,
			));

			// ---- SECTION LOG: INWARD to receiving section ----
			$this->db->insert('ret_section_nontag_item_log', array(
				'bill_id'       => $bill_id,
				'product'       => $item['product'],
				'design'        => $item['design'],
				'id_sub_design' => $item['id_sub_design'],
				'no_of_piece'   => $dispatch_pcs,
				'gross_wt'      => $dispatch_gwt,
				'net_wt'        => $dispatch_nwt,
				'status'        => 0, // Inward
				'from_branch'   => $from_branch,
				'to_branch'     => $to_branch,
				'from_section'  => $item['id_section'],
				'to_section'    => $item['id_section'],
				'created_by'    => $this->session->userdata('uid'),
				'created_on'    => date('Y-m-d H:i:s'),
				'date'          => $log_date,
			));
		}

		if ($this->db->trans_status() === FALSE) {
			log_message('error', 'unlock_nt_stock_for_download FAILED for bill_id=' . $bill_id . ' | Query: ' . $this->db->last_query());
			return 0;
		}

		if (empty($items)) {
			// Nothing joined: ret_nontag_item.st_bill_id was never set for this bill.
			// Usually means migration 20260513_000001 (st_bill_id/is_st_locked) has not
			// been applied on this database, so lock_nt_stock() silently did nothing.
			log_message('error', 'unlock_nt_stock_for_download matched 0 NT rows for bill_id=' . $bill_id . ' — check ret_nontag_item.st_bill_id and migration 20260513_000001');
		}

		return count($items);
	}

	/**
	 * Get full bill header for cancel validation and PI auto-generation
	 * Restricted to transfer bill types (13=ST, 14=SRT)
	 */
	public function get_bill_header($bill_id)
	{
		$sql = $this->db->query("SELECT * FROM ret_billing WHERE bill_id = " . $this->db->escape($bill_id) . " AND bill_type IN (13, 14) LIMIT 1");
		return $sql->row_array();
	}

	// ============================================================
	// === Phase 2: List View + OG Detail Methods ===
	// ============================================================

	// ==========================================================
	// === In-Transit Dashboard Methods ===
	// ==========================================================

	/**
	 * Get all in-transit (dispatched but not downloaded) transfers
	 * Supports filters: from_branch, to_branch, trans_type, direction (bill_type)
	 */
	public function get_in_transit_list($data = array())
	{
		$where = " WHERE b.bill_type IN (13, 14) AND b.download_date IS NULL AND b.bill_status = 1";

		if (!empty($data['from_branch'])) {
			$where .= " AND b.from_branch = " . $this->db->escape($data['from_branch']);
		}
		if (!empty($data['to_branch'])) {
			$where .= " AND b.to_branch = " . $this->db->escape($data['to_branch']);
		}
		if (!empty($data['direction'])) {
			$where .= " AND b.bill_type = " . $this->db->escape($data['direction']);
		}

		$transTypeFilter = "";
		if (!empty($data['trans_type'])) {
			$transTypeFilter = " HAVING salesTransType = " . $this->db->escape($data['trans_type']);
		}

		$sql = $this->db->query("
			SELECT b.bill_id,
				   b.bill_no,
				   b.bill_type,
				   DATE_FORMAT(b.bill_date, '%d-%m-%Y') as bill_date,
				   fb.name as from_branch_name,
				   tb.name as to_branch_name,
				   ABS(b.tot_bill_amount) as tot_bill_amount,
				   DATEDIFF(NOW(), b.bill_date) as days_in_transit,
				   (SELECT COUNT(*) FROM ret_bill_details dt WHERE dt.bill_id = b.bill_id) as item_count,
				   IFNULL(
					   (SELECT dt.salesTransType 
					    FROM ret_bill_details dt 
					    WHERE dt.bill_id = b.bill_id 
					    AND dt.salesTransType IS NOT NULL 
					    AND dt.salesTransType > 0 
					    LIMIT 1), 1
				   ) as salesTransType
			FROM ret_billing b
			LEFT JOIN branch fb ON fb.id_branch = b.from_branch
			LEFT JOIN branch tb ON tb.id_branch = b.to_branch
			{$where}
			{$transTypeFilter}
			ORDER BY b.bill_date ASC
		");
		return $sql->result_array();
	}

	/**
	 * Calculate KPI summary for in-transit items
	 */
	public function get_in_transit_kpi($data = array())
	{
		$where = " WHERE b.bill_type IN (13, 14) AND b.download_date IS NULL AND b.bill_status = 1";
		if (!empty($data['from_branch'])) {
			$where .= " AND b.from_branch = " . $this->db->escape($data['from_branch']);
		}
		if (!empty($data['to_branch'])) {
			$where .= " AND b.to_branch = " . $this->db->escape($data['to_branch']);
		}

		$sql = $this->db->query("
			SELECT COUNT(b.bill_id) as total_bills,
				   IFNULL(SUM(ABS(b.tot_bill_amount)), 0) as total_value,
				   IFNULL((SELECT SUM(sub.cnt) FROM (
					   SELECT COUNT(*) as cnt FROM ret_bill_details WHERE bill_id IN (
						   SELECT bill_id FROM ret_billing WHERE bill_type IN (13,14) AND download_date IS NULL AND bill_status=1
					   )
				   ) sub), 0) as total_items,
				   SUM(CASE WHEN DATEDIFF(NOW(), b.bill_date) > 3 THEN 1 ELSE 0 END) as aging_count
			FROM ret_billing b
			{$where}
		");
		return $sql->row_array();
	}

	/**
	 * Get branches for filter dropdowns
	 */
	public function get_active_branches()
	{
		return $this->db->query("SELECT id_branch, name FROM branch WHERE active = 1 ORDER BY name")->result_array();
	}

	/**
	 * Get full transfer data for print template
	 * Returns header + line items with product/HSN info
	 */
	public function get_transfer_print_data($bill_id)
	{
		$bill_id = $this->db->escape($bill_id);

		$header = $this->db->query("
			SELECT b.bill_id, b.bill_no, b.bill_type,
				   DATE_FORMAT(b.bill_date, '%d-%m-%Y') as bill_date,
				   b.tot_bill_amount, b.goldrate_22ct, b.silverrate_1gm,
				   b.remark, b.form_secret,
				   DATE_FORMAT(b.created_time, '%d-%m-%Y %H:%i') as created_time,
				   u.firstname as created_by_name,
				   fb.name as from_branch_name, fb.gst_number as from_gst,
				   CONCAT(fb.address1, ' ', IFNULL(fb.address2,'')) as from_address, fb.id_state as from_state,
				   tb.name as to_branch_name, tb.gst_number as to_gst,
				   CONCAT(tb.address1, ' ', IFNULL(tb.address2,'')) as to_address, tb.id_state as to_state,
				   c.company_name,
				   DATE_FORMAT(b.download_date, '%d-%m-%Y %H:%i') as download_date,
				   du.firstname as download_by_name
			FROM ret_billing b
			LEFT JOIN branch fb ON fb.id_branch = b.from_branch
			LEFT JOIN branch tb ON tb.id_branch = b.to_branch
			LEFT JOIN employee u ON u.id_employee = b.created_by
			LEFT JOIN employee du ON du.id_employee = b.download_by
			LEFT JOIN company c ON c.id_company = 1
			WHERE b.bill_id = {$bill_id}
		")->row_array();

		$items = $this->db->query("
			SELECT d.bill_det_id, d.tag_id, d.product_id, d.design_id,
				   d.piece, d.gross_wt, d.net_wt, d.less_wt,
				   d.rate_per_grm, d.item_cost, d.item_total_tax,
				   d.total_sgst, d.total_cgst, d.total_igst,
				   d.salesTransType,
				   d.purchase_rate, d.purchase_va, d.purchase_mc,
				   IFNULL(p.product_name, '') as product_name,
				   IFNULL(p.hsn_code, '') as hsn_code,
				   IFNULL(ds.design_name, '') as design_name,
				   IFNULL(t.tag_code, '') as tag_code,
				   d.calculation_based_on,
				   IFNULL(t.retail_max_wastage_percent, IFNULL(d.wastage_percent, 0.00)) as wastage_percent,
				   IFNULL(t.tag_mc_value, IFNULL(d.mc_value, 0.00)) as mc_value,
				   IFNULL(t.tag_mc_type, IFNULL(d.mc_type, 1)) as mc_type
			FROM ret_bill_details d
			LEFT JOIN ret_product_master p ON p.pro_id = d.product_id
			LEFT JOIN ret_design_master ds ON ds.design_no = d.design_id
			LEFT JOIN ret_taging t ON t.tag_id = d.tag_id
			WHERE d.bill_id = {$bill_id}
			ORDER BY d.bill_det_id
		")->result_array();

		return array('header' => $header, 'items' => $items);
	}

	/**
	 * Get unified Sales Transfer + Return Transfer list for DataTable
	 * Returns bill_type 13 (Sales Transfer) and 14 (Sales Return Transfer)
	 */
	public function get_sales_transfer_list($data)
	{
		$from_date = $this->db->escape($data['from_date']);
		$to_date   = $this->db->escape($data['to_date']);

		$sql = $this->db->query("
			SELECT b.bill_id,
				   b.bill_no,
				   b.bill_type,
				   DATE_FORMAT(b.bill_date, '%d-%m-%Y') as bill_date,
				   fb.name as from_branch_name,
				   tb.name as to_branch_name,
				   b.bill_status,
				   IF(b.download_date IS NOT NULL, 1, 0) as download_status,
				   b.tot_bill_amount,
				   IFNULL(
					   (SELECT dt.salesTransType 
					    FROM ret_bill_details dt 
					    WHERE dt.bill_id = b.bill_id 
					    AND dt.salesTransType IS NOT NULL 
					    AND dt.salesTransType > 0 
					    LIMIT 1), 1
				   ) as salesTransType
			FROM ret_billing b
			LEFT JOIN branch fb ON fb.id_branch = b.from_branch
			LEFT JOIN branch tb ON tb.id_branch = b.to_branch
			WHERE b.bill_type IN (13, 14)
			AND b.bill_date >= {$from_date}
			AND b.bill_date < DATE_ADD({$to_date}, INTERVAL 1 DAY)
			ORDER BY b.bill_id DESC
		");
		return $sql->result_array();
	}

	/**
	 * Get full detail of a single Old Gold purchase record
	 * Used by the OG Detail Modal
	 */
	public function get_old_gold_detail($og_id)
	{
		$og_id = $this->db->escape($og_id);
		$sql = $this->db->query("
			SELECT d.old_metal_sale_id,
				   IFNULL(omc.old_metal_cat, 'Old Gold') as category_name,
				   d.piece,
				   d.gross_wt,
				   d.net_wt,
				   IFNULL(d.pure_wt, 0) as pure_wt,
				   d.rate as amount,
				   d.rate_per_grm,
				   d.purity,
				   d.stone_wt,
				   d.less_wt,
				   d.touch,
				   d.melt_percent,
				   b.bill_no,
				   DATE_FORMAT(b.bill_date, '%d-%m-%Y') as bill_date,
				   c.customer_name,
				   c.mobile_no,
				   br.name as branch_name
			FROM ret_bill_old_metal_sale_details d
			LEFT JOIN ret_billing b ON b.bill_id = d.bill_id
			LEFT JOIN customer c ON c.id_customer = b.id_customer
			LEFT JOIN ret_old_metal_category omc ON omc.id_old_metal_cat = d.id_old_metal_category
			LEFT JOIN branch br ON br.id_branch = b.id_branch
			WHERE d.old_metal_sale_id = {$og_id}
		");
		return $sql->row_array();
	}

	// ============================================================
	// === Cancel / Reversal Helper Methods ===
	// ============================================================

	/**
	 * Unlock NT stock rows that were locked during dispatch (not yet transferred)
	 * Used when cancelling a dispatched-only bill
	 */
	public function unlock_nt_stock_by_bill($bill_id)
	{
		// Match on st_bill_id alone. lock_nt_stock() intentionally does NOT set
		// is_st_locked (partial quantities must stay available), so requiring
		// is_st_locked = 1 here matched nothing and left st_bill_id populated on
		// cancelled bills — permanently withholding that quantity from
		// get_non_tagged_stock()'s availability calculation.
		$this->db->where('st_bill_id', $bill_id);
		return $this->db->update('ret_nontag_item', array(
			'is_st_locked' => 0,
			'st_bill_id' => NULL,
		));
	}

	/**
	 * Remove the status=11 dispatch logs written by log_nt_dispatch().
	 *
	 * Only for cancelling a bill that was dispatched but NEVER downloaded: no stock
	 * actually moved, so the outward entry must not linger in the Stock In & Out and
	 * Section-Wise reports. Downloaded bills keep their logs — their stock has already
	 * moved, and removing the logs would desync the reports from ret_nontag_item.
	 */
	public function delete_nt_dispatch_logs($bill_id)
	{
		$this->db->where('bill_id', $bill_id);
		$this->db->where('status', 11);
		$this->db->delete('ret_nontag_item_log');

		$this->db->where('bill_id', $bill_id);
		$this->db->where('status', 11);
		$this->db->delete('ret_section_nontag_item_log');

		return TRUE;
	}

	/**
	 * Reverse NT stock transfer after a downloaded bill is cancelled
	 * Moves stock back from receiving branch to sending branch
	 */
	public function reverse_nt_stock_transfer($bill_id, $original_from_branch)
	{
		// Get the original transferred quantities from ret_bill_details
		// These are the exact amounts that were dispatched (salesTransType=2 = NT items)
		$bill_items = $this->db->query("
			SELECT d.product_id, d.piece, d.gross_wt, d.net_wt
			FROM ret_bill_details d
			WHERE d.bill_id = " . $this->db->escape($bill_id) . "
			AND d.salesTransType = 2
		")->result_array();

		// Get source items that were transferred
		// Note: After download, source rows are reset to is_st_locked=0, st_bill_id=NULL
		// So reversal must use ret_bill_details to get the original transfer quantities
		// and match source rows by (branch, product, design, sub_design, section)
		$sql = $this->db->query("
			SELECT id_nontag_item, product, design, id_sub_design, id_section, branch
			FROM ret_nontag_item 
			WHERE st_bill_id = " . $this->db->escape($bill_id) . "
		");
		$items = $sql->result_array();

		$idx = 0;
		foreach ($items as $item) {
			// Get the original quantities from bill_details for this item
			$orig_piece = isset($bill_items[$idx]) ? (int)$bill_items[$idx]['piece'] : 0;
			$orig_gwt   = isset($bill_items[$idx]) ? $bill_items[$idx]['gross_wt'] : 0;
			$orig_nwt   = isset($bill_items[$idx]) ? $bill_items[$idx]['net_wt'] : 0;

			// Delta-ADD back to source row (reverse of the download subtraction)
			$this->db->query("
				UPDATE ret_nontag_item SET 
					no_of_piece = (no_of_piece + " . $orig_piece . "),
					gross_wt    = (gross_wt + " . $this->db->escape($orig_gwt) . "),
					net_wt      = (net_wt + " . $this->db->escape($orig_nwt) . "),
					is_st_locked = 0,
					st_bill_id   = NULL
				WHERE id_nontag_item = " . $this->db->escape($item['id_nontag_item']) . "
			");

			// Delta-SUBTRACT from receiving branch row
			// <=> (NULL-safe equality): design/id_sub_design are NULL for products like
			// COINS, and CI's escape() renders PHP NULL as the literal NULL.
			$existing = $this->db->query("
				SELECT id_nontag_item
				FROM ret_nontag_item
				WHERE branch = " . $this->db->escape($original_from_branch) . "
				AND product = " . $this->db->escape($item['product']) . "
				AND design <=> " . $this->db->escape($item['design']) . "
				AND id_sub_design <=> " . $this->db->escape($item['id_sub_design']) . "
				AND id_section <=> " . $this->db->escape($item['id_section']) . "
				AND st_bill_id IS NULL
				AND is_st_locked = 0
				LIMIT 1
			")->row_array();

			if ($existing) {
				$this->db->query("
					UPDATE ret_nontag_item SET 
						no_of_piece = (no_of_piece - " . $orig_piece . "),
						gross_wt    = (gross_wt - " . $this->db->escape($orig_gwt) . "),
						net_wt      = (net_wt - " . $this->db->escape($orig_nwt) . ")
					WHERE id_nontag_item = " . $this->db->escape($existing['id_nontag_item']) . "
				");
			}
			// Note: If receiving branch stock was already consumed (retagged/sold),
			// deduction may cause negative values — requires manual correction.

			$idx++;
		}
		return count($items);
	}

	/**
	 * Unlock OG items locked to a specific transfer bill
	 * Works for both dispatched-only and downloaded cancellations
	 */
	public function unlock_og_item_by_bill($bill_id)
	{
		$this->db->where('st_bill_id', $bill_id);
		$this->db->where('is_transferred', 1);
		return $this->db->update('ret_bill_old_metal_sale_details', array(
			'is_transferred' => 0,
			'st_bill_id' => NULL,
		));
	}

	/**
	 * Cancel the auto-generated Purchase Invoice (bill_type=15) 
	 * linked to a dispatched Sales Transfer bill
	 */
	public function cancel_linked_purchase_invoice($dispatch_bill_id)
	{
		// Find PI linked via ref_bill_id
		$pi = $this->db->query("
			SELECT bill_id FROM ret_billing 
			WHERE ref_bill_id = " . $this->db->escape($dispatch_bill_id) . "
			AND bill_type = 15 
			AND bill_status != 2
			LIMIT 1
		")->row_array();

		if ($pi) {
			$this->db->where('bill_id', $pi['bill_id']);
			return $this->db->update('ret_billing', array(
				'bill_status' => 2,
				'cancel_reason' => 'Auto-cancelled: Source transfer bill cancelled',
				'cancelled_date' => date("Y-m-d H:i:s"),
				'updated_time' => date("Y-m-d H:i:s"),
			));
		}
		return false;
	}

	/**
	 * Insert an audit log entry into ret_sales_transfer_log
	 * @param int    $bill_id  FK to ret_billing.bill_id
	 * @param string $action   create|download|cancel|update
	 * @param int    $user_id  User who performed the action
	 * @param string $details  JSON or text description
	 * @param string $ip       Client IP address
	 */
	function log_transfer_action($bill_id, $action, $user_id, $details = '', $ip = '') {
		$this->db->insert('ret_sales_transfer_log', array(
			'bill_id'    => $bill_id,
			'action'     => $action,
			'user_id'    => $user_id,
			'ip_address' => ($ip != '' ? $ip : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null)),
			'details'    => $details,
			'created_at' => date("Y-m-d H:i:s"),
		));
	}

	// ============================================================
	// === Purchase Items — Combined OG + SR + PS (BT Parity) ===
	// ============================================================

	/**
	 * Get combined Purchase Items (Old Gold + Sales Return + Partly Sold)
	 * Adapted from ret_brntransfer_model::get_purchase_items()
	 * Returns grouped rows by metal type with bill_det detail arrays
	 */
	function get_st_purchase_items($data)
	{
		$return_Data = [];

		// === PARTLY SOLD ITEMS ===
		$metals = $this->db->query("SELECT * FROM metal WHERE metal_status = 1");
		$metals_result = $metals->result_array();
		foreach ($metals_result as $items) {
			$type = 'partly_sale_' . strtolower($items['metal']);
			$itemDetails = $this->get_st_partly_sale_details($data['from_date'], $data['to_date'], $data['from_branch'], $items['id_metal']);
			$gross_wt = 0;
			$net_wt   = 0;
			$tot_item_cost = 0;
			$dia_wt   = 0;
			foreach ($itemDetails as $val) {
				$dia_wt += $val['dia_wt'];
				$gross_wt += $val['gross_wt'];
				$net_wt += $val['net_wt'];
				$tot_item_cost += $val['tot_item_cost'];
			}
			if ($gross_wt > 0) {
				$return_Data[] = array(
					'type'              => $type,
					'metal_type'		=> 'PARTLY SALE' . '-' . $items['metal'],
					'metal_name'		=> $items['metal'],
					'id_metal'		    => $items['id_metal'],
					'dia_wt'			=> number_format($dia_wt, 3, '.', ''),
					'gross_wt'			=> number_format($gross_wt, 3, '.', ''),
					'rate'	            => number_format($tot_item_cost, 2, '.', ''),
					'net_wt'			=> number_format($net_wt, 3, '.', ''),
					'bill_det'			=> $itemDetails,
				);
			}
		}

		// === SALES RETURN ITEMS ===
		$sales_ret = $this->db->query("SELECT * FROM metal WHERE metal_status = 1");
		$sales_ret_result = $sales_ret->result_array();
		foreach ($sales_ret_result as $items) {
			$type = 'sales_ret_items_' . strtolower($items['metal']);
			$sales_return_det = $this->get_st_sales_ret_details($data['from_date'], $data['to_date'], $data['from_branch'], $items['id_metal']);
			$gross_wt = 0;
			$net_wt   = 0;
			$dia_wt   = 0;
			$tot_item_cost = 0;
			foreach ($sales_return_det as $val) {
				$gross_wt += $val['gross_wt'];
				$net_wt += $val['net_wt'];
				$dia_wt += $val['dia_wt'];
				$tot_item_cost += $val['amount'];
			}
			if ($gross_wt > 0 || $tot_item_cost > 0) {
				$return_Data[] = array(
					'type'              => $type,
					'metal_type'		=> 'SALES RETURN' . '-' . $items['metal'],
					'metal_name'		=> $items['metal'],
					'id_metal'		    => $items['id_metal'],
					'dia_wt'			=> number_format($dia_wt, 3, '.', ''),
					'gross_wt'			=> number_format($gross_wt, 3, '.', ''),
					'rate'	            => number_format($tot_item_cost, 3, '.', ''),
					'net_wt'			=> number_format($net_wt, 3, '.', ''),
					'bill_det'			=> $sales_return_det,
				);
			}
		}

		// === OLD METAL (OLD GOLD) ITEMS ===
		$sql = $this->db->query("SELECT s.old_metal_sale_id,IFNULL(SUM(s.gross_wt),0) as gross_wt,IFNULL(SUM(s.net_wt),0) as net_wt,IFNULL(SUM(s.rate),0) as rate,
        s.metal_type,s.id_old_metal_type, met.metal,
		FORMAT(IFNULL(SUM(bill_st.dia_wt), 0), 3) AS dia_wt
        FROM ret_billing b 
        LEFT JOIN ret_bill_old_metal_sale_details s ON s.bill_id=b.bill_id
        LEFT JOIN ret_estimation_old_metal_sale_details est ON est.old_metal_sale_id=s.esti_old_metal_sale_id
        LEFT JOIN ret_old_metal_type t ON t.id_metal_type=s.id_old_metal_type 
        LEFT JOIN metal as met ON met.id_metal = t.id_metal 
		LEFT JOIN (SELECT IFNULL(SUM(bill_st.wt),0) as dia_wt,bill_st.old_metal_sale_id
		FROM ret_billing_item_stones bill_st
		LEFT JOIN ret_bill_old_metal_sale_details s ON s.old_metal_sale_id=bill_st.old_metal_sale_id
		LEFT JOIN ret_stone st ON st.stone_id = bill_st.stone_id
		LEFT JOIN ret_billing bill ON bill.bill_id = bill_st.bill_id
		WHERE bill.bill_status=1 and st.stone_type = 1	 
		GROUP BY bill_st.old_metal_sale_id) as bill_st ON bill_st.old_metal_sale_id = s.old_metal_sale_id
        
        Left join (
        SELECT bti.old_metal_sale_id 
        FROM `ret_branch_transfer` bt 
        Left join ret_brch_transfer_old_metal bti on bti.transfer_id = bt.branch_transfer_id 
        WHERE (status = 1 or status = 2) and transfer_item_type=3 and bt.transfer_from_branch=" . $data['from_branch'] . "
        GROUP BY bti.old_metal_sale_id) btrans on btrans.old_metal_sale_id = s.old_metal_sale_id 
        
        WHERE b.bill_status=1 AND s.old_metal_sale_id IS NOT null AND btrans.old_metal_sale_id IS NULL
        " . ($data['from_branch'] != '' ?  " and s.current_branch=" . $data['from_branch'] . "" : '') . "
        and (date(b.bill_date) BETWEEN '" . date('Y-m-d', strtotime($data['from_date'])) . "' AND '" . date('Y-m-d', strtotime($data['to_date'])) . "') 
        and s.is_transferred=0 AND s.st_bill_id IS NULL
        group by s.metal_type");

		$result = $sql->result_array();
		foreach ($result as $items) {
			$type = 'old_metal_' . strtolower($items['metal']);
			$return_Data[] = array(
				'type'              => $type,
				'metal_type'		=> 'OLD METAL' . ' ' . strtoupper($items['metal']),
				'dia_wt'			=> $items['dia_wt'],
				'gross_wt'			=> $items['gross_wt'],
				'net_wt'			=> $items['net_wt'],
				'rate'				=> $items['rate'],
				'bill_det'			=> $this->st_old_metal_bill_details($data['from_date'], $data['to_date'], $data['from_branch'], $items['metal_type']),
			);
		}

		return $return_Data;
	}

	/**
	 * Get Partly Sale detail rows for ST purchase items
	 * Adapted from ret_brntransfer_model::get_partly_sale_details() without is_eda filter
	 */
	function get_st_partly_sale_details($from_date, $to_date, $id_branch, $id_metal)
	{
		$sql = $this->db->query("SELECT (IFNULL(tag.gross_wt,0)-IFNULL(t.sold_gross_wt,0)) as gross_wt,'0' as amount,'0' as tot_item_cost,cat.id_metal,mt.metal as metal_name,
        DATE_FORMAT(bill.bill_date,'%d-%m-%Y') as bill_date,bill.sales_ref_no as bill_no,bill.bill_id,'0' as is_checked,d.tag_id,d.bill_det_id,
        (IFNULL(tag.net_wt,0)-IFNULL(t.sold_net_wt,0)) as net_wt,
		FORMAT(IFNULL(stn.dia_wt,0)-IFNULL(bill_st.sold_wt,0),3) as dia_wt,
        if(mt.id_metal=1,'partly_sale_gold','partly_sale_silver') as item_type,'3' as transfer_items,d.bill_det_id as trans_id,tag.gross_wt as tagged_gwt,
        IFNULL(brch.gross_wt,0) as transfered_wt,'' as is_non_tag
        FROM ret_bill_details d 
        LEFT JOIN ret_taging tag ON tag.tag_id=d.tag_id
        LEFT JOIN ret_billing bill ON bill.bill_id=d.bill_id
        LEFT JOIN ret_product_master p ON p.pro_id=d.product_id
        LEFT JOIN ret_category cat ON cat.id_ret_category=p.cat_id
        LEFT JOIN metal mt ON mt.id_metal=cat.id_metal
        
        Left join (
        SELECT bti.tag_id 
        FROM `ret_branch_transfer` bt 
        Left join ret_brch_transfer_old_metal bti on bti.transfer_id = bt.branch_transfer_id 
        WHERE (status = 1 or status = 2) and bti.item_type=3 and bt.transfer_from_branch=" . $id_branch . "
        GROUP BY bti.tag_id) btrans on btrans.tag_id = tag.tag_id
        
        Left join (
        SELECT bti.tag_id,IFNULL(SUM(bti.gross_wt),0) as gross_wt
        FROM `ret_branch_transfer` bt 
        Left join ret_brch_transfer_old_metal bti on bti.transfer_id = bt.branch_transfer_id 
        WHERE bti.item_type=3 and bt.transfer_from_branch=" . $id_branch . "
        GROUP BY bti.tag_id) brch on brch.tag_id = tag.tag_id
		LEFT join(SELECT IFNULL(SUM(st.wt),0) as dia_wt,st.tag_id
        FROM ret_taging_stone st
		LEFT JOIN ret_bill_details d ON d.tag_id=st.tag_id
		LEFT JOIN ret_billing bill ON bill.bill_id=d.bill_id
        LEFT JOIN ret_stone s ON s.stone_id=st.stone_id
        LEFT JOIN ret_uom uom ON uom.uom_id=s.uom_id
        WHERE bill.bill_status=1 AND d.tag_id IS NOT NULL AND s.stone_type=1
		" . ($id_branch != '' ?  " and bill.id_branch=" . $id_branch . "" : '') . "
		and (date(bill.bill_date) BETWEEN '" . date('Y-m-d', strtotime($from_date)) . "' AND '" . date('Y-m-d', strtotime($to_date)) . "')
        group by st.tag_id) as stn ON stn.tag_id = tag.tag_id
		LEFT JOIN (SELECT IFNULL(SUM(bill_st.wt),0) as sold_wt,d.tag_id
		FROM ret_billing_item_stones bill_st
		LEFT JOIN ret_stone st ON st.stone_id = bill_st.stone_id
		LEFT JOIN ret_bill_details d ON d.bill_det_id = bill_st.bill_det_id
		LEFT JOIN ret_billing bill ON bill.bill_id = d.bill_id
		WHERE  bill.bill_status=1  AND d.tag_id IS NOT NULL AND st.stone_type=1
		" . ($id_branch != '' ?  " and bill.id_branch=" . $id_branch . "" : '') . "
		and (date(bill.bill_date) BETWEEN '" . date('Y-m-d', strtotime($from_date)) . "' AND '" . date('Y-m-d', strtotime($to_date)) . "')
		GROUP BY d.tag_id) as bill_st ON bill_st.tag_id = tag.tag_id
        
        LEFT JOIN (SELECT IFNULL(sum(d.gross_wt),0) as sold_gross_wt,IFNULL(sum(d.net_wt),0) as sold_net_wt,d.tag_id
                  FROM ret_taging tag
                  LEFT JOIN ret_bill_details d ON d.tag_id=tag.tag_id
                  LEFT JOIN ret_billing bill ON bill.bill_id=d.bill_id
                  LEFT JOIN ret_product_master p ON p.pro_id=d.product_id
        		  LEFT JOIN ret_category cat ON cat.id_ret_category=p.cat_id
                  WHERE bill.bill_status=1  AND d.tag_id IS NOT NULL
                  " . ($id_branch != '' ?  " and bill.id_branch=" . $id_branch . "" : '') . "
		          and (date(bill.bill_date) BETWEEN '" . date('Y-m-d', strtotime($from_date)) . "' AND '" . date('Y-m-d', strtotime($to_date)) . "')
                  GROUP BY d.tag_id) as t ON t.tag_id=d.tag_id
				  
        WHERE bill.bill_status=1 AND d.is_partial_sale=1 AND btrans.tag_id IS NULL AND mt.id_metal=" . $id_metal . "
        " . ($id_branch != '' ?  " and bill.id_branch=" . $id_branch . "" : '') . "
		and (date(bill.bill_date) BETWEEN '" . date('Y-m-d', strtotime($from_date)) . "' AND '" . date('Y-m-d', strtotime($to_date)) . "')
		AND tag.tag_id NOT IN (
			SELECT dt2.tag_id FROM ret_bill_details dt2
			INNER JOIN ret_billing b2 ON b2.bill_id = dt2.bill_id
			WHERE b2.bill_type IN (13,14) AND b2.bill_status = 1
			AND b2.download_date IS NULL /* only IN-FLIGHT ST/SRT dispatches lock the tag; completed transfers must not blacklist it forever */
			AND dt2.tag_id IS NOT NULL
		)
		" . ($id_branch != '' ? " AND IFNULL(tag.current_branch, bill.id_branch) = " . intval($id_branch) . "" : '') . "
        HAVING gross_wt > transfered_wt");
		return $sql->result_array();
	}

	/**
	 * Get Sales Return detail rows for ST purchase items
	 * Adapted from ret_brntransfer_model::get_sales_ret_details() without is_eda filter
	 */
	function get_st_sales_ret_details($from_date, $to_date, $id_branch, $id_metal)
	{
		$returnData = array();
		$sql = $this->db->query("SELECT IFNULL((d.gross_wt),0) as gross_wt,IFNULL((d.net_wt),0) as net_wt,
        DATE_FORMAT(b.bill_date,'%d-%m-%Y') as bill_date,b.s_ret_refno as bill_no,b.bill_id,'0' as is_checked,'0' as amount,
        if(d.is_non_tag=0,IFNULL(t.tag_id,d.bill_det_id),d.bill_det_id) as trans_id,if(mt.id_metal=1,'sales_ret_items_gold','sales_ret_items_silver') as type,'2' as transfer_items,IFNULL(d.item_cost,0) as amount,
        if(mt.id_metal=1,'sales_ret_items_gold','sales_ret_items_silver') as item_type,IFNULL(brch.gross_wt,0) as transfered_wt,t.gross_wt as tagged_gwt,t.tag_id,
        p.sales_mode,t.trans_to_acc_stock,d.is_non_tag,t.tag_status,IFNULL(btrans.tag_id,'') as btrans_tag_id,d.transferred_to_acc_stock,d.bill_det_id,
        IFNULL(non_tag_brch.sold_bill_det_id,'') as non_tag_brch_det_id,FORMAT(IFNULL(bill_st.dia_wt,0),3) as dia_wt,IFNULL(d.item_type,0) as det_item_type
        FROM ret_billing b 
        LEFT JOIN ret_bill_return_details r ON r.bill_id=b.bill_id
        LEFT JOIN ret_bill_details d ON d.bill_det_id=r.ret_bill_det_id
        LEFT JOIN ret_taging t ON t.tag_id=d.tag_id
        LEFT JOIN ret_product_master p ON p.pro_id=d.product_id
        LEFT JOIN ret_category cat ON cat.id_ret_category=p.cat_id
        LEFT JOIN metal mt ON mt.id_metal=cat.id_metal
        
        Left join (
        SELECT bti.tag_id 
        FROM `ret_branch_transfer` bt 
        Left join ret_brch_transfer_old_metal bti on bti.transfer_id = bt.branch_transfer_id 
        WHERE (status = 1 or status = 2) and bti.item_type = 2 and bt.transfer_from_branch=" . $id_branch . "
        GROUP BY bti.tag_id) btrans on btrans.tag_id = t.tag_id 
        
        Left join (
        SELECT bti.tag_id,IFNULL(SUM(bti.gross_wt),0) as gross_wt
        FROM `ret_branch_transfer` bt 
        Left join ret_brch_transfer_old_metal bti on bti.transfer_id = bt.branch_transfer_id 
        WHERE transfer_item_type=3 and bti.item_type = 2 and bt.transfer_from_branch=" . $id_branch . "
        GROUP BY bti.tag_id) brch on brch.tag_id = t.tag_id
		LEFT JOIN (SELECT IFNULL(bill_st.wt,0) as dia_wt,d.bill_det_id
		FROM ret_billing_item_stones bill_st
		LEFT JOIN ret_stone st ON st.stone_id = bill_st.stone_id
		LEFT JOIN ret_bill_return_details r ON r.ret_bill_det_id= bill_st.bill_det_id
		LEFT JOIN ret_bill_details d ON d.bill_det_id = r.ret_bill_det_id
		LEFT JOIN ret_billing bill ON bill.bill_id = r.ret_bill_id
		LEFT JOIN ret_product_master p ON p.pro_id=d.product_id
        LEFT JOIN ret_category cat ON cat.id_ret_category=p.cat_id
        LEFT JOIN metal mt ON mt.id_metal=cat.id_metal
		WHERE  bill.bill_status=1  and st.stone_type = 1 and mt.id_metal=" . $id_metal . "
		" . ($id_branch != '' ?  " and bill.id_branch=" . $id_branch . "" : '') . "
		and (date(bill.bill_date) BETWEEN '" . date('Y-m-d', strtotime($from_date)) . "' AND '" . date('Y-m-d', strtotime($to_date)) . "')
		GROUP BY bill_st.bill_det_id) as bill_st ON bill_st.bill_det_id = r.ret_bill_det_id
        
        Left join (
        SELECT bti.sold_bill_det_id,IFNULL(SUM(bti.gross_wt),0) as gross_wt
        FROM `ret_branch_transfer` bt 
        Left join ret_brch_transfer_old_metal bti on bti.transfer_id = bt.branch_transfer_id 
        WHERE transfer_item_type=3 and bti.item_type = 2 and bti.is_non_tag = 1 and bt.transfer_from_branch=" . $id_branch . "
        GROUP BY bti.sold_bill_det_id) non_tag_brch on non_tag_brch.sold_bill_det_id = d.bill_det_id
        
        WHERE b.bill_status=1  and mt.id_metal=" . $id_metal . "
        " . ($id_branch != '' ?  " and b.id_branch=" . $id_branch . "" : '') . "
		and (date(b.bill_date) BETWEEN '" . date('Y-m-d', strtotime($from_date)) . "' AND '" . date('Y-m-d', strtotime($to_date)) . "')
		AND (d.tag_id IS NULL OR d.tag_id NOT IN (
			SELECT dt2.tag_id FROM ret_bill_details dt2
			INNER JOIN ret_billing b2 ON b2.bill_id = dt2.bill_id
			WHERE b2.bill_type IN (13,14) AND b2.bill_status = 1
			AND b2.download_date IS NULL /* only IN-FLIGHT ST/SRT dispatches lock the tag; completed transfers must not blacklist it forever */
			AND dt2.tag_id IS NOT NULL
		))
		" . ($id_branch != '' ? " AND IFNULL(t.current_branch, b.id_branch) = " . intval($id_branch) . "" : '') . "");
		$result = $sql->result_array();
		foreach ($result as $items) {
			if ($items['is_non_tag'] == 0 && $items['tag_status'] == 6 && $items['btrans_tag_id'] == '') {
				if (($items['sales_mode'] == 1) && ($items['trans_to_acc_stock'] == 0)) {
					$returnData[] = $items;
				} else if (($items['sales_mode'] == 2) && ($items['gross_wt'] > $items['transfered_wt'])) {
					$returnData[] = $items;
				}
			} else if ($items['is_non_tag'] == 0 && $items['det_item_type'] == 2 && ($items['tag_id'] == '' || $items['tag_id'] == NULL) && $items['transferred_to_acc_stock'] == 0) {
				$returnData[] = $items;
			} else if ($items['is_non_tag'] == 1 && $items['non_tag_brch_det_id'] == '') {
				if ($items['transferred_to_acc_stock'] == 0) {
					$returnData[] = $items;
				}
			}
		}
		return $returnData;
	}

	/**
	 * Get Old Metal detail rows for ST purchase items
	 * Adapted from ret_brntransfer_model::old_metal_bill_details() without is_eda filter
	 */
	function st_old_metal_bill_details($from_date, $to_date, $id_branch, $metal_type)
	{
		$sql = $this->db->query("SELECT s.old_metal_sale_id as trans_id,s.gross_wt as gross_wt,s.net_wt as net_wt,s.rate as amount,est.id_old_metal_type,
		t.metal_type,DATE_FORMAT(b.bill_date,'%d-%m-%Y') as bill_date,b.pur_ref_no as bill_no,b.bill_id,'0' as is_checked,'old_metal_items' as type,s.old_metal_sale_id,
		concat('old_metal_',LOWER(met.metal)) as item_type,'1' as transfer_items,'' as tag_id,'' as is_non_tag,
		FORMAT(IFNULL(bill_st.dia_wt,0),3) as dia_wt
		FROM ret_billing b 
		LEFT JOIN ret_bill_old_metal_sale_details s ON s.bill_id=b.bill_id
		LEFT JOIN ret_estimation_old_metal_sale_details est ON est.old_metal_sale_id=s.esti_old_metal_sale_id
		LEFT JOIN ret_old_metal_type t ON t.id_metal_type=est.id_old_metal_type 
		LEFT JOIN metal as met ON met.id_metal = s.metal_type
		
		Left join (
        SELECT bti.old_metal_sale_id 
        FROM `ret_branch_transfer` bt 
        Left join ret_brch_transfer_old_metal bti on bti.transfer_id = bt.branch_transfer_id 
        WHERE (status = 1 or status = 2) and transfer_item_type=3 and bt.transfer_from_branch=" . $id_branch . "
        GROUP BY bti.old_metal_sale_id) btrans on btrans.old_metal_sale_id = s.old_metal_sale_id 
		LEFT JOIN (SELECT IFNULL(SUM(bill_st.wt),0) as dia_wt,bill_st.old_metal_sale_id
		FROM ret_billing_item_stones bill_st
		LEFT JOIN ret_bill_old_metal_sale_details s ON s.old_metal_sale_id=bill_st.old_metal_sale_id
		LEFT JOIN ret_stone st ON st.stone_id = bill_st.stone_id
		LEFT JOIN ret_billing bill ON bill.bill_id = bill_st.bill_id
		WHERE bill.bill_status=1 and st.stone_type = 1	 
		" . ($id_branch != '' ?  " and s.current_branch=" . $id_branch . "" : '') . "
		" . ($metal_type != '' ?  " and s.metal_type=" . $metal_type . "" : '') . "
		and (date(bill.bill_date) BETWEEN '" . date('Y-m-d', strtotime($from_date)) . "' AND '" . date('Y-m-d', strtotime($to_date)) . "') 
		and s.is_transferred=0
		GROUP BY bill_st.old_metal_sale_id) as bill_st ON bill_st.old_metal_sale_id = s.old_metal_sale_id
        
		WHERE b.bill_status=1 AND s.old_metal_sale_id IS NOT null AND btrans.old_metal_sale_id IS NULL
		" . ($id_branch != '' ?  " and s.current_branch=" . $id_branch . "" : '') . "
		" . ($metal_type != '' ?  " and s.metal_type=" . $metal_type . "" : '') . "
		and (date(b.bill_date) BETWEEN '" . date('Y-m-d', strtotime($from_date)) . "' AND '" . date('Y-m-d', strtotime($to_date)) . "') 
		AND s.is_transferred=0
		AND s.st_bill_id IS NULL");
		return $sql->result_array();
	}

	/**
	 * Get bill details from dispatch ST bill for mirror purchase creation.
	 * Returns item data WITHOUT tag_id — tag_id is deliberately excluded as
	 * a second line of defense to prevent stock duplication.
	 * 
	 * @param int $bill_id  The dispatch ST/SRT bill_id
	 * @return array
	 */
	function get_transfer_bill_details_for_mirror($bill_id)
	{
		$this->db->select('d.bill_det_id, d.product_id, d.design_id, d.purity, d.piece,
			d.less_wt, d.net_wt, d.gross_wt, d.calculation_based_on,
			d.item_cost, d.total_igst, d.total_sgst, d.total_cgst,
			d.item_total_tax, d.rate_per_grm, d.item_type,
			IFNULL(d.salesTransType, 1) as salesTransType', FALSE);
		$this->db->where('d.bill_id', $bill_id);
		return $this->db->get('ret_bill_details d')->result_array();
	}

	/**
	 * Copy a tag's diamond/stone rows (ret_taging_stone) onto a newly-created
	 * Sales Transfer / Sales Return Transfer bill line item. Mirrors the same
	 * copy admin_ret_billing.php does for normal sales — without this, ST/SRT
	 * bills silently carry no diamond weight/value at all.
	 */
	function copy_tag_stones_to_bill_item($tag_id, $bill_id, $bill_det_id)
	{
		if (empty($tag_id) || empty($bill_det_id)) {
			return;
		}
		$stones = $this->db->query("SELECT stone_id, pieces, wt, uom_id, rate_per_gram, amount, is_apply_in_lwt, stone_cal_type
			FROM ret_taging_stone WHERE tag_id = " . $this->db->escape($tag_id))->result_array();
		foreach ($stones as $stone) {
			$this->db->insert('ret_billing_item_stones', array(
				'bill_id'         => $bill_id,
				'bill_det_id'     => $bill_det_id,
				'stone_id'        => $stone['stone_id'],
				'pieces'          => $stone['pieces'],
				'wt'              => $stone['wt'],
				'uom_id'          => $stone['uom_id'],
				'price'           => $stone['amount'],
				'item_type'       => 1, // Sale item
				'is_apply_in_lwt' => $stone['is_apply_in_lwt'],
				'stone_cal_type'  => $stone['stone_cal_type'],
				'rate_per_gram'   => $stone['rate_per_gram'],
			));
		}
	}

	/**
	 * Copy an existing bill line item's stone rows onto a new bill line item —
	 * used when mirroring a Sales/Sales-Return Transfer dispatch bill's items
	 * into the receiving branch's auto-created purchase bill.
	 */
	function copy_bill_item_stones($source_bill_det_id, $target_bill_id, $target_bill_det_id)
	{
		if (empty($source_bill_det_id) || empty($target_bill_det_id)) {
			return;
		}
		$stones = $this->db->query("SELECT stone_id, pieces, wt, uom_id, price, is_apply_in_lwt, stone_cal_type, rate_per_gram
			FROM ret_billing_item_stones WHERE bill_det_id = " . $this->db->escape($source_bill_det_id))->result_array();
		foreach ($stones as $stone) {
			$this->db->insert('ret_billing_item_stones', array(
				'bill_id'         => $target_bill_id,
				'bill_det_id'     => $target_bill_det_id,
				'stone_id'        => $stone['stone_id'],
				'pieces'          => $stone['pieces'],
				'wt'              => $stone['wt'],
				'uom_id'          => $stone['uom_id'],
				'price'           => $stone['price'],
				'item_type'       => 1,
				'is_apply_in_lwt' => $stone['is_apply_in_lwt'],
				'stone_cal_type'  => $stone['stone_cal_type'],
				'rate_per_gram'   => $stone['rate_per_gram'],
			));
		}
	}

}

?>