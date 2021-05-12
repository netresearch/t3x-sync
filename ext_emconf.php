<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "nr_sync".
 *
 * Auto generated 13-11-2020 09:00
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Netresearch Sync',
    'description' => 'Sync CMS content',
    'version' => '0.11.1',
    'category' => 'module',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Netresearch\\Sync\\' => 'Classes'
        ],
    ],
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearCacheOnLoad' => true,
    'author' => 'Sebastian Mendel, Tobias Hein, Rico Sonntag, Thomas Schöne, Axel Seemann',
    'author_email' => 'sebastian.mendel@netresearch.de, tobias.hein@netresearch.de, rico.sonntag@netresearch.de, thomas.schoene@netresearch.de, axel.seemann@netresearch.de',
    'author_company' => 'Netresearch GmbH & Co. KG',
    '_md5_values_when_last_written' => '',
];
