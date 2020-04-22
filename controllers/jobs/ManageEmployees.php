<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

class ManageEmployees  extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SyncEmployeesLib
		$this->load->library('extensions/FHC-Core-SAPSF/SyncToFhcLib');
		$this->load->library('extensions/FHC-Core-SAPSF/SyncEmployeesLib');
		$this->load->model('extensions/FHC-Core-SAPSF/SAPSFQueries/QueryUserModel', 'QueryUserModel');
	}

	public function syncEmployees()
	{
		$this->logInfo('End employee data synchronization with SAP Success Factors');

		$conffieldmappings = $this->config->item('fieldmappings');

		$selects = array();
		foreach ($conffieldmappings[syncemployeeslib::OBJTYPE] as $mappingset)
		{
			foreach ($mappingset as $sapsfkey => $fhcvalue)
			{
				$selects[] = $sapsfkey;
			}
		}

		//$employees = $this->QueryUserModel->getAll($selects);
		$employees = $this->QueryUserModel->getByUserId('karpenko', $selects);
		$results = $this->syncemployeeslib->syncEmployeesWithFhc($employees);

		if (hasData($results))
		{
			$results = getData($results);

			foreach ($results AS $result)
			{
				if (isError($result))
				{
					$this->logError(getError($result));
				}
			}
		}
		else
			$this->logInfo('No employee data synced with SAP Success Factors');

		$this->logInfo('End employee data synchronization with SAP Success Factors');
	}
}