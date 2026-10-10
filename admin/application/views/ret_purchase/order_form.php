<!-- Content Wrapper. Contains page content -->

    <style>

    	.remove-btn{

			margin-top: -168px;

		    margin-left: -38px;

		    background-color: #e51712 !important;

		    border: none;

		    color: white !important;

		}

		.sm{

			font-weight: normal;

		}

		/* CPO-03/06: Common PO layout.
		   The standard order handlers re-show .stock_and_cus_ord with an inline
		   css('display','block') whenever the Order For radio changes, which beats
		   any .hide() we apply. A class on <body> with !important is used instead so
		   the Common PO layout cannot be clobbered by those handlers. */
		body.common-po-active .stock_and_cus_ord,
		body.common-po-active .remark,
		body.common-po-active .cpo-hide,
		body.common-po-active .stock_order,
		body.common-po-active .customer_order,
		body.common-po-active .stock_repair {
			display: none !important;
		}

    </style>

      <div class="content-wrapper">

        <!-- Content Header (Page header) -->





        <!-- Main content -->

        <section class="content order">



          <!-- Default box -->

          <div class="box box-primary">

            <div class="box-header with-border">

              <h3 class="box-title">PURCHASE ORDER</h3>

            </div>

            <div class="box-body">

             <!-- form container -->

	             <!-- form -->

				<form id="order_submit">

				<div class="row">

				    <div class="col-md-12">

				        <div class="col-md-4">

    	                     <div class="form-group">

    	                            <label>Order For<span class="error">*</span></label></br>

    	                            <input type="radio" id="stock_order" name="order[order_for]" value="1" checked="true" <?php echo ($purchase_details[0]['order_type']==1 ? 'checked' :'') ?>> <label for="stock_order" class="custom-label" > Stock Order </label>

    	                            <input type="radio" id="cus_order" name="order[order_for]" value="2" <?php echo ($purchase_details[0]['order_type']==2 ? 'checked' :'') ?>> <label for="cus_order" class="custom-label"> Customer Order </label>

    	                            <input type="radio" id="stock_repair_order" name="order[order_for]" value="3"<?php echo ($purchase_details[0]['order_type']==3 ? 'checked' :'') ?> > <label for="stock_repair_order" class="custom-label"> Stock Repair Order </label>

    	                     </div>

    			        </div>

				        <div class="col-md-2">

    	                     <div class="form-group">

    	                       <label>Select Karigar<span class="error">*</span></label>

    								<select id="select_karigar" class="form-control" name="order[id_karigar]" style="width:100%;" tabindex="1">></select>

    	                     </div>

				        </div>



				        <div class="col-md-2 stock_repair" style="display:none;">

    	                     <div class="form-group">

    	                       <label>Select Branch<span class="error">*</span></label>

    								<select id="branch_select" class="form-control" name="order[id_branch]" style="width:100%;" tabindex="1" disabled></select>

    								<input id="id_branch" name="lt_item[id_branch]" type="hidden" value="<?php echo set_value('po_item[id_branch]',$po_item['id_branch']); ?>" />

    	                     </div>

				        </div>



				        <div class="col-md-2">

    	                     <div class="form-group">

    	                       <label>Due Date<span class="error">*</span></label>

    							<input class="form-control datemask date smith_due_dt" name="order[smith_due_dt]" placeholder="Select Due Date" data-date-format="dd-mm-yyyy" type="text" tabindex="2"/>

    	                     </div>

    			        </div>



    			        <div class="col-md-2 cpo-hide">

    	                     <div class="form-group">

    	                       <label>Customer Order No<span class="error">*</span></label>

    								<select id="select_order_no" name="order[id_customer_order]" class="form-control"  style="width:100%;" tabindex="3" disabled></select>

    	                     </div>



    			        </div>



    			        <div class="col-md-2 stock_and_cus_ord">

    	                     <div class="form-group"></br>

    	                       <button type="button" id="add_image" class="btn btn-primary" >Add Image</button>

    	                       <input type="hidden" id="order_iamges">

                               <!-- PUR-CLT02: Preview container for uploaded images -->
                               <div id="add_image_preview" style="margin-top:5px; display:flex; flex-wrap:wrap; gap:5px;"></div>

    			        </div>


    			     </div>


                    <?php /* CPO-06: header-level Rate Fixed / Rate Value do not apply to a
                             Common PO — rate type is per line item (BRS 4.1 field 11). */ ?>
                    <div class="col-md-2 cpo-hide">
                        <div class="form-group">
                            <label>Rate Fixed</label><br>
                            <input type="checkbox" id="rate_fixed" name="order[rate_type]" value="1" style="transform: scale(1.3);">
                        </div>
                    </div>

                     <div class="col-md-2 cpo-hide">
                        <div class="form-group">
                            <label>Rate Value</label>
                            <input type="number" step="any" id="rate_value" name="rate_value" class="form-control" style="width:100%;" value="<?php echo (isset($gold_rate) ? $gold_rate : ''); ?>" readonly>
                        </div>
                    </div>

                    <?php /* CPO-03: Common PO checkbox. Rendered only when the feature is enabled in ret_settings. */ ?>
                    <?php if (isset($common_po_enabled) && $common_po_enabled == 1) { ?>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Common PO</label><br>
                            <input type="checkbox" id="common_po" name="order[is_common_po]" value="1" style="transform: scale(1.3);">
                            <input type="hidden" id="common_po_unfixed_allow" value="<?php echo (isset($common_po_unfixed_allow) ? $common_po_unfixed_allow : '1'); ?>">
                        </div>
                    </div>
                    <?php } ?>

    			 </div>

    			 <div class="row stock_and_cus_ord">

    			     <div class="col-md-12">

    			        <div class="col-md-2">

    	                     <div class="form-group">

    	                       <label>Select Product<span class="error">*</span></label>

    								<select id="select_product" class="form-control"  style="width:100%;" tabindex="4"></select>

    	                     </div>

    			        </div>



    			         <div class="col-md-2 design">

    	                     <div class="form-group">

    	                       <label>Select Design<span class="error">*</span></label>

    								<select id="select_design" class="form-control"  style="width:100%;" tabindex="5"></select>

    	                     </div>

    			        </div>

    			         <div class="col-md-2 sub_design">

    	                     <div class="form-group">

    	                       <label>Sub Design<span class="error">*</span></label>

    								<select id="select_sub_design" class="form-control"  style="width:100%;" tabindex="6"></select>

    	                     </div>

    			        </div>



    			        <div class="col-md-2 stock_order">

    	                     <div class="form-group">

    	                       <label>Select Weight Range<span class="error">*</span></label>

    								<select id="select_weight_range" class="form-control"  style="width:100%;" tabindex="7"></select>

									<input type="hidden" class="id_weight_range" id="id_weight_range" >

									<input id="id_customerorder" name="order[id_customerorder]" type="hidden" value="<?php echo $purchase_details[0]['id_customerorder']?>">

									<?php
									/* PO-EDIT-5: in edit mode the controller hands over the PO
									   header and its lines. Emitted as JSON for the PO edit block in ret_purchase_order.js
									   rather than rendered as markup here, because the item
									   rows have to carry the same field names the create flow
									   posts and that shape belongs with the JS that builds it. */
									if (!empty($po_edit_head)) { ?>
									<script>
									    window.PO_EDIT = <?php echo json_encode(array(
									        'head'  => $po_edit_head,
									        'lines' => (isset($po_edit_lines) ? $po_edit_lines : array()),
									    )); ?>;
									</script>
									<?php } ?>

									<input type="hidden" id="id_karigar" value="<?php echo $purchase_details[0]['id_karigar']?>"/>

    	                     </div>

    			        </div>



    			        <div class="col-md-2 customer_order" style="display:none;">

    	                     <div class="form-group">

    	                       <label>Weight<span class="error">*</span></label>

    								<input id="order_weight" class="form-control"  style="width:100%;" tabindex="7"></select>



    	                     </div>

    			        </div>



    			        <div class="col-md-2 stock_order">

    	                     <div class="form-group">

    	                       <label>Select Size</label>

    								<select id="select_size" class="form-control"  style="width:100%;" tabindex="8"></select>

    	                     </div>

    			        </div>



    			        <div class="col-md-2 pcs">

    	                     <div class="form-group">

    	                       <label>Pcs<span class="error">*</span></label>

    							<input type="number" class="form-control" id="tot_pcs" tabindex="9">

    	                     </div>

    			        </div>



				    </div>

				</div>

				<?php /* ================= CPO-03: Common PO bullion entry block =================
				     Rendered only when common_po_enabled = 1 and hidden until the Common PO
				     checkbox is ticked. Kept as a self-contained block with cpo_-prefixed IDs
				     rather than woven into the existing item entry row, so none of the
				     established Stock / Customer / Repair order flows are touched.
				     Field order follows BRS section 4.1 fields 1-15.
				     Enable/disable behaviour for Rate Type = Fixed/Unfixed is CPO-04.
				     ========================================================================= */ ?>
				<?php if (isset($common_po_enabled) && $common_po_enabled == 1) { ?>
				<div class="row common_po_block" style="display:none;">
				    <div class="col-md-12">

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Category<span class="error">*</span></label>
				                <select id="cpo_category" class="form-control" style="width:100%;">
				                    <option value="">Select Category</option>
				                    <?php foreach ((isset($common_po_categories) ? $common_po_categories : array()) as $cat) { ?>
				                        <option value="<?php echo $cat['id_ret_category']; ?>"><?php echo html_escape($cat['name']); ?></option>
				                    <?php } ?>
				                </select>
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Purity<span class="error">*</span></label>
				                <select id="cpo_purity" class="form-control" style="width:100%;">
				                    <option value="">Select Purity</option>
				                </select>
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Product<span class="error">*</span></label>
				                <select id="cpo_product" class="form-control" style="width:100%;">
				                    <option value="">Select Product</option>
				                </select>
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Design</label>
				                <select id="cpo_design" class="form-control" style="width:100%;">
				                    <option value="">Select Design</option>
				                </select>
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Sub Design</label>
				                <select id="cpo_sub_design" class="form-control" style="width:100%;">
				                    <option value="">Select Sub Design</option>
				                </select>
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Size</label>
				                <input type="text" id="cpo_size" class="form-control" maxlength="100">
				            </div>
				        </div>

				    </div>

				    <div class="col-md-12">

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Pcs<span class="error">*</span></label>
				                <input type="number" id="cpo_pcs" class="form-control" min="0" step="1">
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Gross Wt (g)<span class="error">*</span></label>
				                <input type="number" id="cpo_gross_wt" class="form-control" min="0" step="0.001">
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Stone Wt (g)</label>
				                <input type="number" id="cpo_stone_wt" class="form-control" min="0" step="0.001" value="0.000">
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Net Wt (g)</label>
				                <input type="number" id="cpo_net_wt" class="form-control" step="0.001" readonly>
				            </div>
				        </div>

				        <?php /* CPO-05b: bullion is valued on FINE weight, matching Supplier Bill
				                 Entry (item_cost = pure_wt x rate) and ret_karigar.karigar_calc_type.
				                 pure_wt = net_wt x purity / 100. The basis is selectable so a client
				                 can bill on gross or net if their supplier agreement says so, but it
				                 defaults to PureWt exactly like gst_bill_rate_basis does. */ ?>
				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Pure Wt (g)</label>
				                <input type="number" id="cpo_pure_wt" class="form-control" step="0.001" readonly>
				                <input type="hidden" id="cpo_purity_value" value="0">
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Rate Basis<span class="error">*</span></label>
				                <select id="cpo_rate_basis" class="form-control" style="width:100%;">
				                    <option value="0" selected>Pure Wt</option>
				                    <option value="1">Gross Wt</option>
				                    <option value="2">Net Wt</option>
				                </select>
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Rate Type<span class="error">*</span></label>
				                <select id="cpo_rate_type" class="form-control" style="width:100%;">
				                    <option value="1">Fixed</option>
				                    <option value="2">Unfixed</option>
				                </select>
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Rate / Gram<span class="error">*</span></label>
				                <input type="number" id="cpo_rate_per_gram" class="form-control cpo_fixed_only" min="0" step="0.01">
				            </div>
				        </div>

				    </div>

				    <div class="col-md-12">

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Taxable Amount</label>
				                <input type="number" id="cpo_taxable_amount" class="form-control cpo_fixed_only" step="0.01" readonly>
				            </div>
				        </div>

				        <?php /* CPO-05a: Tax % is DERIVED from the product's purchase tax group,
				                 exactly as Supplier Bill Entry derives it. Readonly by design —
				                 a typed percentage could disagree with the bill raised later. */ ?>
				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Tax % <small class="sm" id="cpo_tax_group_name"></small></label>
				                <input type="number" id="cpo_tax_percent" class="form-control cpo_fixed_only" min="0" step="0.01" value="0.00" readonly>
				                <input type="hidden" id="cpo_tax_group_id" value="">
				                <input type="hidden" id="cpo_tax_calc_type" value="2">
				                <small class="sm" id="cpo_tax_basis"></small>
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Tax Amount</label>
				                <input type="number" id="cpo_tax_amount" class="form-control cpo_fixed_only" step="0.01" readonly>
				            </div>
				        </div>

				        <div class="col-md-2">
				            <div class="form-group">
				                <label>Total Amount</label>
				                <input type="number" id="cpo_total_amount" class="form-control cpo_fixed_only" step="0.01" readonly>
				            </div>
				        </div>

				        <div class="col-md-4">
				            <div class="form-group"><br>
				                <button type="button" id="cpo_add_item" class="btn btn-primary">Add Bullion Item</button>
				                <button type="button" id="cpo_clear_item" class="btn btn-default">Clear</button>
				                <input type="hidden" id="cpo_edit_row" value="">
				            </div>
				        </div>

				    </div>
				</div>

				<div class="row common_po_block" style="display:none;">
				    <div class="col-md-12">
				        <div class="table-responsive">
				            <table id="cpo_item_detail" class="table table-bordered table-striped">
				                <thead>
				                    <tr>
				                        <th>Category</th>
				                        <th>Purity</th>
				                        <th>Product</th>
				                        <th>Design</th>
				                        <th>Sub Design</th>
				                        <th>Size</th>
				                        <th>Pcs</th>
				                        <th>Gross Wt</th>
				                        <th>Stone Wt</th>
				                        <th>Net Wt</th>
				                        <th>Rate Type</th>
				                        <th>Rate/Gram</th>
				                        <th>Taxable</th>
				                        <th>Tax</th>
				                        <th>Total</th>
				                        <th>Action</th>
				                    </tr>
				                </thead>
				                <tbody></tbody>
				                <tfoot>
				                    <tr style="font-weight:bold;">
				                        <td colspan="6" style="text-align:center;">Total</td>
				                        <td><span class="cpo_tot_pcs">0</span><input type="hidden" id="cpo_order_pcs" name="order[cpo_order_pcs]"></td>
				                        <td><span class="cpo_tot_gross_wt">0.000</span></td>
				                        <td><span class="cpo_tot_stone_wt">0.000</span></td>
				                        <td><span class="cpo_tot_net_wt">0.000</span><input type="hidden" id="cpo_order_wt" name="order[cpo_order_wt]"></td>
				                        <td colspan="2"></td>
				                        <td><span class="cpo_tot_taxable">0.00</span></td>
				                        <td><span class="cpo_tot_tax">0.00</span></td>
				                        <td><span class="cpo_tot_amount">0.00</span><input type="hidden" id="cpo_grand_total" name="order[cpo_grand_total]"></td>
				                        <td></td>
				                    </tr>
				                </tfoot>
				            </table>
				        </div>
				    </div>
				</div>
				<?php } ?>
				<?php /* ================= end CPO-03 block ================= */ ?>

				<div class="row">

				    <div class="col-md-12 remark">

				        <div class="col-sm-10">

				 			<div class="form-group">

							 <label>Note</label>

					 			<div class="input-group">

					 				<textarea class="form-control" id="remark" rows="5" cols="100"> </textarea>

								</div>

							</div>

				 		</div>

				    </div>

				</div>





				<?php /* CPO-06: this Add Item / Cancel pair drives the standard (non-bullion)
				         entry row. Common PO has its own "Add Bullion Item" button inside its
				         block, so this row is hidden while Common PO is active. The form-level
				         save/Cancel further down is NOT hidden — that submits the PO. */ ?>
				<div class="row cpo-hide">

				    <div class="col-sm-12" align="center">

						<button type="button" id="add_order_item" class="btn btn-primary">Add Item</button>

						<button type="button" id="order_item_cancel" class="btn btn-default btn-cancel">Cancel</button>

				    </div>

				</div>







				<div class="row">

					<div class="col-md-12">

					    <input type="hidden" id="i_increment">

					    <?php /* CPO-06: standard item table — hidden under Common PO, which
					             renders its own #cpo_item_detail table instead. */ ?>
					    <div class="table-responsive item_detailed cpo-hide" >

						<p class="help-block"></p></legend>

						 <table id="item_detail" class="table table-bordered table-striped">

						 <input type="hidden" id="custom_active_id">



							<thead>

						          <tr>

									<th width="15%;">Image</th>



						            <th width="15%;">Product</th>

						            <th width="15%;">Design</th>

						            <th width="15%;">Sub Design</th>

						            <th width="10%;">Size</th>

						            <th width="10%;">Wgt Range</th>

						            <th width="10%;">Approx Wt</th>

						            <th width="10%;">Pcs</th>

									<th width="10%;">Remarks</th>

						            <th width="10%;">Due Date</th>

						            <th width="10%;">Action</th>

						          </tr>

					         </thead>

					         <tbody>

    							 <?php if($this->uri->segment(3) == 'edit')

    							 foreach($item_details as $ikey => $ival){

                                    {

                                    	echo '<tr>

                                    			<td><div class="product_name">'.$ival['product_name'].'</div><input class="id_product" type="hidden" name="order_details[product][]" value="'.$ival['id_product'].'" /></td>

                                    			<td><div class="design_name">'.$ival['design_name'].'</div><input class="design_no" type="hidden" name="order_details[design][]" value="'.$ival['design_no'].'" /></td>

                                    			<td><div class="sub_design_name">'.$ival['sub_design_name'].'</div><input class="id_sub_design" type="hidden" name="order_details[sub_design][]" value="'.$ival['id_sub_design'].'" /></td>

                                    			<td><div class="size">'.$ival['size'].'</div><input class="id_size" type="hidden" name="order_details[size][]" value="'.$ival['id_size'].'" /></td>

                                    			<td><div class="weight_range">'.$ival['weight_range'].'</div><input class="id_weight_range" type="hidden" name="order_details[weight_range][]" value="'.$ival['id_weight_range'].'" /></td>

                                    			<td><div class="weight">'.$ival['weight'].'</div><input class="approx_wt" type="hidden" name="order_details[order_wt][]" value="'.$ival['weight'].'" /></td>

                                    			<td><div class="tot_items">'.$ival['tot_items'].'</div><input class="piece" type="hidden" name="order_details[piece][]" value="'.$ival['tot_items'].'" /></td>

                                    			<td><div class="smith_due_date">'.$ival['smith_due_date'].'</div><input type="hidden" name="order_details[due_date][]" value="'.$ival['smith_due_date'].'" /></td>

                                    			<td><a href="#" onClick="edit_purchaseorder_items($(this).closest(\'tr\'))" class="btn btn-success btn-edit"style="padding:5px;"  data-id ><i class="fa fa-edit" ></i></a><a href="#" onClick="remove_purchase_order_row($(this).closest(\'tr\'))" class="btn btn-danger btn-del btn-md" style="padding:5px;" ><i class="fa fa-trash"></i></a></td>

                                    	</tr>';

                                    	}

                                    }

        							?>

					         </tbody>



					         <tfoot>

					             <tr style="font-weight:bold;">



					                 <td colspan="6" style="text-align:center;">Total</td>

					                 <td><input type="hidden" class="order_wt" id="order_wt" name="order[order_wt]"><span class="tot_wt"></span></td>

					                 <td><input type="hidden" class="order_pcs" id="order_pcs" name="order[order_pcs]"><span class="tot_pcs"></span></td>

					                 <td></td>

									 <td></td>

					                 <td></td>

					             </tr>

					         </tfoot>

						</table>

					    </div>

					</div>

				</div>



				<div class="row">

					<div class="col-md-12">

					    <div class="table-responsive purchase_detailed cpo-hide" style="display:none">

						<p class="help-block"></p></legend>

						 <table id="purchase_details" class="table table-bordered table-striped">

							<thead>

						          <tr>

								    <th width="15%;">Image</th>

									<th class="tag_code" width="15%;">Tag Code</th>

						            <th width="15%;">Product</th>

						            <th width="15%;">Design</th>

						            <th width="15%;">Sub Design</th>

						            <th width="10%;">Size</th>

						            <th width="10%;">Pcs</th>

									<th width="10%;">GWT</th>

									<th width="10%;">LWT</th>

						            <th width="10%;">NWT</th>

						            <th width="10%;">Stone Value</th>

									<th width="10%;">Wastage %</th>

						            <th width="10%;">MC Value</th>

									<th width="10%;">Remarks</th>

						            <th width="10%;">Description</th>

						            <th width="10%;">Action</th>

						          </tr>

					         </thead>

					         <tbody>

    							 <?php if($this->uri->segment(3) == 'edit')

    							 foreach($item_details as $ikey => $ival){

                                    {

                                    	echo '<tr>

                                    			<td><div class="product_name">'.$ival['product_name'].'</div><input class="id_product" type="hidden" name="order_details[product][]" value="'.$ival['id_product'].'" /></td>

                                    			<td><div class="design_name">'.$ival['design_name'].'</div><input class="design_no" type="hidden" name="order_details[design][]" value="'.$ival['design_no'].'" /></td>

                                    			<td><div class="sub_design_name">'.$ival['sub_design_name'].'</div><input class="id_sub_design" type="hidden" name="order_details[sub_design][]" value="'.$ival['id_sub_design'].'" /></td>

                                    			<td><div class="size">'.$ival['size'].'</div><input class="id_size" type="hidden" name="order_details[size][]" value="'.$ival['id_size'].'" /></td>

                                    			<td><div class="weight_range">'.$ival['weight_range'].'</div><input class="id_weight_range" type="hidden" name="order_details[weight_range][]" value="'.$ival['id_weight_range'].'" /></td>

                                    			<td><div class="weight">'.$ival['weight'].'</div><input class="approx_wt" type="hidden" name="order_details[order_wt][]" value="'.$ival['weight'].'" /></td>

                                    			<td><div class="tot_items">'.$ival['tot_items'].'</div><input class="piece" type="hidden" name="order_details[piece][]" value="'.$ival['tot_items'].'" /></td>

                                    			<td><div class="smith_due_date">'.$ival['smith_due_date'].'</div><input type="hidden" name="order_details[due_date][]" value="'.$ival['smith_due_date'].'" /></td>

                                    			<td><a href="#" onClick="edit_purchaseorder_items($(this).closest(\'tr\'))" class="btn btn-success btn-edit"style="padding:5px;"  data-id ><i class="fa fa-edit" ></i></a><a href="#" onClick="remove_purchase_order_row($(this).closest(\'tr\'))" class="btn btn-danger btn-del btn-md" style="padding:5px;" ><i class="fa fa-trash"></i></a></td>

                                    	</tr>';

                                    	}

                                    }

        							?>

					         </tbody>



					         <tfoot>

					             <tr style="font-weight:bold;">

								 	<td class="tag_code" style="display:none;" ></td>

					                 <td colspan="5" style="text-align:center;">Total</td>

					                 <td><input type="hidden" class="order_pcs" id="order_pcs" name="order[order_pcs]"><span class="tot_pcs"></span></td>

									 <td><input type="hidden" class="order_wt" id="order_wt" name="order[order_wt]"><span class="tot_wt"></span></td>

									 <td></td>

					             </tr>

					         </tfoot>

						</table>

					    </div>

					</div>

				</div>









				<p class="help-block"></p>





				<div class="row">

				    <div class="col-sm-12" align="center">

						<button type="button" id="create_order" class="btn btn-primary">save</button>

						<button type="button" class="btn btn-default btn-cancel">Cancel</button>

				    </div>

				</div>



	           </div>

	            <?php echo form_close();?>



	       </div>

	       <div class="overlay" style="display:none">

				  <i class="fa fa-refresh fa-spin"></i>

				</div>

        </section>

    </div>





<!--  Image Upload-->

<div class="modal fade" id="imageModal_new" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">

    <div class="modal-dialog" style="width:60%;">

		<div class="modal-content">

			<div class="modal-header">

				<h4 class="modal-title" id="myModalLabel">Add Image</h4>

			</div>

			<div class="modal-body" style="height: 200px;">

           <div class="ord_img">

              Add Image

            <input id="order_images_new" class="order_images_new" name="order_images_new" accept="image/*" type="file" multiple="true">

        </div>

        <br>

         <div id="order_images" style="margin-top: 2%;"></div>

		</div>



		  <div class="modal-footer">

			<button type="button" id="update_img_new" class="btn btn-success">Save</button>

			<button type="button" id="close_stone_details_new" class="btn btn-warning" data-dismiss="modal">Close</button>

		  </div>

		</div>

	</div>

</div>

</div>





<!--  Image Upload-->



<div class="modal fade" id="order_des" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">



  	<div class="modal-dialog">



    	<div class="modal-content">



      		<div class="modal-header">



        		<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>



        		<h4 class="modal-title" id="myModalLabel">Add Description</h4>



      		</div>



      		<div class="modal-body">



				<div class="row">



        			<div class="col-md-10 col-md-offset-1">



        				<label for="user_lastname">Item Description</label>



						<div class='form-group'>



			               	<textarea  cols="70"  id="description" name="description" ></textarea>



			        	</div>



			    	</div>



			    </div>



			</div>



			<div class="modal-footer">



				<a href="#" class="btn btn-success add_order_desc">Add</a>



				<button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>



			</div>



		</div>



	</div>



</div>









<div class="modal fade" id="imageModal_bulk_edit" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">

      <div class="modal-dialog" style="width:90%;">

          <div class="modal-content">

              <div class="modal-header">

                  <h4 class="modal-title" id="myModalLabel">Image Preview</h4>

              </div>

              <div class="modal-body">

    			  <div class="row">

                  	<div id="order_images_preview" style="margin-top: 2%;"></div>

    			  </div>

              </div>

              <div class="modal-footer">

                  </br>

                  <button type="button" id="close_stone_details" class="btn btn-warning"

                      data-dismiss="modal">Close</button>

              </div>

          </div>

      </div>

</div>





<div class="modal fade" id="imageModal_preview" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">

    <div class="modal-dialog" style="width: 90%;">

        <div class="modal-content">

            <div class="modal-header">

                <h4 class="modal-title" id="myModalLabel">Image Preview</h4>

            </div>

            <div class="modal-body">

                <div class="row">

                    <div id="modalImageContainer" style="margin-top: 2%;">

                        <img id="modalImage" src="" width="20%" height="auto" />

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button type="button" id="close_stone_details" class="btn btn-warning" data-dismiss="modal">Close</button>

            </div>

        </div>

    </div>

</div>


<div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="width:60%;">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Image Preview</h4>
			</div>
			<input type="file" name="pur_order_images" id="pur_order_images" multiple="multiple">
			<input type="hidden" id="active_row">
			<div class="modal-body">
			<div id="uploadArea_p_stn" class="col-md-12"></div>

			</div>

		  <div  class="modal-footer">

		  	<button type="button"  id="update_pur_img" class="btn btn-success">Save</button>

			<button type="button" id="close_stone_details" class="btn btn-warning" data-dismiss="modal">Close</button>

		  </div>

		</div>

	</div>

</div>



<div class="modal fade" id="cus_stoneModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">

	<div class="modal-dialog" style="width:72%;">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Add Stone</h4>
			</div>
			<div class="modal-body">
				<div class="row">
					<div class="box-tools pull-right">
					</div>
				</div>
				<div class="row">
					<table id="estimation_stone_cus_item_details" class="table table-bordered table-striped text-center">
						<thead>
							<tr>
								<th width="5%">LWT</th>
								<th width="15%">Type</th>
								<th width="15%">Name</th>
								<th width="10%">Pcs</th>
								<th width="20%">Wt</th>
								<th width="10%">Cal.Type</th>
								<th width="15%">Rate</th>
								<th width="17%">Amount</th>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
		  	</div>
		  	<div class="modal-footer">
				<button type="button" id="close_stone_details" class="btn btn-warning" data-dismiss="modal">Close</button>
		  	</div>
		</div>
	</div>
</div>
