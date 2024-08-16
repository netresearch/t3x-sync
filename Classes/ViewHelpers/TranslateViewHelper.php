<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\ViewHelpers;

use Netresearch\Sync\Traits\TranslationTrait;
use RuntimeException;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * A ViewHelper to translate.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class TranslateViewHelper extends AbstractViewHelper
{
    use TranslationTrait;

    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'key',
            'string',
            'The Translation key'
        );

        $this->registerArgument(
            'id',
            'string',
            'The Translation ID. Same as key.'
        );

        $this->registerArgument(
            'data',
            'array',
            'Optional array of data to be replaced in the message. The individual values '
            . 'can, but do not have to, be passed in curly brackets.',
        );
    }

    /**
     * Returns the translated label.
     *
     * @return string
     */
    public function render(): string
    {
        $id = $this->arguments['id'] ?? $this->arguments['key'] ?? null;
        $id = (string) $id;

        if ($id === '') {
            throw new RuntimeException('An argument "key" or "id" has to be provided', 1711101732);
        }

        return $this->getLabel(
            $id,
            $this->arguments['data'] ?? []
        );
    }
}
