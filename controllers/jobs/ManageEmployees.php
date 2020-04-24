<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

class ManageEmployees  extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SyncEmployeesLib
		$this->load->library('extensions/FHC-Core-SAPSF/SyncToFhcLib');
		$this->load->library('extensions/FHC-Core-SAPSF/SyncEmployeesLib');
		$this->load->model('extensions/FHC-Core-SAPSF/SAPSFQueries/QueryUserModel', 'QueryUserModel');
	}

	/**
	 * Initiates employee synchronisation.
	 */
	public function syncEmployees()
	{
		$this->logInfo('Start employee data synchronization with SAP Success Factors');

		// only sny employees changed after last sync
		$this->JobsQueueModel->addOrder('creationtime', 'DESC');
		$lastJobs = $this->JobsQueueModel->loadWhere(array('status' => 'done', 'type' => SyncEmployeesLib::SAPSF_EMPLOYEES_CREATE));
		if (isError($lastJobs))
		{
			$this->logError('An error occurred while getting last employee sync job', getError($lastJobs));
		}
		else
		{
			$lastModifiedDateTime = null;
			if (hasData($lastJobs))
			{
				$jobs = getData($lastJobs);
				$lastjobtime = $jobs[0]->starttime;
				$lastModifiedDateTime = $this->_convertToLastModifiedDateTime($lastjobtime);
			}

			$conffieldmappings = $this->config->item('fieldmappings');

			$selects = array();
			foreach ($conffieldmappings[syncemployeeslib::OBJTYPE] as $mappingset)
			{
				foreach ($mappingset as $sapsfkey => $fhcvalue)
				{
					$selects[] = $sapsfkey;
				}
			}

			$employees = $this->QueryUserModel->getAll($selects, $lastModifiedDateTime);

			if (isError($employees))
			{
				$this->logError(getError($employees));
			}
			elseif (!hasData($employees))
			{
				$this->logInfo("No employees found for synchronisation");
			}
			else
			{
				$results = $this->syncemployeeslib->syncEmployeesWithFhc($employees);

				if (hasData($results))
				{
					$results = getData($results);

					foreach ($results AS $result)
					{
						if (isError($result))
						{
							$this->logError(getError($result));
						}
					}
				}
				else
					$this->logInfo('No employee data synced with SAP Success Factors');
			}
		}


		$this->logInfo('End employee data synchronization with SAP Success Factors');
	}

	/**
	 * Converts an fhc db timestamp to LastModifieDdatetime params format in sapsf
	 * @param $timestamp
	 * @return string
	 */
	private function _convertToLastModifiedDateTime($timestamp)
	{
		date_default_timezone_set(synctofhclib::TOTIMEZONE);

		try
		{
			$datetime = new DateTime($timestamp);
		}
		catch (Exception $e)
		{
			return $timestamp;
		}

		$sftimezone = new DateTimeZone(synctofhclib::FROMTIMEZONE);
		$datetime->setTimezone($sftimezone);
		$timestamptz = $datetime->format('Y-m-d H:i:s');
		return str_replace(' ', 'T', $timestamptz);
	}
}