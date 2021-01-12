<?php
/**
 * Fields for sync.
 */

/**
 * If required fields are not present in the object, sync is not possible and it is an error.
 * type (no type given - assuming string), foreign key references (ref), unique constraints (unique) are also checked
 * "name" is display name for errors
 **/

$config['fhcfields']['User'] = array(
	'person' => array(
		'vorname' => array('required' => true),
		'nachname' => array('required' => true),
		'geschlecht' => array('required' => true),
		'staatsbuergerschaft' =>
			array('ref' => 'bis.tbl_nation',
			'reffield' => 'nation_code'),
		'geburtsnation' =>
			array('ref' => 'bis.tbl_nation',
				'reffield' => 'nation_code'),
		'gebdatum' =>
			array('name' => 'Geburtsdatum',
			'type' => 'date'),
		'svnr' =>
			array('length' => 10), // has to be exactly 10 chars
		'ersatzkennzeichen' =>
			array('unique' => true,
				'pk' => 'person_id')
	),
	'mitarbeiter' => array(
		'mitarbeiter_uid' =>
			array('required' => true),
		'personalnummer' =>
			array('required' => true,
				'unique' => true,
				'pk' => 'mitarbeiter_uid',
				'type' => 'integer'),
		'fixangestellt' =>
			array('type' => 'boolean', 'required' => true),
		'lektor' =>
			array('type' => 'boolean', 'required' => true),
		'bismelden' =>
			array('type' => 'boolean', 'required' => true),
		'stundensatz' =>
			array('type' => 'integer'),
		'ausbildungcode' =>
			array('type' => 'integer',
				'ref' => 'bis.tbl_ausbildung'),
		'standort_id' =>
			array('name' => 'Standort',
				'type' => 'integer')
	),
	'benutzer' => array(
		'uid' => array('required' => true),
		'aktiv' => array('required' => true,
						'type' => 'boolean')
	),
	'kontaktmail' => array('kontakt' =>
		array(
			'required' => true,
			'name' => 'E-Mail-Adresse'
		)
	),
	'kontakttelefon' => array('kontakt' =>
		array(
			'name' => 'Telefonkontakt',
			'notnull' => true
		)
	),
	/*'kontakttelmobile' => array('kontakt' =>
		array(
			'name' => 'Telefonmobilkontakt',
			'notnull' => true
		),
	),*/
	'kontaktnotfall' => array('kontakt' =>
		array(
			'name' => 'Notfallkontakt',
			'notnull' => true
		)
	),
	'adresse' => array(
		'strasse' =>
		array(
			'name' => 'Hauptadresse',
			'notnull' => true
		),
		'nation' =>
			array(
				'name' => 'Hauptadressenation',
				'notnull' => true
		)
	),
	'nebenadresse' => array(
		'strasse' =>
			array(
				'name' => 'Nebenadresse',
				'notnull' => true
			),
		'nation' =>
			array(
				'name' => 'Nebenadressenation',
				'notnull' => true
			)
	)
);

$config['fhcfields']['CostCenter'] = array(
	'benutzerfunktion' => array(
		'oe_kurzbz' =>
			array('required' => true,
				'name' => 'Organisationseinheit',
				'ref' => 'public.tbl_organisationseinheit'),
		'datum_von' =>
			array('required' => true,
				'name' => 'Startdatum',
				'type' => 'date'),
		'uid' =>
			array('required' => true,
				'ref' => 'public.tbl_benutzer')
	)
);

$emailfield = 'kontaktmail/emailAddress';

// required sapsf fields, excluded from sync if not present
$config['requiredsapsffields']['PerEmail']['kontaktmail'] = array(
	$emailfield
);

$config['requiredsapsffields']['PerEmail']['kontaktmailprivate'] = array(
	$emailfield
);

$phonefields = array(
	'kontakttel/phoneNumber',
	'kontakttel/countryCode',
	'kontakttel/areaCode',
	'kontakttel/extension'
);

$phonefields_kontakttel = $phonefields;

$config['requiredsapsffields']['PerPhone']['kontakttel'] = $phonefields_kontakttel;

$config['requiredsapsffields']['PerPhone']['kontakttelmobile'] = array(
	'kontakttel/phoneNumber',
	'kontakttelmobile/phoneNumber',
	'kontakttelmobile/countryCode',
	'kontakttelmobile/areaCode'
);

$config['requiredsapsffields']['PerPhone']['kontakttelprivate'] = $phonefields;

// entity predicate value ~ primary keys for SAPSF
$config['sapsfpredicates']['User'] = array(
	'userId'
);

$config['sapsfpredicates']['PerEmail'] = array(
	'personIdExternal',
	'emailType'
);

$config['sapsfpredicates']['PerPhone'] = array(
	'personIdExternal',
	'phoneType'
);

$config['sapsfpredicates']['PerPersonal'] = array(// for office
	'personIdExternal',
	'startDate'
);

// navigation fields - for syncing to sapsf, where to find the field?
$config['sapsfnavigationfields']['PerPersonal'] = array(
	'customString4' => 'empInfo/personNav/personalInfoNav'
);

// fields to be checked for lastModifiedDate in GET query
$config['sapsflastmodifiedfields'] = array(
	"empInfo/personNav",
	"empInfo/personNav/nationalIdNav",
	"empInfo/personNav/emailNav",
	"empInfo/personNav/phoneNav",
	"empInfo/personNav/emergencyContactNav"
);

// fields to be checked for start date in GET query
$config['sapsfstartdatefields'] = array(
	"empInfo/personNav/personalInfoNav",
	"empInfo/jobInfoNav",
	"empInfo/compInfoNav/empPayCompRecurringNav",
	"empInfo/personNav/homeAddressNavDEFLT"
);

$config['timebasedfieldexceptions'] = array(
	'empInfo/jobInfoNav/costCenter',
	//'empInfo/jobInfoNav/businessUnit',
	'empInfo/jobInfoNav/startDate',
	'empInfo/jobInfoNav/endDate'
);

// fields which are not only retrieved by start date (time-based), but also by type
// [sapsffieldname] => [sapsftypefieldname]
$config['sapsftypetimebasedfields'] = array(
	'empInfo/compInfoNav/empPayCompRecurringNav/paycompvalue' => 'payComponent',
	'empInfo/compInfoNav/empPayCompRecurringNav/payComponent' => 'payComponent',
	'empInfo/personNav/homeAddressNavDEFLT/addressType' => 'addressType',
	'empInfo/personNav/homeAddressNavDEFLT/country' => 'addressType',
	'empInfo/personNav/homeAddressNavDEFLT/zipCode' => 'addressType',
	'empInfo/personNav/homeAddressNavDEFLT/city' => 'addressType',
	'empInfo/personNav/homeAddressNavDEFLT/address1' => 'addressType',
	'empInfo/personNav/homeAddressNavDEFLT/address2' => 'addressType'
);
