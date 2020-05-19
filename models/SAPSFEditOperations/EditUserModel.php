<?php
require_once 'SAPSFEditOperationsModel.php';

class EditUserModel extends SAPSFEditOperationsModel
{
	/**
	 * SAPSFEditOperationsModel constructor.
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Updates a single SAPSF user .
	 * @param string $userId
	 * @param array $userdata
	 * @return object
	 */
	public function updateUser($userId, $userdata)
	{
		$this->_setEntity(SyncEmployeesToSAPSFLib::OBJTYPE, $userId, $userdata);
		return $this->_callMerge();
	}

	/**
	 * Updates multiple SAPSF users.
	 * @param array $userdata
	 * @return object
	 */
	public function updateUsers($userdata)
	{
		$results = array();
		$entitydata = array();

		foreach ($userdata as $uid => $sapsfobj)
		{
			foreach ($sapsfobj as $sapsfentity => $sapsfdata)
			{
				foreach ($sapsfdata as $fhctbl => $data)
				{
					$entitydata[$sapsfentity][] = $data;
				}
			}
		}

		foreach ($entitydata as $entity => $sapsfdata)
		{
			foreach ($sapsfdata as $sd)
			{
				$predicates = $sd[SyncEmployeesToSAPSFLib::PREDICATE_INDEX];
				$data = $sd[SyncEmployeesToSAPSFLib::DATA_INDEX];

				$this->_setEntity($entity, $predicates, $data);
			}

			$results[] = $this->_callUpsert();
		}
		return $results;
	}
}
