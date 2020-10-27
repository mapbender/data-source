<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Component\DataStore;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Base;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
abstract class BaseDriver implements Base
{
    /**
     * Only used for inflecting misplaced methods
     * @todo 0.2.0: remove attribute and all usages
     * @var DataStore
     */
    protected $repository;

    /**
     * @var array Field to select from the table
     */
    protected $fields = array();

    /**
     * @var Connection|mixed
     */
    protected $connection;

    /**
     * @param array $args
     * @param DataStore $repository
     *
     * @todo 0.2.0: remove repository binding and all methods requiring repository inflection
     */
    public function __construct(array $args, DataStore $repository)
    {
        $this->repository = $repository;
        if (!empty($args['fields'])) {
            $this->setFields($args['fields']);
        }
    }

    /**
     * @return array
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array $fields
     * @return array
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function setFields(array $fields)
    {
        return $this->fields = $fields;
    }

    /**
     * Get unique ID field name
     *
     * @return string
     * @deprecated
     * @internal
     * @todo: 0.2.0 remove this method
     */
    public function getUniqueId()
    {
        return $this->repository->getUniqueId();
    }

    /**
     * Cast DataItem by $args
     *
     * @param mixed $args
     * @return DataItem
     * @deprecated
     * @todo 0.2.0: remove method
     */
    public function create($args)
    {
        return $this->repository->create($args);
    }

    /**
     * Get connection link
     *
     * @return Connection|mixed
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Detect (E)WKT geometry type
     *
     * @param string $wkt
     * @return string
     * @todo 0.2.0 move to utility collection
     */
    public static function getWktType($wkt)
    {
        $isEwkt = strpos($wkt, 'SRID') === 0;
        if ($isEwkt) {
            $wkt = substr($wkt, strpos($wkt, ';') + 1);
        }
        return substr($wkt, 0, strpos($wkt, '('));
    }
}
