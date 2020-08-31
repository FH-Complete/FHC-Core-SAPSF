<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Contains general logic for syncing from SAPSF to FHC
 */
class SyncToSAPSFLib
{
	protected $_conffieldmappings;
	protected $_confvaluedefaults;
	protected $_predicates;

	/**
	 * SyncToSAPSFLib constructor.
	 */
	public function __construct()
	{
		$this->ci =& get_instance();

		// load heloper
		$this->ci->load->helper('extensions/FHC-Core-SAPSF/sync_helper');

		// load config
		$this->ci->config->load('extensions/FHC-Core-SAPSF/SAPSFSyncparams');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/fieldmappings');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/valuedefaults');
		$this->ci->config->load('extensions/FHC-Core-SAPSF/fields');

		$this->_syncpreview = $this->ci->config->item('FHC-Core-SAPSFSyncparams')['syncpreview'];
		$this->_conffieldmappings = $this->ci->config->item('fieldmappings');
		$this->_conffieldmappings = $this->_conffieldmappings['tosapsf'];
		$this->_confvaluedefaults = $this->ci->config->item('sapsfdefaults');
		$this->_predicates = $this->ci->config->item('sapsfpredicates');
		$this->_requiredfields = $this->ci->config->item('requiredsapsffields');

		// load models
		$this->ci->load->model('extensions/FHC-Core-SAPSF/fhcomplete/FhcDbModel', 'FhcDbModel');
	}

	/**
	 * Converts a unix SAPSF timestamp to dateformat of SAPSF.
	 * @param $timestamp
	 * @return string
	 */
	protected function _convertSAPSFTimestampToDateTime($timestamp)
	{
		$millisec = (int)filter_var($timestamp, FILTER_SANITIZE_NUMBER_INT);

		$seconds = $millisec / 1000;
		$datetime = new DateTime("@$seconds");

		$date_time_format = $datetime->format('Y-m-d H:i:s');
		return str_replace(' ', 'T', $date_time_format);
	}
}
