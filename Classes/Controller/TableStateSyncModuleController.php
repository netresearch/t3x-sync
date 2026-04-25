<?php

/*
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Controller;

use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Traits\FlashMessageTrait;
use Netresearch\Sync\Traits\TableDifferenceTrait;
use Netresearch\Sync\Traits\TranslationTrait;

/**
 * Class TableStateModuleController.
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
class TableStateSyncModuleController extends BaseSyncModuleController
{
    use FlashMessageTrait;
    use TableDifferenceTrait;
    use TranslationTrait;

    /**
     * @param Area $area
     *
     * @return void
     */
    public function run(Area $area): void
    {
        // Do not call parent method

        if (isset($_POST['data']['submit'])) {
            if ($this->createNewDefinitions()) {
                $this->addSuccessMessage(
                    $this->getLabel('message.table_state_success'),
                );
            }
        } else {
            $this->testTablesForDifferences($this->getAllTables());
        }
    }

    /**
     * @return string[]
     */
    private function getAllTables(): array
    {
        return $this->connectionPool
            ->getConnectionForTable('pages')
            ->createSchemaManager()
            ->listTableNames();
    }

    /**
     * Writes the table definition of the database into a file.
     *
     * @return bool TRUE if the file was written else FALSE
     */
    private function createNewDefinitions(): bool
    {
        $tables = [];
        foreach ($this->getAllTables() as $tableName) {
            $columns = $this->connectionPool
                ->getConnectionForTable($tableName)
                ->createSchemaManager()
                ->listTableColumns($tableName);

            $columnNames = [];
            foreach ($columns as $column) {
                $columnNames[] = $column->getName();
            }

            $tables[$tableName] = $columnNames;
        }

        $defaultFolder = $this->storageService->getDefaultStorage();

        if ($defaultFolder->hasFile($this->getStateFile()) === false) {
            $defaultFolder->createFile(
                $this->tableSerializedFile,
                $defaultFolder->getFolder(
                    $this->storageService->getBaseFolderIdentifier(),
                ),
            );
        }

        $tableStateFile = $defaultFolder->getFile($this->getStateFile());

        if (($tableStateFile === null)
            || ($defaultFolder->checkFileActionPermission('write', $tableStateFile) === false)
        ) {
            $this->addErrorMessage(
                $this->getLabel('error.could_not_write_tablestate') . ' ' . $tableStateFile->getName(),
            );

            return false;
        }

        // JSON instead of serialize() so the on-disk state file cannot
        // be a vector for PHP object injection (#42). $tables is
        // array<string, string[]> — pure data, no objects.
        // TableDifferenceTrait::loadTableDefinition() falls back to
        // unserialize-with-allowed_classes-false for state files written
        // by pre-migration versions, so this change is backward-compatible.
        $tableStateFile->setContents(json_encode($tables, JSON_THROW_ON_ERROR));

        return true;
    }
}
