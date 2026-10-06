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

namespace Netresearch\Sync\Tests\Unit\Service;

use Netresearch\Sync\Service\StorageService;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[CoversClass(StorageService::class)]
final class StorageServiceTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();

        parent::tearDown();
    }

    private function useStorages(mixed $configuredStorageUid, ResourceStorage $defaultStorage, ?ResourceStorage $configuredStorage = null): void
    {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($configuredStorageUid);
        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfiguration);

        $resourceFactory = $this->createStub(ResourceFactory::class);
        $resourceFactory->method('getDefaultStorage')->willReturn($defaultStorage);
        $resourceFactory->method('getStorageObject')->willReturn($configuredStorage ?? $defaultStorage);
        GeneralUtility::setSingletonInstance(ResourceFactory::class, $resourceFactory);
    }

    private function createStorage(bool $isPublic): ResourceStorage
    {
        $storage = $this->createStub(ResourceStorage::class);
        $storage->method('isPublic')->willReturn($isPublic);

        return $storage;
    }

    #[Test]
    public function configuredStorageHoldsTheSyncFiles(): void
    {
        $configuredStorage = $this->createStorage(false);
        $this->useStorages('3', $this->createStorage(true), $configuredStorage);

        self::assertSame($configuredStorage, (new StorageService())->getDefaultStorage());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unsetStorageUids(): array
    {
        return [
            'zero'         => ['0'],
            'empty string' => [''],
            'missing'      => [null],
        ];
    }

    #[Test]
    #[DataProvider('unsetStorageUids')]
    public function defaultStorageOfTypo3HoldsTheSyncFilesWithoutConfiguredStorage(mixed $storageUid): void
    {
        $defaultStorage = $this->createStorage(true);
        $this->useStorages($storageUid, $defaultStorage, $this->createStorage(false));

        self::assertSame($defaultStorage, (new StorageService())->getDefaultStorage());
    }

    #[Test]
    public function credentialTablesAreNotAllowedInAPublicStorage(): void
    {
        $this->useStorages('0', $this->createStorage(true));

        self::assertSame(
            ['be_users', 'tx_scheduler_task'],
            (new StorageService())->getTablesNotAllowedInStorage(['be_groups', 'be_users', 'tx_scheduler_task', 'pages']),
        );
    }

    #[Test]
    public function credentialTablesAreAllowedInAStorageThatIsNotPublic(): void
    {
        $this->useStorages('3', $this->createStorage(true), $this->createStorage(false));

        self::assertSame([], (new StorageService())->getTablesNotAllowedInStorage(['be_groups', 'be_users']));
    }

    #[Test]
    public function otherTablesAreAllowedInAPublicStorage(): void
    {
        $this->useStorages('0', $this->createStorage(true));

        self::assertSame(
            [],
            (new StorageService())->getTablesNotAllowedInStorage(['pages', 'tt_content', 'sys_file', 'fe_groups', 'be_groups']),
        );
    }
}
