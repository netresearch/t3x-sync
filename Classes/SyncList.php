<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync;

use Doctrine\DBAL\FetchMode;
use Netresearch\Sync\Helper\Area;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use function count;
use function in_array;
use function is_array;

/**
 * Class SyncList
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class SyncList
{
    /**
     * @var ConnectionPool
     */
    private $connectionPool;

    /**
     * @var FlashMessageService
     */
    private $flashMessageService;

    /**
     * @var array
     */
    private $syncList = [];

    /**
     * @var string
     */
    private $id = '';

    /**
     * SyncList constructor.
     *
     * @param ConnectionPool $connectionPool
     * @param FlashMessageService $flashMessageService
     */
    public function __construct(
        ConnectionPool $connectionPool,
        FlashMessageService $flashMessageService
    ) {
        $this->connectionPool = $connectionPool;
        $this->flashMessageService = $flashMessageService;
    }

    /**
     * @param string $syncListId
     */
    public function load(string $syncListId): void
    {
        $this->syncList = (array) $this->getBackendUser()->getSessionData('nr_sync_synclist' . $syncListId);
        $this->id       = $syncListId;
    }

    /**
     * Saves the sync list to user session.
     */
    public function saveSyncList(): void
    {
        $this->getBackendUser()
            ->setAndSaveSessionData('nr_sync_synclist' . $this->id, $this->syncList);
    }

    /**
     * Adds given data to sync list, if page ID doesn't already exists.
     *
     * @param array $data Data to add to sync list.
     *
     * @return void
     */
    public function addToSyncList(array $data): void
    {
        $data['removeable'] = true;

        // TODO: Nur Prüfen ob gleiche PageID schon drin liegt
        if ($this->isInTree((int) $data['pageID'], $this->syncList[$data['areaID']])) {
            $this->addMessage(
                'Diese Seite wurde bereits zur Synchronisation vorgemerkt.',
                FlashMessage::ERROR
            );
        } else {
            $this->syncList[$data['areaID']][] = $data;
        }
    }

    /**
     * Adds error message to message queue. Message types are defined as class constants self::STYLE_*.
     *
     * @param string $message The message
     * @param int    $type    The message type
     */
    public function addMessage(string $message, int $type = FlashMessage::INFO): void
    {
        /** @var FlashMessage $flashMessage */
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class, $message, '', $type, true
        );

        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->addMessage($flashMessage);
    }

    /**
     * Adds given data to sync list, if pageId does not already exists.
     *
     * @param array $data Data to add to sync list.
     *
     * @return void
     */
    public function deleteFromSyncList(array $data): void
    {
        $arDeleteArea = array_keys($data['delete']);
        $arDeletePageID = array_keys(
            $data['delete'][$arDeleteArea[0]]
        );
        foreach ($this->syncList[$arDeleteArea[0]] as $key => $value) {
            if ($value['removeable']
                && $value['pageID'] == $arDeletePageID[0]
            ) {
                unset($this->syncList[$arDeleteArea[0]][$key]);
                if (count($this->syncList[$arDeleteArea[0]]) === 0) {
                    unset($this->syncList[$arDeleteArea[0]]);
                }
                break;
            }
        }
    }

    /**
     * Checks whether the given page ID is already in the sync list.
     *
     * @param int        $pid      The page ID
     * @param null|array $syncList A list of page IDs
     *
     * @return bool
     */
    private function isInTree(int $pid, array $syncList = null): bool
    {
        if (is_array($syncList)) {
            foreach ($syncList as $value) {
                if ($value['pageID'] === $pid) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return BackendUserAuthentication
     */
    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count($this->syncList) < 1;
    }

    /**
     * @return array
     */
    public function getAsArray(): array
    {
        return $this->syncList;
    }

    /**
     * Gibt alle PageIDs zurück die durch eine Syncliste definiert wurden.
     * Und Editiert werden dürfen
     *
     * @param int $areaID Area
     *
     * @return array
     */
    public function getAllPageIDs(int $areaID): array
    {
        $syncList = $this->syncList[$areaID];

        $pageIDs = [];
        foreach ($syncList as $arSyncPage) {
            // Prüfen ob User Seite Bearbeiten darf
            $arPage = BackendUtility::getRecord('pages', (int) $arSyncPage['pageID']);
            if ($this->getBackendUser()->doesUserHaveAccess($arPage, 2)) {
                $pageIDs[] = (int) $arSyncPage['pageID'];
            }

            // Wenn der ganze Baum syncronisiert werden soll
            // getSubpagesAndCount liefert nur Pages zurück die Editiert werden
            // dürfen
            // @TODO
            if ($arSyncPage['type'] === 'tree') {
                /** @var Area $area */
                $area = GeneralUtility::makeInstance(Area::class, $arSyncPage['areaID']);
                $arCount = $this->getSubpagesAndCount(
                    (int) $arSyncPage['pageID'],
                    $dummy,
                    0,
                    (int) $arSyncPage['levelmax'],
                    $area->getNotDocType(),
                    $area->getDocType()
                );

                $a = $this->getPageIDsFromTree($arCount);
                $pageIDs = array_merge($pageIDs, $a);

            }
        }

        $pageIDs = array_merge($pageIDs, $this->getPageTranslations($pageIDs));

        return array_unique($pageIDs);
    }

    /**
     * @param $areaID
     */
    public function emptyArea($areaID): void
    {
        unset($this->syncList[$areaID]);
    }

    /**
     * Returns a TYPO3 QueryBuilder instance for a given table, without any restriction.
     *
     * @param string $tableName The table name
     *
     * @return QueryBuilder
     */
    private function getQueryBuilderForTable(string $tableName): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    /**
     * Returns the page, its sub-pages and their number for a given page ID,
     * if this page can be edited by the user.
     *
     * @param int        $pid               The page id to count on
     * @param null|array &$arCount          Information about the count data
     * @param int        $nLevel            Depth on which we are
     * @param int        $nLevelMax         Maximum depth to search for
     * @param null|array $arDocTypesExclude TYPO3 doc types to exclude
     * @param null|array $arDocTypesOnly    TYPO3 doc types to count only
     * @param null|array $tables            Tables this task manages
     *
     * @return array
     */
    public function getSubpagesAndCount(
        int $pid,
        array &$arCount = null,
        int $nLevel = 0,
        int $nLevelMax = 1,
        array $arDocTypesExclude = null,
        array $arDocTypesOnly = null,
        array $tables = null
    ): array {
        $arCountDefault = [
            'count'      => 0,
            'deleted'    => 0,
            'noaccess'   => 0,
            'falses'     => 0,
            'other_area' => 0,
        ];

        if (!is_array($arCount) || empty($arCount)) {
            $arCount = $arCountDefault;
        }

        $return = [];

        if ($pid < 0 || ($nLevel >= $nLevelMax && $nLevelMax !== 0)) {
            return $return;
        }

        $queryBuilder = $this->getQueryBuilderForTable('pages');

        $result = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $pid)
            )
            ->execute();

        while ($arPage = $result->fetchAssociative()) {
            if (is_array($arDocTypesExclude)
                && in_array($arPage['doktype'], $arDocTypesExclude, true)) {
                continue;
            }

            if (isset($this->areas[$arPage['uid']])) {
                $arCount['other_area']++;
                continue;
            }

            if (count($arDocTypesOnly)
                && !in_array($arPage['doktype'], $arDocTypesOnly, true)
            ) {
                $arCount['falses']++;
                continue;
            }

            $arSub = $this->getSubpagesAndCount(
                (int) $arPage['uid'],
                $arCount,
                $nLevel + 1,
                $nLevelMax,
                $arDocTypesExclude,
                $arDocTypesOnly,
                $tables
            );

            if ($this->getBackendUser()->doesUserHaveAccess($arPage, 2)) {
                $return[] = [
                    'page' => $arPage,
                    'sub'  => $arSub,
                ];
            } else {
                $return[] = [
                    'sub' => $arSub,
                ];
                $arCount['noaccess']++;
            }

            // Die Zaehlung fuer die eigene Seite
            if ($this->pageContainsData($arPage['uid'], $tables)) {
                $arCount['count']++;
                if ($arPage['deleted']) {
                    $arCount['deleted']++;
                }
            }
        }

        return $return;
    }

    /**
     * Tests if given tables holds data on given page id.
     * Returns true if "pages" is one of the tables to look for without checking
     * if page exists.
     *
     * @param int        $nId    The page id to look for
     * @param null|array $tables Tables this task manages
     *
     * @return bool TRUE if data exists otherwise FALSE.
     */
    private function pageContainsData(int $nId, array $tables = null): bool
    {
        if ($tables === null) {
            return false;
        }

        if (in_array('pages', $tables, true)) {
            return true;
        }

        foreach ($tables as $strTableName) {
            if (isset($GLOBALS['TCA'][$strTableName])) {
                $queryBuilder = $this->connectionPool->getQueryBuilderForTable($strTableName);

                $nCount = $queryBuilder->count('pid')
                    ->from($strTableName)
                    ->where($queryBuilder->expr()->eq('pid', $nId))
                    ->execute()
                    ->fetchOne();

                if ($nCount > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns all IDs from a page tree.
     *
     * @param array $tree The page tree to get IDs from
     *
     * @return array
     */
    private function getPageIDsFromTree(array $tree): array
    {
        $pageIDs = [];

        foreach ($tree as $value) {
            // See if there is a page on the branch (may be missing due to editing rights)
            if (isset($value['page'])) {
                $pageIDs[] = $value['page']['uid'];
            }

            // See if there are any underlying pages
            if (is_array($value['sub'])) {
                $pageIDs = array_merge(
                    $pageIDs,
                    $this->getPageIDsFromTree($value['sub'])
                );
            }
        }

        return $pageIDs;
    }

    /**
     * Returns the page id of translation records
     *
     * @param array $pages Array with page ids
     *
     * @return array
     */
    private function getPageTranslations(array $pages = []): array
    {
        if (empty($pages)) {
            return [];
        }

        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                                    ->getConnectionForTable('pages');

        $queryBuilder = $connection->createQueryBuilder();

        return $queryBuilder->select('uid')
            ->from('pages')
            ->where($queryBuilder->expr()->in('l10n_parent', $pages))
            ->groupBy('uid')
            ->execute()
            ->fetchFirstColumn();
    }
}
