<?php
/**
 * CLI Robot
 *
 * PHP version 5
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Alexander Opitz <alexander.opitz@netresearch.de>
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */

use Netresearch\Sync\Service\ClearCache;

defined('TYPO3_cliMode') or die('You cannot run this script directly!');

/**
 * CLI interface for nr_sync
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Alexander Opitz <alexander.opitz@netresearch.de>
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */
class Nr_Sync_Cli extends t3lib_cli
{
    /**
     * @var int CLI return code: All okay.
     */
    public const CLI_OK            = 0;

    /**
     * @var int CLI return code: Error on given command.
     */
    public const CLI_ERROR_COMMAND = 1;

    /**
     * @var int CLI return code: Error with given file.
     */
    public const CLI_ERROR_FILE    = 2;

    /**
     * @var int CLI return code: Error unknown.
     */
    public const CLI_ERROR_UNKNOWN = 255;

    /**
     * @var ClearCache Service object to call.
     */
    private $service;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Running parent class constructor
        parent::__construct();

        // Setting help texts:
        $this->cli_help['name']
            = 'Nr_Sync - Clears the cache for given uids in given tables';
        $this->cli_help['synopsis'] = '###OPTIONS###';
        $this->cli_help['description']
            = 'Operations for Synchronisation to Live'."\n"
            . 'The script clears the cache for given uids and tables.'."\n"
            . 'It calls the typo3 clear cache functionality for the given table';
        $this->cli_help['examples']
            = '/.../cli_dispatch.phpsh nr_sync TASK [OPTIONS]';
        $this->cli_help['author'] = 'Alexander Opitz';
        $this->cli_help['license'] = 'AGPL v3';
        $this->cli_help['available tasks'] = 'clearCache';

        $this->cli_options[]
            = ['-f filename', 'Filename with listed "table:uid" form.'];
        $this->cli_options[]
            = ['-d data', 'Data in the "table:uid" form.'];
    }

    /**
     * Main CLI function with echo output.
     *
     * @return void
     */
    public function CLI_main(): void
    {
        // validate input
        $this->cli_validateArgs();

        // get task (function)
        $strTask = (string) $this->cli_args['_DEFAULT'][1];

        if ($strTask === 'clearCache') {
            $nResult = $this->cliClearCache();
        } else {
            $this->cli_help();
            $nResult = self::CLI_OK;
        }

        exit ($nResult);
    }

    /**
     * Function for the clearCache task. With echo output.
     *
     * @return int CLI Error code.
     */
    private function cliClearCache(): int
    {
        if ($this->cli_isArg('-f')) {
            $strFilename = $this->cli_argValue('-f');
            if (is_readable($strFilename)) {
                $arData = $this->getDataFromFile($strFilename);
            } else {
                $this->cli_echo('Cannot read file'."\n", true);
                return self::CLI_ERROR_FILE;
            }
        } elseif ($this->cli_isArg('-d')) {
            $arData = explode(',', $this->cli_argValue('-d'));
        } else {
            $this->cli_echo('You need to specify -f or -d'."\n", true);
            return self::CLI_ERROR_COMMAND;
        }
        if (!$this->setupClearCacheService()) {
            $this->cli_echo('Something went wrong during init'."\n", true);
            return self::CLI_ERROR_UNKNOWN;
        }
        $this->runClearCacheService($arData);
        return self::CLI_OK;
    }

    /**
     * Setups the needed ClearCacheService with echo output.
     *
     * @return bool True if initialization was ok otherwise false.
     */
    private function setupClearCacheService(): bool
    {
        $this->service = t3lib_div::makeInstanceService('nrClearCache');

        if (is_object($this->service)) {
            return true;
        }

        $this->cli_echo(
            'Error: Could not find nrClearCache service'."\n",
            true
        );
        return false;
    }

    /**
     * Reads data from a readable file. The file can contain lines of comma
     * separated "table:uid" entries.
     *
     * @param string $strFilename Name of a readable file.
     *
     * @return array Array of the "table:uid" values.
     */
    private function getDataFromFile(string $strFilename): array
    {
        $arData = [];
        $arFileLines = file($strFilename);
        foreach ($arFileLines as $strFileLine) {
            $arData = array_merge(
                $arData,
                explode(',', trim($strFileLine))
            );
        }

        return $arData;
    }

    /**
     * CLI runner with echo output
     *
     * @param array $arData Array with values in table:uid order.
     *
     * @return void
     */
    private function runClearCacheService(array $arData): void
    {
        $this->cli_echo('TYPO3 cache clearing...'."\n");
        $this->service->clearCaches($arData);
        $this->cli_echo('caches cleared'."\n");
    }
}

$robot = t3lib_div::makeInstance('Nr_Sync_Cli');
$robot->CLI_main();
