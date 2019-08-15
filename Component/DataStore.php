<?php
namespace Mapbender\DataSourceBundle\Component;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use Mapbender\CoreBundle\Component\UploadsManager;
use Mapbender\DataSourceBundle\Component\Drivers\BaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\DoctrineBaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Base;
use Mapbender\DataSourceBundle\Component\Drivers\Oracle;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;
use Mapbender\DataSourceBundle\Component\Drivers\SQLite;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Class DataSource
 *
 * Data source manager.
 *
 * @package Mapbender\DataSourceBundle
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DataStore
{
    const ORACLE_PLATFORM        = 'oracle';
    const POSTGRESQL_PLATFORM    = 'postgresql';
    const SQLITE_PLATFORM        = 'sqlite';

    /**
     * Eval events
     */
    const EVENT_ON_AFTER_SAVE    = 'onAfterSave';
    const EVENT_ON_BEFORE_SAVE   = 'onBeforeSave';
    const EVENT_ON_BEFORE_REMOVE = 'onBeforeRemove';
    const EVENT_ON_AFTER_REMOVE  = 'onAfterRemove';
    const EVENT_ON_BEFORE_SEARCH = 'onBeforeSearch';
    const EVENT_ON_AFTER_SEARCH  = 'onAfterSearch';

    /** @var ContainerInterface */
    protected $container;
    /** @var Filesystem */
    protected $filesystem;

    /** @var Base */
    protected $driver;
    public    $events;
    protected $allowSave;
    protected $allowRemove;

    protected $parentField;
    protected $mapping;
    protected $connectionName;
    protected $connectionType;
    protected $fields;


    /**
     * @param ContainerInterface $container
     * @param null               $args
     */
    public function __construct(ContainerInterface $container, $args = null)
    {
        $this->container = $container;
        $this->filesystem = $container->get('filesystem');
        $type           = isset($args["type"]) ? $args["type"] : "doctrine";
        $connectionName = isset($args["connection"]) ? $args["connection"] : "default";
        $this->events   = isset($args["events"]) ? $args["events"] : array();
        $hasFields      = isset($args["fields"]) && is_array($args["fields"]);

        $this->connectionName = $connectionName;
        $this->connectionType = $type;

        if ($hasFields && isset($args["parentField"])) {
            $args["fields"][] = $args["parentField"];
        }

        // init $methods by $args
        if (is_array($args)) {
            $methods = get_class_methods(get_class($this));
            foreach ($args as $key => $value) {
                $keyMethod = "set" . ucwords($key);
                if($key == "fields"){
                    continue;
                }
                if (in_array($keyMethod, $methods)) {
                    $this->$keyMethod($value);
                }
            }
        }

        /** @var Connection $connection */
        $connection = $container->get("doctrine.dbal.{$connectionName}_connection");
        switch ($connection->getDatabasePlatform()->getName()) {
            case self::SQLITE_PLATFORM;
                $this->driver = new SQLite($connection, $args);
                break;
            case self::POSTGRESQL_PLATFORM;
                $this->driver = new PostgreSQL($connection, $args);
                break;
            case self::ORACLE_PLATFORM;
                $this->driver = new Oracle($connection, $args);
                break;
        }
        if (!$hasFields) {
            $this->driver->setFields($this->driver->getStoreFields());
        } else {
            $this->driver->setFields($args["fields"]);
        }
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
     * @return DataItem|null
     */
    public function getParent($id)
    {
        /** @var Statement $statement */
        $dataItem     = $this->get($id);
        $queryBuilder = $this->driver->getSelectQueryBuilder();
        $queryBuilder->andWhere($this->driver->getUniqueId() . " = " . $dataItem->getAttribute($this->getParentField()));
        $queryBuilder->setMaxResults(1);
        $statement  = $queryBuilder->execute();
        $rows = $this->driver->prepareResults($statement->fetchAll());
        if ($rows) {
            return $rows[0];
        } else {
            return null;
        }
    }

    /**
     * Get tree
     *
     * @param null|int $parentId  Parent ID
     * @param bool     $recursive Recursive [true|false]
     * @return DataItem[]
     */
    public function getTree($parentId = null, $recursive = true)
    {
        $queryBuilder = $this->driver->getSelectQueryBuilder();
        if ($parentId === null) {
            $queryBuilder->andWhere($this->getParentField() . " IS NULL");
        } else {
            $queryBuilder->andWhere($this->getParentField() . " = " . $parentId);
        }
        $statement  = $queryBuilder->execute();
        $rows = $this->driver->prepareResults($statement->fetchAll());

        if ($recursive) {
            /** @var DataItem $dataItem */
            foreach ($rows as $dataItem) {
                $dataItem->setChildren($this->getTree($dataItem->getId(), $recursive));
            }
        }

        return $rows;
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
        if (isset($this->events[ self::EVENT_ON_BEFORE_SAVE ])) {
            $this->secureEval($this->events[ self::EVENT_ON_BEFORE_SAVE ], array(
                'item' => &$item
            ));
        }
        if ($this->allowSave) {
            $result = $this->getDriver()->save($item, $autoUpdate);
        }

        if (isset($this->events[ self::EVENT_ON_AFTER_SAVE ])) {
            $this->secureEval($this->events[ self::EVENT_ON_AFTER_SAVE ], array(
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
        if (isset($this->events[ self::EVENT_ON_BEFORE_REMOVE ])) {

            $this->secureEval($this->events[ self::EVENT_ON_BEFORE_REMOVE ], array(
                'args'   => &$args,
                'method' => 'remove'
            ));
        }
        if ($this->allowRemove) {
            $result = $this->getDriver()->remove($args);
        }
        if (isset($this->events[ self::EVENT_ON_AFTER_REMOVE ])) {
            $this->secureEval($this->events[ self::EVENT_ON_AFTER_REMOVE ], array(
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
        $criteria['where'] = isset($criteria['where']) ? $criteria['where'] : '';
        if (isset($this->events[ self::EVENT_ON_BEFORE_SEARCH ])) {
            $this->secureEval($this->events[ self::EVENT_ON_BEFORE_SEARCH ], array(
                'criteria' => &$criteria
            ));
        }

        $results = $this->getDriver()->search($criteria);

        if (isset($this->events[ self::EVENT_ON_AFTER_SEARCH ])) {
            $this->secureEval($this->events[ self::EVENT_ON_AFTER_SEARCH ], array(
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
     * @return Base|BaseDriver|DoctrineBaseDriver|PostgreSQL
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

    /**
     * @param       $code
     * @param array $args
     * @throws \Exception
     */
    public function secureEval($code, array $args = array())
    {
        //extract($args);
        /** @var AuthorizationCheckerInterface $context */
        $context    = $this->container->get("security.authorization_checker");
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->container->get('security.token_storage');
        $user       = $tokenStorage->getToken()->getUser();
        $userRoles = array();
        foreach ($tokenStorage->getToken()->getRoles() as $role) {
            $roles[] = $role->getRole();
        }
        $idKey      = $this->getDriver()->getUniqueId();
        $connection = $this->getConnection();

        foreach ($args as $key => &$value) {
            ${$key} = &$value;
        }
        if (isset($item)) {
            $originData = $this->get($item);
        }
        if (isset($args)) {
            $originData = $this->get($args);
        }

        $return = eval($code);

        if ($return === false && ($errorDetails = error_get_last())) {
            $lastError = end($errorDetails);
            throw new \Exception($lastError["message"], $lastError["type"]);
        }

    }

    /**
     * @param mixed $parentField
     */
    public function setParentField($parentField)
    {
        $this->parentField = $parentField;
    }

    /**
     * @return mixed
     */
    public function getParentField()
    {
        return $this->parentField;
    }

    /**
     * @param mixed $mapping
     * @return DataStore
     */
    public function setMapping($mapping)
    {
        $this->mapping = $mapping;
        return $this;
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

    /**
     * Get related objects through mapping
     *
     * @param $mappingId
     * @param $id
     * @return DataItem[]
     */
    public function getTroughMapping($mappingId, $id)
    {
        $config            = $this->mapping[ $mappingId ];
        /** @var DataStoreService $dataStoreService */
        $dataStoreService  = $this->container->get("data.source");
        $externalDataStore = $dataStoreService->get($config["externalDataStore"]);
        $externalDriver    = $externalDataStore->getDriver();
        $internalFieldName = null;
        $externalFieldName = null;

        if (isset($config['internalId'])) {
            $internalFieldName = $config['internalId'];
        }

        if (isset($config['externalId'])) {
            $externalFieldName = $config['externalId'];
        }

        if (isset($config['internalFieldName'])) {
            $internalFieldName = $config['internalFieldName'];
        }

        if (isset($config['relatedFieldName'])) {
            $externalFieldName = $config['relatedFieldName'];
        }

        if (!$externalDriver instanceof DoctrineBaseDriver) {
            throw new Exception('This kind of externalDriver can\'t get relations');
        }

        $criteria = $this->get($id)->getAttribute($internalFieldName);

        return $externalDriver->getByCriteria($criteria, $externalFieldName);
    }

    /**
     * @return UploadsManager
     */
    protected function getUploadsManager()
    {
        /** @var UploadsManager $ulm */
        $ulm = $this->container->get('mapbender.uploads_manager.service');
        return $ulm;
    }
}