<?php

/**
 * Implements the SAP SOAP client basic funcitonalities
 */
abstract class SAPSFClientModel extends CI_Model
{
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
	 * Generic SAPSF call. It checks also for specific SAPSF blocking and non-blocking errors
	 */
	protected function _call($odataUriPart, $httpMethod, $callParametersArray = array())
	{
		// Call the SAPSF webservice with the given parameters
		$wsResult = $this->sapsfclientlib->call(
			$odataUriPart,
			$httpMethod,
			$callParametersArray
		);


		// If an error occurred
		if ($this->sapsfclientlib->isError())
		{
            $wsResult = error($this->sapsfclientlib->getError());
        }
		else
		{
			$data = $this->sapsfclientlib->hasData() ? $this->getSFData($wsResult) : $wsResult;
			$wsResult = success($data);
		}

		$this->sapsfclientlib->resetToDefault(); // reset to the default values

		return $wsResult;
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	private function getSFData($response)
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
