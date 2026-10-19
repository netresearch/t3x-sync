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

use Netresearch\Sync\Tests\Unit\Helper\Fixtures\ConfiguredArea;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the handling of mistyped values in the area configuration.
 *
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class AreaConfigurationTest extends TestCase
{
    private ConfiguredArea $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = new ConfiguredArea(0);
    }

    #[Test]
    public function entriesThatAreNoArrayAreSkipped(): void
    {
        self::assertSame([0], array_keys($this->area->areas));
    }

    #[Test]
    public function scalarValuesAreConvertedToTheDeclaredType(): void
    {
        self::assertSame('5', $this->area->getName());
        self::assertSame('All pages', $this->area->getDescription());
        self::assertSame(0, $this->area->getId());
    }

    #[Test]
    public function listsKeepOnlyElementsOfTheDeclaredType(): void
    {
        self::assertSame([1, 4], $this->area->getDocType());
        self::assertSame([], $this->area->getNotDocType());
        self::assertSame(['a.txt'], $this->area->getFilesToSync());
    }

    #[Test]
    public function aNotDocTypeThatIsNoArrayExcludesNothing(): void
    {
        self::assertTrue($this->area->isDocTypeAllowed(['doktype' => 3]));
    }

    #[Test]
    public function systemsAreCompletedWithDefaults(): void
    {
        self::assertSame(
            [
                'live' => [
                    'name'      => 'Live',
                    'directory' => 'live',
                    'url-path'  => '',
                    'notify'    => ['type' => 'ftp', 'tls' => false],
                    'hide'      => true,
                ],
                'stage' => [
                    'name'      => '',
                    'directory' => 'stage',
                    'url-path'  => '',
                    'notify'    => [],
                    'hide'      => false,
                ],
            ],
            $this->area->getSystems(),
        );
        self::assertSame(['live', 'stage'], $this->area->getDirectories());
    }

    #[Test]
    public function anUnknownSystemIsAnEmptyArray(): void
    {
        self::assertSame([], $this->area->getSystem('unknown'));
    }
}
