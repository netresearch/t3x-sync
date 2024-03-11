<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Traits;

/**
 * SyncTargetLockTrait.
 *
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
trait SyncTargetLockTrait
{
    /**
     * @return void
     */
    private function handleTargetLock(): void
    {
        if (!isset($_REQUEST['lock'])) {
            return;
        }

        $defaultStorage = $this->storageService->getDefaultStorage();

        foreach ($_REQUEST['lock'] as $systemName => $lockState) {
            $system = $this->getArea()->getSystem($systemName);

            $systemDirectory = $this
                ->storageService
                ->getSyncFolder()
                ->getSubfolder($system['directory']);

            if ($lockState) {
                $systemDirectory
                    ->getStorage()
                    ->createFile('.lock', $systemDirectory)
                    ->setContents('lock');

                $this->addInfoMessage(
                    $this->getLabel(
                        'message.target_locked',
                        [
                            '{target}' => $systemName,
                        ]
                    )
                );
            } elseif ($defaultStorage->hasFile($systemDirectory->getIdentifier() . '.lock')) {
                $defaultStorage
                    ->deleteFile(
                        $defaultStorage
                            ->getFile($systemDirectory->getIdentifier() . '.lock')
                    );

                $this->addInfoMessage(
                    $this->getLabel(
                        'message.target_unlocked',
                        [
                            '{target}' => $systemName,
                        ]
                    )
                );
            }
        }
    }
}
