<?php
namespace Mapbender\DataSourceBundle\Component;

use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Component\Drivers\Oracle;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;
use Mapbender\DataSourceBundle\Component\Drivers\SQLite;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DataStore extends EventAwareDataRepository
{
    /** @var ContainerInterface */
    protected $container;

    /**
     * @param ContainerInterface $container
     * @param Connection $connection
     * @param array|null $args
     * @todo: drop container injection; replace with owning DataStoreService / FeatureTypeService injection
     */
    public function __construct(ContainerInterface $container, Connection $connection, $args = array())
    {
        $eventConfig = isset($args["events"]) ? $args["events"] : array();
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $container->get('security.token_storage');
        /** @var EventProcessor $eventProcessor */
        $eventProcessor = $container->get('mbds.default_event_processor');
        $filter = (!empty($args['filter'])) ? $args['filter'] : null;
        parent::__construct($connection, $tokenStorage, $eventProcessor, $eventConfig, $args['table'], $args['uniqueId'], $filter);

        // Rest
        $this->container = $container;
        $this->configure($args);
        $this->fields = $this->initializeFields($args);
    }

    protected function configure(array $args)
    {
    }

    protected function initializeFields($args)
    {
        $platform = $this->connection->getDatabasePlatform();
        if (isset($args['fields'])) {
            if (!is_array($args['fields'])) {
                throw new \InvalidArgumentException("Unexpected type " . gettype($args['fields']) . " for 'fields'. Expected array.");
            }
            $names = $args['fields'];
        } else {
            $names = array();
            foreach ($this->getTableMetaData()->getColumNames() as $columnName) {
                $names[] = \strtolower($columnName);
            }
        }
        $fields = array();
        foreach ($names as $name) {
            $fields[$platform->getSQLResultCasing($name)] = $name;
        }
        if (!\in_array($this->uniqueIdFieldName, $names, true)) {
            $fields[$platform->getSQLResultCasing($this->uniqueIdFieldName)] = $this->uniqueIdFieldName;
        }
        return $fields;
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
     * Save data item. Auto-inflects to insert (no id) or update (non-empty id).
     *
     * @param DataItem|array $item Data item
     * @return DataItem
     * @throws \Exception
     */
    public function save($item)
    {
        if (!is_array($item) && !is_object($item)) {
            throw new \Exception("Feature data given isn't compatible to save into the table: " . $this->getTableName());
        }

        $saveItem = $this->create($item);
        if (isset($this->events[self::EVENT_ON_BEFORE_SAVE]) || isset($this->events[self::EVENT_ON_AFTER_SAVE])) {
            $eventData = $this->getSaveEventData($saveItem);
        } else {
            $eventData = null;
        }

        if (isset($this->events[self::EVENT_ON_BEFORE_SAVE])) {
            $this->eventProcessor->runExpression($this->events[self::EVENT_ON_BEFORE_SAVE], $eventData);
            $runSave = $this->eventProcessor->allowSave;
        } else {
            $runSave = true;
        }
        if ($runSave) {
            if (!$saveItem->getId()) {
                $this->insertItem($saveItem);
            } else {
                $this->updateItem($saveItem);
            }
        }

        if (isset($this->events[self::EVENT_ON_AFTER_SAVE])) {
            $this->eventProcessor->runExpression($this->events[self::EVENT_ON_AFTER_SAVE], $eventData);
        }
        return $saveItem;
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
            $eventData = $this->getCommonEventData() + array(
                'args' => &$args,
                'method' => 'remove',
                'originData' => $item,
            );
        } else {
            $eventData = null;
        }
        if (isset($this->events[ self::EVENT_ON_BEFORE_REMOVE ])) {
            $this->eventProcessor->runExpression($this->events[self::EVENT_ON_BEFORE_REMOVE], $eventData);
            $doRemove = $this->eventProcessor->allowRemove;
        } else {
            $doRemove = true;
        }
        if ($doRemove) {
            $result = !!$this->connection->delete($this->tableName, $this->idToIdentifier($itemId));
        } else {
            $result = null;
        }
        if (isset($this->events[self::EVENT_ON_AFTER_REMOVE])) {
            $this->eventProcessor->runExpression($this->events[self::EVENT_ON_AFTER_REMOVE], $eventData);
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
        if (!empty($this->events[self::EVENT_ON_BEFORE_SEARCH]) || !empty($this->events[self::EVENT_ON_AFTER_SEARCH])) {
            $criteria['where'] = isset($criteria['where']) ? $criteria['where'] : '';
            $eventData = $this->getCommonEventData() + array(
                'criteria' => &$criteria
            );
        } else {
            $eventData = null;
        }
        if (!empty($this->events[self::EVENT_ON_BEFORE_SEARCH])) {
            $this->eventProcessor->runExpression($this->events[self::EVENT_ON_BEFORE_SEARCH], $eventData);
        }
        $queryBuilder = $this->createQueryBuilder();
        $this->configureSelect($queryBuilder, true, $criteria);

        $results = $this->prepareResults($queryBuilder->execute()->fetchAll());

        if (!empty($this->events[self::EVENT_ON_AFTER_SEARCH])) {
            $eventData['results'] = &$results;
            $this->eventProcessor->runExpression($this->events[self::EVENT_ON_BEFORE_SEARCH], $eventData);
        }

        return $results;
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
}
