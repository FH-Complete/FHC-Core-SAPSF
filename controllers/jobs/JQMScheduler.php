<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class JQMScheduler extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads JQMSchedulerLib
		$this->load->library('extensions/FHC-Core-SAPSF/JQMSchedulerLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Create entry with null input for daily employee sync.
	 */
	public function syncEmployees()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAPSF->syncEmployeesFromSAPSF');

		// If an error occured then log it
		// Add the new job to the jobs queue
		$addNewJobResult = $this->addNewJobsToQueue(
			JQMSchedulerLib::JOB_TYPE_SYNC_EMPLOYEES_FROM_SAPSF, // job type
			$this->generateJobs( // gnerate the structure of the new job
				JobsQueueLib::STATUS_NEW,
				null
			)
		);

		// If error occurred return it
		if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));

		$this->logInfo('End job queue scheduler FHC-Core-SAPSF->syncEmployeesFromSAPSF');
	}
}
