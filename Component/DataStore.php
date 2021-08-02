<?php
namespace Mapbender\DataSourceBundle\Component;

use Mapbender\CoreBundle\Component\UploadsManager;
use Mapbender\DataSourceBundle\Component\Drivers\Oracle;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;
use Mapbender\DataSourceBundle\Component\Drivers\SQLite;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DataStore extends EventAwareDataRepository
{
    const ORACLE_PLATFORM        = 'oracle';
    const POSTGRESQL_PLATFORM    = 'postgresql';
    const SQLITE_PLATFORM        = 'sqlite';

    /** @var ContainerInterface */
    protected $container;
    /** @var RepositoryRegistry */
    protected $registry;

    protected $parentField;
    protected $mapping;

    /**
     * @var array file info list
     */
    protected $filesInfo = array();

    /**
     * @param ContainerInterface $container
     * @param array|null $args
     * @param RepositoryRegistry|null $registry
     * @todo: drop container injection; replace with owning DataStoreService / FeatureTypeService injection
     */
    public function __construct(ContainerInterface $container, $args = array(), RepositoryRegistry $registry = null)
    {
        // Extract parent constructor arguments
        $defaults = array(
            'uniqueId' => 'id',
            'connection' => 'default',  // Uh-oh!
        );
        $args += $defaults;
        $eventConfig = isset($args["events"]) ? $args["events"] : array();
        $registry = $registry ?: $container->get('data.source');
        $connection = $registry->getDbalConnectionByName($args['connection']);
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $container->get('security.token_storage');
        /** @var EventProcessor $eventProcessor */
        $eventProcessor = $container->get('mbds.default_event_processor');
        $filter = (!empty($args['filter'])) ? $args['filter'] : null;
        parent::__construct($connection, $tokenStorage, $eventProcessor, $eventConfig, $args['table'], $args['uniqueId'], $filter);

        // Rest
        $this->container = $container;
        $this->registry = $registry;
        $this->configure($args);
        $this->fields = $this->initializeFields($args);
    }

    protected function configure(array $args)
    {
        /** @todo 0.2.0: remove extra filter condition preprocessing step */
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
    }

    protected function initializeFields($args)
    {
        $platform = $this->connection->getDatabasePlatform();
        if (isset($args['fields'])) {
            if (!is_array($args['fields'])) {
                throw new \InvalidArgumentException("Unexpected type " . gettype($args['fields']) . " for 'fields'. Expected array.");
            }
            $names = $args['fields'];
            if (!empty($args['parentField']) && !in_array($args['parentField'], $names)) {
                @trigger_error("DEPRECATED: parentField / getParent / getTree are deprecated and will be removed in 0.2.0", E_USER_DEPRECATED);
                $names[] = $args['parentField'];
            }
            $names = \array_combine($names, $names) ?: array();
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
     * Get parent by child ID
     *
     * @param integer|string $id
     * @return DataItem|null
     * @deprecated use getById (twice)
     * @todo 0.2: remove this method
     */
    public function getParent($id)
    {
        $dataItem = $this->getById($id);
        return $this->getById($dataItem->getAttribute($this->getParentField()));
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
        $queryBuilder = $this->createQueryBuilder();
        $this->configureSelect($queryBuilder, false, array());
        if ($parentId === null) {
            $queryBuilder->andWhere($this->getParentField() . " IS NULL");
        } else {
            $queryBuilder->andWhere($this->getParentField() . " = " . $parentId);
        }

        $rows = $this->prepareResults($queryBuilder->execute()->fetchAll());

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
        $externalDataStore = $this->registry->get($config["externalDataStore"]);
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

        $mappedId = $this->getById($id)->getAttribute($internalFieldName);

        $queryBuilder = $externalDataStore->getSelectQueryBuilder();
        $queryBuilder->where($externalFieldName . " = :criteria");
        $queryBuilder->setParameter('criteria', $mappedId);

        return $this->prepareResults($queryBuilder->execute->fetchAll());
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
     * @deprecated
     * @todo 0.2: remove (breaks data-manager < 1.2, Digitizer < 1.4)
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
     * @deprecated
     * @todo 0.2: remove (breaks data-manager < 1.2, Digitizer < 1.4)
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
     * @deprecated
     * @todo 0.2: remove (breaks data-manager < 1.2, Digitizer < 1.4)
     */
    public function getFileUrl($fieldName = "")
    {
        $fileUri = $this->getFileUri($fieldName);
        $fs = new Filesystem();
        if ($fs->isAbsolutePath($fileUri)) {
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
     * @deprecated
     * @todo 0.2: remove (breaks data-manager < 1.2, Digitizer < 1.4)
     */
    public function setFiles($fileInfo)
    {
        $this->filesInfo = $fileInfo;
    }

    /**
     * @return array[]
     * @deprecated
     * @todo 0.2: remove (breaks data-manager < 1.2, Digitizer < 1.4)
     */
    public function getFileInfo()
    {
        return $this->filesInfo;
    }
    /**
     * @return string
     * @deprecated
     * @todo 0.2: remove (breaks data-manager < 1.2, Digitizer < 1.4)
     */
    public function getUploadsDirectoryName()
    {
        return "ds-uploads";
    }
}
