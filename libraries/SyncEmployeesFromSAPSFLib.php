<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once('include/functions.inc.php');// needed for activation key generation

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Success Factors
 */
class SyncEmployeesFromSAPSFLib extends SyncFromSAPSFLib
{
	const OBJTYPE = 'User';
	const SAPSF_EMPLOYEES_FROM_SAPSF = 'SyncEmployeesFromSAPSF';

	private $_convertfunctions = array(
		'person' => array(
			'gebdatum' => array(
				'function' => '_convertDateToFhc',
				'extraParams' => null
			)
		),
		'kontaktmail' => array(
			'kontakt' => array(
				'function' => '_selectEmailForFhc',
				'extraParams' => array(array('table' => 'mailtyp', 'name' => 'emailtyp'))
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
				$ma = $this->_convertEmployeeToFhc($employee);
				$result = $this->_saveMitarbeiter($ma);
				$results[] = $result;
			}
		}

		return success($results);
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
		$uid = $benutzer['uid'];

		$this->ci->db->trans_begin();

		$this->ci->BenutzerModel->addSelect('uid, person_id');
		$benutzerexists = $this->ci->BenutzerModel->loadWhere(array('uid' =>$uid));

		if (hasData($benutzerexists))
		{
			// if benutzer exists, person must exist -> update person
			$this->_stamp('update', $person);
			$this->ci->PersonModel->update($person_id, $person);

			// update email - assuming there is only one!
			$this->ci->KontaktModel->addSelect('kontakt_id');
			$this->ci->KontaktModel->addOrder('insertamum', 'kontakt_id');
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

			$this->_stamp('update', $kontaktmail);
			$this->ci->PersonModel->update($person_id, $person);

			// Mitarbeiter may not exist even if there is a Benutzer - update only if already exists, otherwise insert
			$mitarbeiterexists = $this->ci->MitarbeiterModel->load(array('mitarbeiter_uid' => $uid));
			if (hasData($mitarbeiterexists))
			{
				$this->_stamp('update', $mitarbeiter);
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
			$this->_stamp('insert', $person);
			$personres = $this->ci->PersonModel->insert($person);
			if (isSuccess($personres))
			{
				$person_id = getData($personres);

				$kontaktmail['person_id'] = $person_id;
				$this->_stamp('insert', $kontaktmail);
				$kontaktmailres = $this->ci->KontaktModel->insert($kontaktmail);

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

	/**
	 * Converts employee from SAPSF to mitarbeiter to save in the fhc database.
	 * @param $employee
	 * @return array converted employee
	 */
	private function _convertEmployeeToFhc($employee)
	{
		$fhctables = array_keys($this->_conffieldmappings[self::OBJTYPE]);
		$fhcemployee = array();

		foreach ($fhctables as $fhctable)
		{
			if (isset($this->_confvaluedefaults[self::OBJTYPE][$fhctable]))
			{
				foreach ($this->_confvaluedefaults[self::OBJTYPE][$fhctable] as $fhcfield => $fhcvalue)
				{
					$fhcemployee[$fhctable][$fhcfield] = $fhcvalue;
				}
			}

			/*if (isset($this->_conffieldmappings[self::OBJTYPE][$fhctable]))
			{*/
			foreach ($this->_conffieldmappings[self::OBJTYPE][$fhctable] as $sffield => $fhcfield)
			{
				if (isset($employee->{$sffield}) /*&& !isEmptyString($employee->{$sffield})*/)//TODO what if update to empty?
				{
					$fhcemployee[$fhctable][$fhcfield] = $employee->{$sffield};
				}
				elseif (strpos($sffield, '/') )
				{
					// if navigation property, navigate to value needed
					$navfield = substr($sffield, 0, strrpos($sffield, '/'));
					$field = substr($sffield, strrpos($sffield, '/') + 1, strlen($sffield));
					$props = explode('/', $navfield);

					if (isset($employee->{$props[0]}))
					{
						$value = $employee->{$props[0]};
						for ($i = 1; $i < count($props); $i++)
						{
							if (isset($value->{$props[$i]}))
							{
								$value = $value->{$props[$i]};
							}
							// navigate further if value has results array instead of a finite value
							elseif (isset($value->results[0]->{$props[$i]}))
							{
								$noValues = count($value->results);
								if ($noValues == 1)
									$value = $value->results[0]->{$props[$i]};
							}
						}

						if (isset($value->{$field}))
							$fhcemployee[$fhctable][$fhcfield] = $value->{$field};
						elseif (isset($value->results[0]->{$field})) // if value has results array
						{
							if (count($value->results) == 1) // take first result
								$fhcemployee[$fhctable][$fhcfield] = $value->results[0]->{$field};
							elseif (count($value->results) > 1) // or take all results
							{
								foreach ($value->results as $result)
								{
									$fhcemployee[$fhctable][$fhcfield][] = $result->{$field};
								}
							}
						}
					}
				}

				if (isset($fhcemployee[$fhctable][$fhcfield]))
				{
					$fieldvalue = $fhcemployee[$fhctable][$fhcfield];

					if (isset($this->_confvaluemappings[self::OBJTYPE][$fhctable][$fhcfield][$fieldvalue]))
						$fhcemployee[$fhctable][$fhcfield] = $this->_confvaluemappings[self::OBJTYPE][$fhctable][$fhcfield][$fieldvalue];

					if (isset($this->_convertfunctions[$fhctable][$fhcfield]))
					{
						$params = array();
						if (is_array($this->_convertfunctions[$fhctable][$fhcfield]['extraParams']))
						{
							foreach ($this->_convertfunctions[$fhctable][$fhcfield]['extraParams'] as $param)
							{
								if (isset($fhcemployee[$param['table']][$param['name']]))
									$params[$param['name']] = $fhcemployee[$param['table']][$param['name']];
							}
						}

						$fhcemployee[$fhctable][$fhcfield] = $this->{$this->_convertfunctions[$fhctable][$fhcfield]['function']}(
							$fieldvalue,
							$params
						);
					}
				}
			}
		}

		return $fhcemployee;
	}

	/**
	 * Selects correct email string to be inserted in fhc.
	 * @param $mailarr contains all mails present in sapsf
	 * @param $params
	 * @return string the mail kontakt to insert in fhc
	 */
	private function _selectEmailForFhc($mailarr, $params)
	{
		$mail = '';
		if (is_array($mailarr))
		{
			for ($i = 0; $i < count($mailarr); $i++)
			{
				if (isset($params['emailtyp'][$i]) && $params['emailtyp'][$i] == $this->_sapsfvaluedefaults['person']['PerEmail']['emailType'])
				{
					$mail = $mailarr[$i];
					break;
				}
			}
		}
		else
			return $mailarr;

		return $mail;
	}
}
