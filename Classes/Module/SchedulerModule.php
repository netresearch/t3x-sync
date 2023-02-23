<?php

/**
 * This file is part of the package netresearch/nrc-resco.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Module;

/**
 * Class SchedulerModule
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class SchedulerModule extends BaseModule
{
    /**
     * The name of the sync module to be displayed in sync module selection menu.
     *
     * @var string
     */
    protected $name = 'Scheduler tasks';

    /**
     * The access level of the module (value between 0 and 100). 100 requires admin access to typo3 backend.
     *
     * @var int
     */
    protected $accessLevel = 100;

    /**
     * Base name of the sync file.
     *
     * @var string
     */
    protected $dumpFileName = 'scheduler.sql';

    /**
     * Tables which should be synchronized.
     *
     * @var string[]
     */
    protected $tables = [
        'tx_scheduler_task',
    ];
}