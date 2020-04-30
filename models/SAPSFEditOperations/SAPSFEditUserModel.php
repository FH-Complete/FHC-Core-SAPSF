<?php
require_once 'SAPSFEditOperationsModel.php';

class SAPSFEditUserModel extends SAPSFEditOperationsModel
{
	const EMAIL_POSTFIX = '@technikum-wien.at';

	/**
	 * SAPSFEditOperationsModel constructor.
	 */
	public function __construct()
	{
		parent::__construct();
	}

	public function updateEmail($userId, $alias)
	{
		$this->_setEntity('User', $userId, array('email' => $alias.self::EMAIL_POSTFIX));
		return $this->_callMerge();
	}

	public function updateEmails($data)
	{
		foreach ($data as $uid => $alias)
		{
			$this->_setEntity('User', $uid, array('email' => $alias.self::EMAIL_POSTFIX));
		}
		return $this->_callUpsert();
	}
}
