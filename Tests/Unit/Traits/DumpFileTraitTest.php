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

namespace Netresearch\Sync\Tests\Unit\Traits;

use Netresearch\Sync\Tests\Unit\Traits\Fixtures\DumpFileSubject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Test case for DumpFileTrait.
 *
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class DumpFileTraitTest extends TestCase
{
    private DumpFileSubject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->createStub(Connection::class);
        $connection->method('quoteSingleIdentifier')
            ->willReturnCallback(static fn (string $identifier): string => '`' . $identifier . '`');
        $connection->method('quote')
            ->willReturnCallback(static fn (string $value): string => "'" . $value . "'");

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($connection);

        $this->subject = new DumpFileSubject($connectionPool);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TCA']);

        parent::tearDown();
    }

    #[Test]
    public function insertUpdateLineWritesNullForNullColumns(): void
    {
        $line = $this->subject->insertUpdateLine(
            'tt_content',
            ['uid', 'header', 'bodytext'],
            ['uid' => 7, 'header' => 'Head', 'bodytext' => null],
        );

        self::assertSame(
            "INSERT INTO `tt_content` (`uid`, `header`, `bodytext`) VALUES (7, 'Head', NULL)"
            . ' ON DUPLICATE KEY UPDATE `uid` = VALUES(`uid`), `header` = VALUES(`header`),'
            . ' `bodytext` = VALUES(`bodytext`);',
            $line,
        );
    }

    #[Test]
    public function insertUpdateLineKeepsNumericValuesUnquoted(): void
    {
        $line = $this->subject->insertUpdateLine(
            'pages',
            ['uid', 'sorting', 'title'],
            ['uid' => '3', 'sorting' => 1.5, 'title' => '42'],
        );

        self::assertStringContainsString('VALUES (3, 1.5, 42)', $line);
    }

    #[Test]
    public function referenceTablesIgnoreNonStringTableNames(): void
    {
        $mmConfig = ['type' => 'select', 'MM' => 'tx_test_mm'];

        $GLOBALS['TCA']['tx_test']['columns'] = [
            'valid'         => ['config' => $mmConfig],
            'int_mm'        => ['config' => ['type' => 'select', 'MM' => 5]],
            'array_foreign' => ['config' => ['type' => 'inline', 'foreign_table' => ['tx_other']]],
            'no_type'       => ['config' => ['MM' => 'tx_ignored_mm']],
        ];

        self::assertSame(
            ['tx_test_mm' => [$mmConfig]],
            $this->subject->referenceTablesOf('tx_test'),
        );
    }
}
