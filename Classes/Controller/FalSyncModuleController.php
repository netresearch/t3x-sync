<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Controller;

use Doctrine\DBAL\Exception;
use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Traits\TranslationTrait;

/**
 * Class FalSyncModuleController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class FalSyncModuleController extends BaseSyncModuleController
{
    use TranslationTrait;

    /**
     * @param Area $area
     *
     * @return void
     *
     * @throws Exception
     */
    protected function run(Area $area): void
    {
        parent::run($area);

        // See http://jira.aida.de/jira/browse/SDM-2099
        if (isset($_POST['data']['dam_cleanup'])) {
            $this->cleanUpDAM();
        }

        // DAM Test
        $this->testDAMForErrors();

        if ($this->hasError()) {
            $this->moduleTemplate->assign('customModulePartial', 'FalSyncModule/CleanUpButton');
        }
    }

    /**
     * http://jira.aida.de/jira/browse/SDM-2099.
     *
     * @return void
     */
    private function cleanUpDAM(): void
    {
        $this->addMessage($this->getLabel('label.clean_fal'));

        $this->connectionPool
            ->getConnectionForTable('sys_file_reference')
            ->delete(
                'sys_file_reference',
                [
                    'uid_foreign' => 0,
                ]
            );

        $this->addMessage($this->getLabel('label.done'));
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    private function testDAMForErrors(): void
    {
        $queryBuilder = $this
            ->connectionPool
            ->getQueryBuilderForTable('sys_file_reference');

        /** @var int $count */
        $count = $queryBuilder
            ->count('*')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    0
                )
            )
            ->executeQuery()
            ->fetchOne();

        if ($count > 0) {
            $this->error .= $this->getLabel('error.fal_dirty');
        }
    }
}
