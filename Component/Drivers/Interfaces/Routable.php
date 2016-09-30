<?php
namespace Mapbender\DataSourceBundle\Component\Drivers\Interfaces;

/**
 * Interface Routable
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 */
interface Routable
{
    /**
     * @param        $waysVerticesTableName
     * @param        $waysGeomFieldName
     * @param        $ewkt
     * @param null   $transformTo
     * @param string $idKey
     * @return mixed
     */
    public function getNodeFromGeom(
        $waysVerticesTableName,
        $waysGeomFieldName,
        $ewkt,
        $transformTo = null,
        $idKey = "id"
    );

    /**
     * @param      $waysTableName
     * @param      $waysGeomFieldName
     * @param      $startNodeId
     * @param      $endNodeId
     * @param      $srid
     * @param bool $directedGraph
     * @param bool $hasReverseCost
     * @return mixed
     */
    public function routeBetweenNodes(
        $waysTableName,
        $waysGeomFieldName,
        $startNodeId,
        $endNodeId,
        $srid,
        $directedGraph = false,
        $hasReverseCost = false
    );
}