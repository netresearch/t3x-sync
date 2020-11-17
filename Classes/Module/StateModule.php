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
    protected $dumpFileName = '';
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
        $arTableNames = $this->connectionPool
            ->getConnectionForTable('pages')
            ->getSchemaManager()
            ->listTableNames();

        $this->testTablesForDifferences($arTableNames);
    }

    /**
     * Writes the table definition of database into an file.
     *
     * @return bool True if file was written else false.
     */
    private function createNewDefinitions(): bool
    {
        $arTableNames = $this->connectionPool
            ->getConnectionForTable('pages')
            ->getSchemaManager()
            ->listTableNames();

        $arTables = [];
        foreach ($arTableNames as $strTableName) {
            $arColumns = $this->connectionPool
                ->getConnectionForTable($strTableName)
                ->getSchemaManager()
                ->listTableColumns($strTableName);

            $arColumnNames = [];
            foreach ($arColumns as $column) {
                $arColumnNames[] = $column->getName();
            }
            $arTables[$strTableName] = $arColumnNames;
        }

        $strTables = serialize($arTables);

        $strFile = $this->getStateFile();

        if (file_exists($strFile) && !is_writable($strFile)) {
            $this->addMessage(
                'Tabellendefinitionsdatei ist nicht schreibar!' . ' ' . $strFile,
                FlashMessage::ERROR
            );
            return false;
        }

        $fpDumpFile = fopen($strFile, 'wb');

        if ($fpDumpFile === false) {
            $this->addMessage(
                'Konnte Tabellendefinitionsdatei nicht öffnen!' . ' ' . $strFile,
                FlashMessage::ERROR);
            return false;
        }

        $ret = fwrite($fpDumpFile, $strTables);
        if ($ret === false) {
            $this->addMessage(
                'Konnte Tabellendefinitionsdatei nicht schreiben!' . ' ' . $strFile,
                FlashMessage::ERROR);
            return false;
        }

        fclose($fpDumpFile);

        return true;
    }
}
