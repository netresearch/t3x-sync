<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\ViewHelpers\Folder;

use Netresearch\Sync\Service\StorageService;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderWritePermissionsException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Filter\FileExtensionFilter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns an array of files found in the provided path.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class FilesViewHelper extends AbstractViewHelper
{
    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'directory',
            'string',
            'The name and path to the lock file directory',
            true
        );
    }

    /**
     * @return File[]
     *
     * @throws InsufficientFolderAccessPermissionsException
     * @throws InsufficientFolderWritePermissionsException
     */
    public function render(): array
    {
        /** @var FileExtensionFilter $filter */
        $filter = GeneralUtility::makeInstance(FileExtensionFilter::class);
        $filter->setAllowedFileExtensions(['gz']);

        $subFolder = GeneralUtility::makeInstance(StorageService::class)
            ->getSyncFolder()
            ->getSubfolder($this->arguments['directory']);

        // .sql.gz
        $subFolder->setFileAndFolderNameFilters(
            [
                static fn (
                    string $itemName,
                    string $itemIdentifier,
                    string $parentIdentifier,
                    array $additionalInformation,
                    DriverInterface $driver
                ): bool|int => $filter->filterFileList(
                    $itemName,
                    $itemIdentifier,
                    $parentIdentifier,
                    $additionalInformation,
                    $driver
                ),
            ]
        );

        return $subFolder->getFiles();
    }
}
