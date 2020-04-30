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

		$this->load->library('extensions/FHC-Core-SAPSF/SyncToFhcLib');
		$this->load->library('extensions/FHC-Core-SAPSF/SyncEmployeesLib');
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
		$jobresults = $this->addNewJobsToQueue(SyncEmployeesLib::SAPSF_EMPLOYEES_CREATE, array($startJob));
		$jobresult = getData($jobresults)[0];

		$this->logInfo('Start employee data synchronization with SAP Success Factors');

		// only sny employees changed after last sync
		$this->JobsQueueModel->addOrder('creationtime', 'DESC');
		$lastJobs = $this->JobsQueueModel->loadWhere(array('status' => jobsqueuelib::STATUS_DONE, 'type' => SyncEmployeesLib::SAPSF_EMPLOYEES_CREATE));
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
				$lastModifiedDateTime = $this->synctofhclib->_convertDateToSAPSF($lastjobtime);
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
				$syncedemployees = array();

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

					if (!isEmptyArray($syncedemployees))
					{
						$this->logInfo('SAP Success Factors employees successfully synced');
						// update job, set it to done, write synced employees as output.
						$jobresult->{jobsqueuelib::PROPERTY_OUTPUT} = json_encode($syncedemployees);
						$jobresult->{jobsqueuelib::PROPERTY_STATUS} = jobsqueuelib::STATUS_DONE;
						$jobresult->{jobsqueuelib::PROPERTY_END_TIME} = date('Y-m-d H:i:s');
						$this->updateJobsQueue(SyncEmployeesLib::SAPSF_EMPLOYEES_CREATE, array($jobresult));
					}

				}
				else
					$this->logInfo('No employee data synced with SAP Success Factors');
			}
		}

		if (isError($lastJobs))
		{
			$this->logError('An error occurred while updating sync job', getError($lastJobs));
		}

		$this->logInfo('End employee data synchronization with SAP Success Factors');
	}
}