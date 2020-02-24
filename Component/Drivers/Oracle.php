<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class Oracle extends DoctrineBaseDriver implements Geographic
{
    /**
     * Transform result column names from lower case to upper
     *
     * @param array[] $rows
     */
    public static function transformColumnNames(&$rows)
    {
        if (!$rows) {
            $columnNames = array();
        } else {
            $columnNames = array_keys(current($rows));
        }
        foreach ($rows as &$row) {
            foreach ($columnNames as $name) {
                $row[ strtolower($name) ] = &$row[ $name ];
                unset($row[ $name ]);
            }
        }
    }

    /**
     * Convert results to Feature objects
     *
     * @param array $rows
     * @return DataItem[]
     * @deprecated DataStore is responsible for DataItem creation, and already handles this
     * @todo 0.2.0: remove this method
     */
    public function prepareResults($rows)
    {
        self::transformColumnNames($rows);
        return parent::prepareResults($rows);
    }

    /**
     * Add geometry column
     *
     * @param string $tableName
     * @param string $type
     * @param integer $srid
     * @param string $geomFieldName
     * @param string $schemaName
     * @param int    $dimensions
     * @return mixed
     * @deprecated remove in 0.2.0 this is DBA work
     */
    public function addGeometryColumn($tableName,
        $type,
        $srid,
        $geomFieldName = "geom",
        $schemaName = "public",
        $dimensions = 2)
    {
        throw new \RuntimeException("Method not implemented");
    }

    /**
     * Get table geometry type
     *
     * @param string $tableName
     * @param string $schema
     * @return mixed
     */
    public function getTableGeomType($tableName, $schema = null)
    {
        // TODO: Implement getTableGeomType() method.
        throw new \RuntimeException("Method not implemented");
    }

    /**
     * Returns transformed geometry in NATIVE FORMAT (resource).
     *
     * @param string $ewkt
     * @param null $srid
     * @return mixed
     * @todo: null srid makes no sense, should throw an error
     * @todo: if an ewkt goes in, an ewkt should come out; native format is pretty useless outside of insert / update usage
     */
    public function transformEwkt($ewkt, $srid = null)
    {
        // @todo: use param binding for injection safety
        return $this->getConnection()->fetchColumn(
            "SELECT 
              SDO_CS.TRANSFORM(
                SDO_UTIL.TO_WKBGEOMETRY('$ewkt'), 
              $srid)");
    }

    /**
     * @inheritdoc
     */
    public function getIntersectCondition($wkt, $geomFieldName, $srid, $sridTo)
    {
        return "SDO_RELATE($wkt ,SDO_GEOMETRY(SDO_CS.TRANSFORM('$geomFieldName',$srid),$sridTo), 'mask=ANYINTERACT querytype=WINDOW') = 'TRUE'";
    }

    /**
     * @inheritdoc
     */
    public function getGeomAttributeAsWkt($geometryAttribute, $sridTo)
    {
      return "SDO_UTIL.TO_WKTGEOMETRY(SDO_CS.TRANSFORM($geometryAttribute, $sridTo)) AS $geometryAttribute";
    }

    /**
     * @inheritdoc
     */
    public function findGeometryFieldSrid($tableName, $geomFieldName)
    {
        $connection = $this->getConnection();
        return $connection->fetchColumn("SELECT {$tableName}.{$geomFieldName}.SDO_SRID FROM TABLE " . $tableName);
    }
}
