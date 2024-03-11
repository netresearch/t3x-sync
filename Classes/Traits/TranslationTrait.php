<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Traits;

use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * TranslationTrait.
 *
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
trait TranslationTrait
{
    protected string $defaultLanguageFile = 'LLL:EXT:nr_sync/Resources/Private/Language/locallang_mod_sync.xlf';

    /**
     * Returns an instance of the language service.
     *
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Return the translated label.
     *
     * @param string                    $id           The id or key of label or field
     * @param array<string, int|string> $data         An optional array with data to replace in the message
     * @param string                    $languageFile An optional path to a language file
     *
     * @return string
     */
    protected function getLabel(string $id, array $data = [], string $languageFile = ''): string
    {
        if ($languageFile === '') {
            $languageFile = $this->defaultLanguageFile;
        }

        $translation = $this->getLanguageService()->sL(
            $languageFile . ':' . $id
        );

        if ($translation === '') {
            return $id;
        }

        if ($data === []) {
            return $translation;
        }

        $keyReplacements = [];

        // Ensure that every key is surrounded by curly braces.
        // This may not be the case if the method is called from within a view helper. This helps
        // to avoid errors in Fluid, as curly brackets are also used as markers here.
        foreach (array_keys($data) as $key) {
            $keyReplacements[$key] = '{' . trim($key, '{}') . '}';
        }

        $data = array_combine(
            array_merge(
                $data,
                $keyReplacements
            ),
            $data
        );

        return str_replace(array_keys($data), $data, $translation);
    }
}
