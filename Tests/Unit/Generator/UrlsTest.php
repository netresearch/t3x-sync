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

namespace Netresearch\Sync\Tests\Unit\Generator;

use Netresearch\Sync\Controller\BaseSyncModuleController;
use Netresearch\Sync\Generator\Urls;
use Netresearch\Sync\Service\StorageService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test case for Urls.
 *
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class UrlsTest extends TestCase
{
    #[Test]
    public function aSyncWithoutUrlsWritesNoUrlFile(): void
    {
        $storageService = $this->createMock(StorageService::class);
        $storageService->expects(self::never())->method(self::anything());

        $sync = $this->createMock(BaseSyncModuleController::class);
        $sync->expects(self::never())->method('getTarget');

        // arUrlsPerMachine is missing, as in DumpFileTrait::createClearCacheFile()
        (new Urls($storageService))->postProcessSync(
            [
                'arUrlsOnce'  => [],
                'bProcess'    => true,
                'bSyncResult' => true,
            ],
            $sync,
        );
    }
}
