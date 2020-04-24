<?php

require_once APPPATH.'models/extensions/FHC-Core-SAPSF/SAPSFClientModel.php';

/**
 * This implements the functionality for executing queries using the SAPSF API
 */
class SAPSFQueryModel extends SAPSFClientModel
{
	private $_entity; // Main entity queried, e.g. User
    private $_mainUriProperties; // Properties of the entity.
    // Is part of the Odata url separated by / before the ? marking the options section.
    // Can be navigation properties leading to another entity or simple properties.
    private $_queryOptions; // params after the ? in the url
    private $_odataQueryString; // whole query string

	private $_hasError;
	private $_errors;

	// query option names
	const FILTEROPTION = 'filter';
	const SELECTOPTION = 'select';
	const ORDERBYOPTION = 'orderby';
	const FORMATOPTION = 'format';

	const DEFAULT_FILTER_CONNECTIONOPERATOR = 'and'; // default connector for multiple filters
    const FILTERVALUE_PLACEHOLDER = '?'; // placeholder for replacement of filter values in url


	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Initialises properties
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_initialiseProperties();
	}

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Initialises a GET call to the api with the url generated based on this models properties.
	 * @return object call result
	 */
	protected function _query()
    {
    	$result = null;
    	$this->_setFormat();
		$this->_generateQueryString();

		if ($this->_hasError())
		{
			$result = error(implode("; ", $this->_errors));
		}
		else
		{
			$result = $this->_call($this->_odataQueryString, SAPSFClientLib::HTTP_GET_METHOD);
		}

		// reset properties
		$this->_initialiseProperties();

    	return $result;
    }

	/**
	 * Sets main Entity queried, optionally a key predicate value (like primary key).
	 * @param string $entityName
	 * @param string|array $keyPredicateValue if associative array, composite key value is set
	 */
    protected function _setEntity($entityName, $keyPredicateValue = null)
    {
    	if ($this->_checkEntityName($entityName))
		{
			// only one entity for each query
    		$this->_entity = array();

    		$this->_entity['name'] = $entityName;
    		if (isset($keyPredicateValue))
			{
				if (is_string($keyPredicateValue))
				{
					$this->_entity['value'] = $keyPredicateValue;
				}
				elseif (is_array($keyPredicateValue)) // composite key
				{
					if (count($keyPredicateValue) >= 2)
					{
						$valid = true;
						foreach ($keyPredicateValue as $name => $value)
						{
							if (!$this->_checkQueryOptionName($name) || !is_string($value))
							{
								$valid = false;
								$this->_setError('Invalid key pedicate provided: (' . $name . ') => ' . $value);
							}
						}

						if ($valid)
							$this->_entity['value'] = $keyPredicateValue;
					}
					else
						$this->_setError('Composite key predicate must have at least two values.');
				}
				else
					$this->_setError('Invalid key pedicate value provided.');
			}
		}
    	else
			$this->_setError('Invalid entity name provided: '.$entityName);
    }

	/**
	 * Sets main uri property,a property of the Entity separated by /.
	 * Can be a navigation or simple property.
	 * @param string $propertyName
	 * @param string $propertyValue
	 */
	protected function _setMainUriProperty($propertyName, $propertyValue = null)
	{
		if ($this->_checkMainUriPropertyName($propertyName))
		{
			$propertyValue = is_string($propertyValue) ? $propertyValue : null;
			$property = array('name' => $propertyName, 'value' => $propertyValue);
			$this->_mainUriProperties[] = $property;
		}
		else
			$this->_setError('Invalid uri property name provided: '.$propertyName);
	}

	/**
	 * Sets multiple uri main properties.
	 * @param array $queryProperties must consist of arrays with ['name'] and ['value'] keys
	 */
	protected function _setMainUriProperties($queryProperties)
	{
		if (is_array($queryProperties))
		{
			foreach ($queryProperties as $queryProperty)
			{
				if (isset($queryProperty['name']) && is_string($queryProperty['name']))
				{
					$value = isset($queryProperty['value']) && is_string($queryProperty['value']) ? $queryProperty['value'] : null;
					$this->_setMainUriProperty($queryProperty['name'], $value);
				}
				else
					$this->_setError('Invalid main uri property provided.');
			}
		}
		else
			$this->_setError('Invalid main uri properties array. Array must contain subarrays with [name] and optionally [value] properties.');
	}

	/**
	 * Sets a single select option.
	 * @param string $selectProperty
	 */
	protected function _setSelect($selectProperty)
	{
		if ($this->_checkQueryOptionName($selectProperty))
			$this->_setQueryOption(self::SELECTOPTION, $selectProperty);
		else
			$this->_setError('Invalid select property provided: '.$selectProperty);
	}

	/**
	 * Sets multiple select options.
	 * @param array $selectProperties
	 */
	protected function _setSelects($selectProperties)
	{
		if (is_array($selectProperties))
		{
			foreach ($selectProperties as $selectProperty)
			{
				$this->_setSelect($selectProperty);
			}
		}
		else
			$this->_setError('Invalid select properties provided');
	}

	/**
	 * Sets a single filter option.
	 * @param string $filterName
	 * @param string|array $filterValue if array, logical operator 'in' is used with multiple values
	 * @param string $logicalOperator
	 */
    protected function _setFilter($filterName, $filterValue, $logicalOperator = 'eq')
    {
    	if ($this->_checkFilter($filterName, $filterValue, $logicalOperator))
		{
        	$this->_setQueryOption(self::FILTEROPTION, array('filters' => array(array('name' => $filterName, 'value' => $filterValue, 'operator' => $logicalOperator))));
		}
    }

	/**
	 * Sets multiple filter options.
	 * @param array $filterProperties must consist of arrays with ['name'] and ['value'] and ['operator']  keys
	 * @param string $logicalConnectionOperator connection between the filters (and/or)
	 */
	protected function _setFilters($filterProperties, $logicalConnectionOperator = self::DEFAULT_FILTER_CONNECTIONOPERATOR)
	{
		if (is_array($filterProperties))
		{
			if ($this->_checkLogicalConnectionOperator($logicalConnectionOperator))
			{
				$filters = array('filters' => array(), 'connectionOperator' => $logicalConnectionOperator);
				foreach ($filterProperties as $filterProperty)
				{
					if (is_array($filterProperty))
					{
						if (isset($filterProperty['name']) && isset($filterProperty['value']) && isset($filterProperty['operator']))
						{
							if ($this->_checkFilter($filterProperty['name'], $filterProperty['value'], $filterProperty['operator']))
								$filters['filters'][] = array('name' => $filterProperty['name'], 'value' => $filterProperty['value'], 'operator' => $filterProperty['operator']);
						}
						else
							$this->_setError('Filtername, value or operator not provided. Array must contain [name], [value] and [operator] properties.');
					}
					else
					{
						$this->_setError('Invalid filter property provided: ' . $filterProperty);
					}
				}
				$this->_setQueryOption(self::FILTEROPTION, $filters);
			}
			else
				$this->_setError('Invalid logical connection operator provided: ' . $logicalConnectionOperator);
		}
		else
			$this->_setError('Invalid filter properties provided');
	}

	/**
	 * Sets a custom filter string (for more complex queries).
	 * @param string $filterString must contain placeholder characters to be replaced with filter values
	 * @param array $filterValues
	 */
	protected function _setFilterString($filterString, $filterValues = array())
	{
		if ($this->_checkFilterString($filterString) && is_array($filterValues))
		{
			$resultFilterString = $filterString;
			foreach ($filterValues as $val)
			{
				$pos = strpos($filterString, self::FILTERVALUE_PLACEHOLDER);
				if (!$pos)
				{
					$this->_setError('Property placeholders number in filter string do not match provided filter Values.');
					return;
				}

				if (is_string($val) || is_numeric($val))
				{
					$val = $this->_encodeFilterValue($val);
				}
				else
				{
					$this->_setError('Invalid filter value provided');
					return;
				}
				$resultFilterString = substr_replace($filterString, $val, $pos, 1);
			}
			$this->_setQueryOption(self::FILTEROPTION, $resultFilterString);
		}
		else
			$this->_setError('Invalid filter string or invalid filter values provided. Filter values in the string should be replaced by ? and it shouldn\'t contain any unallowed special characters.');
	}

	/**
	 * Sets single order by option.
	 * @param string $orderbyProperty
	 * @param string $order sort order
	 */
	protected function _setOrderBy($orderbyProperty, $order = 'asc')
	{
		if ($this->_checkQueryOptionName($orderbyProperty))
		{
			$order = is_string($order) ? $order : 'asc';
			$this->_setQueryOption(self::ORDERBYOPTION, array('name' => $orderbyProperty, 'order' => $order));
		}
		else
			$this->_setError('Invalid orderby property provided: '.$orderbyProperty);
	}

	/**
	 * Sets multiple order by options.
	 * @param array $orderbyProperties must consist of arrays with ['name'] and optionally ['order']  keys
	 */
	protected function _setOrderBys($orderbyProperties)
    {
    	if (is_array($orderbyProperties))
		{
			foreach ($orderbyProperties as $orderbyProperty)
			{
				if (is_array($orderbyProperty))
				{
					if (isset($orderbyProperty['name']))
					{
						if (isset($orderbyProperty['order']))
							$this->_setOrderBy($orderbyProperty['name'], $orderbyProperty['order']);
						else
							$this->_setOrderBy($orderbyProperty['name']);
					}
					else
						$this->_setError('Invalid orderby properties array. Array must contain [name] and optionally [order] (asc or desc) properties.');
				}
				else
				{
					$this->_setOrderBy($orderbyProperty);
				}
			}
		}
		else
			$this->_setError('Invalid orderby properties provided');
    }

	/**
	 * Sets filter so that only employees modified after a certain date are retrieved from sapsf.
	 * @param $lastModifiedDateTime
	 */
	protected function _setLastModifiedDateTime($lastModifiedDateTime)
	{
		if (isset($lastModifiedDateTime))
			$this->_setFilterString("lastModifiedDateTime gt datetime?", array($lastModifiedDateTime));
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * (re)sets the properties to their initial state.
	 */
	private function _initialiseProperties()
	{
		$this->_entity = array();
		$this->_mainUriProperties = array();
		$this->_queryOptions = array();
		$this->_odataQueryString = '';
		$this->_hasError = false;
		$this->_errors = array();
	}

	/**
	 * Set data format option.
	 */
	private function _setFormat()
	{
		if (!isset($this->_queryOptions[self::FORMATOPTION]))
			$this->_setQueryOption(self::FORMATOPTION, SAPSFClientModel::DATAFORMAT);
	}

	/**
	 * Set a query option
	 * @param string $name
	 * @param mixed $value
	 */
	private function _setQueryOption($name, $value)
	{
		if (!isset($this->_queryOptions[$name]))
			$this->_queryOptions[$name] = array();
		$this->_queryOptions[$name][] = $value;
	}

	/**
	 * Generates the query string for the query call out of the set Entity, mainuriproperties and queryoptions.
	 * Takes care about url encoding.
	 */
	private function _generateQueryString()
    {
    	$queryString = '';
		if (isset($this->_entity) && isset($this->_entity['name']) && !isEmptyArray($this->_entity))
		{
			$queryString .= $this->_entity['name'];
			if (isset($this->_entity['value']))
			{
				$entityvalue = $this->_entity['value'];
				if (is_string($entityvalue))
					$queryString .= "('" . $this->_encodeForOdata($entityvalue) . "')";
				elseif (is_array($entityvalue))
				{
					$queryString .= '(';
					$first = true;
					foreach ($entityvalue as $predname => $predvalue)
					{
						if (!$first)
							$queryString .= ',';
						$queryString .= $predname . "='" . $this->_encodeForOdata($predvalue) . "'";
						$first = false;
					}
					$queryString .= ')';
				}
			}

			if (!isEmptyArray($this->_mainUriProperties))
			{
				foreach ($this->_mainUriProperties as $mainUriProperty)
				{
					if (isset($mainUriProperty['name']))
					{
						$queryString .= '/' . $mainUriProperty['name'];
						if (isset($mainUriProperty['value']))
						{
							$queryString .= "('" . $this->_encodeForOdata($mainUriProperty['value']) . "')";
						}
					}
				}
			}

			$firstQueryOptions = true;
			if (!isEmptyArray($this->_queryOptions))
			{
				foreach ($this->_queryOptions as $optionname => $queryOptions)
				{
					if (isEmptyArray($queryOptions))
						continue;

					$queryString .= $firstQueryOptions ? '?' : '&';

					switch($optionname)
					{
						case self::SELECTOPTION:
							$first = true;
							foreach ($queryOptions as $selectOption)
							{
								if ($first)
									$queryString .= '$' . self::SELECTOPTION . '=';
								else
									$queryString .= ',';
								$queryString .= $selectOption;
								$first = false;
							}
						break;
						case self::FILTEROPTION:
							$filteroptions = array();
							foreach ($queryOptions as $filter)
							{
								if (is_string($filter))
								{
									$filteroptions[] = $filter;
								}
								else
								{
									if (isset($filter['filters']) && is_array($filter['filters']) &&
										!(count($filter['filters']) > 1 && !isset($filter['connectionOperator'])))
									{
										$fiStr = '';
										$firstfil = true;
										foreach ($filter['filters'] as $fil)
										{
											if (!$firstfil)
												$fiStr .= ' ' . $filter['connectionOperator'] . ' ';
											$fiStr .= $fil['name'] . ' '. $fil['operator'] . ' ';

											if (is_array($fil['value']))
											{
												foreach ($fil['value'] as $idx => $val)
												{
													$fiStr .= $this->_encodeFilterValue($val);
													if ($idx !== count($fil['value']) - 1)
														$fiStr .= ',';
												}
											}
											else
												$fiStr .= $this->_encodeFilterValue($fil['value']);

											$firstfil = false;
										}
										$filteroptions[] = $fiStr;
									}
								}
							}
							$nofilteroptions = count($filteroptions);
							if ($nofilteroptions > 0)
							{
								$queryString .= '$' . self::FILTEROPTION . '=';
								if ($nofilteroptions == 1)
									$queryString .= $filteroptions[0];
								else
								{
									$firstfi = true;
									foreach ($filteroptions as $filteroption)
									{
										if (!$firstfi)
											$queryString .= ' ' . self::DEFAULT_FILTER_CONNECTIONOPERATOR . ' ';
										$queryString .= '(' . $filteroption . ')';
										$firstfi = false;
									}
								}
							}

							break;
						case self::ORDERBYOPTION:
							$first = true;
							foreach ($queryOptions as $orderbyOption)
							{
								if (isset($orderbyOption['name']))
								{
									if ($first)
										$queryString .= '$' . self::ORDERBYOPTION . '=';
									else
										$queryString .= ',';
									$queryString .= $orderbyOption['name'];
									if (isset($orderbyOption['order']))
										$queryString .= ' '.$orderbyOption['order'];
									$first = false;
								}
							}
							break;
						case self::FORMATOPTION:
							$queryString .= '$' . self::FORMATOPTION . '=' . $queryOptions[0];
					}
					$firstQueryOptions = false;
				}
			}
			$this->_setOdataQueryString($queryString);
		}
		else
			$this->_setError('Entity not set');
    }

	/**
	 * Sets the odata query string property.
	 * @param string $odataQueryString
	 */
	private function _setOdataQueryString($odataQueryString)
	{
		$this->_odataQueryString = $odataQueryString;
	}

	/**
	 * Checks an entity name for its validity.
	 * @param string $entityName
	 * @return bool
	 */
	private function _checkEntityName($entityName)
	{
		return is_string($entityName) && preg_match('/^[a-zA-Z0-9_]+$/', $entityName) === 1;
	}

	/**
	 * Checks a main uri property name for its validity.
	 * @param string $propertyName
	 * @return bool
	 */
	private function _checkMainUriPropertyName($propertyName)
	{
		//can begin with $ if its a special property like $value
		return is_string($propertyName) && preg_match('/^\$?[a-zA-Z0-9_]+$/', $propertyName) === 1;
	}

	/**
	 * Checks a query option name for its validity.
	 * @param string $optionName
	 * @return bool
	 */
    private function _checkQueryOptionName($optionName)
	{
		//can have a / if it's a navigation property
		return is_string($optionName) && preg_match('/^[a-zA-Z0-9_\/]+$/', $optionName) === 1;
	}

	/**
	 * Checks a filter for its validity.
	 * @param string $filterName
	 * @param string $logicalOperator
	 * @return bool
	 */
	private function _checkFilter($filterName, $filterValue, $logicalOperator)
	{
		$valid = true;

		if (!$this->_checkQueryOptionName($filterName))
		{
			$this->_setError('Invalid filter property provided: '.$filterName);
			$valid = false;
		}
		elseif (!$this->_checkLogicalOperator($logicalOperator))
		{
			$this->_setError('Invalid filter logical operator provided: '.$logicalOperator);
			$valid = false;
		}
		elseif ((!is_string($filterValue) && !is_array($filterValue)))
		{
			$this->_setError('Invalid filter value');
			$valid = false;
		}
		elseif ((is_array($filterValue) && $logicalOperator !== 'in') || (!is_array($filterValue) && $logicalOperator === 'in'))
		{
			$this->_setError('Logical operator \'in\' requires filter given as array');
			$valid = false;
		}

		return $valid;
	}

	/**
	 * Checks a filter string for its validity.
	 * @param string $filterString
	 * @return bool
	 */
	private function _checkFilterString($filterString)
	{
		return is_string($filterString) && preg_match('/^[a-zA-Z0-9\s?()]+$/', $filterString) === 1;
	}

	/**
	 * Checks a logical operator for its validity.
	 * @param string $logicalOperator
	 * @return bool
	 */
	private function _checkLogicalOperator($logicalOperator)
	{
		return is_string($logicalOperator) && preg_match('/^[a-z]{2}$/', $logicalOperator);
	}

	/**
	 * Checks a logical connection operator for its validity.
	 * @param string $logicalOperator
	 * @return bool
	 */
	private function _checkLogicalConnectionOperator($logicalOperator)
	{
		return is_string($logicalOperator) && preg_match('/^(or|and)$/', $logicalOperator);
	}

	/**
	 * Encodes a string for odata querying.
	 * @param string $str
	 * @return string
	 */
	private function _encodeForOdata($str)
	{
		//replace apostroph with two apostrophs for escaping
		return urlencode(str_replace("'", "''", $str));
	}

	/**
	 * Encodes a filter option value for odata querying.
	 * @param string $val
	 * @return int|string
	 */
	private function _encodeFilterValue($val)
	{
		return is_integer($val) ? $val : "'" . $this->_encodeForOdata($val) . "'";
	}

	/**
	 * Checks if an error has occured during querying.
	 * @return bool
	 */
    private function _hasError()
	{
		return $this->_hasError;
	}

	/**
	 * Sets an error.
	 * @param string $errormsg
	 */
    private function _setError($errormsg)
	{
		$this->_errors[] = 'QUERYBUILD_ERROR: '.$errormsg;
		$this->_hasError = true;
	}
}
