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

use Netresearch\Sync\SyncList;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Test case for SyncList.
 *
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class SyncListTest extends TestCase
{
    private mixed $backupBackendUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupBackendUser = $GLOBALS['BE_USER'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['BE_USER'] = $this->backupBackendUser;

        parent::tearDown();
    }

    /**
     * @param array<mixed> $sessionData
     */
    private function createSyncList(array $sessionData, ?BackendUserAuthentication $backendUser = null): SyncList
    {
        if (!$backendUser instanceof BackendUserAuthentication) {
            $backendUser = $this->createStub(BackendUserAuthentication::class);
        }

        $backendUser->method('getSessionData')->willReturn($sessionData);
        $GLOBALS['BE_USER'] = $backendUser;

        $syncList = new class ($this->createStub(ConnectionPool::class)) extends SyncList {
            #[Override]
            protected function getPageRecord(int $pageId): ?array
            {
                // Every page of the list was deleted after it was marked
                return null;
            }
        };
        $syncList->load('test');

        return $syncList;
    }

    #[Test]
    public function aMarkedPageThatNoLongerExistsIsLeftOutOfThePageIds(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->expects(self::never())->method('doesUserHaveAccess');

        $syncList = $this->createSyncList(
            [0 => [['pageID' => '5', 'areaID' => '0', 'type' => 'page', 'removeable' => true]]],
            $backendUser,
        );

        self::assertSame([], $syncList->getAllPageIDs(0));
    }

    #[Test]
    public function loadKeepsOnlyScalarValuesWithStringKeys(): void
    {
        $syncList = $this->createSyncList(
            [0 => [['pageID' => 5, 'removeable' => true, 'nested' => ['x'], 3 => 'int key']], 'x' => 'no entries'],
        );

        self::assertSame(
            [0 => [['pageID' => '5', 'removeable' => true]]],
            $syncList->getAsArray(),
        );
    }

    #[Test]
    public function deleteWithoutDeleteDataChangesNothing(): void
    {
        $entries  = [0 => [['pageID' => '5', 'removeable' => true]]];
        $syncList = $this->createSyncList($entries);

        $syncList->deleteFromSyncList([]);

        self::assertSame($entries, $syncList->getAsArray());
    }

    #[Test]
    public function deleteRemovesTheMarkedPage(): void
    {
        $syncList = $this->createSyncList([0 => [['pageID' => '5', 'removeable' => true]]]);

        $syncList->deleteFromSyncList(['delete' => [0 => [5 => 'x']]]);

        self::assertTrue($syncList->isEmpty());
    }
}
