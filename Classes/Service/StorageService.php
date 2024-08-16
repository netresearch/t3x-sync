<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Service;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderWritePermissionsException;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * StorageTrait.
 *
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class StorageService
{
    /**
     * @var ResourceStorage|null
     */
    private ?ResourceStorage $defaultStorage = null;

    /**
     * @var Folder|null
     */
    private ?Folder $tempFolder = null;

    /**
     * Identifier for TempFolder.
     *
     * @var string
     */
    private string $tempFolderIdentifier = 'nr_sync_temp/';

    /**
     * @var string
     */
    private string $baseFolderIdentifier = 'nr_sync/';

    /**
     * @return string
     */
    public function getTempFolderIdentifier(): string
    {
        return $this->tempFolderIdentifier;
    }

    /**
     * @return string
     */
    public function getBaseFolderIdentifier(): string
    {
        return $this->baseFolderIdentifier;
    }

    /**
     * @return ResourceFactory
     */
    private function getResourceFactory(): ResourceFactory
    {
        return GeneralUtility::makeInstance(ResourceFactory::class);
    }

    /**
     * Returns the default storage.
     *
     * @return ResourceStorage
     */
    public function getDefaultStorage(): ResourceStorage
    {
        if ($this->defaultStorage instanceof ResourceStorage) {
            return $this->defaultStorage;
        }

        $this->defaultStorage = $this->getResourceFactory()->getDefaultStorage();

        return $this->defaultStorage;
    }

    /**
     * Returns an instance of the temp-folder Instance.
     *
     * @return Folder
     *
     * @throws InsufficientFolderAccessPermissionsException
     * @throws InsufficientFolderWritePermissionsException
     */
    public function getTempFolder(): Folder
    {
        if ($this->tempFolder instanceof Folder) {
            return $this->tempFolder;
        }

        $storage = $this->getResourceFactory()->getStorageObject(1);

        if (Environment::isCli()) {
            $storage->setEvaluatePermissions(false);
        }

        try {
            if ($storage->hasFolder($this->tempFolderIdentifier) === false) {
                $storage->createFolder($this->tempFolderIdentifier);
            }
        } catch (ExistingTargetFolderException) {
        }

        $this->tempFolder = $storage->getFolder($this->tempFolderIdentifier);

        return $this->tempFolder;
    }

    /**
     * @return Folder
     *
     * @throws InsufficientFolderAccessPermissionsException
     * @throws InsufficientFolderWritePermissionsException
     */
    public function getSyncFolder(): Folder
    {
        try {
            if ($this->getDefaultStorage()->hasFolder($this->baseFolderIdentifier) === false) {
                $this->getDefaultStorage()->createFolder($this->baseFolderIdentifier);
            }
        } catch (ExistingTargetFolderException) {
        }

        return $this->getDefaultStorage()->getFolder($this->baseFolderIdentifier);
    }
}
