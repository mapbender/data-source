<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Base;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class DoctrineBaseDriver
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DoctrineBaseDriver extends BaseDriver implements Base
{
    const MAX_RESULTS = 100;

    /** @var Connection */
    public $connection;

    /**
     * @var string Table name
     */
    protected $tableName;

    /**
     * @var string SQL where filter
     */
    protected $sqlFilter;

    public function __construct(Connection $connection, array $args = array())
    {
        $this->connection = $connection;
        parent::__construct($args);
    }

    /**
     * Get by ID, array or object
     *
     * @param $args
     * @return DataItem
     */
    public function get($args)
    {
        $dataItem = $this->create($args);
        if ($dataItem->hasId()) {
            $dataItem = $this->getById($dataItem->getId());
        }
        return $dataItem;
    }

    /**
     * Save the data
     *
     * @param mixed $data
     * @param bool  $autoUpdate update instead of insert if ID given
     * @return mixed
     * @throws \Exception
     */
    public function save($data, $autoUpdate = true)
    {
        if (!is_array($data) && !is_object($data)) {
            throw new \Exception("Data item given isn't compatible to save into the table: " . $this->getTableName());
        }

        $dataItem = $this->create($data);

        try {
            // Insert if no ID given
            if (!$autoUpdate || !$dataItem->hasId()) {
                $dataItem = $this->insert($dataItem);
            } // Replace if has ID
            else {
                $dataItem = $this->update($dataItem);
            }

            // Get complete dataItem data
            $result = $this->getById($dataItem->getId());

        } catch (\Exception $e) {
            $result = array(
                "exception" => $e,
                "dataItem"  => $dataItem,
                "data"      => $data
            );
        }

        return $result;
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
     */
    public function canRead()
    {
        return $this->isReady(); // TODO: implement user access check
    }

    /**
     * Has permission to write?
     *
     * @return bool
     */
    public function canWrite()
    {
        return $this->isReady(); // TODO: implement user access check
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
     * Executes statement and fetch list as array
     *
     * @param $statement
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
     * @param $name
     * @return $this
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
     */
    public function getSelectQueryBuilder(array $fields = array())
    {
        $connection = $this->getConnection();
        $qb         = $connection->createQueryBuilder();
        $fields     = array_merge($this->getFields(), $fields);
        $fields     = array_merge(array($this->getUniqueId()), $fields);

        foreach ($fields as &$field) {
            if (is_array($field)) {
                $keyName    = current(array_keys($field));
                $expression = current(array_values($field));
                $field      = "$expression AS " . $this->connection->quoteIdentifier($keyName);
            }
        }

        $queryBuilder = $qb->select($fields)->from($this->getTableName(), 't');
        return $queryBuilder;
    }

    /**
     * Search by criteria
     *
     * @param array $criteria
     * @return DataItem[]
     */
    public function search(array $criteria = array())
    {

        /** @var Statement $statement */
        $maxResults   = isset($criteria['maxResults']) ? intval($criteria['maxResults']) : self::MAX_RESULTS;
        $where        = isset($criteria['where']) ? $criteria['where'] : null;
        $queryBuilder = $this->getSelectQueryBuilder();

        // add filter (https://trac.wheregroup.com/cp/issues/3733)
        if (!empty($this->sqlFilter)) {
            $queryBuilder->andWhere($this->sqlFilter);
        }

        // add second filter (https://trac.wheregroup.com/cp/issues/4643)
        if ($where) {
            $queryBuilder->andWhere($where);
        }

        $queryBuilder->setMaxResults($maxResults);
        $statement  = $queryBuilder->execute();
        $rows       = $statement->fetchAll();

        // Cast array to DataItem array list
        return $this->prepareResults($rows);
    }

    /**
     * Convert results to DataItem objects
     *
     * @param array $rows - Data items to be casted
     * @return DataItem[]
     */
    public function prepareResults($rows)
    {
        $rowsOut = array();
        foreach ($rows as $key => $row) {
            $rowsOut[] = $this->create($row);
        }
        return $rowsOut;
    }


    /**
     * Set permanent SQL filter used by $this->search()
     * https://trac.wheregroup.com/cp/issues/3733
     *
     * @see $this->search()
     * @param $sqlFilter
     */
    public function setFilter($sqlFilter)
    {
        $this->sqlFilter = $sqlFilter;
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
     * @param $id
     * @return DataItem
     */
    public function getById($id)
    {
        $list = $this->getByCriteria($id, $this->getUniqueId());
        return reset($list);
    }

    /**
     * Insert data item
     *
     * @param array|DataItem $item
     * @param bool           $cleanData
     * @return DataItem
     * @internal param string $idFieldName
     */
    public function insert($item, $cleanData = true)
    {
        $item       = $this->create($item);
        $connection = $this->getConnection();

        if ($cleanData) {
            $data = $this->cleanData($item->toArray());
        } else {
            $data = $item->toArray();
        }

        $connection->insert($this->tableName, $data);
        $item->setId($connection->lastInsertId());
        return $item;
    }

    /**
     * Clean data this can't be saved into db table from data array
     *
     * @param array $data
     * @return array
     */
    protected function cleanData($data)
    {
        $originalFields = $this->getFields();
        $uniqueId       = $this->getUniqueId();
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
     */
    public function update($dataItem)
    {
        /** @var DataItem $dataItem */
        $dataItem   = $this->create($dataItem);
        $data       = $this->cleanData($dataItem->toArray());
        $connection = $this->getConnection();
        unset($data[ $this->getUniqueId() ]);

        if (empty($data)) {
            throw new \Exception("DataItem can't be updated without criteria");
        }

        $connection->update($this->tableName, $data, array($this->uniqueId => $dataItem->getId()));
        return $dataItem;
    }

    /**
     * Remove data item
     *
     * @param  DataItem|array|int $arg
     * @return bool
     */
    public function remove($arg)
    {
        return $this->getConnection()
            ->delete($this->tableName, array($this->uniqueId => $this->create($arg)->getId())) > 0;
    }

    /**
     * List objects by criteria
     *
     * @param $criteria
     * @param $fieldName
     * @return DataItem[]
     */
    public function getByCriteria($criteria, $fieldName)
    {
        /** @var Statement $statement */
        $queryBuilder = $this->getSelectQueryBuilder();
        $queryBuilder->where($fieldName . " = :criteria");
        $queryBuilder->setParameter('criteria', $criteria);

        $statement = $queryBuilder->execute();
        $rows      = $statement->fetchAll();
        return $this->prepareResults($rows);
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
     * Extract ordered type list from two associate key lists of data and types.
     *
     * @param array $data
     * @param array $types
     *
     * @return array
     */
    protected function extractTypeValues(array $data, array $types)
    {
        $typeValues = array();

        foreach ($data as $k => $_) {
            $typeValues[] = isset($types[$k])
                ? $types[$k]
                : \PDO::PARAM_STR;
        }

        return $typeValues;
    }
}
