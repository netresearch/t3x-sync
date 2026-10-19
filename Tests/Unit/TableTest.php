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

use Netresearch\Sync\Table;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test case for Table.
 *
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class TableTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TCA']);

        parent::tearDown();
    }

    #[Test]
    public function controlFieldsAreReadFromTheTableControlSection(): void
    {
        $GLOBALS['TCA']['tx_test']['ctrl'] = [
            'delete'        => 'deleted',
            'enablecolumns' => [
                'disabled' => 'hidden',
                'endtime'  => 'endtime',
            ],
        ];

        self::assertSame(
            ['delete' => 'deleted', 'disabled' => 'hidden', 'endtime' => 'endtime'],
            (new Table('tx_test', 'dump.sql'))->getControlFieldsFromTcaByTableName(),
        );
    }

    #[Test]
    public function controlFieldsThatAreNoStringAreIgnored(): void
    {
        $GLOBALS['TCA']['tx_test']['ctrl'] = [
            'delete'        => 'deleted',
            'enablecolumns' => [
                'disabled' => 5,
                'endtime'  => ['endtime'],
            ],
        ];

        self::assertSame(
            ['delete' => 'deleted'],
            (new Table('tx_test', 'dump.sql'))->getControlFieldsFromTcaByTableName(),
        );
    }

    #[Test]
    public function aTableWithoutTcaHasNoControlFields(): void
    {
        self::assertSame([], (new Table('tx_unknown', 'dump.sql'))->getControlFieldsFromTcaByTableName());
    }
}
