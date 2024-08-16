<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Generator;

use Netresearch\Sync\Controller\BaseSyncModuleController;
use Netresearch\Sync\Helper\Area;
use Netresearch\Sync\Service\StorageService;
use Netresearch\Sync\Traits\TranslationTrait;
use TYPO3\CMS\Core\Resource\FileInterface;

use function count;

/**
 * Generate files with the list of URLs that have to be called
 * after importing the data.
 *
 * @author  Christian Weiske <christian.weiske@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class Urls
{
    use TranslationTrait;

    /**
     * @var string Filename-format for once files
     */
    final public const FILE_FORMAT_ONCE = '%s-once.txt';

    /**
     * @var string Filename-format for per-machine files
     */
    final public const FILE_FORMAT_PERMACHINE = '%s-per-machine.txt';

    /**
     * @var StorageService
     */
    protected StorageService $storageService;

    /**
     * Constructor.
     *
     * @param StorageService $storageService
     */
    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Called after the sync button has been pressed.
     * We generate the URL files here.
     *
     * @param array<string, string[]|bool> $params information about what to sync
     * @param BaseSyncModuleController     $sync   Main sync module object
     *
     * @return void
     */
    public function postProcessSync(array $params, BaseSyncModuleController $sync): void
    {
        if (($params['bProcess'] === false)
            || ($params['bSyncResult'] === false)) {
            return;
        }

        if ((count($params['arUrlsOnce']) === 0)
            && (count($params['arUrlsPerMachine']) === 0)
        ) {
            return;
        }

        $matchingAreas = Area::getMatchingAreas($sync->getTarget());
        $folders       = $this->getFolders($matchingAreas, $sync);
        $count         = 0;

        if (isset($params['arUrlsOnce'])) {
            $count = $this->generateUrlFile(
                $params['arUrlsOnce'],
                $folders,
                self::FILE_FORMAT_ONCE
            );
        }

        if (isset($params['arUrlsPerMachine'])) {
            $count += $this->generateUrlFile(
                $params['arUrlsPerMachine'],
                $folders,
                self::FILE_FORMAT_PERMACHINE
            );
        }

        $sync->addSuccessMessage(
            $this->getLabel(
                'message.hook_files',
                [
                    '{number}' => $count,
                ]
            )
        );
    }

    /**
     * Generates the url files for a given format.
     *
     * @param string[] $urls    Array with urls to write onto file
     * @param string[] $folders Folders in which the files should be stored
     * @param string   $format  Format of filename
     *
     * @return int
     */
    private function generateUrlFile(array $urls, array $folders, string $format): int
    {
        [$content, $strPath] = $this->prepareFile($urls, $format);

        return $this->saveFile($content, $strPath, $folders);
    }

    /**
     * Prepares file content and file name for an url list file.
     *
     * @param string[] $arUrls           URLs
     * @param string   $fileNameTemplate Template for file name. Date will be put into it
     *
     * @return array<int, string|null> First value is the file content, second the file name
     */
    protected function prepareFile(array $arUrls, string $fileNameTemplate): array
    {
        if ($arUrls === []) {
            return [null, null];
        }

        return [
            implode("\n", $arUrls) . "\n",
            sprintf($fileNameTemplate, date(BaseSyncModuleController::DATE_FORMAT)),
        ];
    }

    /**
     * Saves the given file into different folders.
     *
     * @param string|null $content  File content to save
     * @param string|null $fileName File name to use
     * @param string[]    $folders  Folders to save file into
     *
     * @return int Number of created files
     */
    protected function saveFile(?string $content, ?string $fileName, array $folders): int
    {
        if (($content === null)
            || ($content === '')
            || ($fileName === null)
            || ($fileName === '')
            || ($folders === [])
        ) {
            return 0;
        }

        foreach ($folders as $folder) {
            $folder = $this->storageService
                ->getSyncFolder()
                ->getSubfolder($folder);

            /** @var FileInterface|null $file */
            $file = $this->storageService
                ->getDefaultStorage()
                ->createFile($fileName, $folder);

            $file?->setContents($content);
        }

        return count($folders);
    }

    /**
     * Returns full folder paths. Creates folders if necessary.
     * The URL files have to be put in each of the folders.
     *
     * @param Area[]                   $areas Areas to sync to
     * @param BaseSyncModuleController $sync  Main sync module object
     *
     * @return string[] Array of full paths with trailing slashes
     */
    protected function getFolders(array $areas, BaseSyncModuleController $sync): array
    {
        $paths = [];

        foreach ($areas as $area) {
            foreach ($area->getSystems() as $system) {
                if ($sync->isSystemLocked($system['directory'])) {
                    $sync->addWarningMessage(
                        $this->getLabel(
                            'warning.urls_system_locked',
                            [
                                '{target}' => $system['directory'],
                            ]
                        )
                    );

                    continue;
                }

                $paths[] = $system['url-path'];
            }
        }

        return array_unique($paths);
    }
}
