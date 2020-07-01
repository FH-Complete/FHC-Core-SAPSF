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

	protected $_convertfunctions = array(
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
				'extraParams' => array('table' => 'kztyp', 'name' => 'kztyp', 'fhcfield' => 'svnr')
			),
			'ersatzkennzeichen' => array(
				'function' => '_selectKzForFhc',
				'extraParams' => array('table' => 'kztyp', 'name' => 'kztyp', 'fhcfield' => 'ersatzkennzeichen')
			)
		),
		'kontaktmail' => array(
			'kontakt' => array(
				'function' => '_selectEmailForFhc',
				'extraParams' => array('table' => 'mailtyp', 'name' => 'emailtyp')
			)
		)
	);

	/**
	 * SyncEmployeesFromSAPSFLib constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		//load models
		$this->ci->load->model('person/person_model', 'PersonModel');
		$this->ci->load->model('ressource/mitarbeiter_model', 'MitarbeiterModel');
		$this->ci->load->model('person/benutzer_model', 'BenutzerModel');
		$this->ci->load->model('person/adresse_model', 'AdresseModel');
		$this->ci->load->model('person/kontakt_model', 'KontaktModel');
		$this->ci->load->model('codex/Nation_model', 'NationModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Starts employee sync. Converts given employee data to fhc format and saves the employee.
	 * @param $employees
	 * @return object
	 */
	public function syncEmployeesWithFhc($employees)
	{
		$results = array();

		if (hasData($employees))
		{
			$employees = getData($employees);

			foreach ($employees as $employee)
			{
				$ma = $this->_convertSapsfObjToFhc($employee, self::OBJTYPE);
				$result = $this->_saveMitarbeiter($ma);
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
		$results = array();

		if (hasData($hourlyrates))
		{
			$hourlyrates = getData($hourlyrates);

			foreach ($hourlyrates as $rate)
			{
				$hr = $this->_convertSapsfObjToFhc($rate, self::HOURLY_RATE_OBJ);
				$result = $this->ci->FhcDbModel->saveKalkStundensatz($hr);
				$results[] = $result;
			}
		}

		return success($results);
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
		$mails = is_string($mails) ? array($mails) : $mails;
		$params['emailtyp'] = is_string($params['emailtyp']) ? array($params['emailtyp']) : $params['emailtyp'];
		$mail = '';

		if (is_array($mails))
		{
			for ($i = 0; $i < count($mails); $i++)
			{
				if (isset($params['emailtyp'][$i]) && $params['emailtyp'][$i] == $this->_sapsfvaluedefaults['kontaktmailprivate']['PerEmail']['emailType'])
				{
					$mail = $mails[$i];
					break;
				}
			}
		}
		else
			return $mails;

		return $mail;
	}

	/**
	 * Selects correct Kennzeichen (svnr, ersatzkennzeichen) to be inserted in fhc.
	 * @param $kzval contain Kennzeichen
	 * @param $params contain kztyp information
	 * @return string the kz to insert in fhc
	 */
	protected function _selectKzForFhc($kzval, $params)
	{
		$kz = null;
		if (is_array($kzval))
		{
			for ($i = 0; $i < count($kzval); $i++)
			{
				if (isset($params['kztyp'][$i]) && $params['kztyp'][$i] == $this->_confvaluedefaults['User']['kztyp'][$params['fhcfield']])
				{
					$kz = $kzval[$i];
					break;
				}
			}
		}
		elseif (isset($params['kztyp']) && $params['kztyp'] == $this->_confvaluedefaults['User']['kztyp'][$params['fhcfield']])
		{
			$kz = $kzval;
		}

		return $kz;
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

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
		$kontaktmail = $maobj['kontaktmail'];
		$kontaktnotfall = $maobj['kontaktnotfall'];
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

			if (hasData($kontaktnotfallToUpdate))
			{
				$kontakt_id = getData($kontaktnotfallToUpdate)[0]->kontakt_id;
				$this->_stamp('update', $kontaktnotfall);
				$kontaktnotfallres = $this->ci->KontaktModel->update($kontakt_id, $kontaktnotfall);
			}
			elseif (!isEmptyString($kontaktnotfall['kontakt']))
			{
				$this->_stamp('insert', $kontaktnotfall);
				$kontaktnotfallres = $this->ci->KontaktModel->insert($kontaktnotfall);
			}

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
		else // new person
		{
			$this->_stamp('insert', $person);
			$personres = $this->ci->PersonModel->insert($person);
			if (isSuccess($personres))
			{
				$person_id = getData($personres);

				$kontaktmail['person_id'] = $person_id;
				$this->_stamp('insert', $kontaktmail);
				$kontaktmailres = $this->ci->KontaktModel->insert($kontaktmail);

				if (!isEmptyString($kontaktnotfall['kontakt']))
				{
					$kontaktnotfall['person_id'] = $person_id;
					$this->_stamp('insert', $kontaktnotfall);
					$kontaktnotfallres = $this->ci->KontaktModel->insert($kontaktnotfall);
				}
				$benutzer['person_id'] = $person_id;
				$benutzer['aktivierungscode'] = generateActivationKey();

				$this->_stamp('insert', $benutzer);
				$benutzerres = $this->ci->BenutzerModel->insert($benutzer);

				if (isSuccess($benutzerres))
				{
					$this->_stamp('insert', $mitarbeiter);
					$mitarbeiterres = $this->ci->MitarbeiterModel->insert($mitarbeiter);
				}
			}
		}

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
}
