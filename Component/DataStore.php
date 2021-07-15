<?php
namespace Mapbender\DataSourceBundle\Component;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Mapbender\CoreBundle\Component\UploadsManager;
use Mapbender\DataSourceBundle\Component\Drivers\DoctrineBaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Component\Drivers\Oracle;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;
use Mapbender\DataSourceBundle\Component\Drivers\SQLite;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
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

    /** @var DoctrineBaseDriver */
    protected $driver;
    public    $events;
    protected $allowSave;
    protected $allowRemove;

    protected $parentField;
    protected $mapping;
    /** @var Connection|null */
    protected $connection;
    protected $connectionName;
    protected $connectionType;
    protected $fields;

    protected $uniqueIdFieldName = 'id';

    /** @var string SQL where filter */
    protected $sqlFilter;
    /** @var string|null */
    protected $tableName;

    /**
     * @var array file info list
     */
    protected $filesInfo = array();

    /**
     * @param ContainerInterface $container
     * @param array|null $args
     * @todo: drop container injection; replace with owning DataStoreService / FeatureTypeService injection
     */
    public function __construct(ContainerInterface $container, $args = array())
    {
        $this->container = $container;
        $this->filesystem = $container->get('filesystem');
        $this->connectionType = isset($args["type"]) ? $args["type"] : "doctrine";
        $this->connectionName = isset($args["connection"]) ? $args["connection"] : "default";
        if (!empty($args['table'])) {
            $this->tableName = $args['table'];
        }
        $this->events = isset($args["events"]) ? $args["events"] : array();
        $args = $this->lcfirstKeys($args ?: array());
        $this->configure($args);
        // @todo: lazy-init driver on first getDriver invocation
        $this->driver = $this->driverFactory($args);
    }

    protected function configure(array $args)
    {
        if (!empty($args['uniqueId'])) {
            $this->uniqueIdFieldName = $args['uniqueId'];
        }
        if (array_key_exists('filter', $args)) {
            $this->setFilter($args['filter']);
        }
        if (array_key_exists('mapping', $args)) {
            $this->setMapping($args['mapping']);
        }
        if (array_key_exists('parentField', $args)) {
            $this->setParentField($args['parentField']);
        }
        if (array_key_exists('files', $args)) {
            $this->setFiles($args['files']);
        }
        $unhandledArgs = array_diff_key($args, array_flip(array(
            'connection',
            'table',
            'uniqueId',
            'mapping',
            'parentField',
            'filter',
            'files',
        )));
        if ($unhandledArgs) {
            $this->configureMagic($unhandledArgs);
        }
    }

    /**
     * Handle remaining constructor arguments via magic setter inflection
     *
     * @param mixed[] $args
     * @deprecated remove in 0.2.0
     */
    private function configureMagic($args)
    {
        // @todo: drop magic setter invocations
        $methods = get_class_methods(get_class($this));
        foreach ($args as $key => $value) {
            $keyMethod = "set" . ucwords($key);
            if (in_array($keyMethod, $methods)) {
                @trigger_error("DEPRECATED: magic setter inflection in " . get_class($this) . " initialization, will be removed in 0.2.0. Override confiugure to explicitly handle all relevant values.", E_USER_DEPRECATED);
                $this->$keyMethod($value);
            }
        }
    }

    /**
     * @param array $args
     * @return DoctrineBaseDriver
     * @throws \Doctrine\DBAL\DBALException
     * @throws \RuntimeException on incompatible platform
     */
    protected function driverFactory(array $args)
    {
        // uniqueId ownership here
        unset($args['uniqueId']);
        $connection = $this->getConnection();

        $platformName = $connection->getDatabasePlatform()->getName();
        switch ($platformName) {
            case self::SQLITE_PLATFORM;
                $driver = new SQLite($connection, $this);
                break;
            case self::POSTGRESQL_PLATFORM;
                $driver = new PostgreSQL($connection, $this);
                break;
            case self::ORACLE_PLATFORM;
                $driver = new Oracle($connection, $this);
                break;
            default:
                throw new \RuntimeException("Unsupported DBAL platform " . print_r($platformName, true));
        }
        $this->fields = $this->initializeFields($driver, $args);
        return $driver;
    }

    protected function initializeFields(DoctrineBaseDriver $driver, $args)
    {
        if (!empty($args['fields'])) {
            if (!is_array($args['fields'])) {
                throw new \InvalidArgumentException("Unexpected type " . gettype($args['fields']) . " for 'fields'. Expected array.");
            }
            $fields = $args['fields'];
            if (!empty($args['parentField']) && !in_array($args['parentField'], $fields)) {
                $fields[] = $args['parentField'];
            }
            return $fields;
        } else {
            return $driver->getStoreFields();
        }
    }

    /**
     * @param integer|string $id
     * @return DataItem|null
     */
    public function getById($id)
    {
        $qb = $this->getSelectQueryBuilder()->setMaxResults(1);
        $qb->where($this->getUniqueId() . ' = :id');
        $qb->setParameter(':id', $id);
        $items = $this->prepareResults($qb->execute()->fetchAll());
        if ($items) {
            return $items[0];
        } else {
            return null;
        }
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Get parent by child ID
     *
     * @param integer|string $id
     * @return DataItem|null
     */
    public function getParent($id)
    {
        $dataItem = $this->getById($id);
        $queryBuilder = $this->getSelectQueryBuilder();
        $queryBuilder->andWhere($this->getUniqueId() . " = " . $dataItem->getAttribute($this->getParentField()));
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
     * @deprecated
     * @todo 0.2: remove this method
     */
    public function getTree($parentId = null, $recursive = true)
    {
        $queryBuilder = $this->getSelectQueryBuilder();
        if ($parentId === null) {
            $queryBuilder->andWhere($this->getParentField() . " IS NULL");
        } else {
            $queryBuilder->andWhere($this->getParentField() . " = " . $parentId);
        }
        $statement  = $queryBuilder->execute();
        $rows = $this->prepareResults($statement->fetchAll());

        if ($recursive) {
            foreach ($rows as $dataItem) {
                $dataItem->setChildren($this->getTree($dataItem->getId(), $recursive));
            }
        }

        return $rows;
    }

    /**
     * Promote mostly random input data to a DataItem, or return a passed DataItem as-is, unmodified
     *
     * @param DataItem|object|int|array $data
     * @return DataItem
     * @todo 0.2.0: remove unmodified object pass-through
     * @todo 0.2.0: remove the path for all hope is lost, let's just call get_object_vars
     * @todo 0.2.0: remove the path for scalar id pre-initialization
     */
    public function create($data)
    {
        if (is_object($data)) {
            $referenceClassName = \get_class($this->itemFactory());
            if (\is_a($data, $referenceClassName, true)) {
                @trigger_error("Deprecated: do not call create AT ALL if you know you're working with a {$referenceClassName}. This will be an error in 0.2.0. Use the object you already have.", E_USER_DEPRECATED);
                return $data;
            } else {
                @trigger_error("Deprecated: do not call create with a random unrecognized object type. This will be an error in 0.2.0. Bring your own attributes and call itemFromArray.", E_USER_DEPRECATED);
                return $this->itemFromArray(get_object_vars($data));
            }
        } elseif (is_numeric($data)) {
            @trigger_error("Deprecated: do not call create with a scalar to preinitialize the item id. This will be an error in 0.2.0.", E_USER_DEPRECATED);
            return $this->itemFromArray(array(
                $this->getUniqueId() => $data,
            ));
        } else {
            return $this->itemFromArray($data);
        }
    }

    /**
     * Create empty item
     *
     * @return DataItem
     * @since 0.1.16.2
     */
    public function itemFactory()
    {
        return new DataItem(array(), $this->getUniqueId());
    }

    /**
     * Create preinitialized item
     *
     * @param array $values
     * @return DataItem
     * @since 0.1.16.2
     */
    public function itemFromArray(array $values)
    {
        /**
         * NOTE: we deliberatly AVOID using itemFactory here because of the absolutely irritating matrix
         * of potential constructor initializer types
         * @sse DataItem::__construct
         */
        $item = new DataItem($values, $this->getUniqueId());
        return $item;
    }

    /**
     * @param DataItem $item
     * @param DataItem|array|mixed $dataArg original value passed to save method
     * @return array
     */
    protected function getSaveEventData(DataItem $item, &$dataArg)
    {
        // legacy quirk originData:
        // 1) for inserts (no id), provide a blank, empty, DataItem object (like ->get(array()))
        // 2) for updates, reload the original item (incoming item already carries new data!)
        if ($item->getId()) {
            $originData = $this->reloadItem($item);
        } else {
            $originData = $this->itemFactory();
        }

        return array(
            'item' => &$dataArg,
            'originData' => $originData,
        );
    }

    /**
     * @param DataItem $item
     * @return mixed[]
     */
    protected function getSaveData(DataItem $item)
    {
        $data = $item->toArray();
        if ($this->cleanDataOnSave()) {
            $data = $this->cleanSaveData($data);
        }
        return $data;
    }

    /**
     * Removes values from $data array that cannot be saved.
     *
     * @param mixed[] $data
     * @return mixed[]
     * @todo 0.2: remove data cleaning; configurations containing invalid data fields need fixing, and they should absolutely cause errors
     */
    protected function cleanSaveData($data)
    {
        $removeFields = array_diff(array_keys($data), $this->fields);
        foreach ($removeFields as $removeField) {
            unset($data[$removeField]);
        }
        return $data;
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
        if (!is_array($item) && !is_object($item)) {
            throw new \Exception("Feature data given isn't compatible to save into the table: " . $this->getTableName());
        }

        $saveItem = $this->create($item);
        if (isset($this->events[self::EVENT_ON_BEFORE_SAVE]) || isset($this->events[self::EVENT_ON_AFTER_SAVE])) {
            $eventData = $this->getSaveEventData($saveItem, $item);
        } else {
            $eventData = null;
        }

        $this->allowSave = true;

        if (isset($this->events[self::EVENT_ON_BEFORE_SAVE])) {
            $this->secureEval($this->events[self::EVENT_ON_BEFORE_SAVE], $eventData);
        }
        if ($this->allowSave) {
            if (!$autoUpdate || !$saveItem->getId()) {
                $this->insertItem($saveItem);
            } else {
                $this->updateItem($saveItem);
            }
        }

        if (isset($this->events[ self::EVENT_ON_AFTER_SAVE ])) {
            $this->secureEval($this->events[self::EVENT_ON_AFTER_SAVE], $eventData);
        }
        return $this->reloadItem($saveItem);
    }

    /**
     * @param DataItem $item
     * @return DataItem|null
     */
    protected function reloadItem($item)
    {
        return $this->getById($item->getId());
    }

    /**
     * Insert new row
     *
     * @param array|DataItem $itemOrData
     * @return DataItem
     */
    public function insert($itemOrData)
    {
        return $this->insertItem($this->create($itemOrData));
    }

    /**
     * @param DataItem $item
     * @return DataItem
     */
    protected function insertItem(DataItem $item)
    {
        $values = $this->getSaveData($item);
        $idName = $this->getUniqueId();
        unset($values[$idName]);
        $id = $this->getDriver()->insert($this->getTableName(), $values);
        $item->setId($id);
        return $item;
    }

    /**
     * Update existing row
     *
     * @param array|DataItem $itemOrData
     * @return DataItem
     */
    public function update($itemOrData)
    {
        $item = $this->create($itemOrData);
        return $this->updateItem($item);
    }

    /**
     * @param DataItem $item
     * @return DataItem
     * @throws \Doctrine\DBAL\DBALException
     */
    public function updateItem(DataItem $item)
    {
        $data = $this->getSaveData($item);
        $identifier = array(
            $this->getUniqueId() => $item->getId(),
        );
        $this->getDriver()->update($this->getTableName(), $data, $identifier);
        return $item;
    }

    protected function cleanDataOnSave()
    {
        return true;
    }

    /**
     * Remove data item
     * @param mixed $args
     * @return int number of deleted rows
     */
    public function remove($args)
    {
        $itemId = $this->anythingToId($args);
        if (isset($this->events[self::EVENT_ON_BEFORE_REMOVE]) || isset($this->events[self::EVENT_ON_AFTER_REMOVE])) {
            // uh-oh
            $item = $this->getById($itemId);
            $eventData = array(
                'args' => &$args,
                'method' => 'remove',
                'originData' => $item,
            );
        } else {
            $eventData = null;
        }
        $result = null;
        $this->allowRemove = true;
        if (isset($this->events[ self::EVENT_ON_BEFORE_REMOVE ])) {
            $this->secureEval($this->events[self::EVENT_ON_BEFORE_REMOVE], $eventData);
        }
        if ($this->allowRemove) {
            $result = !!$this->getDriver()->delete($this->getTableName(), $this->idToIdentifier($itemId));
        }
        if (isset($this->events[self::EVENT_ON_AFTER_REMOVE])) {
            $this->secureEval($this->events[self::EVENT_ON_AFTER_REMOVE], $eventData);
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

        if (!empty($criteria['maxResults'])) {
            $queryBuilder->setMaxResults(intval($criteria['maxResults']));
        }

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
     */
    public function getUniqueId()
    {
        return $this->uniqueIdFieldName;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
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
     * @param array $fields
     * @return QueryBuilder
     */
    public function getSelectQueryBuilder(array $fields = array())
    {
        $connection = $this->getConnection();
        $qb = $connection->createQueryBuilder();
        $qb->from($this->getTableName(), 't');
        $fields = array_merge($this->getFields(), $fields);
        $fields = array_merge(array($this->getUniqueId()), $fields);

        foreach ($fields as $field) {
            if (is_array($field)) {
                // @todo: specify, document
                $alias = current(array_keys($field));
                $expression = current(array_values($field));
                $qb->addSelect("$expression AS " . $connection->quoteIdentifier($alias));
            } else {
                // Quote fields, unless they are expressions.
                // Bare-bones detection for
                // * literal * (as in SELECT * FROM ...)
                // * SQL functions (round brackets)
                // * String literals
                // * Pre-quoted identifiers (Backtick on MySQL, double-quote on PostgreSQL)
                if (!preg_match('#["\'`()]#', $field) && $field !== '*') {
                    $field = $connection->quoteIdentifier($field);
                }
                $qb->addSelect($field);
            }
        }

        return $qb;
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
     * @return DoctrineBaseDriver|Geographic
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
     * Get by argument. ID of given DataItem or configured id column name entry in given array, or scalar id
     * @param DataItem|array|string|integer
     * @return DataItem
     */
    public function get($args)
    {
        if ($id = $this->anythingToId($args)) {
            return $this->getById($id);
        } else {
            return $this->create($args);    // uh-oh
        }
    }

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatformName()
    {
        return $this->getConnection()->getDatabasePlatform()->getName();
    }

    /**
     * Get DBAL Connection
     *
     * @return Connection
     */
    public function getConnection()
    {
        if (!$this->connection) {
            $this->connection = $this->getDbalConnectionByName($this->connectionName);
        }
        return $this->connection;
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
        $idKey = $this->getUniqueId();
        $connection = $this->getConnection();

        foreach ($args as $key => &$value) {
            ${$key} = &$value;
        }

        $return = eval($code);

        if ($return === false && ($errorDetails = error_get_last())) {
            $lastError = end($errorDetails);
            throw new \Exception($lastError["message"], $lastError["type"]);
        }

    }

    /**
     * @param mixed $parentField
     * @deprecated
     * @todo 0.2: remove this method
     */
    public function setParentField($parentField)
    {
        $this->parentField = $parentField;
    }

    /**
     * @return mixed
     * @deprecated
     * @todo 0.2: remove this method
     */
    public function getParentField()
    {
        return $this->parentField;
    }

    /**
     * @param mixed $mapping
     * @return $this
     * @deprecated
     * @todo 0.2: remove this method
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
     * @deprecated
     * @todo 0.2: remove this method
     */
    public function getTroughMapping($mappingId, $id)
    {
        $config            = $this->mapping[ $mappingId ];
        // This right here breaks Element-level customization
        // The parent ~registry (using Doctrine lingo) should be known to
        // each DataStore and FeatureType
        // @todo: inject DataStoreService / FeatureTypeService into DataStore / FeatureType
        //        objects
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

        $criteria = $this->getById($id)->getAttribute($internalFieldName);

        $queryBuilder = $externalDataStore->getSelectQueryBuilder();
        $queryBuilder->where($externalFieldName . " = :criteria");
        $queryBuilder->setParameter('criteria', $criteria);

        $statement = $queryBuilder->execute();
        return $this->prepareResults($statement->fetchAll());
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

    /**
     * Lower-cases the first letter in each key of given array. This is for BC
     * with magic setter inflection where each key is run through ucwords. E.g. a
     * config value 'Fields' would invoke the same setter as 'fields'.
     * Method emits a deprecation warning when this occurs.
     *
     * @param mixed[] $args
     * @return mixed[]
     * @deprecated remove in 0.2.0 along with magic argument handling
     */
    private function lcfirstKeys(array $args)
    {
        $argsOut = array_combine(array_map('\lcfirst', array_keys($args)), array_values($args));
        $modifiedKeys = array_diff(array_keys($args), array_keys($argsOut));
        if ($modifiedKeys) {
            @trigger_error("DEPRECATED: passed miscapitalized config key(s) " . implode(', ', $modifiedKeys) . ' to ' . get_class($this) . '. This will be an error in 0.2.0', E_USER_DEPRECATED);
        }
        return $argsOut;
    }

    /**
     * @param string $name
     * @return Connection
     * @internal
     * @todo: after injecting owning DataStoreService / FeatureType, remove this
     *        method and delegate to equivalent (but public) owner method
     */
    protected function getDbalConnectionByName($name)
    {
        /** @var RegistryInterface $registry */
        $registry = $this->container->get('doctrine');
        /** @var Connection $connection */
        $connection = $registry->getConnection($name);
        return $connection;
    }

    /**
     * @param mixed $id
     * @return array
     */
    private function idToIdentifier($id)
    {
        $uniqueId = $this->getUniqueId();
        return array($uniqueId => $id);
    }

    /**
     * Attempts to extract an id from whatever $arg is
     * Completely equivalent to DataStore::create($arg)->getId()
     *
     * @param mixed $arg
     * @return integer|null
     */
    private function anythingToId($arg)
    {
        if (\is_numeric($arg)) {
            return $arg;
        } elseif (\is_object($arg)) {
            if ($arg instanceof DataItem) {
                return $arg->getId();
            } else {
                // self-delegate to array path
                return $this->anythingToId(\get_object_vars($arg));
            }
        } elseif (\is_array($arg)) {
            $uniqueId = $this->getUniqueId();
            if (!empty($arg[$uniqueId])) {
                return $arg[$uniqueId];
            }
        }
        // uh-oh!
        return null;
    }

    /**
     * Get files directory, relative to base upload directory
     *
     * @param null $fieldName
     * @return string
     */
    public function getFileUri($fieldName = null)
    {
        $path = $this->getUploadsDirectoryName() . "/" . $this->getTableName();

        if ($fieldName) {
            $path .= "/" . $fieldName;
        }

        foreach ($this->getFileInfo() as $fileInfo) {
            if (isset($fileInfo["field"]) && isset($fileInfo["uri"]) && $fieldName == $fileInfo["field"]) {
                $path = $fileInfo["uri"];
                break;
            }
        }

        return $path;
    }

    /**
     * Get files base path
     *
     * @param null $fieldName  file field name
     * @param bool $createPath check and create path?
     * @return string
     * @deprecated remove in 0.2.0
     */
    public function getFilePath($fieldName = null, $createPath = true)
    {
        foreach ($this->getFileInfo() as $fileInfo) {
            if (isset($fileInfo["field"]) && isset($fileInfo["path"]) && $fieldName == $fileInfo["field"]) {
                $path = $fileInfo["path"];
                if ($createPath && !is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                return $path;
            }
        }
        $fileUri = $this->getFileUri($fieldName);
        return $this->getUploadsManager()->getSubdirectoryPath($fileUri, $createPath);
    }

    /**
     * @param string $fieldName
     * @return string
     * @deprecated remove in 0.2.0
     */
    public function getFileUrl($fieldName = "")
    {
        $fileUri = $this->getFileUri($fieldName);
        if ($this->filesystem->isAbsolutePath($fileUri)) {
            return $fileUri;
        } else {
            /** @var Request $request */
            $request = $this->container->get('request_stack')->getCurrentRequest();
            $baseUrl = implode('', array(
                $request->getSchemeAndHttpHost(),
                $request->getBasePath(),
            ));
            foreach ($this->getFileInfo() as $fileInfo) {
                if (isset($fileInfo["field"]) && isset($fileInfo["uri"]) && $fieldName == $fileInfo["field"]) {
                    return "{$baseUrl}/{$fileUri}";
                }
            }
            $uploadsDir = $this->getUploadsManager()->getWebRelativeBasePath(false);
            return "{$baseUrl}/{$uploadsDir}/{$fileUri}";
        }
    }

    /**
     * @param array[] $fileInfo
     * @internal param $fileInfos
     */
    public function setFiles($fileInfo)
    {
        $this->filesInfo = $fileInfo;
    }

    /**
     * @return array[]
     */
    public function getFileInfo()
    {
        return $this->filesInfo;
    }
    /**
     * @return string
     */
    public function getUploadsDirectoryName()
    {
        return "ds-uploads";
    }
}
