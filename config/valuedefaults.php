<?php
/*$config['fhcdefaults']['employee']['kontakttel'] = array(
	'kontakttyp' => 'telefon'
);*/

$config['fhcdefaults']['User']['kontaktmail'] = array(
	'kontakttyp' => 'email',
	'zustellung' => true
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
