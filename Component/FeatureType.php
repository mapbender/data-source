<?php
namespace Mapbender\DataSourceBundle\Component;

use Doctrine\DBAL\Query\QueryBuilder;
use Mapbender\DataSourceBundle\Component\Drivers\BaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Component\Drivers\Oracle;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Mapbender\DataSourceBundle\Entity\Feature;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
 */
class FeatureType extends DataStore
{

    /**
     * Default upload directory
     */
    const UPLOAD_DIR_NAME = "featureTypes";

    /**
     * Events
     */
    const EVENT_ON_BEFORE_UPDATE = 'onBeforeUpdate';
    const EVENT_ON_AFTER_UPDATE  = 'onAfterUpdate';
    const EVENT_ON_BEFORE_INSERT = 'onBeforeInsert';
    const EVENT_ON_AFTER_INSERT  = 'onAfterInsert';

    /**
     *  Default max results by search
     */
    const MAX_RESULTS = 5000;

    /**
     * @var string Geometry field name
     */
    protected $geomField = 'geom';

    /**
     * @var int SRID to get geometry converted to
     */
    protected $srid = null;

    /**
     * @var string SQL where filter
     */
    protected $sqlFilter;

    /**
     * @var array file info list
     */
    protected $filesInfo = array();


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
     * @var bool Allow insert feature flag
     */
    protected $allowInsert;

    /**
     * @var bool Allow update feature flag
     */
    protected $allowUpdate;

    /** @var array|null */
    protected $exportFields;

    protected function configure(array $args)
    {
        parent::configure($args);
        if (!empty($args['export'])) {
            if (!is_array($args['export'])) {
                throw new \InvalidArgumentException("Unexpected type " . gettype($args['export']) . " for 'export'. Expected array.");
            }
            if (!empty($args['export']['fields'])) {
                $this->exportFields = $args['export']['fields'];
            }
        }
    }

    /**
     * @param array $args
     * @return Drivers\DoctrineBaseDriver|Drivers\Interfaces\Base
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function driverFactory(array $args)
    {
        $driver = parent::driverFactory($args);
        // filter geometry field from select fields, unless explicitly configured
        $geomField = $this->geomField;
        if (empty($args['fields']) || !in_array($geomField, $args['fields'])) {
            $filteredFields = array_filter($driver->getFields(), function($fieldName) use ($geomField) {
                return $fieldName != $geomField;
            });
            $driver->setFields($filteredFields);
        }
        return $driver;
    }

    /**
     * @param string $geomField
     */
    public function setGeomField($geomField)
    {
        $this->geomField = $geomField;
    }

    /**
     * @param int $srid
     */
    public function setSrid($srid)
    {
        $this->srid = $srid;
    }

    /**
     * Get feature by ID and SRID
     *
     * @param int $id
     * @param int $srid SRID
     * @return Feature
     */
    public function getById($id, $srid = null)
    {
        $rows = $this->getSelectQueryBuilder($srid)
            ->where($this->getUniqueId() . " = :id")
            ->setParameter('id', $id)
            ->execute()
            ->fetchAll();
        $this->prepareResults($rows, $srid);
        return reset($rows);
    }

    /**
     * Save feature
     *
     * @param array|Feature|DataItem $featureData
     * @param bool                   $autoUpdate update instead of insert if ID given
     * @return DataItem|Feature
     * @throws \Exception
     */
    public function save($featureData, $autoUpdate = true)
    {
        if (!is_array($featureData) && !is_object($featureData)) {
            throw new \Exception("Feature data given isn't compatible to save into the table: " . $this->getTableName());
        }

        $feature = $this->create($featureData);
        $event   = array(
            'item'    => &$featureData,
            'feature' => $feature
        );

        $this->allowSave = true;

        if (isset($this->events[ static::EVENT_ON_BEFORE_SAVE ])) {
            $this->secureEval($this->events[ static::EVENT_ON_BEFORE_SAVE ], $event);
        }

        if ($this->allowSave) {
            // Insert if no ID given
            if (!$autoUpdate || !$feature->hasId()) {
                $feature = $this->insert($feature);
            } // Replace if has ID
            else {
                $feature = $this->update($feature);
            }
        }

        if (isset($this->events[ static::EVENT_ON_AFTER_SAVE ])) {
            $this->secureEval($this->events[ static::EVENT_ON_AFTER_SAVE ], $event);
        }

        // Get complete feature data
        $result = $this->getById($feature->getId(), $feature->getSrid());

        return $result;
    }

    /**
     * Insert feature
     *
     * @param array|Feature $featureData
     * @return Feature
     */
    public function insert($featureData)
    {
        $feature                       = $this->create($featureData);
        $data                          = $this->cleanFeatureData($feature->toArray());
        $driver                        = $this->getDriver();
        $lastId                        = null;
        $data[ $this->getGeomField() ] = $this->transformEwkt($data[ $this->getGeomField() ], $this->getSrid());
        $event                         = array(
            'item'    => &$data,
            'feature' => $feature
        );
        $this->allowInsert             = true;

        if (isset($this->events[ self::EVENT_ON_BEFORE_INSERT ])) {
            $this->secureEval($this->events[ self::EVENT_ON_BEFORE_INSERT ], $event);
        }

        if ($this->allowInsert) {
            $lastId = $driver->insert($data, false)->getId();
        }

        $feature->setId($lastId);

        if (isset($this->events[ self::EVENT_ON_AFTER_INSERT ])) {
            $this->secureEval($this->events[ self::EVENT_ON_AFTER_INSERT ], $event);
        }
        return $feature;
    }


    /**
     * Returns transformed geometry in NATIVE FORMAT (WKB or resource).
     *
     * @param string $ewkt EWKT geometry
     * @param null|int $srid SRID
     * @return bool|string
     * @throws \Exception
     * @todo: if an ewkt goes in, an ewkt should come out; native format is pretty useless outside of insert / update usage
     */
    public function transformEwkt($ewkt, $srid = null)
    {
        /** @var Geographic|BaseDriver $driver */
        $srid   = $srid ? $srid : $this->getSrid();
        $driver = $this->getDriver();

        if (!($driver instanceof Geographic)) {
            throw new \Exception('Driver isn\'t ablet to transform ewkt');
        }

        return $driver->transformEwkt($ewkt, $srid);
    }

    /**
     * @param mixed $featureData
     * @return Feature
     * @throws \Exception
     */
    public function update($featureData)
    {
        $feature                       = $this->create($featureData);
        $data                          = $this->cleanFeatureData($feature->toArray());
        $connection                    = $this->getConnection();
        $data[ $this->getGeomField() ] = $this->transformEwkt($data[ $this->getGeomField() ]);
        unset($data[ $this->getUniqueId() ]);

        $event             = array(
            'item'    => &$data,
            'feature' => $feature
        );
        $this->allowUpdate = true;

        if (isset($this->events[ static::EVENT_ON_BEFORE_UPDATE ])) {
            $this->secureEval($this->events[ static::EVENT_ON_BEFORE_UPDATE ], $event);
        }
        if (empty($data)) {
            throw new \Exception("Feature can't be updated without criteria");
        }

        $tableName = $this->getTableName();
        $quotedData = array();
        foreach($data as $key => $value){
            $quotedData[$connection->quoteIdentifier($key)] = $value;
        }



        if ($this->allowUpdate) {
            $connection->update($tableName, $quotedData, array($this->getUniqueId() => $feature->getId()));
        }

        if (isset($this->events[ self::EVENT_ON_AFTER_UPDATE ])) {
            $this->secureEval($this->events[ self::EVENT_ON_AFTER_UPDATE ], $event);
        }
        return $feature;
    }

    /**
     * Search feature by criteria
     *
     * @param array $criteria
     * @return Feature[]
     */
    public function search(array $criteria = array())
    {
        // @todo: support unlimited selects
        $maxResults      = isset($criteria['maxResults']) ? intval($criteria['maxResults']) : self::MAX_RESULTS;
        $returnType      = isset($criteria['returnType']) ? $criteria['returnType'] : null;
        $srid            = isset($criteria['srid']) ? $criteria['srid'] : $this->getSrid();
        $queryBuilder    = $this->getSelectQueryBuilder($srid);

        $this->addCustomSearchCritera($queryBuilder, $criteria);

        $queryBuilder->setMaxResults($maxResults);

        $statement  = $queryBuilder->execute();
        $rows = $statement->fetchAll();
        $rows = $this->prepareResults($rows, $srid);

        if ($returnType == "FeatureCollection") {
            $rows = $this->toFeatureCollection($rows);
        }

        return $rows;
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
        // add bounding geometry condition
        if (!empty($params['intersect'])) {
            $geometry = BaseDriver::roundGeometry($params['intersect'], 2);
            $queryBuilder->andWhere($this->getDriver()->getIntersectCondition($geometry, $this->geomField, $srid, $this->getSrid()));
        }
        // add filter (dead link https://trac.wheregroup.com/cp/issues/3733)
        // @todo: specify and document
        if (!empty($this->sqlFilter)) {
            if (preg_match('#:userName([^_\w\d]|$)#', $this->sqlFilter)) {
                /** @var TokenStorageInterface $tokenStorage */
                $tokenStorage = $this->container->get("security.token_storage");
                $queryBuilder->setParameter(':userName', $tokenStorage->getToken()->getUsername());
            }
            $queryBuilder->andWhere($this->sqlFilter);
        }
        // add second filter (dead link https://trac.wheregroup.com/cp/issues/4643)
        // @ŧodo: specify and document
        if (!empty($params['where'])) {
            $queryBuilder->andWhere($params['where']);
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
     * Get unique ID
     *
     * @return mixed unique ID
     * @todo: this information belongs HERE, not in the driver
     */
    public function getUniqueId()
    {
        return $this->getDriver()->getUniqueId();
    }

    /**
     * @return string
     * @todo: this information belongs here, not in the driver
     */
    public function getTableName()
    {
        return $this->getDriver()->getTableName();
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
     * @param Feature[] $rows
     * @param null      $srid
     * @return Feature[]
     * @todo: this logic belongs in the driver, not here
     * @todo: resolve reference abuse, callers should use the return value
     */
    public function prepareResults(&$rows, $srid = null)
    {
        $driver = $this->getDriver();
        $hasSrid = $srid != null;

        if ($driver instanceof Oracle) {
            Oracle::transformColumnNames($rows);
        }

        foreach ($rows as $key => &$row) {
            $row = $this->create($row);
            if ($hasSrid) {
                $row->setSrid($srid);
            }
        }

        return $rows;
    }

    /**
     * Get query builder prepared to select from the source table
     *
     * @param null $srid
     * @return QueryBuilder
     */
    public function getSelectQueryBuilder($srid = null)
    {
        $driver = $this->getDriver();
        $geomFieldCondition = $driver->getGeomAttributeAsWkt($this->geomField, $srid ? $srid : $this->getSrid());
        $queryBuilder = parent::getSelectQueryBuilder();
        $queryBuilder->addSelect($geomFieldCondition);
        return $queryBuilder;
    }

    /**
     * Cast feature by $args
     *
     * @param mixed $args
     * @return Feature
     */
    public function create($args)
    {
        $feature = null;
        if (is_object($args)) {
            if ($args instanceof Feature) {
                $feature = $args;
            } else {
                $args = get_object_vars($args);
            }
        } elseif (is_numeric($args)) {
            $args = array($this->getUniqueId() => intval($args));
        }
        return $feature && $feature instanceof Feature ? $feature : new Feature($args, $this->getSrid(), $this->getUniqueId(), $this->getGeomField());
    }

    /**
     * Get SRID
     *
     * @return int
     */
    public function getSrid()
    {
        $driver = $this->getDriver();
        if (!$this->srid  && $driver instanceof Geographic) {
            /** @var PostgreSQL|Geographic $driver */
            $this->srid = $driver->findGeometryFieldSrid($this->getTableName(), $this->geomField);
        }
        return $this->srid;
    }

    /**
     * Convert Features[] to FeatureCollection
     *
     * @param Feature[] $rows
     * @return array FeatureCollection
     */
    public function toFeatureCollection($rows)
    {
        foreach ($rows as $k => $feature) {
            $rows[$k] = $feature->toGeoJson();
        }
        return array("type"     => "FeatureCollection",
                     "features" => $rows);
    }

    /**
     * Clean data this can't be saved into db table from data array
     *
     * @param array $data
     * @return array
     */
    private function cleanFeatureData($data)
    {
        $fields = array_merge($this->getFields(), array($this->getUniqueId(), $this->getGeomField()));

        // clean data from feature
        foreach ($data as $fieldName => $value) {
            if (isset($fields[ $fieldName ])) {
                unset($data[ $fieldName ]);
            }
        }
        return $data;
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
     * Set FeatureType permanent SQL filter used by $this->search()
     * https://trac.wheregroup.com/cp/issues/3733
     *
     * @see $this->search()
     * @param string $sqlFilter
     */
    protected function setFilter($sqlFilter)
    {
        if ($sqlFilter) {
            // unquote quoted parameter references
            // we use parameter binding
            $filtered = preg_replace('#([\\\'"])(:[\w\d_]+)(\\1)#', '\\2', $sqlFilter);
            if ($filtered !== $sqlFilter) {
                @trigger_error("DEPRECATED: DO NOT quote parameter references in sql filter configuration", E_USER_DEPRECATED);
            }
            $sqlFilter = $filtered;
        }
        $this->sqlFilter = $sqlFilter;
    }

    /**
     * Get sequence name
     *
     * @return string sequence name
     * @throws \Doctrine\DBAL\DBALException
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
     */
    public function repairTableSequence()
    {
        return $this->getConnection()->fetchColumn("SELECT setval('" . $this->getTableSequenceName() . "', (SELECT MAX(" . $this->getUniqueId() . ") FROM " . $this->getTableName() . "))");
    }

    /**
     * Get files directory, relative to base upload directory
     *
     * @param null $fieldName
     * @return string
     */
    public function getFileUri($fieldName = null)
    {
        $path = self::UPLOAD_DIR_NAME . "/" . $this->getTableName();

        if ($fieldName) {
            $path .= "/" . $fieldName;
        }

        foreach ($this->getFileInfo() as $fileInfo) {
            if (isset($fileInfo["field"]) && isset($fileInfo["uri"]) && $fieldName == $fileInfo["field"]) {
                $path = $fileInfo["uri"];
                break;
            }
        }

        return $path;
    }

    /**
     * Get files base path
     *
     * @param null $fieldName  file field name
     * @param bool $createPath check and create path?
     * @return string
     */
    public function getFilePath($fieldName = null, $createPath = true)
    {
        foreach ($this->getFileInfo() as $fileInfo) {
            if (isset($fileInfo["field"]) && isset($fileInfo["path"]) && $fieldName == $fileInfo["field"]) {
                $path = $fileInfo["path"];
                if ($createPath && !is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                return $path;
            }
        }
        $fileUri = $this->getFileUri($fieldName);
        return $this->getUploadsManager()->getSubdirectoryPath($fileUri, $createPath);
    }

    /**
     * @param string $fieldName
     * @return string
     */
    public function getFileUrl($fieldName = "")
    {
        $fileUri = $this->getFileUri($fieldName);
        if ($this->filesystem->isAbsolutePath($fileUri)) {
            return $fileUri;
        } else {
            /** @var Request $request */
            $request = $this->container->get('request_stack')->getCurrentRequest();
            $baseUrl = implode('', array(
                $request->getSchemeAndHttpHost(),
                $request->getBasePath(),
            ));
            foreach ($this->getFileInfo() as $fileInfo) {
                if (isset($fileInfo["field"]) && isset($fileInfo["uri"]) && $fieldName == $fileInfo["field"]) {
                    return "{$baseUrl}/{$fileUri}";
                }
            }
            $uploadsDir = $this->getUploadsManager()->getWebRelativeBasePath(false);
            return "{$baseUrl}/{$uploadsDir}/{$fileUri}";
        }
    }

    /**
     * Generate unique file name for a field.
     *
     * @param null $fieldName Field
     * @return string[]
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
     */
    private function countFiles($fieldName = null)
    {
        $finder = new Finder();
        $finder->files()->in($this->getFilePath($fieldName));
        return count($finder);
    }

    /**
     * @param array[] $fileInfo
     * @internal param $fileInfos
     */
    public function setFiles($fileInfo)
    {
        $this->filesInfo = $fileInfo;
    }

    /**
     * @return array[]
     */
    public function getFileInfo()
    {
        return $this->filesInfo;
    }

    /**
     * @param string $tableName
     * @param string $schema
     * @return mixed|null
     */
    public function getGeomType($tableName, $schema = null)
    {
        $driver = $this->getDriver();
        $type   = null;
        if ($driver instanceof Geographic) {
            /** @var Geographic|PostgreSQL $driver */
            $type = $driver->getTableGeomType($tableName, $schema);
        }
        return $type;
    }

    /**
     * Detect (E)WKT geometry type
     *
     * @param string $wkt
     * @return string
     * @todo: remove in 0.2.0; only accessed by unit tests. Move implementation to Utility.
     */
    public static function getWktType($wkt)
    {
        return BaseDriver::getWktType($wkt);
    }


    /**
     * Get route nodes between geometries
     *
     * @param string $sourceGeom EWKT geometry
     * @param string $targetGeom EWKT geometry
     * @return Feature[]
     */
    public function routeBetweenGeom($sourceGeom, $targetGeom)
    {
        $driver     = $this->getDriver();
        $srid       = $this->getSrid();
        $sourceNode = $driver->getNodeFromGeom($this->waysVerticesTableName, $this->waysGeomFieldName, $sourceGeom, $srid, 'id');
        $targetNode = $driver->getNodeFromGeom($this->waysVerticesTableName, $this->waysGeomFieldName, $targetGeom, $srid, 'id');
        return $driver->routeBetweenNodes($this->waysVerticesTableName, $this->waysGeomFieldName, $sourceNode, $targetNode, $srid);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $tableName = $this->getTableName();
        return array(
            'type'        => $this->connectionType,
            'connection'  => $this->connectionName,
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
     * @param bool $prepareResults
     * @return array[][]
     */
    public function getByIds($ids, $prepareResults = true)
    {
        $queryBuilder = $this->getSelectQueryBuilder();
        $connection   = $queryBuilder->getConnection();
        $condition = $queryBuilder->expr()->in($this->getUniqueId(), array_map(array($connection, 'quote'), $ids));
        $rows = $queryBuilder->where($condition)->execute()->fetchAll();

        if ($prepareResults) {
            $this->prepareResults($rows);
        }

        return $rows;
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
        return $result;
    }

    /**
     * @return string|null
     * @internal
     * @todo: sqlFilter belongs in DataStore; current DataStore implementation delegates to driver
     *        Resolve driver delegation in DataStore::getSqlFilter, then push this method down,
     *        along with the setter and the attribute
     */
    protected function getSqlFilter()
    {
        return $this->sqlFilter;
    }
}
