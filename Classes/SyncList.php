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

namespace Netresearch\Sync;

use Doctrine\DBAL\Exception;
use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Helper\TypeHelper;
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
use function is_bool;
use function is_int;
use function is_scalar;
use function is_string;

/**
 * Class SyncList.
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 *
 * @phpstan-type SyncListEntry array<string, string|bool>
 */
class SyncList
{
    use TranslationTrait;
    use FlashMessageTrait;

    /**
     * @var string[][][][]
     */
    public array $areas = [];

    /**
     * @var ConnectionPool
     */
    private readonly ConnectionPool $connectionPool;

    /**
     * @var array<int, array<int, SyncListEntry>>
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
        ConnectionPool $connectionPool,
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
        $syncList = [];

        foreach (TypeHelper::toArray($this->getBackendUserAuthentication()->getSessionData('nr_sync_synclist' . $syncListId)) as $areaId => $entries) {
            foreach (TypeHelper::toArray($entries) as $key => $entry) {
                $syncList[(int) $areaId][(int) $key] = $this->toEntry($entry);
            }
        }

        $this->syncList = $syncList;
        $this->id       = $syncListId;
    }

    /**
     * Keeps the scalar values with string keys of a sync list entry, as strings or booleans.
     *
     * @return SyncListEntry
     */
    private function toEntry(mixed $data): array
    {
        $entry = [];

        foreach (TypeHelper::toArray($data) as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_bool($value)) {
                $entry[$key] = $value;
            } elseif (is_scalar($value)) {
                $entry[$key] = (string) $value;
            }
        }

        return $entry;
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
     * @param array<mixed> $data The data to add to sync list
     *
     * @return void
     */
    public function addToSyncList(array $data): void
    {
        $data               = $this->toEntry($data);
        $data['removeable'] = true;
        $areaId             = (int) ($data['areaID'] ?? 0);

        // TODO: Nur Prüfen ob gleiche PageID schon drin liegt
        if (isset($data['pageID'], $this->syncList[$areaId])
            && $this->isInTree((int) $data['pageID'], $this->syncList[$areaId])
        ) {
            $this->addMessage(
                $this->getLabel('error.page_is_marked'),
                ContextualFeedbackSeverity::ERROR,
            );
        } else {
            $this->syncList[$areaId][] = $data;
        }
    }

    /**
     * Removes given data from the sync list.
     *
     * @param array<mixed> $data Data to remove from sync list: ['delete' => [<area ID> => [<page ID> => ...]]]
     *
     * @return void
     */
    public function deleteFromSyncList(array $data): void
    {
        $delete     = TypeHelper::toArray($data['delete'] ?? null);
        $deleteArea = array_key_first($delete);

        if (!is_int($deleteArea) || !isset($this->syncList[$deleteArea])) {
            return;
        }

        $deletePageId = array_key_first(TypeHelper::toArray($delete[$deleteArea]));

        foreach ($this->syncList[$deleteArea] as $key => $syncPage) {
            if (($syncPage['removeable'] ?? false) === true
                && ((int) ($syncPage['pageID'] ?? 0)) === $deletePageId
            ) {
                unset($this->syncList[$deleteArea][$key]);

                // Fix index order after removal
                if ($this->syncList[$deleteArea] !== []) {
                    $this->syncList[$deleteArea]
                        = array_values($this->syncList[$deleteArea]);
                } else {
                    unset($this->syncList[$deleteArea]);
                }

                break;
            }
        }
    }

    /**
     * Checks whether the given page ID is already in the sync list.
     *
     * @param int                            $pid      The page ID
     * @param array<int, SyncListEntry>|null $syncList A list of pages
     *
     * @return bool
     */
    private function isInTree(int $pid, ?array $syncList = null): bool
    {
        if (is_array($syncList)) {
            foreach ($syncList as $syncPage) {
                if (((int) ($syncPage['pageID'] ?? 0)) === $pid) {
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
        $backendUser = $GLOBALS['BE_USER'];
        assert($backendUser instanceof BackendUserAuthentication);

        return $backendUser;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->syncList === [];
    }

    /**
     * @return array<int, array<int, SyncListEntry>>
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

        foreach ($this->syncList[$areaId] ?? [] as $syncPage) {
            // Prüfen ob User Seite Bearbeiten darf
            $pageRow = $this->getPageRecord((int) ($syncPage['pageID'] ?? 0));

            if ($pageRow !== null
                && $this->getBackendUserAuthentication()->doesUserHaveAccess($pageRow, Permission::PAGE_EDIT)
            ) {
                $pageIds[] = [
                    (int) $syncPage['pageID'],
                ];
            }

            // Wenn der ganze Baum syncronisiert werden soll
            // getSubpagesAndCount liefert nur Pages zurück die Editiert werden
            // dürfen
            // @TODO
            if (($syncPage['type'] ?? null) === 'tree') {
                /** @var Area $area */
                $area = GeneralUtility::makeInstance(
                    Area::class,
                    (int) ($syncPage['areaID'] ?? 0),
                );

                $arCount = $this->getSubpagesAndCount(
                    (int) ($syncPage['pageID'] ?? 0),
                    $area,
                    $dummy,
                    0,
                    (int) ($syncPage['levelmax'] ?? 0),
                );

                $pageIds[] = $this->getPageIDsFromTree($arCount);
            }
        }

        $pageIds = array_filter(
            array_merge(...$pageIds),
            static fn (int $pageId): bool => $pageId !== 0,
        );
        $pageIds = array_merge($pageIds, $this->getPageTranslations($pageIds));

        return array_unique($pageIds);
    }

    /**
     * Returns the page record, or NULL if the page does not exist or is deleted.
     *
     * @return array<mixed>|null
     */
    protected function getPageRecord(int $pageId): ?array
    {
        return BackendUtility::getRecord('pages', $pageId);
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
     *
     * @param-out array{count: int, deleted: int, noaccess: int, falses: int, other_area: int} $arCount
     *
     * @param int           $level    Depth on which we are
     * @param int           $levelMax Maximum depth to search for
     * @param string[]|null $tables   Tables this task manages
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
        ?array $tables = null,
    ): array {
        $arCount = [
            'count'      => $arCount['count'] ?? 0,
            'deleted'    => $arCount['deleted'] ?? 0,
            'noaccess'   => $arCount['noaccess'] ?? 0,
            'falses'     => $arCount['falses'] ?? 0,
            'other_area' => $arCount['other_area'] ?? 0,
        ];

        $return = [];

        if ($pid < 0 || ($level >= $levelMax && $levelMax !== 0)) {
            return $return;
        }

        $queryBuilder = $this->getQueryBuilderForTable('pages');

        $result = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $pid),
            )
            ->executeQuery();

        while ($pageRow = $result->fetchAssociative()) {
            $pageUid = TypeHelper::toInt($pageRow['uid'] ?? null);

            if (in_array($pageRow['doktype'], $area->getNotDocType(), true)) {
                continue;
            }

            if (isset($area->areas[$pageUid])) {
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
                $pageUid,
                $area,
                $arCount,
                $level + 1,
                $levelMax,
                $tables,
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
            if ($this->pageContainsData($pageUid, $tables)) {
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
            if (TypeHelper::arrayPath($GLOBALS, 'TCA', $tableName) !== null) {
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
     * @param array<mixed> $tree The page tree to get IDs from
     *
     * @return int[]
     */
    private function getPageIDsFromTree(array $tree): array
    {
        $pageIds = [[]];

        foreach ($tree as $value) {
            // See if there is a page on the branch (may be missing due to editing rights)
            if (TypeHelper::arrayPath($value, 'page') !== null) {
                $pageIds[] = [
                    TypeHelper::toInt(TypeHelper::arrayPath($value, 'page', 'uid')),
                ];
            }

            // See if there are any underlying pages
            $sub = TypeHelper::arrayPath($value, 'sub');

            if (is_array($sub)) {
                $pageIds[] = $this->getPageIDsFromTree($sub);
            }
        }

        return array_filter(
            array_merge(...$pageIds),
            static fn (int $pageId): bool => $pageId !== 0,
        );
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

        return TypeHelper::toIntList(
            $queryBuilder
                ->select('uid')
                ->from('pages')
                ->where($queryBuilder->expr()->in('l10n_parent', $pageIds))
                ->groupBy('uid')
                ->executeQuery()
                ->fetchFirstColumn(),
        );
    }
}
