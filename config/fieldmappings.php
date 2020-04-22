<?php

/**
 * config file containing mapping of fieldnames from fhcomplete and mobility online
 * array structure:
 * ['fieldmappings']['mobilityonlineobject']['fhctable'] = array('fhcfieldname' => 'mobilityonlinefieldname')
 */

$config['fieldmappings']['employee']['person'] = array(
	'firstName' => 'vorname',
	'lastName' => 'nachname',
	'nationality' => 'staatsbuergerschaft',
	'dateOfBirth' => 'gebdatum'
);

$config['fieldmappings']['employee']['mitarbeiter'] = array(
	'userId' => 'mitarbeiter_uid',
	'empId' => 'personalnummer'
);

$config['fieldmappings']['employee']['benutzer'] = array(
	'userId' => 'uid'
);

/*$config['fieldmappings']['employee']['kontaktmail'] = array(
	'email' => 'kontakt'
);*/

$config['fieldmappings']['employee']['kontakttel'] = array(
	'businessPhone' => 'kontakt'
);
