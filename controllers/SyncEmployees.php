<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

class SyncEmployees extends Auth_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index' => 'basis/mitarbeiter:rw',
				'syncEmployeesFromSAPSF' => 'basis/mitarbeiter:rw',
				'syncEmployeesToSAPSF' => 'basis/mitarbeiter:rw',
				'getSyncEmployeesFromSAPSF' => 'basis/mitarbeiter:rw',
				'postSyncEmployeesToSAPSF' => 'basis/mitarbeiter:rw'
			)
		);

		$this->_uid = getAuthUID();

		if (!$this->_uid)
			show_error('User authentification failed');

		$this->load->library('LogLib', array(
				'classIndex' => 5,
				'functionIndex' => 5,
				'lineIndex' => 4,
				'dbLogType' => 'job', // required
				'dbExecuteUser' => $this->_uid)
		);
	}

	public function index()
	{
		$this->load->view(
			'extensions/FHC-Core-SAPSF/manualEmployeeSyncFromSAPSF'
		);
	}

	/**
	 * Shows GUI to sync from SAPSF.
	 */
	public function syncEmployeesFromSAPSF()
	{
		$this->load->view(
			'extensions/FHC-Core-SAPSF/manualEmployeeSyncFromSAPSF'
		);
	}

	/**
	 * Shows GUI to sync to SAPSF.
	 */
	public function syncEmployeesToSAPSF()
	{
		$this->load->view(
			'extensions/FHC-Core-SAPSF/manualEmployeeSyncToSAPSF'
		);
	}

	/**
	 * Synchronizes employees (passed uids) from SAPSF to fhcomplete.
	 */
	public function getSyncEmployeesFromSAPSF()
	{
		$this->load->library('extensions/FHC-Core-SAPSF/SyncEmployeesFromSAPSFLib');

		$uids = $this->input->get('uids');
		$result = null;

		if (checkStringArray($uids))
		{
			$syncObj = new stdClass();
			$syncObj->uids = $uids;
			$syncObj->syncAll = false;

			$this->_logInfo('Manual Employee Sync from SAPSF to FHC start');
			$employees = $this->syncemployeesfromsapsflib->getEmployeesForSync(SyncEmployeesFromSAPSFLib::OBJTYPE, $syncObj);

			if (isError($employees))
			{
				$this->_logError('An error occurred while getting employees', getError($employees));
				$result = error(getError($employees));
			}
			elseif (!hasData($employees))
				$this->_logInfo('No employees found for synchronisation');
			else
			{
				$results = $this->syncemployeesfromsapsflib->syncEmployeesWithFhc($employees);

				if (hasData($results))
				{
					$results = getData($results);
					$syncedMitarbeiter = array();
					$syncedMitarbeiterRes = $this->syncemployeesfromsapsflib->getSyncedEmployees($results);

					foreach ($syncedMitarbeiterRes as $key => $res)
					{
						if (isError($res))
						{
							$this->_logError(getError($res));
							$syncedMitarbeiter[$key] = getError($res);
						}
						else
							$syncedMitarbeiter[$key] = getData($res);
					}

					$result = success($syncedMitarbeiter);
				}
			}
			$this->_logInfo('Manual Employee Sync from SAPSF to FHC end');
		}
		else
			$result = error('Invalid uids passed');

		$this->outputJson($result);
	}

	/**
	 * Synchronizes employees (passed uids) from fhcomplete to SAPSF.
	 */
	public function postSyncEmployeesToSAPSF()
	{
		$this->load->model('extensions/FHC-Core-SAPSF/SAPSFEditOperations/EditUserModel', 'EditUserModel');

		$this->load->library('extensions/FHC-Core-SAPSF/SyncToSAPSFLib');
		$this->load->library('extensions/FHC-Core-SAPSF/SyncEmployeesToSAPSFLib');

		$uids = $this->input->get('uids');
		$result = null;

		if (checkStringArray($uids))
		{
			$mitarbeiterToSync = $this->syncemployeestosapsflib->getEmployeesForSync($uids);

			// employees to be synced with sapsf
			if (isError($mitarbeiterToSync))
				$this->_logError(getError($mitarbeiterToSync));
			elseif (hasData($mitarbeiterToSync))
			{
				$syncedMitarbeiter = array();
				$mitarbeiterToSync = getData($mitarbeiterToSync);
				$results = $this->EditUserModel->updateUsers($mitarbeiterToSync);

				foreach ($results as $callresult)
				{
					if (isError($callresult))
					{
						$this->_logError(getError($callresult));
					}
					elseif (hasData($callresult))
					{
						foreach (getData($callresult) as $arr)
						{
							if (hasData($arr))
							{
								foreach (getData($arr) as $item)
								{
									$resobj = new stdClass();
									if (isSuccess($item))
									{
										$key = getData($item);
										$resobj->key = $key;
									}
									else
									{
										$error = getError($item);
										$resobj->error = $error;
										$this->_logError("An error occurred while updating users data of SAPSF", $error);
									}
									$syncedMitarbeiter[] = $resobj;
								}
							}
						}
					}
				}

				$result = success($syncedMitarbeiter);
			}
		}
		else
			$result = error('Invalid uids passed');

		$this->outputJson($result);
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Writes a cronjob info log
	 */
	private function _logInfo($response, $parameters = null)
	{
		$this->_log(LogLib::INFO, 'JQW - info', $response, $parameters);
	}

	/**
	 * Writes a cronjob warning log
	 */
	private function _logWarning($response, $parameters = null)
	{
		$this->_log(LogLib::WARNING, 'JQW - warning', $response, $parameters);
	}

	/**
	 * Writes a cronjob error log
	 */
	private function _logError($response, $parameters = null)
	{
		$this->_log(LogLib::ERROR, 'JQW - error', $response, $parameters);
	}

	/**
	 * Writes a log to database
	 */
	private function _log($level, $requestId, $response, $parameters)
	{
		$data = new stdClass();

		$data->response = $response;
		if ($parameters != null) $data->parameters = $parameters;

		switch($level)
		{
			case LogLib::INFO:
				$this->loglib->logInfoDB($requestId, json_encode(success($data, LogLib::INFO)));
				break;
/*			case LogLib::DEBUG:
				$this->loglib->logDebugDB($requestId, json_encode(success($data, LogLib::DEBUG)));
				break;*/
			case LogLib::WARNING:
				$this->loglib->logWarningDB($requestId, json_encode(error($data, LogLib::WARNING)));
				break;
			case LogLib::ERROR:
				$this->loglib->logErrorDB($requestId, json_encode(error($data, LogLib::ERROR)));
				break;
		}
	}
}
