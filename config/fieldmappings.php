<?php

/**
 * config file containing mapping of fieldnames from fhcomplete and SAP Success Factors
 * array structure:
 * ['fieldmappings']['mobilityonlineobject']['fhctable'] = array('fhcfieldname' => 'mobilityonlinefieldname')
 */

$config['fieldmappings']['fromsapsf']['employee']['person'] = array(
	'firstName' => 'vorname',
	'lastName' => 'nachname',
	'nationality' => 'staatsbuergerschaft',
	'dateOfBirth' => 'gebdatum'
);

$config['fieldmappings']['fromsapsf']['employee']['mitarbeiter'] = array(
	'userId' => 'mitarbeiter_uid',
	'empId' => 'personalnummer'
);

$config['fieldmappings']['fromsapsf']['employee']['benutzer'] = array(
	'userId' => 'uid'
);

$config['fieldmappings']['tosapsf']['kontakttel']['employee'] = array(
	'firmentelefon' => 'businessPhone'
);

$config['fieldmappings']['tosapsf']['benutzer']['employee'] = array(
	'uid' => 'email' // email is generated from alias, which is derived from the uid
);

/*$config['fieldmappings']['tosapsf']['person']['employee'] = array(
	'ort_kurzbz' => 'buero'
);*/


