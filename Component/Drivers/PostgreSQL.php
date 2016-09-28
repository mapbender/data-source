<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class PostgreSQL
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class PostgreSQL extends DoctrineBaseDriver implements Geographic
{
    /**
     * Insert data item
     *
     * @param array|DataItem $item
     * @return DataItem
     */
    public function insert($item)
    {
        $dataItem = parent::insert($item);
        $id       = $this->getLastInsertId();
        $dataItem->setId($id);
        return $dataItem;
    }

    /**
     * Add geometry column
     *
     * @param string $tableName
     * @param string $type
     * @param string $srid
     * @param string $geomFieldName
     * @param string $schemaName
     * @param int    $dimensions
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public function addGeometryColumn($tableName,
        $type,
        $srid,
        $geomFieldName = "geom",
        $schemaName = "public",
        $dimensions = 2)
    {
        $connection = $this->getConnection();
        return $this->connection->exec("SELECT AddGeometryColumn("
            . $connection->quote($schemaName) . ','
            . $connection->quote($tableName) . ','
            . $connection->quote($geomFieldName) . ','
            . $srid . ','
            . $connection->quote($type) . ','
            . $dimensions
            . ')');
    }

    /**
     * @param        $name
     * @param string $idColumn
     * @param bool   $dropBeforeCreate
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public function createTable($name, $idColumn = 'id', $dropBeforeCreate = false)
    {
        $db = $this->connection;
        if ($dropBeforeCreate) {
            $this->dropTable($name);
        }

        return $db->exec("CREATE TABLE IF NOT EXISTS "
            . $db->quoteIdentifier($name)
            . " (" . $db->quoteIdentifier($idColumn)
            . " SERIAL PRIMARY KEY)");
    }

    /**
     * @param $name
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public function dropTable($name)
    {
        $db = $this->connection;
        return $db->exec("DROP TABLE IF EXISTS " . $db->quoteIdentifier($name));
    }


    /**
     * Get table geom type
     *
     * @param string $tableName Table name. The name can contains schema name splited by dot.
     * @param string $schema
     * @return string
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getTableGeomType($tableName, $schema = null)
    {
        $connection = $this->connection;
        if (strpos($tableName, '.')) {
            list($schema, $tableName) = explode('.', $tableName);
        }
        $_schema = $schema ? $connection->quote($schema) : 'current_schema()';
        $type    = $connection->query("SELECT \"type\"
                FROM geometry_columns
                WHERE f_table_schema = " . $_schema . "
                AND f_table_name = " . $connection->quote($tableName))->fetchColumn();
        return $type;
    }

    /**
     * Get last insert id
     *
     * @return int
     */
    public function getLastInsertId()
    {
        $connection   = $this->getConnection();
        $tableName    = $connection->quote($this->tableName);
        $uidFieldName = $connection->quote($this->getUniqueId());
        $id           = $connection->lastInsertId();

        if ($id < 1) {
            $sql = /** @lang PostgreSQL */
                "SELECT currval(
                  pg_get_serial_sequence('" . $tableName . "','" . $uidFieldName . "'))
                ";
            $id  = $connection->fetchColumn($sql);
        }

        if ($id < 1) {
            $fullTableName    = $connection->quoteIdentifier($tableName);
            $fullUniqueIdName = $fullTableName . '.' . $connection->quoteIdentifier($uidFieldName);
            $sql              = /** @lang PostgreSQL */
                "SELECT $fullUniqueIdName 
                 FROM $fullTableName
                 LIMIT 1 
                 OFFSET (SELECT count($fullUniqueIdName)-1 FROM $fullTableName )";

            $id = $connection->fetchColumn($sql);
            return $id;
        }
        return $id;
    }

    /**
     * Get nearest node to given geometry
     *
     * Important: <-> operator works not well!!
     *
     * @param        $waysVerticesTableName
     * @param        $waysGeomFieldName
     * @param string $ewkt EWKT
     * @param null   $transformTo
     * @return int Node ID
     */
    public function getNodeFromGeom($waysVerticesTableName, $waysGeomFieldName, $ewkt, $transformTo = null)
    {
        $db   = $this->getConnection();
        $geom = "ST_GeometryFromText('" . $db->quote($ewkt) . "')";

        if ($transformTo) {
            $geom = "ST_TRANSFORM($geom,$transformTo)";
        }

        return $db->fetchColumn(/** @lang PostgreSQL */ "
            SELECT id, ST_Distance({$db->quoteIdentifier($waysGeomFieldName)}, $geom) AS distance
            FROM {$db->quoteIdentifier($waysVerticesTableName)}
            ORDER BY distance ASC
            LIMIT 1");
    }
}