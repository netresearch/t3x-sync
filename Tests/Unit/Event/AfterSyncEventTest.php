<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Tests\Unit\Event;

use Netresearch\Sync\Event\AfterSyncEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test case for AfterSyncEvent.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final class AfterSyncEventTest extends TestCase
{
    #[Test]
    public function constructorSetsPropertiesWithSuccess(): void
    {
        $tables     = ['table1', 'table2'];
        $dumpFile   = 'test.sql';
        $targetName = 'production';
        $success    = true;

        $event = new AfterSyncEvent($tables, $dumpFile, $targetName, $success);

        self::assertSame($tables, $event->getTables());
        self::assertSame($dumpFile, $event->getDumpFile());
        self::assertSame($targetName, $event->getTargetName());
        self::assertTrue($event->isSuccess());
    }

    #[Test]
    public function constructorSetsPropertiesWithFailure(): void
    {
        $tables     = ['table1', 'table2'];
        $dumpFile   = 'test.sql';
        $targetName = 'production';
        $success    = false;

        $event = new AfterSyncEvent($tables, $dumpFile, $targetName, $success);

        self::assertSame($tables, $event->getTables());
        self::assertSame($dumpFile, $event->getDumpFile());
        self::assertSame($targetName, $event->getTargetName());
        self::assertFalse($event->isSuccess());
    }

    #[Test]
    public function constructorSetsPropertiesWithNullTarget(): void
    {
        $tables   = ['table1', 'table2'];
        $dumpFile = 'test.sql';
        $success  = true;

        $event = new AfterSyncEvent($tables, $dumpFile, null, $success);

        self::assertSame($tables, $event->getTables());
        self::assertSame($dumpFile, $event->getDumpFile());
        self::assertNull($event->getTargetName());
        self::assertTrue($event->isSuccess());
    }

    #[Test]
    public function propertiesAreReadonly(): void
    {
        $tables   = ['table1', 'table2'];
        $dumpFile = 'test.sql';
        $success  = true;

        $event = new AfterSyncEvent($tables, $dumpFile, null, $success);

        // Verify that the returned arrays are copies and modifying them doesn't affect the event
        $returnedTables   = $event->getTables();
        $returnedTables[] = 'table3';

        self::assertCount(2, $event->getTables());
        self::assertNotContains('table3', $event->getTables());
    }
}
