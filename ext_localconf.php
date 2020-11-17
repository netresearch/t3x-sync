<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

defined('TYPO3_MODE') || die();

// Register icons
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \TYPO3\CMS\Core\Imaging\IconRegistry::class
);

$iconRegistry->registerIcon(
    'nr_sync_extension_icon',
    \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
    ['source' => 'EXT:nr_sync/Resources/Public/Icons/Extension.svg']
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
    'nr_sync',
    'nrClearCache' /* sv type */,
    'tx_nrsync_clearcache' /* sv key */,
    [

        'title' => 'NrSync Cache clear',
        'description' => 'Clears the cache of given tables',

        'subtype' => '',

        'available' => true,
        'priority' => 50,
        'quality' => 50,

        'os' => '',
        'exec' => '',

        'className' => Netresearch\Sync\Service\ClearCache::class,
    ]
);


$TYPO3_CONF_VARS['FE']['eID_include']['nr_sync'] = 'EXT:nr_sync/eid/nr_sync.php';
