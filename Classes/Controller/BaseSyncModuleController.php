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
use Netresearch\Sync\Generator\Urls;
use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\ModuleInterface;
use Netresearch\Sync\Service\StorageService;
use Netresearch\Sync\SyncList;
use Netresearch\Sync\SyncListManager;
use Netresearch\Sync\SyncLock;
use Netresearch\Sync\SyncStats;
use Netresearch\Sync\Traits\DumpFileTrait;
use Netresearch\Sync\Traits\FlashMessageTrait;
use Netresearch\Sync\Traits\SyncTargetLockTrait;
use Netresearch\Sync\Traits\TableDifferenceTrait;
use Netresearch\Sync\Traits\TranslationTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderWritePermissionsException;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class BaseSyncModuleController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class BaseSyncModuleController implements ModuleInterface
{
    use FlashMessageTrait;
    use TranslationTrait;
    use SyncTargetLockTrait;
    use TableDifferenceTrait;
    use DumpFileTrait;

    /**
     * Access rights for new folders.
     *
     * @var int
     */
    public const FOLDER_ACCESS_RIGHTS = 0777;

    /**
     * The date format prepended to the sync files.
     *
     * @var string
     */
    final public const DATE_FORMAT = 'Ymd-His';

    /**
     * @var IconFactory
     */
    protected IconFactory $iconFactory;

    /**
     * @var UriBuilder
     */
    protected UriBuilder $uriBuilder;

    /**
     * @var ModuleTemplateFactory
     */
    protected ModuleTemplateFactory $moduleTemplateFactory;

    /**
     * @var ConnectionPool
     */
    protected ConnectionPool $connectionPool;

    /**
     * @var SyncListManager
     */
    protected SyncListManager $syncListManager;

    /**
     * @var SyncLock
     */
    protected SyncLock $syncLock;

    /**
     * @var StorageService
     */
    protected StorageService $storageService;

    /**
     * @var ModuleTemplate|null
     */
    protected ?ModuleTemplate $moduleTemplate = null;

    /**
     * @var ModuleData|null
     */
    protected ?ModuleData $moduleData = null;

    /**
     * @var Area|null
     */
    protected ?Area $area = null;

    /**
     * @var Urls;
     */
    private Urls $urlGenerator;

    /**
     * The selected page ID.
     *
     * @var int
     */
    protected int $pageUid = 0;

    /**
     * @var bool
     */
    protected bool $useSyncList = false;

    /**
     * @var string
     */
    protected string $target = 'all';

    /**
     * @var string|null
     */
    protected ?string $error = null;

    /**
     * ClearCache URL format.
     *
     * @var string
     */
    public string $clearCacheUrl = '?nr-sync-clear-cache&task=clearCache&data=%s&new=true';

    /**
     * Constructor.
     *
     * @param IconFactory           $iconFactory
     * @param UriBuilder            $uriBuilder
     * @param ModuleTemplateFactory $moduleTemplateFactory
     * @param ConnectionPool        $connectionPool
     * @param SyncListManager       $syncListManager
     * @param SyncLock              $syncLock
     * @param StorageService        $storageService
     * @param Urls                  $urlGenerator
     */
    public function __construct(
        IconFactory $iconFactory,
        UriBuilder $uriBuilder,
        ModuleTemplateFactory $moduleTemplateFactory,
        ConnectionPool $connectionPool,
        SyncListManager $syncListManager,
        SyncLock $syncLock,
        StorageService $storageService,
        Urls $urlGenerator
    ) {
        $this->iconFactory           = $iconFactory;
        $this->uriBuilder            = $uriBuilder;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->connectionPool        = $connectionPool;
        $this->syncListManager       = $syncListManager;
        $this->syncLock              = $syncLock;
        $this->storageService        = $storageService;
        $this->urlGenerator          = $urlGenerator;
    }

    /**
     * @param ModuleData|null $moduleData
     *
     * @return BaseSyncModuleController
     */
    public function setModuleData(?ModuleData $moduleData): BaseSyncModuleController
    {
        $this->moduleData = $moduleData;

        return $this;
    }

    /**
     * The default action to call.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->setModuleData($request->getAttribute('moduleData'));
        $this->initFolders($this->getArea());
        $this->initializeAction($request);

        if (($request->getMethod() === 'POST')
            && ($request->getParsedBody() !== null)
            && ($request->getParsedBody() !== [])
            && isset($request->getParsedBody()['data']['submit'])
        ) {
            $dumpFile = $this->getDumpFile();

            if (($dumpFile !== '') && ($dumpFile !== null)) {
                $dumpFile = $this->addInformationToSyncfileName($dumpFile);

                $this->performSync($dumpFile);
            }
        }

        return $this->htmlResponse();
    }

    /**
     * Initializes the controller before invoking an action method.
     *
     * @param ServerRequestInterface $request
     */
    protected function initializeAction(ServerRequestInterface $request): void
    {
        $parsedBody  = $request->getParsedBody();
        $queryParams = $request->getQueryParams();

        $this->pageUid        = $this->getPageId($request);
        $this->moduleTemplate = $this->getModuleTemplate($request, $this->pageUid);
        $this->target         = $parsedBody['target'] ?? $queryParams['target'] ?? 'all';

        $this->initModule($this->moduleTemplate);
    }

    /**
     * Returns the page ID extracted from the given request object.
     *
     * @param ServerRequestInterface $request
     *
     * @return int
     */
    protected function getPageId(ServerRequestInterface $request): int
    {
        return (int) ($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? -1);
    }

    /**
     * @return ResponseInterface
     */
    protected function htmlResponse(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/DefaultSyncModule');
    }

    /**
     * @param ServerRequestInterface $request
     * @param int                    $pageUid
     *
     * @return ModuleTemplate
     */
    protected function getModuleTemplate(ServerRequestInterface $request, int $pageUid): ModuleTemplate
    {
        $this->updateRoutePackageName($request);

        $moduleTemplate   = $this->moduleTemplateFactory->create($request);
        $permissionClause = $this->getBackendUserAuthentication()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageRecord       = BackendUtility::readPageAccess($pageUid, $permissionClause);

        if ($pageRecord !== false) {
            $moduleTemplate
                ->getDocHeaderComponent()
                ->setMetaInformation($pageRecord);
        }

        $additionalQueryParams = [
            'id' => $this->getPageId($request),
        ];

        $moduleTemplate->makeDocHeaderModuleMenu($additionalQueryParams);

        if ($this->getBackendUserAuthentication()->isAdmin()) {
            $this->syncLock->handleModuleLock();
        }

        $moduleTemplate->assign('syncLock', $this->syncLock);

        $this->handleTargetLock();
        $this->sortMenuItems($moduleTemplate);
        $this->createButtons($moduleTemplate);

        return $moduleTemplate;
    }

    /**
     * Updates the package name of the current route to provide the correct templates
     * for third party extensions.
     *
     * @param ServerRequestInterface $request
     *
     * @return void
     *
     * @see \TYPO3\CMS\Backend\View\BackendViewFactory::create
     */
    private function updateRoutePackageName(ServerRequestInterface $request): void
    {
        /** @var Route $route */
        $route = $request->getAttribute('route');
        $route->setOption('packageName', 'netresearch/nr-sync');
    }

    /**
     * Sort the menu entries alphabetically in ascending order.
     *
     * @param ModuleTemplate $moduleTemplate
     *
     * @return void
     */
    private function sortMenuItems(ModuleTemplate $moduleTemplate): void
    {
        /** @var Menu $moduleMenu */
        $moduleMenu = $moduleTemplate
            ->getDocHeaderComponent()
            ->getMenuRegistry()
            ->getMenus()['moduleMenu'];

        $menuItems = $moduleMenu->getMenuItems();

        usort(
            $menuItems,
            static fn (MenuItem $a, MenuItem $b): int => strtolower($a->getTitle()) <=> strtolower($b->getTitle())
        );

        $moduleMenu = $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $moduleMenu->setIdentifier('moduleMenu');

        foreach ($menuItems as $menuItem) {
            $moduleMenu->addMenuItem($menuItem);
        }

        $moduleTemplate
            ->getDocHeaderComponent()
            ->getMenuRegistry()
            ->addMenu($moduleMenu);
    }

    /**
     * Create the panel of buttons for submitting the form or otherwise perform operations.
     *
     * @param ModuleTemplate $moduleTemplate
     *
     * @throws RouteNotFoundException
     */
    private function createButtons(ModuleTemplate $moduleTemplate): void
    {
        $buttonBar = $moduleTemplate
            ->getDocHeaderComponent()
            ->getButtonBar();

        // Add lock buttons
        if ($this->getBackendUserAuthentication()->isAdmin()) {
            $this->addButtonBarLockButton($moduleTemplate);
            $this->addButtonBarAreaLockButtons($moduleTemplate);
        }

        $permissionClause = $this->getBackendUserAuthentication()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageRecord       = BackendUtility::readPageAccess($this->pageUid, $permissionClause);

        if (($this->pageUid !== 0)
            && ($pageRecord !== false)
        ) {
            // Shortcut
            $shortcutButton = $buttonBar
                ->makeShortcutButton()
                ->setDisplayName('View page ' . $this->pageUid)
                ->setRouteIdentifier($this->getModuleIdentifier());

            $buttonBar->addButton($shortcutButton);
        }
    }

    /**
     * @param ModuleTemplate $moduleTemplate
     *
     * @return void
     *
     * @throws RouteNotFoundException
     */
    private function addButtonBarLockButton(ModuleTemplate $moduleTemplate): void
    {
        $buttonBar  = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $lockButton = $buttonBar->makeLinkButton();

        if ($this->syncLock->isLocked()) {
            $lockButton
                ->setTitle($this->getLabel('label.unlock_module'))
                ->setHref(
                    $this->getModuleUrl(
                        [
                            'id'   => $this->pageUid,
                            'data' => [
                                'lock' => '0',
                            ],
                        ]
                    )
                )
                ->setIcon(
                    $this->iconFactory->getIcon(
                        'actions-lock',
                        Icon::SIZE_SMALL
                    )
                )
                ->setClasses('btn-warning');
        } else {
            $lockButton
                ->setTitle($this->getLabel('label.lock_module'))
                ->setHref(
                    $this->getModuleUrl(
                        [
                            'id'   => $this->pageUid,
                            'data' => [
                                'lock' => '1',
                            ],
                        ]
                    )
                )
                ->setIcon(
                    $this->iconFactory->getIcon(
                        'actions-unlock',
                        Icon::SIZE_SMALL
                    )
                );
        }

        $lockButton->setShowLabelText(true);

        $buttonBar->addButton($lockButton, ButtonBar::BUTTON_POSITION_LEFT, 0);
    }

    /**
     * @throws RouteNotFoundException
     */
    private function addButtonBarAreaLockButtons(ModuleTemplate $moduleTemplate): void
    {
        foreach ($this->getArea()->getSystems() as $systemName => $system) {
            if (isset($system['hide']) && ($system['hide'] === true)) {
                continue;
            }

            $this->addButtonBarAreaLockButton($moduleTemplate, $systemName, $system);
        }
    }

    /**
     * @param ModuleTemplate $moduleTemplate
     * @param string         $systemName
     * @param string[]       $system
     *
     * @throws RouteNotFoundException
     */
    private function addButtonBarAreaLockButton(ModuleTemplate $moduleTemplate, string $systemName, array $system): void
    {
        $buttonBar       = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $lockButton      = $buttonBar->makeLinkButton();
        $systemDirectory = $this->storageService->getSyncFolder()->getSubfolder($system['directory']);

        if ($systemDirectory->hasFile('.lock')) {
            $lockButton
                ->setTitle($this->getLabel('label.unlock_target', ['{system}' => $system['name']]))
                ->setHref(
                    $this->getModuleUrl(
                        [
                            'id'   => $this->pageUid,
                            'lock' => [
                                $systemName => '0',
                            ],
                        ]
                    )
                )
                ->setIcon(
                    $this->iconFactory->getIcon(
                        'actions-lock',
                        Icon::SIZE_SMALL
                    )
                )
                ->setClasses('btn-warning');
        } else {
            $lockButton
                ->setTitle($this->getLabel('label.lock_target', ['{system}' => $system['name']]))
                ->setHref(
                    $this->getModuleUrl(
                        [
                            'id'   => $this->pageUid,
                            'lock' => [
                                $systemName => '1',
                            ],
                        ]
                    )
                )
                ->setIcon(
                    $this->iconFactory->getIcon(
                        'actions-unlock',
                        Icon::SIZE_SMALL
                    )
                );
        }

        $lockButton->setShowLabelText(true);

        $buttonBar->addButton($lockButton);
    }

    /**
     * @return bool
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * @param ModuleTemplate $moduleTemplate
     *
     * @return void
     *
     * @throws Exception
     * @throws ExistingTargetFolderException
     * @throws InsufficientFolderAccessPermissionsException
     * @throws InsufficientFolderWritePermissionsException
     * @throws RouteNotFoundException
     */
    protected function initModule(ModuleTemplate $moduleTemplate): void
    {
        if ($this->syncLock->isLocked() || $this->isTargetLocked()) {
            return;
        }

        $this->run($this->getArea());

        if ($this->hasError()) {
            $this->addErrorMessage($this->getError());
        }

        //        // TODO
        //        $this->useSyncList = false;

        if (($this->useSyncList === false) && ($this->getTables() !== [])) {
            $syncStats = GeneralUtility::makeInstance(
                SyncStats::class,
                GeneralUtility::makeInstance(ConnectionPool::class),
                $this->getTables()
            );

            $moduleTemplate->assign('tableSyncStats', $syncStats);
            $moduleTemplate->assign('showTableSyncStats', true);
        }

        $moduleTemplate->assign('selectedMenuItem', $this->getCurrentFunctionId());
        $moduleTemplate->assign('useSyncList', $this->useSyncList);
        $moduleTemplate->assign('syncList', $this->getSyncList());
        $moduleTemplate->assign('area', $this->getArea());
        $moduleTemplate->assign('moduleRoute', $this->getModuleIdentifier());
        $moduleTemplate->assign('moduleUrl', $this->getModuleUrl());
        $moduleTemplate->assign('pageUid', $this->pageUid);

        if ((($this->useSyncList === false) && ($this->getTables() !== []))
            || ($this->useSyncList && !$this->getSyncList()->isEmpty())
        ) {
            $moduleTemplate->assign('showCheckBoxes', true);
        }
    }

    /**
     * Returns true if the target is locked.
     *
     * @return bool
     *
     * @throws ExistingTargetFolderException
     * @throws InsufficientFolderAccessPermissionsException
     * @throws InsufficientFolderWritePermissionsException
     */
    private function isTargetLocked(): bool
    {
        $system = $this->getArea()->getSystem($this->target);

        if ($system === []) {
            return false;
        }

        return $this->isSystemLocked($system['directory']);
    }

    /**
     * Returns true if the system is locked.
     *
     * @param string $system System Dump directory
     *
     * @return bool
     *
     * @throws InsufficientFolderAccessPermissionsException
     * @throws InsufficientFolderWritePermissionsException
     */
    public function isSystemLocked(string $system = ''): bool
    {
        if ($system === '') {
            return false;
        }

        $systemDirectory = $this
            ->storageService
            ->getSyncFolder()
            ->getSubfolder($system);

        return $this
            ->storageService
            ->getDefaultStorage()
            ->hasFile($systemDirectory->getIdentifier() . '.lock');
    }

    /**
     * Initializes all needed directories.
     *
     * @return void
     *
     * @throws InsufficientFolderAccessPermissionsException
     * @throws InsufficientFolderWritePermissionsException
     */
    public function initFolders(Area $area): void
    {
        $syncFolder = $this->storageService->getSyncFolder();

        foreach ($area->getSystems() as $system) {
            if ($syncFolder->hasFolder($system['directory'] . '/') === false) {
                $syncFolder->createFolder($system['directory'] . '/');
            }

            if ($syncFolder->hasFolder($system['url-path'] . '/') === false) {
                $syncFolder->createFolder($system['url-path'] . '/');
            }
        }
    }

    /**
     * @return string
     */
    protected function getCurrentFunctionId(): string
    {
        return static::class;
    }

    /**
     * @return string
     */
    protected function getModuleIdentifier(): string
    {
        return $this->moduleData->getModuleIdentifier();
    }

    /**
     * @return string[]
     */
    public function getTables(): array
    {
        return $this->moduleData->get('tables', []);
    }

    /**
     * @return string|null
     */
    public function getDumpFile(): ?string
    {
        return $this->moduleData->get('dumpFile');
    }

    /**
     * @return string[]
     */
    public function getClearCacheEntries(): array
    {
        return $this->moduleData->get('clearCacheEntries', []);
    }

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * @return Area
     */
    protected function getArea(): Area
    {
        if (!$this->area instanceof Area) {
            $this->area = GeneralUtility::makeInstance(
                Area::class,
                $this->pageUid,
                $this->target
            );
        }

        return $this->area;
    }

    /**
     * @return SyncList
     */
    protected function getSyncList(): SyncList
    {
        return $this->syncListManager->getSyncList($this->getCurrentFunctionId());
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @param array<string, array<string, string>> $parameters An array of parameters
     *
     * @return string
     *
     * @throws RouteNotFoundException
     */
    protected function getModuleUrl(array $parameters = []): string
    {
        return (string) $this
            ->uriBuilder
            ->buildUriFromRoute($this->getModuleIdentifier(), $parameters);
    }

    /**
     * @return bool
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Executes the module.
     *
     * @param Area $area
     *
     * @return void
     *
     * @throws Exception
     */
    public function run(Area $area): void
    {
        $this->testTablesForDifferences($this->getTables());
    }

    /**
     * Adds information about full or inc sync to sync file.
     *
     * @param string $dumpFile the name of the file
     *
     * @return string
     */
    private function addInformationToSyncfileName(string $dumpFile): string
    {
        $isFullSync = isset($_POST['data']['force_full_sync']) && ($_POST['data']['force_full_sync'] === '1');
        $prefix     = 'inc_';
        $target     = strtolower($this->target);

        if ($isFullSync) {
            $prefix = 'full_';
        }

        return $prefix . $target . '_' . $dumpFile;
    }
}
