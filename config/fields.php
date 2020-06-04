<?php
/**
 * Fields for sync.
 * If required fields are not present in the object, sync is not possible and it is an error.
 * type (no type given - assuming string), foreign key references (ref) are also checked
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

		//'anrede' => array(),
		'gebdatum' =>
			array('name' => 'Geburtsdatum',
			'type' => 'date'),
		'svnr' =>
			array('unique' => true,
				'pk' => 'person_id',
				'length' => 10)
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
			array('type' => 'boolean'),
		'lektor' =>
			array('type' => 'boolean'),
		'bismelden' =>
			array('type' => 'boolean'),
		'stundensatz' =>
			array('type' => 'integer'),
		'ausbildungcode' =>
			array('type' => 'integer',
				'ref' => 'bis.tbl_ausbildung')
	),
	'benutzer' => array('uid' => array('required' => true)),
/*	'adresse' => array('nation' => array('required' => true,

		'ref' => 'bis.tbl_nation',
		'reffield' => 'nation_code'),
		'ort' => array('required' => true),
		'strasse' => array('required' => true),
		'plz' => array('name' => 'Postleitzahl'),
		'gemeinde' => array()
	),*/
	'kontaktmail' => array('kontakt' => array('required' => true,
		'name' => 'E-Mail-Adresse')
	)
	/*'kontaktnotfall' => array('kontakt' => array('name' => 'Notfallkontakt')
	),*/
);

// entity predicate value ~ primary keys for SAPSF
$config['sapsfpredicates']['User'] = array(
	'userId'
);

$config['sapsfpredicates']['PerEmail'] = array(
	'personIdExternal',
	'emailType'
);

$config['sapsfpredicates']['PerPersonal'] = array(
	'personIdExternal',
	'startDate'
);
