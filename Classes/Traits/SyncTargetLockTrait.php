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

namespace Netresearch\Sync\Traits;

use function is_array;

/**
 * SyncTargetLockTrait.
 *
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
trait SyncTargetLockTrait
{
    /**
     * Locks or unlocks the target systems named in the request parameter "lock". Only administrators may
     * change the lock of a target system; the request of any other user is ignored.
     *
     * @return void
     */
    private function handleTargetLock(): void
    {
        if (!isset($_REQUEST['lock'])
            || !is_array($_REQUEST['lock'])
            || !$this->getBackendUserAuthentication()->isAdmin()
        ) {
            return;
        }

        $defaultStorage = $this->storageService->getDefaultStorage();

        foreach ($_REQUEST['lock'] as $systemName => $lockState) {
            $system = $this->getArea()->getSystem($systemName);

            $systemDirectory = $this
                ->storageService
                ->getSyncFolder()
                ->getSubfolder($system['directory']);

            if ((bool) $lockState) {
                $systemDirectory
                    ->getStorage()
                    ->createFile('.lock', $systemDirectory)
                    ->setContents('lock');

                $this->addInfoMessage(
                    $this->getLabel(
                        'message.target_locked',
                        [
                            '{target}' => $systemName,
                        ],
                    ),
                );
            } elseif ($defaultStorage->hasFile($systemDirectory->getIdentifier() . '.lock')) {
                $defaultStorage
                    ->deleteFile(
                        $defaultStorage
                            ->getFile($systemDirectory->getIdentifier() . '.lock'),
                    );

                $this->addInfoMessage(
                    $this->getLabel(
                        'message.target_unlocked',
                        [
                            '{target}' => $systemName,
                        ],
                    ),
                );
            }
        }
    }
}
