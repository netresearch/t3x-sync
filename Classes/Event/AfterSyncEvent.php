<?php

/*
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Event;

/**
 * This event is dispatched after a sync dump has been created.
 * It allows listeners to perform actions after the sync.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final readonly class AfterSyncEvent
{
    /**
     * Constructor.
     *
     * @param array<int, string> $tables
     * @param string             $dumpFile
     * @param string|null        $targetName
     * @param bool               $success
     */
    public function __construct(
        private array $tables,
        private string $dumpFile,
        private ?string $targetName,
        private bool $success,
    ) {}

    /**
     * @return array<int, string>
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @return string
     */
    public function getDumpFile(): string
    {
        return $this->dumpFile;
    }

    public function getTargetName(): ?string
    {
        return $this->targetName;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }
}
