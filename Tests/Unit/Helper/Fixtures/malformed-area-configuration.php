<?php

/*
 * This file is part of the package netresearch/nr-sync.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

/**
 * An area configuration with values of the wrong type, for AreaConfigurationTest.
 */
return [
    0 => [
        'name'          => 5,
        'description'   => 'All pages',
        'doctype'       => [1, '4', 'x'],
        'not_doctype'   => '3',
        'files_to_sync' => ['a.txt', 7],
        'system'        => [
            'live' => [
                'name'      => 'Live',
                'directory' => 'live',
                'notify'    => [
                    'type' => 'ftp',
                    'tls'  => false,
                ],
                'hide' => 1,
            ],
            'broken' => 'not an array',
            'stage'  => [
                'directory' => 'stage',
            ],
        ],
    ],
    7 => 'not an area',
];
