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

namespace Netresearch\Sync\Tests\Unit\Middleware;

use Netresearch\Sync\Middleware\ClearCache;
use Netresearch\Sync\Service\ClearCacheService;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaRequiredException;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;

#[CoversClass(ClearCache::class)]
final class ClearCacheTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $backupConfiguration = [];

    private mixed $backupBackendUser = null;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->backupConfiguration = $GLOBALS['TYPO3_CONF_VARS'] ?? [];
        $this->backupBackendUser   = $GLOBALS['BE_USER'] ?? null;

        $GLOBALS['TYPO3_CONF_VARS']['BE']['IPmaskList'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['BE']['lockSSL']    = false;
    }

    #[Override]
    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = $this->backupConfiguration;
        $GLOBALS['BE_USER']         = $this->backupBackendUser;

        parent::tearDown();
    }

    /**
     * @param array<string, mixed>|null $userRecord
     */
    private function createBackendUser(?array $userRecord, bool $isAdmin, bool $requiresMfa = false): BackendUserAuthentication&Stub
    {
        $backendUser       = $this->createStub(BackendUserAuthentication::class);
        $backendUser->user = $userRecord;
        $backendUser->method('isAdmin')->willReturn($isAdmin);

        if ($requiresMfa) {
            $backendUser->method('start')->willThrowException(
                new MfaRequiredException($this->createStub(MfaProviderManifestInterface::class), 1),
            );
        }

        return $backendUser;
    }

    private function createSubject(BackendUserAuthentication $backendUser, ClearCacheService $clearCacheService): ClearCache
    {
        return new class ($clearCacheService, $backendUser) extends ClearCache {
            public function __construct(
                ClearCacheService $clearCacheService,
                private readonly BackendUserAuthentication $backendUser,
            ) {
                parent::__construct($clearCacheService);
            }

            #[Override]
            protected function createBackendUserAuthentication(): BackendUserAuthentication
            {
                return $this->backendUser;
            }
        };
    }

    /**
     * @param array<string, string> $queryParams
     */
    private function createRequest(array $queryParams, string $remoteAddress = '192.0.2.10', bool $https = true): ServerRequestInterface
    {
        $serverParams = [
            'REMOTE_ADDR' => $remoteAddress,
            'HTTP_HOST'   => 'target.example.org',
            'HTTPS'       => $https ? 'on' : 'off',
        ];

        return (new ServerRequest('https://target.example.org/', 'GET', 'php://input', [], $serverParams))
            ->withQueryParams($queryParams)
            ->withAttribute('normalizedParams', new NormalizedParams($serverParams, [], '/var/www/public/index.php', '/var/www/public/'));
    }

    private function createHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response())->withStatus(204);
            }
        };
    }

    /**
     * @return array<string, string>
     */
    private function clearCacheQuery(): array
    {
        return [
            'nr-sync-clear-cache' => '',
            'task'                => 'clearCache',
            'data'                => 'pages:12,pages:13',
        ];
    }

    #[Test]
    public function requestWithoutClearCacheParameterIsPassedOn(): void
    {
        $clearCacheService = $this->createMock(ClearCacheService::class);
        $clearCacheService->expects(self::never())->method('clearCaches');

        $response = $this->createSubject($this->createBackendUser(null, false), $clearCacheService)
            ->process($this->createRequest(['id' => '1']), $this->createHandler());

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function administratorClearsTheListedCaches(): void
    {
        $clearCacheService = $this->createMock(ClearCacheService::class);
        $clearCacheService->expects(self::once())
            ->method('clearCaches')
            ->with(['pages:12', 'pages:13']);

        $backendUser = $this->createBackendUser(['uid' => 3, 'admin' => 1], true);

        $response = $this->createSubject($backendUser, $clearCacheService)
            ->process($this->createRequest($this->clearCacheQuery()), $this->createHandler());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($backendUser, $GLOBALS['BE_USER']);
    }

    /**
     * @return array<string, array{array<string, mixed>|null, bool, bool}>
     */
    public static function usersWithoutAccess(): array
    {
        return [
            'no session'                => [null, false, false],
            'session without user id'   => [['uid' => 0], true, false],
            'editor'                    => [['uid' => 5, 'admin' => 0], false, false],
            'administrator without MFA' => [['uid' => 3, 'admin' => 1], true, true],
        ];
    }

    /**
     * @param array<string, mixed>|null $userRecord
     */
    #[Test]
    #[DataProvider('usersWithoutAccess')]
    public function requestOfAnyoneButALoggedInAdministratorIsRefused(?array $userRecord, bool $isAdmin, bool $requiresMfa): void
    {
        $clearCacheService = $this->createMock(ClearCacheService::class);
        $clearCacheService->expects(self::never())->method('clearCaches');

        $response = $this->createSubject($this->createBackendUser($userRecord, $isAdmin, $requiresMfa), $clearCacheService)
            ->process($this->createRequest($this->clearCacheQuery()), $this->createHandler());

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function administratorOutsideTheBackendIpMaskIsRefused(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['BE']['IPmaskList'] = '198.51.100.*';

        $clearCacheService = $this->createMock(ClearCacheService::class);
        $clearCacheService->expects(self::never())->method('clearCaches');

        $response = $this->createSubject($this->createBackendUser(['uid' => 3, 'admin' => 1], true), $clearCacheService)
            ->process($this->createRequest($this->clearCacheQuery(), '192.0.2.10'), $this->createHandler());

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function administratorOverHttpIsRefusedWhenTheBackendRequiresHttps(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['BE']['lockSSL'] = true;

        $clearCacheService = $this->createMock(ClearCacheService::class);
        $clearCacheService->expects(self::never())->method('clearCaches');

        $response = $this->createSubject($this->createBackendUser(['uid' => 3, 'admin' => 1], true), $clearCacheService)
            ->process($this->createRequest($this->clearCacheQuery(), '192.0.2.10', false), $this->createHandler());

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * @return array<string, array{array<string, string>, int}>
     */
    public static function invalidParameters(): array
    {
        return [
            'unknown task' => [['nr-sync-clear-cache' => '', 'task' => 'flush', 'data' => 'pages:1'], 400],
            'missing data' => [['nr-sync-clear-cache' => '', 'task' => 'clearCache'], 400],
            'empty data'   => [['nr-sync-clear-cache' => '', 'task' => 'clearCache', 'data' => ''], 400],
        ];
    }

    /**
     * @param array<string, string> $queryParams
     */
    #[Test]
    #[DataProvider('invalidParameters')]
    public function administratorRequestWithInvalidParametersClearsNothing(array $queryParams, int $expectedStatus): void
    {
        $clearCacheService = $this->createMock(ClearCacheService::class);
        $clearCacheService->expects(self::never())->method('clearCaches');

        $response = $this->createSubject($this->createBackendUser(['uid' => 3, 'admin' => 1], true), $clearCacheService)
            ->process($this->createRequest($queryParams), $this->createHandler());

        self::assertSame($expectedStatus, $response->getStatusCode());
    }
}
