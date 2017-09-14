<?php
/**
 * Created by PhpStorm.
 * User: sebastian.mendel
 * Date: 2017-09-04
 * Time: 14:52
 */

namespace Netresearch\Sync\Module;


use Netresearch\Sync\Helper\Area;
use TYPO3\CMS\Core\Messaging\FlashMessage;

class StateModule extends BaseModule
{
    protected $name = 'Table state';
    protected $type = 'sync_tables';
    protected $target = 'local';
    protected $dumpFileName = '';
    protected $accessLevel = 100;



    public function run(Area $area = null)
    {
        parent::run();

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
    protected function testAllTablesForDifferences()
    {
        $arTableNames = $this->connectionPool->getConnectionForTable('pages')
            ->getSchemaManager()
            ->listTableNames();

        $this->testTablesForDifferences($arTableNames);
    }



    /**
     * Writes the table definition of database into an file.
     *
     * @return boolean True if file was written else false.
     */
    protected function createNewDefinitions()
    {
        $arTableNames = $this->connectionPool->getConnectionForTable('pages')
            ->getSchemaManager()
            ->listTableNames();

        $arTables = [];
        foreach ($arTableNames as $strTableName) {
            $arColumns = $this->connectionPool->getConnectionForTable($strTableName)
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
        $fpDumpFile = fopen($strFile, 'w');
        if (false === $fpDumpFile) {
            $this->addMessage(
                'Konnte Tabellendefinitionsdatei nicht öffnen!' . ' ' . $strFile,
                FlashMessage::ERROR);
            return false;
        }
        $ret = fwrite($fpDumpFile, $strTables);
        if (false === $ret) {
            $this->addMessage(
                'Konnte Tabellendefinitionsdatei nicht schreiben!' . ' ' . $strFile,
                FlashMessage::ERROR);
            return false;
        }
        fclose($fpDumpFile);

        return true;
    }
}