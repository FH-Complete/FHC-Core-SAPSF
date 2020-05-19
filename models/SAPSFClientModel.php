<?php

/**
 * Implements the SAP SF client basic funcitonalities
 */
abstract class SAPSFClientModel extends CI_Model
{
	protected $_hasError;
	protected $_errors;

    const DATAFORMAT = 'json';

    public function __construct()
    {
        parent::__construct();
    	// Loads the SAPSFClientLib library
        $this->load->library('extensions/FHC-Core-SAPSF/SAPSFClientLib');
    }

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Generic SAPSF call. It checks also for specific SAPSF errors
	 * @param string $odataUriPart specific uri part contining odata parameters
	 * @param string $httpMethod
	 * @param array $additionalHeaders additional HTTP headers
	 * @param array $callParametersArray
	 * @return object call result (success or error)
	 */
	protected function _call($odataUriPart, $httpMethod, $additionalHeaders = array(), $callParametersArray = array())
	{
		// Call the SAPSF webservice with the given parameters
		$wsResult = $this->sapsfclientlib->call(
			$odataUriPart,
			$httpMethod,
			$additionalHeaders,
			$callParametersArray
		);

		// If an error occurred
		if ($this->sapsfclientlib->isError())
		{
            $wsResult = error($this->sapsfclientlib->getError());
        }
		else
		{
			$data = $this->_getSFData($wsResult);
			$wsResult = success($data);
		}

		$this->sapsfclientlib->resetToDefault(); // reset to the default values

		return $wsResult;
	}

	/**
	 * Generates entity string from entity object which can be appended to the odata update url.
	 * @param array $entity must have name and value
	 * @return string
	 */
	protected function _getEntityString($entity)
	{
		$entityString = '';
		if (isset($entity['name']))
		{
			$entityString .= $entity['name'];
			if (isset($entity['value']))
			{
				$entityvalue = $entity['value'];
				if (is_string($entityvalue))
					$entityString .= "(" . $this->_prepareEntityValueForOdata($entityvalue) . ")";
				elseif (is_array($entityvalue))
				{
					$entityString .= '(';
					$first = true;
					foreach ($entityvalue as $predname => $predvalue)
					{
						if (!$first)
							$entityString .= ',';
						$entityString .= $predname . "=" . $this->_prepareEntityValueForOdata($predvalue);
						$first = false;
					}
					$entityString .= ')';
				}
			}
		}

		return $entityString;
	}

	/**
	 * Checks an entity name for its validity.
	 * @param string $entityName
	 * @return bool
	 */
	protected function _checkEntityName($entityName)
	{
		return is_string($entityName) && preg_match('/^[a-zA-Z0-9_]+$/', $entityName) === 1;
	}

	/**
	 * Checks a query option name for its validity.
	 * @param string $optionName
	 * @return bool
	 */
	protected function _checkQueryOptionName($optionName)
	{
		//can have a / if it's a navigation property
		return is_string($optionName) && preg_match('/^[a-zA-Z0-9_\/]+$/', $optionName) === 1;
	}

	/**
	 * Prepares a string to be passed to odata.
	 * @param string $value
	 * @return string
	 */
	protected function _prepareEntityValueForOdata($value)
	{
		$preparedVal = '';
		if ($this->_isOdataDateFormat($value))
			$preparedVal .= "datetime";
		$preparedVal .= "'" . $this->_encodeForOdata($value) . "'";
		return $preparedVal;
	}

	/**
	 * Encodes a string for odata querying.
	 * @param string $str
	 * @return string
	 */
	protected function _encodeForOdata($str)
	{
		//replace apostroph with two apostrophs for escaping
		return urlencode(str_replace("'", "''", $str));
	}

	/**
	 * Sets an error.
	 * @param string $errormsg
	 */
	protected function _setError($errormsg)
	{
		$this->_errors[] = 'CLIENTERROR: '.$errormsg;
		$this->_hasError = true;
	}

	/**
	 * Checks if an error has occured during querying.
	 * @return bool
	 */
	protected function _hasError()
	{
		return $this->_hasError;
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Gets data from a SAPSF HTTP response.
	 */
	private function _getSFData($response)
	{
		$data = null;

		if (isset($response->body))
		{
			$responsebody = $response->body;
			if (isset($responsebody->d))
			{
				if (isset($responsebody->d->results))
				{
					$data = $responsebody->d->results;
				}
				else
					$data = $responsebody->d;
			}
			else
				$data = $responsebody;
		}
		else
			$this->_setError("Response has no body");

		return $data;
	}

	/**
	 * Checks if a string is in odata date format.
	 * @param string $value
	 * @return bool
	 */
	private function _isOdataDateFormat($value)
	{
		return preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]T[0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/', $value) === 1;
	}
}
