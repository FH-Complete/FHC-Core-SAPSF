<?php

require_once APPPATH.'models/extensions/FHC-Core-SAPSF/SAPSFClientModel.php';

/**
 * Contains generic functionality for editing SAPSF data.
 */
class SAPSFEditOperationsModel extends SAPSFClientModel
{
	private $_entities;

	const UPSERT = 'upsert';
	const MERGE_HEADER_NAME = 'x-http-method';
	const MERGE_HEADER_VALUE = 'MERGE';
	const MAX_REQUEST_NUMBER = 1000; // maximum number of subrequests sent in batch request

	/**
	 * SAPSFEditOperationsModel constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_inititaliseProperties();
	}

	//------------------------------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Sets main Entity queried and a key predicate value (like primary key).
	 * @param string $entityName
	 * @param string|array $keyPredicateValue if associative array, composite key value is set
	 * @param array $updateData data to be updated
	 */
	protected function _setEntity($entityName, $keyPredicateValue, $updateData)
	{
		$valid = true;
		if ($this->_checkEntityName($entityName))
		{
			if (isset($keyPredicateValue))
			{
				if (!is_string($keyPredicateValue) && !is_array($keyPredicateValue))
				{
					$valid = false;
					$this->_setError('Invalid key pedicate value provided.');
				}
				elseif (is_array($keyPredicateValue)) // composite key
				{
					if (count($keyPredicateValue) >= 2)
					{
						foreach ($keyPredicateValue as $name => $value)
						{
							if (!$this->_checkQueryOptionName($name) || !is_string($value))
							{
								$valid = false;
								$this->_setError('Invalid key pedicate provided: (' . $name . ') => ' . $value);
							}
						}

						if ($valid)
							$this->_entities['value'] = $keyPredicateValue;
					}
					else
					{
						$valid = false;
						$this->_setError('Composite key predicate must have at least two values.');
					}
				}
			}
		}
		else
		{
			$valid = false;
			$this->_setError('Invalid entity name provided: '.$entityName);
		}

		if (isset($updateData))
		{
			if (!$this->_checkUpdateArray($updateData))
			{
				$valid = false;
				$this->_setError('Invalid update data provided');
			}
		}

		if ($valid)
		{
			$entity = array();
			$entity['name'] = $entityName;
			$entity['value'] = $keyPredicateValue;
			$entity['updatedata'] = $updateData;
			$this->_entities[] = $entity;
		}
	}

	/**
	 * Calls a single merge, i.e. updates a single record.
	 * Not all enitity parameters have to be provided (type is MERGE).
	 * @return object
	 */
	protected function _callMerge()
	{
		return $this->_callEdit(array(self::MERGE_HEADER_NAME => self::MERGE_HEADER_VALUE));
	}

	/**
	 * Update of multiple entities in one call.
	 * @return object
	 */
	protected function _callUpsert()
	{
		$result = null;
		if (count($this->_entities) < 1)
		{
			$this->_setError('No entities provided.');
		}

		if ($this->_hasError())
			$result =  $this->_getErrors();
		else
		{
			$responses = array();
			$data = array();
			$mainurientities = array(); // still add entities to uri even if upsert - faster

			foreach ($this->_entities as $entity)
			{
				if (!in_array($entity['name'], $mainurientities))
					$mainurientities[] = $entity['name'];
				$entitystr = $this->_getEntityString($entity);
				//[{"__metadata":{"uri":"User('karpenko')"},"email":"bla3@bla.com"},{"__metadata":{"uri":"User('bison')"},"email":"bla4@bla.com"}]
				if (!isEmptyString($entitystr))
				{
					$element = array('__metadata' => array('uri' => $entitystr));
					$element = array_merge($element, $entity['updatedata']);
					$data[] = $element;
				}
			}
			$uripart = implode(',', $mainurientities) . '/' . self::UPSERT . $this->_getFormatParamString();

			$chunks = count($data) > self::MAX_REQUEST_NUMBER ? array_chunk($data, self::MAX_REQUEST_NUMBER) : array($data);

			foreach ($chunks as $chunk)
			{
				$responses[] = $this->_transformUpsertResponse($this->_call($uripart, SAPSFClientLib::HTTP_POST_METHOD, array(), $chunk));
			}
			$result = success($responses);
		}
		// reset entities after call
		$this->_inititaliseProperties();

		return $result;
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Call of update for single record.
	 * @param array $additionalHeaders http headers to pass
	 * @return object|null
	 */
	private function _callEdit($additionalHeaders = array())
	{
		$result = null;

		if (count($this->_entities) !== 1)
		{
			$this->_setError('Wrong number of entities provided. Try upsert if more than one.');
		}

		if ($this->_hasError())
			$result = $this->_getErrors();
		else
		{
			$uripart = $this->_getEntityString($this->_entities[0]);
			$uripart .= $this->_getFormatParamString();
			$data = $this->_entities[0]['updatedata'];
			$result = $this->_call($uripart, SAPSFClientLib::HTTP_POST_METHOD, $additionalHeaders, $data);
		}

		// reset entities after call
		$this->_inititaliseProperties();

		return $result;
	}

	/**
	 * Generates entity string from entity object which can be appended to the odata update url.
	 * @param array $entity must have name and value
	 * @return string
	 */
	private function _getEntityString($entity)
	{
		$entityString = '';
		if (isset($entity['name']) && isset($entity['value']))
		{
			$entityString .= $entity['name'];
			$entityvalue = $entity['value'];
			if (is_string($entityvalue))
				$entityString .= "('" . $this->_encodeForOdata($entityvalue) . "')";
			elseif (is_array($entityvalue))
			{
				$entityString .= '(';
				$first = true;
				foreach ($entityvalue as $predname => $predvalue)
				{
					if (!$first)
						$entityString .= ',';
					$entityString .= $predname . "='" . $this->_encodeForOdata($predvalue) . "'";
					$first = false;
				}
				$entityString .= ')';
			}
		}

		return $entityString;
	}

	/**
	 * Checks if postdata passed is valid.
	 * @param $updateData
	 * @return bool
	 */
	private function _checkUpdateArray($updateData)
	{
		if (is_array($updateData))
		{
			foreach ($updateData as $key => $item)
			{
				if (!is_string($key) || !is_string($item))
					return false;
			}
		}
		return true;
	}

	/**
	 * Transforms response from upsert api call so it can be processed.
	 * @param $response
	 * @return object object with success/error objects for each performed upsert operation.
	 */
	private function _transformUpsertResponse($response)
	{
		if (hasData($response))
		{
			$transformedResponse = array();
			$upsertResponse = getData($response);
			foreach ($upsertResponse as $item)
			{
				if (isset($item->editStatus) && $item->editStatus !== 'error')
					$transformedResponse[] = success($item->key);
				else
					$transformedResponse[] = error($item->message);
			}
			return success($transformedResponse);
		}
		else
			return $response;
	}

	/**
	 * Gets all errors as an error object with concatinated string.
	 * @return object
	 */
	private function _getErrors()
	{
		return error(implode("; ", $this->_errors));
	}

	/**
	 * Returns url string for setting the return data format.
	 * @return string
	 */
	private function _getFormatParamString()
	{
		return '?$format='. self::DATAFORMAT;
	}

	/**
	 * (Re-)sets initial properties of this class.
	 */
	private function _inititaliseProperties()
	{
		$this->_entities = array();
	}
}
