<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Service\AbstractService;

/**
 * Service clear cache for Netresearch Synchronisation.
 *
 * @author  Alexander Opitz <alexander.opitz@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ClearCache implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var DataHandler
     */
    private DataHandler $dataHandler;

    /**
     * Same as class name
     *
     * @var string
     */
    public string $prefixId = __CLASS__;

    /**
     * ClearCache constructor.
     *
     * @param DataHandler $dataHandler
     */
    public function __construct(DataHandler $dataHandler)
    {
        $this->dataHandler = $dataHandler;
    }

    /**
     * Calls the clear cache function of DataHandler for every array entry.
     *
     * @param array $data Array for elements to clear cache from as "table:uid"
     *
     * @return void
     */
    public function clearCaches(array $data): void
    {
        $this->dataHandler
            ->start([], []);

        foreach ($data as $entry) {
            [$table, $uid] = explode(':', $entry);

            $this->logger
                ->info('Clear cache table: ' . $table . '; uid: ' . $uid);

            // This clears only the cache with specified record uid in table "pages"
            $this->dataHandler
                ->clear_cacheCmd((int) $uid);
        }
    }
}
