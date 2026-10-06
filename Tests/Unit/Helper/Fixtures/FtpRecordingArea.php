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

namespace Netresearch\Sync\Tests\Unit\Helper\Fixtures;

use FTP\Connection;
use Netresearch\Sync\Helper\Area;
use Override;
use ReflectionClass;

/**
 * Area that records how the FTP notification opens its connection and never connects.
 */
final class FtpRecordingArea extends Area
{
    /**
     * @var list<array{string, bool}> Host and TLS flag of every connection attempt
     */
    public array $connections = [];

    /**
     * Creates the area without the constructor of Area, which reads the area configuration.
     */
    public static function create(): self
    {
        return (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param array<string, mixed> $ftpConfig
     */
    public function notify(array $ftpConfig): void
    {
        $this->notifyMasterViaFtp($ftpConfig);
    }

    #[Override]
    protected function openFtpConnection(string $host, bool $useTls): Connection|false
    {
        $this->connections[] = [$host, $useTls];

        return false;
    }
}
