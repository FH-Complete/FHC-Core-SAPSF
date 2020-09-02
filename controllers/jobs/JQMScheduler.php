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
	 * Create jobsqueue entry with null input for daily employee sync.
	 */
	public function syncEmployeesFromSAPSF()
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

	/**
	 * Create jobsqueue entry for syncing to SAPSF, taking uids as input.
	 */
	public function syncEmployeesToSAPSF()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAPSF->syncEmployeesToSAPSF');

		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->getSyncemployeesToSAPSF();

		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			if (hasData($jobInputResult))
			{
				// If an error occured then log it
				// Add the new job to the jobs queue
				$addNewJobResult = $this->addNewJobsToQueue(
					JQMSchedulerLib::JOB_TYPE_SYNC_EMPLOYEES_TO_SAPSF, // job type
					$this->generateJobs( // gnerate the structure of the new job
						JobsQueueLib::STATUS_NEW,
						getData($jobInputResult)
					)
				);

				// If error occurred return it
				if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-SAPSF->syncEmployeesToSAPSF');
	}
}
