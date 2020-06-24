<?php
/*$config['fhcdefaults']['employee']['kontakttel'] = array(
	'kontakttyp' => 'telefon'
);*/

$config['fhcdefaults']['User']['kontaktmail'] = array(
	'kontakttyp' => 'email',
	'zustellung' => true
);

$config['fhcdefaults']['User']['kontaktnotfall'] = array(
	'kontakt' => '',
	'kontakttyp' => 'notfallkontakt',
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

// Business Mail
$config['sapsfdefaults']['kontaktmail']['PerEmail'] = array(
	'emailType' => 28799,//2470,
	'isPrimary' => true
);

// Private Mail
$config['sapsfdefaults']['kontaktmailprivate']['PerEmail'] = array(
	'emailType' => 28800,
	'isPrimary' => false
);

// Business Phone
$config['sapsfdefaults']['kontakttel']['PerPhone'] = array(
	'phoneType' => 28489,
	'isPrimary' => true
);
