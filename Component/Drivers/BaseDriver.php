<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

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
     */
    public function setFields(array $fields)
    {
        $this->fields = $fields;
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
}