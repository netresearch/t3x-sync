<?php
/**
 * Part of Nr_Sync package.
 * Holds Configuration
 *
 * PHP version 5
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Michael Ablass <ma@netresearch.de>
 * @author     Alexander Opitz <alexander.opitz@netresearch.de>
 * @author     Tobias Hein <tobias.hein@netresearch.de>
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Sync\Controller;

use Netresearch\NrcMksearch\Hooks\Sync;
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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Messaging\Renderer\BootstrapRenderer;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

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
 * PHP version 5
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
class SyncModuleController extends \TYPO3\CMS\Backend\Module\BaseScriptClass
{
    const FUNC_SINGLE_PAGE = 46;

    /**
     * key to use for storing insert statements
     * in $arGlobalSqlLinesStoarage
     */
    const STATEMENT_TYPE_INSERT = 'insert';

    /**
     * key to use for storing delete statements
     * in $arGlobalSqlLinesStoarage
     */
    const STATEMENT_TYPE_DELETE = 'delete';

    var $nDumpTableRecursion = 0;

    /**
     * @var array backend page information
     */
    var $pageinfo;

    /**
     * @var string Where to put DB Dumps (trailing Slash)
     */
    var $strDBFolder = '';

    /**
     * @var string Where to put URL file lists
     */
    public $strUrlFolder = '';

    /**
     * @var string clearCache url format
     */
    public $strClearCacheUrl = '?eID=nr_sync&task=clearCache&data=%s&v8=true';

    /**
     * @var integer Access rights for new folders
     */
    public $nFolderRights = 0777;

    /**
     * @var string Dummy file
     */
    var $strDummyFile = '';

    /**
     * @var string path to temp folder
     */
    protected $strTempFolder = null;

    /**
     * @var int
     */
    protected $nRecursion = 1;

    /**
     * @var array pages to sync
     */
    protected $arPageIds = array();

    /**
     * @var array
     */
    var $arObsoleteRows = array();

    /**
     * @var array
     */
    var $arReferenceTables = array();

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
    protected $arGlobalSqlLineStorage = array();

    /**
     * ModuleTemplate Container
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'web_txnrsyncM1';

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var StandaloneView
     */
    protected $view;

    /**
     * @var Area
     */
    protected $area = null;


    protected $arFunctions = [
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
                'pages_language_overlay',
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
     * SyncModuleController constructor.
     */
    public function __construct()
    {
        $this->moduleTemplate = $this->getObjectManager()->get(ModuleTemplate::class);
        $this->urlGenerator   = $this->getObjectManager()->get(Urls::class);
        $this->getLanguageService()->includeLLFile('EXT:nr_sync/Resources/Private/Language/locallang.xlf');
        $this->MCONF = [
            'name' => $this->moduleName,
        ];
        /*
        $this->cshKey = '_MOD_' . $this->moduleName;
        $this->backendTemplatePath = ExtensionManagementUtility::extPath('nr_sync') . 'Resources/Private/Templates/Backend/SchedulerModule/';
        $this->view = $this->getObjectManager()->get(\TYPO3\CMS\Fluid\View\StandaloneView::class);
        $this->view->getRequest()->setControllerExtensionName('nr_sync');
        $this->view->setPartialRootPaths([ExtensionManagementUtility::extPath('nr_sync') . 'Resources/Private/Partials/Backend/SchedulerModule/']);
        */
        //$this->moduleUri = BackendUtility::getModuleUrl($this->moduleName);

        $pageRenderer = $this->getObjectManager()->get(PageRenderer::class);
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/SplitButtons');
    }



    /**
     * Init sync module.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->initFolders();
    }



    /**
     * Initialize the folders needed while synchronize.
     *
     * @return void
     */
    private function initFolders()
    {
        $strRootPath = $_SERVER['DOCUMENT_ROOT'];
        if (empty($strRootPath)) {
            $strRootPath = substr(PATH_site, 0, -1);
        }
        $this->strDummyFile = $strRootPath . '/db.txt';
        $this->strDBFolder = $strRootPath . '/db/';
        $this->strUrlFolder = $this->strDBFolder . 'url/';
        $this->strTempFolder = $this->strDBFolder . 'tmp/';

        if (!file_exists($this->strTempFolder)) {
            mkdir($this->strTempFolder, $this->nFolderRights, true);
        }
        if (!file_exists($this->strUrlFolder)) {
            mkdir($this->strUrlFolder, $this->nFolderRights, true);
        }
    }

    /**
     * Returns a TYPO3 QueryBuilder instance for a given table, without any restrcition.
     *
     * @param $tableName
     *
     * @return \TYPO3\CMS\Core\Database\Query\QueryBuilder
     */
    private function getQueryBuilderForTable($tableName)
    {
        /**
         * @var ConnectionPool $connectionPool
         */
        $connectionPool = $this->getObjectManager()->get(ConnectionPool::class);

        $queryBuilder = $connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    /**
     * Adds items to the ->MOD_MENU array. Used for the function menu selector.
     *
     * @return void
     */
    function menuConfig()
    {
        $this->MOD_MENU = array('function' => array());

        $nAccessLevel = 50;
        if ($this->getBackendUser()->isAdmin()) {
            $nAccessLevel = 100;
        }

        DebuggerUtility::var_dump(class_exists(Sync::class));

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['nr_sync/mod1/index.php']['hookClass'] as $extKey => $hookClass) {
            GeneralUtility::callUserFunction($hookClass . '->postProcessMenu',$this->MOD_MENU, $this);
        }

        foreach ($this->arFunctions as $functionKey => $function) {
            $function = $this->getFunctionObject($functionKey);
            if ($nAccessLevel >= $function->getAccessLevel()) {
                $this->MOD_MENU['function'][$functionKey] = $function->getName();
            }
        }

        natcasesort($this->MOD_MENU['function']);

        $this->MOD_MENU['function']
            = array('0' => 'Please select') + $this->MOD_MENU['function'];

        parent::menuConfig();
    }



    /**
     * @param int $functionKey
     * @return BaseModule
     */
    protected function getFunctionObject($functionKey)
    {
        /* @var $function BaseModule */
        if (is_string($this->arFunctions[(int) $functionKey])) {
            $function = $this->getObjectManager()->get($this->arFunctions[(int) $functionKey]);
        } else {
            $function = $this->getObjectManager()->get(
                BaseModule::class,
                $this->arFunctions[(int) $functionKey]
            );
        }

        return $function;
    }



    /**
     * Injects the request object for the current request or subrequest
     * Simply calls main() and init() and outputs the content
     *
     * @param ServerRequestInterface $request the current request
     * @param ResponseInterface      $response
     * @return ResponseInterface the response with the content
     */
    public function mainAction(ServerRequestInterface $request, ResponseInterface $response)
    {
        $GLOBALS['SOBE'] = $this;
        $this->init();
        $this->main();

        $this->view = $this->getFluidTemplateObject('nrsync', 'nrsync');
        $this->view->assign('moduleName', BackendUtility::getModuleUrl($this->moduleName));
        $this->view->assign('id', $this->id);
        //$this->view->assign('functionMenuModuleContent', $this->getExtObjContent());
        $this->view->assign('functionMenuModuleContent', $this->content);
        // Setting up the buttons and markers for docheader

        $this->getButtons();
        $this->generateMenu();

        //$this->content .= $this->view->render();

        $this->moduleTemplate->setContent(
            $this->content
            . '</form>'
        );
        $response->getBody()->write($this->moduleTemplate->renderContent());
        return $response;
    }



    /**
     * returns a new standalone view, shorthand function
     *
     * @param string $extensionName
     * @param string $controllerExtensionName
     * @param string $templateName
     * @return StandaloneView
     */
    protected function getFluidTemplateObject($extensionName, $controllerExtensionName, $templateName = 'Main')
    {
        /** @var StandaloneView $view */
        $view = $this->getObjectManager()->get(StandaloneView::class);
        $view->setLayoutRootPaths([GeneralUtility::getFileAbsFileName('EXT:' . $extensionName . '/Resources/Private/Layouts')]);
        $view->setPartialRootPaths([GeneralUtility::getFileAbsFileName('EXT:' . $extensionName . '/Resources/Private/Partials')]);
        $view->setTemplateRootPaths([GeneralUtility::getFileAbsFileName('EXT:' . $extensionName . '/Resources/Private/Templates')]);

        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName('EXT:' . $extensionName . '/Resources/Private/Templates/' . $templateName . '.html'));

        $view->getRequest()->setControllerExtensionName($controllerExtensionName);

        return $view;
    }



    /**
     * Tests if given tables holds data on given page id.
     * Returns true if "pages" is one of the tables to look for without checking
     * if page exists.
     *
     * @param integer $nId      The page id to look for.
     * @param array   $arTables Tables this task manages.
     *
     * @return boolean True if data exists otherwise false.
     */
    protected function pageContainsData($nId, array $arTables = null)
    {
        global $TCA;

        if (null === $arTables) {
            return false;
        } elseif (false !== array_search('pages', $arTables)) {
            return true;
        } else {
            foreach ($arTables as $strTableName) {
                if (isset($TCA[$strTableName])) {
                    $queryBuilder = $this->getQueryBuilderForTable($strTableName);

                    $nCount = $queryBuilder->count('pid')
                        ->from($strTableName)
                        ->where($queryBuilder->expr()->eq('pid', intval($nId)))
                        ->execute()
                        ->fetchColumn(0);

                    if ($nCount > 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }



    /**
     * Shows the page selection, depending on selected id and tables to look at.
     *
     * @param string $strName  Name of this page selection (Depends on task).
     * @param array  $arTables Tables this task manages.
     *
     * @return string HTML Output for the selection box.
     */
    protected function showPageSelection(
        $strName, array $arTables
    ) {
        $strPreOutput = '';

        if ($this->id == 0) {
            $this->addError(
                'Please select a page from the page tree.'
            );
            return $strPreOutput;
        }

        $record = BackendUtility::getRecord('pages', $this->id);

        if (null === $record) {
            $this->addError(
                'Could not load record for selected page: ' . $this->id . '.'
            );

            return $strPreOutput;
        }

        if (false === $this->getArea()->isDocTypeAllowed($record)) {
            $this->addError(
                'Page type not allowed to sync.'
            );

            return $strPreOutput;
        }

        $bShowButton = false;

        $this->nRecursion = (int) $this->getBackendUser()->getSessionData(
            'nr_sync_synclist_levelmax' . $strName
        );
        if (isset($_POST['data']['rekursion'])) {
            $this->nRecursion = (int) $_POST['data']['levelmax'];
            $this->getBackendUser()->setAndSaveSessionData(
                'nr_sync_synclist_levelmax' . $strName, $this->nRecursion
            );
        }
        if ($this->nRecursion < 1) {
            $this->nRecursion = 1;
        }

        $this->getSubpagesAndCount(
            $this->id, $arCount, 0, $this->nRecursion,
            $this->getArea()->getNotDocType(), $this->getArea()->getDocType(),
            $arTables
        );

        $strTitle = $this->getArea()->getName() . ' - ' . $record['uid'] . ' - ' . $record['title'];
        if ($record['doktype'] == 4) {
            $strTitle .= ' - LINK';
        }

        $strPreOutput .= '<div class="form-section">';
        $strPreOutput .= '<input type="hidden" name="data[pageID]" value="' . $this->id . '">';
        $strPreOutput .= '<input type="hidden" name="data[count]" value="' . $arCount['count'] . '">';
        $strPreOutput .= '<input type="hidden" name="data[deleted]" value="' . $arCount['deleted'] . '">';
        $strPreOutput .= '<input type="hidden" name="data[noaccess]" value="' . $arCount['noaccess'] . '">';
        $strPreOutput .= '<input type="hidden" name="data[areaID]" value="' . $this->getArea()->getId() . '">';


        $strPreOutput .= '<h3>' . $strTitle . '</h3>';
        $strPreOutput .= '<div class="form-group">';
        if ($this->pageContainsData($this->id, $arTables)) {
            $strPreOutput .= '<div class="checkbox">';
            $strPreOutput .= '<label for="data_type_alone">'
                . '<input type="radio" name="data[type]" value="alone" id="data_type_alone">'
                . ' Only selected page</label>';
            $strPreOutput .= '</div>';
            $bShowButton = true;
        }

        if ($arCount['count'] > 0) {
            $strPreOutput .= '<div class="checkbox">';
            $strPreOutput .= '<label for="data_type_tree">'
                . '<input type="radio" name="data[type]" value="tree" id="data_type_tree">'
                . ' Selected page and all ' . $arCount['count']
                . ' sub pages</label> <small>(thereof are '
                . $arCount['deleted'] . ' deleted, '
                . $arCount['noaccess'] . ' are inaccessible and '
                . $arCount['falses'] . ' have wrong document type)</small>';
            $strPreOutput .= '</div>';
            $bShowButton = true;
            if ($arCount['other_area'] > 0) {
                $strPreOutput .= '<br><b>There are restricted sub areas which are excluded from sync.</b>';
            }
        }
        $strPreOutput .= '</div>';

        if (!$bShowButton) {
            $this->addError(
                'Bitte wählen Sie eine Seite mit entsprechendem Inhalt aus.'
            );
        } else {
            $strPreOutput .= '<div class="form-group">';
            $strPreOutput .= '<div class="row">';

            $strPreOutput .= '<div class="form-group col-xs-6">';
            $strPreOutput .= '<button class="btn btn-default" type="submit" name="data[add]" value="Add to sync list">';
            $strPreOutput .= $this->getIconFactory()->getIcon('actions-add', Icon::SIZE_SMALL)->render();
            $strPreOutput .= 'Add to sync list';
            $strPreOutput .= '</button>
                </div>';

            $strPreOutput .= '<div class="form-group col-xs-1">
            <input class="form-control" type="number" name="data[levelmax]" value="'
                . $this->nRecursion . '">'
                . ' </div>
            <div class="form-group col-xs-4 form">
            <input class="btn btn-default" type="submit" name="data[rekursion]" value="set recursion depth">
            </div>
            </div>';

            $strPreOutput .= '</div>';
        }


        $strPreOutput .= '</div>';

        return $strPreOutput;
    }



    /**
     * Manages adding and deleting of pages/trees to the sync list.
     *
     * @return SyncList
     */
    protected function manageSyncList()
    {
        // ID hinzufügen
        if (isset($_POST['data']['add'])) {
            if (isset($_POST['data']['type'])) {
                $this->getSyncList()->addToSyncList($_POST['data']);
            } else {
                $this->addError(
                    'Bitte wählen Sie aus, wie die Seite vorgemerkt werden soll.'
                );
            }
        }

        // ID entfernen
        if (isset($_POST['data']['delete'])) {
            $this->getSyncList()->deleteFromSyncList($_POST['data']);
        }

        $this->getSyncList()->saveSyncList();

        return $this->getSyncList();
    }



    /**
     * @param mixed $syncListId
     *
     * @return SyncList
     */
    protected function getSyncList($syncListId = null)
    {
        if (null === $syncListId) {
            $syncListId = $this->MOD_SETTINGS['function'];
        }
        return $this->getSyncListManager()->getSyncList($syncListId);
    }



    /**
     * @return Area
     */
    protected function getArea()
    {
        if (null === $this->area) {
            $this->area = $this->getObjectManager()->get(
                Area::class, $this->id
            );
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
     */
    public function main()
    {
        // Access check!
        // The page will show only if there is a valid page and if this page may
        // be viewed by the user
        $this->pageinfo = BackendUtility::readPageAccess(
            $this->id, $this->perms_clause
        );
        if ($this->pageinfo) {
            $this->moduleTemplate->getDocHeaderComponent()->setMetaInformation($this->pageinfo);
        }

        /* @var $syncLock SyncLock */
        $syncLock = $this->getObjectManager()->get(SyncLock::class);

        if ($this->getBackendUser()->isAdmin()) {
            $syncLock->handleLockRequest();
        }

        if ($syncLock->isLocked()) {
            $this->content .= '<div class="alert alert-warning">';
            $this->content .= $syncLock->getLockMessage();
            $this->content .= '</div>';
            return;
        }

        if (isset($_REQUEST['lock'])) {
            foreach ($_REQUEST['lock'] as $systemName => $lockState) {
                $systems = $this->getArea()->getSystems();
                $system = $systems[$systemName];
                $systemDirectory = $this->strDBFolder . $system['directory'];
                $lockFilePath    = $systemDirectory . '/.lock';
                if ($lockState) {
                    if (!is_dir($systemDirectory)) {
                        mkdir($systemDirectory, 0777, true);
                    }
                    $handle = fopen($lockFilePath, 'w');
                    if ($handle) {
                        fclose($handle);
                    }
                    $this->addSuccess('Target ' . $systemName . ' disabled for sync.');
                } else {
                    if (file_exists($lockFilePath)) {
                        unlink($lockFilePath);
                        $this->addSuccess('Target ' . $systemName . ' enabled for sync.');
                    }
                }
            }
        }

        $bUseSyncList = false;

        if (! isset($this->arFunctions[(int) $this->MOD_SETTINGS['function']])) {
            $this->MOD_SETTINGS['function'] = 0;
        }

        $this->function = $this->getFunctionObject($this->MOD_SETTINGS['function']);

        $strDumpFile    = $this->function->getDumpFileName();

        $this->content .= '<h1>Create new sync</h1>';
        $this->content .= '<form action="" method="POST">';

        switch ((int)$this->MOD_SETTINGS['function']) {
            /**
             * Sync einzelner Pages/Pagetrees (TYPO3 6.2.x)
             */
            case self::FUNC_SINGLE_PAGE: {
                $this->content .= $this->showPageSelection(
                    $this->MOD_SETTINGS['function'],
                    $this->function->getTableNames()
                );
                $this->manageSyncList();

                $bUseSyncList = true;
                break;
            }
        }

        $this->function->run($this->getArea());

        if ($this->function->hasError()) {
            $this->addError($this->function->getError());
        }

        $this->content .= $this->function->getContent();

        // sync process
        if (isset($_POST['data']['submit']) && $strDumpFile != '') {
            $strDumpFile = $this->addInformationToSyncfileName($strDumpFile);
            //set_time_limit(480);

            if ($bUseSyncList) {
                $syncList = $this->getSyncList();
                if (! $syncList->isEmpty()) {

                    $strDumpFileArea = date('YmdHis_') . $strDumpFile;

                    foreach ($syncList->getAsArray() as $areaID => $arSynclistArea) {

                        /* @var $area Area */
                        $area = $this->getObjectManager()->get(Area::class, $areaID);

                        $arPageIDs = $syncList->getAllPageIDs($areaID);

                        $ret = $this->createShortDump(
                            $arPageIDs, $this->function->getTableNames(), $strDumpFileArea,
                            $area->getDirectories()
                        );

                        if ($ret
                            && $this->createClearCacheFile('pages', $arPageIDs)
                        ) {
                            if ($area->notifyMaster() == false) {
                                $this->addError('Please re-try in a couple of minutes.');
                                foreach ($area->getDirectories() as $strDirectory) {
                                    @unlink($this->strDBFolder . $strDirectory . '/' . $strDumpFileArea);
                                    @unlink($this->strDBFolder . $strDirectory . '/' . $strDumpFileArea . '.gz');
                                }
                            } else {
                                $this->addSuccess(
                                    'Sync started - should be processed within in next 15 minutes.'
                                );
                                $syncList->emptyArea($areaID);
                            }

                            $syncList->saveSyncList();
                        }
                    }
                }
            } else {
                $bSyncResult = $this->createDumpToAreas(
                    $this->function->getTableNames(), $strDumpFile
                );

                if ($bSyncResult) {
                    $this->addSuccess(
                        'Sync initiated.'
                    );
                }
            }
        }

        $this->content .= '<div class="form-section">';

        if (empty($bUseSyncList) && !empty($this->function->getTableNames())) {
            /* @var $syncStats SyncStats */
            $syncStats = $this->getObjectManager()->get(SyncStats::class, $this->function->getTableNames());
            $syncStats->createTableSyncStats();
            $this->content .= $syncStats->getContent();
        }

        // Syncliste anzeigen
        if ($bUseSyncList) {
            if (! $this->getSyncList()->isEmpty()) {
                $this->content .= $this->getSyncList()->showSyncList();
            }
        }

        if (($bUseSyncList && ! $this->getSyncList()->isEmpty())
            || (false === $bUseSyncList && count($this->function->getTableNames()))
        ) {
            $this->content .= '<div class="form-group">';
            $this->content .= '<div class="checkbox">';
            $this->content .= '<label for="force_full_sync">'
                . '<input type="checkbox" name="data[force_full_sync]" value="1" id="force_full_sync">'
                . 'Force full sync. A full sync of this element will be initiated even if an incremental sync is possible. This should be avoided.'
                . '</label>';
            $this->content .= '</div>';
            $this->content .= '<div class="checkbox">'
                . '<label for="delete_obsolete_rows">'
                . '<input type="checkbox" checked="checked" name="data[delete_obsolete_rows]" value="1" id="delete_obsolete_rows">'
                . 'Delete obsolete Rows on LIVE system. All Rows which are hidden or deleted or where endtime is a date in past will be deleted on live system'
                . '</label>';
            $this->content .= '</div>';
            $this->content .= '</div>';
        }
        $this->content .= '</div>';

        if (!empty($this->MOD_SETTINGS['function'])) {
            $strDisabled  = '';
            if ($bUseSyncList && $this->getSyncList()->isEmpty()) {
                $strDisabled = ' disabled="disabled"';
            }

            $this->content .= '<div class="form-section">';
            $this->content .= '<div class="form-group">';
            $this->content .= '<input class="btn btn-primary" type="Submit" name="data[submit]" value="Create sync" ' . $strDisabled . '>';
            $this->content .= '</div>';
            $this->content .= '</div>';
        }

        $this->showSyncState();
    }



    /**
     * Shows how many files are waiting for sync and how old the oldest file is.
     *
     * @return void
     */
    protected function showSyncState()
    {
        $this->content .= '<h1>Waiting syncs</h1>';

        foreach ($this->getArea()->getSystems() as $systemKey => $system) {
            if (! empty($system['hide'])) {
                continue;
            }

            $this->content .= '<h2>';

            if (is_file($this->strDBFolder . $system['directory'] . '/.lock')) {
                $href =  BackendUtility::getModuleUrl(
                    $this->moduleName,
                    [
                        'lock' => [$systemKey => '0'],
                        'id'   => $this->id,
                    ]
                );
                $icon = $this->getIconFactory()->getIcon('actions-lock', Icon::SIZE_SMALL);
                $this->content .= '<a href="' . $href . '" class="btn btn-warning" title="Sync disabled, click to enable">' . $icon . '</a>';
            } else {
                $href =  BackendUtility::getModuleUrl(
                    $this->moduleName,
                    [
                        'lock' => [$systemKey => '1'],
                        'id'   => $this->id,
                    ]
                );
                $icon = $this->getIconFactory()->getIcon('actions-unlock', Icon::SIZE_SMALL);
                $this->content .= '<a href="' . $href . '" class="btn btn-success" title="Sync enabled, click to disable">' . $icon . '</a>';
            }

            $this->content .= ' Sync target: "' . htmlspecialchars($system['name']) . '"</h2>';


            $arFiles = $this->removeLinksAndFoldersFromFileList(
                glob($this->strDBFolder . $system['directory'] . '/*')
            );

            $nDumpFiles = count($arFiles);
            if ($nDumpFiles < 1) {
                continue;
            }

            $strFiles = '';
            $nSyncSize = 0;

            $strFiles .= '<div class="table-fit">';
            $strFiles .= '<table class="table table-striped table-hover" id="ts-overview">';
            $strFiles .= '<thead>';
            $strFiles .= '<tr><th>File</th><th>Size</th></tr>';
            $strFiles .= '</thead>';
            $strFiles .= '<tbody>';

            foreach ($arFiles as $strFile) {
                $nSize = filesize($strFile);
                $nSyncSize += $nSize;

                $strFiles .= '<tr class="bgColor4">';
                $strFiles .= '<td>';
                $strFiles .= htmlspecialchars(basename($strFile));
                $strFiles .= '</td>';

                $strFiles .= '<td>';
                $strFiles .= number_format($nSize / 1024 / 1024, 2, '.', ',') . ' MiB';
                $strFiles .= '</td>';

                $strFiles .= '</tr>';
            }

            $strFiles .= '</tbody>';
            $strFiles .= '</table>';
            $strFiles .= '</div>';

            $nTime = filemtime(reset($arFiles));
            if ($nTime < time() - 60 * 15) {
                // if oldest file time is older than 15 minutes display this in red
                $type = FlashMessage::ERROR;
            } else {
                $type = FlashMessage::INFO;
            }

            /* @var $message2 FlashMessage */
            $message = $this->getObjectManager()->get(
                FlashMessage::class,
                $nDumpFiles . ' file'
                . ($nDumpFiles == 1 ? '' : 's') . ' waiting for synchronisation'
                . ' (' . number_format($nSyncSize / 1024 / 1024, 2, '.', ',') . ' MiB).'
                . ' Oldest file from ' . date('Y-m-d H:i', $nTime)
                . ' and therefor ' . ceil((time() - $nTime) / 60)
                . ' minutes old.',
                '',
                $type
            );

            /* @var $renderer BootstrapRenderer */
            $renderer = $this->getObjectManager()->get(BootstrapRenderer::class);
            $this->content .= $renderer->render([$message]);

            $this->content .= $strFiles;
        }
    }



    /**
     * Remove links and folders from fileList for displaying SyncStat
     *
     * @param array $arFiles
     *
     * @return array
     */
    protected function removeLinksAndFoldersFromFileList(array $arFiles)
    {
        foreach ($arFiles as $index => $strPath) {
            if (is_link($strPath) || is_dir($strPath)) {
                unset($arFiles[$index]);
            }
        }

        return $arFiles;
    }



    /**
     * Gibt alle ID's aus einem Pagetree zurück.
     *
     * @param array $arTree The pagetree to get IDs from.
     *
     * @return array
     */
    protected function getPageIDsFromTree(array $arTree)
    {
        $arPageIDs = array();
        foreach ($arTree as $value) {
            // Schauen ob es eine Seite auf dem Ast gibt (kann wegen
            // editierrechten fehlen)
            if (isset($value['page'])) {
                array_push($arPageIDs, $value['page']['uid']);
            }

            // Schauen ob es unter liegende Seiten gibt
            if (is_array($value['sub'])) {
                $arPageIDs = array_merge(
                    $arPageIDs, $this->getPageIDsFromTree($value['sub'])
                );
            }
        }
        return $arPageIDs;
    }



    /**
     * Gibt die Seite, deren Unterseiten und ihre Zählung zu einer PageID zurück,
     * wenn sie vom User editierbar ist.
     *
     * @param integer $pid               The page id to count on.
     * @param array   &$arCount          Information about the count data.
     * @param integer $nLevel            Depth on which we are.
     * @param integer $nLevelMax         Maximum depth to search for.
     * @param array   $arDocTypesExclude TYPO3 doc types to exclude.
     * @param array   $arDocTypesOnly    TYPO3 doc types to count only.
     * @param array   $arTables          Tables this task manages.
     *
     * @return array
     */
    protected function getSubpagesAndCount(
        $pid, &$arCount, $nLevel = 0, $nLevelMax = 1, array $arDocTypesExclude = null,
        array $arDocTypesOnly = null, array $arTables = null
    ) {
        $arCountDefault = array(
            'count'      => 0,
            'deleted'    => 0,
            'noaccess'   => 0,
            'falses'     => 0,
            'other_area' => 0,
        );

        if (!is_array($arCount)) {
            $arCount = $arCountDefault;
        }

        $return = array();

        if ($pid < 0 || ($nLevel >= $nLevelMax && $nLevelMax !== 0)) {
            return $return;
        }

        $queryBuilder = $this->getQueryBuilderForTable('pages');

        $result = $queryBuilder->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', intval($pid))
            )
            ->execute();

        while ($arPage = $result->fetch()) {
            if (is_array($arDocTypesExclude) && in_array($arPage['doktype'], $arDocTypesExclude)) {
                continue;
            }

            if (isset($this->areas[$arPage['uid']])) {
                $arCount['other_area']++;
                continue;
            }

            if (count($arDocTypesOnly)
                && !in_array($arPage['doktype'], $arDocTypesOnly)
            ) {
                $arCount['falses']++;
                continue;
            }

            $arSub = $this->getSubpagesAndCount(
                $arPage['uid'], $arCount, $nLevel + 1, $nLevelMax,
                $arDocTypesExclude, $arDocTypesOnly, $arTables
            );

            if ($this->getBackendUser()->doesUserHaveAccess($arPage, 2)) {
                $return[] = array(
                    'page' => $arPage,
                    'sub'  => $arSub,
                );
            } else {
                $return[] = array(
                    'sub' => $arSub,
                );
                $arCount['noaccess']++;
            }

            // Die Zaehlung fuer die eigene Seite
            if ($this->pageContainsData($arPage['uid'], $arTables)) {
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
     * @param string[] $arTables     Table names
     * @param string   $strDumpFile  Name of the dump file.
     *
     * @return boolean success
     */
    protected function createDumpToAreas(
        array $arTables, $strDumpFile
    ) {
        $filename = date('YmdHis_') . $strDumpFile;

        $strDumpFile = $this->strTempFolder . sprintf($strDumpFile, '');

        if (file_exists($strDumpFile) || file_exists($strDumpFile . '.gz')
        ) {
            $this->addError('Die letzte Synchronisationsvorbereitung ist noch'
                . ' nicht abgeschlossen. Bitte versuchen Sie es in 5'
                . ' Minuten noch einmal.');
            return false;
        }

        Table::writeDumps(
            $arTables, $strDumpFile, $arOptions = array(
                'bForceFullSync'
                => !empty($_POST['data']['force_full_sync']),
                'bDeleteObsoleteRows'
                => !empty($_POST['data']['delete_obsolete_rows'])
            )
        );

        if (!file_exists($strDumpFile)) {
            $this->addInfo(
                'No data dumped for further processing. '
                . 'The selected sync process did not produce any data to be '
                . 'synched to the live system.'
            );
            return false;
        }

        if (!$this->createGZipFile($strDumpFile)) {
            $this->addError('Zipfehler: ' . $strDumpFile);
            return false;
        }

        foreach (Area::getMatchingAreas() as $area) {
            foreach ($area->getDirectories() as $strPath) {
                if (!file_exists($this->strDBFolder . $strPath . '/')) {
                    mkdir($this->strDBFolder . $strPath, $this->nFolderRights, true);
                }
                if (!copy(
                    $strDumpFile . '.gz',
                    $this->strDBFolder . $strPath . '/' . $filename
                    . '.gz'
                )) {
                    $this->addError('Konnte ' . $this->strTempFolder
                        . $strDumpFile . '.gz nicht nach '
                        . $this->strDBFolder . $strPath . '/'
                        . $filename . '.gz kopieren.');
                    return false;
                }
                chmod($this->strDBFolder . $strPath . '/' . $filename . '.gz', 0666);
            }
            if (false === $area->notifyMaster()) {
                return false;
            }
        }
        unlink($strDumpFile . '.gz');
        return true;
    }



    /**
     * Generates the file with the content for the clear cache task.
     *
     * @param string   $strTable    Name of the table which cache should be cleared.
     * @param int[]    $arUids      Array with the uids to clear cache.
     *
     * @return boolean True if file was generateable otherwise false.
     */
    private function createClearCacheFile($strTable, array $arUids)
    {
        $arClearCacheData = array();

        // Create data
        foreach ($arUids as $strUid) {
            $arClearCacheData[] = $strTable . ':' . $strUid;
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
     * @param array  $arPageIDs   List if page IDs to dump
     * @param array  $arTables    List of tables to dump
     * @param string $strDumpFile Name of target dump file
     * @param array  $arPath
     *
     * @return boolean success
     */
    protected function createShortDump(
        $arPageIDs, $arTables, $strDumpFile, $arPath
    ) {
        if (!is_array($arPageIDs) || count($arPageIDs) <= 0) {
            $this->addError('Keine Seiten für die Synchronisation vorgemerkt.');
            return false;
        }

        try {
            $fpDumpFile = $this->openTempDumpFile($strDumpFile, $arPath);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        foreach ($arTables as $value) {
            $this->dumpTableByPageIDs($arPageIDs, $value, $fpDumpFile);
        }

        // TYPO-206
        // Append Statement for Delete unused rows in LIVE environment
        $this->writeToDumpFile(
            array(),
            array(),
            $fpDumpFile,
            $this->getDeleteRowStatements()
        );
        // TYPO-2214: write inserts at the end of the file
        $this->writeInsertLines($fpDumpFile);

        try {
            fclose($fpDumpFile);
            $this->finalizeDumpFile($strDumpFile, $arPath, true);
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
            if (file_exists($this->strDBFolder . $strPath . '/' . $strFileName)
                || file_exists($this->strDBFolder . $strPath . '/' . $strFileName . '.gz')
            ) {
                throw new Exception(
                    'Die letzte Synchronisation ist noch nicht abgeschlossen.'
                    . ' Bitte versuchen Sie es in wenigen Minuten noch einmal.'
                );
            }
        }

        $fp = fopen($this->strTempFolder . $strFileName, 'w');

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
     * @param string  $strDumpFile Name of the dump file.
     * @param array   $arDirectories The directories to copy files into.
     * @param boolean $bZipFile The directories to copy files into.
     *
     * @return void
     * @throws Exception If file can't be zipped or copied.
     */
    private function finalizeDumpFile($strDumpFile, array $arDirectories, $bZipFile)
    {
        if ($bZipFile) {
            // Dateien komprimieren
            if (!$this->createGZipFile($this->strTempFolder . $strDumpFile)) {
                throw new Exception('Could not create ZIP file.');
            }
            $strDumpFile = $strDumpFile . '.gz';
        }

        // Dateien an richtige Position kopieren
        foreach ($arDirectories as $strPath) {
            $strTargetDir = $this->strDBFolder . $strPath . '/';
            if (!file_exists($strTargetDir)) {
                mkdir($strTargetDir, $this->nFolderRights, true);
                // TYPO-3713: change folder permissions to nFolderRights due to
                // mask of mkdir could be overwritten by the host
                chmod($strTargetDir, $this->nFolderRights);
            }

            $bCopied = copy(
                $this->strTempFolder . $strDumpFile,
                $strTargetDir . $strDumpFile
            );
            chmod($strTargetDir . $strDumpFile, 0666);
            if (!$bCopied) {
                throw new Exception(
                    'Konnte ' . $this->strTempFolder . $strDumpFile
                    . ' nicht nach '
                    . $strTargetDir . $strDumpFile
                    . ' kopieren.'
                );
            }
        }
        unlink($this->strTempFolder . $strDumpFile);
    }



    /**
     * Erzeugt ein Dump durch Seiten IDs.
     *
     * @param array    $arPageIDs    Page ids to dump.
     * @param string   $strTableName Name of table to dump from.
     * @param resource $fpDumpFile   File pointer to the SQL dump file.
     * @param boolean  $bContentIDs  True to interpret pageIDs as content IDs.
     *
     * @return void
     * @throws Exception
     */
    protected function dumpTableByPageIDs(
        array $arPageIDs, $strTableName, $fpDumpFile, $bContentIDs = false
    ) {
        if (substr($strTableName, -3) == '_mm') {
            throw new Exception(
                'MM Tabellen wie: ' . $strTableName . ' werden nicht mehr unterstützt.'
            );
        }

        /* @var $connectionPool ConnectionPool */
        $connectionPool = $this->getObjectManager()->get(ConnectionPool::class);

        $this->nDumpTableRecursion++;
        $arDeleteLine = array();
        $arInsertLine = array();

        $arColumns = $connectionPool->getConnectionForTable($strTableName)
            ->getSchemaManager()
            ->listTableColumns($strTableName);

        $arColumnNames = [];
        foreach ($arColumns as $column) {
            $arColumnNames[] = $column->getName();
        }

        $queryBuilder = $this->getQueryBuilderForTable($strTableName);

        // In pages und pages_language_overlay entspricht die pageID der uid
        // pid ist ja der Parent (Elternelement) ... so mehr oder weniger *lol*
        if ($strTableName == 'pages' || $bContentIDs) {
            $strWhere = $queryBuilder->expr()->in('uid', $arPageIDs);
        } else {
            $strWhere = $queryBuilder->expr()->in('pid', $arPageIDs);
        }

        $refTableContent = $queryBuilder->select('*')
            ->from($strTableName)
            ->where($strWhere)
            ->execute();

        if ($refTableContent) {
            while ($arContent = $refTableContent->fetch()) {
                $arDeleteLine[$strTableName][$arContent['uid']]
                    = $this->buildDeleteLine($strTableName, $arContent['uid']);
                $arInsertLine[$strTableName][$arContent['uid']]
                    = $this->buildInsertUpdateLine($strTableName, $arColumnNames, $arContent);

                $this->writeMMReferences(
                    $strTableName, $arContent, $fpDumpFile
                );
                if (count($arDeleteLine) > 50) {

                    $this->prepareDump($arDeleteLine, $arInsertLine, $fpDumpFile);
                    $arDeleteLine = array();
                    $arInsertLine = array();
                }
            }
        }

        // TYPO-206: append delete obsolete rows on live
        if (!empty($_POST['data']['delete_obsolete_rows'])) {
            $this->addAsDeleteRowTable($strTableName);
        }

        $this->prepareDump($arDeleteLine, $arInsertLine, $fpDumpFile);

        $this->nDumpTableRecursion--;
    }



    /**
     * Adds the Table and its DeleteObsoleteRows statement to an array
     * if the statement does not exists in the array
     *
     * @param string $strTableName the name of the Table the obsolete rows
     *                             should be added to the $arObsoleteRows array
     *                             for
     *
     * @return void
     */
    public function addAsDeleteRowTable($strTableName)
    {
        $table = new Table($strTableName, 'dummy');
        if (!isset($this->arObsoleteRows[0])) {
            $this->arObsoleteRows[0] = "-- Delete obsolete Rows on live, see: TYPO-206";
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
    public function getDeleteRowStatements()
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
    ) {
        /* @var $connectionPool ConnectionPool */
        $connectionPool = $this->getObjectManager()->get(ConnectionPool::class);

        $arDeleteLine = array();
        $arInsertLine = array();

        $this->arReferenceTables = array();
        $this->addMMReferenceTables($strRefTableName);
        foreach ($this->arReferenceTables as $strMMTableName => $arTableFields) {
            $arColumns = $connectionPool->getConnectionForTable($strMMTableName)
                ->getSchemaManager()
                ->listTableColumns($strMMTableName);

            $arColumnNames = [];
            foreach ($arColumns as $column) {
                $arColumnNames[] = $column->getName();
            }

            foreach ($arTableFields as $arMMConfig) {
                $this->writeMMReference(
                    $strRefTableName, $strMMTableName, $arContent['uid'],
                    $arMMConfig,
                    $arColumnNames, $fpDumpFile
                );
            }
        }
        $this->prepareDump($arDeleteLine, $arInsertLine, $fpDumpFile);
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
     * @param string   $strTableName    Table to get MM data from.
     * @param integer  $uid             The uid of element which references.
     * @param array    $arMMConfig      The configuration of this MM reference.
     * @param array    $arColumnNames   Table columns
     * @param resource $fpDumpFile      File pointer to the SQL dump file.
     *
     * @return void
     */
    protected function writeMMReference(
        $strRefTableName, $strTableName, $uid, array $arMMConfig,
        array $arColumnNames, $fpDumpFile
    )
    {
        $arDeleteLine = array();
        $arInsertLine = array();

        $strFieldName = 'uid_foreign';
        if (isset($arMMConfig['foreign_field'])) {
            $strFieldName = $arMMConfig['foreign_field'];
        }

        /* @var $connectionPool ConnectionPool */
        $connectionPool = $this->getObjectManager()->get(ConnectionPool::class);

        /* @var $connection \TYPO3\CMS\Core\Database\Connection */
        $connection = $connectionPool->getConnectionForTable($strTableName);

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
            ->from($strTableName)
            ->where($strWhere)
            ->execute();

        $arDeleteLine[$strTableName][$strWhere]
            = 'DELETE FROM ' . $connection->quoteIdentifier($strTableName) . ' WHERE ' . $strWhere . ';';

        if ($refTableContent) {
            while ($arContent = $refTableContent->fetch()) {
                $strContentKey = implode('-', $arContent);
                $arInsertLine[$strTableName][$strContentKey] = $this->buildInsertUpdateLine(
                    $strTableName, $arColumnNames, $arContent
                );

                $strDamTable = 'sys_file';
                $strDamRefTable = 'sys_file_reference';

                if ($strRefTableName !== $strDamTable
                    && $arMMConfig['MM'] === $strDamRefTable
                    && $arMMConfig['form_type'] === 'user'
                ) {
                    $this->dumpTableByPageIDs(
                        array($arContent['uid_local']), $strDamTable, $fpDumpFile,
                        true
                    );
                }
            }
            unset($refTableContent);
        }

        $this->prepareDump($arDeleteLine, $arInsertLine, $fpDumpFile);
    }



    /**
     * Finds MM reference tables and the config of them. Respects flexform fields.
     * Data will be set in arReferenceTables
     *
     * @param string $strTableName Table to find references.
     *
     * @return void
     */
    protected function addMMReferenceTables($strTableName)
    {
        global $TCA;

        if ( ! isset($TCA[$strTableName]['columns'])) {
            return;
        }

        foreach ($TCA[$strTableName]['columns'] as $strFieldName => $arColumn) {
            if (isset($arColumn['config']['type'])) {
                switch ($arColumn['config']['type']) {
                    case 'inline':
                        $this->addForeignTableToReferences($arColumn);
                        break;
                    default:
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
    protected function addForeignTableToReferences($arColumn)
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
    protected function addMMTableToReferences(array $arColumn)
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
    protected function addLinesToLineStorage($strStatementType, array $arSqlLines)
    {
        foreach ($arSqlLines as $strTableName => $arLines) {
            if (!is_array($arLines)) {
                return;
            }
            foreach ($arLines as $strIdentifier => $strLine) {
                $this->arGlobalSqlLineStorage[$strStatementType][$strTableName][$strIdentifier] = $strLine;
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
    public function clearDuplicateLines($strStatementType, array &$arSqlLines)
    {
        foreach ($arSqlLines as $strTableName => $arLines) {
            foreach ($arLines as $strIdentifier => $strStatement) {
                if (!empty($this->arGlobalSqlLineStorage[$strStatementType][$strTableName][$strIdentifier])) {
                    unset($arSqlLines[$strTableName][$strIdentifier]);
                }
            }
            // unset tablename key if no statement exists anymore
            if (0 === count($arSqlLines[$strTableName])) {
                unset($arSqlLines[$strTableName]);
            }
        }
    }



    /**
     * Writes the data into dump file. Line per line.
     *
     * @param array $arDeleteLines The lines with the delete statements.
     *                                        Expected structure:
     *                                        $arDeleteLines['table1']['uid1'] = 'STATMENT1'
     *                                        $arDeleteLines['table1']['uid2'] = 'STATMENT2'
     *                                        $arDeleteLines['table2']['uid2'] = 'STATMENT3'
     * @param array $arInsertLines The lines with the insert statements.
     *                                        Expected structure:
     *                                        $arInsertLines['table1']['uid1'] = 'STATMENT1'
     *                                        $arInsertLines['table1']['uid2'] = 'STATMENT2'
     *                                        $arInsertLines['table2']['uid2'] = 'STATMENT3'
     * @param resource $fpDumpFile File pointer to the SQL dump file.
     * @param array $arDeleteObsoleteRows the lines with delete obsolete
     *                                        rows statement
     *
     * @throws Exception
     * @return void
     */
    protected function writeToDumpFile(
        array $arDeleteLines,
        array $arInsertLines,
        $fpDumpFile,
        $arDeleteObsoleteRows = array()
    ) {

        // Keep the current lines in mind
        $this->addLinesToLineStorage(
            self::STATEMENT_TYPE_DELETE,
            $arDeleteLines
        );
        // Keep the current lines in mind
        $this->addLinesToLineStorage(
            self::STATEMENT_TYPE_INSERT,
            $arInsertLines
        );

        // Foreach Table in DeleteArray
        foreach ($arDeleteLines as $arDelLines) {
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

        foreach ($arInsertLines as $strTable => $arInsertStatements) {
            foreach ($arInsertStatements as $nUid => $strStatement) {
                $this->setLastDumpTimeForElement($strTable, $nUid);
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
    protected function writeInsertLines($fpDumpFile)
    {
        if (!is_array(
            $this->arGlobalSqlLineStorage[self::STATEMENT_TYPE_INSERT]
        )) {
            return;
        }

        $arInsertLines
            = $this->arGlobalSqlLineStorage[self::STATEMENT_TYPE_INSERT];
        // Foreach Table in InsertArray
        foreach ($arInsertLines as $strTable => $arTableInsLines) {
            if (count($arTableInsLines)) {
                $strInsertLines
                    = '-- Insert lines for Table: '
                    . $strTable
                    . "\n";
                $strInsertLines .= implode("\n", $arTableInsLines);
                fwrite($fpDumpFile, $strInsertLines . "\n\n");
            }
        }
        return;
    }



    /**
     * Removes all delete statements from $arDeleteLines where an insert statement
     * exists in $arInsertLines.
     *
     * @param array &$arDeleteLines referenced array with delete statements
     *                              structure should be
     *                              $arDeleteLines['table1']['uid1'] = 'STATMENT1'
     *                              $arDeleteLines['table1']['uid2'] = 'STATMENT2'
     *                              $arDeleteLines['table2']['uid2'] = 'STATMENT3'
     * @param array &$arInsertLines referenced array with insert statements
     *                              structure should be
     *                              $arDeleteLines['table1']['uid1'] = 'STATMENT1'
     *                              $arDeleteLines['table1']['uid2'] = 'STATMENT2'
     *                              $arDeleteLines['table2']['uid2'] = 'STATMENT3'
     *
     * @return void
     */
    protected function diffDeleteLinesAgainstInsertLines(
        array &$arDeleteLines, array &$arInsertLines
    ) {
        foreach ($arInsertLines as $strTableName => $arElements) {
            // no modification for arrays with old flat structure
            if (!is_array($arElements)) {
                return;
            }
            // UNSET each delete line where an insert exists
            foreach ($arElements as $strUid => $strStatement) {
                if (!empty($arDeleteLines[$strTableName][$strUid])) {
                    unset($arDeleteLines[$strTableName][$strUid]);
                }
            }

            if (0 === count($arDeleteLines[$strTableName])) {
                unset($arDeleteLines[$strTableName]);
            }
        }
    }



    /**
     * Returns SQL DELETE query.
     *
     * @param string $strTableName name of table to delete from
     * @param integer $uid uid of row to delete
     *
     * @return string
     */
    protected function buildDeleteLine($strTableName, $uid)
    {
        /* @var $connectionPool ConnectionPool */
        $connectionPool = $this->getObjectManager()->get(ConnectionPool::class);

        /* @var $connection \TYPO3\CMS\Core\Database\Connection */
        $connection = $connectionPool->getConnectionForTable($strTableName);

        return 'DELETE FROM '
            . $connection->quoteIdentifier($strTableName)
            . ' WHERE uid = ' . (int) $uid . ';';
    }



    /**
     * Returns SQL INSERT .. UPDATE ON DUPLICATE KEY query.
     *
     * @param string $strTableName name of table to insert into
     * @param array  $arColumnNames
     * @param array  $arContent
     *
     * @return string
     */
    protected function buildInsertUpdateLine($strTableName, array $arColumnNames, array $arContent)
    {
        /* @var $connectionPool ConnectionPool */
        $connectionPool = $this->getObjectManager()->get(ConnectionPool::class);

        /* @var $connection \TYPO3\CMS\Core\Database\Connection */
        $connection = $connectionPool->getConnectionForTable($strTableName);

        $arUpdateParts = array();
        foreach ($arContent as $key => $value) {
            if (!is_numeric($value)) {
                $arContent[$key] = $connection->quote($value);
            }
            // TYPO-2215 - Match the column to its update value
            $arUpdateParts[$key] = $key . ' = VALUES(' . $key . ')';
        }

        $strStatement = 'INSERT INTO '
            . $connection->quoteIdentifier($strTableName)
            . ' (' . implode(', ', $arColumnNames) . ') VALUES ('
            . implode(', ', $arContent) . ')' . "\n"
            . ' ON DUPLICATE KEY UPDATE '
            . implode(', ', $arUpdateParts) . ';';

        return $strStatement;
    }



    /**
     * Returns SQL INSERT query.
     *
     * @param string $strTableName name of table to insert into
     * @param array  $arTableStructure
     * @param array  $arContent
     *
     * @return string
     */
    protected function buildInsertLine($strTableName, $arTableStructure, $arContent)
    {
        /* @var $connectionPool ConnectionPool */
        $connectionPool = $this->getObjectManager()->get(ConnectionPool::class);

        /* @var $connection \TYPO3\CMS\Core\Database\Connection */
        $connection = $connectionPool->getConnectionForTable($strTableName);

        foreach ($arContent as $key => $value) {
            if (!is_numeric($value)) {
                $arContent[$key] = $connection->quote($value);
            }
        }

        $arColumnNames = array_keys($arTableStructure);
        $str = 'REPLACE INTO '
            . $connection->quoteIdentifier($strTableName)
            . ' (' . implode(', ', $arColumnNames) . ') VALUES ('
            . implode(', ', $arContent) . ');';

        return $str;
    }



    /**
     * Erzeugt ein gzip vom Dump File
     *
     * @param string $strDumpFile name of dump file to gzip
     *
     * @return boolean success
     */
    protected function createGZipFile($strDumpFile)
    {
        $strExec = 'gzip ' . escapeshellarg($strDumpFile);

        $ret = shell_exec($strExec);

        if (!file_exists($strDumpFile . '.gz')) {
            $this->addError('Fehler beim Erstellen der gzip Datei aus dem Dump.');
            return false;
        }

        chmod($strDumpFile . '.gz', 0666);

        return true;
    }



    /**
     * Generates the menu based on $this->MOD_MENU
     *
     */
    protected function generateMenu()
    {
        $menu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('WebFuncJumpMenu');
        foreach ($this->MOD_MENU['function'] as $controller => $title) {
            $item = $menu
                ->makeMenuItem()
                ->setHref(
                    BackendUtility::getModuleUrl(
                        $this->moduleName,
                        [
                            'id' => $this->id,
                            'SET' => [
                                'function' => $controller
                            ]
                        ]
                    )
                )
                ->setTitle($title);
            if ($controller === (int) $this->MOD_SETTINGS['function']) {
                $item->setActive(true);
            }
            $menu->addMenuItem($item);
        }
        $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }



    /**
     * Create the panel of buttons for submitting the form or otherwise perform operations.
     */
    protected function getButtons()
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        // CSH
        $cshButton = $buttonBar->makeHelpButton()
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
            $shortcutButton = $buttonBar->makeShortcutButton()
                ->setModuleName($this->moduleName)
                ->setGetVariables(['id', 'edit_record', 'pointer', 'new_unique_uid', 'search_field', 'search_levels', 'showLimit'])
                ->setSetVariables(array_keys($this->MOD_MENU));
            $buttonBar->addButton($shortcutButton);
        }
    }



    protected function addButtonBarAreaLockButtons()
    {
        foreach ($this->getArea()->getSystems() as $systemName => $system) {
            if (! empty($system['hide'])) {
                continue;
            }
            $this->addButtonBarAreaLockButton($systemName, $system);
        }
    }



    protected function addButtonBarAreaLockButton($systemName, array $system)
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $lockButton = $buttonBar->makeLinkButton();

        if (is_file($this->strDBFolder . $system['directory'] . '/.lock')) {
            $lockButton->setHref(
                BackendUtility::getModuleUrl(
                    $this->moduleName,
                    [
                        'lock' => [$systemName => '0'],
                        'id'   => $this->id,
                    ]
                )
            );
            $lockButton->setTitle($system['name']);
            $lockButton->setIcon($this->getIconFactory()->getIcon('actions-lock', Icon::SIZE_SMALL));
            $lockButton->setClasses('btn btn-warning');
        } else {
            $lockButton->setHref(
                BackendUtility::getModuleUrl(
                    $this->moduleName,
                    [
                        'lock' => [$systemName => '1'],
                        'id'   => $this->id,
                    ]
                )
            );
            $lockButton->setTitle($system['name']);
            $lockButton->setIcon($this->getIconFactory()->getIcon('actions-unlock', Icon::SIZE_SMALL));
        }

        $lockButton->setShowLabelText(true);

        $buttonBar->addButton($lockButton);
    }



    /**
     * @return void
     */
    protected function addButtonBarLockButton()
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $lockButton = $buttonBar->makeLinkButton();

        /* @var $syncLock SyncLock */
        $syncLock = $this->getObjectManager()->get(SyncLock::class);

        if ($syncLock->isLocked()) {
            $lockButton->setHref(
                BackendUtility::getModuleUrl(
                    $this->moduleName,
                    [
                        'data' => ['lock' => '0'],
                        'id'   => $this->id,
                    ]
                )
            );
            $lockButton->setTitle('Unlock sync module');
            $lockButton->setIcon($this->getIconFactory()->getIcon('actions-lock', Icon::SIZE_SMALL));
            $lockButton->setClasses('btn-warning');
        } else {
            $lockButton->setHref(
                BackendUtility::getModuleUrl(
                    $this->moduleName,
                    [
                        'data' => ['lock' => '1'],
                        'id'   => $this->id,
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
    protected function getObjectManager()
    {
        /* @var $objectManager ObjectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        return $objectManager;
    }



    protected function getSyncListManager()
    {
        if (null === $this->syncListManager) {
            $this->syncListManager = $this->getObjectManager()->get(SyncListManager::class);
        }

        return $this->syncListManager;
    }



    /**
     * @return IconFactory
     */
    protected function getIconFactory()
    {
        if (null === $this->iconFactory) {
            $this->iconFactory = $this->getObjectManager()->get(IconFactory::class);
        }

        return $this->iconFactory;
    }



    /**
     * Adds error message to message queue.
     *
     * @param string $strMessage error message
     *
     * @return void
     */
    public function addError($strMessage)
    {
        $this->addMessage($strMessage, FlashMessage::ERROR);
    }



    /**
     * Adds error message to message queue.
     *
     * @param string $strMessage success message
     *
     * @return void
     */
    public function addSuccess($strMessage)
    {
        $this->addMessage($strMessage, FlashMessage::OK);
    }



    /**
     * Adds error message to message queue.
     *
     * @param string $strMessage info message
     *
     * @return void
     */
    public function addInfo($strMessage)
    {
        $this->addMessage($strMessage, FlashMessage::INFO);
    }



    /**
     * Adds error message to message queue.
     *
     * message types are defined as class constants self::STYLE_*
     *
     * @param string $strMessage message
     * @param integer $type message type
     *
     * @return void
     */
    public function addMessage($strMessage, $type)
    {
        /* @var $message FlashMessage */
        $message = $this->getObjectManager()->get(
            FlashMessage::class, $strMessage, '', $type, true
        );

        /* @var $messageService FlashMessageService */
        $messageService = $this->getObjectManager()->get(
            FlashMessageService::class
        );

        $messageService->getMessageQueueByIdentifier()->addMessage($message);
    }



    /**
     * Remove entries not needed for the sync.
     *
     * @param array $arLines lines with data to sync
     *
     * @return array
     */
    protected function removeNotSyncableEntries(array $arLines)
    {
        $arResult = $arLines;
        foreach ($arLines as $strTable => $arStatements) {
            foreach ($arStatements as $nUid => $strStatement) {
                if (!$this->isElementSyncable($strTable, $nUid)) {
                    unset($arResult[$strTable][$nUid]);
                }
            }
        }
        return $arResult;
    }



    /**
     * Sets time of last dump/sync for this element.
     *
     * @param string  $strTable the table, the element contains to
     * @param integer $nUid     the uid for the element
     *
     * @return void
     * @throws Exception
     */
    protected function setLastDumpTimeForElement($strTable, $nUid)
    {
        if (strpos($nUid, '-')) {
            // CRAP - we get something like: 47-18527-0-0-0--0-0-0-0-0-0-1503315964-1500542276-…
            // happens in writeMMReference() before createupdateinsertline()
            // take second number as ID:
            $nUid = explode('-', $nUid)[1];
        }

        $nTime = time();
        $nUserId = intval($this->getBackendUser()->user['uid']);
        $strUpdateField = ($this->getForcedFullSync()) ? 'full' : 'incr';

        /* @var $connectionPool ConnectionPool */
        $connectionPool = $this->getObjectManager()->get(ConnectionPool::class);

        /* @var $connection \TYPO3\CMS\Core\Database\Connection */
        $connection = $connectionPool->getConnectionForTable('tx_nrsync_syncstat');

        $connection->exec(
            'INSERT INTO tx_nrsync_syncstat'
            . " (tab, " . $strUpdateField . ", cruser_id, uid_foreign) VALUES "
            . ' ('
            . $connection->quote($strTable)
            . ', ' . $connection->quote($nTime)
            . ', ' . $connection->quote($nUserId)
            . ', ' . $connection->quote($nUid) . ')'
            . ' ON DUPLICATE KEY UPDATE'
            . ' cruser_id = ' . $connection->quote($nUserId) . ', '
            . $strUpdateField . ' = ' . $connection->quote($nTime)
        );
    }



    /**
     * Fetches syncstats for an element from db.
     *
     * @param string $strTable the table, the element belongs to
     * @param integer $nUid the uid for the element
     *
     * @return array|boolean syncstats for an element or false if stats don't exist
     * @throws Exception
     */
    protected function getSyncStatsForElement($strTable, $nUid)
    {
        $queryBuilder = $this->getQueryBuilderForTable($strTable);

        $arRow = $queryBuilder->select('*')
            ->from('tx_nrsync_syncstat')
            ->where(
                $queryBuilder->expr()->eq('tab', $queryBuilder->quote($strTable)),
                $queryBuilder->expr()->eq('uid_foreign', intval($nUid))
            )
            ->execute()
            ->fetch();

        return $arRow;
    }


    /**
     * Returns time stamp of this element.
     *
     * @param string $strTable The table, the elements belongs to
     * @param integer $nUid The uid of the element.
     *
     * @return integer
     * @throws Exception
     */
    protected function getTimestampOfElement($strTable, $nUid)
    {
        $queryBuilder = $this->getQueryBuilderForTable($strTable);

        $arRow = $queryBuilder->select('tstamp')
            ->from($strTable)
            ->where(
                $queryBuilder->expr()->eq('uid', intval($nUid))
            )
            ->execute()
            ->fetch();

        return $arRow['tstamp'];
    }


    /**
     * Clean up statements and prepare dump file.
     *
     * @param array    $arDeleteLine Delete statements
     * @param array    $arInsertLine Insert statements
     * @param resource $fpDumpFile   dump file
     *
     * @return void
     */
    protected function prepareDump(array $arDeleteLine, array $arInsertLine, $fpDumpFile)
    {
        if (!$this->getForcedFullSync()) {
            $arDeleteLine = $this->removeNotSyncableEntries($arDeleteLine);
            $arInsertLine = $this->removeNotSyncableEntries($arInsertLine);
        }

        // Remove Deletes which has a corresponding Insert statement
        $this->diffDeleteLinesAgainstInsertLines(
            $arDeleteLine,
            $arInsertLine
        );

        // Remove all DELETE Lines which already has been put to file
        $this->clearDuplicateLines(
            self::STATEMENT_TYPE_DELETE,
            $arDeleteLine
        );
        // Remove all INSERT Lines which already has been put to file
        $this->clearDuplicateLines(
            self::STATEMENT_TYPE_INSERT,
            $arInsertLine
        );
        $this->writeToDumpFile($arDeleteLine, $arInsertLine, $fpDumpFile);

        $this->writeStats($arInsertLine);
    }



    /**
     * Write stats for the sync.
     *
     * @param array $arInsertLines insert array ofstatements for elements to sync
     *
     * @return void
     */
    protected function writeStats(array $arInsertLines)
    {
        foreach ($arInsertLines as $strTable => $arInstertStatements) {
            if (strpos($strTable, '_mm') !== false) {
                continue;
            }
            foreach ($arInstertStatements as $nUid => $strStatement) {
                $this->setLastDumpTimeForElement($strTable, $nUid);
            }
        }

    }



    /**
     * Return true if a full sync should be forced.
     *
     * @return boolean
     */
    protected function getForcedFullSync()
    {
        return isset($_POST['data']['force_full_sync'])
            && !empty($_POST['data']['force_full_sync']);
    }



    /**
     * Return true if an element, given by tablename and uid is syncable.
     *
     * @param string $strTable the table, the element belongs to
     * @param integer $nUid the uid of the element
     *
     * @return boolean
     */
    protected function isElementSyncable($strTable, $nUid)
    {
        if (strpos($strTable, '_mm') !== false) {
            return true;
        }

        $arSyncStats = $this->getSyncStatsForElement($strTable, $nUid);
        $nTimeStamp = $this->getTimestampOfElement($strTable, $nUid);
        if (!$nTimeStamp) {
            return false;
        }
        if (isset($arSyncStats['full']) && $arSyncStats['full'] > $nTimeStamp) {
            return false;
        }

        return true;
    }



    /**
     * Adds information about full or inc sync to syncfile
     *
     * @param string $strDumpFile the name of the file
     *
     * @return string
     */
    protected function addInformationToSyncfileName($strDumpFile)
    {
        $bIsFullSync = !empty($_POST['data']['force_full_sync']);
        $strPrefix = 'inc_';
        if ($bIsFullSync) {
            $strPrefix = 'full_';
        }
        return $strPrefix . $strDumpFile;
    }
}
