<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Ret_dayclose_model
 *
 * Backs the "Settings > Auto Day Close" screen and the autoDayClose() ticker.
 * Resolves the effective auto day close time per branch from three possible
 * sources (today-only override > branch default > global default), and
 * records every cron tick to ret_dayclose_cron_log.
 */
class Ret_dayclose_model extends CI_Model
{
	function __construct()
	{
		parent::__construct();
	}

	// ---------------------------------------------------------------
	// Global settings (ret_settings: auto_dayclose_time / auto_dayclose_enabled / auto_dayclose_unlock_fallback)
	// ---------------------------------------------------------------

	function getGlobalSettings()
	{
		$rows = $this->db->select('name,value')
			->where_in('name', array('auto_dayclose_time', 'auto_dayclose_enabled', 'auto_dayclose_unlock_fallback'))
			->get('ret_settings')->result_array();

		$settings = array(
			'auto_dayclose_time'            => '19:30:00',
			'auto_dayclose_enabled'         => '1',
			'auto_dayclose_unlock_fallback' => '02:30:00',
		);
		foreach ($rows as $row) {
			$settings[$row['name']] = $row['value'];
		}
		return $settings;
	}

	function saveGlobalSetting($name, $value, $uid = NULL)
	{
		return $this->db->where('name', $name)
			->update('ret_settings', array(
				'value'      => $value,
				'updated_by' => $uid,
				'updated_on' => date('Y-m-d H:i:s'),
			));
	}

	// ---------------------------------------------------------------
	// Branch list + resolved schedule (feeds the settings screen)
	// ---------------------------------------------------------------

	function getBranchDayClosing($id_branch = '')
	{
		$this->db->select('id_branch,is_day_closed,entry_date,day_close_time,updated_on');
		if ($id_branch !== '') {
			$this->db->where('id_branch', $id_branch);
		}
		$result = $this->db->get('ret_day_closing');
		return $id_branch !== '' ? $result->row_array() : $result->result_array();
	}

	function saveBranchDefault($id_branch, $close_time, $uid = NULL)
	{
		return $this->db->where('id_branch', $id_branch)
			->update('ret_day_closing', array(
				'day_close_time' => $close_time,
				'updated_on'     => date('Y-m-d H:i:s'),
			));
	}

	// ---------------------------------------------------------------
	// Today-only overrides (ret_dayclose_override), keyed on (id_branch, override_date)
	// where override_date is that branch's CURRENT entry_date - so it expires the
	// moment that business day closes.
	// ---------------------------------------------------------------

	function getOverride($id_branch, $override_date)
	{
		return $this->db->where('id_branch', $id_branch)
			->where('override_date', $override_date)
			->where('active', 1)
			->get('ret_dayclose_override')->row_array();
	}

	// Atomic upsert (INSERT ... ON DUPLICATE KEY UPDATE) rather than select-then-branch:
	// two admins saving the same branch+date within the same instant must not be able
	// to race past a SELECT and both attempt an INSERT, which the UNIQUE KEY on
	// (id_branch, override_date) would otherwise turn into a hard DB error (V9).
	function saveOverride($id_branch, $override_date, $close_time, $remarks, $uid)
	{
		$now = date('Y-m-d H:i:s');
		$sql = "INSERT INTO ret_dayclose_override
					(id_branch, override_date, close_time, remarks, active, created_by, created_on, updated_by, updated_on)
				VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					close_time = VALUES(close_time),
					remarks    = VALUES(remarks),
					active     = 1,
					updated_by = VALUES(updated_by),
					updated_on = VALUES(updated_on)";
		return $this->db->query($sql, array($id_branch, $override_date, $close_time, $remarks, $uid, $now, $uid, $now));
	}

	// Used only by snapshotTodayDefault() to auto-retain the previous effective time
	// when a standing default is changed mid-day. Not user-facing. The caller has
	// already confirmed (via getOverride(), which filters active=1) that no explicit
	// extension currently governs today, so any row found here must be a soft-deleted
	// one left behind by "Remove extension" - reactivate it rather than skip, otherwise
	// the new default would apply to the still-open business day immediately (V4).
	function saveSystemOverride($id_branch, $override_date, $close_time, $remarks)
	{
		$existing = $this->db->where('id_branch', $id_branch)
			->where('override_date', $override_date)
			->get('ret_dayclose_override')->row_array();
		if ($existing) {
			$this->db->where('id_dayclose_override', $existing['id_dayclose_override'])
				->update('ret_dayclose_override', array(
					'close_time' => $close_time,
					'remarks'    => $remarks,
					'active'     => 1,
					'updated_on' => date('Y-m-d H:i:s'),
				));
			return;
		}
		$this->db->insert('ret_dayclose_override', array(
			'id_branch'     => $id_branch,
			'override_date' => $override_date,
			'close_time'    => $close_time,
			'remarks'       => $remarks,
			'active'        => 1,
			'created_on'    => date('Y-m-d H:i:s'),
		));
	}

	function expireOverride($id_branch, $override_date)
	{
		return $this->db->where('id_branch', $id_branch)
			->where('override_date', $override_date)
			->update('ret_dayclose_override', array(
				'active'     => 0,
				'updated_on' => date('Y-m-d H:i:s'),
			));
	}

	// ---------------------------------------------------------------
	// Resolved effective time for a branch: override > branch default > global default
	// ---------------------------------------------------------------

	function resolveEffectiveTime($branch_row, $global_settings)
	{
		$override = $this->getOverride($branch_row['id_branch'], $branch_row['entry_date']);
		if ($override) {
			return array('time' => $override['close_time'], 'source' => 'override', 'override' => $override);
		}
		if (!empty($branch_row['day_close_time'])) {
			return array('time' => $branch_row['day_close_time'], 'source' => 'branch', 'override' => NULL);
		}
		return array('time' => $global_settings['auto_dayclose_time'], 'source' => 'global', 'override' => NULL);
	}

	// Called just BEFORE a standing default (global or branch) is changed. If this
	// branch's business day is still open and nothing already governs today explicitly,
	// snapshot today's existing effective time as a system-generated override so the
	// change only takes effect from the next business day - the client never has a
	// default change silently close today's still-open day.
	function snapshotTodayDefault($branch_row, $old_global_settings)
	{
		if ($branch_row['is_day_closed'] == 1) {
			return; // already closed - tomorrow will naturally use the new default
		}
		if ($this->getOverride($branch_row['id_branch'], $branch_row['entry_date'])) {
			return; // an explicit today-only extension already governs today - leave it
		}
		$effective = $this->resolveEffectiveTime($branch_row, $old_global_settings);
		$remarks = 'Auto-retained previous default time (' . date('h:i A', strtotime($effective['time'])) . ') because the standing default was changed while today was still open';
		$this->saveSystemOverride($branch_row['id_branch'], $branch_row['entry_date'], $effective['time'], $remarks);
	}

	// ---------------------------------------------------------------
	// Branches + resolved schedule, joined - feeds the Settings screen table directly.
	// ---------------------------------------------------------------

	function getBranchesWithSchedule()
	{
		return $this->db->select('b.id_branch, b.name as branch_name, dc.entry_date, dc.is_day_closed, dc.day_close_time, dc.updated_on')
			->from('branch b')
			->join('ret_day_closing dc', 'dc.id_branch = b.id_branch', 'left')
			->where('b.active', 1)
			->order_by('b.name', 'asc')
			->get()->result_array();
	}

	// ---------------------------------------------------------------
	// Cron run log
	// ---------------------------------------------------------------

	function logCronRun($id_branch, $entry_date, $effective_time, $source, $action, $message = '')
	{
		$this->db->insert('ret_dayclose_cron_log', array(
			'run_at'               => date('Y-m-d H:i:s'),
			'id_branch'            => $id_branch,
			'entry_date'           => $entry_date,
			'effective_close_time' => $effective_time,
			'time_source'          => $source,
			'action'               => $action,
			'message'              => $message,
		));
	}

	function purgeCronLog($days = 90)
	{
		$this->db->where('run_at <', date('Y-m-d H:i:s', strtotime('-' . (int)$days . ' days')))
			->delete('ret_dayclose_cron_log');
	}

	function getCronLog($id_branch = '', $from_date = '', $to_date = '', $limit = 500)
	{
		$this->db->select('l.*, b.name as branch_name')
			->from('ret_dayclose_cron_log l')
			->join('branch b', 'b.id_branch = l.id_branch', 'left')
			->order_by('l.run_at', 'desc')
			->limit($limit);
		if ($id_branch !== '') {
			$this->db->where('l.id_branch', $id_branch);
		}
		if ($from_date !== '') {
			$this->db->where('DATE(l.run_at) >=', $from_date);
		}
		if ($to_date !== '') {
			$this->db->where('DATE(l.run_at) <=', $to_date);
		}
		return $this->db->get()->result_array();
	}

	// Feeds the Run Log summary strip: last tick time, how many branches closed today,
	// how many fallback repairs / failures in the last 30 days.
	function getCronSummary()
	{
		$last = $this->db->select_max('run_at')->get('ret_dayclose_cron_log')->row_array();

		$closed_today = $this->db->where('DATE(run_at)', date('Y-m-d'))
			->where('action', 'closed')
			->count_all_results('ret_dayclose_cron_log');

		$since = date('Y-m-d H:i:s', strtotime('-30 days'));
		$fallback_30d = $this->db->where('run_at >=', $since)
			->where('action', 'reopened_fallback')
			->count_all_results('ret_dayclose_cron_log');

		$failed_30d = $this->db->where('run_at >=', $since)
			->where('action', 'failed')
			->count_all_results('ret_dayclose_cron_log');

		return array(
			'last_run_at'  => $last ? $last['run_at'] : NULL,
			'closed_today' => $closed_today,
			'fallback_30d' => $fallback_30d,
			'failed_30d'   => $failed_30d,
		);
	}
}
