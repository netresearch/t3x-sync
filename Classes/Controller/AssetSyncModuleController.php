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

use Doctrine\DBAL\Exception;
use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Helper\TypeHelper;
use Netresearch\Sync\Traits\TranslationTrait;

/**
 * Class AssetSyncModuleController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
class AssetSyncModuleController extends BaseSyncModuleController
{
    use TranslationTrait;

    /**
     * @param Area $area
     *
     * @return void
     *
     * @throws Exception
     */
    public function run(Area $area): void
    {
        parent::run($area);

        if (TypeHelper::arrayPath($_POST, 'data', 'submit') === null) {
            return;
        }

        $this->addMessage(
            $this->getLabel('success.sync_assest_init'),
        );

        $area->notifyMaster();
    }
}
