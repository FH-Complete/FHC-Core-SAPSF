<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once('include/functions.inc.php');// needed for activation key generation
require_once 'SyncFromSAPSFLib.php';

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
		'benutzer' => array(
			'aktiv' => array(
				'function' => '_selectAktivForFhc',
				'extraParams' => array(
					array('table' => 'sapaktiv', 'name' => 'sapaktiv'),
					array('table' => 'sapaktiv', 'name' => 'sapstartdatum')
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
					array('table' => 'telefondaten', 'name' => 'telefontyp', 'fhcfield' => 'kontakttelprivate'),
					array('table' => 'telefondaten', 'name' => 'landesvorwahl'),
					array('table' => 'telefondaten', 'name' => 'ortsvorwahl'),
					array('table' => 'telefondaten', 'name' => 'telefonklappe')
				)
			)
		),
/*		'kontakttelmobile' => array(
			'kontakt' => array(
				'function' => '_selectPhoneForFhc',
				'extraParams' => array(
					array('table' => 'telefondaten', 'name' => 'telefontyp', 'fhcfield' => 'kontakttelmobile'),
					array('table' => 'telefondaten', 'name' => 'landesvorwahl'),
					array('table' => 'telefondaten', 'name' => 'ortsvorwahl'),
					array('table' => 'telefondaten', 'name' => 'telefonklappe')
				)
			)
		),*/
		'sap_kalkulatorischer_stundensatz' => array(
			'sap_kalkulatorischer_stundensatz' => array(
				'function' => '_selectKalkStundensatzForFhc',
				'extraParams' => array(
					array('table' => 'sap_stundensatz_typ', 'name' => 'sap_stundensatz_typ'),
					array('table' => 'sap_stundensatz_typ', 'name' => 'sap_stundensatz_startdate')
				)
			)
		),
		'adresse' => array(),
		'nebenadresse' => array()
	);

	/**
	 * SyncEmployeesFromSAPSFLib constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_setAdresseConvertFunctions();

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
	 * Gets employees to be synced.
	 * @param $objtype
	 * @param $newJobObj object contains information for sync (uids, fullsync)
	 * @param $lastDoneJobs object jobs executed last
	 * @return object the mitarbeiter to sync on success, error otherwise
	 */
	public function getEmployeesForSync($objtype, $newJobObj, $lastDoneJobs = null)
	{
		$lastModifiedDateTime = null;
		$lastjobtime = null;

		if (hasData($lastDoneJobs))
		{
			$lastJobsData = getData($lastDoneJobs);
			$lastjobtime = $lastJobsData[0]->starttime;
			$lastModifiedDateTime = $this->convertDateToSAPSF($lastjobtime);
		}

		// input uids if only certain users should be synced
		$uids = checkStringArray($newJobObj->uids) ? $newJobObj->uids : array();

		$selects = $this->getSelectsFromFieldMappings($objtype);
		$expands = $this->getExpandsFromFieldMappings($objtype);
		$lastmodifiedprops = isset($lastModifiedDateTime) ? $this->_sapsflastmodifiedfields : null;
		$startdateprops = isset($lastModifiedDateTime) ? $this->_sapsfstartdatefields: null;

		$uidsToSync = array();
		$maToSync = array();

		// full sync
		if (isset($newJobObj->syncAll) && $newJobObj->syncAll)
		{
			$employees = $this->ci->QueryUserModel->getAll($selects, $expands, $lastModifiedDateTime, $lastmodifiedprops, $lastjobtime, $startdateprops);

			if (isError($employees))
			{
				return error(getError($employees));
			}

			if (hasData($employees))
			{
				$empData = getData($employees);

				foreach ($empData as $emp)
				{
					$maToSync[] = success($emp);
					$uidsToSync[] = $emp->userId;
				}
			}
		}

		// include additional, manually passed uids
		$uids = array_diff($uids, $uidsToSync);

		foreach ($uids as $uid)
		{
			$employee = $this->ci->QueryUserModel->getByUserId($uid, $selects, $expands);

			$maToSync[] = $employee;
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

		$errorno = 0;
		foreach ($results as $result)
		{
			if (isError($result))
			{
				$syncedMitarbeiterRes['error_'.$errorno] = error(getError($result));
				$errorno++;
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
		$convEmployees = $this->_convertEmployeesForFhc($employees, self::OBJTYPE);

		if ($this->_syncpreview !== false)
			printAndDie($convEmployees);

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

	/**
	 * Starts hourly rates sync.
	 * Converts given hourly rate data to fhc format and saves the hourly rates object.
	 * @param $hourlyrates
	 * @return object
	 */
	public function syncHourlyRateWithFhc($hourlyrates)
	{
		$convEmployees = $this->_convertEmployeesForFhc($hourlyrates, self::HOURLY_RATE_OBJ);

		if ($this->_syncpreview !== false)
			printAndDie($convEmployees);

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

	//------------------------------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Selects correct email string to be inserted in fhc.
	 * @param $mails array contains all mails present in sapsf
	 * @param $params array contain emailtyp information
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

			$fhcphonetype = $params['misc']['telefondaten']['fhcfield'];

			if (is_array($phones))
			{
				for ($i = 0; $i < count($phones); $i++)
				{
					if (isset($params['telefontyp'][$i]) && $params['telefontyp'][$i] == $this->_sapsfvaluedefaults[$fhcphonetype]['PerPhone']['phoneType'] && !isEmptyString($phones[$i]))
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
	 * @param $kzval array contains Kennzeichen
	 * @param $params array contain kztyp information
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
					if (isset($params['kztyp'][$i]) && $params['kztyp'][$i] == $this->_confvaluedefaults['User']['kztyp'][$params['misc']['kztyp']['fhcfield']])
					{
						$kz = str_replace(' ', '', $kzval[$i]);
						break;
					}
				}
			}
			elseif (isset($params['kztyp']) && $params['kztyp'] == $this->_confvaluedefaults['User']['kztyp'][$params['misc']['kztyp']['fhcfield']])
			{
				$kz = str_replace(' ', '', $kzval);
			}
		}

		return $kz;
	}

	/**
	 * Selects correct Lektorenstundensatz to be inserted in fhc.
	 * @param $stundensaetze array
	 * @param $params array contains Stundensatz type
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
	 * @param $stundensaetze array
	 * @param $params array contains Stundensatz type
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

	/**
	 * Selects correct adresse field to be inserted in fhc.
	 * @param $adressen array the adresse fields
	 * @param $params array contains adresse type
	 * @return string the adresse field to insert in fhc
	 */
	protected function _selectAdresseForFhc($adressen, $params)
	{
		return $this->_selectFieldForFhc(
			$adressen,
			$params,
			'typ',
			isset($params['misc']['adressedaten']['adressetyp']) ? $params['misc']['adressedaten']['adressetyp'] : null
		);
	}

	/**
	 * Selects correct adresse nation to be inserted in fhc.
	 * @param $adressen array the adresse nation fields
	 * @param $params array contains adresse type
	 * @return string the adresse nation field to insert in fhc
	 */
	protected function _selectAdresseNationForFhc($adressen, $params)
	{
		$nation = $this->_selectFieldForFhc(
			$adressen,
			$params,
			'typ',
			isset($params['misc']['adressedaten']['adressetyp']) ? $params['misc']['adressedaten']['adressetyp'] : null
		);

		return $this->_convertNationToFhc($nation);
	}

	/**
	 * Selects correct adresse strasse to be inserted in fhc.
	 * @param $adressen array the adresse strasse fields
	 * @param $params array contains adresse type
	 * @return string the adresse strasse field to insert in fhc
	 */
	protected function _selectStrasseForFhc($adressen, $params)
	{
		$adresse = $this->_selectFieldForFhc(
			$adressen,
			$params,
			'typ',
			isset($params['misc']['adressedaten']['adressetyp']) ? $params['misc']['adressedaten']['adressetyp'] : null
		);

		if (isset($params['hausnr']) && !isEmptyString($params['hausnr']))
		{
			$hausnr = $this->_selectFieldForFhc(
				$params['hausnr'],
				$params,
				'typ',
				isset($params['misc']['adressedaten']['adressetyp']) ? $params['misc']['adressedaten']['adressetyp'] : null
			);

			$adresse .= ' ' . $hausnr;
		}

		return $adresse;
	}

	/**
	 * Sets correct aktiv boolean for fhc.
	 * If FAS-aktiv in SAPSF is false, set to false.
	 * Otherwise use SAPSF-aktiv. If there is a future start date in SAPSF, it still counts as active.
	 * @param $aktiv fas-aktiv in SAPSF
	 * @param $params contains other necessary parameter, like sapaktiv and start date of employment
	 * @return bool the value to be saved in FAS aktiv field
	 */
	protected function _selectAktivForFhc($aktiv, $params)
	{
		$fasaktiv = isset($params['sapaktiv']) && is_bool($params['sapaktiv']) ? $params['sapaktiv'] : true;

		if ($aktiv === false)
			$fasaktiv = false;
		elseif (isset($params['sapstartdatum']))
		{
			$sapstartdate = $this->_convertDateToFhc($params['sapstartdatum']);

			if ($sapstartdate > date('Y-m-d')) // if there is a future start date, it counts as aktiv
				$fasaktiv = true;
		}

		return $fasaktiv;
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Sets the convert functions for Hauptadresse and Nebenadresse.
	 */
	private function _setAdresseConvertFunctions()
	{
		$adresseConvFunctions =
		array(
			'nation' => array(
				'function' => '_selectAdresseNationForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h')
				)
			),
			'plz' => array(
				'function' => '_selectAdresseForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h')
				)
			),
			'ort' => array(
				'function' => '_selectAdresseForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h')
				)
			),
			'strasse' => array(
				'function' => '_selectStrasseForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h'),
					array('table' => 'adressedaten', 'name' => 'hausnr')
				)
			),
			'gemeinde' => array(
				'function' => '_selectAdresseForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h')
				)
			),
			'name' => array(
				'function' => '_selectAdresseForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h')
				)
			)
		);

		// nebenadresse and hauptadresse have almost same structure, so they are set separately
		$this->_convertfunctions['adresse'] = $adresseConvFunctions;

		foreach ($adresseConvFunctions as $field => $function)
		{
			foreach ($adresseConvFunctions[$field]['extraParams'] as $idx => $param)
			{
				if ($param['name'] == 'typ')
					$adresseConvFunctions[$field]['extraParams'][$idx]['adressetyp'] = 'n';

			}
		}

		$this->_convertfunctions['nebenadresse'] = $adresseConvFunctions;
	}

	/**
	 * Converts given employee data to fhc format.
	 * @param $sapsfemployees
	 * @param $objtype
	 * @return array converted employees
	 */
	private function _convertEmployeesForFhc($sapsfemployees, $objtype)
	{
		$mas = array();

		foreach ($sapsfemployees as $employee)
		{
			if (hasData($employee))
			{
				$ma = $this->_convertSapsfObjToFhc(getData($employee), $objtype);
				$mas[] = $ma;
			}
		}

		return $mas;
	}

	/**
	 * Selects a value out of a data array based on extraparameters.
	 * @param $data
	 * @param $params extraparameters
	 * @param $fieldname of the extraparameters
	 * @param $sffieldtype type to select
	 * @return object|null
	 */
	private function _selectFieldForFhc($data, $params, $fieldname, $sffieldtype)
	{
		$returnvalue = null;

		if (isset($data) && isset($params[$fieldname]))
		{
			$data = is_string($data) ? array($data) : $data;
			$params[$fieldname] = is_string($params[$fieldname]) ?
				array($params[$fieldname]) : $params[$fieldname];

			if (is_array($data))
			{
				// get correct value according to fielddtype
				for ($i = 0; $i < count($data); $i++)
				{
					if (isset($params[$fieldname][$i]) &&
						$params[$fieldname][$i] == $sffieldtype)
					{
						$returnvalue = $data[$i];
						break;
					}
				}
			}
			else
				$returnvalue = $data;
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
		$kontakttelefone = array($maobj['kontakttelefon']/*, $maobj['kontakttelmobile']*/);
		$kontaktnotfall = $maobj['kontaktnotfall'];
		$adressen = array($maobj['adresse'], $maobj['nebenadresse']);

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

		foreach ($kontakttelefone as $kontakttelefon)
		{
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

		// update adressen
		foreach ($adressen as $adresse)
		{
			// update adress - assuming there is only one!
			$this->ci->AdresseModel->addSelect('adresse_id');
			$this->ci->AdresseModel->addOrder('insertamum', 'DESC');
			$this->ci->AdresseModel->addOrder('adresse_id', 'DESC');
			$adresse['person_id'] = $person_id;

			$adresseToUpdate = $this->ci->AdresseModel->loadWhere(array(
					'typ' => $adresse['typ'],
					'person_id' => $person_id,
					'zustelladresse' => $adresse['zustelladresse'])
			);

			if (!isEmptyString($adresse['strasse']))
			{
				if (hasData($adresseToUpdate))
				{
					$adresse_id = getData($adresseToUpdate)[0]->adresse_id;
					$this->_stamp('update', $adresse);
					$kontakaddrres = $this->ci->AdresseModel->update($adresse_id, $adresse);
				}
				else
				{
					$this->_stamp('insert', $adresse);
					$kontaktaddrres = $this->ci->AdresseModel->insert($adresse);
				}
			}
		}
	}
}
