      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
          <h1>
        	Tagging
            <small>Wholesale Order Link</small>
          </h1>
          <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="#">Tagging</a></li>
            <li class="active">Wholesale Order Link</li>
          </ol>
        </section>

        <!-- Main content -->
        <section class="content product">
          <!-- Default box -->
          <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">Wholesale Order Link</h3>
            </div>
            <div class="box-body">
			<div class="row">
				<div class="col-sm-12"> 
					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label><a data-toggle="tooltip" title="Search Wholesale Order">Search Wholesale Order</a></label>
								<select id="select_wholesale_order" class="form-control" style="width:100%;"></select>
							</div>
						</div>
				 	</div> 	
				 	<p class="help-block"></p>			 
				</div>	
			</div>	
			
			<div class="row">
			    <div class="col-md-12">
			        <div class="box box-success">
			            <div class="box-header"><h3 class="box-title">Order Details</h3></div>
			            <div class="box-body table-responsive">
			                <table class="table table-bordered table-striped" id="order_details_table">
								<thead>
			                    <tr>
									<th>Order No</th>
									<th>Category</th>
									<th>Design</th>
									<th>Customer</th>
									<th>Tag No</th>
									<th>Action</th>
								</tr>
								</thead>
								<tbody id="ws_order_details_body">
									<tr><td colspan="6" class="text-center">Select an order to view details</td></tr>
								</tbody>
			                </table>
			            </div>
			        </div>
			    </div>
			</div>
			
			   <p class="help-block"> </p> 
			     <div class="row" id="ws_save_container" style="display:none;">
				   <div class="box box-default"><br/>
					  <div class="col-xs-offset-5">
						<button type="submit"  class="btn btn-primary" id="ws_tag_link_submit">Save Mapping</button> 
						<button type="button" class="btn btn-default btn-cancel">Cancel</button>
					  </div> <br/>
					</div>
				  </div> 
	            </div> 
	            <div class="overlay" style="display:none">
				  <i class="fa fa-refresh fa-spin"></i>
				</div>
	          </div>
             </section>
</div>
