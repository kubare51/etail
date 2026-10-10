<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * SessionGuard — Single Active Session Login Restriction
 *
 * post_controller_constructor hook. On every authenticated request it
 * validates that this browser's session token is still THE active session
 * registered for the account (active_session table). If the token is
 * missing, revoked, or the stored session expired by inactivity, the
 * session is logged out and the user is sent back to the login page.
 *
 * Gated per client by chit_settings.single_session_login
 * (Settings → General). Default 0 = disabled → this hook does nothing.
 */
class SessionGuard
{
	// How often (seconds) last_activity is written back to the DB.
	const TOUCH_INTERVAL = 60;

	// Controllers that must stay reachable without an active-session token
	// (login / OTP / logout endpoints).
	private $whitelist = array('chit_admin');

	public function check()
	{
		$CI = & get_instance();

		if ( ! isset($CI->session) || ! $CI->session->userdata('is_logged'))
		{
			return; // not logged in — each controller already redirects to login
		}
		if (in_array(strtolower($CI->router->fetch_class()), $this->whitelist))
		{
			return;
		}

		if ($CI->session->userdata('sess_exempt'))
		{
			return; // employee exempted from the restriction (flag set at login)
		}

		$CI->load->model('active_session_model');
		if ( ! $CI->active_session_model->is_enabled())
		{
			return;
		}

		$uid   = $CI->session->userdata('uid');
		$token = $CI->session->userdata('sess_token');

		if ( ! $CI->active_session_model->validate_token($uid, $token))
		{
			$this->force_logout($CI);
		}
		else
		{
			$last = (int) $CI->session->userdata('sess_token_touch');
			if ((time() - $last) >= self::TOUCH_INTERVAL)
			{
				$CI->active_session_model->touch($token);
				$CI->session->set_userdata('sess_token_touch', time());
			}
		}
	}

	/**
	 * Drop the authentication keys (keeping the CI session alive so the
	 * flash message can render on the login page) and bounce the request.
	 */
	private function force_logout($CI)
	{
		$CI->session->unset_userdata('is_logged');
		$CI->session->unset_userdata('uid');
		$CI->session->unset_userdata('sess_token');
		$CI->session->unset_userdata('sess_token_touch');
		$CI->session->unset_userdata('id_log');
		$CI->session->unset_userdata('access_time_from');
		$CI->session->unset_userdata('access_time_to');

		$msg = 'Your session is no longer active. Please log in again.';

		if ($CI->input->is_ajax_request())
		{
			header('HTTP/1.1 401 Unauthorized');
			header('Content-Type: application/json');
			echo json_encode(array('result' => 0, 'session_expired' => 1, 'msg' => $msg));
		}
		else
		{
			$CI->session->set_flashdata('errMsg', $msg);
			$CI->load->helper('url');
			redirect('admin/login');
		}
		exit;
	}
}

/* End of file SessionGuard.php */
/* Location: ./application/hooks/SessionGuard.php */
