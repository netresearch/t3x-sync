<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Module;

use Doctrine\DBAL\Exception;
use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\ModuleInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
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
class BaseModule implements ModuleInterface
{
    /**
     * @var FlashMessageService
     */
    private readonly FlashMessageService $flashMessageService;

    /**
     * @var ConnectionPool
     */
    protected ConnectionPool $connectionPool;

    /**
     * A list of table names to synchronise.
     *
     * @var string[]
     */
    protected array $tables = [];

    /**
     * The name of the sync module to be displayed in sync module selection menu.
     *
     * @var string
     */
    protected mixed $name = 'Please select';

    /**
     * The type of tables to sync, e.g. "sync_tables", "sync_fe_groups", "sync_be_groups" or "backsync_tables".
     *
     * @var string
     *
     * @deprecated Seems deprecated. Not used anywhere?
     */
    protected mixed $type = '';

    /**
     * The name of the sync target.
     *
     * @var string
     */
    protected mixed $target = '';

    /**
     * The name of the synchronisation file containing the SQL statements to update the database records.
     *
     * @var string
     */
    protected mixed $dumpFileName = '';

    /**
     * The access level of the module (value between 0 and 100). 100 requires admin access to typo3 backend.
     *
     * @var int
     */
    protected int $accessLevel = 0;

    /**
     * @var null|string
     */
    protected ?string $error = null;

    /**
     * Additional content to output by the module.
     *
     * @var string
     *
     * @deprecated Provide additional content by views/templates instead
     */
    protected string $content = '';

    /**
     * The current table definition.
     *
     * @var array
     */
    private array $tableDefinition;

    /**
     * File where to store table information.
     *
     * @var string
     */
    private string $tableSerializedFile = 'tables_serialized.txt';

    /**
     * Where to put DB Dumps (trailing Slash).
     *
     * @var string
     */
    private string $dbFolder = '';

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
            $this->type = $options['type'] ?: ModuleInterface::SYNC_TYPE_TABLES;
            $this->dumpFileName = $options['dumpFileName'] ?: 'dump.sql';
            $this->accessLevel = (int) $options['accessLevel'] ?: 0;
        }
    }

    /**
     * @return bool
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * @param Area $area
     *
     * @return void
     * @throws Exception
     */
    public function run(Area $area): void
    {
        $rootPath = $_SERVER['DOCUMENT_ROOT'];

        if (empty($rootPath)) {
            $rootPath = substr((string) Environment::getPublicPath(), 0, -1);
        }

        $this->dbFolder = $rootPath . '/db/';

        $this->testTablesForDifferences();
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
     *
     * @deprecated
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
     *
     * @deprecated
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * @return string
     *
     * @deprecated
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
     * @throws Exception
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
    public function addMessage(string $message, int $type = AbstractMessage::INFO): void
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
     * @throws Exception
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
                AbstractMessage::WARNING
            );
        }
    }
}
