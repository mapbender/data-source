<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;


use Mapbender\DataSourceBundle\Component\Driver\IDriver;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class YAML
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class YAML implements IDriver{

    /**
     * @param $id
     * @return DataItem
     */
    public function get($id)
    {
        // TODO: Implement get() method.
    }

    /**
     * Save the data
     *
     * @param $data
     * @return mixed
     */
    public function save(DataItem $data)
    {
        // TODO: Implement save() method.
    }

    /**
     * Remove by ID
     *
     * @param $id
     * @return mixed
     */
    public function remove($id)
    {
        // TODO: Implement remove() method.
    }

    /**
     * Connect to the source
     *
     * @param $url
     * @return mixed
     */
    public function connect($url)
    {
        // TODO: Implement connect() method.
    }

    /**
     * Is the driver connected an ready to interact?
     *
     * @return bool
     */
    public function isReady()
    {
        // TODO: Implement isReady() method.
    }

    /**
     * Has permission to read?
     *
     * @return bool
     */
    public function canRead()
    {
        // TODO: Implement canRead() method.
    }

    /**
     * Has permission to write?
     *
     * @return bool
     */
    public function canWrite()
    {
        // TODO: Implement canWrite() method.
    }
}