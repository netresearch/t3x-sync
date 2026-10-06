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

namespace Netresearch\Sync\Tests\Unit\Traits;

use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Service\StorageService;
use Netresearch\Sync\Tests\Unit\Traits\Fixtures\TargetLockSubject;
use Netresearch\Sync\Traits\SyncTargetLockTrait;
use Override;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;

#[CoversTrait(SyncTargetLockTrait::class)]
final class SyncTargetLockTraitTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $backupRequest = [];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->backupRequest = $_REQUEST;
        $_REQUEST            = ['lock' => ['stage' => '1']];
    }

    #[Override]
    protected function tearDown(): void
    {
        $_REQUEST = $this->backupRequest;

        parent::tearDown();
    }

    private function createBackendUser(bool $isAdmin): BackendUserAuthentication
    {
        $backendUser = $this->createStub(BackendUserAuthentication::class);
        $backendUser->method('isAdmin')->willReturn($isAdmin);

        return $backendUser;
    }

    private function createArea(): Area
    {
        $area = $this->createStub(Area::class);
        $area->method('getSystem')->willReturn(['name' => 'stage', 'directory' => 'stage']);

        return $area;
    }

    #[Test]
    public function administratorLocksTheRequestedTargetSystem(): void
    {
        $lockFile = $this->createMock(File::class);
        $lockFile->expects(self::once())->method('setContents')->with('lock');

        $systemFolder = $this->createStub(Folder::class);

        $storage = $this->createMock(ResourceStorage::class);
        $storage->expects(self::once())
            ->method('createFile')
            ->with('.lock', $systemFolder)
            ->willReturn($lockFile);

        $systemFolder->method('getStorage')->willReturn($storage);

        $syncFolder = $this->createStub(Folder::class);
        $syncFolder->method('getSubfolder')->willReturn($systemFolder);

        $storageService = $this->createStub(StorageService::class);
        $storageService->method('getSyncFolder')->willReturn($syncFolder);
        $storageService->method('getDefaultStorage')->willReturn($storage);

        $subject = new TargetLockSubject($storageService, $this->createArea(), $this->createBackendUser(true));
        $subject->handle();

        self::assertSame(['message.target_locked'], $subject->messages);
    }

    #[Test]
    public function lockRequestOfAUserWhoIsNotAdministratorChangesNothing(): void
    {
        $storageService = $this->createMock(StorageService::class);
        $storageService->expects(self::never())->method('getSyncFolder');
        $storageService->expects(self::never())->method('getDefaultStorage');

        $subject = new TargetLockSubject($storageService, $this->createArea(), $this->createBackendUser(false));
        $subject->handle();

        self::assertSame([], $subject->messages);
    }

    #[Test]
    public function unlockRequestOfAUserWhoIsNotAdministratorChangesNothing(): void
    {
        $_REQUEST = ['lock' => ['stage' => '0']];

        $storageService = $this->createMock(StorageService::class);
        $storageService->expects(self::never())->method('getSyncFolder');
        $storageService->expects(self::never())->method('getDefaultStorage');

        $subject = new TargetLockSubject($storageService, $this->createArea(), $this->createBackendUser(false));
        $subject->handle();

        self::assertSame([], $subject->messages);
    }
}
