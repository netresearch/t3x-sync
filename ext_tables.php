<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

defined('TYPO3_MODE') || die();

call_user_func(static function () {

    if (TYPO3_MODE === 'BE') {
        // Add module
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'NrSync',
            'web',
            'administration',
            '',
            [
                \Netresearch\Sync\Controller\SyncModuleController::class => 'main',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:nr_sync/Resources/Public/Icons/Extension.svg',
                'labels' => 'LLL:EXT:nr_sync/Resources/Private/Language/locallang.xlf',
                'path' => '/module/web/SyncAdministration/',
            ]
        );

        // Add context sensitive help (csh) to the backend module
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr(
            '_MOD_web_SyncAdministration',
            'EXT:nr_sync/Resources/Private/Language/locallang.xlf'
        );
    }

});
