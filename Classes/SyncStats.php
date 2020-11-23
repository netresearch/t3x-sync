<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync;

use TYPO3\CMS\Core\Database\ConnectionPool;

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
     * @var ConnectionPool
     */
    private $connectionPool;

    /**
     * @var string[]
     */
    private array $tables;

    /**
     * SyncStats constructor.
     *
     * @param ConnectionPool $connectionPool
     * @param string[] $tables Table names
     */
    public function __construct(
        ConnectionPool $connectionPool,
        array $tables
    ) {
        $this->connectionPool = $connectionPool;
        $this->tables = $tables;
    }

    /**
     * Returns table sync statistics.
     *
     * @return array
     */
    public function getSyncStats(): array
    {
        $connection   = $this->connectionPool->getConnectionForTable('tx_nrsync_syncstat');
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
