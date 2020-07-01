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
		$this->load->model('extensions/FHC-Core-SAPSF/SAPSFQueries/QueryUserModel', 'QueryUserModel');
		$this->load->helper('extensions/FHC-Core-SAPSF/sync_helper');
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

			// only employees changed after last full sync
			$this->JobsQueueModel->addOrder('creationtime', 'DESC');
			$lastDoneJobs = $this->JobsQueueModel->loadWhere(array(
				'status' => jobsqueuelib::STATUS_DONE,
				'type' => SyncEmployeesFromSAPSFLib::SAPSF_EMPLOYEES_FROM_SAPSF,
				'input' => null // input empty -> sync all
				)
			);

			if (isError($lastDoneJobs))
			{
				$this->logError('An error occurred while getting last finished employee sync job', getError($lastDoneJobs));
			}
			else
			{
				$lastModifiedDateTime = null;
				if (hasData($lastDoneJobs))
				{
					$lastJobsData = getData($lastDoneJobs);
					$lastjobtime = $lastJobsData[0]->starttime;
					$lastModifiedDateTime = $this->syncfromsapsflib->convertDateToSAPSF($lastjobtime);
				}

				// input uids if only certain users should be synced
				$syncObj = mergeEmployeesArray($lastNewJobs);
				$uids = $syncObj->uids;

				$selects = $this->syncfromsapsflib->getSelectsFromFieldMappings(SyncEmployeesFromSAPSFLib::OBJTYPE);
				$expands = $this->syncfromsapsflib->getExpandsFromFieldMappings(SyncEmployeesFromSAPSFLib::OBJTYPE);
				$lastmodifiedprops = $this->syncfromsapsflib->getLastModifiedDateTimeProps();

				$uidsToSync = array();
				$maToSync = array();
				if ($syncObj->syncAll)
				{
					$employees = $this->QueryUserModel->getAll($selects, $expands, $lastModifiedDateTime, $lastmodifiedprops);

					if (isError($employees))
					{
						$this->logError(getError($employees));
					}

					if (hasData($employees))
					{
						$empData = getData($employees);

						foreach ($empData as $emp)
						{
							$maToSync[] = $emp;
							$uidsToSync[] = $emp->userId;
						}
					}
				}

				foreach ($uids as $uid)
				{
					if (in_array($uid, $uidsToSync))
						continue;

					$employee = $this->QueryUserModel->getByUserId($uid, $selects, $expands);

					if (isError($employee))
						$this->logError(getError($employee));
					elseif (hasData($employee))
					{
						$maToSync[] = getData($employee)[0];
					}
				}

				$employees = success($maToSync);

				if (!hasData($employees))
				{
					$this->logInfo("No employees found for synchronisation");
				}
				else
				{
					$syncedMitarbeiter = array();

					$results = $this->syncemployeesfromsapsflib->syncEmployeesWithFhc($employees);

					if (hasData($results))
					{
						$results = getData($results);

						foreach ($results as $result)
						{
							if (isError($result))
							{
								$this->logError(getError($result));
							}
							elseif (hasData($result))
							{
								$employeeid = getData($result);
								if (is_string($employeeid))
								{
									$employee = new stdClass();
									$employee->uid = $employeeid;
									$syncedMitarbeiter[$employeeid] = $employee;
								}
							}
						}

						// update jobs, set them to done, write synced employees as output.
						foreach ($lastNewJobs as $job)
						{
							$joboutput = array();
							$decodedInput = json_decode($job->input);
							if ($decodedInput == null)// if there was job input, only output synced mitarbeiter for this input
							{
								foreach ($syncedMitarbeiter as $uidkey => $ma)
								{
									$maobj = new stdClass();
									$maobj->uid = $uidkey;
									$joboutput[] = $maobj;
								}
							}
							else
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
							$job->{jobsqueuelib::PROPERTY_END_TIME} = date('Y-m-d H:i:s');
							$updatedJobs[] = $job;
						}

						// update job, set it to done, write synced employees as output.
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

			$syncObj = mergeEmployeesArray($lastNewJobs);
			$uids = $syncObj->uids;

			$selects = $this->syncfromsapsflib->getSelectsFromFieldMappings(SyncEmployeesFromSAPSFLib::HOURLY_RATE_OBJ);
			$expands = $this->syncfromsapsflib->getExpandsFromFieldMappings(SyncEmployeesFromSAPSFLib::HOURLY_RATE_OBJ);

			$uidsToSync = array();
			$maToSync = array();
			if ($syncObj->syncAll)
			{
				$hourlyrates = $this->QueryUserModel->getAll($selects, $expands);

				if (isError($hourlyrates))
				{
					$this->logError(getError($hourlyrates));
				}

				if (hasData($hourlyrates))
				{
					$empData = getData($hourlyrates);

					foreach ($empData as $emp)
					{
						$maToSync[] = $emp;
						$uidsToSync[] = $emp->userId;
					}
				}
			}

			foreach ($uids as $uid)
			{
				if (in_array($uid, $uidsToSync))
					continue;

				$hourlyRate = $this->QueryUserModel->getByUserId($uid, $selects, $expands);

				if (isError($hourlyRate))
					$this->logError(getError($hourlyRate));
				elseif (hasData($hourlyRate))
				{
					$maToSync[] = getData($hourlyRate)[0];
				}
			}

			$hourlyrates = success($maToSync);

			if (!hasData($hourlyrates))
			{
				$this->logInfo("No hourly rates found for synchronisation");
			}
			else
			{
				$syncedMitarbeiter = array();

				$results = $this->syncemployeesfromsapsflib->syncHourlyRateWithFhc($hourlyrates);

				if (hasData($results))
				{
					$results = getData($results);

					foreach ($results as $result)
					{
						if (isError($result))
						{
							$this->logError(getError($result));
						}
						elseif (hasData($result))
						{
							$employeeid = getData($result);
							if (is_string($employeeid))
							{
								$hourlyRate = new stdClass();
								$hourlyRate->uid = $employeeid;
								$syncedMitarbeiter[$employeeid] = $hourlyRate;
							}
						}
					}

					// update jobs, set them to done, write synced employees as output.
					foreach ($lastNewJobs as $job)
					{
						$joboutput = array();
						$decodedInput = json_decode($job->input);
						if ($decodedInput == null)// if there was job input, only output synced mitarbeiter for this input
						{
							foreach ($syncedMitarbeiter as $uidkey => $ma)
							{
								$maobj = new stdClass();
								$maobj->uid = $uidkey;
								$joboutput[] = $maobj;
							}
						}
						else
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
						$job->{jobsqueuelib::PROPERTY_END_TIME} = date('Y-m-d H:i:s');
						$updatedJobs[] = $job;
					}

					// update job, set it to done, write synced employees as output.
					$updatejobsres = $this->updateJobsQueue(SyncEmployeesFromSAPSFLib::SAPSF_HOURLY_RATES_FROM_SAPSF, $updatedJobs);
					if (isError($updatejobsres))
					{
						$this->logError('An error occurred while updating hourlyratessapsfjob', getError($updatejobsres));
					}
				}
				else
					$this->logInfo('No hourly rate data synced from SAP Success Factors');
			}
		}

		$this->logInfo('End hourly rate data synchronization from SAP Success Factors');
	}
}
