<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Controller;

use Netresearch\Sync\Exception;
use Netresearch\Sync\Generator\Urls;
use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Module\AssetModule;
use Netresearch\Sync\Module\BackendGroupsModule;
use Netresearch\Sync\Module\BaseModule;
use Netresearch\Sync\Module\FalModule;
use Netresearch\Sync\Module\FrontendGroupsModule;
use Netresearch\Sync\Module\NewsModule;
use Netresearch\Sync\Module\SchedulerModule;
use Netresearch\Sync\Module\SinglePageModule;
use Netresearch\Sync\Module\TableStateModule;
use Netresearch\Sync\Module\Typo3RedirectsModule;
use Netresearch\Sync\ModuleInterface;
use Netresearch\Sync\PageSyncModuleInterface;
use Netresearch\Sync\SinglePageSyncModuleInterface;
use Netresearch\Sync\SyncList;
use Netresearch\Sync\SyncListManager;
use Netresearch\Sync\SyncLock;
use Netresearch\Sync\SyncStats;
use Netresearch\Sync\Table;
use RuntimeException;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use function count;
use function in_array;
use function is_array;
use function is_string;

/**
 * Module 'Netresearch Sync' for the 'nr_sync' extension.
 *
 * Diese Modul sorgt für die Synchronisation zwischen Staging und
 * Live. Es wird je nach Benutzergruppe eine Auswahl zu synchronisierender
 * Tabellen erscheinen. Diese werden gedumpt, gezippt und in ein bestimmtes
 * Verzeichnis geschrieben. Danach wird per FTP der Hauptserver
 * benachrichtigt. Dieser holt sich dann per RSync die Daten ab und spielt
 * sie in die DB ein. Der Cache wird ebenfalls gelöscht. Aktuell werden
 * immer auch die Files (fileadmin/ & statisch/) mitsynchronisiert.
 *
 * @todo      doc
 * @todo      Logfile in DB wo Syncs hineingeschrieben werden
 * @package   Netresearch/TYPO3/Sync
 * @author    Michael Ablass <ma@netresearch.de>
 * @author    Alexander Opitz <alexander.opitz@netresearch.de>
 * @author    Tobias Hein <tobias.hein@netresearch.de>
 * @author    Rico Sonntag <rico.sonntag@netresearch.de>
 * @company   Netresearch GmbH & Co.KG <info@netresearch.de>
 * @copyright 2004-2021 Netresearch GmbH & Co.KG
 * @license   https://www.gnu.org/licenses/agpl AGPL v3
 * @link      http://www.netresearch.de
 */
class SyncModuleController extends ActionController
{
    /**
     * The default menu item IDs.
     */
    public const MODULE_ASSET       = 1;
    public const MODULE_BE_GROUPS   = 2;
    public const MODULE_FAL         = 3;
    public const MODULE_FE_GROUPS   = 4;
    public const MODULE_SCHEDULER   = 5;
    public const MODULE_SINGLE_PAGE = 6;
    public const MODULE_TABLE_STATE = 7;
    public const MODULE_REDIRECTS   = 8;
    public const MODULE_NEWS        = 9;

    /**
     * key to use for storing insert statements
     * in $arGlobalSqlLinesStoarage
     */
    public const STATEMENT_TYPE_INSERT = 'insert';

    /**
     * key to use for storing delete statements
     * in $arGlobalSqlLinesStoarage
     */
    public const STATEMENT_TYPE_DELETE = 'delete';

    /**
     * The date format prepended to the sync files.
     */
    public const DATE_FORMAT = 'Ymd-His';

    /**
     * @var ConnectionPool
     */
    private ConnectionPool $connectionPool;

    /**
     * @var FlashMessageService
     */
    private FlashMessageService $flashMessageService;

    /**
     * @var SyncListManager
     */
    private SyncListManager $syncListManager;

    /**
     * @var Urls;
     */
    private Urls $urlGenerator;

    /**
     * Default menu functions.
     *
     * @var array
     */
    protected array $functions = [
        self::MODULE_ASSET       => AssetModule::class,
        self::MODULE_BE_GROUPS   => BackendGroupsModule::class,
        self::MODULE_FAL         => FalModule::class,
        self::MODULE_FE_GROUPS   => FrontendGroupsModule::class,
        self::MODULE_SCHEDULER   => SchedulerModule::class,
        self::MODULE_SINGLE_PAGE => SinglePageModule::class,
        self::MODULE_TABLE_STATE => TableStateModule::class,
        self::MODULE_REDIRECTS   => Typo3RedirectsModule::class,
        self::MODULE_NEWS        => NewsModule::class,
    ];

    /**
     * @var int
     */
    public $nDumpTableRecursion = 0;

    /**
     * @var array backend page information
     */
    public $pageinfo;

    /**
     * @var string Where to put DB Dumps (trailing Slash)
     */
    public $dbFolder = '';

    /**
     * @var string Where to put URL file lists
     */
    public $strUrlFolder = '';

    /**
     * @var string clearCache url format
     */
    public $strClearCacheUrl = '?nr-sync-clear-cache&task=clearCache&data=%s&new=true';

    /**
     * @var int Access rights for new folders
     */
    public $nFolderRights = 0777;

    /**
     * @var string Dummy file
     */
    public $strDummyFile = '';

    /**
     * @var string path to temp folder
     */
    protected $strTempFolder;

    /**
     * @var array pages to sync
     */
    protected $arPageIds = [];

    /**
     * @var array
     */
    public $arObsoleteRows = [];

    /**
     * @var array
     */
    public $arReferenceTables = [];

    /**
     * Multidimensional array to save the lines put to the
     * current sync file for the current sync process
     * Structure
     * $arGlobalSqlLineStorage[<statementtype>][<tablename>][<identifier>] = <statement>;
     *
     * statementtypes: delete, insert
     * tablename:      name of the table the records belong to
     * identifier:     unique identifier like uid or a uique string
     *
     * @var array
     */
    protected $arGlobalSqlLineStorage = [];

    /**
     * The name of the module. Build of the configured main module name, extension name and submodule name.
     *
     * @var string
     * @see Configuration in ext_tables.php
     */
    private $moduleName = 'web_NrSyncAdministration';

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var Area
     */
    protected $area;

    /**
     * Hook Objects
     *
     * @var array
     */
    protected $hookObjects = [];

    /**
     * @var BaseModule
     */
    protected $function;

    /**
     * BackendTemplateContainer
     *
     * @var BackendTemplateView
     */
    protected $view;

    /**
     * Backend Template Container
     *
     * @var string
     */
    protected $defaultViewObjectName = BackendTemplateView::class;


    /**
     * The int value of the GET/POST var, 'id'. Used for submodules to the 'Web' module (page id)
     *
     * @var int
     */
    private $id;

    /**
     * A WHERE clause for selection records from the pages table based on read-permissions of the current backend user.
     *
     * @var string
     */
    private $perms_clause;

    /**
     * Loaded with the global array $MCONF which holds some module configuration from the conf.php file of backend modules.
     *
     * @var array
     */
    private $MCONF;

    /**
     * The module menu items array. Each key represents a key for which values can range between the items in the array of that key.
     *
     * @var array
     */
    private $MOD_MENU = [
        'function' => []
    ];

    /**
     * Current settings for the keys of the MOD_MENU array
     *
     * @var array
     */
    private $MOD_SETTINGS = [];

    /**
     * SyncModuleController constructor.
     *
     * @param ConnectionPool $connectionPool
     * @param FlashMessageService $flashMessageService
     * @param SyncListManager $syncListManager
     * @param Urls $urlGenerator
     */
    public function __construct(
        ConnectionPool $connectionPool,
        FlashMessageService $flashMessageService,
        SyncListManager $syncListManager,
        Urls $urlGenerator
    ) {
        $this->connectionPool = $connectionPool;
        $this->flashMessageService = $flashMessageService;
        $this->syncListManager = $syncListManager;
        $this->urlGenerator = $urlGenerator;

        $this->MCONF = [
            'name' => $this->moduleName,
        ];

        $this->id = (int) GeneralUtility::_GP('id');

        $this->perms_clause = $this->getBackendUser()->getPagePermsClause(1);
    }

    /**
     * Initializes the controller before invoking an action method.
     *
     * Override this method to solve tasks which all actions have in
     * common.
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();

        $this->initFolders();
        $this->menuConfig();
    }

    /**
     * Initializes the view before invoking an action method.
     *
     * Override this method to solve assign variables common for all actions
     * or prepare the view in another way before the action is called.
     *
     * @param ViewInterface $view The view to be initialized
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws RouteNotFoundException
     */
    protected function initializeView(ViewInterface $view): void
    {
        parent::initializeView($view);

        if ($view instanceof BackendTemplateView) {
            $pageRenderer = $view->getModuleTemplate()->getPageRenderer();
            $pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
            $pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/SplitButtons');
        }

        $this->createMenu();
        $this->createButtons();
    }

    /**
     * Initialize the folders needed while synchronize.
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private function initFolders(): void
    {
        $strRootPath = $_SERVER['DOCUMENT_ROOT'];

        if (empty($strRootPath)) {
            $strRootPath = substr(Environment::getPublicPath(), 0, -1);
        }

        $this->strDummyFile = $strRootPath . '/db.txt';
        $this->dbFolder = $strRootPath . '/db/';
        $this->strUrlFolder = $this->dbFolder . 'url/';
        $this->strTempFolder = $this->dbFolder . 'tmp/';

        // https://github.com/kalessil/phpinspectionsea/blob/master/docs/probable-bugs.md#mkdir-race-condition
        if (!file_exists($this->strTempFolder)
            && !is_dir($this->strTempFolder)
            && !mkdir($this->strTempFolder, $this->nFolderRights, true)
            && !is_dir($this->strTempFolder)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->strTempFolder));
        }

        // https://github.com/kalessil/phpinspectionsea/blob/master/docs/probable-bugs.md#mkdir-race-condition

        if (!file_exists($this->strUrlFolder)
            && !is_dir($this->strUrlFolder)
            && !mkdir($this->strUrlFolder, $this->nFolderRights, true)
            && !is_dir($this->strUrlFolder)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->strUrlFolder));
        }
    }

    /**
     * Adds items to the ->MOD_MENU array. Used for the function menu selector.
     *
     * @return void
     */
    private function menuConfig(): void
    {
        $this->MOD_MENU = [
            'function' => [],
        ];

        $accessLevel = $this->getBackendUser()->isAdmin() ? 100 : 50;

        // Menu hook
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['nr_sync/mod1/index.php']['hookClass'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['nr_sync/mod1/index.php']['hookClass'] as $id => $hookObject) {
                if ($hookObject !== null) {
                    $this->functions[$id] = $hookObject;
                    $this->hookObjects[$id] = $hookObject;
                }
            }
        }

        /** @var ModuleInterface $function */
        foreach ($this->functions as $functionKey => $functionClass) {
            $function = $this->getFunctionObject($functionKey);

            if ($function && ($accessLevel >= $function->getAccessLevel())) {
                $this->MOD_MENU['function'][$functionKey] = $function->getName();
            }
        }

        // Sort by name
        natcasesort($this->MOD_MENU['function']);

        // Add a "please select" entry to the menu list
        $this->MOD_MENU['function'] = [ 0 => 'Please select' ] + $this->MOD_MENU['function'];

        $this->MOD_SETTINGS = BackendUtility::getModuleData(
            $this->MOD_MENU,
            GeneralUtility::_GP('SET'),
            $this->MCONF['name']
        );
    }

    /**
     * Returns a TYPO3 QueryBuilder instance for a given table, without any restriction.
     *
     * @param string $tableName The table name
     *
     * @return QueryBuilder
     */
    private function getQueryBuilderForTable(string $tableName): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    /**
     * @param int $functionKey
     *
     * @return null|ModuleInterface
     */
    private function getFunctionObject(int $functionKey): ?ModuleInterface
    {
        if (is_string($this->functions[$functionKey])
            && class_exists($this->functions[$functionKey])
        ) {
            $function = GeneralUtility::makeInstance($this->functions[$functionKey]);

            if (!($function instanceof ModuleInterface)) {
                $this->addError(
                    sprintf(
                        'The deprecated module "%s" has been disabled. It must '
                        . 'implement \Netresearch\Sync\ModuleInterface',
                        $this->functions[$functionKey]
                    )
                );

                return null;
            }

            // Object is not available due some reasons
            if (!$function->isAvailable()) {
                return null;
            }
        } else {
            $function = GeneralUtility::makeInstance(
                BaseModule::class,
                $this->functions[$functionKey]
            );
        }

        return $function;
    }

    /**
     * The controllers main action.
     *
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws RouteNotFoundException
     */
    public function mainAction(): void
    {
        $GLOBALS['SOBE'] = $this;

        $this->main();

        $this->view->assign('moduleName', $this->getModuleUrl());
        $this->view->assign('id', $this->id);
    }

    /**
     * Tests if given tables holds data on given page id.
     * Returns true if "pages" is one of the tables to look for without checking
     * if page exists.
     *
     * @param int        $nId    The page id to look for
     * @param null|array $tables The tables this task manages
     *
     * @return bool True if data exists otherwise false.
     */
    private function pageContainsData(int $nId, array $tables = null): bool
    {
        if ($tables === null) {
            return false;
        }

        if (in_array('pages', $tables, true)) {
            return true;
        }

        foreach ($tables as $tableName) {
            if (isset($GLOBALS['TCA'][$tableName])) {
                $queryBuilder = $this->getQueryBuilderForTable($tableName);

                $count = $queryBuilder
                    ->count('pid')
                    ->from($tableName)
                    ->where($queryBuilder->expr()->eq('pid', $nId))
                    ->execute()
                    ->fetchOne();

                if ($count > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Shows the page selection, depending on selected id and tables to look at.
     *
     * @param int   $functionId The function ID of this page selection (Depends on task)
     * @param array $tables     Tables this task manages
     *
     * @return void
     */
    private function showPageSelection(int $functionId, array $tables): void
    {
        if ($this->id === 0) {
            $this->addError('Please select a page from the page tree.');
            return;
        }

        $record = BackendUtility::getRecord('pages', $this->id);

        if ($record === null) {
            $this->addError('Could not load record for selected page: ' . $this->id . '.');
            return;
        }

        if ($this->getArea()->isDocTypeAllowed($record) === false) {
            $this->addError('Page type not allowed to sync.');
            return;
        }

        $bShowButton = false;
        $recursion = (int) $this->getBackendUser()->getSessionData('nr_sync_synclist_levelmax' . $functionId);

        if (isset($_POST['data']['rekursion'])) {
            $recursion = (int) $_POST['data']['levelmax'];

            $this->getBackendUser()->setAndSaveSessionData('nr_sync_synclist_levelmax' . $functionId, $recursion);
        }

        if ($recursion < 1) {
            $recursion = 1;
        }

        $arCount = [];

        $this->getSyncList()
            ->getSubpagesAndCount(
                $this->id,
                $arCount,
                0,
                $recursion,
                $this->getArea()->getNotDocType(),
                $this->getArea()->getDocType(),
                $tables
            );

        $strTitle = $this->getArea()->getName() . ' - ' . $record['uid'] . ' - ' . $record['title'];

        if (((int) $record['doktype']) === PageRepository::DOKTYPE_SHORTCUT) {
            $strTitle .= ' - LINK';
        }

        $this->view->assign('pageValid', true);
        $this->view->assign('title', $strTitle);
        $this->view->assign('arCount', $arCount);
        $this->view->assign('record', $record);

        if ($this->pageContainsData($this->id, $tables)) {
            $this->view->assign('pageContainsData', true);
            $bShowButton = true;
        }

        if ($arCount['count'] > 0) {
            $bShowButton = true;
        }

        $this->view->assign('bShowButton', $bShowButton);

        if ($bShowButton) {
            $this->view->assign('recursion', $recursion);
        } else {
            $this->addError(
                'Bitte wählen Sie eine Seite mit entsprechendem Inhalt aus.'
            );
        }
    }

    /**
     * Manages adding and deleting of pages/trees to the sync list.
     */
    private function manageSyncList(): void
    {
        // Add ID
        if (isset($_POST['data']['add'])) {
            if (isset($_POST['data']['type'])) {
                $this->getSyncList()->addToSyncList($_POST['data']);
            } else {
                $this->addError(
                    'Please select how the page should be bookmarked.'
                );
            }
        }

        // Remove ID
        if (isset($_POST['data']['delete'])) {
            $this->getSyncList()->deleteFromSyncList($_POST['data']);
        }

        $this->getSyncList()->saveSyncList();
    }

    /**
     * @return int
     */
    private function getCurrentFunctionId(): int
    {
        return (int) $this->MOD_SETTINGS['function'];
    }

    /**
     * @return SyncList
     */
    private function getSyncList(): SyncList
    {
        return $this->syncListManager->getSyncList($this->getCurrentFunctionId());
    }

    /**
     * @return Area
     */
    private function getArea(): Area
    {
        if ($this->area === null) {
            $this->area = GeneralUtility::makeInstance(Area::class, $this->id);
        }

        return $this->area;
    }

    /**
     * Main function of the module. Assigns relevant data to the output view.
     *
     * If you chose 'web' as main module, you will need to consider the $this->id
     * parameter which will contain the uid-number of the page clicked in the page
     * tree
     *
     * @return void
     *
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws \Exception
     */
    private function main(): void
    {
        // Access check!
        // The page will show only if there is a valid page and if this page may
        // be viewed by the user
        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);

        if ($this->pageinfo) {
            $this->view
                ->getModuleTemplate()
                ->getDocHeaderComponent()
                ->setMetaInformation($this->pageinfo);
        }

        /** @var SyncLock $syncLock */
        $syncLock = $this->objectManager->get(SyncLock::class);

        if ($this->getBackendUser()->isAdmin()) {
            $syncLock->handleLockRequest();
        }

        $this->view->assign('syncLock', $syncLock);

        if ($syncLock->isLocked()) {
            return;
        }

        if (isset($_REQUEST['lock'])) {
            foreach ($_REQUEST['lock'] as $systemName => $lockState) {
                $systems         = $this->getArea()->getSystems();
                $system          = $systems[$systemName];
                $systemDirectory = $this->dbFolder . $system['directory'];
                $lockFilePath    = $systemDirectory . '/.lock';

                if ($lockState) {
                    // https://github.com/kalessil/phpinspectionsea/blob/master/docs/probable-bugs.md#mkdir-race-condition
                    if (!is_dir($systemDirectory)
                        && !mkdir($systemDirectory, 0777, true)
                        && !is_dir($systemDirectory)
                    ) {
                        throw new RuntimeException(sprintf('Directory "%s" was not created', $systemDirectory));
                    }

                    $handle = fopen($lockFilePath, 'wb');

                    if ($handle) {
                        fclose($handle);
                    }

                    $this->addSuccess('Target ' . $systemName . ' disabled for sync.');
                } elseif (file_exists($lockFilePath)) {
                    unlink($lockFilePath);

                    $this->addSuccess('Target ' . $systemName . ' enabled for sync.');
                }
            }
        }

        $bUseSyncList = false;

        if (!isset($this->functions[$this->MOD_SETTINGS['function']])) {
            $this->MOD_SETTINGS['function'] = 0;
        }

        $this->function = $this->getFunctionObject($this->getCurrentFunctionId());
        $dumpFile       = $this->function->getDumpFileName();

        $this->view->assign('selectedMenuItem', $this->getCurrentFunctionId());
        $this->view->assign('function', $this->function);
        $this->view->assign('id', $this->id);
        $this->view->assign('area', $this->getArea());
        $this->view->assign('dbFolder', $this->dbFolder);
        $this->view->assign('isSingePageSyncModule', $this->isSinglePageSyncModule());

        // Sync single pages/page trees (TYPO3 6.2.x)
        if ($this->function instanceof SinglePageSyncModuleInterface) {
            $this->showPageSelection(
                $this->getCurrentFunctionId(),
                $this->function->getTableNames()
            );

            $this->manageSyncList();
            $bUseSyncList = true;
        }

        $this->function->run($this->getArea());

        if ($this->function->hasError()) {
            $this->addError($this->function->getError());
        }

        // sync process
        if (isset($_POST['data']['submit']) && ($dumpFile !== '')) {
            $dumpFile = $this->addInformationToSyncfileName($dumpFile);

            if ($bUseSyncList) {
                $syncList = $this->getSyncList();

                if (!$syncList->isEmpty()) {
                    $strDumpFileArea = date(self::DATE_FORMAT . '_') . $dumpFile;

                    foreach ($syncList->getAsArray() as $areaID => $syncListArea) {
                        /** @var Area $area */
                        $area = GeneralUtility::makeInstance(Area::class, $areaID);

                        $pageIDs = $syncList->getAllPageIDs($areaID);

                        $ret = $this->createShortDump(
                            $pageIDs,
                            $this->function->getTableNames(),
                            $strDumpFileArea,
                            $area->getDirectories()
                        );

                        if ($ret && $this->createClearCacheFile('pages', $pageIDs)) {
                            if ($area->notifyMaster() !== false) {
                                $this->addSuccess(
                                    'Sync started - should be processed within next 15 minutes.'
                                );
                                $syncList->emptyArea($areaID);
                            } else {
                                $this->addError('Please re-try in a couple of minutes.');
                                foreach ($area->getDirectories() as $strDirectory) {
                                    @unlink($this->dbFolder . $strDirectory . '/' . $strDumpFileArea);
                                    @unlink($this->dbFolder . $strDirectory . '/' . $strDumpFileArea . '.gz');
                                }
                            }

                            $syncList->saveSyncList();
                        }
                    }
                }
            } else {
                foreach ($this->hookObjects as $hookClass) {
                    /** @var ModuleInterface $hookObject */
                    $hookObject = GeneralUtility::makeInstance($hookClass);

                    if (method_exists($hookObject, 'preProcessSync')) {
                        $hookObject->preProcessSync(
                            $this->function->getTableNames()[0],
                            $this->getPreSyncParams(),
                            $this->getSyncList()
                        );
                    }
                }

                $bSyncResult = $this->createDumpToAreas(
                    $this->function->getTableNames(), $dumpFile
                );

                // MFI-152 Create page sync files for pages which have related pages.
                if ($bSyncResult
                    && ($this->function instanceof PageSyncModuleInterface)
                ) {
                    $arPageIDs = $this->function->getPagesToSync();
                    $dumpFile = 'pages_' . $dumpFile;
                    $strDumpFileArea = date(self::DATE_FORMAT . '_') . $dumpFile;
                    $tables = [
                        'pages',
                        'tt_content',
                        'sys_template',
                        'sys_file_reference',
                    ];

                    $area = GeneralUtility::makeInstance(Area::class, 0);

                    $this->createShortDump(
                        $arPageIDs,
                        $tables,
                        $strDumpFileArea,
                        $area->getDirectories()
                    );
                    $this->createClearCacheFile('pages', $arPageIDs);
                }

                if ($bSyncResult) {
                    $this->addSuccess('Sync initiated.');
                }
            }
        }

        if (empty($bUseSyncList) && !empty($this->function->getTableNames())) {
            /** @var SyncStats $syncStats */
            $syncStats = $this->objectManager->get(
                SyncStats::class, null, $this->function->getTableNames()
            );

            $this->view->assign('tableSyncStats', $syncStats);
            $this->view->assign('showTableSyncStats', true);
        }

        $this->view->assign('bUseSyncList', $bUseSyncList);
        $this->view->assign('syncList', $this->getSyncList());

        if (($bUseSyncList && !$this->getSyncList()->isEmpty())
            || ($bUseSyncList === false && count($this->function->getTableNames()))
        ) {
            $this->view->assign('showCheckBoxes', true);
        }

        $this->view->assign('moduleRoute', $this->moduleName);
    }

    /**
     * Returns TRUE if the current module is the single page sync module.
     *
     * @return bool
     */
    private function isSinglePageSyncModule(): bool
    {
        return ($this->getCurrentFunctionId()) === self::MODULE_SINGLE_PAGE;
    }

    /**
     *
     * @param string[] $tables Table names
     * @param string $dumpFile Name of the dump file.
     *
     * @return bool
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     * @throws \Exception
     */
    private function createDumpToAreas(
        array $tables,
        string $dumpFile
    ): bool {
        $filename = date(self::DATE_FORMAT . '_') . $dumpFile;
        $dumpFile = $this->strTempFolder . sprintf($dumpFile, '');

        if (file_exists($dumpFile)
            || file_exists($dumpFile . '.gz')
        ) {
            $this->addError('Die letzte Synchronisationsvorbereitung ist noch'
                . ' nicht abgeschlossen. Bitte versuchen Sie es in 5'
                . ' Minuten noch einmal.');
            return false;
        }

        Table::writeDumps(
            $tables,
            $dumpFile,
            [
                'forceFullSync'      => !empty($_POST['data']['force_full_sync']),
                'deleteObsoleteRows' => !empty($_POST['data']['delete_obsolete_rows']),
            ]
        );

        if (!file_exists($dumpFile)) {
            $this->addInfo(
                'No data dumped for further processing. '
                . 'The selected sync process did not produce any data to be '
                . 'synched to the live system.'
            );
            return false;
        }

        if (!$this->createGZipFile($dumpFile)) {
            $this->addError('Zipfehler: ' . $dumpFile);
            return false;
        }

        foreach (Area::getMatchingAreas() as $area) {
            foreach ($area->getDirectories() as $strPath) {
                // https://github.com/kalessil/phpinspectionsea/blob/master/docs/probable-bugs.md#mkdir-race-condition
                if (!file_exists($this->dbFolder . $strPath . '/')
                    && !is_dir($this->dbFolder . $strPath)
                    && !mkdir($this->dbFolder . $strPath, $this->nFolderRights, true)
                    && !is_dir($this->dbFolder . $strPath)
                ) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $this->dbFolder . $strPath));
                }

                if (!copy(
                    $dumpFile . '.gz',
                    $this->dbFolder . $strPath . '/' . $filename
                    . '.gz'
                )) {
                    $this->addError('Konnte ' . $this->strTempFolder
                        . $dumpFile . '.gz nicht nach '
                        . $this->dbFolder . $strPath . '/'
                        . $filename . '.gz kopieren.');
                    return false;
                }
                chmod($this->dbFolder . $strPath . '/' . $filename . '.gz', 0666);
            }

            if ($area->notifyMaster() === false) {
                return false;
            }
        }

        unlink($dumpFile . '.gz');

        return true;
    }


    /**
     * Generates the file with the content for the clear cache task.
     *
     * @param string $table Name of the table which cache should be cleared.
     * @param int[] $arUids Array with the uids to clear cache.
     *
     * @return bool True if file was generateable otherwise false.
     *
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function createClearCacheFile(
        string $table,
        array $arUids
    ): bool {
        $arClearCacheData = [];

        // Create data
        foreach ($arUids as $strUid) {
            $arClearCacheData[] = $table . ':' . $strUid;
        }

        $strClearCacheData = implode(',', $arClearCacheData);
        $clearCacheUrl = sprintf($this->strClearCacheUrl, $strClearCacheData);

        $this->urlGenerator->postProcessSync(
            [
                'arUrlsOnce' => [
                    $clearCacheUrl
                ],
                'bProcess' => true,
                'bSyncResult' => true
            ],
            $this
        );

        return true;
    }

    /**
     * Baut speziellen Dump zusammen, der nur die angewählten Pages enthält.
     * Es werden nur Pages gedumpt, zu denen der Redakteur auch Zugriff hat.
     *
     * @param array $pageIDs List if page IDs to dump
     * @param array $tables List of tables to dump
     * @param string $dumpFile Name of target dump file
     * @param array $arPath
     *
     * @return bool success
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function createShortDump(
        array $pageIDs,
        array $tables,
        string $dumpFile,
        array $arPath
    ): bool {
        if (!is_array($pageIDs) || count($pageIDs) <= 0) {
            $this->addError('Keine Seiten für die Synchronisation vorgemerkt.');
            return false;
        }

        try {
            $fpDumpFile = $this->openTempDumpFile($dumpFile, $arPath);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        foreach ($tables as $value) {
            $this->dumpTableByPageIDs($pageIDs, $value, $fpDumpFile);
        }

        // TYPO-206
        // Append Statement for Delete unused rows in LIVE environment
        $this->writeToDumpFile(
            [],
            [],
            $fpDumpFile,
            $this->getDeleteRowStatements()
        );
        // TYPO-2214: write inserts at the end of the file
        $this->writeInsertLines($fpDumpFile);

        try {
            fclose($fpDumpFile);
            $this->finalizeDumpFile($dumpFile, $arPath, true);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Test and opens the temporary dump file.
     *
     * @param string $strFileName   Name of the dump file.
     * @param array  $arDirectories The directories to copy the temp files later.
     *
     * @return resource The opened dump file
     * @throws Exception If file can't be opened or last sync is in progress.
     */
    private function openTempDumpFile(string $strFileName, array $arDirectories)
    {
        if (file_exists($this->strTempFolder . $strFileName)
            || file_exists($this->strTempFolder . $strFileName . '.gz')
        ) {
            throw new Exception(
                'Die letzte Synchronisationsvorbereitung ist noch nicht abgeschlossen.'
                . ' Bitte versuchen Sie es in wenigen Minuten noch einmal.'
                . "<br/>\n"
                . $this->strTempFolder . $strFileName . '(.gz)'
            );
        }

        foreach ($arDirectories as $strPath) {
            if (file_exists($this->dbFolder . $strPath . '/' . $strFileName)
                || file_exists($this->dbFolder . $strPath . '/' . $strFileName . '.gz')
            ) {
                throw new Exception(
                    'Die letzte Synchronisation ist noch nicht abgeschlossen.'
                    . ' Bitte versuchen Sie es in wenigen Minuten noch einmal.'
                );
            }
        }

        $fp = fopen($this->strTempFolder . $strFileName, 'wb');

        if ($fp === false) {
            throw new Exception(
                $this->strTempFolder . $strFileName
                . ' konnte nicht angelegt werden.'
            );
        }

        return $fp;
    }

    /**
     * Zips the tmp dump file and copy it to given directories.
     *
     * @param string $dumpFile      Name of the dump file.
     * @param array  $arDirectories The directories to copy files into.
     * @param bool   $bZipFile      The directories to copy files into.
     *
     * @return void
     * @throws Exception If file can't be zipped or copied.
     */
    private function finalizeDumpFile(string $dumpFile, array $arDirectories, bool $bZipFile): void
    {
        if ($bZipFile) {
            // Dateien komprimieren
            if (!$this->createGZipFile($this->strTempFolder . $dumpFile)) {
                throw new Exception('Could not create ZIP file.');
            }
            $dumpFile .= '.gz';
        }

        // Dateien an richtige Position kopieren
        foreach ($arDirectories as $strPath) {
            $strTargetDir = $this->dbFolder . $strPath . '/';
            if (!file_exists($strTargetDir)) {
                // https://github.com/kalessil/phpinspectionsea/blob/master/docs/probable-bugs.md#mkdir-race-condition
                if (!is_dir($strTargetDir)
                    && !mkdir($strTargetDir, $this->nFolderRights, true)
                    && !is_dir($strTargetDir)
                ) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $strTargetDir));
                }

                // TYPO-3713: change folder permissions to nFolderRights due to
                // mask of mkdir could be overwritten by the host
                chmod($strTargetDir, $this->nFolderRights);
            }

            $bCopied = copy(
                $this->strTempFolder . $dumpFile,
                $strTargetDir . $dumpFile
            );

            chmod($strTargetDir . $dumpFile, 0666);

            if (!$bCopied) {
                throw new Exception(
                    'Konnte ' . $this->strTempFolder . $dumpFile
                    . ' nicht nach '
                    . $strTargetDir . $dumpFile
                    . ' kopieren.'
                );
            }
        }

        unlink($this->strTempFolder . $dumpFile);
    }

    /**
     * Erzeugt ein Dump durch Seiten IDs.
     *
     * @param array $pageIDs Page ids to dump.
     * @param string $tableName Name of table to dump from.
     * @param resource $fpDumpFile File pointer to the SQL dump file.
     * @param bool $bContentIDs True to interpret pageIDs as content IDs.
     *
     * @return void
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function dumpTableByPageIDs(
        array $pageIDs,
        string $tableName,
        $fpDumpFile,
        bool $bContentIDs = false
    ): void {
        if (substr($tableName, -3) === '_mm') {
            throw new Exception(
                'MM Tabellen wie: ' . $tableName . ' werden nicht mehr unterstützt.'
            );
        }

        $this->nDumpTableRecursion++;
        $deleteLines = [];
        $insertLines = [];

        $columns = $this->connectionPool
            ->getConnectionForTable($tableName)
            ->getSchemaManager()
            ->listTableColumns($tableName);

        $columnNames = [];
        foreach ($columns as $column) {
            $columnNames[] = $column->getName();
        }

        $queryBuilder = $this->getQueryBuilderForTable($tableName);

        // In pages und pages_language_overlay entspricht die pageID der uid
        // pid ist ja der Parent (Elternelement) ... so mehr oder weniger *lol*
        if ($tableName === 'pages' || $bContentIDs) {
            $strWhere = $queryBuilder->expr()->in('uid', $pageIDs);
        } else {
            $strWhere = $queryBuilder->expr()->in('pid', $pageIDs);
        }

        $statement = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->where($strWhere)
            ->execute();

        if ($statement) {
            foreach ($this->hookObjects as $hookClass) {
                /** @var ModuleInterface $hookObject */
                $hookObject = GeneralUtility::makeInstance($hookClass);

                // Run hook for each
                if (method_exists($hookObject, 'preProcessSync')) {
                    $hookObject->preProcessSync(
                        $tableName,
                        $this->getPreSyncParams(),
                        $this->getSyncList()
                    );
                }
            }

            while ($row = $statement->fetchAssociative()) {
                $deleteLines[$tableName][$row['uid']]
                    = $this->buildDeleteLine($tableName, $row['uid']);

                $insertLines[$tableName][$row['uid']]
                    = $this->buildInsertUpdateLine($tableName, $columnNames, $row);

                $this->writeMMReferences($tableName, $row, $fpDumpFile);

                if (count($deleteLines) > 50) {
                    $this->prepareDump($deleteLines, $insertLines, $fpDumpFile);
                    $deleteLines = [];
                    $insertLines = [];
                }
            }
        }

        // TYPO-206: append delete obsolete rows on live
        if (!empty($_POST['data']['delete_obsolete_rows'])) {
            $this->addAsDeleteRowTable($tableName);
        }

        $this->prepareDump($deleteLines, $insertLines, $fpDumpFile);

        $this->nDumpTableRecursion--;
    }

    /**
     * Get params for the presync hook.
     *
     * @return string[]
     */
    private function getPreSyncParams(): array
    {
        $postData = GeneralUtility::_GP('data');

        return [
            'area' => $this->getArea(),
            'function' => $this->getCurrentFunctionId(),
            'dbFolder' => $this->dbFolder,
            'forceFullSync' => $postData['force_full_sync'],
        ];
    }

    /**
     * Adds the Table and its DeleteObsoleteRows statement to an array
     * if the statement does not exists in the array
     *
     * @param string $tableName The name of the table the obsolete rows
     *                          should be added to the $arObsoleteRows array for
     *
     * @return void
     */
    private function addAsDeleteRowTable(string $tableName): void
    {
        $table = GeneralUtility::makeInstance(Table::class, $tableName, 'dummy');

        if (!isset($this->arObsoleteRows[0])) {
            $this->arObsoleteRows[0] = '-- Delete obsolete Rows on live, see: TYPO-206';
        }

        $strSql = $table->getSqlDroppingObsoleteRows();
        unset($table);

        if (empty($strSql)) {
            return;
        }

        $strSqlKey = md5($strSql);

        if (isset($this->arObsoleteRows[$strSqlKey])) {
            return;
        }

        $this->arObsoleteRows[$strSqlKey] = $strSql;
    }

    /**
     * @return array
     */
    private function getDeleteRowStatements(): array
    {
        return $this->arObsoleteRows;
    }

    /**
     * Writes the references of a table to the sync data.
     *
     * @param string $strRefTableName Table to reference.
     * @param array $row The database row to find MM References.
     * @param resource $fpDumpFile File pointer to the SQL dump file.
     *
     * @return void
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function writeMMReferences(
        string $strRefTableName,
        array $row,
        $fpDumpFile
    ): void {
        $deleteLines = [];
        $insertLines = [];

        $this->arReferenceTables = [];
        $this->addMMReferenceTables($strRefTableName);

        foreach ($this->arReferenceTables as $mmTableName => $arTableFields) {
            $columns = $this->connectionPool
                ->getConnectionForTable($mmTableName)
                ->getSchemaManager()
                ->listTableColumns($mmTableName);

            $columnNames = [];
            foreach ($columns as $column) {
                $columnNames[] = $column->getName();
            }

            foreach ($arTableFields as $arMMConfig) {
                $this->writeMMReference(
                    $strRefTableName,
                    $mmTableName,
                    $row['uid'],
                    $arMMConfig,
                    $columnNames,
                    $fpDumpFile
                );
            }
        }

        $this->prepareDump($deleteLines, $insertLines, $fpDumpFile);
    }

    /**
     * Writes the data of a MM table to the sync data.
     * Calls dumpTableByPageIDs for sys_file_reference if MM Table isn't sys_file. Or
     * calls dumpTableByPageIDs for tx_dam_mm_ref if MM Table isn't tx_dam.
     *
     * MM table structure:
     *
     * - uid_local
     * -- uid from 'local' table, local table ist first part of mm table name
     * -- sys_file_reference -> uid_local points to uid in sys_file
     *    /tx_dam_mm_ref -> uid_local points to uid in tx_dam
     * -- tt_news_cat_mm -> uid_local points to uid in tt_news_cat
     * - uid_foreign
     * -- uid from foreign table, foreign is the table in field 'tablenames'
     * --- tx_Dem_mm_ref -> uid_foreign points to uid in table from 'tablenames'
     * -- or static table name (hidden in code)
     * --- tt_news_cat_mm -> uid_foreign points to uid in tt_news
     * -- or last part of mm table name
     * --- sys_category_record_mm -> uid_foreign points to uid in sys_category
     *     /tx_dam_mm_cat -> uid_foreign points to uid in tx_dam_cat
     * - tablenames
     * -- optional, if present forms unique data with uid_* and ident
     * - ident
     * -- optional, if present forms unique data with uid_* and tablenames
     * -- points to a field in TCA or Flexform
     * - sorting - optional
     * - sorting_foreign - optional
     *
     * @param string $strRefTableName Table which we get the references from.
     * @param string $tableName Table to get MM data from.
     * @param int $uid The uid of element which references.
     * @param array $arMMConfig The configuration of this MM reference.
     * @param array $columnNames Table columns
     * @param resource $fpDumpFile File pointer to the SQL dump file.
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function writeMMReference(
        string $strRefTableName,
        string $tableName,
        int $uid,
        array $arMMConfig,
        array $columnNames,
        $fpDumpFile
    ): void {
        $deleteLines = [];
        $insertLines = [];

        $strFieldName = $arMMConfig['foreign_field'] ?? 'uid_foreign';

        $connection = $this->connectionPool->getConnectionForTable($tableName);

        $strAdditionalWhere = ' AND ' . $connection->quoteIdentifier('tablenames')
            . ' = ' . $connection->quote($strRefTableName);

        $strWhere = $strFieldName . ' = ' . $uid;

        if (isset($arMMConfig['foreign_match_fields'])) {
            foreach ($arMMConfig['foreign_match_fields'] as $strName => $strValue) {
                $strWhere .= ' AND ' . $connection->quoteIdentifier($strName) . ' = ' . $connection->quote($strValue)
                    . $strAdditionalWhere;
            }
        }

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();

        $statement = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->where($strWhere)
            ->execute();


        if ($tableName !== 'sys_file_reference') {
            $deleteLines[$tableName][$uid] = sprintf(
                'DELETE FROM %s WHERE %s;',
                $connection->quoteIdentifier($tableName),
                $strWhere
            );
        }

        if ($statement) {
            while ($row = $statement->fetchAssociative()) {
                $insertLines[$tableName][$row['uid']]
                    = $this->buildInsertUpdateLine($tableName, $columnNames, $row);

                if (($strRefTableName !== 'sys_file')
                    && ($arMMConfig['MM'] === 'sys_file_reference')
                    && ($arMMConfig['form_type'] === 'user')
                ) {
                    $this->dumpTableByPageIDs(
                        [
                            $row['uid_local'],
                        ],
                        'sys_file',
                        $fpDumpFile,
                        true
                    );
                }
            }

            unset($statement);
        }

        $this->prepareDump($deleteLines, $insertLines, $fpDumpFile);
    }

    /**
     * Finds MM reference tables and the config of them. Respects flexform fields.
     * Data will be set in arReferenceTables
     *
     * @param string $tableName Table to find references.
     *
     * @return void
     */
    private function addMMReferenceTables(string $tableName): void
    {
        if (!isset($GLOBALS['TCA'][$tableName]['columns'])) {
            return;
        }

        foreach ($GLOBALS['TCA'][$tableName]['columns'] as $column) {
            if (isset($column['config']['type'])) {
                if ($column['config']['type'] === 'inline') {
                    $this->addForeignTableToReferences($column);
                } else {
                    $this->addMMTableToReferences($column);
                }
            }
        }
    }

    /**
     * Adds Column config to references table, if a foreign_table reference config
     * like in inline-fields exists.
     *
     * @param array $column Column config to get foreign_table data from.
     *
     * @return void
     */
    private function addForeignTableToReferences(array $column): void
    {
        if (isset($column['config']['foreign_table'])) {
            $strForeignTable = $column['config']['foreign_table'];
            $this->arReferenceTables[$strForeignTable][] = $column['config'];
        }
    }

    /**
     * Adds Column config to references table, if a MM reference config exists.
     *
     * @param array $column Column config to get MM data from.
     *
     * @return void
     */
    private function addMMTableToReferences(array $column): void
    {
        if (isset($column['config']['MM'])) {
            $this->arReferenceTables[$column['config']['MM']][] = $column['config'];
        }
    }

    /**
     * Add the passed $arSqlLines to the $arGlobalSqlLineStorage in unique way.
     *
     * @param string $strStatementType the type of the current arSqlLines
     * @param array  $arSqlLines       multidimensional array of sql statements
     *
     * @return void
     */
    private function addLinesToLineStorage(string $strStatementType, array $arSqlLines): void
    {
        foreach ($arSqlLines as $tableName => $lines) {
            if (!is_array($lines)) {
                return;
            }

            foreach ($lines as $strIdentifier => $strLine) {
                $this->arGlobalSqlLineStorage[$strStatementType][$tableName][$strIdentifier] = $strLine;
            }
        }
    }

    /**
     * Removes all entries from $arSqlLines which already exists in $arGlobalSqlLineStorage
     *
     * @param string $strStatementType Type the type of the current arSqlLines
     * @param array  &$arSqlLines      multidimensional array of sql statements
     *
     * @return void
     */
    private function clearDuplicateLines(string $strStatementType, array &$arSqlLines): void
    {
        foreach ($arSqlLines as $tableName => $lines) {
            foreach ($lines as $strIdentifier => $strStatement) {
                if (!empty($this->arGlobalSqlLineStorage[$strStatementType][$tableName][$strIdentifier])) {
                    unset($arSqlLines[$tableName][$strIdentifier]);
                }
            }

            // unset tablename key if no statement exists anymore
            if (count($arSqlLines[$tableName]) === 0) {
                unset($arSqlLines[$tableName]);
            }
        }
    }

    /**
     * Writes the data into dump file. Line per line.
     *
     * @param array $deleteLines The lines with the delete statements.
     *                                        Expected structure:
     *                                        $deleteLines['table1']['uid1'] = 'STATMENT1'
     *                                        $deleteLines['table1']['uid2'] = 'STATMENT2'
     *                                        $deleteLines['table2']['uid2'] = 'STATMENT3'
     * @param array $insertLines The lines with the insert statements.
     *                                        Expected structure:
     *                                        $insertLines['table1']['uid1'] = 'STATMENT1'
     *                                        $insertLines['table1']['uid2'] = 'STATMENT2'
     *                                        $insertLines['table2']['uid2'] = 'STATMENT3'
     * @param resource $fpDumpFile File pointer to the SQL dump file.
     * @param array $arDeleteObsoleteRows the lines with delete obsolete
     *                                        rows statement
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    private function writeToDumpFile(
        array $deleteLines,
        array $insertLines,
        $fpDumpFile,
        array $arDeleteObsoleteRows = []
    ): void {
        // Keep the current lines in mind
        $this->addLinesToLineStorage(
            self::STATEMENT_TYPE_DELETE,
            $deleteLines
        );

        // Keep the current lines in mind
        $this->addLinesToLineStorage(
            self::STATEMENT_TYPE_INSERT,
            $insertLines
        );

        // Foreach Table in DeleteArray
        foreach ($deleteLines as $arDelLines) {
            if (count($arDelLines)) {
                $strDeleteLines = implode("\n", $arDelLines);
                fwrite($fpDumpFile, $strDeleteLines . "\n\n");
            }
        }

        // do not write the inserts here, we want to add them
        // at the end of the file see $this->writeInsertLines

        if (count($arDeleteObsoleteRows)) {
            $strDeleteObsoleteRows = implode("\n", $arDeleteObsoleteRows);
            fwrite($fpDumpFile, $strDeleteObsoleteRows . "\n\n");
        }

        foreach ($insertLines as $table => $arInsertStatements) {
            foreach ($arInsertStatements as $uid => $strStatement) {
                if (strpos($table, '_mm') !== false) {
                    continue;
                }

                $this->setLastDumpTimeForElement($table, $uid);
            }
        }
    }

    /**
     * Writes all SQL Lines from arGlobalSqlLineStorage[self::STATEMENT_TYPE_INSERT]
     * to the passed file stream.
     *
     * @param resource $fpDumpFile the file to write the lines to
     *
     * @return void
     */
    private function writeInsertLines($fpDumpFile): void
    {
        if (!is_array($this->arGlobalSqlLineStorage[self::STATEMENT_TYPE_INSERT])) {
            return;
        }

        $insertLines = $this->arGlobalSqlLineStorage[self::STATEMENT_TYPE_INSERT];

        // Foreach Table in InsertArray
        foreach ($insertLines as $table => $arTableInsLines) {
            if (count($arTableInsLines)) {
                $strInsertLines = '-- Insert lines for Table: '
                    . $table . "\n"
                    . implode("\n", $arTableInsLines);

                fwrite($fpDumpFile, $strInsertLines . "\n\n");
            }
        }
    }

    /**
     * Removes all delete statements from $deleteLines where an insert statement
     * exists in $insertLines.
     *
     * @param array &$deleteLines referenced array with delete statements
     *                              structure should be
     *                              $deleteLines['table1']['uid1'] = 'STATMENT1'
     *                              $deleteLines['table1']['uid2'] = 'STATMENT2'
     *                              $deleteLines['table2']['uid2'] = 'STATMENT3'
     * @param array $insertLines referenced array with insert statements
     *                              structure should be
     *                              $deleteLines['table1']['uid1'] = 'STATMENT1'
     *                              $deleteLines['table1']['uid2'] = 'STATMENT2'
     *                              $deleteLines['table2']['uid2'] = 'STATMENT3'
     *
     * @return void
     */
    private function diffDeleteLinesAgainstInsertLines(
        array &$deleteLines,
        array $insertLines
    ): void {
        if (empty($deleteLines)) {
            return;
        }
        foreach ($insertLines as $tableName => $arElements) {
            // no modification for arrays with old flat structure
            if (!is_array($arElements)) {
                return;
            }

            // UNSET each delete line where an insert exists
            foreach ($arElements as $strUid => $strStatement) {
                if (!empty($deleteLines[$tableName][$strUid])) {
                    unset($deleteLines[$tableName][$strUid]);
                }
            }

            if (count($deleteLines[$tableName]) === 0) {
                unset($deleteLines[$tableName]);
            }
        }
    }

    /**
     * Returns SQL DELETE query.
     *
     * @param string $tableName The name of table to delete from
     * @param int    $uid       The UID of row to delete
     *
     * @return string
     */
    private function buildDeleteLine(string $tableName, int $uid): string
    {
        $connection = $this->connectionPool->getConnectionForTable($tableName);

        return sprintf(
            'DELETE FROM %s WHERE uid = %d;',
            $connection->quoteIdentifier($tableName),
            $uid
        );
    }

    /**
     * Returns SQL INSERT .. UPDATE ON DUPLICATE KEY query.
     *
     * @param string $tableName name of table to insert into
     * @param array  $columnNames
     * @param array  $row
     *
     * @return string
     */
    private function buildInsertUpdateLine(string $tableName, array $columnNames, array $row): string
    {
        $connection = $this->connectionPool->getConnectionForTable($tableName);

        $arUpdateParts = [];

        foreach ($row as $key => $value) {
            if (!is_numeric($value)) {
                $row[$key] = $connection->quote($value);
            }

            // TYPO-2215 - Match the column to its update value
            $arUpdateParts[$key] = $key . ' = VALUES(' . $key . ')';
        }

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s;',
            $connection->quoteIdentifier($tableName),
            implode(', ', $columnNames),
            implode(', ', $row),
            implode(', ', $arUpdateParts)
        );
    }

    /**
     * Erzeugt ein gzip vom Dump File
     *
     * @param string $dumpFile name of dump file to gzip
     *
     * @return bool success
     */
    private function createGZipFile(string $dumpFile): bool
    {
        $strExec = 'gzip ' . escapeshellarg($dumpFile);

        shell_exec($strExec);

        if (!file_exists($dumpFile . '.gz')) {
            $this->addError('Fehler beim Erstellen der gzip Datei aus dem Dump.');
            return false;
        }

        chmod($dumpFile . '.gz', 0666);

        return true;
    }

    /**
     * Generates the menu based on $this->MOD_MENU
     *
     * @throws RouteNotFoundException
     */
    private function createMenu(): void
    {
        /** @var \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);
        $uriBuilder->setRequest($this->request);

        $menu = $this->view
            ->getModuleTemplate()
            ->getDocHeaderComponent()
            ->getMenuRegistry()
            ->makeMenu();

        $menu->setIdentifier('sync');

        foreach ($this->MOD_MENU['function'] as $functionId => $title) {
            $item = $menu
                ->makeMenuItem()
                ->setTitle($title)
                ->setHref(
                    $this->getModuleUrl(
                        [
                            'id' => $this->id,
                            'SET' => [
                                'function' => $functionId,
                            ]
                        ]
                    )
                );

            if ($functionId === $this->getCurrentFunctionId()) {
                $item->setActive(true);
            }

            $menu->addMenuItem($item);
        }

        $this->view
            ->getModuleTemplate()
            ->getDocHeaderComponent()
            ->getMenuRegistry()
            ->addMenu($menu);
    }

    /**
     * Create the panel of buttons for submitting the form or otherwise perform operations.
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws RouteNotFoundException
     */
    private function createButtons(): void
    {
        $buttonBar = $this->view
            ->getModuleTemplate()
            ->getDocHeaderComponent()
            ->getButtonBar();

        // CSH
        $cshButton = $buttonBar
            ->makeHelpButton()
            ->setModuleName($this->moduleName)
            ->setFieldName('');

        $buttonBar->addButton($cshButton);

        if ($this->getBackendUser()->isAdmin()) {
            // Lock
            $this->addButtonBarLockButton();
            $this->addButtonBarAreaLockButtons();
        }

        if ($this->id && is_array($this->pageinfo)) {
            // Shortcut
            $shortcutButton = $buttonBar
                ->makeShortcutButton()
                ->setModuleName($this->moduleName)
                ->setGetVariables(['id', 'edit_record', 'pointer', 'new_unique_uid', 'search_field', 'search_levels', 'showLimit'])
                ->setSetVariables(array_keys($this->MOD_MENU));

            $buttonBar->addButton($shortcutButton);
        }
    }

    /**
     *
     * @throws RouteNotFoundException
     */
    private function addButtonBarAreaLockButtons(): void
    {
        foreach ($this->getArea()->getSystems() as $systemName => $system) {
            if (!empty($system['hide'])) {
                continue;
            }

            $this->addButtonBarAreaLockButton($systemName, $system);
        }
    }

    /**
     * @param string $systemName
     * @param array $system
     *
     * @throws RouteNotFoundException
     */
    private function addButtonBarAreaLockButton(string $systemName, array $system): void
    {
        $buttonBar  = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();
        $lockButton = $buttonBar->makeLinkButton();

        if (is_file($this->dbFolder . $system['directory'] . '/.lock')) {
            $lockButton
                ->setTitle($system['name'])
                ->setHref(
                    $this->getModuleUrl(
                        [
                            'id' => $this->id,
                            'lock' => [
                                $systemName => '0',
                            ],
                        ]
                    )
                )
                ->setIcon(
                    $this->view
                        ->getModuleTemplate()
                        ->getIconFactory()
                        ->getIcon(
                            'actions-lock',
                            Icon::SIZE_SMALL
                        )
                )
                ->setClasses('btn btn-warning');
        } else {
            $lockButton
                ->setTitle($system['name'])
                ->setHref(
                    $this->getModuleUrl(
                        [
                            'id' => $this->id,
                            'lock' => [
                                $systemName => '1',
                            ],
                        ]
                    )
                )
                ->setIcon(
                    $this->view
                        ->getModuleTemplate()
                        ->getIconFactory()
                        ->getIcon(
                            'actions-unlock',
                            Icon::SIZE_SMALL
                        )
                );
        }

        $lockButton->setShowLabelText(true);

        $buttonBar->addButton($lockButton);
    }

    /**
     * @return void
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws RouteNotFoundException
     */
    private function addButtonBarLockButton(): void
    {
        $buttonBar  = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();
        $lockButton = $buttonBar->makeLinkButton();

        /** @var SyncLock $syncLock */
        $syncLock = $this->objectManager->get(SyncLock::class);

        if ($syncLock->isLocked()) {
            $lockButton
                ->setTitle('Unlock sync module')
                ->setHref(
                    $this->getModuleUrl(
                        [
                            'id' => $this->id,
                            'data' => [
                                'lock' => '0',
                            ],
                        ]
                    )
                )
                ->setIcon(
                    $this->view
                        ->getModuleTemplate()
                        ->getIconFactory()
                        ->getIcon('actions-lock', Icon::SIZE_SMALL)
                )
                ->setClasses('btn-warning');
        } else {
            $lockButton
                ->setTitle('Lock sync module')
                ->setHref(
                    $this->getModuleUrl(
                        [
                            'id' => $this->id,
                            'data' => [
                                'lock' => '1',
                            ],
                        ]
                    )
                )
                ->setIcon(
                    $this->view
                        ->getModuleTemplate()
                        ->getIconFactory()
                        ->getIcon('actions-unlock', Icon::SIZE_SMALL)
                );
        }

        $buttonBar->addButton($lockButton, ButtonBar::BUTTON_POSITION_LEFT, 0);
    }

    /**
     * Adds error message to message queue.
     *
     * @param string $message error message
     */
    public function addError(string $message): void
    {
        $this->addMessage($message, FlashMessage::ERROR);
    }

    /**
     * Adds error message to message queue.
     *
     * @param string $message success message
     */
    public function addSuccess(string $message): void
    {
        $this->addMessage($message, FlashMessage::OK);
    }

    /**
     * Adds error message to message queue.
     *
     * @param string $message info message
     */
    public function addInfo(string $message): void
    {
        $this->addMessage($message);
    }

    /**
     * Adds error message to message queue. Message types are defined as class constants self::STYLE_*.
     *
     * @param string $message The message
     * @param int    $type    The message type
     */
    public function addMessage(string $message, int $type = FlashMessage::INFO): void
    {
        /** @var FlashMessage $flashMessage */
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class, $message, '', $type, true
        );

        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->addMessage($flashMessage);
    }

    /**
     * Remove entries not needed for the sync.
     *
     * @param array $lines lines with data to sync
     *
     * @return array
     */
    private function removeNotSyncableEntries(array $lines): array
    {
        $result = $lines;

        foreach ($lines as $table => $arStatements) {
            foreach ($arStatements as $uid => $strStatement) {
                if (strpos($table, '_mm') !== false) {
                    continue;
                }

                if (!$this->isElementSyncable($table, $uid)) {
                    unset($result[$table][$uid]);
                }
            }
        }
        return $result;
    }

    /**
     * Sets time of last dump/sync for this element.
     *
     * @param string $table The table, the elements belongs to
     * @param int $uid The uid of the element
     *
     * @throws \Doctrine\DBAL\Exception
     */
    private function setLastDumpTimeForElement(string $table, int $uid): void
    {
        $nTime          = time();
        $nUserId        = (int) $this->getBackendUser()->user['uid'];
        $strUpdateField = ($this->getForcedFullSync()) ? 'full' : 'incr';

        $connection = $this->connectionPool
            ->getConnectionForTable('tx_nrsync_syncstat');

        $connection->executeStatement(
            sprintf(
                'INSERT INTO tx_nrsync_syncstat (tab, %s, cruser_id, uid_foreign)'
                . ' VALUES (%s, %s, %s, %s)'
                . ' ON DUPLICATE KEY UPDATE cruser_id = %s, %s = %s',
                $strUpdateField,
                $connection->quote($table),
                $connection->quote($nTime),
                $connection->quote($nUserId),
                $connection->quote($uid),
                $connection->quote($nUserId),
                $strUpdateField,
                $connection->quote($nTime)
            )
        );
    }

    /**
     * Fetches synchronisation statistics for an element from database.
     *
     * @param string $table The table, the elements belongs to
     * @param int    $uid   The uid of the element
     *
     * @return array|false Synchronisation statistics or FALSE if statistics don't exist
     */
    private function getSyncStatsForElement(string $table, int $uid)
    {
        $queryBuilder = $this->getQueryBuilderForTable($table);

        return $queryBuilder
            ->select('*')
            ->from('tx_nrsync_syncstat')
            ->where(
                $queryBuilder->expr()->eq('tab', $queryBuilder->quote($table)),
                $queryBuilder->expr()->eq('uid_foreign', $uid)
            )
            ->execute()
            ->fetchAssociative();
    }

    /**
     * Returns time stamp of this element.
     *
     * @param string $table The table, the elements belongs to
     * @param int    $uid   The uid of the element
     *
     * @return int
     */
    private function getTimestampOfElement(string $table, int $uid): int
    {
        $queryBuilder = $this->getQueryBuilderForTable($table);

        $arRow = $queryBuilder
            ->select('tstamp')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('uid', $uid)
            )
            ->execute()
            ->fetchAssociative();

        return (int) $arRow['tstamp'];
    }

    /**
     * Clean up statements and prepare dump file.
     *
     * @param array $deleteLines Delete statements
     * @param array $insertLines Insert statements
     * @param resource $fpDumpFile Dump file
     *
     * @throws \Doctrine\DBAL\Exception
     */
    private function prepareDump(array $deleteLines, array $insertLines, $fpDumpFile): void
    {
        if (!$this->getForcedFullSync()) {
            $deleteLines = $this->removeNotSyncableEntries($deleteLines);
            $insertLines = $this->removeNotSyncableEntries($insertLines);
        }

        // Remove Deletes which has a corresponding Insert statement
        $this->diffDeleteLinesAgainstInsertLines(
            $deleteLines,
            $insertLines
        );

        // Remove all DELETE Lines which already has been put to file
        $this->clearDuplicateLines(
            self::STATEMENT_TYPE_DELETE,
            $deleteLines
        );

        // Remove all INSERT Lines which already has been put to file
        $this->clearDuplicateLines(
            self::STATEMENT_TYPE_INSERT,
            $insertLines
        );

        $this->writeToDumpFile($deleteLines, $insertLines, $fpDumpFile);
        $this->writeStats($insertLines);
    }

    /**
     * Write stats for the sync.
     *
     * @param array $insertLines insert array ofstatements for elements to sync
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\Exception
     */
    private function writeStats(array $insertLines): void
    {
        foreach ($insertLines as $table => $insertStatements) {
            if (strpos($table, '_mm') !== false) {
                continue;
            }

            foreach ($insertStatements as $uid => $statement) {
                $this->setLastDumpTimeForElement($table, $uid);
            }
        }
    }

    /**
     * Return true if a full sync should be forced.
     *
     * @return bool
     */
    private function getForcedFullSync(): bool
    {
        return isset($_POST['data']['force_full_sync'])
            && !empty($_POST['data']['force_full_sync']);
    }

    /**
     * Return true if an element, given by table name and uid is syncable.
     *
     * @param string $table The table, the elements belongs to
     * @param int    $uid   The uid of the element
     *
     * @return bool
     */
    private function isElementSyncable(string $table, int $uid): bool
    {
        if (strpos($table, '_mm') !== false) {
            return true;
        }

        $syncStats = $this->getSyncStatsForElement($table, $uid);
        $timeStamp = $this->getTimestampOfElement($table, $uid);

        if (!$timeStamp) {
            return false;
        }

        if (($syncStats !== false)
            && isset($syncStats['full'])
            && (((int) $syncStats['full']) > $timeStamp)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Adds information about full or inc sync to sync file
     *
     * @param string $dumpFile the name of the file
     *
     * @return string
     */
    private function addInformationToSyncfileName(string $dumpFile): string
    {
        $bIsFullSync = !empty($_POST['data']['force_full_sync']);
        $strPrefix = 'inc_';
        if ($bIsFullSync) {
            $strPrefix = 'full_';
        }
        return $strPrefix . $dumpFile;
    }

    /**
     * @return BackendUserAuthentication
     */
    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @param array $parameters An array of parameters
     *
     * @return mixed
     *
     * @throws RouteNotFoundException
     */
    private function getModuleUrl(array $parameters = [])
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        return $uriBuilder->buildUriFromRoute($this->moduleName, $parameters);
    }
}
