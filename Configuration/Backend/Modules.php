<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\Sync\Controller\SyncModuleController;

return [
    'web_NrSyncAdministration' => [
        'parent'                                   => 'web',
        'position'                                 => [],
        'access'                                   => 'user,group',
        'iconIdentifier'                           => 'tx-sync-module-web',
        'path'                                     => '/module/web/SyncAdministration',
        'labels'                                   => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang.xlf',
        'extensionName'                            => 'NrSync',
        'inheritNavigationComponentFromMainModule' => false,
        'navigationComponentId'                    => '',
        'controllerActions'                        => [
            SyncModuleController::class => [
                'main',
            ],
        ],
    ],
];
