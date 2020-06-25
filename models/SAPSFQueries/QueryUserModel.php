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
	 * Gets user with specific user id.
	 * @param $userId
	 * @param $selects array fields to retrieve for this user
	 * @param array $expands fields to expand
	 * @param string $lastModifiedDateTime date when user was last modified
	 * @return object userdata
	 */
	public function getByUserId($userId, $selects = array(), $expands = array())
	{
		//$this->_setEntity('User', $userId);
		$this->_setEntity('User');
		$this->_setSelects($selects);
		$this->_setExpands($expands);
		$this->_setFilter('userId', $userId);

		return $this->_query();
	}

	/**
	 * Gets all users present in SAPSF.
	 * @param array $selects fields to retrieve for each user
	 * @param array $expands fields to expand
	 * @param string $lastModifiedDateTime date when users were last modified
	 * @return object userdata
	 */
	public function getAll($selects = array(), $expands = array(), $lastModifiedDateTime = null, $lastModifiedDateTimeProps = null)
	{
		$this->_setEntity('User');
		$this->_setSelects($selects);
		$this->_setExpands($expands);
		$this->_setOrderBys(array('lastName', 'firstName'));
		//$this->_setLastModifiedDateTime($lastModifiedDateTime);
		$lastModFilterStr = '(lastModifiedDateTime gt datetime?';
		foreach ($lastModifiedDateTimeProps as $prop)
		{
			$lastModFilterStr .= ' or ';
			$lastModFilterStr .= $prop."/lastModifiedDateTime gt datetime?";
		}
		$lastModFilterStr .= ')';
		$lastModifiedDates = array_pad(array(), count($lastModifiedDateTimeProps) + 1, $lastModifiedDateTime);
		$this->_setFilterString($lastModFilterStr, $lastModifiedDates);

		return $this->_query();
	}
}
