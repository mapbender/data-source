<?php
namespace Mapbender\DataSourceBundle\Entity;

/**
 * Class BaseConfiguration
 *
 * @package Mapbender\DataSourceBundle\Entity
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class BaseConfiguration
{

    /* @var id*/

    protected $id;



    /**
     * Constructor
     *
     * @param array $args Arguments
     */
    public function __construct(array $args = null)
    {
        if ($args) {
            $this->fill($args);
        }
    }

    /**
     * Fill object with values from $args
     *
     * @param $args
     */
    public function fill($args)
    {
        foreach (get_object_vars($this) as $key => $value) {
            foreach ($args as $argKey => $argValue) {
                if ($key == $argKey || $key == $argKey . "Name") {
                    $this->$key = $argValue;
                }
            }
        }
    }

    /**
     * Export
     */
    public function toArray()
    {
        return get_object_vars($this);
    }

    /**
     * @param mixed $id
     * @return BaseConfiguration
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}