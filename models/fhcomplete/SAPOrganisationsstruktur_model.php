<?php

class SAPOrganisationsstruktur_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_sap_organisationsstruktur';
	}
}
