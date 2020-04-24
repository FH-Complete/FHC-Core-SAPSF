<?php

require_once 'SAPSFQueryModel.php';

/**
 * This implements all the calls for querying data from SAP success factors
 */
class QueryUserModel extends SAPSFQueryModel
{
	/**
	 * QueryUserModel constructor.
	 */
	public function __construct()
	{
		parent::__construct();
	}

	// --------------------------------------------------------------------------------------------
    // Public methods

	/**
	 * Gets user with specific usr id.
	 * @param $userId
	 * @param $selects array fields to retrieve for this user
	 * @param string $lastModifiedDateTime date when user was last modified
	 * @return object userdata
	 */
	public function getByUserId($userId, $selects = array(), $lastModifiedDateTime = null)
	{
		//$this->_setEntity('User', $userId);
		$this->_setSelects($selects);
		$this->_setEntity('User');
		$this->_setFilter('userId', $userId);
		$this->_setLastModifiedDateTime($lastModifiedDateTime);
		//$this->_setFilterString('userId like ?', array('bison'));

		return $this->_query();
	}

	/**
	 * Gets all users present in SAPSF.
	 * @param array $selects fields to retrieve for each user
	 * @param string $lastModifiedDateTime date when users ware last modified
	 * @return object userdata
	 */
	public function getAll($selects = array(), $lastModifiedDateTime = null)
	{
		$this->_setEntity('User');
		$this->_setSelects($selects);
		//$this->_setOrderBys(array(array('name' => 'lastName', 'order' => 'desc'),array('name' => 'firstName')));
		$this->_setOrderBys(array('lastName', 'firstName'));
		$this->_setLastModifiedDateTime($lastModifiedDateTime);

		return $this->_query();
	}
}
