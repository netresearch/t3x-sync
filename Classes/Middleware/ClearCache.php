<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Middleware;

use Netresearch\Sync\Service\ClearCacheService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Mfa\MfaRequiredException;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The clear cache middleware.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ClearCache implements MiddlewareInterface
{
    /**
     * The clear cache service.
     *
     * @var ClearCacheService
     */
    private readonly ClearCacheService $clearCacheService;

    /**
     * ClearCache constructor.
     *
     * @param ClearCacheService $clearCacheService
     */
    public function __construct(ClearCacheService $clearCacheService)
    {
        $this->clearCacheService = $clearCacheService;
    }

    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     *
     * @throws MfaRequiredException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!isset($request->getQueryParams()['nr-sync-clear-cache'])) {
            return $handler->handle($request);
        }

        $task = $request->getQueryParams()['task'] ?? null;
        $data = $request->getQueryParams()['data'] ?? [];

        if ($task !== 'clearCache') {
            return (new Response())->withStatus(400, 'Task unknown');
        }

        if (($data === '') || ($data === [])) {
            return (new Response())->withStatus(400, 'Data parameter absent');
        }

        $this->runClearCacheService($request, explode(',', $data));

        return (new Response())->withStatus(200);
    }

    /**
     * @return BackendUserAuthentication
     */
    private function getBackendUserAuthentication(): BackendUserAuthentication
    {
        $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(BackendUserAuthentication::class);

        return $GLOBALS['BE_USER'];
    }

    /**
     * Run the service.
     *
     * @param ServerRequestInterface $request
     * @param string[]               $data    array with values in table:uid order
     *
     * @return void
     *
     * @throws MfaRequiredException
     */
    private function runClearCacheService(ServerRequestInterface $request, array $data): void
    {
        $backendUser = $this->getBackendUserAuthentication();
        $backendUser->start($request);

        // Try increased memory limit
        ini_set('memory_limit', '256M');

        $this->clearCacheService->clearCaches($data);
    }
}
