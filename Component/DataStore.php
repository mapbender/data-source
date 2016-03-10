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
    public    $events;
    protected $allowSave;
    protected $allowRemove;

    /**
     * @param ContainerInterface $container
     * @param null               $args
     */
    public function __construct(ContainerInterface $container, $args = null)
    {
        /** @var Connection $connection */
        $this->setContainer($container);
        $type           = isset($args["type"]) ? $args["type"] : "doctrine";
        $connectionName = isset($args["connection"]) ? $args["connection"] : "default";
        $driver         = null;
        $this->events   = isset($args["events"]) ? $args["events"] : array();
        $hasFields      = isset($args["fields"]) && is_array($args["fields"]);

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
     * @param DataItem|array $item Data item
     * @param bool           $autoUpdate Create item if doesn't exists
     * @return DataItem
     * @throws \Exception
     */
    public function save($item, $autoUpdate = true)
    {
        $result = null;
        $this->allowSave = true;
        if (isset($this->events['onBeforeSave'])) {
            $this->secureEval($this->events['onBeforeSave'], array(
                'item' => &$item
            ));
        }
        if ($this->allowSave) {
            $result = $this->getDriver()->save($item, $autoUpdate);
        }

        if (isset($this->events['onAfterSave'])) {
            $this->secureEval($this->events['onAfterSave'], array(
                'item' => &$item
            ));
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
        $result = null;
        $this->allowRemove = true;
        if (isset($this->events['onBeforeRemove'])) {

            $this->secureEval($this->events['onBeforeRemove'], array(
                'args'   => &$args,
                'method' => 'remove'
            ));
        }
        if ($this->allowRemove) {
            $result = $this->getDriver()->remove($args);
        }
        if (isset($this->events['onAfterRemove'])) {
            $this->secureEval($this->events['onAfterRemove'], array(
                'args' => &$args

            ));
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
        if (isset($this->events['onBeforeSearch'])) {
            $this->secureEval($this->events['onBeforeSearch'], array(
                'criteria' => &$criteria
            ));
        }

        $results = $this->getDriver()->search($criteria);

        if (isset($this->events['onAfterSearch'])) {
            $this->secureEval($this->events['onAfterSearch'], array(
                'criteria' => &$criteria,
                'results' => &$results
            ));
        }

        return $results;
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
     * Get current driver instance
     *
     * @return IDriver|BaseDriver|DoctrineBaseDriver
     */
    public function getDriver()
    {
        return $this->driver;
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

    public function secureEval($code, array $args = array())
    {
        //extract($args);
        $context   = $this->container->get("security.context");
        $user      = $context->getToken()->getUser();
        $userRoles = $context->getRolesAsArray();
        $idKey     = $this->getDriver()->getUniqueId();

        foreach ($args as $key => &$value) {
            ${$key} = &$value;
        }
        if (isset($item)) {
            $originData = $this->get($item);
        }
        if (isset($args)) {
            $originData = $this->get($args);
        }

        $criteria['where'] = isset($criteria['where']) ? $criteria['where'] : '';
        $return            = eval($code);
        if ($return === false && ($errorMessage = error_get_last())) {
            throw new \Exception($errorMessage);
        }

    }

    /**
     * Prevent save item.
     * For event handling only.
     *
     * @param string $msg Prevent save message
     */
    protected function preventSave($msg = "")
    {
        $this->allowSave = false;
    }

    /**
     * Prevent remove item.
     * For event handling only.
     *
     * @param string $msg Prevent reason message
     */
    protected function preventRemove($msg = "")
    {
        $this->allowRemove = false;
    }
}