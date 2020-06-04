<?php

$config['valuemappings']['fromsapsf']['User']['person']['geschlecht'] = array(
	'M' => 'm',
	'F' => 'w',
	'U' => 'u',
	'O' => 'x',
	'D' => 'u'
);

$config['valuemappings']['fromsapsf']['User']['person']['anrede'] = array(
	'MR' => 'Herr',
	'MRS' => 'Frau',
	'MS' => 'Frau',
	'Divers' => ''
);

$nations = array(
	'AGO' => 'AGL',
	'ALB' => 'AL',
	'AUT' => 'A',
	'BTN' => 'BHU',
	'DEU' => 'D',
	'DZA' => 'DZ',
	'ITA' => 'I',
	'ROU' => 'R'
);

$config['valuemappings']['fromsapsf']['User']['person']['geburtsnation'] = $nations;
$config['valuemappings']['fromsapsf']['User']['person']['staatsbuergerschaft'] = $nations;

$yesnofield = array('N' => false, 'Y' => true);

$config['valuemappings']['fromsapsf']['User']['mitarbeiter']['lektor'] = $yesnofield;
$config['valuemappings']['fromsapsf']['User']['mitarbeiter']['bismelden'] = $yesnofield;
$config['valuemappings']['fromsapsf']['User']['mitarbeiter']['ausbildungcode'] = array(
	'UnivMaster' => 2,
	'PhD' => 1,
	'FHMaster' => 3,
	'UnivBachelor' => 4,
	'FHBachelor' => 5,
	'tertiaer' => 7,
	'AHS' => 8,
	'BHS' => 9,
	'Pflichtschule' => 11,
	'AkadDiplom' => 6,
	'Lehrabschluss' => 10
);

/*$config['valuemappings']['fromsapsf']['User']['kontaktmail']['zustellung'] = array(
	1404 => true,
	2470 => false
);*/
/*$config['valuemappings']['fromsapsf']['User']['mitarbeiter']['habilitation'] = $yesnofield;*/
