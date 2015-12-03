<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

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
    public function getVersion()
    {
        $this->fetchColumn("select sqlite_version()");
    }
}