<?php

/*
 * This file is part of the package netresearch/nr-sync.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Middleware;

use function is_array;
use function is_string;

use Netresearch\Sync\Service\ClearCacheService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Mfa\MfaRequiredException;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The clear cache middleware.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
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
     * Clears the caches listed in the "data" parameter of a request that carries "nr-sync-clear-cache".
     *
     * The request must come from a logged-in backend administrator; any other request is answered with
     * 403 and clears nothing.
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!isset($request->getQueryParams()['nr-sync-clear-cache'])) {
            return $handler->handle($request);
        }

        $backendUser = $this->authenticateAdministrator($request);

        if (!$backendUser instanceof BackendUserAuthentication) {
            return (new Response())->withStatus(403, 'Backend administrator login required');
        }

        $task = $request->getQueryParams()['task'] ?? null;
        $data = $request->getQueryParams()['data'] ?? [];

        if ($task !== 'clearCache') {
            return (new Response())->withStatus(400, 'Task unknown');
        }

        if (!is_string($data) || ($data === '')) {
            return (new Response())->withStatus(400, 'Data parameter absent');
        }

        $GLOBALS['BE_USER'] = $backendUser;

        // Try increased memory limit
        ini_set('memory_limit', '256M');

        $this->clearCacheService->clearCaches(explode(',', $data));

        return (new Response())->withStatus(200);
    }

    /**
     * Returns the backend user of the request if it is a logged-in administrator who passed multi-factor
     * authentication and meets the IP mask and HTTPS settings of the backend, otherwise NULL.
     *
     * @param ServerRequestInterface $request
     */
    protected function authenticateAdministrator(ServerRequestInterface $request): ?BackendUserAuthentication
    {
        $backendUser = $this->createBackendUserAuthentication();

        try {
            $backendUser->start($request);
        } catch (MfaRequiredException) {
            return null;
        }

        if (!is_array($backendUser->user)
            || ((int) ($backendUser->user['uid'] ?? 0) <= 0)
            || !$backendUser->isAdmin()
        ) {
            return null;
        }

        $normalizedParams = $request->getAttribute('normalizedParams');
        $ipMask           = trim((string) ($GLOBALS['TYPO3_CONF_VARS']['BE']['IPmaskList'] ?? ''));

        if (($ipMask !== '')
            && (!$normalizedParams instanceof NormalizedParams
                || !GeneralUtility::cmpIP($normalizedParams->getRemoteAddress(), $ipMask))
        ) {
            return null;
        }

        if ((bool) ($GLOBALS['TYPO3_CONF_VARS']['BE']['lockSSL'] ?? false)
            && (!$normalizedParams instanceof NormalizedParams || !$normalizedParams->isHttps())
        ) {
            return null;
        }

        return $backendUser;
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function createBackendUserAuthentication(): BackendUserAuthentication
    {
        return GeneralUtility::makeInstance(BackendUserAuthentication::class);
    }
}
