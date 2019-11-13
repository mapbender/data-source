<?php
namespace Mapbender\DataSourceBundle\Entity;

/**
 * @package Mapbender\DataSourceBundle\Entity
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 * @deprecated this is nothing more than an array with defaults, so just use an array and defaults
 * @todo: remove. Removal will break
 *     query-builder < 1.0.4
 *     data-manager <= a726e7668f33fb1b79aaaa7161b3f6603d663153 (unresolved)
 *
 */
class BaseConfiguration
{
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
     * @param array $args
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
}
