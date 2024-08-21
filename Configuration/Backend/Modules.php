<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\Sync\Controller\AssetSyncModuleController;
use Netresearch\Sync\Controller\BaseSyncModuleController;
use Netresearch\Sync\Controller\FalSyncModuleController;
use Netresearch\Sync\Controller\NewsSyncModuleController;
use Netresearch\Sync\Controller\SinglePageSyncModuleController;
use Netresearch\Sync\Controller\TableStateSyncModuleController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// Caution, variable name must not exist within \TYPO3\CMS\Core\Package\AbstractServiceProvider::configureBackendModules
$backendModulesConfiguration = [
    'netresearch_module' => [
        'labels'         => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'extension-netresearch-module',
        'position'       => [
            'after' => 'web',
        ],
    ],
    'netresearch_sync' => [
        'parent'                                   => 'netresearch_module',
        'position'                                 => [],
        'access'                                   => 'user',
        'iconIdentifier'                           => 'extension-netresearch-sync',
        'path'                                     => '/module/netresearch/sync',
        'labels'                                   => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod_sync.xlf',
        'extensionName'                            => 'NrSync',
        'inheritNavigationComponentFromMainModule' => false,
        'navigationComponent'                      => '@typo3/backend/page-tree/page-tree-element',
    ],
    'netresearch_sync_singlePage' => [
        'parent'         => 'netresearch_sync',
        'access'         => 'user',
        'path'           => '/module/netresearch/sync/single-page',
        'iconIdentifier' => 'extension-netresearch-sync',
        'labels'         => [
            'title' => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod_sync.xlf:mod_singlePage',
        ],
        'routes' => [
            '_default' => [
                'target' => SinglePageSyncModuleController::class . '::indexAction',
            ],
        ],
        'moduleData' => [
            'dumpFile' => 'partly-pages.sql',
            'tables'   => [
                'pages',
                'sys_file_reference',
                'sys_template',
                'tt_content',
            ],
        ],
    ],
    'netresearch_sync_fal' => [
        'parent'         => 'netresearch_sync',
        'access'         => 'user',
        'path'           => '/module/netresearch/sync/fal',
        'iconIdentifier' => 'extension-netresearch-sync',
        'labels'         => [
            'title' => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod_sync.xlf:mod_fal',
        ],
        'routes' => [
            '_default' => [
                'target' => FalSyncModuleController::class . '::indexAction',
            ],
        ],
        'moduleData' => [
            'dumpFile' => 'fal.sql',
            'tables'   => [
                'sys_category',
                'sys_category_record_mm',
                'sys_file',
                'sys_file_metadata',
                'sys_file_reference',
                'sys_filemounts',
            ],
        ],
    ],
    'netresearch_sync_fe_groups' => [
        'parent'         => 'netresearch_sync',
        'access'         => 'user',
        'path'           => '/module/netresearch/sync/fe_groups',
        'iconIdentifier' => 'extension-netresearch-sync',
        'labels'         => [
            'title' => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod_sync.xlf:mod_fe_groups',
        ],
        'routes' => [
            '_default' => [
                'target' => BaseSyncModuleController::class . '::indexAction',
            ],
        ],
        'moduleData' => [
            'dumpFile' => 'fe_groups.sql',
            'tables'   => [
                'fe_groups',
            ],
        ],
    ],

    'netresearch_sync_redirect' => [
        'parent'         => 'netresearch_sync',
        'access'         => 'user',
        'path'           => '/module/netresearch/sync/redirect',
        'iconIdentifier' => 'extension-netresearch-sync',
        'labels'         => [
            'title' => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod_sync.xlf:mod_redirect',
        ],
        'routes' => [
            '_default' => [
                'target' => BaseSyncModuleController::class . '::indexAction',
            ],
        ],
        'moduleData' => [
            'dumpFile' => 'redirects.sql',
            'tables'   => [
                'sys_redirect',
            ],
            'clearCacheEntries' => [
                'pages|redirects',
            ],
        ],
    ],
    'netresearch_sync_tableState' => [
        'parent'         => 'netresearch_sync',
        'access'         => 'admin',
        'path'           => '/module/netresearch/sync/table-state',
        'iconIdentifier' => 'extension-netresearch-sync',
        'labels'         => [
            'title' => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod_sync.xlf:mod_tableState',
        ],
        'routes' => [
            '_default' => [
                'target' => TableStateSyncModuleController::class . '::indexAction',
            ],
        ],
        'moduleData' => [
            'target' => 'local',
        ],
    ],
    'netresearch_sync_asset' => [
        'parent'         => 'netresearch_sync',
        'access'         => 'admin',
        'path'           => '/module/netresearch/sync/asset',
        'iconIdentifier' => 'extension-netresearch-sync',
        'labels'         => [
            'title' => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod_sync.xlf:mod_asset',
        ],
        'routes' => [
            '_default' => [
                'target' => AssetSyncModuleController::class . '::indexAction',
            ],
        ],
    ],
    'netresearch_sync_be_users' => [
        'parent'         => 'netresearch_sync',
        'access'         => 'admin',
        'path'           => '/module/netresearch/sync/be_users',
        'iconIdentifier' => 'extension-netresearch-sync',
        'labels'         => [
            'title' => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod_sync.xlf:mod_be_users',
        ],
        'routes' => [
            '_default' => [
                'target' => BaseSyncModuleController::class . '::indexAction',
            ],
        ],
        'moduleData' => [
            'dumpFile' => 'be_users_groups.sql',
            'tables'   => [
                'be_groups',
                'be_users',
            ],
        ],
    ],
    'netresearch_sync_scheduler' => [
        'parent'         => 'netresearch_sync',
        'access'         => 'admin',
        'path'           => '/module/netresearch/sync/scheduler',
        'iconIdentifier' => 'extension-netresearch-sync',
        'labels'         => [
            'title' => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod_sync.xlf:mod_scheduler',
        ],
        'routes' => [
            '_default' => [
                'target' => BaseSyncModuleController::class . '::indexAction',
            ],
        ],
        'moduleData' => [
            'dumpFile' => 'scheduler.sql',
            'tables'   => [
                'tx_scheduler_task',
                'tx_scheduler_task_group',
            ],
        ],
    ],
];

// Add the news sync module only if the news extension is available
if (ExtensionManagementUtility::isLoaded('georgringer/news')) {
    $backendModulesConfiguration['netresearch_sync_news'] = [
        'parent'         => 'netresearch_sync',
        'access'         => 'user',
        'path'           => '/module/netresearch/sync/news',
        'iconIdentifier' => 'extension-netresearch-sync',
        'labels'         => [
            'title' => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod_sync.xlf:mod_news',
        ],
        'routes' => [
            '_default' => [
                'target' => NewsSyncModuleController::class . '::indexAction',
            ],
        ],
        'moduleData' => [
            'dumpFile' => 'news.sql',
            'tables'   => [
                'sys_category',
                'sys_category_record_mm',
                'sys_file_reference',
                'tx_news_domain_model_link',
                'tx_news_domain_model_news',
                'tx_news_domain_model_news_related_mm',
                'tx_news_domain_model_news_tag_mm',
                'tx_news_domain_model_news_ttcontent_mm',
                'tx_news_domain_model_tag',
            ],
        ],
    ];
}

return $backendModulesConfiguration;
