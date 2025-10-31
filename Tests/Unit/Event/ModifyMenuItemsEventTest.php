<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Tests\Unit\Event;

use Netresearch\Sync\Event\ModifyMenuItemsEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;

/**
 * Test case for ModifyMenuItemsEvent.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final class ModifyMenuItemsEventTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $menuItems        = [$this->createMock(MenuItem::class)];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyMenuItemsEvent($menuItems, $moduleIdentifier);

        self::assertSame($menuItems, $event->getMenuItems());
        self::assertSame($moduleIdentifier, $event->getCurrentModuleIdentifier());
    }

    #[Test]
    public function setMenuItemsReplacesMenuItems(): void
    {
        $initialMenuItems = [$this->createMock(MenuItem::class)];
        $newMenuItems     = [
            $this->createMock(MenuItem::class),
            $this->createMock(MenuItem::class),
        ];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyMenuItemsEvent($initialMenuItems, $moduleIdentifier);
        $event->setMenuItems($newMenuItems);

        self::assertSame($newMenuItems, $event->getMenuItems());
        self::assertCount(2, $event->getMenuItems());
    }

    #[Test]
    public function addMenuItemAppendsMenuItem(): void
    {
        $menuItems        = [$this->createMock(MenuItem::class)];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyMenuItemsEvent($menuItems, $moduleIdentifier);

        $newMenuItem = $this->createMock(MenuItem::class);
        $event->addMenuItem($newMenuItem);

        self::assertCount(2, $event->getMenuItems());
        self::assertSame($newMenuItem, $event->getMenuItems()[1]);
    }

    #[Test]
    public function addMultipleMenuItems(): void
    {
        $menuItems        = [];
        $moduleIdentifier = 'netresearch_sync_singlePage';

        $event = new ModifyMenuItemsEvent($menuItems, $moduleIdentifier);

        $event->addMenuItem($this->createMock(MenuItem::class));
        $event->addMenuItem($this->createMock(MenuItem::class));
        $event->addMenuItem($this->createMock(MenuItem::class));

        self::assertCount(3, $event->getMenuItems());
    }
}
