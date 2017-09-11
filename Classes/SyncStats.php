<?php
/**
 * Created by PhpStorm.
 * User: sebastian.mendel
 * Date: 2017-09-04
 * Time: 18:23
 */

namespace Netresearch\Sync;


use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SyncStats
{
    protected $tables = [];

    protected $content = '';

    /**
     * SyncStats constructor.
     *
     * @param array $arTables Table names
     */
    public function __construct(array $arTables)
    {
        $this->tables = (array) $arTables;
    }



    /**
     * Render sync stats for given tables.
     *
     * @return void
     */
    public function createTableSyncStats()
    {
        $this->content .= '<h3>Table sync status:</h3>';

        $this->content .= '<div class="table-fit">';
        $this->content .= '<table class="table table-striped table-hover" id="ts-overview">';
        $this->content .= '<thead>';
        $this->content .= '<tr><th>Table</th><th>Last sync time</th><th>Last sync type</th><th>Last sync user</th></tr>';
        $this->content .= '</thead>';
        $this->content .= '<tbody>';

        foreach ($this->getSyncStats() as $strTable => $arTableSyncStats) {

            $this->content .= '<tr class="bgColor4">';
            $this->content .= '<td>';
            $this->content .= htmlspecialchars($strTable);
            $this->content .= '</td>';

            if ($arTableSyncStats['last_time']) {
                $this->content .= '<td>';
                $this->content .= static::fmtTime($arTableSyncStats['last_time']);
                $this->content .= '</td>';
                $this->content .= '<td>';
                $this->content .= $arTableSyncStats['last_type'];
                $this->content .= '</td>';
                $this->content .= '<td>';
                $this->content .= static::fmtUser($arTableSyncStats['last_user']);
                $this->content .= '</td>';
            } else {
                $this->content .= '<td colspan="3">n/a</td>';
            }

            $this->content .= '</tr>';
        }
        $this->content .= '</tbody>';
        $this->content .= '</table>';
        $this->content .= '</div>';
    }







    /**
     * Returns time formatted to be displayed in table sync stats.
     *
     * @param integer $nTime Unix timestamp.
     *
     * @return string
     */
    protected static function fmtTime($nTime)
    {
        if ($nTime) {
            return date('Y-m-d H:i:s', $nTime);
        }

        return 'n/a';
    }



    /**
     * Returns human readable user name.
     *
     * @param integer $nUser USer ID.
     *
     * @return string
     */
    protected static function fmtUser($nUser)
    {
        /* @var $BE_USER \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        global $BE_USER;

        if ($nUser) {
            //return date('Y-m-d H:i:s', $nTime);
            $arUser = $BE_USER->getRawUserByUid($nUser);
            return $arUser['realName'] . ' #' . $nUser;
        } elseif ($nUser === 0) {
            return 'SYSTEM';
        } else {
            return 'UNKNOWN';
        }
    }



    public function getContent()
    {
        return $this->content;
    }



    /**
     * Returns table sync statistics.
     *
     * @throws Exception
     * @return array
     */
    protected function getSyncStats()
    {
        /* @var $connectionPool \TYPO3\CMS\Core\Database\ConnectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        /* @var $connection \TYPO3\CMS\Core\Database\Connection */
        $connection = $connectionPool->getConnectionForTable('tx_nrsync_syncstat');

        $arResult = array();

        $queryBuilder = $connection->createQueryBuilder();
        $arRow = $queryBuilder->select('*')
            ->from('tx_nrsync_syncstat')
            ->where(
                $queryBuilder->expr()->eq('tab', $queryBuilder->quote('*'))
            )
            ->execute()
            ->fetch();

        $arDefault = array(
            'full' => $arRow['full'],
            'incr' => $arRow['incr'],
            'last_time' => max($arRow['incr'], $arRow['full']),
            'last_type' => ($arRow['full'] > $arRow['incr'] ? 'full' : 'incr'),
            'last_user' => $arRow['cruser_id'],
        );

        foreach ($this->tables as $strTable) {

            $arResult[$strTable] = $arDefault;

            $queryBuilder = $connection->createQueryBuilder();
            $arRow = $queryBuilder->select('*')
                ->from('tx_nrsync_syncstat')
                ->where(
                    $queryBuilder->expr()->eq('tab', $queryBuilder->quote($strTable))
                )
                ->execute()
                ->fetch();

            $arResultRow = array(
                'full' => $arRow['full'],
                'incr' => $arRow['incr'],
                'last_time' => max($arRow['incr'], $arRow['full']),
                'last_type' => ($arRow['full'] > $arRow['incr'] ? 'full' : 'incr'),
                'last_user' => $arRow['cruser_id'],
            );

            $arResult[$strTable] = array(
                'full' => max($arResultRow['full'], $arDefault['full']),
                'incr' => max($arResultRow['incr'], $arDefault['incr']),
                'last_time' => max($arResultRow['last_time'], $arDefault['last_time']),
                'last_type' => (
                $arResultRow['last_time'] > $arDefault['last_time']
                    ? $arResultRow['last_type'] : $arDefault['last_type']
                ),
                'last_user' => (
                $arResultRow['last_time'] > $arDefault['last_time']
                    ? $arResultRow['last_user'] : $arDefault['last_user']
                ),
            );
        }

        return $arResult;
    }
}