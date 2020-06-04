<?php

/**
 * config file containing mapping of fieldnames from fhcomplete and SAP Success Factors
 * array structure:
 * ['fieldmappings']['mobilityonlineobject']['fhctable'] = array('fhcfieldname' => 'mobilityonlinefieldname')
 */

$config['fieldmappings']['fromsapsf']['User']['person'] = array(
	'firstName' => 'vorname',
	'lastName' => 'nachname',
	'empInfo/personNav/personalInfoNav/nationality' => 'staatsbuergerschaft',
	'empInfo/personNav/dateOfBirth' => 'gebdatum',
	'empInfo/personNav/countryOfBirth' => 'geburtsnation',
	'empInfo/personNav/personalInfoNav/title' => 'titelpre',
	'empInfo/personNav/personalInfoNav/secondTitle' => 'titelpost',
	'empInfo/personNav/personalInfoNav/gender' => 'geschlecht',
	'empInfo/personNav/personalInfoNav/salutationNav/externalCode' => 'anrede',
	'empInfo/personNav/personalInfoNav/middleName' => 'vornamen',
	'empInfo/personNav/nationalIdNav/nationalId' => 'svnr'
);

$config['fieldmappings']['fromsapsf']['User']['mailtyp'] = array(
	'empInfo/personNav/emailNav/emailType' => 'emailtyp',
);

$config['fieldmappings']['fromsapsf']['User']['kontaktmail'] = array(
	/*'email' => 'kontakt'*/
	'empInfo/personNav/emailNav/emailAddress' => 'kontakt'
);

$config['fieldmappings']['fromsapsf']['User']['mitarbeiter'] = array(
	'userId' => 'mitarbeiter_uid',
	'empInfo/personNav/customString1' => 'personalnummer',
/*	'empInfo/jobInfoNav/isFulltimeEmployee' => 'fixangestellt',*/
	'empInfo/personNav/personalInfoNav/customString10Nav/externalCode' => 'lektor',
	'empInfo/personNav/personalInfoNav/customString12Nav/externalCode' => 'bismelden',
	'empInfo/personNav/personalInfoNav/customString14Nav/externalCode' => 'ausbildungcode',
	'empInfo/personNav/personalInfoNav/customString2' => 'stundensatz'
);

$config['fieldmappings']['fromsapsf']['User']['benutzer'] = array(
	'userId' => 'uid'
);

/*$config['fieldmappings']['fromsapsf']['User']['bisverwendung'] = array(
	'empInfo/personNav/personalInfoNav/customString16Nav/externalCode' => 'habilitation',
	'empInfo/personNav/personalInfoNav/customStringxNav/externalCode' => 'hautpberuf'
);*/

$config['fieldmappings']['tosapsf']['kontakttel']['User'] = array(
	'firmentelefon' => 'businessPhone',
	'uid' => 'userId'
);

$config['fieldmappings']['tosapsf']['benutzer']['PerEmail'] = array(
	'uid' => 'emailAddress' // email is generated from alias, which is derived from the uid
);

$config['fieldmappings']['tosapsf']['mitarbeiter']['PerPersonal'] = array(
	'ort_kurzbz' => 'customString4'
);
