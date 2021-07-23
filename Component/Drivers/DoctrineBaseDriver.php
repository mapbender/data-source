<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Component\DataStore;
use Mapbender\DataSourceBundle\Component\Expression;
use Mapbender\DataSourceBundle\Component\Meta\TableMeta;

/**
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
abstract class DoctrineBaseDriver
{
    /** @var Connection */
    public $connection;


    public function __construct(Connection $connection, DataStore $repository)
    {
        $this->connection = $connection;

        if (!$repository->getTableName()) {
            throw new \LogicException("Cannot initialize " . get_class($this) . " with empty table name");
        }
    }

    /**
     * @return Connection
     */
    protected function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param Connection $connection
     * @param string $tableName
     * @param array $data
     * @param string $identifier
     * @return int the last insert id
     */
    public function insert(Connection $connection, $tableName, array $data, $identifier)
    {
        $pData = $this->prepareInsertData($connection, $data);
        $tableName = $connection->quoteIdentifier($tableName);

        $sql = $this->getInsertSql($tableName, $pData[0], $pData[1]);
        $connection->executeQuery($sql, $pData[2]);
        return $connection->lastInsertId();
    }

    /**
     * @param Connection $connection
     * @param mixed[] $data
     * @return array numeric with 3 entries: first: quoted column names; second: sql value expressions; third: query parameters
     */
    protected function prepareInsertData(Connection $connection, array $data)
    {
        $columns = array();
        $sqlValues = array();
        $params = array();
        foreach ($data as $columnName => $value) {
            if ($value instanceof Expression) {
                $sqlValues[] = $value->getText();
            } else {
                // add placeholder and param binding
                $sqlValues[] = '?';
                $params[] = $this->prepareParamValue($value);
            }
            $columns[] = $connection->quoteIdentifier($columnName);
        }
        return array(
            $columns,
            $sqlValues,
            $params,
        );
    }

    protected function getInsertSql($tableName, $columns, $values)
    {
        return
            'INSERT INTO ' . $tableName
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES '
            . ' (' . implode(', ', $values) . ')'
        ;
    }

    /**
     * @param Connection $connection
     * @param string $tableName
     * @param mixed[] $data
     * @param mixed[] $identifier
     * @return int rows affected
     * @throws \Doctrine\DBAL\DBALException
     */
    public function update(Connection $connection, $tableName, array $data, array $identifier)
    {
        $data = array_diff_key($data, $identifier);
        if (empty($data)) {
            throw new \Exception("Can't update row without data");
        }
        $initializers = array();
        $conditions = array();
        $params = array();
        foreach ($data as $columnName => $value) {
            $columnQuoted = $connection->quoteIdentifier($columnName);
            if ($value instanceof Expression) {
                $initializers[] = "{$columnQuoted} = {$value->getText()}";
            } else {
                // add placeholder and param binding
                $initializers[] = "{$columnQuoted} = ?";
                $params[] = $this->prepareParamValue($value);
            }
        }
        foreach ($identifier as $columnName => $value) {
            $conditions[] = $connection->quoteIdentifier($columnName) . ' = ?';
            $params[] = $this->prepareParamValue($value);
        }

        $sql =
            'UPDATE ' . $connection->quoteIdentifier($tableName)
            . ' SET '
            . implode(', ', $initializers)
            . ' WHERE '
            . implode(' AND ', $conditions)
        ;
        return $connection->executeUpdate($sql, $params);
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
     * @param mixed $value
     * @return mixed
     */
    protected function prepareParamValue($value)
    {
        // Base driver: no transformation
        return $value;
    }

    /**
     * @param Connection $connection
     * @param string $tableName
     * @return TableMeta
     */
    abstract public function loadTableMeta(Connection $connection, $tableName);

    /**
     * @param mixed $x
     * @return mixed
     * @deprecated returns unchanged input value; remove invocations
     * @todo: 0.2.0: remove this method (breaks mapbender/search)
     */
    final public static function roundGeometry($x)
    {
        return $x;
    }
}
