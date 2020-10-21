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
	const KONTAKTTEL_NAME = 'kontakttel';
	const SAPSF_IS_PRIMARY_NAME = 'isPrimary';
	const SAPSF_END_DATE_NAME = 'endDate';
	const EMAIL_POSTFIX = '@technikum-wien.at';

	private $_convertfunctions = array(
		'kontakttel' => array(
			'firmentelefon_nummer' => '_convertToPhoneParts'
		),
		'kontakttelmobile' => array(
			'firmenhandy' => '_convertToPhoneParts'
		),
		'kontaktmail' => array(
			'uid' => '_convertToAliasMail'
		),
		'kontaktmailtech' => array(
			'uid' => '_convertToTechMail'
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
			$mitarbeiterobjname = 'mitarbeiter';
			$fhcmailprivate = 'kontaktmailprivate';
			$fhcmailbusiness = 'kontaktmail';
			$fhcmailtechnical = 'kontaktmailtech';
			$fhctelbusiness = 'kontakttelefon';
			$fhctelprivate = 'kontakttelprivate';
			$fhctelmobile = 'kontakttelmobile';

			$mitarbeiter = getData($mitarbeiter);

			$sapsfUidName = $this->_predicates[self::OBJTYPE][0];
			$sapsfPersonIdName = $this->_predicates[self::MAILTYPE][0];
			$sapsfstartDateName = $this->_predicates[self::PERSONALINFOTYPE][1];
			$sapsfEmailTypeName = $this->_predicates[self::MAILTYPE][1];
			$sapsfPhoneTypeName = $this->_predicates[self::PHONETYPE][1];
			$sapsfOfficeName = $this->_conffieldmappings[$mitarbeiterobjname][self::PERSONALINFOTYPE]['ort_kurzbz'];
			$sapsfNummerName = $this->_conffieldmappings[self::KONTAKTTEL_NAME][self::PHONETYPE]['firmentelefon_nummer'];

			$mailfieldmapping = $this->ci->syncfromsapsflib->getFieldMappings('User', $fhcmailbusiness, array('kontakt'))['kontakt'];
			$phonefieldmapping = $this->ci->syncfromsapsflib->getFieldMappings('User', $fhctelbusiness, array('kontakt'))['kontakt'];
			$officefieldmapping = $this->_navigationfields[self::PERSONALINFOTYPE][$sapsfOfficeName];

			$mail_expl = explode('/', $mailfieldmapping);
			$phone_expl = explode('/', $phonefieldmapping);

			$mailfieldmapping_arr = array_slice($mail_expl, 0, count($mail_expl) - 1);
			$phonefieldmapping_arr = array_slice($phone_expl, 0, count($phone_expl) - 1);
			$officefieldmapping_arr = explode('/', $officefieldmapping);

			$emailnav = implode('/',$mailfieldmapping_arr);
			$phonenav = implode('/', $phonefieldmapping_arr);

			// only update employees which are already in sapsf
			$sapsfemployees = $this->ci->QueryUserModel->getAll(
				array($sapsfUidName,
					  self::PERSON_KEY_NAV.'/'.$sapsfPersonIdName,
					  $emailnav.'/'.$sapsfEmailTypeName,
					  $phonenav .'/'.$sapsfPhoneTypeName,
					  $officefieldmapping . '/'.$sapsfOfficeName,
					  $officefieldmapping . '/'.$sapsfstartDateName,
					  $officefieldmapping . '/'.self::SAPSF_END_DATE_NAME
					), // selects
				array(self::PERSON_KEY_NAV,  $emailnav, $phonenav, $officefieldmapping), // expands
				null, null, null, $uids //no lastmodifieddate and no from - to dates
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
							$fhcdate = isset($ma->updateamum) ? $ma->updateamum : $ma->insertamum;
							if (isEmptyString($fhcdate))
								$fhcdate = date('Y-m-d H:i:s');
							$syncdate = $this->ci->syncfromsapsflib->convertDateToSAPSF($fhcdate);

							$sapsfvals = array(
								$sapsfPersonIdName => $sapsfemployee->{self::PERSON_KEY_NAV}->{$sapsfPersonIdName},
								$sapsfstartDateName => $syncdate
							);

							$emailTypes = getPropertyByArray($sapsfemployee, $mailfieldmapping_arr);
							$emailTypes = isset($emailTypes->results) ? $emailTypes->results : array();

							$phoneTypes = getPropertyByArray($sapsfemployee, $phonefieldmapping_arr);
							$phoneTypes = isset($phoneTypes->results) ? $phoneTypes->results : array();

							$personalInfos = getPropertyByArray($sapsfemployee, $officefieldmapping_arr);
							$personalInfos = isset($personalInfos->results) ? $personalInfos->results : array();

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
							$hasBusinessMail = false;
							$personalInfoDatesToUpdate = array();
							$privateMailTypCode = $this->_confvaluedefaults[$fhcmailprivate][self::MAILTYPE][$sapsfEmailTypeName];
							$businessMailTypCode = $this->_confvaluedefaults[$fhcmailbusiness][self::MAILTYPE][$sapsfEmailTypeName];
							$privateTelTypCode = $this->_confvaluedefaults[$fhctelprivate][self::PHONETYPE][$sapsfPhoneTypeName];
							$mobileTelTypCode = $this->_confvaluedefaults[$fhctelmobile][self::PHONETYPE][$sapsfPhoneTypeName];

							foreach ($emailTypes as $emailType)
							{
								if ($emailType->{$sapsfEmailTypeName} == $privateMailTypCode)
								{
									$hasPrivateMail = true;
								}
								elseif ($emailType->{$sapsfEmailTypeName} == $businessMailTypCode)
								{
									$hasBusinessMail = true;
								}
							}

							foreach ($phoneTypes as $phoneType)
							{
								if ($phoneType->{$sapsfPhoneTypeName} == $privateTelTypCode)
								{
									$hasPrivatePhone = true;
								}
								elseif ($phoneType->{$sapsfPhoneTypeName} == $mobileTelTypCode)
								{
									$hasMobilePhone = true;
								}
							}

							// get dates for updating personalInfo (each entry in future should be updated)
							foreach ($personalInfos as $personalInfo)
							{
								$start = $this->_convertSAPSFTimestampToDateTime($personalInfo->{$sapsfstartDateName});
								$ende = $this->_convertSAPSFTimestampToDateTime($personalInfo->{self::SAPSF_END_DATE_NAME});

								if ($ende > $syncdate)
								{
									if (isEmptyString($personalInfo->{$sapsfOfficeName})
										|| $personalInfo->{$sapsfOfficeName} != $matosync[self::PERSONALINFOTYPE][$mitarbeiterobjname][self::DATA_INDEX][$sapsfOfficeName])
									{
										if ($start < $syncdate) // current office
											$personalInfoDatesToUpdate[] = $syncdate;
										else
											$personalInfoDatesToUpdate[] = $start;
									}
								}
							}

							// not sync private mail and phone if there is none in SAPSF
							if (!$hasPrivateMail)
								unset($matosync[self::MAILTYPE][$fhcmailprivate]);

							if (!$hasPrivatePhone)
								unset($matosync[self::PHONETYPE][$fhctelprivate]);

							if (!$hasMobilePhone && !isset($matosync[self::PHONETYPE][$fhctelmobile][self::DATA_INDEX][$sapsfNummerName]))
								unset($matosync[self::PHONETYPE][$fhctelmobile]);

							if (isEmptyArray($personalInfoDatesToUpdate))
								unset($matosync[self::PERSONALINFOTYPE]);
							else
							{
								// special case office: update all future personalInfos if there are any (otherwise existing future entries will overwrite the change)
								$currPersonal = $matosync[self::PERSONALINFOTYPE][$mitarbeiterobjname];
								$idx = 0;
								foreach ($personalInfoDatesToUpdate as $personalInfoDate)
								{
									$mitarbeiterobjnameIdx = $idx > 0 ? $mitarbeiterobjname."_$idx" : $mitarbeiterobjname;
									$currPersonal[self::PREDICATE_INDEX][$sapsfstartDateName] = $personalInfoDate;
									$matosync[self::PERSONALINFOTYPE][$mitarbeiterobjnameIdx] = $currPersonal;
									$idx++;
								}
							}

							// not sync required data if empty
							foreach ($matosync as $sapsfproperty => $values)
							{
								if (isset($this->_requiredfields[$sapsfproperty]))
								{
									foreach ($values as $objname => $data)
									{
										if (isset($this->_requiredfields[$sapsfproperty][$objname]))
										{
											$requiredExists = false;
											$required = $this->_requiredfields[$sapsfproperty][$objname];

											foreach ($required as $req)
											{
												$navprops = explode('/', $req);

												if (isset($matosync[$sapsfproperty][$navprops[0]][self::DATA_INDEX][$navprops[1]]) &&
												!isEmptyString($matosync[$sapsfproperty][$navprops[0]][self::DATA_INDEX][$navprops[1]]))
												{ // at least one field has to be present
													$requiredExists = true;
													break;
												}
											}

											if (!$requiredExists)
												unset($matosync[$sapsfproperty][$objname]); // remove if required field not present
										}
									}
								}
							}

							// set technical mail as primary if there is no other mail
							if (!$hasPrivateMail && !$hasBusinessMail
								&& isset($matosync[self::MAILTYPE][$fhcmailtechnical][self::DATA_INDEX])
								&& count($matosync[self::MAILTYPE]) == 1
								&& !isEmptyArray($matosync[self::MAILTYPE][$fhcmailtechnical][self::DATA_INDEX]))
							{
								$matosync[self::MAILTYPE][$fhcmailtechnical][self::DATA_INDEX][self::SAPSF_IS_PRIMARY_NAME] = true;
							}

							// set firmenhandy as primary if there is no other phone
							if (!$hasPrivatePhone
								&& isset($matosync[self::PHONETYPE][$fhctelmobile][self::DATA_INDEX])
								&& count($matosync[self::PHONETYPE]) == 1
								&& !isEmptyArray($matosync[self::PHONETYPE][$fhctelmobile][self::DATA_INDEX]))
							{
								$matosync[self::PHONETYPE][$fhctelmobile][self::DATA_INDEX][self::SAPSF_IS_PRIMARY_NAME] = true;
							}

							if (!isEmptyArray($matosync))
								$mitarbeiterToSync[$ma->uid] = $matosync;
						}
					}
				}
			}
		}

		if ($this->_syncpreview !== false)
			printAndDie($mitarbeiterToSync);

		return success($mitarbeiterToSync);
	}

	/**
	 * Converts employee to save in SAPSF.
	 * @param $employee
	 * @return array converted employee
	 */
	private function _convertEmployeeToSapsf($employee)
	{
		$fhctables = array('benutzer', 'person', 'mitarbeiter', 'kontakttel', 'kontakttelprivate', 'kontakttelmobile',
			'kontaktmail', 'kontaktmailprivate', 'kontaktmailtech');
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
						$splitted = false;
						$value = isset($employee->{$fhcfield}) ? $employee->{$fhcfield} : '';
						if (isset($this->_convertfunctions[$fhctable][$fhcfield]))
						{
							$value = $this->{$this->_convertfunctions[$fhctable][$fhcfield]}($value);

							if (is_object($value) || is_array($value)) // if object/array, all properties are copied
							{
								$splitted = true;
								foreach ($value as $key => $item)
								{
									$sapsfemployee[$sapsfentity][$fhctable][self::DATA_INDEX][$key] = $item;
								}
							}
						}

						//if (!isEmptyString($value))// data should not get lost in SAPSF if empty field
						if (!$splitted)
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
	private function _convertToAliasMail($uid)
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

	/**
	 * Gets correct alias for a uid
	 * @param string $uid
	 * @return string
	 */
	private function _convertToTechMail($uid)
	{
		$mail = null;

		if (!isEmptyString($uid))
			$mail = $uid.self::EMAIL_POSTFIX;

		return $mail;
	}

	/**
	 * Splits a phone contact into vorwahl, ortsvorwahl and nummer.
	 * @param $kontakt
	 * @return object containing vorwahl, ortsvorwahl and nummer
	 */
	private function _convertToPhoneParts($kontakt)
	{
		$sapsfVorwahlName = $this->_conffieldmappings[self::KONTAKTTEL_NAME][self::PHONETYPE]['firmentelefon_vorwahl'];
		$sapsfOrtsvorwahlName = $this->_conffieldmappings[self::KONTAKTTEL_NAME][self::PHONETYPE]['firmentelefon_ortsvorwahl'];
		$sapsfNummerName = $this->_conffieldmappings[self::KONTAKTTEL_NAME][self::PHONETYPE]['firmentelefon_nummer'];
		$teleobj = new stdClass();

		// parse phone and get parts
		$telparts = explode(' ', str_replace('-', ' ', $kontakt));
		$counttelparts = count($telparts);

		if ($counttelparts >= 3)
		{
			$teleobj->{$sapsfVorwahlName} = preg_replace('/[^0-9]/', '', $telparts[0]);
			$teleobj->{$sapsfOrtsvorwahlName} = preg_replace('/[^0-9]/', '', $telparts[1]);
			$teleobj->{$sapsfNummerName} = preg_replace('/[^0-9]/', '', implode('', array_slice($telparts, 2, $counttelparts)));
		}
		else // split phone reasonably if no separators...
		{
			$phone = preg_replace('/[^0-9]/', '', $kontakt);
			if (strlen($phone) >= 5)
			{
				$vorwahl_length = 1;
				if (substr($phone, 0, 2) == '43')
					$vorwahl_length = 2;
				if (substr($phone, 0, 4) == '0043')
					$vorwahl_length = 4;

				$teleobj->{$sapsfVorwahlName} = substr($phone, 0, $vorwahl_length);
				$teleobj->{$sapsfOrtsvorwahlName} = substr($phone, $vorwahl_length, 3);
				$teleobj->{$sapsfNummerName} = substr($phone, $vorwahl_length + 3, strlen($phone));
			}
		}

		return $teleobj;
	}
}
