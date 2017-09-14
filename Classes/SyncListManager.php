<?php
/**
 * Created by PhpStorm.
 * User: sebastian.mendel
 * Date: 2017-09-08
 * Time: 14:47
 */

namespace Netresearch\Sync;


use TYPO3\CMS\Core\SingletonInterface;

class SyncListManager implements SingletonInterface
{

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     * @inject
     */
    protected $objectManager;

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
            $this->syncLists[$syncListId] = $this->objectManager->get(SyncList::class);

            $this->syncLists[$syncListId]->load($syncListId);
        }

        return $this->syncLists[$syncListId];
    }

}