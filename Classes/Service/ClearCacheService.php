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
use Throwable;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Service clear cache.
 *
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ClearCacheService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var CacheManager
     */
    private readonly CacheManager $cacheManager;

    /**
     * @var DataHandler
     */
    private readonly DataHandler $dataHandler;

    /**
     * Constructor.
     *
     * @param CacheManager $cacheManager
     * @param DataHandler  $dataHandler
     */
    public function __construct(
        CacheManager $cacheManager,
        DataHandler $dataHandler,
    ) {
        $this->cacheManager = $cacheManager;
        $this->dataHandler  = $dataHandler;
    }

    /**
     * Calls the clear cache function of DataHandler for every array entry.
     *
     * @param string[] $data Array for elements to clear cache from as "table:uid"
     *
     * @return void
     */
    public function clearCaches(array $data): void
    {
        foreach ($data as $entry) {
            $this->executeClearCacheCmd($entry);
        }
    }

    /**
     * Executes the clear cache cmd based on the cmd-string which is passed.
     *
     * @param string $cmd
     *
     * @return void
     */
    private function executeClearCacheCmd(string $cmd): void
    {
        [$type, $identifier] = explode(':', $cmd);

        if ($type === 'framework') {
            [$cache, $key] = explode('|', $identifier);

            try {
                $this->logger
                    ->debug('Clear cache: ' . $cache . '; key: ' . $key);

                $this->cacheManager
                    ->getCache($cache)
                    ->remove($key);
            } catch (Throwable $throwable) {
                $this->logger->error(
                    'Clear cache failed with message: ' . $throwable->getMessage(),
                    [
                        'exception' => $throwable,
                    ]
                );
            }

            return;
        }

        $this->dataHandler->start([], []);

        if ($type === 'pages') {
            $this->logger
                ->debug('Clear cache table: ' . $type . '; uid: ' . $identifier);

            $this->dataHandler->clear_cacheCmd((int) $identifier);

            return;
        }

        $this->logger
            ->debug(sprintf('Execute clear_cacheCmd (%s)', $cmd));

        $this->dataHandler->clear_cacheCmd($cmd);
    }
}
