<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class PostgreSQL
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class Oracle extends DoctrineBaseDriver implements Geographic
{
    /**
     * Transform result column names from lower case to upper
     *
     * @param        $rows         array Two dimensional array link
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
     */
    public function prepareResults($rows)
    {
        $rowsOut = parent::prepareResults($rows);
        self::transformColumnNames($rowsOut);
        return $rowsOut;
    }

    /**
     * Add geometry column
     *
     * @param        $tableName
     * @param        $type
     * @param        $srid
     * @param string $geomFieldName
     * @param string $schemaName
     * @param int    $dimensions
     * @return mixed
     */
    public function addGeometryColumn($tableName,
        $type,
        $srid,
        $geomFieldName = "geom",
        $schemaName = "public",
        $dimensions = 2)
    {
        // TODO: Implement addGeometryColumn() method.
        throw new \RuntimeException("Method not implemented");
    }

    /**
     * Get table geometry type
     *
     * @param        $tableName
     * @param string $schema
     * @return mixed
     */
    public function getTableGeomType($tableName, $schema = null)
    {
        // TODO: Implement getTableGeomType() method.
        throw new \RuntimeException("Method not implemented");
    }

    /**
     * @param      $ewkt
     * @param null $srid
     * @return mixed
     * @internal param $wkt
     */
    public function transformEwkt($ewkt, $srid = null)
    {
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