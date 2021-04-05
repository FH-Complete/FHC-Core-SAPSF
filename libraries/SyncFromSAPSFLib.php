<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Contains general logic for syncing from SAPSF to FHC
 */
class SyncFromSAPSFLib
{
	const FROMTIMEZONE = 'UTC'; // timezone on remote server
	const TOTIMEZONE = 'Europe/Vienna'; // local timezone

	protected $_syncpreview; // if false, sync, otherwise only output

	protected $_convertfunctions;

	protected $_conffieldmappings;
	protected $_confvaluedefaults;
	protected $_confvaluemappings;
	protected $_sapsflastmodifiedfields;
	protected $_sapsfstartdatefields;
	protected $_sapsftypetimebasedfields;

	/**
	 * SyncFromSAPSFLib constructor.
	 */
	public function __construct()
	{
		$this->ci =& get_instance();

		// load helper
		$this->ci->load->helper('extensions/FHC-Core-SAPSF/sync_helper');

		// load config
		$this->ci->config->load('extensions/FHC-Core-SAPSF/SAPSFSyncparams');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/fieldmappings');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/valuemappings');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/valuedefaults');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/fields');

		$this->_syncpreview = $this->ci->config->item('FHC-Core-SAPSFSyncparams')['syncpreview'];
		$conffieldmappings = $this->ci->config->item('fieldmappings');
		$this->_conffieldmappings = $conffieldmappings['fromsapsf'];
		$confvaluemappings = $this->ci->config->item('valuemappings');
		$this->_confvaluemappings = $confvaluemappings['fromsapsf'];
		$this->_confvaluedefaults = $this->ci->config->item('fhcdefaults');
		$this->_sapsfvaluedefaults = $this->ci->config->item('sapsfdefaults');
		$this->_sapsflastmodifiedfields = $this->ci->config->item('sapsflastmodifiedfields');
		$this->_sapsfstartdatefields = $this->ci->config->item('sapsfstartdatefields');
		$this->_sapsftypetimebasedfields = $this->ci->config->item('sapsftypetimebasedfields');
		$this->_timebasedfieldexceptions = $this->ci->config->item('timebasedfieldexceptions');
		$this->_synccostcentereventtypeexceptions = $this->ci->config->item('synccostcentereventtypeexceptions');

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
	 * Gets SAPSF to FHC fieldmappings.
	 * @param string $sapsfobj SAPSF objecttype
	 * @param string $fhctable
	 * @param array $fhcvaluenames
	 * @return array
	 */
	public function getFieldMappings($sapsfobj, $fhctable, $fhcvaluenames = array())
	{
		$valuenames = array();

		if (isset($this->_conffieldmappings[$sapsfobj][$fhctable]))
		{
			foreach ($this->_conffieldmappings[$sapsfobj][$fhctable] as $sapsfname => $fhcname)
			{
				if (is_array($fhcname))
				{
					foreach ($fhcname as $name)
					{
						if (in_array($name, $fhcvaluenames))
							$valuenames[$name] = $sapsfname;
					}
				}
				elseif (in_array($fhcname, $fhcvaluenames))
					$valuenames[$fhcname] = $sapsfname;
			}
		}

		return $valuenames;
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
									if ($noValues == 1) // if only one result, navigate into it. Otherwise choose last based on date.
										$value = $value->results[0]->{$props[$i]};
									elseif (isset($value->results[0]->startDate))
									{
										$navtypefield = isset($this->_sapsftypetimebasedfields[$navfield]) ? $this->_sapsftypetimebasedfields[$navfield] : null;

										if (isset($navtypefield))
										{
											$value = $this->_extractLastResultByType($value->results, $props[$i], $navtypefield);
										}
										else
										{
											$value = $this->_extractLastResult($value->results, $props[$i]);
										}
									}
									elseif (isset($value->results[0]->{$props[$i]}->results)) // in case of nested results
									{
										$gatheredResults = array();

										foreach ($value->results as $topResult) // gather all the results
										{
											foreach ($topResult->{$props[$i]}->results as $bottomResult)
											{
												$gatheredResults[] = $bottomResult;
											}
										}
										$value = new stdClass();
										$value->results = $gatheredResults;
									}
								}
							}

							// $value can contain finite non-timebased field or results array.
							if (isset($value->{$field}))
								$sfvalue = $value->{$field};
							elseif (isset($value->results[0]) && property_exists($value->results[0], $field)) // if value has results array with the field
							{
								if (count($value->results) == 1) // take first result
									$sfvalue = $value->results[0]->{$field};
								elseif (isset($value->results[0]->startDate) && !in_array($sffield, $this->_timebasedfieldexceptions)) // if results are time-based
								{
									// take only chronologically last results for each type
									$typefield = isset($this->_sapsftypetimebasedfields[$sffield]) ? $this->_sapsftypetimebasedfields[$sffield] : null;
									$sfvalue = $this->_extractLastResultByType($value->results, $field, $typefield);
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
							elseif (is_array($value) && isset($value[0]->{$field}))
							{
								foreach ($value as $val)
								{
									$sfvalue[] = $val->{$field};
								}
							}
						}
					}

					// set values from valuemappings
					if (is_array($sfvalue))
					{
						foreach ($sfvalue as $idx => $sfval)
						{
							if (isset($this->_confvaluemappings[$objtype][$fhctable][$fhcfield][$sfval]))
							{
								$sfvalue[$idx] = $this->_confvaluemappings[$objtype][$fhctable][$fhcfield][$sfval];
							}
						}
					}
					elseif (isset($this->_confvaluemappings[$objtype][$fhctable][$fhcfield][$sfvalue]))
					{
						$sfvalue = $this->_confvaluemappings[$objtype][$fhctable][$fhcfield][$sfvalue];
					}

					// set sapsf value if present
					if (isset($sfvalue))
						$fhcemployee[$fhctable][$fhcfield] = $sfvalue;

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
								// property of SAPSF object with given "table" and name is passed as function param
								if (isset($param['table']) && isset($param['name']) && isset($fhcemployee[$param['table']][$param['name']]))
								{
									$paramtbl = $param['table'];
									$paramname = $param['name'];
									$params[$paramname] = $fhcemployee[$paramtbl][$paramname];
									unset($param['table']);
									unset($param['name']);

									// miscellaneous parameters passed to function
									foreach ($param as $miscname => $misc)
									{
										$params['misc'][$paramtbl][$miscname] = $misc;
									}
								}
							}
						}

						$funcresult = $this->{$this->_convertfunctions[$fhctable][$fhcfield]['function']}(
							$sfvalue,
							$params
						);

						// if function result is null, set only if there is no default value already.
						if ($funcresult == null)
						{
							if (!isset($this->_confvaluedefaults[$objtype][$fhctable]) ||
								!array_key_exists($fhcfield, $this->_confvaluedefaults[$objtype][$fhctable]))
								unset($fhcemployee[$fhctable][$fhcfield]);
							elseif ($this->_confvaluedefaults[$objtype][$fhctable][$fhcfield] === '')
								$fhcemployee[$fhctable][$fhcfield] = '';
							else
								$fhcemployee[$fhctable][$fhcfield] = $funcresult;
						}
						else
							$fhcemployee[$fhctable][$fhcfield] = $funcresult;
					}
				}
			}
		}

		return $fhcemployee;
	}

	/**
	 * Converts unix timestamp date from SAPSF to fhc datetime format
	 * @param $unixstr
	 * @return string fhc datetime
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

		$fhc_date_str = $fhc_date->format('Y-m-d');

		return substr($fhc_date_str, 0, 4) == '9999' ? null : $fhc_date_str; // if starting with 9999, infinite validity
	}

	/**
	 * Converts multiple unix timestamp dates from SAPSF to fhc datetime format
	 * @param $dates as unixstrings if given as string, it is converted as one date
	 * @return array fhc datetimes
	 */
	protected function _convertDatesToFhc($dates)
	{
		$results = array();

		if (is_string($dates))
			$results[] = $this->_convertDateToFhc($dates);
		elseif (is_array($dates))
		{
			foreach ($dates as $date)
			{
				$results[] = $this->_convertDateToFhc($date);
			}
		}

		return $results;
	}

	/**
	 * Converts an SAPSF nation to fhc format.
	 * @param $sfnation
	 * @return string
	 */
	protected function _convertNationToFhc($sfnation)
	{
		$fhcnation = null;
		$nation_code = $this->ci->FhcDbModel->getNationByIso3Code($sfnation);

		if (hasData($nation_code))
			$fhcnation = getData($nation_code)[0]->nation_code;
		else
			$fhcnation = $this->_confvaluedefaults['User']['person']['staatsbuergerschaft'];

		return $fhcnation;
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Extracts the chronologically last result from an array of time-based objects,
	 * based on startDate property.
	 * @param $results array
	 * @param $property string the property to extract
	 * @param $typefield
	 * @return mixed the extracted property data
	 */
	private function _extractLastResult($results, $property)
	{
		$max = (int)filter_var($results[0]->startDate, FILTER_SANITIZE_NUMBER_INT);
		$maxObj = $results[0]->{$property};
		foreach ($results as $result)
		{
			$millisec = (int)filter_var($result->startDate, FILTER_SANITIZE_NUMBER_INT);
			if ($millisec > $max)
			{
				$maxObj = $result->{$property};
				$max = $millisec;
			}
		}
		return $maxObj;
	}

	/**
	 * Extracts the chronologically last result from an array of time-based objects,
	 * based on startDate property for each result type.
	 * @param $results array
	 * @param $property string the property to extract
	 * @param $typeproperty the property indicating the result type
	 * @return mixed the extracted property data, one result for each type.
	 */
	private function _extractLastResultByType($results, $property, $typeproperty)
	{
		if (!isset($typeproperty))
			return $this->_extractLastResult($results, $property);

		$typeResults = array();
		$firstResults = array();

		foreach ($results as $result)
		{
			if (isset($result->{$typeproperty}))
			{
				$typeResults[$result->{$typeproperty}][] = $result;
			}
		}

		foreach ($typeResults as $typeResult)
		{
			$firstResults[] = $this->_extractLastResult($typeResult, $property);
		}

		return $firstResults;
	}
}
