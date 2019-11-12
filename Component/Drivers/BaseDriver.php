<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class BaseDriver
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
abstract class BaseDriver
{
    /**
     * @var array Field to select from the table
     */
    protected $fields = array();

    /**
     * @var mixed Unique id field name
     */
    protected $uniqueId = 'id';

    /**
     * @var Connection|mixed
     */
    protected $connection;

    /**
     * BaseDriver constructor.
     *
     * @param array              $args
     */
    public function __construct(array $args = array())
    {
        // init $methods by $args
        if (is_array($args)) {
            $methods = get_class_methods(get_class($this));
            foreach ($args as $key => $value) {
                $keyMethod = "set" . ucwords($key);
                if (in_array($keyMethod, $methods)) {
                    $this->$keyMethod($value);
                }
            }
        }
    }

    /**
     * @param int $uniqueId
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function setUniqueId($uniqueId)
    {
        $this->uniqueId = $uniqueId;
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
     * Get fields defined by store
     *
     * @return array
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function getStoreFields()
    {
        return $this->getFields();
    }

    /**
     * Get unique ID field name
     *
     * @return mixed unique ID
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function getUniqueId()
    {
        return $this->uniqueId;
    }

    /**
     * Cast DataItem by $args
     *
     * @param mixed $args
     * @return DataItem
     */
    public function create($args)
    {
        $data = null;
        if (is_object($args)) {
            if ($args instanceof DataItem) {
                $data = $args;
            } else {
                $args = get_object_vars($args);
            }
        } elseif (is_numeric($args)) {
            $args = array($this->getUniqueId() => intval($args));
        }
        return $data ? $data : new DataItem($args, $this->getUniqueId());
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
     * @param $wkt
     * @return string
     */
    public static function getWktType($wkt)
    {
        $isEwkt = strpos($wkt, 'SRID') === 0;
        if ($isEwkt) {
            $wkt = substr($wkt, strpos($wkt, ';') + 1);
        }
        return substr($wkt, 0, strpos($wkt, '('));
    }

    /**
     *
     * Round geometry up to $round parameter.
     *
     * Default: geometry round = 0.2
     *
     * @param string $geometry WKT
     * @param int    $round    Default=2
     * @return string WKT
     */
    public static function roundGeometry($geometry, $round = 2)
    {
        return preg_replace("/(\\d+)\\.(\\d{$round})\\d+/", '$1.$2', $geometry);
    }
}
