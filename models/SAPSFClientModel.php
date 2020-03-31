<?php

/**
 * Implements the SAP SOAP client basic funcitonalities
 */
abstract class SAPSFClientModel extends CI_Model
{
    const DATAFORMAT = 'json';
	// --------------------------------------------------------------------------------------------
	// Protected methods

    public function __construct()
    {
        parent::__construct();
        $this->load->library('extensions/FHC-Core-SAPSF/SAPSFClientLib');
    }

    // Loads the SAPSFClientLib library

	/**
	 * Generic SAPSF call. It checks also for specific SAPSF blocking and non-blocking errors
	 */
	protected function _call($odataUriPart, $httpMethod, $callParametersArray = array())
	{
		// Call the SAP webservice with the given parameters
		$wsResult = success(
			$this->sapsfclientlib->call(
			    $odataUriPart,
                $httpMethod,
                $callParametersArray
			)
		);

		// If an error occurred
		if ($this->sapsfclientlib->isError())
		{
            $wsResult = error($this->sapsfclientlib->getError());
        }

		$this->sapsfclientlib->resetToDefault(); // reset to the default values

		return $wsResult;
	}
}
