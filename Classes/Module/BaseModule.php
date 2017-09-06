<?php
/**
 * Created by PhpStorm.
 * User: sebastian.mendel
 * Date: 2017-09-04
 * Time: 14:52
 */

namespace Netresearch\Sync\Module;


class BaseModule
{
    protected $tables = [];
    protected $name = 'Default sync';
    protected $type = 'sync_tables';
    protected $target = '';
    protected $dumpFileName = 'dump.sql';
    protected $accessLevel = 0;
    protected $error = null;
    protected $content = '';

    function __construct(array $arOptions = null)
    {
        $this->tables = (array) $arOptions['tables'] ?: [];
        $this->name = $arOptions['name'] ?: 'Default sync';
        $this->target = $arOptions['target'] ?: '';
        $this->type = $arOptions['type'] ?: 'sync_tables';
        $this->dumpFileName = $arOptions['dumpFileName'] ?: 'dump.sql';
        $this->accessLevel = intval($arOptions['accessLevel']) ?: 0;
    }

    public function run()
    {
        return true;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getName()
    {
        return $this->name;
    }
    public function getType()
    {
        return $this->type;
    }
    public function getDumpFileName()
    {
        return $this->dumpFileName;
    }
    public function getTableNames()
    {
        return $this->tables;
    }
    public function getAccessLevel()
    {
        return $this->accessLevel;
    }
    public function getTarget()
    {
        return $this->target;
    }

    public function getDescription()
    {
        return 'Target: ' . $this->getTarget() . '<br>'
            . 'Content: ' . $this->getName();
    }

    public function getError()
    {
        return $this->error;
    }

    public function hasError()
    {
        return null !== $this->error;
    }
}