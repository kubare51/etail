<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Admin_ret_dayclose
 *
 * Settings > Auto Day Close - lets an admin set the global/per-branch default auto day
 * close time and extend today's close time for a single branch, backing the 30-minute
 * ticker in Admin_ret_services::autoDayClose(). See Ret_dayclose_model for the resolution
 * rules (today-only override > branch default > global default).
 *
 * No get_access() gate here by design - page visibility is granted through the existing
 * Settings > Menu / Access screens once the menu row is added there.
 */
class Admin_ret_dayclose extends CI_Controller
{
	const DC_MODEL   = "ret_dayclose_model";
	const SETT_MODEL = "admin_settings_model";

	function __construct()
	{
		parent::__construct();
		ini_set('date.timezone', 'Asia/Calcutta');
		$this->load->model(self::DC_MODEL);
		$this->load->model(self::SETT_MODEL);
		$this->load->model("ret_services_model");

		if (!$this->session->userdata('is_logged')) {
			redirect('admin/login');
		} elseif ($this->session->userdata('access_time_from') != NULL && $this->session->userdata('access_time_from') != "") {
			$now = time();
			$from = $this->session->userdata('access_time_from');
			$to = $this->session->userdata('access_time_to');
			$allowedAccess = ($now > $from && $now < $to) ? TRUE : FALSE;
			if ($allowedAccess == FALSE) {
				$this->session->set_flashdata('login_errMsg', 'Exceeded allowed access time!!');
				redirect('chit_admin/logout');
			}
		}
	}

	// ---------------------------------------------------------------
	// Page loads + AJAX data (GET-style, hit as index.php/admin_ret_dayclose/schedule/<type>)
	// ---------------------------------------------------------------

	public function schedule($type = "")
	{
		$model = self::DC_MODEL;
		switch ($type) {
			case 'list':
				$data['main_content'] = 'settings/dayclose/list';
				$this->load->view('layout/template', $data);
				break;

			case 'log':
				$data['main_content'] = 'settings/dayclose/log';
				$this->load->view('layout/template', $data);
				break;

			case 'valid_slots':
				// Slots strictly later than "now" (Asia/Kolkata) - used by the
				// Extend-today modal so a past time is never selectable (rule V2).
				echo json_encode($this->getValidFutureSlots());
				break;

			case 'log_data':
				echo json_encode($this->buildLogData(
					$this->input->get('id_branch') ? $this->input->get('id_branch') : '',
					$this->input->get('from_date') ? $this->input->get('from_date') : '',
					$this->input->get('to_date') ? $this->input->get('to_date') : ''
				));
				break;

			default:
				$id_branch_filter = $this->input->get('id_branch');
				echo json_encode($this->buildScheduleData($id_branch_filter ? $id_branch_filter : ''));
				break;
		}
	}

	// ---------------------------------------------------------------
	// Writes (POST, hit as index.php/admin_ret_dayclose/schedule_post/<type>)
	// ---------------------------------------------------------------

	public function schedule_post($type = "")
	{
		$model = self::DC_MODEL;
		$uid = $this->session->userdata('uid');

		switch ($type) {

			// Global default time / on-off / unlock-fallback time. Only the fields
			// actually posted are updated, so the three controls on the settings
			// screen can each save independently.
			// Note: there is deliberately no way to disable auto day close entirely
			// through this endpoint - Ret_dayclose_model::getGlobalSettings() still
			// exposes 'auto_dayclose_enabled' and the ticker in Admin_ret_services
			// still honours it, but it is only ever changed directly in the database
			// (a support-only circuit breaker), never from this UI.
			case 'save_global':
				$default_time = $this->input->post('default_time');
				$unlock_fb    = $this->input->post('unlock_fallback');
				$message      = '';

				// This project's Input::post() returns FALSE (not NULL) for a field that
				// was never submitted - each save_global request only ever posts ONE of
				// these fields, so both checks must also exclude FALSE, or a field that
				// simply wasn't sent gets treated as "a value was sent" and fails slot
				// validation on FALSE/'' instead of being skipped.
				if ($default_time !== FALSE && $default_time !== NULL && $default_time !== '') {
					if (!$this->isValidSlot($default_time)) {
						echo json_encode(array('message' => 'Invalid time. Please select a 30-minute slot.', 'class' => 'danger', 'title' => 'Auto Day Close'));
						return;
					}
					$old_global = $this->$model->getGlobalSettings();
					foreach ($this->$model->getBranchDayClosing() as $row) {
						$this->$model->snapshotTodayDefault($row, $old_global);
					}
					$this->$model->saveGlobalSetting('auto_dayclose_time', $default_time . ':00', $uid);
					$message = 'Default auto day close time updated. This applies from the next business day - branches still open today keep their existing close time.';
				}

				if ($unlock_fb !== FALSE && $unlock_fb !== NULL && $unlock_fb !== '') {
					if (!$this->isValidSlot($unlock_fb)) {
						echo json_encode(array('message' => 'Invalid time. Please select a 30-minute slot.', 'class' => 'danger', 'title' => 'Auto Day Close'));
						return;
					}
					$this->$model->saveGlobalSetting('auto_dayclose_unlock_fallback', $unlock_fb . ':00', $uid);
					$message = 'Unlock fallback time updated.';
				}

				echo json_encode(array('message' => ($message != '' ? $message : 'Nothing to update.'), 'class' => 'success', 'title' => 'Auto Day Close'));
				break;

			// Per-branch default time. Empty close_time means "use the global default".
			case 'save_branch_default':
				$id_branch  = (int) $this->input->post('id_branch');
				$close_time = $this->input->post('close_time'); // '' or 'HH:MM'

				$row = $this->$model->getBranchDayClosing($id_branch);
				if (!$row) { // V7
					echo json_encode(array('message' => 'Branch not available for day close configuration.', 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}
				if ($close_time !== '' && !$this->isValidSlot($close_time)) { // V1
					echo json_encode(array('message' => 'Invalid time. Please select a 30-minute slot.', 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}

				$old_global = $this->$model->getGlobalSettings();
				$this->$model->snapshotTodayDefault($row, $old_global);
				$this->$model->saveBranchDefault($id_branch, $close_time !== '' ? $close_time . ':00' : NULL, $uid);

				echo json_encode(array('message' => 'Branch default close time updated. This applies from the next business day.', 'class' => 'success', 'title' => 'Auto Day Close'));
				break;

			// Today-only extension - the "Extend today" / "Change" button.
			case 'save_override':
				$id_branch  = (int) $this->input->post('id_branch');
				$close_time = $this->input->post('close_time'); // 'HH:MM'
				$remarks    = trim((string) $this->input->post('remarks'));

				$row = $this->$model->getBranchDayClosing($id_branch);
				if (!$row) { // V7
					echo json_encode(array('message' => 'Branch not available for day close configuration.', 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}
				if ($row['is_day_closed'] == 1) { // V3 - the client's explicit requirement
					echo json_encode(array(
						'message' => "Day close for " . date('d-m-Y', strtotime($row['entry_date'])) . " has already been completed. Today's close time can no longer be changed - the next business day will use the standing default.",
						'class' => 'danger', 'title' => 'Auto Day Close'
					));
					return;
				}
				if (!$this->isValidSlot($close_time)) { // V1
					echo json_encode(array('message' => 'Invalid time. Please select a 30-minute slot.', 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}

				$tz = new DateTimeZone('Asia/Kolkata');
				$now_hm = (new DateTime('now', $tz))->format('H:i');
				if ($close_time <= $now_hm) { // V2
					echo json_encode(array('message' => date('h:i A', strtotime($close_time)) . ' has already passed. Please select a later time.', 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}
				if (strlen($remarks) < 3) { // V6
					echo json_encode(array('message' => 'Please enter a reason for extending today\'s day close.', 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}

				$global = $this->$model->getGlobalSettings();
				$effective = $this->$model->resolveEffectiveTime($row, $global);
				if ($close_time . ':00' === $effective['time']) { // V8
					echo json_encode(array('message' => 'This is already today\'s close time.', 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}

				$this->$model->saveOverride($id_branch, $row['entry_date'], $close_time . ':00', $remarks, $uid);
				echo json_encode(array('message' => "Today's close time updated to " . date('h:i A', strtotime($close_time)) . ". Reverts automatically from tomorrow.", 'class' => 'success', 'title' => 'Auto Day Close'));
				break;

			// Same as save_override, applied to every branch in one go. Branches already
			// closed today are silently skipped (V3 still applies per-branch) rather than
			// failing the whole batch - the point of this action is "close everyone I can
			// by 11:00", not an all-or-nothing operation.
			case 'save_override_all':
				$close_time = $this->input->post('close_time');
				$remarks    = trim((string) $this->input->post('remarks'));

				if ($close_time === FALSE || $close_time === NULL || $close_time === '' || !$this->isValidSlot($close_time)) { // V1
					echo json_encode(array('message' => 'Invalid time. Please select a 30-minute slot.', 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}

				$tz = new DateTimeZone('Asia/Kolkata');
				$now_hm = (new DateTime('now', $tz))->format('H:i');
				if ($close_time <= $now_hm) { // V2
					echo json_encode(array('message' => date('h:i A', strtotime($close_time)) . ' has already passed. Please select a later time.', 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}
				if (strlen($remarks) < 3) { // V6
					echo json_encode(array('message' => 'Please enter a reason for extending today\'s day close.', 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}

				$global  = $this->$model->getGlobalSettings();
				$applied = 0;
				$skipped_closed    = 0;
				$skipped_unchanged = 0;

				foreach ($this->$model->getBranchesWithSchedule() as $row) {
					if ($row['entry_date'] === NULL) {
						continue; // active branch with no ret_day_closing row - same defensive skip as the list screen
					}
					if ($row['is_day_closed'] == 1) { // V3, per branch
						$skipped_closed++;
						continue;
					}
					$effective = $this->$model->resolveEffectiveTime($row, $global);
					if ($close_time . ':00' === $effective['time']) { // V8, per branch
						$skipped_unchanged++;
						continue;
					}
					$this->$model->saveOverride($row['id_branch'], $row['entry_date'], $close_time . ':00', $remarks, $uid);
					$applied++;
				}

				$message = "Today's close time updated to " . date('h:i A', strtotime($close_time)) . " for " . $applied . " branch" . ($applied == 1 ? '' : 'es') . ".";
				if ($skipped_closed > 0) {
					$message .= " " . $skipped_closed . " already closed today and were left unchanged.";
				}
				if ($skipped_unchanged > 0) {
					$message .= " " . $skipped_unchanged . " already had this time.";
				}

				echo json_encode(array('message' => $message, 'class' => 'success', 'title' => 'Auto Day Close'));
				break;

			case 'delete_override':
				$id_branch = (int) $this->input->post('id_branch');
				$row = $this->$model->getBranchDayClosing($id_branch);
				if (!$row) {
					echo json_encode(array('message' => 'Branch not available for day close configuration.', 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}
				if ($row['is_day_closed'] == 1) { // V3
					echo json_encode(array('message' => "Day close for " . date('d-m-Y', strtotime($row['entry_date'])) . " has already been completed. Today's time can no longer be changed.", 'class' => 'danger', 'title' => 'Auto Day Close'));
					return;
				}
				$this->$model->expireOverride($id_branch, $row['entry_date']);
				echo json_encode(array('message' => "Today's extension removed. Falls back to the standing default.", 'class' => 'success', 'title' => 'Auto Day Close'));
				break;
		}
	}

	// ---------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------

	// V1: only 30-minute slots ("HH:MM", 00:00-23:30) are ever accepted.
	private function isValidSlot($hm)
	{
		if (!preg_match('/^([01]\d|2[0-3]):(00|30)$/', (string) $hm)) {
			return FALSE;
		}
		return TRUE;
	}

	private function getValidFutureSlots()
	{
		$tz  = new DateTimeZone('Asia/Kolkata');
		$now = new DateTime('now', $tz);
		$slots = array();
		for ($h = 0; $h <= 23; $h++) {
			foreach (array('00', '30') as $m) {
				$hm = sprintf('%02d:%s', $h, $m);
				$candidate = DateTime::createFromFormat('H:i', $hm, $tz);
				if ($candidate > $now) {
					$slots[] = array('value' => $hm, 'label' => $candidate->format('h:i A'), 'is_am' => ($candidate->format('A') == 'AM'));
				}
			}
		}
		return $slots;
	}

	private function buildScheduleData($id_branch_filter = "")
	{
		$model  = self::DC_MODEL;
		$global = $this->$model->getGlobalSettings();
		$rows   = $this->$model->getBranchesWithSchedule();

		$branches = array();
		foreach ($rows as $row) {
			if ($id_branch_filter !== "" && $id_branch_filter != $row['id_branch']) {
				continue;
			}
			if ($row['entry_date'] === NULL) {
				// active branch with no ret_day_closing row yet - shouldn't happen via
				// the normal branch-creation flow, but don't let it break the screen
				continue;
			}

			$effective = $this->$model->resolveEffectiveTime($row, $global);

			$branches[] = array(
				'id_branch'          => $row['id_branch'],
				'branch_name'        => $row['branch_name'],
				'entry_date'         => date('d-m-Y', strtotime($row['entry_date'])),
				'entry_date_raw'     => $row['entry_date'],
				'is_day_closed'      => (int) $row['is_day_closed'],
				'closed_at'          => ($row['is_day_closed'] == 1 ? date('h:i A', strtotime($row['updated_on'])) : NULL),
				'branch_default'     => ($row['day_close_time'] ? date('h:i A', strtotime($row['day_close_time'])) : NULL),
				'branch_default_raw' => ($row['day_close_time'] ? substr($row['day_close_time'], 0, 5) : ''),
				'effective_time'     => date('h:i A', strtotime($effective['time'])),
				'effective_source'   => $effective['source'], // override | branch | global
				'override_remarks'   => ($effective['override'] ? $effective['override']['remarks'] : NULL),
			);
		}

		return array(
			'global' => array(
				'default_time'    => date('h:i A', strtotime($global['auto_dayclose_time'])),
				'default_time_raw'=> substr($global['auto_dayclose_time'], 0, 5),
				'unlock_fallback'    => date('h:i A', strtotime($global['auto_dayclose_unlock_fallback'])),
				'unlock_fallback_raw'=> substr($global['auto_dayclose_unlock_fallback'], 0, 5),
			),
			'branches' => $branches,
		);
	}

	private function buildLogData($id_branch = '', $from_date = '', $to_date = '')
	{
		$model   = self::DC_MODEL;
		$summary = $this->$model->getCronSummary();
		$logs    = $this->$model->getCronLog($id_branch, $from_date, $to_date);

		$last_run_minutes_ago = NULL;
		if ($summary['last_run_at']) {
			$last_run_minutes_ago = round((time() - strtotime($summary['last_run_at'])) / 60);
		}

		$log_rows = array();
		foreach ($logs as $row) {
			$log_rows[] = array(
				'run_at'               => date('d-m-Y h:i A', strtotime($row['run_at'])),
				'branch_name'          => ($row['branch_name'] ? $row['branch_name'] : '-'),
				'entry_date'           => ($row['entry_date'] ? date('d-m-Y', strtotime($row['entry_date'])) : '-'),
				'effective_close_time' => ($row['effective_close_time'] ? date('h:i A', strtotime($row['effective_close_time'])) : '-'),
				'time_source'          => ($row['time_source'] ? $row['time_source'] : '-'),
				'action'               => $row['action'],
				'message'              => $row['message'],
			);
		}

		return array(
			'summary' => array(
				'last_run_at'          => ($summary['last_run_at'] ? date('d-m-Y h:i A', strtotime($summary['last_run_at'])) : NULL),
				'last_run_minutes_ago' => $last_run_minutes_ago,
				'is_stale'             => ($last_run_minutes_ago === NULL || $last_run_minutes_ago > 45),
				'closed_today'         => $summary['closed_today'],
				'fallback_30d'         => $summary['fallback_30d'],
				'failed_30d'           => $summary['failed_30d'],
			),
			'logs' => $log_rows,
		);
	}
}
