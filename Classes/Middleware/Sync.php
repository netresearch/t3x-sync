<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * The disclaimer middleware.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class Sync implements MiddlewareInterface
{
    /**
     * @var ConfigurationManagerInterface
     */
    private ConfigurationManagerInterface $configurationManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Sync constructor.
     *
     * @param ConfigurationManagerInterface $configurationManager
     * @param LogManager $logManager
     */
    public function __construct(
        ConfigurationManagerInterface $configurationManager,
        LogManager $logManager
    ) {
        $this->configurationManager = $configurationManager;
        $this->logger               = $logManager->getLogger(__CLASS__);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!isset($request->getQueryParams()['nr-sync'])) {
            return $handler->handle($request);
        }

        // TODO
    }
}
