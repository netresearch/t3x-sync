<?php
/**
 * Part of nr_sync
 *
 * PHP version 5
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Christian Weiske <christian.weiske@netresearch.de>
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Sync\Generator;

use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Controller\SyncModuleController;

/**
 * Generate files with the list of URLs that have to be called
 * after importing the data.
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Christian Weiske <christian.weiske@netresearch.de>
 * @copyright  2013 Netresearch GmbH & Co.KG
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */
class Urls
{
    /**
     * @var string Filename-format for once files
     */
    const FILE_FORMAT_ONCE = '%s-once.txt';

    /**
     * @var string Filename-format for per-machine files
     */
    const FILE_FORMAT_PERMACHINE = '%s-per-machine.txt';

    /**
     * Called after the sync button has been pressed.
     * We generate the URL files here.
     *
     * @param array  $arParams Information about what to sync.
     * @param SyncModuleController $sync     Main sync module object
     *
     * @return void
     */
    public function postProcessSync(array $arParams, SyncModuleController $sync)
    {
        if ($arParams['bProcess'] == false || $arParams['bSyncResult'] == false) {
            return;
        }

        if (count($arParams['arUrlsOnce']) == 0
            && count($arParams['arUrlsPerMachine']) == 0
        ) {
            return;
        }

        $arMatchingAreas = Area::getMatchingAreas(
            $arParams['arAreas'], $arParams['strTableType']
        );
        $arFolders = $this->getFolders($arMatchingAreas, $sync);

        $nCount = 0;

        if (isset($arParams['arUrlsOnce'])) {
            $nCount = $this->generateUrlFile(
                $arParams['arUrlsOnce'], $arFolders, self::FILE_FORMAT_ONCE
            );
        }
        if (isset($arParams['arUrlsPerMachine'])) {
            $nCount += $this->generateUrlFile(
                $arParams['arUrlsPerMachine'], $arFolders, self::FILE_FORMAT_PERMACHINE
            );
        }

        $sync->addSuccess(
            sprintf('Created %d hook URL files', $nCount)
        );
    }


    /**
     * Generates the url files for a given format
     *
     * @param array  $urls    Array with urls to write onto file
     * @param array  $folders Folders in which the files should be stored
     * @param string $format  Format of filename
     *
     * @return int
     */
    private function generateUrlFile(array $urls, array $folders, $format)
    {
        list($strContent, $strPath) = $this->prepareFile($urls, $format);
        return $this->saveFile($strContent, $strPath, $folders);
    }


    /**
     * Prepares file content and file name for an url list file
     *
     * @param array  $arUrls              URLs
     * @param string $strFileNameTemplate Template for file name.
     *                                    Date will be put into it
     *
     * @return array First value is the file content, second the file name
     */
    protected function prepareFile(array $arUrls, $strFileNameTemplate)
    {
        if (count($arUrls) == 0) {
            return array(null, null);
        }

        return array(
            implode("\n", $arUrls) . "\n",
            sprintf($strFileNameTemplate, date('YmdHis'))
        );
    }



    /**
     * Saves the given file into different folders
     *
     * @param string $strContent  File content to save
     * @param string $strFileName File name to use
     * @param array  $arFolders   Folders to save file into
     *
     * @return integer Number of created files
     */
    protected function saveFile($strContent, $strFileName, array $arFolders)
    {
        if ($strContent === null || $strFileName == '' || !count($arFolders)) {
            return 0;
        }

        foreach ($arFolders as $strFolder) {
            file_put_contents($strFolder . '/' . $strFileName, $strContent);
        }

        return count($arFolders);
    }



    /**
     * Returns full folder paths. Creates folders if necessary.
     * The URL files have to be put in each of the folders.
     *
     * @param Area[]               $arAreas Areas to sync to
     * @param SyncModuleController $sync    Main sync module object
     *
     * @return array Array of full paths with trailing slashes
     */
    protected function getFolders(array $arAreas, SyncModuleController $sync)
    {
        $arPaths = [];

        foreach ($arAreas as $area) {
            foreach ($area->getUrlDirectories() as $strDirectory) {
                $arPaths[] = $sync->strDBFolder . $strDirectory . '/';
            }
        }
        $arPaths = array_unique($arPaths);

        foreach ($arPaths as $strPath) {
            if (!is_dir($strPath)) {
                mkdir($strPath, $sync->nFolderRights);
            }
        }

        return $arPaths;
    }
}
