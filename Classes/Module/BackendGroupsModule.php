<?php

/**
 * This file is part of the package netresearch/nrc-resco.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Module;

use Netresearch\Sync\ModuleInterface;

/**
 * Class BackendGroupsModule
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class BackendGroupsModule extends BaseModule
{
    /**
     * The name of the sync module to be displayed in sync module selection menu.
     *
     * @var string
     */
    protected $name = 'BE users and groups';

    /**
     * The type of tables to sync, e.g. "sync_tables", "sync_fe_groups", "sync_be_groups" or "backsync_tables".
     *
     * @var string
     *
     * @deprecated Seems deprecated. Not used anywhere?
     */
    protected $type = ModuleInterface::SYNC_TYPE_BE_GROUPS;

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
    protected $dumpFileName = 'be_users_groups.sql';

    /**
     * Tables which should be synchronized.
     *
     * @var string[]
     */
    protected $tables = [
        'be_users',
        'be_groups',
    ];
}
