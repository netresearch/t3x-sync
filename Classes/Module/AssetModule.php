<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Module;

use Exception;
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
    /**
     * The name of the sync module to be displayed in sync module selection menu.
     *
     * @var string
     */
    protected mixed $name = 'Assets';

    /**
     * The access level of the module (value between 0 and 100). 100 requires admin access to typo3 backend.
     *
     * @var int
     */
    protected int $accessLevel = 100;

    /**
     * The name of the sync target.
     *
     * @var string
     */
    protected mixed $target = 'sync server';

    /**
     * @param Area $area
     *
     * @return void
     * @throws Exception
     */
    public function run(Area $area): void
    {
        parent::run($area);

        if (isset($_POST['data']['submit']) && $area->notifyMaster()) {
            $this->addMessage(
                'Sync assets is initiated.'
            );
        }
    }
}
