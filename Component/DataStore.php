<?php
namespace Mapbender\DataSourceBundle\Component;

use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Component\Drivers\BaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\DoctrineBaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\IDriver;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;
use Mapbender\DataSourceBundle\Component\Drivers\SQLite;
use Mapbender\DataSourceBundle\Component\Drivers\YAML;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DataSource
 *
 * Data source manager.
 *
 * @package Mapbender\DataSourceBundle
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DataStore extends ContainerAware
{
    const ORACLE_PLATFORM     = 'oracle';
    const POSTGRESQL_PLATFORM = 'postgresql';
    const SQLITE_PLATFORM     = 'sqlite';

    /**
     * @var IDriver $driver
     */
    protected $driver;
    public    $lifeCycleFunctions;

    /**
     * @param ContainerInterface $container
     * @param null               $args
     */
    public function __construct(ContainerInterface $container, $args = null)
    {
        /** @var Connection $connection */
        $this->setContainer($container);
        $type                     = isset($args["type"]) ? $args["type"] : "doctrine";
        $connectionName           = isset($args["connection"]) ? $args["connection"] : "default";
        $driver                   = null;
        $this->lifeCycleFunctions = isset($args["lifeCycleFunctions"]) ? $args["lifeCycleFunctions"] : array();

        $hasFields = isset($args["fields"]) && is_array($args["fields"]);

        // init $methods by $args
        if (is_array($args)) {
            $methods = get_class_methods(get_class($this));
            foreach ($args as $key => $value) {
                $keyMethod = "set" . ucwords($key);
                if (in_array($keyMethod, $methods)) {
                    $this->$keyMethod($value);
                }
            }
        }

        switch ($type) {
            case'yaml':
                $driver = new YAML($this->container, $args);
                break;
            default: // doctrine
                $connection = $this->container->get("doctrine.dbal.{$connectionName}_connection");
                switch ($connection->getDatabasePlatform()->getName()) {
                    case self::SQLITE_PLATFORM;
                        $driver = new SQLite($this->container, $args);
                        break;
                    case self::POSTGRESQL_PLATFORM;
                        $driver = new PostgreSQL($this->container, $args);
                        break;

                }
                $driver->connect($connectionName);
                if (!$hasFields) {
                    $driver->setFields($driver->getStoreFields());
                }

        }
        $this->driver = $driver;
    }

    /**
     * @param $url
     * @return mixed
     */
    public function connect($url)
    {
        return $this->getDriver()->connect($url);
    }

    /**
     * @param $id
     * @return DataItem
     */
    public function getById($id)
    {
        return $this->getDriver()->get($id);
    }

    /**
     * Get parent by child ID
     *
     * @param $id
     * @return DataItem
     */
    public function getParent($id)
    {
        return new DataItem();
    }

    /**
     * Get children ID
     *
     * @param $id
     * @return DataItem[]
     */
    public function getChildren($id)
    {
        return new DataItem();
    }

    /**
     * Convert array to DataItem object
     *
     * @param $data
     * @return DataItem
     */
    public function create($data)
    {
        return $this->driver->create($data);
    }

    /**
     * Save data item
     *
     * @param DataItem $item
     * @param bool     $autoUpdate Create item if doesn't exists
     * @return DataItem
     */
    public function save($item, $autoUpdate = true)
    {
        if (isset($this->lifeCycleFunctions['onBeforeInsert'])) {
            eval($this->lifeCycleFunctions['onBeforeInsert']);
        }
        $result = $this->getDriver()->save($item, $autoUpdate);
        if (isset($this->lifeCycleFunctions['onAfterInsert'])) {
            eval($this->lifeCycleFunctions['onAfterInsert']);
        }
        return $result;
    }

    /**
     * Remove data item
     *
     * @inheritdoc
     */
    public function remove($args)
    {
        if (isset($this->lifeCycleFunctions['onBeforeRemove'])) {
            eval($this->lifeCycleFunctions['onBeforeRemove']);
        }

        $result = $this->getDriver()->remove($args);

        if (isset($this->lifeCycleFunctions['onAfterRemove'])) {
            eval($this->lifeCycleFunctions['onAfterRemove']);
        }
        return $result;
    }

    /**
     * Search by criteria
     *
     * @param array $criteria
     * @return DataItem[]
     */
    public function search(array $criteria = array())
    {
        if (isset($this->lifeCycleFunctions['onBeforeSelect'])) {
            eval($this->lifeCycleFunctions['onBeforeSelect']);
        }
        $results = $this->getDriver()->search($criteria);

        if (isset($this->lifeCycleFunctions['onAfterSelect'])) {
            eval($this->lifeCycleFunctions['onAfterSelect']);
        }

        return $results;
    }

    /**
     * Get current driver instance
     *
     * @return IDriver|BaseDriver|DoctrineBaseDriver
     */
    public function getDriver()
    {
        return $this->driver;
    }


    /**
     * Is oralce platform
     *
     * @return bool
     */
    public function isOracle()
    {
        static $r;
        if (is_null($r)) {
            $r = $this->driver->getPlatformName() == self::ORACLE_PLATFORM;
        }
        return $r;
    }

    /**
     * Is SQLite platform
     *
     * @return bool
     */
    public function isSqlite()
    {
        static $r;
        if (is_null($r)) {
            $r = $this->driver->getPlatformName() == self::SQLITE_PLATFORM;
        }
        return $r;
    }

    /**
     * Is postgres platform
     *
     * @return bool
     */
    public function isPostgres()
    {
        static $r;
        if (is_null($r)) {
            $r = $this->driver->getPlatformName() == self::POSTGRESQL_PLATFORM;
        }
        return $r;
    }

    /**
     * Get driver types
     *
     * @return array
     */
    public function getTypes()
    {
        $list            = array();
        $reflectionClass = new \ReflectionClass(__CLASS__);
        foreach ($reflectionClass->getConstants() as $k => $v) {
            if (strrpos($k, "_PLATFORM") > 0) {
                $list[] = $v;
            }
        }
        return $list;
    }

    /**
     * Get by argument
     *
     * @inheritdoc
     */
    public function get($args)
    {
        return $this->driver->get($args);
    }

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatformName()
    {
        return $this->getDriver()->getPlatformName();
    }


    /**
     * Get DBAL Connection
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->getDriver()->getConnection();
    }
}