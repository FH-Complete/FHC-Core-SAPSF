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

$config['fieldmappings']['tosapsf']['kontakttel']['User'] = array(
	'firmentelefon' => 'businessPhone',
	'uid' => 'userId'
);

$config['fieldmappings']['tosapsf']['benutzer']['PerEmail'] = array(
	'uid' => 'emailAddress' // email is generated from alias, which is derived from the uid
);

$config['fieldmappings']['tosapsf']['mitarbeiter']['PerPersonal'] = array(
	'ort_kurzbz' => 'customString4' // email is generated from alias, which is derived from the uid
);
