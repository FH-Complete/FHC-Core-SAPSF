<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once 'SyncToSAPSFLib.php';

class SyncEmployeesToSAPSFLib extends SyncToSAPSFLib
{
	const SAPSF_EMPLOYEES_TO_SAPSF = 'SyncEmployeesToSAPSF';

	const PREDICATE_INDEX = 'sapsf_predicates';
	const DATA_INDEX = 'data';

	const OBJTYPE = 'User';
	const MAILTYPE = 'PerEmail';
	const PERSONALINFOTYPE = 'PerPersonal';

	const PERSON_NAV = 'personKeyNav';
	const PERSONAL_INFO_NAV = 'empInfo/personNav/personalInfoNav';

	const EMAIL_POSTFIX = '@technikum-wien.at';

	private $_convertfunctions = array(
		'kontaktmail' => array(
			'uid' => '_convertToAlias'
		)
	);

	private $_sapsfdatenames = array(
		'startDate'
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
		$this->ci->load->model('extensions/FHC-Core-SAPSF/SAPSFQueries/QueryUserModel', 'QueryUserModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Gets employees from fhcomplete
	 * @param array $uids
	 * @return mixed
	 */
	public function getEmployeesForSync($uids)
	{
		// if no uids passed, all employee aliases are synced
		$mitarbeiterToSync = array();
		$mitarbeiter = $this->ci->FhcDbModel->getMitarbeiter($uids);

		if (hasData($mitarbeiter))
		{
			$mitarbeiter = getData($mitarbeiter);

			// only update employees which are in sapsf too
			$sapsfUidName = $this->_predicates[self::OBJTYPE][0];
			$sapsfPersonIdName = $this->_predicates[self::MAILTYPE][0];
			$sapsfstartDateName = $this->_predicates[self::PERSONALINFOTYPE][1];
			$sapsfemployees = $this->ci->QueryUserModel->getAll(
				array($sapsfUidName, self::PERSON_NAV.'/' . $sapsfPersonIdName, self::PERSONAL_INFO_NAV . '/' . $sapsfstartDateName),
				array(self::PERSON_NAV, self::PERSONAL_INFO_NAV)
			);

			if (isError($sapsfemployees))
				return $sapsfemployees;
			elseif (hasData($sapsfemployees))
			{
				$sapsfemployees = getData($sapsfemployees);
				foreach ($mitarbeiter as $ma)
				{
					foreach ($sapsfemployees as $sapsfemployee)
					{
						if ($sapsfemployee->{$sapsfUidName} == $ma->uid)
						{
							$matosync = $this->_convertEmployeeToSapsf($ma);

							// check if any of the predicate keys can be found in queried sapsf employees.
							// If yes, add them to employee to sync.
							foreach ($sapsfemployee as $prop => $value)
							{
								if (is_object($value))
								{
									foreach ($value as $valprop => $valval)
									{
										foreach ($matosync as $entity => $mats)
										{
											foreach ($mats as $fhctbl => $data)
											{
												foreach ($matosync[$entity][$fhctbl][self::PREDICATE_INDEX] as $valprop => $valval)
												{
													$foundValue = $this->_recursive_object_search($valprop, $value);
													if ($foundValue)
													{
														if (in_array($valprop, $this->_sapsfdatenames)) // if date present, convert it to required format
															$foundValue = $this->_convertSAPSFTimestampToDateTime($foundValue);
														$matosync[$entity][$fhctbl][self::PREDICATE_INDEX][$valprop] = $foundValue;
													}
												}
											}
										}
									}
								}
							}

							if (!isEmptyArray($matosync))
								$mitarbeiterToSync[$ma->uid] = $matosync;

							break;
						}
					}
				}
			}
		}

		return success($mitarbeiterToSync);
	}

	/**
	 * Converts employee to save in SAPSF.
	 * @param $employee
	 * @return array converted employee
	 */
	private function _convertEmployeeToSapsf($employee)
	{
		$fhctables = array('benutzer', 'person', 'mitarbeiter', 'kontakttel', 'kontaktmail', 'kontaktmailprivate');
		$sapsfemployee = array();

		foreach ($fhctables as $fhctable)
		{
			if (isset($this->_confvaluedefaults[$fhctable]))
			{
				foreach ($this->_confvaluedefaults[$fhctable] as $sapsfentity => $confvaluedefaults)
				{
					foreach ($confvaluedefaults as $sffield => $sfvalue)
					{
						$sapsfemployee[$sapsfentity][$fhctable][self::DATA_INDEX][$sffield] = $sfvalue;
					}
				}
			}

			if (isset($this->_conffieldmappings[$fhctable]))
			{
				foreach ($this->_conffieldmappings[$fhctable] as $sapsfentity => $conffieldmappings)
				{
					foreach ($conffieldmappings as $fhcfield => $sffield)
					{
						if (isset($employee->{$fhcfield}))
						{
							$value = $employee->{$fhcfield};
							if (isset($this->_convertfunctions[$fhctable][$fhcfield]))
								$value = $this->{$this->_convertfunctions[$fhctable][$fhcfield]}($value);


							if (!isEmptyString($value))// data should not get lost in SAPSF if empty field
								$sapsfemployee[$sapsfentity][$fhctable][self::DATA_INDEX][$sffield] = $value;
						}
					}
				}
			}

			// add predicates which are used for unique identification for update
			foreach ($this->_predicates as $sapsfentity => $sffieldnames)
			{
				if (isset($sapsfemployee[$sapsfentity][$fhctable]))
				{
					foreach ($sffieldnames as $sffield)
					{
						if (isset($sapsfemployee[$sapsfentity][$fhctable][self::DATA_INDEX][$sffield]))
						{
							$sapsfemployee[$sapsfentity][$fhctable][self::PREDICATE_INDEX][$sffield] = $sapsfemployee[$sapsfentity][$fhctable][self::DATA_INDEX][$sffield];
							unset($sapsfemployee[$sapsfentity][$fhctable][self::DATA_INDEX][$sffield]); // unset predicate from data - no need to update predicate
						}
						else
							$sapsfemployee[$sapsfentity][$fhctable][self::PREDICATE_INDEX][$sffield] = '';
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

	/**
	 * Recursively searches for a value with a certain key in an object or array.
	 * @param string $needle the key to search for
	 * @param object|array $haystack
	 * @return string|bool found value or false
	 */
	private function _recursive_object_search($needle, $haystack)
	{
		foreach($haystack as $prop => $value)
		{
			if (is_object($value) || is_array($value))
			{
				$nextKey = $this->_recursive_object_search($needle,$value);
				if ($nextKey)
				{
					return $nextKey;
				}
			}
			elseif ($prop == $needle) {
				return $value;
			}
		}
		return false;
	}
}
