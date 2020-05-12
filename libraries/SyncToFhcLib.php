<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Contains general logic for syncing from SAPSF to FHC
 */
class SyncToFhcLib
{
	const FROMTIMEZONE = 'UTC'; // timezone on remote server
	const TOTIMEZONE = 'Europe/Vienna'; // local timezone
	const IMPORTUSER = 'SAPSF'; // user for insertion/update

	protected $_conffieldmappings;
	protected $_confvaluedefaults;

	/**
	 * SyncToFhcLib constructor.
	 */
	public function __construct()
	{
		$this->ci =& get_instance();

		$this->ci->config->load('extensions/FHC-Core-SAPSF/fieldmappings');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/valuedefaults');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/fields');

		$this->_conffieldmappings = $this->ci->config->item('fieldmappings');
		$this->_conffieldmappings = $this->_conffieldmappings['fromsapsf'];
		$this->_confvaluedefaults = $this->ci->config->item('fhcdefaults');
		$this->_fhcconffields = $this->ci->config->item('fhcfields');

		$this->ci->load->model('extensions/FHC-Core-SAPSF/fhcomplete/FhcDbModel', 'FhcDbModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Converts an fhc db timestamp to date format in sapsf as passend in query url,
	 * like lastModifiedDateTime Y-m-dTH:i:s
	 * @param $timestamp
	 * @return string
	 */
	public function _convertDateToSAPSF($timestamp)
	{
		date_default_timezone_set(synctofhclib::TOTIMEZONE);

		try
		{
			$datetime = new DateTime($timestamp);
		}
		catch (Exception $e)
		{
			return $timestamp;
		}

		$sftimezone = new DateTimeZone(synctofhclib::FROMTIMEZONE);
		$datetime->setTimezone($sftimezone);
		$timestamptz = $datetime->format('Y-m-d H:i:s');
		return str_replace(' ', 'T', $timestamptz);
	}

	//------------------------------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Checks if fhcomplete object has errors, e.g. missing fields, thus cannot be inserted in db.
	 * @param $fhcobj
	 * @param $objtype
	 * @return StdClass object with properties boolean for has Error and array with errormessages
	 */
	protected function _fhcObjHasError($fhcobj, $objtype, $fhcobjid)
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

					if (isset($fhcobj[$table][$field]))
					{
						$value = $fhcobj[$table][$field];

						if ($required && !is_numeric($value) && isEmptyString($value))
						{
							$haserror = true;
							$errortext = 'is missing';
						}
						else
						{
							// right data type?
							$wrongdatatype = false;
							if (isset($params['type']))
							{
								switch($params['type'])
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
										if (!$this->_validateDate($value))
										{
											$wrongdatatype = true;
										}
										break;
									case 'base64':
										if (!base64_encode(base64_decode($value, true)) === $value)
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
							elseif (!$haserror)
							{
								// right string length?
								if (($params['type'] === 'string' || $params['type'] === 'base64') &&
									!$this->ci->FhcDbModel->checkLength($table, $field, $value))
								{
									$haserror = true;
									$errortext = "is too long ($value)";
								}
								// value referenced with foreign key exists?
								elseif (isset($params['ref']))
								{
									$fkfield = isset($params['reffield']) ? $params['reffield'] : $field;
									$foreignkeyexists = $this->ci->FhcDbModel->valueExists($params['ref'], $fkfield, $value);

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
	 * Converts unix timestamp date from SAPSF to fhc datetime format
	 * @param $unixstr
	 * @return string fhc datetime
	 * @throws Exception
	 */
	protected function _convertDateToFhc($unixstr)
	{
		// extract milliseconds from string
		$millisec = (int)filter_var($unixstr, FILTER_SANITIZE_NUMBER_INT);

		$seconds = $millisec / 1000;
		$datetime = new DateTime("@$seconds");

		$date_time_format = $datetime->format('Y-m-d');

		try
		{
			$fhc_date = new DateTime($date_time_format, new DateTimeZone(self::FROMTIMEZONE));
		}
		catch (Exception $e)
		{
			return $unixstr;
		}

		// Date time with specific timezone
		$fhc_date->setTimezone(new DateTimeZone(self::TOTIMEZONE));
		return $fhc_date->format('Y-m-d');
	}

	/**
	 * Sets timestamp and importuser for insert/update.
	 * @param $modtype
	 * @param $arr
	 */
	protected function _stamp($modtype, &$arr)
	{
		$idx = $modtype . 'amum';
		$arr[$idx] = date('Y-m-d H:i:s', time());
		$idx = $modtype . 'von';
		$arr[$idx] = self::IMPORTUSER;
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Checks if given date exists and is valid.
	 * @param $date
	 * @param string $format
	 * @return bool
	 */
	private function _validateDate($date, $format = 'Y-m-d')
	{
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) === $date;
	}
}
