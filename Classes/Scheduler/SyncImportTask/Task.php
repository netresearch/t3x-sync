<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Scheduler\SyncImportTask;

use Netresearch\NrScheduler\AbstractTask;
use Netresearch\Sync\Service\ClearCacheService;
use Netresearch\Sync\Service\StorageService;
use Netresearch\Sync\Traits\DatabaseConnectionTrait;
use RuntimeException;
use TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFileAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function is_array;

/**
 * Scheduler task to import the sync MyQSL files.
 *
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class Task extends AbstractTask
{
    use DatabaseConnectionTrait;

    /**
     * @var string
     */
    public string $syncStoragePath = '';

    /**
     * @var string
     */
    public string $syncUrlsPath = '';

    /**
     * @return StorageService
     */
    private function getStorageService(): StorageService
    {
        return GeneralUtility::makeInstance(StorageService::class);
    }

    /**
     * @return ClearCacheService
     */
    private function getClearCacheService(): ClearCacheService
    {
        return GeneralUtility::makeInstance(ClearCacheService::class);
    }

    /**
     * Executes the task.
     *
     * @return bool
     *
     * @throws FileOperationErrorException
     * @throws InsufficientFileAccessPermissionsException
     * @throws InsufficientFolderAccessPermissionsException
     */
    public function executeTask(): bool
    {
        return $this->importSqlFiles()
            && $this->clearCaches();
    }

    /**
     * Import the sql files.
     *
     * @return bool
     *
     * @throws FileOperationErrorException
     * @throws InsufficientFileAccessPermissionsException
     * @throws InsufficientFolderAccessPermissionsException
     */
    private function importSqlFiles(): bool
    {
        $sqlFiles = $this->findFilesToImport();

        if ($sqlFiles === []) {
            $this->logger->info('Nothing to import');
        }

        $databaseConnection = $this->getDefaultDatabaseConnection();

        foreach ($sqlFiles as $name => $file) {
            $this->logger->info('Start import of file ' . $name);

            $tmpFile = '/tmp/' . $name;
            file_put_contents($tmpFile, gzdecode($file->getContents()));
            $this->deleteFile($file);

            $command = sprintf(
                'mysql -h"%s" -u"%s" -p"%s" "%s" < %s 2>&1',
                $databaseConnection->getParams()['host'],
                $databaseConnection->getParams()['user'],
                $databaseConnection->getParams()['password'],
                $databaseConnection->getParams()['dbname'],
                $tmpFile
            );

            $output = [];
            $return = '';
            exec($command, $output, $return);
            unlink($tmpFile);

            if ($return > 0) {
                $this->logger->error(sprintf('Something went wrong on importing %s. Please check further logs and the file.', $name));
                throw new RuntimeException(implode(PHP_EOL, $output));
            }

            $this->logger->info(sprintf('Import %s is finished. Delete File.', $name));
            $this->logger->info('Import was done successful.');
        }

        return true;
    }

    /**
     * Runs the clear cache command to flush the page caches.
     *
     * @return bool
     *
     * @throws FileOperationErrorException
     * @throws InsufficientFileAccessPermissionsException
     * @throws InsufficientFolderAccessPermissionsException
     */
    private function clearCaches(): bool
    {
        $urlFiles = $this->findUrlFiles();

        foreach ($urlFiles as $name => $file) {
            $this->logger->info('start processing ' . $name);

            $matches = [];
            preg_match_all('/[a-zA-Z]+:[0-9|a-zA-Z\-_]+/', $file->getContents(), $matches);

            $cacheEntries = reset($matches);

            if (!is_array($cacheEntries)) {
                $cacheEntries = [];
            }

            $this->getClearCacheService()
                ->clearCaches($cacheEntries);

            $this->deleteFile($file);
            $this->logger->info('finish processing ' . $name);
        }

        return true;
    }

    /**
     * Returns all files in a folder in the default storage.
     *
     * @param string $folderPath Path to folder
     *
     * @return File[]
     *
     * @throws InsufficientFolderAccessPermissionsException
     */
    private function findFilesInFolder(string $folderPath): array
    {
        $storage = $this->getStorageService()->getDefaultStorage();
        $folder  = $storage->getFolder($folderPath);

        return $storage->getFilesInFolder($folder);
    }

    /**
     * Deletes a file in the default storage.
     *
     * @param File $file File object of file to delte
     *
     * @return void
     *
     * @throws FileOperationErrorException
     * @throws InsufficientFileAccessPermissionsException
     */
    private function deleteFile(File $file): void
    {
        $this->getStorageService()->getDefaultStorage()->deleteFile($file);
    }

    /**
     * Returns an array with sql.qz files to import.
     *
     * @return File[]
     *
     * @throws InsufficientFolderAccessPermissionsException
     */
    private function findFilesToImport(): array
    {
        $files = $this->findFilesInFolder($this->syncStoragePath);

        foreach ($files as $fileName => $file) {
            if ($file->getExtension() !== 'gz') {
                unset($files[$fileName]);
            }
        }

        return $files;
    }

    /**
     * Returns a array with files containing urls.
     *
     * @return File[]
     *
     * @throws InsufficientFolderAccessPermissionsException
     */
    private function findUrlFiles(): array
    {
        $files = $this->findFilesInFolder($this->syncUrlsPath);

        foreach (array_keys($files) as $name) {
            if ((bool) preg_match('/once\.txt$/', $name) === false) {
                unset($files[$name]);
            }
        }

        return $files;
    }
}
