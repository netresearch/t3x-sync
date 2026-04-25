<?php

/*
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
 *
 * @see    https://www.netresearch.de
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
                . "\n\n" . implode(', ', $errorTables),
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

        // Differ the table definition? Compare via JSON encoding rather
        // than serialize() so the comparison stays consistent with the
        // on-disk format (#42).
        return json_encode($this->tableDefinition[$tableName]) !== json_encode($columnNames);
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

        // Defense in depth (#42): tableDefinition is array<string, string[]>
        // pure data — no objects to deserialize. Try JSON first; on JSON
        // failure (state file written by a pre-migration version), fall
        // back to unserialize() with allowed_classes=false so PHP object
        // injection cannot be triggered even if the sync folder is somehow
        // attacker-writable. Writers (TableStateSyncModuleController) emit
        // JSON exclusively from this PR onward.
        $decoded = json_decode((string) $stateFileContent, true);
        if (is_array($decoded)) {
            $this->tableDefinition = $decoded;

            return;
        }

        // nosemgrep: php.lang.security.unserialize-use.unserialize-use -- backward-compat fallback for state files written by pre-JSON-migration versions; allowed_classes=false makes PHP refuse to instantiate any class, so PHP object injection is impossible regardless of the file contents. Writers from this PR onward use json_encode().
        $legacy                = @unserialize($stateFileContent, ['allowed_classes' => false]);
        $this->tableDefinition = is_array($legacy) ? $legacy : [];
    }

    /**
     * @return string
     */
    private function getStateFile(): string
    {
        return $this->storageService->getSyncFolder()->getIdentifier() . $this->tableSerializedFile;
    }
}
