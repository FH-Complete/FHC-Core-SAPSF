<?php
/**
 * Fields for sync.
 * If required fields are not present in the object, sync is not possible and it is an error.
 * type (no type given - assuming string), foreign key references (ref) are also checked
 * "name" is display name for errors
 */

$config['fhcfields']['employee'] = array(
	'person' => array(
		'vorname' => array('required' => true),
		'nachname' => array('required' => true),
		//'geschlecht' => array('required' => true),
		'staatsbuergerschaft' =>
			array('ref' => 'bis.tbl_nation',
			'reffield' => 'nation_code'),
		//'anrede' => array(),
		'gebdatum' =>
			array('name' => 'Geburtsdatum',
			'type' => 'date'),
		//'sprache' => array('ref' => 'public.tbl_sprache'),
		//'anmerkung' => array(),
		//'foto' => array('type' => 'base64')
	),
	'mitarbeiter' => array(
		'mitarbeiter_uid' =>
			array('required' => true),
		'personalnummer' =>
			array('required' => true,
				'type' => 'integer')
	),
	'benutzer' => array('uid' => array('required' => true)),
/*	'adresse' => array('nation' => array('required' => true,

		'ref' => 'bis.tbl_nation',
		'reffield' => 'nation_code'),
		'ort' => array('required' => true),
		'strasse' => array('required' => true),
		'plz' => array('name' => 'Postleitzahl'),
		'gemeinde' => array()
	),
	'kontaktmail' => array('kontakt' => array('required' => true,
		'name' => 'E-Mail-Adresse')
	),
	'kontaktnotfall' => array('kontakt' => array('name' => 'Notfallkontakt')
	),*/
	'kontakttel' => array('kontakt' => array('name' => 'Phone number')
	)
);

