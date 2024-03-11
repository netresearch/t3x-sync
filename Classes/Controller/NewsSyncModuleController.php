<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Controller;

use Exception;
use Netresearch\Sync\PageSyncModuleInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Class NewsSyncModuleController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class NewsSyncModuleController extends BaseSyncModuleController implements PageSyncModuleInterface
{
    /**
     * @param ModuleTemplate $moduleTemplate
     *
     * @return void
     */
    protected function initModule(ModuleTemplate $moduleTemplate): void
    {
        if (!$this->isAvailable()) {
            $this->addErrorMessage(
                'Required extension "news" seems not available in your current TYPO3 installation.',
            );

            return;
        }

        parent::initModule($moduleTemplate);
    }

    /**
     * @return bool
     */
    protected function isAvailable(): bool
    {
        return parent::isAvailable()
            && ExtensionManagementUtility::isLoaded('news');
    }

    /**
     * Returns the page IDs which should also be synchronized.
     *
     * @return int[]
     */
    public function getPagesToSync(): array
    {
        $connection = $this
            ->connectionPool
            ->getConnectionForTable('tt_content');

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->select('pid')
            ->from('tt_content')
            ->orWhere(
                $queryBuilder->expr()->like(
                    'list_type',
                    $queryBuilder->createNamedParameter('%news%')
                ),
                // Starting with version 11 of the news extension, the plugins are of type "CType"
                $queryBuilder->expr()->like(
                    'CType',
                    $queryBuilder->createNamedParameter('%news%')
                )
            )
            ->groupBy('pid');

        try {
            return $queryBuilder
                ->executeQuery()
                ->fetchFirstColumn();
        } catch (Exception) {
            return [];
        }
    }
}
