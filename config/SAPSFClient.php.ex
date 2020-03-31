<?php

$config['fhc_sapsf_active_connection'] = 'DEVELOPMENT'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['fhc_sapsf_connections'] = array(
	'DEVELOPMENT' =>  array(
			'login' => 'LOGIN',
            'companyid' => 'COMPANYID',
			'password' => 'PASSWORD'
	),
	'PRODUCTION' => array(
        'login' => 'LOGIN2',
        'companyid' => 'COMPANYID2',
        'password' => 'PASSWORD2'
	)
);

/**
 * Connection protocol to SAPSF instance
 */
$config['FHC-Core-SAPSF']['protocol'] = 'https';

/**
 * URL to SAPSF instance
 */
$config['FHC-Core-SAPSF']['host'] = 'examplehost.com';

/**
 * Path to SAPSF API
 */
$config['FHC-Core-SAPSF']['path'] = 'my/path';
