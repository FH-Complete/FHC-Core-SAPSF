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
		$this->_setEntity('User', $userId);
		$this->_setSelects($selects);
		$this->_setExpands($expands);
		//$this->_setFilter('userId', $userId);
		$this->_setEffectiveDates();

		return $this->_query();
	}

	/**
	 * Gets all users present in SAPSF.
	 * @param array $selects fields to retrieve for each user
	 * @param array $expands fields to expand
	 * @param string $lastModifiedDateTime date when users were last modified
	 * @param null $lastModifiedDateTimeProps additional properties checked for lastModifiedDateTime
	 * @param null $uids
	 * @return object userdata
	 */
	public function getAll($selects = array(), $expands = array(), $lastModifiedDateTime = null, $lastModifiedDateTimeProps = null, $uids = null)
	{
		$this->_setEntity('User');
		$this->_setSelects($selects);
		$this->_setExpands($expands);
		$this->_setOrderBys(array('lastName', 'firstName'));

		// get all modified after given date
		if (isset($lastModifiedDateTime))
		{
			$lastModFilterStr = 'lastModifiedDateTime ge datetime?';

			if (isset($lastModifiedDateTimeProps))
			{
				foreach ($lastModifiedDateTimeProps as $prop)
				{
					$lastModFilterStr .= ' or ';
					$lastModFilterStr .= $prop . "/lastModifiedDateTime ge datetime?";
				}
			}
			else
				$lastModifiedDateTimeProps = array();

			$lastModifiedDates = array_pad(array(), count($lastModifiedDateTimeProps) + 1, $lastModifiedDateTime);
			$this->_setFilterString($lastModFilterStr, $lastModifiedDates);
		}

		//get all which are active in sapsf OR have "fas aktiv" checked
/*		$benutzerfields = $this->config->item('fieldmappings')['fromsapsf']['User']['benutzer'];
		foreach ($benutzerfields as $sapsfield => $fhcfield)
		{
			if ($fhcfield === 'aktiv')
			{
				$benutzerfieldname = $sapsfield;
				break;
			}
		}

		$valuemappings = $this->config->item('valuemappings')['fromsapsf']['User']['benutzer']['aktiv'];
		foreach ($valuemappings as $sapsfield => $fhcfield)
		{
			if ($fhcfield === true)
			{
				$yesval = $sapsfield;
				break;
			}
		}

		if (isset($benutzerfieldname) && isset($yesval))
		{*/
		$this->_setFilter('status', array('active', 'inactive'), 'in');
		//}

		if (isset($uids))
		{
			$this->_setFilter('userId', $uids, 'in');
		}

		$this->_setEffectiveDates();

		return $this->_query();
	}
}
