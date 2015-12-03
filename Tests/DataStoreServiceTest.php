<?php
namespace Mapbender\DataSourceBundle\Tests;

use Mapbender\DataSourceBundle\Component\DataStoreService;
use Mapbender\DataSourceBundle\Component\Drivers\BaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\IDriver;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;

/**
 * Class DataStoreServiceTest
 *
 * @package Mapbender\DataSourceBundle\Tests
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DataStoreServiceTest extends SymfonyTest
{
    public function testDriver()
    {
        /** @var DataStoreService $service */
        $service       = $this->get("data.source");
        $dataStoreList = $this->getParameter("dataStores");
        foreach ($dataStoreList as $name => $settings) {
            $dataStore = $service->get($name);
            $driver    = $dataStore->getDriver();
            $this->assertTrue($driver instanceof BaseDriver);
            $this->assertTrue($driver instanceof IDriver);
            $dataStore->search();
        }

    }
}
