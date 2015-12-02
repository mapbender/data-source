<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Component\Driver\IDriver;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class SQLite
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class SQLite extends DoctrineBaseDriver
{
    /**
     * @inheritdoc
     */
    public function getVersion(){
        $this->fetchColumn("select sqlite_version()");
    }
}