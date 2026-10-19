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

namespace Netresearch\Sync\Helper;

use Stringable;

use function is_array;
use function is_int;
use function is_scalar;
use function is_string;

/**
 * Narrows untyped values (superglobals, $GLOBALS, TCA, database rows, view
 * helper arguments) to the types the code works with.
 *
 * toString() and toInt() give the same result as a (string) or (int) cast
 * for scalars and null; for arrays and objects without __toString(), where a
 * cast emits a warning or throws an Error, they return the empty string or 0.
 *
 * @internal
 */
final class TypeHelper
{
    /**
     * Returns the value at the given key path of nested arrays, or NULL if a
     * key is missing or a value on the way is not an array.
     */
    public static function arrayPath(mixed $value, int|string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Returns the value if it is an array, otherwise an empty array.
     *
     * @return array<mixed>
     */
    public static function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    public static function toString(mixed $value): string
    {
        if (is_scalar($value) || $value === null || $value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }

    public static function toInt(mixed $value): int
    {
        if (is_scalar($value) || $value === null) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * Returns the string elements of an array, keys preserved; other elements are dropped.
     *
     * @return array<string>
     */
    public static function toStringList(mixed $value): array
    {
        $result = [];

        foreach (self::toArray($value) as $key => $item) {
            if (is_string($item)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * Returns the integer elements of an array (numeric strings converted), keys preserved;
     * other elements are dropped.
     *
     * @return array<int>
     */
    public static function toIntList(mixed $value): array
    {
        $result = [];

        foreach (self::toArray($value) as $key => $item) {
            if (is_int($item)) {
                $result[$key] = $item;
            } elseif (is_string($item) && is_numeric($item)) {
                $result[$key] = (int) $item;
            }
        }

        return $result;
    }
}
