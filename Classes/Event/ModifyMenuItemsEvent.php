<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Event;

use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;

/**
 * This event is dispatched when building the sync module menu.
 * It allows listeners to add, modify, or remove menu items.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final class ModifyMenuItemsEvent
{
    /**
     * Constructor.
     *
     * @param array<int, MenuItem> $menuItems
     * @param string               $currentModuleIdentifier
     */
    public function __construct(
        private array $menuItems,
        private readonly string $currentModuleIdentifier,
    ) {
    }

    /**
     * @return array<int, MenuItem>
     */
    public function getMenuItems(): array
    {
        return $this->menuItems;
    }

    /**
     * @param array<int, MenuItem> $menuItems
     *
     * @return void
     */
    public function setMenuItems(array $menuItems): void
    {
        $this->menuItems = $menuItems;
    }

    /**
     * @param MenuItem $menuItem
     *
     * @return void
     */
    public function addMenuItem(MenuItem $menuItem): void
    {
        $this->menuItems[] = $menuItem;
    }

    /**
     * @return string
     */
    public function getCurrentModuleIdentifier(): string
    {
        return $this->currentModuleIdentifier;
    }
}
