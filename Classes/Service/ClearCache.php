<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Service;

use TYPO3;
use TYPO3\CMS\Core\Service\AbstractService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Service clear cache for Netresearch Synchronisation
 *
 * @author  Alexander Opitz <alexander.opitz@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ClearCache extends AbstractService
{
    public $prefixId = __CLASS__;// Same as class name
    public $scriptRelPath = 'sv/clearCache.php';
    public $extKey = 'nr_sync';    // The extension key.

    /**
     * Calls the clear cache function of t3lib_TCEmain for every array entry
     *
     * @param array $arData Array for elements to clear cache from as "table:uid"
     *
     * @return void
     */
    public function clearCaches(array $arData): void
    {
        /** @var TYPO3\CMS\Core\DataHandling\DataHandler $tce */
        $tce = GeneralUtility::makeInstance(DataHandler::class);
        $tce->start([], []);

        foreach ($arData as $strData) {
            [$strTable, $uid] = explode(':', $strData);

            GeneralUtility::devLog(
                'Clear cache table: ' . $strTable . '; uid: ' . $uid, 'nr_sync',
                GeneralUtility::SYSLOG_SEVERITY_INFO
            );

            $tce->clear_cacheCmd((int)$uid);
        }
    }
}
