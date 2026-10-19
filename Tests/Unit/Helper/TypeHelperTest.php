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

use Netresearch\Sync\Helper\TypeHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

/**
 * Test case for TypeHelper.
 *
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class TypeHelperTest extends TestCase
{
    #[Test]
    public function arrayPathReturnsNestedValue(): void
    {
        $data = ['a' => ['b' => ['c' => 'value', 0 => 7]]];

        self::assertSame('value', TypeHelper::arrayPath($data, 'a', 'b', 'c'));
        self::assertSame(7, TypeHelper::arrayPath($data, 'a', 'b', 0));
        self::assertSame($data, TypeHelper::arrayPath($data));
    }

    #[Test]
    public function arrayPathReturnsNullForMissingKeyOrNonArray(): void
    {
        $data = ['a' => ['b' => 'string'], 'n' => null];

        self::assertNull(TypeHelper::arrayPath($data, 'x'));
        self::assertNull(TypeHelper::arrayPath($data, 'a', 'b', 'c'));
        self::assertNull(TypeHelper::arrayPath($data, 'n'));
        self::assertNull(TypeHelper::arrayPath('string', 'a'));
        self::assertNull(TypeHelper::arrayPath(null, 'a'));
    }

    #[Test]
    public function toArrayReturnsArraysAndEmptyArrayOtherwise(): void
    {
        self::assertSame([1, 'a' => 2], TypeHelper::toArray([1, 'a' => 2]));
        self::assertSame([], TypeHelper::toArray('a'));
        self::assertSame([], TypeHelper::toArray(null));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function toStringProvider(): iterable
    {
        yield 'string' => ['abc', 'abc'];
        yield 'int' => [42, '42'];
        yield 'float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'null' => [null, ''];
        yield 'array' => [['a'], ''];
        yield 'object' => [new stdClass(), ''];
        yield 'stringable' => [
            new class implements Stringable {
                public function __toString(): string
                {
                    return 'stringable';
                }
            },
            'stringable',
        ];
    }

    #[Test]
    #[DataProvider('toStringProvider')]
    public function toStringCastsScalarsAndReturnsEmptyStringOtherwise(mixed $value, string $expected): void
    {
        self::assertSame($expected, TypeHelper::toString($value));
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function toIntProvider(): iterable
    {
        yield 'int' => [42, 42];
        yield 'numeric string' => ['17', 17];
        yield 'non-numeric string' => ['abc', 0];
        yield 'float' => [2.9, 2];
        yield 'true' => [true, 1];
        yield 'null' => [null, 0];
        yield 'array' => [[5], 0];
        yield 'object' => [new stdClass(), 0];
    }

    #[Test]
    #[DataProvider('toIntProvider')]
    public function toIntCastsScalarsAndReturnsZeroOtherwise(mixed $value, int $expected): void
    {
        self::assertSame($expected, TypeHelper::toInt($value));
    }

    #[Test]
    public function toStringListKeepsOnlyStringsWithTheirKeys(): void
    {
        self::assertSame(
            [0 => 'a', 'k' => 'b'],
            TypeHelper::toStringList([0 => 'a', 1 => 2, 'k' => 'b', 'n' => null]),
        );
        self::assertSame([], TypeHelper::toStringList('a'));
    }

    #[Test]
    public function toIntListKeepsIntegersAndNumericStringsWithTheirKeys(): void
    {
        self::assertSame(
            [0 => 1, 1 => 2, 'k' => 3],
            TypeHelper::toIntList([0 => 1, 1 => '2', 2 => 'x', 'k' => 3, 'n' => null, 'f' => 1.5]),
        );
        self::assertSame([], TypeHelper::toIntList(5));
    }
}
