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
use Netresearch\Sync\Module\BaseModule;
use Netresearch\Sync\Module\FalModule;
use Netresearch\Sync\Module\StateModule;
use Netresearch\Sync\SyncList;
use Netresearch\Sync\SyncListManager;
use Netresearch\Sync\SyncLock;
use Netresearch\Sync\SyncStats;
use Netresearch\Sync\Table;
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
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;

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
 * @company   Netresearch GmbH & Co.KG <info@netresearch.de>
 * @copyright 2004-2012 Netresearch GmbH & Co.KG (ma@netresearch.de)
 * @license   https://www.gnu.org/licenses/agpl AGPL v3
 * @link      http://www.netresearch.de
 */
class SyncModuleController extends ActionController
{
    public const FUNC_SINGLE_PAGE = 46;

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
     * @var FlashMessageService
     */
    private $flashMessageService;

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
    public $strClearCacheUrl = '?eID=nr_sync&task=clearCache&data=%s&v8=true';

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
     * Menu functions.
     *
     * @var array
     */
    protected $functions = [
        0 => BaseModule::class,
        31 => [
            'name'         => 'Domain Records',
            'tables'       => [
                'sys_domain',
            ],
            'dumpFileName' => 'sys_domain.sql',
        ],
        46 => [
            'name'         => 'Single pages with content',
            'tables'       => [
                'pages',
                'tt_content',
                'sys_template',
                'sys_file_reference',
            ],
            'dumpFileName' => 'partly-pages.sql',
        ],
        8 => FalModule::class,
        9 => [
            'name'         => 'FE groups',
            'type'         => 'sync_fe_groups',
            'tables'       => [
                'fe_groups',
            ],
            'dumpFileName' => 'fe_groups.sql',
        ],
        35 => AssetModule::class,
        10 => [
            'name'         => 'BE users and groups',
            'type'         => 'sync_be_groups',
            'tables'       => [
                'be_users',
                'be_groups',
            ],
            'dumpFileName' => 'be_users_groups.sql',
            'accessLevel'  => 100,
        ],
        17 => StateModule::class,
        40 => [
            'name'         => 'Scheduler',
            'tables'       => [
                'tx_scheduler_task',
            ],
            'dumpFileName' => 'scheduler.sql',
            'accessLevel'  => 100,
        ],
        20 => [
            'name'         => 'TextDB',
            'tables'       => [
                'tx_aidatextdb_component',
                'tx_aidatextdb_placeholder',
                'tx_aidatextdb_textmodule',
                'tx_aidatextdb_type',
                'tx_aidatextdb_environment',
            ],
            'dumpFileName' => 'text-db.sql',
            'accessLevel'  => 100,
        ],
    ];

    /**
     * @var BaseModule
     */
    protected $function;

    /**
     * @var SyncListManager
     */
    private $syncListManager;

    /**
     * @var Urls;
     */
    private $urlGenerator;

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
     * @param FlashMessageService $flashMessageService
     */
    public function __construct(
        FlashMessageService $flashMessageService
    ) {
        $this->flashMessageService = $flashMessageService;

//        $this->view->moduleTemplate = $this->getObjectManager()->get(ModuleTemplate::class);
        $this->urlGenerator   = $this->getObjectManager()->get(Urls::class);
//        $this->getLanguageService()->includeLLFile('EXT:nr_sync/Resources/Private/Language/locallang.xlf');

        $this->MCONF = [
            'name' => $this->moduleName,
        ];

        $this->id = (int) GeneralUtility::_GP('id');
        $this->CMD = GeneralUtility::_GP('CMD');

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
     * @throws \RuntimeException
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
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->strTempFolder));
        }

        // https://github.com/kalessil/phpinspectionsea/blob/master/docs/probable-bugs.md#mkdir-race-condition

        if (!file_exists($this->strUrlFolder)
            && !is_dir($this->strUrlFolder)
            && !mkdir($this->strUrlFolder, $this->nFolderRights, true)
            && !is_dir($this->strUrlFolder)
        ) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->strUrlFolder));
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
        if (\is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['nr_sync/mod1/index.php']['hookClass'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['nr_sync/mod1/index.php']['hookClass'] as $id => $hookObject) {
                if ($hookObject !== null) {
                    $this->functions[$id] = $hookObject;
                    $this->hookObjects[$id] = $hookObject;
                }
            }
        }

        foreach ($this->functions as $functionKey => $function) {
            $function = $this->getFunctionObject($functionKey);

            if ($accessLevel >= $function->getAccessLevel()) {
                $this->MOD_MENU['function'][$functionKey] = $function->getName();
            }
        }

        natcasesort($this->MOD_MENU['function']);

        $this->MOD_MENU['function'] = [ '0' => 'Please select' ] + $this->MOD_MENU['function'];

        $this->MOD_SETTINGS = BackendUtility::getModuleData(
            $this->MOD_MENU,
            GeneralUtility::_GP('SET'),
            $this->MCONF['name'],
            '',
            '',
            ''
        );
    }

    /**
     * Returns a TYPO3 QueryBuilder instance for a given table, without any restriction.
     *
     * @param string $tableName The table name
     *
     * @return QueryBuilder
     *
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function getQueryBuilderForTable(string $tableName): QueryBuilder
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = $this->getObjectManager()->get(ConnectionPool::class);

        $queryBuilder = $connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    /**
     * @param int $functionKey
     *
     * @return BaseModule
     */
    private function getFunctionObject(int $functionKey): BaseModule
    {
        /** @var BaseModule $function */
        if (\is_string($this->functions[$functionKey])) {
            $function = GeneralUtility::makeInstance($this->functions[$functionKey]);
        } else {
            $function = GeneralUtility::makeInstance(
                BaseModule::class,
                $this->functions[$functionKey]
            );
        }

        return $function;
    }

    /**
     * Injects the request object for the current request or subrequest
     * Simply calls main() and init() and outputs the content
     */
    public function mainAction(): void
    {
        $GLOBALS['SOBE'] = $this;

//        $this->init();
        $this->main();

//        $this->view = $this->getFluidTemplateObject('nrsync', 'nrsync');

        $this->view->assign('moduleName', $this->getModuleUrl());
        $this->view->assign('id', $this->id);
//        $this->view->assign('functionMenuModuleContent', $this->content);

        // Setting up the buttons and markers for document header
//        $this->getButtons();
//        $this->generateMenu();

//        $this->view->getModuleTemplate()->setContent(
//            $this->content
//            . '</form>'
//        );

//        $response->getBody()->write($this->view->getModuleTemplate()->renderContent());
//
//        return $response;
    }

//    /**
//     * returns a new standalone view, shorthand function
//     *
//     * @param string $extensionName
//     * @param string $controllerExtensionName
//     * @param string $templateName
//     * @return StandaloneView
//     */
//    protected function getFluidTemplateObject($extensionName, $controllerExtensionName, $templateName = 'Main')
//    {
//        /** @var StandaloneView $view */
//        $view = $this->getObjectManager()->get(StandaloneView::class);
//        $view->setLayoutRootPaths([GeneralUtility::getFileAbsFileName('EXT:' . $extensionName . '/Resources/Private/Layouts')]);
//        $view->setPartialRootPaths([GeneralUtility::getFileAbsFileName('EXT:' . $extensionName . '/Resources/Private/Partials')]);
//        $view->setTemplateRootPaths([GeneralUtility::getFileAbsFileName('EXT:' . $extensionName . '/Resources/Private/Templates')]);
//
//        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName('EXT:' . $extensionName . '/Resources/Private/Templates/' . $templateName . '.html'));
//
//        $view->getRequest()->setControllerExtensionName($controllerExtensionName);
//
//        return $view;
//    }

    /**
     * Tests if given tables holds data on given page id.
     * Returns true if "pages" is one of the tables to look for without checking
     * if page exists.
     *
     * @param int   $nId    The page id to look for
     * @param array $tables The tables this task manages
     *
     * @return bool True if data exists otherwise false.
     */
    protected function pageContainsData(int $nId, array $tables = null): bool
    {
        global $TCA;

        if ($tables === null) {
            return false;
        }

        if (\in_array('pages', $tables, true)) {
            return true;
        }

        foreach ($tables as $tableName) {
            if (isset($TCA[$tableName])) {
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
     * @param string $strName  Name of this page selection (Depends on task).
     * @param array  $tables Tables this task manages.
     *
     * @return void
     */
    protected function showPageSelection(string $strName, array $tables): void
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
        $recursion = (int) $this->getBackendUser()->getSessionData('nr_sync_synclist_levelmax' . $strName);

        if (isset($_POST['data']['rekursion'])) {
            $recursion = (int) $_POST['data']['levelmax'];

            $this->getBackendUser()->setAndSaveSessionData('nr_sync_synclist_levelmax' . $strName, $recursion);
        }

        if ($recursion < 1) {
            $recursion = 1;
        }

        $arCount = [];

        $this->getSubpagesAndCount(
            $this->id,
            $arCount,
            0,
            $recursion,
            $this->getArea()->getNotDocType(),
            $this->getArea()->getDocType(),
            $tables
        );

        $strTitle = $this->getArea()->getName() . ' - ' . $record['uid'] . ' - ' . $record['title'];

        if (((int) $record['doktype']) === 4) {
            $strTitle .= ' - LINK';
        }

        $this->view->assign('pageValid', true);
        $this->view->assign('title', $strTitle);
        $this->view->assign('arCount', $arCount);
        $this->view->assign('record', $record);

//        $strPreOutput .= '<div class="form-section">';
//        $strPreOutput .= '<input type="hidden" name="data[pageID]" value="' . $this->id . '">';
//        $strPreOutput .= '<input type="hidden" name="data[count]" value="' . $arCount['count'] . '">';
//        $strPreOutput .= '<input type="hidden" name="data[deleted]" value="' . $arCount['deleted'] . '">';
//        $strPreOutput .= '<input type="hidden" name="data[noaccess]" value="' . $arCount['noaccess'] . '">';
//        $strPreOutput .= '<input type="hidden" name="data[areaID]" value="' . $this->getArea()->getId() . '">';

//        $strPreOutput .= '<h3>' . $strTitle . '</h3>';
//        $strPreOutput .= '<div class="form-group">';
        if ($this->pageContainsData($this->id, $tables)) {
            $this->view->assign('pageContainsData', true);
//            $strPreOutput .= '<div class="checkbox">';
//            $strPreOutput .= '<label for="data_type_alone">'
//                . '<input type="radio" name="data[type]" value="alone" id="data_type_alone">'
//                . ' Only selected page</label>';
//            $strPreOutput .= '</div>';
            $bShowButton = true;
        }

        if ($arCount['count'] > 0) {
//            $strPreOutput .= '<div class="checkbox">';
//            $strPreOutput .= '<label for="data_type_tree">'
//                . '<input type="radio" name="data[type]" value="tree" id="data_type_tree">'
//                . ' Selected page and all ' . $arCount['count']
//                . ' sub pages</label> <small>(thereof are '
//                . $arCount['deleted'] . ' deleted, '
//                . $arCount['noaccess'] . ' are inaccessible and '
//                . $arCount['falses'] . ' have wrong document type)</small>';
//            $strPreOutput .= '</div>';
            $bShowButton = true;

            if ($arCount['other_area'] > 0) {
//                $strPreOutput .= '<br><b>There are restricted sub areas which are excluded from sync.</b>';
            }
        }

        $this->view->assign('bShowButton', $bShowButton);

//        $strPreOutput .= '</div>';

        if ($bShowButton) {
            $this->view->assign('iconFactory', $this->getIconFactory());
            $this->view->assign('recursion', $recursion);


//            $strPreOutput .= '<div class="form-group">';
//            $strPreOutput .= '<div class="row">';
//
//            $strPreOutput .= '<div class="form-group col-xs-6">';
//            $strPreOutput .= '<button class="btn btn-default" type="submit" name="data[add]" value="Add to sync list">';
//            $strPreOutput .= $this->getIconFactory()->getIcon('actions-add', Icon::SIZE_SMALL)->render();
//            $strPreOutput .= 'Add to sync list';
//            $strPreOutput .= '</button>
//                </div>';

//            $strPreOutput .= '<div class="form-group col-xs-1">
//            <input class="form-control" type="number" name="data[levelmax]" value="'
//                . $recursion . '">'
//                . ' </div>
//            <div class="form-group col-xs-4 form">
//            <input class="btn btn-default" type="submit" name="data[rekursion]" value="set recursion depth">
//            </div>
//            </div>';
//
//            $strPreOutput .= '</div>';
        } else {
            $this->addError(
                'Bitte wählen Sie eine Seite mit entsprechendem Inhalt aus.'
            );
        }

//        $strPreOutput .= '</div>';

//        return $strPreOutput;
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
     * @param mixed $syncListId
     *
     * @return SyncList
     */
    private function getSyncList($syncListId = null): SyncList
    {
        if ($syncListId === null) {
            $syncListId = $this->MOD_SETTINGS['function'];
        }

        return $this->getSyncListManager()->getSyncList($syncListId);
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
     * Main function of the module. Write the content to $this->content
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
        $syncLock = GeneralUtility::makeInstance(SyncLock::class);

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
                        throw new \RuntimeException(sprintf('Directory "%s" was not created', $systemDirectory));
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

        $this->function = $this->getFunctionObject((int) $this->MOD_SETTINGS['function']);
        $dumpFile       = $this->function->getDumpFileName();

        $this->view->assign('selectedMenuItem', (int) $this->MOD_SETTINGS['function']);
        $this->view->assign('function', $this->function);
        $this->view->assign('id', $this->id);
        $this->view->assign('area', $this->getArea());
        $this->view->assign('dbFolder', $this->dbFolder);

        // Sync single pages/page trees (TYPO3 6.2.x)
        if (((int) $this->MOD_SETTINGS['function']) === self::FUNC_SINGLE_PAGE) {
            $this->showPageSelection(
                $this->MOD_SETTINGS['function'],
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
                    $strDumpFileArea = date('YmdHis_') . $dumpFile;

                    foreach ($syncList->getAsArray() as $areaID => $syncListArea) {
                        /** @var Area $area */
                        $area = GeneralUtility::makeInstance(Area::class, $areaID);

                        $pageIDs = $syncList->getAllPageIDs($areaID);
                        $ret = $this->createShortDump(
                            $pageIDs, $this->function->getTableNames(), $strDumpFileArea,
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
                // RSO: Introduced by TYPO-7071, but seems incomplete, $arContent not defined here!
                //
                //foreach ($this->hookObjects as $hookClass) {
                //    $hookObject = GeneralUtility::makeInstance($hookClass);
                //    if (method_exists($hookObject, 'preProcessSync')) {
                //        $hookObject->preProcessSync($arContent['uid'], $this->function->getTableNames(), $this->getPreSyncParams(), $this->getSyncList(), $this);
                //    }
                //}

                $bSyncResult = $this->createDumpToAreas(
                    $this->function->getTableNames(), $dumpFile
                );

                if ($bSyncResult) {
                    $this->addSuccess(
                        'Sync initiated.'
                    );
                }
            }
        }

        if (empty($bUseSyncList) && !empty($this->function->getTableNames())) {
            /** @var SyncStats $syncStats */
            $syncStats = GeneralUtility::makeInstance(SyncStats::class, $this->function->getTableNames());

            $this->view->assign('tableSyncStats', $syncStats);
            $this->view->assign('showTableSyncStats', true);
        }

        $this->view->assign('bUseSyncList', $bUseSyncList);
        $this->view->assign('syncList', $this->getSyncList());

        if (($bUseSyncList && !$this->getSyncList()->isEmpty())
            || ($bUseSyncList === false && \count($this->function->getTableNames()))
        ) {
            $this->view->assign('showCheckBoxes', true);
        }

        $this->view->assign('moduleRoute', $this->moduleName);
    }

    /**
     * Gibt alle ID's aus einem Pagetree zurück.
     *
     * @param array $arTree The pagetree to get IDs from.
     *
     * @return array
     */
    protected function getPageIDsFromTree(array $arTree): array
    {
        $pageIDs = [];
        foreach ($arTree as $value) {
            // Schauen ob es eine Seite auf dem Ast gibt (kann wegen
            // editierrechten fehlen)
            if (isset($value['page'])) {
                $pageIDs[] = $value['page']['uid'];
            }

            // Schauen ob es unter liegende Seiten gibt
            if (\is_array($value['sub'])) {
                $pageIDs = array_merge(
                    $pageIDs, $this->getPageIDsFromTree($value['sub'])
                );
            }
        }
        return $pageIDs;
    }

    /**
     * Returns the page, its sub-pages and their number for a given page ID,
     * if this page can be edited by the user.
     *
     * @param int        $pid               The page id to count on
     * @param array      &$arCount          Information about the count data
     * @param int        $nLevel            Depth on which we are
     * @param int        $nLevelMax         Maximum depth to search for
     * @param null|array $arDocTypesExclude TYPO3 doc types to exclude
     * @param null|array $arDocTypesOnly    TYPO3 doc types to count only
     * @param null|array $tables            Tables this task manages
     *
     * @return array
     */
    protected function getSubpagesAndCount(
        int $pid,
        array &$arCount,
        int $nLevel = 0,
        int $nLevelMax = 1,
        array $arDocTypesExclude = null,
        array $arDocTypesOnly = null,
        array $tables = null
    ): array {
        $arCountDefault = [
            'count'      => 0,
            'deleted'    => 0,
            'noaccess'   => 0,
            'falses'     => 0,
            'other_area' => 0,
        ];

        if (!\is_array($arCount) || empty($arCount)) {
            $arCount = $arCountDefault;
        }

        $return = [];

        if ($pid < 0 || ($nLevel >= $nLevelMax && $nLevelMax !== 0)) {
            return $return;
        }

        $queryBuilder = $this->getQueryBuilderForTable('pages');

        $result = $queryBuilder->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $pid)
            )
            ->execute();

        while ($arPage = $result->fetchAssociative()) {
            if (\is_array($arDocTypesExclude) && \in_array($arPage['doktype'], $arDocTypesExclude, true)) {
                continue;
            }

            if (isset($this->areas[$arPage['uid']])) {
                $arCount['other_area']++;
                continue;
            }

            if (\count($arDocTypesOnly)
                && !\in_array($arPage['doktype'], $arDocTypesOnly, true)
            ) {
                $arCount['falses']++;
                continue;
            }

            $arSub = $this->getSubpagesAndCount(
                $arPage['uid'], $arCount, $nLevel + 1, $nLevelMax,
                $arDocTypesExclude, $arDocTypesOnly, $tables
            );

            if ($this->getBackendUser()->doesUserHaveAccess($arPage, 2)) {
                $return[] = [
                    'page' => $arPage,
                    'sub'  => $arSub,
                ];
            } else {
                $return[] = [
                    'sub' => $arSub,
                ];
                $arCount['noaccess']++;
            }

            // Die Zaehlung fuer die eigene Seite
            if ($this->pageContainsData($arPage['uid'], $tables)) {
                $arCount['count']++;
                if ($arPage['deleted']) {
                    $arCount['deleted']++;
                }
            }
        }

        return $return;
    }

    /**
     *
     * @param string[] $tables Table names
     * @param string $dumpFile Name of the dump file.
     *
     * @return bool success
     */
    protected function createDumpToAreas(
        array $tables, string $dumpFile
    ): bool
    {
        $filename = date('YmdHis_') . $dumpFile;

        $dumpFile = $this->strTempFolder . sprintf($dumpFile, '');

        if (file_exists($dumpFile) || file_exists($dumpFile . '.gz')
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
                'bForceFullSync'      => !empty($_POST['data']['force_full_sync']),
                'bDeleteObsoleteRows' => !empty($_POST['data']['delete_obsolete_rows']),
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
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->dbFolder . $strPath));
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
     */
    private function createClearCacheFile(string $table, array $arUids): bool
    {
        $arClearCacheData = [];

        // Create data
        foreach ($arUids as $strUid) {
            $arClearCacheData[] = $table . ':' . $strUid;
        }

        $strClearCacheData = implode(',', $arClearCacheData);
        $clearCacheUrl = sprintf($this->strClearCacheUrl, $strClearCacheData);

        $this->urlGenerator->postProcessSync(
            ['arUrlsOnce' => [$clearCacheUrl], 'bProcess' => true, 'bSyncResult' => true],
            $this
        );

        return true;
    }



    /**
     * Baut Speciellen Dump zusammen, der nur die angewählten Pages enthällt.
     * Es werden nur Pages gedumpt, zu denen der Redakteur auch Zugriff hat.
     *
     * @param array  $pageIDs   List if page IDs to dump
     * @param array  $tables    List of tables to dump
     * @param string $dumpFile Name of target dump file
     * @param array  $arPath
     *
     * @return bool success
     */
    protected function createShortDump(
        array $pageIDs, array $tables, string $dumpFile, array $arPath
    ): bool {
        if (!\is_array($pageIDs) || \count($pageIDs) <= 0) {
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
    private function openTempDumpFile($strFileName, array $arDirectories)
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
     * @param string  $dumpFile Name of the dump file.
     * @param array   $arDirectories The directories to copy files into.
     * @param bool $bZipFile The directories to copy files into.
     *
     * @return void
     * @throws Exception If file can't be zipped or copied.
     */
    private function finalizeDumpFile($dumpFile, array $arDirectories, $bZipFile): void
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
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $strTargetDir));
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
     * @param array    $pageIDs    Page ids to dump.
     * @param string   $tableName Name of table to dump from.
     * @param resource $fpDumpFile   File pointer to the SQL dump file.
     * @param bool  $bContentIDs  True to interpret pageIDs as content IDs.
     *
     * @return void
     * @throws Exception
     */
    private function dumpTableByPageIDs(
        array $pageIDs, string $tableName, $fpDumpFile, bool $bContentIDs = false
    ): void {
        if (substr($tableName, -3) === '_mm') {
            throw new Exception(
                'MM Tabellen wie: ' . $tableName . ' werden nicht mehr unterstützt.'
            );
        }

        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $this->nDumpTableRecursion++;
        $deleteLines = [];
        $insertLines = [];

        $arColumns = $connectionPool->getConnectionForTable($tableName)
            ->getSchemaManager()
            ->listTableColumns($tableName);

        $arColumnNames = [];
        foreach ($arColumns as $column) {
            $arColumnNames[] = $column->getName();
        }

        $queryBuilder = $this->getQueryBuilderForTable($tableName);

        // In pages und pages_language_overlay entspricht die pageID der uid
        // pid ist ja der Parent (Elternelement) ... so mehr oder weniger *lol*
        if ($tableName === 'pages' || $bContentIDs) {
            $strWhere = $queryBuilder->expr()->in('uid', $pageIDs);
        } else {
            $strWhere = $queryBuilder->expr()->in('pid', $pageIDs);
        }

        $refTableContent = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->where($strWhere)
            ->execute();

        if ($refTableContent) {
            while ($arContent = $refTableContent->fetchAssociative()) {
                foreach ($this->hookObjects as $hookClass) {
                    $hookObject = GeneralUtility::makeInstance($hookClass);
                    if (method_exists($hookObject, 'preProcessSync')) {
                        $hookObject->preProcessSync($arContent['uid'], $tableName, $this->getPreSyncParams(), $this->getSyncList(), $this);
                    }
                }
                $deleteLines[$tableName][$arContent['uid']]
                    = $this->buildDeleteLine($tableName, $arContent['uid']);
                $insertLines[$tableName][$arContent['uid']]
                    = $this->buildInsertUpdateLine($tableName, $arColumnNames, $arContent);

                $this->writeMMReferences(
                    $tableName, $arContent, $fpDumpFile
                );

                if (\count($deleteLines) > 50) {
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
            'function' => $this->MOD_SETTINGS['function'],
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
        $table = new Table($tableName, 'dummy');

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
     * @param array $arContent The database row to find MM References.
     * @param resource $fpDumpFile File pointer to the SQL dump file.
     *
     * @return void
     */
    protected function writeMMReferences(
        $strRefTableName, array $arContent, $fpDumpFile
    ): void {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $deleteLines = [];
        $insertLines = [];

        $this->arReferenceTables = [];
        $this->addMMReferenceTables($strRefTableName);

        foreach ($this->arReferenceTables as $strMMTableName => $arTableFields) {
            $arColumns = $connectionPool
                ->getConnectionForTable($strMMTableName)
                ->getSchemaManager()
                ->listTableColumns($strMMTableName);

            $arColumnNames = [];
            foreach ($arColumns as $column) {
                $arColumnNames[] = $column->getName();
            }

            foreach ($arTableFields as $arMMConfig) {
                $this->writeMMReference(
                    $strRefTableName,
                    $strMMTableName,
                    $arContent['uid'],
                    $arMMConfig,
                    $arColumnNames,
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
     * @param string   $strRefTableName Table which we get the references from.
     * @param string   $tableName       Table to get MM data from.
     * @param int      $uid             The uid of element which references.
     * @param array    $arMMConfig      The configuration of this MM reference.
     * @param array    $arColumnNames   Table columns
     * @param resource $fpDumpFile      File pointer to the SQL dump file.
     */
    private function writeMMReference(
        string $strRefTableName,
        string $tableName,
        int $uid,
        array $arMMConfig,
        array $arColumnNames,
        $fpDumpFile
    ): void {
        $deleteLines = [];
        $insertLines = [];

        $strFieldName = $arMMConfig['foreign_field'] ?? 'uid_foreign';

        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $connection = $connectionPool->getConnectionForTable($tableName);

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
        $refTableContent = $queryBuilder->select('*')
            ->from($tableName)
            ->where($strWhere)
            ->execute();

//        $deleteLines[$tableName][$strWhere]
        $deleteLines[$tableName][$uid]
            = 'DELETE FROM ' . $connection->quoteIdentifier($tableName) . ' WHERE ' . $strWhere . ';';

        if ($refTableContent) {
            while ($arContent = $refTableContent->fetchAssociative()) {
//                $strContentKey = implode('-', $arContent);

                $insertLines[$tableName][$arContent['uid']]
                    = $this->buildInsertUpdateLine($tableName, $arColumnNames, $arContent);

                $strDamTable = 'sys_file';
                $strDamRefTable = 'sys_file_reference';

                if ($strRefTableName !== $strDamTable
                    && $arMMConfig['MM'] === $strDamRefTable
                    && $arMMConfig['form_type'] === 'user'
                ) {
                    $this->dumpTableByPageIDs(
                        [
                            $arContent['uid_local'],
                        ],
                        $strDamTable,
                        $fpDumpFile,
                        true
                    );
                }
            }
            unset($refTableContent);
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
    protected function addMMReferenceTables($tableName): void
    {
        global $TCA;

        if ( ! isset($TCA[$tableName]['columns'])) {
            return;
        }

        foreach ($TCA[$tableName]['columns'] as $strFieldName => $arColumn) {
            if (isset($arColumn['config']['type'])) {
                if ($arColumn['config']['type'] === 'inline') {
                    $this->addForeignTableToReferences($arColumn);
                } else {
                    $this->addMMTableToReferences($arColumn);
                }
            }
        }
    }



    /**
     * Adds Column config to references table, if a foreign_table reference config
     * like in inline-fields exists.
     *
     * @param array $arColumn Column config to get foreign_table data from.
     *
     * @return void
     */
    protected function addForeignTableToReferences($arColumn): void
    {
        if (isset($arColumn['config']['foreign_table'])) {
            $strForeignTable = $arColumn['config']['foreign_table'];
            $this->arReferenceTables[$strForeignTable][] = $arColumn['config'];
        }
    }



    /**
     * Adds Column config to references table, if a MM reference config exists.
     *
     * @param array $arColumn Column config to get MM data from.
     *
     * @return void
     */
    protected function addMMTableToReferences(array $arColumn): void
    {
        if (isset($arColumn['config']['MM'])) {
            $strMMTableName = $arColumn['config']['MM'];
            $this->arReferenceTables[$strMMTableName][] = $arColumn['config'];
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
    protected function addLinesToLineStorage($strStatementType, array $arSqlLines): void
    {
        foreach ($arSqlLines as $tableName => $lines) {
            if (!\is_array($lines)) {
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
    public function clearDuplicateLines($strStatementType, array &$arSqlLines): void
    {
        foreach ($arSqlLines as $tableName => $lines) {
            foreach ($lines as $strIdentifier => $strStatement) {
                if (!empty($this->arGlobalSqlLineStorage[$strStatementType][$tableName][$strIdentifier])) {
                    unset($arSqlLines[$tableName][$strIdentifier]);
                }
            }
            // unset tablename key if no statement exists anymore
            if (\count($arSqlLines[$tableName]) === 0) {
                unset($arSqlLines[$tableName]);
            }
        }
    }



    /**
     * Writes the data into dump file. Line per line.
     *
     * @param array $deleteLiness The lines with the delete statements.
     *                                        Expected structure:
     *                                        $deleteLiness['table1']['uid1'] = 'STATMENT1'
     *                                        $deleteLiness['table1']['uid2'] = 'STATMENT2'
     *                                        $deleteLiness['table2']['uid2'] = 'STATMENT3'
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
     */
    private function writeToDumpFile(
        array $deleteLiness,
        array $insertLines,
        $fpDumpFile,
        array $arDeleteObsoleteRows = []
    ): void {

        // Keep the current lines in mind
        $this->addLinesToLineStorage(
            self::STATEMENT_TYPE_DELETE,
            $deleteLiness
        );
        // Keep the current lines in mind
        $this->addLinesToLineStorage(
            self::STATEMENT_TYPE_INSERT,
            $insertLines
        );

        // Foreach Table in DeleteArray
        foreach ($deleteLiness as $arDelLines) {
            if (\count($arDelLines)) {
                $strDeleteLines = implode("\n", $arDelLines);
                fwrite($fpDumpFile, $strDeleteLines . "\n\n");
            }
        }

        // do not write the inserts here, we want to add them
        // at the end of the file see $this->writeInsertLines

        if (\count($arDeleteObsoleteRows)) {
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
    protected function writeInsertLines($fpDumpFile): void
    {
        if (!\is_array(
            $this->arGlobalSqlLineStorage[self::STATEMENT_TYPE_INSERT]
        )) {
            return;
        }

        $insertLines
            = $this->arGlobalSqlLineStorage[self::STATEMENT_TYPE_INSERT];
        // Foreach Table in InsertArray
        foreach ($insertLines as $table => $arTableInsLines) {
            if (\count($arTableInsLines)) {
                $strInsertLines
                    = '-- Insert lines for Table: '
                    . $table
                    . "\n";
                $strInsertLines .= implode("\n", $arTableInsLines);
                fwrite($fpDumpFile, $strInsertLines . "\n\n");
            }
        }
    }

    /**
     * Removes all delete statements from $deleteLiness where an insert statement
     * exists in $insertLines.
     *
     * @param array &$deleteLiness referenced array with delete statements
     *                              structure should be
     *                              $deleteLiness['table1']['uid1'] = 'STATMENT1'
     *                              $deleteLiness['table1']['uid2'] = 'STATMENT2'
     *                              $deleteLiness['table2']['uid2'] = 'STATMENT3'
     * @param array &$insertLines referenced array with insert statements
     *                              structure should be
     *                              $deleteLiness['table1']['uid1'] = 'STATMENT1'
     *                              $deleteLiness['table1']['uid2'] = 'STATMENT2'
     *                              $deleteLiness['table2']['uid2'] = 'STATMENT3'
     *
     * @return void
     */
    protected function diffDeleteLinesAgainstInsertLines(
        array &$deleteLiness, array &$insertLines
    ): void
    {
        foreach ($insertLines as $tableName => $arElements) {
            // no modification for arrays with old flat structure
            if (!\is_array($arElements)) {
                return;
            }
            // UNSET each delete line where an insert exists
            foreach ($arElements as $strUid => $strStatement) {
                if (!empty($deleteLiness[$tableName][$strUid])) {
                    unset($deleteLiness[$tableName][$strUid]);
                }
            }

            if (\count($deleteLiness[$tableName]) === 0) {
                unset($deleteLiness[$tableName]);
            }
        }
    }



    /**
     * Returns SQL DELETE query.
     *
     * @param string $tableName name of table to delete from
     * @param int $uid uid of row to delete
     *
     * @return string
     */
    protected function buildDeleteLine($tableName, $uid): string
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $connection = $connectionPool->getConnectionForTable($tableName);

        return 'DELETE FROM '
            . $connection->quoteIdentifier($tableName)
            . ' WHERE uid = ' . (int) $uid . ';';
    }



    /**
     * Returns SQL INSERT .. UPDATE ON DUPLICATE KEY query.
     *
     * @param string $tableName name of table to insert into
     * @param array  $arColumnNames
     * @param array  $arContent
     *
     * @return string
     */
    private function buildInsertUpdateLine(string $tableName, array $arColumnNames, array $arContent): string
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection     = $connectionPool->getConnectionForTable($tableName);

        $arUpdateParts = [];
        foreach ($arContent as $key => $value) {
            if (!is_numeric($value)) {
                $arContent[$key] = $connection->quote($value);
            }
            // TYPO-2215 - Match the column to its update value
            $arUpdateParts[$key] = $key . ' = VALUES(' . $key . ')';
        }

        return 'INSERT INTO '
            . $connection->quoteIdentifier($tableName)
            . ' (' . implode(', ', $arColumnNames) . ') VALUES ('
            . implode(', ', $arContent) . ')' . "\n"
            . ' ON DUPLICATE KEY UPDATE '
            . implode(', ', $arUpdateParts) . ';';
    }



    /**
     * Returns SQL INSERT query.
     *
     * @param string $tableName name of table to insert into
     * @param array  $arTableStructure
     * @param array  $arContent
     *
     * @return string
     */
    protected function buildInsertLine($tableName, $arTableStructure, $arContent): string
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $connection = $connectionPool->getConnectionForTable($tableName);

        foreach ($arContent as $key => $value) {
            if (!is_numeric($value)) {
                $arContent[$key] = $connection->quote($value);
            }
        }

        $arColumnNames = array_keys($arTableStructure);
        return 'REPLACE INTO '
            . $connection->quoteIdentifier($tableName)
            . ' (' . implode(', ', $arColumnNames) . ') VALUES ('
            . implode(', ', $arContent) . ');';
    }



    /**
     * Erzeugt ein gzip vom Dump File
     *
     * @param string $dumpFile name of dump file to gzip
     *
     * @return bool success
     */
    protected function createGZipFile($dumpFile): bool
    {
        $strExec = 'gzip ' . escapeshellarg($dumpFile);

        $ret = shell_exec($strExec);

        if (!file_exists($dumpFile . '.gz')) {
            $this->addError('Fehler beim Erstellen der gzip Datei aus dem Dump.');
            return false;
        }

        chmod($dumpFile . '.gz', 0666);

        return true;
    }

    /**
     * Generates the menu based on $this->MOD_MENU
     */
    private function createMenu(): void
    {
//        $uriBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);

        /** @var \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);
        $uriBuilder->setRequest($this->request);

        $menu = $this->view
            ->getModuleTemplate()
            ->getDocHeaderComponent()
            ->getMenuRegistry()
            ->makeMenu();

        $menu->setIdentifier('sync');

        foreach ($this->MOD_MENU['function'] as $controller => $title) {
            $item = $menu
                ->makeMenuItem()
                ->setTitle($title)
//                ->setTitle($this->getLanguageService()->sL('LLL:EXT:news/Resources/Private/Language/locallang_be.xlf:module.' . $action['label']))
//                ->setHref($uriBuilder->reset()->uriFor($controller, [], 'Administration'))
                ->setHref(
                    $this->getModuleUrl(
                        [
                            'id' => $this->id,
                            'SET' => [
                                'function' => $controller,
                            ]
                        ]
                    )
                );

            if ($controller === (int) $this->MOD_SETTINGS['function']) {
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

        if ($this->id && \is_array($this->pageinfo)) {
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
     */
    private function addButtonBarAreaLockButton(string $systemName, array $system): void
    {
        $buttonBar  = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();
        $lockButton = $buttonBar->makeLinkButton();

        if (is_file($this->dbFolder . $system['directory'] . '/.lock')) {
            $lockButton->setHref(
                $this->getModuleUrl(
                    [
                        'id' => $this->id,
                        'lock' => [
                            $systemName => '0',
                        ],
                    ]
                )
            );

            $lockButton->setTitle($system['name'])
                ->setIcon($this->getIconFactory()->getIcon('actions-lock', Icon::SIZE_SMALL))
                ->setClasses('btn btn-warning');
        } else {
            $lockButton->setHref(
                $this->getModuleUrl(
                    [
                        'id' => $this->id,
                        'lock' => [
                            $systemName => '1',
                        ],
                    ]
                )
            );

            $lockButton->setTitle($system['name'])
                ->setIcon($this->getIconFactory()->getIcon('actions-unlock', Icon::SIZE_SMALL));
        }

        $lockButton->setShowLabelText(true);

        $buttonBar->addButton($lockButton);
    }

    /**
     * @return void
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function addButtonBarLockButton(): void
    {
        $buttonBar  = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();
        $lockButton = $buttonBar->makeLinkButton();

        /** @var SyncLock $syncLock */
        $syncLock = GeneralUtility::makeInstance(SyncLock::class);

        if ($syncLock->isLocked()) {
            $lockButton->setHref(
                $this->getModuleUrl(
                    [
                        'id' => $this->id,
                        'data' => [
                            'lock' => '0',
                        ],
                    ]
                )
            );
            $lockButton->setTitle('Unlock sync module');
            $lockButton->setIcon($this->getIconFactory()->getIcon('actions-lock', Icon::SIZE_SMALL));
            $lockButton->setClasses('btn-warning');
        } else {
            $lockButton->setHref(
                $this->getModuleUrl(
                    [
                        'id' => $this->id,
                        'data' => [
                            'lock' => '1',
                        ],
                    ]
                )
            );
            $lockButton->setTitle('Lock sync module');
            $lockButton->setIcon($this->getIconFactory()->getIcon('actions-unlock', Icon::SIZE_SMALL));
        }

        $buttonBar->addButton($lockButton, ButtonBar::BUTTON_POSITION_LEFT, 0);
    }

    /**
     * @return ObjectManager
     */
    private function getObjectManager(): ObjectManager
    {
        /** @var ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        return $objectManager;
    }

    /**
     * @return SyncListManager
     */
    private function getSyncListManager(): SyncListManager
    {
        if ($this->syncListManager === null) {
            $this->syncListManager = GeneralUtility::makeInstance(SyncListManager::class);
        }

        return $this->syncListManager;
    }

    /**
     * @return IconFactory
     */
    private function getIconFactory(): IconFactory
    {
        if ($this->iconFactory === null) {
            $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        }

        return $this->iconFactory;
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
        $this->addMessage($message, FlashMessage::INFO);
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
    protected function removeNotSyncableEntries(array $lines): array
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
     * @param int    $uid   The uid of the element
     */
    protected function setLastDumpTimeForElement(string $table, int $uid): void
    {
//        if (strpos($uid, '-')) {
//            // CRAP - we get something like: 47-18527-0-0-0--0-0-0-0-0-0-1503315964-1500542276-…
//            // happens in writeMMReference() before createupdateinsertline()
//            // take second number as ID:
//            $uid = explode('-', $uid)[1];
//        }

        $nTime          = time();
        $nUserId        = (int) $this->getBackendUser()->user['uid'];
        $strUpdateField = ($this->getForcedFullSync()) ? 'full' : 'incr';

        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_nrsync_syncstat');

        $connection->executeStatement(
            'INSERT INTO tx_nrsync_syncstat'
            . ' (tab, ' . $strUpdateField . ', cruser_id, uid_foreign) VALUES '
            . ' ('
            . $connection->quote($table)
            . ', ' . $connection->quote($nTime)
            . ', ' . $connection->quote($nUserId)
            . ', ' . $connection->quote($uid) . ')'
            . ' ON DUPLICATE KEY UPDATE'
            . ' cruser_id = ' . $connection->quote($nUserId) . ', '
            . $strUpdateField . ' = ' . $connection->quote($nTime)
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
     * @param array    $deleteLines Delete statements
     * @param array    $insertLines Insert statements
     * @param resource $fpDumpFile  Dump file
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
     * Adds information about full or inc sync to syncfile
     *
     * @param string $dumpFile the name of the file
     *
     * @return string
     */
    protected function addInformationToSyncfileName($dumpFile): string
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
     */
    private function getModuleUrl(array $parameters = [])
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        return $uriBuilder->buildUriFromRoute($this->moduleName, $parameters);
    }
}
