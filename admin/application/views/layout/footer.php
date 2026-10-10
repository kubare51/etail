<?php $comp_details=$this->admin_settings_model->get_company(); $version = $this->config->item('version'); ?>
<?php $versiondb=$this->admin_settings_model->get_last_version(); ?>
<?php $inactivity_time = $this->admin_settings_model->get_inactivity_time(); ?>
 <footer class="main-footer">
        <div class="pull-right hidden-xs">
         <!-- <b>Version</b> 1.0 <strong>{elapsed_time}</strong> seconds -->
		 <?php if (!get_flag('hide_company_logo')): ?>
		 Powered by <a href="http://www.logimaxindia.com" target="_blank"><img src="<?php echo base_url('assets/img/logimax.png');?>"/></a>
		 <?php endif; ?>
        </div>
        <?php if (!get_flag('hide_company_logo')): ?>
        <strong>Copyright &copy; <?php echo date("Y"); ?> <a href="<?php echo $comp_details['website']; ?>" target="_blank"><?php echo $comp_details['company_name'];?></a>.</strong> All rights reserved.
        <?php endif; ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        version : <strong><?php echo $versiondb['version_no'] ;?></strong>
      </footer>
      <!-- Control Sidebar -->
      <aside class="control-sidebar control-sidebar-dark">
        <!-- Create the tabs -->
        <ul class="nav nav-tabs nav-justified control-sidebar-tabs">
          <li><a href="#control-sidebar-home-tab" data-toggle="tab"><i class="fa fa-home"></i></a></li>
          <li><a href="#control-sidebar-settings-tab" data-toggle="tab"><i class="fa fa-gears"></i></a></li>
        </ul>
        <!-- Tab panes -->
        <div class="tab-content">
          <!-- Home tab content -->
          <div class="tab-pane" id="control-sidebar-home-tab">
            <h3 class="control-sidebar-heading">Recent Activity</h3>
            <ul class='control-sidebar-menu'>
              <li>
                <a href='javascript::;'>
                  <i class="menu-icon fa fa-birthday-cake bg-red"></i>
                  <div class="menu-info">
                    <h4 class="control-sidebar-subheading">Langdon's Birthday</h4>
                    <p>Will be 23 on April 24th</p>
                  </div>
                </a>
              </li>
              <li>
                <a href='javascript::;'>
                  <i class="menu-icon fa fa-user bg-yellow"></i>
                  <div class="menu-info">
                    <h4 class="control-sidebar-subheading">Frodo Updated His Profile</h4>
                    <p>New phone +1(800)555-1234</p>
                  </div>
                </a>
              </li>
              <li>
                <a href='javascript::;'>
                  <i class="menu-icon fa fa-envelope-o bg-light-blue"></i>
                  <div class="menu-info">
                    <h4 class="control-sidebar-subheading">Nora Joined Mailing List</h4>
                    <p>nora@example.com</p>
                  </div>
                </a>
              </li>
              <li>
                <a href='javascript::;'>
                  <i class="menu-icon fa fa-file-code-o bg-green"></i>
                  <div class="menu-info">
                    <h4 class="control-sidebar-subheading">Cron Job 254 Executed</h4>
                    <p>Execution time 5 seconds</p>
                  </div>
                </a>
              </li>
            </ul><!-- /.control-sidebar-menu -->
            <h3 class="control-sidebar-heading">Tasks Progress</h3>
            <ul class='control-sidebar-menu'>
              <li>
                <a href='javascript::;'>
                  <h4 class="control-sidebar-subheading">
                    Custom Template Design
                    <span class="label label-danger pull-right">70%</span>
                  </h4>
                  <div class="progress progress-xxs">
                    <div class="progress-bar progress-bar-danger" style="width: 70%"></div>
                  </div>
                </a>
              </li>
              <li>
                <a href='javascript::;'>
                  <h4 class="control-sidebar-subheading">
                    Update Resume
                    <span class="label label-success pull-right">95%</span>
                  </h4>
                  <div class="progress progress-xxs">
                    <div class="progress-bar progress-bar-success" style="width: 95%"></div>
                  </div>
                </a>
              </li>
              <li>
                <a href='javascript::;'>
                  <h4 class="control-sidebar-subheading">
                    Laravel Integration
                    <span class="label label-waring pull-right">50%</span>
                  </h4>
                  <div class="progress progress-xxs">
                    <div class="progress-bar progress-bar-warning" style="width: 50%"></div>
                  </div>
                </a>
              </li>
              <li>
                <a href='javascript::;'>
                  <h4 class="control-sidebar-subheading">
                    Back End Framework
                    <span class="label label-primary pull-right">68%</span>
                  </h4>
                  <div class="progress progress-xxs">
                    <div class="progress-bar progress-bar-primary" style="width: 68%"></div>
                  </div>
                </a>
              </li>
            </ul><!-- /.control-sidebar-menu -->
          </div><!-- /.tab-pane -->
          <!-- Stats tab content -->
          <div class="tab-pane" id="control-sidebar-stats-tab">Stats Tab Content</div><!-- /.tab-pane -->
          <!-- Settings tab content -->
          <div class="tab-pane" id="control-sidebar-settings-tab">
            <form method="post">
              <h3 class="control-sidebar-heading">General Settings</h3>
              <div class="form-group">
                <label class="control-sidebar-subheading">
                  Report panel usage
                  <input type="checkbox" class="pull-right" checked />
                </label>
                <p>
                  Some information about this general settings option
                </p>
              </div><!-- /.form-group -->
              <div class="form-group">
                <label class="control-sidebar-subheading">
                  Allow mail redirect
                  <input type="checkbox" class="pull-right" checked />
                </label>
                <p>
                  Other sets of options are available
                </p>
              </div><!-- /.form-group -->
              <div class="form-group">
                <label class="control-sidebar-subheading">
                  Expose author name in posts
                  <input type="checkbox" class="pull-right" checked />
                </label>
                <p>
                  Allow the user to show his name in blog posts
                </p>
              </div><!-- /.form-group -->
              <h3 class="control-sidebar-heading">Chat Settings</h3>
              <div class="form-group">
                <label class="control-sidebar-subheading">
                  Show me as online
                  <input type="checkbox" class="pull-right" checked />
                </label>
              </div><!-- /.form-group -->
              <div class="form-group">
                <label class="control-sidebar-subheading">
                  Turn off notifications
                  <input type="checkbox" class="pull-right" />
                </label>
              </div><!-- /.form-group -->
              <div class="form-group">
                <label class="control-sidebar-subheading">
                  Delete chat history
                  <a href="javascript::;" class="text-red pull-right"><i class="fa fa-trash-o"></i></a>
                </label>
              </div><!-- /.form-group -->
            </form>
          </div><!-- /.tab-pane -->
        </div>
      </aside><!-- /.control-sidebar -->
      <!-- Add the sidebar's background. This div must be placed
           immediately after the control sidebar -->
      <div class='control-sidebar-bg'></div>
    </div><!-- ./wrapper -->
 <!-- jQuery 2.1.4 -->
    <?php if($this->uri->segment(1)!='reports'){ ?>
    <!-- jQuery 2.1.4 -->
    <script src="<?php echo base_url(); ?>assets/plugins/jQuery/jQuery-2.1.4.min.js"></script>
<?php }else{?>
<script src="<?php echo base_url(); ?>assets\plugins\datatables\report_print/jquery-1.12.4.js"></script>
<script src="<?php echo base_url(); ?>assets\plugins\datatables\report_print/jquery.dataTables.min.js"></script>
<script src="<?php echo base_url(); ?>assets\plugins\datatables\report_print/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.3.1/js/buttons.html5.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.15/css/jquery.dataTables.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.3.1/css/buttons.dataTables.min.css">
 <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
<!--<script type="text/javascript" src="https://code.jquery.com/jquery-1.12.4.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.15/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.3.1/js/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.3.1/js/buttons.print.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.15/css/jquery.dataTables.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.3.1/css/buttons.dataTables.min.css">-->
<?php }?>
<?php if($this->uri->segment(2) =='payment_daterange'){ ?>
<script src="<?php echo base_url(); ?>assets\plugins\datatables\report_print/buttons.print.min1.js"></script>
<?php }?>
<?php if($this->uri->segment(2)=='payment_modewise_data' || $this->uri->segment(2)=='payment_details'  || $this->uri->segment(2)=='payment_schemewise'  || $this->uri->segment(2)=='paydatewise_schcoll_data'){ ?>
<script src="<?php echo base_url(); ?>assets\plugins\datatables\report_print/buttons.print.min1.js"></script>
<?php }?>
<?php if($this->uri->segment(2)=='payment_datewise_schemedata'){ ?>
<script src="<?php echo base_url(); ?>assets\plugins\datatables\report_print/buttons.print.min2.js"></script>
<?php }?>
<?php if($this->uri->segment(2)=='payment_outstanding'){ ?>
<script src="<?php echo base_url(); ?>assets\plugins\datatables\report_print/buttons.print.min1.js"></script>
<?php }?>
<?php if($this->uri->segment(2)=='employee_ref_success'){ ?>
<script src="<?php echo base_url(); ?>assets\plugins\datatables\report_print/buttons.print.min1.js"></script>
<?php }?>
<?php if($this->uri->segment(2)=='cus_ref_success'){ ?>
<script src="<?php echo base_url(); ?>assets\plugins\datatables\report_print/buttons.print.min1.js"></script>
<?php }?>
    <!-- jQuery UI 1.11.2 -->
    <script src="https://code.jquery.com/ui/1.11.2/jquery-ui.min.js" type="text/javascript"></script>
    <!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
    <script>
      $.widget.bridge('uibutton', $.ui.button);
    </script>
    <!-- Bootstrap 3.3.2 JS -->
    <script src="<?php echo base_url(); ?>assets/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
     <script src="<?php echo base_url(); ?>assets/dist/jstree.min.js"></script>
    <!-- Morris.js charts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/raphael/2.1.0/raphael-min.js"></script>
    <script src="<?php echo base_url(); ?>assets/plugins/morris/morris.min.js" type="text/javascript"></script>
    <!-- Sparkline -->
    <script src="<?php echo base_url(); ?>assets/plugins/sparkline/jquery.sparkline.min.js" type="text/javascript"></script>

	<script src="<?php echo base_url(); ?>assets/plugins/typehead/bootstrap-typeahead.js" type="text/javascript"></script>
    <!-- jvectormap -->
    <script src="<?php echo base_url(); ?>assets/plugins/jvectormap/jquery-jvectormap-1.2.2.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>assets/plugins/jvectormap/jquery-jvectormap-world-mill-en.js" type="text/javascript"></script>
    <!-- jQuery Knob Chart -->
    <script src="<?php echo base_url(); ?>assets/plugins/knob/jquery.knob.js" type="text/javascript"></script>
    <!-- jQuery Chart JS-->
    <!-- <script src="<?php echo base_url(); ?>assets/plugins/chartjs/Chart.js" type="text/javascript"></script> -->
    <!-- moment -->
    <script src="<?php echo base_url(); ?>assets/plugins/moment/moment.js" type="text/javascript"></script>
    <!-- daterangepicker -->
    <script src="<?php echo base_url(); ?>assets/plugins/daterangepicker/daterangepicker.js" type="text/javascript"></script>
    <!-- datepicker -->
    <script src="<?php echo base_url(); ?>assets/plugins/datepicker/bootstrap-datepicker.js" type="text/javascript"></script>
<!-- Datetime picker -->
     <script src="<?php echo base_url(); ?>assets/plugins/bootstrap-datetimepicker/js/bootstrap-datetimepicker.js" type="text/javascript"></script>

     <!-- Initial -->
    <script src="<?php echo base_url(); ?>assets/plugins/initial/initial.js" type="text/javascript"></script>
    <!-- Bootstrap WYSIHTML5 -->
    <script src="<?php echo base_url(); ?>assets/plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.all.min.js" type="text/javascript"></script>
    <!-- Slimscroll -->
    <script src="<?php echo base_url(); ?>assets/plugins/slimScroll/jquery.slimscroll.min.js" type="text/javascript"></script>
    <!-- iCheck 1.0.1 -->
    <script src="<?php echo base_url(); ?>assets/plugins/iCheck/icheck.min.js"></script>
      <!-- Select2 -->
    <script src="<?php echo base_url(); ?>assets/plugins/select2/select2.full.min.js"></script>
    <!-- Fix: Bootstrap Modal + Select2 focus trap conflict -->
    <script>
    if ($.fn.modal && $.fn.modal.Constructor) {
        $.fn.modal.Constructor.prototype.enforceFocus = function() {
            $(document).off('focusin.bs.modal').on('focusin.bs.modal', $.proxy(function(e) {
                if (this.$element[0] !== e.target && !this.$element.has(e.target).length && !$(e.target).closest('.select2-dropdown').length) {
                    this.$element.trigger('focus');
                }
            }, this));
        };
    }
    </script>
    <!-- FastClick -->
    <script src='<?php echo base_url(); ?>assets/plugins/fastclick/fastclick.min.js'></script>
    <!-- AdminLTE App -->
    <script src="<?php echo base_url(); ?>assets/dist/js/app.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>assets/plugins/datatables/jquery.dataTables.min.js" type="text/javascript"></script>
	    <script src="<?php echo base_url(); ?>assets/plugins/bootstrap-switch/bootstrap-switch.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>assets/plugins/datatables/dataTables.bootstrap.min.js" type="text/javascript"></script>
    <script type="text/javascript" src="<?php echo base_url(); ?>assets/plugins/datetime-moment/datetime-moment.js"></script>
    <script>
    $.fn.dataTable.moment('DD-MM-YYYY');
    </script>
	   <!-- InputMask -->
     	<script src="<?php echo base_url(); ?>assets/plugins/input-mask/jquery.inputmask.js" type="text/javascript"></script>
     	<script src="<?php echo base_url(); ?>assets/plugins/input-mask/jquery.inputmask.date.extensions.js" type="text/javascript"></script>
     	<script src="<?php echo base_url(); ?>assets/plugins/input-mask/jquery.inputmask.extensions.js" type="text/javascript"></script>
    <!-- jQuery Knob -->
<script src="<?php echo base_url(); ?>assets/plugins/knob/jquery.knob.js"></script>


    <!-- jQuery Toaster -->
        <script src="<?php echo base_url(); ?>assets/plugins/toaster/jquery.toaster.js"></script>
   <!-- jQuery Toaster -->

    <!-- Centralized Validation Library (MUST load before any module JS) -->
    <script src="<?= get_asset_url('js/validation.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>

    <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
   <!-- <script src="<?php echo base_url(); ?>assets/dist/js/pages/dashboard.js" type="text/javascript"></script>-->
    <!-- AdminLTE for winchit purposes -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.12.4/js/bootstrap-select.min.js"></script>
    <script src="<?= get_asset_url('js/general.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
    <!-- Error Monitor — captures JS errors, AJAX failures, unhandled rejections -->
    <script src="<?= get_asset_url('js/error_monitor.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
    <script src="<?= get_asset_url('plugins/compression/index.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>

    <?php if($this->uri->segment(1)!='employee' && $this->uri->segment(1)!='payment' ){ ?>
    <script src="<?= get_asset_url('js/ret_general.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
    <?php } ?>

    <!-- Common Input Validation Library (Global) -->
    <script src="<?= get_asset_url('js/validation.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>

    <?php if($this->uri->segment(1)=='employee' || $this->uri->segment(1)=='admin_employee'){ ?>
        <script src="<?= get_asset_url('js/employee.js'); ?>" type="text/javascript"></script>
        <script src="<?php echo base_url(); ?>assets/plugins/date_picker/date_picker.js" type="text/javascript"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/css/date_picker.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
     <?php } ?>

     <?php if( $this->uri->segment(1)=='admin_dashboard'){ ?>
        <script src="<?= get_asset_url('js/employee.js'); ?>" type="text/javascript"></script>
        <script src="<?php echo base_url(); ?>assets/plugins/date_picker/date_picker.js" type="text/javascript"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/css/date_picker.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
     <?php } ?>



    <script src="<?php echo base_url(); ?>assets/dist/js/demo.js" type="text/javascript"></script>

    <script type="text/javascript">
    	var base_url="<?php echo base_url();  ?>";
      var metal_wgt_roundoff = "<?php echo $this->session->userdata('metal_wgt_roundoff'); ?>";
        var metal_wgt_decimal = "<?php echo (int)$this->session->userdata('metal_wgt_decimal'); ?>";
    	// --- Override System: CLIENT_ID Forwarding (Three-Layer Override) ---
    	// On production, Apache SetEnv CLIENT_ID handles this. On local dev,
    	// ?client= query param sets it. This block ensures all JS-initiated
    	// requests (AJAX, window.open) forward the client param automatically.
    	var LMX_CLIENT_ID = "<?php echo defined('CLIENT_ID') ? CLIENT_ID : 'default'; ?>";
    	<?php 
    	    $has_console_feature = function_exists('has_feature_flag') ? has_feature_flag('USE_CONSOLE_TAB') : false;
    	    if ($has_console_feature) {
    	        $use_console_flag = function_exists('feature_flag') ? feature_flag('USE_CONSOLE_TAB') : false;
    	        $user_profile     = $this->session->userdata('profile');
    	        // Inspect is ALLOWED only for Superadmin (profile == 1) when USE_CONSOLE_TAB is INACTIVE (0 / OFF).
    	        if ($user_profile == 1 && !$use_console_flag) {
    	            $restrict_inspect = false;
    	        } else {
    	            $restrict_inspect = true;
    	        }
    	    } else {
    	        $restrict_inspect = false;
    	    }
    	?>
    	var RESTRICT_INSPECT = <?php echo $restrict_inspect ? 'true' : 'false'; ?>;
    </script>
    <?php if (defined('CLIENT_ID_FROM_QUERY') && CLIENT_ID_FROM_QUERY): ?>
    <script type="text/javascript">
    (function() {
        // Helper: append client param to a URL string
        function appendClientParam(url) {
            if (!url || typeof url !== "string") return url;
            // Skip external URLs, data URIs, javascript: etc.
            if (url.indexOf("http") === 0 && url.indexOf(base_url) !== 0) return url;
            if (url.indexOf("data:") === 0 || url.indexOf("javascript:") === 0) return url;
            // Skip if client param already present
            if (url.indexOf("client=") !== -1) return url;
            var separator = url.indexOf("?") !== -1 ? "&" : "?";
            return url + separator + "client=" + LMX_CLIENT_ID;
        }

        // 1. Auto-append to all jQuery AJAX requests
        if (typeof $ !== "undefined" && $.ajaxPrefilter) {
            $.ajaxPrefilter(function(options) {
                if (options.url) {
                    options.url = appendClientParam(options.url);
                }
            });
        }

        // 2. Auto-append to window.open calls
        var _nativeOpen = window.open;
        window.open = function(url, target, features) {
            return _nativeOpen.call(window, appendClientParam(url), target, features);
        };
    })();
    </script>
    <?php endif; ?>


     <!-- WebCam Options -->
        <script src="<?php echo base_url(); ?>assets/plugins/webcam/webcam.min.js"></script>
   <!-- WebCam Options -->

   <!-- dashboard  -->
       <?php if($this->uri->segment(2)=='dashboard' || ($this->uri->segment(1)=='dashboard' && $this->uri->segment(2)=='collection_App')){ ?>
       <script>
            // refresh the page every 5 minutes unless someone presses a key or moves the mouse. This uses jQuery for event binding
            var inactivityTime = <?php echo (is_numeric($inactivity_time) ? $inactivity_time : 5) * 60 * 1000; ?>; // convert minutes to ms
             var time = new Date().getTime();
             $(document.body).bind("mousemove keypress", function(e) {
                 time = new Date().getTime();
             });
             function refresh() {
                 if(new Date().getTime() - time >= inactivityTime)
                     window.location.reload(true);
                 else
                     setTimeout(refresh, 1000);
             }
             setTimeout(refresh, 1000);
        </script>
     	<script src="<?= get_asset_url('js/dashboard.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
     	<script src="<?= get_asset_url('js/ret_dashboard.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>

     	 <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
    <?php } ?>
    <?php if($this->uri->segment(1)=='settings'){ ?>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
    	<script src="https://cdn.ckeditor.com/4.4.3/standard/ckeditor.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <!-- added by Durga 12.05.2023 starts here-->
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
        <!-- added by Durga 12.05.2023 ends here-->
     	<script src="<?= get_asset_url('js/admin_settings.js'); ?>" type="text/javascript"></script>

    <?php } ?>
    <?php if($this->uri->segment(2)=='dayclose'){ ?>
     	<script src="<?= get_asset_url('js/dayclose_settings.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
    <?php } ?>
	<?php if($this->uri->segment(1)=='branch'){ ?>
     	<script src="<?= get_asset_url('js/admin_settings.js'); ?>" type="text/javascript"></script>
     	<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
    <?php } ?>
     <?php if($this->uri->segment(1)=='catalog'){ ?>
     	<script src="<?= get_asset_url('js/catalog.js'); ?>" type="text/javascript"></script>
    <?php } ?>
<!-- scheme -->
       <?php if($this->uri->segment(1)=='scheme' || $this->uri->segment(2)=='classification'){ ?>
       <script src="https://cdn.ckeditor.com/4.4.3/standard/ckeditor.js"></script>
     	<script src="<?= get_asset_url('js/scheme.js'); ?>" type="text/javascript"></script>
     	<script src="<?= get_asset_url('js/settlement.js'); ?>" type="text/javascript"></script>
    <?php } ?>
<!-- /scheme -->
<!-- Customer -->
       <?php if($this->uri->segment(1)=='customer'  ){ ?>
     	<script src="<?= get_asset_url('js/kyc.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
     	<script src="<?= get_asset_url('js/customer.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
     	<script src="<?php echo base_url(); ?>assets/plugins/date_picker/date_picker.js" type="text/javascript"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/css/date_picker.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
        <!-- //webcam upload, #AD On:20-12-2022,Cd:CLin,Up:AB -->
        <script src="<?php echo base_url();?>assets/plugins/shortcutkeys/JQuery.ShortcutKeys-1.0.0.js" type="text/javascript"></script>
    <?php } ?>
<!-- /Customer -->
<!-- Account  -->
       <?php if($this->uri->segment(1)=='account'){ ?>
        <script src="<?= get_asset_url('js/kyc.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
        <script src="<?= get_asset_url('js/scheme_account.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
        <!-- //webcam upload, #AD On:20-12-2022,Cd:CLin,Up:AB -->
        <script src="<?php echo base_url();?>assets/plugins/shortcutkeys/JQuery.ShortcutKeys-1.0.0.js" type="text/javascript"></script>
    <?php } ?>
<!-- /account  -->
<!-- payment  -->
       <?php if($this->uri->segment(1)=='payment' ){ ?>
       <script src="<?= get_asset_url('js/kyc.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
       <script src="<?= get_asset_url('js/payment.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jquery.dataTables.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
       <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
      <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
    <?php } ?>
<!--/ payment  -->
<!-- payment settlement-->
    <?php if($this->uri->segment(2)=='gtway_settlement'){ ?>
     	<script src="<?= get_asset_url('js/settled_payments.js'); ?>" type="text/javascript"></script>
    <?php } ?>
<!--/ payment settlement  -->
<!-- settlement  -->
       <?php if($this->uri->segment(1)=='settlement' ){ ?>
     	<script src="<?= get_asset_url('js/settlement.js'); ?>" type="text/javascript"></script>
    <?php } ?>
<!--/ settlement  -->
<!-- verify  -->
       <?php if($this->uri->segment(1)=='verify' ){ ?>
     	<script src="<?= get_asset_url('js/verify_payment.js'); ?>" type="text/javascript"></script>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jquery.dataTables.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
    <?php } ?>
<!--/ verify  -->
<!-- online payment  -->
       <?php if($this->uri->segment(1)=='online' ){ ?>
     	<script src="<?= get_asset_url('js/online_payments.js'); ?>" type="text/javascript"></script>
     	<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
    <?php } ?>
<!--/ online payment  -->
<!-- postdated  -->
	 <?php if($this->uri->segment(1)=='postdated'){ ?>
     	<script src="<?= get_asset_url('js/postdated_payment.js'); ?>" type="text/javascript"></script>
    <?php } ?>
<!--/ postdated  -->
<!-- reports  -->
       <?php if($this->uri->segment(1)=='reports'){ ?>
       	<script src="<?= get_asset_url('js/reports.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
			<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

        <!-- xlsx -->
        <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
	   <?php } ?>


	   <?php if($this->uri->segment(1)=='reports' || $this->uri->segment(1)=='admin_ret_reports'){ ?>
     	<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
    <?php } ?>

    <?php if($this->uri->segment(1)=='reports' || $this->uri->segment(1)=='admin_ret_metal_process'){ ?>
      <script src="<?= get_asset_url('js/ret_metal_process.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
     	<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
    <?php } ?>




     <?php if($this->uri->segment(1)=='admin_gift_vocuher'){ ?>
        <script src="https://cdn.ckeditor.com/4.4.3/standard/ckeditor.js"></script>
		<script src="<?= get_asset_url('js/gift_voucher.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>

<!--/ reports  -->
<!-- log  -->
       <?php if($this->uri->segment(1)=='log' || $this->uri->segment(1)=='form_logger'){ ?>
     	<script src="<?= get_asset_url('js/log.js'); ?>" type="text/javascript"></script>
    <?php } ?>
<!--/ log  -->
<!-- sms  -->
       <?php if($this->uri->segment(1)=='sms' || $this->uri->segment(1)=='email'){ ?>
     	<script src="<?= get_asset_url('js/sms.js'); ?>" type="text/javascript"></script>
    <?php } ?>
   <!--/ sms  -->
<!-- notification  -->
       <?php if($this->uri->segment(1)=='notification' || $this->uri->segment(2)=='sendnotification'){ ?>
     	<script src="<?= get_asset_url('js/notification.js'); ?>" type="text/javascript"></script>
    <?php } ?>
<!--/ notification  -->
<!-- Wallet  -->
       <?php if($this->uri->segment(1)=='wallet'){ ?>
       <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">

     	<script src="<?= get_asset_url('js/wallet.js'); ?>" type="text/javascript"></script>
    <?php } ?>
<!--/ Wallet  -->
<!-- Catalog Master  -->
       <?php if($this->uri->segment(1)=='purity' || $this->uri->segment(1)=='cut' || $this->uri->segment(1)=='clarity' || $this->uri->segment(1)=='color' || $this->uri->segment(1)=='category' || $this->uri->segment(1)=='sub_category' || $this->uri->segment(1)=='product' || $this->uri->segment(1)=='admin_ret_catalog' || $this->uri->segment(1)=='product_division' || $this->uri->segment(1)=='deposit' || $this->uri->segment(1)=='admin_stock_age_master'){ ?>
        <script src="<?= get_asset_url('js/catalog_master.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
    <?php } ?>


<!-- ## Retail JS ## -->
	<!-- Catalog Master  -->
	<?php if($this->uri->segment(1)=='admin_ret_lot'){ ?>
	    <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
			<script src="<?= get_asset_url('js/ret_lot.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>

	<?php if($this->uri->segment(1)=='admin_ret_order'){ ?>
	        <script src="https://cdn.ckeditor.com/4.4.3/standard/ckeditor.js"></script>
	        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
            <script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script>
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
	        <script src="<?= get_asset_url('js/ret_order.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>

	<?php if($this->uri->segment(1)=='admin_ret_tagging'){ ?>
	    <script src="<?php echo base_url();?>assets/plugins/shortcutkeys/JQuery.ShortcutKeys-1.0.0.js" type="text/javascript"></script>
	    <script src="<?= get_asset_url('js/ret_tagging.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
      <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
      <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
      <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
      <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
      <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
      <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
      <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
      <script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script>
      <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
      <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
      <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
	<?php } ?>

	<?php if($this->uri->segment(1)=='admin_ret_tag_parts'){ ?>
		<script src="<?= get_asset_url('js/tag_parts.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>

	<?php if($this->uri->segment(1)=='admin_ret_estimation' || $this->uri->segment(1)=='admin_ret_offer'){ ?>
		<script src="<?= get_asset_url('js/ret_offer.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>
	<?php if($this->uri->segment(1)=='admin_ret_estimation'){ ?>
		<script src="<?= get_asset_url('js/ret_estimation.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>
	<?php if($this->uri->segment(1)=='admin_ret_eda'){ ?>
		<script src="<?= get_asset_url('js/ret_eda.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>
	<?php if($this->uri->segment(1)=='admin_ret_brntransfer'){ ?>
	    <script src="<?= get_asset_url('js/ret_branch_transfer.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
    <script src="<?php echo base_url();?>assets/js/socket.io.js?v=<?php echo $version;?>" type="text/javascript"></script>

	<?php } ?>
	<?php if($this->uri->segment(1)=='admin_ret_billing'){ ?>
    <script src="<?php echo base_url();?>assets/js/socket.io.js?v=<?php echo $version;?>" type="text/javascript"></script>
	    <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
			<!-- QR Code library for POS DQR (local, no external dependency) -->
			<script src="<?php echo base_url();?>assets/js/qrcode.min.js" type="text/javascript"></script>
			<script src="<?= get_asset_url('js/ret_billing.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>


	<?php } ?>

	<?php if($this->uri->segment(1)=='admin_ret_dashoard'){ ?>
		<script src="<?= get_asset_url('js/ret_dashoard.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>
	<?php if($this->uri->segment(1)=='admin_ret_reports'){ ?>
		<script src="<?= get_asset_url('js/ret_reports.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>

        <!-- Dynamic Column Manager Dependencies -->
        <link rel="stylesheet" href="https://cdn.datatables.net/colreorder/1.1.3/css/dataTables.colReorder.min.css">
        <script src="https://cdn.datatables.net/colreorder/1.1.3/js/dataTables.colReorder.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

	<?php } ?>

  <?php if($this->uri->segment(1)=='admin_tally_transfer'){ ?>
		<script src="<?php echo base_url();?>assets/js/tally_transfer.js?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>

	 <?php if($this->uri->segment(1)=='admin_ret_task'){ ?>
        <script src="<?= get_asset_url('js/ret_task.js'); ?>?nocache=<?php echo $version;?>"  type="text/javascript"></script>
    <?php } ?>

     <!-- advance_book (Lock your Gold)  -->    
       <?php if($this->uri->segment(1)=='admin_adv_booking' ){ ?>
       <script src="<?= get_asset_url('js/advance_book.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jquery.dataTables.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
     <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
     <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
       <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
      <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css"> 
    <?php } ?>

    <?php if($this->uri->segment(1)=='admin_ret_purchase'){ ?>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
            <script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script>
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
	        <script src="https://cdn.ckeditor.com/4.4.3/standard/ckeditor.js"></script>
	        <script src="<?= get_asset_url('js/ret_purchase_order.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>

	<!-- Opening Master -->
	<?php if($this->uri->segment(1)=='admin_opening_master'){ ?>
		<script src="<?= get_asset_url('js/opening_master.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>


	 <?php if($this->uri->segment(1)=='admin_ret_purchase_approval'){ ?>
	    <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
            <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
            <script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script>
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
	        <script src="<?= get_asset_url('js/ret_purchase_approval.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	 <?php } ?>

	<!-- <php if($this->uri->segment(1)=='admin_ret_other_inventory'){ ?>
        <script src="<?= get_asset_url('js/ret_other_inventory.js'); ?>?nocache=<?php echo $version;?>"  type="text/javascript"></script>
    <php } ?> -->

    <?php if($this->uri->segment(1)=='admin_ret_other_inventory'){ ?>
    <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
    
        <script src="<?= get_asset_url('js/ret_other_inventory.js'); ?>?nocache=<?php echo $version;?>"  type="text/javascript"></script>

    <?php } ?>

    <?php if($this->uri->segment(1)=='admin_ret_stock_issue'){ ?>
        <script src="https://cdn.ckeditor.com/4.4.3/standard/ckeditor.js"></script>
        <script src="<?= get_asset_url('js/ret_stock_issue.js'); ?>?nocache=<?php echo $version;?>"  type="text/javascript"></script>
    <?php } ?>

    <?php if($this->uri->segment(1)=='videoshopping_appt'){ ?>
		<script src="<?= get_asset_url('js/vs_appt.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>

	<?php if($this->uri->segment(1)=='admin_ret_sales_transfer'){ ?>
	        <script src="https://cdn.ckeditor.com/4.4.3/standard/ckeditor.js"></script>
	        <script src="<?= get_asset_url('js/ret_sales_transfer.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>

  <?php if($this->uri->segment(1)=='admin_ret_supp_catalog'){ ?>
      <script src="<?= get_asset_url('js/ret_supp_catalog.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/0.9.15/css/bootstrap-multiselect.css" />
      <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/0.9.15/js/bootstrap-multiselect.min.js"></script>
    <?php } ?>

    <?php if($this->uri->segment(1)=='admin_ret_wishlist'){ ?>
      <script src="<?= get_asset_url('js/ret_wishlist.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
    <?php } ?>

    <?php if($this->uri->segment(1)=='admin_ret_section_transfer'){ ?>
	    <script src="<?= get_asset_url('js/ret_section_transfer.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
	<?php } ?>

    <?php if($this->uri->segment(1)=='admin_irn_settings'){ ?>
        <script src="<?= get_asset_url('js/irn_settings.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
    <?php } ?>

<!--/ ## Retail JS ## -->

<!-- Agent -->
       <?php if($this->uri->segment(1)=='agent' || $this->uri->segment(1)=='admin_agent'){ ?>
     	<script src="<?= get_asset_url('js/agent.js'); ?>?v=<?php echo $version;?>" type="text/javascript"></script>
          <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
    <?php } ?>
<!-- /Agent -->
<!--CRM Footers-->
<?php $this->load->view("layout/crm_footers"); ?>
<!--/CRM Footers-->
     <!-- DATA TABLES -->
	<link href="<?php echo base_url(); ?>assets/plugins/datatables/extensions/TableTools/css/dataTables.tableTools.css" rel="stylesheet" type="text/css" />
	<script src="<?php echo base_url(); ?>assets/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.js"></script>
	<script src="<?php echo base_url(); ?>assets/plugins/datatables/extensions/rowReorder/rowReorder.min.js"></script>
<!-- /Account -->
    <script type="text/javascript">
    	$('.btn-cancel').click(function(){
    		window.history.back();
    	});

    	  function money_format_india(x)
          {

            var negavail = false;
            if(x==null || x=='')
            {
                x = 0;
            }
            x = x.toString();
            if(x.charAt(0) === '-'){
               negavail = true;
               x = x.substring(1);
            }
            var afterPoint = '';
            if(x.indexOf('.') > 0)
              afterPoint = x.substring(x.indexOf('.'),x.length);
            x = Math.floor(x);
            x=x.toString();
            var lastThree = x.substring(x.length-3);
            var otherNumbers = x.substring(0,x.length-3);
            if(otherNumbers != '')
              lastThree = ',' + lastThree;
            var res = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree + afterPoint;
            if(negavail){
                return "-" + res;
            }else{
                return res;
            }

        }

    </script>
    
     <?php if($this->uri->segment(1)=='admin_manage' || $this->uri->segment(1)=='gift_issue_form'){ ?>
        <script src="<?= get_asset_url('js/gift_issue_inv.js'); ?>" type="text/javascript"></script>
        <script src="<?php echo base_url(); ?>assets/plugins/date_picker/date_picker.js" type="text/javascript"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.flash.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/jszip.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.html5.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/buttons.print.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/vfs_fonts.js"></script>
        <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/payment/pdfmake.min.js"></script>
        <link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/css/date_picker.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css">
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
     <?php } ?>

  <?php if (!get_flag('hide_company_logo')): ?>
  <?php $this->load->view('layout/_support_widget'); ?>
  <?php endif; ?>

  <!-- Global KYC Collection Modal -->
  <?php if($this->uri->segment(1)=='customer' || $this->uri->segment(1)=='account' || $this->uri->segment(1)=='payment' || $this->uri->segment(1)=='kyc'){ ?>
  <?php $this->load->view('master/customer/kyc_modal'); ?>
  <?php } ?>
  <!-- /Global KYC Collection Modal -->

  <?php
  $profile_id = $this->session->userdata('profile');
  $show_customer_passwd = 0;
  if ($profile_id) {
      $profile_data = $this->db->select('show_customer_passwd')->where('id_profile', $profile_id)->get('profile')->row_array();
      if (isset($profile_data['show_customer_passwd'])) {
          $show_customer_passwd = $profile_data['show_customer_passwd'];
      }
  }
  ?>
  <script type="text/javascript">
      localStorage.setItem('show_customer_passwd', '<?php echo $show_customer_passwd; ?>');

      function updateEyeIconVisibility() {
          var val = localStorage.getItem('show_customer_passwd');
          var toggleBtn = document.getElementById('togglePassword');
          if (toggleBtn) {
              if (val == '1') {
                  toggleBtn.style.display = '';
              } else {
                  toggleBtn.style.display = 'none';
              }
          }
      }

      window.addEventListener('storage', function(e) {
          if (e.key === 'show_customer_passwd') {
              updateEyeIconVisibility();
          }
      });

      window.addEventListener('focus', function() {
          updateEyeIconVisibility();
      });

      // Run visibility check immediately
      updateEyeIconVisibility();
      document.addEventListener('DOMContentLoaded', function() {
          updateEyeIconVisibility();
      });
  </script>

  <!-- Developer Tools Warning Modal -->
  <div class="modal fade" id="devToolsWarningModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" style="z-index: 999999 !important;">
      <div class="modal-dialog modal-dialog-centered" role="document" style="margin-top: 12%;">
          <div class="modal-content" style="border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.5); border: none;">
              <div class="modal-header" style="background-color: #dd4b39; color: #fff; border-top-left-radius: 8px; border-top-right-radius: 8px; text-align: center; padding: 15px;">
                  <h4 class="modal-title" style="font-weight: bold; margin: 0;">
                      <i class="fa fa-exclamation-triangle" style="margin-right: 8px;"></i> Developer Tools Detected
                  </h4>
              </div>
              <div class="modal-body" style="text-align: center; padding: 25px 20px;">
                  <p style="font-size: 16px; color: #333; margin-bottom: 10px; font-weight: 600;">
                      Developer Tools / Console tab is currently open.
                  </p>
                  <p style="font-size: 14px; color: #666; margin: 0;">
                      Please close the Developer Tools / Console window to continue using the system.
                  </p>
              </div>
          </div>
      </div>
  </div>

  </body>
</html>
