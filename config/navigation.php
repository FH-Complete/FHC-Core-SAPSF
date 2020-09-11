<?php

// Add Menu-Entry to Main Page
$config['navigation_header']['*']['Personen']['children']['SAPSF'] = array(
	'link' => site_url('extensions/FHC-Core-SAPSF/rest/SyncEmployees'),
	'description' => 'SuccessFactors Sync',
	'expand' => false,
	'requiredPermissions' => 'basis/mitarbeiter:rw'
);

// Add Side-Menu-Entry to Extension Page
$config['navigation_menu']['extensions/FHC-Core-SAPSF/*'] = array(
/*	'Back' => array(
		'link' => site_url(),
		'description' => 'Zurück',
		'icon' => 'angle-left'
	),*/
	'Sync from SuccessFactors' => array(
		'link' => site_url('extensions/FHC-Core-SAPSF/rest/SyncEmployees/syncEmployeesFromSAPSF'),
		'description' => 'Sync from SuccessFactors',
		'icon' => 'long-arrow-left'
	),
	'Sync to SuccessFactors' => array(
		'link' => site_url('extensions/FHC-Core-SAPSF/rest/SyncEmployees/syncEmployeesToSAPSF'),
		'description' => 'Sync to SuccessFactors',
		'icon' => 'long-arrow-right'
	)
);
