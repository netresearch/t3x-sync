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

namespace Netresearch\Sync\Tests\Unit\Helper;

use Netresearch\Sync\Exception;
use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Tests\Unit\Helper\Fixtures\FtpRecordingArea;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Area::class)]
final class AreaTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function notifyConfigurations(): array
    {
        return [
            'without tls setting' => [['type' => 'ftp', 'host' => 'ftp.example.org', 'user' => 'u', 'password' => 'p'], true],
            'tls enabled'         => [['type' => 'ftp', 'host' => 'ftp.example.org', 'user' => 'u', 'password' => 'p', 'tls' => true], true],
            'tls disabled'        => [['type' => 'ftp', 'host' => 'ftp.example.org', 'user' => 'u', 'password' => 'p', 'tls' => false], false],
        ];
    }

    /**
     * @param array<string, mixed> $ftpConfig
     */
    #[Test]
    #[DataProvider('notifyConfigurations')]
    public function ftpNotificationUsesTlsUnlessTheConfigurationTurnsItOff(array $ftpConfig, bool $expectedTls): void
    {
        $area = FtpRecordingArea::create();

        try {
            $area->notify($ftpConfig);
            self::fail('A failed connection must raise an exception');
        } catch (Exception) {
        }

        self::assertSame([['ftp.example.org', $expectedTls]], $area->connections);
    }
}
