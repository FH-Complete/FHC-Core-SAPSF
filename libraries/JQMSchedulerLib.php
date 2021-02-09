<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library that contains the logic to generate new jobs
 */
class JQMSchedulerLib
{
	private $_ci; // Code igniter instance

	const JOB_TYPE_SYNC_EMPLOYEES_FROM_SAPSF = 'SyncEmployeesFromSAPSF';
	const JOB_TYPE_SYNC_HOURLY_RATES_FROM_SAPSF = 'SyncHourlyRatesFromSAPSF';
	const JOB_TYPE_SYNC_COSTCENTERS_FROM_SAPSF = 'SyncCostcenterFromSAPSF';
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
						WHERE (tbl_mitarbeiter.updateamum >= NOW() - INTERVAL \' %s hours\'
								OR EXISTS (SELECT 1 FROM bis.tbl_bisverwendung
									WHERE tbl_bisverwendung.beginn = CURRENT_DATE
									AND tbl_bisverwendung.mitarbeiter_uid = tbl_mitarbeiter.mitarbeiter_uid))
						AND tbl_mitarbeiter.personalnummer >= 0
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

	/**
	 * Gets uids of employees with hourly rates for sync from SAPSF, criteria:
	 * - fixangestellt AND
	 * - aktiv AND
	 * - does not exist or exists with NULL in sap_stundensatz sync table
	 * @return mixed
	 */
	public function getHourlyRatesToSAPSF()
	{
		$jobInput = null;

		$qry = 'SELECT
					mitarbeiter_uid AS "uid"
				FROM
					public.tbl_mitarbeiter
					JOIN public.tbl_benutzer on(uid=mitarbeiter_uid)
				WHERE
					tbl_mitarbeiter.fixangestellt
					AND tbl_benutzer.aktiv
					AND NOT EXISTS(SELECT * FROM (SELECT tbl_sap_stundensatz.sap_kalkulatorischer_stundensatz
									FROM sync.tbl_sap_stundensatz
									WHERE tbl_sap_stundensatz.mitarbeiter_uid = tbl_mitarbeiter.mitarbeiter_uid
									ORDER BY tbl_sap_stundensatz.insertamum DESC
									LIMIT 1) k WHERE sap_kalkulatorischer_stundensatz IS NOT NULL) 
					AND tbl_mitarbeiter.personalnummer >= 0
					ORDER BY mitarbeiter_uid';

		$dbModel = new DB_Model();

		$maToSyncResult = $dbModel->execReadOnlyQuery(
			$qry
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
}
