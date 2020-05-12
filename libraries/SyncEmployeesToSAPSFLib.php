<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once 'SyncToSAPSFLib.php';

class SyncEmployeesToSAPSFLib extends SyncToSAPSFLib
{
	const SAPSF_EMPLOYEES_TO_SAPSF = 'SyncEmployeesToSAPSF';
	const OBJTYPE = 'employee';
	const EMAIL_POSTFIX = '@technikum-wien.at';

	private $_convertfunctions = array(
		'benutzer' => array(
			'uid' => '_convertToAlias'
		)
	);

	/**
	 * SyncEmployeesToSAPSFLib constructor.
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
		$this->ci->load->model('extensions/FHC-Core-SAPSF/fhcomplete/FhcDbModel', 'FhcDbModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Converts employee from SAPSF to mitarbeiter to save in the fhc database.
	 * @param $employee
	 * @return array converted employee
	 */
	public function convertEmployeeToSapsf($employee)
	{
		$fhctables = array('benutzer', 'kontakttel');
		$sapsfemployee = array();

		foreach ($fhctables as $fhctable)
		{
			if (isset($this->_conffieldmappings[$fhctable][self::OBJTYPE]))
			{
				foreach ($this->_conffieldmappings[$fhctable][self::OBJTYPE] as $fhcfield => $sffield)
				{
					if (isset($employee->{$fhcfield}))
					{
						$value = $employee->{$fhcfield};
						if (isset($this->_convertfunctions[$fhctable][$fhcfield]))
							$value = $this->{$this->_convertfunctions[$fhctable][$fhcfield]}($value);

						if (!isEmptyString($value))// data should not get lost in SAPSF if empty field
							$sapsfemployee[$sffield] = $value;
					}
				}
			}
		}

		return $sapsfemployee;
	}

	/**
	 * Gets correct alias for a uid
	 * @param string $uid
	 * @return string
	 */
	private function _convertToAlias($uid)
	{
		$alias = $this->ci->FhcDbModel->manageAliasForUid($uid);

		if (!isEmptyString($alias))
			$alias = $alias.self::EMAIL_POSTFIX;

		return $alias;
	}
}
