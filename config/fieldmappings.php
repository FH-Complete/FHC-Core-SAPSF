<?php

/**
 * config file containing mapping of fieldnames from fhcomplete and SAP Success Factors
 * array structure:
 * ['fieldmappings']['syncdirection']['objecttosyncfrom']['objecttosyncinto'] =
 * array('objecttosyncfromfieldname' => 'objecttosyncintofieldname')
 */

$config['fieldmappings']['fromsapsf']['User']['kztyp'] = array(
	'empInfo/personNav/nationalIdNav/cardType' => 'kztyp' // not synced, just needed to get svnr and ersatzkennzeichen
	// MUST BE PLACED BEFORE PERSON so it's populated before!
);

$config['fieldmappings']['fromsapsf']['User']['person'] = array(
	'empInfo/personNav/personalInfoNav/firstName' => 'vorname',
	'empInfo/personNav/personalInfoNav/lastName' => 'nachname',
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

$phonemapping = array('empInfo/personNav/phoneNav/phoneNumber' => 'kontakt');

$config['fieldmappings']['fromsapsf']['User']['kontakttelefon'] = $phonemapping;
//$config['fieldmappings']['fromsapsf']['User']['kontakttelmobile'] = $phonemapping;

$config['fieldmappings']['fromsapsf']['User']['kontaktnotfall'] = array(
	'empInfo/personNav/emergencyContactNav/phone' => 'kontakt',
	'empInfo/personNav/emergencyContactNav/name' => 'anmerkung'
);

$config['fieldmappings']['fromsapsf']['User']['adressedaten'] = array(
	'empInfo/personNav/homeAddressNavDEFLT/addressType' => 'typ',
	'empInfo/personNav/homeAddressNavDEFLT/address2' => 'hausnr'
);

$adressmappings = array(
	'empInfo/personNav/homeAddressNavDEFLT/country' => 'nation',
	'empInfo/personNav/homeAddressNavDEFLT/zipCode' => 'plz',
	'empInfo/personNav/homeAddressNavDEFLT/city' => array('ort', 'gemeinde'),
	'empInfo/personNav/homeAddressNavDEFLT/address1' => 'strasse',
	'empInfo/personNav/homeAddressNavDEFLT/addressType' => 'name'
);

$config['fieldmappings']['fromsapsf']['User']['adresse'] = $adressmappings;
$config['fieldmappings']['fromsapsf']['User']['nebenadresse'] = $adressmappings;

$stundensatztyp = array(
	'empInfo/compInfoNav/empPayCompRecurringNav/payComponent' => 'sap_stundensatz_typ',
	'empInfo/compInfoNav/empPayCompRecurringNav/startDate' => 'sap_stundensatz_startdate'
	// not synced, just needed to get correct stundensatz to sync
	// MUST BE PLACED BEFORE STUNDENSATZ so it's populated before!
);

$config['fieldmappings']['fromsapsf']['User']['sap_stundensatz_typ'] = $stundensatztyp;

$config['fieldmappings']['fromsapsf']['User']['mitarbeiter'] = array(
	'userId' => 'mitarbeiter_uid',
	'empInfo/personNav/customString1' => 'personalnummer',
	'empInfo/compInfoNav/empPayCompRecurringNav/paycompvalue' => 'stundensatz',
	'empInfo/jobInfoNav/customString11Nav/externalCode' => 'lektor',
	'empInfo/jobInfoNav/customString12Nav/externalCode' => 'bismelden',
	'empInfo/personNav/personalInfoNav/customString14Nav/externalCode' => 'ausbildungcode',
	'empInfo/jobInfoNav/employeeTypeNav/externalCode' => 'fixangestellt',
	'empInfo/jobInfoNav/location' => 'standort_id'
);

$config['fieldmappings']['fromsapsf']['User']['sapaktiv'] = array(
	'status' => 'sapaktiv',
	'empInfo/jobInfoNav/startDate' => 'sapstartdatum'
	// not synced, just needed to set aktiv in fas
	// MUST BE PLACED BEFORE benutzer so it's populated before!
);

$config['fieldmappings']['fromsapsf']['User']['benutzer'] = array(
	'userId' => 'uid',
	'empInfo/jobInfoNav/customString13Nav/externalCode' => 'aktiv'
);

/*$config['fieldmappings']['fromsapsf']['User']['bisverwendung'] = array(
	'empInfo/personNav/personalInfoNav/customString16Nav/externalCode' => 'habilitation',
	'empInfo/personNav/personalInfoNav/customStringxNav/externalCode' => 'hautpberuf'
);*/

$config['fieldmappings']['fromsapsf']['CostCenter']['benutzerfunktion'] = array(
	'userId' => 'mitarbeiter_uid',
	'empInfo/jobInfoNav/costCenter' => 'oe_kurzbz',
	//'empInfo/jobInfoNav/businessUnit' => '',
	'empInfo/jobInfoNav/startDate' => 'datum_von',
	'empInfo/jobInfoNav/endDate' => 'datum_bis'
);

$config['fieldmappings']['fromsapsf']['CostCenter']['sapaktiv'] = array(
	'status' => 'sapaktiv',
	'empInfo/personNav/customString1' => 'personalnummer'
);

$config['fieldmappings']['fromsapsf']['HourlyRate']['sap_stundensatz_typ'] = $stundensatztyp;

$config['fieldmappings']['fromsapsf']['HourlyRate']['sap_kalkulatorischer_stundensatz'] = array(
	'userId' => 'mitarbeiter_uid',
	'empInfo/compInfoNav/empPayCompRecurringNav/paycompvalue' => 'sap_kalkulatorischer_stundensatz'
);

$config['fieldmappings']['tosapsf']['kontakttel']['PerPhone'] = array(
	'firmentelefon_vorwahl' => 'countryCode', // only for getting sf name, overwritten by phone
	'firmentelefon_ortsvorwahl' => 'areaCode', // only for getting sf name, overwritten by phone
	'firmentelefon_nummer' => 'phoneNumber',
	'firmentelefon_telefonklappe' => 'extension'
);

$config['fieldmappings']['tosapsf']['kontakttelmobile']['PerPhone'] = array(
	'firmenhandy' => 'phoneNumber'
);

$config['fieldmappings']['tosapsf']['kontaktmail']['PerEmail'] = array(
	'uid' => 'emailAddress' // email is generated from alias, which is derived from the uid
);

$config['fieldmappings']['tosapsf']['kontaktmailtech']['PerEmail'] = array(
	'uid' => 'emailAddress' // email is generated from uid
);

$config['fieldmappings']['tosapsf']['mitarbeiter']['PerPersonal'] = array(
	'ort_kurzbz' => 'customString4'
);
