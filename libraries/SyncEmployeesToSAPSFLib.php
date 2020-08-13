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
	const PHONETYPE = 'PerPhone';

	const PERSON_KEY_NAV = 'personKeyNav';
	const EMPINFO = 'empInfo';
	const PERSON_NAV = 'personNav';
	const EMAIL_NAV = 'emailNav';
	const PHONE_NAV = 'phoneNav';
	const EMAIL_POSTFIX = '@technikum-wien.at';

	private $_convertfunctions = array(
		'kontaktmail' => array(
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
		$this->ci->load->model('person/adresse_model', 'AdresseModel');
		$this->ci->load->model('person/kontakt_model', 'KontaktModel');
		$this->ci->load->model('extensions/FHC-Core-SAPSF/fhcomplete/FhcDbModel', 'FhcDbModel');
		$this->ci->load->model('extensions/FHC-Core-SAPSF/SAPSFQueries/QueryUserModel', 'QueryUserModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Gets employees from fhcomplete. Prepares them for the sync.
	 * @param array $uids
	 * @return mixed
	 */
	public function getEmployeesForSync($uids)
	{
		$this->ci->load->library('extensions/FHC-Core-SAPSF/SyncFromSAPSFLib');

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
			$sapsfEmailTypeName = $this->_predicates[self::MAILTYPE][1];
			$sapsfPhoneTypeName = $this->_predicates[self::PHONETYPE][1];

			$emailnav = self::EMPINFO.'/'.self::PERSON_NAV.'/'.self::EMAIL_NAV;
			$phonenav = self::EMPINFO.'/'.self::PERSON_NAV.'/'.self::PHONE_NAV;

			$fhcmailprivate = 'kontaktmailprivate';
			$fhctelprivate = 'kontakttelprivate';
			$fhctelmobile = 'kontakttelmobile';

			$sapsfemployees = $this->ci->QueryUserModel->getAll(
				array($sapsfUidName, self::PERSON_KEY_NAV.'/'.$sapsfPersonIdName, $emailnav.'/'.$sapsfEmailTypeName, $phonenav .'/'.$sapsfPhoneTypeName), // selects
				array(self::PERSON_KEY_NAV,  $emailnav, $phonenav), // expands
				null, null, $uids
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

							$sapsfvals = array(
								$sapsfPersonIdName => $sapsfemployee->{self::PERSON_KEY_NAV}->{$sapsfPersonIdName},
								$sapsfstartDateName => $this->ci->syncfromsapsflib->convertDateToSAPSF(date('Y-m-d H:i:s'))
							);

							$emailTypes = isset($sapsfemployee->{self::EMPINFO}->{self::PERSON_NAV}->{self::EMAIL_NAV}->results) ?
								$sapsfemployee->{self::EMPINFO}->{self::PERSON_NAV}->{self::EMAIL_NAV}->results : array();

							$phoneTypes = isset($sapsfemployee->{self::EMPINFO}->{self::PERSON_NAV}->{self::PHONE_NAV}->results) ?
								$sapsfemployee->{self::EMPINFO}->{self::PERSON_NAV}->{self::PHONE_NAV}->results : array();

							foreach ($matosync as $entity => $mats)
							{
								foreach ($mats as $fhctbl => $data)
								{
									foreach ($matosync[$entity][$fhctbl][self::PREDICATE_INDEX] as $predprop => $predval)
									{
										if (isset($sapsfvals[$predprop]))
											$matosync[$entity][$fhctbl][self::PREDICATE_INDEX][$predprop] = $sapsfvals[$predprop];
									}
								}
							}

							$hasPrivatePhone = false;
							$hasMobilePhone = false;
							$hasPrivateMail = false;
							$privateMailTypCode = $this->_confvaluedefaults[$fhcmailprivate][self::MAILTYPE][$sapsfEmailTypeName];
							$privateTelTypCode = $this->_confvaluedefaults[$fhctelprivate][self::PHONETYPE][$sapsfPhoneTypeName];
							$mobileTelTypCode = $this->_confvaluedefaults[$fhctelmobile][self::PHONETYPE][$sapsfPhoneTypeName];

							foreach ($emailTypes as $emailType)
							{
								if ($emailType->{$sapsfEmailTypeName} == $privateMailTypCode)
								{
									$hasPrivateMail = true;
									break;
								}
							}

							foreach ($phoneTypes as $phoneType)
							{
								if ($phoneType->{$sapsfPhoneTypeName} == $privateTelTypCode)
								{
									$hasPrivatePhone = true;
								}

								if ($phoneType->{$sapsfPhoneTypeName} == $mobileTelTypCode)
								{
									$hasMobilePhone = true;
								}
							}

							// not sync private mail and phone if there is none
							if (!$hasPrivateMail)
								unset($matosync[self::MAILTYPE][$fhcmailprivate]);

							if (!$hasPrivatePhone)
								unset($matosync[self::PHONETYPE][$fhctelprivate]);

							if (!$hasMobilePhone)
								unset($matosync[self::PHONETYPE][$fhctelmobile]);

							if (!isEmptyArray($matosync))
								$mitarbeiterToSync[$ma->uid] = $matosync;
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
		$fhctables = array('benutzer', 'person', 'mitarbeiter', 'kontakttel', 'kontakttelprivate', 'kontakttelmobile', 'kontaktmail', 'kontaktmailprivate');
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
						/*if (isset($employee->{$fhcfield}))*/
						//{
							$value = isset($employee->{$fhcfield}) ? $employee->{$fhcfield} : '';
							if (isset($this->_convertfunctions[$fhctable][$fhcfield]))
								$value = $this->{$this->_convertfunctions[$fhctable][$fhcfield]}($value);

							//if (!isEmptyString($value))// data should not get lost in SAPSF if empty field
							$sapsfemployee[$sapsfentity][$fhctable][self::DATA_INDEX][$sffield] = $value;
						//}
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
		$alias = null;
		$this->ci->BenutzerModel->addLimit(1);
		$this->ci->BenutzerModel->addSelect('alias');
		$alias = $this->ci->BenutzerModel->loadWhere(array('uid' => $uid));

		if (hasData($alias))
		{
			$alias = getData($alias)[0]->alias;
			if (!isEmptyString($alias))
				$alias = $alias.self::EMAIL_POSTFIX;
		}

		return $alias;
	}
}
