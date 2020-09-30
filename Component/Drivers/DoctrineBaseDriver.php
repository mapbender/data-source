<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Mapbender\DataSourceBundle\Component\DataStore;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DoctrineBaseDriver extends BaseDriver
{
    const MAX_RESULTS = 100;

    /** @var Connection */
    public $connection;

    /**
     * @var string Table name
     */
    protected $tableName;

    public function __construct(Connection $connection, array $args, DataStore $repository)
    {
        $this->connection = $connection;
        parent::__construct($args, $repository);
        if (!empty($args['table'])) {
            $this->setTable($args['table']);
        }
    }

    /**
     * Get by ID, array or object
     *
     * @param mixed $args
     * @return DataItem
     * @deprecated
     * @todo 0.2: remove this method
     */
    public function get($args)
    {
        return $this->repository->get($args);
    }

    /**
     * Auto-inflect insert or update, depending on prepopulated id in the given $data
     * @deprecated use method on DataStore / FeatureType ("repository")
     * @todo 0.2: remove this method
     *
     * @param mixed $data
     * @param bool  $autoUpdate update instead of insert if ID given
     * @return DataItem
     * @throws \Exception
     */
    public function save($data, $autoUpdate = true)
    {
        return $this->repository->save($data, $autoUpdate);
    }

    /**
     * Is the driver connected an ready to interact?
     *
     * @return bool
     */
    public function isReady()
    {
        return $this->connection && $this->connection->isConnected();
    }

    /**
     * Has permission to read?
     *
     * @return bool
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function canRead()
    {
        return $this->isReady();
    }

    /**
     * Has permission to write?
     *
     * @return bool
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function canWrite()
    {
        return $this->isReady();
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * @param string  $statement The SQL query to be executed.
     * @param array   $params    The prepared statement params.
     * @param integer $columnNum The 0-indexed column number to retrieve.
     *
     * @return mixed
     */
    public function fetchColumn($statement, array $params = array(), $columnNum = 0)
    {
        return $this->connection->fetchColumn($statement, $params, $columnNum);
    }


    /**
     * Fetches single-column SQL result set.
     *
     * @param string $statement
     * @return array
     */
    public function fetchList($statement)
    {
        $result = array();
        foreach ($this->getConnection()->fetchAll($statement) as $row) {
            $result[] = current($row);
        }
        return $result;
    }

    /**
     * Get version
     */
    public function getVersion()
    {
        $this->fetchColumn("SELECT version()");
    }

    /**
     * Set table name
     *
     * @param string $name
     * @return $this
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function setTable($name)
    {
        $this->tableName = $name;
        return $this;
    }

    /**
     * Get table fields
     *
     * Info: $schemaManager->listTableColumns($this->tableName) doesn't work if fields are geometries!
     *
     * @throws \Doctrine\DBAL\DBALException
     * @return string[] field names
     */
    public function getStoreFields()
    {
        $schemaManager = $this->connection->getDriver()->getSchemaManager($this->connection);
        $columns       = array();
        $sql           = $schemaManager->getDatabasePlatform()->getListTableColumnsSQL($this->tableName, $this->connection->getDatabase());
        foreach ($this->connection->fetchAll($sql) as $fieldInfo) {
            $columns[] = $fieldInfo["field"];
        }
        return $columns;
    }

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatformName()
    {
        static $name = null;
        if (!$name) {
            $name = $this->connection->getDatabasePlatform()->getName();
        }
        return $name;
    }


    /**
     * Get query builder prepared to select from the source table
     *
     * @param array $fields
     * @return QueryBuilder
     * @deprecated use implementation in DataStore / FeatureType
     * @todo 0.2.0: remove this method
     */
    public function getSelectQueryBuilder(array $fields = array())
    {
        @trigger_error("DEPRECATED: " . get_class($this) . '::getSelectQueryBuilder does nothing but delegate to DataStore / FeatureType::getSelectQueryBuilder and will be removed in 0.2.0', E_USER_DEPRECATED);
        return $this->repository->getSelectQueryBuilder($fields);
    }

    /**
     * Search by criteria
     *
     * @param array $criteria
     * @return DataItem[]
     * @deprecated this method's body has been baked into DataStore::search, where it belongs; FeatureType doesn't even use this
     * @todo 0.2.0: remove this method
     */
    public function search(array $criteria = array())
    {
        @trigger_error("DEPRECATED: " . get_class($this) . '::search does nothing but delegate to DataStore / FeatureType::search and will be removed in 0.2.0', E_USER_DEPRECATED);
        return $this->repository->search($criteria);
    }

    /**
     * Convert results to DataItem objects
     *
     * @param array $rows - Data items to be casted
     * @return DataItem[]
     * @deprecated DataStore is responsible for DataItem creation, and already handles this
     * @todo 0.2.0: remove this method
     */
    public function prepareResults($rows)
    {
        @trigger_error("DEPRECATED: " . get_class($this) . '::prepareResults does nothing but delegate to DataStore / FeatureType::prepareResults and will be removed in 0.2.0', E_USER_DEPRECATED);
        return $this->repository->prepareResults($rows);
    }

    /**
     * Does absolutely nothing
     *
     * @deprecated does nothing
     * @todo 0.2.0: remove this method
     */
    public function setFilter()
    {
        @trigger_error("DEPRECATED: " . get_class($this) . '::setFilter does nothing and will be removed in 0.2.0', E_USER_DEPRECATED);
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * Get data item by id
     *
     * @param integer|string $id
     * @return DataItem
     * @deprecated previously only used by / only works for DataStore (doesn't pass srid to FeatureType)
     * @todo 0.2.0: remove this method
     */
    public function getById($id)
    {
        @trigger_error("DEPRECATED: " . get_class($this) . '::getById does nothing but delegate to DataStore / FeatureType::getById and will be removed in 0.2.0', E_USER_DEPRECATED);
        return $this->repository->getById($id);
    }

    /**
     * Insert data item
     *
     * @param array|DataItem $item
     * @param bool           $cleanData
     * @return DataItem
     *
     * @deprecated Driver does not care about DataItem objects, use updateValues with an array
     */
    public function insert($item, $cleanData = true)
    {
        $item       = $this->repository->create($item);
        if ($cleanData) {
            $data = $this->cleanData($item->toArray());
        } else {
            $data = $item->toArray();
        }
        $id = $this->insertValues($this->getTableName(), $data);
        $item->setId($id);
        return $item;
    }

    /**
     * @param string $tableName
     * @param array $data
     * @return int the last insert id
     */
    public function insertValues($tableName, array $data)
    {
        $this->connection->insert($tableName, $this->prepareParams($data));
        return $this->connection->lastInsertId();
    }

    /**
     * Clean data this can't be saved into db table from data array
     *
     * @param array $data
     * @return array
     */
    public function cleanData($data)
    {
        $originalFields = $this->getFields();
        $uniqueId = $this->repository->getUniqueId();
        $fields         = array_merge(
            $originalFields,
            array($uniqueId));

        // clean data from data item
        foreach ($data as $fieldName => $value) {
            if (array_search($fieldName, $fields) === false) {
                unset($data[ $fieldName ]);
            }
        }

        if (isset($data[ $uniqueId ]) && empty($data[ $uniqueId ])) {
            unset($data[ $uniqueId ]);
        }

        return $data;
    }

    /**
     * Update data
     *
     * @param array|DataItem $dataItem
     * @return DataItem
     * @throws \Exception
     * @deprecated Driver does not care about DataItem objects, use updateValues with an array
     */
    public function update($dataItem)
    {
        $dataItem   = $this->repository->create($dataItem);
        $data       = $this->cleanData($dataItem->toArray());
        $identifier = array(
            $this->repository->getUniqueId() => $dataItem->getId(),
        );
        $this->updateValues($this->getTableName(), $data, $identifier);
        return $dataItem;
    }

    /**
     * @param string $tableName
     * @param mixed[] $data
     * @param mixed[] $identifier
     * @return int rows affected
     * @throws \Doctrine\DBAL\DBALException
     */
    public function updateValues($tableName, array $data, array $identifier)
    {
        $connection = $this->getConnection();
        $data = array_diff_key($data, $identifier);
        if (empty($data)) {
            throw new \Exception("Can't update row without data");
        }

        return $connection->update($tableName, $this->prepareParams($data), $identifier);
    }



    /**
     * Remove data item
     *
     * @param DataItem|array|int $arg
     * @return bool
     * @deprecated Driver does not care about DataItems, use delete (same API semantics as DBAL Connection)
     * @todo 0.2: remove this method
     */
    public function remove($arg)
    {
        $identifier = $this->anythingToIdentifier($arg);
        // @todo: empty id should be an error
        return $this->getConnection()->delete($this->getTableName(), $identifier) > 0;
    }

    /**
     * Delete rows
     * @see \Doctrine\DBAL\Connection::delete
     *
     * @param string $tableName
     * @param mixed[] $identifier
     * @return int number of affected rows
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    public function delete($tableName, array $identifier)
    {
        return $this->getConnection()->delete($tableName, $identifier);
    }

    /**
     * List objects by criteria
     *
     * @param string|integer $criteria
     * @param string $fieldName
     * @return DataItem[]
     * @deprecated no remaining usages
     * @todo 0.2.0: remove this method
     */
    public function getByCriteria($criteria, $fieldName)
    {
        $queryBuilder = $this->repository->getSelectQueryBuilder();
        $queryBuilder->where($fieldName . " = :criteria");
        $queryBuilder->setParameter('criteria', $criteria);

        $statement = $queryBuilder->execute();
        $rows      = $statement->fetchAll();
        return $this->repository->prepareResults($rows);
    }

    /**
     * Makes given plain column-name => value mapping safely insertable / updatetable.
     *
     * @param mixed[] $data
     * @return mixed[]
     */
    protected function prepareParams($data)
    {
        $connection = $this->getConnection();
        $prepared = array();
        foreach ($data as $key => $value) {
            $prepared[$connection->quoteIdentifier($key)] = $this->prepareParamValue($value);
        }
        return $prepared;
    }

    /**
     * @param mixed $value
     * @return mixed
     * @todo: expression support
     */
    protected function prepareParamValue($value)
    {
        // Base driver: no transformation
        return $value;
    }

    /**
     * Get last insert ID
     *
     * @return int
     */
    public function getLastInsertId()
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Attempts to extract an identifier array from whatever $arg is
     * Extracted id equivalent to DataStore::create($arg)->getId()
     *
     * @param mixed $arg
     * @return mixed[]
     */
    private function anythingToIdentifier($arg)
    {
        $uniqueId = $this->repository->getUniqueId();
        if (\is_numeric($arg)) {
            return array($uniqueId => $arg);
        } elseif (\is_object($arg)) {
            if ($arg instanceof DataItem) {
                return array($uniqueId => $arg->getId());
            } else {
                // self-delegate to array path
                return $this->anythingToIdentifier(\get_object_vars($arg));
            }
        } elseif (\is_array($arg)) {
            if (!empty($arg[$uniqueId])) {
                return array($uniqueId => $arg[$uniqueId]);
            }
        }
        // uh-oh!
        return null;
    }
}
