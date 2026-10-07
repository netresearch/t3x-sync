<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */


$EM_CONF['nr_sync'] = [
    'title'          => 'Content Synchronization',
    'description'    => 'Backend module that synchronizes content from a production system to one or more target systems.',
    'category'       => 'module',
    'author'         => 'Sebastian Mendel, Tobias Hein, Rico Sonntag, Thomas Schöne, Axel Seemann',
    'author_email'   => 'sebastian.mendel@netresearch.de, tobias.hein@netresearch.de, rico.sonntag@netresearch.de, thomas.schoene@netresearch.de, axel.seemann@netresearch.de',
    'author_company' => 'Netresearch DTT GmbH',
    'state'          => 'stable',
    'version'        => '2.0.0',
    'constraints'    => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
