<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SyncStats
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class SyncStats
{
    /**
     * @var string[]
     */
    private array $tables;

    /**
     * SyncStats constructor.
     *
     * @param string[] $tables Table names
     */
    public function __construct(array $tables)
    {
        $this->tables = $tables;
    }

    /**
     * Render sync stats for given tables.
     *
     * @return string
     */
    public function createTableSyncStats(): string
    {
        $content = '<h3>Table sync status:</h3>';
        $content .= '<div class="table-fit">';
        $content .= '<table class="table table-striped table-hover" id="ts-overview">';
        $content .= '<thead>';
        $content .= '<tr><th>Table</th><th>Last sync time</th><th>Last sync type</th><th>Last sync user</th></tr>';
        $content .= '</thead>';
        $content .= '<tbody>';

        foreach ($this->getSyncStats() as $table => $arTableSyncStats) {
            $content .= '<tr class="bgColor4">';
            $content .= '<td>';
            $content .= htmlspecialchars($table);
            $content .= '</td>';

            if ($arTableSyncStats['last_time']) {
                $content .= '<td>';
                $content .= $this->fmtTime($arTableSyncStats['last_time']);
                $content .= '</td>';
                $content .= '<td>';
                $content .= $arTableSyncStats['last_type'];
                $content .= '</td>';
                $content .= '<td>';
                $content .= $this->fmtUser($arTableSyncStats['last_user']);
                $content .= '</td>';
            } else {
                $content .= '<td colspan="3">n/a</td>';
            }

            $content .= '</tr>';
        }
        $content .= '</tbody>';
        $content .= '</table>';
        $content .= '</div>';

        return $content;
    }

    /**
     * Returns time formatted to be displayed in table sync stats.
     *
     * @param int $time Unix timestamp.
     *
     * @return string
     */
    private function fmtTime(int $time): string
    {
        if ($time) {
            return date('Y-m-d H:i:s', $time);
        }

        return 'n/a';
    }

    /**
     * Returns human readable user name.
     *
     * @param int $userId The User ID
     *
     * @return string
     */
    private function fmtUser(int $userId): string
    {
        if ($userId) {
            $arUser = $this->getBackendUser()->getRawUserByUid($userId);
            return $arUser['realName'] . ' #' . $userId;
        }

        if ($userId === 0) {
            return 'SYSTEM';
        }

        return 'UNKNOWN';
    }

    /**
     * @return BackendUserAuthentication
     */
    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Returns table sync statistics.
     *
     * @return array
     */
    private function getSyncStats(): array
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_nrsync_syncstat');

        $queryBuilder = $connection->createQueryBuilder();
        $row = $queryBuilder
            ->select('*')
            ->from('tx_nrsync_syncstat')
            ->where(
                $queryBuilder->expr()->eq('tab', $queryBuilder->quote('*'))
            )
            ->execute()
            ->fetchAssociative();

        $default = [
            'full' => $row['full'],
            'incr' => $row['incr'],
            'last_time' => max($row['incr'], $row['full']),
            'last_type' => ($row['full'] > $row['incr'] ? 'full' : 'incr'),
            'last_user' => $row['cruser_id'],
        ];

        $result = [];

        foreach ($this->tables as $table) {
            $result[$table] = $default;

            $queryBuilder = $connection->createQueryBuilder();
            $row = $queryBuilder->select('*')
                ->from('tx_nrsync_syncstat')
                ->where(
                    $queryBuilder->expr()->eq('tab', $queryBuilder->quote($table))
                )
                ->execute()
                ->fetchAssociative();

            $resultRow = [
                'full'      => $row['full'],
                'incr'      => $row['incr'],
                'last_time' => max($row['incr'], $row['full']),
                'last_type' => ($row['full'] > $row['incr'] ? 'full' : 'incr'),
                'last_user' => $row['cruser_id'],
            ];

            $result[$table] = [
                'full'      => max($resultRow['full'], $default['full']),
                'incr'      => max($resultRow['incr'], $default['incr']),
                'last_time' => max($resultRow['last_time'], $default['last_time']),
                'last_type' => $resultRow['last_time'] > $default['last_time'] ? $resultRow['last_type'] : $default['last_type'],
                'last_user' => $resultRow['last_time'] > $default['last_time'] ? $resultRow['last_user'] : $default['last_user'],
            ];
        }

        return $result;
    }
}
