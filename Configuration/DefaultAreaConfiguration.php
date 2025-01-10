<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

/**
 * The default area configuration file. To customize, add a new file named "sync-area-configuration.php"
 * below the system/-folder, e.g., system/sync-area-configuration.php.
 */
return [
    // Refers to all pages (and their subpages) starting with page ID 0
    0 => [
        'name'        => 'Alle',
        'description' => 'Sync to all servers',
        'not_doctype' => [],
        'system'      => [
            'Production' => [
                'name'      => 'Production',
                'directory' => 'production',
                'url-path'  => 'production/url',
                'notify'    => [
                    'type' => 'none',
                ],
            ],
            'archive' => [
                'name'      => 'Archive',
                'directory' => 'archive',
                'url-path'  => 'archive/url',
                'notify'    => [
                    'type' => 'none',
                ],
                'hide' => true,
            ],
        ],
        'sync_fe_groups' => true,
        'sync_be_groups' => true,
        'sync_tables'    => true,
    ],
];
