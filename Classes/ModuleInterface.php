<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync;

use Netresearch\Sync\Helper\Area;

/**
 * The module interface.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
interface ModuleInterface
{
    /**
     * Table sync types.
     *
     * @var string
     */
    public const SYNC_TYPE_TABLES = 'sync_tables';

    /**
     * @var string
     */
    public const SYNC_TYPE_FE_GROUPS = 'sync_fe_groups';

    /**
     * @var string
     */
    public const SYNC_TYPE_BE_GROUPS = 'sync_be_groups';

    /**
     * @var string
     */
    public const SYNC_TYPE_BACKSYNC = 'backsync_tables';

    /**
     * Returns TRUE if the module is available, otherwise FALSE.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Returns the name of the module.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Returns a list of table names to synchronize.
     *
     * @return string[]
     */
    public function getTableNames(): array;

    /**
     * Returns the name of the synchronization file containing the SQL statements to update the database records.
     *
     * @return string
     */
    public function getDumpFileName(): string;

    /**
     * @return string
     */
    public function getError(): string;

    /**
     * @return bool
     */
    public function hasError(): bool;

    /**
     * Executes the module.
     *
     * @param Area $area
     *
     * @return void
     */
    public function run(Area $area): void;
}
