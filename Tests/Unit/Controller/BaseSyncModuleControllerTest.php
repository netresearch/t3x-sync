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

namespace Netresearch\Sync\Tests\Unit\Controller;

use Netresearch\Sync\Controller\BaseSyncModuleController;
use Netresearch\Sync\SyncLock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use TYPO3\CMS\Core\Http\ServerRequest;

#[CoversClass(BaseSyncModuleController::class)]
final class BaseSyncModuleControllerTest extends TestCase
{
    private function isSyncRequested(bool $moduleLocked, string $method, mixed $parsedBody): bool
    {
        $syncLock = $this->createStub(SyncLock::class);
        $syncLock->method('isLocked')->willReturn($moduleLocked);

        $controller = (new ReflectionClass(BaseSyncModuleController::class))->newInstanceWithoutConstructor();
        (new ReflectionClass(BaseSyncModuleController::class))
            ->getProperty('syncLock')
            ->setValue($controller, $syncLock);

        $request = (new ServerRequest('https://source.example.org/typo3/module/netresearch/sync', $method))
            ->withParsedBody($parsedBody);

        return (bool) (new ReflectionMethod(BaseSyncModuleController::class, 'isSyncRequested'))
            ->invoke($controller, $request);
    }

    #[Test]
    public function submittedFormStartsTheSyncWhileTheModuleIsUnlocked(): void
    {
        self::assertTrue($this->isSyncRequested(false, 'POST', ['data' => ['submit' => '1']]));
    }

    #[Test]
    public function submittedFormDoesNotStartTheSyncWhileTheModuleIsLocked(): void
    {
        self::assertFalse($this->isSyncRequested(true, 'POST', ['data' => ['submit' => '1']]));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function requestsWithoutSubmit(): array
    {
        return [
            'GET request'         => ['GET', null],
            'empty POST body'     => ['POST', []],
            'POST without data'   => ['POST', ['id' => '12']],
            'POST without submit' => ['POST', ['data' => ['lock' => '1']]],
        ];
    }

    #[Test]
    #[DataProvider('requestsWithoutSubmit')]
    public function requestWithoutSubmittedFormDoesNotStartTheSync(string $method, mixed $parsedBody): void
    {
        self::assertFalse($this->isSyncRequested(false, $method, $parsedBody));
    }
}
