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

namespace Netresearch\Sync\Tests\Unit\Traits\Fixtures;

use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Service\StorageService;
use Netresearch\Sync\Traits\SyncTargetLockTrait;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Uses SyncTargetLockTrait with the collaborators it expects from a sync module controller.
 */
final class TargetLockSubject
{
    use SyncTargetLockTrait;

    /**
     * @var list<string>
     */
    public array $messages = [];

    public function __construct(
        private readonly StorageService $storageService,
        private readonly Area $area,
        private readonly BackendUserAuthentication $backendUser,
    ) {}

    public function handle(): void
    {
        $this->handleTargetLock();
    }

    public function addInfoMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    private function getArea(): Area
    {
        return $this->area;
    }

    private function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $this->backendUser;
    }

    /**
     * @param array<string, string|int> $data
     */
    private function getLabel(string $id, array $data = []): string
    {
        return $id;
    }
}
