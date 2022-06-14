<?php
namespace Mapbender\DataSourceBundle\Component;

use Doctrine\DBAL\Query\QueryBuilder;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Mapbender\DataSourceBundle\Entity\Feature;
use Mapbender\DataSourceBundle\Utils\WktUtility;
use Symfony\Component\Finder\Finder;

/**
 * Loads and stores Features (DataItem with geometry).
 *
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 * @copyright 2015 by WhereGroup GmbH & Co. KG
 * @link      https://github.com/mapbender/mapbender-digitizer
 *
 * @method Feature save(Feature|array $feature, bool $autoUpdate = true)
 * @method Feature insertItem(Feature $item)
 * @method Feature updateItem(Feature $item)
 * @method Feature update($itemOrData)
 * @method Feature insert($itemOrData)
 * @method Feature get($args)
 * @method Feature[]|array[] getByIds(array $ids)
 * @method Feature itemFactory()
 * @method Feature[] prepareResults(array $rows)
 */
class FeatureType extends DataStore
{

    /**
     * Default upload directory
     * @deprecated class consts cannot be changed by child classes; use getUploadsDirectoryName method
     * @todo 0.2.0: remove this const
     */
    const UPLOAD_DIR_NAME = "featureTypes";

    /**
     * @var string Geometry field name
     */
    protected $geomField = 'geom';

    /** @var int|null fallback source srid used only if detection fails (e.g. materialized views) */
    protected $configuredSrid;

    /**
     * @var int SRID to get geometry converted to
     */
    protected $srid = null;

    /**
     * @var string Routing ways node table name.
     */
    protected $waysTableName = "ways";

    /**
     * @var string Way vertices table name
     */
    protected $waysVerticesTableName = 'ways_vertices_pgr';

    /**
     * @var string Routing ways geometry field name
     */
    protected $waysGeomFieldName = "the_geom";


    /** @var array|null */
    protected $exportFields;

    protected function configure(array $args)
    {
        if (!empty($args['srid'])) {
            $this->configuredSrid = \intval($args['srid']) ?: null;
        }
        if (array_key_exists('geomField', $args)) {
            $this->geomField = $args['geomField'];
        }
        if (array_key_exists('waysTableName', $args)) {
            $this->setWaysTableName($args['waysTableName']);
        }
        if (array_key_exists('waysGeomFieldName', $args)) {
            $this->setWaysGeomFieldName($args['waysGeomFieldName']);
        }
        if (array_key_exists('waysVerticesTableName', $args)) {
            $this->setWaysVerticesTableName($args['waysVerticesTableName']);
        }
        if (!empty($args['export'])) {
            if (!is_array($args['export'])) {
                throw new \InvalidArgumentException("Unexpected type " . gettype($args['export']) . " for 'export'. Expected array.");
            }
            if (!empty($args['export']['fields'])) {
                $this->exportFields = $args['export']['fields'];
            }
        }
        $remaining = array_diff_key($args, array_flip(array(
            'geomField',
            'geomType',     // driver scope
            'srid',
            'export',
            'waysTableName',
            'waysGeomFieldName',
            'waysVerticesTableName',
        )));

        parent::configure($remaining);

    }

    protected function initializeFields($args)
    {
        $fields = parent::initializeFields($args);
        if ($this->geomField && false !== ($key = \array_search($this->geomField, $fields))) {
            unset($fields[$key]);
        }
        return $fields;
    }

    public function getFields()
    {
        return array_merge(parent::getFields(), array($this->geomField));
    }

    /**
     * Get feature by ID and SRID
     *
     * @param int $id
     * @param int $srid SRID
     * @return Feature|null
     */
    public function getById($id, $srid = null)
    {
        $qb = $this->createQueryBuilder();
        $this->configureSelect($qb, false, array(
            'srid' => $srid,
            'maxResults' => 1,
        ));
        $qb
            ->setMaxResults(1)
            ->where($this->getUniqueId() . " = :id")
            ->setParameter('id', $id)
        ;
        $features = $this->prepareResults($qb->execute()->fetchAll());
        if ($features) {
            return $features[0];
        } else {
            return null;
        }
    }

    /**
     * @param Feature $feature
     * @return Feature|null
     */
    protected function reloadItem($feature)
    {
        return $this->getById($feature->getId(), $feature->getSrid());
    }

    /**
     * @param DataItem $feature
     * @return mixed[]
     */
    protected function prepareStoreValues(DataItem $feature)
    {
        $data = parent::prepareStoreValues($feature);
        /** @var Feature $feature */
        $ewkt = $feature->getEwkt();
        $meta = $this->getTableMetaData();
        $geomColumnName = $meta->getRealColumnName($this->geomField);
        if ($ewkt) {
            $tableSrid = $this->getSrid();
            // HACK: replace invalid client-supplied geometries with empty dummy WKTs
            // @see https://repo.wheregroup.com/bev/tickets---extern/issues/36 (internal)
            if (strpos(strtoupper($ewkt), 'NAN') !== false) {
                @trigger_error("WARNING: replacing invalid geometry with empty point. This will be an error in a future version (supplied EWKT: {$ewkt})", E_USER_DEPRECATED);
                $ewkt = "SRID={$tableSrid};POINT EMPTY";
            }
            $driver = $this->getDriver();
            $geomSql = $driver->getTransformSql($driver->getReadEwktSql($this->connection->quote($ewkt)), $tableSrid);
            if ($this->checkPromoteToCollection($ewkt, $geomColumnName)) {
                $geomSql = $driver->getPromoteToCollectionSql($geomSql);
            }
            $data[$geomColumnName] = new Expression($geomSql);
        } else {
            $data[$geomColumnName] = null;
        }
        return $data;
    }

    /**
     * @param string $ewkt
     * @param string|null $columnName
     * @return boolean
     */
    protected function checkPromoteToCollection($ewkt, $columnName)
    {
        $tableType = $this->getTableMetaData()->getColumn($columnName)->getGeometryType();
        $wktType = WktUtility::getGeometryType($ewkt);

        // @todo: document why we would want to promote to collection, and why we only have a Postgis implementation
        return $tableType && $wktType != $tableType
            && strtoupper($tableType) !== 'GEOMETRY'
            && preg_match('#^MULTI#i', $tableType)
            && !preg_match('#^MULTI#i', $wktType)
        ;
    }

    /**
     * Search feature by criteria
     *
     * @param array $criteria
     * @return Feature[]|array
     * @todo: methods should not have parametric return types
     */
    public function search(array $criteria = array())
    {
        $queryBuilder = $this->createQueryBuilder();
        $this->configureSelect($queryBuilder, true, $criteria);
        $this->addCustomSearchCritera($queryBuilder, $criteria);

        $features = $this->prepareResults($queryBuilder->execute()->fetchAll());

        if (!empty($criteria['returnType']) && $criteria['returnType'] === 'FeatureCollection') {
            @trigger_error("DEPRECATED: passed 'returnType' => 'FeatureCollection' to search. This path will be removed in 0.2.0. Change your code to use the default WKT format.", E_USER_DEPRECATED);
            return $this->toFeatureCollection($features);
        } else {
            return $features;
        }
    }

    /**
     * Add custom (non-Doctrineish) criteria to passed query builder.
     * Override hook for customization
     *
     * @param QueryBuilder $queryBuilder
     * @param array $params
     */
    protected function addCustomSearchCritera(QueryBuilder $queryBuilder, array $params)
    {
        // Add condition for maximum distance to given wkt 'source'
        // @todo: specify and document
        if (isset($params["source"]) && isset($params["distance"])) {
            // @todo: quote column identifer
            $queryBuilder->andWhere("ST_DWithin(t." . $this->getGeomField() . ","
                . $queryBuilder->getConnection()->quote($params["source"])
                . ', :distance)');
            $queryBuilder->setParameter(':distance', $params['distance']);
        }
    }

    /**
     * @return string
     */
    public function getGeomField()
    {
        return $this->geomField;
    }

    /**
     * Cast feature by $args
     *
     * @param mixed $args
     * @return Feature
     * @todo 0.2.0: remove specialization promoting from DataItem to Feature
     */
    public function create($args)
    {
        if (is_object($args) && ($args instanceof DataItem) && !($args instanceof Feature)) {
            @trigger_error("Deprecated: unsafe promotion from DataItem to Feature. This will be an error in 0.2.0.", E_USER_DEPRECATED);
            return $this->itemFromArray($args->getAttributes());
        } else {
            /** @var Feature $feature */ /** @see itemFactory */ /** @see itemFromArray */
            $feature = parent::create($args);
            return $feature;
        }
    }

    /**
     * Create preinitialized item
     *
     * @param array $values
     * @return Feature
     * @since 0.1.16.2
     */
    public function itemFromArray(array $values)
    {
        return new Feature($values, $this->uniqueIdFieldName, $this->geomField);
    }

    /**
     * Get SRID
     *
     * @return int
     */
    public function getSrid()
    {
        $this->srid = $this->srid ?: $this->getTableMetaData()->getColumn($this->geomField)->getSrid() ?: $this->configuredSrid;
        return $this->srid;
    }

    /**
     * Convert Features to FeatureCollection
     *
     * @param Feature[] $features
     * @return array FeatureCollection
     * @deprecated
     * @todo 0.2.0: remove this method, drop phayes/geophp dependency
     */
    public function toFeatureCollection($features)
    {
        @trigger_error("DEPRECATED: converting to GeoJson using abandoned phayes/geophp package. This method will be removed in 0.2.0.", E_USER_DEPRECATED);
        $collection = array(
            'type' => 'FeatureCollection',
            'features' => array(),
        );
        foreach ($features as $feature) {
            $collection['features'][] = $feature->toGeoJson();
        }
        return $collection;
    }

    /**
     * @param string $waysTableName
     */
    public function setWaysTableName($waysTableName)
    {
        $this->waysTableName = $waysTableName;
    }

    /**
     * @param string $waysGeomFieldName
     */
    public function setWaysGeomFieldName($waysGeomFieldName)
    {
        $this->waysGeomFieldName = $waysGeomFieldName;
    }

    /**
     * @return string
     */
    public function getWaysVerticesTableName()
    {
        return $this->waysVerticesTableName;
    }

    /**
     * @param string $waysVerticesTableName
     */
    public function setWaysVerticesTableName($waysVerticesTableName)
    {
        $this->waysVerticesTableName = $waysVerticesTableName;
    }

    /**
     * Generate unique file name for a field.
     *
     * @param null $fieldName Field
     * @return string[]
     * @deprecated no known callers; non-conflicting unique file names generated in {@see Uploader::upcount_name}
     * @todo 0.2.0: remove this method
     */
    public function genFilePath($fieldName = null)
    {
        $id   = $this->countFiles($fieldName) + 1;
        $src  = null;
        $path = null;

        while (1) {
            $path = $id; //. "-" . System::generatePassword(12) ;
            $src  = $this->getFilePath($fieldName) . "/" . $path;
            if (!file_exists($src)) {
                break;
            }
            $id++;
        }

        return array(
            "src"  => $src,
            "path" => $path
        );
    }

    /**
     * Count files in the field directory
     *
     * @param null $fieldName
     * @return int
     * @deprecated only called by deprecated / unused genFilePath
     * @todo 0.2.0: remove this method
     */
    private function countFiles($fieldName = null)
    {
        $finder = new Finder();
        $finder->files()->in($this->getFilePath($fieldName));
        return count($finder);
    }

    /**
     * Detect (E)WKT geometry type
     *
     * @param string $wkt
     * @return string
     * @todo: remove in 0.2.0
     */
    public static function getWktType($wkt)
    {
        return WktUtility::getGeometryType($wkt);
    }

    /**
     * Get route nodes between geometries
     *
     * @param string $sourceGeom EWKT geometry
     * @param string $targetGeom EWKT geometry
     * @return Feature[]
     * @deprecated data-source is an appropriate starting point for pg routing; roll your own
     */
    public function routeBetweenGeom($sourceGeom, $targetGeom)
    {
        $connection = $this->getConnection();
        $srid = $this->getSrid();
        $sourceId = LegacyPgRouting::nodeFromGeom($connection, $this->waysVerticesTableName, $this->waysGeomFieldName, $sourceGeom, $srid, 'id');
        $targetId = LegacyPgRouting::nodeFromGeom($connection, $this->waysVerticesTableName, $this->waysGeomFieldName, $targetGeom, $srid, 'id');
        $rows = LegacyPgRouting::route($connection, $this->waysVerticesTableName, $this->waysGeomFieldName, $sourceId, $targetId, $srid);
        $features = array();
        foreach ($rows as $row) {
            $feature = new Feature(array(), $this->getUniqueId(), $this->getGeomField());
            $feature->setAttributes($row);
            $features[] = $feature;
        }
        return $features;
    }

    /**
     * Get feature type configuration by key name
     *
     * @param string $key only allowed value is 'export'
     * @return array|null
     * @deprecated remove in 0.2.0; you can't create a FeatureType without already having
     *     access to its configuration
     * @throws \InvalidArgumentException
     */
    public function getConfiguration($key = null)
    {
        if ($key !== 'export') {
            throw new \InvalidArgumentException("Invalid getConfiguration call with key " . print_r($key, true));
        }
        if ($this->exportFields) {
            return array(
                'fields' => $this->exportFields,
            );
        } else {
            return null;
        }
    }

    /**
     * @param array $rows
     * @return array
     * @todo: eliminate eval
     * No known callers
     */
    public function export(array $rows)
    {
        $fieldNames = $this->exportFields;

        if ($fieldNames) {
            $result = array();
            foreach ($rows as $row) {
                $exportRow = array();
                foreach ($fieldNames as $fieldName => $fieldCode) {
                    $exportRow[$fieldName] = $this->evaluateField($row, $fieldCode);
                }
                $result[] = $exportRow;
            }
            return $result;
        } else {
            return $rows;
        }
    }

    /**
     * @param mixed[] $row
     * @param string $code
     * @return mixed
     * @todo: stop using eval already
     */
    private function evaluateField($row, $code)
    {
        $result = null;
        extract($row);
        eval('$result = ' . $code . ';');
        /** @noinspection PhpExpressionAlwaysNullInspection */
        return $result;
    }

    /**
     * @return string
     * @since 0.1.20
     */
    public function getUploadsDirectoryName()
    {
        return 'featureTypes';
    }

    /**
     * @return FeatureQueryBuilder
     */
    public function createQueryBuilder()
    {
        return new FeatureQueryBuilder($this->connection, $this->getDriver(), $this->getSrid());
    }

    protected function attributesFromRow(array $values)
    {
        $attributes = parent::attributesFromRow($values);
        if ($this->geomField && !\array_key_exists($this->geomField, $attributes)) {
            $meta = $this->getTableMetaData();
            $attributes[$this->geomField] = $values[$meta->getRealColumnName($this->geomField)];
        }
        return $attributes;
    }

    protected function configureSelect(QueryBuilder $queryBuilder, $includeDefaultFilter, array $params)
    {
        /** @var FeatureQueryBuilder $queryBuilder */
        parent::configureSelect($queryBuilder, $includeDefaultFilter, $params);
        $queryBuilder->addGeomSelect($this->geomField);
        if (!empty($params['srid'])) {
            $queryBuilder->setTargetSrid($params['srid']);
        }
    }

    protected function addQueryFilters(QueryBuilder $queryBuilder, $includeDefaultFilter, $params)
    {
        parent::addQueryFilters($queryBuilder, $includeDefaultFilter, $params);
        // add bounding geometry condition
        if (!empty($params['intersect'])) {
            $clipWkt = $params['intersect'];
            if (!($srid = WktUtility::getEwktSrid($clipWkt))) {
                if (!empty($params['srid'])) {
                    $clipSrid = $params['srid'];
                } else {
                    $clipSrid = $this->getSrid();
                }
                $clipWkt = "SRID={$clipSrid};$clipWkt";
            }
            $connection = $queryBuilder->getConnection();
            $driver = $this->getDriver();
            $clipGeomExpression = $driver->getReadEwktSql($connection->quote($clipWkt));
            $clipGeomExpression = $driver->getTransformSql($clipGeomExpression, $this->getSrid());
            $columnReference = $connection->quoteIdentifier($this->geomField);
            $queryBuilder->andWhere($driver->getNativeIntersectCondition($columnReference, $clipGeomExpression));
        }
    }
}
