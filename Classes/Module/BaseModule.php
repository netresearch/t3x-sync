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
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function count;

/**
 * Class BaseModule
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class BaseModule
{
    /**
     * @var FlashMessageService
     */
    private $flashMessageService;

    /**
     * @var ConnectionPool
     */
    protected $connectionPool;

    /**
     * A list of table names to synchronise.
     *
     * @var string[]
     */
    protected $tables = [];

    /**
     * The name of the module.
     *
     * @var string
     */
    protected $name = 'Please select';

    /**
     * The type of tables to sync, e.g. "sync_tables", "sync_fe_groups", "sync_be_groups" or "backsync_tables".
     *
     * @var string
     */
    protected $type = '';

    /**
     * @var string
     */
    protected $target = '';

    /**
     * The name of the synchronisation file containg the SQL statements to update the database records.
     *
     * @var string
     */
    protected $dumpFileName = '';

    /**
     * The access level of the module (value between 0 and 100). 100 requires admin access to typo3 backend.
     *
     * @var int
     */
    protected $accessLevel = 0;

    /**
     * @var null|string
     */
    protected $error;

    /**
     * Additional content to output by the module.
     *
     * @var string
     *
     * @deprecated Provide additional content by views/templates instead
     */
    protected $content = '';

    /**
     * The current table definition.
     *
     * @var array
     */
    private $tableDefinition;

    /**
     * File where to store table information.
     *
     * @var string
     */
    private $tableSerializedFile = 'tables_serialized.txt';

    /**
     * Where to put DB Dumps (trailing Slash).
     *
     * @var string
     */
    private $dbFolder = '';

    /**
     * BaseModule constructor.
     *
     * @param null|array $options
     */
    public function __construct(array $options = null)
    {
        $this->flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $this->connectionPool      = GeneralUtility::makeInstance(ConnectionPool::class);

        if ($options !== null) {
            $this->tables = (array) $options['tables'] ?: [];
            $this->name = $options['name'] ?: 'Default sync';
            $this->target = $options['target'] ?: '';
            $this->type = $options['type'] ?: 'sync_tables';
            $this->dumpFileName = $options['dumpFileName'] ?: 'dump.sql';
            $this->accessLevel = (int) $options['accessLevel'] ?: 0;
        }
    }

    /**
     * @param Area $area
     *
     * @return bool
     */
    public function run(Area $area): bool
    {
        $rootPath = $_SERVER['DOCUMENT_ROOT'];

        if (empty($rootPath)) {
            $rootPath = substr(Environment::getPublicPath(), 0, -1);
        }

        $this->dbFolder = $rootPath . '/db/';

        $this->testTablesForDifferences();

        return true;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getDumpFileName(): string
    {
        return $this->dumpFileName;
    }

    /**
     * @return string[]
     */
    public function getTableNames(): array
    {
        return $this->tables;
    }

    /**
     * @return int
     */
    public function getAccessLevel(): int
    {
        return $this->accessLevel;
    }

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Target: ' . $this->getTarget() . '<br>'
            . 'Content: ' . $this->getName();
    }

    /**
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * @return bool
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * @return string
     */
    protected function getStateFile(): string
    {
        return $this->dbFolder . $this->tableSerializedFile;
    }

    /**
     * Loads the last saved definition of the tables.
     *
     * @return void
     */
    protected function loadTableDefinition(): void
    {
        $file = $this->getStateFile();

        if (!file_exists($file)) {
            $this->tableDefinition = [];
            return;
        }

        $fpDumpFile = fopen($file, 'rb');
        $allTables = fread($fpDumpFile, filesize($file));
        fclose($fpDumpFile);

        $this->tableDefinition = unserialize($allTables);
    }

    /**
     * Tests if a table in the DB differs from last saved state.
     *
     * @param string $tableName Name of table.
     *
     * @return bool True if file differs otherwise false.
     */
    protected function isTableDifferent(string $tableName): bool
    {
        if (!isset($this->tableDefinition)) {
            $this->loadTableDefinition();
        }

        // Table did not exists before
        if (!isset($this->tableDefinition[$tableName])) {
            return true;
        }

        $columns = $this->connectionPool
            ->getConnectionForTable($tableName)
            ->getSchemaManager()
            ->listTableColumns($tableName);

        $columnNames = [];
        foreach ($columns as $column) {
            $columnNames[] = $column->getName();
        }

        // Table still not exists
        if (count($columnNames) === 0) {
            return true;
        }

        // Differ the table definition?
        if (serialize($this->tableDefinition[$tableName]) !== serialize($columnNames)) {
            return true;
        }

        // All fine
        return false;
    }

    /**
     * Adds error message to message queue. Message types are defined as class constants self::STYLE_*.
     *
     * @param string $message The message
     * @param int    $type    The message type
     */
    public function addMessage(string $message, int $type = FlashMessage::INFO): void
    {
        /** @var FlashMessage $flashMessage */
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class, $message, '', $type, true
        );

        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->addMessage($flashMessage);
    }

    /**
     * Test if the given tables in the DB differs from last saved state.
     *
     * @param string[] $tableNames
     *
     * @return void
     */
    protected function testTablesForDifferences(array $tableNames = null): void
    {
        $errorTables = [];

        if ($tableNames === null) {
            $tableNames = $this->getTableNames();
        }

        foreach ($tableNames as $tableName) {
            if ($this->isTableDifferent($tableName)) {
                $errorTables[] = htmlspecialchars($tableName);
            }
        }

        if (count($errorTables)) {
            $this->addMessage(
                'Following tables have changed, please contact your TYPO3 admin: '
                . implode(', ', $errorTables),
                FlashMessage::WARNING
            );
        }
    }
}
