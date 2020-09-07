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
				'getSyncEmployeesFromSAPSF' => 'basis/mitarbeiter:rw',
				'postSyncEmployeesToSAPSF' => 'basis/mitarbeiter:rw'
			)
		);

		$this->load->library('extensions/FHC-Core-SAPSF/SyncFromSAPSFLib');
		$this->load->library('extensions/FHC-Core-SAPSF/SyncToSAPSFLib');
		$this->load->library('extensions/FHC-Core-SAPSF/SyncEmployeesFromSAPSFLib');
		$this->load->library('extensions/FHC-Core-SAPSF/SyncEmployeesToSAPSFLib');

		// It also specify parameters to set database fields
		$params = array(
			'classIndex' => 5,
			'functionIndex' => 5,
			'lineIndex' => 4,
			'dbLogType' => 'SAPSFManualSync', // required
			'dbExecuteUser' => 'employeeSync'
		);

		$this->load->library('LogLib', $params);
	}

	public function index()
	{
		$this->load->library('WidgetLib');

		$this->load->view(
			'extensions/FHC-Core-SAPSF/manualSAPSFEmployeeSync'
		);
	}

	/**
	 * Synchronizes employees to fhc with passed uids.
	 */
	public function getSyncEmployeesFromSAPSF()
	{
		$uids = $this->input->get('uids');
		$result = null;

		if (checkStringArray($uids))
		{
			$syncObj = new stdClass();
			$syncObj->uids = $uids;
			$syncObj->syncAll = false;

			//$this->_logInfo('Manual Employee Sync from SAPSF to FHC start');
			$employees = $this->syncemployeesfromsapsflib->getEmployeesForSync(SyncEmployeesFromSAPSFLib::OBJTYPE, $syncObj);

			if (isError($employees))
			{
				//$this->_logError('An error occurred while getting employees', getError($employees));
				$result = error(getError($employees));
			}
			elseif (!hasData($employees))
				echo "nope";
				//$this->_logInfo('No employees found for synchronisation');
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
							//$this->_logError(getError($res));
						}
						else
							$syncedMitarbeiter[$key] = getData($res);
					}

					$result = success($syncedMitarbeiter);
				}
			}
			//$this->_logInfo('Manual Employee Sync from SAPSF to FHC end');
		}
		else
			$result = error('Invalid uids passed');

		$this->outputJson($result);
	}

	public function postSyncEmployeesToSAPSF()
	{
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
									if (isSuccess($item))
									{
										$key = getData($item);
										$resobj = new stdClass();
										$resobj->key = $key;
										$syncedMitarbeiter[] = $resobj;
									}
									else
									{
										$this->_logError("An error occurred while updating users data of SAPSF", getError($item));
									}
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
		$this->_log(LogLib::INFO, 'Employeesync info', $response, $parameters);
	}

	/**
	 * Writes a cronjob debug log
	 */
/*	private function logDebug($response, $parameters = null)
	{
		$this->_log(LogLib::DEBUG, 'Cronjob debug', $response, $parameters);
	}*/

	/**
	 * Writes a cronjob warning log
	 */
	private function _logWarning($response, $parameters = null)
	{
		$this->_log(LogLib::WARNING, 'Employeesync warning', $response, $parameters);
	}

	/**
	 * Writes a cronjob error log
	 */
	private function _logError($response, $parameters = null)
	{
		$this->_log(LogLib::ERROR, 'Employeesync error', $response, $parameters);
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
