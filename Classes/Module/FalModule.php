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
use Netresearch\Sync\ModuleInterface;

/**
 * Class FalModule
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class FalModule extends BaseModule
{
    /**
     * The name of the sync module to be displayed in sync module selection menu.
     *
     * @var string
     */
    protected $name = 'FAL (File Abstraction Layer)';

    /**
     * The type of tables to sync, e.g. "sync_tables", "sync_fe_groups", "sync_be_groups" or "backsync_tables".
     *
     * @var string
     *
     * @deprecated Seems deprecated. Not used anywhere?
     */
    protected $type = ModuleInterface::SYNC_TYPE_TABLES;

    /**
     * The name of the synchronisation file containing the SQL statements to update the database records.
     *
     * @var string
     */
    protected $dumpFileName = 'fal.sql';

    /**
     * A list of table names to synchronise.
     *
     * @var string[]
     */
    protected $tables = [
        'sys_file',
        'sys_category',
        'sys_filemounts',
        'sys_category_record_mm',
        'sys_file_reference',
        'sys_collection',
        'sys_file_metadata',
    ];

    public function run(Area $area): void
    {
        // See http://jira.aida.de/jira/browse/SDM-2099
        if (isset($_POST['data']['dam_cleanup'])) {
            $this->cleanUpDAM();
        }

        // DAM Test
        $this->testDAMForErrors();

        // http://jira.aida.de/jira/browse/SDM-2099
        if ($this->hasError()) {
            // TODO Move to a template
            $this->content = '<input class="btn btn-warning" type="Submit" name="data[dam_cleanup]" value="Clean up FAL">';
        }
    }

    /**
     * http://jira.aida.de/jira/browse/SDM-2099
     *
     * @return void
     */
    private function cleanUpDAM(): void
    {
        $this->addMessage('This tasks can take some time, please be patient ...');

        $this->connectionPool
            ->getConnectionForTable('sys_file_reference')
            ->delete(
                'sys_file_reference',
                [
                    'uid_foreign' => 0
                ]
            );

        $this->addMessage('Done.');
    }

    /**
     * http://jira.aida.de/jira/browse/SDM-2099
     *
     * @return void
     */
    private function testDAMForErrors(): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');

        $count = $queryBuilder
            ->count('*')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', 0)
            )
            ->execute()
            ->fetchOne();

        if ($count > 0) {
            $this->error .= 'FAL has corrupted entries. (Entries with uid_foreign = 0)';
        }
    }
}
