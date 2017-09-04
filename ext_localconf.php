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

/*
$TYPO3_CONF_VARS['SC_OPTIONS']['GLOBAL']['cliKeys'][$_EXTKEY]
    = array('EXT:' . $_EXTKEY . '/cli/nr_sync.php', '_CLI_nrsync');

$TYPO3_CONF_VARS['FE']['eID_include'][$_EXTKEY]
    = 'EXT:' . $_EXTKEY . '/eid/nr_sync.php';

t3lib_extMgm::addService(
    $_EXTKEY,
    'nrClearCache',
    'Netresearch\Sync\Service\clearCache',
    array(

        'title' => 'NrSync Cache löschen',
        'description' => 'Clears the cache of given tables',

        'subtype' => '',

        'available' => true,
        'priority' => 50,
        'quality' => 50,

        'os' => '',
        'exec' => '',

        'classFile' => t3lib_extMgm::extPath($_EXTKEY)
            . 'sv/clearCache.php',
        'className' => 'Netresearch\Sync\Service\clearCache',
    )
);
*/
