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

		$this->load->library('extensions/FHC-Core-SAPSF/SyncToFhcLib');
		$this->load->library('extensions/FHC-Core-SAPSF/SyncEmployeesToSAPSFLib');
		$this->load->model('extensions/FHC-Core-SAPSF/SAPSFEditOperations/EditUserModel', 'EditUserModel');
		$this->load->model('extensions/FHC-Core-SAPSF/SAPSFQueries/QueryUserModel', 'QueryUserModel');
		$this->load->model('resource/Mitarbeiter_model', 'MitarbeiterModel');
		$this->load->model('extensions/FHC-Core-SAPSF/fhcomplete/FhcDbModel', 'FhcDbModel');
	}

	/**
	 * Initiates alias synchronisation. Alias is synced from fhcomplete to SAPSF.
	 * Uids for which to sync alias are jobinput. No uids-> all employees are synced.
	 */
	public function syncEmployees()
	{
		$this->logInfo('Start employee data synchronization to SAP Success Factors');
		// add new job to queue TODO remove, done by the scheduler!
/*		$startJob = new stdClass();
		$startJob->{jobsqueuelib::PROPERTY_STATUS} = jobsqueuelib::STATUS_NEW;
		$startJob->{jobsqueuelib::PROPERTY_CREATIONTIME} = date('Y-m-d H:i:s');
		$startJob->{jobsqueuelib::PROPERTY_START_TIME} = date('Y-m-d H:i:s');
		$jobresults = $this->addNewJobsToQueue(SyncEmployeesLib::SAPSF_EMPLOYEES_ALIAS, array($startJob));*/

		$lastJobs = $this->getLastJobs(SyncEmployeesToSAPSFLib::SAPSF_EMPLOYEES_TO_SAPSF);

		if (isError($lastJobs))
		{
			$this->logError('An error occurred while updating users data in SAP', getError($lastJobs));
		}
		elseif (hasData($lastJobs))
		{
			// get all input uids of jobs
			$lastJobsData = getData($lastJobs);
			$uids = $this->_mergeEmployeesArray($lastJobsData);
			$mitarbeiterToSync = array();

			// if no uids passed, all employee aliases are synced
			$mitarbeiter = $this->FhcDbModel->getMitarbeiter($uids);

			if (hasData($mitarbeiter))
			{
				$mitarbeiter = getData($mitarbeiter);

				// only update employees which are in sapsf too
				$conffields = $this->config->item('sapsffields');
				$sapsfUidName = $conffields[SyncEmployeesToSAPSFLib::OBJTYPE]['uid'];
				$sapsfemployees = $this->QueryUserModel->getAll(array($sapsfUidName));


				if (hasData($sapsfemployees))
				{
					$sapsfemployees = getData($sapsfemployees);
					foreach ($mitarbeiter as $ma)
					{
						foreach ($sapsfemployees as $sapsfemployee)
						{
							if ($sapsfemployee->{$sapsfUidName} == $ma->uid)
							{
								$matosync = $this->syncemployeestosapsflib->convertEmployeeToSapsf($ma);
								if (!isEmptyArray($matosync))
									$mitarbeiterToSync[$ma->uid] = $this->syncemployeestosapsflib->convertEmployeeToSapsf($ma);
								break;
							}
						}
					}
				}
			}

			// employees to be synced with sapsf
			$syncedMitarbeiter = array();

			$noMa = count($mitarbeiterToSync);

			if ($noMa < 1)
				$this->logInfo('No employees found for update');
			else
			{
				$result = $this->EditUserModel->updateUsers($mitarbeiterToSync);

				if (hasData($result))
				{
					foreach (getData($result) as $arr)
					{
						if (hasData($arr))
						{
							foreach (getData($arr) as $item)
							{
								if (isSuccess($item))
								{
									$key = getData($item);
									$syncedMitarbeiter[$key] = $mitarbeiterToSync[$key];
								}
								else
								{
									$this->logError("An error occurred while updating users data of SAPSF", getError($item));
								}
							}
						}
					}
				}

				if (isError($result))
				{
					$this->logError(getError($result));
				}
				else
				{
					// update jobs, set them to done, write synced aliases as output.
					foreach ($lastJobsData as $job)
					{
						$joboutput = array();
						$decodedInput = json_decode($job->input);
						if ($decodedInput != null)// if there was job input, only output synced mitarbeiter for this input
						{
							foreach ($decodedInput as $el)
							{
								if (isset($syncedMitarbeiter[$el->uid]))
								{
									$joboutput[] = $syncedMitarbeiter;
								}
							}
						}
						else
							$joboutput = $syncedMitarbeiter;

						$job->{jobsqueuelib::PROPERTY_OUTPUT} = json_encode($joboutput);
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
			}
		}
		else
			$this->logInfo('Employees sync to SAPSF: No new jobs found to process');

		$this->logInfo('End employee data synchronization to SAP Success Factors');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Helper function for merging job employee input of multiple jobs
	 */
	private function _mergeEmployeesArray($jobs)
	{
		$mergedEmployeesArray = array();

		if (count($jobs) == 0) return $mergedEmployeesArray;

		foreach ($jobs as $job)
		{
			$decodedInput = json_decode($job->input);
			if ($decodedInput != null)
			{
				foreach ($decodedInput as $el)
				{
					if (!in_array($el->uid, $mergedEmployeesArray))
						$mergedEmployeesArray[] = $el->uid;
				}
			}
		}
		return $mergedEmployeesArray;
	}
}
