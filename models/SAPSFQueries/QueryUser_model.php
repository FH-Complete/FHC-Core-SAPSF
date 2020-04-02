<?php

require_once 'SAPSFQueryModel.php';

/**
 * This implements all the calls for querying data from SAP success factors
 */
class QueryUser_model extends SAPSFQueryModel
{
	/**
	 * Set the properties to perform REST calls
	 */
	public function __construct()
	{
		parent::__construct();
	}

	// --------------------------------------------------------------------------------------------
    // Public methods

	/**
	 * Gets user with specific usr id
	 * @param $userId
	 * @return object userdata
	 */
	public function getByUserId($userId)
	{
		$this->_setEntity('User', $userId);
		$this->_setSelects(array('userId', 'empId', 'department', 'nationality', 'email', 'firstName', 'lastName'));
		//$this->_setFilterString('userId like ?', array('bison'));

		return $this->_query();
	}

	/**
	 * Gets all users present in SAPSF
	 * @return object userdata
	 */
	public function getAll()
	{
		$this->_setEntity('User');
		$this->_setSelects(array('userId', 'empId', 'department', 'nationality', 'email', 'firstName', 'lastName'));
		//$this->_setOrderBys(array(array('name' => 'lastName', 'order' => 'desc'),array('name' => 'firstName')));
		$this->_setOrderBys(array('lastName', 'firstName'));
		return $this->_query();
	}
}
