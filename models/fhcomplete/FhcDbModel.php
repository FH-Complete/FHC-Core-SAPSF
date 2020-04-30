<?php


class FhcDbModel extends DB_Model
{
	const TABLE_PREFIX = 'tbl_';

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Checks if a table column value exists in fhcomplete database
	 * @param $table
	 * @param $field
	 * @param $value
	 * @return mixed
	 */
	public function valueExists($table, $field, $value)
	{
		$query = "SELECT 1 FROM %s WHERE %s = ? LIMIT 1";
		return $this->execQuery(sprintf($query, $table, $field), array($value));
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
}
