<?php

/*
 * This file is part of the package netresearch/nr-sync.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Service;

use function array_intersect;
use function array_values;

use Exception;

use function is_numeric;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
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
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
class StorageService
{
    /**
     * Tables whose rows hold credentials or configuration that may carry credentials. Dumps of these tables are
     * written only to a storage that is not public.
     *
     * @var string[]
     */
    public const CREDENTIAL_TABLES = [
        'be_users',
        'fe_users',
        'tx_scheduler_task',
    ];

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
     * Returns the storage that holds the sync files: the storage set in the extension setting "storageUid",
     * or the default storage of TYPO3 if the setting is 0 or missing.
     *
     * @return ResourceStorage
     */
    public function getDefaultStorage(): ResourceStorage
    {
        if ($this->defaultStorage instanceof ResourceStorage) {
            return $this->defaultStorage;
        }

        $storageUid = $this->getConfiguredStorageUid();

        $this->defaultStorage = $storageUid > 0
            ? $this->getResourceFactory()->getStorageObject($storageUid)
            : $this->getResourceFactory()->getDefaultStorage();

        return $this->defaultStorage;
    }

    /**
     * Returns the tables of the list that must not be written to the sync storage because the storage is
     * public and the tables hold credentials.
     *
     * @param string[] $tables
     *
     * @return string[]
     */
    public function getTablesNotAllowedInStorage(array $tables): array
    {
        $credentialTables = array_values(array_intersect($tables, self::CREDENTIAL_TABLES));

        if (($credentialTables === []) || !$this->getDefaultStorage()->isPublic()) {
            return [];
        }

        return $credentialTables;
    }

    /**
     * @return int
     */
    private function getConfiguredStorageUid(): int
    {
        try {
            $storageUid = GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('nr_sync', 'storageUid');
        } catch (Exception) {
            return 0;
        }

        return is_numeric($storageUid) ? (int) $storageUid : 0;
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

        $storage = $this->getDefaultStorage();

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
