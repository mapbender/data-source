<?php
namespace Mapbender\DataSourceBundle\Component;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use Mapbender\CoreBundle\Component\Application as AppComponent;
use Mapbender\DataSourceBundle\Component\Drivers\BaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Component\Drivers\Oracle;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Mapbender\DataSourceBundle\Entity\Feature;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class FeatureType handles Feature objects.
 *
 * Main goal of the handler is, to get manage GeoJSON Features
 * for communication between OpenLayers and databases
 * with spatial abilities like Oracle or PostgreSQL.
 *
 *
 * @package   Mapbender\CoreBundle\Entity
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

    /**
     * @var array Initial arguments
     */
    protected $_args = array();

    /**
     * @param ContainerInterface $container
     * @param null               $args
     */
    public function __construct(ContainerInterface $container, $args = null)
    {
        $hasFields = isset($args["fields"]) && is_array($args["fields"]);

        parent::__construct($container, $args);

        // if no fields defined, but geomField, find it all and remove geo field from the list
        if (!$hasFields && isset($args["geomField"])) {
            $fields = $this->getDriver()->getStoreFields();
            unset($fields[ array_search($args["geomField"], $fields, false) ]);
            $this->setFields($fields);
        }

        $this->_args = $args;
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
     * @param array $fields
     * @return array
     */
    public function setFields(array $fields)
    {
        return $this->driver->setFields($fields);
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
     * @param string $ewkt EWKT geometry
     * @param null|int $srid SRID
     * @return bool|string
     * @throws \Exception
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
     * Update
     *
     * @param $featureData
     * @return Feature
     * @throws \Exception
     * @internal param array $criteria
     */
    public function update($featureData)
    {
        /** @var Feature $feature */
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
        /** @var Statement $statement */
        /** @var Feature $feature */
        $maxResults      = isset($criteria['maxResults']) ? intval($criteria['maxResults']) : self::MAX_RESULTS;
        $intersect       = isset($criteria['intersectGeometry']) ? $criteria['intersectGeometry'] : null;
        $returnType      = isset($criteria['returnType']) ? $criteria['returnType'] : null;
        $srid            = isset($criteria['srid']) ? $criteria['srid'] : $this->getSrid();
        $where           = isset($criteria['where']) ? $criteria['where'] : null;
        $queryBuilder    = $this->getSelectQueryBuilder($srid);
        $connection      = $queryBuilder->getConnection();
        $whereConditions = array();

        // add GEOM where condition
        if ($intersect) {
            $geometry          = BaseDriver::roundGeometry($intersect, 2);
            $whereConditions[] = $this->getDriver()->getIntersectCondition($geometry, $this->geomField, $srid, $this->getSrid());
        }

        // add filter (https://trac.wheregroup.com/cp/issues/3733)
        if (!empty($this->sqlFilter)) {
            /** @var TokenStorageInterface $tokenStorage */
            $tokenStorage = $this->container->get("security.token_storage");
            $token = $tokenStorage->getToken();
            if ($token instanceof AnonymousToken) {
                $userId = null;
            } else {
                $user = $token->getUser();
                if ($user && is_object($user) && method_exists($user, 'getId')) {
                    $userId = $user->getId();
                } else {
                    $userId = null;
                }
            }
            $userName = $token->getUsername();
            $sqlFilter         = strtr($this->sqlFilter, array(
                ':userName' => $userId,
            ));
            $whereConditions[] = $sqlFilter;

        }

        // add second filter (https://trac.wheregroup.com/cp/issues/4643)
        if ($where) {
            $whereConditions[] = $where;
        }

        if (isset($criteria["source"]) && isset($criteria["distance"])) {
            $whereConditions[] = "ST_DWithin(t." . $this->getGeomField() . ","
                . $connection->quote($criteria["source"])
                . "," . $criteria['distance'] . ')';
        }


        if (count($whereConditions)) {
            $queryBuilder->where(join(" AND ", $whereConditions));
        }

        $queryBuilder->setMaxResults($maxResults);

        // $queryBuilder->setParameters($params);
        // $sql = $queryBuilder->getSQL();

        $statement  = $queryBuilder->execute();
        $rows       = $statement->fetchAll();
        $hasResults = count($rows) > 0;

        // Convert to Feature object
        if ($hasResults) {
            $this->prepareResults($rows, $srid);
        }

        if ($returnType == "FeatureCollection") {
            $rows = $this->toFeatureCollection($rows);
        }

        return $rows;
    }

    /**
     * Get unique ID
     *
     * @return mixed unique ID
     */
    public function getUniqueId()
    {
        return $this->driver->getUniqueId();
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->driver->getTableName();
    }

    /**
     * @return string
     */
    public function getGeomField()
    {
        return $this->geomField;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->getDriver()->getFields();
    }

    /**
     * Convert results to Feature objects
     *
     * @param Feature[] $rows
     * @param null      $srid
     * @return Feature[]
     */
    public function prepareResults(&$rows, $srid = null)
    {
        $hasSrid = $srid != null;

        if ($this->driver instanceof Oracle) {
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
        $driver             = $this->driver;
        $geomFieldCondition = $driver->getGeomAttributeAsWkt($this->geomField, $srid ? $srid : $this->getSrid());
        $queryBuilder       = $driver->getSelectQueryBuilder(array($geomFieldCondition));
        return $queryBuilder;
    }

    /**
     * Cast feature by $args
     *
     * @param $args
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
        $driver = $this->driver;
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
        /** @var Feature $feature */
        foreach ($rows as $k => $feature) {
            $rows[ $k ] = $feature->toGeoJson(true);
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
     * @param $sqlFilter
     */
    protected function setFilter($sqlFilter)
    {
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
        $path = realpath(AppComponent::getUploadsDir($this->container)) . "/" . $this->getFileUri($fieldName);

        foreach ($this->getFileInfo() as $fileInfo) {
            if (isset($fileInfo["field"]) && isset($fileInfo["path"]) && $fieldName == $fileInfo["field"]) {
                $path = $fileInfo["path"];
                break;
            }
        }

        if ($createPath && !is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    /**
     * @param string $fieldName
     * @return string
     */
    public function getFileUrl($fieldName = "")
    {
        $baseUrl   = AppComponent::getBaseUrl($this->container);
        $uploadDir = AppComponent::getUploadsDir($this->container, true) . "/";

        foreach ($this->getFileInfo() as $fileInfo) {
            if (isset($fileInfo["field"]) && isset($fileInfo["uri"]) && $fieldName == $fileInfo["field"]) {
                $uploadDir = "";
                break;
            }
        }

        $fileUri = $this->getFileUri($fieldName);
        $url     = strpos($fileUri, "/") === 0 ? $fileUri : $baseUrl . '/' . $uploadDir . $fileUri;
        return $url;
    }

    /**
     * Generate unique file name for a field.
     *
     * @param null $fieldName Field
     * @return string[]
     * @internal param string $extension File extension
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
     * @param $fileInfo
     * @internal param $fileInfos
     */
    public function setFiles($fileInfo)
    {
        $this->filesInfo = $fileInfo;
    }

    /**
     * @return array
     */
    public function getFileInfo()
    {
        return $this->filesInfo;
    }

    /**
     * @param        $tableName
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
     * @param $wkt
     * @return string
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
     * @param string $key Key name
     * @return array|mixed|null
     */
    public function getConfiguration($key = null)
    {
        return isset($this->_args[ $key ]) ? $this->_args[ $key ] : null;
    }

    /**
     * Export by ID's
     *
     * @param array $rows
     * @return array
     * @internal param array $features
     * @internal param array $ids
     */
    public function export(array &$rows)
    {
        $config     = $this->getConfiguration('export');
        $fieldNames = isset($config['fields']) ? $config['fields'] : null;
        $result     = array();

        if ($fieldNames) {
            foreach ($rows as &$row) {
                $exportRow = array();
                foreach ($fieldNames as $fieldName => $fieldCode) {
                    $exportRow[ $fieldName ] = $this->evaluateField($row, $fieldCode);
                }
                $result[] = $exportRow;
            }
        } else {
            $result = &$rows;
        }

        return $result;
    }

    /**
     * @param $row
     * @param $code
     * @return null
     */
    private function evaluateField(&$row, $code)
    {
        $result = null;
        extract($row);
        eval('$result = ' . $code . ';');
        return $result;
    }
}
