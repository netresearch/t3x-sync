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

namespace Netresearch\Sync\Tests\Unit\Traits\Fixtures;

use Netresearch\Sync\Traits\DumpFileTrait;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Exposes private methods of DumpFileTrait to the unit tests.
 */
final class DumpFileSubject
{
    use DumpFileTrait;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param string[]             $columnNames
     * @param array<string, mixed> $row
     */
    public function insertUpdateLine(string $tableName, array $columnNames, array $row): string
    {
        return $this->buildInsertUpdateLine($tableName, $columnNames, $row);
    }

    /**
     * @return array<mixed>
     */
    public function referenceTablesOf(string $tableName): array
    {
        $this->referenceTables = [];
        $this->addMMReferenceTables($tableName);

        return $this->referenceTables;
    }
}
