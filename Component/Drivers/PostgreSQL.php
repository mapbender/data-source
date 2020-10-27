<?php

namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Manageble;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Routable;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Mapbender\DataSourceBundle\Entity\Feature;

/**
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class PostgreSQL extends DoctrineBaseDriver implements Manageble, Routable, Geographic
{

    protected function getInsertSql($tableName, $columns, $values)
    {
        $idName = $this->repository->getUniqueId();
        return parent::getInsertSql($tableName, $columns, $values)
            . ' RETURNING ' . $this->getConnection()->quoteIdentifier($idName)
        ;
    }

    protected function prepareParamValue($value)
    {
        if (\is_bool($value)) {
            // PostgreSQL PDO will accept a variety of string representations for boolean columns
            // including 't' and 'f'
            return $value ? 't' : 'f';
        } else {
            return parent::prepareParamValue($value);
        }
    }

    /**
     * Add geometry column
     *
     * @param string $tableName
     * @param string $type
     * @param string $srid
     * @param string $geomFieldName
     * @param string $schemaName
     * @param int $dimensions
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     * @deprecated remove in 0.2.0 this is DBA work
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
     * @param string $name
     * @param string $idColumn
     * @param bool $dropBeforeCreate
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     * @deprecated remove in 0.2.0 this is DBA work
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
     * @param string $name
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     * @deprecated remove in 0.2.0 this is DBA work
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
        $type = $connection->query("SELECT \"type\"
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
        $id = $connection->lastInsertId();
        if ($id < 1) {
            $fullTableName = $this->tableName;
            $fullUniqueIdName = $connection->quoteIdentifier($this->repository->getUniqueId());
            $sql = /** @lang PostgreSQL */
                "SELECT $fullUniqueIdName 
                 FROM $fullTableName
                 LIMIT 1 
                 OFFSET (SELECT count($fullUniqueIdName)-1 FROM $fullTableName )";

            $id = $connection->fetchColumn($sql);
        }
        return $id;
    }

    /**
     * Get id of geometry in given table nearest to given ewkt
     *
     * @param string $waysVerticesTableName
     * @param string $waysGeomFieldName
     * @param string $ewkt
     * @param null|int $transformTo optional srid
     * @param string $idKey
     * @return mixed id column value
     * @todo: this has nothing to do with routing
     * @todo: support returning more than just the id
     * @todo: this implementation is super slow on non-trivial datasets
     */
    public function getNodeFromGeom($waysVerticesTableName, $waysGeomFieldName, $ewkt, $transformTo = null, $idKey = "id")
    {
        $db = $this->getConnection();
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
     * @param string $waysTableName
     * @param string $waysGeomFieldName
     * @param int $startNodeId
     * @param int $endNodeId
     * @param mixed $srid completely ignored @todo: either use this argument or remove it
     * @param bool $directedGraph directed graph
     * @param bool $hasReverseCost Has reverse cost, only can be true, if  directed graph=true
     * @return DataItem[]
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
        $db = $this->getConnection();
        $waysTableName = $db->quoteIdentifier($waysTableName);
        $geomFieldName = $db->quoteIdentifier($waysGeomFieldName);
        $directedGraph = $directedGraph ? 'TRUE' : 'FALSE'; // directed graph [true|false]
        $hasReverseCost = $hasReverseCost && $directedGraph ? 'TRUE' : 'FALSE'; // directed graph [true|false]
        $results = $db->query("SELECT
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
        return $this->prepareResults($results);
    }

    /**
     * @return array
     */
    public function listDatabases()
    {
        return $this->fetchList("SELECT datname FROM pg_database WHERE datistemplate = false");
    }

    /**
     * @param string $databaseName
     * @return string[]
     */
    public function listSchemas($databaseName)
    {
        return $this->fetchList("SELECT DISTINCT table_schema FROM information_schema.tables");
    }

    /**
     * Get database table names
     *
     * @param string $schemaName
     * @return string[]
     */
    public function listTables($schemaName)
    {
        $schemaName = $this->getConnection()->quote($schemaName);
        return $this->fetchList("SELECT DISTINCT table_name FROM information_schema.tables WHERE table_schema LIKE '$schemaName'");
    }

    /**
     * Returns transformed geometry in NATIVE FORMAT (WKB).
     *
     * @param string $ewkt
     * @param int|null $srid
     * @return mixed
     * @internal param $wkt
     * @todo: if an ewkt goes in, an ewkt should come out; native format is pretty useless outside of insert / update usage
     */
    public function transformEwkt($ewkt, $srid = null)
    {
        $db = $this->getConnection();
        $sql = $this->getReadEwktSql($db->quote($ewkt));
        $sql = $this->getTransformSql($sql, $srid);

        $type = $this->getTableGeomType($this->getTableName());
        $wktType = static::getWktType($ewkt);

        // @todo: document why we would want to promote to collection, and why we only do it on Postgis
        if ($type
            && $wktType != $type
            && in_array(strtoupper($wktType), Feature::$simpleGeometries)
            && in_array(strtoupper($type), Feature::$complexGeometries)
        ) {
            $sql = "ST_Multi({$sql})";
        }
        return $db->fetchColumn("SELECT {$sql}");
    }

    public function getReadEwktSql($data)
    {
        return "ST_MakeValid(ST_GeomFromEWKT({$data}))";
    }

    public function getTransformSql($data, $sridTo)
    {
        if (!$sridTo || !\is_numeric($sridTo)) {
            throw new \InvalidArgumentException("Invalid sridTo " . print_r($sridTo, true));
        }
        return "ST_MakeValid(ST_Transform({$data}, " . intval($sridTo) . '))';
    }

    public function getDumpWktSql($data)
    {
        return "ST_AsText({$data})";
    }

    /**
     * @inheritdoc
     */
    public function getIntersectCondition($wkt, $geomFieldName, $srid, $sridTo)
    {
        $db = $this->getConnection();
        $geomFieldName = $db->quoteIdentifier($geomFieldName);
        $wkt = $db->quote($wkt);
        $srid = is_numeric($srid) ? intval($srid) : $db->quote($srid);
        $sridTo = is_numeric($sridTo) ? intval($sridTo) : $db->quote($sridTo);
        return "(ST_TRANSFORM(ST_GEOMFROMTEXT($wkt,$srid),$sridTo) && ST_MakeValid($geomFieldName))";
    }

    /**
     * @inheritdoc
     */
    public function getGeomAttributeAsWkt($geometryAttribute, $sridTo)
    {
        $connection = $this->getConnection();
        $geomFieldName = $connection->quoteIdentifier($geometryAttribute);
        $sridTo = is_numeric($sridTo) ? intval($sridTo) : $connection->quote($sridTo);
        return "ST_ASTEXT(ST_TRANSFORM($geomFieldName, $sridTo)) AS $geomFieldName";
    }

    /**
     * @inheritdoc
     */
    public function findGeometryFieldSrid($tableName, $geomFieldName)
    {
        $connection = $this->getConnection();
        $sql = 'SELECT srid FROM "public"."geometry_columns" WHERE "f_geometry_column" = ? AND "f_table_name" = ?';
        $params[] = $geomFieldName;
        if (false !== strpos($tableName, ".")) {
            $tableNameParts = explode('.', $tableName, 2);
            $params[] = $tableNameParts[1];
            $params[] = $tableNameParts[0];
            $sql .= ' AND "f_table_schema" = ?';
        } else {
            $params[] = $tableName;
            $sql .= ' AND "f_table_schema" = current_schema()';
        }
        return $connection->fetchColumn($sql, $params);
    }
}
