<?php
/**
 * Part of Nr_Sync package.
 * Holds Configuration
 *
 * PHP version 5
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Alexander Opitz <alexander.opitz@netresearch.de>
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */

$MCONF['name']   = 'system_txnrsyncM1';
$MCONF['access'] = 'user,group';
$MCONF['script'] = '_DISPATCH';

$MLANG['default']['tabs_images']['tab'] = 'moduleicon.gif';
$MLANG['default']['ll_ref'] = 'LLL:EXT:nr_sync/mod1/locallang_mod.php';

if ('netresearch' === $_SERVER['AIDA_ENV']) {

} elseif ('cmstest' === $_SERVER['AIDA_ENV']) {

} elseif ('cmsprod' === $_SERVER['AIDA_ENV']) {

} else {
    // LIVE und alle anderen
    define('TYPO3_TX_NR_SYNC_DISABLED', true);
}


/**
 * @TODO: Doku der Konfig
 */

$AREA = array(
    0     => array(
        'name'                 => 'AIDA',
        'description'          => 'Sync mit Live Server',
        'not_doctype'          => array(199),
        'system'               => array(
            'LIVE-AWS' => array(
                'directory' => 'aida-aws-live',
                'notify'    => array(
                    'type'     => 'ftp',
                    'host'     => 'uzsync11.aida.de',
                    'user'     => 'aida-aws-prod-typo62',
                    'password' => 'Thu2phoh',
                ),
                'report_error' => true,
            ),
            'ITG-AWS'  => array(
                'directory' => 'aida-aws-itg',
                'notify'    => array(
                    'type'     => 'ftp',
                    'host'     => 'uzsync11.aida.de',
                    'user'     => 'aida-aws-itg-typo62',
                    'password' => 'zo6Aelow',
                ),
                'report_error' => true,
            ),
            'archive'  => array(
                'directory' => 'archive',
                'notify'    => array(
                    'type'     => 'none',
                ),
            ),
        ),
        'inform_server'        => true,
        'sync_fe_groups'       => true,
        'sync_be_groups'       => true,
        'sync_tables'          => true,
    ),
);

if (isset($_SERVER['AIDA_ENV']) && 'cmsprod' === $_SERVER['AIDA_ENV']) {
    //FTP Notify nur auf cmsprod
    $AREA[0]['inform_server'] = true;
}
