<?php
/**
 * Created by PhpStorm.
 * User: sebastian.mendel
 * Date: 2017-09-04
 * Time: 14:52
 */

namespace Netresearch\Sync\Module;


use Netresearch\Sync\Helper\Area;

class AssetModule extends BaseModule
{
    protected $name = 'Assets';
    protected $type = '';
    protected $target = 'sync server';
    protected $dumpFileName = '';
    protected $accessLevel = 100;

    public function run(Area $area = null)
    {
        parent::run();

        if (isset($_POST['data']['submit'])) {
            if ($area->notifyMaster()) {
                $this->addMessage(
                    'Sync assets is initiated.'
                );
            }
        }

        return true;
    }
}