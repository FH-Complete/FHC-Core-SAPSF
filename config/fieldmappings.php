<?php

/**
 * config file containing mapping of fieldnames from fhcomplete and SAP Success Factors
 * array structure:
 * ['fieldmappings']['mobilityonlineobject']['fhctable'] = array('fhcfieldname' => 'mobilityonlinefieldname')
 */

$config['fieldmappings']['fromsapsf']['User']['kztyp'] = array(
	'empInfo/personNav/nationalIdNav/cardType' => 'kztyp' // not synced, just needed to get svnr and ersatzkennzeichen
	// MUST BE PLACED BEFORE PERSON so it's populated before!
);

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
	'empInfo/personNav/nationalIdNav/nationalId' => array('svnr', 'ersatzkennzeichen')
);

$config['fieldmappings']['fromsapsf']['User']['mailtyp'] = array(
	'empInfo/personNav/emailNav/emailType' => 'emailtyp' // not synced, just needed to get correct mail to sync
	// MUST BE PLACED BEFORE KONTAKTMAIL so it's populated before!
);

$config['fieldmappings']['fromsapsf']['User']['kontaktmail'] = array(
	'empInfo/personNav/emailNav/emailAddress' => 'kontakt'
);

$config['fieldmappings']['fromsapsf']['User']['telefondaten'] = array(
	'empInfo/personNav/phoneNav/phoneType' => 'telefontyp',
	'empInfo/personNav/phoneNav/countryCode' => 'landesvorwahl',
	'empInfo/personNav/phoneNav/areaCode' => 'ortsvorwahl',
	'empInfo/personNav/phoneNav/extension' => 'telefonklappe',
	// not synced, just needed to get correct phone data to sync
	// MUST BE PLACED BEFORE KONTAKTPHONE so it's populated before!
);

$config['fieldmappings']['fromsapsf']['User']['kontakttelefon'] = array(
	'empInfo/personNav/phoneNav/phoneNumber' => 'kontakt'
);

$config['fieldmappings']['fromsapsf']['User']['kontaktnotfall'] = array(
	'empInfo/personNav/emergencyContactNav/phone' => 'kontakt',
	'empInfo/personNav/emergencyContactNav/name' => 'anmerkung'
);

$config['fieldmappings']['fromsapsf']['User']['mitarbeiter'] = array(
	'userId' => 'mitarbeiter_uid',
	'empInfo/personNav/customString1' => 'personalnummer',
	'externalCodeOfcust_HourlyRateNav/cust_HourlyRate1' => 'stundensatz',
	'empInfo/jobInfoNav/customString11Nav/externalCode' => 'lektor',
	'empInfo/jobInfoNav/customString12Nav/externalCode' => 'bismelden',
	'empInfo/personNav/personalInfoNav/customString14Nav/externalCode' => 'ausbildungcode',
	'empInfo/jobInfoNav/employeeTypeNav/externalCode' => 'fixangestellt',
	'empInfo/jobInfoNav/location' => 'standort_id'
);

$config['fieldmappings']['fromsapsf']['User']['benutzer'] = array(
	'userId' => 'uid',
	'empInfo/jobInfoNav/customString13Nav/externalCode' => 'aktiv'
);

/*$config['fieldmappings']['fromsapsf']['User']['bisverwendung'] = array(
	'empInfo/personNav/personalInfoNav/customString16Nav/externalCode' => 'habilitation',
	'empInfo/personNav/personalInfoNav/customStringxNav/externalCode' => 'hautpberuf'
);*/

$config['fieldmappings']['fromsapsf']['HourlyRate']['sap_stundensatz'] = array(
	'userId' => 'mitarbeiter_uid',
	'externalCodeOfcust_HourlyRateNav/cust_HourlyRate2' => 'sap_kalkulatorischer_stundensatz'
);

$config['fieldmappings']['tosapsf']['kontakttel']['PerPhone'] = array(
	'firmentelefon_nummer' => 'phoneNumber',
	'firmentelefon_vorwahl' => 'countryCode',
	'firmentelefon_ortsvorwahl' => 'areaCode',
	'firmentelefon_telefonklappe' => 'extension'
);

$config['fieldmappings']['tosapsf']['kontaktmail']['PerEmail'] = array(
	'uid' => 'emailAddress' // email is generated from alias, which is derived from the uid
);

$config['fieldmappings']['tosapsf']['mitarbeiter']['PerPersonal'] = array(
	'ort_kurzbz' => 'customString4'
);
