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
use Netresearch\Sync\Service\StorageService;
use Netresearch\Sync\Traits\FlashMessageTrait;
use Netresearch\Sync\Traits\TranslationTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function in_array;

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
    use FlashMessageTrait;
    use TranslationTrait;

    /**
     * The name of table.
     *
     * @var string
     */
    private readonly string $tableName;

    /**
     * The name of dump file.
     *
     * @var string
     */
    private string $strDumpFile;

    /**
     * Force a complete sync.
     *
     * @var bool
     */
    private bool $forceFullSync = false;

    /**
     * Add the --no-create-info option to the dump.
     *
     * @var bool
     */
    private bool $noCreateInfo = true;

    /**
     * Delete rows which are not used on live system (delete, disabled, endtime), default is true.
     *
     * @var bool
     */
    private bool $deleteObsoleteRows = true;

    /**
     * Array of Tables which should be synced with INSERT INTO REPLACE.
     *
     * @var string[]
     */
    private array $arTablesUsingReplacetatement = [
        'sys_file_metadata',
        'sys_file',
    ];

    /**
     * @var FileInterface|null
     */
    private ?FileInterface $dumpFile = null;

    /**
     * Constructor.
     *
     * @param string                         $tableName name of table
     * @param string                         $dumpFile  name of target dump file
     * @param array<string, int|bool|string> $options   additional options
     */
    public function __construct(
        string $tableName,
        string $dumpFile,
        array $options = []
    ) {
        $this->tableName   = $tableName;
        $this->strDumpFile = $dumpFile;

        if (isset($options['forceFullSync'])) {
            $this->forceFullSync = (bool) $options['forceFullSync'];
        }

        if (isset($options['deleteObsoleteRows'])) {
            $this->deleteObsoleteRows = (bool) $options['deleteObsoleteRows'];
        }

        if (isset($options['noCreateInfo'])) {
            $this->setNoCreateInfo($options['noCreateInfo']);
        }
    }

    /**
     * Returns the dumpfile.
     *
     * @return FileInterface
     */
    private function getDumpFile(): FileInterface
    {
        if ($this->dumpFile instanceof FileInterface) {
            return $this->dumpFile;
        }

        /** @var StorageService $storageService */
        $storageService = GeneralUtility::makeInstance(StorageService::class);
        $this->dumpFile = $storageService->getTempFolder()->getStorage()->getFile($this->strDumpFile);

        return $this->dumpFile;
    }

    /**
     * Append content to dump file.
     *
     * @param string $content Content to add to the dump file
     *
     * @return void
     */
    private function appendToDumpFile(string $content): void
    {
        $this->getDumpFile()->setContents(
            $this->getDumpFile()->getContents() .
            PHP_EOL . $content . PHP_EOL
        );
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
        return in_array(
            $this->tableName,
            $this->arTablesUsingReplacetatement,
            true
        );
    }

    /**
     * Write tables data to dump file.
     *
     * Options:
     *
     * forceFullSync: ignore last sync time and always do a full sync and
     *     no incremental sync
     *
     * @param string[]            $tables   Tables to dump
     * @param string              $dumpFile Target file for dump data
     * @param array<string, bool> $options  Additional options
     *
     * @throws AspectNotFoundException
     * @throws Exception
     */
    public static function writeDumps(
        array $tables,
        string $dumpFile,
        array $options = []
    ): void {
        /** @var Table[] $instances */
        $instances = [];

        foreach ($tables as $table) {
            $table = new Table($table, $dumpFile, $options);

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
     * @throws AspectNotFoundException
     * @throws Exception
     */
    public function writeDump(): void
    {
        if (!$this->forceFullSync && $this->hasTstampField()) {
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
        $this->addInfoMessage(
            $this->getLabel(
                'message.notify_skipped_table',
                [
                    '{table}' => $this->tableName,
                ]
            )
        );
    }

    /**
     * Returns row count affected for sync/dump.
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function hasUpdatedRows(): bool
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection     = $connectionPool->getConnectionForTable($this->tableName);

        $strWhere = $this->getDumpWhereCondition();

        if ($strWhere === '' || $strWhere === false) {
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

        $count = $queryBuilder
            ->count($tstampField)
            ->from($this->tableName)
            ->where($strWhere)
            ->executeQuery()
            ->fetchOne();

        return $count !== false && $count > 0;
    }

    /**
     * Fetches a list of every updatable entry that could be found.
     * If force full sync is set to true, this will return every entry found.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getUpdatableEntries(): array
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection     = $connectionPool->getConnectionForTable($this->tableName);
        $queryBuilder   = $connection->createQueryBuilder();

        $strWhere = '';

        if (!$this->forceFullSync && $this->hasTstampField()) {
            $strWhere = $this->getDumpWhereCondition();
        }

        $statement = $queryBuilder
            ->selectLiteral("GROUP_CONCAT(uid SEPARATOR ',') AS uid_list")
            ->from($this->tableName);

        if ($strWhere !== '' && $strWhere !== false) {
            $statement->where($strWhere);
        }

        $result = $statement->executeQuery()->fetchAllAssociative();
        $list   = [];

        if (isset($result['0']['uid_list']) && ($result['0']['uid_list'] !== '')) {
            $list = array_filter(explode(',', $result['0']['uid_list']));
        }

        $data = [];
        foreach ($list as $row) {
            $data[] = [
                'uid' => $row,
            ];
        }

        return $data;
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
        $connection     = $connectionPool->getConnectionForTable($this->tableName);

        $this->appendToDumpFile('

TRUNCATE TABLE ' . $this->tableName . ";\n\n");

        $strExec = 'mysqldump --host="' . $connection->getParams()['host'] . '"'
            . ' --user="' . $connection->getParams()['user'] . '"'
            . ' --password="' . $connection->getParams()['password'] . '"'
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
        $strExec .= ' --complete-insert --extended-insert --disable-keys --hex-blob ' . $connection->getDatabase() . ' ' . $this->tableName;

        $this->appendToDumpFile(shell_exec($strExec));
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
        $connection     = $connectionPool->getConnectionForTable($this->tableName);

        $strWhere = $this->getDumpWhereCondition();

        if ($strWhere === '' || $strWhere === false) {
            throw new Exception(
                'Could not get WHERE condition for tstamp field for table "'
                . $this->tableName . '".'
            );
        }

        $strExec = 'mysqldump --host="' . $connection->getParams()['host'] . '"'
            . ' --user="' . $connection->getParams()['user'] . '"'
            . ' --password="' . $connection->getParams()['password'] . '"'
            // do not drop tables here, we truncated them already
            . ' --skip-add-drop-table';

        if ($this->noCreateInfo) {
            // do not add CREATE TABLE
            $strExec .= ' --no-create-info';
        }

        // use INSERT with column names
        // - prevent errors due to differences in tables on live system
        $strExec .= ' --complete-insert --extended-insert --disable-keys --replace --hex-blob --where="' . $strWhere . '"'
            . ' ' . $connection->getDatabase() . ' ' . $this->tableName;

        $this->appendToDumpFile(shell_exec($strExec));
    }

    /**
     * Appends the Delete statement for obsolete rows to the
     * current temporary file of the table.
     */
    public function appendDeleteObsoleteRowsToFile(): void
    {
        $strSqlObsoleteRows = $this->getSqlDroppingObsoleteRows();

        if ($strSqlObsoleteRows === null || $strSqlObsoleteRows === '') {
            return;
        }

        $this->appendToDumpFile("\n\n-- Delete obsolete rows on target\n" . $strSqlObsoleteRows);
    }

    /**
     * Returns WHERE condition for table tstamp field or false.
     *
     * @return string|false
     *
     * @throws Exception
     */
    protected function getDumpWhereCondition(): bool|string
    {
        // load TCA and check for tstamp field
        $tableTstampField = $this->getTstampField();

        if ($tableTstampField === false) {
            return false;
        }

        $nTime = $this->getLastDumpTime();

        if ($nTime !== 0) {
            return $tableTstampField . ' > ' . $nTime;
        }

        return false;
    }

    /**
     * Returns table tstamp field - if defined, otherwise false.
     *
     * @return string|false
     */
    protected function getTstampField(): false|string
    {
        if (!isset($GLOBALS['TCA'][$this->tableName]['ctrl']['tstamp'])) {
            return false;
        }

        if ($GLOBALS['TCA'][$this->tableName]['ctrl']['tstamp'] === '') {
            return false;
        }

        return $GLOBALS['TCA'][$this->tableName]['ctrl']['tstamp'];
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
     * Returns time stamp for the last sync/dump of this table.
     *
     * @return int
     *
     * @throws Exception
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
            ->executeQuery()
            ->fetchAssociative();

        // DEFAULT: date of last full dump - facelift 2013
        $nTime = mktime(0, 0, 0, 1, 1, 2000);

        if ($arRow === [] || $arRow === false) {
            return $nTime;
        }

        $nTimeMaxRow = max($arRow['incr'], $arRow['full']);

        if ($nTimeMaxRow > 0) {
            return $nTimeMaxRow;
        }

        return $nTime;
    }

    /**
     * Sets time of last dump/sync for this table.
     *
     * @param int|null $nTime time of last table dump/sync
     * @param bool     $bIncr set time for last incremental or full dump/sync
     *
     * @throws Exception
     * @throws AspectNotFoundException
     */
    protected function setLastDumpTime(?int $nTime = null, bool $bIncr = true): void
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $connection = $connectionPool->getConnectionForTable('tx_nrsync_syncstat');

        global $BE_USER;

        if ($nTime === null) {
            $nTime = (int) GeneralUtility::makeInstance(Context::class)
                ->getPropertyFromAspect('date', 'timestamp');
        }

        if ($nTime === 0) {
            $nTime = time();
        }

        $strUpdateField = $bIncr ? 'incr' : 'full';
        $nUserId        = (int) $BE_USER->user['uid'];

        $connection->executeStatement(
            sprintf(
                'INSERT INTO tx_nrsync_syncstat (tab, %s, cruser_id) VALUES (%s, %s, %s)'
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
     * in context of there control fields (hidden,deleted,endtime).
     *
     * @return string|null
     */
    public function getSqlDroppingObsoleteRows(): ?string
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool  = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection      = $connectionPool->getConnectionForTable('tx_nrsync_syncstat');
        $arControlFields = $connection->quoteIdentifiers($this->getControlFieldsFromTcaByTableName());

        if ($arControlFields === []) {
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

        if ($arWhereClauseParts === []) {
            return null;
        }

        $strStatement .= ' WHERE ' . implode(' OR ', $arWhereClauseParts);

        return $strStatement . ';';
    }

    /**
     * Returns an array of key-values where the key is the key-name of the
     * control field, and the value is the name of the control field in the
     * current table object.
     *
     * @return array<string, string> An array with control field key and the name of the key field
     *                               in the current table
     */
    public function getControlFieldsFromTcaByTableName(): array
    {
        if (!isset($GLOBALS['TCA'][$this->tableName])) {
            return [];
        }

        $arControl      = $GLOBALS['TCA'][$this->tableName]['ctrl'];
        $arEnableFields = $arControl['enablecolumns'] ?? [];

        $arReturn = [];

        if (isset($arControl['delete'])) {
            $arReturn['delete'] = $arControl['delete'];
        }

        if (isset($arEnableFields['disabled'])) {
            $arReturn['disabled'] = $arEnableFields['disabled'];
        }

        if (isset($arEnableFields['endtime'])) {
            $arReturn['endtime'] = $arEnableFields['endtime'];
        }

        return $arReturn;
    }

    /**
     * Setter for noCreateInfo.
     *
     * @param bool $noCreateInfo TRUE to not add CREATE TABLE
     */
    public function setNoCreateInfo(bool $noCreateInfo): void
    {
        $this->noCreateInfo = $noCreateInfo;
    }
}
