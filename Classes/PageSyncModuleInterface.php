<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync;

/**
 * The page sync module interface.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
interface PageSyncModuleInterface
{
    /**
     * Returns the page IDs which should also be synchronized.
     *
     * @return int[]
     */
    public function getPagesToSync(): array;
}
