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
 * Class FalModule
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class FalModule extends BaseModule
{
    protected $tables = [
        'sys_file',
        'sys_category',
        'sys_filemounts',
        'sys_category_record_mm',
        'sys_file_reference',
        'sys_collection',
        'sys_file_metadata',
    ];

    protected $name = 'FAL';
    protected $type = 'sync_tables';
    protected $dumpFileName = 'fal.sql';

    public function run(Area $area): bool
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

        return true;
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
