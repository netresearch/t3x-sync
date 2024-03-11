<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\ViewHelpers\Folder;

use Closure;
use Netresearch\Sync\Service\StorageService;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Filter\FileExtensionFilter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Returns an array of files found in the provided path.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class FilesViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        $this->registerArgument(
            'directory',
            'string',
            'The name and path to the lock file directory',
            true
        );
    }

    /**
     * @param array<string, mixed>      $arguments
     * @param Closure                   $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return File[]
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): array {
        /** @var FileExtensionFilter $filter */
        $filter = GeneralUtility::makeInstance(FileExtensionFilter::class);
        $filter->setAllowedFileExtensions(['gz']);

        $subFolder = GeneralUtility::makeInstance(StorageService::class)
            ->getSyncFolder()
            ->getSubfolder($arguments['directory']);

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
