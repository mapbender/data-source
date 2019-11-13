<?php
namespace Mapbender\DataSourceBundle\Component\Drivers\Interfaces;

interface Geographic
{
    /**
     * Add geometry column
     *
     * @param string $tableName
     * @param string $type
     * @param string $srid
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
        $dimensions = 2);

    /**
     * Get table geometry type
     *
     * @param string $tableName
     * @param string $schema
     * @return mixed
     */
    public function getTableGeomType($tableName, $schema = null);

    /**
     * Returns transformed geometry in NATIVE FORMAT (WKB or resource).
     *
     * @param string $ewkt
     * @param int|null $srid
     * @return mixed
     * @todo: null srid makes no sense, should throw an error
     * @todo: if an ewkt goes in, an ewkt should come out; native format is pretty useless outside of insert / update usage
     */
    public function transformEwkt($ewkt, $srid = null);

    /**
     * Get intersect SQL condition
     *
     * @param string $wkt           WKT
     * @param string $geomFieldName geometry field name
     * @param string $srid          SRID convert from
     * @param string $sridTo        SRID convert to
     * @return string SQL
     */
    public function getIntersectCondition($wkt, $geomFieldName, $srid, $sridTo);

    /**
     * Get WKB geometry attribute as WKT
     *
     * @param string $geometryAttribute
     * @param string $sridTo SRID convert to
     * @return string SQL
     */
    public function getGeomAttributeAsWkt($geometryAttribute, $sridTo);


    /**
     * Get WKB geometry attribute as WKT
     *
     * @param  string $tableName
     * @param  string $geomFieldName
     * @return string SQL
     */
    public function findGeometryFieldSrid($tableName, $geomFieldName);
}

