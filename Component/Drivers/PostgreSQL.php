<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Manageble;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Routable;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Mapbender\DataSourceBundle\Entity\Feature;

/**
 * Class PostgreSQL
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class PostgreSQL extends DoctrineBaseDriver implements Manageble, Routable, Geographic
{

    /**
     * Insert data item
     *
     * @param array|DataItem $item
     * @param bool           $cleanData Clean data before insert?
     * @return DataItem
     * @internal param string $idFieldName
     * @internal param array|DataItem $rawData
     * @internal param string $idField
     * @internal param array|DataItem $item
     */
    public function insert($item, $cleanData = true)
    {
        $connection = $this->connection;
        $keys       = array();
        $values     = array();
        $item       = $this->create($item);

        $connection->connect();

        if ($cleanData) {
            $data = $this->cleanData($item->toArray());
        } else {
            $data = $item->toArray();
        }

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;

            }
            $keys[]   = $connection->quoteIdentifier($key);
            $values[] = $connection->quote($value);
        }

        $sql = 'INSERT INTO ' . $connection->quoteIdentifier($this->tableName)
            . ' (' . implode(', ', $keys) . ')'
            . ' VALUES '
            . ' (' . implode(', ', $values) . ')'
            . ' RETURNING ' . $connection->quoteIdentifier($this->getUniqueId());

        $id = $connection->fetchColumn($sql);
        $item->setId($id);
        return $item;
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
        $connection = $this->getConnection();
        $id         = $connection->lastInsertId();
        if ($id < 1) {
            $fullTableName    = $this->tableName;
            $fullUniqueIdName = $connection->quoteIdentifier($this->getUniqueId());
            $sql              = /** @lang PostgreSQL */
                "SELECT $fullUniqueIdName 
                 FROM $fullTableName
                 LIMIT 1 
                 OFFSET (SELECT count($fullUniqueIdName)-1 FROM $fullTableName )";

            $id = $connection->fetchColumn($sql);
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
     * @param string $idKey
     * @return int Node ID
     */
    public function getNodeFromGeom($waysVerticesTableName, $waysGeomFieldName, $ewkt, $transformTo = null, $idKey = "id")
    {
        $db   = $this->getConnection();
        $geom = "ST_GeometryFromText('" . $db->quote($ewkt) . "')";

        if ($transformTo) {
            $geom = "ST_TRANSFORM($geom, $transformTo)";
        }

        return $db->fetchColumn(/** @lang PostgreSQL */
            "SELECT 
              {$db->quoteIdentifier($idKey)}, 
              ST_Distance({$db->quoteIdentifier($waysGeomFieldName)}, $geom) AS distance
            FROM 
              {$db->quoteIdentifier($waysVerticesTableName)}
            ORDER BY 
              distance ASC
            LIMIT 1");
    }

    /**
     * Route between nodes
     *
     * @param      $waysTableName
     * @param      $waysGeomFieldName
     * @param int  $startNodeId
     * @param int  $endNodeId
     * @param      $srid
     * @param bool $directedGraph  directed graph
     * @param bool $hasReverseCost Has reverse cost, only can be true, if  directed graph=true
     * @return \Mapbender\DataSourceBundle\Entity\Feature[]
     */
    public function routeBetweenNodes(
        $waysTableName,
        $waysGeomFieldName,
        $startNodeId,
        $endNodeId,
        $srid,
        $directedGraph = false,
        $hasReverseCost = false)
    {
        /** @var Connection $db */
        $db             = $this->getConnection();
        $waysTableName  = $db->quoteIdentifier($waysTableName);
        $geomFieldName  = $db->quoteIdentifier($waysGeomFieldName);
        $directedGraph  = $directedGraph ? 'TRUE' : 'FALSE'; // directed graph [true|false]
        $hasReverseCost = $hasReverseCost && $directedGraph ? 'TRUE' : 'FALSE'; // directed graph [true|false]
        $results        = $db->query("SELECT
                route.seq as orderId,
                route.id1 as startNodeId,
                route.id2 as endNodeId,
                route.cost as distance,
                ST_AsEWKT ($waysTableName.$geomFieldName) AS geom
            FROM
                pgr_dijkstra (
                    'SELECT gid AS id, source, target, length AS cost FROM $waysTableName',
                    $startNodeId,
                    $endNodeId,
                    $directedGraph,
                    $hasReverseCost
                ) AS route
            LEFT JOIN $waysTableName ON route.id2 = $waysTableName.gid")->fetchAll();
        return $this->prepareResults($results, $srid);
    }

    /**
     * @return array
     */
    public function listDatabases()
    {
        return $this->fetchList("SELECT datname FROM pg_database WHERE datistemplate = false");
    }

    /**
     * @return array
     */
    public function listSchemas($databaseName)
    {

        return $this->fetchList("SELECT DISTINCT table_schema FROM information_schema.tables");
    }

    /**
     * Get database table names
     *
     * @param $schemaName
     * @return array
     */
    public function listTables($schemaName)
    {
        $schemaName = $this->getConnection()->quote($schemaName);
        return $this->fetchList("SELECT DISTINCT table_name FROM information_schema.tables WHERE table_schema LIKE '$schemaName'");
    }

    /**
     * @param      $ewkt
     * @param null $srid
     * @return mixed
     * @internal param $wkt
     */
    public function transformEwkt($ewkt, $srid = null)
    {
        $db      = $this->getConnection();
        $type    = $this->getTableGeomType($this->getTableName());
        $wktType = static::getWktType($ewkt);

        if ($type
            && $wktType != $type
            && in_array(strtoupper($wktType), Feature::$simpleGeometries)
            && in_array(strtoupper($type), Feature::$complexGeometries)
        ) {
            $ewkt = 'SRID=' . $srid . ';' . $db->fetchColumn("SELECT ST_ASTEXT(ST_TRANSFORM(ST_MULTI(" . $db->quote($ewkt) . "),$srid))");
        }

        $srid = $db->quote($srid);
        $ewkt = $db->quote($ewkt);

        return $db->fetchColumn("SELECT ST_TRANSFORM(ST_GEOMFROMTEXT($ewkt), $srid)");
    }

    /**
     * @inheritdoc
     */
    public function getIntersectCondition($wkt, $geomFieldName, $srid, $sridTo)
    {
        $db            = $this->getConnection();
        $geomFieldName = $db->quoteIdentifier($geomFieldName);
        $wkt           = $db->quote($wkt);
        $srid          = $db->quote($srid);
        $sridTo        = $db->quote($sridTo);
        return "(ST_ISVALID($geomFieldName) AND ST_INTERSECTS(ST_TRANSFORM(ST_GEOMFROMTEXT($wkt,$srid),$sridTo), $geomFieldName ))";
    }

    /**
     * @inheritdoc
     */
    public function getGeomAttributeAsWkt($geometryAttribute, $sridTo)
    {
        $connection    = $this->getConnection();
        $geomFieldName = $connection->quoteIdentifier($geometryAttribute);
        $sridTo        = $connection->quote($sridTo);
        return "ST_ASTEXT(ST_TRANSFORM($geomFieldName, $sridTo)) AS $geomFieldName";
    }

    /**
     * @inheritdoc
     */
    public function findGeometryFieldSrid($tableName, $geomFieldName)
    {
        $connection = $this->getConnection();
        $schemaName = "current_schema()";
        if (strpos($tableName, ".")) {
            list($schemaName, $tableName) = explode('.', $tableName);
            $schemaName = $connection->quote($schemaName);
        }

        return $connection->fetchColumn("SELECT Find_SRID(" . $schemaName . ", 
            " . $connection->quote($tableName) . ", 
            " . $connection->quote($geomFieldName) . ")");
    }
}