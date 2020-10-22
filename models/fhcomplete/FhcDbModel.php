<?php


class FhcDbModel extends DB_Model
{
	const TABLE_PREFIX = 'tbl_';

	const MAX_INT = 2147483647;
	const MIN_INT = -2147483648;

	public function __construct()
	{
		parent::__construct();

		$this->load->model('person/Benutzer_model', 'BenutzerModel');
		$this->load->model('ressource/Mitarbeiter_model', 'MitarbeiterModel');
		$this->load->model('codex/Nation_model', 'NationModel');
		$this->load->model('extensions/FHC-Core-SAPSF/fhcomplete/SAPStundensatz_model', 'StundensatzModel');
	}

	/**
	 * Checks if a table column value exists in fhcomplete database
	 * @param $table
	 * @param $field
	 * @param $value
	 * @param $exceptions
	 * @return object
	 */
	public function valueExists($table, $field, $value, $exceptions = null)
	{
		$table = substr($table, 0, strlen(self::TABLE_PREFIX)) === self::TABLE_PREFIX || strstr($table, '.' . self::TABLE_PREFIX) ? $table : self::TABLE_PREFIX . $table;
		$query = "SELECT 1 FROM %s WHERE %s = ?";

		$fields = array($table, $field);
		$values = array($value);

		if (isset($exceptions) && is_array($exceptions))
		{
			foreach ($exceptions as $key => $value)
			{
				$query .= " AND %s <> ?";
				$fields[] = $key;
				$values[] = $value;
			}
		}

		return $this->execQuery(vsprintf($query, $fields), $values);
	}

	/**
	 * Checks if a table column value has right length
	 * @param $table
	 * @param $field
	 * @param $value
	 * @return bool
	 */
	public function checkStrLength($table, $field, $value)
	{
		$table = self::TABLE_PREFIX.$table;
		$query = "SELECT character_maximum_length FROM information_schema.columns
					WHERE table_name = ? AND column_name = ? LIMIT 1";
		$length = $this->execQuery($query, array($table, $field));

		if (isSuccess($length))
		{
			if (hasData($length))
			{
				$lengthdata = getData($length);
				$lengthdata = $lengthdata[0]->character_maximum_length;
				return !isset($lengthdata) || strlen($value) <= $lengthdata;
			}
			else
				return true;
		}
		return false;
	}

	/**
	 * Checks if a integer value has right length.
	 * @param $value
	 * @return bool
	 */
	public function checkIntLength($value)
	{
		return $value <= self::MAX_INT && $value >= self::MIN_INT;
	}

	/**
	 * Takes care of actions concerning alias for a user.
	 * - If non-empty alias exists, it is returned.
	 * - If alias is not present, it is generated.
	 * - if alias is generated, it is updated in fhcomplete.
	 * @param string $uid
	 * @return string alias for the uid or empty string
	 */
	public function manageAliasForUid($uid)
	{
		$alias = '';
		$this->BenutzerModel->addSelect('alias');
		$aliasres = $this->BenutzerModel->loadWhere(array('uid' => $uid));
		$hasAlias = false;

		if (hasData($aliasres))
		{
			$aliasres = getData($aliasres);
			$aliasres = $aliasres[0]->alias;
			if (!isEmptyString($aliasres))
			{
				// if there is a non-empty alias in fhc, it is used
				$alias = $aliasres;
				$hasAlias = true;
			}
		}

		if (!$hasAlias)
		{
			// no non-empty alias found -> generate
			$genAlias = $this->BenutzerModel->generateAlias($uid);
			if (hasData($genAlias))
			{
				$genAlias = getData($genAlias);

				if (!isEmptyString($genAlias))
				{
					// set alias in fhcomplete
					$this->BenutzerModel->update(array('uid' => $uid), array('alias' => $genAlias));
					$alias = $genAlias;
				}
			}
		}

		return $alias;
	}

	/**
	 * Takes care of actions concerning kurzbz for a user.
	 * - If non-empty kurzbz exists, it is returned.
	 * - If kurzbz is not present, it is generated.
	 * - if kurzbz is generated, it is updated in fhcomplete.
	 * @param string $uid
	 * @return string kurzbz for the uid or empty string
	 */
	public function manageKurzbzForUid($uid)
	{
		$kurzbz = '';
		$this->MitarbeiterModel->addSelect('kurzbz');
		$kurzbzres = $this->MitarbeiterModel->loadWhere(array('mitarbeiter_uid' => $uid));
		$hasKurzbz = false;

		if (isSuccess($kurzbzres) && !hasData($kurzbzres))
		{
			$kurzbzres = getData($kurzbzres);
			$kurzbzres = $kurzbzres[0]->kurzbz;
			if (!isEmptyString($kurzbzres))
			{
				$kurzbz = $kurzbzres;
				$hasKurzbz = true;
			}
		}

		if (!$hasKurzbz)
		{
			// no non-empty kurzbz found -> generate
			$genKurzbz = $this->MitarbeiterModel->generateKurzbz($uid);
			if (hasData($genKurzbz))
			{
				$genKurzbz = getData($genKurzbz);

				if (!isEmptyString($genKurzbz))
				{
					// set alias in fhcomplete
					$this->MitarbeiterModel->update(array('mitarbeiter_uid' => $uid), array('kurzbz' => $genKurzbz));
					$kurzbz = $genKurzbz;
				}
			}
		}

		return $kurzbz;
	}

	/**
	 * Gets Mitarbeiter with Firmentelefon.
	 * @param array $uids filter by uids
	 * @return object
	 */
	public function getMitarbeiter($uids = null)
	{
		$this->load->model('ressource/Mitarbeiter_model', 'MitarbeiterModel');
		$this->load->model('person/Kontakt_model', 'KontaktModel');

		$mitarbeiterres = array();
		$mitarbeiter = $this->MitarbeiterModel->getPersonal(null, null, null, true);

		if (hasData($mitarbeiter))
		{
			$mitarbeiter = getData($mitarbeiter);
			$allma = isEmptyArray($uids);
			foreach ($mitarbeiter as $idx => $ma)
			{
				if ($allma || in_array($ma->uid, $uids))
				{
					$telefon = $this->KontaktModel->getFirmentelefon($ma->uid);

					if (hasData($telefon))
					{
						$telefon = getData($telefon);
						$ma->firmentelefon_nummer = $telefon['kontakt'];
						$telefonklappe = $telefon['telefonklappe'];
						if (!isEmptyString($telefonklappe))
								$ma->firmentelefon_telefonklappe = $telefonklappe;
					}

					$firmenhandy = $this->KontaktModel->getZustellKontakt($ma->person_id, 'firmenhandy');

					if (hasData($firmenhandy))
					{
						$ma->firmenhandy = getData($firmenhandy)[0]->kontakt;
					}

					$mitarbeiterres[] = $ma;
				}
			}
		}

		return success($mitarbeiterres);
	}

	/**
	 * Saves Kalkulatorischen Stundensatz in fhcomplete if mitarbeiter is present.
	 * @param object $stdsobj Studensatzobject to save.
	 * @return object error or success
	 */
	public function saveKalkStundensatz($stdsobj)
	{
		$result = null;
		$stundensatz = $stdsobj['sap_kalkulatorischer_stundensatz'];

		if (isset($stundensatz['mitarbeiter_uid']))
		{
			$uid = $stundensatz['mitarbeiter_uid'];

			$this->MitarbeiterModel->addSelect('1');
			$maExists = $this->MitarbeiterModel->loadWhere(array('mitarbeiter_uid' => $uid));

			if (hasData($maExists))
			{
				$this->StundensatzModel->addLimit(1);
				$this->StundensatzModel->addOrder('insertamum', 'DESC');
				$stundensatzExists = $this->StundensatzModel->loadWhere(array('mitarbeiter_uid' => $uid));

				$insertSt = false;

				if (isError($stundensatzExists))
					$result = error("Error when querying employee $uid");
				else
				{
					if (hasData($stundensatzExists))
					{
						$stsatz = getData($stundensatzExists)[0]->sap_kalkulatorischer_stundensatz;
						$newstsatz = isset($stundensatz['sap_kalkulatorischer_stundensatz']) ? $stundensatz['sap_kalkulatorischer_stundensatz'] : null;

						if ($stsatz != $newstsatz) // do not save if same Stundensatz already there
						{
							$insertSt = true;
						}
					}
					else
						$insertSt = true;

					if ($insertSt)
					{
						$stdResult = $this->StundensatzModel->insert($stundensatz);

						if (hasData($stdResult))
							$result = success($uid);
						else
							$result = error('Error when inserting kalk. Stundensatz');
					}
					else
						$result = success("Stundensatz for $uid did not need to be inserted.");

				}
			}
			else
				$result = error("Employee $uid does not exist");
		}

		return $result;
	}

	/**
	 * Gets a fhc database nation code for a 3 letters ISO country code.
	 * @param $isocode
	 * @return object
	 */
	public function getNationByIso3Code($isocode)
	{
		$result = null;

		if (isset($isocode))
		{
			$this->NationModel->addSelect('nation_code');
			$result = $this->NationModel->loadWhere(array('iso3166_1_a3' => $isocode));
		}

		return $result;
	}
}
