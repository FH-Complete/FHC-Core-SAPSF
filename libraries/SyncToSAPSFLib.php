<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Contains general logic for syncing from SAPSF to FHC
 */
class SyncToSAPSFLib
{
	protected $_conffieldmappings;

	/**
	 * SyncToFhcLib constructor.
	 */
	public function __construct()
	{
		$this->ci =& get_instance();

		$this->ci->config->load('extensions/FHC-Core-SAPSF/fieldmappings');

		$this->_conffieldmappings = $this->ci->config->item('fieldmappings');
		$this->_conffieldmappings = $this->_conffieldmappings['tosapsf'];

		$this->ci->load->model('extensions/FHC-Core-SAPSF/fhcomplete/FhcDbModel', 'FhcDbModel');
	}
}
