<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BaseDriver
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
abstract class BaseDriver extends ContainerAware
{
    /**
     * @var array Driver settings
     */
    protected $settings;

    /**
     * @var array Field to select from the table
     */
    protected $fields = array();

    /**
     * @var mixed Unique id field name
     */
    protected $uniqueId = 'id';

    /**
     * @var mixed
     */
    protected $connection;

    /**
     * BaseDriver constructor.
     *
     * @param ContainerInterface $container
     * @param array              $args
     */
    public function __construct(ContainerInterface $container, array $args = array())
    {
        $this->setContainer($container);

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
        $this->settings = $args;
    }

    /**
     * @param int $uniqueId
     */
    public function setUniqueId($uniqueId)
    {
        $this->uniqueId = $uniqueId;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array $fields
     * @return array
     */
    public function setFields(array $fields)
    {
        return $this->fields = $fields;
    }

    /**
     * Get fields defined by store
     *
     * @return array
     */
    public function getStoreFields()
    {
        return $this->getFields();
    }

    /**
     * Get unique ID
     *
     * @return mixed unique ID
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

}