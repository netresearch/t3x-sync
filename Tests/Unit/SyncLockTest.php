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

namespace Netresearch\Sync\Tests\Unit;

use Netresearch\Sync\SyncLock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Test case for SyncLock.
 *
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class SyncLockTest extends TestCase
{
    private function createSyncLock(mixed $configuration): SyncLock
    {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($configuration);

        return new SyncLock($extensionConfiguration);
    }

    #[Test]
    public function lockStateAndMessageComeFromTheExtensionConfiguration(): void
    {
        $syncLock = $this->createSyncLock(['syncModuleLocked' => '1', 'syncModuleLockedMessage' => 'Maintenance']);

        self::assertTrue($syncLock->isLocked());
        self::assertSame('Maintenance', $syncLock->getLockMessage());
    }

    #[Test]
    public function missingSettingsMeanUnlockedWithoutMessage(): void
    {
        $syncLock = $this->createSyncLock([]);

        self::assertFalse($syncLock->isLocked());
        self::assertSame('', $syncLock->getLockMessage());
    }
}
