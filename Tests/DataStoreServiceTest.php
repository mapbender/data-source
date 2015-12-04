<?php
namespace Mapbender\DataSourceBundle\Tests;

use Mapbender\DataSourceBundle\Component\DataStoreService;
use Mapbender\DataSourceBundle\Component\Drivers\BaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\IDriver;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;
use Mapbender\DataSourceBundle\Entity\DataItem;

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

            // Test search method
            foreach ($dataStore->search() as $dataItem) {
                $this->assertTrue($dataItem instanceof DataItem);
            }

            // Test create method
            $testTitle = "test#10";
            $dataItem  = $dataStore->create(array("title" => $testTitle));
            $this->assertTrue($dataItem instanceof DataItem);
            $this->assertTrue($dataItem->getAttribute("title") == $testTitle);


            // Test save method
            $dataStore->save($dataItem);

        }
    }
}
