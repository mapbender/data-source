<?php
namespace Mapbender\DataSourceBundle\Component;

use Doctrine\DBAL\Query\QueryBuilder;
use Mapbender\DataSourceBundle\Component\Drivers\DoctrineBaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Component\Drivers\Oracle;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Mapbender\DataSourceBundle\Entity\Feature;
use Mapbender\DataSourceBundle\Utils\WktUtility;
use Symfony\Component\Finder\Finder;

/**
 * Class FeatureType handles Feature objects.
 *
 * Main goal of the handler is, to get manage GeoJSON Features
 * for communication between OpenLayers and databases
 * with spatial abilities like Oracle or PostgreSQL.
 *
 *
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 * @copyright 2015 by WhereGroup GmbH & Co. KG
 * @link      https://github.com/mapbender/mapbender-digitizer
 *
 * @method Feature save(Feature|array $feature, bool $autoUpdate = true)
 * @method Feature update($itemOrData)
 * @method Feature insert($itemOrData)
 * @method Feature get($args)
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


    /**
     * @var bool Allow update feature flag
     */
    protected $allowUpdate;

    /** @var array|null */
    protected $exportFields;

    /** @var array|null */
    private $toArrayData;


    protected function configure(array $args)
    {
        $this->toArrayData = array(
            'type' => 'doctrine',
            'connection' => $args['connection'],
        );

        if (array_key_exists('geomField', $args)) {
            $this->setGeomField($args['geomField']);
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

    /**
     * @param array $args
     * @return Drivers\DoctrineBaseDriver
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function driverFactory(array $args)
    {
        $driver = parent::driverFactory($args);
        if (!$driver instanceof Geographic) {
            // Reset and ignore srid from featureType configuration if driver can auto-detect field CRS
            $driverSrid = $driver->findGeometryFieldSrid($this->getTableName(), $this->getGeomField());
            if ($driverSrid) {
                $this->srid = $driverSrid;
            }
        }
        return $driver;
    }

    protected function initializeFields(DoctrineBaseDriver $driver, $args)
    {
        $fields = parent::initializeFields($driver, $args);
        // filter geometry field from select fields, unless explicitly configured
        $geomField = $this->getGeomField();
        if (empty($args['fields']) || !in_array($geomField, $args['fields'])) {
            $fields = array_diff($fields, array($geomField));
        }
        return $fields;
    }

    /**
     * @param string $geomField
     */
    public function setGeomField($geomField)
    {
        $this->geomField = $geomField;
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
        $qb = $this->getSelectQueryBuilder();
        if ($srid) {
            $qb->setTargetSrid($srid);
        }
        $qb
            ->setMaxResults(1)
            ->where($this->getUniqueId() . " = :id")
            ->setParameter('id', $id)
        ;
        $features = $this->prepareResults($qb);
        if ($features) {
            return $features[0];
        } else {
            return null;
        }
    }

    /**
     * @param DataItem $feature
     * @param Feature|array|mixed $dataArg original value passed to save method
     * @return array
     */
    protected function getSaveEventData(DataItem $feature, &$dataArg)
    {
        // legacy quirk originData:
        // 1) for inserts (no id), provide a blank, empty, Feature object (like ->get(array()))
        // 2) for updates, reload the original item (incoming feature already carries new data!)
        if ($feature->getId()) {
            /** @var Feature $feature */
            $originData = $this->reloadItem($feature);
        } else {
            $originData = $this->itemFactory();
        }
        /** @var Feature $feature */
        return array(
            'item' => &$dataArg,
            'feature' => $feature,
            'originData' => $originData,
        );
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
     * Extracts persistable values (insert / update) from Feature
     * Implicitly transforms the geometry to a compatible CRS.
     *
     * @param DataItem $feature
     * @return mixed[]
     */
    protected function getSaveData(DataItem $feature)
    {
        /** @var Feature $feature */
        $data = $feature->toArray();
        $ewkt = $feature->getEwkt();
        $geomField = $this->getGeomField();
        if ($ewkt) {
            $tableSrid = $this->getSrid();
            // HACK: replace invalid client-supplied geometries with empty dummy WKTs
            // @see https://repo.wheregroup.com/bev/tickets---extern/issues/36 (internal)
            if (strpos(strtoupper($ewkt), 'NAN') !== false) {
                @trigger_error("WARNING: replacing invalid geometry with empty point. This will be an error in a future version (supplied EWKT: {$ewkt})", E_USER_DEPRECATED);
                $ewkt = "SRID={$tableSrid};POINT EMPTY";
            }
            $driver = $this->getDriver();
            $connection = $driver->getConnection();
            $geomSql = $driver->getTransformSql($driver->getReadEwktSql($connection->quote($ewkt)), $tableSrid);
            if ($this->checkPromoteToCollection($ewkt)) {
                $geomSql = $driver->getPromoteToCollectionSql($geomSql);
            }
            $data[$geomField] = new Expression($geomSql);
        } else {
            $data[$geomField] = null;
        }
        return $data;
    }

    /**
     * @param string $ewkt
     * @return boolean
     */
    protected function checkPromoteToCollection($ewkt)
    {
        $type = $this->getDriver()->getTableGeomType($this->getTableName());
        $wktType = WktUtility::getGeometryType($ewkt);

        // @todo: document why we would want to promote to collection, and why we only have a Postgis implementation
        return $type && $wktType != $type
            && in_array(strtoupper($wktType), Feature::$simpleGeometries)
            && in_array(strtoupper($type), Feature::$complexGeometries)
        ;
    }

    /**
     * Insert feature
     *
     * @param DataItem $feature
     * @return Feature
     * @throws \Exception
     */
    public function insertItem(DataItem $feature)
    {
        /** @var Feature $feature */
        $feature = $this->insertItemInternal($feature, self::EVENT_ON_BEFORE_INSERT, self::EVENT_ON_AFTER_INSERT);
        return $feature;
    }

    /**
     * @param DataItem $feature
     * @return Feature
     * @throws \Doctrine\DBAL\DBALException
     */
    public function updateItem(DataItem $feature)
    {
        /** @var Feature $feature */
        $data = $this->getSaveData($feature);

        $this->allowUpdate = true;
        if (isset($this->events[self::EVENT_ON_BEFORE_UPDATE]) || isset($this->events[self::EVENT_ON_AFTER_UPDATE])) {
            $eventData = $this->getSaveEventData($feature, $data);
        } else {
            $eventData = null;
        }

        if (isset($this->events[static::EVENT_ON_BEFORE_UPDATE])) {
            $this->secureEval($this->events[static::EVENT_ON_BEFORE_UPDATE], $eventData);
        }
        if ($this->allowUpdate) {
            $identifier = array(
                $this->getUniqueId() => $feature->getId(),
            );
            $this->getDriver()->update($this->getTableName(), $data, $identifier);
        }

        if (isset($this->events[self::EVENT_ON_AFTER_UPDATE])) {
            $this->secureEval($this->events[self::EVENT_ON_AFTER_UPDATE], $eventData);
        }
        return $feature;
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
        $queryBuilder = $this->getSelectQueryBuilder();
        if (!empty($criteria['srid'])) {
            $queryBuilder->setTargetSrid($criteria['srid']);
        }

        $this->addCustomSearchCritera($queryBuilder, $criteria);

        if (!empty($criteria['maxResults'])) {
            $queryBuilder->setMaxResults(intval($criteria['maxResults']));
        }

        $features = $this->prepareResults($queryBuilder);

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
        parent::addCustomSearchCritera($queryBuilder, $params);
        // add bounding geometry condition
        if (!empty($params['intersect'])) {
            $clipWkt = $params['intersect'];
            if (!empty($params['srid'])) {
                $clipSrid = $params['srid'];
            } else {
                $clipSrid = $this->getSrid();
            }
            $queryBuilder->andWhere($this->getDriver()->getIntersectCondition($clipWkt, $this->geomField, $clipSrid, $this->getSrid()));
        }
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
     * Convert results to Feature objects
     *
     * @param QueryBuilder $queryBuilder
     * @return Feature[]
     */
    protected function prepareResults(QueryBuilder $queryBuilder)
    {
        /** @var FeatureQueryBuilder $queryBuilder */
        $driver = $this->getDriver();

        $rows = $queryBuilder->execute()->fetchAll();
        if ($driver instanceof Oracle) {
            // @todo: this logic belongs in the driver, not here
            // @todo: this behaviour may cause more trouble than it solves. There should be an option
            //        to disable it.
            Oracle::transformColumnNames($rows);
        }
        $features = array();
        $srid = $queryBuilder->getTargetSrid();
        foreach ($rows as $row) {
            $feature = new Feature($row, $srid, $this->getUniqueId(), $this->getGeomField());
            $features[] = $feature;
        }
        return $features;
    }

    /**
     * Get query builder prepared to select from the source table
     *
     * @return FeatureQueryBuilder
     */
    public function getSelectQueryBuilder()
    {
        /** @var FeatureQueryBuilder $queryBuilder */
        $queryBuilder = parent::getSelectQueryBuilder();
        $queryBuilder->addGeomSelect($this->geomField);
        return $queryBuilder;
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
     * Create empty item
     *
     * @return Feature
     * @since 0.1.16.2
     */
    public function itemFactory()
    {
        return new Feature(array(), $this->getSrid(), $this->getUniqueId(), $this->getGeomField());
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
        /**
         * NOTE: we deliberatly AVOID using itemFactory here because of the absolutely irritating matrix
         * of potential constructor initializer types
         * @see Feature::__construct
         * @sse DataItem::__construct
         */
        return new Feature($values, $this->getSrid(), $this->getUniqueId(), $this->getGeomField());
    }

    /**
     * Get SRID
     *
     * @return int
     */
    public function getSrid()
    {
        $driver = $this->getDriver();
        if (!$this->srid && ($driver instanceof Geographic)) {
            /** @var PostgreSQL|Geographic $driver */
            $this->srid = $driver->findGeometryFieldSrid($this->getTableName(), $this->geomField);
        }
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
     * @deprecated remove in 0.2.0; this isn't a schema manager utility, find a DBA
     */
    public function addGeometryColumn($tableName,
        $type,
        $srid,
        $geomFieldName = "geom",
        $schemaName = "public",
        $dimensions = 2)
    {
        $driver = $this->getDriver();
        if ($driver instanceof Geographic) {
            $driver->addGeometryColumn($tableName, $type, $srid, $geomFieldName, $schemaName, $dimensions);
            return true;
        }
        return false;
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
     * Get sequence name
     *
     * @return string sequence name
     * @throws \Doctrine\DBAL\DBALException
     * @todo 0.2.0: remove this method (DBA work)
     * @deprecated
     */
    public function getTableSequenceName()
    {
        $connection = $this->getConnection();
        $result     = $connection->fetchColumn("SELECT column_default from information_schema.columns where table_name='" . $this->getTableName() . "' and column_name='" . $this->getUniqueId() . "'");
        $result     = explode("'", $result);
        return $result[0];
    }

    /**
     * Repair table sequence.
     * Set sequence next ID to (highest ID + 1) in the table
     *
     * @return int last insert ID
     * @throws \Doctrine\DBAL\DBALException
     * @todo 0.2.0: remove this method (DBA work)
     * @deprecated
     */
    public function repairTableSequence()
    {
        return $this->getConnection()->fetchColumn("SELECT setval('" . $this->getTableSequenceName() . "', (SELECT MAX(" . $this->getUniqueId() . ") FROM " . $this->getTableName() . "))");
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
     * @param string $tableName
     * @return mixed|null
     */
    public function getGeomType($tableName)
    {
        $driver = $this->getDriver();
        if ($driver instanceof Geographic) {
            return $driver->getTableGeomType($tableName);
        } else {
            return null;
        }
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
            $feature = new Feature(array(), $srid, $this->getUniqueId(), $this->getGeomField());
            $feature->setAttributes($row);
            $features[] = $feature;
        }
        return $features;
    }

    /**
     * @return array
     * @todo 0.2.0: remove this method
     * @deprecated
     */
    public function toArray()
    {
        $tableName = $this->getTableName();
        return $this->toArrayData + array(
            'table'       => $tableName,
            'geomType'    => $this->getGeomType($tableName),
            'fields'      => $this->fields,
            'geomField'   => $this->geomField,
            'srid'        => $this->getSrid(),
            'allowSave'   => $this->allowSave,
            'allowRemove' => $this->allowRemove,
        );
    }

    /**
     * Get by ID list
     *
     * @param mixed[] $ids
     * @param bool $prepareResults to return Feature objects instead of associative row arrays
     * @return array[]|Feature[]
     * @todo: methods should not have parametric return types
     */
    public function getByIds($ids, $prepareResults = true)
    {
        $queryBuilder = $this->getSelectQueryBuilder();
        $connection   = $queryBuilder->getConnection();
        $condition = $queryBuilder->expr()->in($this->getUniqueId(), array_map(array($connection, 'quote'), $ids));
        $queryBuilder->where($condition);

        if ($prepareResults) {
            return $this->prepareResults($queryBuilder);
        } else {
            return $queryBuilder->execute()->fetchAll();
        }
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
}
