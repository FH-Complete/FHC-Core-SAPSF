<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example API
 */
class Example extends API_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct(array('Example' => 'basis/person:rw'));
		// Loads QueryAccountsModel
		$this->load->model('extensions/FHC-Core-SAPSF/SAPSFQueries/QueryUser_model', 'QueryUserModel');
	}

	/**
	 * Example method
	 */
	public function getExample()
	{
		$resp = $this->response(
			$this->QueryUserModel->getByUserId('karpenko'),
			REST_Controller::HTTP_OK
		);
	}
}
