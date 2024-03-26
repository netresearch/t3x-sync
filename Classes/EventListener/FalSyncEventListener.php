<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\EventListener;

use Netresearch\Sync\Controller\FalSyncModuleController;
use Netresearch\Sync\Event\FalSyncEvent;
use Netresearch\Sync\Helper\Area;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Event listener which is called to immediately trigger an FAL sync.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class FalSyncEventListener
{
    /**
     * Invoke the event listener.
     *
     * @param FalSyncEvent $event
     *
     * @return void
     */
    public function __invoke(FalSyncEvent $event): void
    {
        /** @var Area $area */
        $area = GeneralUtility::makeInstance(Area::class, $event->getAreaId());

        /** @var ModuleProvider $moduleProvider */
        $moduleProvider = GeneralUtility::makeInstance(ModuleProvider::class);

        /** @var ModuleInterface $module */
        $module = $moduleProvider->getModule('netresearch_sync_fal');

        /** @var FalSyncModuleController $syncModule */
        $syncModule = GeneralUtility::makeInstance(FalSyncModuleController::class);
        $syncModule->setModuleData(
            ModuleData::createFromModule(
                $module,
                $module->getDefaultModuleData()
            )
        );
        $syncModule->initFolders($area);

        foreach (array_keys($area->getSystems()) as $key) {
            if (strtolower($key) === 'archive') {
                continue;
            }

            $dumpFile = $syncModule->getDumpFile();

            if ($dumpFile === '') {
                continue;
            }

            if ($dumpFile === null) {
                continue;
            }

            $syncModule->createDumpToAreas(
                $syncModule->getTables(),
                $event->getDumpFilePrefix() . '-' . $dumpFile,
                $key
            );
        }
    }
}
