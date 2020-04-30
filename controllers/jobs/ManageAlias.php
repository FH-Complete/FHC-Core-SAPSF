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
		$this->load->model('person/Benutzer_model', 'BenutzerModel');
		$this->load->model('resource/Mitarbeiter_model', 'MitarbeiterModel');
	}

	public function syncAlias($uids)
	{
		// add new job to queue TODO remove, done by the scheduler!
		$startJob = new stdClass();
		$startJob->{jobsqueuelib::PROPERTY_STATUS} = jobsqueuelib::STATUS_NEW;
		$startJob->{jobsqueuelib::PROPERTY_CREATIONTIME} = date('Y-m-d H:i:s');
		$startJob->{jobsqueuelib::PROPERTY_START_TIME} = date('Y-m-d H:i:s');
		$jobresults = $this->addNewJobsToQueue(SyncEmployeesLib::SAPSF_EMPLOYEES_CREATE, array($startJob));
		$jobresult = getData($jobresults)[0];

		//$uids = array('bison', 'oesi', 'karpenko');

		$this->logInfo('Start mail alias data synchronization with SAP Success Factors');

		$aliasforsapsf = array();
		if (is_array($uids))
		{
			foreach ($uids as $uid)
			{
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
						$aliasforsapsf[$uid] = $aliasres;
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
							$aliasforsapsf[$uid] = $genAlias;
						}
					}
				}
			}

			$noAliases = count($aliasforsapsf);

			if ($noAliases === 0)
				$this->logInfo('No aliases of employees found for update');
			else
			{
				if ($noAliases === 1)
				{
					foreach ($aliasforsapsf as $key => $item)
					{
						$result = $this->EditUserModel->updateEmail($key, $item);
						break;
					}
				}
				elseif ($noAliases > 1)
					$result = $this->EditUserModel->updateEmails($aliasforsapsf);

				if (isError($result))
				{
					$this->logError(getError($result));
				}
				else
				{
					// update job, set it to done, write synced aliases as output.
					$jobresult->{jobsqueuelib::PROPERTY_OUTPUT} = json_encode($aliasforsapsf);
					$jobresult->{jobsqueuelib::PROPERTY_STATUS} = jobsqueuelib::STATUS_DONE;
					$jobresult->{jobsqueuelib::PROPERTY_END_TIME} = date('Y-m-d H:i:s');
					$this->updateJobsQueue(SyncEmployeesLib::SAPSF_EMPLOYEES_ALIAS, array($jobresult));
				}
			}
		}

		$this->logInfo('End mail alias data synchronization with SAP Success Factors');
	}

	public function syncAllAlias()
	{
		$uids = array();
		$mitarbeiter = $this->MitarbeiterModel->getPersonal(true, null, null);

		if (hasData($mitarbeiter))
		{
			$mitarbeiter = getData($mitarbeiter);
			foreach ($mitarbeiter as $ma)
			{
				$uids[] = $ma->uid;
			}
		}
		$this->syncAlias($uids);
	}
}
