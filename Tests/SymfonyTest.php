<?php
namespace Mapbender\DataSourceBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * @package Mapbender\DataSourceBundle\Tests
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
abstract class SymfonyTest extends WebTestCase
{

    /** @var Client */
    protected static $client;

    /** @var Container Container */
    protected static $container;

    /**
     * Setup before run tests
     */
    public static function setUpBeforeClass()
    {
        self::$client    = static::createClient();
        self::$container = self::$client->getContainer();
    }

    /**
     * @param string $serviceName
     * @return object
     */
    protected function get($serviceName)
    {
        return static::$container->get($serviceName);
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return self::$client;
    }

    /**
     * Get symfony parameter
     *
     * @param $name
     * @return mixed
     */
    protected function getParameter($name)
    {
        return static::$container->getParameter($name);
    }

    /**
     * Get method configuration if available
     *
     * @return mixed|null
     */
    protected function getConfiguration()
    {
        $trace         = debug_backtrace();
        $caller        = $trace[1];
        $methodName    = preg_replace("/^test/", "", $caller["function"]);
        $methodName[0] = strtolower($methodName[0]);
        $path = "test/" . $caller["class"] . "/" . $methodName;

        $names = explode("/", $path);
        $r = self::$container->getParameter($names[0]);
        foreach (array_slice($names, 1) as $name) {
            $r = $r[$name];
        }
        return $r;
    }
}
