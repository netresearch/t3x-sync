<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Traits;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * DatabaseConnectionTrait.
 *
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
trait DatabaseConnectionTrait
{
    /**
     * Returns an instance of the connection pool.
     *
     * @return ConnectionPool
     */
    private function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * Returns the database connection for a given table.
     *
     * @param string $table The name of the table to get the connection for
     *
     * @return Connection
     */
    private function getDatabaseConnectionForTable(string $table): Connection
    {
        return $this
            ->getConnectionPool()
            ->getConnectionForTable($table);
    }

    /**
     * Returns the query builder for a given table name without any restrictions from TCA.
     *
     * @param string $table The name of table to get the query builder for
     *
     * @return QueryBuilder
     */
    private function getQueryBuilderForTable(string $table): QueryBuilder
    {
        $queryBuilder = $this
            ->getDatabaseConnectionForTable($table)
            ->createQueryBuilder();

        $queryBuilder
            ->getRestrictions()
            ->removeAll();

        return $queryBuilder;
    }

    /**
     * Returns the default database connection.
     *
     * @return Connection
     *
     * @throws Exception
     */
    private function getDefaultDatabaseConnection(): Connection
    {
        return $this
            ->getConnectionPool()
            ->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
    }
}
