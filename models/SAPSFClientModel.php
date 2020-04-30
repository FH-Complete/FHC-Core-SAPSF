<?php

/**
 * Implements the SAP SF client basic funcitonalities
 */
abstract class SAPSFClientModel extends CI_Model
{
	protected $_hasError;
	protected $_errors;

    const DATAFORMAT = 'json';
	// --------------------------------------------------------------------------------------------
	// Public methods

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
			$data = $this->sapsfclientlib->hasData() ? $this->_getSFData($wsResult) : $wsResult;
			$wsResult = success($data);
		}

		$this->sapsfclientlib->resetToDefault(); // reset to the default values

		return $wsResult;
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
	 * Gets data in a SAP SF HTTP response.
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
		}

		return $data;
	}
}
