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
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function count;
use function is_array;

/**
 * Controls table sync/dump generation.
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class Table
{
    /**
     * The name of table.
     *
     * @var string
     */
    private $tableName;

    /**
     * The name of dump file.
     *
     * @var string
     */
    private $dumpFile;

    /**
     * Force a complete sync.
     *
     * @var bool
     */
    private $forceFullSync = false;

    /**
     * Add the --no-create-info option to the dump.
     *
     * @var bool
     */
    private $noCreateInfo = true;

    /**
     * Delete rows which are not used on live system (delete, disabled, endtime), default is true.
     *
     * @var bool
     */
    private $deleteObsoleteRows = true;

    /**
     * Constructor.
     *
     * @param string $tableName Name of table.
     * @param string $dumpFile  Name of target dump file.
     * @param array  $options Additional options.
     */
    public function __construct(
        string $tableName,
        string $dumpFile,
        array $options = []
    ) {
        $this->tableName = $tableName;
        $this->dumpFile  = $dumpFile;

        if (is_array($options)) {
            if (isset($options['forceFullSync'])) {
                $this->forceFullSync = (bool)$options['forceFullSync'];
            }

            if (isset($options['deleteObsoleteRows'])) {
                $this->deleteObsoleteRows = (bool)$options['deleteObsoleteRows'];
            }

            if (isset($options['noCreateInfo'])) {
                $this->setNoCreateInfo($options['noCreateInfo']);
            }
        }
    }

    /**
     * Returns true if REPLACE INTO instead fo INSERT INTO should be used.
     *
     * Currently for only one database REPLACE INTO is needed therefore the table name
     * is hardcoded.
     *
     * @see http://jira.aida.de/browse/TYPO-5566
     *
     * @return bool
     */
    protected function useReplace(): bool
    {
        return $this->tableName === 'sys_file_metadata';
    }

    /**
     * Write tables data to dump file.
     *
     * Options:
     *
     * forceFullSync: ignore last sync time and always do a full sync and
     *     no incremental sync
     *
     * @param string[] $tables Tables to dump
     * @param string $dumpFile Target file for dump data
     * @param array $options Additional options
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function writeDumps(
        array $tables,
        string $dumpFile,
        array $options = []
    ): void {
        /** @var Table[] $instances */
        $instances = [];

        foreach ($tables as $table) {
            $table = new static($table, $dumpFile, $options);

            $instances[] = $table;
            $table->writeDump();
        }

        // TYPO-206 append delete statements at the end of the table
        foreach ($instances as $table) {
            if ($table->deleteObsoleteRows) {
                $table->appendDeleteObsoleteRowsToFile();
            }
        }
    }

    /**
     * Write table data to dump file.
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function writeDump(): void
    {
        if ($this->forceFullSync === false && $this->hasTstampField()) {
            if ($this->hasUpdatedRows()) {
                $this->appendUpdateToFile();
                $this->setLastDumpTime();
            } else {
                $this->notifySkippedEmptyTable();
            }
        } else {
            $this->appendDumpToFile();
            $this->setLastDumpTime(null, false);
        }
    }

    /**
     * Adds flash message about skipped tables in sync.
     */
    protected function notifySkippedEmptyTable(): void
    {
        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            'Table "' . $this->tableName . '" skipped - no changes since last sync.',
            'Skipped table',
            FlashMessage::INFO
        );

        /** @var FlashMessageService $messageService */
        $messageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageService->getMessageQueueByIdentifier()->addMessage($message);
    }

    /**
     * Returns row count affected for sync/dump.
     *
     * @return int|false
     * @throws Exception
     */
    protected function hasUpdatedRows()
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $connection = $connectionPool->getConnectionForTable($this->tableName);

        $strWhere = $this->getDumpWhereCondition();

        if (empty($strWhere)) {
            throw new Exception(
                'Could not get WHERE condition for tstamp field for table "'
                . $this->tableName . '".'
            );
        }

        $queryBuilder = $connection->createQueryBuilder();

        $tstampField = 'tstamp';

        if ($this->tableName === 'sys_redirect') {
            $tstampField = 'updatedon';
        }

        return $queryBuilder
            ->count($tstampField)
            ->from($this->tableName)
            ->where($strWhere)
            ->execute()
            ->fetchOne();
    }

    /**
     * Fetches a list of every updatable entry that could be found.
     * If force full sync is set to true, this will return every entry found.
     *
     * @return array
     */
    public function getUpdatableEntries(): array
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection     = $connectionPool->getConnectionForTable($this->tableName);
        $queryBuilder   = $connection->createQueryBuilder();

        $strWhere = '';

        if ($this->forceFullSync === false && $this->hasTstampField()) {
            $strWhere = $this->getDumpWhereCondition();
        }

        $statement = $queryBuilder
            ->selectLiteral('GROUP_CONCAT(uid SEPARATOR \',\') AS uid_list')
            ->from($this->tableName);

        if (!empty($strWhere)) {
            $statement->where($strWhere);
        }

        $data = $statement->execute()->fetchAllAssociative();
        $list = $data['0']['uid_list'] ? array_filter(explode(',', $data['0']['uid_list'])) : [];

        $arData = [];
        foreach ($list as $row) {
            $arData[] = [
                'uid' => $row,
            ];
        }

        return $arData;
    }

    /**
     * Appends table dump data to file.
     *
     * Uses TRUNCATE TABLE instead of DROP
     *
     * @throws Exception
     */
    protected function appendDumpToFile(): void
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $connection = $connectionPool->getConnectionForTable($this->tableName);

        $r = file_put_contents(
            $this->dumpFile,
            "\n\n" . 'TRUNCATE TABLE ' . $this->tableName . ";\n\n",
            FILE_APPEND
        );

        if ($r === false) {
            throw new Exception('Could not write into dump file.');
        }

        $strExec = 'mysqldump -h' . $connection->getHost() . ' -u' . $connection->getUsername()
            . ' -p' . $connection->getPassword()
            // do not drop tables here, we truncated them already
            . ' --skip-add-drop-table';

        if ($this->noCreateInfo) {
            // do not add CREATE TABLE
            $strExec .= ' --no-create-info';
        }

        $bUseReplace = $this->useReplace();

        if ($bUseReplace) {
            $strExec .= ' --replace';
        }

        // use INSERT with column names
        // - prevent errors due to differences in tables on live system
        $strExec .= ' --complete-insert'
            // use more ROWS with every INSERT command
            // why was this set to FALSE?
            . ' --extended-insert'
            // Performance
            . ' --disable-keys'
            // export blobs as hex
            . ' --hex-blob'
            . ' ' . $connection->getDatabase() . ' ' . $this->tableName . ' >> ' . $this->dumpFile;

        shell_exec($strExec);
    }

    /**
     * Appends table dump data updated since last dump/sync to file.
     *
     * Does not add DROP TABLE.
     * Uses REPLACE instead of INSERT.
     *
     * @throws Exception
     */
    protected function appendUpdateToFile(): void
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $connection = $connectionPool->getConnectionForTable($this->tableName);

        $strWhere = $this->getDumpWhereCondition();

        if (empty($strWhere)) {
            throw new Exception(
                'Could not get WHERE condition for tstamp field for table "'
                . $this->tableName . '".'
            );
        }

        $strExec = 'mysqldump -h' . $connection->getHost() . ' -u' . $connection->getUsername()
            . ' -p' . $connection->getPassword()
            // do not drop tables here, we truncated them already
            . ' --skip-add-drop-table';

        if ($this->noCreateInfo) {
            // do not add CREATE TABLE
            $strExec .= ' --no-create-info';
        }
        // use INSERT with column names
        // - prevent errors due to differences in tables on live system
        $strExec .= ' --complete-insert'
            // use more ROWS with every INSERT command
            // why was this set to FALSE?
            . ' --extended-insert'
            // Performance
            . ' --disable-keys'
            //
            . ' --replace'
            // export blobs as hex
            . ' --hex-blob'
            . ' --where="' . $strWhere . '"'
            . ' ' . $connection->getDatabase() . ' ' . $this->tableName . ' >> ' . $this->dumpFile;

        shell_exec($strExec);
    }

    /**
     * Appends the Delete statement for obsolete rows to the
     * current temporary file of the table
     */
    public function appendDeleteObsoleteRowsToFile(): void
    {
        $strSqlObsoleteRows = $this->getSqlDroppingObsoleteRows();

        if (empty($strSqlObsoleteRows) === true) {
            return;
        }

        file_put_contents(
            $this->dumpFile,
            "\n\n-- Delete obsolete Rows on live, see: TYPO-206 \n"
            . $strSqlObsoleteRows,
            FILE_APPEND
        );
    }

    /**
     * Returns WHERE condition for table tstamp field or false.
     *
     * @return string|false
     */
    protected function getDumpWhereCondition()
    {
        // load TCA and check for tstamp field
        $tableTstampField = $this->getTstampField();

        if ($tableTstampField === false) {
            return false;
        }

        $nTime = $this->getLastDumpTime();

        if ($nTime) {
            return $tableTstampField . ' > ' . $nTime;
        }

        return false;
    }

    /**
     * Returns table tstamp field - if defined, otherwise false.
     *
     * @return string|false
     */
    protected function getTstampField()
    {
        if (!empty($GLOBALS['TCA'][$this->tableName]['ctrl']['tstamp'])) {
            return $GLOBALS['TCA'][$this->tableName]['ctrl']['tstamp'];
        }

        return false;
    }

    /**
     * Returns whether a table has tstamp field or not.
     *
     * @return bool
     */
    protected function hasTstampField(): bool
    {
        return $this->getTstampField() !== false;
    }

    /**
     * Returns time stamp for last sync/dump of this table
     *
     * @return int
     */
    protected function getLastDumpTime(): int
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection     = $connectionPool->getConnectionForTable('tx_nrsync_syncstat');
        $queryBuilder   = $connection->createQueryBuilder();

        $arRow = $queryBuilder
            ->selectLiteral(
                'max(' . $queryBuilder->quoteIdentifier('incr') . ') AS ' . $queryBuilder->quoteIdentifier('incr'),
                'max(' . $queryBuilder->quoteIdentifier('full') . ') AS ' . $queryBuilder->quoteIdentifier('full')
            )
            ->from('tx_nrsync_syncstat')
            ->where(
                $queryBuilder->expr()->in('tab', [$queryBuilder->quote('*'), $queryBuilder->quote($this->tableName)])
            )
            ->execute()
            ->fetchAssociative();

        // DEFAULT: date of last full dump - facelift 2013
        $nTime = mktime(0, 0, 0, 2, 1, 2013);

        if (empty($arRow)) {
            return $nTime;
        }

        $nTimeMaxRow = max($arRow['incr'], $arRow['full']);

        if ($nTimeMaxRow) {
            $nTime = $nTimeMaxRow;
        }

        return $nTime;
    }

    /**
     * Sets time of last dump/sync for this table.
     *
     * @param null|int $nTime Time of last table dump/sync.
     * @param bool $bIncr Set time for last incremental or full dump/sync.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    protected function setLastDumpTime(int $nTime = null, bool $bIncr = true): void
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $connection = $connectionPool->getConnectionForTable('tx_nrsync_syncstat');

        global $BE_USER;

        if ($nTime === null) {
            $nTime = (int) $GLOBALS['EXEC_TIME'];
        }

        if (!$nTime) {
            $nTime = time();
        }

        if ($bIncr) {
            $strUpdateField = 'incr';
        } else {
            $strUpdateField = 'full';
        }

        $nUserId = (int) $BE_USER->user['uid'];
        $nTime   = (int) $nTime;

        $connection->executeStatement(
            sprintf(
                'INSERT INTO tx_nrsync_syncstat (tab, %s, cruser_id)'
                . ' VALUES (%s, %s, %s)'
                . ' ON DUPLICATE KEY UPDATE cruser_id = %s, %s = %s',
                $strUpdateField,
                $connection->quote($this->tableName),
                $connection->quote($nTime),
                $connection->quote($nUserId),
                $connection->quote($nUserId),
                $strUpdateField,
                $connection->quote($nTime)
            )
        );
    }

    /**
     * Return a sql statement to drop rows from the table which are useless
     * in context of there control fields (hidden,deleted,endtime)
     *
     * @return null|string
     */
    public function getSqlDroppingObsoleteRows(): ?string
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection     = $connectionPool->getConnectionForTable('tx_nrsync_syncstat');
        $arControlFields = $this->getControlFieldsFromTcaByTableName();

        if (count($arControlFields) === 0) {
            return null;
        }

        // date to compare end timestamp with
        $nToday = strtotime(
            date('Y-m-d')
        );

        $strStatement = 'DELETE FROM '
            . $connection->quoteIdentifier($this->tableName);
        $arWhereClauseParts = [];
        if (isset($arControlFields['delete'])) {
            $arWhereClauseParts[] = $arControlFields['delete'] . ' = 1';
        }

        if (isset($arControlFields['disabled'])) {
            $arWhereClauseParts[] = $arControlFields['disabled'] . ' = 1';
        }

        if (isset($arControlFields['endtime'])) {
            $arWhereClauseParts[] = '('
                . $arControlFields['endtime'] . ' < ' . $nToday
                . ' AND '
                . $arControlFields['endtime'] . ' <> 0'
                . ')';
        }

        if (count($arWhereClauseParts) === 0) {
            return null;
        }

        $strStatement .= ' WHERE ' . implode(' OR ', $arWhereClauseParts);
        $strStatement .= ';';

        return $strStatement;
    }

    /**
     * Returns an array of key-values where the key is the key-name of the
     * control field and the value is the name of the controlfield in the
     * current table object.
     *
     * @return array An array with controlfield key and the name of the keyfield
     *               in the current table
     */
    public function getControlFieldsFromTcaByTableName(): array
    {
        if (!isset($GLOBALS['TCA'][$this->tableName])) {
            return [];
        }

        $arControl = $GLOBALS['TCA'][$this->tableName]['ctrl'];
        $arEnableFields = $arControl['enablecolumns'];

        $arReturn = [];

        if (!empty($arControl['delete'])) {
            $arReturn['delete'] = $arControl['delete'];
        }

        if (!empty($arEnableFields['disabled'])) {
            $arReturn['disabled'] = $arEnableFields['disabled'];
        }

        if (!empty($arEnableFields['endtime'])) {
            $arReturn['endtime'] = $arEnableFields['endtime'];
        }

        return $arReturn;
    }

    /**
     * Setter for noCreateInfo
     *
     * @param bool $noCreateInfo True if do not add CREATE TABLE
     */
    public function setNoCreateInfo(bool $noCreateInfo): void
    {
        $this->noCreateInfo = $noCreateInfo;
    }
}
