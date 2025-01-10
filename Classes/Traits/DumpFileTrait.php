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
use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\PageSyncModuleInterface;
use Netresearch\Sync\Table;
use RuntimeException;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function count;
use function is_array;
use function sprintf;

/**
 * DumpFileTrait.
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
trait DumpFileTrait
{
    /**
     * @var int
     */
    public int $dumpTableRecursion = 0;

    /**
     * @var array<string, mixed>
     */
    private array $referenceTables = [];

    /**
     * Multidimensional array to save the lines put to the
     * current sync file for the current sync process
     * Structure
     * $globalSqlLineStorage[<statementtype>][<tablename>][<identifier>] = <statement>;.
     *
     * statementtypes: delete, insert
     * tablename:      name of the table the records belong to
     * identifier:     unique identifier like uid or a uique string
     *
     * @var array<string, array<string, mixed>>
     */
    private array $globalSqlLineStorage = [];

    /**
     * @var string[]
     */
    private array $obsoleteRows = [];

    /**
     * @param string $dumpFile
     *
     * @return void
     */
    private function performSync(string $dumpFile): void
    {
        if ($this->useSyncList) {
            $syncList = $this->getSyncList();

            if (!$syncList->isEmpty()) {
                $dumpFileArea = date(self::DATE_FORMAT . '_') . $dumpFile;

                /** @var int $areaId */
                foreach (array_keys($syncList->getAsArray()) as $areaId) {
                    /** @var Area $area */
                    $area = GeneralUtility::makeInstance(
                        Area::class,
                        $areaId,
                        $this->target
                    );

                    $pageIds = $syncList->getAllPageIDs($areaId);

                    $ret = $this->createShortDump(
                        $pageIds,
                        $this->getTables(),
                        $dumpFileArea,
                        $area->getDirectories()
                    );

                    if ($ret && $this->createClearCacheFile('pages', $pageIds)) {
                        $area->notifyMaster();

                        $this->addSuccessMessage(
                            $this->getLabel('success.sync_in_progress')
                        );

                        $syncList->emptyArea($areaId);
                        $syncList->saveSyncList();
                    }
                }
            }
        } else {
            $syncResult = $this->createDumpToAreas(
                $this->getTables(),
                $dumpFile
            );

            // Create page sync files for pages which have related pages.
            if ($syncResult
                && ($this instanceof PageSyncModuleInterface)
            ) {
                $this->createClearCacheFile(
                    'pages',
                    $this->getPagesToSync()
                );
            }

            $clearCacheEntries = $this->getClearCacheEntries();

            if ($syncResult
                && ($clearCacheEntries !== [])
            ) {
                // Clear required caches after sync
                $this->createClearCacheFile('framework', $clearCacheEntries);
            }

            if ($syncResult) {
                $this->addSuccessMessage(
                    $this->getLabel('success.sync_initiated')
                );
            }
        }
    }

    /**
     * Baut speziellen Dump zusammen, der nur die angewählten Pages enthält.
     * Es werden nur Pages gedumpt, zu denen der Redakteur auch Zugriff hat.
     *
     * @param int[]    $pageIDs  List if page IDs to dump
     * @param string[] $tables   List of tables to dump
     * @param string   $dumpFile Name of target dump file
     * @param string[] $arPath
     *
     * @return bool
     */
    private function createShortDump(
        array $pageIDs,
        array $tables,
        string $dumpFile,
        array $arPath,
    ): bool {
        if (count($pageIDs) <= 0) {
            $this->addErrorMessage($this->getLabel('error.no_pages_marked'));

            return false;
        }

        try {
            $fpDumpFile = $this->openTempDumpFile($dumpFile, $arPath);
        } catch (Exception $exception) {
            $this->addErrorMessage($exception->getMessage());

            return false;
        }

        foreach ($tables as $table) {
            $this->dumpTableByPageIDs($pageIDs, $table, $fpDumpFile);
        }

        // Append statement for delete unused rows in target environment
        $this->writeToDumpFile(
            [],
            [],
            $fpDumpFile,
            $this->getDeleteRowStatements()
        );

        $this->writeInsertLines($fpDumpFile);

        try {
            $this->finalizeDumpFile($dumpFile, $arPath);
        } catch (Exception $exception) {
            $this->addErrorMessage($exception->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function getDeleteRowStatements(): array
    {
        return $this->obsoleteRows;
    }

    /**
     * Writes all SQL Lines from globalSqlLineStorage['insert']
     * to the passed file stream.
     *
     * @param FileInterface $fpDumpFile the file to write the lines to
     *
     * @return void
     */
    private function writeInsertLines(FileInterface $fpDumpFile): void
    {
        if (!is_array($this->globalSqlLineStorage['insert'])) {
            return;
        }

        $content = $fpDumpFile->getContents();

        // Foreach table in insert array
        foreach ($this->globalSqlLineStorage['insert'] as $table => $tableInsLines) {
            if (count($tableInsLines) > 0) {
                $insertLines = '-- Insert lines for table: '
                    . $table . "\n"
                    . implode("\n", $tableInsLines);

                $content .= $insertLines . "\n\n";
            }
        }

        $fpDumpFile->setContents($content);
    }

    /**
     * Zips the tmp dump file and copy it to given directories.
     *
     * @param string   $dumpFileName The name of the dump file
     * @param string[] $directories  The directories to copy files into
     *
     * @return void
     *
     * @throws Exception If file can't be zipped
     */
    private function finalizeDumpFile(string $dumpFileName, array $directories): void
    {
        $tempFolder  = $this->storageService->getTempFolder();
        $tempStorage = $tempFolder->getStorage();

        // Compress file
        $dumpFile = $this->createGZipFile($tempFolder, $dumpFileName);

        if ($dumpFile === null) {
            throw new Exception('Could not create ZIP file.');
        }

        // Copy files to correct location
        foreach ($directories as $path) {
            if ($this->isSystemLocked($path) === true) {
                $this->addWarningMessage(
                    $this->getLabel(
                        'warning.system_locked',
                        [
                            '{system}' => $path,
                        ]
                    )
                );

                continue;
            }

            $folder = $this->storageService
                ->getSyncFolder()
                ->getSubfolder($path);

            $this->storageService
                ->getDefaultStorage()
                ->copyFile($dumpFile, $folder);
        }

        $tempStorage->deleteFile($dumpFile);
    }

    /**
     * Creating dump to areas.
     *
     * @param string[]    $tables     Table names
     * @param string      $filename   name of the dump file
     * @param string|null $targetName Target to create sync for
     *
     * @return bool
     */
    public function createDumpToAreas(
        array $tables,
        string $filename,
        ?string $targetName = null,
    ): bool {
        $tempFolder         = $this->storageService->getTempFolder();
        $tempFileIdentifier = $tempFolder->getIdentifier() . $filename;
        $tempStorage        = $tempFolder->getStorage();

        if ($tempStorage->hasFile($tempFileIdentifier)
            || $tempStorage->hasFile($tempFileIdentifier . '.gz')
        ) {
            $this->addErrorMessage(
                $this->getLabel('error.last_sync_not_finished')
            );

            return false;
        }

        $tempStorage->createFile($filename, $tempFolder);

        Table::writeDumps(
            $tables,
            $tempFileIdentifier,
            [
                'forceFullSync'      => isset($_POST['data']['force_full_sync']) && ($_POST['data']['force_full_sync'] === '1'),
                'deleteObsoleteRows' => isset($_POST['data']['delete_obsolete_rows']) && ($_POST['data']['delete_obsolete_rows'] === '1'),
            ]
        );

        $dumpFile = null;

        try {
            $dumpFile = $tempStorage->getFile($tempFileIdentifier);
        } catch (\Exception) {
            $this->addInfoMessage(
                $this->getLabel('info.no_data_dumped')
            );
        } finally {
            if ($dumpFile === null) {
                $this->addInfoMessage(
                    $this->getLabel('info.no_data_dumped')
                );

                return false;
            }
        }

        $compressedDumFile = $this->createGZipFile($tempFolder, $dumpFile->getName());

        if ($compressedDumFile === null) {
            $this->addErrorMessage(
                $this->getLabel(
                    'error.zip_failure',
                    [
                        '{file}' => $dumpFile->getIdentifier(),
                    ]
                )
            );

            return false;
        }

        $target         = $targetName ?? $this->target;
        $targetFilename = date(self::DATE_FORMAT . '_') . $filename;

        foreach (Area::getMatchingAreas($target) as $area) {
            foreach ($area->getDirectories() as $path) {
                if ($this->isSystemLocked($path)) {
                    $this->addWarningMessage($this->getLabel('warning.system_locked', ['{system}' => $path]));
                    continue;
                }

                $targetFolder = $this->storageService
                    ->getSyncFolder()
                    ->getSubfolder($path);

                try {
                    $this->storageService
                        ->getDefaultStorage()
                        ->copyFile($compressedDumFile, $targetFolder, $targetFilename . '.gz');
                } catch (\Exception) {
                    $this->addErrorMessage(
                        $this->getLabel(
                            'error.cannot_move_file',
                            [
                                '{file}'   => $compressedDumFile->getIdentifier(),
                                '{target}' => $targetFolder->getIdentifier() . $targetFilename . '.gz',
                            ]
                        )
                    );

                    return false;
                }
            }

            $area->notifyMaster();
        }

        $tempFolder
            ->getStorage()
            ->deleteFile($compressedDumFile);

        return true;
    }

    /**
     * Creates an Gzip File from a Dumpfile.
     *
     * @param Folder $folder   Folder where the file si stored
     * @param string $filename Name of File
     *
     * @return FileInterface|null
     */
    protected function createGZipFile(Folder $folder, string $filename): ?FileInterface
    {
        try {
            $tempFolder         = $this->storageService->getTempFolder();
            $tempStorage        = $tempFolder->getStorage();
            $tempFileIdentifier = $folder->getIdentifier() . $filename;
            $dumpFile           = $tempStorage->getFile($tempFileIdentifier);

            /** @var FileInterface|null $compressedDumpFile */
            $compressedDumpFile = $tempStorage->createFile($filename . '.gz', $folder);
            $compressedDumpFile?->setContents(
                gzencode(
                    $dumpFile instanceof FileInterface ? $dumpFile->getContents() : '',
                    9
                )
            );

            $tempStorage->deleteFile($dumpFile);
        } catch (\Exception) {
            $this->addErrorMessage(
                $this->getLabel(
                    'error.zip_failure',
                    [
                        '{file}' => isset($dumpFile) ? $dumpFile->getIdentifier() : '',
                    ]
                )
            );

            return null;
        }

        return $compressedDumpFile;
    }

    /**
     * Generates the file with the content for the clear cache task.
     *
     * @param string         $table The name of the table which cache should be cleared
     * @param int[]|string[] $uids  An array with the UIDs to clear cache
     *
     * @return bool TRUE if file was generated otherwise FALSE
     */
    private function createClearCacheFile(
        string $table,
        array $uids,
    ): bool {
        $arClearCacheData = [];

        // Create data
        foreach ($uids as $uid) {
            $arClearCacheData[] = $table . ':' . $uid;
        }

        $strClearCacheData = implode(',', $arClearCacheData);
        $clearCacheUrl     = sprintf($this->clearCacheUrl, $strClearCacheData);

        $this->urlGenerator->postProcessSync(
            [
                'arUrlsOnce' => [
                    $clearCacheUrl,
                ],
                'bProcess'    => true,
                'bSyncResult' => true,
            ],
            $this
        );

        return true;
    }

    /**
     * Open the temporary dumpfile.
     *
     * @param string   $filename    Name of file
     * @param string[] $directories Array with directories
     *
     * @return FileInterface
     *
     * @throws Exception
     */
    private function openTempDumpFile(string $filename, array $directories): FileInterface
    {
        $tempFolder         = $this->storageService->getTempFolder();
        $tempFileIdentifier = $tempFolder->getIdentifier() . $filename;
        $tempStorage        = $tempFolder->getStorage();

        if ($tempStorage->hasFile($tempFileIdentifier)
            || $tempStorage->hasFile($tempFileIdentifier . '.gz')
        ) {
            throw new Exception(
                $this->getLabel('error.last_sync_not_finished')
                . "<br/>\n"
                . $tempFileIdentifier . '(.gz)'
            );
        }

        foreach ($directories as $path) {
            $syncFolder     = $this->storageService->getSyncFolder()->getSubfolder($path);
            $fileIdentifier = $syncFolder->getIdentifier() . $filename;
            $defaultStorage = $this->storageService->getDefaultStorage();

            if ($defaultStorage->hasFile($fileIdentifier)
                || $defaultStorage->hasFile($fileIdentifier . '.gz')
            ) {
                throw new Exception(
                    $this->getLabel('error.last_sync_not_finished')
                );
            }
        }

        $tmpFile = $tempStorage->createFile($filename, $tempFolder);

        $encodingHeader = '/*!40101 SET NAMES %s */;' . PHP_EOL;

        $tmpFile->setContents(sprintf($encodingHeader, $this->getDbConnectionCharSet()));

        return $tmpFile;
    }

    /**
     * Returns the charset for the database connection.
     *
     * @return string
     */
    private function getDbConnectionCharSet(): string
    {
        return $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['charset'] ?? 'utf8';
    }

    /**
     * Erzeugt ein Dump durch Seiten IDs.
     *
     * @param int[]         $pageIDs    page ids to dump
     * @param string        $tableName  name of table to dump from
     * @param FileInterface $fpDumpFile file pointer to the SQL dump file
     * @param bool          $contentIDs true to interpret pageIDs as content IDs
     *
     * @return void
     *
     * @throws Exception
     */
    protected function dumpTableByPageIDs(
        array $pageIDs,
        string $tableName,
        FileInterface $fpDumpFile,
        bool $contentIDs = false,
    ): void {
        if (str_ends_with($tableName, '_mm')) {
            throw new Exception(
                $this->getLabel(
                    'error.mm_tables',
                    [
                        '{tableName}' => $tableName,
                    ]
                )
            );
        }

        ++$this->dumpTableRecursion;

        $deleteLines = [];
        $insertLines = [];

        $connection = $this->connectionPool
            ->getConnectionForTable($tableName);

        $columns = $connection
            ->createSchemaManager()
            ->listTableColumns($tableName);

        $columnNames = [];
        foreach ($columns as $column) {
            $columnNames[] = $column->getName();
        }

        $queryBuilder = $this->getQueryBuilderForTable($tableName);

        // In pages und pages_language_overlay entspricht die pageID der uid
        // pid ist ja der Parent (Elternelement) ... so mehr oder weniger *lol*
        if ($tableName === 'pages' || $contentIDs) {
            $strWhere = $queryBuilder->expr()->in('uid', $pageIDs);
        } else {
            $strWhere = $queryBuilder->expr()->in('pid', $pageIDs);
        }

        $refTableResult = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->where($strWhere)
            ->executeQuery();

        while ($row = $refTableResult->fetchAssociative()) {
            if ($row['deleted'] === 1) {
                $deleteLines[$tableName][$row['uid']]
                    = $this->buildDeleteLine($tableName, $row['uid']);
            } else {
                $insertLines[$tableName][$row['uid']]
                    = $this->buildInsertUpdateLine($tableName, $columnNames, $row);
            }

            $this->writeMMReferences($tableName, $row, $fpDumpFile);

            if (count($deleteLines) > 50) {
                $this->prepareDump($deleteLines, $insertLines, $fpDumpFile);

                $deleteLines = [];
                $insertLines = [];
            }
        }

        if (isset($_POST['data']['delete_obsolete_rows'])
            && ($_POST['data']['delete_obsolete_rows'] === '1')
        ) {
            $this->addAsDeleteRowTable($tableName);
        }

        // Write remaining delete and insert lines
        $this->prepareDump($deleteLines, $insertLines, $fpDumpFile);

        --$this->dumpTableRecursion;
    }

    /**
     * Adds the Table and its DeleteObsoleteRows statement to an array
     * if the statement does not exists in the array.
     *
     * @param string $tableName The name of the table the obsolete rows
     *                          should be added to the $obsoleteRows array for
     *
     * @return void
     */
    private function addAsDeleteRowTable(string $tableName): void
    {
        /** @var Table $table */
        $table = GeneralUtility::makeInstance(Table::class, $tableName, 'dummy');

        if (!isset($this->obsoleteRows[0])) {
            $this->obsoleteRows[0] = '-- Delete obsolete rows on target';
        }

        $strSql = $table->getSqlDroppingObsoleteRows();
        unset($table);

        if ($strSql === null || $strSql === '') {
            return;
        }

        $strSqlKey = md5($strSql);

        if (isset($this->obsoleteRows[$strSqlKey])) {
            return;
        }

        $this->obsoleteRows[$strSqlKey] = $strSql;
    }

    /**
     * Returns a TYPO3 QueryBuilder instance for a given table, without any restriction.
     *
     * @param string $tableName The table name
     *
     * @return QueryBuilder
     */
    private function getQueryBuilderForTable(string $tableName): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    /**
     * Returns SQL DELETE query.
     *
     * @param string $tableName The name of table to delete from
     * @param int    $uid       The UID of row to delete
     *
     * @return string
     */
    private function buildDeleteLine(string $tableName, int $uid): string
    {
        $connection = $this->connectionPool->getConnectionForTable($tableName);

        return sprintf(
            'DELETE FROM %s WHERE uid = %d;',
            $connection->quoteIdentifier($tableName),
            $uid
        );
    }

    /**
     * Returns SQL INSERT .. UPDATE ON DUPLICATE KEY query.
     *
     * @param string               $tableName   name of table to insert into
     * @param string[]             $columnNames
     * @param array<string, mixed> $row
     *
     * @return string
     */
    private function buildInsertUpdateLine(string $tableName, array $columnNames, array $row): string
    {
        $connection  = $this->connectionPool->getConnectionForTable($tableName);
        $updateParts = [];

        foreach ($row as $key => $value) {
            if (!is_numeric($value)) {
                $row[$key] = $connection->quote($value);
            }

            // TYPO-2215 - Match the column to its update value
            $updateParts[$key] = sprintf(
                '%1$s = VALUES(%1$s)',
                $connection->quoteIdentifier($key)
            );
        }

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s;',
            $connection->quoteIdentifier($tableName),
            implode(', ', $connection->quoteIdentifiers($columnNames)),
            implode(', ', $row),
            implode(', ', $updateParts)
        );
    }

    /**
     * Writes the references of a table to the sync data.
     *
     * @param string               $refTableName The table to reference
     * @param array<string, mixed> $row          The database row to find MM References
     * @param FileInterface        $fpDumpFile   The file pointer to the SQL dump file
     *
     * @return void
     */
    private function writeMMReferences(
        string $refTableName,
        array $row,
        FileInterface $fpDumpFile,
    ): void {
        $this->referenceTables = [];
        $this->addMMReferenceTables($refTableName);

        foreach ($this->referenceTables as $mmTableName => $arTableFields) {
            $columns = $this->connectionPool
                ->getConnectionForTable($mmTableName)
                ->createSchemaManager()
                ->listTableColumns($mmTableName);

            $columnNames = [];
            foreach ($columns as $column) {
                $columnNames[] = $column->getName();
            }

            foreach ($arTableFields as $arMMConfig) {
                $this->writeMMReference(
                    $refTableName,
                    $mmTableName,
                    $row['uid'],
                    $arMMConfig,
                    $columnNames,
                    $fpDumpFile
                );
            }
        }
    }

    /**
     * Finds MM reference tables and the config of them. Respects flexform fields.
     * Data will be set in referenceTables.
     *
     * @param string $tableName table to find references
     *
     * @return void
     */
    private function addMMReferenceTables(string $tableName): void
    {
        if (!isset($GLOBALS['TCA'][$tableName]['columns'])) {
            return;
        }

        foreach ($GLOBALS['TCA'][$tableName]['columns'] as $column) {
            if (isset($column['config']['type'])) {
                if ($column['config']['type'] === 'inline') {
                    $this->addForeignTableToReferences($column);
                } else {
                    $this->addMMTableToReferences($column);
                }
            }
        }
    }

    /**
     * Adds Column config to reference table, if a foreign_table reference config
     * like in inline-fields exists.
     *
     * @param array<string, array<string, mixed>> $column column config to get foreign_table data from
     *
     * @return void
     */
    private function addForeignTableToReferences(array $column): void
    {
        if (isset($column['config']['foreign_table'])) {
            $strForeignTable                           = $column['config']['foreign_table'];
            $this->referenceTables[$strForeignTable][] = $column['config'];
        }
    }

    /**
     * Adds column config to reference table, if a MM reference config exists.
     *
     * @param array<string, array<string, mixed>> $column Column config to get MM data from
     *
     * @return void
     */
    private function addMMTableToReferences(array $column): void
    {
        if (isset($column['config']['MM'])) {
            $this->referenceTables[$column['config']['MM']][] = $column['config'];
        }
    }

    /**
     * Writes the data of a MM table to the sync data.
     * Calls dumpTableByPageIDs for sys_file_reference if MM Table isn't sys_file. Or
     * calls dumpTableByPageIDs for tx_dam_mm_ref if MM Table isn't tx_dam.
     *
     * MM table structure:
     *
     * - uid_local
     * -- uid from 'local' table, local table ist first part of mm table name
     * -- sys_file_reference -> uid_local points to uid in sys_file
     *    /tx_dam_mm_ref -> uid_local points to uid in tx_dam
     * -- tt_news_cat_mm -> uid_local points to uid in tt_news_cat
     * - uid_foreign
     * -- uid from foreign table, foreign is the table in field 'tablenames'
     * --- tx_Dem_mm_ref -> uid_foreign points to uid in table from 'tablenames'
     * -- or static table name (hidden in code)
     * --- tt_news_cat_mm -> uid_foreign points to uid in tt_news
     * -- or last part of mm table name
     * --- sys_category_record_mm -> uid_foreign points to uid in sys_category
     *     /tx_dam_mm_cat -> uid_foreign points to uid in tx_dam_cat
     * - tablenames
     * -- optional, if present forms unique data with uid_* and ident
     * - ident
     * -- optional, if present forms unique data with uid_* and tablenames
     * -- points to a field in TCA or Flexform
     * - sorting - optional
     * - sorting_foreign - optional
     *
     * @param string               $strRefTableName table which we get the references from
     * @param string               $tableName       table to get MM data from
     * @param int                  $uid             the uid of element which references
     * @param array<string, mixed> $arMMConfig      the configuration of this MM reference
     * @param string[]             $columnNames     Table columns
     * @param FileInterface        $fpDumpFile      file pointer to the SQL dump file
     */
    private function writeMMReference(
        string $strRefTableName,
        string $tableName,
        int $uid,
        array $arMMConfig,
        array $columnNames,
        FileInterface $fpDumpFile,
    ): void {
        $deleteLines = [];
        $insertLines = [];

        $strFieldName = $arMMConfig['foreign_field'] ?? 'uid_foreign';
        $connection   = $this->connectionPool->getConnectionForTable($tableName);

        $strWhere = $connection->quoteIdentifier($strFieldName) . ' = ' . $uid;

        if (isset($arMMConfig['MM_match_fields'])) {
            foreach ($arMMConfig['MM_match_fields'] as $strName => $strValue) {
                $strWhere .= ' AND ' . $connection->quoteIdentifier($strName) . ' = ' . $connection->quote($strValue);
            }
        }

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();

        $statement = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->where($strWhere)
            ->executeQuery();

        if ($tableName !== 'sys_file_reference') {
            $deleteLines[$tableName][$uid] = sprintf(
                'DELETE FROM %s WHERE %s;',
                $connection->quoteIdentifier($tableName),
                $strWhere
            );
        }

        while ($row = $statement->fetchAssociative()) {
            if (isset($row['uid'])) {
                $insertLines[$tableName][$row['uid']]
                    = $this->buildInsertUpdateLine($tableName, $columnNames, $row);
            } elseif (isset($row['uid_foreign'])) {
                $insertLines[$tableName][$row['uid_foreign']]
                    = $this->buildInsertUpdateLine($tableName, $columnNames, $row);
            } else {
                throw new RuntimeException('Table misses column "uid" or "uid_foreign');
            }

            if (
                isset($arMMConfig['MM'], $arMMConfig['form_type'])
                && ($strRefTableName !== 'sys_file')
                && ($arMMConfig['MM'] === 'sys_file_reference')
                && ($arMMConfig['form_type'] === 'user')
            ) {
                $this->dumpTableByPageIDs(
                    [
                        $row['uid_local'],
                    ],
                    'sys_file',
                    $fpDumpFile,
                    true
                );
            }
        }

        unset($statement);

        $this->prepareDump($deleteLines, $insertLines, $fpDumpFile);
    }

    /**
     * Clean up statements and prepare dump file.
     *
     * @param array<string, string[]> $deleteLines Delete statements
     * @param array<string, string[]> $insertLines Insert statements
     * @param FileInterface           $fpDumpFile  Dump file
     */
    private function prepareDump(array $deleteLines, array $insertLines, FileInterface $fpDumpFile): void
    {
        if (!$this->isForceFullSync()) {
            $deleteLines = $this->removeNotSynchronizableEntries($deleteLines);
            $insertLines = $this->removeNotSynchronizableEntries($insertLines);
        }

        // Remove Deletes which has a corresponding Insert statement
        $this->diffDeleteLinesAgainstInsertLines(
            $deleteLines,
            $insertLines
        );

        // Remove all DELETE Lines that already has been put to file
        $this->clearDuplicateLines(
            'delete',
            $deleteLines
        );

        // Remove all INSERT Lines that already has been put to file
        $this->clearDuplicateLines(
            'insert',
            $insertLines
        );

        $this->writeToDumpFile($deleteLines, $insertLines, $fpDumpFile);
        $this->writeStats($insertLines);
    }

    /**
     * Return true if a full sync should be forced.
     *
     * @return bool
     */
    private function isForceFullSync(): bool
    {
        return isset($_POST['data']['force_full_sync'])
            && ($_POST['data']['force_full_sync'] === '1');
    }

    /**
     * Remove entries not needed for the sync.
     *
     * @param array<string, string[]> $lines lines with data to sync
     *
     * @return array<string, string[]>
     */
    private function removeNotSynchronizableEntries(array $lines): array
    {
        $result = $lines;

        foreach ($lines as $table => $statements) {
            foreach (array_keys($statements) as $uid) {
                if (!$this->isElementSynchronizable($table, $uid)) {
                    unset($result[$table][$uid]);
                }
            }
        }

        return $result;
    }

    /**
     * Returns TRUE if an element, given by table name and UID is syncable.
     *
     * @param string     $table The table, the element belongs to
     * @param string|int $uid   The uid of the element
     *
     * @return bool
     */
    private function isElementSynchronizable(string $table, string|int $uid): bool
    {
        if (str_contains($table, '_mm')) {
            return true;
        }

        $syncStats = $this->getSyncStatsForElement($table, $uid);
        $timeStamp = $this->getTimestampOfElement($table, $uid);

        if ($timeStamp === 0) {
            return false;
        }

        if ($syncStats === false) {
            return true;
        }

        if (isset($syncStats['incr']) && ($timeStamp < $syncStats['incr'])) {
            return false;
        }

        if (!isset($syncStats['full'])) {
            return true;
        }

        return $syncStats['full'] <= $timeStamp;
    }

    /**
     * Fetches synchronization statistics for an element from database.
     *
     * @param string $table The table, the elements belong to
     * @param int    $uid   The uid of the element
     *
     * @return array<string, mixed>|false Synchronisation statistics or FALSE if statistics don't exist
     */
    private function getSyncStatsForElement(string $table, int $uid): false|array
    {
        $queryBuilder = $this->getQueryBuilderForTable($table);

        return $queryBuilder
            ->select('full', 'incr')
            ->from('tx_nrsync_syncstat')
            ->where(
                $queryBuilder->expr()->eq('tab', $queryBuilder->quote($table)),
                $queryBuilder->expr()->eq('uid_foreign', $uid)
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * Returns timestamp of this element.
     *
     * @param string $table The table, the elements belong to
     * @param int    $uid   The uid of the element
     *
     * @return int
     */
    private function getTimestampOfElement(string $table, int $uid): int
    {
        $queryBuilder = $this->getQueryBuilderForTable($table);

        return (int) $queryBuilder
            ->select('tstamp')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('uid', $uid)
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Removes all delete statements from $deleteLines where an insert statement
     * exists in $insertLines.
     *
     * @param array<string, string[]> &$deleteLines referenced array with delete statements
     *                                              structure should be
     *                                              $deleteLines['table1']['uid1'] = 'STATMENT1'
     *                                              $deleteLines['table1']['uid2'] = 'STATMENT2'
     *                                              $deleteLines['table2']['uid2'] = 'STATMENT3'
     * @param array<string, string[]> $insertLines  referenced array with insert statements
     *                                              structure should be
     *                                              $deleteLines['table1']['uid1'] = 'STATMENT1'
     *                                              $deleteLines['table1']['uid2'] = 'STATMENT2'
     *                                              $deleteLines['table2']['uid2'] = 'STATMENT3'
     *
     * @return void
     */
    private function diffDeleteLinesAgainstInsertLines(
        array &$deleteLines,
        array $insertLines,
    ): void {
        if ($deleteLines === []) {
            return;
        }

        foreach ($insertLines as $tableName => $elements) {
            // no modification for arrays with old flat structure
            if (!is_array($elements)) {
                return;
            }

            // UNSET each delete line where an insert exists
            foreach (array_keys($elements) as $uid) {
                if (isset($deleteLines[$tableName][$uid])) {
                    unset($deleteLines[$tableName][$uid]);
                }
            }

            if (count($deleteLines[$tableName]) === 0) {
                unset($deleteLines[$tableName]);
            }
        }
    }

    /**
     * Removes all entries from $sqlLines which already exists in $globalSqlLineStorage.
     *
     * @param string                  $statementType Type the type of the current arSqlLines
     * @param array<string, string[]> &$sqlLines     multidimensional array of sql statements
     *
     * @return void
     */
    private function clearDuplicateLines(string $statementType, array &$sqlLines): void
    {
        foreach ($sqlLines as $tableName => $lines) {
            foreach (array_keys($lines) as $uid) {
                if (isset($this->globalSqlLineStorage[$statementType][$tableName][$uid])) {
                    unset($sqlLines[$tableName][$uid]);
                }
            }

            // unset table name key if no statement exists anymore
            if (count($sqlLines[$tableName]) === 0) {
                unset($sqlLines[$tableName]);
            }
        }
    }

    /**
     * Writes the data into dump file. Line per line.
     *
     * @param array<string, string[]> $deleteLines        The lines with the delete statements.
     *                                                    Expected structure:
     *                                                    $deleteLines['table1']['uid1'] = 'STATMENT1'
     *                                                    $deleteLines['table1']['uid2'] = 'STATMENT2'
     *                                                    $deleteLines['table2']['uid2'] = 'STATMENT3'
     * @param array<string, string[]> $insertLines        The lines with the insert statements.
     *                                                    Expected structure:
     *                                                    $insertLines['table1']['uid1'] = 'STATMENT1'
     *                                                    $insertLines['table1']['uid2'] = 'STATMENT2'
     *                                                    $insertLines['table2']['uid2'] = 'STATMENT3'
     * @param FileInterface           $fpDumpFile         file pointer to the SQL dump file
     * @param string[]                $deleteObsoleteRows the lines with delete obsolete
     *                                                    rows statement
     *
     * @return void
     */
    private function writeToDumpFile(
        array $deleteLines,
        array $insertLines,
        FileInterface $fpDumpFile,
        array $deleteObsoleteRows = [],
    ): void {
        $fileContent = $fpDumpFile->getContents();

        // Keep the current lines in mind
        $this->addLinesToLineStorage('delete', $deleteLines);
        $this->addLinesToLineStorage('insert', $insertLines);

        // Foreach Table in DeleteArray
        foreach ($deleteLines as $arDelLines) {
            if (count($arDelLines) > 0) {
                $fileContent .= implode("\n", $arDelLines) . "\n\n";
            }
        }

        // do not write the inserts here, we want to add them
        // at the end of the file see $this->writeInsertLines

        if ($deleteObsoleteRows !== []) {
            $fileContent .= implode("\n", $deleteObsoleteRows) . "\n\n";
        }

        $fpDumpFile->setContents($fileContent);

        foreach ($insertLines as $table => $arInsertStatements) {
            foreach (array_keys($arInsertStatements) as $uid) {
                if (str_contains($table, '_mm')) {
                    continue;
                }

                $this->setLastDumpTimeForElement($table, $uid);
            }
        }
    }

    /**
     * Write stats for the sync.
     *
     * @param array<string, string[]> $insertLines Array of statements for elements to sync
     *
     * @return void
     */
    private function writeStats(array $insertLines): void
    {
        foreach ($insertLines as $table => $insertStatements) {
            if (str_contains($table, '_mm')) {
                continue;
            }

            foreach (array_keys($insertStatements) as $uid) {
                $this->setLastDumpTimeForElement($table, $uid);
            }
        }
    }

    /**
     * Add the passed $sqlLines to the $globalSqlLineStorage in unique way.
     *
     * @param string                  $statementType the type of the current arSqlLines
     * @param array<string, string[]> $sqlLines      multidimensional array of sql statements
     *
     * @return void
     */
    private function addLinesToLineStorage(string $statementType, array $sqlLines): void
    {
        foreach ($sqlLines as $tableName => $lines) {
            if (!is_array($lines)) {
                return;
            }

            foreach ($lines as $uid => $strLine) {
                $this->globalSqlLineStorage[$statementType][$tableName][$uid] = $strLine;
            }
        }
    }

    /**
     * Sets time of last dump/sync for this element.
     *
     * @param string $table The table, the elements belong to
     * @param int    $uid   The uid of the element
     */
    private function setLastDumpTimeForElement(string $table, int $uid): void
    {
        $time        = time();
        $userId      = (int) $this->getBackendUserAuthentication()->user['uid'];
        $updateField = $this->isForceFullSync() ? 'full' : 'incr';

        $connection = $this->connectionPool
            ->getConnectionForTable('tx_nrsync_syncstat');

        $connection->executeStatement(
            sprintf(
                'INSERT INTO tx_nrsync_syncstat (tab, %s, cruser_id, uid_foreign) VALUES (%s, %s, %s, %s)'
                . ' ON DUPLICATE KEY UPDATE cruser_id = %s, %s = %s',
                $updateField,
                $connection->quote($table),
                $connection->quote($time),
                $connection->quote($userId),
                $connection->quote($uid),
                $connection->quote($userId),
                $updateField,
                $connection->quote($time)
            )
        );
    }
}
