<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * Class SyncListManager
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class SyncListManager implements SingletonInterface
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var SyncList[]
     */
    private array $syncLists = [];

    /**
     * SyncListManager constructor.
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * @param int $syncListId
     *
     * @return SyncList
     */
    public function getSyncList(int $syncListId): SyncList
    {
        if ($this->syncLists[$syncListId] === null) {
            /** @var SyncList $syncList */
            $syncList = $this->objectManager->get(SyncList::class);
            $syncList->load($syncListId);

            $this->syncLists[$syncListId] = $syncList;
        }

        return $this->syncLists[$syncListId];
    }
}
