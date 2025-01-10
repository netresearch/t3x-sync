<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\ViewHelpers\Math;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * A ViewHelper to perform ceil() optionen on input value.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class CeilViewHelper extends AbstractViewHelper
{
    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'value',
            'mixed',
            'The number to process'
        );
    }

    /**
     * Render stuff.
     *
     * @return int
     */
    public function render(): int
    {
        /** @var int|float $value */
        $value = $this->arguments['value'] ?? $this->buildRenderChildrenClosure()();

        return (int) ceil($value);
    }
}
