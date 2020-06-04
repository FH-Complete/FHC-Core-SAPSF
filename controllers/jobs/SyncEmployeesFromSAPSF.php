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
	}

	/**
	 * Initiates employee synchronisation.
	 */
	public function syncEmployees()
	{
		// add new job to queue TODO remove, done by the scheduler!
		$startJob = new stdClass();
		$startJob->{jobsqueuelib::PROPERTY_STATUS} = jobsqueuelib::STATUS_NEW;
		$startJob->{jobsqueuelib::PROPERTY_CREATIONTIME} = date('Y-m-d H:i:s');
		$startJob->{jobsqueuelib::PROPERTY_START_TIME} = date('Y-m-d H:i:s');
		$jobresults = $this->addNewJobsToQueue(SyncEmployeesFromSAPSFLib::SAPSF_EMPLOYEES_FROM_SAPSF, array($startJob));
		$jobresult = getData($jobresults)[0];

		$this->logInfo('Start employee data synchronization from SAP Success Factors');

		// only sny employees changed after last sync
		$this->JobsQueueModel->addOrder('creationtime', 'DESC');
		$lastJobs = $this->JobsQueueModel->loadWhere(array('status' => jobsqueuelib::STATUS_DONE, 'type' => SyncEmployeesFromSAPSFLib::SAPSF_EMPLOYEES_FROM_SAPSF));
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
				$lastModifiedDateTime = $this->syncfromsapsflib->convertDateToSAPSF($lastjobtime);
			}

			$properties = $this->syncfromsapsflib->getPropertiesFromFieldMappings(SyncEmployeesFromSAPSFLib::OBJTYPE);
			$navproperties = $this->syncfromsapsflib->getNavPropertiesFromFieldMappings(SyncEmployeesFromSAPSFLib::OBJTYPE);

			$employees = $this->QueryUserModel->getAll(array_merge($properties, $navproperties), $navproperties, null/*$lastModifiedDateTime*/);

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
				$syncedemployees = array();

				$results = $this->syncemployeesfromsapsflib->syncEmployeesWithFhc($employees);

				if (hasData($results))
				{
					$results = getData($results);

					foreach ($results AS $result)
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
								$syncedemployees[] = $employee;
							}
						}
					}

					// update job, set it to done, write synced employees as output.
					$jobresult->{jobsqueuelib::PROPERTY_OUTPUT} = json_encode($syncedemployees);
					$jobresult->{jobsqueuelib::PROPERTY_STATUS} = jobsqueuelib::STATUS_DONE;
					$jobresult->{jobsqueuelib::PROPERTY_END_TIME} = date('Y-m-d H:i:s');
					$updatejobsres = $this->updateJobsQueue(SyncEmployeesFromSAPSFLib::SAPSF_EMPLOYEES_FROM_SAPSF, array($jobresult));
					if (isError($updatejobsres))
					{
						$this->logError('An error occurred while updating syncfromsapsfjob', getError($updatejobsres));
					}
				}
				else
					$this->logInfo('No employee data synced from SAP Success Factors');
			}
		}

		$this->logInfo('End employee data synchronization from SAP Success Factors');
	}
}
