<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Contains general logic for syncing from SAPSF to FHC
 */
class SyncFromSAPSFLib
{
	const FROMTIMEZONE = 'UTC'; // timezone on remote server
	const TOTIMEZONE = 'Europe/Vienna'; // local timezone
	const IMPORTUSER = 'SAPSF'; // user for insertion/update

	const UNKNOWN_NATION_CODE = 'XXX';

	protected $_convertfunctions;

	protected $_conffieldmappings;
	protected $_confvaluedefaults;
	protected $_confvaluemappings;
	protected $_fhcconffields;
	protected $_sapsflastmodifiedfields;
	protected $_sapsfnontimebasedfields;

	/**
	 * SyncFromSAPSFLib constructor.
	 */
	public function __construct()
	{
		$this->ci =& get_instance();

		// load helper
		$this->ci->load->helper('extensions/FHC-Core-SAPSF/sync_helper');

		// load config
		$this->ci->config->load('extensions/FHC-Core-SAPSF/fieldmappings/fieldmappings');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/fieldmappings/valuemappings');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/fieldmappings/valuedefaults');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/fieldmappings/fields');

		$this->_conffieldmappings = $this->ci->config->item('fieldmappings');
		$this->_conffieldmappings = $this->_conffieldmappings['fromsapsf'];
		$this->_confvaluemappings = $this->ci->config->item('valuemappings');
		$this->_confvaluemappings = $this->_confvaluemappings['fromsapsf'];
		$this->_confvaluedefaults = $this->ci->config->item('fhcdefaults');
		$this->_sapsfvaluedefaults = $this->ci->config->item('sapsfdefaults');
		$this->_fhcconffields = $this->ci->config->item('fhcfields');
		$this->_sapsflastmodifiedfields = $this->ci->config->item('sapsflastmodifiedfields');
		$this->_sapsfnontimebasedfields = $this->ci->config->item('sapsfnontimebasedfields');

		// load models
		$this->ci->load->model('extensions/FHC-Core-SAPSF/fhcomplete/FhcDbModel', 'FhcDbModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Converts an fhc db timestamp to date format in sapsf as passend in query url,
	 * like lastModifiedDateTime Y-m-dTH:i:s
	 * @param string $timestamp fhc timestamp Y-m-d
	 * @return string
	 */
	public function convertDateToSAPSF($timestamp)
	{
		date_default_timezone_set(SyncFromSAPSFLib::TOTIMEZONE);

		try
		{
			$datetime = new DateTime($timestamp);
		}
		catch (Exception $e)
		{
			return $timestamp;
		}

		$sftimezone = new DateTimeZone(SyncFromSAPSFLib::FROMTIMEZONE);
		$datetime->setTimezone($sftimezone);
		$timestamptz = $datetime->format('Y-m-d H:i:s');
		return str_replace(' ', 'T', $timestamptz);
	}

	/**
	 * Gets sapsf select properties from field mappings config.
	 * @param $sapsfobj
	 * @return array
	 */
	public function getSelectsFromFieldMappings($sapsfobj)
	{
		$selects = array();
		foreach ($this->_conffieldmappings[$sapsfobj] as $mappingset)
		{
			foreach ($mappingset as $sapsfkey => $fhcvalue)
			{
				if (strpos($sapsfkey, '/')) // if navigation property
				{
					$select = substr($sapsfkey, 0, strrpos($sapsfkey, '/'));
				}
				else
					$select = $sapsfkey;

				if (!in_array($select, $selects))
				{
					$selects[] = $select;
				}
			}
		}
		return $selects;
	}

	/**
	 * Gets sapsf expand properties from field mappings config.
	 * @param $sapsfobj
	 * @return array
	 */
	public function getExpandsFromFieldMappings($sapsfobj)
	{
		$expands = array();
		foreach ($this->_conffieldmappings[$sapsfobj] as $mappingset)
		{
			foreach ($mappingset as $sapsfkey => $fhcvalue)
			{
				if (strpos($sapsfkey, '/')) // if navigation property
				{
					$navprop = substr($sapsfkey, 0, strrpos($sapsfkey, '/'));
					$found = false;

					for ($i = 0; $i < count($expands); $i++)
					{
						if (strpos($expands[$i], $navprop) !== false)
						{
							$found = true;
							break;
						}
						elseif (strpos($navprop, $expands[$i]) !== false)
						{
							$found = true;
							$expands[$i] = $navprop;
							break;
						}
					}

					if (!$found)
						$expands[] = $navprop;
				}
			}
		}
		return $expands;
	}

	/**
	 * Get properties which should be checked for lastModifiedDate from config.
	 * @return array
	 */
	public function getLastModifiedDateTimeProps()
	{
		return $this->_sapsflastmodifiedfields;
	}

	//------------------------------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Converts object from SAPSF to save in the fhc database.
	 * @param $sapsfobj
	 * @param $objtype
	 * @return array converted employee
	 */
	protected function _convertSapsfObjToFhc($sapsfobj, $objtype)
	{
		$fhctables = array_keys($this->_conffieldmappings[$objtype]);
		$fhcemployee = array();

		foreach ($fhctables as $fhctable)
		{
			// prefill with value defaults
			if (isset($this->_confvaluedefaults[$objtype][$fhctable]))
			{
				foreach ($this->_confvaluedefaults[$objtype][$fhctable] as $fhcfield => $fhcdefault)
				{
					$fhcemployee[$fhctable][$fhcfield] = $fhcdefault;
				}
			}

			// any property in fieldmappings is synced
			foreach ($this->_conffieldmappings[$objtype][$fhctable] as $sffield => $fhcfields)
			{
				$fhcfields = is_array($fhcfields) ? $fhcfields : array($fhcfields);

				foreach ($fhcfields as $fhcfield)
				{
					$sfvalue = null;
					if (isset($sapsfobj->{$sffield}) /*&& !isEmptyString($employee->{$sffield})*/)
					{
						$sfvalue = $sapsfobj->{$sffield};
					}
					elseif (strpos($sffield, '/'))
					{
						// if navigation property, navigate to value needed
						$navfield = substr($sffield, 0, strrpos($sffield, '/'));
						$field = substr($sffield, strrpos($sffield, '/') + 1, strlen($sffield));
						$props = explode('/', $navfield);

						if (isset($sapsfobj->{$props[0]}))
						{
							$value = $sapsfobj->{$props[0]};

							for ($i = 1; $i < count($props); $i++)
							{
								if (isset($value->{$props[$i]}))
								{
									$value = $value->{$props[$i]};
								}
								// navigate further if value has results array instead of a finite value
								elseif (isset($value->results[0]->{$props[$i]}))
								{
									$noValues = count($value->results);
									if ($noValues == 1) // if only one result, navigate into it. Otherwise choose first based on date.
										$value = $value->results[0]->{$props[$i]};
									elseif (isset($value->results[0]->startDate))
									{
										$value = $this->_extractFirstResult($value, $props[$i]);
									}
								}
							}

							if (isset($value->{$field}))
								$sfvalue = $value->{$field};
							elseif (isset($value->results[0]) && property_exists($value->results[0], $field)) // if value has results array with the field
							{
								if (count($value->results) == 1) // take first result
									$sfvalue = $value->results[0]->{$field};
								elseif (isset($value->results[0]->startDate) && !in_array($field, $this->_sapsfnontimebasedfields)) // if results are time-based
								{
									$sfvalue = $this->_extractFirstResult($value, $field);
								}
								else // or take all results
								{
									$sfvalue = array();
									foreach ($value->results as $result)
									{
										$sfvalue[] = $result->{$field};
									}
								}
							}
						}
					}

					// set sapsf value if it is not null
					if (isset($sfvalue))
						$fhcemployee[$fhctable][$fhcfield] = $sfvalue;

					// check if there is a valuemapping
					$mapped = null;
					if (!is_array($sfvalue) && isset($this->_confvaluemappings[$objtype][$fhctable][$fhcfield][$sfvalue]))
					{
						$mapped = $this->_confvaluemappings[$objtype][$fhctable][$fhcfield][$sfvalue];
						$fhcemployee[$fhctable][$fhcfield] = $mapped;
					}

					// check for convertfunctions, execute with passed extra parameters if found
					if (isset($this->_convertfunctions[$fhctable][$fhcfield]))
					{
						$params = array();
						if (isset($this->_convertfunctions[$fhctable][$fhcfield]['extraParams']) &&
							is_array($this->_convertfunctions[$fhctable][$fhcfield]['extraParams']))
						{
							$allparams = $this->_convertfunctions[$fhctable][$fhcfield]['extraParams'];

							foreach ($allparams as $param)
							{
								if (isset($param['table']) && isset($param['name']) && isset($fhcemployee[$param['table']][$param['name']]))
								{
									$params[$param['name']] = $fhcemployee[$param['table']][$param['name']];
									if (isset($param['fhcfield']))
										$params['fhcfield'] = $param['fhcfield'];
								}
							}
						}

						$funcval = isset($mapped) ? $mapped : $sfvalue;
						$funcresult = $this->{$this->_convertfunctions[$fhctable][$fhcfield]['function']}(
							$funcval,
							$params
						);

						// if there is a null function result set only if null/empty values are permitted
						if ($funcresult == null && (!isset($this->_confvaluedefaults[$objtype][$fhctable]) ||
														!array_key_exists($fhcfield, $this->_confvaluedefaults[$objtype][$fhctable]) ||
														!isEmptyString($this->_confvaluedefaults[$objtype][$fhctable][$fhcfield])))
							unset($fhcemployee[$fhctable][$fhcfield]);
						else
							$fhcemployee[$fhctable][$fhcfield] =  $funcresult;
					}
				}
			}
		}

		return $fhcemployee;
	}
	
	/**
	 * Checks if fhcomplete object has errors, e.g. missing fields, thus cannot be inserted in db.
	 * @param $fhcobj
	 * @param $objtype
	 * @param $fhcobjidname
	 * @param $fhcobjid
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

						if ($required && (!isset($value) || $value === ''))
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
										if (!validateDateFormat($value))
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
							// right string length?
							$rightlength = true;
							if (!$haserror && ($params['type'] === 'string' || $params['type'] === 'base64'))
							{
								$rightlength = $this->ci->FhcDbModel->checkStrLength($table, $field, $value);;

								if ($rightlength && isset($params['length']) && is_numeric($params['length']))
									$rightlength = $params['length'] == strlen($value);
							}
							elseif ($params['type'] === 'integer' && is_integer($value))
							{
								$rightlength = $this->ci->FhcDbModel->checkIntLength($value);
							}

							if (!$rightlength)
							{
								$haserror = true;
								$errortext = "has wrong length ($value)";
							}

							// unique constraint violated?
							if (!$haserror && isset($params['unique']) && $params['unique'] === true && isset($params['pk']))
							{
								$exceptions = isset($fhcobj[$table][$params['pk']]) ? array($params['pk'] => $fhcobj[$table][$params['pk']]) : null;
								$valueexists = $this->ci->FhcDbModel->valueExists($table, $field, $value, $exceptions);

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
								$foreignkeyexists = $this->ci->FhcDbModel->valueExists($params['ref'], $fkfield, $value);

								if (!hasData($foreignkeyexists))
								{
									$haserror = true;
									$errortext = 'has no match in FHC';
								}
							}
						}
					}
					else
					{
						if ($required)
						{
							$haserror = true;
							$errortext = 'is missing';
						}
						elseif (isset($params['notnull']) && $params['notnull'] === true)
						{
							// notnull constraint violated
							$haserror = true;
							$errortext = "cannot be null";
						}
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
		if (isEmptyString($unixstr))
			return null;

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
	 * Converts an SAPSF nation to fhc format.
	 * @param $sfnation
	 * @return string
	 */
	protected function _convertNationToFhc($sfnation)
	{
		$nation_code = $this->ci->FhcDbModel->getNationByIso3Code($sfnation);

		if (hasData($nation_code))
			return getData($nation_code)[0]->nation_code;
		else
			return self::UNKNOWN_NATION_CODE;// TODO maybe not hardcoded...
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
	 * Extracts the chronologically first result from an array of time-based objects,
	 * based on startDate property.
	 * @param $results
	 * @param $property the property to extract
	 * @return mixed the extracted property data
	 */
	private function _extractFirstResult($results, $property)
	{
		$min = (int)filter_var($results->results[0]->startDate, FILTER_SANITIZE_NUMBER_INT);
		$minObj = $results->results[0]->{$property};
		foreach ($results->results as $result)
		{
			$millisec = (int)filter_var($result->startDate, FILTER_SANITIZE_NUMBER_INT);
			if ($millisec < $min)
			{
				$minObj = $result->{$property};
				$min = $millisec;
			}
		}
		return $minObj;
	}
}
