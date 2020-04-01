<?php

require_once 'SAPSFQueryModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name QueryAccounts
 */
class QueryUser_model extends SAPSFQueryModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();
	}

	// --------------------------------------------------------------------------------------------
    // Public methods

	/**
	 */
	public function getByUserId($userId)
	{
		$this->_setEntity('User', $userId);
		$this->_setSelects(array('userId', 'empId', 'department', 'nationality', 'email', 'firstName', 'lastName'));
		//$this->_setFilterString('userId like ?', array('bison'));

		return $this->_query();
	}

	public function getAll()
	{
		$this->_setEntity('User');
		$this->_setSelects(array('userId', 'empId', 'department', 'nationality', 'email', 'firstName', 'lastName'));
		//$this->_setOrderBys(array(array('name' => 'lastName', 'order' => 'desc'),array('name' => 'firstName')));
		$this->_setOrderBys(array('lastName', 'firstName'));
		return $this->_query();
	}
}
