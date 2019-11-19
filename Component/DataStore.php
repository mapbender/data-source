<?php
namespace Mapbender\DataSourceBundle\Component;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
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

    /** @var string SQL where filter */
    protected $sqlFilter;

    /**
     * @param ContainerInterface $container
     * @param array|null $args
     */
    public function __construct(ContainerInterface $container, $args = array())
    {
        $this->container = $container;
        $this->filesystem = $container->get('filesystem');
        $this->connectionType = isset($args["type"]) ? $args["type"] : "doctrine";
        $this->connectionName = isset($args["connection"]) ? $args["connection"] : "default";
        $this->events   = isset($args["events"]) ? $args["events"] : array();
        $this->configure($args ?: array());
        // @todo: lazy-init driver on first getDriver invocation
        $this->driver = $this->driverFactory($args ?: array());
    }

    protected function configure(array $args)
    {
        // @todo: drop magic setter invocations
        $methods = get_class_methods(get_class($this));
        foreach ($args as $key => $value) {
            $keyMethod = "set" . ucwords($key);
            if (in_array($keyMethod, $methods)) {
                $this->$keyMethod($value);
            }
        }
    }

    /**
     * @param array $args
     * @return Base|DoctrineBaseDriver
     * @throws \Doctrine\DBAL\DBALException
     * @throws \RuntimeException on incompatible platform
     */
    protected function driverFactory(array $args)
    {
        $hasFields = isset($args["fields"]) && is_array($args["fields"]);

        if ($hasFields && isset($args["parentField"])) {
            $args["fields"][] = $args["parentField"];
        }

        /** @var Connection $connection */
        $connection = $this->container->get("doctrine.dbal.{$this->connectionName}_connection");
        $platformName = $connection->getDatabasePlatform()->getName();
        switch ($connection->getDatabasePlatform()->getName()) {
            case self::SQLITE_PLATFORM;
                $driver = new SQLite($connection, $args);
                break;
            case self::POSTGRESQL_PLATFORM;
                $driver = new PostgreSQL($connection, $args);
                break;
            case self::ORACLE_PLATFORM;
                $driver = new Oracle($connection, $args);
                break;
            default:
                throw new \RuntimeException("Unsupported DBAL platform " . print_r($platformName, true));
        }
        if (!empty($args['fields'])) {
            if (!is_array($args['fields'])) {
                throw new \InvalidArgumentException("Unexpected type " . gettype($args['fields']) . " for 'fields'. Expected array.");
            }
            $fields = $args['fields'];
            if (!empty($args['parentField']) && !in_array($args['parentField'], $fields)) {
                $fields[] = $args['parentField'];
            }
            $driver->setFields($fields);
        } else {
            $driver->setFields($driver->getStoreFields());
        }
        return $driver;
    }

    /**
     * @param integer|string $id
     * @return DataItem
     * @todo: the implementation belongs here, not in the driver
     */
    public function getById($id)
    {
        return $this->getDriver()->getById($id);
    }

    /**
     * @return array
     * @todo: this information belongs here, not in the driver
     */
    public function getFields()
    {
        return $this->getDriver()->getFields();
    }

    /**
     * Get parent by child ID
     *
     * @param integer|string $id
     * @return DataItem|null
     */
    public function getParent($id)
    {
        $driver = $this->getDriver();
        $dataItem = $this->get($id);
        $queryBuilder = $driver->getSelectQueryBuilder();
        $queryBuilder->andWhere($driver->getUniqueId() . " = " . $dataItem->getAttribute($this->getParentField()));
        $queryBuilder->setMaxResults(1);
        $statement  = $queryBuilder->execute();
        $rows = $this->prepareResults($statement->fetchAll());
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
        $driver = $this->getDriver();
        $queryBuilder = $driver->getSelectQueryBuilder();
        if ($parentId === null) {
            $queryBuilder->andWhere($this->getParentField() . " IS NULL");
        } else {
            $queryBuilder->andWhere($this->getParentField() . " = " . $parentId);
        }
        $statement  = $queryBuilder->execute();
        $rows = $this->prepareResults($statement->fetchAll());

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
     * @param mixed $data
     * @return DataItem
     * @todo: the implementation belongs here, not in the driver
     */
    public function create($data)
    {
        return $this->getDriver()->create($data);
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
        $queryBuilder = $this->getSelectQueryBuilder();

        $this->addCustomSearchCritera($queryBuilder, $criteria);

        // @todo: support unlimited selects
        $maxResults = isset($criteria['maxResults']) ? intval($criteria['maxResults']) : DoctrineBaseDriver::MAX_RESULTS;
        $queryBuilder->setMaxResults($maxResults);
        $statement  = $queryBuilder->execute();
        $results = $this->prepareResults($statement->fetchAll());

        if (isset($this->events[ self::EVENT_ON_AFTER_SEARCH ])) {
            $this->secureEval($this->events[ self::EVENT_ON_AFTER_SEARCH ], array(
                'criteria' => &$criteria,
                'results' => &$results
            ));
        }

        return $results;
    }

    /**
     * Get unique ID field name
     *
     * @return string
     * @todo: this information belongs HERE, not in the driver
     */
    public function getUniqueId()
    {
        return $this->getDriver()->getUniqueId();
    }

    /**
     * @return string
     * @todo: this information belongs here, not in the driver
     */
    public function getTableName()
    {
        return $this->getDriver()->getTableName();
    }

    /**
     * Add custom (non-Doctrineish) criteria to passed query builder.
     * Override hook for customization
     *
     * @param QueryBuilder $queryBuilder
     * @param array $params
     */
    protected function addCustomSearchCritera(QueryBuilder $queryBuilder, array $params)
    {
        // add filter (dead link https://trac.wheregroup.com/cp/issues/3733)
        // @todo: specify and document
        if (!empty($this->sqlFilter)) {
            if (preg_match('#:userName([^_\w\d]|$)#', $this->sqlFilter)) {
                /** @var TokenStorageInterface $tokenStorage */
                $tokenStorage = $this->container->get("security.token_storage");
                $queryBuilder->setParameter(':userName', $tokenStorage->getToken()->getUsername());
            }
            $queryBuilder->andWhere($this->sqlFilter);
        }
        // add second filter (dead link https://trac.wheregroup.com/cp/issues/4643)
        // @ŧodo: specify and document
        if (!empty($params['where'])) {
            $queryBuilder->andWhere($params['where']);
        }
    }

    /**
     * Convert database rows to DataItem objects
     *
     * @param array[] $rows
     * @return DataItem[]
     */
    public function prepareResults($rows)
    {
        $uniqueId = $this->getUniqueId();
        $items = array();
        foreach ($rows as $key => $row) {
            $item = new DataItem(array(), $uniqueId);
            $item->setAttributes($row);
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Get query builder prepared to select from the source table
     *
     * @return QueryBuilder
     * @todo: add select fields here, not in driver
     */
    public function getSelectQueryBuilder()
    {
        return $this->getDriver()->getSelectQueryBuilder();
    }

    /**
     * @return bool
     * @deprecated
     */
    public function isOracle()
    {
        return ($this->getDriver()) instanceof Oracle;
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
     * @deprecated
     */
    public function isSqlite()
    {
        return ($this->getDriver()) instanceof SQLite;
    }

    /**
     * Is postgres platform
     *
     * @return bool
     * @deprecated
     */
    public function isPostgres()
    {
        return ($this->getDriver()) instanceof PostgreSQL;
    }

    /**
     * Get driver types
     *
     * @return array
     */
    public function getTypes()
    {
        return array(
            self::POSTGRESQL_PLATFORM,
            self::ORACLE_PLATFORM,
            self::SQLITE_PLATFORM,
        );
    }

    /**
     * Get by argument
     *
     * @inheritdoc
     */
    public function get($args)
    {
        return $this->getDriver()->get($args);
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

    /** @noinspection PhpUnused */
    /**
     * Set permanent SQL filter used by $this->search()
     * https://trac.wheregroup.com/cp/issues/3733
     *
     * @see $this->search()
     * @param string $sqlFilter
     * NOTE: magic setter invocation; expected config value comes with key 'filter'
     */
    protected function setFilter($sqlFilter)
    {
        if ($sqlFilter) {
            // unquote quoted parameter references
            // we use parameter binding
            $filtered = preg_replace('#([\\\'"])(:[\w\d_]+)(\\1)#', '\\2', $sqlFilter);
            if ($filtered !== $sqlFilter) {
                @trigger_error("DEPRECATED: DO NOT quote parameter references in sql filter configuration", E_USER_DEPRECATED);
            }
            $sqlFilter = $filtered;
        }
        $this->sqlFilter = $sqlFilter;
    }

    /**
     * @param string $code
     * @param array $args
     * @throws \Exception
     * @todo: stop using eval already
     */
    public function secureEval($code, array $args = array())
    {
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
     * @return $this
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
     * @param string $mappingId
     * @param integer|string $id
     * @return DataItem[]
     * @todo: figure out who uses this
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
