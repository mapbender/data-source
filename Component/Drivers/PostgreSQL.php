<?php

namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Manageble;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Routable;
use Mapbender\DataSourceBundle\Component\LegacyPgRouting;
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

    public function insert($tableName, array $data)
    {
        $data = $this->metaDataLoader->getTableMeta($tableName)->prepareInsertData($data);
        $pData = $this->prepareInsertData($data);

        $sql = $this->getInsertSql($tableName, $pData[0], $pData[1]);
        $connection = $this->getConnection();
        return $connection->fetchColumn($sql, $pData[2], 0);
    }

    public function update($tableName, array $data, array $identifier)
    {
        $data = array_diff_key($data, $identifier);
        $data = $this->metaDataLoader->getTableMeta($tableName)->prepareUpdateData($data);

        return parent::update($tableName, $data, $identifier);
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
     * @return string
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getTableGeomType($tableName)
    {
        $connection = $this->connection;
        if (strpos($tableName, '.')) {
            $parts = explode('.', $tableName, 2);
            $schema = $parts[0];
            $tableName = $parts[1];
        } else {
            $schema = null;
        }
        $sql = 'SELECT "type" FROM geometry_columns WHERE'
             . ' f_table_schema = ' . ($schema ? $connection->quote($schema) : 'current_schema()')
             . ' AND f_table_name = ' . $connection->quote($tableName)
        ;
        $type = $connection->query($sql)->fetchColumn();
        return $type;
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

    /**
     * @param string $geomExpression
     * @return string
     */
    public function getPromoteToCollectionSql($geomExpression)
    {
        return "ST_Multi({$geomExpression})";
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
        return "(ST_TRANSFORM(ST_GEOMFROMTEXT($wkt,$srid),$sridTo) && $geomFieldName)";
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

    public function getNodeFromGeom($waysVerticesTableName, $waysGeomFieldName, $ewkt, $transformTo = null, $idKey = "id")
    {
        return LegacyPgRouting::nodeFromGeom($this->getConnection(), $waysVerticesTableName, $waysGeomFieldName, $ewkt, $transformTo, $idKey);
    }

    public function routeBetweenNodes($waysTableName, $waysGeomFieldName, $startNodeId, $endNodeId, $srid, $directedGraph = false, $hasReverseCost = false)
    {
        $results = LegacyPgRouting::route($this->getConnection(), $waysTableName, $waysGeomFieldName, $startNodeId, $endNodeId, $srid, $directedGraph, $hasReverseCost);
        $features = array();
        $geomName = 'geom'; // This is hard-coded in the routing query sql
        $idName = 'orderId'; // This is hard-coded in the routing query sql
        foreach ($results as $row) {
            $feature = new Feature(array(), $srid, $idName, $geomName);
            $feature->setAttributes($row);
            $features[] = $feature;
        }
        return $features;
    }
}
