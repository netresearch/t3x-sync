<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "nr_sync".
 *
 * Auto generated 29-08-2017 10:16
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Netresearch Sync',
	'description' => 'Sync CMS content',
	'category' => 'module',
	'author' => 'Sebastian Mendel, Alexander Opitz, Tobias Hein, André Hähnel, Christian Weiske, Michael Ablass, Rico Sonntag, Thomas Schöne, Martin Wunderlich. Dandy Umlauft. Axel Kummer. Steffen Paasch. Michael Kunze. Marian Pollzien. Mathias Uhlmann. Alexander Gunkel. René Schulze',
	'author_company' => 'Netresearch GmbH. & Co.KG',
	'author_email' => 'sebastian.mendel@netresearch.de, alexander.opitz@netresearch.de, tobias.hein@netresearch.de',
	'state' => 'alpha',
	'createDirs' => 'db',
	'constraints' => array(
		'depends' => array(
			'cms' => '6.2.0-8.7.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
);
