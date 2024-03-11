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
 * Class FrontendGroupsModule
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class FrontendGroupsModule extends BaseModule
{
    /**
     * The name of the sync module to be displayed in sync module selection menu.
     *
     * @var string
     */
    protected mixed $name = 'FE groups';

    /**
     * The type of tables to sync, e.g. "sync_tables", "sync_fe_groups", "sync_be_groups" or "backsync_tables".
     *
     * @var string
     *
     * @deprecated Seems deprecated. Not used anywhere?
     */
    protected mixed $type = ModuleInterface::SYNC_TYPE_FE_GROUPS;

    /**
     * Base name of the sync file.
     *
     * @var string
     */
    protected mixed $dumpFileName = 'fe_groups.sql';

    /**
     * Tables which should be synchronized.
     *
     * @var string[]
     */
    protected array $tables = [
        'fe_groups',
    ];
}
