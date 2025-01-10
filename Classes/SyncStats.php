<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Class SyncStats.
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
    private readonly ConnectionPool $connectionPool;

    /**
     * @var string[]
     */
    private readonly array $tables;

    /**
     * SyncStats constructor.
     *
     * @param ConnectionPool $connectionPool
     * @param string[]       $tables         Table names
     */
    public function __construct(
        ConnectionPool $connectionPool,
        array $tables,
    ) {
        $this->connectionPool = $connectionPool;
        $this->tables         = $tables;
    }

    /**
     * Returns table sync statistics.
     *
     * @return array<string, array<string, int|string>>
     *
     * @throws Exception
     */
    public function getSyncStats(): array
    {
        $connection   = $this->connectionPool->getConnectionForTable('tx_nrsync_syncstat');
        $queryBuilder = $connection->createQueryBuilder();

        /** @var array{incr: int, full: int, cruser_id: int} $row */
        $row = $queryBuilder
            ->select('incr', 'full', 'cruser_id')
            ->from('tx_nrsync_syncstat')
            ->where(
                $queryBuilder->expr()->eq(
                    'tab',
                    $queryBuilder->quote('*')
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        $full          = $row['full'] ?? 0;
        $incr          = $row['incr'] ?? 0;
        $backendUserId = $row['cruser_id'] ?? 0;

        $default = [
            'full'      => $full,
            'incr'      => $incr,
            'last_time' => max($incr, $full),
            'last_type' => ($full > $incr ? 'full' : 'incr'),
            'last_user' => $backendUserId,
        ];

        $result = [];

        foreach ($this->tables as $table) {
            $result[$table] = $default;

            $queryBuilder = $connection->createQueryBuilder();

            /** @var array{incr: int, full: int, cruser_id: int} $row */
            $row = $queryBuilder
                ->select('incr', 'full', 'cruser_id')
                ->from('tx_nrsync_syncstat')
                ->where(
                    $queryBuilder->expr()->eq(
                        'tab',
                        $queryBuilder->quote($table)
                    )
                )
                ->executeQuery()
                ->fetchAssociative();

            $full          = $row['full'] ?? 0;
            $incr          = $row['incr'] ?? 0;
            $backendUserId = $row['cruser_id'] ?? 0;

            $resultRow = [
                'full'      => $full,
                'incr'      => $incr,
                'last_time' => max($incr, $full),
                'last_type' => ($full > $incr ? 'full' : 'incr'),
                'last_user' => $backendUserId,
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
