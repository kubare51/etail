<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Active_session_model — Single Active Session Login Restriction
 *
 * Keeps exactly one row per employee in the `active_session` table.
 * That row represents the ONLY browser/device allowed to hold a live
 * session for the account. Used by:
 *   - chit_admin::authenticate() / update_otp()  → block second login, register session
 *   - chit_admin::logout()                       → release the session
 *   - hooks/SessionGuard.php                     → validate token on every request
 *
 * Feature is gated per client by chit_settings.single_session_login
 * (managed in Settings → General). Default 0 = disabled, so shared-repo
 * deployments change nothing until a client enables the setting.
 *
 * Migration REQUIRED before deploying this code (fail-fast by design —
 * a missing schema raises a visible DB error rather than silently
 * disabling the feature). Run both, with a DB account that has DDL rights:
 *   database/migrations/20260722_000001_create_active_session.sql
 *   database/migrations/20260722_000002_add_columns_chit_settings.sql
 * This model itself never issues DDL.
 */
class Active_session_model extends CI_Model
{
	const TABLE          = "active_session";
	const SETTINGS_TABLE = "chit_settings";
	const EMP_TABLE      = "employee";

	private $settings = NULL;
	private $exempt   = array();

	private function get_settings()
	{
		if ($this->settings === NULL)
		{
			$this->settings = $this->db->query(
				"SELECT single_session_login
				 FROM ".self::SETTINGS_TABLE." WHERE id_chit_settings = 1"
			)->row_array();
			if (empty($this->settings))
			{
				$this->settings = array('single_session_login' => 0);
			}
		}
		return $this->settings;
	}

	/**
	 * Is the single-session restriction enabled for this client?
	 */
	public function is_enabled()
	{
		$settings = $this->get_settings();
		return ((int) $settings['single_session_login'] === 1);
	}

	/**
	 * Is this employee exempted from the single-session restriction?
	 * DB-only control (employee.single_session_exempt) — intentionally no UI
	 * anywhere; grant/revoke only by support via direct SQL:
	 *   UPDATE employee SET single_session_exempt = 1 WHERE id_employee = <id>;
	 * Read at login time; a change applies from the employee's next login.
	 */
	public function is_exempt($id_employee)
	{
		if ($id_employee == '')
		{
			return FALSE;
		}
		$id_employee = (int) $id_employee;
		if ( ! isset($this->exempt[$id_employee]))
		{
			$row = $this->db->query(
				"SELECT single_session_exempt FROM ".self::EMP_TABLE."
				 WHERE id_employee = ".$id_employee
			)->row_array();
			$this->exempt[$id_employee] = ( ! empty($row) && (int) $row['single_session_exempt'] === 1);
		}
		return $this->exempt[$id_employee];
	}

	/**
	 * Inactivity window (seconds) after which a stored session is treated
	 * as expired and released. Follows sess_expiration so the lock lives
	 * exactly as long as the login session itself — never longer (would
	 * dead-lock the account) and never shorter (would kick idle users).
	 * A crashed PC that must be released sooner is a support action:
	 *   DELETE FROM active_session WHERE id_employee = <id>;
	 */
	public function timeout()
	{
		$sess_expiration = (int) $this->config->item('sess_expiration');
		if ($sess_expiration <= 0)
		{
			$sess_expiration = 7200;
		}
		return $sess_expiration;
	}

	public function get_by_employee($id_employee)
	{
		return $this->db->get_where(self::TABLE, array('id_employee' => $id_employee))->row_array();
	}

	/**
	 * TRUE when the account already holds a live session on another
	 * browser/device. Stale rows (idle past timeout) are released here so
	 * the user can log in again after the old session expires.
	 */
	public function has_other_active_session($id_employee, $current_token = '')
	{
		$row = $this->get_by_employee($id_employee);
		if (empty($row))
		{
			return FALSE;
		}
		if ($current_token != '' && $row['session_token'] === $current_token)
		{
			return FALSE;
		}
		if (strtotime($row['last_activity']) < (time() - $this->timeout()))
		{
			$this->remove_by_employee($id_employee);
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Register a fresh login as THE active session for the employee.
	 * Replaces any previous row (stale ones, or re-login after expiry).
	 */
	public function register($id_employee, $token, $details = array())
	{
		$now  = date("Y-m-d H:i:s");
		$data = array(
			'id_employee'   => $id_employee,
			'session_token' => $token,
			'login_on'      => $now,
			'last_activity' => $now,
		);
		foreach (array('device_type', 'browser', 'operating_system', 'ip_address', 'user_agent') as $col)
		{
			if (isset($details[$col]))
			{
				$data[$col] = substr($details[$col], 0, 255);
			}
		}
		$this->db->where('id_employee', $id_employee);
		$this->db->delete(self::TABLE);
		return $this->db->insert(self::TABLE, $data);
	}

	/**
	 * TRUE when the token is still THE active session for the employee.
	 * Deliberately does NOT expire the owner on staleness: last_activity is
	 * throttle-written (up to TOUCH_INTERVAL behind the true last request),
	 * so expiring here would log the owner out early. The owner's lifetime
	 * is governed by the CI session alone; row staleness only matters when
	 * OTHERS try to log in (has_other_active_session).
	 * FALSE only when the row is gone (released by support/logout) or the
	 * token no longer matches (another login took over the account).
	 */
	public function validate_token($id_employee, $token)
	{
		if ($token == '' || $id_employee == '')
		{
			return FALSE;
		}
		$row = $this->get_by_employee($id_employee);
		if (empty($row) || $row['session_token'] !== $token)
		{
			return FALSE;
		}
		return TRUE;
	}

	public function touch($token)
	{
		$this->db->where('session_token', $token);
		return $this->db->update(self::TABLE, array('last_activity' => date("Y-m-d H:i:s")));
	}

	public function remove_by_employee($id_employee)
	{
		$this->db->where('id_employee', $id_employee);
		return $this->db->delete(self::TABLE);
	}

	public function remove_by_token($token)
	{
		$this->db->where('session_token', $token);
		return $this->db->delete(self::TABLE);
	}

	/**
	 * Generate a unique session token for a successful login.
	 */
	public function generate_token()
	{
		if (function_exists('random_bytes'))
		{
			return bin2hex(random_bytes(32));
		}
		if (function_exists('openssl_random_pseudo_bytes'))
		{
			return bin2hex(openssl_random_pseudo_bytes(32));
		}
		return md5(uniqid(mt_rand(), TRUE)).md5(uniqid(mt_rand(), TRUE));
	}
}

/* End of file active_session_model.php */
/* Location: ./application/models/active_session_model.php */
