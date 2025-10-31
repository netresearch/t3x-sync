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
 * This event is dispatched before a sync dump is created.
 * It allows listeners to modify the tables to sync or perform actions before the sync.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final class BeforeSyncEvent
{
    /**
     * Constructor.
     *
     * @param array<int, string> $tables
     * @param string             $dumpFile
     * @param string|null        $targetName
     */
    public function __construct(
        private array $tables,
        private readonly string $dumpFile,
        private readonly ?string $targetName = null,
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
     * @return string
     */
    public function getDumpFile(): string
    {
        return $this->dumpFile;
    }

    /**
     * @return string|null
     */
    public function getTargetName(): ?string
    {
        return $this->targetName;
    }
}
