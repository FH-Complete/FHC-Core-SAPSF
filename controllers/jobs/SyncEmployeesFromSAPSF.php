<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

class SyncEmployeesFromSAPSF  extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->load->library('extensions/FHC-Core-SAPSF/SyncFromSAPSFLib');
		$this->load->library('extensions/FHC-Core-SAPSF/SyncEmployeesFromSAPSFLib');
	}

	/**
	 * Initiates employee synchronisation.
	 */
	public function syncEmployees()
	{
		$this->logInfo('Start employee data synchronization from SAP Success Factors');

		// get last new job for input
		$lastNewJobs = $this->getLastJobs(SyncEmployeesfromSAPSFLib::SAPSF_EMPLOYEES_FROM_SAPSF);
		if (isError($lastNewJobs))
		{
			$this->logError('An error occurred while getting last new employee sync job', getError($lastNewJobs));
		}
		elseif (hasData($lastNewJobs))
		{
			$lastNewJobs = getData($lastNewJobs);
			$startdate = date('Y-m-d H:i:s');

			// only employees changed after last full sync
			$lastDoneJobs = $this->getJobsByTypeStatusInput(
				SyncEmployeesFromSAPSFLib::SAPSF_EMPLOYEES_FROM_SAPSF,
				jobsqueuelib::STATUS_DONE,
				null
			);

			if (isError($lastDoneJobs))
			{
				$this->logError('An error occurred while getting last finished employee sync job', getError($lastDoneJobs));
			}
			else
			{
				$syncObj = mergeEmployeesArray($lastNewJobs);
				$employees = $this->syncemployeesfromsapsflib->getEmployeesForSync(SyncEmployeesFromSAPSFLib::OBJTYPE, $syncObj, $lastDoneJobs);

				if (isError($employees))
					$this->logError('An error occurred while getting employees', getError($employees));
				elseif (!hasData($employees))
					$this->logInfo('No employees found for synchronisation');
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
								$this->logError(getError($res));
							else
								$syncedMitarbeiter[$key] = getData($res);
						}

						$enddate = date('Y-m-d H:i:s');

						// update jobs, set them to done, write synced employees as output.
						$updatedJobs = $this->_getUpdatedJobs($syncedMitarbeiter, $lastNewJobs, $startdate, $enddate);
						$updatejobsres = $this->updateJobsQueue(SyncEmployeesFromSAPSFLib::SAPSF_EMPLOYEES_FROM_SAPSF, $updatedJobs);

						if (isError($updatejobsres))
						{
							$this->logError('An error occurred while updating syncfromsapsfjob', getError($updatejobsres));
						}
					}
					else
						$this->logInfo('No employee data synced from SAP Success Factors');
				}
			}
		}

		$this->logInfo('End employee data synchronization from SAP Success Factors');
	}

	/**
	 * Initiates hourly rate synchronisation.
	 */
	public function syncHourlyRates()
	{
		$this->logInfo('Start hourly rate data synchronization from SAP Success Factors');

		// get last new job for input
		$lastNewJobs = $this->getLastJobs(SyncEmployeesFromSAPSFLib::SAPSF_HOURLY_RATES_FROM_SAPSF);
		if (isError($lastNewJobs))
		{
			$this->logError('An error occurred while getting last new hourly rate sync job', getError($lastNewJobs));
		}
		elseif (hasData($lastNewJobs))
		{
			$lastNewJobs = getData($lastNewJobs);
			$startdate = date('Y-m-d H:i:s');

			$syncObj = mergeEmployeesArray($lastNewJobs);
			$hourlyrates = $this->syncemployeesfromsapsflib->getEmployeesForSync(SyncEmployeesFromSAPSFLib::HOURLY_RATE_OBJ, $syncObj);

			if (isError($hourlyrates))
				$this->logError('An error occurred while getting hourly rates', getError($hourlyrates));
			if (!hasData($hourlyrates))
			{
				$this->logInfo("No hourly rates found for synchronisation");
			}
			else
			{
				$results = $this->syncemployeesfromsapsflib->syncHourlyRateWithFhc($hourlyrates);

				if (hasData($results))
				{
					$results = getData($results);
					$syncedMitarbeiter = array();
					$syncedMitarbeiterRes = $this->syncemployeesfromsapsflib->getSyncedEmployees($results);

					foreach ($syncedMitarbeiterRes as $key => $res)
					{
						if (isError($res))
							$this->logError(getError($res));
						else
							$syncedMitarbeiter[$key] = getData($res);
					}

					$enddate = date('Y-m-d H:i:s');

					// update jobs, set them to done, write synced employees as output.
					$updatedJobs = $this->_getUpdatedJobs($syncedMitarbeiter, $lastNewJobs, $startdate, $enddate);
					$updatejobsres = $this->updateJobsQueue(SyncEmployeesFromSAPSFLib::SAPSF_HOURLY_RATES_FROM_SAPSF, $updatedJobs);

					if (isError($updatejobsres))
					{
						$this->logError('An error occurred while updating hourlyratessapsfjob', getError($updatejobsres));
					}
				}
				else
					$this->logInfo('No hourly rates data synced from SAP Success Factors');
			}
		}

		$this->logInfo('End hourly rate data synchronization from SAP Success Factors');
	}

	/**
	 * Gets jobs updated after sync.
	 * @param $syncedMitarbeiter array synced employees
	 * @param $lastNewJobs array jobs before sync
	 * @param $startdate string job start date
	 * @param $enddate string job end date
	 * @return array updated jobs
	 */
	private function _getUpdatedJobs($syncedMitarbeiter, $lastNewJobs, $startdate, $enddate)
	{
		$updatedJobs = array();

		foreach ($lastNewJobs as $job)
		{
			$joboutput = array();
			$decodedInput = json_decode($job->input);
			if ($decodedInput == null)
			{
				foreach ($syncedMitarbeiter as $uidkey => $ma)
				{
					$maobj = new stdClass();
					$maobj->uid = $uidkey;
					$joboutput[] = $maobj;
				}
			}
			elseif (is_array($decodedInput))// if there was job input, only output synced mitarbeiter for this input
			{
				foreach ($decodedInput as $el)
				{
					if (isset($syncedMitarbeiter[$el->uid]))
					{
						$maobj = new stdClass();
						$maobj->uid = $el->uid;
						$joboutput[] = $maobj;
					}
				}
			}

			$job->{jobsqueuelib::PROPERTY_OUTPUT} = json_encode($joboutput);
			$job->{jobsqueuelib::PROPERTY_STATUS} = jobsqueuelib::STATUS_DONE;
			$job->{jobsqueuelib::PROPERTY_START_TIME} = $startdate;
			$job->{jobsqueuelib::PROPERTY_END_TIME} = $enddate;
			$updatedJobs[] = $job;
		}

		return $updatedJobs;
	}
}
