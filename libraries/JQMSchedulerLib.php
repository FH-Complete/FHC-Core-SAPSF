<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library that contains the logic to generate new jobs
 */
class JQMSchedulerLib
{
	private $_ci; // Code igniter instance

	const JOB_TYPE_SYNC_EMPLOYEES_FROM_SAPSF = 'SyncEmployeesFromSAPSF';
	const JOB_TYPE_SYNC_EMPLOYEES_TO_SAPSF = 'SyncEmployeesToSAPSF';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Gets uids of employees to sync, criteria:
	 * - mitarbeiter updateamum modified recently OR
	 * - startdate of employee contract is current date
	 * @return mixed
	 */
	public function getSyncemployeesToSAPSF()
	{
		// load config
		$this->_ci->config->load('extensions/FHC-Core-SAPSF/SAPSFSyncparams');

		$daysInPast = $this->_ci->config->item('FHC-Core-SAPSFSyncparams')['fhcMaHoursInPast'];

		if (is_numeric($daysInPast) && $daysInPast >= 0)
		{
			$jobInput = null;

			$qry = 'SELECT mitarbeiter_uid AS "uid" FROM public.tbl_mitarbeiter
						WHERE tbl_mitarbeiter.updateamum >= NOW() - INTERVAL \' %s hours\'
						OR EXISTS (SELECT 1 FROM bis.tbl_bisverwendung
									WHERE tbl_bisverwendung.beginn = CURRENT_DATE
									AND tbl_bisverwendung.mitarbeiter_uid = tbl_mitarbeiter.mitarbeiter_uid)
						ORDER BY mitarbeiter_uid';

			$dbModel = new DB_Model();

			$maToSyncResult = $dbModel->execReadOnlyQuery(
				sprintf($qry, $daysInPast)
			);

			// If error occurred while retrieving new users from database then return the error
			if (isError($maToSyncResult)) return $maToSyncResult;

			// If new users are present
			if (hasData($maToSyncResult))
			{
				$jobInput = json_encode(getData($maToSyncResult));
			}

			return success($jobInput);
		}
		else
			return error('Invalid daysInPast parameter');
	}

	public function checkUidInput($uids)
	{
		$valid = false;

		if (!isset($uids))
			$valid = true;
		elseif (is_array($uids))
		{
			$valid = true;
			foreach ($uids as $uid)
			{
				if (!isset($uid->uid))
				{
					$valid = false;
					break;
				}
			}
		}

		return $valid;
	}

	public function createSyncEmployeesInput($uids)
	{
		$syncinput = null;

		if (isset($uids) && $this->checkUidInput($uids))
		{
			foreach ($uids as $uid)
			{
				$uidobj = new stdClass();
				$syncinput[] = $uidobj;
			}
		}

		return $syncinput;
	}
}
