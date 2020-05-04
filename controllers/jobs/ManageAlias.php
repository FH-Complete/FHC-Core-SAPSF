<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class ManageAlias  extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->load->library('extensions/FHC-Core-SAPSF/SyncToFhcLib');
		$this->load->library('extensions/FHC-Core-SAPSF/SyncEmployeesLib');
		$this->load->model('extensions/FHC-Core-SAPSF/SAPSFEditOperations/SAPSFEditUserModel', 'EditUserModel');
		$this->load->model('extensions/FHC-Core-SAPSF/SAPSFQueries/QueryUserModel', 'QueryUserModel');
		$this->load->model('person/Benutzer_model', 'BenutzerModel');
		$this->load->model('resource/Mitarbeiter_model', 'MitarbeiterModel');
	}

	/**
	 * Initiates alias synchronisation. Alias is synced from fhcomplete to SAPSF.
	 * Uids for which to sync alias are jobinput. No uids-> all employees are synced.
	 */
	public function syncAlias()
	{
		$this->logInfo('Start mail alias data synchronization with SAP Success Factors');
		// add new job to queue TODO remove, done by the scheduler!
/*		$startJob = new stdClass();
		$startJob->{jobsqueuelib::PROPERTY_STATUS} = jobsqueuelib::STATUS_NEW;
		$startJob->{jobsqueuelib::PROPERTY_CREATIONTIME} = date('Y-m-d H:i:s');
		$startJob->{jobsqueuelib::PROPERTY_START_TIME} = date('Y-m-d H:i:s');
		$jobresults = $this->addNewJobsToQueue(SyncEmployeesLib::SAPSF_EMPLOYEES_ALIAS, array($startJob));*/

		$lastJobs = $this->getLastJobs(SyncEmployeesLib::SAPSF_EMPLOYEES_ALIAS);

		if (isError($lastJobs))
		{
			$this->logError('An error occurred while updating users data in SAP', getError($lastJobs));
		}
		elseif (hasData($lastJobs))
		{
			// get all input uids of jobs
			$lastJobsData = getData($lastJobs);
			$uids = $this->_mergeEmployeesArray($lastJobsData);

			// if no uids passed, all employee aliases are synced
			if (isEmptyArray($uids))
			{
				$mitarbeiter = $this->MitarbeiterModel->getPersonal(true, null, null);

				if (hasData($mitarbeiter))
				{
					$mitarbeiter = getData($mitarbeiter);

					// only update employees which are in sapsf too
					$conffieldmappings = $this->config->item('fieldmappings');
					$sapsfUidName = array_keys($conffieldmappings['employee']['benutzer'], 'uid');
					$sapsfemployees = $this->QueryUserModel->getAll(array($sapsfUidName[0]));

					if (hasData($sapsfemployees))
					{
						$sapsfemployees = getData($sapsfemployees);
						foreach ($mitarbeiter as $ma)
						{
							foreach ($sapsfemployees as $sapsfemployee)
							{
								if ($sapsfemployee->{$sapsfUidName[0]} == $ma->uid)
								{
									$uids[] = $ma->uid;
									break;
								}
							}
						}
					}
				}
			}

			// aliases to be synced with sapsf
			$aliasforsapsf = array();
			$syncedAliases = array();

			foreach ($uids as $uid)
			{
				$alias = $this->_manageAliasForUid($uid);
				if (!isEmptyString($alias))
				{
					$aliasforsapsf[$uid] = $alias;
				}
			}

			$noAliases = count($aliasforsapsf);

			if ($noAliases < 1)
				$this->logInfo('No aliases of employees found for update');
			else
			{
				$result = $this->EditUserModel->updateEmails($aliasforsapsf);
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
									$syncedAliases[$key] = $aliasforsapsf[$key];
								}
								else
									$this->logError('An error occurred while updating users data in SAPSF', getError($arr));
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
						if ($decodedInput != null)
						{
							foreach ($decodedInput as $el)
							{
								if (isset($syncedAliases[$el->uid]))
								{
									$outputObj = new stdClass();
									$outputObj->uid = $el->uid;
									$outputObj->alias = $aliasforsapsf[$el->uid];
									$joboutput[] = $outputObj;
								}
							}
						}
						else
							$joboutput = $syncedAliases;

						$job->{jobsqueuelib::PROPERTY_OUTPUT} = json_encode($joboutput);
						$job->{jobsqueuelib::PROPERTY_STATUS} = jobsqueuelib::STATUS_DONE;
						$job->{jobsqueuelib::PROPERTY_END_TIME} = date('Y-m-d H:i:s');
						$updatedJobs[] = $job;
					}
					$this->updateJobsQueue(SyncEmployeesLib::SAPSF_EMPLOYEES_ALIAS, $updatedJobs);
				}
			}
		}
		else
			$this->logInfo('SAPSF alias sync: No new jobs found to process');

		$this->logInfo('End mail alias data synchronization with SAP Success Factors');
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
					if (!in_array($el, $mergedEmployeesArray))
						$mergedEmployeesArray[] = $el->uid;
				}
			}
		}
		return $mergedEmployeesArray;
	}

	/**
	 * Takes care of actions concerting alias for a user.
	 * - If non-empty alias exists, it is returned.
	 * - If alias is not present, it is generated.
	 * - if alias is generated, it is updated in fhcomplete.
	 * @param string $uid
	 * @return string alias for the uid or empty string
	 */
	private function _manageAliasForUid($uid)
	{
		$alias = '';
		$this->BenutzerModel->addSelect('alias');
		$aliasres = $this->BenutzerModel->loadWhere(array('uid' => $uid));
		$hasAlias = false;

		if (hasData($aliasres))
		{
			$aliasres = getData($aliasres);
			$aliasres = $aliasres[0]->alias;
			if (!isEmptyString($aliasres))
			{
				// if there is a non-empty alias in fhc, it is used
				$alias = $aliasres;
				$hasAlias = true;
			}
		}

		if (!$hasAlias)
		{
			// no non-empty alias found -> generate
			$genAlias = $this->BenutzerModel->generateAlias($uid);
			if (hasData($genAlias))
			{
				$genAlias = getData($genAlias);

				if (!isEmptyString($genAlias))
				{
					// set alias in fhcomplete
					$this->BenutzerModel->update(array('uid' => $uid), array('alias' => $genAlias));
					$alias = $genAlias;
				}
			}
		}
		return $alias;
	}
}
