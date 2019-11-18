<?php
namespace Mapbender\DataSourceBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * @package Mapbender\DataSourceBundle\Tests
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class SymfonyTest extends WebTestCase
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
        return self::$container->get($serviceName);
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
     * @return mixed|null
     */
    public function getParameter($name)
    {
        $names = explode("/", $name);
        $r     = null;
        $name  = current($names);
        if (!$name || !self::$container->hasParameter($name)) {
            return $r;
        }

        $parameters = self::$container->getParameter($name);
        $c          = count($names);
        foreach ($names as $k => &$name) {
            if ($k == 0) {
                continue;
            }

            if (isset($parameters[ $name ])) {
                $parameters = $parameters[ $name ];
                if ($k + 1 == $c) {
                    $r = $parameters;
                }
            } else {
                break;
            }
        }

        return $r;
    }

    /**
     * Get method configuration if available
     *
     * @return mixed|null
     */
    public function getConfiguration()
    {
        $trace         = debug_backtrace();
        $caller        = $trace[1];
        $methodName    = preg_replace("/^test/", "", $caller["function"]);
        $methodName[0] = strtolower($methodName[0]);
        $parameter     = $this->getParameter("test/" . $caller["class"] . "/" . $methodName);
        return $parameter;
    }
}
