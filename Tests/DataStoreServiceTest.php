<?php
namespace Mapbender\DataSourceBundle\Tests;

use Mapbender\DataSourceBundle\Component\DataStore;
use Mapbender\DataSourceBundle\Component\DataStoreService;
use Mapbender\DataSourceBundle\Component\Drivers\BaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\IDriver;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class DataStoreServiceTest
 *
 * @package Mapbender\DataSourceBundle\Tests
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DataStoreServiceTest extends SymfonyTest
{
    public function testFields()
    {
        if (!$configuration = $this->getConfiguration()) {
            self::markTestSkipped("No test declaration found");
            return;
        }
        $dataStore = new DataStore(parent::$container, $configuration);
        $results   = $dataStore->search();
        foreach ($results as $result) {
            $attributes = $result->getAttributes();
        };
    }

    public function testTree()
    {
        if (!$configuration = $this->getConfiguration()) {
            self::markTestSkipped("No test declaration found");
            return;
        }

        $dataStore = new DataStore(parent::$container, $configuration);
        foreach ($dataStore->getTree() as $dataItem) {
            $this->assertTrue($dataItem->getAttribute($dataStore->getParentField()) == null);
            $this->assertTrue($dataItem->getAttribute($dataStore->getDriver()->getUniqueId()) > 0);
        }
    }

    public function testDriver()
    {
        if(!$configuration = $this->getConfiguration()){
            self::markTestSkipped("No dataStore test declaration found");
            return;
        }

        // TODO check
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
            $testTitle = "test#".rand(0,1000000);
            $dataItem  = $dataStore->create(array("title" => $testTitle));
            $this->assertTrue($dataItem instanceof DataItem);
            $this->assertTrue($dataItem->getAttribute("title") == $testTitle);

            // Test save method
            $dataStore->save($dataItem);
            $this->assertTrue($dataItem->getId() > 0);

            // Test get method
            $dataItem = $dataStore->get($dataItem->getId());

            // Test remove method
            $this->assertTrue($dataItem->getId() > 0);
            $this->assertTrue($dataStore->remove($dataItem));
        }
    }
}
