<?php
/**
 * Extension config script
 *
 * PHP version 5
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Alexander Opitz <alexander.opitz@netresearch.de>
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */

defined('TYPO3_MODE') or die('Access denied.');

$TYPO3_CONF_VARS['FE']['eID_include'][$_EXTKEY]
    = 'EXT:' . $_EXTKEY . '/eid/nr_sync.php';

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
    $_EXTKEY,
    'nrClearCache' /* sv type */,
    'tx_nrsync_clearcache' /* sv key */,
    array(

        'title' => 'NrSync Cache clear',
        'description' => 'Clears the cache of given tables',

        'subtype' => '',

        'available' => true,
        'priority' => 50,
        'quality' => 50,

        'os' => '',
        'exec' => '',

        'className' => Netresearch\Sync\Service\clearCache::class,
    )
);