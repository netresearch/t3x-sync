<?php

/**
 * This file is part of the package netresearch/nrc-resco.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Module;

use Doctrine\DBAL\FetchMode;
use Exception;
use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\ModuleInterface;
use Netresearch\Sync\PageSyncModuleInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Class NewsModule
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Axel Seemann <axel.seemann@netresearch.de>
 * @copyright  2020 Netresearch DTT GmbH
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */
class NewsModule extends BaseModule implements PageSyncModuleInterface
{
    /**
     * The name of the sync module to be displayed in sync module selection menu.
     *
     * @var string
     */
    protected $name = 'News';

    /**
     * The type of tables to sync, e.g. "sync_tables", "sync_fe_groups", "sync_be_groups" or "backsync_tables".
     *
     * @var string
     *
     * @deprecated Seems deprecated. Not used anywhere?
     */
    protected $type = ModuleInterface::SYNC_TYPE_TABLES;

    /**
     * Base name of the sync file.
     *
     * @var string
     */
    protected $dumpFileName = 'news.sql';

    /**
     * Tables which should be synchronized.
     *
     * @var string[]
     */
    protected $tables = [
        'tx_news_domain_model_link',
        'tx_news_domain_model_news',
        'tx_news_domain_model_news_related_mm',
        'tx_news_domain_model_news_tag_mm',
        'tx_news_domain_model_news_ttcontent_mm',
        'tx_news_domain_model_tag',
        'sys_file_reference'
    ];

    public function isAvailable(): bool
    {
        return parent::isAvailable() && ExtensionManagementUtility::isLoaded('news');
    }

    /**
     * Returns the PageIDs which should also be synchronized
     *
     * @return array
     */
    public function getPagesToSync(): array
    {
        $connection = $this->connectionPool
            ->getConnectionForTable('tt_content');

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->select('pid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->like(
                    'list_type',
                    $queryBuilder->createNamedParameter('news%')
                )
            )
            ->groupBy('pid');

        try {
            return $queryBuilder->execute()->fetchAll(FetchMode::COLUMN);
        } catch (Exception $exception) {
            return [];
        }
    }
}
