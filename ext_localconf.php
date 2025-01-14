<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\Sync\Scheduler\SyncImportTask\AdditionalFieldsProvider;
use Netresearch\Sync\Scheduler\SyncImportTask\Task;
use Netresearch\Sync\Service\ClearCacheService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || exit('Access denied.');

call_user_func(static function (): void {
    ExtensionManagementUtility::addService(
        'nr_sync',
        'nrClearCache',
        ClearCacheService::class,
        [
            'title'       => 'NrSync cache clear service',
            'description' => 'Clears the cache of given tables',
            'subtype'     => '',
            'available'   => true,
            'priority'    => 50,
            'quality'     => 50,
            'os'          => '',
            'exec'        => '',
            'className'   => ClearCacheService::class,
        ]
    );

    // Add TypoScript automatically (to use it in backend modules)
    ExtensionManagementUtility::addTypoScript(
        'nr_sync',
        'constants',
        '@import "EXT:nr_sync/Configuration/TypoScript/constants.typoscript"'
    );

    ExtensionManagementUtility::addTypoScript(
        'nr_sync',
        'setup',
        '@import "EXT:nr_sync/Configuration/TypoScript/setup.typoscript"'
    );

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][Task::class] = [
        'extension'        => 'nr_sync',
        'title'            => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_scheduler.xlf:tx_nrcsync_scheduler_import.name',
        'description'      => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_scheduler.xlf:tx_nrcsync_scheduler_import.description',
        'additionalFields' => AdditionalFieldsProvider::class,
    ];
});
