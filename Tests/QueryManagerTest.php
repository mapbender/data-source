<?php
/**
 * Created by PhpStorm.
 * User: ransomware
 * Date: 04/10/16
 * Time: 11:46
 */

namespace Mapbender\DataSourceBundle\Tests;

use Mapbender\DataSourceBundle\Component\FeatureType;
use Mapbender\SearchBundle\Entity\Query;
use Mapbender\DataSourceBundle\Entity\StyleMap;
use Mapbender\SearchBundle\Component\QueryManager;
use Mapbender\SearchBundle\Entity\QueryCondition;

class QueryManagerTest extends SymfonyTest2
{


    public function testSave()
    {

        $queryManager = $this->getQueryManager();
        $query        = $this->getMockupQuery();
        $hkv          = $queryManager->save($query);

        $saveFailedMessage = "Querymanager could not save the query: " . json_encode($query->toArray());
        $idKey             = "id";
        self::assertObjectHasAttribute($idKey, $hkv, $saveFailedMessage);

        $hkvToArray = $hkv->toArray();
        //var_dump($hkvToArray);
        self::assertNotEquals(null, $hkvToArray["id"], json_encode($hkv));

    }

    public function testGetById()
    {

        $this->markTestIncomplete(
            'This test has not been implemented properly yet.'
        );

        $container    = self::$container;
        $queryManager = new QueryManager($container);

        $query  = $this->getMockupQuery();
        $hkv    = $queryManager->save($query);
        $result = $queryManager->getById($hkv->getId());

        $getByIdFailedMessage = "ID: " . $hkv->getId() . "QueryManager could not resolve the query:" . json_encode($query->toArray());
        self::assertNotNull($result, $getByIdFailedMessage);

    }


    public function testListQueries()
    {
        $this->markTestIncomplete(
            'This test has not been implemented properly yet.'
        );
        $queryManager = $this->getQueryManager();
        $query        = $this->getMockupQuery();
        $queryManager->save($query);

    }

    public function testRemove()
    {

        $this->markTestIncomplete(
            'This test has not been implemented properly yet.'
        );
        $queryManager = $this->getQueryManager();

        $query = $this->getMockupQuery();

        $hkv                   = $queryManager->save($query);
        $removingFailedMessage = "The Querymanager could not resolve the query : " . json_encode($query->toArray());
        self::assertTrue($queryManager->remove($hkv->getId()), $removingFailedMessage);

    }

    /**
     * Helpmethods
     */

    public function getQueryManager()
    {
        $container = self::$container;
        return new QueryManager($container);
    }

    public function getMockupQuery()
    {
        $queryConditionArgs = array("fieldName" => "name",
                                    "operator"  => "LIKE",
                                    "value"     => "Matthias",
                                    "sql"       => ""
        );

        $queryCondition  = new QueryCondition($queryConditionArgs);
        $queryConditions = array($queryCondition);

        $styleMap  = new StyleMap();
        $queryArgs = array("name"       => "Test",
                           "conditions" => $queryConditions,
                           "styleMap"   => $styleMap,
                           "userId"     => 0
        );

        return new Query($queryArgs);

    }
}