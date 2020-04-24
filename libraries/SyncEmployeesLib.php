<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once('include/functions.inc.php');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Success Factors
 */
class SyncEmployeesLib extends SyncToFhcLib
{
	const OBJTYPE = 'employee';
	const SAPSF_EMPLOYEES_CREATE = 'SAPSFEmployeesCreate';

	private $_convertfunctions = array(
		'person' => array(
			'gebdatum' => '_convertDateToFhc'
		)
	);

	/**
	 * SyncEmployeesLib constructor.
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

	/**
	 * Converts employee from SAPSF to mitarbeiter to save in the fhc database.
	 * @param $employee
	 * @return array converted employee
	 */
	private function _convertEmployeeToFhc($employee)
	{
		$fhctables = array('person', 'mitarbeiter', 'benutzer', 'kontakttel');
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

			if (isset($this->_conffieldmappings[self::OBJTYPE][$fhctable]))
			{
				foreach ($this->_conffieldmappings[self::OBJTYPE][$fhctable] as $sffield => $fhcfield)
				{
					if (isset($employee->{$sffield}) && !isEmptyString($employee->{$sffield}))
					{
						$value = $employee->{$sffield};
						if (isset($this->_convertfunctions[$fhctable][$fhcfield]))
							$value = $this->{$this->_convertfunctions[$fhctable][$fhcfield]}($value);
						$fhcemployee[$fhctable][$fhcfield] = $value;
					}
				}
			}
		}

		return $fhcemployee;
	}

	/**
	 * Saves Mitarbeiter in fhc database.
	 * @param $maobj
	 * @return mixed
	 */
	private function _saveMitarbeiter($maobj)
	{
		$uid = isset($maobj['benutzer']['uid']) ? $maobj['benutzer']['uid'] : '';
		$errors = $this->_fhcObjHasError($maobj, self::OBJTYPE, $uid);

		if ($errors->error)
		{
			return error(implode(", ", $errors->errorMessages));
		}
		else
		{
			$person = $maobj['person'];
			$mitarbeiter = $maobj['mitarbeiter'];
			$benutzer = $maobj['benutzer'];
			//$kontaktmail = $employee['kontaktmail'];
			$kontakttel = $maobj['kontakttel'];
			$uid = $benutzer['uid'];

			$this->ci->db->trans_begin();

			$this->ci->BenutzerModel->addSelect('uid, person_id');
			$benutzerexists = $this->ci->BenutzerModel->loadWhere(array('uid' =>$uid));

			if (hasData($benutzerexists))
			{
				//if benutzer exists, person must exist -> update person
				$person_id = getData($benutzerexists)[0]->person_id;
				$this->_stamp('update', $person);
				$this->ci->PersonModel->update($person_id, $person);
				// Mitarbeiter may not exist even if there is a Benutzer - update only if already exists, otherwise insert
				$mitarbeiterexists = $this->ci->MitarbeiterModel->load($uid);
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
					$person_id = getData($personres);;

					$kontakttel['person_id'] = $person_id;
					$this->_stamp('insert', $kontakttel);
					$kontakttelres = $this->ci->KontaktModel->insert($kontakttel);

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
}
