<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync;

use Doctrine\DBAL\Exception;
use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Traits\FlashMessageTrait;
use Netresearch\Sync\Traits\TranslationTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function in_array;
use function is_array;

/**
 * Class SyncList.
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class SyncList
{
    use TranslationTrait;
    use FlashMessageTrait;

    /**
     * @var string[][][][]
     */
    public array $areas;

    /**
     * @var ConnectionPool
     */
    private readonly ConnectionPool $connectionPool;

    /**
     * @var array<int, array<int, array<string, string|bool>>>
     */
    private array $syncList = [];

    /**
     * @var string
     */
    private string $id = self::class;

    /**
     * SyncList constructor.
     *
     * @param ConnectionPool $connectionPool
     */
    public function __construct(
        ConnectionPool $connectionPool
    ) {
        $this->connectionPool = $connectionPool;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $syncListId
     */
    public function load(string $syncListId): void
    {
        $this->syncList = (array) $this->getBackendUserAuthentication()->getSessionData('nr_sync_synclist' . $syncListId);
        $this->id       = $syncListId;
    }

    /**
     * Saves the sync list to user session.
     */
    public function saveSyncList(): void
    {
        $this
            ->getBackendUserAuthentication()
            ->setAndSaveSessionData('nr_sync_synclist' . $this->id, $this->syncList);
    }

    /**
     * Adds given data to sync list, if page ID doesn't already exist.
     *
     * @param array<string, array<int, array<int, mixed>>> $data The data to add to sync list
     *
     * @return void
     */
    public function addToSyncList(array $data): void
    {
        $data['removeable'] = true;

        // TODO: Nur Prüfen ob gleiche PageID schon drin liegt
        if (isset($data['pageID'], $this->syncList[(int) $data['areaID']])
            && $this->isInTree((int) $data['pageID'], $this->syncList[(int) $data['areaID']])
        ) {
            $this->addMessage(
                $this->getLabel('error.page_is_marked'),
                ContextualFeedbackSeverity::ERROR
            );
        } else {
            $this->syncList[(int) $data['areaID']][] = $data;
        }
    }

    /**
     * Removes given data from the sync list.
     *
     * @param array<string, array<int, array<int, mixed>>> $data Data to remove to sync list
     *
     * @return void
     */
    public function deleteFromSyncList(array $data): void
    {
        $deleteArea   = array_keys($data['delete']);
        $deletePageId = array_keys($data['delete'][$deleteArea[0]]);

        foreach ($this->syncList[$deleteArea[0]] as $key => $syncPage) {
            if ($syncPage['removeable']
                && ((int) $syncPage['pageID']) === $deletePageId[0]
            ) {
                unset($this->syncList[$deleteArea[0]][$key]);

                // Fix index order after removal
                if ($this->syncList[$deleteArea[0]] !== []) {
                    $this->syncList[$deleteArea[0]]
                        = array_values($this->syncList[$deleteArea[0]]);
                } else {
                    unset($this->syncList[$deleteArea[0]]);
                }

                break;
            }
        }
    }

    /**
     * Checks whether the given page ID is already in the sync list.
     *
     * @param int                                   $pid      The page ID
     * @param array<int, array<string, mixed>>|null $syncList A list of pages
     *
     * @return bool
     */
    private function isInTree(int $pid, ?array $syncList = null): bool
    {
        if (is_array($syncList)) {
            foreach ($syncList as $syncPage) {
                if (((int) $syncPage['pageID']) === $pid) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return BackendUserAuthentication
     */
    private function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->syncList === [];
    }

    /**
     * @return array<int, array<int, array<string, string|bool>>>
     */
    public function getAsArray(): array
    {
        return $this->syncList;
    }

    /**
     * Gibt alle PageIDs zurück die durch eine Syncliste definiert wurden.
     * Und Editiert werden dürfen.
     *
     * @param int $areaId Area
     *
     * @return int[]
     *
     * @throws Exception
     */
    public function getAllPageIDs(int $areaId): array
    {
        $pageIds = [[]];

        foreach ($this->syncList[$areaId] as $syncPage) {
            // Prüfen ob User Seite Bearbeiten darf
            $pageRow = BackendUtility::getRecord('pages', (int) $syncPage['pageID']);

            if ($this->getBackendUserAuthentication()->doesUserHaveAccess($pageRow, Permission::PAGE_EDIT)) {
                $pageIds[] = [
                    (int) $syncPage['pageID'],
                ];
            }

            // Wenn der ganze Baum syncronisiert werden soll
            // getSubpagesAndCount liefert nur Pages zurück die Editiert werden
            // dürfen
            // @TODO
            if ($syncPage['type'] === 'tree') {
                /** @var Area $area */
                $area = GeneralUtility::makeInstance(
                    Area::class,
                    (int) $syncPage['areaID']
                );

                $arCount = $this->getSubpagesAndCount(
                    (int) $syncPage['pageID'],
                    $area,
                    $dummy,
                    0,
                    (int) $syncPage['levelmax']
                );

                $pageIds[] = $this->getPageIDsFromTree($arCount);
            }
        }

        $pageIds = array_filter(array_merge(...$pageIds));
        $pageIds = array_merge($pageIds, $this->getPageTranslations($pageIds));

        return array_unique($pageIds);
    }

    /**
     * @param int $areaId
     */
    public function emptyArea(int $areaId): void
    {
        unset($this->syncList[$areaId]);
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
     * Returns the page, its subpages and their number for a given page ID if the user can edit this page.
     *
     * @param int                     $pid      The page id to count on
     * @param array<string, int>|null &$arCount Information about the count data
     * @param int                     $level    Depth on which we are
     * @param int                     $levelMax Maximum depth to search for
     * @param string[]|null           $tables   Tables this task manages
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getSubpagesAndCount(
        int $pid,
        Area $area,
        ?array &$arCount = null,
        int $level = 0,
        int $levelMax = 1,
        ?array $tables = null
    ): array {
        $arCountDefault = [
            'count'      => 0,
            'deleted'    => 0,
            'noaccess'   => 0,
            'falses'     => 0,
            'other_area' => 0,
        ];

        if (!is_array($arCount) || $arCount === []) {
            $arCount = $arCountDefault;
        }

        $return = [];

        if ($pid < 0 || ($level >= $levelMax && $levelMax !== 0)) {
            return $return;
        }

        $queryBuilder = $this->getQueryBuilderForTable('pages');

        $result = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $pid)
            )
            ->executeQuery();

        while ($pageRow = $result->fetchAssociative()) {
            if (in_array($pageRow['doktype'], $area->getNotDocType(), true)) {
                continue;
            }

            if (isset($area->areas[$pageRow['uid']])) {
                ++$arCount['other_area'];
                continue;
            }

            if (($area->getDocType() !== [])
                && !in_array($pageRow['doktype'], $area->getDocType(), true)
            ) {
                ++$arCount['falses'];
                continue;
            }

            $arSub = $this->getSubpagesAndCount(
                (int) $pageRow['uid'],
                $area,
                $arCount,
                $level + 1,
                $levelMax,
                $tables
            );

            if ($this->getBackendUserAuthentication()->doesUserHaveAccess($pageRow, Permission::PAGE_EDIT)) {
                $return[] = [
                    'page' => $pageRow,
                    'sub'  => $arSub,
                ];
            } else {
                $return[] = [
                    'sub' => $arSub,
                ];
                ++$arCount['noaccess'];
            }

            // The count for our own site
            if ($this->pageContainsData($pageRow['uid'], $tables)) {
                ++$arCount['count'];

                if ($pageRow['deleted'] === 1) {
                    ++$arCount['deleted'];
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
     * @param int           $id     The page id to look for
     * @param string[]|null $tables Tables this task manages
     *
     * @return bool TRUE if data exists otherwise FALSE
     *
     * @throws Exception
     */
    public function pageContainsData(int $id, ?array $tables = null): bool
    {
        if ($tables === null) {
            return false;
        }

        if (in_array('pages', $tables, true)) {
            return true;
        }

        foreach ($tables as $tableName) {
            if (isset($GLOBALS['TCA'][$tableName])) {
                $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);

                $count = $queryBuilder
                    ->count('pid')
                    ->from($tableName)
                    ->where($queryBuilder->expr()->eq('pid', $id))
                    ->executeQuery()
                    ->fetchOne();

                if ($count > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns all IDs from a page tree.
     *
     * @param array<int|string, array<string, mixed>> $tree The page tree to get IDs from
     *
     * @return int[]
     */
    private function getPageIDsFromTree(array $tree): array
    {
        $pageIds = [[]];

        foreach ($tree as $value) {
            // See if there is a page on the branch (may be missing due to editing rights)
            if (isset($value['page'])) {
                $pageIds[] = [
                    $value['page']['uid'],
                ];
            }

            // See if there are any underlying pages
            if (is_array($value['sub'])) {
                $pageIds[] = $this->getPageIDsFromTree($value['sub']);
            }
        }

        return array_filter(array_merge(...$pageIds));
    }

    /**
     * Returns the page IDs of translation records.
     *
     * @param int[] $pageIds Array with page IDs
     *
     * @return int[]
     *
     * @throws Exception
     */
    private function getPageTranslations(array $pageIds = []): array
    {
        if ($pageIds === []) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilderForTable('pages');

        return $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where($queryBuilder->expr()->in('l10n_parent', $pageIds))
            ->groupBy('uid')
            ->executeQuery()
            ->fetchFirstColumn();
    }
}
