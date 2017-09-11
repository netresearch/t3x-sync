<?php
/**
 * Created by PhpStorm.
 * User: sebastian.mendel
 * Date: 2017-09-08
 * Time: 14:47
 */

namespace Netresearch\Sync;


use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SyncListManager implements SingletonInterface
{
    /**
     * @var SyncList[]
     */
    protected $syncLists = [];


    /**
     * @param $syncListId
     * @return SyncList
     */
    public function getSyncList($syncListId)
    {
        if (null === $this->syncLists[$syncListId]) {
            $this->syncLists[$syncListId] = GeneralUtility::makeInstance(SyncList::class);

            $this->syncLists[$syncListId]->load($syncListId);
        }

        return $this->syncLists[$syncListId];
    }

}