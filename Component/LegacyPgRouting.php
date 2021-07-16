<?php


namespace Mapbender\DataSourceBundle\Component;


use Doctrine\DBAL\Connection;

/**
 * Legacy pg routing logic extracted from PostgreSQL driver. Kept only as a
 * reference for further work.
 *
 * @deprecated data-source is an inappropriate starting point for pg routing; roll your own
 * @todo 0.2.0: remove this class, RoutableInterface, related PostgreSQL driver methods
 * @internal
 */
class LegacyPgRouting
{

    public static function nodeFromGeom(Connection $connection, $waysVerticesTableName, $waysGeomFieldName, $ewkt, $transformTo = null, $idKey = "id")
    {
        $geom = "ST_GeometryFromText('" . $connection->quote($ewkt) . "')";

        if ($transformTo) {
            $geom = "ST_TRANSFORM($geom, $transformTo)";
        }

        return $connection->fetchColumn(/** @lang PostgreSQL */
            "SELECT 
              {$connection->quoteIdentifier($idKey)}, 
              ST_Distance({$connection->quoteIdentifier($waysGeomFieldName)}, $geom) AS distance
            FROM 
              {$connection->quoteIdentifier($waysVerticesTableName)}
            ORDER BY 
              distance ASC
            LIMIT 1");
    }

    public static function route(Connection $connection, $waysTableName, $waysGeomFieldName, $startNodeId, $endNodeId, $srid, $directedGraph = false, $hasReverseCost = false)
    {
        $waysTableName = $connection->quoteIdentifier($waysTableName);
        $geomFieldName = $connection->quoteIdentifier($waysGeomFieldName);
        $directedGraph = $directedGraph ? 'TRUE' : 'FALSE'; // directed graph [true|false]
        $hasReverseCost = $hasReverseCost && $directedGraph ? 'TRUE' : 'FALSE'; // directed graph [true|false]
        return $connection->query("SELECT
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
    }
}
