<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Command;

use Netresearch\Sync\Service\ClearCache as ClearCacheService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The clear-cache command class.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch http://www.netresearch.de/
 * @link    http://www.netresearch.de/
 */
class ClearCache extends Command
{
    /**
     * CLI return code: All okay.
     *
     * @var int
     */
    private const CLI_OK = 0;

    /**
     * CLI return code: Error on given command.
     *
     * @var int
     */
    private const CLI_ERROR_COMMAND = 1;

    /**
     * CLI return code: Error with given file.
     *
     * @var int
     */
    private const CLI_ERROR_FILE = 2;

    /**
     * CLI return code: Error unknown.
     *
     * @var int
     */
    private const CLI_ERROR_UNKNOWN = 255;

    /**
     * @var SymfonyStyle
     */
    private SymfonyStyle $io;

    /**
     * The clear cache service.
     *
     * @var ClearCacheService
     */
    private ClearCacheService $clearCacheService;

    /**
     * ClearCache constructor.
     *
     * @param ClearCacheService $clearCacheService
     * @param string $name
     */
    public function __construct(
        ClearCacheService $clearCacheService,
        string $name = 'sync:cache:clear'
    ) {
        parent::__construct($name);

        $this->clearCacheService = $clearCacheService;
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Clears the cache for given UIDs in given tables.')
            ->setHelp('Operations for Synchronisation to Live. The script clears the cache for '
                . 'given UIDs and tables. It calls the typo3 clear cache functionality for the given table');

        $this->addUsage('--filename=tables.txt');
        $this->addUsage('--data=tt_content:123,pages:4556');

        $this->addOption(
            'filename',
            'f',
            InputOption::VALUE_REQUIRED,
            'A file containing a list of table names, each line could be a comma separated list of "table:uid" entries.'
        );

        $this->addOption(
            'data',
            'd',
            InputOption::VALUE_REQUIRED,
            'A comma separated line of "table:uid" entries.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        if ($inputFilename = $input->getOption('filename')) {
            if (is_readable($inputFilename)) {
                $data = $this->getDataFromFile($inputFilename);
            } else {
                $this->io->error('Cannot read file "' . $inputFilename . '".');
                return self::CLI_ERROR_FILE;
            }
        } elseif ($inputData = $input->getOption('data')) {
            $data = explode(',', $inputData);
        } else {
            $this->io->error('You need to specify either option -f or -d. Type --help for more information.');
            return self::CLI_ERROR_COMMAND;
        }

        if (!$this->setupClearCacheService()) {
            $this->io->error('Something went wrong during init.');
            return self::CLI_ERROR_UNKNOWN;
        }

        $this->runClearCacheService($data);

        return self::CLI_OK;
    }

    /**
     * Reads data from a readable file. The file can contain lines of comma
     * separated "table:uid" entries.
     *
     * @param string $filename Name of a readable file
     *
     * @return string[] Array of the "table:uid" values
     */
    private function getDataFromFile(string $filename): array
    {
        $fileLines = file($filename);
        $result    = [[]];

        foreach ($fileLines as $fileLine) {
            $result[] = explode(',', trim($fileLine));
        }

        return array_filter(array_merge(...$result));
    }

    /**
     * Setups the needed ClearCacheService with echo output.
     *
     * @return bool True if initialization was ok otherwise false.
     */
    private function setupClearCacheService(): bool
    {
        if ($this->clearCacheService instanceof ClearCacheService) {
            return true;
        }

        $this->io->error('Error: Could not find nrClearCache service.');
        return false;
    }

    /**
     * CLI runner with echo output
     *
     * @param string[] $data Array with values in table:uid order.
     *
     * @return void
     */
    private function runClearCacheService(array $data): void
    {
        $this->io->success('TYPO3 cache clearing....');
        $this->clearCacheService->clearCaches($data);
        $this->io->success('Caches cleared.');
    }
}
