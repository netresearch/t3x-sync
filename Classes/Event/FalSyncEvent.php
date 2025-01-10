<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Event;

/**
 * This event is used to immediately trigger an FAL sync.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final class FalSyncEvent
{
    /**
     * Constructor.
     *
     * @param int    $areaId
     * @param string $dumpFilePrefix
     */
    public function __construct(
        private readonly int $areaId,
        private readonly string $dumpFilePrefix,
    ) {
    }

    /**
     * @return int
     */
    public function getAreaId(): int
    {
        return $this->areaId;
    }

    /**
     * @return string
     */
    public function getDumpFilePrefix(): string
    {
        return $this->dumpFilePrefix;
    }
}
