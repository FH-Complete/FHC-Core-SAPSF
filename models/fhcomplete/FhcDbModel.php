<?php


class FhcDbModel extends DB_Model
{
	const TABLE_PREFIX = 'tbl_';

	public function __construct()
	{
		parent::__construct();

		$this->load->model('person/Benutzer_model', 'BenutzerModel');
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
	public function checkLength($table, $field, $value)
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
	 * Takes care of actions concerting alias for a user.
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
	 * Gets Mitarbeiter with Firmentelefon
	 * @param array $uids filter by uids
	 * @return object
	 */
	public function getMitarbeiter($uids = null)
	{
		$this->load->model('ressource/Mitarbeiter_model', 'MitarbeiterModel');
		$this->load->model('person/Kontakt_model', 'KontaktModel');

		$mitarbeiterres = array();
		$mitarbeiter = $this->MitarbeiterModel->getPersonal(true, null, null);

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
						$telefonno = getData($telefon);
						$ma->firmentelefon = str_replace(' ', '', $telefonno[0]);
					}

					$mitarbeiterres[] = $ma;
				}
			}
		}

		return success($mitarbeiterres);
	}
}
