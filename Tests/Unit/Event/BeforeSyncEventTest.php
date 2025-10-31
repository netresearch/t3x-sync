<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Tests\Unit\Event;

use Netresearch\Sync\Event\BeforeSyncEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test case for BeforeSyncEvent.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final class BeforeSyncEventTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $tables     = ['table1', 'table2'];
        $dumpFile   = 'test.sql';
        $targetName = 'production';

        $event = new BeforeSyncEvent($tables, $dumpFile, $targetName);

        self::assertSame($tables, $event->getTables());
        self::assertSame($dumpFile, $event->getDumpFile());
        self::assertSame($targetName, $event->getTargetName());
    }

    #[Test]
    public function constructorSetsPropertiesWithNullTarget(): void
    {
        $tables   = ['table1', 'table2'];
        $dumpFile = 'test.sql';

        $event = new BeforeSyncEvent($tables, $dumpFile);

        self::assertSame($tables, $event->getTables());
        self::assertSame($dumpFile, $event->getDumpFile());
        self::assertNull($event->getTargetName());
    }

    #[Test]
    public function setTablesModifiesTableList(): void
    {
        $initialTables = ['table1', 'table2'];
        $newTables     = ['table3', 'table4', 'table5'];
        $dumpFile      = 'test.sql';

        $event = new BeforeSyncEvent($initialTables, $dumpFile);
        $event->setTables($newTables);

        self::assertSame($newTables, $event->getTables());
        self::assertNotSame($initialTables, $event->getTables());
    }

    #[Test]
    public function setTablesCanAddTables(): void
    {
        $initialTables = ['table1', 'table2'];
        $dumpFile      = 'test.sql';

        $event = new BeforeSyncEvent($initialTables, $dumpFile);

        $modifiedTables   = $event->getTables();
        $modifiedTables[] = 'table3';
        $event->setTables($modifiedTables);

        self::assertCount(3, $event->getTables());
        self::assertContains('table3', $event->getTables());
    }

    #[Test]
    public function setTablesCanRemoveTables(): void
    {
        $initialTables = ['table1', 'table2', 'table3'];
        $dumpFile      = 'test.sql';

        $event = new BeforeSyncEvent($initialTables, $dumpFile);

        $modifiedTables = array_filter($event->getTables(), fn ($table) => $table !== 'table2');
        $event->setTables(array_values($modifiedTables));

        self::assertCount(2, $event->getTables());
        self::assertNotContains('table2', $event->getTables());
    }
}
