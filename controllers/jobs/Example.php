<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example JOB
 */
class Example extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads QueryUserModel
        $this->load->model('extensions/FHC-Core-SAPSF/SAPSFQueries/QueryUser_model', 'QueryUserModel');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Example method
	 */
	public function example()
	{
		$this->logInfo('Example job start');

		$queryResult = $this->QueryUserModel->getAll();

		// If groups are present
		if (isError($queryResult))
		{
			$this->logError('Error: '.getError($queryResult));
		}
		elseif (hasData($queryResult))
		{
			$this->logInfo('Result: '.getData($queryResult));
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Example job stop');
	}
}
