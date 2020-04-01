<?php

require_once APPPATH.'models/extensions/FHC-Core-SAPSF/SAPSFClientModel.php';

/**
 * This implements the basic parameters to call all the API in SAPCoreAPI
 */
class SAPSFQueryModel extends SAPSFClientModel
{
    private $_entity;//Main entity queried, e.g. User
    private $_mainUriProperties;//Properties of the entity.
    //Is part of the Odata url separated by / before the ? markingoptions section.
    //Can be navigation properties leading to another entity or simple properties.
    private $_queryOptions; //params after the ? in the url
    private $_odataQueryString; //whole query string

	private $_hasError;
	private $_errors;

	const FILTEROPTION = 'filter';
	const SELECTOPTION = 'select';
	const ORDERBYOPTION = 'orderby';
	const FORMATOPTION = 'format';
    const FILTERVALUE_PLACEHOLDER = '?';


	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_initialiseProperties();
	}

	// --------------------------------------------------------------------------------------------
	// Protected methods

    protected function _query()
    {
    	$this->_setFormat();
		$this->_generateQueryString();
		if ($this->_hasError())
		{
			return error($this->_errors);
		}

    	return $this->_call($this->_odataQueryString, SAPSFClientLib::HTTP_GET_METHOD);
    }

    protected function _setEntity($entityName, $entityValue = null)
    {
    	if (is_string($entityName) && $this->_checkEntityName($entityName))
		{
    		$this->_entity = array();
			$entityValue = is_string($entityValue) ? $entityValue : null;
        	$this->_entity['name'] = $entityName;
        	$this->_entity['value'] = $entityValue;
		}
    	else
			$this->_setError('Invalid entity name provided: '.$entityName);
    }

    protected function _setMainUriProperty($propertyName, $propertyValue = null)
    {
		if (is_string($propertyName) && $this->_checkMainUriPropertyName($propertyName))
		{
			$propertyValue = is_string($propertyValue) ? $propertyValue : null;
			$property = array('name' => $propertyName, 'value' => $propertyValue);
			$this->_mainUriProperties[] = $property;
		}
		else
			$this->_setError('Invalid uri property name provided: '.$propertyName);
    }

	protected function _setMainUriProperties($queryProperties)
	{
		if (is_array($queryProperties))
		{
			foreach ($queryProperties as $queryProperty)
			{
				if (isset($queryProperty['name']) && is_string($queryProperty['name']))
				{
					$value = isset($queryProperty['value']) && is_string($queryProperty['value']) ? $queryProperty['value'] : null;
					$this->_setMainUriProperty([$queryProperty['name']], $value);
				}
				else
					$this->_setError('Invalid main uri property provided.');
			}
		}
		else
			$this->_setError('Invalid main uri properties array. Array must contain subarrays with [name] and optionally [value] properties.');
	}

    protected function _setFilterString($filterString, $filterValues = array())
    {
    	if (is_string($filterString) && $this->_checkFilterString($filterString) && is_array($filterValues))
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

				if (is_string($val))
				{
					$val = "'" . $this->_encodeForOdata($val) . "'";
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

    /*protected function _setFilter($filterName, $filterValue)
    {
        $this->_setQueryOption('filter', $filterValue);
    }*/

    protected function _setSelect($selectProperty)
    {
    	if (is_string($selectProperty) && $this->_checkQueryOptionName($selectProperty))
			$this->_setQueryOption(self::SELECTOPTION, $selectProperty);
    	else
    		$this->_setError('Invalid select property provided: '.$selectProperty);
    }

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

	protected function _setOrderBy($orderbyProperty, $order = 'asc')
	{
		if (is_string($orderbyProperty) && $this->_checkQueryOptionName($orderbyProperty))
		{
			$order = is_string($order) ? $order : 'asc';
			$this->_setQueryOption(self::ORDERBYOPTION, array('name' => $orderbyProperty, 'order' => $order));
		}
		else
			$this->_setError('Invalid orderby property provided: '.$orderbyProperty);
	}

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

	// --------------------------------------------------------------------------------------------
	// Private methods

	private function _initialiseProperties()
	{
		$this->_entity = array();
		$this->_mainUriProperties = array();
		$this->_queryOptions = array();
		$this->_odataQueryString = array();
		$this->_hasError = false;
		$this->_errors = array();
	}

	private function _setFormat()
	{
		if (!isset($this->_queryOptions[self::FORMATOPTION]))
			$this->_setQueryOption(self::FORMATOPTION, SAPSFClientModel::DATAFORMAT);
	}

	private function _setQueryOption($name, $value)
	{
		if (!isset($this->_queryOptions[$name]))
			$this->_queryOptions[$name] = array();
		$this->_queryOptions[$name][] = $value;
	}

    private function _generateQueryString()
    {
    	$queryString = '';
		if (isset($this->_entity) && isset($this->_entity['name']) && !isEmptyArray($this->_entity))
		{
			$queryString .= $this->_entity['name'];
			if (isset($this->_entity['value']))
				$queryString .= "('" . $this->_encodeForOdata($this->_entity['value']) . "')";

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
					$queryString .= ($firstQueryOptions) ? '?' : '&';

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
							$first = true;
							foreach ($queryOptions as $filterString)
							{
								if (isset($filterString))
								{
									if ($first)
										$queryString .= '$' . self::FILTEROPTION . '=';
									else
										$queryString .= ' and ';//TODO default: concatinate filter with and
									$queryString .= $filterString;
									$first = false;
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
				$this->_setOdataQueryString($queryString);
			}
		}
		else
			$this->_setError('Entity not set');
    }

	private function _setOdataQueryString($odataQueryString)
	{
		$this->_setFormat();
		$this->_odataQueryString = $odataQueryString;
	}

	private function _checkEntityName($entityName)
	{
		return preg_match('/^[a-zA-Z0-9_]+$/', $entityName) === 1;
	}

	private function _checkMainUriPropertyName($propertyName)
	{
		//can begin with $ if its a special property like $value
		return preg_match('/^\$?[a-zA-Z0-9_]+$/', $propertyName) === 1;
	}

    private function _checkQueryOptionName($optionName)
	{
		return preg_match('/^[a-zA-Z0-9_\/]+$/', $optionName) === 1;
	}

	private function _checkFilterString($filterString)
	{
		return preg_match('/^[a-zA-Z0-9\s\?\(\)]+$/', $filterString) === 1;
	}

	private function _encodeForOdata($str)
	{
		//replace apostroph with two apostrophs for escaping
		return urlencode(str_replace("'", "''", $str));
	}

    private function _hasError()
	{
		return $this->_hasError;
	}

    private function _setError($errormsg)
	{
		$this->_errors[] = 'QUERYBUILD_ERROR: '.$errormsg;
		$this->_hasError = true;
	}
}
