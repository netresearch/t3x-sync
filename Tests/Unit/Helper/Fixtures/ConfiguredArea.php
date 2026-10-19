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

use Netresearch\Sync\Helper\Area;
use Override;

/**
 * Area that reads its configuration from the fixture file next to this class.
 */
final class ConfiguredArea extends Area
{
    #[Override]
    public function getAreaConfigurationFileLocation(): string
    {
        return __DIR__ . '/malformed-area-configuration.php';
    }
}
