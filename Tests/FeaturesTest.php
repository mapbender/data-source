<?php
namespace Mapbender\DataSourceBundle\Tests;

use Mapbender\DataSourceBundle\Component\FeatureType;
use Mapbender\DataSourceBundle\Entity\Feature;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 * @copyright 2015 by WhereGroup GmbH & Co. KG
 */
class FeaturesTest extends WebTestCase
{
    protected static $fieldName;
    /**
     * @var Client
     */
    protected static $client;

    /**
     * @var FeatureType
     */
    protected static $featureType;

    /**
     * @var Container
     */
    protected static $container;
    protected static $hasDefinitions;
    protected static $definitions;

    public static function setUpBeforeClass()
    {
        self::$client         = static::createClient();
        self::$container      = self::$client->getContainer();
        self::$hasDefinitions = self::$container->hasParameter('featureTypes');
        self::$definitions    = self::$hasDefinitions ? self::$container->getParameter('featureTypes') : array();

        if (!self::$hasDefinitions) {
            self::markTestSkipped("No feature declaration found");
            return;
        }

        self::$featureType = self::$container->get('features')->get(key(self::$definitions));
        self::$fieldName   = current(self::$featureType->getFields());
    }

    public function testSearch()
    {
        self::$featureType->search(array());
    }

    public function testCustomSearch()
    {
        $qb = self::$featureType->getSelectQueryBuilder();
        $qb->setMaxResults(1);
        $results = $qb->execute()->fetchAll();
        self::$featureType->prepareResults($results);
        $this->assertTrue(is_array($results));
    }

    public function testSaveArray()
    {
        $featureData = array(self::$fieldName => "testSaveArray");
        $feature     = self::$featureType->save($featureData);
        $this->assertTrue($feature instanceof Feature);
    }

    public function testSaveObject()
    {
        $featureData = array(self::$fieldName => "testSaveObject");
        $feature     = new Feature($featureData);
        $feature     = self::$featureType->save($feature);
        $this->assertTrue($feature instanceof Feature);
    }

    public function testGetById()
    {
        $originFeature = $this->getRandomFeature();
        $feature       = self::$featureType->getById($originFeature->getId());
        $this->assertTrue($feature instanceof Feature);
        if ($feature instanceof Feature) {
            $this->assertTrue($feature->hasId());
            $this->assertTrue($feature->getId() == $originFeature->getId(), "ID is incorrect");
        }
    }

    public function testRemove()
    {
        $featureType = self::$featureType;
        $this->assertGreaterThan(0, $featureType->remove(array(self::$fieldName => "testSaveArray")));
        $this->assertGreaterThan(0, $featureType->remove(array(self::$fieldName => "testSaveObject")));
    }

    public function testUpdate()
    {
        $originFeature = $this->getRandomFeature();
        self::$featureType->save($originFeature);
    }

    /**
     * @param int $maxResults
     * @return Feature
     */
    private function getRandomFeature($maxResults = 10)
    {
        $features      = self::$featureType->search(array('maxResults' => $maxResults));
        $originFeature = $features[ rand(1, count($features)) - 1 ];
        return $originFeature;
    }

    public function testJson()
    {
        $feature = $this->getRandomFeature();
        self::$featureType->getSrid();
        $json     = $feature . "";
        self::$featureType->create($json);
    }
}
