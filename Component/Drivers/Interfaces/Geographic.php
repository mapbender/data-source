<?php
namespace Mapbender\DataSourceBundle\Component\Drivers\Interfaces;

interface Geographic
{
    /**
     * Returns an sql expression string reprojecting $data to $sridTo
     *
     * @param string $data column reference (should be passed pre-quoted) or sql expression
     * @param integer $sridTo
     * @return string
     */
    public function getTransformSql($data, $sridTo);

    /**
     * Returns an sql expression string constructing a database-native geometry object from $ewkt
     *
     * @param string $ewkt
     * @return string
     */
    public function getReadEwktSql($ewkt);

    /**
     * Returns an sql expression converting native geometry object $data to its WKT representation
     *
     * @param string $data column reference (should be passed pre-quoted) or sql expression
     * @return string
     */
    public function getDumpWktSql($data);

    /**
     * Returns an sql expression promiting $geomExpression to a multi-geometry type
     * @param string $geomExpression
     * @return string
     */
    public function getPromoteToCollectionSql($geomExpression);

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

