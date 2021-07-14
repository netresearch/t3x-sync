<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\ViewHelpers;

use Netresearch\Sync\Helper\Area;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\Renderer\BootstrapRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use function constant;

/**
 * A ViewHelper to print return an area by a given areaId.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class FlashMessageViewHelper extends AbstractViewHelper
{
    /**
     * @var BootstrapRenderer
     */
    private $bootstrapRenderer;

    /**
     * @var bool
     */
    protected $escapeChildren = false;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * FlashMessageViewHelper constructor.
     *
     * @param BootstrapRenderer $bootstrapRenderer
     */
    public function __construct(
        BootstrapRenderer $bootstrapRenderer
    ) {
        $this->bootstrapRenderer = $bootstrapRenderer;
    }

    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'type',
            'int',
            'The flash message type (either NOTICE, INFO, OK, WARNING or ERROR)',
            true
        );
    }

    /**
     * Returns the renderer flash message.
     *
     * @return Area
     */
    public function render(): string
    {
        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $this->renderChildren(),
            '',
            constant(FlashMessage::class . '::' . $this->arguments['type'])
        );

        return $this->bootstrapRenderer->render([ $message ]);
    }
}
