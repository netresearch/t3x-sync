<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Controller;

use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\SinglePageSyncModuleInterface;
use Netresearch\Sync\Traits\FlashMessageTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

/**
 * Class SinglePageSyncModuleController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class SinglePageSyncModuleController extends BaseSyncModuleController implements SinglePageSyncModuleInterface
{
    use FlashMessageTrait;

    /**
     * @param Area $area
     *
     * @return void
     */
    protected function run(Area $area): void
    {
        parent::run($area);

        $this->moduleTemplate->assign('isSingePageSyncModule', true);

        // Sync single pages/page trees
        $this->showPageSelection($this->getTables());
        $this->manageSyncList();

        $this->useSyncList = true;
    }

    /**
     * Shows the page selection, depending on selected id and tables to look at.
     *
     * @param string[] $tables Tables this task manages
     *
     * @return void
     */
    private function showPageSelection(array $tables): void
    {
        if ($this->pageUid === 0) {
            $this->addErrorMessage($this->getLabel('error.no_page'));

            return;
        }

        $record = BackendUtility::getRecord('pages', $this->pageUid);

        if ($record === null) {
            $this->addErrorMessage(
                $this->getLabel(
                    'error.page_not_load',
                    [
                        '{page_selected}' => $this->pageUid,
                    ]
                )
            );

            return;
        }

        if (!$this->getArea()->isDocTypeAllowed($record)) {
            $this->addErrorMessage($this->getLabel('error.page_type_not_allowed'));

            return;
        }

        $bShowButton = false;
        $recursion   = (int) $this
            ->getBackendUserAuthentication()
            ->getSessionData(
                'nr_sync_synclist_levelmax' . $this->getSyncList()->getId()
            );

        if (isset($_POST['data']['recursion'])) {
            $recursion = (int) $_POST['data']['levelmax'];

            $this->getBackendUserAuthentication()
                ->setAndSaveSessionData('nr_sync_synclist_levelmax' . $this->getSyncList()->getId(), $recursion);
        }

        if ($recursion < 1) {
            $recursion = 1;
        }

        $arCount = [];

        $this->getSyncList()
            ->getSubpagesAndCount(
                $this->pageUid,
                $this->getArea(),
                $arCount,
                0,
                $recursion,
                $tables
            );

        $strTitle = $this->getArea()->getName() . ' - ' . $record['uid'] . ' - ' . $record['title'];

        if (((int) $record['doktype']) === PageRepository::DOKTYPE_SHORTCUT) {
            $strTitle .= ' - LINK';
        }

        $this->moduleTemplate->assign('pageValid', true);
        $this->moduleTemplate->assign('title', $strTitle);
        $this->moduleTemplate->assign('arCount', $arCount);
        $this->moduleTemplate->assign('record', $record);

        if ($this->getSyncList()->pageContainsData($this->pageUid, $tables)) {
            $this->moduleTemplate->assign('pageContainsData', true);
            $bShowButton = true;
        }

        if ($arCount['count'] > 0) {
            $bShowButton = true;
        }

        $this->moduleTemplate->assign('bShowButton', $bShowButton);

        if ($bShowButton) {
            $this->moduleTemplate->assign('recursion', $recursion);
        } else {
            $this->addErrorMessage(
                $this->getLabel('error.select_mark_type')
            );
        }
    }

    /**
     * Manages adding and deleting of pages/trees to the sync list.
     *
     * @return void
     */
    protected function manageSyncList(): void
    {
        // ID hinzufügen
        if (isset($_POST['data']['add'])) {
            if (isset($_POST['data']['type'])) {
                $this->getSyncList()
                    ->addToSyncList(
                        $_POST['data']
                    );
            } else {
                $this->addErrorMessage($this->getLabel('error.select_mark_type'));
            }
        }

        // ID entfernen
        if (isset($_POST['data']['delete'])) {
            $this->getSyncList()->deleteFromSyncList($_POST['data']);
        }

        $this->getSyncList()->saveSyncList();
    }
}
