<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Module;

use Netresearch\Sync\Helper\Area;

/**
 * Class AssetModule
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class AssetModule extends BaseModule
{
    protected $name = 'Assets';
    protected $target = 'sync server';
    protected $accessLevel = 100;

    public function run(Area $area): bool
    {
        parent::run($area);

        if (isset($_POST['data']['submit']) && $area->notifyMaster()) {
            $this->addMessage(
                'Sync assets is initiated.'
            );
        }

        return true;
    }
}
