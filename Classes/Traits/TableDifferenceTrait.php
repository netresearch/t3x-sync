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

/**
 * Service clear cache for Netresearch Synchronisation.
 *
 * @author  Alexander Opitz <alexander.opitz@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
trait TableDifferenceTrait
{
    /**
     * The current table definition.
     *
     * @var array<string, mixed>
     */
    private array $tableDefinition = [];

    /**
     * File where to store table information.
     *
     * @var string
     */
    private string $tableSerializedFile = 'tables_serialized.txt';

    /**
     * Test if the given tables in the DB differ from the last saved state.
     *
     * @param string[] $tableNames
     *
     * @return void
     *
     * @throws Exception
     */
    public function testTablesForDifferences(array $tableNames): void
    {
        $errorTables = [];

        foreach ($tableNames as $tableName) {
            if ($this->isTableDifferent($tableName)) {
                $errorTables[] = htmlspecialchars($tableName);
            }
        }

        if ($errorTables !== []) {
            $this->addWarningMessage(
                $this->getLabel('warning.table_state')
                . "\n\n" . implode(', ', $errorTables)
            );
        }
    }

    /**
     * Tests if a table in the DB differs from the last saved state.
     *
     * @param string $tableName name of table
     *
     * @return bool TRUE if the file differs otherwise false
     *
     * @throws Exception
     */
    private function isTableDifferent(string $tableName): bool
    {
        if ($this->tableDefinition === []) {
            $this->loadTableDefinition();
        }

        // Table did not exist before
        if (!isset($this->tableDefinition[$tableName])) {
            return true;
        }

        $columns = $this->connectionPool
            ->getConnectionForTable($tableName)
            ->createSchemaManager()
            ->listTableColumns($tableName);

        $columnNames = [];
        foreach ($columns as $column) {
            $columnNames[] = $column->getName();
        }

        // Table still doesn't exist
        if ($columnNames === []) {
            return true;
        }

        // Differ the table definition?
        return serialize($this->tableDefinition[$tableName]) !== serialize($columnNames);
    }

    /**
     * Loads the last saved definition of the tables.
     *
     * @return void
     */
    private function loadTableDefinition(): void
    {
        $defaultStorage = $this->storageService->getDefaultStorage();

        if (!$defaultStorage->hasFile($this->getStateFile())) {
            $this->tableDefinition = [];

            return;
        }

        $stateFileContent = $defaultStorage
            ->getFile($this->getStateFile())
            ->getContents();

        $this->tableDefinition = unserialize($stateFileContent) ?? [];
    }

    /**
     * @return string
     */
    private function getStateFile(): string
    {
        return $this->storageService->getSyncFolder()->getIdentifier() . $this->tableSerializedFile;
    }
}
