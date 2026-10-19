<?php

/*
 * This file is part of the package netresearch/nr-sync.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Controller;

use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Helper\TypeHelper;
use Netresearch\Sync\SinglePageSyncModuleInterface;
use Netresearch\Sync\Traits\FlashMessageTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

/**
 * Class SinglePageSyncModuleController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
class SinglePageSyncModuleController extends BaseSyncModuleController implements SinglePageSyncModuleInterface
{
    use FlashMessageTrait;

    /**
     * @param Area $area
     *
     * @return void
     */
    public function run(Area $area): void
    {
        parent::run($area);

        $this->getInitializedModuleTemplate()->assign('isSingePageSyncModule', true);

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
                    ],
                ),
            );

            return;
        }

        if (!$this->getArea()->isDocTypeAllowed($record)) {
            $this->addErrorMessage($this->getLabel('error.page_type_not_allowed'));

            return;
        }

        $bShowButton = false;
        $recursion   = TypeHelper::toInt(
            $this
                ->getBackendUserAuthentication()
                ->getSessionData(
                    'nr_sync_synclist_levelmax' . $this->getSyncList()->getId(),
                ),
        );

        if (TypeHelper::arrayPath($_POST, 'data', 'recursion') !== null) {
            $recursion = TypeHelper::toInt(TypeHelper::arrayPath($_POST, 'data', 'levelmax'));

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
                $tables,
            );

        $strTitle = $this->getArea()->getName()
            . ' - ' . TypeHelper::toString($record['uid'] ?? '')
            . ' - ' . TypeHelper::toString($record['title'] ?? '');

        if (TypeHelper::toInt($record['doktype'] ?? null) === PageRepository::DOKTYPE_SHORTCUT) {
            $strTitle .= ' - LINK';
        }

        $this->getInitializedModuleTemplate()->assign('pageValid', true);
        $this->getInitializedModuleTemplate()->assign('title', $strTitle);
        $this->getInitializedModuleTemplate()->assign('arCount', $arCount);
        $this->getInitializedModuleTemplate()->assign('record', $record);

        if ($this->getSyncList()->pageContainsData($this->pageUid, $tables)) {
            $this->getInitializedModuleTemplate()->assign('pageContainsData', true);
            $bShowButton = true;
        }

        if ($arCount['count'] > 0) {
            $bShowButton = true;
        }

        $this->getInitializedModuleTemplate()->assign('bShowButton', $bShowButton);

        if ($bShowButton) {
            $this->getInitializedModuleTemplate()->assign('recursion', $recursion);
        } else {
            $this->addErrorMessage(
                $this->getLabel('error.select_mark_type'),
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
        $data = TypeHelper::toArray($_POST['data'] ?? null);

        // ID hinzufügen
        if (isset($data['add'])) {
            if (isset($data['type'])) {
                $this->getSyncList()
                    ->addToSyncList(
                        $data,
                    );
            } else {
                $this->addErrorMessage($this->getLabel('error.select_mark_type'));
            }
        }

        // ID entfernen
        if (isset($data['delete'])) {
            $this->getSyncList()->deleteFromSyncList($data);
        }

        $this->getSyncList()->saveSyncList();
    }
}
