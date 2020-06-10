<?php
/*$config['fhcdefaults']['employee']['kontakttel'] = array(
	'kontakttyp' => 'telefon'
);*/

$config['fhcdefaults']['User']['kontaktmail'] = array(
	'kontakttyp' => 'email',
	'zustellung' => true
);

$config['fhcdefaults']['User']['mitarbeiter'] = array(
	'standort_id' => 3
);

$config['fhcdefaults']['User']['person'] = array(
	'staatsbuergerschaft' => 'XXX',
	'geburtsnation' => 'XXX'
);

$config['fhcdefaults']['User']['kztyp'] = array(
	'svnr' => 'essn',
	'ersatzkennzeichen' => 'ekz'
);

/*$config['fhcdefaults']['employee']['person'] = array(
	'geburtsnation' => 'email',
	'staatsbuergerschaft' => 'email'
);*/

$config['sapsfdefaults']['benutzer']['PerEmail'] = array(
	'emailType' => 2470,
	'isPrimary' => true
);

$config['sapsfdefaults']['person']['PerEmail'] = array(
	'emailType' => 1404,
	'isPrimary' => false
);

$config['sapsfdefaults']['kontakttel']['PerPhone'] = array(
	'phoneType' => 2354,
	'isPrimary' => true
);
