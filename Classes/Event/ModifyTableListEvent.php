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
 * This event is dispatched when determining which tables to sync.
 * It allows listeners to add or remove tables from the sync list.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final class ModifyTableListEvent
{
    /**
     * Constructor.
     *
     * @param array<int, string> $tables
     * @param string             $moduleIdentifier
     */
    public function __construct(
        private array $tables,
        private readonly string $moduleIdentifier,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @param array<int, string> $tables
     *
     * @return void
     */
    public function setTables(array $tables): void
    {
        $this->tables = $tables;
    }

    /**
     * @param string $table
     *
     * @return void
     */
    public function addTable(string $table): void
    {
        if (!in_array($table, $this->tables, true)) {
            $this->tables[] = $table;
        }
    }

    /**
     * @param string $table
     *
     * @return void
     */
    public function removeTable(string $table): void
    {
        $key = array_search($table, $this->tables, true);

        if ($key !== false) {
            unset($this->tables[$key]);
            $this->tables = array_values($this->tables);
        }
    }

    /**
     * @return string
     */
    public function getModuleIdentifier(): string
    {
        return $this->moduleIdentifier;
    }
}
