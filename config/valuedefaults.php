<?php
/*$config['fhcdefaults']['employee']['kontakttel'] = array(
	'kontakttyp' => 'telefon'
);*/

$config['fhcdefaults']['User']['kontaktmail'] = array(
	'kontakt' => '',
	'kontakttyp' => 'email',
	'zustellung' => true
);

$config['fhcdefaults']['User']['kontakttelefon'] = array(
	'kontakt' => '',
	'kontakttyp' => 'telefon',
	'zustellung' => true
);

$config['fhcdefaults']['User']['kontaktnotfall'] = array(
	'kontakt' => '',
	'kontakttyp' => 'notfallkontakt',
	'zustellung' => true
);

$config['fhcdefaults']['User']['mitarbeiter'] = array(
	'standort_id' => 3,
	'fixangestellt' => false
);

$config['fhcdefaults']['User']['person'] = array(
	'staatsbuergerschaft' => 'XXX',
	'geburtsnation' => 'XXX'
);

$config['fhcdefaults']['User']['kztyp'] = array(
	'svnr' => 'SocialSecurityNumber', // svnr/essn
	'ersatzkennzeichen' => 'ekz'
);

/*$config['fhcdefaults']['employee']['person'] = array(
	'geburtsnation' => 'email',
	'staatsbuergerschaft' => 'email'
);*/

// Business Mail
$config['sapsfdefaults']['kontaktmail']['PerEmail'] = array(
	'emailType' => 28799,//1404 / 28799
	'isPrimary' => true
);

// Private Mail
$config['sapsfdefaults']['kontaktmailprivate']['PerEmail'] = array(
	'emailType' => 28800,//1406 / 28800
	'isPrimary' => false
);

// Business Phone
$config['sapsfdefaults']['kontakttel']['PerPhone'] = array(
	'phoneType' => 28489,//2354 / 28489
	'isPrimary' => true
);

// Private Phone
$config['sapsfdefaults']['kontakttelprivate']['PerPhone'] = array(
	'phoneType' => 28491,//2358 / 28491
	'isPrimary' => false
);

// Mobile Phone
$config['sapsfdefaults']['kontakttelmobile']['PerPhone'] = array(
	'phoneType' => 28490,//2356 / 28490
	'isPrimary' => false
);
