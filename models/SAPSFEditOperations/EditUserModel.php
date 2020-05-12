<?php
require_once 'SAPSFEditOperationsModel.php';

class EditUserModel extends SAPSFEditOperationsModel
{
	const USER_ENTITY = 'User';
	/*const EMAIL_PROPERTY = 'email';*/
	/*const EMAIL_POSTFIX = '@technikum-wien.at';*/ // appended to each user mail

	/**
	 * SAPSFEditOperationsModel constructor.
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Updates a single SAPSF user mail using the user alias.
	 * @param string $userId
	 * @param string $alias
	 * @return object
	 */
	/*public function updateEmail($userId, $alias)
	{
		$this->_setEntity(self::USER_ENTITY, $userId, array(self::EMAIL_PROPERTY => $alias.self::EMAIL_POSTFIX));
		return $this->_callMerge();
	}*/

	/**
	 * Updates multiple SAPSF user mails using the user aliases.
	 * @param array $data should have uids as keys, aliases as values
	 * @return object
	 */
/*	public function updateEmails($data)
	{
		foreach ($data as $uid => $alias)
		{
			$this->_setEntity(self::USER_ENTITY, $uid, array(self::EMAIL_PROPERTY => $alias.self::EMAIL_POSTFIX));
		}
		return $this->_callUpsert();
	}*/

	/**
	 * Updates a single SAPSF user .
	 * @param string $userId
	 * @param array $userdata
	 * @return object
	 */
	public function updateUser($userId, $userdata)
	{
		$this->_setEntity(self::USER_ENTITY, $userId, $userdata);
		return $this->_callMerge();
	}

	/**
	 * Updates multiple SAPSF users.
	 * @param array $userdata
	 * @return object
	 */
	public function updateUsers($userdata)
	{
		foreach ($userdata as $uid => $data)
		{
			$uid = (string) $uid;
			$this->_setEntity(self::USER_ENTITY, $uid, $data);
		}

		return $this->_callUpsert();
	}
}
