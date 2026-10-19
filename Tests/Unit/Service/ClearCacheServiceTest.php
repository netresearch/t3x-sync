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

use Netresearch\Sync\Service\ClearCacheService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Test case for ClearCacheService.
 *
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class ClearCacheServiceTest extends TestCase
{
    #[Test]
    public function pageCachesAreClearedWithoutALogger(): void
    {
        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->expects(self::once())->method('clear_cacheCmd')->with(5);

        // No setLogger(): the service runs outside the DI container
        (new ClearCacheService($this->createStub(CacheManager::class), $dataHandler))
            ->clearCaches(['pages:5']);
    }
}
