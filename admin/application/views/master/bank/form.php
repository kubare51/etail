      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
          <h1>
            Bank Master 
            <small></small>
          </h1>
          <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="<?php echo base_url('index.php/settings/bank/list');?>">Master</a></li>
            <li class="active">Bank</li>
          </ol>
        </section>

        <!-- Main content -->
        <section class="content">
     
          <!-- Default box -->
          <div class="box">
            <div class="box-header with-border">
              <h3 class="box-title">Bank - <?php echo ( $bank['id_bank']!=NULL?'Edit' :'Add'); ?></h3>
              <div class="box-tools pull-right">
                <button class="btn btn-box-tool" data-widget="collapse" data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                <button class="btn btn-box-tool" data-widget="remove" data-toggle="tooltip" title="Remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <div class="box-body">
              <div class="">
				<?php echo form_open((  $bank['id_bank']!=NULL &&  $bank['id_bank']>0 ?'settings/bank/update/'.$bank['id_bank']:'settings/bank/save')) ?>
				 <div class="row">
				 	<div class="form-group">
                       <label for="chargeseme_name" class="col-md-2 col-md-offset-1 ">Bank Name <span class="error">*</span></label>
                       <div class="col-md-4">
 <input type="text" class="form-control" id="bank_name" name="bank[bank_name]" value="<?php echo set_value('bank[bank_name]',$bank['bank_name']); ?>" onkeypress="return  /^[a-zA-Z ]$/i.test(event.key)" placeholder="eg: State Bank Of India" required="true" autofocus minlength="10" maxlength="50" pattern="[A-Za-z ]+" title="Bank Name must be 10-50 alphabetic characters">                   <p class="help-block"></p>
                       	
                       </div>
                    </div>
				 </div>
				
				<div class="row">
				 	<div class="form-group">
                       <label for="chargeseme_name" class="col-md-2 col-md-offset-1 ">Short Code <span class="error">*</span></label>
                       <div class="col-md-4">
<input type="text" class="form-control" name="bank[short_code]" id="short_code" value="<?php echo set_value('bank[short_code]',$bank['short_code']); ?>" onkeypress="return /^[a-zA-Z]$/i.test(event.key)" placeholder="eg: SBI" required="true" maxlength="5" style="text-transform:uppercase" title="Short Code must be 1-5 alphabetic characters"/>               
                        <p class="help-block"></p>
                       	
                       </div>
                    </div>
				 </div>
				 
				 <div class="row">
				 	<div class="form-group">
                       <label for="chargeseme_name" class="col-md-2 col-md-offset-1 ">ACC NO <span class="error">*</span></label>
                       <div class="col-md-4">
<input type="text" class="form-control" name="bank[acc_number]" id="acc_number" value="<?php echo set_value('bank[acc_number]',$bank['acc_number']); ?>" onkeypress="return /^[0-9]$/i.test(event.key)" placeholder="eg: 11111111111" required="true" minlength="10" maxlength="17" title="Account Number must be 10-17 digits"/>	               
                        <p class="help-block"></p>
                       	
                       </div>
                    </div>
				 </div>
				 
				 <div class="row">
				 	<div class="form-group">
                       <label for="chargeseme_name" class="col-md-2 col-md-offset-1 ">IFSC <span class="error">*</span></label>
                       <div class="col-md-4">
<input type="text" class="form-control" name="bank[ifsc_code]" id="ifsc_code" value="<?php echo set_value('bank[ifsc_code]',$bank['ifsc_code']); ?>" onkeypress="return /^[a-zA-Z0-9]$/i.test(event.key)" placeholder="eg: SBIN0001234" required="true" maxlength="11" pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}" style="text-transform:uppercase" title="IFSC must be in format AAAA0NNNNNN (e.g., SBIN0001234)"/>               
                        <p class="help-block"></p>
                       	
                       </div>
                    </div>
				 </div>
 
	
				
				<br/>      
				 <div class="row col-xs-12">
				   <div class="box box-default"><br/>
					  <div class="col-xs-offset-5">
						<button type="submit" class="btn btn-primary">Save</button> 
						<button type="button" class="btn btn-default btn-cancel">Cancel</button>
						
					  </div> <br/>
					</div>
				  </div>      
				        	
               </form>              	              	
              </div>
            </div><!-- /.box-body -->
            <div class="box-footer">
              
            </div><!-- /.box-footer-->
          </div><!-- /.box -->
         

        </section><!-- /.content -->
      </div><!-- /.content-wrapper -->