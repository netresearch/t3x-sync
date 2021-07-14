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
use Netresearch\Sync\ModuleInterface;
use TYPO3\CMS\Core\Messaging\FlashMessage;

/**
 * Class TableStateModule
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class TableStateModule extends BaseModule
{
    /**
     * The name of the sync module to be displayed in sync module selection menu.
     *
     * @var string
     */
    protected $name = 'Table state';

    /**
     * The type of tables to sync, e.g. "sync_tables", "sync_fe_groups", "sync_be_groups" or "backsync_tables".
     *
     * @var string
     *
     * @deprecated Seems deprecated. Not used anywhere?
     */
    protected $type = ModuleInterface::SYNC_TYPE_TABLES;

    /**
     * The access level of the module (value between 0 and 100). 100 requires admin access to typo3 backend.
     *
     * @var int
     */
    protected $accessLevel = 100;

    /**
     * The name of the sync target.
     *
     * @var string
     */
    protected $target = 'local';

    public function run(Area $area): void
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
