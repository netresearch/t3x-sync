<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Scheduler\SyncImportTask;

use Netresearch\NrScheduler\AbstractAdditionalFieldProvider;
use Netresearch\NrScheduler\Fields\TextField;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * AdditionalFieldsProvider.
 *
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class AdditionalFieldsProvider extends AbstractAdditionalFieldProvider
{
    /**
     * @var string
     */
    final public const FIELD_NAME_PATH = 'syncStoragePath';

    /**
     * @var string
     */
    final public const FIELD_NAME_URLS = 'syncUrlsPath';

    /**
     * @var string
     */
    final public const TRANSLATION_FILE = 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_scheduler.xlf';

    /**
     * Saves the data of additional fields.
     *
     * @param array<mixed> $submittedData Data submitted by the form
     * @param AbstractTask $task          TaskObject to save the data to
     *
     * @return void
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        parent::saveAdditionalFields($submittedData, $task);

        /** @var Task $task */
        $task->syncStoragePath = $submittedData[self::FIELD_NAME_PATH];
        $task->syncUrlsPath    = $submittedData[self::FIELD_NAME_URLS];
    }

    /**
     * Returns the array with the field configuration.
     *
     * @return array<string, array<string, bool|int|string|string[]>>
     */
    public function getFieldConfiguration(): array
    {
        return [
            self::FIELD_NAME_PATH => [
                'default'         => '',
                'type'            => TextField::class,
                'translationFile' => self::TRANSLATION_FILE,
                'validators'      => [],
            ],
            self::FIELD_NAME_URLS => [
                'default'         => '',
                'type'            => TextField::class,
                'translationFile' => self::TRANSLATION_FILE,
                'validators'      => [],
            ],
        ];
    }
}
