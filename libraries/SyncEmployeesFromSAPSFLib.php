<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once('include/functions.inc.php');// needed for activation key generation

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Success Factors
 */
class SyncEmployeesFromSAPSFLib extends SyncFromSAPSFLib
{
	const OBJTYPE = 'User';
	const HOURLY_RATE_OBJ = 'HourlyRate';
	const SAPSF_EMPLOYEES_FROM_SAPSF = 'SyncEmployeesFromSAPSF';
	const SAPSF_HOURLY_RATES_FROM_SAPSF = 'SyncHourlyRatesFromSAPSF';

	protected $_convertfunctions = array( // extraParams -> additional parameters coming from SF which are needed by function
		'mitarbeiter' =>array(
			'stundensatz' => array(
				'function' => '_selectStundensatzForFhc',
				'extraParams' => array(
					array('table' => 'sap_stundensatz_typ', 'name' => 'sap_stundensatz_typ'),
					array('table' => 'sap_stundensatz_typ', 'name' => 'sap_stundensatz_startdate')
				)
			)
		),
		'person' => array(
			'gebdatum' => array(
				'function' => '_convertDateToFhc',
				'extraParams' => null
			),
			'geburtsnation' => array(
				'function' => '_convertNationToFhc',
				'extraParams' => null
			),
			'staatsbuergerschaft' => array(
				'function' => '_convertNationToFhc',
				'extraParams' => null
			),
			'svnr' => array(
				'function' => '_selectKzForFhc',
				'extraParams' => array(
					array('table' => 'kztyp', 'name' => 'kztyp', 'fhcfield' => 'svnr')
				)
			),
			'ersatzkennzeichen' => array(
				'function' => '_selectKzForFhc',
				'extraParams' => array(
					array('table' => 'kztyp', 'name' => 'kztyp', 'fhcfield' => 'ersatzkennzeichen')
				)
			)
		),
		'kontaktmail' => array(
			'kontakt' => array(
				'function' => '_selectEmailForFhc',
				'extraParams' => array(
					array('table' => 'mailtyp', 'name' => 'emailtyp')
				)
			)
		),
		'kontakttelefon' => array(
			'kontakt' => array(
				'function' => '_selectPhoneForFhc',
				'extraParams' => array(
					array('table' => 'telefondaten', 'name' => 'telefontyp'),
					array('table' => 'telefondaten', 'name' => 'landesvorwahl'),
					array('table' => 'telefondaten', 'name' => 'ortsvorwahl'),
					array('table' => 'telefondaten', 'name' => 'telefonklappe')
				)
			)
		),
		'sap_kalkulatorischer_stundensatz' => array(
			'sap_kalkulatorischer_stundensatz' => array(
				'function' => '_selectKalkStundensatzForFhc',
				'extraParams' => array(
					array('table' => 'sap_stundensatz_typ', 'name' => 'sap_stundensatz_typ'),
					array('table' => 'sap_stundensatz_typ', 'name' => 'sap_stundensatz_startdate')
				)
			)
		),
	);

	/**
	 * SyncEmployeesFromSAPSFLib constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->ci->load->helper('extensions/FHC-Core-SAPSF/sync_helper');

		//load models
		$this->ci->load->model('person/person_model', 'PersonModel');
		$this->ci->load->model('ressource/mitarbeiter_model', 'MitarbeiterModel');
		$this->ci->load->model('person/benutzer_model', 'BenutzerModel');
		$this->ci->load->model('person/adresse_model', 'AdresseModel');
		$this->ci->load->model('person/kontakt_model', 'KontaktModel');
		$this->ci->load->model('codex/Nation_model', 'NationModel');
		$this->ci->load->model('extensions/FHC-Core-SAPSF/SAPSFQueries/QueryUserModel', 'QueryUserModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * @param $objtype
	 * @param $newJobObj object contains information for sync (uids, fullsync)
	 * @param $lastDoneJobs oject jobs executed last
	 * @return object the mitarbeiter to sync on success, error otherwise
	 */
	public function getEmployeesForSync($objtype, $newJobObj, $lastDoneJobs = null)
	{
		$lastModifiedDateTime = null;
		if (hasData($lastDoneJobs))
		{
			$lastJobsData = getData($lastDoneJobs);
			$lastjobtime = $lastJobsData[0]->starttime;
			$lastModifiedDateTime = $this->convertDateToSAPSF($lastjobtime);
		}

		// input uids if only certain users should be synced
		$uids = $newJobObj->uids;

		$selects = $this->getSelectsFromFieldMappings($objtype);
		$expands = $this->getExpandsFromFieldMappings($objtype);
		$lastmodifiedprops = isset($lastModifiedDateTime) ? $this->getLastModifiedDateTimeProps() : null;

		$uidsToSync = array();
		$maToSync = array();
		if ($newJobObj->syncAll)
		{
			$employees = $this->ci->QueryUserModel->getAll($selects, $expands, $lastModifiedDateTime, $lastmodifiedprops);

			if (isError($employees))
			{
				return error(getError($employees));
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

			$employee = $this->ci->QueryUserModel->getByUserId($uid, $selects, $expands);

			if (isError($employee))
				return error(getError($employee));
			elseif (hasData($employee))
			{
				$maToSync[] = getData($employee);
			}
		}

		return success($maToSync);
	}

	/**
	 * Gets synced employees uids, or errors from result.
	 * @param $results
	 * @return array
	 */
	public function getSyncedEmployees($results)
	{
		$syncedMitarbeiterRes = array();

		foreach ($results as $result)
		{
			if (isError($result))
			{
				$syncedMitarbeiterRes[] = error(getError($result));
			}
			elseif (hasData($result))
			{
				$employeeid = getData($result);
				if (is_string($employeeid))
				{
					$employee = new stdClass();
					$employee->uid = $employeeid;
					$syncedMitarbeiterRes[$employeeid] = success($employee);
				}
			}
		}

		return $syncedMitarbeiterRes;
	}

	/**
	 * Starts employee sync. Converts given employee data to fhc format and saves the employee.
	 * @param $employees
	 * @return object
	 */
	public function syncEmployeesWithFhc($employees)
	{
		if ($this->_syncpreview === false)
		{
			$convEmployees = $this->getEmployeesForFhcSync($employees, self::OBJTYPE);

			$results = array();

			if (is_array($convEmployees))
			{
				foreach ($convEmployees as $employee)
				{
					$result = $this->_saveMitarbeiter($employee);
					$results[] = $result;
				}
			}

			return success($results);
		}
		else
		{
			$mas = $this->getEmployeesForFhcSync($employees, self::OBJTYPE);
			printAndDie($mas);
		}
	}

	/**
	 * Starts hourly rates sync.
	 * Converts given hourly rate data to fhc format and saves the hourly rates object.
	 * @param $hourlyrates
	 * @return object
	 */
	public function syncHourlyRateWithFhc($hourlyrates)
	{
		if ($this->_syncpreview === false)
		{
			$convEmployees = $this->getEmployeesForFhcSync($hourlyrates, self::HOURLY_RATE_OBJ);

			$results = array();

			if (is_array($convEmployees))
			{
				foreach ($convEmployees as $employee)
				{
					$result = $this->ci->FhcDbModel->saveKalkStundensatz($employee);
					$results[] = $result;
				}
			}

			return success($results);
		}
		else
		{
			$mas = $this->getEmployeesForFhcSync($hourlyrates, self::HOURLY_RATE_OBJ);
			printAndDie($mas);
		}
	}

	/**
	 * Converts given employee data to fhc format.
	 * @param $sapsfemployees
	 * @param $objtype
	 * @return array converted employees
	 */
	public function getEmployeesForFhcSync($sapsfemployees, $objtype)
	{
		$mas = array();

		if (hasData($sapsfemployees))
		{
			$employees = getData($sapsfemployees);

			foreach ($employees as $employee)
			{
				$ma = $this->_convertSapsfObjToFhc($employee, $objtype);
				$mas[] = $ma;
			}
		}

		return $mas;
	}

	//------------------------------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Selects correct email string to be inserted in fhc.
	 * @param $mails contains all mails present in sapsf
	 * @param $params contain emailtyp information
	 * @return string the mail kontakt to insert in fhc
	 */
	protected function _selectEmailForFhc($mails, $params)
	{
		return $this->_selectFieldForFhc(
			$mails,
			$params,
			'emailtyp',
			$this->_sapsfvaluedefaults['kontaktmailprivate']['PerEmail']['emailType']
		);
	}

	/**
	 * Selects and builds correct phone string to be inserted in fhc.
	 * @param $phones contains all phones present in sapsf
	 * @param $params contains phone components and phone type
	 * @return string the phone kontakt to insert in fhc
	 */
	protected function _selectPhoneForFhc($phones, $params)
	{
		$phone = '';

		if (isset($phones) && isset($params['telefontyp']))
		{
			$phones = is_string($phones) ? array($phones) : $phones;
			$params['telefontyp'] = is_string($params['telefontyp']) ? array($params['telefontyp']) : $params['telefontyp'];
			$params['landesvorwahl'] = is_string($params['landesvorwahl']) ? array($params['landesvorwahl']) : $params['landesvorwahl'];
			$params['ortsvorwahl'] = is_string($params['ortsvorwahl']) ? array($params['ortsvorwahl']) : $params['ortsvorwahl'];
			if (isset($params['telefonklappe']))
				$params['telefonklappe'] = is_string($params['telefonklappe']) ? array($params['telefonklappe']) : $params['telefonklappe'];

			if (is_array($phones))
			{
				for ($i = 0; $i < count($phones); $i++)
				{
					if (isset($params['telefontyp'][$i]) && $params['telefontyp'][$i] == $this->_sapsfvaluedefaults['kontakttelprivate']['PerPhone']['phoneType'] && !isEmptyString($phones[$i]))
					{
						$phone = $params['landesvorwahl'][$i] . ' ' . $params['ortsvorwahl'][$i] . ' ' . $phones[$i] . (isset($params['telefonklappe'][$i]) ? '-' . $params['telefonklappe'][$i] : '');
						break;
					}
				}
			}
			else
				$phone = $params['landesvorwahl'] . ' ' . $params['ortsvorwahl'] . ' ' . $phones . (isset($params['telefonklappe']) ? '-' . $params['telefonklappe'] : '');
		}
		return $phone;
	}

	/**
	 * Selects correct Kennzeichen (svnr, ersatzkennzeichen) to be inserted in fhc.
	 * @param $kzval contains Kennzeichen
	 * @param $params contain kztyp information
	 * @return string the kz to insert in fhc
	 */
	protected function _selectKzForFhc($kzval, $params)
	{
		$kz = null;

		if (isset($kzval) && isset($params['kztyp']))
		{
			if (is_array($kzval))
			{
				for ($i = 0; $i < count($kzval); $i++)
				{
					if (isset($params['kztyp'][$i]) && $params['kztyp'][$i] == $this->_confvaluedefaults['User']['kztyp'][$params['fhcfield']])
					{
						$kz = str_replace(' ', '', $kzval[$i]);
						break;
					}
				}
			}
			elseif (isset($params['kztyp']) && $params['kztyp'] == $this->_confvaluedefaults['User']['kztyp'][$params['fhcfield']])
			{
				$kz = str_replace(' ', '', $kzval);
			}
		}

		return $kz;
	}

	/**
	 * Selects correct kalkulatorischer Stundensatz to be inserted in fhc.
	 * @param $stundensaetze
	 * @param $params contains Stundensatz type
	 * @return string the kalkulatorischer Stundensatz to insert in fhc
	 */
	protected function _selectStundensatzForFhc($stundensaetze, $params)
	{
		return $this->_selectFieldForFhc(
			$stundensaetze,
			$params,
			'sap_stundensatz_typ',
			$this->_sapsfvaluedefaults['sap_lekt_stundensatz']['HourlyRates']['hourlyRatesType']
		);
	}

	/**
	 * Selects correct kalkulatorischer Stundensatz to be inserted in fhc.
	 * @param $stundensaetze
	 * @param $params contains Stundensatz type
	 * @return string the kalkulatorischer Stundensatz to insert in fhc
	 */
	protected function _selectKalkStundensatzForFhc($stundensaetze, $params)
	{
		return $this->_selectFieldForFhc(
			$stundensaetze,
			$params,
			'sap_stundensatz_typ',
			$this->_sapsfvaluedefaults['sap_kalkulatorischer_stundensatz']['HourlyRates']['hourlyRatesType']
		);
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Selects a value out of a data array based on extraparameters
	 * @param $data
	 * @param $params extraparameters
	 * @param $fieldname of the extraparameters
	 * @param $sffieldtype type to select
	 * @return object|null
	 */
	private function _selectFieldForFhc($data, $params, $fieldname, $sffieldtype)
	{
		$returnvalue = null;
		$fieldresults = array();
		if (isset($data) && isset($params[$fieldname]))
		{
			$data = is_string($data) ? array($data) : $data;
			$params[$fieldname] = is_string($params[$fieldname]) ?
				array($params[$fieldname]) : $params[$fieldname];

			if (is_array($data))
			{
				for ($i = 0; $i < count($data); $i++)
				{
					if (isset($params[$fieldname][$i]) &&
						$params[$fieldname][$i] == $sffieldtype)
					{
						// multiple field values with same type -> handle later
						$fieldresults[$i] = $data[$i];
					}
				}
			}
			else
				$returnvalue = $data;

			if (count($fieldresults) > 0)
			{
				if (isset($params['startdate'])) // if multiple values, try to get chronologically first, otherwise take just first
				{
					$min = (int)filter_var(array_shift($params['startdate']), FILTER_SANITIZE_NUMBER_INT);
					$minObj = array_shift($fieldresults);
					foreach ($fieldresults as $idx => $fieldresult)
					{
						$millisec = (int)filter_var($params['startdate'][$idx], FILTER_SANITIZE_NUMBER_INT);
						if ($millisec < $min)
						{
							$minObj = $fieldresults[$idx];
							$min = $millisec;
						}
					}
					$returnvalue = $minObj;
				}
				else
					$returnvalue = array_shift($fieldresults);
			}
		}

		return $returnvalue;
	}

	/**
	 * Saves Mitarbeiter in fhc database.
	 * @param $maobj
	 * @return object
	 */
	private function _saveMitarbeiter($maobj)
	{
		$uid = isset($maobj['benutzer']['uid']) ? $maobj['benutzer']['uid'] : '';
		$person_id = null;

		$this->ci->BenutzerModel->addSelect('uid, person_id');
		$benutzerexists = $this->ci->BenutzerModel->loadWhere(array('uid' =>$uid));

		if (hasData($benutzerexists))
		{
			// set person_id so pk is present and unique values can be checked
			$person_id = getData($benutzerexists)[0]->person_id;
			if (isset($maobj['person']))
				$maobj['person']['person_id'] = $person_id;
		}

		$errors = $this->_fhcObjHasError($maobj, self::OBJTYPE, $uid);
		if ($errors->error)
			return error(implode(", ", $errors->errorMessages));

		$person = $maobj['person'];
		$mitarbeiter = $maobj['mitarbeiter'];
		$benutzer = $maobj['benutzer'];
		$uid = $benutzer['uid'];

		$this->ci->db->trans_begin();

		$this->ci->BenutzerModel->addSelect('uid, person_id, aktiv');
		$benutzerexists = $this->ci->BenutzerModel->loadWhere(array('uid' =>$uid));

		if (hasData($benutzerexists))
		{
			$prevaktiv = getData($benutzerexists)[0]->aktiv;
			$updateaktiv = $prevaktiv !== $benutzer['aktiv'];

			// update benutzer
			unset($benutzer['uid']); // avoiding update error
			$this->_stamp('update', $benutzer);
			if ($updateaktiv)
			{
				$benutzer['updateaktivvon'] = self::IMPORTUSER;
				$benutzer['updateaktivam'] = date('Y-m-d');
			}

			$this->ci->BenutzerModel->update(array('uid' => $uid), $benutzer);

			// if benutzer exists, person must exist -> update person
			$this->_stamp('update', $person);
			$this->ci->PersonModel->update($person_id, $person);

			$this->_updatePersonContacts($person_id, $maobj);

			// Mitarbeiter may not exist even if there is a Benutzer - update only if already exists, otherwise insert
			$mitarbeiterexists = $this->ci->MitarbeiterModel->load(array('mitarbeiter_uid' => $uid));
			if (hasData($mitarbeiterexists))
			{
				//$this->_stamp('update', $mitarbeiter); no stamp so it is not marked as new for ToSAPSFSync
				$mitarbeiterres = $this->ci->MitarbeiterModel->update(array('mitarbeiter_uid' => $uid), $mitarbeiter);
			}
			else
			{
				$this->_stamp('insert', $mitarbeiter);
				$mitarbeiterres = $this->ci->MitarbeiterModel->insert($mitarbeiter);
			}
		}
		else
		{
			// no benutzer found - checking if person with same svnr already exists
			if (isset($person['svnr']) && !isEmptyString($person['svnr']))
			{
				$this->ci->PersonModel->addSelect('person_id');
				$hasSvnr = $this->ci->PersonModel->loadWhere(array('svnr' => $person['svnr']));

				if (isSuccess($hasSvnr) && hasData($hasSvnr))
				{
					$person_id = getData($hasSvnr)[0]->person_id;
					// update person if found svnr
					$this->_stamp('update', $person);
					$this->ci->PersonModel->update($person_id, $person);
				}
			}

			if (!isset($person_id))
			{
				// new person
				$this->_stamp('insert', $person);
				$personres = $this->ci->PersonModel->insert($person);
				if (hasData($personres))
				{
					$person_id = getData($personres);
				}
			}

			if (isset($person_id))
			{
				$this->_updatePersonContacts($person_id, $maobj);

				// generate benutzer
				$benutzer['person_id'] = $person_id;
				$benutzer['aktivierungscode'] = generateActivationKey();

				$this->_stamp('insert', $benutzer);
				$benutzerres = $this->ci->BenutzerModel->insert($benutzer);

				// insert mitarbeiter
				if (isSuccess($benutzerres))
				{
					$this->_stamp('insert', $mitarbeiter);
					$mitarbeiterres = $this->ci->MitarbeiterModel->insert($mitarbeiter);
				}
			}
		}

		// generate and save alias
		$this->ci->FhcDbModel->manageAliasForUid($uid);

		// generate and save kurzbz
		$this->ci->FhcDbModel->manageKurzbzForUid($uid);

		// Transaction complete!
		$this->ci->db->trans_complete();

		// Check if everything went ok during the transaction
		if ($this->ci->db->trans_status() === false)
		{
			$this->output .= "rolling back...";
			$this->ci->db->trans_rollback();
			return error("Database error occured while syncing " . $uid);
		}
		else
		{
			$this->ci->db->trans_commit();
			return success($uid);
		}
	}

	/**
	 * Updates contacts of a person in fhc database, including mail, telefon, contact
	 * @param $person_id
	 * @param $maobj the employee to save in db
	 */
	private function _updatePersonContacts($person_id, $maobj)
	{
		$kontaktmail = $maobj['kontaktmail'];
		$kontakttelefon = $maobj['kontakttelefon'];
		$kontaktnotfall = $maobj['kontaktnotfall'];

		// update email - assuming there is only one!
		$this->ci->KontaktModel->addSelect('kontakt_id');
		$this->ci->KontaktModel->addOrder('insertamum', 'DESC');
		$this->ci->KontaktModel->addOrder('kontakt_id', 'DESC');
		$kontaktmail['person_id'] = $person_id;

		$kontaktmailToUpdate = $this->ci->KontaktModel->loadWhere(array(
				'kontakttyp' => $kontaktmail['kontakttyp'],
				'person_id' => $person_id,
				'zustellung' => true)
		);

		if (!isEmptyString($kontaktmail['kontakt']))
		{
			if (hasData($kontaktmailToUpdate))
			{
				$kontakt_id = getData($kontaktmailToUpdate)[0]->kontakt_id;
				$this->_stamp('update', $kontaktmail);
				$kontaktmailres = $this->ci->KontaktModel->update($kontakt_id, $kontaktmail);
			}
			else
			{
				$this->_stamp('insert', $kontaktmail);
				$kontaktmailres = $this->ci->KontaktModel->insert($kontaktmail);
			}
		}

		// update phone - assuming there is only one!
		$this->ci->KontaktModel->addSelect('kontakt_id');
		$this->ci->KontaktModel->addOrder('insertamum', 'DESC');
		$this->ci->KontaktModel->addOrder('kontakt_id', 'DESC');
		$kontakttelefon['person_id'] = $person_id;

		$kontakttelToUpdate = $this->ci->KontaktModel->loadWhere(array(
				'kontakttyp' => $kontakttelefon['kontakttyp'],
				'person_id' => $person_id,
				'zustellung' => true)
		);

		if (!isEmptyString($kontakttelefon['kontakt']))
		{
			if (hasData($kontakttelToUpdate))
			{
				$kontakt_id = getData($kontakttelToUpdate)[0]->kontakt_id;
				//$this->_stamp('update', $kontaktmail); no stamp because sync to SAPSF can assume it changed -> sync loop
				$kontakttelres = $this->ci->KontaktModel->update($kontakt_id, $kontakttelefon);
			}
			else
			{
				$this->_stamp('insert', $kontakttelefon);
				$kontakttelres = $this->ci->KontaktModel->insert($kontakttelefon);
			}
		}

		// update kontaktnotfall
		$kontaktnotfall['person_id'] = $person_id;

		$this->ci->KontaktModel->addSelect('kontakt_id');
		$this->ci->KontaktModel->addOrder('insertamum', 'DESC');
		$this->ci->KontaktModel->addOrder('kontakt_id', 'DESC');
		$kontaktnotfallToUpdate = $this->ci->KontaktModel->loadWhere(
			array(
				'kontakttyp' => $kontaktnotfall['kontakttyp'],
				'person_id' => $person_id,
				'zustellung' => true
			)
		);

		if (!isEmptyString($kontaktnotfall['kontakt']))
		{
			if (hasData($kontaktnotfallToUpdate))
			{
				$kontakt_id = getData($kontaktnotfallToUpdate)[0]->kontakt_id;
				$this->_stamp('update', $kontaktnotfall);
				$kontaktnotfallres = $this->ci->KontaktModel->update($kontakt_id, $kontaktnotfall);
			}
			else
			{
				$this->_stamp('insert', $kontaktnotfall);
				$kontaktnotfallres = $this->ci->KontaktModel->insert($kontaktnotfall);
			}
		}
	}
}
