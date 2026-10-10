<?php $comp_details=$this->admin_settings_model->get_company();?>



<!DOCTYPE html>

<html>

  <head>

    <meta charset="UTF-8">

    <title><?php echo $comp_details['company_name']?> | Login</title>
    <?php if (!get_flag('hide_company_logo')): ?><link rel="shortcut icon" href="<?php echo base_url() ?>favicon.ico?v=2" type="image/x-icon"><link rel="icon" href="<?php echo base_url() ?>favicon.ico?v=2" type="image/x-icon"><?php else: ?><link rel="icon" href="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" type="image/gif"><link rel="shortcut icon" href="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" type="image/gif"><?php endif; ?>

    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <!-- Bootstrap 3.3.4 -->

    <link href="<?php echo base_url(); ?>assets/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />

    <!-- Font Awesome Icons -->

    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />

    <!-- Theme style -->

    <link href="<?php echo base_url(); ?>assets/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />

    <!-- iCheck -->

    <link href="<?php echo base_url(); ?>assets/plugins/iCheck/square/blue.css" rel="stylesheet" type="text/css" />

	<link href="<?php echo base_url() ?>assets/css/style.css" rel="stylesheet" type="text/css">

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->

    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->

    <!--[if lt IE 9]>

        <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>

        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>

    <![endif]-->

  </head>

  <body class="login-page" >

    <div class="login-box">
    <div class="login-logo">
	    <?php if (!get_flag('hide_company_logo')): ?>
	    <img style="width: 40%" src="<?php echo base_url(); ?>assets/img/logo.png">
	    <?php endif; ?>
	  </div>

     <div class="login-title">

		<b><?php echo $comp_details['company_name']?> </b><br>Login

      </div><!-- /.login-logo -->

      <div class="login-box-body">

        <p class="login-box-msg">Sign in to start your session</p>
		
			<form id="log_submit">

       <?php $attributes = array('autocomplete' => "off");

		     // echo form_open('admin/login/authenticate', $attributes); 

		      if(isset($login_error) && $login_error){ ?>

            <div class="alert alert-danger alert-dismissable">

                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>

                    <h4><i class="icon fa fa-info"></i> Invalid Login!</h4>

                    The username and password you entered don't match. 
                    
            <?php if($this->session->flashdata('successMsg')) { ?>
						<div class="alert alert-success" align="center">
						  <button type="button" class="close" data-dismiss="alert">&times;</button>
						  <strong><?php echo $this->session->flashdata('successMsg'); ?> </strong>
						</div>      
				<?php } else if($this->session->flashdata('errMsg')) {  ?>							 
						<div class="alert alert-danger" align="center">
						  <button type="button" class="close" data-dismiss="alert">&times;</button>
						  <strong><?php echo $this->session->flashdata('errMsg'); ?></strong>
						</div>
				<?php } ?>

            </div>

           <?php } ?>

          <div class="form-group has-feedback">

            <input type="text" id="username"  autocomplete="off" name="username" autocomplete="off" class="form-control" placeholder="User Name"/>

            <span class="glyphicon glyphicon-envelope form-control-feedback"></span>

          </div>

          <div class="form-group has-feedback">

            <input type="password"  id="password" name="password" class="form-control" placeholder="Password"/>

            <span class="glyphicon glyphicon-lock form-control-feedback"></span>

          </div>
          
          
          <?php if($comp_details['company_settings']==1) {?>

          <div class="form-group has-feedback">

           <label for="" ><a  data-toggle="tooltip" title="Select Your Company"> Select Company  </a> <span class="error">*</span></label>
                <select  id="company_select" name="company" placeholder="Select Company" class="form-control select2 cls">
                    <option value="">--Select Company--</option>
                </select>

                

          </div>
          <?php }?>
          
          <input id="id_company" name="id_company" type="hidden" value=""  />
          <input id="company_settings" type="hidden" value="<?php echo $comp_details['company_settings']; ?>"  />
          
          <?php if($comp_details['login_branch']==1) {?>

          <div class="form-group has-feedback" >

           <label for="" ><a  data-toggle="tooltip" title="Select branch to create Scheme Account"> Select Branch  </a> <span class="error">*</span></label>
                <select  id="branch_select" placeholder="Select Branch" class="form-control select2 cls"></select>

                <input id="id_branch" name="emp[id_branch]" type="hidden" value=""  />

          </div>
          <?php }?>
          
          

          <div class="row">

            <div class="col-xs-8">    

				<div class="pull-left" style="margin-left: 25px;">
					<a  href="<?php echo base_url(); ?>index.php/chit_admin/forgetAdmin" style="color:#717171;" title="Click here to reset password" >Forgot Password?</a>
					</div>
				<!--<div class="pull-left" style="margin-left: 25px;">
					<a  href="<?php echo base_url(); ?>index.php/user/forget" style="color:#717171;" title="Click here to reset password" rel="tooltip">Forgot Password?</a>
					</div>-->

              <div class="checkbox icheck">

                <label>

               <!--   <input type="checkbox"> Remember Me -->

                </label>

              </div>                        

            </div>

<!-- /.col -->

            <div class="col-xs-4">

              <button type="submit" id="submit_login" name="submit_login" class="btn btn-primary btn-block btn-flat">Sign In</button>

            </div><!-- /.col -->

          </div>

        </form>

<!--

        <div class="social-auth-links text-center">

          <p>- OR -</p>

          <a href="#" class="btn btn-block btn-social btn-facebook btn-flat"><i class="fa fa-facebook"></i> Sign in using Facebook</a>

          <a href="#" class="btn btn-block btn-social btn-google-plus btn-flat"><i class="fa fa-google-plus"></i> Sign in using Google+</a>

        </div>-->

        <!-- /.social-auth-links -->



      <!--  <a href="#">I forgot my password</a><br> -->

        <!--<a href="register.html" class="text-center">Register a new membership</a>-->



      </div><!-- /.login-box-body -->

    </div><!-- /.login-box -->



    <!-- OTP Modal — Enhanced UI -->
    <style>
      /* ── Scoped OTP Modal Styles ─────────────────────────── */
      #otp_modal .modal-dialog {
        max-width: 380px;
        margin: 60px auto;
      }
      #otp_modal .modal-content {
        border: none;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,.25);
        overflow: hidden;
        animation: otpSlideIn .35s ease-out;
      }
      @keyframes otpSlideIn {
        from { opacity: 0; transform: translateY(-30px) scale(.96); }
        to   { opacity: 1; transform: translateY(0)    scale(1);   }
      }

      /* Header */
      #otp_modal .otp-header {
        background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%);
        padding: 20px 24px 16px;
        text-align: center;
        position: relative;
      }
      #otp_modal .otp-header .otp-icon-circle {
        width: 44px; height: 44px;
        margin: 0 auto 10px;
        background: rgba(255,255,255,.18);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      #otp_modal .otp-header .otp-icon-circle svg {
        width: 22px; height: 22px;
        fill: #fff;
      }
      #otp_modal .otp-header h3 {
        margin: 0;
        font-size: 17px;
        font-weight: 600;
        color: #fff;
        letter-spacing: .3px;
      }
      #otp_modal .otp-header p {
        margin: 6px 0 0;
        font-size: 13px;
        color: rgba(255,255,255,.78);
      }
      #otp_modal .otp-close-btn {
        position: absolute;
        top: 12px; right: 14px;
        background: rgba(255,255,255,.2);
        border: none;
        color: #fff;
        width: 30px; height: 30px;
        border-radius: 50%;
        font-size: 18px;
        line-height: 30px;
        text-align: center;
        cursor: pointer;
        transition: background .2s;
      }
      #otp_modal .otp-close-btn:hover {
        background: rgba(255,255,255,.35);
      }

      /* Body */
      #otp_modal .modal-body {
        padding: 20px 24px 16px;
        text-align: center;
      }
      #otp_modal .modal-body label.otp-label {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: #555;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: .8px;
      }
      #otp_modal .modal-body .otp-input-wrap {
        position: relative;
        max-width: 240px;
        margin: 0 auto 14px;
      }
      #otp_modal .modal-body .otp-input-wrap input#otp {
        width: 100%;
        height: 42px;
        border: 2px solid #dde1e6;
        border-radius: 10px;
        text-align: center;
        font-size: 22px;
        font-weight: 600;
        letter-spacing: 8px;
        color: #1e293b;
        transition: border-color .25s, box-shadow .25s;
        box-sizing: border-box;
      }
      #otp_modal .modal-body .otp-input-wrap input#otp:focus {
        border-color: #1e88e5;
        box-shadow: 0 0 0 3px rgba(30,136,229,.15);
        outline: none;
      }
      #otp_modal #OTPloader {
        display: inline-block;
        margin-top: 4px;
      }
      #otp_modal #OTPloader img {
        width: 28px; height: 28px;
      }

      /* Verify button */
      #otp_modal .otp-verify-btn {
        display: inline-block;
        width: 100%;
        max-width: 240px;
        padding: 10px 0;
        border: none;
        border-radius: 10px;
        background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%);
        color: #fff;
        font-size: 15px;
        font-weight: 600;
        letter-spacing: .5px;
        cursor: pointer;
        transition: transform .15s, box-shadow .2s;
        box-shadow: 0 4px 14px rgba(21,101,192,.35);
      }
      #otp_modal .otp-verify-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(21,101,192,.45);
      }
      #otp_modal .otp-verify-btn:active {
        transform: translateY(0);
      }

      /* Footer / Resend */
      #otp_modal .modal-footer {
        border-top: 1px solid #f0f0f0;
        padding: 12px 24px;
        text-align: center;
      }
      #otp_modal .modal-footer .resendotp {
        background: none;
        border: none;
        color: #1e88e5;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        padding: 4px 8px;
        border-radius: 6px;
        transition: background .2s, color .2s;
      }
      #otp_modal .modal-footer .resendotp:hover {
        background: rgba(30,136,229,.08);
        color: #1565c0;
      }
      #otp_modal .modal-footer .resendotp:disabled {
        color: #aaa;
        cursor: not-allowed;
      }
    </style>

    <div id="otp_modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">

          <!-- Header -->
          <div class="otp-header">
            <button type="button" id="close_model" class="otp-close-btn" data-dismiss="modal" aria-hidden="true">&times;</button>
            <div class="otp-icon-circle">
              <svg viewBox="0 0 24 24"><path d="M17 1H7C5.9 1 5 1.9 5 3v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-2-2-2zm0 18H7V5h10v14zm-5 3c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z"/></svg>
            </div>
            <h3 id="myModalLabel">Mobile Number Verification</h3>
            <p>Please enter the code sent to your mobile number</p>
          </div>

          <!-- Body -->
          <div class="modal-body">
            <label class="otp-label" for="otp">Enter Verification Code</label>
            <div class="otp-input-wrap">
              <input type="text" id="otp" name="otp" value="" class="form-control" required maxlength="6" placeholder="••••••" autocomplete="one-time-code" />
            </div>
            <input type="submit" value="Verify" id="verify_otp" class="otp-verify-btn" />
            <!-- <span id="OTPloader"><img src="<?php echo base_url()?>assets/img/loader.gif" ></span> -->
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <input type="submit" id="resendotp" value="Resend OTP" class="resendotp" />
          </div>

        </div>
      </div>
    </div>


    <!-- jQuery 2.1.4 -->

    <script src="<?php echo base_url(); ?>assets/plugins/jQuery/jQuery-2.1.4.min.js"></script>

    <script src="<?php echo base_url(); ?>assets/plugins/select2/select2.full.min.js"></script>

    <script src="<?php  echo base_url(); ?>assets/js/otp.js"></script>
    
    <script src="<?php echo base_url(); ?>assets/plugins/toaster/jquery.toaster.js"></script>

    <!-- Bootstrap 3.3.2 JS -->

    <script src="<?php echo base_url(); ?>assets/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>

    <!-- iCheck -->

    <script src="<?php echo base_url(); ?>assets/plugins/iCheck/icheck.min.js" type="text/javascript"></script>

    <script>

      $(function () {

        $('input').iCheck({

          checkboxClass: 'icheckbox_square-blue',

          radioClass: 'iradio_square-blue',

          increaseArea: '20%' // optional

        });

      });

       var base_url = "<?php echo base_url();?>";

    </script>
    
    <script>
      function initFingerprintJS() {
        FingerprintJS.load().then(fp => {
          // The FingerprintJS agent is ready.
          // Get a visitor identifier when you'd like to.
          fp.get().then(result => {
            // This is the visitor identifier:
            const visitorId = result.visitorId;
            console.log(visitorId);
            $('#system_fp_id').val(visitorId);
          });
        });
      }
    </script>

  <script>
      function generateRandomID() {
          const length = Math.floor(Math.random() * 9) + 32; // random length between 32 and 40
          const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
          let id = '';
          for (let i = 0; i < length; i++) {
              id += chars.charAt(Math.floor(Math.random() * chars.length));
          }
          return id;
      }
      
      function getDeviceID() {
          let deviceID = localStorage.getItem('deviceID');
          if (!deviceID) {
              deviceID = generateRandomID(36); 
              localStorage.setItem('deviceID', deviceID);
          }
          return deviceID;
      }
      
      const uniqueID = getDeviceID();

      DeviceId = localStorage.getItem("deviceID");
      console.log("DeviceId", DeviceId);
  </script>


  </body>

</html>