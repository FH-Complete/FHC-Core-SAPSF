<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library for handling saving of data coming from SAPSF in FHC
 */
class SaveFromSAPSFLib
{
	const IMPORTUSER = 'SAPSF'; // user for insertion/update

	private $_ci;
	private $_fhcconffields;

	public function __construct()
	{
		$this->_ci =& get_instance();

		$this->_fhcconffields = $this->_ci->config->item('fhcfields');

		//load models
		$this->_ci->load->model('person/person_model', 'PersonModel');
		$this->_ci->load->model('ressource/mitarbeiter_model', 'MitarbeiterModel');
		$this->_ci->load->model('person/benutzer_model', 'BenutzerModel');
		$this->_ci->load->model('person/adresse_model', 'AdresseModel');
		$this->_ci->load->model('person/kontakt_model', 'KontaktModel');
		$this->_ci->load->model('codex/Nation_model', 'NationModel');
		$this->_ci->load->model('person/Benutzerfunktion_model', 'BenutzerfunktionModel');
		$this->_ci->load->model('extensions/FHC-Core-SAPSF/fhcomplete/FhcDbModel', 'FhcDbModel');
	}

	/**
	 * Saves Mitarbeiter in fhc database.
	 * @param $maobj
	 * @return object
	 */
	public function saveMitarbeiter($maobj)
	{
		$uid = isset($maobj['benutzer']['uid']) ? $maobj['benutzer']['uid'] : '';
		$person_id = null;

		$this->_ci->BenutzerModel->addSelect('uid, person_id');
		$benutzerexists = $this->_ci->BenutzerModel->loadWhere(array('uid' =>$uid));

		if (hasData($benutzerexists))
		{
			// set person_id so pk is present and unique values can be checked
			$person_id = getData($benutzerexists)[0]->person_id;
			if (isset($maobj['person']))
				$maobj['person']['person_id'] = $person_id;
		}

		$errors = $this->_fhcObjHasError($maobj, SyncEmployeesFromSAPSFLib::OBJTYPE, $uid);
		if ($errors->error)
			return error(implode(", ", $errors->errorMessages));

		$person = $maobj['person'];
		$mitarbeiter = $maobj['mitarbeiter'];
		$benutzer = $maobj['benutzer'];
		$uid = $benutzer['uid'];

		$this->_ci->db->trans_begin();

		$this->_ci->BenutzerModel->addSelect('uid, person_id, aktiv');
		$benutzerexists = $this->_ci->BenutzerModel->loadWhere(array('uid' =>$uid));

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

			$this->_ci->BenutzerModel->update(array('uid' => $uid), $benutzer);

			// if benutzer exists, person must exist -> update person
			$this->_stamp('update', $person);
			$this->_ci->PersonModel->update($person_id, $person);

			$this->_updatePersonContacts($person_id, $maobj);

			// Mitarbeiter may not exist even if there is a Benutzer - update only if already exists, otherwise insert
			$mitarbeiterexists = $this->_ci->MitarbeiterModel->load(array('mitarbeiter_uid' => $uid));
			if (hasData($mitarbeiterexists))
			{
				//$this->_stamp('update', $mitarbeiter); no stamp so it is not marked as new for ToSAPSFSync
				$mitarbeiterres = $this->_ci->MitarbeiterModel->update(array('mitarbeiter_uid' => $uid), $mitarbeiter);
			}
			else
			{
				$this->_stamp('insert', $mitarbeiter);
				$mitarbeiterres = $this->_ci->MitarbeiterModel->insert($mitarbeiter);
			}
		}
		else
		{
			// no benutzer found - checking if person with same svnr already exists
			if (isset($person['svnr']) && !isEmptyString($person['svnr']))
			{
				$this->_ci->PersonModel->addSelect('person_id');
				$hasSvnr = $this->_ci->PersonModel->loadWhere(array('svnr' => $person['svnr']));

				if (isSuccess($hasSvnr) && hasData($hasSvnr))
				{
					$person_id = getData($hasSvnr)[0]->person_id;
					// update person if found svnr
					$this->_stamp('update', $person);
					$this->_ci->PersonModel->update($person_id, $person);
				}
			}

			if (!isset($person_id))
			{
				// new person
				$this->_stamp('insert', $person);
				$personres = $this->_ci->PersonModel->insert($person);
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
				$benutzerres = $this->_ci->BenutzerModel->insert($benutzer);

				// insert mitarbeiter
				if (isSuccess($benutzerres))
				{
					$this->_stamp('insert', $mitarbeiter);
					$mitarbeiterres = $this->_ci->MitarbeiterModel->insert($mitarbeiter);
				}
			}
		}

		// generate and save alias
		$this->_ci->FhcDbModel->manageAliasForUid($uid);

		// generate and save kurzbz
		$this->_ci->FhcDbModel->manageKurzbzForUid($uid);

		// Transaction complete!
		$this->_ci->db->trans_complete();

		// Check if everything went ok during the transaction
		if ($this->_ci->db->trans_status() === false)
		{
			$this->output .= "rolling back...";
			$this->_ci->db->trans_rollback();
			return error("Database error occured while syncing " . $uid);
		}
		else
		{
			$this->_ci->db->trans_commit();
			return success($uid);
		}
	}

	/**
	 * Saves Kostenstellenfunktionen in FHC database.
	 * @param $kstobj contains all Kostenstellenfunktionen for an employee
	 * @return object
	 */
	public function saveKostenstellenfunktionen($kstobj)
	{
		$benutzerfunktion = $kstobj['benutzerfunktion'];
		$uid = $benutzerfunktion['mitarbeiter_uid'];

		if (!isset($benutzerfunktion['oe_kurzbz']) || isEmptyString($benutzerfunktion['oe_kurzbz']))
			return error("No oe_kurzbz found for uid $uid");

		$funktionenToInsert = array();

		foreach ($benutzerfunktion['oe_kurzbz'] as $idx => $oe_kurzbz)
		{
			$funktionToInsert = array(
				'uid' => $uid,
				'oe_kurzbz' => $oe_kurzbz,
				'datum_von' => $benutzerfunktion['datum_von'][$idx],
				'datum_bis' => $benutzerfunktion['datum_bis'][$idx],//$this->_sapsfDateIsUnlimited($newDatumBis) ? null : $newDatumBis, // 9999 means unlimited, currently active
				'bezeichnung' => $benutzerfunktion['bezeichnung'],
				'funktion_kurzbz' => $benutzerfunktion['funktion_kurzbz'],
				'insertvon' => $benutzerfunktion['insertvon']
			);

			$errors = $this->_fhcObjHasError(array('benutzerfunktion' => $funktionToInsert), SyncEmployeesFromSAPSFLib::COST_CENTER_OBJ, $uid);

			if ($errors->error)
				return error(implode(", ", $errors->errorMessages));

			$funktionenToInsert[] = $funktionToInsert;
		}

		$this->_ci->db->trans_begin();

		// delete all existing Standardkostenstelle assignments
		$deleteFunktionenResult = $this->_ci->FhcDbModel->deleteKostenstellenFunktionen($benutzerfunktion['mitarbeiter_uid']);

		if (isError($deleteFunktionenResult))
			return $deleteFunktionenResult;

		foreach ($funktionenToInsert as $funktion)
		{
			$insertres = $this->_ci->BenutzerfunktionModel->insert($funktion);

			if (isError($insertres))
				return $insertres;
		}

		// Transaction complete!
		$this->_ci->db->trans_complete();

		// Check if everything went ok during the transaction
		if ($this->_ci->db->trans_status() === false)
		{
			$this->output .= "rolling back...";
			$this->_ci->db->trans_rollback();
			return error("Database error occured while saving Kostenstelle for " . $uid);
		}
		else
		{
			$this->_ci->db->trans_commit();
			return success($uid);
		}
	}

	/**
	 * Checks if fhcomplete object has errors, e.g. missing fields, thus cannot be inserted in db.
	 * @param $fhcobj
	 * @param $objtype
	 * @param $fhcobjidname
	 * @param $fhcobjid
	 * @return StdClass object with properties boolean for has Error and array with errormessages
	 */
	private function _fhcObjHasError($fhcobj, $objtype, $fhcobjid)
	{
		$hasError = new StdClass();
		$hasError->error = false;
		$hasError->errorMessages = array();
		$allfields = $this->_fhcconffields[$objtype];

		foreach ($allfields as $table => $fields)
		{
			if (array_key_exists($table, $fhcobj))
			{
				foreach ($fields as $field => $params)
				{
					$haserror = false;
					$errortext = '';
					$required = isset($params['required']) && $params['required'];

					if (array_key_exists($field, $fhcobj[$table]))
					{
						$value = $fhcobj[$table][$field];

						if ($required && (!isset($value) || $value === ''))
						{
							$haserror = true;
							$errortext = 'is missing';
						}
						elseif(isset($params['notnull']) && $params['notnull'] === true && $value === null)
						{
							// notnull constraint violated
							$haserror = true;
							$errortext = "cannot be null";
						}
						else
						{
							// right data type?
							$wrongdatatype = false;

							if ($value !== null)
							{
								if (isset($params['type']))
								{
									switch ($params['type'])
									{
										case 'integer':
											if (!is_numeric($value))
											{
												$wrongdatatype = true;
											}
											break;
										case 'boolean':
											if (!is_bool($value))
											{
												$wrongdatatype = true;
											}
											break;
										case 'date':
											if (!validateDateFormat($value))
											{
												$wrongdatatype = true;
											}
											break;
										case 'base64':
											if (!base64_encode(base64_decode($value, true)) === $value)
												$wrongdatatype = true;
											break;
										case 'stringarray':
											if (is_array($value))
											{
												foreach ($value as $item)
												{
													if (!is_string($item))
													{
														$wrongdatatype = true;
													}
												}
											}
											else
												$wrongdatatype = true;
											break;
										case 'string':
											if (!is_string($value))
											{
												$wrongdatatype = true;
											}
											break;
									}
								}
								elseif (!is_string($value))
								{
									$wrongdatatype = true;
								}
								else
								{
									$params['type'] = 'string';
								}

								if ($wrongdatatype)
								{
									$haserror = true;
									$errortext = 'has wrong data type';
								}
								// right string/int length?
								$rightlength = true;

								if (!$haserror)
								{
									if ($params['type'] === 'string' || $params['type'] === 'base64')
									{
										$rightlength = $this->_ci->FhcDbModel->checkStrLength($table, $field, $value);

										if ($rightlength && isset($params['length']) && is_numeric($params['length']))
											$rightlength = $params['length'] == strlen($value);
									}
									elseif ($params['type'] === 'integer' && is_integer($value))
									{
										$rightlength = $this->_ci->FhcDbModel->checkIntLength($value);
									}

									if (!$rightlength)
									{
										$haserror = true;
										$errortext = "has wrong length ($value)";
									}
								}

								// unique constraint violated?
								if (!$haserror && isset($params['unique']) && $params['unique'] === true && isset($params['pk']))
								{
									$exceptions = isset($fhcobj[$table][$params['pk']]) ? array($params['pk'] => $fhcobj[$table][$params['pk']]) : null;
									$valueexists = $this->_ci->FhcDbModel->valueExists($table, $field, $value, $exceptions);

									if (hasData($valueexists))
									{
										$haserror = true;
										$errortext = "already exists ($value)";
									}
								}
								// value referenced with foreign key exists?
								if (!$haserror && isset($params['ref']))
								{
									$fkfield = isset($params['reffield']) ? $params['reffield'] : $field;
									$foreignkeyexists = $this->_ci->FhcDbModel->valueExists($params['ref'], $fkfield, $value);

									if (!hasData($foreignkeyexists))
									{
										$haserror = true;
										$errortext = 'has no match in FHC';
									}
								}
							}
						}
					}
					elseif ($required)
					{
						$haserror = true;
						$errortext = 'is missing';
					}

					if ($haserror)
					{
						$fieldname = isset($params['name']) ? $params['name'] : ucfirst($field);

						$hasError->errorMessages[] = "id ".$fhcobjid.": ".ucfirst($table).": $fieldname ".$errortext;
						$hasError->error = true;
					}
				}
			}
			else
			{
				$hasError->errorMessages[] = "data missing: $table";
				$hasError->error = true;
			}
		}

		return $hasError;
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
		$this->_ci->KontaktModel->addSelect('kontakt_id');
		$this->_ci->KontaktModel->addOrder('insertamum', 'DESC');
		$this->_ci->KontaktModel->addOrder('kontakt_id', 'DESC');
		$kontaktmail['person_id'] = $person_id;

		$kontaktmailToUpdate = $this->_ci->KontaktModel->loadWhere(array(
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
				$kontaktmailres = $this->_ci->KontaktModel->update($kontakt_id, $kontaktmail);
			}
			else
			{
				$this->_stamp('insert', $kontaktmail);
				$kontaktmailres = $this->_ci->KontaktModel->insert($kontaktmail);
			}
		}

		foreach ($kontakttelefone as $kontakttelefon)
		{
			// update phone - assuming there is only one!
			$this->_ci->KontaktModel->addSelect('kontakt_id');
			$this->_ci->KontaktModel->addOrder('insertamum', 'DESC');
			$this->_ci->KontaktModel->addOrder('kontakt_id', 'DESC');
			$kontakttelefon['person_id'] = $person_id;

			$kontakttelToUpdate = $this->_ci->KontaktModel->loadWhere(array(
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
					$kontakttelres = $this->_ci->KontaktModel->update($kontakt_id, $kontakttelefon);
				}
				else
				{
					$this->_stamp('insert', $kontakttelefon);
					$kontakttelres = $this->_ci->KontaktModel->insert($kontakttelefon);
				}
			}
		}

		// update kontaktnotfall
		$kontaktnotfall['person_id'] = $person_id;

		$this->_ci->KontaktModel->addSelect('kontakt_id');
		$this->_ci->KontaktModel->addOrder('insertamum', 'DESC');
		$this->_ci->KontaktModel->addOrder('kontakt_id', 'DESC');
		$kontaktnotfallToUpdate = $this->_ci->KontaktModel->loadWhere(
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
				$kontaktnotfallres = $this->_ci->KontaktModel->update($kontakt_id, $kontaktnotfall);
			}
			else
			{
				$this->_stamp('insert', $kontaktnotfall);
				$kontaktnotfallres = $this->_ci->KontaktModel->insert($kontaktnotfall);
			}
		}

		// update adressen
		foreach ($adressen as $adresse)
		{
			// update adress - assuming there is only one!
			$this->_ci->AdresseModel->addSelect('adresse_id');
			$this->_ci->AdresseModel->addOrder('insertamum', 'DESC');
			$this->_ci->AdresseModel->addOrder('adresse_id', 'DESC');
			$adresse['person_id'] = $person_id;

			$adresseToUpdate = $this->_ci->AdresseModel->loadWhere(array(
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
					$kontakaddrres = $this->_ci->AdresseModel->update($adresse_id, $adresse);
				}
				else
				{
					$this->_stamp('insert', $adresse);
					$kontaktaddrres = $this->_ci->AdresseModel->insert($adresse);
				}
			}
		}
	}

	/**
	 * Sets timestamp and importuser for insert/update.
	 * @param $modtype
	 * @param $arr
	 */
	private function _stamp($modtype, &$arr)
	{
		$idx = $modtype . 'amum';
		$arr[$idx] = date('Y-m-d H:i:s', time());
		$idx = $modtype . 'von';
		$arr[$idx] = self::IMPORTUSER;
	}
}
