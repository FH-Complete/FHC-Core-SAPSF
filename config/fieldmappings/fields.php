<?php
/**
 * Fields for sync.
 * If required fields are not present in the object, sync is not possible and it is an error.
 * type (no type given - assuming string), foreign key references (ref), unique constraints (unique) are also checked
 * "name" is display name for errors
 */

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
			array('length' => 10),
		'ersatzkennzeichen' =>
			array('unique' => true,
				'pk' => 'person_id')
		//'sprache' => array('ref' => 'public.tbl_sprache'),
		//'anmerkung' => array(),
		//'foto' => array('type' => 'base64')
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
/*	'adresse' => array('nation' => array('required' => true,

		'ref' => 'bis.tbl_nation',
		'reffield' => 'nation_code'),
		'ort' => array('required' => true),
		'strasse' => array('required' => true),
		'plz' => array('name' => 'Postleitzahl'),
		'gemeinde' => array()
	),*/
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
	'kontaktnotfall' => array('kontakt' =>
		array(
			'name' => 'Notfallkontakt',
			'notnull' => true
		)
	)
);

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

// fields to be checked for lastModifiedDate
$config['sapsflastmodifiedfields'] = array(
	"empInfo/personNav",
	"empInfo/personNav/nationalIdNav",
	"empInfo/personNav/emailNav",
	"empInfo/personNav/phoneNav",
	"empInfo/personNav/emergencyContactNav",
	"empInfo/personNav/personalInfoNav",
	"empInfo/jobInfoNav",
	"empInfo/compInfoNav/empPayCompRecurringNav"
);
