<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Tests\Unit\Event;

use Netresearch\Sync\Event\ModifyTableListEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test case for ModifyTableListEvent.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final class ModifyTableListEventTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $tables           = ['table1', 'table2'];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyTableListEvent($tables, $moduleIdentifier);

        self::assertSame($tables, $event->getTables());
        self::assertSame($moduleIdentifier, $event->getModuleIdentifier());
    }

    #[Test]
    public function setTablesReplacesTableList(): void
    {
        $initialTables    = ['table1', 'table2'];
        $newTables        = ['table3', 'table4', 'table5'];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyTableListEvent($initialTables, $moduleIdentifier);
        $event->setTables($newTables);

        self::assertSame($newTables, $event->getTables());
        self::assertCount(3, $event->getTables());
    }

    #[Test]
    public function addTableAppendsTable(): void
    {
        $tables           = ['table1', 'table2'];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyTableListEvent($tables, $moduleIdentifier);
        $event->addTable('table3');

        self::assertCount(3, $event->getTables());
        self::assertContains('table3', $event->getTables());
    }

    #[Test]
    public function addTableDoesNotAddDuplicates(): void
    {
        $tables           = ['table1', 'table2'];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyTableListEvent($tables, $moduleIdentifier);
        $event->addTable('table1');

        self::assertCount(2, $event->getTables());
    }

    #[Test]
    public function removeTableRemovesExistingTable(): void
    {
        $tables           = ['table1', 'table2', 'table3'];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyTableListEvent($tables, $moduleIdentifier);
        $event->removeTable('table2');

        self::assertCount(2, $event->getTables());
        self::assertNotContains('table2', $event->getTables());
        self::assertContains('table1', $event->getTables());
        self::assertContains('table3', $event->getTables());
    }

    #[Test]
    public function removeTableDoesNothingForNonExistingTable(): void
    {
        $tables           = ['table1', 'table2'];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyTableListEvent($tables, $moduleIdentifier);
        $event->removeTable('table_nonexistent');

        self::assertCount(2, $event->getTables());
        self::assertSame($tables, $event->getTables());
    }

    #[Test]
    public function removeTableReindexesArray(): void
    {
        $tables           = ['table1', 'table2', 'table3'];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyTableListEvent($tables, $moduleIdentifier);
        $event->removeTable('table2');

        $resultTables = $event->getTables();

        // Verify array is reindexed (starts at 0)
        self::assertArrayHasKey(0, $resultTables);
        self::assertArrayHasKey(1, $resultTables);
        self::assertArrayNotHasKey(2, $resultTables);
    }

    #[Test]
    public function multipleOperations(): void
    {
        $tables           = ['table1', 'table2'];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyTableListEvent($tables, $moduleIdentifier);

        // Add tables
        $event->addTable('table3');
        $event->addTable('table4');

        // Remove a table
        $event->removeTable('table2');

        // Add duplicate (should be ignored)
        $event->addTable('table1');

        self::assertCount(3, $event->getTables());
        self::assertContains('table1', $event->getTables());
        self::assertNotContains('table2', $event->getTables());
        self::assertContains('table3', $event->getTables());
        self::assertContains('table4', $event->getTables());
    }
}
