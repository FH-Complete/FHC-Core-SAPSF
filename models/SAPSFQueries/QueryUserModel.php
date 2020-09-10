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
	 * @param array $lastModifiedDateTimeProps additional properties checked for lastModifiedDateTime
	 * @param bool $setEffectiveDates if true, from and to date are set
	 * @param array $uids if only particular uids need to be retrieved
	 * @return object userdata
	 */
	public function getAll($selects = array(), $expands = array(), $lastModifiedDateTime = null, $lastModifiedDateTimeProps = null, $startDateProps = null, $setEffectiveDates = true, $uids = null)
	{
		$this->_setEntity('User');
		$this->_setSelects($selects);
		$this->_setExpands($expands);
		$this->_setOrderBys(array('userId'));

		// get all modified after given date
		if (isset($lastModifiedDateTime))
		{
			$filterDateStr = 'lastModifiedDateTime ge datetime?';
			$filterDates = array($lastModifiedDateTime);

			if (!isEmptyArray($lastModifiedDateTimeProps))
			{
				foreach ($lastModifiedDateTimeProps as $prop)
				{
					$filterDateStr .= ' or ';
					$filterDateStr .= $prop . "/lastModifiedDateTime ge datetime?";
					$filterDates[] = $lastModifiedDateTime;
				}
			}

			// get time-based data by startdate instead of lastmodifieddate. startdate must be >= lastSyncDate and <= today
			if (!isEmptyArray($startDateProps))
			{
				$first = true;
				$filterDatesCnt = count($filterDates);
				foreach ($startDateProps as $prop)
				{
					if (!$first || $filterDatesCnt > 0)
						$filterDateStr .= ' or ';
					$filterDateStr .= "(" . $prop . "/startDate ge ? and " . $prop . "/startDate le ?)";

					$filterDates[] = substr($lastModifiedDateTime, 0, 10);
					$filterDates[] = date('Y-m-d');

					$first = false;
				}
			}

			$this->_setFilterString($filterDateStr, $filterDates);
		}

		$this->_setFilter('status', array('active', 'inactive'), 'in');

		if (isset($uids))
		{
			$this->_setFilter('userId', $uids, 'in');
		}

		if ($setEffectiveDates === true)
			$this->_setEffectiveDates();

		return $this->_query();
	}
}
