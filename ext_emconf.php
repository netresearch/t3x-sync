<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

$EM_CONF['nr_sync'] = [
    'title'          => 'Netresearch - TYPO3 Synchronization',
    'description'    => 'A module for synchronizing content from a production system to a single or multiple target systems.',
    'category'       => 'module',
    'author'         => 'Sebastian Mendel, Tobias Hein, Rico Sonntag, Thomas Schöne, Axel Seemann',
    'author_email'   => 'sebastian.mendel@netresearch.de, tobias.hein@netresearch.de, rico.sonntag@netresearch.de, thomas.schoene@netresearch.de, axel.seemann@netresearch.de',
    'author_company' => 'Netresearch DTT GmbH',
    'state'          => 'stable',
    'version'        => '1.0.4',
    'constraints'    => [
        'depends' => [
            'typo3' => '12.4.0-12.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
