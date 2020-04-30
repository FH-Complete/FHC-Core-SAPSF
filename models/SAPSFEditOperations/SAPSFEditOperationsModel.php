<?php

require_once APPPATH.'models/extensions/FHC-Core-SAPSF/SAPSFClientModel.php';

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

	/**
	 * Sets main Entity queried and a key predicate value (like primary key).
	 * @param string $entityName
	 * @param string|array $keyPredicateValue if associative array, composite key value is set
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

	protected function _callMerge()
	{
		return $this->_callEdit(array(self::MERGE_HEADER_NAME => self::MERGE_HEADER_VALUE));
	}

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

			$uripart = self::UPSERT . $this->_getFormatParamString();
			foreach ($this->_entities as $entity)
			{
				$entitystr = $this->_getEntityString($entity);
				//[{"__metadata":{"uri":"User('karpenko')"},"email":"bla3@bla.com"},{"__metadata":{"uri":"User('bison')"},"email":"bla4@bla.com"}]
				if (!isEmptyString($entitystr))
				{
					$element = array('__metadata' => array('uri' => $entitystr));
					$element = array_merge($element, $entity['updatedata']);
					$data[] = $element;
				}
			}

			if (count($data) > self::MAX_REQUEST_NUMBER)
			{
				$chunks = array_chunk($data, self::MAX_REQUEST_NUMBER);
				foreach ($chunks as $chunk)
				{
					$responses[] = $this->_call($uripart, SAPSFClientLib::HTTP_POST_METHOD, array(), $chunk);
				}
			}
			else
				$responses[] = $this->_call($uripart, SAPSFClientLib::HTTP_POST_METHOD, array(), $data);

			$result = success($responses);
		}
		// reset entities after call
		$this->_inititaliseProperties();

		return $result;
	}

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

	private function _getErrors()
	{
		return error(implode("; ", $this->_errors));
	}

	private function _getFormatParamString()
	{
		return '?$format='. self::DATAFORMAT;
	}

	private function _inititaliseProperties()
	{
		$this->_entities = array();
	}
}
