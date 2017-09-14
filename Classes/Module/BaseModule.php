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

class BaseModule
{
    protected $tables = [];
    protected $name = 'Please select';
    protected $type = '';
    protected $target = '';
    protected $dumpFileName = '';
    protected $accessLevel = 0;
    protected $error = null;
    protected $content = '';

    /**
     * @var array
     */
    protected $tableDefinition;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     * @inject
     */
    protected $objectManager;

    /**
     * @var \TYPO3\CMS\Core\Messaging\FlashMessageService
     * @inject
     */
    protected $messageService;

    /**
     * @var \TYPO3\CMS\Core\Database\ConnectionPool
     * @inject
     */
    protected $connectionPool;

    /**
     * @var string file where to store table information
     */
    protected $strTableSerializedFile = 'tables_serialized.txt';

    /**
     * @var string Where to put DB Dumps (trailing Slash)
     */
    var $strDBFolder = '';



    function __construct(array $arOptions = null)
    {
        if (null !== $arOptions) {
            $this->tables = (array) $arOptions['tables'] ?: [];
            $this->name = $arOptions['name'] ?: 'Default sync';
            $this->target = $arOptions['target'] ?: '';
            $this->type = $arOptions['type'] ?: 'sync_tables';
            $this->dumpFileName = $arOptions['dumpFileName'] ?: 'dump.sql';
            $this->accessLevel = intval($arOptions['accessLevel']) ?: 0;
        }
    }

    public function run(Area $area = null)
    {
        $strRootPath = $_SERVER['DOCUMENT_ROOT'];
        if (empty($strRootPath)) {
            $strRootPath = substr(PATH_site, 0, -1);
        }
        $this->strDBFolder = $strRootPath . '/db/';

        $this->testTablesForDifferences();

        return true;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getName()
    {
        return $this->name;
    }
    public function getType()
    {
        return $this->type;
    }
    public function getDumpFileName()
    {
        return $this->dumpFileName;
    }
    public function getTableNames()
    {
        return $this->tables;
    }
    public function getAccessLevel()
    {
        return $this->accessLevel;
    }
    public function getTarget()
    {
        return $this->target;
    }

    public function getDescription()
    {
        return 'Target: ' . $this->getTarget() . '<br>'
            . 'Content: ' . $this->getName();
    }

    public function getError()
    {
        return $this->error;
    }

    public function hasError()
    {
        return null !== $this->error;
    }



    /**
     * @return string
     */
    protected function getStateFile()
    {
        return $this->strDBFolder . $this->strTableSerializedFile;
    }



    /**
     * Loads the last saved definition of the tables.
     *
     * @return void
     */
    protected function loadTableDefinition()
    {
        $strFile = $this->getStateFile();
        if (!file_exists($strFile)) {
            $this->tableDefinition = array();
            return;
        }

        $fpDumpFile = fopen($strFile, 'r');
        $strAllTables = fread(
            $fpDumpFile,
            filesize($strFile)
        );
        fclose($fpDumpFile);
        $this->tableDefinition = unserialize($strAllTables);
    }



    /**
     * Tests if a table in the DB differs from last saved state.
     *
     * @param string $strTableName Name of table.
     *
     * @return boolean True if file differs otherwise false.
     */
    protected function isTableDifferent($strTableName)
    {
        if (!isset($this->tableDefinition)) {
            $this->loadTableDefinition();
        }

        // Tabelle existierte vorher nicht
        if (!isset($this->tableDefinition[$strTableName])) {
            return true;
        }

        $arColumns = $this->connectionPool->getConnectionForTable($strTableName)
            ->getSchemaManager()
            ->listTableColumns($strTableName);

        $arColumnNames = [];
        foreach ($arColumns as $column) {
            $arColumnNames[] = $column->getName();
        }

        // Tabelle existiert jetzt nicht
        if (count($arColumnNames) == 0) {
            return true;
        }

        // Sind Tabellendefinitionen ungleich?
        if (serialize($this->tableDefinition[$strTableName]) !== serialize($arColumnNames)) {
            return true;
        }

        // Alles in Ordnung
        return false;
    }



    /**
     * Adds error message to message queue.
     *
     * message types are defined as class constants self::STYLE_*
     *
     * @param string $strMessage message
     * @param integer $type message type
     *
     * @return void
     */
    public function addMessage($strMessage, $type = FlashMessage::INFO)
    {
        /* @var $message FlashMessage */
        $message = $this->objectManager->get(
            FlashMessage::class, $strMessage, '', $type, true
        );

        $this->messageService->getMessageQueueByIdentifier()->addMessage($message);
    }


    /**
     * Test if the given tables in the DB differs from last saved state.
     *
     * @param string[] $tableNames
     * @return void
     */
    protected function testTablesForDifferences(array $tableNames = null)
    {
        $arErrorTables = [];

        if (null === $tableNames) {
            $tableNames = $this->getTableNames();
        }

        foreach ($tableNames as $strTableName) {
            if ($this->isTableDifferent($strTableName)) {
                $arErrorTables[] = htmlspecialchars($strTableName);
            }
        }

        if (count($arErrorTables)) {
            $this->addMessage(
                'Following tables have changed, please contact your TYPO3 admin: '
                . implode(', ', $arErrorTables),
                FlashMessage::WARNING
            );
        }
    }
}