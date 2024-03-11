<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Netresearch\Sync\Service\ClearCache;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die('Access denied.');

call_user_func(static function () {
    ExtensionManagementUtility::addService(
        'nr_sync',
        'nrClearCache',
        ClearCache::class,
        [
            'title'       => 'NrSync Cache Clear Service',
            'description' => 'Clears the cache of given tables',
            'subtype'     => '',
            'available'   => true,
            'priority'    => 50,
            'quality'     => 50,
            'os'          => '',
            'exec'        => '',
            'className'   => ClearCache::class,
        ]
    );
});
