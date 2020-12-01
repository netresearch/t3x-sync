<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Module;

use Netresearch\Sync\Helper\Area;
use TYPO3\CMS\Core\Messaging\FlashMessage;

/**
 * Class StateModule
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class StateModule extends BaseModule
{
    protected $name = 'Table state';
    protected $type = 'sync_tables';
    protected $target = 'local';
    protected $accessLevel = 100;

    public function run(Area $area): bool
    {
        parent::run($area);

        if (isset($_POST['data']['submit'])) {
            if ($this->createNewDefinitions()) {
                $this->addMessage(
                    'Updated table state.', FlashMessage::OK
                );
            }
        } else {
            $this->testAllTablesForDifferences();
        }

        return true;
    }

    /**
     * Tests if the tables of db differs from saved file.
     *
     * @return void
     */
    private function testAllTablesForDifferences(): void
    {
        $tableNames = $this->connectionPool
            ->getConnectionForTable('pages')
            ->getSchemaManager()
            ->listTableNames();

        $this->testTablesForDifferences($tableNames);
    }

    /**
     * Writes the table definition of database into an file.
     *
     * @return bool True if file was written else false.
     */
    private function createNewDefinitions(): bool
    {
        $tableNames = $this->connectionPool
            ->getConnectionForTable('pages')
            ->getSchemaManager()
            ->listTableNames();

        $tables = [];
        foreach ($tableNames as $tableName) {
            $columns = $this->connectionPool
                ->getConnectionForTable($tableName)
                ->getSchemaManager()
                ->listTableColumns($tableName);

            $columnNames = [];
            foreach ($columns as $column) {
                $columnNames[] = $column->getName();
            }

            $tables[$tableName] = $columnNames;
        }

        $file = $this->getStateFile();

        if (file_exists($file) && !is_writable($file)) {
            $this->addMessage(
                'Tabellendefinitionsdatei ist nicht schreibar!' . ' ' . $file,
                FlashMessage::ERROR
            );
            return false;
        }

        $fpDumpFile = fopen($file, 'wb');

        if ($fpDumpFile === false) {
            $this->addMessage(
                'Konnte Tabellendefinitionsdatei nicht öffnen!' . ' ' . $file,
                FlashMessage::ERROR);
            return false;
        }

        $ret = fwrite($fpDumpFile, serialize($tables));

        if ($ret === false) {
            $this->addMessage(
                'Konnte Tabellendefinitionsdatei nicht schreiben!' . ' ' . $file,
                FlashMessage::ERROR);
            return false;
        }

        fclose($fpDumpFile);

        return true;
    }
}
