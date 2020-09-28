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

$config['valuemappings']['fromsapsf']['User']['sapaktiv']['sapaktiv'] = array(
	't' => true,
	'f' => false
);

$yesnofield = array('N' => false, 'Y' => true);

$config['valuemappings']['fromsapsf']['User']['benutzer']['aktiv'] = $yesnofield;
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

$config['valuemappings']['fromsapsf']['User']['mitarbeiter']['fixangestellt'] = array(
	'S' => true,
	'N' => false,
	'E' => false,
	'H' => false,
	'F' => false
);

$config['valuemappings']['fromsapsf']['User']['mitarbeiter']['standort_id'] = array(
	'100200' => 3, // technikum höchstädtplatz
	'100001' => 4, // mariahilfer str gst
	'100300' => 5361, // energy base
	'200000' => 4640 // gmbh
);

$adresstypmapping = array(
	'Hauptwohnsitz' => 'h',
	'Nebenwohnsitz' => 'n'
);

$config['valuemappings']['fromsapsf']['User']['adressedaten']['typ'] = $adresstypmapping;

/*$config['valuemappings']['fromsapsf']['User']['mitarbeiter']['habilitation'] = $yesnofield;*/
