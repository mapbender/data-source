<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Base;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class YAML
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class YAML extends BaseDriver implements Base
{

    /**
     * @param int|string $id
     * @param string     $fieldName
     * @return DataItem
     */
    public function get($id, $fieldName = "")
    {
        // TODO: Implement get() method.
    }

    /**
     * Save the data
     *
     * @param array|DataItem $data
     * @param bool           $autoUpdate Create if not exists or update if ID exists
     * @return mixed
     */
    public function save($data, $autoUpdate = true)
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
        return true;
    }

    /**
     * Has permission to write?
     *
     * @return bool
     */
    public function canWrite()
    {
        return false;
    }

    /**
     * Cast DataItem by $args
     *
     * @param mixed $args
     * @return DataItem
     */
    public function create($args)
    {
        // TODO: Implement create() method.
    }

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatformName()
    {
        return "yaml";
    }

    /**
     * @param array $criteria
     * @param bool  $autoSave
     * @return mixed
     */
    public function search(array $criteria, $autoSave = true)
    {
        // TODO: Implement search() method.
    }

    /**
     * Get object by ID and field name
     *
     * @param mixed  $id        ID
     * @param string $fieldName Field name
     */
    public function getById($id)
    {
        // TODO: Implement getById() method.
    }
}