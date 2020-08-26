<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

class SyncEmployeesToSapsf  extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->load->library('extensions/FHC-Core-SAPSF/SyncEmployeesToSAPSFLib');
		$this->load->model('extensions/FHC-Core-SAPSF/SAPSFEditOperations/EditUserModel', 'EditUserModel');
	}

	/**
	 * Initiates synchronisation from fhcomplete to SAPSF.
	 * Uids for which to sync alias are jobinput. No uids-> all employees are synced.
	 */
	public function syncEmployees()
	{
		$this->logInfo('Start employee data synchronization to SAP Success Factors');

		$lastJobs = $this->getLastJobs(SyncEmployeesToSAPSFLib::SAPSF_EMPLOYEES_TO_SAPSF);

		if (isError($lastJobs))
		{
			$this->logError('An error occurred while updating users data in SAP', getError($lastJobs));
		}
		elseif (hasData($lastJobs))
		{
			// get all input uids of jobs
			$lastJobsData = getData($lastJobs);
			$syncobj = mergeEmployeesArray($lastJobsData);
			$uids = $syncobj->syncAll ? null : $syncobj->uids;
			$mitarbeiterToSync = $this->syncemployeestosapsflib->getEmployeesForSync($uids);

			// employees to be synced with sapsf
			if (isError($mitarbeiterToSync))
				$this->logError(getError($mitarbeiterToSync));
			elseif (hasData($mitarbeiterToSync))
			{
				$syncedMitarbeiter = array();
				$mitarbeiterToSync = getData($mitarbeiterToSync);
				$results = $this->EditUserModel->updateUsers($mitarbeiterToSync);

				foreach ($results as $callresult)
				{
					if (isError($callresult))
					{
						$this->logError(getError($callresult));
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
										$this->logError("An error occurred while updating users data of SAPSF", getError($item));
									}
								}
							}
						}
					}
				}

				// update jobs, set them to done, write synced aliases as output.
				$updatedJobs = array();
				foreach ($lastJobsData as $job)
				{
					$job->{jobsqueuelib::PROPERTY_OUTPUT} = json_encode($syncedMitarbeiter);
					$job->{jobsqueuelib::PROPERTY_STATUS} = jobsqueuelib::STATUS_DONE;
					$job->{jobsqueuelib::PROPERTY_END_TIME} = date('Y-m-d H:i:s');
					$updatedJobs[] = $job;
				}
				$updatejobsres = $this->updateJobsQueue(SyncEmployeesToSAPSFLib::SAPSF_EMPLOYEES_TO_SAPSF, $updatedJobs);
				if (isError($updatejobsres))
				{
					$this->logError('An error occurred while updating synctosapsfjob', getError($updatejobsres));
				}
			}
			else
				$this->logInfo('No employees found for update');
		}
		else
			$this->logInfo('Employees sync to SAPSF: No new jobs found to process');

		$this->logInfo('End employee data synchronization to SAP Success Factors');
	}
}
